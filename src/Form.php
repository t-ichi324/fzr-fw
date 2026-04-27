<?php

namespace Fzr;

/**
 * Form Management — entry point for creating and processing web forms.
 *
 * Use to handle user input validation and form rendering in a unified way.
 * Typical uses: processing contact forms, handling user registration, data entry interfaces.
 *
 * - Acts as a factory and container for {@see FormValidator} and {@see FormRender}.
 * - Simplifies the "bind-validate-render" workflow.
 * - Supports mass-assignment from request data via `from()`.
 * - Extends {@see Bag} to hold validated or raw input data.
 */
class Form extends Bag
{
    protected array $validators = [];
    protected array $errors = [];
    protected ?string $csrfToken = null;
    protected ?Model $boundModel = null;

    public static function fromRequest(): self
    {
        return new self($_REQUEST);
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
            if (Request::isJsonRequest()) {
                $raw = @file_get_contents("php://input");
                $json = json_decode($raw ?: '', true);
                $this->data = is_array($json) ? $json : [];
            } else {
                $this->data = array_merge($_GET, $_POST);
            }
        }
        $this->csrfToken = Security::getCsrfToken();
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
        $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        return '<input type="hidden" name="' . h($key) . '" value="' . h((string)$this->getCsrfToken()) . '">';
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

    public function addError(array|string $text, ?string $key = null)
    {
        if ($text === "") {
            return;
        }
        if (is_array($text)) {
            foreach ($text as $t) {
                $this->addError($t, $key);
            }
        } else {
            if ($key) {
                $this->errors[$key][] = $text;
            } else {
                $this->errors[] = $text;
            }
        }
    }

    public function hasError(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(?string $key = null): array
    {
        if ($key) {
            return $this->errors[$key] ?? [];
        }
        $all = [];
        foreach ($this->errors as $k => $v) {
            if (is_array($v)) {
                $all = array_merge($all, $v);
            } else {
                $all[] = $v;
            }
        }
        return $all;
    }

    public function removeError(?string $key = null): void
    {
        if ($key) {
            unset($this->errors[$key]);
        } else {
            $this->errors = [];
        }
    }

    public function flashError(?string $errorMsg = null, ?string $successMsg = null): void
    {
        if ($this->hasError()) {
            if ($errorMsg) {
                Message::error($errorMsg);
            } else {
                foreach ($this->getErrors() as $err) {
                    Message::error($err);
                }
            }
        } elseif ($successMsg) {
            Message::success($successMsg);
        }
    }

    public function tag(string $key): FormRender
    {
        return new FormRender($key, $this);
    }
    public function __invoke(string $key): FormRender
    {
        return $this->tag($key);
    }
}
