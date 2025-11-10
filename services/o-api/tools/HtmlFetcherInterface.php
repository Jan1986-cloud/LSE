<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

interface HtmlFetcherInterface
{
    public function fetch(string $url): string;
}
