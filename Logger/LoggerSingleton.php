<?php
declare(strict_types=1);

namespace Swissup\Logger\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Logger Singleton
 *
 * Keyed singleton — one instance per debug constant.
 * Each module gets its own isolated logger instance.
 *
 * Usage:
 * ```php
 * // In Pagespeed:
 * $logger = LoggerSingleton::getInstance('PAGESPEED_DEBUG', '[Pagespeed]');
 *
 * // In BTE:
 * $logger = LoggerSingleton::getInstance('BTE_DEBUG', '[BTE]');
 * ```
 *
 * Enable debug mode by defining the constant before bootstrap:
 * ```php
 * // in pub/index.php or app/etc/env.php
 * define('PAGESPEED_DEBUG', true);
 * ```
 */
class LoggerSingleton
{
    /**
     * Logger instances keyed by debug constant name
     *
     * @var array<string, LoggerInterface>
     */
    private static array $instances = [];

    /**
     * Get logger instance for the given debug constant (creates on first call)
     *
     * @param string $debugConstant Name of the PHP constant that enables debug mode
     * @param string $prefix Log message prefix shown in console/terminal
     * @return LoggerInterface
     */
    public static function getInstance(string $debugConstant, string $prefix): LoggerInterface
    {
        if (!isset(self::$instances[$debugConstant])) {
            self::$instances[$debugConstant] = self::createLogger($debugConstant, $prefix);
        }

        return self::$instances[$debugConstant];
    }

    /**
     * Create logger based on environment and debug mode
     *
     * @param string $debugConstant
     * @param string $prefix
     * @return LoggerInterface
     */
    private static function createLogger(string $debugConstant, string $prefix): LoggerInterface
    {
        if (!self::isDebugEnabled($debugConstant)) {
            return new NullLogger();
        }

        if (PHP_SAPI === 'cli') {
            return new CliLogger($prefix, true);
        }

        return new BrowserConsoleLogger(true, $prefix);
    }

    /**
     * Check if debug mode is enabled for the given constant
     *
     * @param string $debugConstant
     * @return bool
     */
    public static function isDebugEnabled(string $debugConstant): bool
    {
        return defined($debugConstant) && constant($debugConstant) === true;
    }

    /**
     * Check if logger is initialized for the given constant
     *
     * @param string $debugConstant
     * @return bool
     */
    public static function isInitialized(string $debugConstant): bool
    {
        return isset(self::$instances[$debugConstant]);
    }

    /**
     * Reset singleton instance(s) — for testing only
     *
     * @param string|null $debugConstant Reset specific instance, or all if null
     * @return void
     */
    public static function reset(?string $debugConstant = null): void
    {
        if ($debugConstant === null) {
            self::$instances = [];
        } else {
            unset(self::$instances[$debugConstant]);
        }
    }

    /**
     * Get current logger type for the given constant (for debugging/testing)
     *
     * @param string $debugConstant
     * @return string Logger class name or 'none' if not initialized
     */
    public static function getLoggerType(string $debugConstant): string
    {
        if (!isset(self::$instances[$debugConstant])) {
            return 'none';
        }

        return get_class(self::$instances[$debugConstant]);
    }
}
