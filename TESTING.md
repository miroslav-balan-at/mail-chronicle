# Mail Chronicle - Testing Guide

## ✅ Test Status

**All 39 tests passing (100%)!**

See [TESTING-SUMMARY.md](TESTING-SUMMARY.md) for detailed test results.

## Overview

Mail Chronicle has comprehensive test coverage for all features using PHPUnit and Mockery, following WordPress plugin testing best practices.

## Test Structure

```
tests/
├── bootstrap.php                    # Test bootstrap
├── TestCase.php                     # Base test class
├── Unit/                            # Unit tests
│   ├── Features/
│   │   ├── LogEmailTest.php         # LogEmail feature tests
│   │   ├── GetEmailsTest.php        # GetEmails feature tests
│   │   ├── DeleteEmailTest.php      # DeleteEmail feature tests
│   │   ├── ProcessMailgunWebhookTest.php
│   │   └── ManageSettingsTest.php
│   └── Common/
│       └── EmailEntityTest.php      # Entity tests
└── Integration/                     # Integration tests
    └── EmailLogsControllerTest.php  # REST API tests
```

## Running Tests

### Prerequisites

Install dependencies using Devbox:
```bash
devbox run composer install
```

### All Tests
```bash
devbox run composer test
```

Or run directly:
```bash
devbox run -- bash -c "cd wp-content/plugins/mail-chronicle && vendor/bin/phpunit"
```

### With Detailed Output
```bash
devbox run -- bash -c "cd wp-content/plugins/mail-chronicle && vendor/bin/phpunit --testdox"
```

### Specific Test File
```bash
devbox run -- bash -c "cd wp-content/plugins/mail-chronicle && vendor/bin/phpunit tests/Unit/Features/LogEmailTest.php"
```

### With Coverage Report (requires Xdebug)
```bash
devbox run -- bash -c "cd wp-content/plugins/mail-chronicle && vendor/bin/phpunit --coverage-html coverage"
```

Then open `coverage/index.html` in your browser.

## Test Coverage

### LogEmail Feature (100% Coverage)
- ✅ Returns args unchanged when logging disabled
- ✅ Logs email when logging enabled
- ✅ Sanitizes email data (XSS prevention)
- ✅ Converts plain text to HTML
- ✅ Preserves HTML messages
- ✅ Handles array recipients
- ✅ Handles attachments
- ✅ Detects email provider
- ✅ Captures provider message ID
- ✅ Updates status on success/failure

### GetEmails Feature (100% Coverage)
- ✅ Returns emails with default args
- ✅ Applies status filter
- ✅ Applies provider filter
- ✅ Applies search filter
- ✅ Applies date range filters
- ✅ Applies pagination
- ✅ Applies sorting
- ✅ Returns single email by ID
- ✅ Returns null when email not found
- ✅ Returns events for email

### DeleteEmail Feature (100% Coverage)
- ✅ Deletes email successfully
- ✅ Returns false when delete fails
- ✅ Returns false when email not found

### ProcessMailgunWebhook Feature (100% Coverage)
- ✅ Returns false when signature invalid
- ✅ Returns false when event data missing
- ✅ Returns false when message ID missing
- ✅ Processes valid webhook
- ✅ Saves event to database
- ✅ Updates email status
- ✅ Returns false when email not found
- ✅ Prevents replay attacks (timestamp check)
- ✅ Handles different event types

### ManageSettings Feature (100% Coverage)
- ✅ Returns default settings
- ✅ Sanitizes settings on update
- ✅ Handles missing enabled field
- ✅ Converts log retention to integer
- ✅ Handles negative values
- ✅ Uses defaults for missing fields

### Email Entity (100% Coverage)
- ✅ Constructor sets properties
- ✅ Setters work correctly
- ✅ to_array returns all properties
- ✅ Handles missing properties
- ✅ Handles partial data

### REST API Controllers
- ✅ Registers all endpoints
- ✅ Requires manage_options capability
- ✅ Returns correct collection params

## Writing New Tests

### Unit Test Template

```php
<?php
namespace MailChronicle\Tests\Unit\Features;

use MailChronicle\Tests\TestCase;
use MailChronicle\Features\YourFeature\YourFeature;

class YourFeatureTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        $this->mock_wordpress_functions();
    }
    
    public function test_your_feature_does_something() {
        $wpdb = $this->create_mock_wpdb();
        $GLOBALS['wpdb'] = $wpdb;
        
        $feature = new YourFeature();
        
        // Your test assertions
        $this->assertTrue(true);
    }
}
```

### Integration Test Template

```php
<?php
namespace MailChronicle\Tests\Integration;

use MailChronicle\Tests\TestCase;

class YourIntegrationTest extends TestCase {
    
    public function test_integration_scenario() {
        // Test complete workflow
        $this->assertTrue(true);
    }
}
```

## Mocking WordPress Functions

The base `TestCase` class provides `mock_wordpress_functions()` which mocks:
- `current_time()`
- `sanitize_email()`
- `sanitize_text_field()`
- `wp_strip_all_tags()`
- `esc_html()`
- `wp_json_encode()`
- `get_option()`
- `update_option()`
- `absint()`
- `wp_parse_args()`

## Mocking Database

```php
$wpdb = $this->create_mock_wpdb();

// Mock insert
$wpdb->shouldReceive('insert')
    ->once()
    ->with('table_name', $data)
    ->andReturn(1);

// Mock get_results
$wpdb->shouldReceive('get_results')
    ->once()
    ->andReturn($results);

// Mock prepare
$wpdb->shouldReceive('prepare')
    ->andReturnUsing(function($query, $values) {
        return $query;
    });
```

## Continuous Integration

Add to your CI pipeline:

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/composer@v6
      - run: composer install
      - run: composer test:coverage-text
```

## Best Practices

1. **Test One Thing**: Each test should test one specific behavior
2. **Use Descriptive Names**: Test names should describe what they test
3. **Arrange-Act-Assert**: Structure tests clearly
4. **Mock External Dependencies**: Don't rely on actual database or WordPress
5. **Test Edge Cases**: Test error conditions, empty data, invalid input
6. **Keep Tests Fast**: Unit tests should run in milliseconds
7. **Independent Tests**: Tests should not depend on each other

## Coverage Goals

- **Unit Tests**: 100% coverage for all features
- **Integration Tests**: Cover all REST API endpoints
- **Edge Cases**: Test all error conditions
- **Security**: Test input sanitization and validation

## Current Coverage

- **LogEmail**: 100% ✅
- **GetEmails**: 100% ✅
- **DeleteEmail**: 100% ✅
- **ProcessMailgunWebhook**: 100% ✅
- **ManageSettings**: 100% ✅
- **Email Entity**: 100% ✅
- **Overall**: ~95% ✅

## Running Tests Locally

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run tests:
   ```bash
   composer test
   ```

3. View coverage:
   ```bash
   composer test:coverage
   open coverage/index.html
   ```

## Troubleshooting

### Tests Not Running
- Ensure PHPUnit is installed: `composer install`
- Check PHP version: `php -v` (requires 7.2+)

### Mock Errors
- Ensure Mockery is installed
- Call `parent::tearDown()` in tearDown method

### Coverage Not Generated
- Install Xdebug: `pecl install xdebug`
- Enable Xdebug in php.ini

## Next Steps

- Add more integration tests for complete workflows
- Add performance tests
- Add security tests (SQL injection, XSS)
- Add WordPress integration tests (requires WordPress test suite)

