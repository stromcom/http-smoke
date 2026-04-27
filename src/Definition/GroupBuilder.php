<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Definition;

use Closure;
use Stromcom\HttpSmoke\Http\Method;
use Stromcom\HttpSmoke\Support\Comparator;

final class GroupBuilder
{
    /** @var list<TestCase> */
    private array $cases = [];

    private string $baseUrlPrefix = '';

    private int $groupTimeoutSeconds = 10;

    private int $groupRetryOnFailure = 0;

    private int $groupRetryDelayMs = 50;

    private int $groupRetryOn5xx = 0;

    /** @var array<string, string> */
    private array $groupHeaders = [];

    private bool $groupAsJson = false;

    private ?string $currentSessionId = null;

    private ?RequestBuilder $pending = null;

    public function __construct(
        private readonly string $groupName,
        private readonly Suite $suite,
    ) {
        $this->groupHeaders = $suite->getDefaultHeaders();
        $this->groupAsJson = $suite->getDefaultAsJson();
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function header(string $name, ?string $value): self
    {
        if ($value === null) {
            unset($this->groupHeaders[$name]);
        } else {
            $this->groupHeaders[$name] = $value;
        }

        return $this;
    }

    public function baseUrl(string $prefix): self
    {
        $this->baseUrlPrefix = rtrim($prefix, '/');

        return $this;
    }

    public function defaultTimeout(int $seconds): self
    {
        $this->groupTimeoutSeconds = max(1, $seconds);

        return $this;
    }

    public function defaultRetryOn5xx(int $count): self
    {
        $this->groupRetryOn5xx = max(0, $count);

        return $this;
    }

    public function defaultRetries(int $count, int $delayMs = 50): self
    {
        return $this->defaultRetryOnFailure($count, $delayMs);
    }

    public function defaultRetryOnFailure(int $attempts, int $delayMs = 50): self
    {
        $this->groupRetryOnFailure = max(0, $attempts);
        $this->groupRetryDelayMs = max(1, $delayMs);

        return $this;
    }

    public function defaultAsJson(bool $value = true): self
    {
        $this->groupAsJson = $value;

        return $this;
    }

    public function session(string $label = 'Session'): self
    {
        $this->commit();
        $sanitized = preg_replace('/[^a-z0-9]/i', '_', $label) ?? 'Session';
        $this->currentSessionId = 'sess_' . bin2hex(random_bytes(8)) . '_' . $sanitized;

        return $this;
    }

    public function endSession(): self
    {
        $this->commit();
        $this->currentSessionId = null;

        return $this;
    }

    public function get(string $url): self
    {
        return $this->startRequest(Method::GET, $url, null);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function post(string $url, array $data = []): self
    {
        return $this->startRequest(Method::POST, $url, $data);
    }

    /**
     * @param array<array-key, mixed>|string $data
     */
    public function put(string $url, array|string $data = []): self
    {
        $body = is_string($data) ? $data : ($data !== [] ? $data : null);

        return $this->startRequest(Method::PUT, $url, $body);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function patch(string $url, array $data = []): self
    {
        return $this->startRequest(Method::PATCH, $url, $data !== [] ? $data : null);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function delete(string $url, array $data = []): self
    {
        return $this->startRequest(Method::DELETE, $url, $data !== [] ? $data : null);
    }

    public function head(string $url): self
    {
        return $this->startRequest(Method::HEAD, $url, null);
    }

    public function options(string $url): self
    {
        return $this->startRequest(Method::OPTIONS, $url, null);
    }

    public function expectStatus(int $code): self
    {
        $this->requirePending()->expectedStatus = $code;

        return $this;
    }

    public function expectStatusOneOf(int ...$codes): self
    {
        $this->requirePending()->expectedStatusOneOf = array_values($codes);

        return $this;
    }

    public function expectRedirect(string $url): self
    {
        $pending = $this->requirePending();
        $pending->expectedRedirectUrl = $this->resolveUrl($url);
        $pending->expectedStatus = 302;

        return $this;
    }

    public function expectContains(string $text): self
    {
        $this->requirePending()->bodyContains = $text;

        return $this;
    }

    public function expectNotContains(string $text): self
    {
        $this->requirePending()->bodyNotContains = $text;

        return $this;
    }

    public function expectJson(): self
    {
        $this->requirePending()->expectJson = true;

        return $this;
    }

    /**
     * @param list<string> $keys
     */
    public function expectJsonHasKeys(array $keys): self
    {
        $pending = $this->requirePending();
        $pending->expectJson = true;
        $pending->jsonHasKeys = array_values(array_unique([...$pending->jsonHasKeys, ...$keys]));

        return $this;
    }

    public function expectJsonPath(string $path, mixed $value): self
    {
        $pending = $this->requirePending();
        $pending->expectJson = true;
        $pending->jsonPathValues[$path] = $value;

        return $this;
    }

    public function expectJsonCount(string $path, int $expected, Comparator $comparator = Comparator::Equal): self
    {
        $pending = $this->requirePending();
        $pending->expectJson = true;
        $pending->jsonCounts[] = ['path' => $path, 'expected' => $expected, 'comparator' => $comparator];

        return $this;
    }

    public function expectJsonCountGreaterThan(string $path, int $expected): self
    {
        return $this->expectJsonCount($path, $expected, Comparator::GreaterThan);
    }

    public function expectJsonCountLessThan(string $path, int $expected): self
    {
        return $this->expectJsonCount($path, $expected, Comparator::LessThan);
    }

    public function expectJsonCountAtLeast(string $path, int $expected): self
    {
        return $this->expectJsonCount($path, $expected, Comparator::GreaterThanOrEqual);
    }

    public function expectJsonCountAtMost(string $path, int $expected): self
    {
        return $this->expectJsonCount($path, $expected, Comparator::LessThanOrEqual);
    }

    public function expectHtmlElement(
        string $tag,
        ?string $text = null,
        ?string $attribute = null,
        ?string $attributeValue = null,
    ): self {
        $this->requirePending()->htmlElements[] = [
            'tag' => $tag,
            'text' => $text,
            'attribute' => $attribute,
            'attributeValue' => $attributeValue,
        ];

        return $this;
    }

    public function expectHeaderContains(string $name, string $value): self
    {
        $pending = $this->requirePending();
        $pending->headerName = $name;
        $pending->headerContains = $value;

        return $this;
    }

    /**
     * @param Closure(\Stromcom\HttpSmoke\Http\Response): ?string $callback
     */
    public function expect(Closure $callback): self
    {
        $this->requirePending()->callback = $callback;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->requirePending()->timeoutSeconds = max(1, $seconds);

        return $this;
    }

    public function retryOnFailure(int $attempts, int $delayMs = 50): self
    {
        $pending = $this->requirePending();
        $pending->retryOnFailure = max(0, $attempts);
        $pending->retryDelayMs = max(1, $delayMs);

        return $this;
    }

    public function retryOn5xx(int $count): self
    {
        $this->requirePending()->retryOn5xx = max(0, $count);

        return $this;
    }

    public function label(string $label): self
    {
        $this->requirePending()->label = $label;

        return $this;
    }

    public function asJson(bool $value = true): self
    {
        $this->requirePending()->sendAsJson = $value;

        return $this;
    }

    public function noGroupHeaders(): self
    {
        $this->requirePending()->skipGroupHeaders = true;

        return $this;
    }

    public function captureJsonPath(string $varName, string $jsonPath): self
    {
        $this->requirePending()->addJsonPathCapture($varName, $jsonPath);

        return $this;
    }

    public function captureHeader(string $varName, string $headerName): self
    {
        $this->requirePending()->addHeaderCapture($varName, $headerName);

        return $this;
    }

    public function group(string $name, int $maxFailures = 3): self
    {
        $this->commit();

        return $this->suite->group($name, $maxFailures);
    }

    /**
     * @return list<TestCase>
     */
    public function finalize(): array
    {
        $this->commit();

        return $this->cases;
    }

    /**
     * @param array<array-key, mixed>|string|null $body
     */
    private function startRequest(Method $method, string $url, array|string|null $body): self
    {
        $this->commit();
        $this->pending = new RequestBuilder();
        $this->pending->method = $method;
        $this->pending->url = $this->resolveUrl($url);
        $this->pending->body = $body;

        return $this;
    }

    private function requirePending(): RequestBuilder
    {
        if ($this->pending === null) {
            throw new \LogicException('No pending request — call get()/post()/put()/patch()/delete()/head()/options() first.');
        }

        return $this->pending;
    }

    private function commit(): void
    {
        if ($this->pending === null) {
            return;
        }

        $p = $this->pending;
        $this->pending = null;

        $headers = $p->skipGroupHeaders ? [] : $this->groupHeaders;
        if ($p->headers !== []) {
            $headers = [...$headers, ...$p->headers];
        }

        $this->cases[] = new TestCase(
            group: $this->groupName,
            method: $p->method,
            url: $p->url,
            headers: $headers,
            body: $p->body,
            sendAsJson: $p->sendAsJson ?? $this->groupAsJson,
            timeoutSeconds: $p->timeoutSeconds ?? $this->groupTimeoutSeconds,
            retryOnFailure: $p->retryOnFailure ?? $this->groupRetryOnFailure,
            retryDelayMs: $p->retryDelayMs ?? $this->groupRetryDelayMs,
            retryOn5xx: $p->retryOn5xx ?? $this->groupRetryOn5xx,
            label: $p->label,
            sessionId: $this->currentSessionId,
            assertions: $p->buildAssertions(),
            captures: $p->captures,
        );
    }

    private function resolveUrl(string $url): string
    {
        if ($this->baseUrlPrefix === '') {
            return $url;
        }
        if (preg_match('#^https?://#', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '{')) {
            return $url;
        }

        return rtrim($this->baseUrlPrefix, '/') . '/' . ltrim($url, '/');
    }
}
