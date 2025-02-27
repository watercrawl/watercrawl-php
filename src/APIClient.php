<?php

namespace WaterCrawl;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class APIClient extends BaseAPIClient
{
    public function __construct(string $apiKey, string $baseUrl = 'https://app.watercrawl.dev/')
    {
        parent::__construct($apiKey, $baseUrl);
    }

    /**
     * @param ResponseInterface $response
     * @param bool $download
     * @return Generator
     */
    protected function processEventstream(ResponseInterface $response, bool $download = false): Generator
    {
        $buffer = '';
        $stream = $response->getBody();

        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);
                if (str_starts_with($line, 'data:')) {
                    $line = trim(substr($line, 5));
                    $data = json_decode($line, true);
                    if ($data && isset($data['type']) && $data['type'] === 'result' && $download) {
                        $data['data'] = $this->downloadResult($data['data']);
                    }
                    if ($data) {
                        yield $data;
                    }
                }
            }
        }

        // Handle any remaining data in the buffer
        if ($buffer !== '') {
            $line = trim($buffer);
            if (str_starts_with($line, 'data:')) {
                $line = trim(substr($line, 5));
                $data = json_decode($line, true);
                if ($data && isset($data['type']) && $data['type'] === 'result' && $download) {
                    $data['data'] = $this->downloadResult($data['data']);
                }
                if ($data) {
                    yield $data;
                }
            }
        }
    }

    /**
     * @param ResponseInterface $response
     * @param bool $download
     * @return array|string|Generator|null
     * @throws RuntimeException
     */
    protected function processResponse(ResponseInterface $response, bool $download = false)
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if ($response->getStatusCode() === 204) {
            return null;
        }

        if (str_contains($contentType, 'application/json')) {
            return json_decode($response->getBody()->getContents(), true);
        }

        if (str_contains($contentType, 'application/octet-stream')) {
            return $response->getBody()->getContents();
        }

        if (str_contains($contentType, 'text/event-stream')) {
            return $this->processEventstream($response, $download);
        }

        throw new RuntimeException("Unknown response type: {$contentType}");
    }

    /**
     * @param int|null $page
     * @param int|null $pageSize
     * @return array
     * @throws GuzzleException
     */
    public function getCrawlRequestsList(?int $page = null, ?int $pageSize = null): array
    {
        $queryParams = [
            'page' => $page ?? 1,
            'page_size' => $pageSize ?? 10
        ];

        return $this->processResponse(
            $this->get('/api/v1/core/crawl-requests/', $queryParams)
        );
    }

    /**
     * @param string $itemId
     * @return array
     * @throws GuzzleException
     */
    public function getCrawlRequest(string $itemId): array
    {
        return $this->processResponse(
            $this->get("/api/v1/core/crawl-requests/{$itemId}/")
        );
    }

    /**
     * @param string|array|null $url
     * @param array|null $spiderOptions
     * @param array|null $pageOptions
     * @param array|null $pluginOptions
     * @return array
     * @throws GuzzleException
     */
    public function createCrawlRequest(
        $url = null,
        ?array $spiderOptions = null,
        ?array $pageOptions = null,
        ?array $pluginOptions = null
    ): array {
        $data = [
            'url' => $url,
            'options' => [
                'spider_options' => (object)($spiderOptions ?? []),
                'page_options' => (object)($pageOptions ?? []),
                'plugin_options' => (object)($pluginOptions ?? []),
            ]
        ];

        return $this->processResponse(
            $this->post('/api/v1/core/crawl-requests/', null, $data)
        );
    }

    /**
     * @param string $itemId
     * @return null
     * @throws GuzzleException
     */
    public function stopCrawlRequest(string $itemId)
    {
        return $this->processResponse(
            $this->delete("/api/v1/core/crawl-requests/{$itemId}/")
        );
    }

    /**
     * @param string $itemId
     * @return array
     * @throws GuzzleException
     */
    public function downloadCrawlRequest(string $itemId): array
    {
        return $this->processResponse(
            $this->get("/api/v1/core/crawl-requests/{$itemId}/download/")
        );
    }

    /**
     * @param string $itemId
     * @param bool $download
     * @return Generator
     * @throws GuzzleException
     */
    public function monitorCrawlRequest(string $itemId, bool $download = true): Generator
    {
        return $this->processResponse(
            $this->get(
                "/api/v1/core/crawl-requests/{$itemId}/status/",
                null,
                [RequestOptions::STREAM => true]
            ),
            $download
        );
    }

    /**
     * @param string $itemId
     * @param int|null $page
     * @param int|null $pageSize
     * @return array
     * @throws GuzzleException
     */
    public function getCrawlRequestResults(string $itemId, ?int $page = null, ?int $pageSize = null): array
    {
        $queryParams = [
            'page' => $page ?? 1,
            'page_size' => $pageSize ?? 10
        ];

        return $this->processResponse(
            $this->get(
                "/api/v1/core/crawl-requests/{$itemId}/results/",
                $queryParams
            )
        );
    }

    /**
     * @param string $url
     * @param array|null $pageOptions
     * @param array|null $pluginOptions
     * @param bool $sync
     * @param bool $download
     * @return array|null
     * @throws GuzzleException
     */
    public function scrapeUrl(
        string $url,
        ?array $pageOptions = null,
        ?array $pluginOptions = null,
        bool $sync = true,
        bool $download = true
    ): ?array {
        $result = $this->createCrawlRequest(
            $url,
            ['allowed_domains' => ['*']],
            $pageOptions,
            $pluginOptions
        );

        if (!$sync) {
            return $result;
        }

        foreach ($this->monitorCrawlRequest($result['uuid'], $download) as $monitorResult) {
            if ($monitorResult['type'] === 'result') {
                return $monitorResult['data'];
            }
        }

        return null;
    }

    /**
     * @param array $resultObject
     * @return array
     * @throws GuzzleException
     */
    public function downloadResult(array $resultObject): array
    {
        $response = $this->httpClient->request('GET', $resultObject['result']);
        $resultObject['result'] = json_decode($response->getBody()->getContents(), true);
        return $resultObject;
    }
} 