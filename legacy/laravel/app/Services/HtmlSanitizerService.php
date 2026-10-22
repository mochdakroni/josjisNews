<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * HTML Sanitizer berbasis Whitelist DOM untuk membersihkan output CKEditor 5
 * Mendukung elemen multimedia redaksi yang aman:
 * - Gambar (<figure>, <img>) dari storage/CDN atau URL eksternal
 * - Video (<video>, <source>) yang diunggah langsung
 * - Dokumen PDF & Google Drive Preview (<iframe> dari drive.google.com, youtube.com, atau file .pdf internal)
 */
class HtmlSanitizerService
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'code', 'pre', 'span',
        'blockquote', 'figure', 'figcaption',
        'ul', 'ol', 'li',
        'a', 'img', 'video', 'source', 'iframe',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'div',
    ];

    private const ALLOWED_ATTRIBUTES = [
        '*' => ['class', 'style'],
        'a' => ['href', 'title', 'target', 'rel', 'download'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'video' => ['src', 'controls', 'preload', 'poster', 'width', 'height'],
        'source' => ['src', 'type'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'loading', 'title'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
        'ol' => ['start', 'reversed', 'type'],
    ];

    private const ALLOWED_CSS_PROPERTIES = [
        'text-align',
        'color',
        'background-color',
        'width',
        'max-width',
        'height',
        'font-size',
        'font-family',
        'list-style-type',
        'border-color',
    ];

    public function sanitize(?string $html): string
    {
        if (! $html || trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $wrappedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $this->sanitizeNode($body);

        $cleanHtml = '';
        foreach ($body->childNodes as $child) {
            $cleanHtml .= $dom->saveHTML($child);
        }

        return trim($cleanHtml);
    }

    private function sanitizeNode(DOMNode $node): void
    {
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                if (in_array($tagName, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'meta', 'link', 'base', 'svg', 'math'], true)) {
                    $node->removeChild($child);
                    continue;
                }

                if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                    $node->removeChild($child);
                    continue;
                }

                // Validasi khusus <iframe>: izinkan YouTube, Google Drive Preview (/file/d/.../preview), atau PDF viewer
                if ($tagName === 'iframe') {
                    $src = trim($child->getAttribute('src'));
                    $isYoutube = (bool) preg_match('#^https://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/)[a-zA-Z0-9_-]+#i', $src);
                    $isGoogleDrive = (bool) preg_match('#^https://drive\.google\.com/file/d/[a-zA-Z0-9_-]+/(preview|view)#i', $src);
                    $isSafePdf = (bool) preg_match('#^https?://[^\s"\'<>]+\.pdf(\?[^\s"\'<>]*)?$#i', $src);

                    if (! $isYoutube && ! $isGoogleDrive && ! $isSafePdf) {
                        $node->removeChild($child);
                        continue;
                    }
                    $child->setAttribute('loading', 'lazy');
                }

                $this->sanitizeAttributes($child, $tagName);
            }

            if ($child->hasChildNodes()) {
                $this->sanitizeNode($child);
            }
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $allowedForTag = array_merge(
            self::ALLOWED_ATTRIBUTES['*'] ?? [],
            self::ALLOWED_ATTRIBUTES[$tagName] ?? []
        );

        $attributes = iterator_to_array($element->attributes);

        foreach ($attributes as $attr) {
            $attrName = strtolower($attr->name);
            $attrValue = trim($attr->value);

            if (str_starts_with($attrName, 'on') || ! in_array($attrName, $allowedForTag, true)) {
                $element->removeAttribute($attr->name);
                continue;
            }

            if (in_array($attrName, ['href', 'src', 'poster'], true)) {
                $normalized = preg_replace('/[\x00-\x20]+/', '', strtolower($attrValue));
                if (
                    str_starts_with($normalized, 'javascript:') ||
                    str_starts_with($normalized, 'vbscript:') ||
                    str_starts_with($normalized, 'data:text/html')
                ) {
                    $element->removeAttribute($attr->name);
                    continue;
                }
            }

            if ($attrName === 'style') {
                $cleanStyle = $this->sanitizeCssStyle($attrValue);
                if ($cleanStyle === '') {
                    $element->removeAttribute('style');
                } else {
                    $element->setAttribute('style', $cleanStyle);
                }
            }
        }

        if ($tagName === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function sanitizeCssStyle(string $styleString): string
    {
        $cleanDeclarations = [];
        $declarations = explode(';', $styleString);

        foreach ($declarations as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$prop, $val] = array_map('trim', explode(':', $declaration, 2));
            $propLower = strtolower($prop);
            $valLower = strtolower($val);

            if (! in_array($propLower, self::ALLOWED_CSS_PROPERTIES, true)) {
                continue;
            }

            if (
                str_contains($valLower, 'expression') ||
                str_contains($valLower, 'url(') ||
                str_contains($valLower, 'javascript') ||
                str_contains($valLower, 'behavior')
            ) {
                continue;
            }

            $cleanDeclarations[] = "{$propLower}: {$val}";
        }

        return implode('; ', $cleanDeclarations);
    }
}
