<?php

namespace Fzr\Db;

/**
 * Entity Code Generator — generates Entity class files from database schema.
 *
 * 開発時のコード生成専用（実行時のクエリ処理とは無関係）。
 * 通常は app/commands/make-model.php 等の CLI コマンドから呼び出す。
 *
 * - Reads table definitions via {@see Db::tables()} / {@see Db::schema()}.
 * - Maps DB types to PHP types and emits validation attributes (#[Label], #[Required], ...).
 */
class ModelGenerator
{
    /** 単数形化しない例外語（末尾要素がこれに一致したら s を剥がさない） */
    private const SINGULARIZE_EXCEPTIONS = ['status', 'news', 'address', 'access', 'analysis', 'basis'];

    /**
     * Entity クラスファイルを生成
     *
     * @param  string   $outDir        出力ディレクトリ
     * @param  bool     $force         既存ファイルを上書きする
     * @param  string[] $tables        対象テーブル（空 = 全テーブル）
     * @param  string   $connectionKey 接続キー
     * @return array{generated: list<array{table:string,class:string}>, skipped: list<array{table:string,class:string}>}
     */
    public static function generate(
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

        $allTables = empty($tables) ? Db::tables($connectionKey) : $tables;

        foreach ($allTables as $table) {
            if ($table === 'migrations') continue;

            $className = self::tableToClass($table);
            $filePath  = rtrim($outDir, '/\\') . '/' . $className . '.php';

            if (file_exists($filePath) && !$force) {
                $skipped[] = ['table' => $table, 'class' => $className];
                continue;
            }

            $columns = Db::schema($table, $connectionKey);
            file_put_contents($filePath, self::renderModel($className, $table, $columns));
            $generated[] = ['table' => $table, 'class' => $className];
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private static function tableToClass(string $table): string
    {
        // 末尾 s を除いて単数形化。ss 終わりと例外語（status 等）は複数形とみなさない
        $last = ($p = strrpos($table, '_')) === false ? $table : substr($table, $p + 1);
        $singular = $table;
        if (!str_ends_with($table, 'ss') && !in_array($last, self::SINGULARIZE_EXCEPTIONS, true)) {
            $singular = preg_replace('/s$/', '', $table) ?: $table;
        }
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
            if ($col['length'] && $type === 'string') $attrs[] = "#[Max({$col['length']})]";
            if (strpos($col['type'], 'int') !== false) $attrs[] = '#[Numeric]';
            if ($name === 'email') $attrs[] = '#[Email]';

            $attrStr = implode("\n    ", $attrs);
            $props  .= "    $attrStr\n    public $type \${$name};\n\n";
        }

        return <<<PHP
        <?php

        use Fzr\Db\Entity;
        use Fzr\Attr\Field\{Label, Required, Max, Numeric, Email};

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
