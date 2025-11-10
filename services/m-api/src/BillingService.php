<?php

declare(strict_types=1);

namespace LSE\Services\MApi;

use PDO;
use RuntimeException;

class BillingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{plan:array{id:int,planCode:string,displayName:string},usage:array{tokens:int,cost:float}}
     */
    public function getStatusForUser(int $userId): array
    {
        $plan = $this->fetchActivePlan($userId);
        if ($plan === null) {
            throw new RuntimeException('No active billing plan is assigned to this user.');
        }

        $tokenUsage = $this->fetchTokenUsage($userId);
        $calculatedCost = $this->calculateCost($tokenUsage, (int) $plan['id']);

        return [
            'plan' => [
                'id' => (int) $plan['id'],
                'planCode' => (string) $plan['plan_code'],
                'displayName' => (string) $plan['display_name'],
            ],
            'usage' => [
                'tokens' => $tokenUsage,
                'cost' => $calculatedCost,
            ],
        ];
    }

    public function calculateCost(int $tokenCount, int $planId, ?array $planOverride = null): float
    {
        $plan = $planOverride ?? $this->fetchPlanById($planId);
        if ($plan === null) {
            throw new RuntimeException('Billing plan not found.');
        }

        $tiers = $this->decodeTiers((string) $plan['tier_thresholds']);
        $baseRate = (float) $plan['base_rate'];
        $overageMultiplier = (float) ($plan['overage_multiplier'] ?? 1.0);

        if ($tiers === []) {
            return round($tokenCount * $baseRate, 6);
        }

        $totalCost = 0.0;
        $remainingTokens = $tokenCount;
        $processed = 0;

        foreach ($tiers as $tier) {
            $pricePerToken = (float) ($tier['price_per_token'] ?? $baseRate);
            $upperBound = $tier['upto'] ?? null;

            if ($upperBound !== null) {
                $tierCapacity = max($upperBound - $processed, 0);
                $tokensInTier = min($tierCapacity, $remainingTokens);
            } else {
                $tokensInTier = $remainingTokens;
            }

            if ($tokensInTier <= 0) {
                continue;
            }

            $totalCost += $tokensInTier * $pricePerToken;
            $remainingTokens -= $tokensInTier;
            $processed += $tokensInTier;

            if ($remainingTokens <= 0) {
                break;
            }
        }

        if ($remainingTokens > 0) {
            $totalCost += $remainingTokens * $baseRate * $overageMultiplier;
        }

        return round($totalCost, 6);
    }

    /**
     * @return array{plan_code:string,display_name:string,id:int}|null
     */
    protected function fetchActivePlan(int $userId): ?array
    {
        $sql = 'SELECT bp.id, bp.plan_code, bp.display_name 
                FROM cms_user_billing_plans ubp 
                JOIN cms_billing_plans bp ON bp.id = ubp.billing_plan_id 
                WHERE ubp.user_id = :user_id AND ubp.active = TRUE 
                ORDER BY ubp.assigned_at DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plan === false ? null : $plan;
    }

    /**
     * @return array{base_rate:float,tier_thresholds:string,overage_multiplier:float}|null
     */
    protected function fetchPlanById(int $planId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT base_rate, tier_thresholds, overage_multiplier FROM cms_billing_plans WHERE id = :id');
        $stmt->execute(['id' => $planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plan === false ? null : $plan;
    }

    protected function fetchTokenUsage(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(tokens_used), 0) AS total_tokens 
             FROM cms_token_logs 
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total_tokens'] ?? 0);
    }

    /**
     * @return list<array{price_per_token:float,upto?:int}>
     */
    protected function decodeTiers(string $rawTiers): array
    {
        $decoded = json_decode($rawTiers, true);
        if (!is_array($decoded) || $decoded === []) {
            return [];
        }

        usort($decoded, static function (array $a, array $b): int {
            $aLimit = $a['upto'] ?? PHP_INT_MAX;
            $bLimit = $b['upto'] ?? PHP_INT_MAX;
            return $aLimit <=> $bLimit;
        });

        return array_map(static function (array $tier): array {
            $result = ['price_per_token' => (float) ($tier['price_per_token'] ?? 0.0)];
            if (isset($tier['upto'])) {
                $result['upto'] = (int) $tier['upto'];
            }

            return $result;
        }, $decoded);
    }
}
