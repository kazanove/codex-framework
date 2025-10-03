<?php
declare(strict_types=1);

namespace CodeX\Database;

use PDO;
use RuntimeException;

class ConnectionFactory
{
    public static function make(array $config): ConnectionInterface
    {
        return match ($config['database']['driver']) {
            'mysql' => new MySqlConnection($config),
            'sqlite' => new SqliteConnection($config),
            'pgsql' => new PgsqlConnection($config),
            default => throw new RuntimeException("Драйвер {$config['driver']} не поддерживается"),
        };
    }
}