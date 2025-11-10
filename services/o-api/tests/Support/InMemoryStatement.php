<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests\Support;

use DateTimeImmutable;
use PDOStatement;

final class InMemoryStatement extends PDOStatement
{
    private InMemoryPdo $pdo;
    private string $query;
    /** @var array<string,mixed> */
    private array $values = [];
    private ?int $resultId = null;

    public function __construct(InMemoryPdo $pdo, string $query)
    {
        $this->pdo = $pdo;
        $this->query = strtolower($query);
    }

    public function bindValue($param, $value, $type = null): bool
    {
        $this->values[(string) $param] = $value;
        return true;
    }

    public function execute($params = null): bool
    {
        if (str_starts_with($this->query, 'insert into cms_token_logs')) {
            $row = [
                'user_id' => (int) $this->values[':user_id'],
                'blueprint_id' => $this->values[':blueprint_id'] ?? null,
                'orchestration_id' => (string) $this->values[':orchestration_id'],
                'service_tag' => (string) $this->values[':service_tag'],
                'tokens_used' => (int) $this->values[':tokens_used'],
                'input_tokens' => (int) $this->values[':input_tokens'],
                'output_tokens' => (int) $this->values[':output_tokens'],
                'cost_amount' => (float) $this->values[':cost_amount'],
                'metadata' => $this->values[':metadata'] ?? null,
                'logged_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];

            $this->resultId = $this->pdo->insertRow('cms_token_logs', $row);

            return true;
        }

        if (str_starts_with($this->query, 'insert into cms_sources')) {
            $row = [
                'blueprint_id' => $this->values[':blueprint_id'] ?? null,
                'source_url' => (string) $this->values[':source_url'],
                'title' => $this->values[':title'] ?? null,
                'author' => $this->values[':author'] ?? null,
                'html_snapshot' => (string) $this->values[':html_snapshot'],
                'checksum' => (string) $this->values[':checksum'],
                'captured_at' => (string) $this->values[':captured_at'],
            ];

            $this->resultId = $this->pdo->insertRow('cms_sources', $row);

            return true;
        }

        return false;
    }

    public function fetchColumn($column = 0): mixed
    {
        return $this->resultId;
    }
}
