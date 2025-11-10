<?php

declare(strict_types=1);

namespace LSE\Services\CApi;

use PDO;
use PDOException;
use RuntimeException;

final class ContentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *     id:int,
     *     blueprint_id:?int,
     *     site_context_id:?int,
     *     external_reference:?string,
     *     content_payload:array<string|int,mixed>,
     *     created_at:string,
     *     updated_at:string
     * }|null
     */
    public function fetchByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, blueprint_id, site_context_id, external_reference, content_payload, created_at, updated_at
             FROM cms_content_items
             WHERE external_reference = :reference'
        );
        $stmt->execute(['reference' => $reference]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @return array{
     *     id:int,
     *     blueprint_id:?int,
     *     site_context_id:?int,
     *     external_reference:?string,
     *     content_payload:array<string|int,mixed>,
     *     created_at:string,
     *     updated_at:string
     * }|null
     */
    public function fetchByNumericId(int $contentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, blueprint_id, site_context_id, external_reference, content_payload, created_at, updated_at
             FROM cms_content_items
             WHERE id = :id'
        );
        $stmt->execute(['id' => $contentId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{
     *     id:int,
     *     blueprint_id:?int,
     *     site_context_id:?int,
     *     external_reference:?string,
     *     content_payload:array<string|int,mixed>,
     *     created_at:string,
     *     updated_at:string
     * }
     */
    private function mapRow(array $row): array
    {
        $payload = [];
        $rawPayload = $row['content_payload'] ?? [];

        if (is_array($rawPayload)) {
            $payload = $rawPayload;
        } elseif (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'id' => (int) $row['id'],
            'blueprint_id' => $row['blueprint_id'] !== null ? (int) $row['blueprint_id'] : null,
            'site_context_id' => $row['site_context_id'] !== null ? (int) $row['site_context_id'] : null,
            'external_reference' => $row['external_reference'] !== null ? (string) $row['external_reference'] : null,
            'content_payload' => $payload,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
