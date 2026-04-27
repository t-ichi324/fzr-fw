<?php
namespace Fzr;

/**
 * Form Renderer — generates HTML form elements with automatic state and error handling.
 *
 * Use to output HTML input fields that preserve user data and display validation errors.
 * Typical uses: generating `<input>`, `<select>`, and `<textarea>` tags in view templates.
 *
 * - Automatically populates values from a source data object (via {@see Form}).
 * - Injects CSS error classes and error messages when validation fails.
 * - Simplifies form boilerplate by handling attributes, IDs, and labels.
 */
class FormRender
{
    protected string $key;
    protected Form $form;
    public function __construct(string $key, Form $form)
    {
        $this->key = $key;
        $this->form = $form;
    }

    public function val(): mixed
    {
        return $this->form->get($this->key);
    }
    public function value(): mixed
    {
        return $this->val();
    }
    public function hasError(): bool
    {
        return !empty($this->form->getErrors($this->key));
    }

    public function error(): string
    {
        $errs = $this->form->getErrors($this->key);
        return empty($errs) ? '' : h($errs[0]);
    }

    public function label(array $attrs = []): string
    {
        $attrStr = $this->buildAttributes($attrs);
        $text = h($this->form->label($this->key));
        return "<label{$attrStr}>{$text}</label>";
    }

    public function input(string $type = 'text', array $attrs = []): string
    {
        $attrs['name'] = $this->key;
        if (!isset($attrs['value']) && $type !== 'password') {
            $attrs['value'] = $this->val();
        }
        if ($type === 'text') {
            $rule = clone $this->form->rule($this->key);
            if ($rule->getType()) {
                $type = $rule->getType();
            }
        }
        $attrStr = $this->buildAttributes($attrs);
        return "<input type=\"{$type}\"{$attrStr}>";
    }

    public function textarea(array $attrs = []): string
    {
        $attrs['name'] = $this->key;
        $val = $this->val();
        $valStr = is_array($val) ? implode("\n", $val) : (string)$val;
        $attrStr = $this->buildAttributes($attrs);
        return "<textarea{$attrStr}>" . h($valStr) . "</textarea>";
    }

    public function select(array $options, array $attrs = []): string
    {
        $attrs['name'] = $this->key;
        $attrStr = $this->buildAttributes($attrs);
        $selected = (string)$this->val();
        $html = "<select{$attrStr}>";
        foreach ($options as $k => $v) {
            $sel = ((string)$k === $selected) ? ' selected' : '';
            $html .= "<option value=\"" . h((string)$k) . "\"{$sel}>" . h((string)$v) . "</option>";
        }
        return $html . "</select>";
    }

    private function buildAttributes(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $k => $v) {
            if ($v === true) {
                $html .= " {$k}";
            } elseif ($v !== false && $v !== null) {
                $html .= " {$k}=\"" . h((string)$v) . "\"";
            }
        }
        return $html;
    }

    public function __toString(): string
    {
        $val = $this->val();
        return is_array($val) ? implode(', ', $val) : (string)$val;
    }
}
