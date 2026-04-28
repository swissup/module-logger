<?php
declare(strict_types=1);

namespace Swissup\Logger\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Swissup\Logger\Logger\LoggerHelperTrait;

class LoggerHelperTraitTest extends TestCase
{
    /**
     * Create anonymous class instance that exposes trait methods publicly
     */
    private function makeTrait(): object
    {
        return new class {
            use LoggerHelperTrait;

            public function testInterpolate(string $message, array $context = []): string
            {
                return $this->interpolate($message, $context);
            }

            public function testSanitizeContext(array $context): array
            {
                return $this->sanitizeContext($context);
            }

            public function testSerialize($value): string
            {
                return $this->serialize($value);
            }

            public function testSetSerializeOptions(int $options): void
            {
                $this->setSerializeOptions($options);
            }
        };
    }

    // --- interpolate() ---

    public function testInterpolateSinglePlaceholder(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testInterpolate('Hello {name}', ['name' => 'World']);

        $this->assertSame('Hello World', $result);
    }

    public function testInterpolateMultiplePlaceholders(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testInterpolate('{a} and {b}', ['a' => 'foo', 'b' => 'bar']);

        $this->assertSame('foo and bar', $result);
    }

    public function testInterpolateIgnoresArrayValues(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testInterpolate('Hello {data}', ['data' => ['x', 'y']]);

        $this->assertSame('Hello {data}', $result);
    }

    public function testInterpolateIgnoresObjectsWithoutToString(): void
    {
        $trait = $this->makeTrait();
        $obj = new \stdClass();
        $result = $trait->testInterpolate('val={obj}', ['obj' => $obj]);

        $this->assertSame('val={obj}', $result);
    }

    public function testInterpolateUsesObjectToString(): void
    {
        $trait = $this->makeTrait();
        $obj = new class {
            public function __toString(): string { return 'stringified'; }
        };
        $result = $trait->testInterpolate('val={obj}', ['obj' => $obj]);

        $this->assertSame('val=stringified', $result);
    }

    public function testInterpolateNoPlaceholders(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testInterpolate('no placeholders here', ['key' => 'value']);

        $this->assertSame('no placeholders here', $result);
    }

    // --- sanitizeContext() ---

    public function testSanitizeContextPassesScalarsThrough(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testSanitizeContext(['int' => 42, 'str' => 'hello', 'bool' => true]);

        $this->assertSame(['int' => 42, 'str' => 'hello', 'bool' => true], $result);
    }

    public function testSanitizeContextConvertsResourceToString(): void
    {
        $trait = $this->makeTrait();
        $resource = fopen('php://memory', 'r');
        $result = $trait->testSanitizeContext(['res' => $resource]);
        fclose($resource);

        $this->assertStringStartsWith('resource(', $result['res']);
    }

    public function testSanitizeContextConvertsThrowableToArray(): void
    {
        $trait = $this->makeTrait();
        $e = new \RuntimeException('test error', 42);
        $result = $trait->testSanitizeContext(['exception' => $e]);

        $this->assertIsArray($result['exception']);
        $this->assertSame('exception', $result['exception']['_type']);
        $this->assertSame(\RuntimeException::class, $result['exception']['class']);
        $this->assertSame('test error', $result['exception']['message']);
        $this->assertSame(42, $result['exception']['code']);
    }

    public function testSanitizeContextConvertsObjectWithToStringToString(): void
    {
        $trait = $this->makeTrait();
        $obj = new class {
            public function __toString(): string { return 'my-string'; }
        };
        $result = $trait->testSanitizeContext(['obj' => $obj]);

        $this->assertSame('my-string', $result['obj']);
    }

    public function testSanitizeContextConvertsPlainObjectToClassName(): void
    {
        $trait = $this->makeTrait();
        $obj = new \stdClass();
        $result = $trait->testSanitizeContext(['obj' => $obj]);

        $this->assertSame('object(stdClass)', $result['obj']);
    }

    public function testSanitizeContextHandlesJsonSerializable(): void
    {
        $trait = $this->makeTrait();
        $obj = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed { return ['key' => 'val']; }
        };
        $result = $trait->testSanitizeContext(['obj' => $obj]);

        $this->assertSame($obj, $result['obj']);
    }

    public function testSanitizeContextRecursivelyHandlesNestedArrays(): void
    {
        $trait = $this->makeTrait();
        $e = new \RuntimeException('nested');
        $result = $trait->testSanitizeContext(['level1' => ['level2' => $e]]);

        $this->assertIsArray($result['level1']['level2']);
        $this->assertSame('exception', $result['level1']['level2']['_type']);
    }

    // --- serialize() ---

    public function testSerializeReturnsValidJson(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testSerialize(['key' => 'value', 'num' => 123]);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertSame('value', $decoded['key']);
        $this->assertSame(123, $decoded['num']);
    }

    public function testSerializeHandlesEmptyArray(): void
    {
        $trait = $this->makeTrait();
        $result = $trait->testSerialize([]);

        $this->assertSame('[]', $result);
    }

    public function testSetSerializeOptionsAffectsOutput(): void
    {
        $trait = $this->makeTrait();

        // Without JSON_UNESCAPED_UNICODE unicode should be escaped
        $trait->testSetSerializeOptions(JSON_HEX_TAG);
        $result = $trait->testSerialize(['msg' => 'привіт']);

        // With JSON_HEX_TAG set but not JSON_UNESCAPED_UNICODE, unicode is escaped
        $this->assertStringNotContainsString('привіт', $result);
    }
}
