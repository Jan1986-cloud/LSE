<?php

declare(strict_types=1);

namespace LSE\Services\AApi\Tests;

use LSE\Services\AApi\AnalyticsIngestService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class AnalyticsIngestServiceTest extends TestCase
{
    private PDO $pdo;
    private AnalyticsIngestService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE cms_analytics_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id TEXT NOT NULL,
                blueprint_id INTEGER,
                user_id INTEGER,
                event_type TEXT NOT NULL,
                user_agent TEXT,
                request_ip TEXT,
                metadata TEXT,
                occurred_at TEXT NOT NULL,
                site_context_id INTEGER
            )'
        );

        $this->service = new AnalyticsIngestService($this->pdo, 'test-salt');
    }

    public function testIngestPersistsEventsWithAnonymizedIp(): void
    {
        $payload = [
            'contentId' => 'newsletter-123',
            'deliveryChannel' => 'api',
            'blueprintId' => 42,
            'siteContextId' => 7,
            'events' => [[
                'eventType' => 'content_delivered',
                'occurredAt' => '2025-11-10T10:00:00Z',
                'userAgent' => 'Mozilla/5.0',
                'requestIp' => '203.0.113.5',
                'metadata' => ['latency_ms' => 85.3],
            ]],
        ];

        $this->service->ingest($payload);

        $stmt = $this->pdo->query('SELECT content_id, blueprint_id, user_agent, metadata, site_context_id FROM cms_analytics_log');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertSame('newsletter-123', $row['content_id']);
        self::assertSame(42, (int) $row['blueprint_id']);
        self::assertSame('Mozilla/5.0', $row['user_agent']);
        self::assertSame(7, (int) $row['site_context_id']);

        $metadata = json_decode((string) $row['metadata'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(85.3, $metadata['latency_ms']);
        self::assertSame('api', $metadata['delivery_channel']);
        self::assertArrayHasKey('anonymized_ip', $metadata);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $metadata['anonymized_ip']);
    }

    public function testMissingEventsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->ingest([
            'contentId' => 'abc',
            'events' => [],
        ]);
    }
}
