<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Support;

final class JsonDotPath
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function exists(array $data, string $path): bool
    {
        $tokens = self::tokenize($path);
        if ($tokens === null) {
            return false;
        }

        $current = $data;
        foreach ($tokens as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function get(array $data, string $path): mixed
    {
        $tokens = self::tokenize($path);
        if ($tokens === null) {
            return null;
        }

        $current = $data;
        foreach ($tokens as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function decode(string $body): ?array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<string|int>|null
     */
    private static function tokenize(string $path): ?array
    {
        if ($path === '') {
            return null;
        }

        $tokens = [];
        $length = strlen($path);
        $offset = 0;
        $expectKey = true;

        while ($offset < $length) {
            $char = $path[$offset];

            if ($char === '[') {
                $end = strpos($path, ']', $offset);
                if ($end === false) {
                    return null;
                }
                $inner = substr($path, $offset + 1, $end - $offset - 1);
                if ($inner === '' || !ctype_digit($inner)) {
                    return null;
                }
                $tokens[] = (int) $inner;
                $offset = $end + 1;
                $expectKey = false;
                continue;
            }

            if ($char === '.') {
                if ($expectKey) {
                    return null;
                }
                ++$offset;
                $expectKey = true;
                continue;
            }

            $nextDot = strpos($path, '.', $offset);
            $nextBracket = strpos($path, '[', $offset);
            $next = match (true) {
                $nextDot === false => $nextBracket,
                $nextBracket === false => $nextDot,
                default => min($nextDot, $nextBracket),
            };
            $end = $next === false ? $length : $next;
            $key = substr($path, $offset, $end - $offset);
            if ($key === '') {
                return null;
            }
            $tokens[] = $key;
            $offset = $end;
            $expectKey = false;
        }

        if ($expectKey) {
            return null;
        }

        return $tokens;
    }
}
