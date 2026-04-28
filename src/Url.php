<?php

namespace Fzr;

/**
 * URL Generator — creates application-internal URLs with base path handling.
 *
 * Use to generate links to other pages or assets within the application.
 * Typical uses: generating href attributes, building redirect targets, asset URL resolution.
 *
 * - Automatically prepends the `APP_BASE` path for portable installations.
 * - Supports relative and absolute URL generation.
 * - Simple static interface: `Url::to('/path')`, `Url::base()`.
 */
class Url
{
    private static ?string $root = null;

    public static function init(string $root): void
    {
        self::$root = $root;
    }

    public static function root(): string
    {
        return self::$root ?? Request::url_root();
    }

    /** URL生成（相対パスを絶対化） */
    public static function get(...$parts): string
    {
        $pathParts = [];
        $query = [];

        foreach ($parts as $p) {
            if (is_array($p)) {
                $query = array_merge($query, $p);
            } elseif (is_string($p) || is_numeric($p)) {
                $p = (string)$p;
                // 途中に絶対URLが来たら、それまでのパスをリセット
                if (self::isAbsolute($p)) {
                    $pathParts = [$p];
                } else {
                    $pathParts[] = $p;
                }
            }
        }

        // パスの結合と正規化
        $url = self::combinePaths($pathParts);

        // クエリパラメータの付与
        if (!empty($query)) {
            $url = self::addQuery($url, $query);
        }

        return $url;
    }

    /** パスパーツの結合と正規化 (.. や . の解決) */
    private static function combinePaths(array $parts): string
    {
        if (empty($parts)) return self::root();

        $first = $parts[0];
        $isAbsolute = self::isAbsolute($first);
        
        $resolved = [];
        foreach ($parts as $p) {
            $p = str_replace('\\', '/', $p);
            // スキーム(https://)を一時退避
            $scheme = '';
            if (preg_match('#^([a-z]+://[^/]+)(.*)#i', $p, $matches)) {
                $scheme = $matches[1];
                $p = $matches[2];
            }
            
            foreach (explode('/', $p) as $part) {
                if ($part === '' || $part === '.') continue;
                if ($part === '..') {
                    array_pop($resolved);
                } else {
                    $resolved[] = $part;
                }
            }
            
            if ($scheme) {
                $resolved = [$scheme . '/' . implode('/', $resolved)];
            }
        }

        $result = implode('/', $resolved);
        if ($isAbsolute && !str_contains($result, '://')) {
            return '/' . ltrim($result, '/');
        }
        
        // 相対パスの場合はrootをベースにする
        if (!self::isAbsolute($result)) {
            return rtrim(self::root(), '/') . '/' . ltrim($result, '/');
        }

        return $result;
    }

    /** クエリパラメータの付与 */
    public static function addQuery(string $url, array $params): string
    {
        $parse = parse_url($url) ?: [];
        $query = [];
        if (!empty($parse['query'])) {
            parse_str($parse['query'], $query);
        }
        $query = array_merge($query, $params);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        
        $base = ($parse['scheme'] ?? '') ? ($parse['scheme'] . '://' . ($parse['host'] ?? '') . ($parse['port'] ? ':' . $parse['port'] : '')) : '';
        $path = $parse['path'] ?? '';
        
        return $base . $path . ($queryString ? '?' . $queryString : '');
    }

    /** 絶対URL判定 */
    public static function isAbsolute(string $url): bool
    {
        return str_starts_with($url, '/') || preg_match('#^https?://#i', $url);
    }

    /** @see get() */
    public static function to(...$parts): string
    {
        return self::get(...$parts);
    }

    /** 静的リソースURL（キャッシュバスト） */
    public static function asset(string $path, ?string $version = null): string
    {
        $url = self::get($path);
        $version ??= Env::get('app.assets_version', '');
        if ($version) {
            $url .= '?v=' . $version;
        }
        return $url;
    }

    /** 現在のURL（クエリなし）をベースにしたURL生成 */
    public static function current(...$parts): string
    {
        return self::get(Request::url_without_query(), ...$parts);
    }

    /** APIエンドポイントのURL生成 */
    public static function api(...$parts): string
    {
        $prefix = Env::get('api_prefix', 'api');
        return self::get(self::root($prefix), ...$parts);
    }
}
