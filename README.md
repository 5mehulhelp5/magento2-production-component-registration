# Magento 2 Performance Optimizer for Component Discovery

[![Latest Stable Version](https://poser.pugx.org/hryvinskyi/magento2-production-component-registration/v/stable)](https://packagist.org/packages/hryvinskyi/magento2-production-component-registration)
[![License](https://poser.pugx.org/hryvinskyi/magento2-production-component-registration/license)](https://packagist.org/packages/hryvinskyi/magento2-production-component-registration)

Boost your Magento 2 application startup time by replacing expensive filesystem operations with pre-compiled component mappings in production environments.

## Background

Magento 2 applications experience significant startup delays due to an inefficient component discovery mechanism that executes on every request.

### Performance Bottleneck Analysis

During application initialization, Magento executes filesystem scanning operations through `NonComposerComponentRegistration.php`. This process involves:

- Dynamic pattern matching across multiple directory structures
- Recursive file system traversal using `glob()` functions
- Real-time loading and execution of discovered registration files

The initialization sequence follows this pattern:

```php
$patterns = require __DIR__ . '/registration_globlist.php';
foreach ($patterns as $pattern) {
    $matches = glob($baseDir . $pattern, GLOB_NOSORT);
    array_map(static function ($path) {
        require_once $path;
    }, $matches);
}
```

### Impact on Application Performance

This approach creates measurable delays because:

- File system operations block request processing
- Pattern matching scales poorly with project size
- No optimization exists for repeated operations
- Both web requests and CLI operations suffer equally

### Common Scenarios Affected

Applications with extensive customizations experience the most impact:

- Large module collections in `app/code` directories
- Multiple custom themes in `app/design`
- Legacy components in `lib/internal`
- Frequent CLI command execution
- Automated deployment processes

## Optimization Strategy

This package implements environment-aware component registration that switches between development flexibility and production performance.

### Adaptive Registration Mechanism

The system operates in two distinct modes:

**Development Environment**: Maintains standard Magento behavior for maximum development flexibility

**Production Environment**: Utilizes pre-generated static registration files that eliminate filesystem scanning

The production optimization creates files structured like:

```php
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register('module', 'CustomVendor_ExtensionA', '/app/code/CustomVendor/ExtensionA');
ComponentRegistrar::register('module', 'CustomVendor_ExtensionB', '/app/code/CustomVendor/ExtensionB');
ComponentRegistrar::register('theme', 'frontend/Custom/design', '/app/design/frontend/Custom/design');
// Additional components registered statically
```

## Setup Instructions

Install the package via Composer:

```bash
composer require hryvinskyi/magento2-production-component-registration
```

## Configuration

The package supports optional configuration to filter disabled modules based on your Magento 2 `app/etc/config.php` file.

### Module Filtering

By default, the plugin includes all discovered components. To enable filtering of disabled modules, add the following configuration to your project's `composer.json`:

```json
{
    "extra": {
        "hryvinskyi-production-component-registration": {
            "filter-disabled-modules": true
        }
    }
}
```

When enabled, the plugin will:
- Read your `app/etc/config.php` file
- Only include modules that are enabled (value = 1)
- Skip disabled modules (value = 0) from the generated registration file
- Include all non-module components (themes, libraries, etc.) regardless of their status

This provides additional performance benefits by avoiding registration of disabled modules entirely.

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `filter-disabled-modules` | boolean | `false` | When `true`, only enabled modules from `config.php` will be included in the generated registration file |

## Environment Recognition

The package automatically identifies production environments through multiple detection methods:

1. **Magento Configuration Priority**
   ```php
   // app/etc/env.php
   return [
       'MAGE_MODE' => 'production'
   ];
   ```

2. **System Environment Variables**
   - `MAGE_MODE=production`
   - `MAGENTO_MODE=production`

## Operation

The package functions transparently without requiring configuration changes.

### Force Regeneration

To manually rebuild registration files:

```bash
composer dump-autoload
```

### Safety Features

Built-in protection mechanisms include:
- Automatic backup creation before modifications
- Graceful fallback to standard registration
- Environment-specific file restoration
- Validation of generated registration data

## System Requirements

- PHP 7.4+
- Composer 2.0 or newer

## License

Released under MIT License. Full license text available in [LICENSE](LICENSE) file.

## Development

We welcome community contributions. Please submit pull requests for improvements and bug fixes.

## Support

For technical issues or questions, please [create an issue](https://github.com/hryvinskyi/magento2-production-component-registration/issues) in the project repository.
