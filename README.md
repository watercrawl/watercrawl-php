# WaterCrawl PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/watercrawl/php.svg?style=flat-square)](https://packagist.org/packages/watercrawl/php)
[![Build Status](https://img.shields.io/github/actions/workflow/status/watercrawl/watercrawl-php/release.yml?branch=main&style=flat-square)](https://github.com/watercrawl/watercrawl-php/actions?query=workflow%3ARelease)
[![PHP Version Support](https://img.shields.io/packagist/php-v/watercrawl/php?style=flat-square)](https://packagist.org/packages/watercrawl/php)
[![Total Downloads](https://img.shields.io/packagist/dt/watercrawl/php.svg?style=flat-square)](https://packagist.org/packages/watercrawl/php)
[![License](https://img.shields.io/packagist/l/watercrawl/php.svg?style=flat-square)](https://packagist.org/packages/watercrawl/php)

PHP SDK for WaterCrawl REST APIs. This package provides a simple and elegant way to interact with WaterCrawl's web scraping and crawling services.

## Installation

You can install the package via composer:

```bash
composer require watercrawl/php
```

## Requirements

- PHP 7.4 or higher
- `ext-mbstring`
- `ext-json`

## Usage

```php
use WaterCrawl\APIClient;

// Initialize the client
$client = new APIClient('your-api-key');

// Scrape a single URL
$result = $client->scrapeUrl('https://example.com');

// Create a crawl request
$result = $client->createCrawlRequest(
    'https://example.com',
    ['allowed_domains' => ['example.com']],
    ['wait_for' => '.content'],
    ['extract' => ['title' => 'h1']]
);

// Monitor crawl progress
foreach ($client->monitorCrawlRequest($result['uuid']) as $update) {
    if ($update['type'] === 'result') {
        // Process the result
        print_r($update['data']);
    }
}
```

## Features

- Simple and intuitive API
- Real-time crawl monitoring
- Configurable scraping options
- Automatic response handling
- PHP 7.4+ compatibility
- Proper UTF-8 support

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@watercrawl.dev instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
