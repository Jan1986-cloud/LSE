<?php

declare(strict_types=1);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$normalisedPath = rtrim($rawPath, '/');
if ($normalisedPath === '') {
    $normalisedPath = '/';
}

if ($method !== 'GET') {
    header('Allow: GET');
    respondJson(405, ['error' => 'method_not_allowed']);
    exit;
}

switch ($normalisedPath) {
    case '/health':
        respondJson(200, ['status' => 'ok', 'service' => 'o-api']);
        break;

    case '/internal/ping':
        respondJson(200, ['status' => 'ok', 'from' => 'o-api']);
        break;

    default:
        respondJson(404, ['error' => 'not_found']);
        break;
}

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        http_response_code(500);
        echo '{"error":"response_encoding_failed"}';
        return;
    }

    echo $encoded;
}
