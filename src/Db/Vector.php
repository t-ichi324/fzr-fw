<?php
namespace Fzr\Db;

use Fzr\Logger;

/**
 * Vector Database Support — experimental support for vector similarity search using pgvector.
 *
 * Use to perform nearest-neighbor searches on embeddings (e.g., for AI/RAG applications).
 * Typical uses: semantic search, recommendation engines, clustering, RAG context retrieval.
 *
 * - Specifically designed for PostgreSQL with the `pgvector` extension.
 * - Provides specialized query logic for vector distance functions: Cosine (<=>), L2 (<->), and Inner Product (<#>).
 * - Supports automated table creation with vector columns and appropriate indexes (IVFFlat, HNSW).
 * - Includes a high-level `getContext()` method for combined retrieval in RAG pipelines.
 */
class Vector {
    protected Connection $connection;

    /** 距離関数 */
    const COSINE = 'cosine';
    const L2 = 'l2';
    const INNER_PRODUCT = 'inner_product';

    public function __construct(Connection $connection) {
        if (!$connection->isPostgres()) {
            throw new \RuntimeException("Vector search requires PostgreSQL with pgvector extension.");
        }
        $this->connection = $connection;
    }

    /** pgvector エクステンション有効化 */
    public function ensureExtension(): void {
        $this->connection->getPdo()->exec("CREATE EXTENSION IF NOT EXISTS vector");
        Logger::info("pgvector extension ensured");
    }

    /**
     * ベクトルテーブル作成
     *
     * @param string $table テーブル名
     * @param int $dimensions ベクトル次元数 (例: OpenAI ada=1536, text-embedding-3-small=1536)
     * @param array $extraColumns 追加カラム定義 ['title TEXT NOT NULL', 'content TEXT', 'metadata JSONB']
     * @param string $distanceType インデックスの距離タイプ
     */
    public function createTable(string $table, int $dimensions = 1536, array $extraColumns = [], string $distanceType = self::COSINE): void {
        $cols = ["id BIGSERIAL PRIMARY KEY"];
        foreach ($extraColumns as $col) {
            $cols[] = $col;
        }
        $cols[] = "embedding vector({$dimensions})";
        $cols[] = "created_at TIMESTAMPTZ DEFAULT NOW()";

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (\n  " . implode(",\n  ", $cols) . "\n)";
        $this->connection->getPdo()->exec($sql);

        // インデックス作成
        $op = $this->distanceOp($distanceType);
        $indexName = "idx_{$table}_embedding";
        $this->connection->getPdo()->exec(
            "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} USING ivfflat (embedding {$op}) WITH (lists = 100)"
        );

        Logger::info("Vector table created: {$table} (dim={$dimensions}, distance={$distanceType})");
    }

    /**
     * ベクトルデータ挿入
     *
     * @param string $table テーブル名
     * @param array $embedding float配列
     * @param array $data 追加カラムデータ ['title' => '...', 'content' => '...']
     * @return int|string 挿入ID
     */
    public function insert(string $table, array $embedding, array $data = []): int|string {
        $columns = array_keys($data);
        $columns[] = 'embedding';
        $colStr = implode(', ', $columns);

        $placeholders = [];
        $params = [];
        foreach ($data as $k => $v) {
            $ph = ":v_{$k}";
            $placeholders[] = $ph;
            $params[$ph] = is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
        }
        $placeholders[] = ':embedding';
        $params[':embedding'] = self::toVectorString($embedding);

        $sql = "INSERT INTO {$table} ({$colStr}) VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($params);
        Logger::db($this->connection->getKey(), 2, $sql, ['embedding' => '[...]', ...$data]);
        return $stmt->fetchColumn();
    }

    /**
     * バルクインサート
     *
     * @param string $table テーブル名
     * @param array $rows [['embedding' => [...], 'title' => '...', 'content' => '...'], ...]
     */
    public function bulkInsert(string $table, array $rows): int {
        if (empty($rows)) return 0;
        $pdo = $this->connection->getPdo();
        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $embedding = $row['embedding'] ?? [];
                unset($row['embedding']);
                $this->insert($table, $embedding, $row);
                $count++;
            }
            $pdo->commit();
            return $count;
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            Logger::exception("Vector bulk insert failed", $ex);
            throw $ex;
        }
    }

    /**
     * ベクトル類似検索
     *
     * @param string $table テーブル名
     * @param array $queryEmbedding クエリベクトル (float配列)
     * @param int $limit 取得件数
     * @param string $distanceType 距離計算方法
     * @param array $where 追加WHERE条件 ['column' => 'value']
     * @param array $select 取得カラム (空なら全カラム)
     * @return array 類似度順のオブジェクト配列 (distanceフィールド付き)
     */
    public function search(
        string $table,
        array $queryEmbedding,
        int $limit = 10,
        string $distanceType = self::COSINE,
        array $where = [],
        array $select = []
    ): array {
        $vecStr = self::toVectorString($queryEmbedding);
        $operator = $this->distanceOperator($distanceType);

        // SELECT句
        $selectCols = empty($select) ? '*' : implode(', ', $select);
        $distanceExpr = "embedding {$operator} :query_vec";

        $sql = "SELECT {$selectCols}, ({$distanceExpr}) AS distance FROM {$table}";

        $params = [':query_vec' => $vecStr];

        // WHERE
        if (!empty($where)) {
            $whereParts = [];
            foreach ($where as $col => $val) {
                $ph = ":w_{$col}";
                $whereParts[] = "{$col} = {$ph}";
                $params[$ph] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $sql .= " ORDER BY {$distanceExpr} LIMIT :limit";
        $params[':limit'] = $limit;

        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($params);
        Logger::db($this->connection->getKey(), 3, $sql, ['query_vec' => '[...]', ...$where]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * RAGコンテキスト取得（検索 + テキスト結合）
     *
     * @param string $table テーブル名
     * @param array $queryEmbedding クエリベクトル
     * @param string $contentColumn テキストカラム名
     * @param int $limit 取得件数
     * @param float $maxDistance 最大距離（これ以上遠いものは除外）
     * @param string $separator テキスト結合セパレータ
     * @return array ['context' => '結合テキスト', 'sources' => [行データ], 'count' => 件数]
     */
    public function getContext(
        string $table,
        array $queryEmbedding,
        string $contentColumn = 'content',
        int $limit = 5,
        float $maxDistance = 0.8,
        string $separator = "\n\n---\n\n"
    ): array {
        $results = $this->search($table, $queryEmbedding, $limit);

        $filtered = array_filter($results, fn($r) => $r->distance <= $maxDistance);
        $texts = array_map(fn($r) => $r->$contentColumn ?? '', $filtered);

        return [
            'context' => implode($separator, $texts),
            'sources' => array_values($filtered),
            'count'   => count($filtered),
        ];
    }

    /**
     * ベクトル更新
     */
    public function updateEmbedding(string $table, int|string $id, array $embedding, string $primaryKey = 'id'): int {
        $sql = "UPDATE {$table} SET embedding = :embedding WHERE {$primaryKey} = :id";
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute([
            ':embedding' => self::toVectorString($embedding),
            ':id' => $id,
        ]);
        Logger::db($this->connection->getKey(), 2, $sql, ['id' => $id]);
        return $stmt->rowCount();
    }

    /**
     * ベクトル削除
     */
    public function delete(string $table, int|string $id, string $primaryKey = 'id'): int {
        $sql = "DELETE FROM {$table} WHERE {$primaryKey} = :id";
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        Logger::db($this->connection->getKey(), 2, $sql, ['id' => $id]);
        return $stmt->rowCount();
    }

    /**
     * IVFFlat インデックスの再構築
     * データ量が大幅に増えた後に実行するとパフォーマンスが改善される
     */
    public function reindex(string $table, int $lists = 100, string $distanceType = self::COSINE): void {
        $op = $this->distanceOp($distanceType);
        $indexName = "idx_{$table}_embedding";
        $pdo = $this->connection->getPdo();
        $pdo->exec("DROP INDEX IF EXISTS {$indexName}");
        $pdo->exec("CREATE INDEX {$indexName} ON {$table} USING ivfflat (embedding {$op}) WITH (lists = {$lists})");
        Logger::info("Vector index rebuilt: {$indexName} (lists={$lists})");
    }

    /**
     * HNSW インデックス作成（より高精度、構築は遅い）
     */
    public function createHnswIndex(string $table, string $distanceType = self::COSINE, int $m = 16, int $efConstruction = 64): void {
        $op = $this->distanceOp($distanceType);
        $indexName = "idx_{$table}_embedding_hnsw";
        $pdo = $this->connection->getPdo();
        $pdo->exec("DROP INDEX IF EXISTS {$indexName}");
        $pdo->exec("CREATE INDEX {$indexName} ON {$table} USING hnsw (embedding {$op}) WITH (m = {$m}, ef_construction = {$efConstruction})");
        Logger::info("HNSW index created: {$indexName}");
    }

    // =====================
    // ユーティリティ
    // =====================

    /** float配列 → pgvector文字列 '[0.1,0.2,0.3]' */
    public static function toVectorString(array $embedding): string {
        return '[' . implode(',', array_map('floatval', $embedding)) . ']';
    }

    /** pgvector文字列 → float配列 */
    public static function fromVectorString(string $str): array {
        $str = trim($str, '[]');
        return array_map('floatval', explode(',', $str));
    }

    /** 距離演算子 (SQL用) */
    protected function distanceOperator(string $type): string {
        return match ($type) {
            self::L2 => '<->',
            self::INNER_PRODUCT => '<#>',
            self::COSINE => '<=>',
            default => '<=>',
        };
    }

    /** インデックス用オペレータクラス */
    protected function distanceOp(string $type): string {
        return match ($type) {
            self::L2 => 'vector_l2_ops',
            self::INNER_PRODUCT => 'vector_ip_ops',
            self::COSINE => 'vector_cosine_ops',
            default => 'vector_cosine_ops',
        };
    }
}
