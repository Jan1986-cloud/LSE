<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

final class JsonValidatorTool
{
    public function validateAndRepair(string $jsonPayload): array
    {
        $decoded = json_decode($jsonPayload, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                'valid' => true,
                'repaired' => false,
                'data' => $decoded,
                'errors' => [],
            ];
        }

        return [
            'valid' => false,
            'repaired' => false,
            'data' => null,
            'errors' => [json_last_error_msg()],
        ];
    }
}
