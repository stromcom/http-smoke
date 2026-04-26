<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;

final readonly class StatusAssertion implements AssertionInterface
{
    /**
     * @param list<int> $allowed
     */
    public function __construct(
        public array $allowed,
    ) {}

    public function evaluate(Response $response): ?string
    {
        if (in_array($response->statusCode, $this->allowed, true)) {
            return null;
        }

        if (count($this->allowed) === 1) {
            return "Expected status {$this->allowed[0]}, got {$response->statusCode}";
        }

        $list = implode(', ', $this->allowed);

        return "Expected status one of [{$list}], got {$response->statusCode}";
    }
}
