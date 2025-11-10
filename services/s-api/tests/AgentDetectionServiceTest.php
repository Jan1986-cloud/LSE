<?php

declare(strict_types=1);

namespace LSE\Services\SApi\Tests;

use LSE\Services\SApi\AgentDetectionService;
use LSE\Services\SApi\AiAgentDetector;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class AgentDetectionServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->bootSchema();
    }

    public function testDetectPersistsMatchedAgents(): void
    {
        $service = new AgentDetectionService($this->pdo, new AiAgentDetector());

        $events = [
            [
                'analyticsLogId' => 77,
                'userAgent' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
            ],
        ];

        $detections = $service->detect($events);

        self::assertCount(1, $detections);
        $detection = $detections[0];

        self::assertSame(77, $detection['analyticsLogId']);
        self::assertSame('ChatGPT-User', $detection['agentName']);
        self::assertSame('openai', $detection['agentFamily']);
        self::assertGreaterThan(0.8, $detection['confidence']);
        self::assertStringContainsString('OpenAI agent', $detection['guidance']);

        $row = $this->pdo->query('SELECT agent_name, agent_family, detection_reason FROM cms_agent_detections')->fetch();
        self::assertSame('ChatGPT-User', $row['agent_name']);
        self::assertSame('openai', $row['agent_family']);
        self::assertStringContainsString('OpenAI agent', $row['detection_reason']);
    }

    public function testDetectSkipsUnknownAgents(): void
    {
        $service = new AgentDetectionService($this->pdo, new AiAgentDetector());

        $events = [
            ['analyticsLogId' => 88, 'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Safari/537.36'],
        ];

        $detections = $service->detect($events);

        self::assertSame([], $detections);

        $rowCount = (int) $this->pdo->query('SELECT COUNT(*) FROM cms_agent_detections')->fetchColumn();
        self::assertSame(0, $rowCount);
    }

    private function bootSchema(): void
    {
        $schemaStatements = [
            'CREATE TABLE cms_agent_detections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                analytics_log_id INTEGER NOT NULL,
                agent_name TEXT NOT NULL,
                agent_family TEXT,
                confidence REAL NOT NULL,
                detection_reason TEXT,
                detected_at TEXT NOT NULL
            )'
        ];

        foreach ($schemaStatements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $exception) {
                self::fail('Failed to initialise schema: ' . $exception->getMessage());
            }
        }
    }
}