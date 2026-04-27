<?php
namespace Fzr\Db;

use Fzr\Logger;
use Fzr\Path;

/**
 * Database Migration — handles schema versioning and automated updates.
 *
 * Use to keep the database schema in sync across different environments.
 * Typical uses: creating tables, adding columns, running seed data scripts.
 *
 * - Tracks executed migrations in a `_migrations` table.
 * - Supports simple SQL-based migration files.
 * - Handles both MySQL/PostgreSQL and SQLite schema syntax differences.
 * - Integrates with `Logger` for migration audit trails.
 */
class Migration {
    protected Connection $connection;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    /** マイグレーション実行 */
    public function run(?string $dir = null): void {
        $dir = $dir ?? Path::db('migrations');
        if (!is_dir($dir)) return;

        $pdo = $this->connection->getPdo();

        // マイグレーション管理テーブル作成
        $driver = $this->connection->getDriver();
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, executed_at TEXT DEFAULT (datetime('now')))");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        }

        $done = [];
        $stmt = $pdo->query("SELECT name FROM _migrations ORDER BY id");
        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $done[$row->name] = true;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if (!$files) return;
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) continue;

            $sql = file_get_contents($file);
            if (empty(trim($sql))) continue;

            try {
                $pdo->exec($sql);
                $insert = $pdo->prepare("INSERT INTO _migrations (name) VALUES (:name)");
                $insert->execute(['name' => $name]);
                Logger::info("Migration executed: {$name}");
            } catch (\Exception $ex) {
                Logger::exception("Migration failed: {$name}", $ex);
                throw $ex;
            }
        }
    }
}
