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

        $errors = [json_last_error_msg()];

        $repairResult = $this->attemptRepair($jsonPayload);

        if ($repairResult !== null) {
            [$normalizedPayload, $repairedData] = $repairResult;

            return [
                'valid' => true,
                'repaired' => true,
                'data' => $repairedData,
                'errors' => [],
                'normalized_payload' => $normalizedPayload,
            ];
        }

        return [
            'valid' => false,
            'repaired' => false,
            'data' => null,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{0:string,1:array<mixed>}|null
     */
    private function attemptRepair(string $jsonPayload): ?array
    {
        $withoutTrailingCommas = $this->removeTrailingCommas($jsonPayload);

        if ($withoutTrailingCommas !== $jsonPayload) {
            $decoded = json_decode($withoutTrailingCommas, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [$withoutTrailingCommas, $decoded];
            }
        }

        return null;
    }

    private function removeTrailingCommas(string $jsonPayload): string
    {
        $result = preg_replace('/,(\s*[}\]])/m', '$1', $jsonPayload);

        return $result ?? $jsonPayload;
    }
}
