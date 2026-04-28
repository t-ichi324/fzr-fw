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

    public static function from(mixed $source): static
    {
        return new static($source);
    }

    public function fill(mixed $data): static
    {
        return $this->merge($data);
    }

    public function merge(mixed $data): static
    {
        $data = DataHelper::extract($data);
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) $this->$key = $value;
        }
        return $this;
    }

    public function bind(mixed $source): static
    {
        return $this->merge($source);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->$key ?? $default;
    }

    public function toArray(): array
    {
        $res = [];
        $ref = new \ReflectionObject($this);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if ($prop->isStatic()) continue;
            $res[$name] = $this->$name;
        }
        return $res;
    }

    public function keyList(): array
    {
        return array_keys($this->toArray());
    }

    public function has(string $key): bool
    {
        return property_exists($this, $key);
    }

    public function set(string $key, mixed $value): static
    {
        if (property_exists($this, $key)) $this->$key = $value;
        return $this;
    }

    public function clear(): static
    {
        foreach ($this->keyList() as $key) {
            $this->$key = null;
        }
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
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
}
