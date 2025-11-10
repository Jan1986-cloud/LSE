<?php

declare(strict_types=1);

namespace LSE\Services\SApi;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class AgentDetectionService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AiAgentDetector $detector
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    public function detect(array $events): array
    {
        $detections = [];
        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_agent_detections (
                analytics_log_id,
                agent_name,
                agent_family,
                confidence,
                detection_reason,
                detected_at
            ) VALUES (
                :analytics_log_id,
                :agent_name,
                :agent_family,
                :confidence,
                :detection_reason,
                :detected_at
            )'
        );

        foreach ($events as $event) {
            $userAgent = isset($event['userAgent']) ? (string) $event['userAgent'] : '';
            $detection = $this->detector->detect($userAgent);

            if ($detection === null) {
                continue;
            }

            $analyticsLogId = isset($event['analyticsLogId']) ? (int) $event['analyticsLogId'] : null;

            try {
                $stmt->execute([
                    'analytics_log_id' => $analyticsLogId,
                    'agent_name' => $detection['agentName'],
                    'agent_family' => $detection['agentFamily'],
                    'confidence' => $detection['confidence'],
                    'detection_reason' => $detection['guidance'],
                    'detected_at' => (new DateTimeImmutable('now'))->format('c'),
                ]);
            } catch (PDOException $exception) {
                throw new RuntimeException('Failed to persist agent detection: ' . $exception->getMessage(), 0, $exception);
            }

            $detections[] = [
                'analyticsLogId' => $analyticsLogId,
                'agentName' => $detection['agentName'],
                'agentFamily' => $detection['agentFamily'],
                'confidence' => $detection['confidence'],
                'guidance' => $detection['guidance'],
            ];
        }

        return $detections;
    }
}
