<?php
declare(strict_types=1);

namespace CodeX\Database\Connection;

use CodeX\Database\ConnectionInterface;
use PDO;

class MySql implements ConnectionInterface
{
    private PDO $pdo;
    private \CodeX\Database\Schema\Builder\MySql $schemaBuilder;

    public function __construct(array $config)
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->schemaBuilder = new \CodeX\Database\Schema\Builder\MySql($this);
        }catch (\PDOException $e){
            throw new \RuntimeException("Ошибка подключения к MySQL: " . $e->getMessage());
        }
    }

    public function getSchemaBuilder(): \CodeX\Database\SchemaBuilder
    {
        return $this->schemaBuilder;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // НОВЫЙ МЕТОД: Возвращает ID последней вставленной записи
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}