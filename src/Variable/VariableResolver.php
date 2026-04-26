<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable;

use Stromcom\HttpSmoke\Exception\VariableNotFoundException;

final class VariableResolver
{
    /** @var list<VariableSourceInterface> */
    private array $sources = [];

    public function addSource(VariableSourceInterface $source): self
    {
        $this->sources[] = $source;

        return $this;
    }

    public function get(string $name): ?string
    {
        foreach (array_reverse($this->sources) as $source) {
            $value = $source->get($name);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function resolve(string $input): string
    {
        $result = preg_replace_callback(
            '/\{([A-Z_][A-Z0-9_]*)\}/',
            function (array $matches): string {
                $name = $matches[1];
                $value = $this->get($name);
                if ($value === null) {
                    throw VariableNotFoundException::for($name);
                }

                return $value;
            },
            $input,
        );

        return $result ?? $input;
    }

    public function resolveUrl(string $input): string
    {
        $resolved = $this->resolve($input);
        $normalized = preg_replace('#(?<!:)/{2,}#', '/', $resolved);

        return $normalized ?? $resolved;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $merged = [];
        foreach ($this->sources as $source) {
            foreach ($source->all() as $name => $value) {
                $merged[$name] = $value;
            }
        }

        return $merged;
    }
}
