<?php
declare(strict_types=1);

namespace CodeX\Cli\Commands;

use CodeX\Cli\Command;
use CodeX\Database\Migrator;

class Migrate extends Command
{
    protected string $signature = 'migrate';
    protected string $description = 'Запустить все миграции';

    public function handle(): int
    {
        $migrationsPath = ROOT . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $migrator = new Migrator($migrationsPath);
        $migrator->run();
        $this->info('Миграции успешно выполнены.');
        return 0;
    }
}