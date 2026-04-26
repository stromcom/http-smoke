<?php

declare(strict_types=1);

/**
 * Demo dev server for stromcom/http-smoke examples.
 *
 * Endpoints (kept short — see examples/SmokeHttp/* for what each one is exercised by):
 *
 *   GET  /                           HTML home
 *   GET  /robots.txt                 plain text
 *   GET  /redirect-to-login          302 → /login
 *   GET  /login                      HTML login form
 *
 *   GET  /api/ping                   public JSON ping
 *   GET  /api/version                public JSON version
 *   GET  /api/slow?ms=300            sleeps then 200 (timeout testing)
 *   GET  /api/eventually/{key}/{n}   first N hits return 404, then 200 (retry-on-failure)
 *   GET  /api/fail-once              500 once, 200 thereafter (retry-on-5xx)
 *   GET  /api/users/                 401 without auth, 200 with Bearer dev-test-token
 *
 *   POST /api/threads/               creates thread; returns hash + initial msg hash
 *   GET  /api/threads/{hash}/        returns stored thread
 *   PATCH /api/threads/{hash}/messages/{msg}/notice/   marks message as read
 *   DELETE /api/threads/{hash}/      deletes the thread
 *
 *   POST /session/login              sets session cookie, 302 → /session/dashboard
 *   GET  /session/dashboard          200 with cookie, 401 without
 *   GET  /session/projects           200 with cookie, 401 without
 *   POST /session/logout             clears cookie, 204
 */

const AUTH_TOKEN = 'Bearer dev-test-token';

$stateFile = sys_get_temp_dir() . '/http-smoke-dev-state.json';
$sessionFile = sys_get_temp_dir() . '/http-smoke-dev-sessions.json';

$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$parsed = parse_url($uri, PHP_URL_PATH);
$path = is_string($parsed) ? $parsed : '/';
$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$query = [];
$qs = parse_url($uri, PHP_URL_QUERY);
if (is_string($qs)) {
    parse_str($qs, $query);
}

function respond_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respond_html(string $html, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/**
 * @return array<string, mixed>
 */
function load_state(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $state
 */
function save_state(string $file, array $state): void
{
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function read_input(): string
{
    $raw = file_get_contents('php://input');

    return is_string($raw) ? $raw : '';
}

/**
 * @return array<array-key, mixed>
 */
function read_json_input(): array
{
    $raw = read_input();
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function require_bearer(): void
{
    $auth = is_string($_SERVER['HTTP_AUTHORIZATION'] ?? null) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ($auth !== AUTH_TOKEN) {
        respond_json(['status' => 'error', 'error' => 'unauthenticated'], 401);
    }
}

function read_session_id(): ?string
{
    $cookie = is_string($_SERVER['HTTP_COOKIE'] ?? null) ? $_SERVER['HTTP_COOKIE'] : '';
    if ($cookie === '') {
        return null;
    }
    foreach (explode(';', $cookie) as $part) {
        $part = trim($part);
        if (str_starts_with($part, 'session_id=')) {
            return substr($part, 11);
        }
    }

    return null;
}

// ── Routes ───────────────────────────────────────────────────────────────────

if ($path === '/' && $method === 'GET') {
    header('Cache-Control: public, max-age=60');
    respond_html('<!doctype html><html><head><title>Smoke Demo</title></head><body><h1>Welcome</h1><p>Demo dev server for http-smoke examples.</p></body></html>');
}

if ($path === '/robots.txt' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow:\n";
    exit;
}

if ($path === '/redirect-to-login' && $method === 'GET') {
    http_response_code(302);
    header('Location: /login');
    exit;
}

if ($path === '/login' && $method === 'GET') {
    respond_html('<!doctype html><html><head><title>Login</title></head><body><form method="post" action="/session/login"><input name="username"><input name="password" type="password"><button>Sign in</button></form></body></html>');
}

// ── Public API ──────────────────────────────────────────────────────────────

if ($path === '/api/ping' && $method === 'GET') {
    respond_json(['status' => 'ok', 'pong' => true, 'time' => time()]);
}

if ($path === '/api/version' && $method === 'GET') {
    respond_json(['version' => '1.0.0', 'commit' => 'demo-' . substr(md5(__FILE__), 0, 7)]);
}

if ($path === '/api/slow' && $method === 'GET') {
    $ms = isset($query['ms']) && is_numeric($query['ms']) ? (int) $query['ms'] : 200;
    usleep($ms * 1000);
    respond_json(['status' => 'ok', 'slept_ms' => $ms]);
}

if (preg_match('#^/api/eventually/([a-zA-Z0-9_-]+)/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $key = $m[1];
    $hitsBeforeOk = (int) $m[2];
    $state = load_state($stateFile);
    $counts = is_array($state['eventually'] ?? null) ? $state['eventually'] : [];
    $current = isset($counts[$key]) && is_int($counts[$key]) ? $counts[$key] : 0;
    $counts[$key] = $current + 1;
    $state['eventually'] = $counts;
    save_state($stateFile, $state);

    if ($current < $hitsBeforeOk) {
        respond_json(['status' => 'pending', 'attempt' => $current + 1, 'needed' => $hitsBeforeOk + 1], 404);
    }
    respond_json(['status' => 'ready', 'attempt' => $current + 1]);
}

if ($path === '/api/fail-once' && $method === 'GET') {
    $state = load_state($stateFile);
    $count = isset($state['fail_once_count']) && is_int($state['fail_once_count']) ? $state['fail_once_count'] : 0;
    $state['fail_once_count'] = $count + 1;
    save_state($stateFile, $state);

    if ($count === 0) {
        respond_json(['status' => 'error', 'message' => 'first attempt fails by design'], 500);
    }
    respond_json(['status' => 'ok', 'attempt' => $count + 1]);
}

if ($path === '/api/users/' && $method === 'GET') {
    require_bearer();
    respond_json([
        'status' => 'success',
        'data' => [
            ['id' => 'u-1', 'name' => 'Alice'],
            ['id' => 'u-2', 'name' => 'Bob'],
        ],
        'meta' => ['count' => 2],
    ]);
}

// ── Authenticated CRUD: threads with messages ───────────────────────────────

if ($path === '/api/threads/' && $method === 'POST') {
    require_bearer();
    $payload = read_json_input();
    $threadHash = 'th_' . bin2hex(random_bytes(6));
    $messageHash = 'msg_' . bin2hex(random_bytes(6));

    $state = load_state($stateFile);
    $threads = is_array($state['threads'] ?? null) ? $state['threads'] : [];
    $threads[$threadHash] = [
        'hash' => $threadHash,
        'code' => is_string($payload['thread_code'] ?? null) ? $payload['thread_code'] : 'untitled',
        'messages' => [
            $messageHash => [
                'hash' => $messageHash,
                'body' => is_string($payload['message'] ?? null) ? $payload['message'] : '',
                'read' => false,
            ],
        ],
    ];
    $state['threads'] = $threads;
    save_state($stateFile, $state);

    respond_json([
        'status' => 'success',
        'data' => [
            'thread' => ['hash' => $threadHash, 'code' => $threads[$threadHash]['code']],
            'message' => ['hash' => $messageHash],
        ],
    ], 201);
}

if (preg_match('#^/api/threads/([^/]+)/$#', $path, $m) === 1 && $method === 'GET') {
    require_bearer();
    $state = load_state($stateFile);
    $threads = is_array($state['threads'] ?? null) ? $state['threads'] : [];
    if (!isset($threads[$m[1]])) {
        respond_json(['status' => 'error', 'error' => 'not found'], 404);
    }
    respond_json(['status' => 'success', 'data' => $threads[$m[1]]]);
}

if (preg_match('#^/api/threads/([^/]+)/messages/([^/]+)/notice/$#', $path, $m) === 1 && $method === 'PATCH') {
    require_bearer();
    $payload = read_json_input();
    $action = is_string($payload['action'] ?? null) ? $payload['action'] : '';
    $state = load_state($stateFile);
    $threads = is_array($state['threads'] ?? null) ? $state['threads'] : [];
    if (!isset($threads[$m[1]]) || !is_array($threads[$m[1]]['messages'] ?? null) || !isset($threads[$m[1]]['messages'][$m[2]])) {
        respond_json(['status' => 'error', 'error' => 'not found'], 404);
    }
    $threads[$m[1]]['messages'][$m[2]]['read'] = $action === 'read';
    $state['threads'] = $threads;
    save_state($stateFile, $state);
    respond_json(['status' => 'success']);
}

if (preg_match('#^/api/threads/([^/]+)/$#', $path, $m) === 1 && $method === 'DELETE') {
    require_bearer();
    $state = load_state($stateFile);
    $threads = is_array($state['threads'] ?? null) ? $state['threads'] : [];
    if (!isset($threads[$m[1]])) {
        respond_json(['status' => 'error', 'error' => 'not found'], 404);
    }
    unset($threads[$m[1]]);
    $state['threads'] = $threads;
    save_state($stateFile, $state);
    http_response_code(204);
    exit;
}

// ── Cookie-based session flow ───────────────────────────────────────────────

if ($path === '/session/login' && $method === 'POST') {
    $sessionId = bin2hex(random_bytes(8));
    $sessions = load_state($sessionFile);
    $sessions[$sessionId] = ['user' => 'demo', 'created' => time()];
    save_state($sessionFile, $sessions);

    header('Set-Cookie: session_id=' . $sessionId . '; Path=/; HttpOnly');
    http_response_code(302);
    header('Location: /session/dashboard');
    exit;
}

if (str_starts_with($path, '/session/') && in_array($path, ['/session/dashboard', '/session/projects'], true) && $method === 'GET') {
    $sessionId = read_session_id();
    $sessions = load_state($sessionFile);
    if ($sessionId === null || !isset($sessions[$sessionId])) {
        respond_json(['status' => 'error', 'error' => 'unauthenticated'], 401);
    }
    respond_json(['status' => 'success', 'page' => $path, 'session' => $sessionId]);
}

if ($path === '/session/logout' && $method === 'POST') {
    $sessionId = read_session_id();
    if ($sessionId !== null) {
        $sessions = load_state($sessionFile);
        unset($sessions[$sessionId]);
        save_state($sessionFile, $sessions);
    }
    header('Set-Cookie: session_id=; Path=/; Max-Age=0');
    http_response_code(204);
    exit;
}

// ── Fallback ────────────────────────────────────────────────────────────────

respond_json(['status' => 'error', 'error' => 'route not found', 'path' => $path, 'method' => $method], 404);
