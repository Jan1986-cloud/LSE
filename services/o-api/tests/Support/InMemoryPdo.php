<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests\Support;

use PDO;
use RuntimeException;

final class InMemoryPdo extends PDO
{
    private array $tables = [
        'cms_token_logs' => [],
        'cms_sources' => [],
    ];

    private array $autoIncrement = [
        'cms_token_logs' => 0,
        'cms_sources' => 0,
    ];

    private ?int $lastInsertId = null;
    private string $driver;

    public function __construct(string $driver = 'sqlite')
    {
        $this->driver = $driver;
    }

    public function getAttribute($attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return $this->driver;
        }

        return null;
    }

    public function prepare($query, $options = []): InMemoryStatement|false
    {
        return new InMemoryStatement($this, (string) $query);
    }

    public function query($query, $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetchModeArgs): InMemoryQueryStatement|false
    {
        $queryString = strtolower((string) $query);

        if (str_starts_with($queryString, 'select * from cms_token_logs')) {
            return new InMemoryQueryStatement($this->tables['cms_token_logs']);
        }

        if (str_starts_with($queryString, 'select * from cms_sources')) {
            return new InMemoryQueryStatement($this->tables['cms_sources']);
        }

        return false;
    }

    public function lastInsertId($name = null): string|false
    {
        return $this->lastInsertId !== null ? (string) $this->lastInsertId : false;
    }

    public function insertRow(string $table, array $row): int
    {
        if (!array_key_exists($table, $this->tables)) {
            throw new RuntimeException(sprintf('Table "%s" is not supported in in-memory stub.', $table));
        }

        $this->autoIncrement[$table]++;
        $row['id'] = $this->autoIncrement[$table];
        $this->tables[$table][] = $row;
        $this->lastInsertId = $row['id'];

        return $row['id'];
    }

    public function getRows(string $table): array
    {
        return $this->tables[$table] ?? [];
    }
}
