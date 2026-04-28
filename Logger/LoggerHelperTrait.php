<?php
declare(strict_types=1);

namespace Swissup\Logger\Logger;

/**
 * Common logging utilities for interpolating messages and sanitizing context
 *
 * Shared by BrowserConsoleLogger and CliLogger to avoid code duplication
 */
trait LoggerHelperTrait
{
    /**
     * JSON encoding options
     * Can be customized by implementing class
     *
     * @var int
     */
    private int $serializeOptions = JSON_PARTIAL_OUTPUT_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE;

    /**
     * Set JSON encoding options
     *
     * @param int $options JSON encoding flags
     * @return void
     */
    protected function setSerializeOptions(int $options): void
    {
        $this->serializeOptions = $options;
    }

    /**
     * Interpolate context values into message placeholders
     *
     * Replaces {key} in message with corresponding context values
     *
     * @param string $message Message with {placeholders}
     * @param array $context Context values
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Sanitize context for JSON encoding
     *
     * Handles resources, exceptions, objects, and arrays recursively
     *
     * @param array $context
     * @return array
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if (is_resource($value)) {
                $sanitized[$key] = sprintf('resource(%s)', get_resource_type($value));
            } elseif ($value instanceof \Throwable) {
                $sanitized[$key] = [
                    '_type' => 'exception',
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $sanitized[$key] = (string)$value;
                } elseif ($value instanceof \JsonSerializable) {
                    $sanitized[$key] = $value;
                } else {
                    $sanitized[$key] = sprintf('object(%s)', get_class($value));
                }
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Serialize value to JSON string
     *
     * Uses $serializeOptions property which can be customized
     * via setSerializeOptions() by implementing class
     *
     * @param mixed $value
     * @return string
     */
    private function serialize($value): string
    {
        $json = json_encode($value, $this->serializeOptions);

        if ($json === false) {
            return '{"_error": "JSON encoding failed"}';
        }

        return $json;
    }
}
