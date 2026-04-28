<?php
declare(strict_types=1);

namespace Swissup\Logger\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Swissup\Logger\Logger\BrowserConsoleLogger;

class BrowserConsoleLoggerTest extends TestCase
{
    private function makeLogger(bool $globalStorage = true, string $prefix = '[Test]'): BrowserConsoleLogger
    {
        return new BrowserConsoleLogger($globalStorage, $prefix);
    }

    public function testFlushReturnsScriptBlock(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('hello');

        $output = $logger->flush();

        $this->assertStringStartsWith('<script>', $output);
        $this->assertStringEndsWith('</script>', $output);
    }

    public function testFlushOnEmptyLogsReturnsEmptyString(): void
    {
        $logger = $this->makeLogger();

        $this->assertSame('', $logger->flush());
    }

    public function testFlushClearsLogsAfterOutput(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('msg');
        $logger->flush();

        $this->assertSame('', $logger->flush());
    }

    public function testGroupOutputsConsoleGroup(): void
    {
        $logger = $this->makeLogger();
        $logger->group('MyGroup');
        $logger->debug('msg');

        $output = $logger->flush();

        $this->assertStringContainsString("console.group(", $output);
        $this->assertStringContainsString('MyGroup', $output);
    }

    public function testGroupEndOutputsConsoleGroupEnd(): void
    {
        $logger = $this->makeLogger();
        $logger->group('G');
        $logger->groupEnd();

        $output = $logger->flush();

        $this->assertStringContainsString('console.groupEnd();', $output);
    }

    public function testUnclosedGroupsAreClosedOnFlush(): void
    {
        $logger = $this->makeLogger();
        $logger->group('Unclosed');
        $logger->debug('msg');

        $output = $logger->flush();

        $this->assertStringContainsString('console.groupEnd();', $output);
        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testPrefixAppearsInGroupTitle(): void
    {
        $logger = $this->makeLogger(true, '[MyPrefix]');
        $logger->group('Section');

        $output = $logger->flush();

        $this->assertStringContainsString('[MyPrefix]', $output);
        $this->assertStringContainsString('Section', $output);
    }

    public function testEmptyPrefixNoExtraSpace(): void
    {
        $logger = $this->makeLogger(true, '');
        $logger->group('Title');

        $output = $logger->flush();

        $this->assertStringNotContainsString("console.group(' Title')", $output);
        $this->assertStringContainsString('Title', $output);
    }

    public function testWindowPlogPresentWhenGlobalStorageEnabled(): void
    {
        $logger = $this->makeLogger(true);
        $logger->debug('msg');

        $output = $logger->flush();

        $this->assertStringContainsString('window.plog', $output);
    }

    public function testWindowPlogAbsentWhenGlobalStorageDisabled(): void
    {
        $logger = $this->makeLogger(false);
        $logger->debug('msg');

        $output = $logger->flush();

        $this->assertStringNotContainsString('window.plog', $output);
    }

    public function testErrorLevelUsesConsoleError(): void
    {
        $logger = $this->makeLogger(false);
        $logger->error('something failed');

        $output = $logger->flush();

        $this->assertStringContainsString('console.error(', $output);
    }

    public function testWarningLevelUsesConsoleWarn(): void
    {
        $logger = $this->makeLogger(false);
        $logger->warning('be careful');

        $output = $logger->flush();

        $this->assertStringContainsString('console.warn(', $output);
    }

    public function testDebugLevelUsesConsoleDebug(): void
    {
        $logger = $this->makeLogger(false);
        $logger->debug('debug msg');

        $output = $logger->flush();

        $this->assertStringContainsString('console.debug(', $output);
    }

    public function testMessageAppearsInOutput(): void
    {
        $logger = $this->makeLogger(false);
        $logger->info('unique-info-message');

        $output = $logger->flush();

        $this->assertStringContainsString('unique-info-message', $output);
    }

    public function testContextAppearsAsJsonInOutput(): void
    {
        $logger = $this->makeLogger(false);
        $logger->debug('msg', ['foo' => 'bar123']);

        $output = $logger->flush();

        $this->assertStringContainsString('bar123', $output);
    }

    public function testSingleQuotesAreEscapedInMessage(): void
    {
        $logger = $this->makeLogger(false);
        $logger->debug("it's a test");

        $output = $logger->flush();

        $this->assertStringNotContainsString("it's a test", $output);
        $this->assertStringContainsString("it\\'s a test", $output);
    }

    public function testNewlinesAreEscapedInMessage(): void
    {
        $logger = $this->makeLogger(false);
        $logger->debug("line1\nline2");

        $output = $logger->flush();

        $this->assertStringContainsString('\\n', $output);
    }

    public function testClearResetsLogsAndNestingLevel(): void
    {
        $logger = $this->makeLogger();
        $logger->group('G');
        $logger->debug('msg');
        $logger->clear();

        $this->assertEmpty($logger->getLogs());
        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testGroupNestingLevelTrackedCorrectly(): void
    {
        $logger = $this->makeLogger();

        $this->assertSame(0, $logger->getGroupNestingLevel());
        $logger->group('A');
        $this->assertSame(1, $logger->getGroupNestingLevel());
        $logger->group('B');
        $this->assertSame(2, $logger->getGroupNestingLevel());
        $logger->groupEnd();
        $this->assertSame(1, $logger->getGroupNestingLevel());
        $logger->groupEnd();
        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testGroupEndDoesNothingWhenNoOpenGroup(): void
    {
        $logger = $this->makeLogger();
        $logger->groupEnd();

        $this->assertSame(0, $logger->getGroupNestingLevel());
    }
}
