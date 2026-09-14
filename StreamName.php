<?php

declare(strict_types=1);

namespace Storm\Stream;

use Override;
use Storm\Stream\Exception\InvalidStreamException;
use Stringable;
use Symfony\Component\String\Exception\InvalidArgumentException as StringEncodingException;

use function Symfony\Component\String\u;

/**
 * The identity of a stream, parsed and validated as either category or category-qualifier.
 *
 * The category is the aggregate or stream type, lowercase and snake-friendly; the qualifier is
 * everything after the first delimiter, typically the aggregate id but free-form, so slugs or
 * compound ids work too. A bare category, with no qualifier, names a category stream.
 *
 * The case rule is asymmetric ON PURPOSE: the category is folded to lowercase, the qualifier is
 * NOT, since an opaque id such as base62 must keep its case. Consequence to know: `order-ABC` and
 * `order-abc` are two distinct streams with independent heads, so an application rendering one id
 * in two casings mints two histories with no error at any layer.
 *
 * Qualifiers must already be NFC and contain no whitespace or control characters. Inputs that
 * normalization would change are refused, so identity bytes are never silently rewritten.
 *
 * A value object: validated in the constructor, immutable, and string-convertible. Builders that
 * hold a category and later bind an id use `withQualifier()`.
 *
 * Examples:
 *
 * ```php
 * new StreamName('order'); // category 'order', qualifier null
 * new StreamName('user-550e8400'); // category 'user', qualifier '550e8400'
 * new StreamName('order')->withQualifier('42'); // 'order-42'
 * ```
 */
final readonly class StreamName implements Stringable
{
    public const string DELIM = '-';

    /**
     * Byte budget for the FULL `category-qualifier` name. The name lands in indexed columns,
     * `event_store`'s `UNIQUE (category, stream, version)` and `stream_heads`' primary key, where a
     * PostgreSQL b-tree index row caps out around 2 700 bytes: an unbounded name is accepted by the
     * domain and then fails, or worse bloats, in infrastructure. 512 leaves massive headroom for
     * any sane identity such as UUIDs or multi-segment slugs, while catching a runaway id early.
     */
    public const int MAX_BYTES = 512;

    public const string CATEGORY_REGEX = '/^[a-z][a-z0-9_]*\z/';

    /**
     * No leading / trailing / doubled dash, no whitespace, and NO control or format characters,
     * the `\p{C}` class: a NUL is silently TRUNCATED by the DBAL/PostgreSQL text path, so `order-a`,
     * "order-a\0b" and every other suffix behind the same NUL would converge onto one persisted
     * stream: two distinct identities in memory, one head and one version sequence in the store.
     * The leading- and trailing-dash lookaheads are belt-and-suspenders: the constructor's
     * positional `$pos` guards already reject those on a full value, but `withQualifier()` builds
     * `category-<qualifier>` and routes the raw qualifier through here, so the lookaheads also
     * catch a bad qualifier on that entry path.
     */
    public const string QUALIFIER_REGEX = '/^(?!-)(?!.*--)(?!.*-\z)[^\s\p{C}]+\z/u';

    public string $category;

    /**
     * The qualifier is everything after the first delimiter.
     * It can be a UUID, a slug, or any combination separated by dashes.
     *
     * Examples:
     *
     * - `user-550e8400` yields qualifier `550e8400`
     * - `user-order-550e8400` yields qualifier `order-550e8400`
     * - `order` yields qualifier `null`, a category stream
     */
    public ?string $qualifier;

    /**
     * @throws InvalidStreamException when the value, its category, or its qualifier is malformed,
     *                                not valid UTF-8, or longer than `self::MAX_BYTES` bytes
     */
    public function __construct(string $value)
    {
        $rawValue = $value;
        $value = self::normalized($value);
        $rawDelimiter = strpos($rawValue, self::DELIM);

        if ($rawDelimiter !== false) {
            $rawQualifier = substr($rawValue, $rawDelimiter + 1);

            if ($rawQualifier !== self::normalized($rawQualifier)) {
                throw InvalidStreamException::invalidQualifier($rawQualifier);
            }
        }

        if (strlen($value) > self::MAX_BYTES) {
            throw InvalidStreamException::tooLong(strlen($value), self::MAX_BYTES);
        }

        $pos = strpos($value, self::DELIM);

        if ($pos === false) {
            $this->category = $this->assertCategory($value);
            $this->qualifier = null;

            return;
        }

        if ($pos === 0 || $pos === strlen($value) - 1) {
            throw InvalidStreamException::invalidFormat($value);
        }

        $category = substr($value, 0, $pos);
        $qualifier = substr($value, $pos + 1);
        $this->category = $this->assertCategory($category);
        $this->qualifier = $this->assertQualifier($qualifier);
    }

    /**
     * Returns a new instance with the given qualifier.
     *
     * @throws InvalidStreamException if the qualifier is invalid or already set
     */
    public function withQualifier(string $qualifier): self
    {
        if ($this->qualifier !== null) {
            throw InvalidStreamException::qualifierAlreadySet($this->category);
        }

        return new self($this->category.self::DELIM.$qualifier);
    }

    public function toString(): string
    {
        return $this->qualifier === null
            ? $this->category
            : $this->category.self::DELIM.$this->qualifier;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @throws InvalidStreamException if the qualifier is invalid
     */
    private function assertQualifier(string $qualifier): string
    {
        // deliberately NOT re-trimmed: eating the space of "order- abc" would converge it with
        // "order-abc" silently, the identity drift this class refuses everywhere else; the padded
        // form falls to the regex's whitespace refusal instead. Encoding is already proven, the
        // constructor checked that normalization leaves the raw qualifier unchanged.
        if ($qualifier === '' || ! preg_match(self::QUALIFIER_REGEX, $qualifier)) {
            throw InvalidStreamException::invalidQualifier($qualifier);
        }

        return $qualifier;
    }

    /**
     * @throws InvalidStreamException if the category is invalid
     */
    private function assertCategory(string $category): string
    {
        $category = self::normalized($category, lower: true);

        if ($category === '' || ! preg_match(self::CATEGORY_REGEX, $category)) {
            throw InvalidStreamException::invalidCategory($category);
        }

        return $category;
    }

    /**
     * The encoding boundary: `u()` throws Symfony's own exception on invalid UTF-8, which would
     * escape the package's contracted surface; a consumer depending on `Contracts` alone could not
     * catch every invalid input the constructor documents. Translated here, cause preserved.
     *
     * @throws InvalidStreamException when the value is not valid UTF-8
     */
    private static function normalized(string $value, bool $lower = false): string
    {
        try {
            $unicode = u($value)->trim();

            return ($lower ? $unicode->lower() : $unicode)->toString();
        } catch (StringEncodingException $e) {
            throw InvalidStreamException::invalidEncoding($value, $e);
        }
    }
}
