<?php

namespace WaterCrawl;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class BaseAPIClient
{
    /**
     * @var string
     */
    protected $apiKey;
    
    /**
     * @var string
     */
    protected $baseUrl;
    
    /**
     * @var ClientInterface
     */
    protected $httpClient;

    public function __construct($apiKey, $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->httpClient = $this->initSession();
    }

    /**
     * @return ClientInterface
     */
    protected function initSession()
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

    /**
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array $options
     * @return ResponseInterface
     */
    protected function get($endpoint, $queryParams = null, $options = [])
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        return $this->httpClient->request('GET', $endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array|null $data
     * @param array $options
     * @return ResponseInterface
     */
    protected function post($endpoint, $queryParams = null, $data = null, $options = [])
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('POST', $endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array|null $data
     * @param array $options
     * @return ResponseInterface
     */
    protected function put($endpoint, $queryParams = null, $data = null, $options = [])
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('PUT', $endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array $options
     * @return ResponseInterface
     */
    protected function delete($endpoint, $queryParams = null, $options = [])
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        return $this->httpClient->request('DELETE', $endpoint, $options);
    }

    /**
     * @param string $endpoint
     * @param array|null $queryParams
     * @param array|null $data
     * @param array $options
     * @return ResponseInterface
     */
    protected function patch($endpoint, $queryParams = null, $data = null, $options = [])
    {
        $options[RequestOptions::QUERY] = $queryParams ?? [];
        if ($data !== null) {
            $options[RequestOptions::JSON] = $data;
        }
        return $this->httpClient->request('PATCH', $endpoint, $options);
    }
}