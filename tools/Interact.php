<?php

namespace Fzr\Tool;

/**
 * CLIツール用の対話型プロンプト機能を提供します。
 */
trait Interact
{
    /**
     * 文字列入力を受け取ります。
     */
    protected function ask(string $label, string $default = ''): string
    {
        $hint = $default !== '' ? " \033[2m[{$default}]\033[0m" : '';
        echo "  {$label}{$hint}: ";
        $val = trim(fgets(STDIN));
        return $val !== '' ? $val : $default;
    }

    /**
     * Yes/No 形式の入力を受け取ります。
     */
    protected function askBool(string $label, bool $default): bool
    {
        $hint = $default ? 'Y/n' : 'y/N';
        echo "  {$label} [{$hint}]: ";
        $val = strtolower(trim(fgets(STDIN)));
        if ($val === '') return $default;
        if (in_array($val, ['y', 'yes', '1', 'true', 'on'], true)) return true;
        if (in_array($val, ['n', 'no', '0', 'false', 'off'], true)) return false;
        return $default;
    }

    /**
     * 選択肢から入力を受け取ります。
     *
     * @param string[] $choices
     */
    protected function askChoice(string $label, array $choices, string $default): string
    {
        $display = [];
        foreach ($choices as $i => $choice) {
            $display[] = ($i + 1) . ':' . $choice;
        }
        $list = implode('/', $display);
        echo "  {$label} [{$list}] \033[2m[{$default}]\033[0m: ";
        $val = trim(fgets(STDIN));

        if ($val === '') return $default;

        // 1. 完全一致
        if (in_array($val, $choices, true)) return $val;

        // 2. 数値インデックス (1-based)
        if (is_numeric($val) && isset($choices[(int)$val - 1])) {
            return $choices[(int)$val - 1];
        }

        // 3. 頭文字 (大文字小文字無視)
        $lowerVal = strtolower($val);
        foreach ($choices as $choice) {
            if ($lowerVal === strtolower($choice[0])) {
                return $choice;
            }
        }

        if (method_exists($this, 'out')) {
            $this->out("\033[33m[WARN]\033[0m Invalid choice '{$val}', using default '{$default}'.");
        }
        return $default;
    }

    /**
     * 隠し入力を受け取ります（パスワード等）。
     */
    protected function askSecret(string $label): string
    {
        // Windows では stty が使えないため通常入力にフォールバック
        if (DIRECTORY_SEPARATOR === '\\') {
            echo "  {$label}: ";
            return trim(fgets(STDIN));
        }
        echo "  {$label}: ";
        system('stty -echo');
        $val = trim(fgets(STDIN));
        system('stty echo');
        echo PHP_EOL;
        return $val;
    }
}
