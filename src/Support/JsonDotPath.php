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
        $found = false;
        self::traverse($data, $path, $found);

        return $found;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function get(array $data, string $path): mixed
    {
        $found = false;
        $value = null;
        self::traverse($data, $path, $found, $value);

        return $found ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function traverse(array $data, string $path, bool &$found, mixed &$value = null): void
    {
        $found = false;
        $value = null;

        $tokens = self::tokenize($path);
        if ($tokens === null) {
            return;
        }

        $current = $data;
        foreach ($tokens as $token) {
            if (!is_array($current)) {
                return;
            }

            if ($token instanceof JsonDotPathFunction) {
                if ($current === []) {
                    return;
                }
                $keys = array_keys($current);
                $key = match ($token) {
                    JsonDotPathFunction::First => $keys[0],
                    JsonDotPathFunction::Last => $keys[count($keys) - 1],
                    JsonDotPathFunction::Random => $keys[array_rand($keys)],
                };
            } else {
                if (!array_key_exists($token, $current)) {
                    return;
                }
                $key = $token;
            }

            $current = $current[$key];
        }

        $found = true;
        $value = $current;
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
     * @return list<string|int|JsonDotPathFunction>|null
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
                if ($inner === '') {
                    return null;
                }
                if (ctype_digit($inner)) {
                    $tokens[] = (int) $inner;
                } else {
                    $function = match ($inner) {
                        'first()' => JsonDotPathFunction::First,
                        'last()' => JsonDotPathFunction::Last,
                        'rand()', 'random()' => JsonDotPathFunction::Random,
                        default => null,
                    };
                    if ($function === null) {
                        return null;
                    }
                    $tokens[] = $function;
                }
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
