<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Stromcom\HttpSmoke\Http\Response;

final readonly class HtmlElementAssertion implements AssertionInterface
{
    public function __construct(
        public string $tag,
        public ?string $text = null,
        public ?string $attribute = null,
        public ?string $attributeValue = null,
    ) {}

    public function evaluate(Response $response): ?string
    {
        $desc = $this->describe();

        $body = trim($response->body);
        if ($body === '') {
            return "HTML element {$desc} not found (empty body)";
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8">' . $body,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false) {
            return "HTML element {$desc} not found (response body is not parseable HTML)";
        }

        $xpath = new DOMXPath($dom);
        $expression = $this->buildXpath();
        $nodes = $xpath->query($expression);

        if ($nodes === false || $nodes->length === 0) {
            return "HTML element {$desc} not found";
        }

        if ($this->text === null) {
            return null;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $actualText = trim($node->textContent);
            if ($actualText === $this->text) {
                return null;
            }
        }

        $first = $nodes->item(0);
        $actualText = $first instanceof DOMElement ? trim($first->textContent) : '';

        return "HTML {$desc} text: expected \"{$this->text}\", got \"{$actualText}\"";
    }

    private function describe(): string
    {
        if ($this->attribute !== null && $this->attributeValue !== null) {
            return "<{$this->tag} {$this->attribute}=\"{$this->attributeValue}\">";
        }
        if ($this->attribute !== null) {
            return "<{$this->tag} {$this->attribute}>";
        }

        return "<{$this->tag}>";
    }

    private function buildXpath(): string
    {
        $tag = strtolower($this->tag);
        $xpath = '//' . $tag;

        if ($this->attribute !== null && $this->attributeValue !== null) {
            $xpath .= '[@' . $this->attribute . '=' . $this->xpathLiteral($this->attributeValue) . ']';
        } elseif ($this->attribute !== null) {
            $xpath .= '[@' . $this->attribute . ']';
        }

        return $xpath;
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'{$value}'";
        }
        if (!str_contains($value, '"')) {
            return "\"{$value}\"";
        }
        $parts = explode("'", $value);
        $escaped = [];
        foreach ($parts as $i => $part) {
            $escaped[] = "'{$part}'";
            if ($i < count($parts) - 1) {
                $escaped[] = "\"'\"";
            }
        }

        return 'concat(' . implode(',', $escaped) . ')';
    }
}
