<?php
declare(strict_types=1);

namespace Core;

/**
 * Rich-Text-Whitelist.
 *  - inline:  <b> <strong> <i> <em> <a href> <br> <mark> <sup> <sub> <span class="c-…">
 *  - block:   zusätzlich <p> <ul> <ol> <li> <h2> <h3> <h4> <blockquote>
 * href nur https:, http: (wird zu https:), mailto:, tel:, #anker, /interne-pfade.
 *
 * Rich-Text-Stile (Klassenvertrag für Themes, Handbuch → Technik → „Rich-Text-Stile“) – nur genau diese Werte:
 *  - <p class="t-lead|t-small|t-note">   Hervorgehoben (größer, keine Überschrift), Klein, Hinweis-Box
 *  - <span class="c-accent|c-muted|c-success|c-warning|c-danger">   begrenzte Textfarben (Farben liefert das Theme)
 *  - <ul class="check">                   Häkchen-Liste
 * Links: href (s. o.), optional title, target="_blank" nur zusammen mit rel="noopener" (Option „In neuem Tab öffnen“),
 * data-link="page:12" | "page:12#anker" | "entry:{tabelle}:{id}" | "media:{id}" | "media:{id}:viewer" = stabiler Verweis
 * (Core\Links): Bei der Ausgabe wird href daraus neu berechnet – Links überleben geänderte Adressen.
 * style-, on…- und alle anderen Attribute fallen immer weg.
 */
final class Sanitizer
{
    private const INLINE = ['b', 'strong', 'i', 'em', 'a', 'br', 'mark', 'sup', 'sub', 'span'];
    private const BLOCK  = ['p', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote'];
    /** Erlaubte Absatz-Stile (<p class="…">) */
    public const P_CLASSES = ['t-lead', 't-small', 't-note'];
    /** Erlaubte Textfarben (<span class="c-…">) */
    public const COLORS = ['accent', 'muted', 'success', 'warning', 'danger'];
    /** Tags/Klassen, für die Themes Zusatz-CSS brauchen (conditional_css „@rich“, Core\Theme::conditionalCss) */
    private static bool $styled = false;

    /** Wurden in dieser Anfrage Rich-Text-Stile (h2–h4, Zitat, t-*, c-*, mark, sup/sub) ausgegeben? */
    public static function styled(): bool
    {
        return self::$styled;
    }

    public static function inline(?string $html): string
    {
        return self::clean((string) $html, self::INLINE);
    }

    public static function block(?string $html): string
    {
        return self::clean((string) $html, array_merge(self::INLINE, self::BLOCK));
    }

    public static function safeHref(string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '') {
            return null;
        }
        if (preg_match('~^(#[\w\-]*|/(?!/)[^\s]*)$~u', $href)) {
            return $href;
        }
        if (preg_match('~^(mailto:|tel:)[^\s<>"]+$~i', $href)) {
            return $href;
        }
        if (preg_match('~^https?://[^\s<>"]+$~i', $href)) {
            return preg_replace('~^http://~i', 'https://', $href);
        }
        return null;
    }

    private static function clean(string $html, array $allowed): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!str_contains($html, '<') && !str_contains($html, '&')) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('__r') ?? $doc->documentElement;
        self::normalizeLists($doc, $root);

        return self::walk($root, $allowed);
    }

    /**
     * Browser-Editoren erzeugen teils ungültige Listen:
     *  - <ul> direkt in <ul> (Einrücken) → in den vorherigen <li> verschieben
     *  - <ul>/<ol>/<h2>–<h4>/<blockquote> innerhalb von <p> → <p> auflösen
     */
    private static function normalizeLists(\DOMDocument $doc, \DOMElement $root): void
    {
        $xp = new \DOMXPath($doc);
        foreach (iterator_to_array($xp->query('.//ul/ul | .//ul/ol | .//ol/ul | .//ol/ol', $root)) as $list) {
            $prev = $list->previousSibling;
            while ($prev && !($prev instanceof \DOMElement)) {
                $prev = $prev->previousSibling;
            }
            if ($prev instanceof \DOMElement && strtolower($prev->tagName) === 'li') {
                $prev->appendChild($list);
            } else {
                $li = $doc->createElement('li');
                $list->parentNode->replaceChild($li, $list);
                $li->appendChild($list);
            }
        }
        foreach (iterator_to_array($xp->query('.//p[ul or ol or h2 or h3 or h4 or blockquote or p]', $root)) as $p) {
            while ($p->firstChild) {
                $p->parentNode->insertBefore($p->firstChild, $p);
            }
            $p->parentNode->removeChild($p);
        }
    }

    private static function walk(\DOMNode $node, array $allowed): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'template'], true)) {
                continue;
            }
            // div/span → Inhalt übernehmen; div als Absatz
            if ($tag === 'div' && in_array('p', $allowed, true)) {
                $inner = self::walk($child, $allowed);
                $out .= $inner === '' ? '' : '<p>' . $inner . '</p>';
                continue;
            }
            if (!in_array($tag, $allowed, true)) {
                $out .= self::walk($child, $allowed);
                continue;
            }
            if ($tag === 'br') {
                $out .= '<br>';
                continue;
            }
            $inner = self::walk($child, $allowed);
            if ($tag === 'a') {
                $out .= self::anchor($child, $inner);
                continue;
            }
            if ($tag === 'strong') $tag = 'b';
            if ($tag === 'em') $tag = 'i';
            if ($inner === '' && $tag !== 'li') {
                continue;
            }
            $cls = self::classOf($tag, $child->getAttribute('class'));
            if ($tag === 'span') {
                // Nur Textfarben aus der Liste – sonst bleibt nur der Inhalt
                $out .= $cls === null ? $inner : '<span class="' . $cls . '">' . $inner . '</span>';
                if ($cls !== null) self::$styled = true;
                continue;
            }
            if ($cls !== null || in_array($tag, ['h2', 'h3', 'h4', 'blockquote', 'mark', 'sup', 'sub'], true)) {
                self::$styled = true;
            }
            $out .= $cls !== null ? "<$tag class=\"$cls\">$inner</$tag>" : "<$tag>$inner</$tag>";
        }
        return $out;
    }

    /** Erlaubte Klasse eines Elements (genau ein Wert aus der Whitelist) oder null */
    private static function classOf(string $tag, string $class): ?string
    {
        $tokens = preg_split('~\s+~', trim($class)) ?: [];
        $allowed = match ($tag) {
            'p' => self::P_CLASSES,
            'span' => array_map(fn($c) => 'c-' . $c, self::COLORS),
            'ul' => ['check'],
            default => [],
        };
        foreach ($tokens as $t) {
            if (in_array($t, $allowed, true)) return $t;
        }
        return null;
    }

    /** <a>: href (sicher), stabiler Verweis data-link, optional title und „neuer Tab“ */
    private static function anchor(\DOMElement $a, string $inner): string
    {
        $ref = trim($a->getAttribute('data-link'));
        $ref = Links::isRef($ref) ? $ref : '';
        $href = $ref !== '' ? (self::refHref($ref) ?? self::safeHref($a->getAttribute('href'))) : self::safeHref($a->getAttribute('href'));
        if ($href === null) {
            return $inner;
        }
        $title = trim((string) preg_replace('~\s+~u', ' ', $a->getAttribute('title')));
        $blank = strtolower(trim($a->getAttribute('target'))) === '_blank';
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
            . ($ref !== '' ? ' data-link="' . htmlspecialchars($ref, ENT_QUOTES) . '"' : '')
            . ($title !== '' ? ' title="' . htmlspecialchars(mb_substr($title, 0, 200), ENT_QUOTES) . '"' : '')
            . ($blank ? ' target="_blank" rel="noopener"' : '') . '>' . $inner . '</a>';
    }

    /** Aktuelle Adresse eines Verweises (null: Ziel fehlt oder keine Datenbank → href bleibt) */
    private static function refHref(string $ref): ?string
    {
        try {
            $h = Links::href($ref);
            return $h !== null ? self::safeHref($h) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
