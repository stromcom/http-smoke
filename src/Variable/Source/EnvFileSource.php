<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable\Source;

use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Variable\VariableSourceInterface;

final class EnvFileSource implements VariableSourceInterface
{
    /** @var array<string, string> */
    private array $variables;

    public function __construct(string $filePath)
    {
        $this->variables = self::parse($filePath);
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
     * @return array<string, string>
     */
    private static function parse(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new ConfigException("Environment file not found: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new ConfigException("Failed to read environment file: {$filePath}");
        }

        $vars = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($value === '' || str_contains($value, '${ssm:')) {
                continue;
            }

            $vars[$key] = $value;
        }

        return $vars;
    }
}
