# Storm Stream

The two stream primitives of the framework:

- **`StreamName`** — validates and represents a stream name following the `<category>-<qualifier>`
  convention every Storm surface routes on;
- **`Stream`** — a named, immutable collection of `Message` envelopes; the unit appended to the
  event store.

## Installation

```bash
composer require chronhub/storm-stream
```

## StreamName

### Convention

Stream names follow the `<category>-<qualifier>` convention:

```
category              →  global category stream (no qualifier)
category-uuid         →  stream for a specific aggregate
category-sub-uuid     →  multi-segment qualifier
```

### Rules

- **Category** — lowercase letters, digits, and underscores, must start with a letter
- **Qualifier** — everything after the first `-`, can contain dashes (no leading/trailing/double dashes)
- Category is automatically lowercased on construction

### Usage

```php
use Storm\Stream\StreamName;

// Category only (global stream)
$stream = new StreamName('order');
$stream->category;    // 'order'
$stream->qualifier;   // null
$stream->toString();  // 'order'
(string) $stream;     // 'order'

// Category + qualifier
$stream = new StreamName('order-550e8400');
$stream->category;    // 'order'
$stream->qualifier;   // '550e8400'
$stream->toString();  // 'order-550e8400'

// Multi-segment qualifier
$stream = new StreamName('user-order-550e8400');
$stream->category;    // 'user'
$stream->qualifier;   // 'order-550e8400'

// Uppercase is normalized
$stream = new StreamName('ORDER');
$stream->category;    // 'order'

// Add qualifier to category stream
$stream = new StreamName('order');
$withQualifier = $stream->withQualifier('550e8400');
$withQualifier->toString(); // 'order-550e8400'
```

### Valid Examples

```
order                           ✓  category only
order-550e8400                  ✓  category + uuid
user-order-550e8400             ✓  multi-segment qualifier
order_line-550e8400             ✓  underscore in category
order-01956c2a-1234-7abc-8def   ✓  uuid v7 qualifier
```

### Invalid Examples

```
1order          ✗  category starts with digit
_order          ✗  category starts with underscore
order-          ✗  empty qualifier
order--uuid     ✗  double dash
-order          ✗  leading dash
order!          ✗  special characters in category
```

## Stream

A `Stream` pairs a `StreamName` with a collection of `Message` envelopes — it is the unit
**appended to the event store**. Each message wraps a domain event together with its headers
(aggregate id/type/version, message type, …).

A stream only ever holds events — and the constructor **enforces** it: every element must be a
`Message` whose payload implements `DomainEvent`, refused otherwise with `InvalidStreamContent`
(a producer bug — a serializable command would persist perfectly well and poison every later
read of the stream). The given iterable (array, Generator, `ArrayObject`) is **materialized at
construction into a private re-indexed list**, so the stream is safe to count and iterate
multiple times, and truly immutable: a mutable source container keeps no alias into it.

```php
use Storm\Message\Message;
use Storm\Stream\Stream;
use Storm\Stream\StreamName;

$stream = new Stream(
    streamName: new StreamName('order-550e8400'),
    messages: [new Message($event1), new Message($event2)],
);

// Access stream name
$stream->streamName->category;    // 'order'
$stream->streamName->qualifier;   // '550e8400'
(string) $stream->streamName;     // 'order-550e8400'

// Count messages
count($stream); // 2

// Iterate messages via Generator
foreach ($stream->messages() as $message) {
    $message->message(); // the wrapped domain event
}

// Generator return value = total messages yielded
$generator = $stream->messages();
iterator_to_array($generator);
$generator->getReturn(); // 2

// Generator is materialized — safe to read multiple times
iterator_to_array($stream->messages());
iterator_to_array($stream->messages());
```

## Exceptions

Two exceptions, two audiences:

- **`InvalidStreamException`** (extends SPL `InvalidArgumentException`) implements the contracted
  `Storm\Contracts\Stream\InvalidStreamException`, so a consumer depending on `Contracts` alone
  catches every invalid NAME: bad format/category/qualifier, invalid UTF-8, control characters,
  or a name over `StreamName::MAX_BYTES` (the indexed-column budget).
- **`InvalidStreamContent`** (extends SPL `InvalidArgumentException`) is deliberately NOT
  contracted: a non-`Message` element or a non-event payload is a producer **bug** — fix the
  producer, never catch the exception.

```php
use Storm\Contracts\Stream\InvalidStreamException;

try {
    $stream = new StreamName('1invalid');
} catch (InvalidStreamException $e) {
    // invalid name: format, category, qualifier, encoding, or length
}
```

### Exception factory methods

```php
InvalidStreamException::invalidFormat($value);      // wrong format
InvalidStreamException::invalidCategory($category); // invalid category
InvalidStreamException::invalidQualifier($qualifier); // invalid qualifier
InvalidStreamException::qualifierAlreadySet($category); // qualifier already set
```

## Design Decisions

- **`qualifier` instead of `identifier`** — the qualifier is not always a UUID; it can be a slug, a UUID, or any combination of segments separated by dashes. `qualifier` better reflects this flexibility.
- **Category is lowercased automatically** — no case sensitivity issues when comparing or routing streams.
- **`Stream` carries `Message` envelopes, not raw events** — the event store needs each event's headers (aggregate version, type, …), which live in the neutral `Message`. The "events only" rule is a runtime invariant, not a type constraint.
- **The message collection is materialized at construction** — whatever the source (array, Generator, `ArrayObject`), the elements are copied into a private re-indexed list, making the stream safe to count and iterate multiple times, with no alias left into a mutable source container.
- **The validation rules are published as constants** — `StreamName::DELIM`, `CATEGORY_REGEX`, `QUALIFIER_REGEX` and `MAX_BYTES` (the indexed-column budget), for Doctrine types and tests; the code is their source of truth.

## Tests

```bash
vendor/bin/phpunit src/Stream/Tests   # from the storm root
```

## Resources

This package is developed in the `chronhub/storm` monorepo; a standalone repository for it is a
READ-ONLY subtree split. Report issues and open pull requests on the monorepo, where the tests,
the architecture gates and the full internal documentation live.

---

*Pre-version: this package changes without deprecation cycles — pin a commit if you need
stability, expect resets rather than migrations until the first tagged version.*

## Qualifier input compatibility

A qualifier must already use Unicode NFC. `StreamName` rejects qualifiers whose bytes would
change under Unicode normalization or trimming, through both the constructor and
`withQualifier()`. Category normalization remains unchanged. Valid NFC qualifiers preserve their
bytes and case.

Callers that supplied decomposed Unicode or padded identifiers must correct their input handling.
Those inputs raise `InvalidStreamException` instead of silently addressing a normalized stream.
If a domain chooses to normalize identifiers, do so at its identity boundary before creating
message headers or selecting a stream. Do not rename stored streams to decomposed forms: names
written through `StreamName` already used NFC, and a new spelling could split an existing history.
