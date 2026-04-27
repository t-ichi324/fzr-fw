<?php

namespace Fzr;

/**
 * JWT (JSON Web Token) Utility — HS256 implementation.
 *
 * Zero external dependencies. Uses only PHP standard functions.
 *
 * Standard claims automatically added on encode:
 *   - iat : issued at (Unix timestamp)
 *   - exp : expiry   (Unix timestamp)
 *   - jti : unique token ID for blacklist support
 *
 * @throws \RuntimeException on verify() failure (expired / invalid signature / malformed)
 */
class Jwt
{
    private const ALGORITHM = 'sha256';
    private const HEADER    = ['alg' => 'HS256', 'typ' => 'JWT'];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Issue a signed JWT.
     *
     * @param array  $payload  Arbitrary key-value pairs (do NOT include passwords or sensitive data).
     * @param string $secret   Secret key — store in app.key or .env, never hardcode.
     * @param int    $ttl      Time-to-live in seconds (default: 3600).
     * @return string          Signed JWT string.
     *
     * @example
     *   $token = Jwt::encode(['user_id' => 42, 'role' => 'admin'], Env::get('app.key'));
     */
    public static function encode(array $payload, string $secret, int $ttl = 3600): string
    {
        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;
        $payload['jti'] = bin2hex(random_bytes(8));

        $header    = self::base64url(json_encode(self::HEADER, JSON_THROW_ON_ERROR));
        $body      = self::base64url(json_encode($payload,    JSON_THROW_ON_ERROR));
        $signature = self::sign("$header.$body", $secret);

        return "$header.$body.$signature";
    }

    /**
     * Verify and decode a JWT. Returns null on any failure (expired, tampered, malformed).
     *
     * Use this in non-throwing contexts (e.g., middleware checks).
     *
     * @return array|null  Decoded payload, or null on failure.
     *
     * @example
     *   $payload = Jwt::decode(Request::bearerToken(), Env::get('app.key'));
     *   if ($payload === null) throw HttpException::unauthorized();
     */
    public static function decode(string $token, string $secret): ?array
    {
        try {
            return self::verify($token, $secret);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Verify and decode a JWT. Throws \RuntimeException on failure.
     *
     * Suitable for use where HttpException can be thrown by the caller:
     *   try { $p = Jwt::verify($token, $secret); }
     *   catch (\RuntimeException $e) { throw HttpException::unauthorized($e->getMessage()); }
     *
     * @return array  Decoded payload.
     * @throws \RuntimeException  'Malformed token' | 'Invalid signature' | 'Token expired'
     */
    public static function verify(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed token');
        }

        [$header, $body, $signature] = $parts;

        // Signature check (timing-safe)
        $expected = self::sign("$header.$body", $secret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid signature');
        }

        // Decode payload
        $payload = json_decode(self::base64urlDecode($body), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Malformed token');
        }

        // Expiry check
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('Token expired');
        }

        return $payload;
    }

    /**
     * Extract the raw token string from an Authorization header value.
     *
     * @param  string|null $headerValue  e.g. "Bearer eyJhbGci..."
     * @return string|null               Raw token string, or null if not found.
     *
     * @example
     *   $token = Jwt::fromBearer(Request::header('Authorization'));
     */
    public static function fromBearer(?string $headerValue): ?string
    {
        if ($headerValue === null) return null;

        if (str_starts_with($headerValue, 'Bearer ')) {
            $token = substr($headerValue, 7);
            return $token !== '' ? $token : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private static function sign(string $data, string $secret): string
    {
        return self::base64url(hash_hmac(self::ALGORITHM, $data, $secret, true));
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
