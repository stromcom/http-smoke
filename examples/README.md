# Examples

Self-contained, runnable example: a tiny PHP **dev server** + a set of **smoke
test definitions** that exercise every feature of the package end-to-end.

## What it covers

| Feature | Example file |
|---|---|
| HTML pages, `Cache-Control` header check, redirect, 404 | `SmokeHttp/web/01-public.php` |
| Cookie-based session chain (login → protected page → logout) | `SmokeHttp/web/02-cookie-session.php` |
| Public JSON ping, JSON path / has-keys assertions, 401 without auth | `SmokeHttp/api/01-public.php` |
| Authenticated CRUD, capture chain, multi-step session, DELETE+verify-404 | `SmokeHttp/api/02-thread-lifecycle.php` |
| `retryOnFailure` (eventual consistency), `retryOn5xx`, custom timeout | `SmokeHttp/api/03-retry.php` |

## Run it

In one terminal — start the dev server:

```bash
composer dev-server
# (resets state and starts php -S 127.0.0.1:8080 examples/dev-server/server.php)
```

In another terminal — run all smoke tests against it:

```bash
composer examples
```

You should see ~17 passing tests across 5 groups. The retry test is real: the
`/eventually/<key>/2` endpoint returns 404 the first two times and 200 on the
third — the runner retries automatically and reports `retried 2× in …ms`.

## Reset

```bash
composer dev-server:reset
```

…wipes the temp state files (created threads, sessions, retry counters).

## What the dev server provides

See [`dev-server/server.php`](./dev-server/server.php) for the full route table.
Highlights:

- `GET /api/eventually/{key}/{n}` — first `{n}` hits return 404, then 200 (use
  with `retryOnFailure`)
- `GET /api/fail-once` — 500 once, 200 thereafter (use with `retryOn5xx`)
- `GET /api/slow?ms=300` — sleeps then responds (use with `timeout()`)
- `POST /api/threads/` + capture-driven CRUD chain
- `POST /session/login` → cookie + 302; protected `/session/dashboard` requires
  the cookie

State persists in `sys_get_temp_dir()/http-smoke-dev-state.json` and
`http-smoke-dev-sessions.json`.
