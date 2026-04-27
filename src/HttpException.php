<?php
namespace Fzr;

use Throwable;

/**
 * HTTP Exception — represents various HTTP error states (404, 403, 500, etc.).
 *
 * Use to halt execution and return a specific HTTP status code via {@see Response}.
 * Typical uses: "not found" pages, authorization failures, validation errors.
 *
 * - Includes static factory methods for common error codes (`notFound()`, `forbidden()`).
 * - Automatically resolves human-readable error titles for common HTTP status codes.
 * - Caught by the global error handler in {@see Engine} to render error views.
 */
class HttpException extends \Exception {
    protected static $errorTitles = [
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        409 => "Conflict",
        410 => "Gone",
        422 => "Unprocessable Entity",
        429 => "Too Many Requests",
        500 => "Internal Server Error",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
    ];
    protected $httpCode;

    public function __construct($message = null, $httpCode = 500, ?Throwable $previous = null) {
        $this->httpCode = $httpCode;
        parent::__construct($message ?: self::getErrorTitle($httpCode), $httpCode, $previous);
    }

    public function getHttpCode() {
        return $this->httpCode;
    }

    public static function getErrorTitle($httpCode): string {
        return self::$errorTitles[$httpCode] ?? 'Error';
    }

    public static function badRequest($msg = null) {
        return new self($msg, 400);
    }

    public static function unauthorized($msg = null) {
        return new self($msg, 401);
    }

    public static function forbidden($msg = null) {
        return new self($msg, 403);
    }

    public static function notFound($msg = null) {
        return new self($msg, 404);
    }

    public static function methodNotAllowed($msg = null) {
        return new self($msg, 405);
    }

    public static function internal($msg = null) {
        return new self($msg, 500);
    }
}
