<?php
declare(strict_types=1);

namespace CodeX\Cli;

abstract class Command
{
    protected string $signature;
    protected string $description;

    public function __invoke(): int
    {
        return $this->handle();
    }

    abstract public function handle(): int;

    protected function info(string $message): void
    {
        echo "\033[0;32m" . $message . "\033[0m\n";
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, "\033[0;31m" . $message . "\033[0m\n");
    }

    protected function argument(string $key): string
    {
        global $argv;
        $index = array_search("--$key", $argv) ?: array_search("-$key", $argv);
        if ($index && isset($argv[$index + 1])) {
            return $argv[$index + 1];
        }
        throw new \RuntimeException("Аргумент {$key} не найден");
    }
}