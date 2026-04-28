<?php

namespace Fzr\Db;

use Fzr\Collection;

/**
 * Active Record Entity — maps database tables to typed PHP objects.
 *
 * Use to represent a single row in a database table with property-based access.
 * Typical uses: persistent data models, business logic encapsulation, CRUD operations.
 *
 * - Extends {@see \Fzr\Model} to provide typed properties with DB persistence logic.
 * - Supports automatic model generation from database schema via `generateModels()`.
 * - Provides simple CRUD methods (`save()`, `delete()`, `find()`).
 * - Uses PHP 8 Attributes (#[Table], #[Id], #[Column]) for schema mapping.
 *
 * @template T of static
 */
abstract class Entity extends \Fzr\Model
{
    protected static ?string $connectionKey = null;
    protected static ?string $table = null;
    protected static ?string $primaryKey = 'id';
    /** DB から取得した時の初期状態 */
    protected array $_original = [];

    /** テーブル名取得 */
    public static function tableName(): string
    {
        if (static::$table !== null) return static::$table;
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }

    /** プライマリキー名取得 */
    public static function primaryKeyName(): string
    {
        return static::$primaryKey ?? 'id';
    }

    /** 接続取得 */
    protected static function connection(): Connection
    {
        $key = static::$connectionKey ?: 'default';
        return Db::connection($key);
    }

    /**
     * クエリビルダ取得
     *
     * @return Query<static>
     */
    public static function query(): Query
    {
        return (new Query(static::connection(), static::tableName()))->entity(static::class);
    }

    /**
     * 全取得
     *
     * @return \Fzr\Collection<int, static>
     */
    public static function all(): \Fzr\Collection
    {
        return static::query()->all();
    }

    /**
     * ID 指定で一件取得
     *
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        /** @var static|null */
        return static::query()->where(static::primaryKeyName(), $id)->first();
    }

    /**
     * 条件に一致する最初の1件を取得
     *
     * @return static|null
     */
    public static function first(string|array|\Closure $field, mixed $op = null, mixed $value = null): ?static
    {
        /** @var static|null */
        return static::where($field, $op, $value)->first();
    }

    /**
     * WHERE 検索（クエリビルダ継続）
     *
     * @return Query<static>
     */
    public static function where(string|array|\Closure $field, mixed $op = null, mixed $value = null): Query
    {
        return static::query()->where($field, $op, $value);
    }

    /** 件数取得 */
    public static function count(): int
    {
        return static::query()->count();
    }

    /** 存在確認（ID 指定） */
    public static function exists(int|string $id): bool
    {
        return static::query()->where(static::primaryKeyName(), $id)->exists();
    }

    /**
     * 条件に一致する最初のレコードを取得、なければ作成
     *
     * @param  array<string, mixed> $attributes 検索条件
     * @param  array<string, mixed> $values     新規作成時に追加するデータ
     * @return static
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $q = static::query();
        foreach ($attributes as $k => $v) {
            $q->where($k, $v);
        }
        /** @var static|null $existing */
        $existing = $q->first();
        if ($existing !== null) return $existing;

        $data = array_merge($attributes, $values);
        $id   = static::query()->insert($data);

        /** @var static */
        return static::find($id) ?? static::query()
            ->where(static::primaryKeyName(), $id)
            ->first();
    }

    /**
     * INSERT（新規作成）
     *
     * @return int|string lastInsertId
     */
    public static function create(array $data): int|string
    {
        return static::query()->insert($data);
    }

    /**
     * UPDATE（ID 指定）
     *
     * @return int 更新行数
     */
    public static function updateById(int|string $id, array $data): int
    {
        return static::query()->where(static::primaryKeyName(), $id)->update($data);
    }

    /**
     * DELETE（ID 指定）
     *
     * @return int 削除行数
     */
    public static function deleteById(int|string $id): int
    {
        return static::query()->where(static::primaryKeyName(), $id)->delete();
    }

    // =============================
    // インスタンスメソッド（ActiveRecord 風）
    // =============================

    /** プライマリキー値取得 */
    public function pkValue(): mixed
    {
        $pk = static::primaryKeyName();
        return $this->$pk ?? null;
    }

    /** 現在の状態を初期状態として同期する */
    public function syncOriginal(): static
    {
        $this->_original = $this->toArray();
        return $this;
    }

    /** 変更されたデータ（Dirty Data）のみを取得 */
    public function getDirty(): array
    {
        $current = $this->toArray();
        $dirty = [];
        foreach ($current as $key => $value) {
            // まだ初期状態がない（新規）場合は null 以外をすべて対象にする
            if (!array_key_exists($key, $this->_original)) {
                if ($value !== null) $dirty[$key] = $value;
                continue;
            }
            // 変更がある場合のみ対象にする
            if ($value !== $this->_original[$key]) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * 保存（PK があれば UPDATE、なければ INSERT）
     *
     * @return bool
     */
    public function save(): bool
    {
        $pk    = static::primaryKeyName();
        $pkVal = $this->pkValue();
        $isUpdate = ($pkVal !== null && $pkVal !== '' && $pkVal !== 0);

        if ($isUpdate) {
            // UPDATE: 変更があった（Dirty）データのみを更新
            $dirty = $this->getDirty();
            if (empty($dirty)) return true; // 変更なし

            unset($dirty[$pk]); // PK は更新対象外
            $ok = static::query()->where($pk, $pkVal)->update($dirty) >= 0;
            if ($ok) $this->syncOriginal();
            return $ok;
        }

        // INSERT: null 以外のデータを対象にする（DB デフォルト値を活かすため）
        $data = $this->toArray();
        if (isset($data[$pk]) && ($data[$pk] === null || $data[$pk] === '' || $data[$pk] === 0)) {
            unset($data[$pk]);
        }
        $insertData = array_filter($data, fn($v) => $v !== null);

        $newId = static::query()->insert($insertData);
        if ($newId) {
            $this->$pk = $newId;
            $this->syncOriginal();
        }
        return (bool)$newId;
    }

    /**
     * 削除（インスタンスの PK 値を使って DELETE）
     *
     * @return bool
     */
    public function delete(): bool
    {
        $pkVal = $this->pkValue();
        if ($pkVal === null || $pkVal === '' || $pkVal === 0) return false;
        return static::query()->where(static::primaryKeyName(), $pkVal)->delete() > 0;
    }

    /**
     * リロード（DB から最新値を再取得）
     *
     * @return static|null
     */
    public function fresh(): ?static
    {
        $pkVal = $this->pkValue();
        if ($pkVal === null) return null;
        return static::find($pkVal);
    }
}
