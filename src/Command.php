<?php

namespace Fzr;

/**
 * CLI Command Base — handles command-line arguments and user interaction.
 *
 * Use as the parent class for custom CLI tools located in `app/commands/`.
 * Typical uses: database migrations, cleanup tasks, background workers, code generation.
 *
 * - Provides colored terminal output helpers (info, success, error).
 * - Simplifies argument parsing (positional args, flags like `-f`, options like `--key=val`).
 * - Used by the `php tool` entry point to execute application-specific tasks.
 */
abstract class Command
{
    protected array $argv = [];

    public function __construct(array $argv = [])
    {
        $this->argv = $argv;
    }

    abstract public function handle(): int;

    // ── 出力ヘルパー ───────────────────────────────────────────────────

    protected function line(string $msg = ''): void
    {
        echo $msg . PHP_EOL;
    }

    protected function info(string $msg): void
    {
        echo "\033[36m{$msg}\033[0m" . PHP_EOL;
    }

    protected function success(string $msg): void
    {
        echo "\033[32m{$msg}\033[0m" . PHP_EOL;
    }

    protected function warn(string $msg): void
    {
        echo "\033[33m{$msg}\033[0m" . PHP_EOL;
    }

    protected function error(string $msg): void
    {
        echo "\033[31m{$msg}\033[0m" . PHP_EOL;
    }

    // ── 引数ヘルパー ───────────────────────────────────────────────────

    /** 位置引数を取得 (0始まり) */
    protected function arg(int $n, mixed $default = null): mixed
    {
        return $this->argv[$n] ?? $default;
    }

    /** フラグの有無を確認 (-f, --verbose など) */
    protected function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->argv, true);
    }

    /** --key=value 形式のオプションを取得 */
    protected function option(string $key): ?string
    {
        foreach ($this->argv as $a) {
            if (str_starts_with($a, "--{$key}=")) {
                return substr($a, strlen($key) + 3);
            }
        }
        return null;
    }
}
