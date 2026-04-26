<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Capture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Capture\CaptureStore;

#[CoversClass(CaptureStore::class)]
final class CaptureStoreTest extends TestCase
{
    #[Test]
    public function substitutes_known_placeholders_in_strings(): void
    {
        $name = 'hash';
        $value = 'abc123';
        $store = new CaptureStore();
        $store->set($name, $value);

        $result = $store->apply("/threads/{@{$name}}/");

        self::assertSame("/threads/{$value}/", $result);
    }

    #[Test]
    public function leaves_unknown_placeholders_untouched(): void
    {
        $template = '{@unknown}';
        $store = new CaptureStore();

        $result = $store->apply($template);

        self::assertSame($template, $result);
    }

    #[Test]
    public function recursively_applies_substitution_to_nested_arrays(): void
    {
        $name = 'id';
        $value = '42';
        $placeholder = "{@{$name}}";
        $store = new CaptureStore();
        $store->set($name, $value);

        $result = $store->applyToData([
            'foo' => $placeholder,
            'nested' => ['bar' => $placeholder, 'list' => [$placeholder]],
        ]);

        self::assertSame([
            'foo' => $value,
            'nested' => ['bar' => $value, 'list' => [$value]],
        ], $result);
    }
}
