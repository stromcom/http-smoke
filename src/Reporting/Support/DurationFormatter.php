<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting\Support;

final class DurationFormatter
{
    public static function seconds(float $seconds): string
    {
        if ($seconds < 0.001) {
            return '<1ms';
        }
        if ($seconds < 1.0) {
            return (int) round($seconds * 1000) . 'ms';
        }

        return number_format($seconds, 2) . 's';
    }

    public static function milliseconds(int $ms): string
    {
        if ($ms < 1) {
            return '<1ms';
        }
        if ($ms < 1000) {
            return $ms . 'ms';
        }

        return number_format($ms / 1000, 2) . 's';
    }
}
