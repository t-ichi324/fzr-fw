<?php

namespace Fzr;

/**
 * Storage Adapter Contract — defines the interface for pluggable filesystem drivers.
 *
 * Implement this interface to add support for a new storage backend (e.g., S3, FTP).
 * Typical uses: swapping Local for GCS in cloud deployments, adding test fakes.
 *
 * - All path arguments are relative to the disk's configured root.
 * - Implementations must be registered via {@see Storage::setDisk()} or auto-created from config.
 *
 * Contrast with {@see Storage} (the static facade that delegates to this interface).
 */
interface StorageAdapter
{
    public function exists(string $path): bool;
    public function get(string $path): string|false;
    public function put(string $path, string $contents): bool;
    public function delete(string $path): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function url(string $path): string;
    /** @return list<string> */
    public function files(string $directory = '', bool $recursive = false): array;
}

/**
 * File Storage Manager — abstraction layer for local and cloud file systems.
 *
 * Use to save and retrieve files (uploads, generated reports, etc.) in a driver-agnostic way.
 * Typical uses: handling user uploads, storing private assets, cloud storage integration (GCS/S3).
 *
 * - Supports multiple "disks" (Local, Private, Public, GCS) configured in `app.ini`.
 * - Provides a unified API for common file operations (put, get, delete, exists).
 * - Automatically handles directory creation and path resolution.
 * - Detects stateless environments (Cloud Run) to suggest or switch to GCS.
 */
class Storage
{
    /** @var StorageAdapter[] */
    protected static array $disks = [];

    /**
     * 指定した名称のディスク（アダプタ）を取得
     */
    public static function disk(?string $name = null): StorageAdapter
    {
        // 1. 指定なしなら env のデフォルト名 (未設定なら 'default')
        $name = $name ?: Env::get('storage.default_disk', 'default');

        if (!isset(self::$disks[$name])) {
            self::$disks[$name] = self::createDisk($name);
        }
        return self::$disks[$name];
    }

    /**
     * よく使う「公開」ディスクへのショートカット
     */
    public static function public(): StorageAdapter
    {
        // 2. 公開用として扱うディスク名をを env から取得 (未設定なら 'public')
        return self::disk(Env::get('storage.public_disk', 'public'));
    }

    /**
     * よく使う「非公開」ディスクへのショートカット
     */
    public static function private(): StorageAdapter
    {
        // 3. 非公開用として扱うディスク名をを env から取得 (未設定なら 'private')
        return self::disk(Env::get('storage.private_disk', 'private'));
    }

    /**
     * デフォルトのアダプタを互換性のために取得
     * @deprecated Use disk() instead.
     */
    public static function adapter(): StorageAdapter
    {
        return self::disk();
    }

    /**
     * 設定からアダプタを生成
     */
    protected static function createDisk(string $name): StorageAdapter
    {
        $prefix = ($name === 'default') ? 'storage' : "storage.{$name}";

        // 明示設定を優先: per-disk → global → null の順で取得
        $explicitDriver = Env::get("{$prefix}.driver") ?? Env::get('storage.driver');
        $driver = $explicitDriver ?? 'local';

        // 明示設定がない場合のみ stateless 自動切替を試みる
        if ($explicitDriver === null && Context::isStateless()) {
            $bucket = Env::get("{$prefix}.bucket") ?: Env::get('gcs_bucket');
            if ($bucket) {
                $driver = 'gcs';
            } else {
                Logger::warning("Storage disk '{$name}': stateless environment with no external storage bucket configured. Local filesystem data will not persist across restarts.");
            }
        }

        if ($driver === 'local') {
            $defaultRoot = ($name === 'public') ? Path::storage('public') : (($name === 'private') ? Path::storage('private') : Path::storage());
            $defaultUrl = ($name === 'public') ? '/storage/public' : '/storage';

            return new LocalStorageAdapter(
                Env::get("{$prefix}.root", $defaultRoot),
                Env::get("{$prefix}.url", $defaultUrl)
            );
        }

        if ($driver === 'gcs') {
            return new GcsStorageAdapter(
                Env::get("{$prefix}.bucket", Env::get('gcs_bucket', '')),
                Env::get("{$prefix}.key_file", Env::get('gcs_key_file', null)),
                Env::get("{$prefix}.url", Env::get('gcs_url', '')),
                Env::get("{$prefix}.root", Env::get('gcs_root', ''))
            );
        }

        throw new \RuntimeException("Unsupported storage driver: {$driver} for disk '{$name}'");
    }

    /** 実行時に動的にアダプタを登録する（テスト用など） */
    public static function setDisk(string $name, StorageAdapter $adapter): void
    {
        self::$disks[$name] = $adapter;
    }

    /** ファイルが存在するか確認 */
    public static function exists(string $path): bool
    {
        return self::disk()->exists($path);
    }

    /** ファイル内容を取得 */
    public static function get(string $path): string|false
    {
        return self::disk()->get($path);
    }

    /** ファイルを保存（ディレクトリが無ければ自動生成） */
    public static function put(string $path, string $contents): bool
    {
        return self::disk()->put($path, $contents);
    }

    /** ファイルを削除 */
    public static function delete(string $path): bool
    {
        return self::disk()->delete($path);
    }

    /** ファイルサイズ（バイト）取得 */
    public static function size(string $path): int
    {
        return self::disk()->size($path);
    }

    /** 最終更新日時（UNIXタイムスタンプ）取得 */
    public static function lastModified(string $path): int
    {
        return self::disk()->lastModified($path);
    }

    /** ブラウザからアクセスするための公開URLを取得 */
    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /** @return list<string> */
    public static function files(string $directory = '', bool $recursive = false): array
    {
        return self::disk()->files($directory, $recursive);
    }
}


/**
 * ローカルファイルシステム用ストレージアダプター
 */
class LocalStorageAdapter implements StorageAdapter
{
    private string $root;
    private string $baseUrl;

    public function __construct(?string $root = null, string $baseUrl = '/storage')
    {
        $this->root = rtrim(Path::get($root ?? Path::storage()), '/\\');
        $this->baseUrl = rtrim($baseUrl, '/');
        if (!is_dir($this->root)) {
            mkdir($this->root, 0777, true);
        }
    }

    private function full(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function exists(string $path): bool
    {
        return file_exists($this->full($path));
    }

    public function get(string $path): string|false
    {
        return file_get_contents($this->full($path));
    }

    public function put(string $path, string $contents): bool
    {
        $full = $this->full($path);
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return file_put_contents($full, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        $full = $this->full($path);
        if (is_file($full)) return unlink($full);
        return false;
    }

    public function size(string $path): int
    {
        $full = $this->full($path);
        return is_file($full) ? filesize($full) : 0;
    }

    public function lastModified(string $path): int
    {
        $full = $this->full($path);
        return is_file($full) ? filemtime($full) : 0;
    }

    public function url(string $path): string
    {
        return url($this->baseUrl . '/' . ltrim($path, '/'));
    }

    /** @return list<string> */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->full($directory);
        if (!is_dir($fullPath)) return [];

        $results = [];
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relative = str_replace($this->root . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $results[] = str_replace('\\', '/', $relative);
                }
            }
        } else {
            $files = scandir($fullPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                if (is_file($fullPath . DIRECTORY_SEPARATOR . $file)) {
                    $results[] = ltrim($directory . '/' . $file, '/');
                }
            }
        }
        return $results;
    }
}


/**
 * Google Cloud Storage (GCS) 用ストレージアダプター
 * ※利用には composer require google/cloud-storage が必要です
 */
class GcsStorageAdapter implements StorageAdapter
{
    /** @var \Google\Cloud\Storage\Bucket */
    private $bucket;
    private string $baseUrl;
    private string $root;

    public function __construct(string $bucketName, ?string $keyFilePath = null, string $baseUrl = '', string $root = '')
    {
        $config = [];
        if ($keyFilePath) $config['keyFilePath'] = $keyFilePath;
        $class = '\\Google\\Cloud\\Storage\\StorageClient';
        if (!class_exists($class)) {
            throw new \RuntimeException("Google Cloud Storage SDK not found. Please install it via: composer require google/cloud-storage");
        }
        $storage = new $class($config);
        $this->bucket = $storage->bucket($bucketName);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->root = trim($root, '/');
    }

    private function fullPath(string $path): string
    {
        $path = ltrim($path, '/');
        return $this->root === '' ? $path : $this->root . '/' . $path;
    }

    public function exists(string $path): bool
    {
        return $this->bucket->object($this->fullPath($path))->exists();
    }

    public function get(string $path): string|false
    {
        $obj = $this->bucket->object($this->fullPath($path));
        return $obj->exists() ? $obj->downloadAsString() : false;
    }

    public function put(string $path, string $contents): bool
    {
        $this->bucket->upload($contents, ['name' => $this->fullPath($path)]);
        return true;
    }

    public function delete(string $path): bool
    {
        $obj = $this->bucket->object($this->fullPath($path));
        if ($obj->exists()) {
            $obj->delete();
            return true;
        }
        return false;
    }

    public function size(string $path): int
    {
        $obj = $this->bucket->object($this->fullPath($path));
        return $obj->exists() ? (int)$obj->info()['size'] : 0;
    }

    public function lastModified(string $path): int
    {
        $obj = $this->bucket->object($this->fullPath($path));
        return $obj->exists() ? strtotime($obj->info()['updated']) : 0;
    }

    public function url(string $path): string
    {
        $fullPath = $this->fullPath($path);
        return $this->baseUrl ? $this->baseUrl . '/' . $fullPath : "https://storage.googleapis.com/{$this->bucket->name()}/" . $fullPath;
    }

    /** @return list<string> */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullDir = $this->fullPath($directory);
        $options = ['prefix' => $fullDir === '' ? '' : $fullDir . '/'];

        if (!$recursive) {
            $options['delimiter'] = '/';
        }

        $objects = $this->bucket->objects($options);
        $results = [];
        $rootLen = $this->root === '' ? 0 : strlen($this->root) + 1;

        foreach ($objects as $object) {
            $name = $object->name();
            // ディレクトリ自体のエントリを除外
            if (str_ends_with($name, '/')) continue;

            // ルート部分を除去して相対パスにする
            if ($rootLen > 0 && str_starts_with($name, $this->root . '/')) {
                $name = substr($name, $rootLen);
            }
            $results[] = $name;
        }
        return $results;
    }
}
