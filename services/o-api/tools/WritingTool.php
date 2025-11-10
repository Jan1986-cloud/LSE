<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

use RuntimeException;

final class WritingTool
{
    private LlmClientInterface $client;
    private TokenUsageLoggerInterface $tokenUsageLogger;

    public function __construct(LlmClientInterface $client, TokenUsageLoggerInterface $tokenUsageLogger)
    {
        $this->client = $client;
        $this->tokenUsageLogger = $tokenUsageLogger;
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options
     *
     * @return array{
     *     content:string,
     *     token_log_id:int,
     *     tokens:array{prompt:int,completion:int,total:int},
     *     model:string
     * }
     */
    public function generateContent(
        int $userId,
        int|string $workflowId,
        array $messages,
        string $model,
        ?int $blueprintId = null,
        array $options = []
    ): array {
        $response = $this->client->complete($model, $messages, $options);

        if (!isset($response['content']) || !is_string($response['content'])) {
            throw new RuntimeException('LLM response did not include generated content.');
        }

        if (!isset($response['prompt_tokens'], $response['completion_tokens'])) {
            throw new RuntimeException('LLM response missing token usage details.');
        }

        $promptTokens = (int) $response['prompt_tokens'];
        $completionTokens = (int) $response['completion_tokens'];
        $totalTokens = $promptTokens + $completionTokens;

        $resolvedModel = isset($response['model']) && is_string($response['model'])
            ? $response['model']
            : $model;

        $costAmount = 0.0;
        if (isset($options['cost_amount'])) {
            $costAmount = (float) $options['cost_amount'];
            unset($options['cost_amount']);
        }

        $additionalMetadata = [];
        if (isset($options['metadata'])) {
            if (!is_array($options['metadata'])) {
                throw new RuntimeException('WritingTool metadata option must be an array.');
            }

            $additionalMetadata = $options['metadata'];
            unset($options['metadata']);
        }

        $responseMetadata = [];
        if (isset($response['metadata'])) {
            if (!is_array($response['metadata'])) {
                throw new RuntimeException('LLM response metadata must be an array when provided.');
            }

            $responseMetadata = $response['metadata'];
        }

        $metadata = array_merge(
            ['model' => $resolvedModel, 'messages_count' => count($messages)],
            $responseMetadata,
            $additionalMetadata
        );

        if ($options !== []) {
            $metadata['generation_options'] = $options;
        }

        $tokenLogId = $this->tokenUsageLogger->logUsage(
            $userId,
            $workflowId,
            'o-api:writing-tool',
            $totalTokens,
            $blueprintId,
            $promptTokens,
            $completionTokens,
            $costAmount,
            $metadata
        );

        return [
            'content' => $response['content'],
            'token_log_id' => $tokenLogId,
            'tokens' => [
                'prompt' => $promptTokens,
                'completion' => $completionTokens,
                'total' => $totalTokens,
            ],
            'model' => $resolvedModel,
        ];
    }
}
