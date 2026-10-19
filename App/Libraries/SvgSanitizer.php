<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;

/**
 * Cleans an uploaded SVG (only used for the site logo) so it can't carry scripts:
 * an allow list of drawing elements and attributes is kept, everything else is removed —
 * <script>, <foreignObject> (HTML), animation (can rewrite links), on…= handlers,
 * javascript:/external links, and CSS that loads other files. DTDs/entities are refused (XXE).
 *
 * The result is still served with a sandboxing Content-Security-Policy (App\Controller\BrandingFile),
 * so a mistake here cannot run code either.
 */
class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'style',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'marker', 'image',
        'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feconvolvematrix',
        'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'fedropshadow', 'feflood',
        'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur', 'femerge', 'femergenode',
        'femorphology', 'feoffset', 'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence',
    ];

    /** Namespaced attributes that are kept (everything else with a prefix, e.g. inkscape:, is dropped). */
    private const NS_ATTRIBUTES = ['xlink:href', 'xml:space', 'xml:lang'];

    /**
     * The cleaned SVG markup, or null when the file is not a usable SVG.
     */
    public function sanitize(string $svg): ?string
    {
        // No DTD / entities: blocks XML external entity and "billion laughs" tricks.
        if ($svg === '' || stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) {
            return null;
        }

        $doc      = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $doc->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->documentElement;
        if (! $loaded || $root === null || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $this->clean($root);

        // Root namespaces, so the file renders the same everywhere.
        if ($root->namespaceURI === null) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }
        if (str_contains($doc->saveXML($root), 'xlink:') && $root->lookupNamespaceURI('xlink') === null) {
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', 'http://www.w3.org/1999/xlink');
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $doc->saveXML($root) . "\n";
    }

    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! $this->allowedAttribute($attribute->nodeName, $attribute->value)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if (strtolower($element->localName) === 'style') {
            $element->textContent = $this->cleanCss($element->textContent);

            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->localName), self::ELEMENTS, true) && $child->namespaceURI === $element->namespaceURI) {
                    $this->clean($child);
                } else {
                    $element->removeChild($child);
                }
            } elseif (! in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $element->removeChild($child);   // comments, processing instructions
            }
        }
    }

    private function allowedAttribute(string $name, string $value): bool
    {
        $lower = strtolower($name);

        if (str_starts_with($lower, 'on') || ! preg_match('/^[a-z][a-z0-9:_.-]*$/', $lower)) {
            return false;
        }
        if (str_contains($lower, ':') && ! in_array($lower, self::NS_ATTRIBUTES, true)) {
            return false;
        }
        if (in_array($lower, ['href', 'xlink:href'], true)) {
            return $this->allowedReference($value);
        }
        if ($lower === 'style') {
            return $this->cleanCss($value) === $value;
        }

        // Any other value: no script URLs, and url(...) may only point inside this file.
        $plain = strtolower(preg_replace('/\s+/', '', $value));
        if (str_contains($plain, 'javascript:') || str_contains($plain, 'data:text') || str_contains($plain, 'vbscript:')) {
            return false;
        }

        return ! preg_match('/url\((?!\s*[\'"]?#)/i', $value);
    }

    /**
     * Links may point inside the file (#id) or be an embedded raster image.
     */
    private function allowedReference(string $value): bool
    {
        $value = trim($value);

        return str_starts_with($value, '#')
            || preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\s]+$#i', $value) === 1;
    }

    /**
     * CSS without imports, external url(...), or old IE script hooks.
     */
    private function cleanCss(string $css): string
    {
        $css = preg_replace('/@import[^;]*;?/i', '', $css);
        $css = preg_replace('/url\(\s*(?![\'"]?#)[^)]*\)/i', 'none', $css);

        return preg_replace('/(expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', '', $css);
    }
}
