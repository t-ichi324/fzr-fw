#!/usr/bin/env php
<?php
/**
 * Fzr CLI Entrypoint
 *
 * Usage:
 *   php tool <command> [options]
 *
 * Commands:
 *   init         Initialize a new Fzr project (interactive)
 *   build        Package framework into fzr.phar
 *   bundle       Bundle framework into fzr.php (Single File)
 */

// ツール用オートローダー（FWコア非依存）
spl_autoload_register(function ($class) {
    if (strpos($class, 'Fzr\\Tool\\') === 0) {
        $file = __DIR__ . '/tools/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (file_exists($file)) require $file;
    }
});

require_once __DIR__ . '/tools/ToolBase.php';

$argv    = $_SERVER['argv'];
$command = $argv[1] ?? 'help';

$map = [
    'init'   => \Fzr\Tool\InitTool::class,
    'build'  => \Fzr\Tool\PharTool::class,
    'bundle' => \Fzr\Tool\BundleTool::class,
];

if ($command === 'help' || $command === '--help' || $command === '-h') {
    $version = '1.0.0';
    echo "\033[1;36mFzr Framework\033[0m v{$version}\n\n";
    echo "\033[1mUsage:\033[0m php tool <command> [options]\n\n";
    echo "\033[33mAvailable commands:\033[0m\n";
    echo "  init         Initialize a new Fzr project (interactive)\n";
    echo "  build        Package framework into fzr.phar\n";
    echo "  bundle       Bundle framework into fzr.php (Single File)\n";
    exit(0);
}

if (!isset($map[$command])) {
    echo "\033[31m[ERROR]\033[0m Unknown command: {$command}\n";
    echo "Run 'php tool help' for available commands.\n";
    exit(1);
}

$class    = $map[$command];
$instance = new $class(array_slice($argv, 2));
exit($instance->execute());
