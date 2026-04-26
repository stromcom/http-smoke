<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Http;

enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';

    public function allowsBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT, self::PATCH, self::DELETE => true,
            default => false,
        };
    }
}
