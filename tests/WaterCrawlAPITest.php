<?php

namespace WaterCrawl\Tests;

use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use WaterCrawl\APIClient;

class WaterCrawlAPITest extends TestCase
{
    private APIClient $api;
    private ?string $existingCrawlId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new APIClient(
            getenv('WATERCRAWL_API_KEY')
        );

        // Get an existing crawl ID for tests that need one
        $items = $this->api->getCrawlRequestsList();
        if (!empty($items['results'])) {
            $this->existingCrawlId = $items['results'][0]['uuid'];
        }
    }

    public function testGetCrawlRequestsList(): void
    {
        $response = $this->api->getCrawlRequestsList();
        $this->assertIsArray($response['results']);
    }

    public function testGetCrawlRequest(): void
    {
        $this->assertNotNull($this->existingCrawlId, 'No existing crawl requests found for testing');
        
        $response = $this->api->getCrawlRequest($this->existingCrawlId);
        $this->assertIsArray($response);
    }

    public function testCreateCrawlRequest(): void
    {
        try {
            $response = $this->api->createCrawlRequest('https://watercrawl.dev');
            $this->assertIsArray($response);
            $this->assertArrayHasKey('uuid', $response);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent crawls');
            }
            throw $e;
        }
    }

    public function testStopCrawlRequest(): void
    {
        try {
            $result = $this->api->createCrawlRequest('https://watercrawl.dev');
            $response = $this->api->stopCrawlRequest($result['uuid']);
            $this->assertNull($response);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent crawls');
            }
            throw $e;
        }
    }

    public function testDownloadCrawlRequest(): void
    {
        $this->assertNotNull($this->existingCrawlId, 'No existing crawl requests found for testing');
        
        $response = $this->api->downloadCrawlRequest($this->existingCrawlId);
        $this->assertIsArray($response);
    }

    public function testMonitorCrawlRequest(): void
    {
        try {
            $result = $this->api->createCrawlRequest('https://watercrawl.dev');
            $response = $this->api->monitorCrawlRequest($result['uuid']);
            
            foreach ($response as $item) {
                $this->assertIsArray($item);
                break; // Test only first item to avoid long-running test
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent crawls');
            }
            throw $e;
        }
    }

    public function testGetCrawlRequestResults(): void
    {
        $this->assertNotNull($this->existingCrawlId, 'No existing crawl requests found for testing');
        
        $response = $this->api->getCrawlRequestResults($this->existingCrawlId);
        $this->assertIsArray($response['results']);
    }

    public function testDownloadResult(): void
    {
        $response = $this->api->scrapeUrl('https://watercrawl.dev', [], [], false);
        $this->assertArrayHasKey('uuid', $response);
        $uuid = $response['uuid'];
        
        $result = [];
        foreach ($this->api->monitorCrawlRequest($uuid, false) as $item) {
            if($item['type'] === 'result') {
                $result = $item['data'];
                break;
            }
        }

        if (empty($result)) {
            $this->markTestSkipped('No results available for testing');
            return;
        }

        $downloadResponse = $this->api->downloadResult($result);
        $this->assertIsArray($downloadResponse);
        $this->assertIsArray($downloadResponse['result']);

    }

    public function testScrapeUrl(): void
    {
        try {
            $response = $this->api->scrapeUrl('https://watercrawl.dev');
            if ($response !== null) {
                $this->assertIsArray($response);
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent crawls');
            }
            throw $e;
        }
    }
} 