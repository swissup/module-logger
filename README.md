# module-logger

PSR-3 compliant PHP logging library with browser console and CLI output support.
Designed for use in Magento 2 modules but has no Magento dependency.

## Features

- **BrowserConsoleLogger** — outputs logs to browser DevTools console via injected `<script>` tags
  - Colored output by log level
  - Grouped logs with nesting
  - Global `window.plog` storage for in-browser debugging
  - Memory usage tracking per log entry
- **CliLogger** — outputs logs to terminal
  - ANSI colored output with auto-detection
  - Proper STDOUT/STDERR routing (errors → STDERR)
  - Grouped logs with indentation
  - Immediate or buffered output modes
- **LoggerSingleton** — keyed singleton, one logger instance per debug constant
  - Auto-selects CLI or Browser logger based on `PHP_SAPI`
  - Returns `NullLogger` when debug mode is off (zero overhead in production)

## Installation

```bash
composer require swissup/module-logger
```

## Usage

### LoggerSingleton (recommended)

Enable debug mode by defining a constant before Magento bootstrap:

```php
// in pub/index.php or app/etc/env.php
define('MY_MODULE_DEBUG', true);
```

Then use anywhere in your module:

```php
use Swissup\Logger\Logger\LoggerSingleton;

$logger = LoggerSingleton::getInstance('MY_MODULE_DEBUG', '[MyModule]');

$logger->info('Processing started');
$logger->debug('Item loaded', ['id' => 42]);
$logger->warning('Cache miss', ['key' => 'product_1']);
$logger->error('Failed to connect', ['host' => 'localhost']);
```

When `MY_MODULE_DEBUG` is not defined or not `true`, `getInstance()` returns `NullLogger` — no overhead.

### PSR-3 wrapper in your module

```php
use Psr\Log\AbstractLogger;
use Swissup\Logger\Logger\LoggerSingleton;

class MyLogger extends AbstractLogger
{
    #[\ReturnTypeWillChange]
    public function log($level, $message, array $context = []): void
    {
        LoggerSingleton::getInstance('MY_MODULE_DEBUG', '[MyModule]')->log($level, $message, $context);
    }
}
```

Then bind it in `di.xml`:

```xml
<preference for="Psr\Log\LoggerInterface" type="MyVendor\MyModule\Logger\MyLogger"/>
```

### BrowserConsoleLogger

Outputs logs as JavaScript to the browser console. Call `flush()` to get the `<script>` block and inject it into your HTML response.

```php
use Swissup\Logger\Logger\BrowserConsoleLogger;

$logger = new BrowserConsoleLogger(enableGlobalStorage: true, prefix: '[MyModule]');

$logger->group('Processing request');
$logger->info('Step 1 complete');
$logger->debug('Data loaded', ['rows' => 150]);
$logger->warning('Slow query', ['ms' => 320]);
$logger->groupEnd();

// Inject into response HTML
echo $logger->flush();
```

Browser output example:

```
▼ [MyModule] Processing request
  14:05:59.123 INFO  Step 1 complete
  14:05:59.145 DEBUG Data loaded {rows: 150}
  14:05:59.201 WARN  Slow query {ms: 320}
```

Access all logs in DevTools console:

```js
window.plog         // keyed by timestamp
window.plog._entries // ordered array
```

### CliLogger

Outputs logs to terminal. Immediate output by default.

```php
use Swissup\Logger\Logger\CliLogger;

$logger = new CliLogger(prefix: '[MyModule]');

$logger->group('Processing');
$logger->debug('Step 1', ['size' => 1024]);
$logger->warning('Patch failed', ['nodeId' => 123]);
$logger->groupEnd();
```

Terminal output:

```
┌─ [MyModule] Processing
│ 14:05:59.123 DEBUG   Step 1 {"size":1024}
│ 14:05:59.145 WARNING Patch failed {"nodeId":123}
└─
```

Buffered mode (accumulate then flush):

```php
$logger = new CliLogger(prefix: '[MyModule]', immediateOutput: false);
$logger->info('Step 1');
$logger->info('Step 2');
$output = $logger->flush(); // write all at once
```

## Log Levels

All PSR-3 log levels are supported:

| Level       | CLI color     | Browser color  | Console method |
|-------------|---------------|----------------|----------------|
| `emergency` | Red bg        | `#8B0000`      | `console.error` |
| `alert`     | Magenta bold  | `#DC143C`      | `console.error` |
| `critical`  | Red bold      | `#FF0000`      | `console.error` |
| `error`     | Red           | `#FF4500`      | `console.error` |
| `warning`   | Yellow        | `#FFA500`      | `console.warn`  |
| `notice`    | Cyan          | `#4169E1`      | `console.info`  |
| `info`      | Green         | `#008000`      | `console.info`  |
| `debug`     | Gray          | `#808080`      | `console.debug` |

## Message Interpolation

Context values are interpolated into `{placeholder}` patterns in messages:

```php
$logger->info('User {id} logged in from {ip}', ['id' => 42, 'ip' => '127.0.0.1']);
// → "User 42 logged in from 127.0.0.1"
```

## Context Sanitization

Context arrays are automatically sanitized before JSON encoding:

- **Resources** → `"resource(stream)"`
- **Exceptions/Throwable** → `{_type, class, message, code, file, line}`
- **Objects with `__toString`** → string value
- **JsonSerializable objects** → JSON serialized
- **Other objects** → `"object(ClassName)"`
- **Nested arrays** → recursively sanitized
