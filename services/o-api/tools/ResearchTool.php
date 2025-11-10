<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class ResearchTool
{
    private PDO $pdo;
    private HtmlFetcherInterface $fetcher;

    /** @var callable():DateTimeImmutable */
    private $clock;

    /**
     * @param callable():DateTimeImmutable|null $clock
     */
    public function __construct(?PDO $pdo = null, ?HtmlFetcherInterface $fetcher = null, ?callable $clock = null)
    {
        $this->pdo = $pdo ?? $this->createConnection();
        $this->fetcher = $fetcher ?? new CurlHtmlFetcher();
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    /**
     * @return array{id:int, checksum:string, captured_at:DateTimeImmutable, title:?string}
     */
    public function captureSource(string $url, ?int $blueprintId = null, ?string $author = null): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Invalid URL provided for captureSource.');
        }

        $htmlSnapshot = $this->fetcher->fetch($url);
        $title = $this->extractTitle($htmlSnapshot);
        $checksum = hash('sha256', $htmlSnapshot);
        $capturedAt = ($this->clock)();

    $driver = strtolower((string) ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'pgsql'));
    $isPgsql = $driver === 'pgsql' || $driver === 'postgresql';

        $sql = <<<'SQL'
INSERT INTO cms_sources (
    blueprint_id,
    source_url,
    title,
    html_snapshot,
    checksum,
    author,
    captured_at
) VALUES (
    :blueprint_id,
    :source_url,
    :title,
    :html_snapshot,
    :checksum,
    :author,
    :captured_at
)
SQL;

        if ($isPgsql) {
            $sql .= '\nRETURNING id';
        }

        $statement = $this->pdo->prepare($sql);
        if ($blueprintId !== null) {
            $statement->bindValue(':blueprint_id', $blueprintId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':blueprint_id', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':source_url', $url, PDO::PARAM_STR);

        if ($title !== null) {
            $statement->bindValue(':title', $title, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':title', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':html_snapshot', $htmlSnapshot, PDO::PARAM_STR);
        $statement->bindValue(':checksum', $checksum, PDO::PARAM_STR);
        if ($author !== null) {
            $statement->bindValue(':author', $author, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':author', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':captured_at', $capturedAt->format('c'), PDO::PARAM_STR);

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to insert source snapshot: ' . $exception->getMessage(), 0, $exception);
        }

        if ($isPgsql) {
            $insertedId = $statement->fetchColumn();
            if ($insertedId === false) {
                throw new RuntimeException('Failed to retrieve source identifier.');
            }

            return [
                'id' => (int) $insertedId,
                'checksum' => $checksum,
                'captured_at' => $capturedAt,
                'title' => $title,
            ];
        }

        $lastInsertId = $this->pdo->lastInsertId();

        if ($lastInsertId === false) {
            throw new RuntimeException('Failed to retrieve source identifier.');
        }

        return [
            'id' => (int) $lastInsertId,
            'checksum' => $checksum,
            'captured_at' => $capturedAt,
            'title' => $title,
        ];
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $cleanedTitle = trim(strip_tags($matches[1]));

        if ($cleanedTitle === '') {
            return null;
        }

        return strlen($cleanedTitle) > 255 ? substr($cleanedTitle, 0, 255) : $cleanedTitle;
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
