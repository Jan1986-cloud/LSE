<?php

declare(strict_types=1);

namespace LSE\Services\SApi;

use DateInterval;
use DateTimeImmutable;

final class SuggestionEngine
{
    /**
     * @param array<string,mixed> $siteContext
     * @param array<int,array<string,mixed>> $blueprints
     * @param array<int,array<string,mixed>> $opportunities
     * @return list<array<string,mixed>>
     */
    public function assemble(array $siteContext, array $blueprints, array $opportunities): array
    {
        if ($blueprints === [] || $opportunities === []) {
            return [];
        }

        $suggestions = [];
        $baseDate = new DateTimeImmutable('now');

        foreach ($opportunities as $index => $opportunity) {
            foreach ($blueprints as $blueprint) {
                $suggestions[] = $this->craftSuggestion($siteContext, $blueprint, $opportunity, $baseDate, $index);
            }
        }

        return $suggestions;
    }

    /**
     * @param array<string,mixed> $siteContext
     * @param array<string,mixed> $blueprint
     * @param array<string,mixed> $opportunity
     * @return array<string,mixed>
     */
    private function craftSuggestion(array $siteContext, array $blueprint, array $opportunity, DateTimeImmutable $baseDate, int $position): array
    {
        $priority = max(0, 5 - $position);
        $scheduledDate = $baseDate->add(new DateInterval('P' . (string) ($position + 1) . 'D'));

        $audience = $siteContext['audience_profile']['primary'] ?? 'core audience';
        $tone = $siteContext['tone_profile']['style'] ?? 'authoritative';

        $payload = [
            'topic' => $opportunity['topic'],
            'angle' => sprintf(
                'Position %s as a %s thought-leader on %s for %s.',
                $siteContext['context_snapshot']['brand'] ?? 'the brand',
                $tone,
                $opportunity['topic'],
                $audience
            ),
            'keywords' => $opportunity['keywords'] ?? [],
            'score' => $opportunity['score'],
            'call_to_action' => sprintf('Deploy the %s blueprint to capture emerging demand.', (string) $blueprint['name']),
            'blueprint_version' => $blueprint['version'] ?? 1,
        ];

        return [
            'blueprint_id' => (int) $blueprint['id'],
            'site_context_id' => (int) $siteContext['id'],
            'priority' => $priority,
            'suggested_for' => $scheduledDate->format('Y-m-d'),
            'payload' => $payload,
        ];
    }
}
