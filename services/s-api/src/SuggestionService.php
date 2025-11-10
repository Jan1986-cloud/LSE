<?php

declare(strict_types=1);

namespace LSE\Services\SApi;

use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

final class SuggestionService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TrendAnalysisTool $trendAnalysis,
        private readonly SuggestionEngine $suggestionEngine
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $trendSignals
     * @param array<int>|null $blueprintIds
     * @return list<array<string,mixed>>
     */
    public function generate(int $siteContextId, array $trendSignals, ?array $blueprintIds = null): array
    {
        $siteContext = $this->fetchSiteContext($siteContextId);
        if ($siteContext === null) {
            throw new RuntimeException('Site context not found.');
        }

        $blueprints = $this->fetchBlueprints($blueprintIds);
        if ($blueprints === []) {
            throw new RuntimeException('No blueprints available for suggestion generation.');
        }

        $opportunities = $this->trendAnalysis->identifyOpportunities($trendSignals, $siteContext);
        if ($opportunities === []) {
            return [];
        }

        $suggestions = $this->suggestionEngine->assemble($siteContext, $blueprints, $opportunities);

        return $this->persistSuggestions($suggestions);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchSiteContext(int $contextId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, context_snapshot, tone_profile, audience_profile
             FROM cms_site_context
             WHERE id = :id'
        );
        $stmt->execute(['id' => $contextId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'context_snapshot' => $this->decodeJson($row['context_snapshot']),
            'tone_profile' => $this->decodeJson($row['tone_profile']),
            'audience_profile' => $this->decodeJson($row['audience_profile']),
        ];
    }

    /**
     * @param array<int>|null $blueprintIds
     * @return list<array<string,mixed>>
     */
    private function fetchBlueprints(?array $blueprintIds): array
    {
        $params = [];
        $whereClause = 'status IN (\'active\', \'draft\')';

        if ($blueprintIds !== null && $blueprintIds !== []) {
            $placeholders = implode(',', array_fill(0, count($blueprintIds), '?'));
            $whereClause .= ' AND id IN (' . $placeholders . ')';
            $params = array_map('intval', $blueprintIds);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, workflow_definition
             FROM cms_blueprints
             WHERE ' . $whereClause
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $blueprints = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $blueprints[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'version' => (int) $row['version'],
                'workflow_definition' => $this->decodeJson($row['workflow_definition']),
            ];
        }

        return $blueprints;
    }

    /**
     * @param list<array<string,mixed>> $suggestions
     * @return list<array<string,mixed>>
     */
    private function persistSuggestions(array $suggestions): array
    {
        if ($suggestions === []) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_content_suggestions (
                blueprint_id,
                site_context_id,
                suggestion_payload,
                priority,
                status,
                suggested_for
            ) VALUES (
                :blueprint_id,
                :site_context_id,
                :suggestion_payload,
                :priority,
                :status,
                :suggested_for
            )'
        );

        $persisted = [];
        foreach ($suggestions as $suggestion) {
            try {
                $stmt->execute([
                    'blueprint_id' => $suggestion['blueprint_id'],
                    'site_context_id' => $suggestion['site_context_id'],
                    'suggestion_payload' => json_encode($suggestion['payload'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'priority' => $suggestion['priority'],
                    'status' => 'queued',
                    'suggested_for' => $suggestion['suggested_for'],
                ]);
            } catch (JsonException|PDOException $exception) {
                throw new RuntimeException('Failed to persist suggestion: ' . $exception->getMessage(), 0, $exception);
            }

            $id = (int) $this->pdo->lastInsertId();
            $persisted[] = [
                'id' => $id,
                'blueprintId' => $suggestion['blueprint_id'],
                'siteContextId' => $suggestion['site_context_id'],
                'priority' => $suggestion['priority'],
                'status' => 'queued',
                'suggestedFor' => $suggestion['suggested_for'],
                'payload' => $suggestion['payload'],
                'createdAt' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::RFC3339_EXTENDED),
            ];
        }

        return $persisted;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
