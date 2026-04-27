<?php
namespace Fzr;

/**
 * Breadcrumb Helper — manages navigational breadcrumb trails.
 *
 * Use to build and render breadcrumb links for better UI navigation.
 * Typical uses: site-wide navigation bars, deep-linking indicators.
 *
 * - Provides a static interface to accumulate navigation steps.
 * - Supports automatic HTML rendering with customizable tags and separators.
 * - Automatically escapes output and handles URL generation via {@see Url}.
 */
class Breadcrumb {
    private static array $items = [];

    public static function add(string $label, ?string $url = null): void {
        self::$items[] = ['label' => $label, 'url' => $url];
    }

    public static function get(): array {
        return self::$items;
    }

    public static function clear(): void {
        self::$items = [];
    }

    public static function has(): bool {
        return !empty(self::$items);
    }

    /** HTML出力 */
    public static function render(string $separator = ' &gt; ', string $wrapTag = 'nav', string $wrapClass = 'breadcrumb'): string {
        if (empty(self::$items)) return '';
        $html = [];
        $last = count(self::$items) - 1;
        foreach (self::$items as $i => $item) {
            if ($i === $last || $item['url'] === null) {
                $html[] = '<span>' . h($item['label']) . '</span>';
            } else {
                $html[] = '<a href="' . h(Url::get($item['url'])) . '">' . h($item['label']) . '</a>';
            }
        }
        return "<{$wrapTag} class=\"{$wrapClass}\">" . implode($separator, $html) . "</{$wrapTag}>";
    }
}
