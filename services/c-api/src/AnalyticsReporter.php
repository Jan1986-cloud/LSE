<?php

declare(strict_types=1);

namespace LSE\Services\CApi;

use JsonException;

final class AnalyticsReporter
{
    /** @var list<array<string,mixed>> */
    private array $queuedPayloads = [];

    /** @var callable|null */
    private $sender;

    /**
     * @param callable|null $sender Receives endpoint URL, payload string, timeout ms
     */
    public function __construct(
        private readonly string $endpointUrl,
        private readonly int $timeoutMs = 200,
        ?callable $sender = null
    ) {
        $this->sender = $sender;
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function queue(array $payload): void
    {
        $this->queuedPayloads[] = $payload;
    }

    public function flush(): void
    {
        if ($this->queuedPayloads === []) {
            return;
        }

        foreach ($this->queuedPayloads as $payload) {
            try {
                $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (JsonException) {
                continue;
            }

            if ($this->sender !== null) {
                ($this->sender)($this->endpointUrl, $encoded, $this->timeoutMs);
                continue;
            }

            $this->dispatch($encoded);
        }

        $this->queuedPayloads = [];
    }

    private function dispatch(string $body): void
    {
        $handle = curl_init($this->endpointUrl);
        if ($handle === false) {
            return;
        }

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        if (defined('CURLOPT_TIMEOUT_MS')) {
            curl_setopt($handle, CURLOPT_TIMEOUT_MS, $this->timeoutMs);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, $this->timeoutMs);
        } else {
            $seconds = max(1, (int) ceil($this->timeoutMs / 1000));
            curl_setopt($handle, CURLOPT_TIMEOUT, $seconds);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $seconds);
        }

        curl_exec($handle);
        curl_close($handle);
    }
}
