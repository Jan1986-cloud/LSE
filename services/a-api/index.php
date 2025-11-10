<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    require_once __DIR__ . '/src/AnalyticsIngestService.php';
}

use InvalidArgumentException;
use LSE\Services\AApi\AnalyticsIngestService;
use PDO;
use RuntimeException;
use Throwable;

$requestStart = hrtime(true);

try {
    $pdo = createDatabaseConnection();
} catch (Throwable $exception) {
    respondJson(500, $requestStart, [
        'error' => 'database_unavailable',
        'details' => $exception->getMessage(),
    ]);
    exit;
}

$anonymizationSalt = getenv('ANONYMIZATION_SALT');
if ($anonymizationSalt === false || $anonymizationSalt === '') {
    $anonymizationSalt = 'aurora-default-salt';
}

$ingestService = new AnalyticsIngestService($pdo, $anonymizationSalt);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '/health') {
    respondHealth($pdo, $requestStart);
    return;
}

if ($path === '/live') {
    respondJson(200, $requestStart, ['status' => 'live']);
    return;
}

if ($path === '/analytics/ingest' && $method === 'POST') {
    handleIngestRequest($ingestService, $requestStart);
    return;
}

if ($path === '/analytics/ingest') {
    respondJson(405, $requestStart, ['error' => 'method_not_allowed']);
    return;
}

respondJson(404, $requestStart, ['error' => 'not_found']);

function handleIngestRequest(AnalyticsIngestService $service, int $requestStart): void
{
    try {
        $payload = readJsonBody();
        $service->ingest($payload);
    } catch (InvalidArgumentException $exception) {
        respondJson(422, $requestStart, ['error' => $exception->getMessage()]);
        return;
    } catch (RuntimeException $exception) {
        respondJson(500, $requestStart, ['error' => 'persistence_error', 'details' => $exception->getMessage()]);
        return;
    } catch (Throwable $exception) {
        respondJson(500, $requestStart, ['error' => 'unexpected_error', 'details' => $exception->getMessage()]);
        return;
    }

    respondJson(202, $requestStart, ['status' => 'accepted']);
}

function respondHealth(PDO $pdo, int $requestStart): void
{
    try {
        $pdo->query('SELECT 1');
        $status = 'ok';
    } catch (Throwable) {
        $status = 'degraded';
    }

    respondJson(200, $requestStart, [
        'status' => $status,
        'service' => 'a-api',
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
        echo json_encode(['error' => 'response_encoding_failed'], JSON_UNESCAPED_SLASHES);
        return;
    }

    echo $encoded;
}

function formatResponseTimeHeader(int $requestStart): string
{
    return sprintf('%.2fms', calculateResponseTimeMs($requestStart));
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

/**
 * @return array<string,mixed>
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        throw new InvalidArgumentException('Request body is required.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    }

    return $decoded;
}
