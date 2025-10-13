<?php
declare(strict_types=1);

namespace CodeX\Cli;

use CodeX\Cli\Commands\Migrate;
use CodeX\Cli\Commands\MigrateRollback;
use CodeX\Cli\Commands\MakeMigration;
use CodeX\Cli\Commands\ViewClear;
use ErrorException;

class Application extends \CodeX\Application
{
    public static string $dir;
    private array $commands = [];

    public function __construct(string $dir)
    {
        parent::__construct($dir);
        restore_exception_handler();
        restore_error_handler();
        // CLI-обработчик исключений
        set_exception_handler(static function (\Throwable $e) {
            fwrite(STDERR, "\033[0;31m❌ Ошибка: " . $e->getMessage() . "\033[0m\n");
            fwrite(STDERR, "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n");
            foreach ($e->getTrace() as $trace) {
                $message='';
                if(isset($trace['class'])){
                    $message.=$trace['class'].$trace['type'].$trace['function'].'(';
                    $message.=') - ';
                }
                $message.=$trace['file'].': '.$trace['line'];
                fwrite(STDERR, "\033[0;31m❌ Ошибка: " . $message . "\033[0m\n");

            }
            if ($e instanceof \PDOException) {
                fwrite(STDERR, "SQL: " . $e->getMessage() . "\n");
            }
            exit(1); // Завершаем с кодом 1 (ошибка)
        });

        // CLI-обработчик ошибок (E_WARNING, E_NOTICE и т.д.)
        set_error_handler(/**
         * @throws ErrorException
         */ static function (int $severity, string $message, string $file, int $line, array $trace = []) {
            $errorType = match ($severity) {
                E_ERROR => 'Ошибка',
                E_WARNING => 'Предупреждение',
                E_PARSE => 'Синтаксическая ошибка',
                E_NOTICE => 'Замечание',
                E_CORE_ERROR => 'Критическая ошибка ядра',
                E_CORE_WARNING => 'Предупреждение ядра',
                E_COMPILE_ERROR => 'Ошибка компиляции',
                E_COMPILE_WARNING => 'Предупреждение компиляции',
                E_USER_ERROR => 'Пользовательская ошибка',
                E_USER_WARNING => 'Пользовательское предупреждение',
                E_USER_NOTICE => 'Пользовательское замечание',
                E_RECOVERABLE_ERROR => 'Восстанавливаемая ошибка',
                E_DEPRECATED => 'Устаревший код',
                E_USER_DEPRECATED => 'Устаревший пользовательский код',
                default => 'Неизвестная ошибка',
            };

            fwrite(STDERR, "\033[0;33m⚠️  {$errorType}: {$message}\033[0m\n");
            fwrite(STDERR, "Файл: {$file}:{$line}\n");

            // Для критических ошибок (E_ERROR, E_PARSE, E_CORE_ERROR и т.д.) выбрасываем исключение
            if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        });
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