<?php
declare(strict_types=1);

namespace CodeX\Database;

use CodeX\Application;
use PDOStatement;

class Connection
{
    private static ?ConnectionInterface $instance = null;

    public function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function prepare(string $sql): PDOStatement
    {
        return self::getInstance()->prepare($sql);
    }

    public static function getInstance(): ConnectionInterface
    {
        if (self::$instance === null) {
            $config = Application::getInstance()->config['database'];
            $dbConfig = $config['connections'][$config['default']];
            self::$instance = ConnectionFactory::make($dbConfig);
        }
        return self::$instance;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
}