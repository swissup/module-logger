<?php
declare(strict_types=1);

namespace Swissup\Logger\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Swissup\Logger\Logger\CliLogger;

class CliLoggerTest extends TestCase
{
    private function makeLogger(string $prefix = '[Test]'): CliLogger
    {
        // immediateOutput=false so we can inspect accumulated logs
        return new CliLogger($prefix, false, false);
    }

    public function testLogAccumulatesEntriesWhenImmediateOutputDisabled(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('first');
        $logger->info('second');

        $this->assertCount(2, $logger->getLogs());
    }

    public function testFlushReturnsFormattedStringAndClearsLogs(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('hello');

        $output = $logger->flush();

        $this->assertNotEmpty($output);
        $this->assertEmpty($logger->getLogs());
    }

    public function testFlushOnEmptyLogsReturnsEmptyString(): void
    {
        $logger = $this->makeLogger();

        $this->assertSame('', $logger->flush());
    }

    public function testGroupIncreasesNestingLevel(): void
    {
        $logger = $this->makeLogger();

        $this->assertSame(0, $logger->getGroupNestingLevel());
        $logger->group('MyGroup');
        $this->assertSame(1, $logger->getGroupNestingLevel());
    }

    public function testGroupEndDecreasesNestingLevel(): void
    {
        $logger = $this->makeLogger();
        $logger->group('MyGroup');
        $logger->groupEnd();

        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testGroupEndDoesNotGoBelowZero(): void
    {
        $logger = $this->makeLogger();
        $logger->groupEnd();
        $logger->groupEnd();

        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testFlushClosesUnclosedGroups(): void
    {
        $logger = $this->makeLogger();
        $logger->group('Unclosed');
        $logger->debug('msg');

        $logger->flush();

        $this->assertSame(0, $logger->getGroupNestingLevel());
    }

    public function testPrefixAppearsInGroupOutput(): void
    {
        $logger = $this->makeLogger('[MyPrefix]');
        $logger->group('SomeGroup');

        $output = $logger->flush();

        $this->assertStringContainsString('[MyPrefix]', $output);
        $this->assertStringContainsString('SomeGroup', $output);
    }

    public function testGroupOutputContainsBorderChars(): void
    {
        $logger = $this->makeLogger();
        $logger->group('Test');
        $logger->debug('msg');
        $logger->groupEnd();

        $output = $logger->flush();

        $this->assertStringContainsString('┌─', $output);
        $this->assertStringContainsString('└─', $output);
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

    public function testLogMessageAppearsInFlushOutput(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('unique-message-string');

        $output = $logger->flush();

        $this->assertStringContainsString('unique-message-string', $output);
    }

    public function testContextAppearsInFlushOutput(): void
    {
        $logger = $this->makeLogger();
        $logger->debug('msg', ['key' => 'value123']);

        $output = $logger->flush();

        $this->assertStringContainsString('value123', $output);
    }

    public function testLevelAppearsUppercasedInOutput(): void
    {
        $logger = $this->makeLogger();
        $logger->warning('something');

        $output = $logger->flush();

        $this->assertStringContainsString('WARNING', $output);
    }

    public function testEmptyPrefixDoesNotAddExtraSpace(): void
    {
        $logger = new CliLogger('', false, false);
        $logger->group('Title');

        $output = $logger->flush();

        // With empty prefix there should be no "  Title" (prefix + space + title)
        // The format should be "┌─ Title" not "┌─  Title"
        $this->assertStringContainsString('Title', $output);
        $this->assertStringNotContainsString('┌─  Title', $output);
    }
}
