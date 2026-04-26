# Workflows

Common things you'll do in this repo and the exact commands.

## Quality checks (run before declaring done)

```bash
composer stan      # PHPStan level max — must show "[OK] No errors"
composer cs        # PHP-CS-Fixer dry-run — clean if "files":[]
composer cs:fix    # apply CS-Fixer changes
composer test      # PHPUnit (unit + integration) — auto-starts a built-in server for integration tests
composer ci        # all three in sequence
```

PHPStan needs a higher memory limit on Windows; the script handles it. If
running directly: `vendor/bin/phpstan analyse --memory-limit=512M`.

On Windows, prefer `php vendor/phpunit/phpunit/phpunit` over `vendor/bin/phpunit`
— the `.bat` shim sometimes drops output in WSL/Git-Bash.

## Running the bundled demo

Two terminals:

```bash
# terminal 1: dev server (resets state, starts php -S 127.0.0.1:8080)
composer dev-server

# terminal 2: smoke tests against it (should be 23/23 passing)
composer examples
```

Reset only:

```bash
composer dev-server:reset
```

State files (`http-smoke-dev-state.json`, `http-smoke-dev-sessions.json`)
live in `sys_get_temp_dir()`.

## Running the CLI directly

The published binary is `vendor/bin/http-smoke`. From the repo itself:

```bash
php bin/http-smoke <env> [options]
```

Useful options when iterating:

```bash
--config-dir=examples/SmokeHttp        # override test discovery dir
--smoke-json=examples/smokeHttp.json   # override per-env values
--filter=02-thread                     # only files matching *02-thread*
--group=api.*                          # only matching group(s) (wildcards supported)
--verbose                              # full request/response per test
--no-github-summary                    # skip writing $GITHUB_STEP_SUMMARY (useful locally)
--output-json=build/report.json        # canonical JSON artifact
--output=build/report.md               # Markdown report
--var=KEY=VALUE                        # one-off variable override
```

## Working on the package

- Write source in `src/`, tests in `tests/Unit/<Domain>/` mirroring it.
- After editing, `composer ci` is the full check.
- Integration tests need to bind a TCP port — they pick a free one automatically.

## Git / repo hygiene

- `original/` is the legacy reference dump from the project this was extracted from. Read-only — do not edit. To be removed in/after the first release per user preference.
- `.gitignore` already covers `vendor/`, `composer.lock`, caches, IDE folders.
- This repo is **not yet a git repo** at the time of writing — the user said "init when ready". Don't `git init` proactively.

## Where things live

```
bin/http-smoke              # thin CLI executable
src/                        # the package
tests/Unit/                 # unit tests
tests/Integration/          # integration tests + fixtures/server.php
examples/                   # bundled demo (dev-server + ready-to-run definitions)
docs/                       # user docs (extending) + GitHub Action template
docs/claude/                # this folder — orientation for Claude
original/                   # legacy reference (delete after 1.0)
.github/workflows/ci.yml    # CI for the package itself
```
