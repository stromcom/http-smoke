<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;

final class JsonAssertion implements AssertionInterface
{
    public function evaluate(Response $response): ?string
    {
        json_decode($response->body);
        if (json_last_error() === JSON_ERROR_NONE) {
            return null;
        }

        $preview = mb_substr($response->body, 0, 200);

        return 'Response is not valid JSON: ' . json_last_error_msg() . " (preview: \"{$preview}…\")";
    }
}
