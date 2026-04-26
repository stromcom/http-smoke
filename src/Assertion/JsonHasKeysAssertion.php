<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Support\JsonDotPath;

final readonly class JsonHasKeysAssertion implements AssertionInterface
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths,
    ) {}

    public function evaluate(Response $response): ?string
    {
        $data = JsonDotPath::decode($response->body);
        if ($data === null) {
            return null;
        }

        $missing = [];
        foreach ($this->paths as $path) {
            if (!JsonDotPath::exists($data, $path)) {
                $missing[] = $path;
            }
        }

        if ($missing === []) {
            return null;
        }

        return 'JSON keys not found: ' . implode(', ', array_map(static fn(string $p): string => "\"{$p}\"", $missing));
    }
}
