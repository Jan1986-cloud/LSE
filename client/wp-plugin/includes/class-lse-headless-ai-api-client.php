<?php

declare(strict_types=1);

final class LSE_Headless_AI_Api_Client
{
    /**
     * Map service identifiers to their public URLs.
     * Only M-API is directly accessible - all other services are internal.
     * @var array<string,string>
     */
    private array $serviceUrlMap = [
        'm_api' => LSE_M_API_URL,
    ];

    /**
     * Execute an HTTP request against a configured microservice.
     *
     * @param string $service
     * @param string $path
     * @param string $method
     * @param array<string,mixed>|null $payload
     * @return array{ok:bool,status:int,data:array<string,mixed>|null,error:?string}
     */
    public function request(string $service, string $path, string $method = 'GET', ?array $payload = null): array
    {
        $baseUrl = $this->serviceUrlMap[$service] ?? null;
        if ($baseUrl === null) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => sprintf('Unknown service "%s".', $service),
            ];
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $headers = [
            'Accept' => 'application/json',
        ];

        $apiKey = isset($options['api_key']) ? trim((string) $options['api_key']) : '';
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => 10,
        ];

        if ($payload !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $json = wp_json_encode($payload);
            if ($json === false) {
                return [
                    'ok' => false,
                    'status' => 0,
                    'data' => null,
                    'error' => 'Failed to encode JSON payload.',
                ];
            }

            $args['body'] = $json;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = null;
        $error = null;

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = is_array($decoded) ? $decoded : null;
            } else {
                $error = 'Invalid JSON returned by service.';
            }
        }

        $ok = $statusCode >= 200 && $statusCode < 300;

        return [
            'ok' => $ok,
            'status' => $statusCode,
            'data' => $data,
            'error' => $ok ? $error : ($data['error'] ?? $error ?? 'Service request failed.'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        $defaults = [
            'api_key' => '',
            'site_context_id' => '',
        ];

        $stored = get_option('lse_headless_ai_settings', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    /**
     * @param array<string,mixed> $newOptions
     */
    public function saveOptions(array $newOptions): void
    {
        update_option('lse_headless_ai_settings', $newOptions);
    }
}
