# WaterCrawl PHP SDK

PHP SDK for WaterCrawl REST APIs

## Installation

You can install the package via composer:

```bash
composer require watercrawl/php
```

## Usage

```php
$client = new WaterCrawl\APIClient('your-api-key');

// Create a crawl request
$result = $client->createCrawlRequest('https://example.com');

// Get crawl request results
$results = $client->getCrawlRequestResults($result['uuid']);
```

## Testing

To run the tests, you'll need a WaterCrawl API key. Set it as an environment variable:

```bash
export WATERCRAWL_API_KEY=your-api-key
composer test
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes using conventional commits (`git commit -m 'feat: add some feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Commit Message Format

This repository follows [Conventional Commits](https://www.conventionalcommits.org/). Your commit messages should follow this format:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

Types:
- feat: A new feature
- fix: A bug fix
- docs: Documentation only changes
- style: Changes that do not affect the meaning of the code
- refactor: A code change that neither fixes a bug nor adds a feature
- perf: A code change that improves performance
- test: Adding missing tests or correcting existing tests
- chore: Changes to the build process or auxiliary tools

## Security

If you discover any security related issues, please email security@watercrawl.dev instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
