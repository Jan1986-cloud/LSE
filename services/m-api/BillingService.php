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

    public function calculateCost(int $tokenCount, int $planId): float
    {
        $plan = $this->fetchPlanById($planId);
        if ($plan === null) {
            throw new RuntimeException('Billing plan not found.');
        }

        $tiers = $this->decodeTiers($plan['tier_thresholds']);
        if ($tiers === []) {
            $baseRate = (float) $plan['base_rate'];
            return round($tokenCount * $baseRate, 6);
        }
        $totalCost = 0.0;
        $remainingTokens = $tokenCount;
        $previousThreshold = 0;

        foreach ($tiers as $tier) {
            $pricePerToken = (float) ($tier['price_per_token'] ?? $plan['base_rate']);
            $upperBound = isset($tier['upto']) ? (int) $tier['upto'] : null;

            if ($upperBound !== null) {
                $tokensInTier = max(min($tokenCount, $upperBound) - $previousThreshold, 0);
            } else {
                $tokensInTier = max($tokenCount - $previousThreshold, 0);
            }

            if ($tokensInTier <= 0) {
                continue;
            }

            $totalCost += $tokensInTier * $pricePerToken;
            $remainingTokens -= $tokensInTier;
            $previousThreshold += $tokensInTier;

            if ($upperBound === null) {
                break;
            }
        }

        if ($remainingTokens > 0) {
            $overageMultiplier = (float) $plan['overage_multiplier'];
            $totalCost += $remainingTokens * (float) $plan['base_rate'] * $overageMultiplier;
        }

        return round($totalCost, 6);
    }

    private function fetchActivePlan(int $userId): ?array
    {
        $sql = 'SELECT bp.* 
                FROM cms_user_billing_plans ubp 
                JOIN cms_billing_plans bp ON bp.id = ubp.billing_plan_id 
                WHERE ubp.user_id = :user_id AND ubp.active = TRUE 
                ORDER BY ubp.assigned_at DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plan === false ? null : $plan;
    }

    private function fetchPlanById(int $planId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cms_billing_plans WHERE id = :id');
        $stmt->execute(['id' => $planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $plan === false ? null : $plan;
    }

    private function fetchTokenUsage(int $userId): int
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
    private function decodeTiers(string $rawTiers): array
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
            $price = isset($tier['price_per_token']) ? (float) $tier['price_per_token'] : 0.0;
            $result = ['price_per_token' => $price];
            if (isset($tier['upto'])) {
                $result['upto'] = (int) $tier['upto'];
            }
            return $result;
        }, $decoded);
    }
}
