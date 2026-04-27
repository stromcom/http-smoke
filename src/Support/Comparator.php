<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Support;

enum Comparator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';

    public function compare(int|float $actual, int|float $expected): bool
    {
        return match ($this) {
            self::Equal => $actual === $expected,
            self::NotEqual => $actual !== $expected,
            self::LessThan => $actual < $expected,
            self::LessThanOrEqual => $actual <= $expected,
            self::GreaterThan => $actual > $expected,
            self::GreaterThanOrEqual => $actual >= $expected,
        };
    }
}
