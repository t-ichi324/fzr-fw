<?php

namespace Fzr;

/**
 * View Renderer — handles the compilation and rendering of PHP templates.
 *
 * Use to generate HTML output from view files.
 * Typical uses: rendering page layouts, partials, and dynamic content.
 *
 * - Supports template inheritance and layout wrapping via `Response`.
 * - Handles data extraction into the local scope of the view file.
 * - Can be extended with custom render engines (e.g., Twig, Blade) via `setRenderer()`.
 * - Provides global data storage for cross-view variables.
 */
class Render
{
    private static string $content = '';
    private static string $title = '';
    private static array $data = [];
    private static bool $is_partial = false;
    /** @var callable|null */
    private static mixed $renderer = null;

    /**
     * カスタムレンダラーを登録する。
     * 設定されている場合、getTemplate() はこの関数に処理を委譲する。
     * fn(string $template, array $data): string
     *
     * 例（Twig）:
     *   Render::setRenderer(fn($t, $d) => $twig->render($t . '.twig', $d));
     */
    public static function setRenderer(callable $fn): void
    {
        self::$renderer = $fn;
    }

    public static function setTitle(string $title)
    {
        self::$title = $title;
    }
    public static function getTitle(): string
    {
        return self::$title;
    }

    public static function setPartial(bool $flag)
    {
        self::$is_partial = $flag;
    }
    public static function isPartial(): bool
    {
        return self::$is_partial;
    }

    /**
     * ビューで使用する変数を設定します。
     * 
     * @param string|array $key 変数名または [変数名 => 値] の配列
     * @param mixed $value 値（$key が配列の場合は無視されます）
     */
    public static function set(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            self::$data = array_merge(self::$data, $key);
        } else {
            self::$data[$key] = $value;
        }
    }

    public static function setData(string $key, mixed $value)
    {
        self::set($key, $value);
    }
    /**
     * 設定された変数を取得します。
     * 
     * @template T
     * @param string $key 変数名
     * @param T $default デフォルト値
     * @return T
     */
    public static function getData(string $key, mixed $default = null)
    {
        return self::$data[$key] ?? $default;
    }
    public static function getAllData(): array
    {
        return self::$data;
    }
    public static function clearData(): void
    {
        self::$data = [];
    }

    /** コンテンツ設定 */
    public static function setContent(string $content)
    {
        self::$content = $content;
    }
    public static function getContent(): string
    {
        return self::$content;
    }

    /** テンプレート取得（バッファリング） */
    public static function getTemplate(string $template): string
    {
        if (self::$renderer !== null) {
            return (self::$renderer)($template, self::$data);
        }
        $viewFile = Path::view($template . '.php');
        if (!file_exists($viewFile)) {
            Logger::warning("View not found: $template ($viewFile)");
            return '';
        }
        ob_start();
        extract(self::$data, EXTR_SKIP);
        include $viewFile;
        return ob_get_clean();
    }

    /** テンプレート存在確認 */
    public static function hasTemplate(string $template): bool
    {
        return file_exists(Path::view($template . '.php'));
    }

    /** パーシャルテンプレート出力 */
    public static function include(string $template, array $data = [])
    {
        $viewFile = Path::view($template . '.php');
        if (!file_exists($viewFile)) return;
        $merged = array_merge(self::$data, $data);
        extract($merged, EXTR_SKIP);
        include $viewFile;
    }
}
