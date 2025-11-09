<?php

declare(strict_types=1);

echo "Attempting database migration...\n";

$dbUrl = getenv('DATABASE_URL');
logDatabaseEnv($dbUrl !== false && $dbUrl !== '');
if ($dbUrl === false || $dbUrl === '') {
    echo "ERROR: DATABASE_URL environment variable not set.\n";
    exit(1);
}

$dbConfig = parse_url($dbUrl);
if ($dbConfig === false) {
    echo "ERROR: Failed to parse DATABASE_URL.\n";
    exit(1);
}

$host = $dbConfig['host'] ?? null;
$port = $dbConfig['port'] ?? 5432;
$dbname = isset($dbConfig['path']) ? ltrim((string) $dbConfig['path'], '/') : null;
$user = $dbConfig['user'] ?? null;
$pass = $dbConfig['pass'] ?? null;

if ($host === null || $dbname === null || $user === null) {
    echo "ERROR: DATABASE_URL missing required components.\n";
    exit(1);
}

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Database connection successful.\n";

    $sqlFile = __DIR__ . '/shared/contracts/database_schema.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        echo "ERROR: Could not read schema file at: $sqlFile\n";
        exit(1);
    }

    $schemaExists = schemaAlreadyApplied($pdo);

    $pdo->beginTransaction();

    if ($schemaExists) {
        echo "Schema already present — skipping base DDL.\n";
    } else {
        $pdo->exec($sql);
        echo "Base schema applied.\n";
    }

    verifyLargeHtmlSupport($pdo);

    $pdo->commit();
    echo "SUCCESS: Database migration completed.\n";
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: Database migration failed.\n";
    echo "Message: " . $e->getMessage() . "\n";
    exit(1);
}

function logDatabaseEnv(bool $detected): void
{
    error_log('[db-migrator] env:DATABASE_URL ' . ($detected ? 'detected' : 'absent'));
}

function schemaAlreadyApplied(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT EXISTS (SELECT 1 FROM information_schema.tables 
         WHERE table_schema = 'public' AND table_name = :table) AS present"
    );
    $stmt->execute(['table' => 'cms_users']);
    $result = $stmt->fetch();

    return (bool) ($result['present'] ?? false);
}

function verifyLargeHtmlSupport(PDO $pdo): void
{
    $sourceUrl = 'urn:migrator:html-payload-test';
    $capturedAt = '1970-01-01T00:00:00Z';
    $payload = str_repeat('<p>Phase 0 payload validation.</p>', 512);

    $stmt = $pdo->prepare(
        "INSERT INTO cms_sources (source_url, html_snapshot, captured_at) VALUES (:source_url, :payload, :captured_at)
         ON CONFLICT (source_url, captured_at) DO NOTHING"
    );
    $stmt->execute([
        'source_url' => $sourceUrl,
        'payload' => $payload,
        'captured_at' => $capturedAt,
    ]);

    if ($stmt->rowCount() === 0) {
        echo "Large HTML payload test already recorded.\n";
    } else {
        echo "Large HTML payload test inserted.\n";
    }
}
