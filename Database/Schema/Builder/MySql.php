<?php
declare(strict_types=1);

namespace CodeX\Database\Schema\Builder;

use CodeX\Database\Schema\Blueprint;
use CodeX\Database\SchemaBuilder;

class MySql extends SchemaBuilder
{
    public function hasColumn(string $table, string $column): bool
    {
        $sql = "SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ?";

        $result = $this->connection->query($sql, [$table, $column]);
        return !empty($result);
    }

    public function createTable(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, 'mysql');
        $callback($blueprint);
        $sql = $blueprint->toSql();
        $this->connection->execute($sql);
    }

    public function alterTable(string $table, callable $callback): void
    {
        // Проверяем существование колонок перед добавлением
        $blueprint = new Blueprint($table, 'mysql');
        $blueprint->setConnection($this->connection);
        $callback($blueprint);

        $alterSql = $blueprint->toAlterSql();
        foreach ($alterSql as $sql) {
            $this->connection->execute($sql);
        }
    }

    public function dropTable(string $table): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS `{$table}`");
    }
}