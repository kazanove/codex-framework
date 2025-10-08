<?php
declare(strict_types=1);

namespace CodeX\Cli;

use CodeX\Cli\Commands\Migrate;
use CodeX\Cli\Commands\MigrateRollback;
use CodeX\Cli\Commands\MakeMigration;
use CodeX\Cli\Commands\ViewClear;

class Application
{
    public static string $dir;
    private array $commands = [];

    public function __construct(string $dir)
    {
        // Прямые callable функции
        $this->command('view:clear', function () {
            $command = new ViewClear();
            $command->handle();
        });

        $this->command('make:migration', function () {
            $command = new MakeMigration();
            $command->handle();
        });

        $this->command('migrate', function () {
            $command = new Migrate();
            $command->handle();
        });

        $this->command('migrate:rollback', function () {
            $command = new MigrateRollback();
            $command->handle();
        });
        self::$dir=rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    public function command(string $name, callable $callback): void
    {
        $this->commands[$name] = $callback;
    }

    public function run(): void
    {
        global $argv;

        if (count($argv) < 2) {
            $this->showHelp();
            exit(1);
        }

        $command = $argv[1];

        if (!isset($this->commands[$command])) {
            fwrite(STDERR, "❌ Неизвестная команда: $command\n");
            $this->showHelp();
            exit(1);
        }

        ($this->commands[$command])();
    }

    private function showHelp(): void
    {
        echo "Использование: ./codex <команда>\n\n";
        echo "Доступные команды:\n";
        foreach (array_keys($this->commands) as $command) {
            echo "  • $command\n";
        }
    }
}