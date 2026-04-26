# Roadmap & known limitations

## Planned work

### Next big task: JSON report viewer
The `JsonReporter` produces a canonical, schema-versioned JSON report. The
package needs a dedicated viewer — currently console + markdown are good for
single CI runs, but there's no way to browse historical runs, drill into failed
session chains visually, or compare environments. Open shape: standalone static
HTML+JS, PHP server, or CLI TUI — no commitment yet. (Memory:
`project_future_json_viewer.md`.)

### v1.1: Guzzle HTTP client adapter
Currently only `Http\Curl\CurlMultiClient` is shipped. The interface
(`HttpClientInterface`) is ready. v1.1 will add `Http\Guzzle\GuzzleClient` with
`guzzlehttp/guzzle` as a `suggest`, opt-in via `smoke.config.php`. Some
low-level features (custom resolve, raw timing) may be reduced — document on
arrival.

## Known limitations

### Variable / capture substitution doesn't reach assertion arguments
Today: `{KEY}` and `{@name}` placeholders are substituted by `CaseTranslator`
when building the `Request` (URL, body, headers). They are **NOT** substituted
inside `expectJsonPath()`, `expectContains()`, etc., because assertion objects
own their args at definition time and the runner just calls `evaluate(Response)`
on them.

Workarounds in the wild:
- Use `expect(Closure)` for dynamic checks based on captured state.
- For `expectRedirect()`, this was already addressed: the assertion compares by
  path component, so passing relative paths (`/login`) works regardless of base
  URL. (Fixed during initial build — `expectRedirect()` used to prepend
  `baseUrl()`, which broke `{APP_BASE_URL}` substitution.)

A clean fix would be either:
1. Threading `VariableResolver`/`CaptureStore` into assertions at evaluate time
   (couples assertions to those services), or
2. Having `CaseTranslator` rebuild assertions with substituted args (assertions
   need a `with*()` API).

Not urgent for 1.0 — flagged here so future work doesn't get blindsided.

### `original/` cleanup
Per user preference, the `original/` reference dump stays until the first
release ships, then gets removed. Don't lean on it for ongoing work — it's
historical context, not the source of truth.

### Windows-only quirks observed during development
- `bash` background commands sometimes don't capture `php` stdout to the output
  file. Use `> /tmp/file.txt 2>&1` redirection explicitly or run synchronously.
- `vendor/bin/phpunit` shim can drop output; `php vendor/phpunit/phpunit/phpunit`
  is more reliable.
- `intelephense` shows false-positive "undefined type" for PSR/PHPUnit classes
  before/after vendor index — ignore unless PHPStan also complains.

## Things to deliberately NOT do

- Don't add docblock summaries to silence linters or "for documentation". The
  user has been clear: code should explain itself, comments are a smell.
- Don't add `@phpstan-ignore` or baseline entries. PHPStan errors are real bugs.
- Don't reintroduce `curl_close()` — no-op since PHP 8.0, emits deprecation.
- Don't auto-`git init` or auto-commit. Wait for user instruction.
- Don't wrap the `Container` retrieval pattern in extra abstractions; `getTyped(Foo::class)` is the contract.
