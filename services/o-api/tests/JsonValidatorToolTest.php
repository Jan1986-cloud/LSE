<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tests;

use LSE\Services\OApi\Tools\JsonValidatorTool;
use PHPUnit\Framework\TestCase;

final class JsonValidatorToolTest extends TestCase
{
    public function testRepairsPayloadWithTrailingCommas(): void
    {
        $payload = <<<'JSON'
        {
            "title": "Demo",
            "tags": [
                "a",
                "b",
            ]
        }
        JSON;

        $tool = new JsonValidatorTool();
        $result = $tool->validateAndRepair($payload);

        self::assertTrue($result['valid']);
        self::assertTrue($result['repaired']);
        self::assertSame(['title' => 'Demo', 'tags' => ['a', 'b']], $result['data']);
        self::assertArrayHasKey('normalized_payload', $result);
    }

    public function testReturnsErrorsWhenUnrepairable(): void
    {
        $tool = new JsonValidatorTool();
        $result = $tool->validateAndRepair('{invalid json');

        self::assertFalse($result['valid']);
        self::assertFalse($result['repaired']);
        self::assertNotEmpty($result['errors']);
    }
}
