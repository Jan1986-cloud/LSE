<?php

declare(strict_types=1);

namespace LSE\Services\MApi\Tests;

use LSE\Services\MApi\BlueprintService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class BlueprintServiceTest extends TestCase
{
    private PDO $pdo;
    private BlueprintService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<SQL
            CREATE TABLE cms_blueprints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                category TEXT,
                workflow_definition TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                version INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        $this->service = new BlueprintService($this->pdo);
    }

    public function testCreateBlueprintPersistsRecord(): void
    {
        $blueprint = $this->service->createBlueprint(7, [
            'name' => 'Newsletter',
            'description' => 'Weekly newsletter flow',
            'category' => 'email',
            'workflowDefinition' => [
                'steps' => [
                    ['tool' => 'research', 'config' => ['keywords' => ['ai', 'market']]],
                ],
            ],
        ]);

        self::assertSame('Newsletter', $blueprint['name']);
        self::assertSame('draft', $blueprint['status']);
        self::assertSame(1, $blueprint['version']);
        self::assertSame(7, $blueprint['userId']);
        self::assertArrayHasKey('createdAt', $blueprint);
        self::assertArrayHasKey('updatedAt', $blueprint);
        self::assertSame(['steps' => [['tool' => 'research', 'config' => ['keywords' => ['ai', 'market']]]]], $blueprint['workflowDefinition']);
    }

    public function testListBlueprintsReturnsOnlyForUser(): void
    {
        $this->seedBlueprint(1, 'Alpha');
        $this->seedBlueprint(2, 'Beta');
        $this->seedBlueprint(1, 'Gamma');

        $blueprints = $this->service->listBlueprints(1);

        self::assertCount(2, $blueprints);
        self::assertSame('Gamma', $blueprints[0]['name']);
        self::assertSame('Alpha', $blueprints[1]['name']);
    }

    public function testUpdateBlueprintBumpsVersionWhenStructureChanges(): void
    {
        $initial = $this->service->createBlueprint(3, [
            'name' => 'Contextual Article',
            'workflowDefinition' => ['steps' => []],
        ]);

        $updated = $this->service->updateBlueprint(3, $initial['id'], [
            'name' => 'Contextual Article v2',
            'workflowDefinition' => ['steps' => [['tool' => 'writing']]],
        ]);

        self::assertNotNull($updated);
        self::assertSame(2, $updated['version']);
        self::assertSame('Contextual Article v2', $updated['name']);
        self::assertSame(['steps' => [['tool' => 'writing']]], $updated['workflowDefinition']);
    }

    public function testUpdateBlueprintDoesNotBumpVersionForStatusOnlyChange(): void
    {
        $initial = $this->service->createBlueprint(5, [
            'name' => 'Landing Page',
            'workflowDefinition' => ['steps' => []],
        ]);

        $updated = $this->service->updateBlueprint(5, $initial['id'], [
            'status' => 'active',
        ]);

        self::assertNotNull($updated);
        self::assertSame(1, $updated['version']);
        self::assertSame('active', $updated['status']);
    }

    public function testDeleteBlueprintRemovesRecord(): void
    {
        $blueprint = $this->service->createBlueprint(11, [
            'name' => 'One-off',
            'workflowDefinition' => ['steps' => []],
        ]);

        $deleted = $this->service->deleteBlueprint(11, $blueprint['id']);
        self::assertTrue($deleted);

        $fetched = $this->service->getBlueprint(11, $blueprint['id']);
        self::assertNull($fetched);
    }

    public function testInvalidStatusThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createBlueprint(9, [
            'name' => 'Invalid',
            'status' => 'broken',
            'workflowDefinition' => ['steps' => []],
        ]);
    }

    private function seedBlueprint(int $userId, string $name): void
    {
        $this->service->createBlueprint($userId, [
            'name' => $name,
            'workflowDefinition' => ['steps' => []],
        ]);
    }
}
