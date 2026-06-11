<?php

/**
 * Fzr グローバルヘルパー関数
 * composer autoload.files で自動読み込み
 */

use Fzr\Collection;

if (!function_exists('abort')) {
    /** HTTPエラー送出 */
    function abort(int $code, string $message = ''): never
    {
        throw new \Fzr\HttpException($message ?: null, $code);
    }
}

if (!function_exists('h')) {
    /** HTMLエスケープ */
    function h(string|int|float|null $str, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, $encoding);
    }
}

if (!function_exists('old')) {
    /** 直前の送信値を HTML エスケープ済みで取得（バリデーション失敗後の value 再表示用） */
    function old(string $key, ?string $default = ''): string {
        return h(\Fzr\Request::inputStr($key, $default ?? ''));
    }
}

if (!function_exists('err')) {
    /** フィールドエラーを <p> タグで出力（Message::field() ベース。無ければ空文字） */
    function err(string $key, string $className = 'form-error'): string {
        $msg = \Fzr\Message::field($key);
        return $msg === null ? '' : '<p class="' . h($className) . '">' . h($msg) . '</p>';
    }
}

if (!function_exists('hasErr')) {
    /** フィールドエラーがあれば CSS クラス文字列（先頭スペース付き）を返す */
    function hasErr(string $key, string $className = ' has-error'): string {
        return \Fzr\Message::hasField($key) ? $className : '';
    }
}


if (!function_exists('collect')) {
    /** * コレクション作成 
     * @template TKey of array-key
     * @template T
     * @param array<TKey, T>|\Fzr\Collection<TKey, T> $items
     * @return \Fzr\Collection<TKey, T>
     */
    function collect($items = []): \Fzr\Collection
    {
        return new \Fzr\Collection($items);
    }
}

if (!function_exists('url')) {
    /** URL生成 */
    function url(string $path = ''): string
    {
        return \Fzr\Url::get($path);
    }
}

if (!function_exists('env')) {
    /** 環境設定取得 */
    function env(string $key, ?string $default = null): ?string
    {
        return \Fzr\Env::get($key, $default);
    }
}

if (!function_exists('csrf_field')) {
    /** CSRFフィールドHTML */
    function csrf_field(): string
    {
        return \Fzr\Security::csrfField();
    }
}

if (!function_exists('csrf_token')) {
    /** CSRFトークン値 */
    function csrf_token(): string
    {
        return \Fzr\Security::getCsrfToken();
    }
}

if (!function_exists('redirect')) {
    /** リダイレクト応答生成 */
    function redirect(string $url): array
    {
        return \Fzr\Response::redirect($url);
    }
}

if (!function_exists('view')) {
    /** ビュー応答生成 */
    function view(string $template, array $data = [], ?string $baseTemplate = null): array
    {
        return \Fzr\Response::view($template, $data, $baseTemplate);
    }
}

if (!function_exists('json_response')) {
    /** JSON応答生成 */
    function json_response(array|object $data, ?int $code = null): array
    {
        return \Fzr\Response::json($data, $code);
    }
}

if (!function_exists('dd')) {
    /** デバッグダンプ＋終了 */
    function dd(mixed ...$vars): never
    {
        dump(...$vars);
        exit;
    }
}

if (!function_exists('dump')) {
    /** デバッグダンプ */
    function dump(mixed ...$vars): void
    {
        $isCli = PHP_SAPI === 'cli';
        foreach ($vars as $var) {
            if (!$isCli) echo '<pre>';
            var_dump($var);
            if (!$isCli) echo '</pre>';
        }
    }
}

if (!function_exists('url64_encode')) {
    /** URL-safe Base64 エンコード */
    function url64_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('url64_decode')) {
    /** URL-safe Base64 デコード */
    function url64_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('checked')) {
    /** checkbox/radio の checked 属性を条件に応じて出力 */
    function checked(bool $cond): string {
        return $cond ? ' checked' : '';
    }
}

if (!function_exists('selected')) {
    /** select option の selected 属性を条件に応じて出力 */
    function selected(bool $cond): string {
        return $cond ? ' selected' : '';
    }
}

if (!function_exists('disabled')) {
    /** 入力要素の disabled 属性を条件に応じて出力 */
    function disabled(bool $cond): string {
        return $cond ? ' disabled' : '';
    }
}

if (!function_exists('options')) {
    /** select 要素の option タグ列を生成（値・ラベル共に h() 済み） */
    function options(Collection|array $list, mixed $selected = null): string {
        $html = '';
        foreach ($list as $value => $label) {
            $html .= '<option value="' . h($value) . '"'
                   . selected((string)$value === (string)$selected)
                   . '>' . h($label) . '</option>';
        }
        return $html;
    }
}
