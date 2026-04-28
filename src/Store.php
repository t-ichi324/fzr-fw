<?php

namespace Fzr;

/**
 * Static model base — class-level singleton registry, no instantiation.
 *
 * Use when you need shared, request-scoped state accessible globally without
 * passing an object around.
 * Typical uses: Auth user data, Session, Config, request-level caches.
 *
 * - Data is stored in a static registry keyed by subclass name.
 * - Data is accessed via `ClassName::get('key')` / `ClassName::all()`.
 * - One state per class per process; cleared with `ClassName::clear()`.
 *
 * Contrast with {@see Model} (typed properties) and {@see Bag} (instance array).
 */
abstract class Store
{
    private static array $_allData = [];

    protected static function &data(): mixed
    {
        $class = static::class;
        if (!isset(self::$_allData[$class])) self::$_allData[$class] = null;
        return self::$_allData[$class];
    }

    public static function from(mixed $source): void
    {
        static::replace($source);
    }

    public static function fill(mixed $data): void
    {
        static::merge($data);
    }

    public static function merge(mixed $data): void
    {
        $current = &static::data();
        $newData = DataHelper::extract($data);
        if (is_array($current)) {
            $current = array_merge($current, $newData);
        } elseif (is_object($current)) {
            foreach ($newData as $k => $v) $current->$k = $v;
        } else {
            $current = $newData;
        }
    }

    public static function replace(mixed $data): void
    {
        $ref = &static::data();
        $ref = $data;
    }

    public static function bind(mixed $source): void
    {
        static::merge($source);
    }

    public static function clear(): void
    {
        $ref = &static::data();
        $ref = null;
    }

    public static function toArray(): mixed
    {
        return static::data();
    }

    public static function hasData(): bool
    {
        return !static::isEmpty();
    }

    public static function isEmpty(?string $key = null): bool
    {
        if ($key !== null) {
            return empty(static::get($key));
        }
        return empty(static::data());
    }

    public static function has(string $key): bool
    {
        $data = static::data();
        return ($data === null) ? false : (is_array($data) ? isset($data[$key]) : isset($data->$key));
    }

    public static function keyList(): array
    {
        $data = static::data();
        if ($data === null) return [];
        return is_array($data) ? array_keys($data) : array_keys(get_object_vars($data));
    }

    public static function set(string $key, mixed $value): void
    {
        $data = &static::data();
        if ($data === null) $data = new \stdClass();
        if (is_object($data)) {
            $data->$key = $value;
        } else {
            $data[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $data = static::data();
        if ($data === null) {
            return $default;
        }
        return is_object($data) ? ($data->$key ?? $default) : ($data[$key] ?? $default);
    }

    public static function getString(string $key, ?string $default = null): ?string
    {
        return DataHelper::asString(static::get($key), $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return DataHelper::asInt(static::get($key), $default);
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        return DataHelper::asFloat(static::get($key), $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return DataHelper::asBool(static::get($key), $default);
    }

    public static function getArray(string $key, array $default = []): array
    {
        return DataHelper::asArray(static::get($key), $default);
    }

    public static function getJson(string $key, array $default = []): array
    {
        return DataHelper::asJson(static::get($key), $default);
    }

    public static function getDateTime(string $key, mixed $default = null): ?\DateTime
    {
        return DataHelper::asDateTime(static::get($key), $default);
    }
}
