<?php

declare(strict_types=1);

namespace LSE\Services\SApi;

final class AiAgentDetector
{
    /**
     * @param string $userAgent
     * @return array{agentName:string,agentFamily:?string,confidence:float,guidance:string}|null
     */
    public function detect(string $userAgent): ?array
    {
        $normalized = strtolower(trim($userAgent));
        if ($normalized === '') {
            return null;
        }

        $patterns = [
            [
                'pattern' => '/perplexitybot/i',
                'agentName' => 'PerplexityBot',
                'family' => 'perplexity',
                'confidence' => 0.95,
                'guidance' => 'Perplexity crawler detected. Confirm sitemap freshness and reinforce structured data.',
            ],
            [
                'pattern' => '/google-extended|googlebot\/\d+\.\d+ \(compatible; Googlebot/i',
                'agentName' => 'Google-Extended',
                'family' => 'google',
                'confidence' => 0.9,
                'guidance' => 'Google Extended agent detected. Ensure AI summaries highlight authoritative claims.',
            ],
            [
                'pattern' => '/chatgpt-user|chatgpt-credential|openai\/|gptbot/i',
                'agentName' => 'ChatGPT-User',
                'family' => 'openai',
                'confidence' => 0.88,
                'guidance' => 'OpenAI agent present. Prioritize concise key takeaways and FAQs.',
            ],
            [
                'pattern' => '/claudebot|anthropic|claude-web/i',
                'agentName' => 'Claude-Web',
                'family' => 'anthropic',
                'confidence' => 0.82,
                'guidance' => 'Anthropic crawler detected. Validate citations and freshness for factual authority.',
            ],
            [
                'pattern' => '/bingbot|adidxbot|microsoft ai/i',
                'agentName' => 'BingBot',
                'family' => 'microsoft',
                'confidence' => 0.8,
                'guidance' => 'Microsoft agent encountered. Align metadata and canonical tags for Copilot ingestion.',
            ],
        ];

        foreach ($patterns as $rule) {
            if (preg_match($rule['pattern'], $userAgent) === 1) {
                return [
                    'agentName' => $rule['agentName'],
                    'agentFamily' => $rule['family'],
                    'confidence' => $rule['confidence'],
                    'guidance' => $rule['guidance'],
                ];
            }
        }

        if (str_contains($normalized, 'bot') || str_contains($normalized, 'crawler') || str_contains($normalized, 'spider')) {
            return [
                'agentName' => 'GenericBot',
                'agentFamily' => null,
                'confidence' => 0.55,
                'guidance' => 'Generic automated agent detected. Monitor traffic spikes and throttling.',
            ];
        }

        return null;
    }
}
