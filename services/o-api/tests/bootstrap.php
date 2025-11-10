<?php

declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require $autoloadPath;
    return;
}

foreach ([
    dirname(__DIR__) . '/tools/TokenUsageLoggerInterface.php',
    dirname(__DIR__) . '/tools/TokenUsageAggregator.php',
    dirname(__DIR__) . '/tools/HtmlFetcherInterface.php',
    dirname(__DIR__) . '/tools/CurlHtmlFetcher.php',
    dirname(__DIR__) . '/tools/ResearchTool.php',
    dirname(__DIR__) . '/tools/JsonValidatorTool.php',
    dirname(__DIR__) . '/tools/LlmClientInterface.php',
    dirname(__DIR__) . '/tools/WritingTool.php',
    __DIR__ . '/Support/InMemoryPdo.php',
    __DIR__ . '/Support/InMemoryStatement.php',
    __DIR__ . '/Support/InMemoryQueryStatement.php',
] as $file) {
    require_once $file;
}
