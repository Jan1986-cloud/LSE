<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests;

use LSE\Services\OApi\Tests\Support\InMemoryPdo;
use LSE\Services\OApi\Tools\LlmClientInterface;
use LSE\Services\OApi\Tools\TokenUsageAggregator;
use LSE\Services\OApi\Tools\WritingTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WritingToolTest extends TestCase
{
    private InMemoryPdo $pdo;

    public function testGenerateContentLogsTokenUsage(): void
    {
        $this->pdo = new InMemoryPdo();

        /** @var LlmClientInterface&MockObject $client */
        $client = $this->createMock(LlmClientInterface::class);
        $client
            ->expects(self::once())
            ->method('complete')
            ->with(
                'gpt-4o-mini',
                self::callback(static function (array $messages): bool {
                    return $messages !== [] && $messages[0]['role'] === 'system';
                }),
                self::callback(static fn (array $options): bool => isset($options['temperature']))
            )
            ->willReturn([
                'content' => 'Rendered body',
                'prompt_tokens' => 50,
                'completion_tokens' => 200,
                'model' => 'gpt-4o-mini',
                'metadata' => ['request_id' => 'req-123'],
            ]);

        $aggregator = new TokenUsageAggregator($this->pdo);
        $tool = new WritingTool($client, $aggregator);

        $result = $tool->generateContent(
            userId: 7,
            workflowId: 'wf-42',
            messages: [
                ['role' => 'system', 'content' => 'You are helpful.'],
                ['role' => 'user', 'content' => 'Write a tag line.'],
            ],
            model: 'gpt-4o-mini',
            blueprintId: 22,
            options: [
                'temperature' => 0.5,
                'metadata' => ['purpose' => 'token-test'],
                'cost_amount' => 1.23,
            ]
        );

        self::assertSame('Rendered body', $result['content']);
        self::assertSame(250, $result['tokens']['total']);
        self::assertSame(200, $result['tokens']['completion']);
        self::assertGreaterThan(0, $result['token_log_id']);

        $rows = $this->pdo->getRows('cms_token_logs');
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame(7, $row['user_id']);
        self::assertSame(22, $row['blueprint_id']);
        self::assertSame('wf-42', $row['orchestration_id']);
        self::assertSame('o-api:writing-tool', $row['service_tag']);
        self::assertSame(250, $row['tokens_used']);
        self::assertSame(50, $row['input_tokens']);
        self::assertSame(200, $row['output_tokens']);
        self::assertSame(1.23, $row['cost_amount']);

        $metadata = json_decode((string) $row['metadata'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('gpt-4o-mini', $metadata['model']);
        self::assertSame(2, $metadata['messages_count']);
        self::assertSame('token-test', $metadata['purpose']);
        self::assertSame(['temperature' => 0.5], $metadata['generation_options']);
        self::assertSame('req-123', $metadata['request_id']);
    }
}
