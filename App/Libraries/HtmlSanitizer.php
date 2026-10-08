<?php

namespace App\Libraries;

/**
 * Cleans HTML from the editor (CKEditor fields) before it is saved, so stored content can't run
 * scripts on the site or in the admin: removes script-like elements, on* event attributes and
 * javascript:/data: URLs. Normal formatting (headings, lists, tables, links, images, styles) stays.
 */
class HtmlSanitizer
{
    /** Removed together with their content. */
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'meta', 'link', 'base', 'svg', 'math', 'template', 'noscript', 'portal',
    ];

    /** Attributes that hold a URL. */
    private const URL_ATTRS = ['href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'background', 'cite', 'longdesc', 'data'];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc      = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return '';
        }

        $this->walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // saveHTML() percent-encodes URL attributes: keep the {{base_url}} placeholder intact.
        return str_replace('%7B%7Bbase_url%7D%7D', '{{base_url}}', $out);
    }

    private function walk(\DOMNode $node): void
    {
        // Collect first: the list changes while removing.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if (in_array(strtolower($child->tagName), self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child);
            $this->walk($child);
        }
    }

    private function cleanAttributes(\DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name  = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;

            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction'], true)) {
                $element->removeAttribute($attribute->nodeName);
            } elseif (in_array($name, self::URL_ATTRS, true) && ! $this->safeUrl($value, $element->tagName === 'img' && $name === 'src')) {
                $element->removeAttribute($attribute->nodeName);
            } elseif ($name === 'style' && preg_match('/expression\s*\(|javascript:|vbscript:|-moz-binding|behavior\s*:|url\s*\(\s*[\'"]?\s*(javascript|vbscript|data):/i', $value)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // Links that open a new tab can't control this page.
        if (strtolower($element->tagName) === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', trim($element->getAttribute('rel') . ' noopener noreferrer'));
        }
    }

    /**
     * http(s), mailto, tel, relative links and {{base_url}} tokens are fine; javascript:, vbscript:
     * and data: are not (except data: images in <img src>).
     */
    private function safeUrl(string $url, bool $imageSource): bool
    {
        // Browsers ignore control characters and whitespace inside the scheme ("java\tscript:").
        $plain = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (str_starts_with($plain, 'data:')) {
            return $imageSource && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $plain) === 1;
        }

        return ! preg_match('/^(javascript|vbscript|livescript|mocha):/', $plain);
    }
}
