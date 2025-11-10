<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

interface TokenUsageLoggerInterface
{
    public function logUsage(
        int $userId,
        int|string $workflowId,
        string $toolName,
        int $tokenCount,
        ?int $blueprintId = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $costAmount = 0.0,
        ?array $metadata = null
    ): int;
}
