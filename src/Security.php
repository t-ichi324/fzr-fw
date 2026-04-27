<?php

namespace Fzr;

/**
 * Security Utilities — core protection mechanisms and secure token generation.
 *
 * Use to enforce security policies like CSRF protection and IP-based access control.
 * Typical uses: generating secure tokens, verifying CSRF on POST requests, matching IP CIDRs.
 *
 * - Manages CSRF tokens via session storage with support for hidden field generation.
 * - Provides IPv4 CIDR matching for IP whitelisting (used by `#[IpWhitelist]`).
 * - Generates cryptographically secure random tokens for various purposes.
 * - Integrates with `Request` and `Session` for context-aware security checks.
 */
class Security
{
    /** CSRFトークン生成 */
    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token', $token);
        return $token;
    }

    /** CSRFトークン取得（なければ生成） */
    public static function getCsrfToken(): string
    {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        if (!Session::has($key)) {
            return self::generateCsrfToken();
        }
        return Session::get($key);
    }

    public static function verifyCsrf(): void
    {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $token = Request::input($key)
            ?: Request::header(defined('CSRF_HEADER_NAME') ? CSRF_HEADER_NAME : 'X-CSRF-TOKEN');

        $session_token = Session::get($key);

        if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
            Logger::warning("CSRF validation failed", [
                'ip' => Request::ipAddress(),
                'uri' => Request::uri()
            ]);
            throw HttpException::forbidden("CSRF token mismatch.");
        }
    }

    /** CSRFトークンHTML hidden input */
    public static function csrfField(): string
    {
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        return '<input type="hidden" name="' . h($key) . '" value="' . h(self::getCsrfToken()) . '">';
    }

    /** IP制限チェック */
    public static function checkIP(null|string|array $ips = null): void
    {
        // 引数がなければ Env から取得
        if (empty($ips)) {
            $ips = Env::get('ip.whitelist', '');
        }

        $list = self::resolveIpList($ips);
        if (empty($list)) {
            return;
        }

        $ip = Request::ipAddress();
        foreach ($list as $range) {
            if (self::ipMatch($ip, trim($range))) {
                return;
            }
        }

        Logger::warning("Access denied from IP: $ip", ['uri' => Request::uri()]);
        throw HttpException::forbidden("Access denied.");
    }

    /** IPソースの解決 (再帰的) */
    private static function resolveIpList(string|array $source): array
    {
        if (is_array($source)) {
            $all = [];
            foreach ($source as $item) {
                $all = array_merge($all, self::resolveIpList($item));
            }
            return $all;
        }

        if (empty($source)) {
            return [];
        }

        // Storage 判定 (Windows ドライブレター A:\ 等を除外)
        if (strpos($source, ':') !== false && !preg_match('/^[a-zA-Z]:[\\\\\/]/', $source)) {
            [$disk, $path] = explode(':', $source, 2);
            try {
                $content = Storage::disk($disk)->get($path);
                return array_filter(array_map('trim', explode("\n", $content)));
            } catch (\Throwable $e) {
                Logger::error("Failed to load IP list from storage: $source", ['error' => $e->getMessage()]);
                return [];
            }
        }

        // 物理ファイル判定
        if (file_exists($source)) {
            return array_filter(array_map('trim', file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
        }

        // CSVリテラル判定
        return array_filter(array_map('trim', explode(',', $source)));
    }

    /** IP/CIDR マッチング */
    private static function ipMatch(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        // CIDR 判定 (IPv4)
        list($subnet, $bits) = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) == ($subnetLong & $mask);
    }

    /** パスワードハッシュ */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /** パスワード検証 */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** ランダムトークン生成 */
    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
