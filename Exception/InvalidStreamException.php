<?php

declare(strict_types=1);

namespace Storm\Stream\Exception;

use InvalidArgumentException;
use Storm\Contracts\Stream\InvalidStreamException as InvalidStreamExceptionContract;
use Throwable;

/**
 * The stream-name failure whose messages escape control bytes: a refused value may carry the very
 * NUL or C0 characters it was refused for, and they must not reach logs raw.
 */
final class InvalidStreamException extends InvalidArgumentException implements InvalidStreamExceptionContract
{
    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf("Invalid stream '%s', expected <category>-<qualifier> or <category>.", self::printable($value)),
        );
    }

    public static function invalidCategory(string $category): self
    {
        return new self(
            sprintf("Invalid category '%s'. Must match: lowercase letters, digits and underscores, starting with a letter.", self::printable($category)),
        );
    }

    public static function invalidQualifier(string $qualifier): self
    {
        return new self(
            sprintf("Invalid stream qualifier '%s': no whitespace, no control or format characters, no leading/trailing/doubled dash.", self::printable($qualifier)),
        );
    }

    public static function qualifierAlreadySet(string $category): self
    {
        return new self(
            sprintf("Stream qualifier already set for category '%s'.", self::printable($category)),
        );
    }

    /**
     * EVERY interpolated value routes through this one helper, so a future factory cannot forget
     * the escaping the class exists for.
     */
    private static function printable(string $value): string
    {
        return addcslashes($value, "\0..\37\177");
    }

    public static function invalidEncoding(string $value, Throwable $previous): self
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
