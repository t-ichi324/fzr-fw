<?php

namespace Fzr\Db;

/**
 * Lightweight Database Helper — simplified SQLite facade for quick queries.
 *
 * Use for simple scripts or when the full Query Builder/Entity system is overkill.
 * Typical uses: one-off data cleanup scripts, simple CRUD in small tools, local testing.
 *
 * - Specifically optimized for SQLite; handles file-based connection automatically.
 * - Provides a minimal, static-like interface for PDO operations.
 * - Can explicitly open specific database files via `LiteDb::open()`.
 * - Includes SQLite-specific helpers like `hasTable()` and `hasColumn()`.
 */
class LiteDb
{
    private static string $defaultKey = 'default';

    /**
     * 指定した物理パスのSQLiteファイルを開く（動的接続）
     * * @param string $path DBファイルの完全なパス
     * @param string $key 接続キー
     * @return string 接続キー
     */
    public static function open(string $path, string $key = 'default'): string
    {
        self::$defaultKey = $key;

        Db::addConnection($key, new Connection($key, [
            'driver' => 'sqlite',
            'sqlitePath' => $path,
        ]));

        return $key;
    }

    /** 現在のConnectionを取得 */
    public static function getConnection(): Connection
    {
        // app.ini にDB設定が一切ない場合のフェールセーフ（デフォルトDBの自動生成）
        if (self::$defaultKey === 'default' && !\Fzr\Env::has('db.driver')) {
            self::open(\Fzr\Path::db('app.db'));
        }

        $conn = Db::connection(self::$defaultKey);

        // 誤って app.ini で mysql 等が指定されていた場合のガード
        if ($conn->getDriver() !== 'sqlite') {
            throw new \RuntimeException("LiteDb requires 'sqlite' driver, but '{$conn->getDriver()}' is configured.");
        }

        return $conn;
    }

    /** デフォルトの接続先を切り替え */
    public static function useConnection(string $key): void
    {
        self::$defaultKey = $key;
    }

    // ==========================================
    // クエリビルダ操作
    // ==========================================

    /**
     * テーブル指定クエリ開始
     * @return Query<object>
     */
    public static function table(string $tableName): Query
    {
        return new Query(self::getConnection(), $tableName);
    }

    /**
     * エンティティ指定クエリ開始
     * @template T of Entity
     * @param class-string<T> $entityClass
     * @return Query<T>
     */
    public static function entity(string $entityClass): Query
    {
        return (new Query(self::getConnection(), $entityClass::tableName()))->entity($entityClass);
    }

    // ==========================================
    // 直接SQL実行 (Dbファサードへ委譲)
    // ==========================================

    /**
     * @template T of object
     * @param class-string<T>|null $fetchClass
     * @return \Fzr\Collection<int, T|\stdClass>
     */
    public static function select(string $sql, array $params = [], ?string $fetchClass = null): \Fzr\Collection
    {
        return Db::select($sql, $params, self::getConnection()->getKey(), $fetchClass);
    }

    /**
     * @template T of object
     * @param class-string<T>|null $fetchClass
     * @return Paginated<int, T|\stdClass>
     */
    public static function page(string $sql, array $params = [], int $p = 1, int $perPage = 20, ?string $fetchClass = null): Paginated
    {
        return Db::page($sql, $params, $p, $perPage, self::getConnection()->getKey(), $fetchClass);
    }

    public static function execute(string $sql, array $params = []): int
    {
        return Db::execute($sql, $params, self::getConnection()->getKey());
    }

    /** SQLファイル実行 */
    public static function executeFile(string $filepath): int
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException("SQL file not readable: {$filepath}");
        }
        $sql = file_get_contents($filepath);
        if ($sql === false || trim($sql) === '') return 0;

        $count = 0;
        $statements = preg_split('/;\s*\n/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $connKey = self::getConnection()->getKey();
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            Db::execute($stmt, [], $connKey);
            $count++;
        }
        return $count;
    }

    // ==========================================
    // トランザクション
    // ==========================================

    public static function transaction(callable $callable): mixed
    {
        return Db::transaction($callable, self::getConnection()->getKey());
    }

    public static function beginTransaction(): void
    {
        Db::beginTransaction(self::getConnection()->getKey());
    }
    public static function commit(): void
    {
        Db::commit(self::getConnection()->getKey());
    }
    public static function rollBack(): void
    {
        Db::rollback(self::getConnection()->getKey());
    }

    // ==========================================
    // SQLite特有の便利機能
    // ==========================================

    /** sqlite_master へのクエリビルダ */
    public static function master(): Query
    {
        return self::table('sqlite_master');
    }

    /** テーブル存在確認 */
    public static function hasTable(string $tableName): bool
    {
        return self::master()->where('type', 'table')->where('name', $tableName)->exists();
    }

    /** カラム存在確認 */
    public static function hasColumn(string $tableName, string $columnName): bool
    {
        $res = self::select("PRAGMA table_info(`{$tableName}`)");
        return $res->where('name', $columnName)->isNotEmpty();
    }
}
