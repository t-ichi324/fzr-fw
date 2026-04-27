<?php

namespace Fzr\Tool;

/**
 * ツール基底クラス（FWコアに非依存）
 */
abstract class ToolBase
{
    protected array $args = [];

    public function __construct(array $args = [])
    {
        $this->args = $args;
    }

    abstract public function execute(): int;

    protected function out(string $msg): void { echo $msg . PHP_EOL; }
    protected function success(string $msg): void { echo "\033[32mSUCCESS: $msg\033[0m" . PHP_EOL; }
    protected function error(string $msg): void { echo "\033[31mERROR: $msg\033[0m" . PHP_EOL; }
}
