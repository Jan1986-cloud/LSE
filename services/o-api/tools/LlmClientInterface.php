<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

interface LlmClientInterface
{
    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options
     *
     * @return array{
     *     content:string,
     *     prompt_tokens:int,
     *     completion_tokens:int,
     *     model?:string,
     *     metadata?:array<string,mixed>
     * }
     */
    public function complete(string $model, array $messages, array $options = []): array;
}
