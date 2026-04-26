<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable\Source;

use Stromcom\HttpSmoke\Variable\VariableSourceInterface;

final class ArraySource implements VariableSourceInterface
{
    /**
     * @param array<string, string> $variables
     */
    public function __construct(
        private array $variables = [],
    ) {}

    public function set(string $name, string $value): void
    {
        $this->variables[$name] = $value;
    }

    public function unset(string $name): void
    {
        unset($this->variables[$name]);
    }

    public function get(string $name): ?string
    {
        return $this->variables[$name] ?? null;
    }

    public function all(): array
    {
        return $this->variables;
    }
}
