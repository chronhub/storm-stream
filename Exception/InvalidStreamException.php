<?php

declare(strict_types=1);

namespace Storm\Stream\Exception;

use InvalidArgumentException;
use Storm\Contracts\Stream\InvalidStreamException as InvalidStreamExceptionContract;
use Storm\Support\Text\Str;
use Throwable;

/**
 * The stream-name failure whose messages spell out control and format characters: a refused value
 * may carry the very NUL or bidi override it was refused for, and it must not reach logs raw.
 */
final class InvalidStreamException extends InvalidArgumentException implements InvalidStreamExceptionContract
{
    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf("Invalid stream '%s', expected <category>-<qualifier> or <category>.", Str::printable($value)),
        );
    }

    public static function invalidCategory(string $category): self
    {
        return new self(
            sprintf("Invalid category '%s'. Must match: lowercase letters, digits and underscores, starting with a letter.", Str::printable($category)),
        );
    }

    public static function invalidQualifier(string $qualifier): self
    {
        return new self(
            sprintf("Invalid stream qualifier '%s': must already be NFC; no whitespace, no control or format characters, no leading/trailing/doubled dash.", Str::printable($qualifier)),
        );
    }

    public static function qualifierAlreadySet(string $category): self
    {
        return new self(
            sprintf("Stream qualifier already set for category '%s'.", Str::printable($category)),
        );
    }

    public static function invalidEncoding(string $value, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Invalid stream value: not valid UTF-8 (hex %s).', substr(bin2hex($value), 0, 64)),
            previous: $previous,
        );
    }

    public static function tooLong(int $bytes, int $max): self
    {
        return new self(
            sprintf('Stream name is %d bytes, the maximum is %d — the name lands in indexed columns (event_store unique, stream_heads primary key).', $bytes, $max),
        );
    }
}
