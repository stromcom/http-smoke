<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Definition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Assertion\HtmlElementAssertion;
use Stromcom\HttpSmoke\Assertion\StatusAssertion;
use Stromcom\HttpSmoke\Definition\Suite;
use Stromcom\HttpSmoke\Http\Method;

final class SuiteTest extends TestCase
{
    #[Test]
    public function group_resolves_relative_paths_against_its_base_url_and_inherits_suite_headers(): void
    {
        $authHeader = ['Authorization' => 'Bearer x'];
        $baseUrl = 'https://example.com/api';
        $usersPath = '/users/';
        $postBody = ['name' => 'a'];
        $suite = new Suite();
        $suite->header('Authorization', $authHeader['Authorization']);

        $suite->group('api', maxFailures: 2)
            ->baseUrl($baseUrl)
            ->get($usersPath)
                ->expectStatus(200)
            ->post($usersPath, $postBody)
                ->expectStatusOneOf(201, 200);

        $cases = $suite->getCasesByGroup();
        self::assertArrayHasKey('api', $cases);
        self::assertCount(2, $cases['api']);

        [$getCase, $postCase] = $cases['api'];

        self::assertSame(Method::GET, $getCase->method);
        self::assertSame($baseUrl . $usersPath, $getCase->url);
        self::assertSame($authHeader, $getCase->headers);
        self::assertCount(1, $getCase->assertions);
        self::assertInstanceOf(StatusAssertion::class, $getCase->assertions[0]);

        self::assertSame(Method::POST, $postCase->method);
        self::assertSame($postBody, $postCase->body);
    }

    #[Test]
    public function requests_inside_a_session_share_a_session_id_and_requests_outside_do_not(): void
    {
        $suite = new Suite();
        $suite->group('s')
            ->session('login')
                ->get('/a')->expectStatus(200)
                ->get('/b')->expectStatus(200)
            ->endSession()
            ->get('/c')->expectStatus(200);

        [$firstInSession, $secondInSession, $outsideSession] = $suite->getCasesByGroup()['s'];

        self::assertNotNull($firstInSession->sessionId);
        self::assertSame($firstInSession->sessionId, $secondInSession->sessionId);
        self::assertNull($outsideSession->sessionId);
    }

    #[Test]
    public function expect_html_element_attaches_html_element_assertion(): void
    {
        $suite = new Suite();
        $suite->group('g')
            ->get('/page')
                ->expectStatus(200)
                ->expectHtmlElement('h1', 'Welcome', 'class', 'title');

        $case = $suite->getCasesByGroup()['g'][0];

        $htmlAssertions = array_values(array_filter(
            $case->assertions,
            static fn($assertion): bool => $assertion instanceof HtmlElementAssertion,
        ));
        self::assertCount(1, $htmlAssertions);
        self::assertSame('h1', $htmlAssertions[0]->tag);
        self::assertSame('Welcome', $htmlAssertions[0]->text);
        self::assertSame('class', $htmlAssertions[0]->attribute);
        self::assertSame('title', $htmlAssertions[0]->attributeValue);
    }

    #[Test]
    public function default_retries_is_alias_for_default_retry_on_failure(): void
    {
        $suite = new Suite();
        $suite->group('g')
            ->defaultRetries(4, 25)
            ->get('/x')->expectStatus(200);

        $case = $suite->getCasesByGroup()['g'][0];

        self::assertSame(4, $case->retryOnFailure);
        self::assertSame(25, $case->retryDelayMs);
    }

    #[Test]
    public function captures_are_attached_to_the_case_they_were_declared_on(): void
    {
        $captureName = 'id';
        $suite = new Suite();
        $suite->group('g')
            ->post('/x', [])
                ->expectStatus(201)
                ->captureJsonPath($captureName, 'data.id');

        $case = $suite->getCasesByGroup()['g'][0];

        self::assertCount(1, $case->captures);
        self::assertSame($captureName, $case->captures[0]->name());
    }
}
