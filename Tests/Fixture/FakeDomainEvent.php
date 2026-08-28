<?php

declare(strict_types=1);

namespace Storm\Stream\Tests\Fixture;

use Storm\Contracts\Message\DomainEvent;

/**
 * Minimal concrete domain event for tests that only need a real DomainEvent
 * instance (identity, counting, wrapping). Each `new FakeDomainEvent()` is a distinct
 * instance, which is what identity-based assertions rely on.
 */
final class FakeDomainEvent implements DomainEvent
{
    public function aggregateId(): string
    {
        return 'fake';
    }

    public function toPayload(): array
    {
        return [];
    }

    public static function fromPayload(array $payload): static
    {
        return new self;
    }
}
