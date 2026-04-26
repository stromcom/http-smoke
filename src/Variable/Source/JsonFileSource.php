<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable\Source;

use JsonException;
use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Variable\VariableSourceInterface;

final class JsonFileSource implements VariableSourceInterface
{
    /** @var array<string, string> */
    private array $variables;

    /**
     * @param list<string> $environmentAliases  Environment names tried in order; the first one matching a top-level key in the JSON wins.
     */
    public function __construct(string $filePath, array $environmentAliases)
    {
        $this->variables = self::load($filePath, $environmentAliases);
    }

    public function get(string $name): ?string
    {
        return $this->variables[$name] ?? null;
    }

    public function all(): array
    {
        return $this->variables;
    }

    /**
     * @param list<string> $environmentAliases
     * @return array<string, string>
     */
    private static function load(string $filePath, array $environmentAliases): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new ConfigException("Failed to read JSON file: {$filePath}");
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigException("Invalid JSON in {$filePath}: {$e->getMessage()}", previous: $e);
        }

        foreach ($environmentAliases as $alias) {
            $section = $decoded[$alias] ?? null;
            if (!is_array($section)) {
                continue;
            }

            $vars = [];
            foreach ($section as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (is_string($value)) {
                    $vars[$key] = $value;
                } elseif (is_int($value) || is_float($value)) {
                    $vars[$key] = (string) $value;
                } elseif (is_bool($value)) {
                    $vars[$key] = $value ? '1' : '0';
                }
            }

            return $vars;
        }

        return [];
    }
}
