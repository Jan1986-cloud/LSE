<?php

declare(strict_types=1);

namespace LSE\Services\MApi\Tests;

use LSE\Services\MApi\BillingService;
use PDO;
use PHPUnit\Framework\TestCase;

final class BillingServiceTest extends TestCase
{
    private BillingService $service;

    protected function setUp(): void
    {
        $plan = [
            'base_rate' => 0.0008,
            'tier_thresholds' => '[{"upto":100000,"price_per_token":0.0008},{"upto":1000000,"price_per_token":0.0006},{"price_per_token":0.0005}]',
            'overage_multiplier' => 1.25,
        ];

        $pdo = new class extends PDO {
            public function __construct()
            {
                // Intentionally empty to avoid invoking the parent constructor.
            }
        };

        $this->service = new class ($pdo, $plan) extends BillingService {
            private array $plan;

            public function __construct(PDO $pdo, array $plan)
            {
                parent::__construct($pdo);
                $this->plan = $plan;
            }

            protected function fetchPlanById(int $planId): ?array
            {
                return $this->plan;
            }
        };
    }

    /**
     * @return array<string,array{tokens:int,expected:float}>
     */
    public static function provideTokenScenarios(): array
    {
        return [
            '10k tokens' => ['tokens' => 10_000, 'expected' => 8.0],
            '1 million tokens' => ['tokens' => 1_000_000, 'expected' => 620.0],
            '5.5 million tokens' => ['tokens' => 5_500_000, 'expected' => 2870.0],
        ];
    }

    /**
     * @dataProvider provideTokenScenarios
     */
    public function testCalculateCostWithTieredDiscounts(int $tokens, float $expected): void
    {
        $cost = $this->service->calculateCost($tokens, 1);
        self::assertSame($expected, $cost);
    }
}
