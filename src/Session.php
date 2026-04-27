<?php

namespace Fzr;

/**
 * Session Management — handles request-scoped persistent data.
 *
 * Use to store data that needs to survive across multiple requests (e.g., flash messages, user preferences).
 * Typical uses: storing authentication keys, temporary state after redirects, user UI settings.
 *
 * - Automatically detects and switches between session drivers (File, Redis, or Encrypted Cookie).
 * - Cookie driver uses AES-256-GCM encryption for security in stateless environments.
 * - Provides "flash" messaging support (data that exists for only one subsequent request).
 * - Implements {@see Store} to provide a unified data-access interface.
 */
class Session extends Store
{
    private static bool $started = false;

    /** cookie driver のサイズ警告しきい値 (bytes) */
    public const COOKIE_SIZE_WARN = 3072;

    public static function start(?string $name = null): void
    {
        $name ??= Env::get('session.name', 'SID');
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        if (headers_sent() || PHP_SAPI === 'cli') return;

        $isCloud   = Context::isStateless();
        $redisConf = Context::getRedisConfig();
        $driver    = Env::get('session.driver');

        if (!$driver) {
            if ($isCloud && $redisConf) {
                $driver = 'redis';
            } elseif ($isCloud) {
                $driver = 'cookie';
            } else {
                $driver = 'file';
            }
        }

        if ($driver === 'redis' && $redisConf) {
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', "tcp://{$redisConf['host']}:{$redisConf['port']}");
        } elseif ($driver === 'cookie') {
            self::registerCookieHandler($name ?? session_name());
        } elseif ($driver === 'file') {
            $savePath = Env::get('session.save_path', Path::temp('sessions'));
            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }
            session_save_path($savePath);
        }

        $secure   = Env::getBool('session.secure', Env::getBool('app.force_https', false) || Request::isHttps());
        $httpOnly = Env::getBool('session.httponly', true);
        $sameSite = Env::get('session.samesite', 'Lax');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => Env::getInt('session.lifetime', 0),
            'path'     => '/',
            'domain'   => Env::get('session.domain', ''),
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
        session_start();
        self::$started = true;

        // Lift prior-request flash messages out of session storage into
        // request-scoped memory while the session is still writable.
        // See Message class docblock for the full lifecycle.
        Message::snapshot();
    }

    // ── Cookie セッションハンドラー ────────────────────────────────

    private static function registerCookieHandler(string $cookieName): void
    {
        $handler = new class($cookieName) implements \SessionHandlerInterface {
            public function __construct(private string $cookieName) {}

            public function open(string $path, string $name): bool
            {
                return true;
            }
            public function close(): bool
            {
                return true;
            }
            public function gc(int $max_lifetime): int|false
            {
                return 0;
            }

            public function read(string $id): string|false
            {
                $raw = $_COOKIE[$this->cookieName . '_data'] ?? '';
                if ($raw === '') return '';
                return Session::cookieDecrypt($raw) ?? '';
            }

            public function write(string $id, string $data): bool
            {
                if (strlen($data) > Session::COOKIE_SIZE_WARN) {
                    Logger::warning(
                        "Session (cookie driver): session data is " . strlen($data) . " bytes"
                            . " — approaching the 4KB cookie limit."
                            . " Consider switching to Redis (set REDIS_HOST) for larger session data.",
                        ['size' => strlen($data), 'limit' => 4096]
                    );
                }
                $encrypted = Session::cookieEncrypt($data);
                if ($encrypted === null) return false;

                $secure   = Env::getBool('session.secure', Env::getBool('app.force_https', false) || Request::isHttps());
                $httpOnly = Env::getBool('session.httponly', true);
                $sameSite = Env::get('session.samesite', 'Lax');
                $lifetime = Env::getInt('session.lifetime', 0);

                setcookie($this->cookieName . '_data', $encrypted, [
                    'expires'  => $lifetime > 0 ? time() + $lifetime : 0,
                    'path'     => '/',
                    'domain'   => Env::get('session.domain', ''),
                    'secure'   => $secure,
                    'httponly' => $httpOnly,
                    'samesite' => $sameSite,
                ]);
                return true;
            }

            public function destroy(string $id): bool
            {
                setcookie($this->cookieName . '_data', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'domain'   => Env::get('session.domain', ''),
                    'secure'   => Env::getBool('session.secure', Env::getBool('app.force_https', false) || Request::isHttps()),
                    'httponly' => Env::getBool('session.httponly', true),
                    'samesite' => Env::get('session.samesite', 'Lax'),
                ]);
                return true;
            }
        };

        session_set_save_handler($handler, true);
    }

    // ── 暗号化ユーティリティ (cookie driver 内部用) ───────────────

    /** @internal */
    public static function cookieEncrypt(string $data): ?string
    {
        $key = self::deriveKey();
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) return null;
        return base64_encode($iv . $tag . $ct);
    }

    /** @internal */
    public static function cookieDecrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) return null;  // 12 IV + 16 tag

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $key = self::deriveKey();

        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? null : $pt;
    }

    private static function deriveKey(): string
    {
        $appKey = Env::get('app.key', '');
        return hash('sha256', 'fzr-session|' . $appKey, true);
    }

    // ── 公開 API ─────────────────────────────────────────────────

    /**
     * Store用のデータソースを $_SESSION に紐付ける
     */
    protected static function &data(): mixed
    {
        self::start();
        return $_SESSION;
    }

    public static function remove(string ...$keys): void
    {
        self::start();
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }
}
