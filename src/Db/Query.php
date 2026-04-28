<?php

namespace Fzr\Db;

use Fzr\Logger;
use Fzr\Tracer;
use Fzr\Collection;
use Fzr\Db\Paginated;

/**
 * Query Builder — fluent interface for building SQL queries programmatically.
 *
 * Use to build complex SELECT, INSERT, UPDATE, or DELETE queries without writing raw SQL.
 * Typical uses: dynamic searching, filtering, bulk updates, paginated listings.
 *
 * - Supports method chaining for `where`, `join`, `order`, `group`, and `limit`.
 * - Handles parameter binding automatically to prevent SQL injection.
 * - Can return results as raw objects, arrays, or mapped {@see Entity} objects.
 * - Supports nested `WHERE` conditions and complex subqueries.
 *
 * @template T of object
 */
class Query
{
    protected Connection $connection;
    protected ?string $table = null;
    /** @var class-string<T>|null */
    protected ?string $fetchClass = null;

    protected array $select = ['*'];
    protected array $where = [];
    protected array $params = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $groupBy = null;
    protected ?string $having = null;
    protected array $joins = [];
    protected bool $distinct = false;

    /**
     * @param Connection $connection
     * @param string $table
     * @param class-string<T>|null $fetchClass
     */
    public function __construct(Connection $connection, ?string $table = null, ?string $fetchClass = null)
    {
        $this->connection = $connection;
        $this->table      = $table;
        $this->fetchClass = $fetchClass;
    }
    /**
     * テーブル指定
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 取得結果に適用するクラス名(Entity)を設定
     *
     * @template T2 of object
     * @param  class-string<T2>|T2 $entity_or_className
     * @return self<T2>
     */
    public function entity(mixed $entity_or_className): self
    {
        $this->fetchClass = is_string($entity_or_className) ? $entity_or_className : get_class($entity_or_className);
        /** @var self<T2> $this */
        return $this;
    }

    /** SELECT 列指定 */
    public function select(string ...$columns): self
    {
        $this->select = $columns;
        return $this;
    }

    /** DISTINCT */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * WHERE 条件追加（AND）
     *
     * サポートされている演算子:
     * `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS`, `IS NOT`
     *
     * 使用例:
     * - `where('col', $val)`           → `col = val`
     * - `where('col', '!=', $val)`     → `col != val`
     * - `where('col', [1,2,3])`        → `col IN (1,2,3)` (配列を自動検出)
     * - `where(['col1' => $v1, ...])`  → `col1 = v1 AND ...`
     * - `where(function($q){...})`     → グループ条件 `(cond1 AND cond2)`
     *
     * @param  string|array|\Closure $field
     */
    public function where(string|array|\Closure $field, mixed $op = null, mixed $value = null): self
    {
        if ($field instanceof \Closure) {
            $sub = new self($this->connection, $this->table);
            $field($sub);
            if (!empty($sub->where)) {
                $this->where[]  = ['type' => 'group', 'conditions' => $sub->where, 'connector' => 'AND'];
                $this->params   = array_merge($this->params, $sub->params);
            }
            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $this->where($k, '=', $v);
            }
            return $this;
        }

        // where('col', $val) の 2引数形式を正規化
        if ($value === null && $op !== null) {
            $value = $op;
            $op    = '=';
        }

        $op = strtoupper(trim($op ?? '='));

        // 配列 → IN / NOT IN へ自動変換
        if (is_array($value)) {
            if ($op === '!=' || $op === '<>') {
                return $this->whereNotIn($field, $value);
            }
            return $this->whereIn($field, $value);
        }

        if ($value === null) {
            $sql = ($op === '!=' || $op === '<>') ? "{$field} IS NOT NULL" : "{$field} IS NULL";
            $this->where[] = ['type' => 'raw', 'sql' => $sql];
        } else {
            $placeholder = ':w' . count($this->params);
            $this->where[] = ['type' => 'condition', 'sql' => "{$field} {$op} {$placeholder}", 'connector' => 'AND'];
            $this->params[$placeholder] = $value;
        }

        return $this;
    }

    /** OR WHERE */
    public function orWhere(string|array|\Closure $field, mixed $op = null, mixed $value = null): self
    {
        $prevCount = count($this->where);
        $this->where($field, $op, $value);
        if (count($this->where) > $prevCount) {
            $last = array_pop($this->where);
            $last['connector'] = 'OR';
            $this->where[] = $last;
        }
        return $this;
    }

    /** WHERE IN */
    public function whereIn(string $field, array $values): self
    {
        if (empty($values)) {
            $this->where[] = ['type' => 'raw', 'sql' => '0=1'];
            return $this;
        }
        $placeholders = [];
        foreach ($values as $v) {
            $k = ':wi' . count($this->params);
            $placeholders[] = $k;
            $this->params[$k] = $v;
        }
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} IN (" . implode(',', $placeholders) . ")", 'connector' => 'AND'];
        return $this;
    }

    /** WHERE NOT IN */
    public function whereNotIn(string $field, array $values): self
    {
        if (empty($values)) return $this;
        $placeholders = [];
        foreach ($values as $v) {
            $k = ':wni' . count($this->params);
            $placeholders[] = $k;
            $this->params[$k] = $v;
        }
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} NOT IN (" . implode(',', $placeholders) . ")", 'connector' => 'AND'];
        return $this;
    }

    /** WHERE BETWEEN */
    public function whereBetween(string $field, mixed $min, mixed $max): self
    {
        $k1 = ':wb' . count($this->params);
        $this->params[$k1] = $min;
        $k2 = ':wb' . count($this->params);
        $this->params[$k2] = $max;
        $this->where[] = ['type' => 'raw', 'sql' => "{$field} BETWEEN {$k1} AND {$k2}"];
        return $this;
    }

    /** WHERE LIKE */
    public function whereLike(string $field, string $pattern): self
    {
        $k = ':wl' . count($this->params);
        $this->params[$k] = $pattern;
        $this->where[] = ['type' => 'condition', 'sql' => "{$field} LIKE {$k}", 'connector' => 'AND'];
        return $this;
    }

    /** WHERE RAW */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        if (empty($bindings)) {
            $this->where[] = ['type' => 'raw', 'sql' => $sql];
            return $this;
        }

        if (!array_is_list($bindings)) {
            $this->where[] = ['type' => 'raw', 'sql' => $sql];
            $this->params  = array_merge($this->params, $bindings);
            return $this;
        }

        // ? を名前付きパラメータへ置換
        $mapped        = [];
        $bindIndex     = 0;
        $newSql        = '';
        $inString      = false;
        $stringChar    = '';
        $escaped       = false;
        $baseParamIndex = count($this->params);

        for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
            $char = $sql[$i];
            if ($escaped) {
                $newSql .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                $newSql .= $char;
                continue;
            }
            if ($inString) {
                if ($char === $stringChar) $inString = false;
                $newSql .= $char;
                continue;
            }
            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $newSql .= $char;
                continue;
            }
            if ($char === '?') {
                if (array_key_exists($bindIndex, $bindings)) {
                    $k = ':wr' . ($baseParamIndex + $bindIndex);
                    $mapped[$k] = $bindings[$bindIndex];
                    $newSql .= $k;
                    $bindIndex++;
                } else {
                    $newSql .= $char;
                }
                continue;
            }
            $newSql .= $char;
        }

        $this->where[] = ['type' => 'raw', 'sql' => $newSql];
        $this->params  = array_merge($this->params, $mapped);
        return $this;
    }

    /** JOIN */
    public function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$on}";
        return $this;
    }

    /** LEFT JOIN */
    public function leftJoin(string $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    /** RIGHT JOIN */
    public function rightJoin(string $table, string $on): self
    {
        // SQLite は 3.39.0 未満では RIGHT JOIN 非対応
        if ($this->connection->getDriver() === 'sqlite') {
            Logger::warning('RIGHT JOIN is not supported in SQLite < 3.39.0. Consider rewriting as a LEFT JOIN with swapped tables.');
        }
        return $this->join($table, $on, 'RIGHT');
    }

    /** ORDER BY */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction    = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy = ($this->orderBy ? $this->orderBy . ', ' : '') . $this->quoteIdentifier($column) . " {$direction}";
        return $this;
    }

    /** GROUP BY */
    public function groupBy(string $column): self
    {
        $this->groupBy = $column;
        return $this;
    }

    /** HAVING */
    public function having(string $sql): self
    {
        $this->having = $sql;
        return $this;
    }

    /** LIMIT */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /** OFFSET */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * ページネーション
     *
     * @return Paginated<int, T|\stdClass>
     */
    public function page(int $page, int $perPage = 20): Paginated
    {
        $page         = max(1, $page);
        $this->limit  = $perPage;
        $this->offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($this->table) . $this->buildJoins() . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($countSql);
        $stmt->execute($this->params);
        $total = (int)$stmt->fetchColumn();

        $rows = $this->all();
        return new Paginated($rows, $total, $page, $perPage);
    }

    // =============================
    // 実行系
    // =============================

    /**
     * 全行取得
     *
     * @return \Fzr\Collection<int, T|\stdClass>
     */
    public function all(): \Fzr\Collection
    {
        $sql = $this->buildSelect();
        return $this->executeSelect($sql);
    }

    /**
     * 1行取得
     *
     * @return T|\stdClass|null
     */
    public function first(?object $default = null): ?object
    {
        $this->limit = 1;
        $sql  = $this->buildSelect();
        $rows = $this->executeSelect($sql);
        return $rows[0] ?? $default;
    }

    /**
     * 1値取得（SELECT/LIMIT を clone で副作用なし）
     */
    public function getValue(string $column): mixed
    {
        $q = clone $this;
        $q->select = [$column];
        $q->limit  = 1;
        $sql  = $q->buildSelect();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($q->params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 3, $sql, $q->params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $q->params, $elapsed, $this->connection->getKey());
        return $stmt->fetchColumn();
    }

    /** 件数取得 */
    public function count(): int
    {
        $sql  = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($this->table) . $this->buildJoins() . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($this->params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 3, $sql, $this->params);
        if (\Fzr\Tracer::isEnabled()) \Fzr\Tracer::recordQuery($sql, $this->params, $elapsed, $this->connection->getKey());
        return (int)$stmt->fetchColumn();
    }

    /** SUM */
    public function sum(string $column): float|int
    {
        return $this->getValue("SUM({$column})") ?? 0;
    }

    /** MAX */
    public function max(string $column): mixed
    {
        return $this->getValue("MAX({$column})");
    }

    /** MIN */
    public function min(string $column): mixed
    {
        return $this->getValue("MIN({$column})");
    }

    /** 平均値取得 */
    public function average(string $column): float|int
    {
        return $this->getValue("AVG({$column})") ?? 0;
    }

    /** 存在確認 */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** 不存在確認 */
    public function notExists(): bool
    {
        return $this->count() === 0;
    }

    /**
     * 連想配列（Key-Value）形式で取得（clone で副作用なし）
     *
     * @return array<string, mixed>
     */
    public function getKeyValues(string $keyColumn, string $valueColumn): array
    {
        $q = clone $this;
        $q->select = ["{$keyColumn} AS _kv_k", "{$valueColumn} AS _kv_v"];
        $sql  = $q->buildSelect();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($q->params);
        Logger::db($this->connection->getKey(), 3, $sql, $q->params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['_kv_k']] = $row['_kv_v'];
        }
        return $result;
    }

    /**
     * 単一カラム値をリストで取得（clone で副作用なし）
     *
     * @param  mixed $default NULL 時の代替値
     * @return array<int, mixed>
     */
    public function getValues(string $column, mixed $defaultVal = null): array
    {
        $q = clone $this;
        $q->select = ["{$column} AS _col_v"];
        $sql  = $q->buildSelect();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($q->params);
        Logger::db($this->connection->getKey(), 3, $sql, $q->params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => $r['_col_v'] ?? $defaultVal, $rows);
    }

    /**
     * デバッグ用: SQL 文とパラメータを配列で返す
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function toSql(): array
    {
        return [$this->buildSelect(), $this->params];
    }

    /**
     * デバッグ用: SQL 文を画面出力してチェーンを継続
     *
     * @return static
     */
    public function dump(): static
    {
        [$sql, $params] = $this->toSql();
        echo "<pre style='background:#f4f4f4;border-left:5px solid #333;padding:10px;margin:10px 0'>";
        echo "<b>[SQL]</b> " . htmlspecialchars($sql, ENT_QUOTES) . "\n";
        echo "<b>[Params]</b> " . htmlspecialchars(json_encode($params, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        echo "</pre>";
        return $this;
    }

    // =============================
    // 更新系
    // =============================

    /**
     * INSERT（挿入 ID を返す）
     *
     * @return int|string lastInsertId
     */
    public function insert(array $data): int|string
    {
        $quotedColumns = array_map(fn($c) => $this->quoteIdentifier($c), array_keys($data));
        $columns       = implode(', ', $quotedColumns);
        $placeholders  = [];
        $params        = [];
        foreach ($data as $k => $v) {
            $ph = ':i_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $placeholders[] = $ph;
            $params[$ph]    = $v;
        }
        $sql  = 'INSERT INTO ' . $this->quoteIdentifier($this->table) . " ({$columns}) VALUES (" . implode(', ', $placeholders) . ')';
        $pdo  = $this->connection->getPdo();
        $stmt = $pdo->prepare($sql);
        $start = microtime(true);
        $stmt->execute($params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $params, $elapsed, $this->connection->getKey());
        return $pdo->lastInsertId();
    }

    /**
     * 一括 INSERT（複数行）
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return int 挿入行数
     */
    public function insertMany(array $rows): int
    {
        if (empty($rows)) return 0;

        $columns       = array_keys($rows[0]);
        $quotedColumns = array_map(fn($c) => $this->quoteIdentifier($c), $columns);
        $allPlaceholders = [];
        $params        = [];

        foreach ($rows as $i => $row) {
            $rowPh = [];
            foreach ($columns as $col) {
                $ph = ':im_' . $i . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $col);
                $rowPh[]   = $ph;
                $params[$ph] = $row[$col] ?? null;
            }
            $allPlaceholders[] = '(' . implode(', ', $rowPh) . ')';
        }

        $sql  = 'INSERT INTO ' . $this->quoteIdentifier($this->table) . ' (' . implode(', ', $quotedColumns) . ') VALUES ' . implode(', ', $allPlaceholders);
        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $params, $elapsed, $this->connection->getKey());
        return $stmt->rowCount();
    }

    /**
     * UPSERT（INSERT or UPDATE ON DUPLICATE KEY）
     *
     * MySQL / SQLite 3.24+ に対応。PostgreSQL は ON CONFLICT を使用。
     *
     * @param  array<string, mixed> $data       挿入データ
     * @param  array<string>        $uniqueKeys 重複判定キー（PK やユニークキー）
     * @return int 影響行数
     */
    public function upsert(array $data, array $uniqueKeys): int
    {
        $driver        = $this->connection->getDriver();
        $columns       = array_keys($data);
        $quotedColumns = array_map(fn($c) => $this->quoteIdentifier($c), $columns);
        $params        = [];
        $placeholders  = [];

        foreach ($data as $k => $v) {
            $ph = ':us_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $placeholders[] = $ph;
            $params[$ph]    = $v;
        }

        if ($driver === 'mysql') {
            $updateSets = [];
            foreach ($columns as $col) {
                if (!in_array($col, $uniqueKeys, true)) {
                    $q = $this->quoteIdentifier($col);
                    $updateSets[] = "{$q} = VALUES({$q})";
                }
            }
            $sql = 'INSERT INTO ' . $this->quoteIdentifier($this->table) . ' (' . implode(', ', $quotedColumns) . ')'
                . ' VALUES (' . implode(', ', $placeholders) . ')'
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateSets);
        } else {
            // SQLite / PostgreSQL
            $quotedKeys  = array_map(fn($k) => $this->quoteIdentifier($k), $uniqueKeys);
            $updateSets  = [];
            foreach ($columns as $col) {
                if (!in_array($col, $uniqueKeys, true)) {
                    $q = $this->quoteIdentifier($col);
                    $updateSets[] = "{$q} = excluded.{$q}";
                }
            }
            $sql = 'INSERT INTO ' . $this->quoteIdentifier($this->table) . ' (' . implode(', ', $quotedColumns) . ')'
                . ' VALUES (' . implode(', ', $placeholders) . ')'
                . ' ON CONFLICT (' . implode(', ', $quotedKeys) . ')'
                . ' DO UPDATE SET ' . implode(', ', $updateSets);
        }

        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $params, $elapsed, $this->connection->getKey());
        return $stmt->rowCount();
    }

    /**
     * UPDATE（更新行数を返す）
     *
     * @return int 更新行数
     */
    public function update(array $data): int
    {
        $sets   = [];
        $params = [];
        foreach ($data as $k => $v) {
            $ph = ':u_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
            $sets[]     = $this->quoteIdentifier($k) . " = {$ph}";
            $params[$ph] = $v;
        }
        $params = array_merge($params, $this->params);
        $sql    = 'UPDATE ' . $this->quoteIdentifier($this->table) . ' SET ' . implode(', ', $sets) . $this->buildWhere();
        $stmt   = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 2, $sql, $params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $params, $elapsed, $this->connection->getKey());
        return $stmt->rowCount();
    }

    /**
     * DELETE（削除行数を返す）
     *
     * @return int 削除行数
     */
    public function delete(): int
    {
        $sql  = 'DELETE FROM ' . $this->quoteIdentifier($this->table) . $this->buildWhere();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($this->params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 2, $sql, $this->params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $this->params, $elapsed, $this->connection->getKey());
        return $stmt->rowCount();
    }

    /**
     * 大量データを chunk 件ずつ分割処理
     *
     * @param  int      $size     1回あたりの取得件数
     * @param  callable $callback function(array<T> $rows): bool|void  false を返すと中断
     */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        do {
            $q = clone $this;
            $q->limit  = $size;
            $q->offset = $offset;
            $rows = $q->all();
            // 修正箇所: empty($rows) ではなく isEmpty() を使う
            if ($rows->isEmpty()) break;
            $result = $callback($rows);
            if ($result === false) break;
            $offset += $size;
            // 取得件数が指定サイズより少なければ、そこで終了
        } while ($rows->count() === $size);
    }

    // =============================
    // ビルダー（内部）
    // =============================

    protected function buildSelect(): string
    {
        $dist = $this->distinct ? 'DISTINCT ' : '';
        $sql  = "SELECT {$dist}" . implode(', ', $this->select) . ' FROM ' . $this->quoteIdentifier($this->table);
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        if ($this->groupBy) $sql .= " GROUP BY {$this->groupBy}";
        if ($this->having)  $sql .= " HAVING {$this->having}";
        if ($this->orderBy) $sql .= " ORDER BY {$this->orderBy}";
        if ($this->limit  !== null) $sql .= " LIMIT {$this->limit}";
        if ($this->offset !== null) $sql .= " OFFSET {$this->offset}";
        return $sql;
    }

    protected function buildWhere(): string
    {
        if (empty($this->where)) return '';
        $parts = [];
        foreach ($this->where as $i => $w) {
            $connector = ($i === 0) ? '' : (' ' . ($w['connector'] ?? 'AND') . ' ');
            if ($w['type'] === 'group') {
                $groupParts = [];
                foreach ($w['conditions'] as $j => $c) {
                    $gc = ($j === 0) ? '' : (' ' . ($c['connector'] ?? 'AND') . ' ');
                    $groupParts[] = $gc . $c['sql'];
                }
                $parts[] = $connector . '(' . implode('', $groupParts) . ')';
            } else {
                $parts[] = $connector . $w['sql'];
            }
        }
        return ' WHERE ' . implode('', $parts);
    }

    protected function buildJoins(): string
    {
        return empty($this->joins) ? '' : ' ' . implode(' ', $this->joins);
    }

    /**
     * @return \Fzr\Collection<int, T|\stdClass>
     */
    protected function executeSelect(string $sql): \Fzr\Collection
    {
        $stmt = $this->connection->getPdo()->prepare($sql);
        $start = microtime(true);
        $stmt->execute($this->params);
        $elapsed = microtime(true) - $start;
        Logger::db($this->connection->getKey(), 3, $sql, $this->params);
        if (Tracer::isEnabled()) Tracer::recordQuery($sql, $this->params, $elapsed, $this->connection->getKey());

        $rows = [];
        if ($this->fetchClass !== null) {
            $rows = $stmt->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $this->fetchClass);
            foreach ($rows as $row) {
                if (method_exists($row, 'syncOriginal')) $row->syncOriginal();
            }
        } else {
            $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        return \Fzr\Collection::make($rows);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        $driver = $this->connection->getDriver();
        $q      = $driver === 'mysql' ? '`' : '"';

        // 関数式（括弧含む）・スペース含む複合式はそのまま返す（クォート不可な生SQL式）
        if (str_contains($identifier, '(') || str_contains($identifier, ' ')) {
            return $identifier;
        }

        return implode('.', array_map(
            function (string $part) use ($q): string {
                // * はクォートしない
                if ($part === '*') return $part;
                // すでに同じ引用符でクォート済みならそのまま返す
                if (strlen($part) >= 2 && $part[0] === $q && $part[-1] === $q) return $part;
                return $q . str_replace($q, $q . $q, $part) . $q;
            },
            explode('.', $identifier)
        ));
    }
}
