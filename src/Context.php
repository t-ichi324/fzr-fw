<?php

namespace Fzr;

/**
 * Application Execution Context — request lifecycle tracking and environment state.
 *
 * Use to access metadata about the current execution, such as request ID, execution time,
 * and whether the app is running in a stateless (Cloud/Container) environment.
 * Typical uses: logging correlation, performance profiling, feature branching by environment.
 *
 * - Generates and holds a unique `requestId` per request.
 * - Tracks `startTime` for performance measurement (see `elapsed()`).
 * - Detects stateless environments (Cloud Run, Lambda) via `isStateless()`.
 * - Holds Redis connection config for cloud-native session/cache drivers.
 */
class Context
{
    const MODE_WEB = 'web';
    const MODE_API = 'api';
    const MODE_CLI = 'cli';
    private static string $mode = self::MODE_WEB;
    private static string $requestId = '';
    private static float $startTime = 0;
    private static bool $debug = false;

    public static function init(bool $is_debug): void
    {
        self::$debug = $is_debug;
        self::$startTime = defined('APP_START_TIME') ? APP_START_TIME : microtime(true);
        self::$requestId = substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public static function setMode(string $mode): void
    {
        self::$mode = $mode;
    }
    public static function modeToWeb(): void
    {
        self::$mode = self::MODE_WEB;
    }
    public static function modeToApi(): void
    {
        self::$mode = self::MODE_API;
    }
    public static function modeToCli(): void
    {
        self::$mode = self::MODE_CLI;
    }
    public static function mode(): string
    {
        return self::$mode;
    }
    public static function isApi(): bool
    {
        return self::$mode === self::MODE_API;
    }
    public static function isWeb(): bool
    {
        return self::$mode === self::MODE_WEB;
    }
    public static function isCli(): bool
    {
        return self::$mode === self::MODE_CLI;
    }
    public static function isDebug(): bool
    {
        return self::$debug;
    }
    public static function requestId(): string
    {
        return self::$requestId;
    }
    public static function startTime(): float
    {
        return self::$startTime;
    }
    public static function elapsed(): float
    {
        return microtime(true) - self::$startTime;
    }

    public static function isStateless(): bool
    {
        if (Env::get('app.stateless') !== null) {
            return Env::getBool('app.stateless');
        }
        return getenv('K_SERVICE') !== false || getenv('GAE_APPLICATION') !== false || getenv('AWS_LAMBDA_FUNCTION_NAME') !== false;
    }

    public static function getRedisConfig(): ?array
    {
        $host = Env::get('redis.host') ?: getenv('REDISHOST') ?: getenv('REDIS_HOST');
        if ($host) {
            return [
                'host' => $host,
                'port' => (int)(Env::get('redis.port') ?: getenv('REDISPORT') ?: getenv('REDIS_PORT') ?: 6379),
            ];
        }
        return null;
    }
}
