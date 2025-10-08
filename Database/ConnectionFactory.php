<?php
declare(strict_types=1);

namespace CodeX\Database;

use RuntimeException;

class ConnectionFactory
{
    public static function make(array $config): ConnectionInterface
    {
        return match ($config['driver']) {
            'mysql' => new Connection\MySql($config),
            'sqlite' => new Connection\Sqlite($config),
            'pgsql' => new Connection\Pgsql($config),
            default => throw new RuntimeException("Драйвер {$config['driver']} не поддерживается"),
        };
    }
}