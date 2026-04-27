<?php

namespace Fzr\Attr\Field;

use Attribute;

/**
 * Field Attributes — declarative markers for data mapping and validation.
 *
 * Use to annotate {@see Entity} or {@see Model} properties with metadata.
 *
 * - #[Label]: Provides a human-readable name for form rendering and error messages.
 * - Validation Attributes: #[Required], #[Email], #[Numeric], #[Max], #[Min], #[MaxValue], #[MinValue], etc.
 * - Automatically extracted by {@see Form} and {@see Entity} to enforce rules or map schema.
 */

#[Attribute(Attribute::TARGET_PROPERTY)]
class Label
{
    public string $label;
    public function __construct(string $label)
    {
        $this->label = $label;
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required
{
    public function toValidation(): array
    {
        return ['required' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max
{
    public int $max;
    public function __construct(int $max)
    {
        $this->max = $max;
    }
    public function toValidation(): array
    {
        return ['max' => $this->max];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public int $min;
    public function __construct(int $min)
    {
        $this->min = $min;
    }
    public function toValidation(): array
    {
        return ['min' => $this->min];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class MaxValue
{
    public float $max;
    public function __construct(float $max)
    {
        $this->max = $max;
    }
    public function toValidation(): array
    {
        return ['maxValue' => $this->max];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinValue
{
    public float $min;
    public function __construct(float $min)
    {
        $this->min = $min;
    }
    public function toValidation(): array
    {
        return ['minValue' => $this->min];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email
{
    public function toValidation(): array
    {
        return ['email' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Numeric
{
    public function toValidation(): array
    {
        return ['numeric' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Integer
{
    public function toValidation(): array
    {
        return ['integer' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Url
{
    public function toValidation(): array
    {
        return ['url' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    public string $pattern;
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }
    public function toValidation(): array
    {
        return ['regex' => $this->pattern];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class In
{
    public array $values;
    public function __construct(string ...$values)
    {
        $this->values = $values;
    }
    public function toValidation(): array
    {
        return ['in' => $this->values];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotIn
{
    public array $values;
    public function __construct(string ...$values)
    {
        $this->values = $values;
    }
    public function toValidation(): array
    {
        return ['notIn' => $this->values];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Between
{
    public float $min;
    public float $max;
    public function __construct(float $min, float $max)
    {
        $this->min = $min;
        $this->max = $max;
    }
    public function toValidation(): array
    {
        return ['between' => [$this->min, $this->max]];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Confirmed
{
    public function toValidation(): array
    {
        return ['confirmed' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class SameAs
{
    public string $other;
    public function __construct(string $other)
    {
        $this->other = $other;
    }
    public function toValidation(): array
    {
        return ['match' => $this->other];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Date
{
    public function toValidation(): array
    {
        return ['date' => true];
    }
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Custom
{
    public function __construct(public readonly string $method) {}
}
