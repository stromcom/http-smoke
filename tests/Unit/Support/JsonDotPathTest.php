<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Support\JsonDotPath;

final class JsonDotPathTest extends TestCase
{
    private const array DATA = [
        'data' => [
            'status' => 'ok',
            'items' => [
                ['name' => 'first', 'tags' => ['a', 'b']],
                ['name' => 'second'],
            ],
        ],
    ];

    #[Test]
    #[TestWith(['data.status', self::DATA['data']['status']])]
    #[TestWith(['data.items[0].name', self::DATA['data']['items'][0]['name']])]
    #[TestWith(['data.items[1].name', self::DATA['data']['items'][1]['name']])]
    #[TestWith(['data.items[0].tags[1]', self::DATA['data']['items'][0]['tags'][1]])]
    public function get_resolves_dot_and_bracket_paths(string $path, mixed $expected): void
    {
        self::assertSame($expected, JsonDotPath::get(self::DATA, $path));
        self::assertTrue(JsonDotPath::exists(self::DATA, $path));
    }

    #[Test]
    #[TestWith(['data.items[2].name'])]
    #[TestWith(['data.items[0].missing'])]
    #[TestWith(['data.items[0].tags[5]'])]
    #[TestWith(['nope'])]
    public function get_returns_null_for_missing_paths(string $path): void
    {
        self::assertNull(JsonDotPath::get(self::DATA, $path));
        self::assertFalse(JsonDotPath::exists(self::DATA, $path));
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['data.'])]
    #[TestWith(['data..items'])]
    #[TestWith(['data.items[]'])]
    #[TestWith(['data.items[abc]'])]
    #[TestWith(['data.items[0'])]
    public function malformed_paths_are_treated_as_missing(string $path): void
    {
        self::assertNull(JsonDotPath::get(self::DATA, $path));
        self::assertFalse(JsonDotPath::exists(self::DATA, $path));
    }
}
