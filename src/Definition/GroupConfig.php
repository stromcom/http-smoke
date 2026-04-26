<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Definition;

final readonly class GroupConfig
{
    public function __construct(
        public string $name,
        public int $maxFailures = 3,
    ) {}
}
