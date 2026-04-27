<?php

namespace Fzr;

/**
 * Form Validator — enforces data integrity and business rules on input.
 *
 * Use to validate arrays of data (typically from HTTP requests).
 * Typical uses: checking required fields, validating email formats, enforcing size limits.
 *
 * - Provides a wide range of built-in validation rules (required, email, numeric, etc.).
 * - Supports custom error messages and localized labels.
 * - Returns a structured list of validation errors for use in UI rendering.
 */
class FormValidator
{
    protected string $key;
    protected string $label;
    protected Form $form;
    protected array $rules = [];
    protected array $customs = [];
    protected ?string $type = null;
    protected ?string $lastMessage = null;

    public function __construct(string $key, string $label, Form $form)
    {
        $this->key = $key;
        $this->label = $label;
        $this->form = $form;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    public function getType(): ?string
    {
        return $this->type;
    }

    public function required(?string $message = null): static
    {
        $this->rules['required'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function email(?string $message = null): static
    {
        $this->rules['email'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function min(int $min, ?string $message = null): static
    {
        $this->rules['min'] = ['param' => $min, 'message' => $message];
        return $this;
    }
    public function max(int $max, ?string $message = null): static
    {
        $this->rules['max'] = ['param' => $max, 'message' => $message];
        return $this;
    }
    public function minValue(float|int $min, ?string $message = null): static
    {
        $this->rules['minValue'] = ['param' => $min, 'message' => $message];
        return $this;
    }
    public function maxValue(float|int $max, ?string $message = null): static
    {
        $this->rules['maxValue'] = ['param' => $max, 'message' => $message];
        return $this;
    }
    public function password(?string $message = null): static
    {
        $this->rules['password'] = ['param' => true, 'message' => $message];
        $this->type = 'password';
        return $this;
    }
    public function numeric(?string $message = null): static
    {
        $this->rules['numeric'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function integer(?string $message = null): static
    {
        $this->rules['integer'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function url(?string $message = null): static
    {
        $this->rules['url'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function regex(string $pattern, ?string $message = null): static
    {
        $this->rules['regex'] = ['param' => $pattern, 'message' => $message];
        return $this;
    }
    public function between(float|int $min, float|int $max, ?string $message = null): static
    {
        $this->rules['between'] = ['param' => [$min, $max], 'message' => $message];
        return $this;
    }
    public function confirmed(?string $message = null): static
    {
        $this->rules['confirmed'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function date(?string $message = null): static
    {
        $this->rules['date'] = ['param' => true, 'message' => $message];
        return $this;
    }

    /**
     * カスタムバリデーターを追加する。
     *
     * $fn は (mixed $value): bool|string を返す callable。
     * - true を返す: バリデーション成功
     * - string を返す: エラーメッセージ（:field 置換あり）
     * - false を返す: $message をエラーメッセージとして使用
     */
    public function with(callable $fn, ?string $message = null): static
    {
        $this->customs[] = ['fn' => $fn, 'message' => $message];
        return $this;
    }

    /** File Validation */
    public function file(?string $message = null): static
    {
        $this->rules['file'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function image(?string $message = null): static
    {
        $this->rules['image'] = ['param' => true, 'message' => $message];
        return $this;
    }
    public function maxSize(string $size, ?string $message = null): static
    {
        $this->rules['maxSize'] = ['param' => $size, 'message' => $message];
        return $this;
    }
    public function mimes(array|string $exts, ?string $message = null): static
    {
        $this->rules['mimes'] = ['param' => (array)$exts, 'message' => $message];
        return $this;
    }

    public function getLastMessage(): ?string
    {
        return $this->lastMessage;
    }

    protected function msg(string $rule, array $vars, ?string $customMessage): string
    {
        if ($customMessage) {
            $template = $customMessage;
        } else {
            $envKey = "validation." . $rule;
            $defaultMessages = [
                'required'   => ':field is required.',
                'max'        => ':field must be at most :max characters.',
                'min'        => ':field must be at least :min characters.',
                'maxValue'   => ':field must be at most :max.',
                'minValue'   => ':field must be at least :min.',
                'email'      => ':field must be a valid email address.',
                'password'   => ':field is invalid.',
                'numeric'    => ':field must be numeric.',
                'integer'    => ':field must be an integer.',
                'url'        => ':field must be a valid URL.',
                'regex'      => ':field format is invalid.',
                'between'    => ':field must be between :min and :max.',
                'confirmed'  => ':field confirmation does not match.',
                'date'       => ':field must be a valid date.',
                'file'       => ':field must be a valid file.',
                'image'      => ':field must be an image.',
                'maxSize'    => ':field size must be at most :size.',
                'mimes'      => ':field must be one of: :exts.',
                'custom'     => ':field is invalid.',
            ];
            $template = Env::get($envKey, $defaultMessages[$rule] ?? $rule);
        }
        $vars['field'] = $this->label;
        foreach ($vars as $k => $v) {
            $template = str_replace(':' . $k, (string)$v, $template);
        }
        return $template;
    }

    public function validate(mixed $value): bool
    {
        $this->lastMessage = null;
        $skip = ($value === null || $value === '');
        foreach ($this->rules as $rule => $data) {
            $param = $data['param'];
            $msg = $data['message'];
            $error = match ($rule) {
                'required'   => $skip ? $this->msg('required', [], $msg) : null,
                'max'        => (!$skip && is_string($value) && mb_strlen($value) > $param) ? $this->msg('max', ['max' => $param], $msg) : null,
                'min'        => (!$skip && is_string($value) && mb_strlen($value) < $param) ? $this->msg('min', ['min' => $param], $msg) : null,
                'maxValue'   => (!$skip && (!is_numeric($value) || (float)$value > $param)) ? $this->msg('maxValue', ['max' => $param], $msg) : null,
                'minValue'   => (!$skip && (!is_numeric($value) || (float)$value < $param)) ? $this->msg('minValue', ['min' => $param], $msg) : null,
                'email'      => (!$skip && !filter_var($value, FILTER_VALIDATE_EMAIL)) ? $this->msg('email', [], $msg) : null,
                'numeric'    => (!$skip && !is_numeric($value)) ? $this->msg('numeric', [], $msg) : null,
                'integer'    => (!$skip && !preg_match('/^-?[0-9]+$/', (string)$value)) ? $this->msg('integer', [], $msg) : null,
                'url'        => (!$skip && !filter_var($value, FILTER_VALIDATE_URL)) ? $this->msg('url', [], $msg) : null,
                'regex'      => (!$skip && !preg_match($param, (string)$value)) ? $this->msg('regex', [], $msg) : null,
                'between'    => (!$skip && (!is_numeric($value) || (float)$value < $param[0] || (float)$value > $param[1])) ? $this->msg('between', ['min' => $param[0], 'max' => $param[1]], $msg) : null,
                'confirmed'  => ($value !== ($this->form->get($this->key . '_confirmation'))) ? $this->msg('confirmed', [], $msg) : null,
                'date'       => (!$skip && strtotime((string)$value) === false) ? $this->msg('date', [], $msg) : null,
                'file'       => (!$skip && (!is_array($value) || !isset($value['tmp_name']) || !is_uploaded_file($value['tmp_name']))) ? $this->msg('file', [], $msg) : null,
                'image'      => (!$skip && (!is_array($value) || !isset($value['tmp_name']) || @getimagesize($value['tmp_name']) === false)) ? $this->msg('image', [], $msg) : null,
                'maxSize'    => (!$skip && is_array($value) && isset($value['size']) && $value['size'] > $this->parseSize($param)) ? $this->msg('maxSize', ['size' => $param], $msg) : null,
                'mimes'      => (!$skip && is_array($value) && isset($value['name']) && !in_array(strtolower(pathinfo($value['name'], PATHINFO_EXTENSION)), array_map('strtolower', $param))) ? $this->msg('mimes', ['exts' => implode(',', $param)], $msg) : null,
                default      => null,
            };
            if ($error) {
                $this->lastMessage = $error;
                return false;
            }
        }
        foreach ($this->customs as $custom) {
            if ($skip) {
                continue;
            }
            $result = ($custom['fn'])($value);
            if ($result !== true) {
                $error = is_string($result)
                    ? str_replace(':field', $this->label, $result)
                    : ($custom['message'] ?? $this->msg('custom', [], null));
                $this->lastMessage = $error;
                return false;
            }
        }
        return true;
    }

    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $val = (int)$size;
        return match ($unit) {
            'k' => $val * 1024,
            'm' => $val * 1024 * 1024,
            'g' => $val * 1024 * 1024 * 1024,
            default => $val,
        };
    }
}
