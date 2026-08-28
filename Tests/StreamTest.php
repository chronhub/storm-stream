<?php

declare(strict_types=1);

namespace Storm\Stream\Tests;

use ArrayObject;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Storm\Contracts\Message\SerializablePayload;
use Storm\Message\Message;
use Storm\Stream\Exception\InvalidStreamContent;
use Storm\Stream\Stream;
use Storm\Stream\StreamName;
use Storm\Stream\Tests\Fixture\FakeDomainEvent;

final class StreamTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    #[Test]
    public function creates_empty_stream(): void
    {
        $stream = new Stream(new StreamName('order'));

        $this->assertCount(0, $stream);
    }

    #[Test]
    public function creates_stream_with_messages(): void
    {
        $stream = new Stream(new StreamName('order'), [
            new Message(new FakeDomainEvent),
            new Message(new FakeDomainEvent),
        ]);

        $this->assertCount(2, $stream);
    }

    #[Test]
    public function accepts_an_array_object_of_messages(): void
    {
        $stream = new Stream(new StreamName('order'), new ArrayObject([
            new Message(new FakeDomainEvent),
            new Message(new FakeDomainEvent),
        ]));

        $this->assertCount(2, $stream);
    }

    #[Test]
    public function materializes_a_generator_so_it_can_be_read_multiple_times(): void
    {
        $message = new Message(new FakeDomainEvent);
        $generator = (static function () use ($message) {
            yield $message;
        })();

        $stream = new Stream(new StreamName('order'), $generator);

        $this->assertCount(1, $stream);
        $stream->messages()
            |> iterator_to_array(...)
            |> (fn ($x) => $this->assertCount(1, $x));
        $stream->messages()
            |> iterator_to_array(...)
            |> (fn ($x) => $this->assertCount(1, $x));
    }

    #[Test]
    public function exposes_stream_name(): void
    {
        $streamName = new StreamName('order-550e8400');
        $stream = new Stream($streamName);

        $this->assertSame($streamName, $stream->streamName);
        $this->assertSame('order-550e8400', $stream->streamName->toString());
    }

    #[Test]
    public function stream_name_has_no_redundant_name_property(): void
    {
        $stream = new Stream(new StreamName('order'));

        $this->assertFalse(property_exists($stream, 'name')); // @phpstan-ignore function.impossibleType
    }

    // -------------------------------------------------------------------------
    // messages() Generator
    // -------------------------------------------------------------------------

    #[Test]
    public function messages_yields_all_messages(): void
    {
        $first = new Message(new FakeDomainEvent);
        $second = new Message(new FakeDomainEvent);

        $stream = new Stream(new StreamName('order'), [$first, $second]);
        $yielded = iterator_to_array($stream->messages());

        $this->assertCount(2, $yielded);
        $this->assertSame($first, $yielded[0]);
        $this->assertSame($second, $yielded[1]);
    }

    #[Test]
    public function messages_generator_return_value_is_count(): void
    {
        $stream = new Stream(new StreamName('order'), [
            new Message(new FakeDomainEvent),
            new Message(new FakeDomainEvent),
            new Message(new FakeDomainEvent),
        ]);

        $generator = $stream->messages();
        iterator_to_array($generator); // exhaust

        $this->assertSame(3, $generator->getReturn());
    }

    #[Test]
    public function messages_yields_nothing_for_empty_stream(): void
    {
        $stream = new Stream(new StreamName('order'));

        $stream->messages()
            |> iterator_to_array(...)
            |> $this->assertEmpty(...);
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    #[Test]
    public function count_returns_number_of_messages(): void
    {
        $stream = new Stream(new StreamName('order'), [
            new Message(new FakeDomainEvent),
            new Message(new FakeDomainEvent),
        ]);

        $this->assertCount(2, $stream);
    }

    #[Test]
    public function count_returns_zero_for_empty_stream(): void
    {
        $stream = new Stream(new StreamName('order'));

        $this->assertCount(0, $stream);
    }

    // -------------------------------------------------------------------------
    // the validating gate: immutability and content
    // -------------------------------------------------------------------------

    #[Test]
    #[Group('adversarial')]
    public function mutating_the_source_container_after_construction_changes_nothing(): void
    {
        // the readonly keyword froze only the PROPERTY: keeping the caller's ArrayObject aliased
        // let the "immutable" stream change after validation, counting, or hand-off
        $source = new ArrayObject(['first' => new Message(new FakeDomainEvent)]);
        $stream = new Stream(new StreamName('order-42'), $source);

        $source['second'] = new Message(new FakeDomainEvent);
        $source['first'] = new Message(new FakeDomainEvent);
        unset($source['first']);

        $this->assertCount(1, $stream, 'add, replace and remove on the source leave the stream untouched');
    }

    #[Test]
    public function keys_are_reindexed_whatever_the_origin(): void
    {
        $message = new Message(new FakeDomainEvent);

        foreach ([
            'array' => ['k' => $message],
            'ArrayObject' => new ArrayObject(['k' => $message]),
            'Generator' => (static function () use ($message) {
                yield 'k' => $message;
            })(),
        ] as $origin => $iterable) {
            $keys = array_keys(iterator_to_array(new Stream(new StreamName('order-42'), $iterable)->messages()));

            $this->assertSame([0], $keys, "a $origin origin yields the same list<> shape");
        }
    }

    #[Test]
    #[Group('adversarial')]
    public function an_element_that_is_not_a_message_is_refused(): void
    {
        $this->expectException(InvalidStreamContent::class);
        $this->expectExceptionMessageIsOrContains('is not a Message');

        new Stream(new StreamName('order-42'), ['bad' => new stdClass]); // @phpstan-ignore argument.type (deliberate: proves the runtime gate behind the PHPDoc generic)
    }

    #[Test]
    #[Group('adversarial')]
    public function a_message_wrapping_a_non_event_is_refused_before_it_can_poison_the_store(): void
    {
        // a serializable command persists perfectly well; the fact path mints the version header
        // itself, and every later read of the stream would then throw NotADomainEvent, durably
        $command = new class() implements SerializablePayload
        {
            public function toPayload(): array
            {
                return [];
            }

            public static function fromPayload(array $payload): static
            {
                return new self;
            }
        };

        $this->expectException(InvalidStreamContent::class);
        $this->expectExceptionMessageIsOrContains('not a DomainEvent');

        new Stream(new StreamName('order-42'), [new Message($command)]);
    }
}
