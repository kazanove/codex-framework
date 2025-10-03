<?php
declare(strict_types=1);

namespace CodeX\Database;

abstract class SchemaBuilder
{
    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    abstract public function hasColumn(string $table, string $column): bool;

    abstract public function createTable(string $table, callable $callback): void;

    abstract public function alterTable(string $table, callable $callback): void;

    abstract public function dropTable(string $table): void;
}