<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Capture;

use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Support\JsonDotPath;

final readonly class JsonPathCapture implements CaptureInterface
{
    public function __construct(
        private string $name,
        private string $path,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function extract(Response $response): ?string
    {
        $data = JsonDotPath::decode($response->body);
        if ($data === null) {
            return null;
        }

        $value = JsonDotPath::get($data, $this->path);
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
