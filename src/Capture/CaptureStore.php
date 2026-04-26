<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Capture;

final class CaptureStore
{
    /** @var array<string, string> */
    private array $values = [];

    public function set(string $name, string $value): void
    {
        $this->values[$name] = $value;
    }

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function apply(string $input): string
    {
        $result = preg_replace_callback(
            '/\{@([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn(array $matches): string => $this->values[$matches[1]] ?? $matches[0],
            $input,
        );

        return $result ?? $input;
    }

    /**
     * @param array<array-key, mixed>|string|null $data
     * @return array<array-key, mixed>|string|null
     */
    public function applyToData(array|string|null $data): array|string|null
    {
        if ($data === null) {
            return null;
        }
        if (is_string($data)) {
            return $this->apply($data);
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $out[$key] = $this->apply($value);
            } elseif (is_array($value)) {
                /** @var array<array-key, mixed> $value */
                $out[$key] = $this->applyToData($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
