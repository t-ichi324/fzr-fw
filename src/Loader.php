<?php

namespace Fzr;

// コアクラスを事前に読み込み（オートロードのオーバーヘッド回避）
if (!defined('FZR_BUNDLED')) {
    require_once __DIR__ . '/Config.php';
    require_once __DIR__ . '/Path.php';
    require_once __DIR__ . '/Env.php';
    require_once __DIR__ . '/Context.php';
    require_once __DIR__ . '/Request.php';
    require_once __DIR__ . '/Response.php';
    require_once __DIR__ . '/Render.php';
    require_once __DIR__ . '/Bag.php';
    require_once __DIR__ . '/Form.php';
    require_once __DIR__ . '/Controller.php';
    require_once __DIR__ . '/Attr/Http.php';
    require_once __DIR__ . '/Attr/Field.php';
}

/**
 * Class Autoloader — handles dynamic loading of framework and application classes.
 *
 * Use to register and manage class loading paths.
 * Typical uses: bootstrapping the application, mapping namespaces to directories.
 *
 * - Implements a PSR-like autoloading strategy.
 * - Supports class aliasing for cleaner code (e.g., `Request::get()` instead of `\Fzr\Request::get()`).
 * - Optimizes performance by pre-loading core classes to avoid autoloading overhead.
 */
class Loader
{
    protected static array $baseDirs = [];
    protected static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) return;
        spl_autoload_register([self::class, 'autoload']);
        self::$registered = true;
    }

    public static function add(string|array $dirs): void
    {
        self::register();
        foreach ((is_array($dirs) ? $dirs : [$dirs]) as $dir) {
            if (!in_array($p = Path::get($dir), self::$baseDirs)) {
                self::$baseDirs[] = $p;
            }
        }
    }

    public static function autoload(string $className): void
    {
        self::load($className);
    }

    public static function load(string $name): bool
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
        // Fzr名前空間のプレフィックスを除去したパス（手動読み込み用フォールバック）
        $fzrPath = str_starts_with($name, 'Fzr\\') ? str_replace('\\', DIRECTORY_SEPARATOR, substr($name, 4)) . '.php' : null;

        foreach (self::$baseDirs as $base) {
            $baseDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
            // 1. 名前空間通りのパスを試行
            if (is_readable($full = $baseDir . $path)) {
                require_once $full;
                Logger::debug("[Loader] Loaded: $name from $full");
                return true;
            }
            // 2. Fzrプレフィックスを除去したパスを試行（src直下にある場合など）
            if ($fzrPath && is_readable($full = $baseDir . $fzrPath)) {
                require_once $full;
                Logger::debug("[Loader] Loaded: $name from $full");
                return true;
            }
        }
        return false;
    }
}
