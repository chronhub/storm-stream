<?php

declare(strict_types=1);

namespace Storm\Stream\Exception;

use InvalidArgumentException;
use Storm\Stream\StreamName;

/**
 * A producer handed the event store something that is not an event stream: an element that is not
 * a `Message`, or a message wrapping a payload that is not a `DomainEvent`. A serializable command
 * would otherwise persist perfectly well, since the fact path mints the version header itself, and
 * would then poison every later read of the stream, durably.
 *
 * A bug, not a runtime condition, so deliberately NOT contracted: declaring a bug would invite
 * catching it, and the fix is in the producer, never in a catch.
 */
final class InvalidStreamContent extends InvalidArgumentException
{
    public static function notAMessage(StreamName $stream, int|string $index, mixed $element): self
    {
        return new self(sprintf(
            'Stream "%s" element "%s" is not a Message, got %s — a stream is the appended unit of the event store, every element must be a Message wrapping a DomainEvent.',
            $stream->toString(),
            $index,
            get_debug_type($element),
        ));
    }

    public static function notAnEvent(StreamName $stream, int|string $index, object $payload): self
    {
        return new self(sprintf(
            'Stream "%s" element "%s" wraps %s, which is not a DomainEvent — a persisted non-event would poison every later read of this stream (NotADomainEvent), durably.',
            $stream->toString(),
            $index,
            $payload::class,
        ));
    }
}
