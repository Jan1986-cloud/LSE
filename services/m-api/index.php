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
            $connectivity = checkOApiConnectivity();
            if ($connectivity['ok']) {
                respondJson(200, [
                    'status' => 'success',
                    'message' => 'O-API connectivity test passed.',
                    'o_api_response' => [
                        'http_status' => $connectivity['http_status'],
                        'response_body' => $connectivity['response_body'],
                        'attempted_url' => $connectivity['attempted_url'],
                    ],
                ]);
            } else {
                respondJson(503, [
                    'status' => 'error',
                    'message' => 'O-API connection failed.',
                    'error_details' => $connectivity['error_details'],
                    'attempted_url' => $connectivity['attempted_url'],
                    'http_status' => $connectivity['http_status'],
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
    $databaseStatus = 'pass';
    $databaseError = null;

    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        $databaseStatus = 'fail';
        $databaseError = $e->getMessage();
    }

    $connectivity = checkOApiConnectivity();

    $payload = [
        'status' => ($databaseStatus === 'pass' && $connectivity['ok']) ? 'ok' : 'degraded',
        'checks' => [
            'database' => $databaseStatus,
            'o-api' => $connectivity['ok'] ? 'pass' : 'error',
        ],
        'o_api_details' => [
            'http_status' => $connectivity['http_status'],
            'attempted_url' => $connectivity['attempted_url'],
            'error_details' => $connectivity['error_details'],
        ],
    ];

    if ($databaseError !== null) {
        $payload['database_error'] = $databaseError;
    }

    respondJson(200, $payload);
}

function checkOApiConnectivity(): array
{
    $rawPort = getenv('O_API_INTERNAL_PORT');
    $attemptedUrl = 'http://' . O_API_INTERNAL_HOST . ':<port=undefined>/health';

    if ($rawPort === false || trim($rawPort) === '') {
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => 0,
            'response_body' => null,
            'error_details' => 'invalid_internal_port',
            'attempted_url' => $attemptedUrl,
        ];
    }

    $portString = trim($rawPort);
    if (!ctype_digit($portString)) {
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => 0,
            'response_body' => null,
            'error_details' => 'invalid_internal_port',
            'attempted_url' => 'http://' . O_API_INTERNAL_HOST . ':<port=' . $portString . '>/health',
        ];
    }

    $port = (int) $portString;
    if ($port <= 0 || $port > 65535) {
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => 0,
            'response_body' => null,
            'error_details' => 'invalid_internal_port',
            'attempted_url' => 'http://' . O_API_INTERNAL_HOST . ':<port=' . $portString . '>/health',
        ];
    }

    $url = 'http://' . O_API_INTERNAL_HOST . ':' . $port . '/health';

    $curlHandle = curl_init($url);
    if ($curlHandle === false) {
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => 0,
            'response_body' => null,
            'error_details' => 'Failed to initialize cURL.',
            'attempted_url' => $url,
        ];
    }

    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT, 3);
    curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 3);

    $response = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    curl_close($curlHandle);

    $httpStatus = $status !== false ? (int) $status : 0;

    if ($response === false) {
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => $httpStatus,
            'response_body' => null,
            'error_details' => $curlError !== '' ? $curlError : 'Unknown cURL error.',
            'attempted_url' => $url,
        ];
    }

    $trimmedBody = trim($response);
    $bodySnippet = $trimmedBody === '' ? '' : mb_substr($trimmedBody, 0, 120);

    if ($httpStatus < 200 || $httpStatus >= 300) {
        $snippet = $bodySnippet !== '' ? $bodySnippet : '(empty response)';
        return [
            'ok' => false,
            'status' => 'error',
            'http_status' => $httpStatus,
            'response_body' => $bodySnippet,
            'error_details' => 'HTTP ' . $httpStatus . ' response: ' . $snippet,
            'attempted_url' => $url,
        ];
    }

    return [
        'ok' => true,
        'status' => 'pass',
        'http_status' => $httpStatus,
        'response_body' => $bodySnippet,
        'error_details' => null,
        'attempted_url' => $url,
    ];
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
