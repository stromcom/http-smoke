# stromcom/http-smoke

Professional, extensible HTTP smoke-testing toolkit for PHP 8.4+.

A fluent DSL for declaring HTTP smoke tests, parallel execution, capture chains
between requests, cookie-shared session flows, pluggable variable sources
(`.env`, JSON, OS env, your own), pluggable reporters (console, JSON, Markdown,
GitHub Actions step summary, your own), and a small DI container so any service
can be swapped without forking the package.

```bash
composer require --dev stromcom/http-smoke
```

```bash
vendor/bin/http-smoke dev
```

---

## Features

- **PHP 8.4+** — `readonly` classes, enums, strict types, no legacy cruft.
- **Fluent DSL** — declarative test definitions in plain PHP.
- **Parallel execution** — `curl_multi`-based, concurrency configurable.
- **Session chains** — share cookies across a sequence of requests, run sequentially.
- **Capture variables** — `captureJsonPath('hash', 'data.thread.hash')` then
  `{@hash}` in subsequent URLs / bodies / headers.
- **Variable substitution** — `{ENV_VAR}` placeholders resolved from `.env`,
  `smokeHttp.json`, OS env, CLI overrides — or any custom `VariableSourceInterface`.
- **Pluggable reporters** — console, JSON (canonical artefact), Markdown, GitHub
  step summary; add your own by implementing `ReporterInterface`.
- **Pluggable HTTP client** — default is curl_multi; implement
  `HttpClientInterface` to swap in Guzzle, mock, etc.
- **Retry** — per-test or per-group retry on any failure (useful for
  eventually-consistent endpoints), independent 5xx retry budget.
- **Circuit breaker** — per-group `maxFailures` skips remaining tests once a
  group is clearly broken.
- **PHPStan level max + strict rules**, no docblock noise.

---

## Quick start

### 1. Install

```bash
composer require --dev stromcom/http-smoke
```

### 2. Create test definitions

`tests/SmokeHttp/api-01-basic.php`:

```php
<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $suite->group('api.public', maxFailures: 3)
        ->baseUrl('{API_BASE_URL}')

        ->get('/ping')
            ->expectStatus(200)
            ->expectJsonPath('status', 'ok')

        ->get('/version')
            ->expectStatus(200)
            ->expectJsonHasKeys(['version', 'commit']);
};
```

### 3. Provide variables

Either via `.env.dev`:

```dotenv
API_BASE_URL=http://localhost:8080/api
```

…or `tests/smokeHttp.json`:

```json
{
    "dev":  { "API_BASE_URL": "http://localhost:8080/api" },
    "prod": { "API_BASE_URL": "https://example.com/api" }
}
```

### 4. Run

```bash
vendor/bin/http-smoke dev
vendor/bin/http-smoke prod --concurrency=20 --output-json=report.json
```

---

## CLI reference

```
http-smoke <environment> [options]

  --concurrency=N         Max parallel requests (default: 10)
  --config-dir=DIR        Test definitions directory (default: tests/SmokeHttp)
  --config=FILE           Path to smoke.config.php (default: ./smoke.config.php)
  --smoke-json=FILE       Path to smokeHttp.json (default: ./tests/smokeHttp.json)
  --env-file=FILE         Path to .env.<env> file
  --base-url=URL          Override APP_BASE_URL variable
  --var=KEY=VALUE         Set/override an arbitrary variable (repeatable)
  --filter=PATTERN        Only run files matching *PATTERN*
  --group=NAME            Only run group(s); supports wildcards (api.*)
  --output=FILE           Write Markdown report
  --output-json=FILE      Write canonical JSON report
  --no-console            Suppress console output
  --no-github-summary     Skip GITHUB_STEP_SUMMARY
  --verbose, -v           Show full request/response detail
  --insecure, -k          Skip TLS verification
  --help, -h
```

Exit codes: `0` success · `1` tests failed · `2` config error · `3` usage error.

---

## DSL cheat-sheet

```php
$suite->header('Authorization', '{API_TOKEN}'); // suite-level default header
$suite->asJson();                                // suite-level: send all bodies as JSON

$suite->group('api.users', maxFailures: 3)
    ->baseUrl('{API_BASE_URL}')
    ->header('X-Tenant', 'smoke')
    ->defaultTimeout(5)
    ->defaultRetryOnFailure(10, 50)              // up to 10 retries, 50 ms apart
    // ->defaultRetries(10, 50)                  // shorter alias for the above
    ->defaultAsJson()

    // Sessions: shared cookie jar, sequential execution, fail-fast
    ->session('user-lifecycle')
        ->post('/users/', ['email' => 'x@y.z'])
            ->expectStatus(201)
            ->captureJsonPath('userHash', 'data.hash')
        ->get('/users/{@userHash}/')
            ->expectStatus(200)
        ->delete('/users/{@userHash}/')
            ->expectStatus(204)
    ->endSession()

    // Independent (parallel) requests
    ->get('/users/')
        ->expectStatus(200)
        ->expectJson()
        ->expectJsonHasKeys(['data', 'meta.count'])
        ->expectJsonPath('status', 'success')

    ->put('{@externalUploadUrl}', file_get_contents('photo.jpg'))
        ->noGroupHeaders()                       // skip group Authorization header
        ->asJson(false)                          // raw body
        ->expectStatus(200)

    ->get('/dashboard')
        ->expectStatus(200)
        ->expectHtmlElement('h1', 'Dashboard')         // <h1>Dashboard</h1>
        ->expectHtmlElement('a', null, 'href', '/logout')
        ->expectHtmlElement('meta', null, 'name', 'viewport')

    ->get('/health')
        ->expectHeaderContains('Cache-Control', 'no-store')
        ->expect(fn (Stromcom\HttpSmoke\Http\Response $r) =>
            json_decode($r->body, true)['queue'] > 0 ? 'queue should be empty' : null
        );
```

### Available expectations

| Method | Purpose |
|---|---|
| `expectStatus(int)` | exact status code |
| `expectStatusOneOf(int, ...)` | one of several status codes |
| `expectRedirect(string)` | 3xx with `Location` header path-matching the URL |
| `expectContains(string)` / `expectNotContains(string)` | body substring |
| `expectJson()` | body must parse as JSON |
| `expectJsonHasKeys(array)` | dot-notation paths must exist |
| `expectJsonPath(string, mixed)` | dot-notation path equals value |
| `expectHtmlElement(tag, text?, attribute?, attributeValue?)` | HTML body must contain a matching `<tag>` (optionally with given attribute / text) |
| `expectHeaderContains(string, string)` | header value contains substring |
| `expect(Closure)` | custom callback returning `null` on success or a failure message |

### Captures

Use `captureJsonPath('name', 'data.x.y')` (or `captureHeader('name', 'X-Foo')`) on
any request, then reference `{@name}` in any later request's URL, body, or
header value.

---

## Configuration files

The package merges configuration from several layers (later wins):

1. Defaults
2. `smoke.config.php` (PHP — for registering custom services / closures)
3. `smokeHttp.json` (per-environment static values)
4. `.env.<environment>`
5. OS environment variables (`getenv()`)
6. CLI options (`--base-url`, `--var=KEY=VALUE`, …)

### `smokeHttp.json`

```json
{
    "dev":     { "API_BASE_URL": "http://localhost:8080/api" },
    "staging": { "API_BASE_URL": "https://staging.example.com/api" },
    "prod":    { "API_BASE_URL": "https://example.com/api" }
}
```

### `smoke.config.php`

```php
<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Config\SmokeConfig;
use Stromcom\HttpSmoke\Container\Container;
use Stromcom\HttpSmoke\Variable\Source\ArraySource;

return static function (SmokeConfig $config): void {
    $config->configDir         = __DIR__ . '/tests/SmokeHttp';
    $config->concurrency       = 10;
    $config->jsonOutputPath    = __DIR__ . '/build/smoke.json';
    $config->markdownOutputPath = __DIR__ . '/build/smoke.md';

    // Plug in additional variable sources
    $config->extraVariableSources[] = new ArraySource([
        'CUSTOM_KEY' => 'value',
    ]);

    // Override services in the DI container
    $config->configureContainer = function (Container $container): void {
        // $container->set(HttpClientInterface::class, fn() => new MyClient());
    };
};
```

---

## Extending

### Custom variable source

Implement `Stromcom\HttpSmoke\Variable\VariableSourceInterface`:

```php
final class AwsSecretsSource implements VariableSourceInterface
{
    public function get(string $name): ?string { /* ... */ }
    public function all(): array { /* ... */ }
}

// In smoke.config.php:
$config->extraVariableSources[] = new AwsSecretsSource(...);
```

### Custom reporter

Implement `Stromcom\HttpSmoke\Reporting\ReporterInterface` (`onStart`,
`onResult`, `onEnd`) and add it via `$config->extraReporters[] = new MyReporter()`.

### Custom HTTP client

Implement `Stromcom\HttpSmoke\Http\HttpClientInterface` and register in the
container via `$config->configureContainer`. Guzzle adapter is planned for v1.1.

---

## JSON report schema

The JSON report is the canonical machine-readable artefact. Markdown and GitHub
step-summary outputs are derived from it.

```json
{
  "meta": {
    "schema_version": 2,
    "environment": "prod",
    "generated_at": "2026-04-26T12:00:00+00:00",
    "duration_s": 4.521,
    "concurrency": 10,
    "summary": { "total": 15, "passed": 13, "failed": 1, "skipped": 1, "success": false }
  },
  "groups": [
    {
      "name": "api.users",
      "summary": { "total": 5, "passed": 4, "failed": 1, "skipped": 0 },
      "tests": [
        {
          "label": "POST /users/ – create",
          "method": "POST",
          "url": "https://example.com/api/users/",
          "status": "failed",
          "http_code": 500,
          "duration_ms": 234,
          "attempts": 1,
          "total_duration_ms": 234,
          "session": { "label": "user lifecycle" },
          "failures": ["Expected status 201, got 500"],
          "skip_reason": null,
          "chain_context": [/* preceding session steps */]
        }
      ]
    }
  ]
}
```

---

## Examples

See [`examples/`](./examples) for ready-to-run test suites and a sample
`smoke.config.php` / `smokeHttp.json`.

---

## License

MIT — see [`LICENSE`](./LICENSE).
