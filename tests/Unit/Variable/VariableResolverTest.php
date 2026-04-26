<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Exception\VariableNotFoundException;
use Stromcom\HttpSmoke\Variable\Source\ArraySource;
use Stromcom\HttpSmoke\Variable\VariableResolver;

#[CoversClass(VariableResolver::class)]
final class VariableResolverTest extends TestCase
{
    #[Test]
    public function resolves_multiple_placeholders_within_a_single_string(): void
    {
        $base = 'https://example.com';
        $path = '/api';
        $resolver = self::resolverWith(['BASE' => $base, 'PATH' => $path]);

        self::assertSame($base . $path, $resolver->resolve('{BASE}{PATH}'));
    }

    #[Test]
    public function later_source_overrides_earlier_source_for_the_same_key(): void
    {
        $key = 'X';
        $winning = 'second';
        $resolver = new VariableResolver();
        $resolver->addSource(new ArraySource([$key => 'first']));
        $resolver->addSource(new ArraySource([$key => $winning]));

        self::assertSame($winning, $resolver->resolve("{{$key}}"));
    }

    #[Test]
    public function throws_when_a_referenced_variable_is_not_defined_in_any_source(): void
    {
        $resolver = new VariableResolver();

        $this->expectException(VariableNotFoundException::class);

        $resolver->resolve('{MISSING}');
    }

    #[Test]
    public function resolve_url_collapses_double_slashes_introduced_by_concatenation(): void
    {
        $resolver = self::resolverWith(['BASE' => 'https://example.com/']);

        self::assertSame('https://example.com/users/', $resolver->resolveUrl('{BASE}/users/'));
    }

    /**
     * @param array<string, string> $vars
     */
    private static function resolverWith(array $vars): VariableResolver
    {
        $resolver = new VariableResolver();
        $resolver->addSource(new ArraySource($vars));

        return $resolver;
    }
}
