<?php

declare(strict_types=1);

namespace LSE\Services\SApi\Tests;

use LSE\Services\SApi\SuggestionEngine;
use LSE\Services\SApi\SuggestionService;
use LSE\Services\SApi\TrendAnalysisTool;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SuggestionServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->bootSchema();
        $this->seedFixtures();
    }

    public function testGeneratePersistsSuggestionsAndReturnsPayload(): void
    {
        $service = new SuggestionService($this->pdo, new TrendAnalysisTool(), new SuggestionEngine());

        $trendSignals = [
            [
                'topic' => 'AI marketing automation strategies',
                'searchVolume' => 4200,
                'growthRate' => 0.35,
                'competition' => 0.15,
                'relevancy' => 0.9,
            ],
        ];

        $results = $service->generate(1, $trendSignals);

        self::assertCount(1, $results, 'Expected a single suggestion to be generated.');
        $suggestion = $results[0];

        self::assertArrayHasKey('id', $suggestion);
        self::assertSame(1, $suggestion['blueprintId']);
        self::assertSame(1, $suggestion['siteContextId']);
        self::assertSame('queued', $suggestion['status']);
        self::assertGreaterThan(0, $suggestion['priority']);
        self::assertIsArray($suggestion['payload']);
        self::assertArrayHasKey('topic', $suggestion['payload']);

        $rowCount = (int) $this->pdo->query('SELECT COUNT(*) FROM cms_content_suggestions')->fetchColumn();
        self::assertSame(1, $rowCount, 'Suggestion should be persisted to the datastore.');

        $storedPayload = (string) $this->pdo->query('SELECT suggestion_payload FROM cms_content_suggestions LIMIT 1')->fetchColumn();
        self::assertStringContainsString('AI marketing automation strategies', $storedPayload);
    }

    public function testGenerateThrowsWhenSiteContextMissing(): void
    {
        $service = new SuggestionService($this->pdo, new TrendAnalysisTool(), new SuggestionEngine());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Site context not found.');

        $service->generate(999, [
            [
                'topic' => 'Test topic',
                'searchVolume' => 1000,
            ],
        ]);
    }

    public function testGenerateReturnsEmptyWhenNoOpportunitiesProduced(): void
    {
        $service = new SuggestionService($this->pdo, new TrendAnalysisTool(), new SuggestionEngine());

        $results = $service->generate(1, [
            [
                'searchVolume' => 0,
            ],
        ]);

        self::assertSame([], $results, 'No opportunities should lead to empty suggestions.');

        $rowCount = (int) $this->pdo->query('SELECT COUNT(*) FROM cms_content_suggestions')->fetchColumn();
        self::assertSame(0, $rowCount, 'No suggestions should be persisted.');
    }

    /**
     * @return void
     */
    private function bootSchema(): void
    {
        $schemaStatements = [
            <<<'SQL'
CREATE TABLE cms_site_context (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    context_snapshot TEXT,
    tone_profile TEXT,
    audience_profile TEXT
)
SQL,
            <<<'SQL'
CREATE TABLE cms_blueprints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'active',
    workflow_definition TEXT
)
SQL,
            <<<'SQL'
CREATE TABLE cms_content_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    blueprint_id INTEGER,
    site_context_id INTEGER,
    suggestion_payload TEXT NOT NULL,
    priority INTEGER NOT NULL,
    status TEXT NOT NULL,
    suggested_for TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)
SQL,
        ];

        foreach ($schemaStatements as $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $exception) {
                self::fail('Failed to initialise schema: ' . $exception->getMessage());
            }
        }
    }

    private function seedFixtures(): void
    {
        $contextSnapshot = json_encode([
            'brand' => 'Luminate',
            'keywords' => ['automation', 'ai'],
        ], \JSON_THROW_ON_ERROR);

        $toneProfile = json_encode([
            'style' => 'authoritative',
            'keywords' => ['strategy'],
        ], \JSON_THROW_ON_ERROR);

        $audienceProfile = json_encode([
            'primary' => 'marketing directors',
        ], \JSON_THROW_ON_ERROR);

        $this->pdo->prepare('INSERT INTO cms_site_context (id, user_id, context_snapshot, tone_profile, audience_profile) VALUES (?,?,?,?,?)')
            ->execute([1, 1, $contextSnapshot, $toneProfile, $audienceProfile]);

        $workflowDefinition = json_encode([
            'steps' => ['research', 'draft', 'review'],
        ], \JSON_THROW_ON_ERROR);

        $this->pdo->prepare('INSERT INTO cms_blueprints (id, user_id, name, version, status, workflow_definition) VALUES (?,?,?,?,?,?)')
            ->execute([1, 1, 'Thought Leadership Feature', 3, 'active', $workflowDefinition]);
    }
}