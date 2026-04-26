<?php

declare(strict_types=1);

$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$parsed = parse_url($uri, PHP_URL_PATH);
$path = is_string($parsed) ? $parsed : '/';
$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

header('Content-Type: application/json');

if ($path === '/ping' && $method === 'GET') {
    echo json_encode(['status' => 'ok', 'pong' => true]);
    exit;
}

if ($path === '/echo' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = is_string($raw) ? $raw : '';
    $auth = is_string($_SERVER['HTTP_AUTHORIZATION'] ?? null) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    echo json_encode(['received' => $body, 'auth' => $auth]);
    exit;
}

if (preg_match('#^/items/([^/]+)/?$#', $path, $m) === 1 && $method === 'GET') {
    echo json_encode(['status' => 'success', 'data' => ['id' => $m[1], 'name' => 'Item ' . $m[1]]]);
    exit;
}

if ($path === '/create' && $method === 'POST') {
    http_response_code(201);
    echo json_encode(['status' => 'success', 'data' => ['id' => 'new-id-42']]);
    exit;
}

if ($path === '/redirect' && $method === 'GET') {
    http_response_code(302);
    header('Location: /target');
    exit;
}

if ($path === '/fail-once' && $method === 'GET') {
    $stateFile = sys_get_temp_dir() . '/smoke_fail_once_state';
    $count = is_file($stateFile) ? (int) file_get_contents($stateFile) : 0;
    file_put_contents($stateFile, (string) ($count + 1));
    if ($count === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'first attempt fails']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'attempt' => $count + 1]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not found', 'path' => $path]);
