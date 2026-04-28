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

    public static function from(mixed $source): static
    {
        return new static($source);
    }

    public function fill(array $data): static
    {
        return $this->merge($data);
    }

    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function replace(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function bind(mixed $source): static
    {
        return $this->merge(DataHelper::extract($source));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function isEmpty(?string $key = null): bool
    {
        if ($key !== null) {
            return empty($this->get($key));
        }
        return empty($this->data);
    }

    public function keyList(): array
    {
        return array_keys($this->data);
    }

    public function clear(): static
    {
        $this->data = [];
        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        return DataHelper::asString($this->get($key), $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return DataHelper::asInt($this->get($key), $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return DataHelper::asFloat($this->get($key), $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return DataHelper::asBool($this->get($key), $default);
    }

    public function getArray(string $key, array $default = []): array
    {
        return DataHelper::asArray($this->get($key), $default);
    }

    public function getJson(string $key, array $default = []): array
    {
        return DataHelper::asJson($this->get($key), $default);
    }

    public function getDateTime(string $key, mixed $default = null): ?\DateTime
    {
        return DataHelper::asDateTime($this->get($key), $default);
    }
}
