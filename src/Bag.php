<?php

namespace Fzr;

/**
 * Unstructured model base — instance-based, internal array storage.
 *
 * Use when the data shape is dynamic or unknown at compile time.
 * Typical uses: arbitrary key-value containers, parsed payloads, flexible DTOs.
 *
 * - Data is stored in `$this->data` array; no declared properties needed.
 * - Data is accessed via `$bag->get('key')`.
 * - Instantiated per-object; each instance holds its own data.
 *
 * Contrast with {@see Model} (typed properties) and {@see Store} (static/singleton-like).
 */
abstract class Bag implements \JsonSerializable
{
    protected array $data = [];

    public function __construct(mixed $data = null)
    {
        if ($data !== null) $this->bind($data);
    }

    public static function from(mixed $source): static { return new static($source); }

    public function fill(array $data): static { return $this->merge($data); }

    public function merge(array $data): static { $this->data = array_merge($this->data, $data); return $this; }

    public function replace(array $data): static { $this->data = $data; return $this; }

    public function bind(mixed $source): static { return $this->merge(self::extract($source)); }

    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }

    public function set(string $key, mixed $value): static { $this->data[$key] = $value; return $this; }

    public function has(string $key): bool { return isset($this->data[$key]); }

    public function isEmpty(?string $key = null): bool { if ($key !== null) { return empty($this->get($key)); } return empty($this->data); }

    public function keyList(): array { return array_keys($this->data); }

    public function all(): array { return $this->data; }

    public function toArray(): array { return $this->data; }

    public function jsonSerialize(): mixed { return $this->data; }

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
