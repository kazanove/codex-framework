<?php
declare(strict_types=1);

namespace CodeX\Database\Schema;

class ForeignKeyDefinition
{
    private Blueprint $blueprint;
    private string $column;
    private string $references;
    private string $on;
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';

    public function __construct(Blueprint $blueprint, string $column, string $references, string $on)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
        $this->references = $references;
        $this->on = $on;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function __destruct()
    {
        $this->blueprint->addForeignKey(
            $this->column,
            $this->references,
            $this->on,
            $this->onDelete,
            $this->onUpdate
        );
    }
}