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
const O_API_HTTP_TIMEOUT_MS = 1000;
const O_API_PROBE_PORTS = [8080, 3000, 8000, 5000];

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
                    'attempted_url' => $connectivity['attempted_url'],
                    'http_status' => $connectivity['http_status'],
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
    header('Content-Type: application/json; charset=UTF-8');
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

    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        $databaseStatus = 'fail';
    }

    $connectivity = checkOApiConnectivity();

    respondJson(200, [
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
    ]);
}

/**
 * @return array{ok:bool,http_status:int,attempted_url:string,error_details:?string}
 */
function checkOApiConnectivity(): array
{
    $plan = buildOApiPortCandidates();
    $lastStatus = 0;
    $lastError = $plan['default_error'];
    $lastUrl = buildOApiUrl($plan['default_port_hint'] ?? 0);

    foreach ($plan['candidates'] as $port) {
        $url = buildOApiUrl($port);
        $result = attemptOApiHealth($url);
        if ($result['ok']) {
            return [
                'ok' => true,
                'http_status' => $result['http_status'],
                'attempted_url' => $url,
                'error_details' => null,
            ];
        }

        $lastStatus = $result['http_status'];
        $lastError = $result['error'];
        $lastUrl = $url;
    }

    if ($plan['default_error'] !== null && ($lastError === null || $lastError === 'timeout')) {
        $lastError = $plan['default_error'];
        $lastStatus = 0;
        $lastUrl = buildOApiUrl($plan['default_port_hint'] ?? 0);
    }

    return [
        'ok' => false,
        'http_status' => $lastStatus,
        'attempted_url' => $lastUrl,
        'error_details' => $lastError ?? 'timeout',
    ];
}

/**
 * @return array{candidates:int[],default_error:?string,default_port_hint:int}
 */
function buildOApiPortCandidates(): array
{
    $candidates = [];
    $defaultError = null;
    $defaultPortHint = 0;

    $raw = readEnvValue('O_API_INTERNAL_PORT');
    if ($raw !== null) {
        $trimmed = trim($raw);
        if ($trimmed !== '') {
            $aliasPort = resolvePortAlias($trimmed);
            if ($aliasPort !== null) {
                $candidates[] = $aliasPort;
                $defaultPortHint = $aliasPort;
            } elseif (ctype_digit($trimmed)) {
                $value = (int) $trimmed;
                if ($value > 0 && $value <= 65535) {
                    $candidates[] = $value;
                    $defaultPortHint = $value;
                } else {
                    $defaultError = 'invalid_internal_port';
                    $defaultPortHint = $value;
                }
            } else {
                $defaultError = 'invalid_internal_port';
                $digits = preg_replace('/\D+/', '', $trimmed);
                if (is_string($digits) && $digits !== '') {
                    $defaultPortHint = (int) $digits;
                }
            }
        } else {
            $defaultError = 'invalid_internal_port';
        }
    } else {
        $defaultError = 'invalid_internal_port';
    }

    foreach (O_API_PROBE_PORTS as $fallbackPort) {
        if ($fallbackPort > 0 && $fallbackPort <= 65535 && !in_array($fallbackPort, $candidates, true)) {
            $candidates[] = $fallbackPort;
        }
    }

    if (!isset($candidates[0])) {
        $candidates[] = $defaultPortHint > 0 && $defaultPortHint <= 65535 ? $defaultPortHint : 8080;
    }

    if ($defaultPortHint <= 0 || $defaultPortHint > 65535) {
        $defaultPortHint = $candidates[0] ?? 0;
    }

    return [
        'candidates' => $candidates,
        'default_error' => $defaultError,
        'default_port_hint' => $defaultPortHint,
    ];
}

function readEnvValue(string $key): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    return null;
}

function resolvePortAlias(string $value): ?int
{
    if (preg_match('/^\$?\{?PORT\}?$/i', $value) !== 1) {
        return null;
    }

    $portEnv = readEnvValue('PORT');
    if ($portEnv === null) {
        return null;
    }

    $trimmed = trim($portEnv);
    if ($trimmed === '' || !ctype_digit($trimmed)) {
        return null;
    }

    $port = (int) $trimmed;
    if ($port < 1 || $port > 65535) {
        return null;
    }

    return $port;
}

/**
 * @return array{ok:bool,http_status:int,error:?string}
 */
function attemptOApiHealth(string $url): array
{
    $handle = curl_init($url);

    if ($handle === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'error' => 'timeout',
        ];
    }

    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

    if (defined('CURLOPT_TIMEOUT_MS')) {
        curl_setopt($handle, CURLOPT_TIMEOUT_MS, O_API_HTTP_TIMEOUT_MS);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, O_API_HTTP_TIMEOUT_MS);
    } else {
        $timeoutSeconds = (int) max(1, ceil(O_API_HTTP_TIMEOUT_MS / 1000));
        curl_setopt($handle, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
    }

    $response = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($response === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'error' => 'timeout',
        ];
    }

    $httpStatus = $status !== false ? (int) $status : 0;
    if ($httpStatus >= 200 && $httpStatus < 300) {
        return [
            'ok' => true,
            'http_status' => $httpStatus,
            'error' => null,
        ];
    }

    if ($httpStatus > 0) {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'error' => 'http_error',
        ];
    }

    return [
        'ok' => false,
        'http_status' => 0,
        'error' => 'timeout',
    ];
}

function buildOApiUrl(int $port): string
{
    if ($port < 0 || $port > 65535) {
        $port = 0;
    }

    return sprintf('http://%s:%d/health', O_API_INTERNAL_HOST, $port);
}
