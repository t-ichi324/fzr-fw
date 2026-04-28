<?php

namespace Fzr;

/**
 * Breadcrumb Navigator — manages the hierarchical trail of the current page.
 *
 * This version supports automatic home insertion, semantic HTML5 output,
 * and Schema.org Microdata for SEO.
 */
class Breadcrumb
{
    private static array $items = [];
    private static bool $autoHome = true;
    private static string $homeLabel = 'Home';
    private static string $homeUrl = '/';

    /**
     * 自動でホームを挿入するかどうかを設定
     */
    public static function configure(bool $autoHome = true, string $homeLabel = 'Home', string $homeUrl = '/'): void
    {
        self::$autoHome = $autoHome;
        self::$homeLabel = $homeLabel;
        self::$homeUrl = $homeUrl;
    }

    /**
     * 階層を追加
     *
     * @param string $label 表示名
     * @param string|null $url URL（null の場合はリンクなしの現在地として扱う）
     * @return static
     */
    public static function add(string $label, ?string $url = null): string
    {
        self::$items[] = [
            'label' => $label,
            'url'   => $url ? Url::get($url) : null,
        ];
        return static::class;
    }

    /**
     * ホームを明示的に追加
     *
     * @param string|null $label ホームの表示名（nullならデフォルトを使用）
     * @return static
     */
    public static function home(?string $label = null): string
    {
        self::add($label ?? self::$homeLabel, self::$homeUrl);
        return static::class;
    }

    /**
     * 全アイテム取得（自動ホーム処理を含む）
     */
    public static function all(): array
    {
        $items = self::$items;

        // 自動ホームが有効で、かつ先頭がルートでない場合に挿入
        if (self::$autoHome) {
            $rootUrl = Url::get(self::$homeUrl);
            $hasHome = false;
            foreach ($items as $item) {
                if ($item['url'] === $rootUrl) {
                    $hasHome = true;
                    break;
                }
            }

            if (!$hasHome) {
                array_unshift($items, [
                    'label' => self::$homeLabel,
                    'url'   => $rootUrl,
                ]);
            }
        }

        return $items;
    }

    /** 後方互換用 */
    public static function get(): array
    {
        return self::all();
    }

    /** 最後の一件を取得 */
    public static function last(): ?array
    {
        $items = self::all();
        return !empty($items) ? $items[count($items) - 1] : null;
    }

    public static function clear(): void
    {
        self::$items = [];
    }

    public static function has(): bool
    {
        $items = self::all();
        return !empty($items);
    }

    /** 
     * HTML出力 (SEO & アクセシビリティ対応)
     */
    public static function render(string $wrapClass = 'breadcrumb'): string
    {
        $items = self::all();
        if (empty($items)) return '';

        $html = '<nav aria-label="Breadcrumb" class="' . h($wrapClass) . '">';
        $html .= '<ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

        $lastIndex = count($items) - 1;
        foreach ($items as $i => $item) {
            $label = h($item['label']);
            $position = $i + 1;

            $html .= '<li class="breadcrumb-item' . ($i === $lastIndex ? ' active' : '') . '" ';
            $html .= 'itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

            if ($i === $lastIndex || $item['url'] === null) {
                $html .= '<span itemprop="name">' . $label . '</span>';
            } else {
                $url = h($item['url']);
                $html .= '<a itemprop="item" href="' . $url . '"><span itemprop="name">' . $label . '</span></a>';
            }

            $html .= '<meta itemprop="position" content="' . $position . '">';
            $html .= '</li>';
        }

        $html .= '</ol>';
        $html .= '</nav>';

        return $html;
    }
}
