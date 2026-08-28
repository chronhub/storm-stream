<?php

declare(strict_types=1);

namespace Storm\Stream\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Storm\Stream\Exception\InvalidStreamException;
use Storm\Stream\StreamName;

final class StreamNameTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Category only, no qualifier
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('valid_categories')]
    public function creates_category_only_stream(string $value, string $expectedCategory): void
    {
        $stream = new StreamName($value);

        $this->assertSame($expectedCategory, $stream->category);
        $this->assertNull($stream->qualifier);
        $this->assertSame($expectedCategory, $stream->toString());
    }

    #[Test]
    #[DataProvider('invalid_categories')]
    public function throws_for_invalid_category(string $value): void
    {
        $this->expectException(InvalidStreamException::class);

        new StreamName($value);
    }

    // -------------------------------------------------------------------------
    // Category and qualifier
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('valid_streams')]
    public function creates_stream_with_qualifier(
        string $value,
        string $expectedCategory,
        string $expectedQualifier,
    ): void {
        $stream = new StreamName($value);

        $this->assertSame($expectedCategory, $stream->category);
        $this->assertSame($expectedQualifier, $stream->qualifier);
        $this->assertSame($value, $stream->toString());
    }

    #[Test]
    #[DataProvider('invalid_streams')]
    public function throws_for_invalid_stream(string $value): void
    {
        $this->expectException(InvalidStreamException::class);

        new StreamName($value);
    }

    #[Test]
    #[DataProvider('edge_delimiter_values')]
    public function edge_delimiters_report_the_full_value_as_an_invalid_format(string $value): void
    {
        $thrown = null;

        try {
            new StreamName($value);
        } catch (InvalidStreamException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(InvalidStreamException::class, $thrown);
        $this->assertSame(InvalidStreamException::invalidFormat($value)->getMessage(), $thrown->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function edge_delimiter_values(): iterable
    {
        yield 'leading delimiter' => ['-order'];
        yield 'trailing delimiter' => ['order-'];
    }

    // -------------------------------------------------------------------------
    // toString / __toString
    // -------------------------------------------------------------------------

    #[Test]
    public function to_string_returns_category_when_no_qualifier(): void
    {
        $stream = new StreamName('order');

        $this->assertSame('order', $stream->toString());
        $this->assertSame('order', (string) $stream);
    }

    #[Test]
    public function to_string_returns_full_stream_with_qualifier(): void
    {
        $stream = new StreamName('order-550e8400');

        $this->assertSame('order-550e8400', $stream->toString());
        $this->assertSame('order-550e8400', (string) $stream);
    }

    // -------------------------------------------------------------------------
    // withQualifier
    // -------------------------------------------------------------------------

    #[Test]
    public function with_qualifier_returns_new_instance(): void
    {
        $stream = new StreamName('order');
        $result = $stream->withQualifier('550e8400');

        $this->assertNotSame($stream, $result);
        $this->assertSame('order-550e8400', $result->toString());
    }

    #[Test]
    public function with_qualifier_does_not_mutate_original(): void
    {
        $stream = new StreamName('order');
        $stream->withQualifier('550e8400');

        $this->assertNull($stream->qualifier);
    }

    #[Test]
    public function with_qualifier_throws_when_qualifier_already_set(): void
    {
        $this->expectException(InvalidStreamException::class);

        $stream = new StreamName('order-550e8400');
        $stream->withQualifier('another-id');
    }

    #[Test]
    #[DataProvider('invalid_qualifiers')]
    public function with_qualifier_throws_for_invalid_qualifier(string $qualifier): void
    {
        $this->expectException(InvalidStreamException::class);

        $stream = new StreamName('order');
        $stream->withQualifier($qualifier);
    }

    // -------------------------------------------------------------------------
    // Whitespace trimming
    // -------------------------------------------------------------------------

    #[Test]
    public function trims_whitespace_from_input(): void
    {
        $stream = new StreamName('  order  ');

        $this->assertSame('order', $stream->category);
    }

    #[Test]
    public function category_is_lowercased(): void
    {
        $stream = new StreamName('ORDER');

        $this->assertSame('order', $stream->category);
    }

    // -------------------------------------------------------------------------
    // Complex qualifiers, multi-segment
    // -------------------------------------------------------------------------

    #[Test]
    public function accepts_multi_segment_qualifier(): void
    {
        $stream = new StreamName('user-order-550e8400');

        $this->assertSame('user', $stream->category);
        $this->assertSame('order-550e8400', $stream->qualifier);
    }

    #[Test]
    public function accepts_uuid_v7_as_qualifier(): void
    {
        $stream = new StreamName('order-01956c2a-1234-7abc-8def-abcdef012345');

        $this->assertSame('order', $stream->category);
        $this->assertSame('01956c2a-1234-7abc-8def-abcdef012345', $stream->qualifier);
    }

    #[Test]
    public function accepts_slug_as_qualifier(): void
    {
        $stream = new StreamName('order-my-special-order');

        $this->assertSame('order', $stream->category);
        $this->assertSame('my-special-order', $stream->qualifier);
    }

    // -------------------------------------------------------------------------
    // Data Providers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function valid_categories(): array
    {
        return [
            'simple' => ['order', 'order'],
            'with underscore' => ['order_line', 'order_line'],
            'with digits' => ['order1', 'order1'],
            'uppercase input' => ['ORDER', 'order'],
            'mixed case input' => ['Order', 'order'],
            'with spaces' => ['  order  ', 'order'],
            'single char' => ['a', 'a'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid_categories(): array
    {
        return [
            'empty string' => [''],
            'starts with digit' => ['1order'],
            'starts with underscore' => ['_order'],
            'starts with dash' => ['-order'],
            'spaces only' => ['   '],
            'special characters' => ['order!'],
            'dot separator' => ['order.line'],
            'slash' => ['order/line'],
            'dollar sign' => ['$line'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function valid_streams(): array
    {
        return [
            'category + uuid' => ['order-550e8400', 'order', '550e8400'],
            'category + slug' => ['order-my-order', 'order', 'my-order'],
            'category + multi-segment' => ['user-order-550e8400', 'user', 'order-550e8400'],
            'category + uuid v7' => [
                'order-01956c2a-1234-7abc-8def-abcdef012345',
                'order',
                '01956c2a-1234-7abc-8def-abcdef012345',
            ],
            'underscore category + qualifier' => ['order_line-550e8400', 'order_line', '550e8400'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid_streams(): array
    {
        return [
            'dash at start' => ['-order'],
            'dash at end' => ['order-'],
            'double dash' => ['order--550e8400'],
            'dash only' => ['-'],
            'empty qualifier' => ['order-'],
            'spaces in qualifier' => ['order-my order'],
            'dash start qualifier' => ['order--qualifier'],
            'dollar system stream' => ['$ce-order'],     // ESDB-style $ce category stream, $ reserved
            'double dollar metadata' => ['$$order-123'], // ESDB-style $$ metadata stream, $ reserved
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid_qualifiers(): array
    {
        return [
            'empty' => [''],
            'spaces only' => ['   '],
            'starts with dash' => ['-qualifier'],
            'ends with dash' => ['qualifier-'],
            'double dash' => ['quali--fier'],
            'with spaces' => ['my qualifier'],
        ];
    }

    // -------------------------------------------------------------------------
    // the storage-safety hardening: NUL / controls / encoding / byte budget
    // -------------------------------------------------------------------------

    #[Test]
    #[Group('adversarial')]
    #[DataProvider('storage_toxic_qualifiers')]
    public function refuses_a_qualifier_postgres_would_truncate_or_reject(string $value): void
    {
        // a NUL is silently truncated by the DBAL/PostgreSQL text path: "order-a" and "order-a\0b"
        // were two identities in memory but ONE persisted stream, one head, one version sequence
        $this->expectException(InvalidStreamException::class);

        new StreamName($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storage_toxic_qualifiers(): array
    {
        return [
            'NUL byte' => ["order-a\0b"],
            'C0 control' => ["order-a\x01b"],
            'escape char' => ["order-a\x1bb"],
            'DEL' => ["order-a\x7fb"],
        ];
    }

    #[Test]
    public function refuses_invalid_utf8_through_the_contract_not_a_symfony_exception(): void
    {
        // u() throws Symfony's own exception on invalid UTF-8; a consumer depending on Contracts
        // alone could not catch every invalid input; the boundary translates, cause preserved
        try {
            new StreamName("order-\xC3\x28");
            $this->fail('invalid UTF-8 must be refused');
        } catch (InvalidStreamException $e) {
            $this->assertNotNull($e->getPrevious(), 'the Symfony cause is preserved');
            $this->assertStringNotContainsString("\xC3\x28", $e->getMessage(), 'raw bytes never reach the message');
        }
    }

    #[Test]
    public function invalid_utf8_diagnostic_is_hex_encoded_and_capped_at_64_characters(): void
    {
        $value = "\xFE".str_repeat("\xFF", 39);
        $previous = new RuntimeException('decoder failure');

        $exception = InvalidStreamException::invalidEncoding($value, $previous);

        $this->assertSame(
            'Invalid stream value: not valid UTF-8 (hex fe'.str_repeat('ff', 31).').',
            $exception->getMessage(),
        );
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function accepts_a_name_at_the_byte_budget_and_refuses_one_byte_over(): void
    {
        $atMax = 'order-'.str_repeat('x', StreamName::MAX_BYTES - 6);
        new StreamName($atMax)->toString()
            |> strlen(...)
            |> (fn ($x) => $this->assertSame(StreamName::MAX_BYTES, $x));

        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessageIsOrContains('the maximum is');

        new StreamName($atMax.'x');
    }

    #[Test]
    public function with_qualifier_rides_the_same_hardening(): void
    {
        $this->expectException(InvalidStreamException::class);

        new StreamName('order')->withQualifier("a\0b");
    }

    // -------------------------------------------------------------------------
    // Refusal messages and identity drift
    // -------------------------------------------------------------------------

    #[Test]
    #[Group('adversarial')]
    #[DataProvider('hostile_bytes_in_either_position')]
    public function a_refusal_message_never_carries_raw_control_bytes(string $value): void
    {
        // the exception class's own reason for existing: a refused value carries the very bytes it
        // was refused for, and a raw LF forges a log line, a raw ESC repaints a console
        try {
            new StreamName($value);
            $this->fail('expected InvalidStreamException');
        } catch (InvalidStreamException $e) {
            $this->assertSame(0, preg_match('/[\x00-\x1F\x7F]/', $e->getMessage()), 'control bytes must be escaped, never reach logs raw');
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostile_bytes_in_either_position(): iterable
    {
        yield 'NUL in category' => ["or\0der-x"];
        yield 'LF in category' => ["or\nder-x"];
        yield 'ANSI escape in category' => ["ord\x1b[31mer-x"];
        yield 'C0 behind a leading dash, the invalidFormat arm' => ["-a\x01b"];
        yield 'NUL in qualifier' => ["order-a\0b"];
        yield 'ANSI escape in qualifier' => ["order-a\x1bb"];
    }

    #[Test]
    public function the_qualifier_keeps_its_case_so_two_casings_are_two_streams(): void
    {
        // the asymmetry the class docblock states: the category folds, an opaque id never does
        $upper = new StreamName('order-ABC');
        $lower = new StreamName('order-abc');

        $this->assertSame('order-ABC', $upper->toString(), 'an opaque id is never case-folded');
        $this->assertNotSame($upper->toString(), $lower->toString(), 'two casings are two streams, with independent heads');
    }

    #[Test]
    public function whitespace_behind_the_delimiter_is_refused_never_silently_eaten(): void
    {
        // eating the pad would converge "order- abc" and "order-abc" on one stream silently, the
        // identity drift the NUL rationale refuses one line above
        $this->expectException(InvalidStreamException::class);

        new StreamName('order- abc');
    }
}
