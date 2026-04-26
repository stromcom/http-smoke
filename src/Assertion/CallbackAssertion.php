<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Closure;
use Stromcom\HttpSmoke\Http\Response;

final readonly class CallbackAssertion implements AssertionInterface
{
    /**
     * @param Closure(Response): ?string $callback
     */
    public function __construct(
        private Closure $callback,
    ) {}

    public function evaluate(Response $response): ?string
    {
        return ($this->callback)($response);
    }
}
