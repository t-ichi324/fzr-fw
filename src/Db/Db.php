<?php

namespace Fzr\Db;
use Fzr\Logger;

/**
 * Database Facade — high-level entry point for all database operations.
 *
 * Use to perform raw SQL queries, manage connections, and start transactions.
 * Typical uses: one-off queries, direct record counts, transaction management.
 *
 * - Manages multiple database connections via {@see Connection}.
 * - Provides helper methods for common operations (fetch, execute, count).
 * - Integrates with `Tracer` for database performance monitoring.
 * - Supports automatic pagination logic via `page()`.
 */
class Db
{
    /** @var Connection[] */
    protected static array $connections = [];

    /** 接続登録 */
    public static function addConnection(string $key, Connection $connection): void
    {
        self::$connections[$key] = $connection;
    }

    /** 接続取得（未登録なら app.ini から自動生成） */
    public static function connection(string $key = 'default'): Connection
    {
        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = Connection::fromEnv($key === 'default' ? 'db' : $key);
        }
        return self::$connections[$key];
    }

    /** PDO 取得 */
    public static function pdo(string $connectionKey = 'default'): \PDO
    {
        return self::connection($connectionKey)->getPdo();
    }

    /**
     * テーブルクエリ開始（クエリビルダ）
     *
     * @return Query<object>
     */
    public static function table(string $table, string $connectionKey = 'default'): Query
    {
        return new Query(self::connection($connectionKey), $table);
    }

    /**
     * クエリ開始（クエリビルダ）
     *
     * @return Query<object>
     */
    public static function query(string $connectionKey = 'default'): Query
    {
        return new Query(self::connection($connectionKey));
    }

    /**
     * RAW SQL 実行（SELECT）→ Result を返す
     *
     * @template T of object
     * @param  string      $sql           SELECT 文
     * @param  array       $params        バインドパラメータ
     * @param  string      $connectionKey 接続キー
     * @param  class-string<T>|null $fetchClass 結果を詰めるクラス名（null で stdClass）
     * @return Result<int, T|\stdClass>
     */
    public static function select(
        string $sql,
        array $params = [],
        string $connectionKey = 'default',
        ?string $fetchClass = null
    ): Result {
        $pdo  = self::pdo($connectionKey);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Logger::db($connectionKey, 3, $sql, $params);
        if ($fetchClass) {
            $rows = $stmt->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $fetchClass);
        } else {
            $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
        return new Result($rows, count($rows));
    }

    /**
     * RAW SQL 実行（SELECT + ページネーション）→ Result を返す
     *
     * @template T of object
     * @param  class-string<T>|null $fetchClass 結果を詰めるクラス名
     * @return Result<int, T|\stdClass>
     */
    public static function page(
        string $sql,
        array $params = [],
        int $page = 1,
        int $perPage = 20,
        string $connectionKey = 'default',
        ?string $fetchClass = null
    ): Result {
        $pdo  = self::pdo($connectionKey);
        $page = max(1, $page);

        $countSql  = "SELECT COUNT(*) FROM ({$sql}) AS __cnt_q";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset   = ($page - 1) * $perPage;
        $pagedSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
        $stmt     = $pdo->prepare($pagedSql);
        $stmt->execute($params);
        Logger::db($connectionKey, 3, $pagedSql, $params);

        if ($fetchClass) {
            $rows = $stmt->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $fetchClass);
        } else {
            $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
        return new Result($rows, $total, $page, $perPage);
    }

    /**
     * RAW SQL 実行（INSERT / UPDATE / DELETE 等）
     *
     * @return int 影響行数
     */
    public static function execute(string $sql, array $params = [], string $connectionKey = 'default'): int
    {
        $pdo  = self::pdo($connectionKey);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Logger::db($connectionKey, 2, $sql, $params);
        return $stmt->rowCount();
    }

    /**
     * トランザクション（callable を渡す形式）
     *
     * callable が例外を投げた場合は自動 ROLLBACK し、例外を再スロー。
     *
     * @param  callable $callback function(\PDO $pdo): mixed
     * @return mixed callback の戻り値
     */
    public static function transaction(callable $callback, string $connectionKey = 'default'): mixed
    {
        $conn = self::connection($connectionKey);
        $conn->beginTransaction(); // Connectionクラスのメソッドを呼ぶ
        try {
            $result = $callback($conn->getPdo());
            $conn->commit();
            return $result;
        } catch (\Throwable $ex) {
            $conn->rollBack();
            Logger::exception("Transaction rolled back", $ex);
            throw $ex;
        }
    }

    /** トランザクション開始 */
    public static function beginTransaction(string $connectionKey = 'default'): void
    {
        self::connection($connectionKey)->beginTransaction();
    }

    /** コミット */
    public static function commit(string $connectionKey = 'default'): void
    {
        self::connection($connectionKey)->commit();
    }

    /** ロールバック */
    public static function rollback(string $connectionKey = 'default'): void
    {
        self::connection($connectionKey)->rollBack();
    }

    /** マイグレーション実行 */
    public static function migrate(?string $dir = null, string $connectionKey = 'default'): void
    {
        $migration = new Migration(self::connection($connectionKey));
        $migration->run($dir);
    }

    /** 全接続切断 */
    public static function disconnectAll(): void
    {
        foreach (self::$connections as $conn) {
            $conn->disconnect();
        }
    }

    // ── Schema Introspection ──────────────────────────────────────────────

    /** テーブル一覧取得 */
    public static function tables(string $connectionKey = 'default'): array
    {
        $pdo    = self::pdo($connectionKey);
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
        } else {
            $stmt = $pdo->query("SHOW TABLES");
        }
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * テーブルのカラム定義を取得
     *
     * 戻り値の各要素: ['name', 'type', 'notnull', 'pk', 'comment', 'length']
     */
    public static function schema(string $table, string $connectionKey = 'default'): array
    {
        $pdo    = self::pdo($connectionKey);
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $columns = [];

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(`$table`)");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'name'    => $row['name'],
                    'type'    => strtolower($row['type']),
                    'notnull' => (bool)$row['notnull'],
                    'pk'      => (bool)$row['pk'],
                    'comment' => '',
                    'length'  => null,
                ];
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare("
                SELECT column_name, data_type, is_nullable, column_default, character_maximum_length,
                    (SELECT description FROM pg_description WHERE objoid = :table::regclass AND objsubid = ordinal_position) as comment
                FROM information_schema.columns
                WHERE table_name = :table_name
                ORDER BY ordinal_position
            ");
            $stmt->execute(['table' => $table, 'table_name' => $table]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $columns[] = [
                    'name'    => $row['column_name'],
                    'type'    => strtolower($row['data_type']),
                    'notnull' => $row['is_nullable'] === 'NO',
                    'pk'      => strpos($row['column_default'] ?? '', 'nextval') !== false,
                    'comment' => $row['comment'] ?? '',
                    'length'  => $row['character_maximum_length'],
                ];
            }
        } else {
            $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                preg_match('/\((\d+)\)/', $row['Type'], $matches);
                $columns[] = [
                    'name'    => $row['Field'],
                    'type'    => strtolower(preg_replace('/\(.*\)/', '', $row['Type'])),
                    'notnull' => $row['Null'] === 'NO',
                    'pk'      => $row['Key'] === 'PRI',
                    'comment' => $row['Comment'] ?? '',
                    'length'  => isset($matches[1]) ? (int)$matches[1] : null,
                ];
            }
        }
        return $columns;
    }

    /**
     * Entity クラスファイルを生成
     *
     * @param  string   $outDir        出力ディレクトリ
     * @param  bool     $force         既存ファイルを上書きする
     * @param  string[] $tables        対象テーブル（空 = 全テーブル）
     * @param  string   $connectionKey 接続キー
     * @return array{generated: list<array{table:string,class:string}>, skipped: list<array{table:string,class:string}>}
     */
    public static function generateModels(
        string $outDir = 'app/models',
        bool $force = false,
        array $tables = [],
        string $connectionKey = 'default'
    ): array {
        $generated = [];
        $skipped   = [];

        if (!is_dir($outDir)) {
            @mkdir($outDir, 0777, true);
        }

        $allTables = empty($tables) ? self::tables($connectionKey) : $tables;

        foreach ($allTables as $table) {
            if ($table === 'migrations') continue;

            $className = self::tableToClass($table);
            $filePath  = rtrim($outDir, '/\\') . '/' . $className . '.php';

            if (file_exists($filePath) && !$force) {
                $skipped[] = ['table' => $table, 'class' => $className];
                continue;
            }

            $columns = self::schema($table, $connectionKey);
            file_put_contents($filePath, self::renderModel($className, $table, $columns));
            $generated[] = ['table' => $table, 'class' => $className];
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private static function tableToClass(string $table): string
    {
        $singular = preg_replace('/s$/', '', $table) ?: $table;
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $singular)));
    }

    private static function renderModel(string $className, string $table, array $columns): string
    {
        $props = '';
        foreach ($columns as $col) {
            $name  = $col['name'];
            $type  = self::mapPhpType($col['type']);
            $attrs = [];

            $label = $col['comment'] ?: $name;
            $attrs[] = "#[Label('$label')]";
            if ($col['notnull'] && !$col['pk']) $attrs[] = '#[Required]';
            if ($col['length'] && $type === 'string') $attrs[] = "#[MaxLength({$col['length']})]";
            if (strpos($col['type'], 'int') !== false) $attrs[] = '#[Numeric]';
            if ($name === 'email') $attrs[] = '#[Email]';

            $attrStr = implode("\n    ", $attrs);
            $props  .= "    $attrStr\n    public $type \${$name};\n\n";
        }

        return <<<PHP
        <?php
        namespace App\Model;

        use Fzr\Db\Entity;
        use Fzr\Attr\Field\{Label, Required, MaxLength, Numeric, Email};

        /**
         * $className エンティティ
         */
        class $className extends Entity {
            protected static ?string \$table = '$table';

        $props}
        PHP;
    }

    private static function mapPhpType(string $dbType): string
    {
        if (strpos($dbType, 'int') !== false) return 'int';
        if (strpos($dbType, 'bool') !== false || $dbType === 'bit') return 'bool';
        if (strpos($dbType, 'float') !== false || strpos($dbType, 'double') !== false || strpos($dbType, 'decimal') !== false) return 'float';
        return 'string';
    }
}
