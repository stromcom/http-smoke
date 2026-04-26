<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Assertion;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Assertion\BodyContainsAssertion;
use Stromcom\HttpSmoke\Assertion\HeaderContainsAssertion;
use Stromcom\HttpSmoke\Assertion\JsonAssertion;
use Stromcom\HttpSmoke\Assertion\JsonHasKeysAssertion;
use Stromcom\HttpSmoke\Assertion\JsonPathAssertion;
use Stromcom\HttpSmoke\Assertion\RedirectAssertion;
use Stromcom\HttpSmoke\Assertion\StatusAssertion;
use Stromcom\HttpSmoke\Http\Response;

final class AssertionsTest extends TestCase
{
    /**
     * @param list<int> $allowed
     */
    #[Test]
    #[TestWith([200, [200], null])]
    #[TestWith([201, [200, 201, 204], null])]
    #[TestWith([500, [200], 'Expected status 200, got 500'])]
    #[TestWith([500, [200, 201, 204], 'Expected status one of [200, 201, 204], got 500'])]
    public function status_assertion_compares_response_code_against_allowed_set(
        int $actualStatus,
        array $allowed,
        ?string $expectedError,
    ): void {
        $assertion = new StatusAssertion($allowed);

        $error = $assertion->evaluate(self::response($actualStatus));

        self::assertSame($expectedError, $error);
    }

    #[Test]
    public function json_assertion_passes_for_valid_json_body(): void
    {
        $assertion = new JsonAssertion();
        $response = self::response(status: 200, body: '{"a":1}');

        $error = $assertion->evaluate($response);

        self::assertNull($error);
    }

    #[Test]
    public function json_assertion_fails_for_non_json_body(): void
    {
        $assertion = new JsonAssertion();
        $response = self::response(status: 200, body: 'not-json');

        $error = $assertion->evaluate($response);

        self::assertNotNull($error);
    }

    #[Test]
    public function json_has_keys_passes_when_all_paths_resolve(): void
    {
        $assertion = new JsonHasKeysAssertion(['data.id', 'meta.count']);
        $response = self::response(status: 200, body: '{"data":{"id":42},"meta":{"count":1}}');

        $error = $assertion->evaluate($response);

        self::assertNull($error);
    }

    #[Test]
    public function json_has_keys_fails_when_a_path_is_missing(): void
    {
        $assertion = new JsonHasKeysAssertion(['data.missing']);
        $response = self::response(status: 200, body: '{"data":{"id":42}}');

        $error = $assertion->evaluate($response);

        self::assertNotNull($error);
    }

    #[Test]
    #[TestWith(['status', 'ok', true])]
    #[TestWith(['status', 'fail', false])]
    #[TestWith(['count', 3, true])]
    #[TestWith(['count', 4, false])]
    public function json_path_assertion_matches_value_at_path(string $path, mixed $expected, bool $shouldPass): void
    {
        $assertion = new JsonPathAssertion($path, $expected);
        $response = self::response(status: 200, body: '{"status":"ok","count":3}');

        $error = $assertion->evaluate($response);

        self::assertErrorMatches($shouldPass, $error);
    }

    #[Test]
    #[TestWith(['hello world', 'world', false, true])]
    #[TestWith(['hello world', 'missing', false, false])]
    #[TestWith(['hello world', 'hello', true, false])]
    public function body_contains_assertion_handles_match_and_negation(
        string $body,
        string $needle,
        bool $negate,
        bool $shouldPass,
    ): void {
        $assertion = new BodyContainsAssertion($needle, negate: $negate);
        $response = self::response(status: 200, body: $body);

        $error = $assertion->evaluate($response);

        self::assertErrorMatches($shouldPass, $error);
    }

    #[Test]
    #[TestWith(['Content-Type', 'application/json', true])]
    #[TestWith(['Content-Type', 'text/html', false])]
    #[TestWith(['X-Missing', 'anything', false])]
    public function header_contains_assertion_is_case_insensitive_on_name(
        string $headerName,
        string $needle,
        bool $shouldPass,
    ): void {
        $assertion = new HeaderContainsAssertion($headerName, $needle);
        $response = self::response(status: 200, headers: ['content-type' => 'application/json; charset=utf-8']);

        $error = $assertion->evaluate($response);

        self::assertErrorMatches($shouldPass, $error);
    }

    #[Test]
    #[TestWith(['/dashboard', true])]
    #[TestWith(['https://x.example/dashboard', true])]
    #[TestWith(['/other', false])]
    public function redirect_assertion_compares_location_header(string $expectedLocation, bool $shouldPass): void
    {
        $assertion = new RedirectAssertion($expectedLocation);
        $response = self::response(status: 302, headers: ['location' => '/dashboard/']);

        $error = $assertion->evaluate($response);

        self::assertErrorMatches($shouldPass, $error);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function response(int $status, string $body = '', array $headers = []): Response
    {
        return new Response($status, $body, $headers, 0.1);
    }

    private static function assertErrorMatches(bool $shouldPass, ?string $error): void
    {
        if ($shouldPass) {
            self::assertNull($error);
        } else {
            self::assertNotNull($error);
        }
    }
}
