<?php
namespace Fzr\Db;

use Fzr\Collection;

/**
 * Database Query Result — encapsulates paginated search results and metadata.
 *
 * Use to handle the output of `Query::page()` or `Db::page()`.
 * Typical uses: displaying list views with pagination controls, JSON-returning API results.
 *
 * - Holds the current page data (rows) and total record count.
 * - Calculates total pages, next/previous page numbers, and item offsets.
 * - Generates HTML pagination links compatible with modern CSS frameworks.
 *
 * @template T of object
 * @extends Collection<int, T>
 */
class Result extends Collection
{
    /** 総件数 */
    public int $itemCount = 0;
    /** 現在ページ */
    public int $currentPage = 1;
    /** 1ページあたり件数 */
    public int $perPage = 20;
    /** 最大ページ数 */
    public int $maxPage = 1;

    /**
     * @param array<int, T>|Collection<int, T> $items
     * @param int $itemCount 総件数（0 の場合は items 件数を使用）
     * @param int $currentPage 現在ページ（1始まり）
     * @param int $perPage 1ページあたりの件数
     */
    public function __construct($items = [], int $itemCount = 0, int $currentPage = 1, int $perPage = 20)
    {
        parent::__construct($items);
        $this->itemCount = $itemCount ?: $this->count();
        $this->currentPage = max(1, $currentPage);
        $this->perPage = $perPage;
        $this->maxPage = ($perPage > 0)
            ? (int)max(1, ceil($this->itemCount / $perPage))
            : ($this->itemCount > 0 ? 1 : 0);
    }

    /** 空判定（総件数ベース） */
    public function isEmpty(): bool
    {
        return $this->itemCount === 0;
    }

    /** 非空判定 */
    public function isNotEmpty(): bool
    {
        return $this->itemCount !== 0;
    }

    /** 前ページ存在判定 */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /** 次ページ存在判定 */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->maxPage;
    }

    /** 開始アイテム番号（1〜） */
    public function firstItemIndex(): int
    {
        return $this->itemCount === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
    }

    /** 終了アイテム番号（1〜） */
    public function lastItemIndex(): int
    {
        return $this->itemCount === 0 ? 0 : min($this->firstItemIndex() + $this->perPage - 1, $this->itemCount);
    }

    /** 総件数取得（$itemCount プロパティの別名） */
    public function total(): int
    {
        return $this->itemCount;
    }

    /** 総行数（Collection の count と同じ） */
    public function rowCount(): int
    {
        return $this->count();
    }

    /** ページネーションリンク生成 */
    public function links(string $baseUrl = '?', string $pageParam = 'page', int $range = 5): string
    {
        if ($this->maxPage <= 1) return '';
        $html = '<nav class="pagination">';
        $sep = (strpos($baseUrl, '?') === false) ? '?' : '&';
        if ($this->hasPrev()) $html .= '<a href="' . h($baseUrl) . $sep . h($pageParam) . '=' . ($this->currentPage - 1) . '">&laquo;</a> ';
        $start = max(1, $this->currentPage - $range);
        $end   = min($this->maxPage, $this->currentPage + $range);
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $this->currentPage) ? ' class="active"' : '';
            $html .= '<a href="' . h($baseUrl) . $sep . h($pageParam) . '=' . $i . '"' . $active . '>' . $i . '</a> ';
        }
        if ($this->hasNext()) $html .= '<a href="' . h($baseUrl) . $sep . h($pageParam) . '=' . ($this->currentPage + 1) . '">&raquo;</a>';
        $html .= '</nav>';
        return $html;
    }
}
