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

    public static function init(string $abs): void { self::$abs = $abs; }
    protected static function abs(): string { return self::$abs ?? (defined('ABSPATH') ? ABSPATH : dirname(__DIR__)); }

    public static function get(...$path): string
    {
        $joined = implode(DIRECTORY_SEPARATOR, $path);
        if (str_starts_with($joined, '/') || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Z]:/i', $joined))) {
            return $joined;
        }
        return self::abs() . DIRECTORY_SEPARATOR . $joined;
    }
    public static function app(...$path): string { return self::get(Env::get('path.app', Config::DIR_APP), ...$path); }
    public static function ctrl(...$path): string { return self::get(Env::get('path.ctrl', Config::DIR_CTRL), ...$path); }
    public static function view(...$path): string { return self::get(Env::get('path.view', Config::DIR_VIEW), ...$path); }
    public static function model(...$path): string { return self::get(Env::get('path.models', Config::DIR_MODELS), ...$path); }
    public static function db(...$path): string { return self::get(Env::get('path.db', Config::DIR_DB), ...$path); }
    public static function log(...$path): string { $base = Env::get('path.log', Config::DIR_LOG); $p = self::get($base, ...$path); if (!is_dir(dirname($p))) { mkdir(dirname($p), 0777, true); } return $p; }
    public static function temp(...$path): string { $base = Env::get('path.temp', Config::DIR_TEMP); $p = self::get($base, ...$path); if (!is_dir(dirname($p))) { mkdir(dirname($p), 0777, true); } return $p; }
    public static function storage(...$path): string { return self::get(Env::get('path.storage', Config::DIR_STORAGE), ...$path); }
}
