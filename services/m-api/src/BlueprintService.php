<?php

declare(strict_types=1);

namespace LSE\Services\MApi;

use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

class BlueprintService
{
    private const ALLOWED_STATUSES = ['draft', 'active', 'archived'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{id:int,userId:int,name:string,description:?string,category:?string,status:string,version:int,workflowDefinition:array<string|int,mixed>,createdAt:string,updatedAt:string}>
     */
    public function listBlueprints(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, description, category, status, version, workflow_definition, created_at, updated_at
             FROM cms_blueprints WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $blueprints = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $blueprints[] = $this->hydrate($row);
            }
        }

        return $blueprints;
    }

    /**
     * @return array{id:int,userId:int,name:string,description:?string,category:?string,status:string,version:int,workflowDefinition:array<string|int,mixed>,createdAt:string,updatedAt:string}|null
     */
    public function getBlueprint(int $userId, int $blueprintId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, description, category, status, version, workflow_definition, created_at, updated_at
             FROM cms_blueprints WHERE user_id = :user_id AND id = :id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'id' => $blueprintId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{name?:string,description?:?string,category?:?string,status?:?string,workflowDefinition?:mixed} $input
     * @return array{id:int,userId:int,name:string,description:?string,category:?string,status:string,version:int,workflowDefinition:array<string|int,mixed>,createdAt:string,updatedAt:string}
     */
    public function createBlueprint(int $userId, array $input): array
    {
        $name = isset($input['name']) ? trim((string) $input['name']) : '';
        if ($name === '') {
            throw new InvalidArgumentException('Blueprint name is required.');
        }

        if (!array_key_exists('workflowDefinition', $input)) {
            throw new InvalidArgumentException('workflowDefinition is required.');
        }

        $workflowDefinition = $input['workflowDefinition'];
        if (!is_array($workflowDefinition)) {
            throw new InvalidArgumentException('workflowDefinition must be an object.');
        }

        $status = $this->normalizeStatus($input['status'] ?? null);
        $description = $this->normalizeNullableString($input['description'] ?? null);
        $category = $this->normalizeNullableString($input['category'] ?? null);

        $workflowJson = $this->encodeWorkflow($workflowDefinition);

        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_blueprints (user_id, name, description, category, workflow_definition, status)
             VALUES (:user_id, :name, :description, :category, :workflow_definition, :status)'
        );

        try {
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'workflow_definition' => $workflowJson,
                'status' => $status,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to create blueprint: ' . $exception->getMessage(), 0, $exception);
        }

        $blueprintId = $this->pdo->lastInsertId();
        if ($blueprintId === false) {
            throw new RuntimeException('Failed to determine blueprint identifier.');
        }

        $blueprint = $this->getBlueprint($userId, (int) $blueprintId);
        if ($blueprint === null) {
            throw new RuntimeException('Blueprint created but not found.');
        }

        return $blueprint;
    }

    /**
     * @param array{name?:?string,description?:?string,category?:?string,status?:?string,workflowDefinition?:mixed} $changes
     * @return array{id:int,userId:int,name:string,description:?string,category:?string,status:string,version:int,workflowDefinition:array<string|int,mixed>,createdAt:string,updatedAt:string}|null
     */
    public function updateBlueprint(int $userId, int $blueprintId, array $changes): ?array
    {
        $fields = [];
        $params = [
            'user_id' => $userId,
            'id' => $blueprintId,
        ];

        $bumpVersion = false;

        if (array_key_exists('name', $changes)) {
            $name = $changes['name'];
            if ($name === null || trim((string) $name) === '') {
                throw new InvalidArgumentException('Blueprint name cannot be empty.');
            }
            $fields[] = 'name = :name';
            $params['name'] = trim((string) $name);
            $bumpVersion = true;
        }

        if (array_key_exists('description', $changes)) {
            $fields[] = 'description = :description';
            $params['description'] = $this->normalizeNullableString($changes['description']);
            $bumpVersion = true;
        }

        if (array_key_exists('category', $changes)) {
            $fields[] = 'category = :category';
            $params['category'] = $this->normalizeNullableString($changes['category']);
            $bumpVersion = true;
        }

        if (array_key_exists('status', $changes)) {
            $fields[] = 'status = :status';
            $params['status'] = $this->normalizeStatus($changes['status']);
        }

        if (array_key_exists('workflowDefinition', $changes)) {
            $workflowDefinition = $changes['workflowDefinition'];
            if (!is_array($workflowDefinition)) {
                throw new InvalidArgumentException('workflowDefinition must be an object.');
            }

            $fields[] = 'workflow_definition = :workflow_definition';
            $params['workflow_definition'] = $this->encodeWorkflow($workflowDefinition);
            $bumpVersion = true;
        }

        if ($fields === []) {
            return $this->getBlueprint($userId, $blueprintId);
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        if ($bumpVersion) {
            $fields[] = 'version = version + 1';
        }

        $sql = 'UPDATE cms_blueprints SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id AND id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->getBlueprint($userId, $blueprintId);
    }

    public function deleteBlueprint(int $userId, int $blueprintId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM cms_blueprints WHERE user_id = :user_id AND id = :id');
        $stmt->execute([
            'user_id' => $userId,
            'id' => $blueprintId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string|int,mixed> $workflowDefinition
     */
    private function encodeWorkflow(array $workflowDefinition): string
    {
        try {
            return json_encode($workflowDefinition, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('workflowDefinition must be encodable as JSON.', 0, $exception);
        }
    }

    private function normalizeStatus(?string $status): string
    {
        $value = $status !== null ? strtolower(trim($status)) : 'draft';
        if ($value === '') {
            $value = 'draft';
        }

        if (!in_array($value, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid blueprint status.');
        }

        return $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array{id:int,user_id:int,name:string,description:?string,category:?string,status:string,version:int,workflow_definition:string,created_at:string,updated_at:string} $row
     * @return array{id:int,userId:int,name:string,description:?string,category:?string,status:string,version:int,workflowDefinition:array<string|int,mixed>,createdAt:string,updatedAt:string}
     */
    private function hydrate(array $row): array
    {
        try {
            $workflowDefinition = json_decode((string) $row['workflow_definition'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Stored workflow definition is invalid JSON.', 0, $exception);
        }

        return [
            'id' => (int) $row['id'],
            'userId' => (int) $row['user_id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'category' => $row['category'] !== null ? (string) $row['category'] : null,
            'status' => (string) $row['status'],
            'version' => (int) $row['version'],
            'workflowDefinition' => is_array($workflowDefinition) ? $workflowDefinition : [],
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }
}
