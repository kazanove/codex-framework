<?php
declare(strict_types=1);

namespace CodeX\Database;

class Migrator
{
    private \PDO $pdo; // ← Используем PDO напрямую
    private string $migrationsPath;

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = $migrationsPath;

        // Получаем конфигурацию БД
        $configPath = dirname(__DIR__) . '/config/database.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Файл конфигурации БД не найден: {$configPath}");
        }

        $config = require $configPath;
        $dbConfig = $config['connections'][$config['default']];

        // Создаём PDO напрямую
        $driver = $dbConfig['driver'];
        $dsn = match ($driver) {
            'mysql' => "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
            'pgsql' => "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};user={$dbConfig['username']};password={$dbConfig['password']}",
            'sqlite' => "sqlite:{$dbConfig['database']}",
            default => throw new \RuntimeException("Драйвер {$driver} не поддерживается в миграциях"),
        };
        $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->createMigrationsTable();
    }

    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $this->pdo->exec($sql);
    }

    public function run(): void
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);

        $lastBatch = $this->getLastBatch();
        $batch = $lastBatch + 1;

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if ($this->isMigrated($filename)) {
                continue;
            }

            require_once $file;
            $class = $this->getClassFromFile($file);
            $migration = new $class();
            $migration->up();

            $this->logMigration($filename, $batch);
            echo "Migrated: $filename\n";
        }
    }

    private function getLastBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as batch FROM migrations");
        $result = $stmt->fetch();
        return (int) ($result['batch'] ?? 0);
    }

    private function isMigrated(string $migration): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
        return (bool) $stmt->fetch();
    }

    private function logMigration(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$migration, $batch]);
    }

    private function getClassFromFile(string $file): string
    {
        $content = file_get_contents($file);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        throw new \RuntimeException("Не удалось определить класс миграции в $file");
    }

    public function rollback(): void
    {
        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) {
            echo "Нет миграций для отката\n";
            return;
        }

        $stmt = $this->pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$lastBatch]);
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($migrations as $migration) {
            $classFile = $this->migrationsPath . "/{$migration}.php";
            if (file_exists($classFile)) {
                require_once $classFile;
                $class = $this->getClassFromFile($classFile);
                $instance = new $class();
                $instance->down();
                echo "Rolled back: $migration\n";
            }

            $this->deleteMigration($migration);
        }
    }

    private function deleteMigration(string $migration): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
}