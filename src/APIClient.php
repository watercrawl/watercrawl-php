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
     * @return Generator
     */
    protected function processEventstream(ResponseInterface $response): Generator
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
                    yield $data;
                }
            }
        }

        // Handle any remaining data in the buffer
        if ($buffer !== '') {
            $line = trim($buffer);
            if (str_starts_with($line, 'data:')) {
                $line = trim(substr($line, 5));
                $data = json_decode($line, true);
                yield $data;
            }
        }
    }

    /**
     * @param ResponseInterface $response
     * @return array|string|Generator|null
     * @throws RuntimeException
     */
    protected function processResponse(ResponseInterface $response): array|string|Generator|null
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if ($response->getStatusCode() === 204) {
            return null;
        }

        if (str_contains($contentType, 'application/json')) {
            return json_decode($response->getBody()->getContents(), true);
        }

        if (str_contains($contentType, 'application/octet-stream') || str_contains($contentType, 'application/zip')) {
            return $response->getBody()->getContents();
        }

        if (str_contains($contentType, 'text/event-stream')) {
            return $this->processEventstream($response);
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
     * @return null|array
     * @throws GuzzleException
     */
    public function stopCrawlRequest(string $itemId): null|array
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
        $response = $this->processResponse(
            $this->get("/api/v1/core/crawl-requests/{$itemId}/download/")
        );

        // Handle string response (binary data) by wrapping it in an array
        if (is_string($response)) {
            return ['data' => $response, 'content_type' => 'application/zip'];
        }

        return $response;
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
                ['prefetched' => $download],
                [RequestOptions::STREAM => true]
            )
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

    /**
     * Helper method to get a crawl request for sitemap operations
     * 
     * @param string|array $crawlRequest Either a crawl request UUID string or a crawl request object
     * @return array Crawl request object
     * @throws GuzzleException If the crawl request cannot be fetched
     * @throws RuntimeException If the sitemap is not found in the crawl request
     */
    private function getCrawlRequestForSitemap($crawlRequest): array
    {
        if (is_string($crawlRequest)) {
            $crawlRequest = $this->getCrawlRequest($crawlRequest);
        }

        if (!isset($crawlRequest['sitemap']) || empty($crawlRequest['sitemap'])) {
            throw new RuntimeException('Sitemap not found in crawl request');
        }

        return $crawlRequest;
    }

    /**
     * Download the sitemap for a given crawl request
     * 
     * @param string|array $crawlRequest Either a crawl request UUID string or a crawl request object
     * @return array The sitemap data as an array
     * @throws GuzzleException If there's an error fetching the sitemap
     * @throws RuntimeException If the sitemap is not found in the crawl request
     */
    public function downloadSitemap($crawlRequest): array
    {
        $crawlRequest = $this->getCrawlRequestForSitemap($crawlRequest);
        $response = $this->httpClient->request('GET', $crawlRequest['sitemap']);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Download the sitemap as a graph representation
     * 
     * @param string|array $crawlRequest Either a crawl request UUID string or a crawl request object
     * @return array The graph data
     * @throws GuzzleException If there's an error fetching the sitemap graph
     * @throws RuntimeException If the sitemap is not found in the crawl request
     */
    public function downloadSitemapGraph($crawlRequest): array
    {
        $crawlRequest = $this->getCrawlRequestForSitemap($crawlRequest);
        $url = str_replace('/json', '/graph', $crawlRequest['sitemap']);
        $response = $this->httpClient->request('GET', $url);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Download the sitemap as markdown
     * 
     * @param string|array $crawlRequest Either a crawl request UUID string or a crawl request object
     * @return string The sitemap as markdown text
     * @throws GuzzleException If there's an error fetching the markdown
     * @throws RuntimeException If the sitemap is not found in the crawl request
     */
    public function downloadSitemapMarkdown($crawlRequest): string
    {
        $crawlRequest = $this->getCrawlRequestForSitemap($crawlRequest);
        $url = str_replace('/json', '/markdown', $crawlRequest['sitemap']);
        $response = $this->httpClient->request('GET', $url);

        return $response->getBody()->getContents();
    }

    /**
     * Get a list of search requests
     * 
     * @param int|null $page Page number
     * @param int|null $pageSize Number of results per page
     * @return array List of search requests
     * @throws GuzzleException
     */
    public function getSearchRequestsList(?int $page = null, ?int $pageSize = null): array
    {
        $queryParams = [
            'page' => $page ?? 1,
            'page_size' => $pageSize ?? 10
        ];

        return $this->processResponse(
            $this->get('/api/v1/core/search/', $queryParams)
        );
    }

    /**
     * Get details of a specific search request
     * 
     * @param string $itemId UUID of the search request
     * @param bool $download If true, download results; if false, return URLs
     * @return array Search request details
     * @throws GuzzleException
     */
    public function getSearchRequest(string $itemId, bool $download = true): array
    {
        return $this->processResponse(
            $this->get("/api/v1/core/search/{$itemId}/", ['prefetched' => $download])
        );
    }

    /**
     * Create a new search request
     * 
     * @param string $query Search query
     * @param array|null $searchOptions Search options (language, country, time_range, search_type, depth)
     * @param int|null $resultLimit Maximum number of results to return
     * @param bool $sync If true, wait for results; if false, return immediately
     * @param bool $download If true, download results; if false, return URLs
     * @return array|Generator Either search results (if sync=true) or search request object (if sync=false)
     * @throws GuzzleException
     */
    public function createSearchRequest(
        string $query,
        ?array $searchOptions = null,
        ?int $resultLimit = 5,
        bool $sync = true,
        bool $download = true
    ): array|Generator {
        $response = $this->processResponse(
            $this->post(
                '/api/v1/core/search/',
                null,
                [
                    'query' => $query,
                    'search_options' => (object)($searchOptions ?? new \stdClass()),
                    'result_limit' => $resultLimit
                ]
            )
        );

        if (!$sync) {
            return $response;
        }

        foreach ($this->monitorSearchRequest($response['uuid'], $download) as $result) {
            if ($result['type'] === 'state' && $result['status'] === 'finished') {
                return $result['data'];
            }
        }

        throw new RuntimeException('Search request failed or timed out');
    }

    /**
     * Monitor a search request in real-time
     * 
     * @param string $itemId UUID of the search request to monitor
     * @param bool $download If true, download results; if false, return URLs
     * @return Generator Generator yielding search events
     * @throws GuzzleException
     */
    public function monitorSearchRequest(string $itemId, bool $download = true): Generator
    {
        return $this->processResponse(
            $this->get(
                "/api/v1/core/search/{$itemId}/status/",
                ['prefetched' => $download],
                [RequestOptions::STREAM => true]
            )
        );
    }

    /**
     * Stop a running search request
     * 
     * @param string $itemId UUID of the search request to stop
     * @return null|array
     * @throws GuzzleException
     */
    public function stopSearchRequest(string $itemId): null|array
    {
        return $this->processResponse(
            $this->delete("/api/v1/core/search/{$itemId}/")
        );
    }
}
