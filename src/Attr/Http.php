<?php

namespace Fzr\Attr\Http;

use Attribute;

/**
 * HTTP Attributes — declarative markers for request handling and security.
 *
 * Use to annotate controllers and methods with cross-cutting concerns.
 *
 * - #[Csrf]: Enforces CSRF token validation on the target.
 * - #[Auth]: Requires user authentication; supports optional redirect URL.
 * - #[Roles]: Restricts access to specific user roles.
 * - #[AllowCors]: Enables CORS headers for the target.
 * - #[IpWhitelist]: Restricts access to specific IP ranges.
 */

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Csrf {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Auth
{
    public ?string $redirect;
    public function __construct(?string $redirect = null)
    {
        $this->redirect = $redirect;
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Guest
{
    public ?string $redirect;
    public function __construct(?string $redirect = null)
    {
        $this->redirect = $redirect;
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Api {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Roles
{
    public array $roles;
    public function __construct(string|array ...$roles)
    {
        $merged = [];
        foreach ($roles as $r) {
            if (is_array($r)) $merged = array_merge($merged, $r);
            else $merged[] = $r;
        }
        $this->roles = array_values(array_unique($merged));
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowCors
{
    public array $origins;
    public string $methods;
    public string $headers;
    public bool $credentials;

    public function __construct(
        string|array $origin = '*',
        string $methods = 'GET,POST,PUT,DELETE,OPTIONS',
        string $headers = 'Content-Type,Authorization,X-Requested-With',
        bool $credentials = true
    ) {
        $this->origins = is_array($origin) ? $origin : array_map('trim', explode(',', $origin));
        $this->methods = $methods;
        $this->headers = $headers;
        $this->credentials = $credentials;
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowCache
{
    public int $maxAge;
    public function __construct(int $maxAge = 3600)
    {
        $this->maxAge = $maxAge;
    }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowIframe {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsReadOnly {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IpWhitelist
{
    public array $ips;
    public function __construct(string|array ...$ips)
    {
        $merged = [];
        foreach ($ips as $r) {
            if (is_array($r)) $merged = array_merge($merged, $r);
            else $merged[] = $r;
        }
        $this->ips = array_values(array_unique($merged));
    }
}
