<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Variable\VariableResolver;

final readonly class RedirectAssertion implements AssertionInterface, ResolvableAssertion
{
    public function __construct(
        public string $expectedUrl,
    ) {}

    public function withResolver(VariableResolver $variables): self
    {
        return new self($variables->resolveUrl($this->expectedUrl));
    }

    public function evaluate(Response $response): ?string
    {
        if ($response->statusCode < 300 || $response->statusCode >= 400) {
            return "Expected redirect (3xx), got {$response->statusCode}";
        }

        $actual = $response->getRedirectUrl();
        if ($actual === null) {
            return "Expected redirect to {$this->expectedUrl}, but no Location header";
        }

        if (self::pathsMatch($this->expectedUrl, $actual)) {
            return null;
        }

        return "Expected redirect to {$this->expectedUrl}, got {$actual}";
    }

    private static function pathsMatch(string $expected, string $actual): bool
    {
        $expected = rtrim($expected, '/');
        $actual = rtrim($actual, '/');

        if ($expected === $actual) {
            return true;
        }

        return rtrim(self::extractPath($expected), '/') === rtrim(self::extractPath($actual), '/');
    }

    private static function extractPath(string $url): string
    {
        if (preg_match('#^https?://#', $url) === 1) {
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) ? $path : '/';
        }

        return $url;
    }
}
