<?php
declare(strict_types=1);

namespace CodeX\Database\Schema;

use CodeX\Database\ConnectionInterface;
use RuntimeException;

class Blueprint
{
    private ?string $lastColumn = null;      // Для CREATE TABLE

    private string $table;
    private string $driver;
    private ?ConnectionInterface $connection = null;
    private array $columns = [];
    private array $alterCommands = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    public function __construct(string $table, string $driver = 'mysql')
    {
        $this->table = $table;
        $this->driver = $driver;
    }

    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    public function foreignId(string $name): static
    {
        $this->integer($name);
        return $this;
    } // Для отслеживания последней колонки

    public function integer(string $name): ColumnDefinition
    {
        return new ColumnDefinition($this, 'INT', $name);
    }

// ========== Вспомогательные методы для колонок ========== //

    public function dropColumn(string $name): void
    {
        $this->alterCommands[] = "DROP COLUMN `$name`";
    }

    public function id(): static
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1]['function'] ?? '';

        if ($caller === 'table') {
// Нельзя добавлять id в существующую таблицу через ALTER
            throw new RuntimeException('Нельзя добавить колонку id в существующую таблицу');
        } else {
            $this->columns[] = "id INT AUTO_INCREMENT PRIMARY KEY";
        }
        return $this;
    }

// ========== Типы колонок ========== //

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return new ColumnDefinition($this, 'VARCHAR(' . $length . ')', $name);
    }

    public function text(string $name): ColumnDefinition
    {
        return new ColumnDefinition($this, 'TEXT', $name);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return new ColumnDefinition($this, 'TINYINT(1)', $name);
    }

    public function timestamps(): static
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1]['function'] ?? '';

        if ($caller === 'table') {
            $this->addColumnToAlter("created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $this->addColumnToAlter("updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } else {
            $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
            $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        }
        return $this;
    }

    private function addColumnToAlter(string $definition, ?string $after = null): void
    {
        $command = "ADD COLUMN $definition";
        if ($after) {
            $command .= " AFTER `$after`";
        }
        $this->alterCommands[] = $command;
    }

    public function addColumn(string $type, string $name, array $options = []): void
    {
        // Проверяем, вызывается ли в контексте ALTER TABLE
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[2]['function'] ?? '';

        if ($caller === 'alterTable' && $this->connection) {
            // Проверяем существование колонки
            if ($this->connection->getSchemaBuilder()->hasColumn($this->table, $name)) {
                // Колонка уже существует — пропускаем
                return;
            }
        }

        $definition = $this->createColumnDefinition($type, $name, $options);

        if ($caller === 'alterTable') {
            $after = $options['after'] ?? null;
            $this->addColumnToAlter($definition, $after);
        } else {
            $this->columns[] = $definition;
        }
        if (!empty($options['unique'])) {
            $this->indexes[] = "UNIQUE INDEX idx_{$this->table}_{$name} ({$name})";
        } elseif (!empty($options['index'])) {
            $this->indexes[] = "INDEX idx_{$this->table}_{$name} ({$name})";
        }
    }


// ========== Внутренние методы для ColumnDefinition ========== //

    private function createColumnDefinition(string $type, string $name, array $options = []): string
    {
        $definition = "$name $type";

        if (!empty($options['nullable'])) {
            $definition .= ' NULL';
        } else {
            $definition .= ' NOT NULL';
        }

        if (isset($options['default'])) {
            $default = is_string($options['default']) ? "'{$options['default']}'" : $options['default'];
            $definition .= " DEFAULT $default";
        }

        return $definition;
    }


    public function addForeignKey(string $column, string $references, string $on, string $onDelete, string $onUpdate): void
    {
        $this->foreignKeys[] = "CONSTRAINT fk_{$this->table}_{$column} FOREIGN KEY ({$column}) REFERENCES {$on}({$references}) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
    }

    public function toSql(): string
    {
        $columns = implode(",\n        ", $this->columns);
        $indexes = !empty($this->indexes) ? ",\n        " . implode(",\n        ", $this->indexes) : '';
        $foreignKeys = !empty($this->foreignKeys) ? ",\n        " . implode(",\n        ", $this->foreignKeys) : '';
        return "CREATE TABLE {$this->table} (\n        {$columns}{$indexes}{$foreignKeys}\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    public function toAlterSql(): array
    {
        $sql = [];

        // ALTER для колонок
        if (!empty($this->alterCommands)) {
            $sql[] = "ALTER TABLE {$this->table} " . implode(', ', $this->alterCommands);
        }

        // ALTER для индексов
        foreach ($this->indexes as $index) {
            $sql[] = "ALTER TABLE {$this->table} ADD {$index}";
        }

        // ALTER для внешних ключей
        foreach ($this->foreignKeys as $foreignKey) {
            $sql[] = "ALTER TABLE {$this->table} ADD {$foreignKey}";
        }

        return $sql;
    }
}