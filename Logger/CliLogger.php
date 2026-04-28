<?php
declare(strict_types=1);

namespace Swissup\Logger\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * CLI Console Logger
 *
 * PSR-3 compliant logger that outputs to terminal/console.
 *
 * Features:
 * - Colored output by log level (with auto-detection)
 * - Structured data logging
 * - Performance metrics
 * - Nested grouped logs with indentation
 * - Proper STDOUT/STDERR routing
 *
 * Usage:
 * ```php
 * $logger = new CliLogger('[MyModule]');
 * $logger->group('Processing');
 * $logger->debug('Step 1', ['size' => 1024]);
 * $logger->warning('Patch failed', ['nodeId' => 123]);
 * $logger->groupEnd();
 * ```
 *
 * Terminal output:
 * ```
 * ┌─ [MyModule] Processing
 * │ 14:05:59.123 DEBUG Step 1 {"size":1024}
 * │ 14:05:59.145 WARNING Patch failed {"nodeId":123}
 * └─
 * ```
 */
class CliLogger extends AbstractLogger
{
    use LoggerHelperTrait;

    /**
     * ANSI color codes for log levels
     */
    private const LEVEL_COLORS = [
        LogLevel::EMERGENCY => "\033[1;41m", // Red background
        LogLevel::ALERT     => "\033[1;35m", // Magenta bold
        LogLevel::CRITICAL  => "\033[1;31m", // Red bold
        LogLevel::ERROR     => "\033[0;31m", // Red
        LogLevel::WARNING   => "\033[0;33m", // Yellow
        LogLevel::NOTICE    => "\033[0;36m", // Cyan
        LogLevel::INFO      => "\033[0;32m", // Green
        LogLevel::DEBUG     => "\033[0;37m", // Gray
    ];

    /**
     * ANSI reset code
     */
    private const RESET = "\033[0m";

    /**
     * Special log levels for group control
     */
    private const LEVEL_GROUP_START = '_group_start';
    private const LEVEL_GROUP_END = '_group_end';

    /**
     * Accumulated logs for batch output
     */
    private array $logs = [];

    /**
     * Whether to enable colored output
     */
    private bool $enableColors = true;

    /**
     * Log prefix
     */
    private string $prefix = '';

    /**
     * Whether to group related logs
     */
    private bool $enableGrouping = true;

    /**
     * Current group nesting level
     */
    private int $groupNestingLevel = 0;

    /**
     * Whether to output immediately or accumulate
     */
    private bool $immediateOutput = true;

    /**
     * @param string $prefix Log message prefix
     * @param bool|null $enableColors Enable ANSI colors (auto-detects if terminal supports it)
     * @param bool $immediateOutput Output logs immediately (true) or accumulate for flush (false)
     */
    public function __construct(
        string $prefix = '',
        ?bool $enableColors = null,
        bool $immediateOutput = true
    ) {
        $this->prefix = $prefix;
        $this->enableColors = $enableColors ?? $this->isColorSupported();
        $this->immediateOutput = $immediateOutput;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function log($level, $message, array $context = []): void
    {
        $timestamp = microtime(true);

        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $this->interpolate((string)$message, $context),
            'context' => $this->sanitizeContext($context),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        if ($this->immediateOutput) {
            $this->outputLog($logEntry);
        } else {
            $this->logs[] = $logEntry;
        }
    }

    /**
     * Start a log group
     *
     * @param string $title Group title
     * @return void
     */
    public function group(string $title): void
    {
        if (!$this->enableGrouping) {
            return;
        }

        $this->groupNestingLevel++;

        $logEntry = [
            'timestamp' => microtime(true),
            'level' => self::LEVEL_GROUP_START,
            'message' => $title,
            'context' => [],
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        if ($this->immediateOutput) {
            $this->outputLog($logEntry);
        } else {
            $this->logs[] = $logEntry;
        }
    }

    /**
     * End current log group
     *
     * @return void
     */
    public function groupEnd(): void
    {
        if (!$this->enableGrouping || $this->groupNestingLevel === 0) {
            return;
        }

        $this->groupNestingLevel--;

        $logEntry = [
            'timestamp' => microtime(true),
            'level' => self::LEVEL_GROUP_END,
            'message' => '',
            'context' => [],
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        if ($this->immediateOutput) {
            $this->outputLog($logEntry);
        } else {
            $this->logs[] = $logEntry;
        }
    }

    /**
     * Output all accumulated logs to console
     *
     * @return string Combined output (for testing)
     */
    public function flush(): string
    {
        if (empty($this->logs)) {
            return '';
        }

        $output = [];

        foreach ($this->logs as $log) {
            $output[] = $this->formatLog($log);
            $this->writeLog($log);
        }

        // Close any unclosed groups
        while ($this->groupNestingLevel > 0) {
            $groupEndLog = [
                'timestamp' => microtime(true),
                'level' => self::LEVEL_GROUP_END,
                'message' => '',
                'context' => [],
            ];
            $output[] = $this->formatLog($groupEndLog);
            $this->writeLog($groupEndLog);
            $this->groupNestingLevel--;
        }

        // Clear logs
        $this->logs = [];

        return implode('', $output);
    }

    /**
     * Output single log entry immediately
     *
     * @param array $log
     * @return void
     */
    private function outputLog(array $log): void
    {
        $this->writeLog($log);
    }

    /**
     * Write log to appropriate stream
     *
     * @param array $log
     * @return void
     */
    private function writeLog(array $log): void
    {
        $formattedLog = $this->formatLog($log);

        // Route errors to STDERR, everything else to STDOUT
        $stream = in_array($log['level'], [
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY
        ], true) ? STDERR : STDOUT;

        fwrite($stream, $formattedLog);
    }

    /**
     * Format log entry for output
     *
     * @param array $log
     * @return string
     */
    private function formatLog(array $log): string
    {
        $level = $log['level'];

        // Handle group start
        if ($level === self::LEVEL_GROUP_START) {
            $indent = str_repeat('  ', max(0, $this->groupNestingLevel - 1));
            $groupTitle = ($this->prefix ? $this->prefix . ' ' : '') . $log['message'];

            if ($this->enableColors) {
                $groupTitle = "\033[1;34m{$groupTitle}\033[0m"; // Bold blue
            }

            return "{$indent}┌─ {$groupTitle}\n";
        }

        // Handle group end
        if ($level === self::LEVEL_GROUP_END) {
            $indent = str_repeat('  ', $this->groupNestingLevel);
            return "{$indent}└─\n";
        }

        // Format timestamp
        $timestamp = date('H:i:s', (int)$log['timestamp']) .
                     '.' .
                     str_pad((string)round(($log['timestamp'] - floor($log['timestamp'])) * 1000), 3, '0', STR_PAD_LEFT);

        // Format level
        $levelStr = strtoupper($level);

        if ($this->enableColors && isset(self::LEVEL_COLORS[$level])) {
            $levelStr = self::LEVEL_COLORS[$level] . $levelStr . self::RESET;
        }

        // Format message
        $message = $log['message'];

        // Format context
        $contextStr = '';
        if (!empty($log['context'])) {
            $contextStr = ' ' . $this->serialize($log['context']);
        }

        // Apply indentation for grouped logs
        $indent = str_repeat('  ', $this->groupNestingLevel);
        $groupPrefix = $this->groupNestingLevel > 0 ? '│ ' : '';

        return "{$indent}{$groupPrefix}{$timestamp} {$levelStr} {$message}{$contextStr}\n";
    }

    /**
     * Detect if terminal supports colors
     *
     * @return bool
     */
    private function isColorSupported(): bool
    {
        // Check if we're in CLI mode
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        // Windows color support check
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM_PROGRAM') === 'vscode'
                || getenv('TERM') !== false;
        }

        // Unix-like systems
        if (function_exists('posix_isatty')) {
            if (defined('STDOUT') && is_resource(STDOUT)) {
                return posix_isatty(STDOUT);
            }
        }

        // Fallback: check TERM variable
        return getenv('TERM') !== false;
    }

    /**
     * Get all logs (for testing)
     *
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Clear all logs
     *
     * @return void
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->groupNestingLevel = 0;
    }

    /**
     * Get current group nesting level
     *
     * @return int
     */
    public function getGroupNestingLevel(): int
    {
        return $this->groupNestingLevel;
    }
}
