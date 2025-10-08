<?php
declare(strict_types=1);

namespace CodeX\Cli\Commands;

use CodeX\Cli\Command;
use RuntimeException;

class MakeMigration extends Command
{
    protected string $signature = 'make:migration {name : Имя миграции}';
    protected string $description = 'Создать новую миграцию';

    public function handle(): int
    {
        global $argv;

        // Получаем имя миграции из аргументов
        $name = $argv[2] ?? null;
        if (!$name) {
            throw new RuntimeException("Требуется имя миграции");
        }

        $migrationsPath = \Codex\Cli\Application::$dir . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        // Создаём директорию, если её нет
        if (!is_dir($migrationsPath) && !mkdir($migrationsPath, 0755, true) && !is_dir($migrationsPath)) {
            throw new RuntimeException("Не удалось создать директорию: {$migrationsPath}");
        }

        // Генерируем имя файла с временной меткой
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        $filePath = $migrationsPath . DIRECTORY_SEPARATOR . $fileName;

        // Шаблон миграции
        $stub = $this->getStub($name);

        // Записываем файл
        file_put_contents($filePath, $stub);

        $this->info("Миграция создана: {$fileName}");
        return 0;
    }

    private function getStub(string $name): string
    {
        // Преобразуем имя в класс (create_users_table → CreateUsersTable)
        $className = $this->classify($name);

        return "<?php
declare(strict_types=1);

use CodeX\Database\Migration;
use CodeX\Database\Schema\Builder as Schema;

class {$className} extends Migration
{
    public function up(): void
    {
        // Schema::create('table', function (\$table) {
        //     \$table->id();
        //     \$table->string('name');
        //     \$table->timestamps();
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('table');
    }
}
";
    }

    /**
     * Преобразует snake_case в CamelCase
     */
    private function classify(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}