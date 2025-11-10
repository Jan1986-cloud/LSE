<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    require_once __DIR__ . '/src/ContentRepository.php';
    require_once __DIR__ . '/src/AnalyticsReporter.php';
}

use LSE\Services\CApi\AnalyticsReporter;
use LSE\Services\CApi\ContentRepository;
use PDO;
use RuntimeException;
use Throwable;

const A_API_ANALYTICS_ENDPOINT = 'http://a-api.railway.internal:8080/analytics/ingest';

$requestStart = hrtime(true);

try {
    $pdo = createDatabaseConnection();
} catch (Throwable $exception) {
    respondJson(500, $requestStart, [
        'error' => 'Service unavailable.',
        'details' => $exception->getMessage(),
    ]);
    exit;
}

$repository = new ContentRepository($pdo);
$reporter = new AnalyticsReporter(A_API_ANALYTICS_ENDPOINT);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

if ($path === '/health') {
    respondHealth($pdo, $requestStart);
    return;
}

if ($path === '/live') {
    respondJson(200, $requestStart, ['status' => 'live']);
    return;
}

if (preg_match('#^/content/([\w\-]+)$#', $path, $matches) === 1) {
    handleContentRequest($repository, $reporter, $matches[1], $requestStart);
    return;
}

respondJson(404, $requestStart, ['error' => 'Route not found.']);

function handleContentRequest(ContentRepository $repository, AnalyticsReporter $reporter, string $identifier, int $requestStart): void
{
    $normalized = trim($identifier);
    if ($normalized === '') {
        respondJson(400, $requestStart, ['error' => 'Content identifier is required.']);
        return;
    }

    $content = null;
    if (ctype_digit($normalized)) {
        $content = $repository->fetchByNumericId((int) $normalized);
    }

    if ($content === null) {
        $content = $repository->fetchByReference($normalized);
    }

    if ($content === null) {
        respondJson(404, $requestStart, ['error' => 'Content not found.']);
        return;
    }

    $contentId = $content['external_reference'] ?? (string) $content['id'];
    $responseTime = calculateResponseTimeMs($requestStart);

    $responsePayload = [
        'contentId' => $contentId,
        'blueprintId' => $content['blueprint_id'],
        'siteContextId' => $content['site_context_id'],
        'createdAt' => $content['created_at'],
        'updatedAt' => $content['updated_at'],
        'payload' => $content['content_payload'],
    ];

    $reporter->queue([
        'contentId' => $contentId,
        'blueprintId' => $content['blueprint_id'],
        'siteContextId' => $content['site_context_id'],
        'deliveryChannel' => 'api',
        'events' => [[
            'eventType' => 'content_delivered',
            'occurredAt' => gmdate('c'),
            'userAgent' => getUserAgent(),
            'requestIp' => getClientIp(),
            'metadata' => [
                'latency_ms' => $responseTime,
            ],
        ]],
    ]);

    respondJson(200, $requestStart, ['content' => $responsePayload]);
}

function respondHealth(PDO $pdo, int $requestStart): void
{
    try {
        $pdo->query('SELECT 1');
        $databaseStatus = 'ok';
    } catch (Throwable) {
        $databaseStatus = 'error';
    }

    respondJson(200, $requestStart, [
        'status' => $databaseStatus === 'ok' ? 'ok' : 'degraded',
        'service' => 'c-api',
        'checks' => [
            'database' => $databaseStatus,
        ],
    ]);
}

function respondJson(int $statusCode, int $requestStart, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Response-Time: ' . formatResponseTimeHeader($requestStart));

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode response.'], JSON_UNESCAPED_SLASHES);
        return;
    }

    echo $encoded;
}

function formatResponseTimeHeader(int $requestStart): string
{
    $ms = calculateResponseTimeMs($requestStart);
    return sprintf('%.2fms', $ms);
}

function calculateResponseTimeMs(int $requestStart): float
{
    $elapsedNs = hrtime(true) - $requestStart;
    return $elapsedNs / 1_000_000;
}

function createDatabaseConnection(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl === false || trim($databaseUrl) === '') {
        throw new RuntimeException('DATABASE_URL is not configured.');
    }

    $config = parse_url($databaseUrl);
    if ($config === false || !isset($config['host'], $config['path'])) {
        throw new RuntimeException('DATABASE_URL is malformed.');
    }

    $dsnParts = [
        'host=' . $config['host'],
        'port=' . ($config['port'] ?? 5432),
        'dbname=' . ltrim($config['path'], '/'),
    ];

    if (!empty($config['query'])) {
        parse_str($config['query'], $queryOptions);
        foreach ($queryOptions as $key => $value) {
            $dsnParts[] = $key . '=' . $value;
        }
    }

    $dsn = 'pgsql:' . implode(';', $dsnParts);

    $username = $config['user'] ?? null;
    $password = $config['pass'] ?? null;

    return new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function getUserAgent(): ?string
{
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return null;
    }

    $userAgent = trim((string) $_SERVER['HTTP_USER_AGENT']);
    return $userAgent === '' ? null : $userAgent;
}

function getClientIp(): ?string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded[0]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return trim((string) $_SERVER['REMOTE_ADDR']);
    }

    return null;
}
