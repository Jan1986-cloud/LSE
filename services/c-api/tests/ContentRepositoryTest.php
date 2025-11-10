<?php

declare(strict_types=1);

namespace LSE\Services\CApi\Tests;

use LSE\Services\CApi\ContentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ContentRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE cms_content_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                blueprint_id INTEGER,
                site_context_id INTEGER,
                external_reference TEXT,
                content_payload TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->repository = new ContentRepository($this->pdo);
    }

    public function testFetchByReferenceReturnsStructuredPayload(): void
    {
        $payload = ['title' => 'Hello', 'body' => 'World'];
        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_content_items (
                blueprint_id, site_context_id, external_reference, content_payload, created_at, updated_at
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([1, 2, 'newsletter-001', json_encode($payload, JSON_THROW_ON_ERROR)]);

        $record = $this->repository->fetchByReference('newsletter-001');
        self::assertNotNull($record);
        self::assertSame($payload, $record['content_payload']);
        self::assertSame('newsletter-001', $record['external_reference']);
        self::assertSame(1, $record['blueprint_id']);
        self::assertSame(2, $record['site_context_id']);
    }

    public function testFetchByNumericIdFallsBackToJsonDecode(): void
    {
        $this->pdo->exec(<<<SQL
            INSERT INTO cms_content_items (
                blueprint_id,
                site_context_id,
                external_reference,
                content_payload,
                created_at,
                updated_at
            ) VALUES (NULL, NULL, NULL, '{"message":"ok"}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        SQL);

        $record = $this->repository->fetchByNumericId(1);
        self::assertNotNull($record);
        self::assertSame(['message' => 'ok'], $record['content_payload']);
        self::assertNull($record['external_reference']);
    }

    public function testMissingContentReturnsNull(): void
    {
        self::assertNull($this->repository->fetchByReference('missing'));
        self::assertNull($this->repository->fetchByNumericId(999));
    }
}
