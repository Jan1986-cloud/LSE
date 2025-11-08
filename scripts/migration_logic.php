<?php
echo "Attempting database migration...\n";
$dbUrl = getenv('DATABASE_URL');
if ($dbUrl === false) {
    echo "ERROR: DATABASE_URL environment variable not set.\n";
    exit(1);
}
// Parse the DATABASE_URL
$dbConfig = parse_url($dbUrl);
if ($dbConfig === false) {
    echo "ERROR: Failed to parse DATABASE_URL.\n";
    exit(1);
}
$host = $dbConfig['host'];
$port = $dbConfig['port'] ?? 5432;
$dbname = ltrim($dbConfig['path'], '/');
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
// DSN for PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
try {
    // Connect to the database
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful.\n";
    
    // Read the SQL schema. The script runs in /scripts,
    // so we go one level up to the root.
    $sqlFile = __DIR__ . '/shared/contracts/database_schema.sql';
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        echo "ERROR: Could not read schema file at: $sqlFile\n";
        exit(1);
    }
    
    // Execute the migration
    $pdo->exec($sql);
    
    echo "SUCCESS: Database migration completed.\n";
    
} catch (PDOException $e) {
    echo "ERROR: Database migration failed.\n";
    echo "Message: " . $e->getMessage() . "\n";
    exit(1);
}
?>
