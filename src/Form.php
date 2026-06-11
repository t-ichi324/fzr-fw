<?php

namespace Fzr;

/**
 * Form Management — entry point for creating and processing web forms.
 *
 * Use to handle user input validation and form rendering in a unified way.
 * Typical uses: processing contact forms, handling user registration, data entry interfaces.
 *
 * - Simplifies the "bind-validate-render" workflow.
 * - Supports mass-assignment from request data via `from()`.
 * - Extends {@see Bag} to hold validated or raw input data.
 */
class Form extends Bag
{
    /** addError() でフィールド未指定のときに使う内部キー */
    public const ERROR_GLOBAL = '_global';

    protected array $validators = [];
    /** @var array<string, list<string>> [field => [msg, ...]]（グローバルは ERROR_GLOBAL キー） */
    protected array $errors = [];
    protected ?string $csrfToken = null;
    protected ?Model $boundModel = null;

    public static function fromRequest(): self
    {
        // $_REQUEST（Cookie 混入あり得る）ではなく Request::allInputs() に揃える
        return new self(Request::allInputs());
    }

    /** ソースからFormを生成（Modelの場合はバリデーションルールも自動構築） */
    public static function from(mixed $source, array $ignore = []): static
    {
        $instance = new static();
        if ($source instanceof Model) {
            return $instance->bindModel($source, $ignore);
        }
        return $instance->bind($source);
    }

    public function __construct(null|Model|array $source = null)
    {
        if ($source instanceof Model) {
            $this->data = $source->toArray();
        } elseif (is_array($source)) {
            $this->data = $source;
        } else {
            // GET + POST + JSON ペイロード（Request::input() と同じソース・同じ優先順）
            $this->data = Request::allInputs();
        }
        $this->data = $this->trimData($this->data);
        $this->csrfToken = Security::getCsrfToken();
    }

    /** データをバインドする（文字列は自動で trim） */
    public function bind(mixed $source): static
    {
        $data = is_object($source) ? (array)$source : ($source ?? []);
        $this->data = $this->trimData($data);
        return $this;
    }

    protected function trimData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->trimData($value);
            }
        }
        return $data;
    }

    public function bindModel(Model $model, array $ignore = []): self
    {
        $this->bind($model);
        return $this->applyAttributes($model, $ignore);
    }

    public function applyAttributes(Model $model, array $ignore = []): self
    {
        $this->boundModel = $model;
        $ref = new \ReflectionClass($model);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (in_array($name, $ignore, true)) {
                continue;
            }
            $attrs = $prop->getAttributes();
            $label = null;
            foreach ($attrs as $attr) {
                if ($attr->getName() === 'Fzr\Attr\Field\Label') {
                    $label = $attr->newInstance()->label;
                    break;
                }
            }

            $rule = $this->rule($name, $label);
            foreach ($attrs as $attr) {
                $instance = $attr->newInstance();
                if ($instance instanceof \Fzr\Attr\Field\Required) {
                    $rule->required();
                } elseif ($instance instanceof \Fzr\Attr\Field\Email) {
                    $rule->email();
                } elseif ($instance instanceof \Fzr\Attr\Field\Min) {
                    $rule->min($instance->min);
                } elseif ($instance instanceof \Fzr\Attr\Field\Max) {
                    $rule->max($instance->max);
                } elseif ($instance instanceof \Fzr\Attr\Field\MinValue) {
                    $rule->minValue($instance->min);
                } elseif ($instance instanceof \Fzr\Attr\Field\MaxValue) {
                    $rule->maxValue($instance->max);
                } elseif ($instance instanceof \Fzr\Attr\Field\Numeric) {
                    $rule->numeric();
                } elseif ($instance instanceof \Fzr\Attr\Field\Integer) {
                    $rule->integer();
                } elseif ($instance instanceof \Fzr\Attr\Field\Url) {
                    $rule->url();
                } elseif ($instance instanceof \Fzr\Attr\Field\Regex) {
                    $rule->regex($instance->pattern);
                } elseif ($instance instanceof \Fzr\Attr\Field\Between) {
                    $rule->between($instance->min, $instance->max);
                } elseif ($instance instanceof \Fzr\Attr\Field\Confirmed) {
                    $rule->confirmed();
                } elseif ($instance instanceof \Fzr\Attr\Field\Date) {
                    $rule->date();
                } elseif ($instance instanceof \Fzr\Attr\Field\Custom) {
                    $method = $instance->method;
                    $rule->with([$model, $method]);
                }
            }
            if (!$rule->getType() && str_contains(strtolower($name), 'password')) {
                $rule->password();
            }
        }
        return $this;
    }

    public function sync(Model $model, ?array $fields = null): self
    {
        $ref = new \ReflectionClass($model);
        $targetFields = $fields ?? $this->keyList();
        foreach ($targetFields as $key) {
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                if ($prop->isPublic()) {
                    $model->$key = $this->get($key);
                }
            }
        }
        return $this;
    }

    public function setRules(callable $closure): void
    {
        $closure = $closure->bindTo($this, self::class);
        $closure();
    }

    public function rule(string $key, ?string $label = null): FormValidator
    {
        if (!isset($this->validators[$key])) {
            $this->validators[$key] = new FormValidator($key, $label ?? $key, $this);
        }
        return $this->validators[$key];
    }

    public function file(string $key): ?array
    {
        if (!isset($this->data[$key])) {
            $file = Request::file($key);
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $this->data[$key] = $file;
            }
        }
        return $this->data[$key] ?? null;
    }

    public function get(string $key, $default = null): mixed
    {
        if (!isset($this->data[$key])) {
            $file = Request::file($key);
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $this->data[$key] = $file;
            }
        }
        return parent::get($key, $default);
    }

    public function label(string $key): string
    {
        if (isset($this->validators[$key])) {
            return $this->validators[$key]->getLabel();
        }
        return $key;
    }

    public function getCsrfToken(): ?string
    {
        return $this->csrfToken;
    }
    public function csrfToken(): ?string
    {
        return $this->getCsrfToken();
    }

    public function csrfTag(): string
    {
        return '<input type="hidden" name="' . h(Security::csrfTokenName()) . '" value="' . h((string)$this->getCsrfToken()) . '">';
    }

    public function validate(): bool
    {
        $ok = true;
        $this->errors = [];
        if ($this->boundModel !== null) {
            $this->preSyncModel($this->boundModel);
        }
        foreach ($this->validators as $key => $validator) {
            if (! $validator->validate($this->get($key))) {
                $ok = false;
                $this->addError($validator->getLastMessage(), $key);
            }
        }
        return $ok;
    }

    private function preSyncModel(Model $model): void
    {
        $ref = new \ReflectionClass($model);
        foreach ($this->keyList() as $key) {
            if (!$ref->hasProperty($key)) {
                continue;
            }
            $prop = $ref->getProperty($key);
            if (!$prop->isPublic()) {
                continue;
            }
            try {
                $model->$key = $this->get($key);
            } catch (\TypeError) {
            }
        }
    }

    public function addError(array|string $text, ?string $key = null): void
    {
        if ($text === '' || $text === []) {
            return;
        }
        foreach ((array)$text as $t) {
            $this->errors[$key ?? self::ERROR_GLOBAL][] = $t;
        }
    }

    public function hasError(?string $key = null): bool
    {
        if ($key !== null) {
            return !empty($this->errors[$key]);
        }
        return !empty($this->errors);
    }

    /**
     * エラー取得
     *
     * @return array $key 指定時はそのフィールドのメッセージ一覧、無指定時は [field => [msg, ...]] 全体
     */
    public function getErrors(?string $key = null): array
    {
        if ($key !== null) {
            return $this->errors[$key] ?? [];
        }
        return $this->errors;
    }

    /** 先頭のエラーメッセージ（$key 指定でフィールドを絞る。無ければ null） */
    public function firstError(?string $key = null): ?string
    {
        if ($key !== null) {
            return $this->errors[$key][0] ?? null;
        }
        foreach ($this->errors as $msgs) {
            return $msgs[0] ?? null;
        }
        return null;
    }

    public function removeError(?string $key = null): void
    {
        if ($key) {
            unset($this->errors[$key]);
        } else {
            $this->errors = [];
        }
    }

    /**
     * 全エラーを Message へ送出する（フィールド情報を維持）
     *
     * view 再表示・redirect どちらでも、表示側は Message::field() /
     * Message::globals() で同じように取得できる。
     *
     * @param string|null $summary 概括メッセージ（エラーがあるときグローバルへ1件追加）
     */
    public function toMessage(?string $summary = null): void
    {
        foreach ($this->errors as $field => $msgs) {
            foreach ($msgs as $msg) {
                Message::error($msg, $field === self::ERROR_GLOBAL ? null : $field);
            }
        }
        if ($summary !== null && $this->hasError()) {
            Message::error($summary);
        }
    }

}
