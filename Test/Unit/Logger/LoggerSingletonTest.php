<?php
declare(strict_types=1);

namespace Swissup\Logger\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Swissup\Logger\Logger\BrowserConsoleLogger;
use Swissup\Logger\Logger\CliLogger;
use Swissup\Logger\Logger\LoggerSingleton;

class LoggerSingletonTest extends TestCase
{
    protected function tearDown(): void
    {
        LoggerSingleton::reset();
    }

    public function testGetInstanceReturnsSameInstanceForSameConstant(): void
    {
        $a = LoggerSingleton::getInstance('LOGGER_TEST_DEBUG_A', '[A]');
        $b = LoggerSingleton::getInstance('LOGGER_TEST_DEBUG_A', '[A]');

        $this->assertSame($a, $b);
    }

    public function testGetInstanceReturnsDifferentInstancesForDifferentConstants(): void
    {
        $a = LoggerSingleton::getInstance('LOGGER_TEST_DEBUG_A', '[A]');
        $b = LoggerSingleton::getInstance('LOGGER_TEST_DEBUG_B', '[B]');

        $this->assertNotSame($a, $b);
    }

    public function testReturnsNullLoggerWhenConstantNotDefined(): void
    {
        $logger = LoggerSingleton::getInstance('LOGGER_TEST_UNDEFINED_CONST', '[Test]');

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    public function testIsDebugEnabledReturnsFalseWhenConstantNotDefined(): void
    {
        $this->assertFalse(LoggerSingleton::isDebugEnabled('LOGGER_TEST_UNDEFINED_CONST'));
    }

    public function testIsDebugEnabledReturnsFalseWhenConstantIsFalse(): void
    {
        define('LOGGER_TEST_DEBUG_FALSE', false);

        $this->assertFalse(LoggerSingleton::isDebugEnabled('LOGGER_TEST_DEBUG_FALSE'));
    }

    public function testIsDebugEnabledReturnsTrueWhenConstantIsTrue(): void
    {
        define('LOGGER_TEST_DEBUG_TRUE', true);

        $this->assertTrue(LoggerSingleton::isDebugEnabled('LOGGER_TEST_DEBUG_TRUE'));
    }

    public function testIsInitializedReturnsFalseBeforeFirstCall(): void
    {
        $this->assertFalse(LoggerSingleton::isInitialized('LOGGER_TEST_NOT_INIT'));
    }

    public function testIsInitializedReturnsTrueAfterFirstCall(): void
    {
        LoggerSingleton::getInstance('LOGGER_TEST_INIT_CHECK', '[Test]');

        $this->assertTrue(LoggerSingleton::isInitialized('LOGGER_TEST_INIT_CHECK'));
    }

    public function testResetSpecificConstantRemovesOnlyThatInstance(): void
    {
        LoggerSingleton::getInstance('LOGGER_TEST_RESET_A', '[A]');
        LoggerSingleton::getInstance('LOGGER_TEST_RESET_B', '[B]');

        LoggerSingleton::reset('LOGGER_TEST_RESET_A');

        $this->assertFalse(LoggerSingleton::isInitialized('LOGGER_TEST_RESET_A'));
        $this->assertTrue(LoggerSingleton::isInitialized('LOGGER_TEST_RESET_B'));
    }

    public function testResetNullRemovesAllInstances(): void
    {
        LoggerSingleton::getInstance('LOGGER_TEST_ALL_A', '[A]');
        LoggerSingleton::getInstance('LOGGER_TEST_ALL_B', '[B]');

        LoggerSingleton::reset();

        $this->assertFalse(LoggerSingleton::isInitialized('LOGGER_TEST_ALL_A'));
        $this->assertFalse(LoggerSingleton::isInitialized('LOGGER_TEST_ALL_B'));
    }

    public function testGetLoggerTypeReturnsNoneWhenNotInitialized(): void
    {
        $this->assertSame('none', LoggerSingleton::getLoggerType('LOGGER_TEST_TYPE_NONE'));
    }

    public function testGetLoggerTypeReturnsNullLoggerClassWhenDebugDisabled(): void
    {
        LoggerSingleton::getInstance('LOGGER_TEST_TYPE_NULL', '[Test]');

        $this->assertSame(NullLogger::class, LoggerSingleton::getLoggerType('LOGGER_TEST_TYPE_NULL'));
    }
}
