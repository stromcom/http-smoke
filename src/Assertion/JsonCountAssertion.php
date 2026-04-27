<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Support\Comparator;
use Stromcom\HttpSmoke\Support\JsonDotPath;

final readonly class JsonCountAssertion implements AssertionInterface
{
    public function __construct(
        public string $path,
        public int $expected,
        public Comparator $comparator = Comparator::Equal,
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

        $value = JsonDotPath::get($data, $this->path);
        if (!is_array($value)) {
            return "JSON \"{$this->path}\": expected array, got " . get_debug_type($value);
        }

        $actual = count($value);
        if ($this->comparator->compare($actual, $this->expected)) {
            return null;
        }

        return "JSON count \"{$this->path}\": expected {$this->comparator->value} {$this->expected}, got {$actual}";
    }
}
