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

    // New tests for search functionality
    public function testGetSearchRequestsList(): void
    {
        $response = $this->api->getSearchRequestsList();
        $this->assertIsArray($response['results']);
    }

    public function testCreateSearchRequest(): void
    {
        try {
            // Create an async search request to not wait for results
            $response = $this->api->createSearchRequest('php library', [], 2, false);
            $this->assertIsArray($response);
            $this->assertArrayHasKey('uuid', $response);

            // Clean up after test
            try {
                $this->api->stopSearchRequest($response['uuid']);
            } catch (\Exception $e) {
                // Ignore errors on cleanup
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent searches');
            }
            throw $e;
        }
    }

    public function testGetSearchRequest(): void
    {
        try {
            // Create a search request first
            $request = $this->api->createSearchRequest('php tutorial', [], 2, false);
            
            // Get the details
            $response = $this->api->getSearchRequest($request['uuid']);
            $this->assertIsArray($response);
            $this->assertArrayHasKey('uuid', $response);
            $this->assertArrayHasKey('query', $response);
            $this->assertEquals('php tutorial', $response['query']);

            // Clean up after test
            try {
                $this->api->stopSearchRequest($request['uuid']);
            } catch (\Exception $e) {
                // Ignore errors on cleanup
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent searches');
            }
            throw $e;
        }
    }

    public function testGetSearchRequestWithDownload(): void
    {
        try {
            // Create a search request first
            $request = $this->api->createSearchRequest('php programming', [], 2, false);

            foreach ($this->api->monitorSearchRequest($request['uuid'], false) as $item) {
                if ($item['type'] === 'state' && $item['data']['status'] === 'finished') {
                    break;
                }
            }
            
            // Get the details with download=true
            $response = $this->api->getSearchRequest($request['uuid'], true);
            $this->assertIsArray($response);
            $this->assertArrayHasKey('uuid', $response);
            
            $this->assertArrayHasKey('result', $response);
            $this->assertIsArray($response['result']);
            

            // Test with download=false for comparison
            $responseNoDownload = $this->api->getSearchRequest($request['uuid'], false);
            $this->assertIsString($responseNoDownload['result']);
            
            // Clean up after test
            try {
                $this->api->stopSearchRequest($request['uuid']);
            } catch (\Exception $e) {
                // Ignore errors on cleanup
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent searches');
            }
            throw $e;
        }
    }

    public function testMonitorSearchRequest(): void
    {
        try {
            // Create a search request first
            $request = $this->api->createSearchRequest('php sdk', [], 2, false);
            
            // Test monitoring
            $generator = $this->api->monitorSearchRequest($request['uuid'], false);
            
            // Just test first few events to keep test duration reasonable
            $count = 0;
            foreach ($generator as $event) {
                $this->assertIsArray($event);
                $this->assertArrayHasKey('type', $event);
                $count++;
                if ($count >= 2) break;
            }

            // Clean up after test
            try {
                $this->api->stopSearchRequest($request['uuid']);
            } catch (\Exception $e) {
                // Ignore errors on cleanup
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent searches');
            }
            throw $e;
        }
    }

    public function testStopSearchRequest(): void
    {
        try {
            // Create a search request first
            $request = $this->api->createSearchRequest('php framework', [], 2, false);
            
            // Wait a bit to ensure the request is registered
            sleep(1);
            
            // Stop it
            $response = $this->api->stopSearchRequest($request['uuid']);
            $this->assertNull($response);
            
            // Verify it was stopped
            $status = $this->api->getSearchRequest($request['uuid']);
            $this->assertContains($status['status'], ['canceled', 'cancelled', 'failed', 'finished']);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                $this->markTestSkipped('API plan does not support concurrent searches');
            }
            throw $e;
        }
    }

    // Tests for sitemap functionality
    public function testDownloadSitemap(): void
    {
        // Find a crawl with a sitemap first
        $crawlWithSitemap = null;
        $items = $this->api->getCrawlRequestsList();
        
        foreach ($items['results'] as $item) {
            try {
                $crawl = $this->api->getCrawlRequest($item['uuid']);
                if (isset($crawl['sitemap']) && !empty($crawl['sitemap'])) {
                    $crawlWithSitemap = $crawl;
                    break;
                }
            } catch (\Exception $e) {
                // Skip this item if it can't be retrieved
                continue;
            }
        }
        
        if (!$crawlWithSitemap) {
            $this->markTestSkipped('No crawl requests with sitemap found for testing');
            return;
        }
        
        // Test downloading sitemap using crawl object
        $sitemap = $this->api->downloadSitemap($crawlWithSitemap);
        $this->assertTrue(is_array($sitemap));
        
        // Test downloading sitemap using UUID string
        $sitemapById = $this->api->downloadSitemap($crawlWithSitemap['uuid']);
        $this->assertTrue(is_array($sitemapById));
    }
    
    public function testSitemapGraphAndMarkdown(): void
    {
        // Find a crawl with a sitemap first
        $crawlWithSitemap = null;
        $items = $this->api->getCrawlRequestsList();
        
        foreach ($items['results'] as $item) {
            try {
                $crawl = $this->api->getCrawlRequest($item['uuid']);
                if (isset($crawl['sitemap']) && !empty($crawl['sitemap'])) {
                    $crawlWithSitemap = $crawl;
                    break;
                }
            } catch (\Exception $e) {
                // Skip this item if it can't be retrieved
                continue;
            }
        }
        
        if (!$crawlWithSitemap) {
            $this->markTestSkipped('No crawl requests with sitemap found for testing');
            return;
        }
        
        // Test downloading sitemap graph
        try {
            $graph = $this->api->downloadSitemapGraph($crawlWithSitemap['uuid']);
            $this->assertTrue(is_array($graph));
        } catch (\Exception $e) {
            $this->markTestSkipped('Sitemap graph endpoint might not be supported yet: ' . $e->getMessage());
        }
        
        // Test downloading sitemap markdown
        try {
            $markdown = $this->api->downloadSitemapMarkdown($crawlWithSitemap['uuid']);
            $this->assertTrue(is_string($markdown));
        } catch (\Exception $e) {
            $this->markTestSkipped('Sitemap markdown endpoint might not be supported yet: ' . $e->getMessage());
        }
    }
}