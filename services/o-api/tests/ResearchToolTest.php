<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests;

use DateTimeImmutable;
use LSE\Services\OApi\Tests\Support\InMemoryPdo;
use LSE\Services\OApi\Tools\HtmlFetcherInterface;
use LSE\Services\OApi\Tools\ResearchTool;
use PHPUnit\Framework\TestCase;

final class ResearchToolTest extends TestCase
{
    private InMemoryPdo $pdo;

    public function testCaptureSourcePersistsSnapshotWithTimestamp(): void
    {
        $this->pdo = new InMemoryPdo();
        $html = '<html><head><title>Example Title</title></head><body><p>Sample</p></body></html>';
        $fetcher = new class ($html) implements HtmlFetcherInterface {
            public function __construct(private string $payload)
            {
            }

            public function fetch(string $url): string
            {
                return $this->payload;
            }
        };

        $fixedTime = new DateTimeImmutable('2025-01-02T03:04:05+00:00');
        $clock = static fn (): DateTimeImmutable => $fixedTime;

        $tool = new ResearchTool($this->pdo, $fetcher, $clock);

        $result = $tool->captureSource('https://example.com/article', blueprintId: 11, author: 'Research Agent');

        self::assertGreaterThan(0, $result['id']);
        self::assertSame(hash('sha256', $html), $result['checksum']);
        self::assertInstanceOf(DateTimeImmutable::class, $result['captured_at']);
        self::assertSame($fixedTime->format(DATE_ATOM), $result['captured_at']->format(DATE_ATOM));
        self::assertSame('Example Title', $result['title']);

        $rows = $this->pdo->getRows('cms_sources');
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame('https://example.com/article', $row['source_url']);
        self::assertSame('Example Title', $row['title']);
        self::assertSame($html, $row['html_snapshot']);
        self::assertSame('Research Agent', $row['author']);
        self::assertSame($fixedTime->format(DATE_ATOM), $row['captured_at']);
    }
}
