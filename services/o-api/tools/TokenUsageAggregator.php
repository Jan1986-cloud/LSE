<?php

declare(strict_types=1);

use JsonException;
use PDO;
use PDOException;
use RuntimeException;

final class TokenUsageAggregator
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $this->createConnection();
    }

    public function logUsage(int $userId, int|string $workflowId, string $toolName, int $tokenCount, ?int $blueprintId = null, int $inputTokens = 0, int $outputTokens = 0, float $costAmount = 0.0, ?array $metadata = null): int
    {
        $metadataPayload = null;

        if ($metadata !== null) {
            try {
                $metadataPayload = json_encode($metadata, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Failed to encode metadata payload: ' . $exception->getMessage(), 0, $exception);
            }
        }

        $sql = <<<'SQL'
INSERT INTO cms_token_logs (
    user_id,
    blueprint_id,
    orchestration_id,
    service_tag,
    tokens_used,
    input_tokens,
    output_tokens,
    cost_amount,
    metadata
) VALUES (
    :user_id,
    :blueprint_id,
    :orchestration_id,
    :service_tag,
    :tokens_used,
    :input_tokens,
    :output_tokens,
    :cost_amount,
    :metadata
)
RETURNING id
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);

        if ($blueprintId !== null) {
            $statement->bindValue(':blueprint_id', $blueprintId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':blueprint_id', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':orchestration_id', (string) $workflowId, PDO::PARAM_STR);
        $statement->bindValue(':service_tag', $toolName, PDO::PARAM_STR);
        $statement->bindValue(':tokens_used', $tokenCount, PDO::PARAM_INT);
        $statement->bindValue(':input_tokens', $inputTokens, PDO::PARAM_INT);
        $statement->bindValue(':output_tokens', $outputTokens, PDO::PARAM_INT);
        $statement->bindValue(':cost_amount', (string) $costAmount, PDO::PARAM_STR);

        if ($metadataPayload !== null) {
            $statement->bindValue(':metadata', $metadataPayload, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':metadata', null, PDO::PARAM_NULL);
        }

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to log token usage: ' . $exception->getMessage(), 0, $exception);
        }

        $insertedId = $statement->fetchColumn();

        if ($insertedId === false) {
            throw new RuntimeException('Failed to retrieve token log identifier.');
        }

        return (int) $insertedId;
    }

    private function createConnection(): PDO
    {
        $databaseUrl = getenv('DATABASE_URL');

        if ($databaseUrl === false || trim($databaseUrl) === '') {
            throw new RuntimeException('DATABASE_URL environment variable is not set.');
        }

        $parsedUrl = parse_url($databaseUrl);

        if ($parsedUrl === false) {
            throw new RuntimeException('DATABASE_URL is malformed.');
        }

        $scheme = strtolower($parsedUrl['scheme'] ?? '');

        if (!in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
            throw new RuntimeException(sprintf('Unsupported database driver "%s".', $scheme));
        }

        $host = $parsedUrl['host'] ?? null;
        $dbName = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : null;

        if ($host === null || $dbName === null || $dbName === '') {
            throw new RuntimeException('DATABASE_URL must include host and database name.');
        }

        $port = $parsedUrl['port'] ?? 5432;
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);

            foreach ($queryParams as $key => $value) {
                $dsn .= sprintf(';%s=%s', $key, $value);
            }
        }

        $user = isset($parsedUrl['user']) ? rawurldecode($parsedUrl['user']) : null;
        $password = isset($parsedUrl['pass']) ? rawurldecode($parsedUrl['pass']) : null;

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to connect to database: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
