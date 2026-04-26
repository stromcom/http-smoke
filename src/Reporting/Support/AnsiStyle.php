<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting\Support;

final class AnsiStyle
{
    public const string RESET = "\033[0m";
    public const string BOLD = "\033[1m";
    public const string DIM = "\033[2m";
    public const string RED = "\033[31m";
    public const string GREEN = "\033[32m";
    public const string YELLOW = "\033[33m";
    public const string BLUE = "\033[34m";
    public const string MAGENTA = "\033[35m";
    public const string CYAN = "\033[36m";

    public function __construct(
        private readonly bool $enabled,
    ) {}

    public static function autodetect(): self
    {
        return new self(self::detect());
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function wrap(string $text, string $code): string
    {
        return $this->enabled ? $code . $text . self::RESET : $text;
    }

    public function bold(string $text): string
    {
        return $this->wrap($text, self::BOLD);
    }

    public function dim(string $text): string
    {
        return $this->wrap($text, self::DIM);
    }

    public function red(string $text): string
    {
        return $this->wrap($text, self::RED);
    }

    public function green(string $text): string
    {
        return $this->wrap($text, self::GREEN);
    }

    public function yellow(string $text): string
    {
        return $this->wrap($text, self::YELLOW);
    }

    public function blue(string $text): string
    {
        return $this->wrap($text, self::BLUE);
    }

    public function magenta(string $text): string
    {
        return $this->wrap($text, self::MAGENTA);
    }

    public function cyan(string $text): string
    {
        return $this->wrap($text, self::CYAN);
    }

    private static function detect(): bool
    {
        $noColor = getenv('NO_COLOR');
        if ($noColor !== false && $noColor !== '') {
            return false;
        }
        $forceColor = getenv('FORCE_COLOR');
        if ($forceColor !== false && $forceColor !== '') {
            return true;
        }

        $term = getenv('TERM');
        if (is_string($term) && preg_match('/color|xterm|rxvt/i', $term) === 1) {
            return true;
        }
        if (getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON') {
            return true;
        }
        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support') && defined('STDOUT')) {
            if (@sapi_windows_vt100_support(STDOUT)) {
                return true;
            }
        }

        return defined('STDOUT')
            && function_exists('stream_isatty')
            && @stream_isatty(STDOUT);
    }
}
