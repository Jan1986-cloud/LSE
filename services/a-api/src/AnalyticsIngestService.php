<?php

declare(strict_types=1);

namespace LSE\Services\AApi;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AnalyticsIngestService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $anonymizationSalt
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function ingest(array $payload): void
    {
        $contentId = $this->requireString($payload, 'contentId');
        $deliveryChannel = $this->optionalString($payload, 'deliveryChannel');
        $blueprintId = $this->optionalInt($payload, 'blueprintId');
        $siteContextId = $this->optionalInt($payload, 'siteContextId');

        $events = $payload['events'] ?? null;
        if (!is_array($events) || $events === []) {
            throw new InvalidArgumentException('events array is required.');
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                throw new InvalidArgumentException('Each event must be an object.');
            }

            $eventType = $this->requireString($event, 'eventType');
            $occurredAt = $this->parseDateTime($event['occurredAt'] ?? null);
            $userAgent = $this->optionalString($event, 'userAgent');
            $requestIp = $this->optionalString($event, 'requestIp');
            $metadata = $event['metadata'] ?? [];
            if (!is_array($metadata)) {
                throw new InvalidArgumentException('metadata must be an object when provided.');
            }

            $anonymizedIp = $requestIp !== null ? $this->anonymizeIp($requestIp) : null;
            if ($anonymizedIp !== null) {
                $metadata = $metadata + ['anonymized_ip' => $anonymizedIp];
            }

            if ($deliveryChannel !== null) {
                $metadata['delivery_channel'] = $deliveryChannel;
            }

            $this->insertEvent(
                $contentId,
                $blueprintId,
                $siteContextId,
                $eventType,
                $occurredAt,
                $userAgent,
                $metadata
            );
        }
    }

    /**
     * @param array<string,mixed> $event
     */
    private function insertEvent(
        string $contentId,
        ?int $blueprintId,
        ?int $siteContextId,
        string $eventType,
        DateTimeImmutable $occurredAt,
        ?string $userAgent,
        array $metadata
    ): void {
        $sql = 'INSERT INTO cms_analytics_log (
            content_id,
            blueprint_id,
            user_id,
            event_type,
            user_agent,
            request_ip,
            metadata,
            occurred_at,
            site_context_id
        ) VALUES (
            :content_id,
            :blueprint_id,
            NULL,
            :event_type,
            :user_agent,
            NULL,
            :metadata,
            :occurred_at,
            :site_context_id
        )';

        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([
                'content_id' => $contentId,
                'blueprint_id' => $blueprintId,
                'event_type' => $eventType,
                'user_agent' => $userAgent,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'occurred_at' => $occurredAt->format('c'),
                'site_context_id' => $siteContextId,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to persist analytics event: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function anonymizeIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, $this->anonymizationSalt);
    }

    private function parseDateTime(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('occurredAt must be an ISO-8601 string.');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('occurredAt is not a valid date-time string.', 0, $exception);
        }
    }

    private function requireString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s is required.', $key));
        }

        return trim($value);
    }

    private function optionalString(array $source, string $key): ?string
    {
        if (!array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }

        $value = trim((string) $source[$key]);
        return $value === '' ? null : $value;
    }

    private function optionalInt(array $source, string $key): ?int
    {
        if (!array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }

        if (!is_numeric($source[$key])) {
            throw new InvalidArgumentException(sprintf('%s must be numeric.', $key));
        }

        return (int) $source[$key];
    }
}
