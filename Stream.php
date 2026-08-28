<?php

declare(strict_types=1);

namespace Storm\Stream;

use Countable;
use Generator;
use Override;
use Storm\Contracts\Message\DomainEvent;
use Storm\Message\Message;
use Storm\Stream\Exception\InvalidStreamContent;

/**
 * A named stream of messages, the appended unit of the event store.
 *
 * It is the last typed frontier before the append, so it acts as the VALIDATING gate: every
 * element must be a `Message` wrapping a `DomainEvent`. A serializable command would otherwise
 * persist perfectly well, since the fact path mints the version header itself, so neither
 * serialization nor the NOT NULL version column stops it, and it would then poison every later
 * read of the stream with `NotADomainEvent`, durably. Refusing it at construction is the only
 * moment the producer's bug is still cheap.
 *
 * The given iterable is materialized at construction into a private re-indexed list, whatever its
 * origin, be it an array, `ArrayObject`, or `Generator`, so the stream can be counted and iterated
 * multiple times, and TRULY immutably: the elements are copied out, a mutable source container
 * keeps no alias into the stream, and an array in a readonly property cannot change after
 * initialization.
 *
 * An EMPTY stream is a legal value, never a caller mistake: the append paths return before
 * touching the store, so appending one is a pure no-op, neither an error nor a head-creating
 * write.
 */
final readonly class Stream implements Countable
{
    /** @var list<Message> */
    private array $messages;

    /**
     * @param  iterable<Message>  $messages
     *
     * @throws InvalidStreamContent when an element is not a `Message`, or wraps a payload that is
     *                              not a `DomainEvent`
     */
    public function __construct(
        public StreamName $streamName,
        iterable $messages = [],
    ) {
        $list = [];

        foreach ($messages as $index => $message) {
            // @phpstan-ignore instanceof.alwaysTrue (the PHPDoc generic is no runtime guarantee; this gate exists for the callers the analyzer cannot see)
            if (! $message instanceof Message) {
                throw InvalidStreamContent::notAMessage($this->streamName, $index, $message);
            }

            if (! $message->message() instanceof DomainEvent) {
                throw InvalidStreamContent::notAnEvent($this->streamName, $index, $message->message());
            }

            $list[] = $message;
        }

        $this->messages = $list;
    }

    /**
     * Yields all messages in the stream.
     *
     * The `Generator`'s return value is the total number of messages yielded. Access it via
     * `$generator->getReturn()` after full iteration.
     *
     * @return Generator<int, Message, null, int>
     */
    public function messages(): Generator
    {
        yield from $this->messages;

        return count($this->messages);
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }
}
