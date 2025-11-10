<?php

declare(strict_types=1);

namespace LSE\Services\CApi\Tests;

use LSE\Services\CApi\AnalyticsReporter;
use PHPUnit\Framework\TestCase;

final class AnalyticsReporterTest extends TestCase
{
    public function testQueuedPayloadsInvokeSenderOnFlush(): void
    {
        $captured = [];
        $sender = static function (string $endpoint, string $body, int $timeoutMs) use (&$captured): void {
            $captured[] = [$endpoint, $body, $timeoutMs];
        };

        $reporter = new AnalyticsReporter('http://example.com', 150, $sender);
        $reporter->queue(['hello' => 'world']);
        $reporter->queue(['foo' => 'bar']);
        $reporter->flush();

        self::assertCount(2, $captured);
        self::assertSame('http://example.com', $captured[0][0]);
        self::assertStringContainsString('"hello":"world"', $captured[0][1]);
        self::assertSame(150, $captured[0][2]);
    }
}
