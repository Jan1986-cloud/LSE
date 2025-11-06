<?php
// Project Aurora migration runner for Railway

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    fwrite(STDERR, "ERROR: DATABASE_URL environment variable is not set." . PHP_EOL);
    exit(1);
}

$dsnComponents = parse_url($databaseUrl);
if ($dsnComponents === false) {
    fwrite(STDERR, "ERROR: Unable to parse DATABASE_URL." . PHP_EOL);
    exit(1);
}

$host = $dsnComponents['host'] ?? null;
$port = $dsnComponents['port'] ?? 5432;
$user = $dsnComponents['user'] ?? null;
$pass = $dsnComponents['pass'] ?? '';
$dbName = ltrim($dsnComponents['path'] ?? '', '/');

if (!$host || !$user || !$dbName) {
    fwrite(STDERR, "ERROR: DATABASE_URL missing required components." . PHP_EOL);
    exit(1);
}

$pdoDsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

try {
    $pdo = new PDO($pdoDsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'ERROR: Connection failed - ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$schemaPath = __DIR__ . '/../shared/contracts/database_schema.sql';
if (!is_readable($schemaPath)) {
    fwrite(STDERR, 'ERROR: Cannot read schema file at ' . $schemaPath . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($schemaPath);
if ($sql === false) {
    fwrite(STDERR, 'ERROR: Failed to load schema SQL.' . PHP_EOL);
    exit(1);
}

try {
    $pdo->exec($sql);
    echo 'SUCCESS: Database migration completed.' . PHP_EOL;
} catch (PDOException $e) {
    fwrite(STDERR, 'ERROR: Migration failed - ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
