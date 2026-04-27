# CLAUDE.md

Orientation file for future Claude Code sessions. Read this first.

## What this repo is

`stromcom/http-smoke` — a standalone PHP 8.4+ package for **HTTP smoke testing**.
Originally embedded in another project (see `original/` for the legacy code that
this was extracted from); now a clean, reusable library that can drop into any
PHP project as a `--dev` dependency and runs via `vendor/bin/http-smoke`.

It is **not** a unit-testing framework. It hits real HTTP endpoints in a deployed
environment (dev / staging / prod) and verifies they respond as expected. Use
cases: post-deploy smoke checks, scheduled monitoring, CI gates.

## Core ideas

- **Fluent DSL** for declaring tests in plain PHP files: `$suite->group(...)->get(...)->expectStatus(200)->expectJsonPath(...)`.
- **Parallel execution** via `curl_multi`. Sessions (cookie-shared, sequential) for multi-step flows.
- **Capture chains**: `captureJsonPath('hash', 'data.id')` then `{@hash}` in any later URL/body/header.
- **Variable substitution**: `{KEY}` placeholders resolved from layered sources (`.env`, `smokeHttp.json`, OS env, CLI overrides) — sources are pluggable.
- **Pluggable everything**: HTTP client, reporters (console / JSON / Markdown / GitHub summary), variable sources, captures, assertions — all behind small interfaces, all swappable via `smoke.config.php` and the lightweight DI container.
- **PHP 8.4+** (`readonly` classes, enums), **PHPStan level max + strict-rules**, no docblock noise — code is meant to be self-explanatory.

## Where to look next

- [`docs/claude/architecture.md`](docs/claude/architecture.md) — layer breakdown, key interfaces, how a test flows through the system.
- [`docs/claude/conventions.md`](docs/claude/conventions.md) — code style rules (zero useless comments), PHP version, what NOT to do.
- [`docs/claude/workflows.md`](docs/claude/workflows.md) — common commands (run tests, stan, examples, dev server).
- [`docs/claude/roadmap.md`](docs/claude/roadmap.md) — planned work, known limitations.
- [`README.md`](README.md) — user-facing docs (install, CLI, DSL cheat-sheet, JSON schema).

## Status (as of 2026-04-26)

- Version 0.1 released: package skeleton + full source + tests + CI + docs + examples + bundled dev-server.
- All checks green: PHPStan max (0 errors), PHP-CS-Fixer (clean), PHPUnit (81 tests, 210 assertions), examples (26/26 against the bundled dev-server).
- Recent additions: `expectHtmlElement()` (DOM-based HTML assertion), `defaultRetries()` alias, `ResolvableAssertion` interface so assertion args can resolve `{KEY}` variables at runtime (`RedirectAssertion` is the first user), and `head()` / `options()` DSL methods (HEAD uses `CURLOPT_NOBODY`, dev server maps HEAD→GET and answers OPTIONS with `Allow`). See `docs/claude/architecture.md` and `roadmap.md`.

## ⚠️ Keep this documentation up to date

**Every time you make a non-trivial change to the repo, also update the relevant doc here.** This file and the ones in `docs/claude/` are the contract that future Claude sessions rely on — if they go stale, the next session will operate on lies.

What counts as "non-trivial" and where to reflect it:

| Change | Update |
|---|---|
| Added/removed a domain folder, interface, or extension point | `docs/claude/architecture.md` |
| Changed runtime behaviour (segmentation, retry, substitution rules, etc.) | `docs/claude/architecture.md` (Important runtime behaviour section) |
| New convention, tightened/loosened quality gate, new "do not do" rule | `docs/claude/conventions.md` |
| New composer script, new CLI flag, changed dev-server / examples flow | `docs/claude/workflows.md` |
| Resolved a known limitation, shipped a planned item, discovered a new gotcha | `docs/claude/roadmap.md` |
| Project-level shift (status, version, package name, license) | this `CLAUDE.md` |

When in doubt, update. The cost of a one-line edit is tiny; the cost of a future session acting on outdated assumptions is large. **Do this in the same change as the code edit, not as a follow-up.**

## Notes for future sessions

- The user prefers **terse, self-explanatory code with no comments** unless the WHY is genuinely non-obvious. Memory file `feedback_no_useless_comments.md` covers this — do not add docblocks to classes/methods just because.
- Planned next big task once 1.0 ships: a **viewer for the JSON reports** (memory: `project_future_json_viewer.md`).
- User communicates in Czech; reply in Czech, but keep code/docs/identifiers in English.
