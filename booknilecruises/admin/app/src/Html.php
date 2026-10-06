<?php
declare(strict_types=1);

namespace Bnc;

/**
 * Allowlist HTML sanitiser for trip and post bodies, which the public site prints as-is.
 * Unknown tags are unwrapped (their text stays), dangerous ones are dropped with their
 * content, and only listed attributes with safe values survive.
 */
final class Html
{
    private const DROP = ['script', 'style', 'noscript', 'template', 'object', 'embed', 'applet', 'form', 'input',
        'textarea', 'select', 'option', 'svg', 'math', 'link', 'meta', 'base', 'head', 'title', 'frame', 'frameset'];

    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [], 'span' => [], 'div' => [], 'blockquote' => [], 'figure' => [], 'figcaption' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'table' => [], 'caption' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'iframe' => ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'referrerpolicy', 'loading', 'frameborder'],
    ];

    /** Embeds allowed in iframes. */
    private const IFRAME_HOSTS = ['www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'www.google.com'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="bnc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('bnc-root');
        if (!$root) return '';
        self::walk($root);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) $out .= $doc->saveHTML($child);
        return trim($out);
    }

    private static function walk(\DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction || $node instanceof \DOMCdataSection) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof \DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP, true)) {
                $parent->removeChild($node);
                continue;
            }
            self::walk($node);
            if (!isset(self::TAGS[$tag])) {
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
                continue;
            }
            self::cleanAttributes($node, $tag);
            if ($tag === 'iframe' && !$node->hasAttribute('src')) $parent->removeChild($node);
            if ($tag === 'img' && !$node->hasAttribute('src')) $parent->removeChild($node);
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = [...self::TAGS[$tag], 'class'];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = in_array($name, $allowed, true) && match ($name) {
                'href' => self::safeUrl($value, true),
                'src' => $tag === 'iframe' ? self::safeEmbed($value) : self::safeUrl($value, false),
                'class' => (bool) preg_match('/^[A-Za-z0-9_ -]{0,200}$/', $value),
                'width', 'height', 'colspan', 'rowspan', 'start' => (bool) preg_match('/^\d{1,4}$/', $value),
                'target' => $value === '_blank',
                'loading' => in_array($value, ['lazy', 'eager'], true),
                'decoding' => in_array($value, ['async', 'sync', 'auto'], true),
                'scope' => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true),
                'referrerpolicy' => (bool) preg_match('/^[a-z-]{1,40}$/', $value),
                'allow' => (bool) preg_match('/^[a-z0-9; -]{0,200}$/', $value),
                default => mb_strlen($value) <= 500,
            };
            if (!$keep) $el->removeAttribute($attr->name);
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $rel = array_filter(preg_split('/\s+/', strtolower($el->getAttribute('rel'))) ?: []);
            $el->setAttribute('rel', implode(' ', array_unique([...$rel, 'noopener'])));
        }
        if ($tag === 'a' && $el->hasAttribute('rel')) {
            $rel = array_intersect(preg_split('/\s+/', strtolower($el->getAttribute('rel'))) ?: [], ['noopener', 'noreferrer', 'nofollow', 'sponsored', 'ugc']);
            $rel ? $el->setAttribute('rel', implode(' ', $rel)) : $el->removeAttribute('rel');
        }
    }

    /** Links: http(s), mailto, tel, site-relative paths and #anchors. Images: http(s) and site paths. */
    public static function safeUrl(string $url, bool $link): bool
    {
        // Browsers ignore control characters and whitespace inside schemes ("java\tscript:").
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', $url) ?? '';
        if ($url === '' || str_starts_with($url, '//')) return false;
        if ($url[0] === '/' || ($link && $url[0] === '#')) return true;
        if (preg_match('#^https?://[^/\\\\]#i', $url)) return true;
        return $link && (bool) preg_match('/^(mailto|tel):/i', $url);
    }

    private static function safeEmbed(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', self::IFRAME_HOSTS, true)) return false;
        $path = $parts['path'] ?? '';
        return ($parts['host'] === 'www.google.com') ? str_starts_with($path, '/maps/embed') : true;
    }
}
