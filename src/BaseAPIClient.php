<?php

namespace WaterCrawl;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class BaseAPIClient
{
    protected string $apiKey;
    protected string $baseUrl;
    protected ClientInterface $httpClient;

    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->httpClient = $this->initSession();
    }

    protected function initSession(): ClientInterface
    {
        return new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WaterCrawl-Plugin-PHP',
                'Accept-Language' => 'en-US'
            ]
        ]);
    }

    protected function get(string $endpoint, ?array $queryParams = null, array $options = []): ResponseInterface
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        return $this->httpClient->request('GET', $endpoint, $options);
    }

    protected function post(string $endpoint, ?array $queryParams = null, ?array $data = null, array $options = []): ResponseInterface
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('POST', $endpoint, $options);
    }

    protected function put(string $endpoint, ?array $queryParams = null, ?array $data = null, array $options = []): ResponseInterface
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('PUT', $endpoint, $options);
    }

    protected function delete(string $endpoint, ?array $queryParams = null, array $options = []): ResponseInterface
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        return $this->httpClient->request('DELETE', $endpoint, $options);
    }

    protected function patch(string $endpoint, ?array $queryParams = null, ?array $data = null, array $options = []): ResponseInterface
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('PATCH', $endpoint, $options);
    }
} 