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

    public static function from(mixed $source): void { static::replace($source); }

    public static function fill(mixed $data): void { static::merge($data); }

    public static function merge(mixed $data): void
    {
        $current = &static::data();
        $newData = self::extract($data);
        if (is_array($current)) {
            $current = array_merge($current, $newData);
        } elseif (is_object($current)) {
            foreach ($newData as $k => $v) $current->$k = $v;
        } else {
            $current = $newData;
        }
    }

    public static function replace(mixed $data): void { $ref = &static::data(); $ref = $data; }

    public static function bind(mixed $source): void { static::merge($source); }

    public static function clear(): void { $ref = &static::data(); $ref = null; }

    public static function all(): mixed { return static::data(); }

    public static function hasData(): bool { return !static::isEmpty(); }

    public static function isEmpty(?string $key = null): bool { if ($key !== null) { return empty(static::get($key)); } return empty(static::data()); }

    public static function has(string $key): bool { $data = static::data(); return ($data === null) ? false : (is_array($data) ? isset($data[$key]) : isset($data->$key)); }

    public static function set(string $key, mixed $value): void
    {
        $data = &static::data();
        if ($data === null) $data = new \stdClass();
        if (is_object($data)) { $data->$key = $value; } else { $data[$key] = $value; }
    }

    public static function get(string $key, mixed $default = null): mixed { $data = static::data(); if ($data === null) { return $default; } return is_object($data) ? ($data->$key ?? $default) : ($data[$key] ?? $default); }

    private static function extract(mixed $source): array
    {
        if ($source === null) { return []; }
        if (is_array($source)) { return $source; }
        if (is_string($source)) { return json_decode($source, true) ?: []; }
        if (is_object($source)) {
            if (method_exists($source, 'all')) { return $source->all(); }
            if (method_exists($source, 'toArray')) { return $source->toArray(); }
            return get_object_vars($source);
        }
        return (array)$source;
    }

    protected static function _ensureScalar(mixed $v): mixed { return is_array($v) ? ($v[0] ?? null) : $v; }

    public static function getString(string $key, ?string $default = null): ?string { $v = static::_ensureScalar(static::get($key, $default)); return ($v === null) ? $default : (string)$v; }

    public static function getInt(string $key, int $default = 0): int { $v = static::_ensureScalar(static::get($key, $default)); return is_numeric($v) ? (int)$v : $default; }

    public static function getFloat(string $key, float $default = 0.0): float { $v = static::_ensureScalar(static::get($key, $default)); return is_numeric($v) ? (float)$v : $default; }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = static::_ensureScalar(static::get($key, null));
        if ($v === null) { return $default; }
        if (is_bool($v)) { return $v; }
        if (is_numeric($v)) { return (int)$v !== 0; }
        if (is_string($v)) { $v = strtolower($v); return $v === '1' || $v === 'true' || $v === 'on' || $v === 'yes'; }
        return (bool)$v;
    }

    public static function getArray(string $key, array $default = []): array
    {
        $v = static::get($key, $default);
        if (is_array($v)) { return $v; }
        if (is_string($v) && str_starts_with($v, '[')) { return json_decode($v, true) ?: $default; }
        return [$v];
    }

    public static function getJson(string $key, array $default = []): array
    {
        $v = static::get($key);
        if (empty($v)) { return $default; }
        if (is_array($v)) { return $v; }
        return json_decode($v, true) ?: $default;
    }
}
