<?php
declare(strict_types=1);

namespace CodeX\Database;

abstract class Model
{
    protected static string $table;
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public static function all(): array
    {
        $rows = Connection::getInstance()->query("SELECT * FROM " . static::$table);
        return array_map(static fn($row) => new static($row), $rows);
    }

    public static function find(int $id): ?static
    {
        $row = Connection::getInstance()->queryOne("SELECT * FROM " . static::$table . " WHERE id = ?", [$id]);
        return $row ? new static($row) : null;
    }

    public static function where(string $column, $value): Builder
    {
        return (new Builder(static::class))->where($column, $value);
    }

    public function save(): bool
    {
        $connection = Connection::getInstance();

        if ($this->exists) {
            // UPDATE
            $changes = array_diff_assoc($this->attributes, $this->original);
            if (empty($changes)) {
                return true;
            }
            $set = implode(', ', array_map(fn($col) => "$col = ?", array_keys($changes)));
            $params = array_values($changes);
            $params[] = $this->attributes['id'];
            $sql = "UPDATE " . static::$table . " SET $set WHERE id = ?";
            $result = $connection->execute($sql, $params);
        } else {
            // INSERT
            $columns = implode(', ', array_keys($this->attributes));
            $placeholders = implode(', ', array_fill(0, count($this->attributes), '?'));
            $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
            $result = $connection->execute($sql, array_values($this->attributes));
            if ($result) {
                $this->attributes['id'] = $connection->lastInsertId();
                $this->exists = true;
            }
        }

        $this->original = $this->attributes;
        return (bool)$result;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $sql = "DELETE FROM " . static::$table . " WHERE id = ?";
        $result = Connection::getInstance()->execute($sql, [$this->attributes['id']]);
        if ($result) {
            $this->exists = false;
        }
        return (bool)$result;
    }

    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}