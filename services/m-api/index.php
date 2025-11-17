<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    foreach ([
        'src/UserAuthService.php',
        'src/BillingService.php',
        'src/BlueprintService.php',
        'src/AuthGuard.php',
        'src/Exceptions/UnauthorizedException.php',
        'src/Exceptions/ForbiddenException.php',
    ] as $dependency) {
        require_once __DIR__ . '/' . $dependency;
    }
}

use LSE\Services\MApi\AuthGuard;
use LSE\Services\MApi\BlueprintService;
use LSE\Services\MApi\BillingService;
use LSE\Services\MApi\Exceptions\ForbiddenException;
use LSE\Services\MApi\Exceptions\UnauthorizedException;
use LSE\Services\MApi\UserAuthService;

const O_API_HTTP_TIMEOUT_MS = 1000;

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
$blueprintService = new BlueprintService($pdo);
$authGuard = new AuthGuard($authService);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    if (preg_match('#^/auth/api-keys/(\d+)$#', $path, $matches) === 1) {
        $apiKeyId = (int) $matches[1];
        enforceAllowedMethod($method, ['DELETE']);
        $authContext = requireAuth($authGuard);
        $authService->revokeApiKey($authContext['user_id'], $apiKeyId);
        respondJson(204, []);
        return;
    }

    if (preg_match('#^/blueprints/(\d+)$#', $path, $matches) === 1) {
        $blueprintId = (int) $matches[1];
        enforceAllowedMethod($method, ['GET', 'PUT', 'PATCH', 'DELETE']);
        $authContext = requireAuth($authGuard);

        if ($method === 'GET') {
            $blueprint = $blueprintService->getBlueprint($authContext['user_id'], $blueprintId);
            if ($blueprint === null) {
                respondJson(404, ['error' => 'Blueprint not found.']);
                return;
            }

            respondJson(200, ['blueprint' => $blueprint]);
            return;
        }

        if ($method === 'DELETE') {
            $deleted = $blueprintService->deleteBlueprint($authContext['user_id'], $blueprintId);
            if (!$deleted) {
                respondJson(404, ['error' => 'Blueprint not found.']);
                return;
            }

            respondJson(204, []);
            return;
        }

        $payload = readJsonBody();
        $allowedKeys = ['name', 'description', 'category', 'status', 'workflowDefinition'];
        $changes = array_intersect_key($payload, array_flip($allowedKeys));

        $updated = $blueprintService->updateBlueprint($authContext['user_id'], $blueprintId, $changes);
        if ($updated === null) {
            respondJson(404, ['error' => 'Blueprint not found.']);
            return;
        }

        respondJson(200, ['blueprint' => $updated]);
        return;
    }

    switch ($path) {
        case '/auth/register':
            enforceAllowedMethod($method, ['POST']);
            $payload = readJsonBody();
            $email = (string) ($payload['email'] ?? '');
            $password = (string) ($payload['password'] ?? '');
            $displayName = isset($payload['displayName']) ? (string) $payload['displayName'] : null;
            $user = $authService->register($email, $password, $displayName);
            respondJson(201, [
                'message' => 'User registered successfully.',
                'user' => $user,
            ]);
            break;

        case '/auth/me':
            enforceAllowedMethod($method, ['GET']);
            $authContext = requireAuth($authGuard);
            $profile = $authService->getUserProfile($authContext['user_id']);
            respondJson(200, ['user' => $profile]);
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

        case '/users/me':
            enforceAllowedMethod($method, ['PATCH']);
            $authContext = requireAuth($authGuard);
            $payload = readJsonBody();
            $allowedKeys = ['displayName', 'password', 'currentPassword'];
            $changes = array_intersect_key($payload, array_flip($allowedKeys));
            $updated = $authService->updateUserProfile($authContext['user_id'], $changes);
            respondJson(200, ['user' => $updated]);
            break;

        case '/auth/api-keys':
            enforceAllowedMethod($method, ['GET', 'POST']);
            $authContext = requireAuth($authGuard);
            if ($method === 'GET') {
                $keys = $authService->listApiKeys($authContext['user_id']);
                respondJson(200, ['apiKeys' => $keys]);
                break;
            }

            $payload = readJsonBody();
            $name = isset($payload['name']) ? trim((string) $payload['name']) : null;
            $apiKey = $authService->createApiKey($authContext['user_id'], $name !== '' ? $name : null);
            respondJson(201, ['apiKey' => $apiKey]);
            break;

        case '/blueprints':
            enforceAllowedMethod($method, ['GET', 'POST']);
            $authContext = requireAuth($authGuard);
            if ($method === 'GET') {
                $blueprints = $blueprintService->listBlueprints($authContext['user_id']);
                respondJson(200, ['blueprints' => $blueprints]);
                break;
            }

            $payload = readJsonBody();
            $input = [
                'name' => $payload['name'] ?? null,
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? null,
                'status' => $payload['status'] ?? null,
                'workflowDefinition' => $payload['workflowDefinition'] ?? null,
            ];

            $blueprint = $blueprintService->createBlueprint($authContext['user_id'], $input);
            respondJson(201, ['blueprint' => $blueprint]);
            break;

        case '/billing/status':
            enforceAllowedMethod($method, ['GET']);
            $authContext = requireAuth($authGuard);
            $status = $billingService->getStatusForUser($authContext['user_id']);

            respondJson(200, [
                'user' => [
                    'id' => $authContext['user_id'],
                    'email' => $authContext['email'],
                ],
                'billing' => $status,
            ]);
            break;

        case '/strategy/suggestions':
            enforceAllowedMethod($method, ['POST']);
            $authContext = requireAuth($authGuard);
            $payload = readJsonBody();
            
            // Proxy to S-API via Railway internal network
            $response = proxyToInternalService('s-api', '/strategy/suggestions', 'POST', $payload);
            respondJson($response['status'], $response['data']);
            break;

        case '/analytics/agent-detections':
            enforceAllowedMethod($method, ['POST']);
            $authContext = requireAuth($authGuard);
            $payload = readJsonBody();
            
            // Proxy to S-API via Railway internal network
            $response = proxyToInternalService('s-api', '/strategy/agent-detections', 'POST', $payload);
            respondJson($response['status'], $response['data']);
            break;

        default:
            respondJson(404, ['error' => 'Route not found.']);
    }
} catch (UnauthorizedException $e) {
    respondJson(401, ['error' => $e->getMessage()]);
} catch (ForbiddenException $e) {
    respondJson(403, ['error' => $e->getMessage()]);
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
    
        if ($statusCode === 204) {
            return;
        }
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
    // Railway services use port 8080 by default for internal communication
    $url = 'http://o-api.railway.internal:8080/health';
    
    $handle = curl_init($url);
    if ($handle === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'attempted_url' => $url,
            'error_details' => 'curl_init_failed',
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
    $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($response === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'attempted_url' => $url,
            'error_details' => 'connection_failed',
        ];
    }

    if ($httpStatus >= 200 && $httpStatus < 300) {
        return [
            'ok' => true,
            'http_status' => $httpStatus,
            'attempted_url' => $url,
            'error_details' => null,
        ];
    }

    return [
        'ok' => false,
        'http_status' => $httpStatus,
        'attempted_url' => $url,
        'error_details' => 'http_error',
    ];
}

/**
 * @return array{user_id:int,email:string,api_key_id:int}
 */
function requireAuth(AuthGuard $authGuard): array
{
    return $authGuard->requireUser(getAuthorizationHeader());
}

/**
 * Proxy a request to an internal Railway service.
 * 
 * @param string $service Service name (e.g., 's-api', 'o-api', 'a-api')
 * @param string $path API path to call
 * @param string $method HTTP method
 * @param array<string,mixed>|null $payload Request body
 * @return array{status:int,data:array<string,mixed>}
 */
function proxyToInternalService(string $service, string $path, string $method = 'GET', ?array $payload = null): array
{
    $url = sprintf('http://%s.railway.internal:8080%s', $service, $path);
    
    $handle = curl_init($url);
    if ($handle === false) {
        return [
            'status' => 503,
            'data' => ['error' => 'Failed to initialize internal service connection.'],
        ];
    }

    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Content-Type: application/json'];
    
    if ($payload !== null) {
        $json = json_encode($payload);
        if ($json === false) {
            return [
                'status' => 500,
                'data' => ['error' => 'Failed to encode request payload.'],
            ];
        }
        curl_setopt($handle, CURLOPT_POSTFIELDS, $json);
    }
    
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($handle);
    $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    
    if ($response === false) {
        return [
            'status' => 503,
            'data' => ['error' => 'Internal service unavailable.', 'details' => $error],
        ];
    }
    
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'status' => 502,
            'data' => ['error' => 'Invalid response from internal service.'],
        ];
    }
    
    return [
        'status' => $httpStatus,
        'data' => $decoded,
    ];
}
