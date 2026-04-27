<?php

namespace Fzr;

/**
 * Environment & Configuration Manager — unified access to INI files and environment variables.
 *
 * Use to retrieve application settings that change between environments (local, dev, production).
 * Typical uses: database credentials, API keys, feature flags, version strings.
 *
 * - Merges `app.ini`, `.env` files, and OS environment variables.
 * - Environment variables (OS/`.env`) take precedence over INI values.
 * - Access via `Env::get('key')`, `Env::getBool('key')`, etc.
 * - Supports INI inclusion via `INCLUDE_INI` key for layered configs.
 */
class Env
{
    protected static $dir = null;
    protected static $file = null;
    protected static $ini = null;
    protected static $dot_env = null;

    /**
     * INIファイル設定
     */
    public static function configure(string $file): void
    {
        if (self::$file === $file) return;

        $real = realpath($file) ?: $file;
        self::$file = basename($real);
        self::$dir = dirname($real);
        self::$ini = null;
    }

    /**
     * .envファイルを環境変数として読み込む
     * 既に環境変数が設定されている場合は上書きしない（サーバー設定優先）
     */
    public static function loadDotEnv(string $path): void
    {
        if (!file_exists($path)) return;
        self::$dot_env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = self::normalizeKey($key);
                $val = trim($val);

                // クォート除去
                if (preg_match('/^([\'"])(.*)\1$/', $val, $m)) {
                    $val = $m[2];
                }

                self::$dot_env[$key] = $val;
            }
        }
    }

    /**
     * キーを正規化（例: "app.debug" -> "APP_DEBUG"）
     */
    protected static function normalizeKey(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }

    protected static function init_load()
    {
        if (self::$ini !== null) return;

        $baseIni = [];
        $extraIni = [];

        // INIファイルが設定されていて存在する場合のみ読む
        if (self::$file !== null && self::$dir !== null) {
            $baseIni = self::fromFile(self::$file);

            if (isset($baseIni['INCLUDE_INI'])) {
                $includeFiles = preg_split('/[\s,;]+/', $baseIni['INCLUDE_INI'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($includeFiles as $includeFile) {
                    $dat = self::fromFile(trim($includeFile));
                    foreach ($dat as $k => $v) {
                        $extraIni[$k] = $v;
                    }
                }
            }
        }

        // マージ（include_ini 側を優先して上書き）
        self::$ini = array_merge($baseIni, $extraIni);
    }

    protected static function fromFile(?string $file): array
    {
        $ini = [];
        if ($file === null || self::$dir === null) return [];
        $path = self::$dir . DIRECTORY_SEPARATOR . $file;
        if (file_exists($path)) {
            $dat = parse_ini_file($path, true);
            if ($dat !== false) {
                foreach ($dat as $section => $values) {
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            $key = self::normalizeKey($section . '.' . $k);
                            $ini[$key] = $v;
                        }
                    } else {
                        $key = self::normalizeKey($section);
                        $ini[$key] = $values;
                    }
                }
            }
        }
        return $ini;
    }

    /** 設定キー存在確認 */
    public static function has(string $key): bool
    {
        self::init_load();
        $k = self::normalizeKey($key);
        // 環境変数 → INI の順で探す（getと同じ優先順位）
        return self::getEnvVar($k) !== null || isset(self::$ini[$k]);
    }

    /** 存在時コールバック実行 */
    public static function hasCallback(string $key, ?callable $has_callback, ?callable $else_callback = null): void
    {
        if (self::has($key) && $has_callback !== null) {
            $has_callback(self::get($key));
        } else if ($else_callback !== null) {
            $else_callback();
        }
    }

    /**
     * 設定値取得（環境変数 → INI → デフォルトの優先順）
     */
    public static function get(string $key, string|null $defaultVal = null): ?string
    {
        self::init_load();
        $k = self::normalizeKey($key);

        // 1. 環境変数から
        $envVal = self::getEnvVar($k);
        if ($envVal !== null) {
            return $envVal;
        }

        // 2. INIファイルから
        if (isset(self::$ini[$k])) {
            return self::$ini[$k];
        }

        return $defaultVal;
    }

    /** 真偽値設定取得 */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = strtolower((string)self::get($key, $default ? "true" : "false"));
        return in_array($val, ['1', 'true', 'on', 'yes'], true);
    }

    /** 数値設定取得 */
    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key, null);
        return is_numeric($val) ? (int)$val : $default;
    }

    /** 配列設定取得 */
    public static function getArray(string $key, array $default = []): array
    {
        if (($val = self::get($key)) === null) return $default;
        $parts = preg_split('/[\s,;]+/', $val, -1, PREG_SPLIT_NO_EMPTY);
        return $parts ?: $default;
    }

    /** 設定値出力 */
    public static function echo(string $key, string|null $defaultVal = null)
    {
        echo htmlspecialchars(self::get($key, $defaultVal), ENT_QUOTES, defined('APP_CHARSET') ? APP_CHARSET : 'UTF-8');
    }

    /** 環境判定 */
    public static function is(string $env): bool
    {
        return (strtoupper($env) === strtoupper((string)self::get("env", "")));
    }

    /** 全設定取得（環境変数による上書きを反映） */
    public static function all(): array
    {
        self::init_load();
        $results = self::$ini ?? [];

        if (!empty(self::$dot_env)) {
            $results = array_merge($results, self::$dot_env);
        }

        $get_envs = getenv();
        if (!empty($get_envs)) {
            $results = array_merge($results, $get_envs);
        }

        return $results;
    }

    /**
     * 環境変数から値を取得（Cloud Run / Docker 対応）
     */
    protected static function getEnvVar(string $normalizedKey): ?string
    {
        // 既に正規化されたキー（DB_HOST等）で検索
        $val = getenv($normalizedKey);
        if ($val !== false) return $val;

        // .env
        if (isset(self::$dot_env[$normalizedKey])) return self::$dot_env[$normalizedKey];

        return null;
    }
}
