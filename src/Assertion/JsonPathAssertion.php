<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Support\JsonDotPath;

final readonly class JsonPathAssertion implements AssertionInterface
{
    public function __construct(
        public string $path,
        public mixed $expected,
    ) {}

    public function evaluate(Response $response): ?string
    {
        $data = JsonDotPath::decode($response->body);
        if ($data === null) {
            return null;
        }

        if (!JsonDotPath::exists($data, $this->path)) {
            return "JSON path \"{$this->path}\" not found";
        }

        $actual = JsonDotPath::get($data, $this->path);
        if ($actual === $this->expected) {
            return null;
        }

        $actualJson = json_encode($actual, JSON_UNESCAPED_UNICODE);
        $expectedJson = json_encode($this->expected, JSON_UNESCAPED_UNICODE);

        return "JSON \"{$this->path}\": expected {$expectedJson}, got {$actualJson}";
    }
}
