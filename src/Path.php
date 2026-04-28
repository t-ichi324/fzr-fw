<?php

namespace Fzr;

/**
 * Path Resolver — resolves absolute filesystem paths for various application directories.
 *
 * Use to access files on the server (logs, storage, views, etc.).
 * Typical uses: reading view files, writing logs, saving uploaded files to storage.
 *
 * - Based on the `APP_ROOT` constant.
 * - Provides helper methods for common subdirectories (`storage`, `log`, `view`, `temp`).
 * - Ensures consistent directory separators across different OS environments.
 */
class Path
{
    private static ?string $abs = null;

    public static function init(string $abs): void
    {
        self::$abs = $abs;
    }
    protected static function abs(): string
    {
        return self::$abs ?? (defined('ABSPATH') ? ABSPATH : dirname(__DIR__));
    }

    public static function get(...$path): string
    {
        $resolved = [];
        foreach ($path as $p) {
            if ($p === null || $p === '') continue;
            
            // スラッシュを統一して正規化
            $p = str_replace('\\', '/', $p);
            
            // 途中に絶対パス（/ または C:/）が来たら、それまでの内容をリセット
            if (self::isAbsolute($p)) {
                $resolved = [];
            }
            
            foreach (explode('/', $p) as $part) {
                if ($part === '' || $part === '.') continue;
                if ($part === '..') {
                    array_pop($resolved);
                } else {
                    $resolved[] = $part;
                }
            }
        }

        // 起点が絶対パスかどうかの判定
        $first = str_replace('\\', '/', $path[0] ?? '');
        $isAbsolute = self::isAbsolute($first);
        $isWindowsDrive = preg_match('/^[a-zA-Z]:/', $first);

        $result = implode(DIRECTORY_SEPARATOR, $resolved);

        if ($isWindowsDrive) {
            // ドライブレターの直後のセパレータを補完
            $drive = substr($first, 0, 2);
            return $drive . DIRECTORY_SEPARATOR . ltrim($result, DIRECTORY_SEPARATOR);
        }
        
        if ($isAbsolute) {
            return DIRECTORY_SEPARATOR . $result;
        }

        // 相対パスの場合はプロジェクトルート（abs）を付与
        return self::abs() . DIRECTORY_SEPARATOR . $result;
    }

    /** 絶対パス判定（OS問わず） */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[a-zA-Z]:/i', $path);
    }

    /** OSの一時ディレクトリパス取得 */
    public static function os_temp(...$path): string
    {
        return self::get(sys_get_temp_dir(), ...$path);
    }

    /** 一時ファイルパスの生成（ユニークな名前） */
    public static function temp_file(string $prefix = 'fzr_'): string
    {
        return tempnam(sys_get_temp_dir(), $prefix . bin2hex(random_bytes(4)) . '_');
    }

    public static function public(...$path): string
    {
        return self::get(Env::get('path.public', Config::DIR_PUBLIC), ...$path);
    }
    public static function app(...$path): string
    {
        return self::get(Env::get('path.app', Config::DIR_APP), ...$path);
    }
    public static function ctrl(...$path): string
    {
        return self::get(Env::get('path.ctrl', Config::DIR_CTRL), ...$path);
    }
    public static function view(...$path): string
    {
        return self::get(Env::get('path.view', Config::DIR_VIEW), ...$path);
    }
    public static function model(...$path): string
    {
        return self::get(Env::get('path.models', Config::DIR_MODELS), ...$path);
    }
    public static function db(...$path): string
    {
        return self::get(Env::get('path.db', Config::DIR_DB), ...$path);
    }
    public static function log(...$path): string
    {
        $base = Env::get('path.log', Config::DIR_LOG);
        $p = self::get($base, ...$path);
        if (!is_dir(dirname($p))) {
            mkdir(dirname($p), 0777, true);
        }
        return $p;
    }
    public static function temp(...$path): string
    {
        $base = Env::get('path.temp', Config::DIR_TEMP);
        $p = self::get($base, ...$path);
        if (!is_dir(dirname($p))) {
            mkdir(dirname($p), 0777, true);
        }
        return $p;
    }
    public static function storage(...$path): string
    {
        return self::get(Env::get('path.storage', Config::DIR_STORAGE), ...$path);
    }
}
