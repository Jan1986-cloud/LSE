<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/tools/TokenUsageAggregator.php';
require_once __DIR__ . '/tools/ResearchTool.php';
require_once __DIR__ . '/tools/JsonValidatorTool.php';

try {
	$rawInput = file_get_contents('php://input') ?: '';
	$requestPayload = null;

	if ($rawInput !== '') {
		try {
			$requestPayload = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $jsonException) {
			throw new RuntimeException('Malformed JSON payload: ' . $jsonException->getMessage(), 0, $jsonException);
		}
	}

	$metadata = null;

	if (is_array($requestPayload) && $requestPayload !== []) {
		$metadata = ['request_payload' => $requestPayload];
	}

	$tokenUsageAggregator = new TokenUsageAggregator();
	$tokenLogId = $tokenUsageAggregator->logUsage(1, 1, 'ResearchTool', 100, null, 0, 0, 0.0, $metadata);

	$researchTool = new ResearchTool();
	$sourceId = $researchTool->captureSource('http://example.com');

	$response = [
		'status' => 'SUCCESS',
		'source_id' => $sourceId,
		'token_log_id' => $tokenLogId,
	];

	echo json_encode($response, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
	http_response_code(500);

	$errorResponse = [
		'status' => 'ERROR',
		'message' => $exception->getMessage(),
	];

	try {
		echo json_encode($errorResponse, JSON_THROW_ON_ERROR);
	} catch (JsonException) {
		echo '{"status":"ERROR","message":"Failed to encode error response."}';
	}
}
