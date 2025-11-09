<?php

declare(strict_types=1);
// Explicitly load local service classes for deployments without Composer autoloading.
foreach ([
    'UserAuthService.php',
    'BillingService.php',
] as $dependency) {
    require_once __DIR__ . '/' . $dependency;
}

use LSE\Services\MApi\UserAuthService;
use LSE\Services\MApi\BillingService;

const O_API_INTERNAL_HOST = 'o-api.railway.internal';
const O_API_DEFAULT_PORT = 8080; // See TECHNICAL_DEBT.md entry P0-001 for context.

header('Content-Type: application/json');

logDatabaseEnv();

try {
    $pdo = createDatabaseConnection();
} catch (Throwable $e) {
    respondJson(500, [
        'error' => 'Database connection failed.',
        'details' => $e->getMessage(),
    ]);
    exit;
}

$authService = new UserAuthService($pdo);
$billingService = new BillingService($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    switch ($path) {
        case '/auth/register':
            enforceAllowedMethod($method, ['POST']);
            $payload = readJsonBody();
            $email = (string) ($payload['email'] ?? '');
            $password = (string) ($payload['password'] ?? '');
            $user = $authService->register($email, $password);
            respondJson(201, [
                'message' => 'User registered successfully.',
                'user' => $user,
            ]);
            break;

        case '/health':
            enforceAllowedMethod($method, ['GET']);
            respondHealth($pdo);
            break;

        case '/live':
            enforceAllowedMethod($method, ['GET']);
            respondJson(200, ['status' => 'ok']);
            break;

        case '/ping-o-api':
            enforceAllowedMethod($method, ['GET']);
            $oApiResponse = checkOApiConnectivity();
            if ($oApiResponse['status'] === 'pass') {
                respondJson(200, [
                    'status' => 'success',
                    'message' => 'O-API connectivity test passed',
                    'o_api_response' => $oApiResponse['details']
                ]);
            } else {
                respondJson(503, [
                    'status' => 'failed',
                    'message' => 'O-API connectivity test failed',
                    'error' => $oApiResponse['details']
                ]);
            }
            break;

        case '/auth/login':
            enforceAllowedMethod($method, ['POST']);
            $payload = readJsonBody();
            $email = (string) ($payload['email'] ?? '');
            $password = (string) ($payload['password'] ?? '');
            $result = $authService->login($email, $password);
            respondJson(200, [
                'message' => 'Login successful.',
                'apiKey' => $result['apiKey'],
                'user' => $result['user'],
            ]);
            break;

        case '/billing/status':
            enforceAllowedMethod($method, ['GET']);
            $bearerToken = extractBearerToken();
            if ($bearerToken === null) {
                respondJson(401, ['error' => 'Authorization header with Bearer token is required.']);
                break;
            }

            $authContext = $authService->authenticateApiKey($bearerToken);
            $status = $billingService->getStatusForUser($authContext['user_id']);

            respondJson(200, [
                'user' => [
                    'id' => $authContext['user_id'],
                    'email' => $authContext['email'],
                ],
                'billing' => $status,
            ]);
            break;

        default:
            respondJson(404, ['error' => 'Route not found.']);
    }
} catch (InvalidArgumentException $e) {
    respondJson(422, ['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    respondJson(400, ['error' => $e->getMessage()]);
} catch (JsonException $e) {
    respondJson(400, ['error' => 'Invalid JSON payload.', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
    respondJson(500, ['error' => 'Unexpected server error.', 'details' => $e->getMessage()]);
}

function enforceAllowedMethod(string $method, array $allowed): void
{
    if (in_array($method, $allowed, true)) {
        return;
    }

    header('Allow: ' . implode(', ', $allowed));
    respondJson(405, ['error' => 'Method not allowed.']);
    exit;
}

/**
 * @return array<string, mixed>
 * @throws JsonException
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        throw new InvalidArgumentException('Request body is required.');
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('JSON payload must decode to an object.');
    }

    return $decoded;
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

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode JSON response.'], JSON_UNESCAPED_SLASHES);
        return;
    }

    echo $encoded;
}

function createDatabaseConnection(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
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

function respondHealth(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        respondJson(500, [
            'error' => 'Database ping failed.',
            'details' => $e->getMessage(),
        ]);
        return;
    }

    [$oApiStatus, $oApiDetails] = checkOApiConnectivity();

    $statusCode = $oApiStatus ? 200 : 502;
    respondJson($statusCode, [
        'status' => $oApiStatus ? 'ok' : 'degraded',
        'checks' => [
            'database' => 'pass',
            'o-api' => $oApiStatus ? 'pass' : $oApiDetails,
        ],
    ]);
}

function resolveOApiPort(): int
{
    $provided = getenv('O_API_INTERNAL_PORT');
    if ($provided === false || $provided === '') {
        return O_API_DEFAULT_PORT;
    }

    if (!ctype_digit($provided)) {
        error_log('[m-api] Warning: O_API_INTERNAL_PORT is non-numeric; falling back to ' . O_API_DEFAULT_PORT);
        return O_API_DEFAULT_PORT;
    }

    $port = (int) $provided;
    if ($port !== O_API_DEFAULT_PORT) {
        error_log(
            '[m-api] Warning: O_API_INTERNAL_PORT differs from documented fallback ('
            . O_API_DEFAULT_PORT . ').'
        );
    }

    return $port;
}

function checkOApiConnectivity(): array
{
    $port = resolveOApiPort();
    $url = 'http://' . O_API_INTERNAL_HOST . ':' . $port . '/health';
    $curlHandle = curl_init($url);
    if ($curlHandle === false) {
        return [false, 'Failed to initialize cURL.'];
    }

    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
    curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($curlHandle);
    $error = curl_error($curlHandle);
    $status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    curl_close($curlHandle);

    if ($response === false) {
        return [false, $error !== '' ? $error : 'Unknown cURL error'];
    }

    if ($status >= 400 || $status === 0) {
        return [false, 'Unexpected HTTP status ' . $status];
    }

    return [true, 'pass'];
}

function logDatabaseEnv(): void
{
    static $logged = false;
    if ($logged) {
        return;
    }

    $logged = true;
    $databaseUrl = getenv('DATABASE_URL');
    $status = ($databaseUrl !== false && $databaseUrl !== '') ? 'detected' : 'absent';
    error_log('[m-api] env:DATABASE_URL ' . $status);
}
