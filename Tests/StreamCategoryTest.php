<?php

declare(strict_types=1);

namespace Storm\Stream\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Storm\Stream\Exception\InvalidStreamException;
use Storm\Stream\StreamCategory;
use Storm\Stream\StreamName;

final class StreamCategoryTest extends TestCase
{
    #[Test]
    public function test_it_applies_the_stream_name_category_rule(): void
    {
        // the one rule, shared: whatever StreamName makes of a category segment, the value object
        // makes of the whole value, trimmed and lowercased
        $category = new StreamCategory(' OrDer_42 ');

        $this->assertSame('order_42', $category->value);
        $this->assertSame('order_42', (string) $category);
        $this->assertSame(new StreamName(' OrDer_42 ')->category, $category->value);
        $this->assertTrue($category->equals(new StreamCategory('order_42')));
        $this->assertFalse($category->equals(new StreamCategory('order')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonCategories(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'qualified name' => ['order-42'];
        yield 'leading digit' => ['9order'];
        yield 'inner space' => ['or der'];
        yield 'invalid utf-8' => ["\xB1\x31"];
    }

    #[Test]
    #[DataProvider('nonCategories')]
    public function it_refuses_what_is_not_a_bare_category(string $value): void
    {
        $this->expectException(InvalidStreamException::class);

        new StreamCategory($value);
    }
}
