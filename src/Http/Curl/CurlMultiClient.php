<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Http\Curl;

use CurlHandle;
use Stromcom\HttpSmoke\Http\HttpClientInterface;
use Stromcom\HttpSmoke\Http\Request;
use Stromcom\HttpSmoke\Http\Response;

final class CurlMultiClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $concurrency = 10,
    ) {}

    public function send(Request $request): Response
    {
        $handle = $this->createHandle($request);
        $start = microtime(true);

        $raw = curl_exec($handle);
        $duration = microtime(true) - $start;

        $errno = curl_errno($handle);
        if ($errno !== 0) {
            return Response::transportFailure("({$errno}) " . curl_error($handle), $duration);
        }

        return self::parseResponse($handle, is_string($raw) ? $raw : '', $duration);
    }

    public function sendMany(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $results = [];
        $chunkSize = max(1, $this->concurrency);

        foreach (array_chunk($requests, $chunkSize) as $chunk) {
            foreach ($this->executeChunk($chunk) as $response) {
                $results[] = $response;
            }
        }

        return $results;
    }

    /**
     * @param list<Request> $chunk
     * @return list<Response>
     */
    private function executeChunk(array $chunk): array
    {
        $multi = curl_multi_init();

        /** @var list<CurlHandle> $handles */
        $handles = [];
        /** @var list<float> $startTimes */
        $startTimes = [];

        foreach ($chunk as $request) {
            $handle = $this->createHandle($request);
            $handles[] = $handle;
            $startTimes[] = microtime(true);
            curl_multi_add_handle($multi, $handle);
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($status > CURLM_OK) {
                break;
            }
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0);

        $responses = [];
        foreach ($handles as $index => $handle) {
            $duration = microtime(true) - $startTimes[$index];
            $errno = curl_errno($handle);

            if ($errno !== 0) {
                $error = curl_error($handle);
                $responses[] = Response::transportFailure("({$errno}) {$error}", $duration);
            } else {
                $raw = curl_multi_getcontent($handle);
                $responses[] = self::parseResponse($handle, is_string($raw) ? $raw : '', $duration);
            }

            curl_multi_remove_handle($multi, $handle);
        }

        curl_multi_close($multi);

        return $responses;
    }

    private function createHandle(Request $request): CurlHandle
    {
        $handle = curl_init($request->url);
        if (!$handle instanceof CurlHandle) {
            throw new \RuntimeException('Failed to initialize curl handle.');
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_TIMEOUT, $request->timeoutSeconds);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, min(5, $request->timeoutSeconds));
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, !$request->insecureTls);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $request->insecureTls ? 0 : 2);
        if ($request->userAgent !== '') {
            curl_setopt($handle, CURLOPT_USERAGENT, $request->userAgent);
        }
        curl_setopt($handle, CURLOPT_ENCODING, '');

        if ($request->cookieJarPath !== null && $request->cookieJarPath !== '') {
            curl_setopt($handle, CURLOPT_COOKIEJAR, $request->cookieJarPath);
            curl_setopt($handle, CURLOPT_COOKIEFILE, $request->cookieJarPath);
        }

        $method = $request->method->value;
        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POST, true);
        } elseif ($method === 'HEAD') {
            curl_setopt($handle, CURLOPT_NOBODY, true);
        } elseif ($method !== 'GET') {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($request->body !== null && $request->method->allowsBody()) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, self::encodeBody($request));
        }

        $headerLines = self::buildHeaderLines($request);
        if ($headerLines !== []) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        }

        return $handle;
    }

    /**
     * @return list<string>
     */
    private static function buildHeaderLines(Request $request): array
    {
        $headers = $request->headers;
        $hasBody = $request->body !== null && $request->method->allowsBody();
        $rawBody = is_string($request->body);

        if ($hasBody && !$rawBody) {
            $hasContentType = false;
            foreach (array_keys($headers) as $key) {
                if (strtolower($key) === 'content-type') {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                $headers['Content-Type'] = $request->sendAsJson
                    ? 'application/json'
                    : 'application/x-www-form-urlencoded';
            }
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return $lines;
    }

    private static function encodeBody(Request $request): string
    {
        $body = $request->body;
        if (is_string($body)) {
            return $body;
        }
        if ($body === null) {
            return '';
        }

        if ($request->sendAsJson) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json === false ? '' : $json;
        }

        return http_build_query($body);
    }

    private static function parseResponse(CurlHandle $handle, string $raw, float $duration): Response
    {
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        $headerBlock = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $headers = [];
        foreach (explode("\r\n", $headerBlock) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return new Response(
            statusCode: $statusCode,
            body: $body,
            headers: $headers,
            durationSeconds: $duration,
        );
    }
}
