<?php
declare(strict_types=1);

namespace Swissup\Logger\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Browser Console Logger
 *
 * PSR-3 compliant logger that outputs to browser console via JavaScript.
 *
 * Features:
 * - Colored output by log level
 * - Structured data logging
 * - Performance metrics
 * - Nested grouped logs
 * - Global window.plog storage for debugging
 *
 * Usage:
 * ```php
 * $logger = new BrowserConsoleLogger(true, '[MyModule]');
 * $logger->group('Processing');
 * $logger->debug('Step 1', ['size' => 1024]);
 * $logger->warning('Patch failed', ['nodeId' => 123]);
 * $logger->groupEnd();
 * $html .= $logger->flush();
 * ```
 */
class BrowserConsoleLogger extends AbstractLogger
{
    use LoggerHelperTrait;

    /**
     * Log level colors for console
     */
    private const LEVEL_COLORS = [
        LogLevel::EMERGENCY => '#8B0000', // Dark red
        LogLevel::ALERT     => '#DC143C', // Crimson
        LogLevel::CRITICAL  => '#FF0000', // Red
        LogLevel::ERROR     => '#FF4500', // Orange red
        LogLevel::WARNING   => '#FFA500', // Orange
        LogLevel::NOTICE    => '#4169E1', // Royal blue
        LogLevel::INFO      => '#008000', // Green
        LogLevel::DEBUG     => '#808080', // Gray
    ];

    /**
     * Console methods mapping
     */
    private const LEVEL_METHODS = [
        LogLevel::EMERGENCY => 'error',
        LogLevel::ALERT     => 'error',
        LogLevel::CRITICAL  => 'error',
        LogLevel::ERROR     => 'error',
        LogLevel::WARNING   => 'warn',
        LogLevel::NOTICE    => 'info',
        LogLevel::INFO      => 'info',
        LogLevel::DEBUG     => 'debug',
    ];

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
     * Whether to enable global window.plog storage
     */
    private bool $enableGlobalStorage = true;

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
     * @param bool $enableGlobalStorage Store logs in window.plog
     * @param string $prefix Log message prefix
     */
    public function __construct(bool $enableGlobalStorage = true, string $prefix = '')
    {
        $this->enableGlobalStorage = $enableGlobalStorage;
        $this->prefix = $prefix;
        $this->setSerializeOptions(
            JSON_PARTIAL_OUTPUT_ON_ERROR
            | JSON_HEX_TAG        // Escape < and >
            | JSON_HEX_APOS       // Escape '
            | JSON_HEX_AMP        // Escape &
            | JSON_HEX_QUOT       // Escape "
            | JSON_UNESCAPED_UNICODE
        );
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

        $this->logs[] = $logEntry;
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

        $this->logs[] = [
            'timestamp' => microtime(true),
            'level' => self::LEVEL_GROUP_START,
            'message' => $title,
            'context' => [],
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
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

        $this->logs[] = [
            'timestamp' => microtime(true),
            'level' => self::LEVEL_GROUP_END,
            'message' => '',
            'context' => [],
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Output all accumulated logs to browser console
     *
     * @return string JavaScript code
     */
    public function flush(): string
    {
        if (empty($this->logs)) {
            return '';
        }

        $scripts = [];

        // Initialize window.plog if enabled
        if ($this->enableGlobalStorage) {
            $scripts[] = "if (!window.plog) { window.plog = {}; window.plog._entries = []; }";
        }

        foreach ($this->logs as $log) {
            // Handle group start
            if ($log['level'] === self::LEVEL_GROUP_START) {
                $groupTitle = $this->escapeJs(($this->prefix ? $this->prefix . ' ' : '') . $log['message']);
                $scripts[] = "console.group('{$groupTitle}');";
                continue;
            }

            // Handle group end
            if ($log['level'] === self::LEVEL_GROUP_END) {
                $scripts[] = "console.groupEnd();";
                continue;
            }

            // Normal log entry
            $scripts[] = $this->generateLogScript($log);
        }

        // Close any unclosed groups
        while ($this->groupNestingLevel > 0) {
            $scripts[] = "console.groupEnd();";
            $this->groupNestingLevel--;
        }

        // Clear logs
        $this->logs = [];

        return '<script>' . implode("\n", $scripts) . '</script>';
    }

    /**
     * Generate JavaScript for single log entry
     *
     * @param array $log
     * @return string
     */
    private function generateLogScript(array $log): string
    {
        $level = $log['level'];
        $method = self::LEVEL_METHODS[$level] ?? 'log';
        $color = self::LEVEL_COLORS[$level] ?? '#000000';

        $timestamp = date('H:i:s', (int)$log['timestamp']) .
                     '.' .
                     str_pad((string)round(($log['timestamp'] - floor($log['timestamp'])) * 1000), 3, '0', STR_PAD_LEFT);

        // Format message with color
        $styledPrefix = "color: {$color}; font-weight: bold;";
        $resetStyle = "color: inherit; font-weight: normal;";

        $message = $this->escapeJs($log['message']);

        $scripts = [];

        // Console output with context
        if (!empty($log['context'])) {
            $contextJson = $this->serialize($log['context']);
            $scripts[] = "console.{$method}('%c{$timestamp}%c {$message}', '{$styledPrefix}', '{$resetStyle}', {$contextJson});";
        } else {
            $scripts[] = "console.{$method}('%c{$timestamp}%c {$message}', '{$styledPrefix}', '{$resetStyle}');";
        }

        // Store in window.plog if enabled
        if ($this->enableGlobalStorage) {
            $logKey = str_replace('.', '_', (string)$log['timestamp']);
            $logDataJson = $this->serialize([
                'timestamp' => $log['timestamp'],
                'time' => $timestamp,
                'level' => $level,
                'message' => $log['message'],
                'context' => $log['context'],
                'memory_mb' => round($log['memory'] / 1024 / 1024, 2),
                'peak_memory_mb' => round($log['peak_memory'] / 1024 / 1024, 2),
            ]);
            $scripts[] = "window.plog['{$logKey}'] = {$logDataJson}; window.plog._entries.push(window.plog['{$logKey}']);";
        }

        return implode("\n", $scripts);
    }

    /**
     * Escape string for JavaScript
     *
     * @param string $string
     * @return string
     */
    private function escapeJs(string $string): string
    {
        $string = str_replace(['\\', "'", "\n", "\r", "\t"], ['\\\\', "\\'", '\\n', '\\r', '\\t'], $string);
        return $string;
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
