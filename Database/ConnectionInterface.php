<?php
declare(strict_types=1);

namespace CodeX\Database;

interface ConnectionInterface
{
    public function getSchemaBuilder(): SchemaBuilder;
    public function query(string $sql, array $params = []): array;
    public function queryOne(string $sql, array $params = []): ?array;
    public function execute(string $sql, array $params = []): int;
    public function getDriverName(): string;
}