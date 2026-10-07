<?php

declare(strict_types=1);

namespace Storm\Stream;

use Storm\Stream\Exception\InvalidStreamException;
use Storm\Support\Text\Str;
use Stringable;

/**
 * The category of a stream, the one rule for every entry that names a category alone.
 *
 * It is the category segment of `StreamName`, held to the same rule and the same normalization:
 * trimmed, folded to lowercase, valid UTF-8, then `StreamName::CATEGORY_REGEX`. A qualified name
 * is not a category and is refused, since the regex admits no delimiter. The predicates on
 * `e.category` compare the raw value, so a category that did not pass here reads nothing without
 * an error; every boundary that takes a category from outside builds this object first.
 */
final readonly class StreamCategory implements Stringable
{
    public string $value;

    /**
     * @throws InvalidStreamException when the value is not valid UTF-8, or is not a bare,
     *                                well-formed stream category once trimmed and lowercased
     */
    public function __construct(string $value)
    {
        if (! Str::isWellFormed($value)) {
            throw InvalidStreamException::invalidEncoding($value);
        }

        $category = $value |> Str::canonical(...) |> Str::trim(...) |> Str::lower(...);

        if ($category === '' || ! preg_match(StreamName::CATEGORY_REGEX, $category)) {
            throw InvalidStreamException::invalidCategory($category);
        }

        $this->value = $category;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
