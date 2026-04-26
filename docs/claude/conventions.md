# Conventions

## Code style — the big rule

**No useless comments.** Code is meant to be self-explanatory: clear names, short methods, expressive types.

- No docblock summaries on methods/classes that just restate the signature
  (`/** Returns the user */ public function getUser(): User` — don't).
- No "section divider" comments inside normal classes (`// ── Helpers ──`). Section dividers are OK in entry-point bin scripts where they aid scanning.
- A comment is acceptable **only when WHY is non-obvious**: a hidden constraint, a workaround for a specific bug, an invariant that would surprise a reader.
- If you feel the urge to comment — first try renaming or splitting the method/class. The need to explain is usually a code-design signal.
- PHPStan-required `@param`/`@return` for arrays/iterables (e.g. `array<string, string>`, `list<TestCase>`) ARE necessary and OK to keep.

The user has expressed this preference explicitly. It's also recorded in
persistent memory (`feedback_no_useless_comments.md`).

## PHP version & language features

- Target **PHP 8.4+**. Use modern features liberally:
  - `final readonly class` for value objects
  - `final class` (mutable) for everything else by default
  - Enums (`enum Method: string`)
  - Constructor property promotion
  - Asymmetric visibility / property hooks where they reduce boilerplate
  - Named arguments in tests / call sites with many optional params
  - Pure typed function signatures (`?string`, `int|string`, `list<...>`)
- `declare(strict_types=1);` in every file.

## Quality gates

All three must pass before considering work done:

1. **PHPStan level max + strict-rules + phpunit extension** (`composer stan` — uses `--memory-limit=512M`)
2. **PHP-CS-Fixer** with PER-CS 2.0 + PHP84 migration preset (`composer cs` for dry-run, `composer cs:fix` to apply)
3. **PHPUnit** unit + integration tests (`composer test`)

PHPStan is configured to **forbid** suppression: no `@phpstan-ignore`, no
baseline, no `assert()` to silence, no inline `@var`. Fix root causes.

## Architecture conventions

- One interface per extension point. Default impls live in subfolders (e.g. `Variable\Source\*`, `Http\Curl\*`).
- Immutable VOs (`Request`, `Response`, `TestCase`, `GroupConfig`) — return new instances from `with*()` methods rather than mutating.
- DTOs that need to grow over time (`SmokeConfig`, `Result`, `Report`) can stay mutable but with public typed properties — no getter/setter ceremony.
- Throw typed exceptions from `Exception/` namespace (`ConfigException`, `VariableNotFoundException`, `HttpException`, `Container\Exception\NotFoundException`). All extend `SmokeException` (which extends `RuntimeException`).
- The `Container` is the only place service wiring lives (`Container\ServiceFactory::build`). Don't sprinkle `new Foo()` calls around in callers — go through the container or accept dependencies via constructor.

## Tests

- Unit tests in `tests/Unit/<Domain>/`, mirroring `src/<Domain>/`.
- Integration tests in `tests/Integration/` — use the bundled PHP built-in server (`tests/Integration/fixtures/server.php`) for end-to-end coverage.
- Use `#[CoversClass(...)]` attributes (PHPUnit 11), not `@covers` annotations.
- Prefer `self::assert*` over `$this->assert*` in test methods (CS-Fixer enforces this).

## Commit messages

- Imperative, concise. No special preamble required.
- Don't co-author Claude unless the user asks.
