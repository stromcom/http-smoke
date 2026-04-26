<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;

interface AssertionInterface
{
    /**
     * @return string|null Returns null on success, or a failure message.
     */
    public function evaluate(Response $response): ?string;
}
