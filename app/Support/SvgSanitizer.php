<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/**
 * Makes an uploaded SVG logo safe to serve (spec 4.11): only an allow-list of drawing elements and attributes
 * survives — no <script>, <foreignObject>, <style>, event handlers (on*), external links or entities.
 */
final class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line',
        'polyline', 'polygon', 'text', 'tspan', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-miterlimit', 'opacity', 'transform', 'offset', 'stop-color',
        'stop-opacity', 'gradientunits', 'gradienttransform', 'patternunits', 'clip-path', 'clip-rule', 'mask',
        'font-family', 'font-size', 'font-weight', 'text-anchor', 'dominant-baseline', 'letter-spacing',
        'preserveaspectratio', 'xmlns', 'version', 'href', 'xlink:href', 'fx', 'fy', 'spreadmethod', 'display',
        'visibility', 'style',
    ];

    /** @throws InvalidArgumentException when the file is not a usable SVG */
    public static function sanitize(string $svg): string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            throw new InvalidArgumentException('SVG files with DOCTYPE or entities are not allowed.');
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') {
            throw new InvalidArgumentException('Not a valid SVG image.');
        }

        self::clean($dom->documentElement);

        return $dom->saveXML($dom->documentElement);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $node->removeChild($child);

                    continue;
                }

                self::cleanAttributes($child);
                self::clean($child);
            } elseif ($child->nodeType === XML_PI_NODE || $child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }

        if ($node instanceof DOMElement) {
            self::cleanAttributes($node);
        }
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            $allowed = in_array($name, self::ATTRIBUTES, true) && ! str_starts_with($name, 'on');

            // Links may only point inside the same file (#id); styles may not load anything.
            if (in_array($name, ['href', 'xlink:href'], true) && ! str_starts_with($value, '#')) {
                $allowed = false;
            }

            if ($name === 'style' && preg_match('/url\s*\(|expression|javascript:|@import/i', $value)) {
                $allowed = false;
            }

            if (preg_match('/javascript:|data:/i', $value) && $name !== 'd') {
                $allowed = false;
            }

            if (! $allowed) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
