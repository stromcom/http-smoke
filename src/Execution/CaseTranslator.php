<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Execution;

use Stromcom\HttpSmoke\Assertion\AssertionInterface;
use Stromcom\HttpSmoke\Assertion\ResolvableAssertion;
use Stromcom\HttpSmoke\Capture\CaptureStore;
use Stromcom\HttpSmoke\Definition\TestCase;
use Stromcom\HttpSmoke\Http\Request;
use Stromcom\HttpSmoke\Variable\VariableResolver;

final readonly class CaseTranslator
{
    public function __construct(
        private VariableResolver $variables,
        private CaptureStore $captures,
        private bool $insecureTls = false,
        private string $userAgent = 'StromcomSmokeTest/1.0',
    ) {}

    public function toRequest(TestCase $case, ?string $cookieJarPath = null): Request
    {
        $url = $this->variables->resolveUrl($this->captures->apply($case->url));

        $headers = [];
        foreach ($case->headers as $name => $value) {
            $headers[$name] = $this->variables->resolve($this->captures->apply($value));
        }

        $body = $this->captures->applyToData($case->body);

        return new Request(
            method: $case->method,
            url: $url,
            headers: $headers,
            body: $body,
            sendAsJson: $case->sendAsJson,
            timeoutSeconds: $case->timeoutSeconds,
            cookieJarPath: $cookieJarPath,
            insecureTls: $this->insecureTls,
            userAgent: $this->userAgent,
        );
    }

    /**
     * @return list<AssertionInterface>
     */
    public function resolveAssertions(TestCase $case): array
    {
        $resolved = [];
        foreach ($case->assertions as $assertion) {
            $resolved[] = $assertion instanceof ResolvableAssertion
                ? $assertion->withResolver($this->variables)
                : $assertion;
        }

        return $resolved;
    }
}
