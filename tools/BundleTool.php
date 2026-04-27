<?php

namespace Fzr\Tool;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * php tool bundle
 *
 * Fzr フレームワークの全ソースコードを単一の fzr.php ファイルに連結します。
 * エディタの補完を効かせつつ、1ファイルでの配布を可能にします。
 */
class BundleTool extends ToolBase
{
    public function description(): string
    {
        return 'Bundle the Fzr framework into a single fzr.php file (IDE friendly)';
    }

    public function execute(): int
    {
        $rootPath = dirname(dirname(__FILE__));
        $srcPath  = $rootPath . DIRECTORY_SEPARATOR . 'src';
        $incPath  = $rootPath . DIRECTORY_SEPARATOR . 'inc';
        $outFile  = $rootPath . DIRECTORY_SEPARATOR . 'fzr.bundle.php';

        if (!is_dir($srcPath)) {
            $this->error("src/ directory not found.");
            return 1;
        }

        $this->out("Bundling Fzr into a single file...");
        $this->out("Output : $outFile");

        $now = date('Y-m-d H:i:s');
        $content = "<?php\n";
        $content .= "/**\n * Fzr Framework Bundle (Single File Edition)\n * Generated: " . $now . "\n */\n\n";
        $content .= "namespace {\n    if (!defined('ABSPATH')) define('ABSPATH', __DIR__);\n    if (!defined('FZR_BUNDLED')) define('FZR_BUNDLED', true);\n    if (!defined('FZR_BUNDLE_TIME')) define('FZR_BUNDLE_TIME', '{$now}');\n}\n\n";

        // 1. src 配下のファイルを収集 (Engine.php を優先的に前に持ってくる)
        $files = $this->collectFiles($srcPath);

        // 2. inc 配下のファイルを収集 (helpers.php を先に)
        $incFiles = $this->collectFiles($incPath);

        $allFiles = array_merge($files, $incFiles);

        foreach ($allFiles as $filePath) {
            $rel = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $filePath);
            $this->out("  + $rel");
            $content .= $this->processFile($filePath);
        }

        if (file_put_contents($outFile, $content)) {
            $this->out('');
            $this->success("Created: $outFile (" . round(filesize($outFile) / 1024, 2) . " KB)");
            return 0;
        }

        $this->error("Failed to write to $outFile");
        return 1;
    }

    private function collectFiles(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $results = [];
        $dirIter = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iter    = new RecursiveIteratorIterator($dirIter);

        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $results[] = $file->getPathname();
            }
        }

        // 基本クラスを先に読み込むためのソート
        usort($results, function ($a, $b) {
            // 基底クラスを優先
            $priority = ['Config.php', 'Path.php', 'Env.php', 'Context.php', 'Model.php', 'Controller.php', 'Loader.php', 'Engine.php', 'helpers.php'];
            foreach ($priority as $p) {
                if (str_ends_with($a, $p)) return -1;
                if (str_ends_with($b, $p)) return 1;
            }
            return strcmp($a, $b);
        });

        return $results;
    }

    private function processFile(string $path): string
    {
        $code = file_get_contents($path);
        // <?php タグを除去
        $code = preg_replace('/^<\?php\s*/i', '', $code);

        // namespace の抽出
        $namespace = '';
        if (preg_match('/namespace\s+([^;{\s]+)\s*;/i', $code, $m)) {
            $namespace = $m[1];
            // 行末までの namespace ...; を削除
            $code = preg_replace('/namespace\s+([^;{\s]+)\s*;/i', '', $code, 1);
        }

        $code = trim($code);
        $fileTag = "// File: " . basename($path) . "\n";

        if ($namespace) {
            return "namespace {$namespace} {\n{$fileTag}{$code}\n}\n\n";
        } else {
            return "namespace {\n{$fileTag}{$code}\n}\n\n";
        }
    }
}
