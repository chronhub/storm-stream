<?php

declare(strict_types=1);

namespace Storm\Stream\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Contracts\Stream\InvalidStreamException as InvalidStreamExceptionContract;
use Storm\Stream\Exception\InvalidStreamException;

/**
 * The exception seam of the Stream port: an invalid stream name is catchable by the Contracts-owned
 * type, and the SPL base carries the nature: a value object rejecting a malformed argument is an
 * `InvalidArgumentException`. Break `implements InvalidStreamExceptionContract` and this fails.
 */
final class ContractedExceptionsTest extends TestCase
{
    #[Test]
    public function an_invalid_stream_is_catchable_by_the_contracted_type(): void
    {
        $failure = InvalidStreamException::invalidFormat('order--bad');

        $this->assertInstanceOf(InvalidStreamExceptionContract::class, $failure);
        $this->assertInstanceOf(InvalidArgumentException::class, $failure); // the SPL intent axis: bad argument
    }
}
