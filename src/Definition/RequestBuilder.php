<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Definition;

use Closure;
use Stromcom\HttpSmoke\Assertion\AssertionInterface;
use Stromcom\HttpSmoke\Assertion\BodyContainsAssertion;
use Stromcom\HttpSmoke\Assertion\CallbackAssertion;
use Stromcom\HttpSmoke\Assertion\HeaderContainsAssertion;
use Stromcom\HttpSmoke\Assertion\HtmlElementAssertion;
use Stromcom\HttpSmoke\Assertion\JsonAssertion;
use Stromcom\HttpSmoke\Assertion\JsonCountAssertion;
use Stromcom\HttpSmoke\Assertion\JsonHasKeysAssertion;
use Stromcom\HttpSmoke\Assertion\JsonPathAssertion;
use Stromcom\HttpSmoke\Assertion\RedirectAssertion;
use Stromcom\HttpSmoke\Assertion\StatusAssertion;
use Stromcom\HttpSmoke\Capture\CaptureInterface;
use Stromcom\HttpSmoke\Capture\HeaderCapture;
use Stromcom\HttpSmoke\Capture\JsonPathCapture;
use Stromcom\HttpSmoke\Http\Method;
use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Support\Comparator;

final class RequestBuilder
{
    public Method $method = Method::GET;

    public string $url = '';

    /** @var array<array-key, mixed>|string|null */
    public array|string|null $body = null;

    /** @var array<string, string> */
    public array $headers = [];

    public ?bool $sendAsJson = null;

    public ?int $timeoutSeconds = null;

    public ?int $retryOnFailure = null;

    public ?int $retryDelayMs = null;

    public ?int $retryOn5xx = null;

    public ?string $label = null;

    public bool $skipGroupHeaders = false;

    public ?int $expectedStatus = null;

    /** @var list<int> */
    public array $expectedStatusOneOf = [];

    public ?string $expectedRedirectUrl = null;

    /** @var list<string> */
    public array $jsonHasKeys = [];

    public bool $expectJson = false;

    /** @var array<string, mixed> */
    public array $jsonPathValues = [];

    /** @var list<array{path: string, expected: int, comparator: Comparator}> */
    public array $jsonCounts = [];

    public ?string $bodyContains = null;

    public ?string $bodyNotContains = null;

    public ?string $headerName = null;

    public ?string $headerContains = null;

    public ?Closure $callback = null;

    /** @var list<array{tag: string, text: ?string, attribute: ?string, attributeValue: ?string}> */
    public array $htmlElements = [];

    /** @var list<CaptureInterface> */
    public array $captures = [];

    /**
     * @return list<AssertionInterface>
     */
    public function buildAssertions(): array
    {
        $assertions = [];

        if ($this->expectedStatusOneOf !== []) {
            $assertions[] = new StatusAssertion($this->expectedStatusOneOf);
        } elseif ($this->expectedStatus !== null) {
            $assertions[] = new StatusAssertion([$this->expectedStatus]);
        }

        if ($this->expectedRedirectUrl !== null) {
            $assertions[] = new RedirectAssertion($this->expectedRedirectUrl);
        }

        if ($this->bodyContains !== null) {
            $assertions[] = new BodyContainsAssertion($this->bodyContains);
        }

        if ($this->bodyNotContains !== null) {
            $assertions[] = new BodyContainsAssertion($this->bodyNotContains, negate: true);
        }

        if ($this->expectJson) {
            $assertions[] = new JsonAssertion();
        }

        if ($this->jsonHasKeys !== []) {
            $assertions[] = new JsonHasKeysAssertion($this->jsonHasKeys);
        }

        foreach ($this->jsonPathValues as $path => $value) {
            $assertions[] = new JsonPathAssertion($path, $value);
        }

        foreach ($this->jsonCounts as $count) {
            $assertions[] = new JsonCountAssertion($count['path'], $count['expected'], $count['comparator']);
        }

        if ($this->headerName !== null && $this->headerContains !== null) {
            $assertions[] = new HeaderContainsAssertion($this->headerName, $this->headerContains);
        }

        foreach ($this->htmlElements as $element) {
            $assertions[] = new HtmlElementAssertion(
                $element['tag'],
                $element['text'],
                $element['attribute'],
                $element['attributeValue'],
            );
        }

        if ($this->callback !== null) {
            $callback = $this->callback;
            $assertions[] = new CallbackAssertion(static function (Response $r) use ($callback): ?string {
                $result = $callback($r);

                return is_string($result) ? $result : null;
            });
        }

        return $assertions;
    }

    public function addJsonPathCapture(string $name, string $path): void
    {
        $this->captures[] = new JsonPathCapture($name, $path);
    }

    public function addHeaderCapture(string $name, string $headerName): void
    {
        $this->captures[] = new HeaderCapture($name, $headerName);
    }
}
