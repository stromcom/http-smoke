<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;

final readonly class HeaderContainsAssertion implements AssertionInterface
{
    public function __construct(
        public string $name,
        public string $needle,
    ) {}

    public function evaluate(Response $response): ?string
    {
        $value = $response->getHeader($this->name);
        if ($value === null) {
            return "Response header \"{$this->name}\" not found";
        }

        if (str_contains($value, $this->needle)) {
            return null;
        }

        return "Header \"{$this->name}\" does not contain \"{$this->needle}\" (got: \"{$value}\")";
    }
}
