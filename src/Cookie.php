<?php

namespace Fzr;

/**
 * Cookie Manager — simplified interface for reading and writing HTTP cookies.
 *
 * Use to persist small amounts of data on the client's browser.
 * Typical uses: user preferences, guest tracking, remember-me flags.
 *
 * - Provides a clean API for `setcookie()` and `$_COOKIE`.
 * - Handles default paths, domains, and security flags (HttpOnly, Secure, SameSite) from `Env`.
 * - Automatically detects HTTPS for the `Secure` flag.
 */
class Cookie
{
    public static function get(string $key, $default = null): ?string
    {
        return $_COOKIE[$key] ?? $default;
    }

    public static function set(string $key, string $value, int $expire = 0, string $path = '/', ?string $domain = null, ?bool $secure = null, ?bool $httpOnly = null, ?string $sameSite = null): bool
    {
        $secure = $secure ?? Env::getBool('cookie.secure', Request::isHttps());
        $httpOnly = $httpOnly ?? Env::getBool('cookie.httponly', true);
        $sameSite = $sameSite ?? Env::get('cookie.samesite', 'Lax');

        return setcookie($key, $value, [
            'expires'  => $expire,
            'path'     => $path,
            'domain'   => $domain ?: Env::get('cookie.domain', ''),
            'secure'   => $secure,
            'httponly'  => $httpOnly,
            'samesite'  => $sameSite,
        ]);
    }

    public static function remove(string $key, string $path = '/', ?string $domain = null): void
    {
        self::set($key, '', time() - 3600, $path, $domain);
    }

    public static function has(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }
}
