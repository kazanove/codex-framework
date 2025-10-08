<?php
declare(strict_types=1);

namespace CodeX\Database\Schema;

use CodeX\Database\Connection;

class Builder
{
    public static function create(string $table, callable $callback): void
    {
        $connection = Connection::getInstance();
        $connection->getSchemaBuilder()->createTable($table, $callback);
    }

    public function table(string $table, callable $callback): void
    {
        $connection = Connection::getInstance();
        $connection->getSchemaBuilder()->alterTable($table, $callback);
    }

    public static function drop(string $table): void
    {
        $connection = Connection::getInstance();
        $connection->getSchemaBuilder()->dropTable($table);
    }

    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }
}