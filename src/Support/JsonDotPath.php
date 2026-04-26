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
        $current = $data;
        foreach (explode('.', $path) as $key) {
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
        $current = $data;
        foreach (explode('.', $path) as $key) {
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
}
