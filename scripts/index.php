<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

switch ($path) {
    case '/health':
        respondHealth();
        break;

    case '/migrate':
        handleMigrationRequest($method);
        break;

    default:
        respondNotFound();
        break;
}

function handleMigrationRequest(string $method): void
{
    if ($method !== 'POST') {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found.'], JSON_UNESCAPED_SLASHES);
        return;
    }

    $configuredToken = getenv('MIGRATION_TOKEN');
    if ($configuredToken === false || $configuredToken === '') {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Migration guard not configured.',
            'details' => 'Set MIGRATION_TOKEN to enable /migrate.',
        ], JSON_UNESCAPED_SLASHES);
        return;
    }

    $providedToken = extractBearerToken();
    if ($providedToken === null || !hash_equals($configuredToken, $providedToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden.'], JSON_UNESCAPED_SLASHES);
        return;
    }

    header('Content-Type: text/plain');
    require __DIR__ . '/migration_logic.php';
}

function respondHealth(): void
{
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'db-migrator'], JSON_UNESCAPED_SLASHES);
}

function respondNotFound(): void
{
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Route not found.',
    ], JSON_UNESCAPED_SLASHES);
}

function extractBearerToken(): ?string
{
    $header = getAuthorizationHeader();
    if ($header === null) {
        return null;
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

function getAuthorizationHeader(): ?string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
    }

    if (isset($_SERVER['Authorization'])) {
        return trim((string) $_SERVER['Authorization']);
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return trim((string) $value);
            }
        }
    }

    return null;
}
?>
