<?php

declare(strict_types=1);

namespace LSE\Services\OApi\Tools;

use RuntimeException;

final class CurlHtmlFetcher implements HtmlFetcherInterface
{
    public function fetch(string $url): string
    {
        $curlHandle = curl_init($url);

        if ($curlHandle === false) {
            throw new RuntimeException('Unable to initialize cURL for source capture.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'ProjectAurora-ResearchTool/1.0',
            CURLOPT_ACCEPT_ENCODING => '',
        ]);

        $response = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $httpStatus = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($response === false) {
            throw new RuntimeException(
                'cURL error while fetching source: ' . ($curlError !== '' ? $curlError : 'unknown error')
            );
        }

        if ($httpStatus >= 400 || $httpStatus === 0) {
            throw new RuntimeException('Unexpected HTTP status code while fetching source: ' . $httpStatus);
        }

        $trimmedResponse = trim($response);

        if ($trimmedResponse === '') {
            throw new RuntimeException('Source returned an empty response.');
        }

        return $response;
    }
}
