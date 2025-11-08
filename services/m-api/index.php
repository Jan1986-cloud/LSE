<?php
header('Content-Type: text/plain');
echo "=== M-API DIAGNOSTIC ===\n\n";
// Test 1: M-API Database Connection
try {
	$dbUrl = getenv('DATABASE_URL');
	if (empty($dbUrl)) throw new Exception('DATABASE_URL is not set.');
    
	$dbConfig = parse_url($dbUrl);
	$dsn = sprintf(
		"pgsql:host=%s;port=%s;dbname=%s",
		$dbConfig['host'],
		$dbConfig['port'] ?? 5432,
		ltrim($dbConfig['path'], '/')
	);
    
	$pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
	echo "[DB TEST]: PASSED. M-API connected to database.\n";
} catch (Exception $e) {
	echo "[DB TEST]: FAILED. M-API could not connect to database.\n";
	echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";
// Test 2: Internal Ping to O-API
// Railway's private networking routes http://[service-name]
$oApiPort = getenv('O_API_PORT');
if (empty($oApiPort)) {
	throw new Exception('O_API_PORT environment variable is not set.');
}
$oApiUrl = 'http://o-api.railway.internal:' . $oApiPort;
try {
	$curlHandle = curl_init($oApiUrl);
	if ($curlHandle === false) {
		throw new Exception('Failed to initialize cURL.');
	}

	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curlHandle, CURLOPT_TIMEOUT, 5);
	curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);

	$response = curl_exec($curlHandle);
	$curlError = curl_error($curlHandle);
	$httpStatus = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
	curl_close($curlHandle);

	if ($response === false) {
		throw new Exception($curlError !== '' ? $curlError : 'Unknown cURL error.');
	}

	if ($httpStatus >= 400 || $httpStatus === 0) {
		throw new Exception("Unexpected HTTP status code: $httpStatus");
	}

	echo "[PING TEST]: PASSED. M-API successfully pinged O-API.\n";
	echo "Response from O-API:\n---\n$response\n---\n";

} catch (Exception $e) {
	echo "[PING TEST]: FAILED. M-API could not ping O-API at $oApiUrl.\n";
	echo "Error: " . $e->getMessage() . "\n";
}
echo "\n=== DIAGNOSTIC COMPLETE ===\n";
?>
