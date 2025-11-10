<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests\Support;

use PDO;
use PDOStatement;

final class InMemoryQueryStatement extends PDOStatement
{
    /** @var array<int,array<string,mixed>> */
    private array $rows;
    private int $position = 0;

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (!isset($this->rows[$this->position])) {
            return false;
        }

        $row = $this->rows[$this->position];
        $this->position++;

        return $row;
    }
}
