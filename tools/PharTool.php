<?php

namespace Fzr\Tool;

use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * php tool build
 *
 * Fzr フレームワークを単一の fzr.phar ファイルにパッケージ化します。
 * 実行には php.ini の phar.readonly = 0 が必要です。
 * または: php -d phar.readonly=0 tool build
 */
class PharTool extends ToolBase
{
    public function description(): string
    {
        return 'Package the Fzr framework into a single fzr.phar file';
    }

    public function execute(): int
    {
        if (ini_get('phar.readonly')) {
            $this->error("'phar.readonly' is enabled in your php.ini.");
            $this->out("Please run with: php -d phar.readonly=0 tool build");
            return 1;
        }

        // fzr エントリポイントの隣がプロジェクトルート
        // src/Command/PharCommand.php → src/ → project root
        $rootPath     = dirname(dirname(__FILE__));
        $srcPath      = $rootPath . DIRECTORY_SEPARATOR . 'src';
        $incPath  = $rootPath . DIRECTORY_SEPARATOR . 'inc';
        $outFile      = $rootPath . DIRECTORY_SEPARATOR . 'fzr.phar';

        if (!is_dir($srcPath)) {
            $this->error("src/ directory not found at: {$srcPath}");
            return 1;
        }

        if (file_exists($outFile)) {
            unlink($outFile);
        }

        $this->out("Building Fzr Phar...");
        $this->out("Source : $srcPath");
        if (is_dir($incPath)) {
            $this->out("Inc    : $incPath");
        }
        $this->out("Output : $outFile");

        try {
            $p = new Phar($outFile, 0, 'fzr.phar');

            // src/ → phar内パス "src/Engine.php" 等
            $dirIter = new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $iter    = new RecursiveIteratorIterator($dirIter);
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $rel = 'src' . DIRECTORY_SEPARATOR . str_replace($srcPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $p->addFile($file->getPathname(), $rel);
                    $this->out("  + $rel");
                }
            }

            // inc/ → phar内パス "inc/helpers.php" 等
            if (is_dir($incPath)) {
                $dirIter = new RecursiveDirectoryIterator($incPath, RecursiveDirectoryIterator::SKIP_DOTS);
                $iter    = new RecursiveIteratorIterator($dirIter);
                foreach ($iter as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $rel = 'inc' . DIRECTORY_SEPARATOR . str_replace($incPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        $p->addFile($file->getPathname(), $rel);
                        $this->out("  + $rel");
                    }
                }
            }

            $stub = <<<'PHP'
<?php
Phar::mapPhar('fzr.phar');
if (!defined('FZR_PHAR')) define('FZR_PHAR', true);

// 1. まずオートローダーを登録する
spl_autoload_register(function($class) {
    if (strpos($class, 'Fzr\\') === 0) {
        $file = 'phar://fzr.phar/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// 2. 次にヘルパーとエイリアスを読み込む（これでクラスが解決可能になる）
if (file_exists('phar://fzr.phar/inc/helpers.php')) {
    require 'phar://fzr.phar/inc/helpers.php';
}

if (file_exists('phar://fzr.phar/inc/aliases.php')) {
    require 'phar://fzr.phar/inc/aliases.php';
}

__HALT_COMPILER();
PHP;

            $p->setStub($stub);
            $p->stopBuffering();

            $this->out('');
            $this->success('Created: ' . $outFile . ' (' . round(filesize($outFile) / 1024, 2) . ' KB)');
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
