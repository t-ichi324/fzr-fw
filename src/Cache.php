<?php

namespace Fzr;

/**
 * Cache Management — unified interface for memory, file, and Redis storage.
 *
 * Use to store expensive calculation results or frequently accessed data to improve performance.
 * Typical uses: caching DB query results, API responses, or compiled configuration data.
 *
 * - Implements a two-layer cache: Local Memory (per request) and Persistent (File/Redis).
 * - Automatically switches to Redis in stateless environments if available.
 * - Supports custom cache drivers via `setDriver()`.
 * - Includes a closure-based `get()` method that handles "get or set" logic atomically.
 */
class Cache
{
    private static string $prefix = 'cache';
    private static array $__memory = [];

    /**
     * ドライバ（外部キャッシュストレージ）
     * set すると get/put がドライバに委譲される
     * @var object|null { get(key, ttl, closure), clear(key) }
     */
    private static ?object $driver = null;

    /** 外部キャッシュドライバ設定 */
    public static function setDriver(object $driver): void
    {
        self::$driver = $driver;
    }

    private static bool $booted = false;

    private static function bootIfNeeded(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        if (self::$driver === null) {
            $isCloud = Context::isStateless();
            $redisConf = Context::getRedisConfig();
            if ($isCloud && $redisConf && class_exists('\Redis')) {
                try {
                    self::$driver = new RedisCacheDriver($redisConf['host'], $redisConf['port']);
                } catch (\Throwable) {
                    Logger::debug("Cache: Redis connection failed, falling back to file cache.", ['host' => $redisConf['host']]);
                }
            }
        }
    }

    /**
     * キャッシュ取得
     */
    public static function get(string $key, int $ttl, callable $closure): mixed
    {
        self::bootIfNeeded();

        if (isset(self::$__memory[$key])) {
            return self::$__memory[$key];
        }

        if (self::$driver !== null && method_exists(self::$driver, 'get')) {
            $start = microtime(true);
            $value = self::$driver->get($key, $ttl, $closure);
            $elapsed = microtime(true) - $start;
            // Redisなどは内部でhit/missを判断してaddするべきだが、一旦ここで記録
            // self::$driver->get が $hit を返さないので、Tracer::recordCacheは driver 内で行うのが望ましい
            self::$__memory[$key] = $value;
            return $value;
        }

        $path = self::getPath($key);
        if (file_exists($path) && (time() - filemtime($path)) < $ttl) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $value = json_decode($content, true);
                if ($value !== null || $content === 'null') {
                    if (Tracer::isEnabled()) Tracer::recordCache('GET', $key, true);
                    self::$__memory[$key] = $value;
                    return $value;
                }
            }
        }

        if (Tracer::isEnabled()) Tracer::recordCache('GET', $key, false);

        $value = $closure();
        $result = file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($result !== false) {
            touch($path, time());
        } else {
            Logger::warning("Cache write failed. Please check directory permissions.", ['path' => $path]);
        }
        self::$__memory[$key] = $value;
        return $value;
    }

    public static function delete(string $key): bool
    {
        self::bootIfNeeded();

        unset(self::$__memory[$key]);

        if (self::$driver !== null && method_exists(self::$driver, 'delete')) {
            return self::$driver->delete($key);
        }

        $path = self::getPath($key);
        if (is_file($path)) return unlink($path);
        return false;
    }

    private static function getPath(string $key): string
    {
        $dir = Path::temp(self::$prefix);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}

/**
 * キャッシュドライバのインターフェース
 */
interface CacheDriver
{
    public function get(string $key, int $ttl, callable $closure): mixed;
    public function delete(string $key): bool;
}

/**
 * Redis キャッシュドライバ
 * ※ PECL php-redis 拡張モジュールが必要です
 */
class RedisCacheDriver implements CacheDriver
{
    /** @var mixed|\Redis */
    private mixed $redis;

    /**
     * @param string $host Redisサーバーのホスト (例: '127.0.0.1' またはソケットパス '/var/run/redis/redis-server.sock')
     * @param int $port ポート番号 (例: 6379。ソケット通信の場合は 0 を指定)
     * @param float|int $timeout 接続タイムアウト (秒)
     * @param string|null $password パスワード
     * @param int $dbIndex データベースインデックス
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, float $timeout = 0.0, ?string $password = null, int $dbIndex = 0)
    {
        if (!class_exists('\Redis')) {
            throw new \RuntimeException("Redis extension (php-redis) is not installed. Please install it or use the default file cache.");
        }
        $class = '\Redis';
        $this->redis = new $class();
        $this->redis->connect($host, $port, $timeout);
        if ($password !== null) {
            $this->redis->auth($password);
        }
        if ($dbIndex > 0) {
            $this->redis->select($dbIndex);
        }
    }

    public function get(string $key, int $ttl, callable $closure): mixed
    {
        $val = $this->redis->get($key);
        if ($val !== false) {
            if (Tracer::isEnabled()) Tracer::recordCache('REDIS_GET', $key, true);
            return json_decode($val, true);
        }

        if (Tracer::isEnabled()) Tracer::recordCache('REDIS_GET', $key, false);
        $value = $closure();
        $this->redis->setex($key, $ttl, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $value;
    }

    public function delete(string $key): bool
    {
        return (bool)$this->redis->del($key);
    }
}
