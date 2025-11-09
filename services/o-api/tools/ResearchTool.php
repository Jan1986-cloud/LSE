<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

use PDO;
use PDOException;
use RuntimeException;

final class ResearchTool
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $this->createConnection();
    }

    public function captureSource(string $url): int
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Invalid URL provided for captureSource.');
        }

        $htmlSnapshot = $this->fetchHtmlSnapshot($url);
        $title = $this->extractTitle($htmlSnapshot);
        $checksum = hash('sha256', $htmlSnapshot);

        $sql = <<<'SQL'
INSERT INTO cms_sources (
    blueprint_id,
    source_url,
    title,
    html_snapshot,
    checksum
) VALUES (
    :blueprint_id,
    :source_url,
    :title,
    :html_snapshot,
    :checksum
)
RETURNING id
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':blueprint_id', null, PDO::PARAM_NULL);
        $statement->bindValue(':source_url', $url, PDO::PARAM_STR);

        if ($title !== null) {
            $statement->bindValue(':title', $title, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':title', null, PDO::PARAM_NULL);
        }

        $statement->bindValue(':html_snapshot', $htmlSnapshot, PDO::PARAM_STR);
        $statement->bindValue(':checksum', $checksum, PDO::PARAM_STR);

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to insert source snapshot: ' . $exception->getMessage(), 0, $exception);
        }

        $insertedId = $statement->fetchColumn();

        if ($insertedId === false) {
            throw new RuntimeException('Failed to retrieve source identifier.');
        }

        return (int) $insertedId;
    }

    private function fetchHtmlSnapshot(string $url): string
    {
        $curlHandle = curl_init($url);

        if ($curlHandle === false) {
            throw new RuntimeException('Unable to initialize cURL for source capture.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'ProjectAurora-ResearchTool/1.0',
            CURLOPT_ACCEPT_ENCODING => '',
        ]);

        $response = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $httpStatus = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($response === false) {
            throw new RuntimeException(
                'cURL error while fetching source: ' . ($curlError !== '' ? $curlError : 'unknown error')
            );
        }

        if ($httpStatus >= 400 || $httpStatus === 0) {
            throw new RuntimeException('Unexpected HTTP status code while fetching source: ' . $httpStatus);
        }

        $trimmedResponse = trim($response);

        if ($trimmedResponse === '') {
            throw new RuntimeException('Source returned an empty response.');
        }

        return $response;
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
