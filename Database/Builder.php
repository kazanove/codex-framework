<?php
declare(strict_types=1);

namespace CodeX\Database;

class Builder
{
    private string $modelClass;
    private string $sql;
    private array $params = [];
    private array $wheres = [];

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->sql = "SELECT * FROM " . $modelClass::$table;
    }

    public function where(string $column, $value): static
    {
        $this->wheres[] = [$column, '=', $value];
        return $this;
    }

    public function first(): ?object
    {
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function get(): array
    {
        $sql = $this->sql;
        $params = [];

        if (!empty($this->wheres)) {
            $whereClauses = [];
            foreach ($this->wheres as [$column, $operator, $value]) {
                $whereClauses[] = "$column $operator ?";
                $params[] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $rows = Connection::getInstance()->query($sql, $params);
        return array_map(fn($row) => new $this->modelClass($row), $rows);
    }
}