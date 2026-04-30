<?php

/**
 * Fzr グローバルヘルパー関数
 * composer autoload.files で自動読み込み
 */

if (!function_exists('abort')) {
    /** HTTPエラー送出 */
    function abort(int $code, string $message = ''): never
    {
        throw new \Fzr\HttpException($code, $message);
    }
}

if (!function_exists('h')) {
    /** HTMLエスケープ */
    function h(?string $str, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, $encoding);
    }
}

if (!function_exists('e')) {
    /** HTMLエスケープ（エイリアス） */
    function e(?string $str): string
    {
        return h($str);
    }
}

if (!function_exists('collect')) {
    /** * コレクション作成 
     * @template TKey of array-key
     * @template T
     * @param array<TKey, T>|Collection<TKey, T> $items
     * @return Collection<TKey, T>
     */
    function collect($items = []): Collection
    {
        return new Collection($items);
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
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit;
    }
}

if (!function_exists('dump')) {
    /** デバッグダンプ */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
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
