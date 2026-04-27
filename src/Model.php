<?php

namespace Fzr;

/**
 * Structured model base — instance-based, typed properties.
 *
 * Use when the data shape is known at compile time and you want IDE autocompletion.
 * Typical uses: form DTOs, API request/response objects, typed value objects.
 *
 * - Properties are declared explicitly on the subclass.
 * - Data is accessed as `$model->property`.
 * - Not persisted to DB; for DB-mapped objects use {@see \Fzr\Db\Entity}.
 *
 * Contrast with {@see Bag} (dynamic array-based) and {@see Store} (static/singleton-like).
 */
abstract class Model implements \JsonSerializable
{
    public function __construct(mixed $data = null)
    {
        if ($data !== null) $this->merge($data);
        $this->__after_construct();
    }

    protected function __after_construct() {}

    public static function from(mixed $source): static { return new static($source); }

    public function fill(mixed $data): static { return $this->merge($data); }

    public function merge(mixed $data): static
    {
        $data = self::extract($data);
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) $this->$key = $value;
        }
        return $this;
    }

    public function bind(mixed $source): static { return $this->merge($source); }

    public function get(string $key, mixed $default = null): mixed { return $this->$key ?? $default; }

    public function toArray(): array { return get_object_vars($this); }

    public function jsonSerialize(): mixed { return $this->toArray(); }

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

    protected function _ensureScalar(mixed $v): mixed { return is_array($v) ? ($v[0] ?? null) : $v; }

    public function getString(string $key, ?string $default = null): ?string { $v = $this->_ensureScalar($this->get($key, $default)); return ($v === null) ? $default : (string)$v; }

    public function getInt(string $key, int $default = 0): int { $v = $this->_ensureScalar($this->get($key, $default)); return is_numeric($v) ? (int)$v : $default; }

    public function getFloat(string $key, float $default = 0.0): float { $v = $this->_ensureScalar($this->get($key, $default)); return is_numeric($v) ? (float)$v : $default; }

    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->_ensureScalar($this->get($key, null));
        if ($v === null) { return $default; }
        if (is_bool($v)) { return $v; }
        if (is_numeric($v)) { return (int)$v !== 0; }
        if (is_string($v)) { $v = strtolower($v); return $v === '1' || $v === 'true' || $v === 'on' || $v === 'yes'; }
        return (bool)$v;
    }

    public function getArray(string $key, array $default = []): array
    {
        $v = $this->get($key, $default);
        if (is_array($v)) { return $v; }
        if (is_string($v) && str_starts_with($v, '[')) { return json_decode($v, true) ?: $default; }
        return [$v];
    }

    public function getJson(string $key, array $default = []): array
    {
        $v = $this->get($key);
        if (empty($v)) { return $default; }
        if (is_array($v)) { return $v; }
        return json_decode($v, true) ?: $default;
    }
}
