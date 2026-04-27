# Roadmap & known limitations

## Planned work

### Next big task: JSON report viewer
The `JsonReporter` produces a canonical, schema-versioned JSON report. The
package needs a dedicated viewer — currently console + markdown are good for
single CI runs, but there's no way to browse historical runs, drill into failed
session chains visually, or compare environments. Open shape: standalone static
HTML+JS, PHP server, or CLI TUI — no commitment yet. (Memory:
`project_future_json_viewer.md`.)

## Known limitations

### Variable / capture substitution in assertion arguments — partially solved
Assertions can now opt into `{KEY}` resolution by implementing
`Assertion\ResolvableAssertion` (`withResolver(VariableResolver): self`).
`CaseTranslator::resolveAssertions()` rebuilds them right before evaluation, so
the runner sees fully-resolved args.

Status today:
- `RedirectAssertion` implements it — `expectRedirect('{APP_BASE_URL}/login')`
  works.
- Most other assertions (`JsonPath`, `Contains`, `HeaderContains`, `HtmlElement`,
  …) still own raw args. Adding `ResolvableAssertion` to them is mechanical: take
  a `VariableResolver`, call `resolve()` on each string field, return a new
  instance.
- `{@capture}` substitution still doesn't reach assertions — `CaptureStore` isn't
  threaded into the resolution path. Likely follow-up: a parallel
  `CaptureAwareAssertion` interface, or fold both into one
  `withContext(VariableResolver, CaptureStore)` method.

Workaround until everything is covered: `expect(Closure)` for dynamic checks
based on captured state.

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
