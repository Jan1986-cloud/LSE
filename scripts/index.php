<?php
// Simple router
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
if ($requestUri === '/migrate') {
    // Run the migration logic
    echo "<pre>"; // For readable browser output
    require __DIR__ . '/migration_logic.php';
    echo "</pre>";
} else {
    // Default response
    header('Content-Type: text/plain');
    echo "db-migrator service is active. Visit /migrate to run migration.";
}
?>
