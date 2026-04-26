<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Http\Response;

final readonly class BodyContainsAssertion implements AssertionInterface
{
    public function __construct(
        public string $needle,
        public bool $negate = false,
    ) {}

    public function evaluate(Response $response): ?string
    {
        $contains = str_contains($response->body, $this->needle);

        if ($this->negate) {
            return $contains ? "Body must NOT contain \"{$this->needle}\"" : null;
        }

        if ($contains) {
            return null;
        }

        $preview = mb_substr($response->body, 0, 200);

        return "Body does not contain \"{$this->needle}\" (preview: \"{$preview}…\")";
    }
}
