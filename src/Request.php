<?php

namespace Fzr;

/**
 * HTTP Request Abstraction — provides unified access to request data.
 *
 * Use to retrieve input parameters, headers, client IP, and file uploads.
 * Typical uses: validating user input, checking request methods, handling file uploads.
 *
 * - Merges GET, POST, and JSON payloads into a single searchable input bag.
 * - Resolves client IP address with support for trusted proxies and Cloudflare.
 * - Handles file uploads with simplified access via `file()`.
 * - Provides helper methods for common HTTP checks (isPost, isAjax, isJsonRequest).
 */
class Request
{
    private static $__cache = [];

    private static function requestPath(): string
    {
        $sn = self::server("SCRIPT_NAME");
        $basePath = rtrim(dirname($sn), '/');
        $requestUri = self::server("REQUEST_URI", '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
            $requestPath = substr($requestPath, strlen($basePath));
        }

        return trim($requestPath, '/');
    }

    /** パス要素分解取得 */
    public static function routeParts(): array
    {
        $path = self::requestPath();
        if ($path === '') return [];
        return explode('/', $path);
    }

    /** ルート名取得 */
    public static function route(): string
    {
        $path = self::requestPath();
        return $path === '' ? Config::DEFAULT_ROUTE : $path;
    }

    /** 相対URI取得 */
    public static function uri(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            $sn = self::server("SCRIPT_NAME");
            $basePath = rtrim(dirname($sn), '/\\');
            $requestUri = self::server("REQUEST_URI", '/');
            $path = $requestUri;
            if ($basePath !== '' && str_starts_with($requestUri, $basePath)) {
                $path = substr($requestUri, strlen($basePath));
            }
            self::$__cache[__FUNCTION__] = ($path === '' || $path[0] !== '/') ? '/' . ltrim($path, '/') : $path;
        }
        return self::$__cache[__FUNCTION__];
    }

    /** リクエスト値取得（G+P） */
    public static function param(string $key, $default = null): mixed
    {
        return self::_vg(array_merge($_GET, $_POST), $key, $default);
    }

    /** GET, POST, もしくは JSON ペイロードから値を取得 */
    public static function input(string $key, $default = null): mixed
    {
        if (self::isJsonRequest()) {
            if (!isset(self::$__cache['json_payload'])) {
                self::$__cache['json_payload'] = [];
                $raw = @file_get_contents('php://input') ?: '';
                if ($raw !== '') {
                    $json = json_decode($raw, true);
                    if (is_array($json)) self::$__cache['json_payload'] = $json;
                }
            }
            if (array_key_exists($key, self::$__cache['json_payload'])) {
                return self::$__cache['json_payload'][$key];
            }
        }
        return self::param($key, $default);
    }

    /** GET値取得 */
    public static function get(string $key, $default = null): mixed
    {
        return self::_vg($_GET, $key, $default);
    }

    /** POST値取得 */
    public static function post(string $key, $default = null): mixed
    {
        return self::_vg($_POST, $key, $default);
    }

    /** ファイル取得 */
    public static function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /** 配列値取得（G+P） */
    public static function paramArray(string $key, array $default = []): array
    {
        return self::_vgAry(array_merge($_GET, $_POST), $key, $default);
    }

    /** GET配列値取得 */
    public static function getArray(string $key, array $default = []): array
    {
        return self::_vgAry($_GET, $key, $default);
    }

    /** POST配列値取得 */
    public static function postArray(string $key, array $default = []): array
    {
        return self::_vgAry($_POST, $key, $default);
    }

    /** 数値取得（G+P） */
    public static function paramInt(string $key, int $def = 0): int
    {
        return self::_vgInt(array_merge($_GET, $_POST), $key, $def);
    }

    /** GET数値取得 */
    public static function getInt(string $key, int $def = 0): int
    {
        return self::_vgInt($_GET, $key, $def);
    }

    /** POST数値取得 */
    public static function postInt(string $key, int $def = 0): int
    {
        return self::_vgInt($_POST, $key, $def);
    }

    /** Float取得（G+P） */
    public static function paramFloat(string $key, float $def = 0.0): float
    {
        return self::_vgFloat(array_merge($_GET, $_POST), $key, $def);
    }

    /** GET Float取得 */
    public static function getFloat(string $key, float $def = 0.0): float
    {
        return self::_vgFloat($_GET, $key, $def);
    }

    /** POST Float取得 */
    public static function postFloat(string $key, float $def = 0.0): float
    {
        return self::_vgFloat($_POST, $key, $def);
    }

    /** 真偽値取得（G+P） */
    public static function paramBool(string $key, bool $def = false): bool
    {
        return self::_vgBool(array_merge($_GET, $_POST), $key, $def);
    }

    /** GET 真偽値取得 */
    public static function getBool(string $key, bool $def = false): bool
    {
        return self::_vgBool($_GET, $key, $def);
    }

    /** POST 真偽値取得 */
    public static function postBool(string $key, bool $def = false): bool
    {
        return self::_vgBool($_POST, $key, $def);
    }

    /** SERVER変数取得 */
    public static function server(string $key, mixed $default = ''): mixed
    {
        return $_SERVER[$key] ?? $default;
    }

    /** HTTPヘッダ取得 */
    public static function header(string $key, $default = null): mixed
    {
        $key = str_replace('-', '_', strtoupper($key));
        if ($key !== 'CONTENT_TYPE' && $key !== 'CONTENT_LENGTH') {
            $key = 'HTTP_' . $key;
        }
        return self::server($key, $default);
    }

    /** IPアドレス取得 */
    public static function ipAddress(): string
    {
        $remote = self::server('REMOTE_ADDR');
        $trusted = Env::get('trusted_proxies', '');

        if ($trusted === '') return $remote;

        $trustedList = array_map('trim', explode(',', $trusted));
        $isTrustedProxy = $trusted === '*' || in_array($remote, $trustedList, true);

        if (!$isTrustedProxy) return $remote;

        // Cloudflareのヘッダがあれば優先
        $cfIp = self::server('HTTP_CF_CONNECTING_IP');
        if ($cfIp !== '') return $cfIp;

        $xff = self::server('HTTP_X_FORWARDED_FOR');
        if ($xff === '') return $remote;

        $ips = array_map('trim', explode(',', $xff));

        // プロキシが追加したIPは配列の末尾（右側）に追加されるため、右から検証する
        $ips = array_reverse($ips);

        foreach ($ips as $ip) {
            // 信頼されたプロキシリストに含まれていない最初のIPが、信頼できる最下流のクライアントIP
            if ($trusted !== '*' && !in_array($ip, $trustedList, true)) {
                return $ip;
            }
        }

        // 全てが信頼されたプロキシIPだった場合（フェールセーフ）
        return end($ips) ?: $remote;
    }

    /** UserAgent取得 */
    public static function userAgent(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = self::server('HTTP_USER_AGENT');
        }
        return self::$__cache[__FUNCTION__];
    }

    /** メソッド取得 */
    public static function method(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = strtoupper(self::server('REQUEST_METHOD'));
        }
        return self::$__cache[__FUNCTION__];
    }

    /** プロトコル取得 */
    public static function protocol(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = self::isHttps() ? 'https://' : 'http://';
        }
        return self::$__cache[__FUNCTION__];
    }

    /** ホスト名取得 */
    public static function host(): string
    {
        return self::server('HTTP_HOST');
    }

    /** フルURL取得 */
    public static function url(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = self::protocol() . self::host() . self::server('REQUEST_URI', '/');
        }
        return self::$__cache[__FUNCTION__];
    }

    /** クエリなしURL取得 */
    public static function url_without_query(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = self::protocol() . self::host() . strtok(self::server('REQUEST_URI', '/'), '?');
        }
        return self::$__cache[__FUNCTION__];
    }

    /** ルートURL取得 */
    public static function url_root(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = self::protocol() . self::host() . dirname(self::server('SCRIPT_NAME'));
        }
        return self::$__cache[__FUNCTION__];
    }

    /** パス情報分解 */
    public static function path_info(): ?array
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            self::$__cache[__FUNCTION__] = null;
            $pathInfo = self::server('PATH_INFO');
            if ($pathInfo !== '') {
                self::$__cache[__FUNCTION__] = pathinfo($pathInfo);
            }
        }
        return self::$__cache[__FUNCTION__];
    }

    /** ベース名取得 */
    public static function basename(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            $pinfo = self::path_info();
            self::$__cache[__FUNCTION__] = $pinfo['basename'] ?? '';
        }
        return self::$__cache[__FUNCTION__];
    }

    /** ファイル名取得 */
    public static function filename(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            $pinfo = self::path_info();
            self::$__cache[__FUNCTION__] = $pinfo['filename'] ?? '';
        }
        return self::$__cache[__FUNCTION__];
    }

    /** 拡張子取得 */
    public static function extension(): string
    {
        if (!isset(self::$__cache[__FUNCTION__])) {
            $pinfo = self::path_info();
            self::$__cache[__FUNCTION__] = $pinfo['extension'] ?? '';
        }
        return self::$__cache[__FUNCTION__];
    }

    private static function _vg(array $dat, string $key, mixed $def): mixed
    {
        if (!isset($dat[$key]) && !array_key_exists($key, $dat)) return $def;
        $r = $dat[$key];
        return $r === null ? $def : $r;
    }

    private static function _vgAry(array $dat, string $key, array $def = []): array
    {
        $r = self::_vg($dat, $key, $def);
        return is_array($r) ? $r : [$r];
    }

    private static function _vgInt(array $dat, string $key, int $def = 0): int
    {
        $r = self::_vg($dat, $key, $def);
        if (is_array($r)) return isset($r[0]) ? (int)$r[0] : (int)$def;
        return is_numeric($r) ? (int)$r : (int)$def;
    }

    private static function _vgBool(array $dat, string $key, bool $def = false): bool
    {
        $r = self::_vg($dat, $key, $def);
        if (is_array($r)) $r = $r[0] ?? $def;
        if (is_bool($r)) return $r;
        if (is_numeric($r)) return ((int)$r !== 0);
        if (is_string($r)) return in_array(strtolower($r), ['1', 'true', 'on', 'yes'], true);
        return (bool)$r;
    }

    private static function _vgFloat(array $dat, string $key, float $def = 0.0): float
    {
        $r = self::_vg($dat, $key, $def);
        if (is_array($r)) return isset($r[0]) ? (float)$r[0] : (float)$def;
        return is_numeric($r) ? (float)$r : (float)$def;
    }

    /** POST判定 */
    public static function isPost(): bool
    {
        return self::isMethod('POST');
    }
    /** GET判定 */
    public static function isGet(): bool
    {
        return self::isMethod('GET');
    }
    /** HEAD判定 */
    public static function isHead(): bool
    {
        return self::isMethod('HEAD');
    }
    /** メソッド判定 */
    public static function isMethod($method): bool
    {
        return self::method() === strtoupper($method);
    }

    /** APIルート判定 */
    public static function isApiRoute(): bool
    {
        $prefix = Env::get('api_prefix', 'api');
        return str_starts_with(Request::route(), $prefix . '/');
    }

    /** Ajax判定 */
    public static function isAjax(): bool
    {
        $xrw = self::server('HTTP_X_REQUESTED_WITH');
        return ($xrw !== '' && strtolower($xrw) === 'xmlhttprequest');
    }

    /** JSONリクエスト判定 */
    public static function isJsonRequest(): bool
    {
        return str_contains(self::server('CONTENT_TYPE') ?? '', 'application/json');
    }

    /** JSON応答要求判定 */
    public static function wantsJson(): bool
    {
        return str_contains(self::server('HTTP_ACCEPT') ?? '', 'application/json');
    }

    /** ロボット判定 */
    public static function isRobot(): bool
    {
        $ua = strtolower(self::server('HTTP_USER_AGENT'));
        $bots = ['googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandex', 'sogou', 'exabot', 'facebot', 'ia_archiver'];
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) return true;
        }
        return false;
    }

    /** モバイル判定 */
    public static function isMobile(): bool
    {
        $ua = strtolower(self::server('HTTP_USER_AGENT'));
        $mobiles = ['iphone', 'ipad', 'android', 'windows phone', 'blackberry', 'opera mini', 'iemobile', 'mobile'];
        foreach ($mobiles as $m) {
            if (strpos($ua, $m) !== false) return true;
        }
        return false;
    }

    /** HTTPS判定 */
    public static function isHttps(): bool
    {
        $h = self::server('HTTPS');
        if (!empty($h) && $h !== 'off') return true;
        $p = self::server('HTTP_X_FORWARDED_PROTO');
        return (strtolower($p) === 'https');
    }

    /** ブラウザ名推測 */
    public static function getBrowser(): string
    {
        $ua = self::server('HTTP_USER_AGENT');
        if (strpos($ua, 'Chrome') !== false) return 'Chrome';
        if (strpos($ua, 'Firefox') !== false) return 'Firefox';
        if (strpos($ua, 'Safari') !== false) return 'Safari';
        if (strpos($ua, 'Edge') !== false) return 'Edge';
        if (strpos($ua, 'MSIE') !== false) return 'Internet Explorer';
        return 'Unknown';
    }

    /** OS名推測 */
    public static function getOS(): string
    {
        $ua = self::server('HTTP_USER_AGENT');
        if (strpos($ua, 'Windows NT') !== false) return 'Windows';
        if (strpos($ua, 'Macintosh') !== false) return 'Mac OS';
        if (strpos($ua, 'Linux') !== false) return 'Linux';
        if (strpos($ua, 'Android') !== false) return 'Android';
        if (strpos($ua, 'iPhone') !== false) return 'iOS';
        return 'Unknown';
    }

    /** デバイス型判定 */
    public static function getDeviceType(): string
    {
        $ua = strtolower(self::server('HTTP_USER_AGENT'));
        if (strpos($ua, 'ipad') !== false) return 'tablet';
        if (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) return 'tablet';
        if (strpos($ua, 'iphone') !== false || (strpos($ua, 'android') !== false && strpos($ua, 'mobile') !== false)) return 'mobile';
        return 'pc';
    }
}
