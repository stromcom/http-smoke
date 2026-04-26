# Architecture

PSR-4 root: `Stromcom\HttpSmoke\` → `src/`.

## Layers (top-down: how a single test flows)

```
CLI input  →  Console     →  Config        →  Discovery   →  Definition
                              loaders          finds *.php     (Suite + GroupBuilder)
                              merge layers     test files      builds TestCase[]
                                                                    │
                                                                    ▼
                                              Execution\Runner ── segments cases
                                                                    │
                                              ┌─────────────────────┼─────────────────────┐
                                              ▼                                           ▼
                                        Parallel batch                              Session chain
                                       (CurlMultiClient)                           (sequential, cookie jar)
                                              │                                           │
                                              └────────────────┬──────────────────────────┘
                                                               ▼
                                          CaseTranslator ─ resolves {VAR} (VariableResolver)
                                                          and {@capture} (CaptureStore)
                                                          on URL / body / headers
                                                               │
                                                               ▼
                                                    HttpClientInterface  ── sends Request
                                                               │
                                                               ▼
                                                          Response  ──► Assertions evaluate()
                                                                   ──► Captures extract() → CaptureStore
                                                               │
                                                               ▼
                                                       Result → Report → Reporters
                                                                          (Console / Json / Markdown / GitHub)
```

## Domain folders

| Folder | Purpose |
|---|---|
| `Definition/` | Fluent DSL: `Suite`, `GroupBuilder`, `RequestBuilder`, immutable `TestCase`, `GroupConfig`. Builds the test plan; nothing here knows how requests are sent. |
| `Assertion/` | `AssertionInterface` + concrete impls (Status, Json, JsonPath, JsonHasKeys, BodyContains, HeaderContains, Redirect, HtmlElement, Callback). Each `evaluate(Response)` returns `null` (pass) or a failure message. `ResolvableAssertion` is an opt-in sub-interface for assertions whose args contain `{KEY}` placeholders — `CaseTranslator::resolveAssertions()` rebuilds them with the runtime `VariableResolver` before `evaluate()` is called. |
| `Capture/` | `CaptureInterface` (JsonPath, Header) + `CaptureStore` (runtime `{@name}` substitution). |
| `Variable/` | `VariableResolver` + `VariableSourceInterface` (Array, EnvFile, JsonFile, Getenv). Layered, last-added-wins. Throws `VariableNotFoundException` for unresolved `{KEY}`. |
| `Http/` | `HttpClientInterface` + immutable `Request`/`Response` VO + `Curl\CurlMultiClient` (default; parallel via `curl_multi_*`, single via `curl_exec`, cookie jar support). |
| `Execution/` | `Runner` (orchestrator), `Result`, `Report`, `CaseTranslator` (translates `TestCase` → `Request`, applying variables + captures). |
| `Reporting/` | `ReporterInterface` (`onStart`/`onResult`/`onEnd`) + `Console`, `Json`, `Markdown`, `GithubSummary`, `Null`. JSON is canonical; Markdown + GitHub summary derive from it. |
| `Discovery/` | `ConfigDiscovery` — recursive `*.php` walk + filename filter. Each definition file returns `Closure(Suite): void`. |
| `Config/` | `SmokeConfig` (root config DTO) + `SmokeConfigLoader` (loads `smoke.config.php`). |
| `Container/` | Lightweight PSR-11 container + `ServiceFactory::build()` wires everything. `getTyped(class)` for type-narrowed retrieval. |
| `Console/` | `InputParser`, `ParsedInput`, `RunCommand` (the orchestrator called from `bin/http-smoke`), `ExitCode` enum. |
| `Exception/`, `Support/` | `SmokeException` base + specifics; `JsonDotPath` helper used by JSON assertions/captures. |

## Key extension points (interfaces)

Every one of these is publicly extensible without forking. Register custom impls
via `smoke.config.php` (`extraVariableSources[]`, `extraReporters[]`,
`configureContainer = function(Container $c) { ... }`).

- `Variable\VariableSourceInterface`
- `Http\HttpClientInterface`
- `Assertion\AssertionInterface` (+ optional `ResolvableAssertion` for `{KEY}`-aware args)
- `Capture\CaptureInterface`
- `Reporting\ReporterInterface`

## Important runtime behaviour

- **Variable substitution** (`{KEY}`) and **capture substitution** (`{@name}`) are applied to URL / body / headers in `CaseTranslator`. Assertion args are resolved opt-in: assertions implementing `ResolvableAssertion` (e.g. `RedirectAssertion`) are rebuilt with the resolver via `CaseTranslator::resolveAssertions()` right before `evaluate()`. Assertions that don't implement it (most JSON / body / header assertions today) still own raw args — see `roadmap.md`.
- **Session segmentation**: `Runner::segment()` walks consecutive cases with same `sessionId`, groups them as session segments. Session segments run sequentially with shared `tempnam()` cookie jar; non-session segments run in parallel chunks of `concurrency`.
- **Retry**: `retryOnFailure(N, delayMs)` retries on ANY failure (assertion, network, 5xx). `retryOn5xx(N)` is a separate budget that only kicks in if `retryOnFailure === 0` and status >= 500. Both are tracked in `Result::$attempts` / `$totalDurationSeconds`.
- **Circuit breaker**: each `GroupConfig::$maxFailures` — once exceeded within a group, remaining cases in that group are skipped with reason "Circuit breaker: …".
- **JSON report schema** is versioned (`JsonReporter::SCHEMA_VERSION`). Markdown + GitHub-summary reporters consume the JSON via `MarkdownReporter::build($data)` — clean separation, easy to derive other formats.

## What NOT to assume

- The DSL is independent of the runner — you can build a `Suite` and use `getCasesByGroup()` directly without ever invoking `Runner` (e.g. for static analysis tools, custom executors).
- The `Container` is internal/optional. Direct construction (`new Runner(...)`) works fine — see `tests/Integration/RunnerIntegrationTest.php` for an example.
- `CurlMultiClient` does NOT call `curl_close()` — it's a no-op since PHP 8.0 and emits a deprecation. Don't add it back.
