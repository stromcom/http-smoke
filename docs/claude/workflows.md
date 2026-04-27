# Workflows

Common things you'll do in this repo and the exact commands.

## Quality checks (run before declaring done)

```bash
composer phpstan   # PHPStan level max — must show "[OK] No errors"
composer cs        # PHP-CS-Fixer dry-run — clean if "files":[]
composer cs:fix    # apply CS-Fixer changes
composer test      # PHPUnit (unit + integration) — auto-starts a built-in server for integration tests
composer phpunit   # alias for "composer test"
composer ci        # cs + phpstan + test in sequence
```

**Always invoke checks via `composer <script>`**, not by reaching into `vendor/bin/...` directly. The composer scripts are the contract — they handle Windows shim quirks, memory limits, and any future flags.

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
.github/workflows/ci.yml    # CI for the package itself
```
