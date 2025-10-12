<?php
declare(strict_types=1);

namespace CodeX\Database\Schema;

class ColumnDefinition
{
    private Blueprint $blueprint;
    private string $type;
    private string $name;
    private array $options = [];

    public function __construct(Blueprint $blueprint, string $type, string $name)
    {
        $this->blueprint = $blueprint;
        $this->type = $type;
        $this->name = $name;
    }

    public function nullable(): static
    {
        $this->options['nullable'] = true;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->options['default'] = $value;
        return $this;
    }

    public function after(string $column): static
    {
        $this->options['after'] = $column;
        return $this;
    }

    public function __destruct()
    {
        // Автоматически добавляем колонку при уничтожении объекта
        $this->blueprint->addColumn($this->type, $this->name, $this->options);
    }
    public function unique(): static
    {
        $this->options['unique'] = true;
        return $this;
    }

    public function index(): static
    {
        $this->options['index'] = true;
        return $this;
    }

    public function foreign(string $references, ?string $on = null): ForeignKeyDefinition
    {
        $on = $on ?? $this->blueprint->getTable(); // предполагаем имя таблицы
        return new ForeignKeyDefinition($this->blueprint, $this->name, $references, $on);
    }
}