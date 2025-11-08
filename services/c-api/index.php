<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

switch ($path) {
    case '/health':
        respondHealth();
        break;

    case '/live':
        respondLive();
        break;

    default:
        respondJson(404, ['error' => 'Route not found.']);
}

function respondHealth(): void
{
    respondJson(200, ['status' => 'ok', 'service' => 'c-api']);
}

function respondLive(): void
{
    respondJson(200, ['status' => 'live']);
}

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}
