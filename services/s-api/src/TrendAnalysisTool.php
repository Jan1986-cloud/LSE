<?php

declare(strict_types=1);

namespace LSE\Services\SApi;

final class TrendAnalysisTool
{
    /**
     * Evaluate raw trend signals and return prioritized opportunities.
     *
     * Each trend signal supports the following structure:
     *  - topic (string)
     *  - searchVolume (int)
     *  - growthRate (float between -1 and 1)
     *  - competition (float between 0 and 1)
     *  - relevancy (float between 0 and 1) optional
     *
     * @param array<int,array<string,mixed>> $trendSignals
     * @param array<string,mixed> $siteContext
     * @return list<array<string,mixed>>
     */
    public function identifyOpportunities(array $trendSignals, array $siteContext): array
    {
        $contextKeywords = $this->extractContextKeywords($siteContext);

        $scored = [];
        foreach ($trendSignals as $signal) {
            if (!isset($signal['topic'], $signal['searchVolume'])) {
                continue;
            }

            $topic = (string) $signal['topic'];
            $searchVolume = max(0, (int) $signal['searchVolume']);
            $growthRate = isset($signal['growthRate']) ? (float) $signal['growthRate'] : 0.0;
            $competition = isset($signal['competition']) ? (float) $signal['competition'] : 0.3;
            $relevancy = isset($signal['relevancy']) ? (float) $signal['relevancy'] : 0.5;

            $baseScore = ($searchVolume / 1000.0) * (1 + max(-0.9, $growthRate)) * (1 - min(0.9, max(0.0, $competition)));

            $contextBoost = 1.0;
            foreach ($contextKeywords as $keyword) {
                if (stripos($topic, $keyword) !== false) {
                    $contextBoost += 0.35;
                }
            }

            $score = $baseScore * $contextBoost * (1 + max(0.0, $relevancy));
            $scored[] = [
                'topic' => $topic,
                'score' => round($score, 2),
                'searchVolume' => $searchVolume,
                'growthRate' => $growthRate,
                'competition' => $competition,
                'relevancy' => $relevancy,
                'keywords' => $this->deriveKeywords($topic, $contextKeywords),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 5);
    }

    /**
     * @param array<string,mixed> $siteContext
     * @return list<string>
     */
    private function extractContextKeywords(array $siteContext): array
    {
        $keywords = [];

        if (isset($siteContext['context_snapshot']) && is_array($siteContext['context_snapshot'])) {
            $snapshotKeywords = $siteContext['context_snapshot']['keywords'] ?? [];
            if (is_array($snapshotKeywords)) {
                $keywords = array_merge($keywords, array_map('strval', $snapshotKeywords));
            }
        }

        if (isset($siteContext['tone_profile']) && is_array($siteContext['tone_profile'])) {
            $toneKeywords = $siteContext['tone_profile']['keywords'] ?? [];
            if (is_array($toneKeywords)) {
                $keywords = array_merge($keywords, array_map('strval', $toneKeywords));
            }
        }

        $keywords = array_values(array_unique(array_filter(array_map(static fn (string $keyword): string => strtolower(trim($keyword)), $keywords), static fn (string $keyword): bool => $keyword !== '')));

        return $keywords;
    }

    /**
     * @param list<string> $contextKeywords
     * @return list<string>
     */
    private function deriveKeywords(string $topic, array $contextKeywords): array
    {
        $keywords = [];
        $topicParts = preg_split('/\s+/u', strtolower($topic)) ?: [];
        foreach ($topicParts as $part) {
            $clean = trim($part, "\s.,!?");
            if ($clean !== '') {
                $keywords[] = $clean;
            }
        }

        foreach ($contextKeywords as $keyword) {
            if (stripos($topic, $keyword) !== false) {
                $keywords[] = $keyword;
            }
        }

        return array_values(array_unique($keywords));
    }
}
