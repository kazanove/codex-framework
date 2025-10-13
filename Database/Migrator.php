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

        // Загружаем конфигурацию
        $configPath = ROOT . 'CodeX/config/core.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Файл конфигурации не найден: {$configPath}");
        }

        $config = require $configPath;
        $dbConfig = $config['database']['connections'][$config['database']['default']];

        // Создаём БД если её нет (только для MySQL/PostgreSQL)
        $this->createDatabaseIfNotExists($dbConfig);

        // Подключаемся к БД
        $this->connectToDatabase($dbConfig);

        $this->createMigrationsTable();
    }

    private function createMigrationsTable(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $sql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
                break;

            case 'pgsql':
                $sql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL
                );
            ";
                break;

            case 'sqlite':
                $sql = "
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL
                );
            ";
                break;

            default:
                throw new \RuntimeException("Драйвер {$driver} не поддерживается для миграций");
        }

        $this->pdo->exec($sql);
        if (!$this->tableExists('migrations')) {
            throw new \RuntimeException("Не удалось создать таблицу migrations");
        }
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

            require $file;
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
    private function createDatabaseIfNotExists(array $dbConfig): void
    {
        $driver = $dbConfig['driver'];

        if ($driver === 'sqlite') {
            $dir = dirname($dbConfig['database']);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Не удалось создать директорию для SQLite: {$dir}");
            }
            return;
        }

        if (!in_array($driver, ['mysql', 'pgsql'])) {
            return;
        }

        try {
            // Сначала проверяем, существует ли БД
            $tempDsn = $this->getDsnWithoutDatabase($dbConfig);
            $tempPdo = new \PDO($tempDsn, $dbConfig['username'], $dbConfig['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $dbName = $dbConfig['database'];
            $exists = false;

            if ($driver === 'mysql') {
                $stmt = $tempPdo->prepare("SHOW DATABASES LIKE ?");
                $stmt->execute([$dbName]);
                $exists = (bool) $stmt->fetch();
            } elseif ($driver === 'pgsql') {
                $stmt = $tempPdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
                $stmt->execute([$dbName]);
                $exists = (bool) $stmt->fetch();
            }

            if (!$exists) {
                // Создаём БД
                $charset = $dbConfig['charset'] ?? ($driver === 'mysql' ? 'utf8mb4' : 'utf8');
                if ($driver === 'mysql') {
                    $sql = "CREATE DATABASE `{$dbName}` CHARACTER SET {$charset}";
                } else {
                    $sql = "CREATE DATABASE \"{$dbName}\"";
                }
                $tempPdo->exec($sql);
                echo "✅ База данных '{$dbName}' создана успешно.\n";
            }
            // Если БД существует — ничего не выводим

        } catch (\PDOException $e) {
            throw new \RuntimeException("Ошибка проверки/создания БД: " . $e->getMessage());
        }
    }
    private function getDsnWithoutDatabase(array $dbConfig): string
    {
        $driver = $dbConfig['driver'];

        if ($driver === 'mysql') {
            return "mysql:host={$dbConfig['host']};port={$dbConfig['port']}";
        }

        if ($driver === 'pgsql') {
            return "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};user={$dbConfig['username']};password={$dbConfig['password']}";
        }

        throw new \RuntimeException("Драйвер {$driver} не поддерживается для создания БД");
    }
    private function connectToDatabase(array $dbConfig): void
    {
        $driver = $dbConfig['driver'];

        if ($driver === 'sqlite') {
            $dsn = "sqlite:{$dbConfig['database']}";
        } elseif ($driver === 'mysql') {
            $charset = $dbConfig['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$charset}";
        } elseif ($driver === 'pgsql') {
            $charset = $dbConfig['charset'] ?? 'utf8';
            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};user={$dbConfig['username']};password={$dbConfig['password']};charset={$charset}";
        } else {
            throw new \RuntimeException("Драйвер {$driver} не поддерживается");
        }

        $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }
    private function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $sql = "SHOW TABLES LIKE ?";
                break;
            case 'pgsql':
                $sql = "SELECT tablename FROM pg_tables WHERE tablename = ?";
                break;
            case 'sqlite':
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
                break;
            default:
                return false;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$table]);
        return (bool) $stmt->fetch();
    }
}