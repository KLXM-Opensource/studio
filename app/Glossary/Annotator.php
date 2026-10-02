<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

/**
 * Markiert Glossar-Begriffe im fertigen HTML (serverseitig, ohne DOM-Bibliothek, vor dem Seiten-Cache).
 *
 * Regeln:
 *  - Nur lesbarer Inhalt: Gibt es ein <main>, nur darin. Nie in Links, Buttons, Formularfeldern, Beschriftungen, <summary>,
 *    Code (<code>, <pre>, <kbd>, <samp>), <abbr>/<dfn>, Navigation, Seitenkopf/-fuß (header/footer außerhalb von
 *    main/article/section), Überschriften h1…h{headings}, versteckten Bereichen (hidden, aria-hidden, inert, .sr-only …),
 *    bearbeitbaren Bereichen (contenteditable, Redaktionsnotizen) und Bereichen mit data-glossary="off".
 *  - Je Begriff nur das erste Vorkommen – je Seite (mode „page“) oder je Abschnitt <section>/<article> (mode „section“).
 *  - Wortgrenzen mit Unicode (Umlaute, ß), längste Variante zuerst; Abkürzungen (mehrere Großbuchstaben, z. B. SPF, IPv6,
 *    MTA-STS) nur in genau dieser Schreibweise (+ Plural-s), Wörter ohne Rücksicht auf Groß-/Kleinschreibung (+ übliche
 *    Endungen ab 4 Zeichen in der Sprache der Seite, Option lang: Englisch -s/-es bzw. y → ies, sonst die deutschen Endungen).
 *    "In Anführungszeichen" erzwingt die genaue Schreibweise ohne Endungen.
 *  - Bereits markierte Stellen (Hülle .gl) werden übersprungen – zweimal anwenden ändert nichts.
 *
 * Ausgabe je Treffer (Disclosure/„Toggletip“, keine role=tooltip – das Fenster enthält einen Link):
 *   <span class="gl"><button type="button" class="gl-term" popovertarget="gl-1" aria-expanded="false" aria-controls="gl-1">SPF</button>
 *   <span class="gl-pop" id="gl-1" popover>…Begriff, Kurz-Erklärung, „Mehr im Glossar →“…</span></span>
 *
 * Begriffe: list<array{id: int, key: string, term: string, short: string, url: ?string, variants: list<string>, draft?: bool}>
 */
final class Annotator
{
    /** Elemente, in denen nie markiert wird (Inhalt bleibt unberührt) */
    private const SKIP_TAGS = ['a', 'button', 'label', 'summary', 'legend', 'select', 'option', 'textarea', 'input', 'kbd', 'samp', 'var',
        'abbr', 'dfn', 'nav', 'dialog', 'time', 'output', 'meter', 'progress', 'map', 'canvas', 'audio', 'video', 'picture', 'title'];
    /** Bereiche, die ungelesen übernommen werden (Rohtext oder Code) */
    // Rohtext-Bereiche und Kommentare; (?|…) hält die Gruppen gleich (Kommentar: Gruppe 2 leer) – sonst verschob ein Kommentar die
    // Teile von preg_split und der Text danach galt als Rohtext (z. B. Kommentare der Fragmente im Debug-Modus: keine Markierung)
    private const RAW = '~(?|(<(script|style|textarea|template|svg|math|noscript|iframe|object|select|pre|code|head)\b[^>]*>.*?</\2\s*>)|(<!--.*?-->)())~is';
    /** Klassen versteckter bzw. technischer Bereiche */
    private const SKIP_CLASSES = ['gl', 'gl-pop', 'sr-only', 'visually-hidden', 'screen-reader-text', 'sr-text', 'cms-note', 'skip-link', 'notranslate'];
    /** Klassen von Etiketten (Dachzeile, Schlagwort, Badge, Chip) – Endung des Klassennamens */
    private const LABEL_CLASS = '~(^|[-_])(eyebrow|kicker|overline|badge|chip|chips|tag|tags|pill|label)$~';
    private const SKIP_ROLES = ['button', 'link', 'navigation', 'banner', 'contentinfo', 'tab', 'tablist', 'menu', 'menubar', 'menuitem', 'option',
        'switch', 'checkbox', 'radio', 'search', 'toolbar', 'tooltip', 'treeitem', 'textbox', 'combobox', 'listbox', 'dialog', 'alertdialog', 'img', 'math'];
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'param'];
    /** Optional geschlossene Elemente: ein neues gleichartiges schließt das offene */
    private const AUTO_CLOSE = ['p' => ['p'], 'li' => ['li'], 'dt' => ['dt', 'dd'], 'dd' => ['dt', 'dd'], 'tr' => ['tr', 'td', 'th'], 'td' => ['td', 'th'], 'th' => ['td', 'th']];
    /** Übliche Endungen deutscher Wörter (Plural, Genitiv, Dativ) */
    private const SUFFIX_WORD = '(?:e|en|n|s|es|er|ern)?';
    /** Englisch: Plural -s/-es (Wörter auf Konsonant + y: -ies, Annotator::alternative) */
    private const SUFFIX_WORD_EN = '(?:s|es)?';

    private array $byId = [];
    private ?string $rx = null;
    private array $seen = [];
    private int $n = 0;
    /** Anzahl der Markierungen im letzten annotate() */
    public int $count = 0;
    /** Markierte Begriffe (id => true) im letzten annotate() */
    public array $marked = [];
    /** Markierter Text je Begriff (id => Treffer wie im Text, z. B. „SPF-Einträge“) – Quick-Glossar „Auf dieser Seite“ */
    public array $hits = [];

    /**
     * $o: mode (page|section), headings (0–6: h1…hN überspringen, Standard 3), exclude (Liste von Begriffs-IDs),
     *     labels ([more, close, draft]), prefix (ID-Präfix, Standard „gl-“), main (null = automatisch: nur in <main>, falls vorhanden),
     *     lang (Sprache der Seite für die Wortendungen, Standard „de“)
     */
    public function __construct(array $terms, private array $o = [])
    {
        $this->o += ['mode' => 'page', 'headings' => 3, 'exclude' => [], 'labels' => [], 'prefix' => 'gl-', 'main' => null, 'lang' => 'de'];
        $this->o['labels'] += ['more' => 'Mehr im Glossar', 'close' => 'Schließen', 'draft' => 'Entwurf'];
        $exclude = array_map('intval', (array) $this->o['exclude']);
        foreach ($terms as $t) {
            if (in_array((int) $t['id'], $exclude, true) || trim((string) ($t['short'] ?? '')) === '') continue;
            $this->byId[(int) $t['id']] = $t;
        }
        $this->rx = self::pattern(array_values($this->byId), (string) $this->o['lang']);
    }

    /** Regulärer Ausdruck für alle Varianten (längste zuerst, Begriffs-ID als MARK) – null ohne Begriffe. $lang: Wortendungen */
    public static function pattern(array $terms, string $lang = 'de'): ?string
    {
        $alts = [];
        foreach ($terms as $t) {
            foreach (self::variants($t) as $v) {
                [$src, $len] = self::alternative($v, $lang);
                if ($src === '') continue;
                $alts[] = [$len, $src . '(*MARK:' . (int) $t['id'] . ')'];
            }
        }
        if (!$alts) return null;
        usort($alts, fn($a, $b) => $b[0] <=> $a[0]);
        $alts = array_unique(array_column($alts, 1));
        // Linke Grenze: kein Buchstabe/Ziffer davor, auch kein Teil einer Adresse (.@/:) oder Entity (&); rechts ebenso (Satzpunkt erlaubt)
        return '~(?<![\p{L}\p{N}_@./:&=\\\\])(?:' . implode('|', $alts) . ')(?![\p{L}\p{N}_@=]|[.:/][\p{L}\p{N}])~u';
    }

    /** Begriff + Varianten (ohne Leeres und Doppeltes) */
    public static function variants(array $t): array
    {
        $out = [];
        foreach ([(string) $t['term'], ...array_map('strval', (array) ($t['variants'] ?? []))] as $v) {
            $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            if ($v !== '' && !in_array($v, $out, true)) $out[] = $v;
        }
        // "Wort" in Anführungszeichen ersetzt dasselbe Wort ohne (auch den Begriff selbst): nur genaue Schreibweise
        foreach ($out as $v) {
            if (preg_match('~^["„“](.+)["“”]$~u', $v, $m)) $out = array_values(array_filter($out, fn($x) => $x === $v || mb_strtolower($x) !== mb_strtolower(trim($m[1]))));
        }
        return $out;
    }

    /**
     * Abkürzung bzw. Markenschreibweise? Ein Wort mit mindestens zwei Großbuchstaben (SPF, IPv6, DNSSEC, MTA-STS) oder einem
     * Großbuchstaben nach einem Kleinbuchstaben (eID) – dann zählt die genaue Schreibweise.
     */
    public static function caseSensitive(string $v): bool
    {
        foreach (preg_split('~[\s\-/]+~u', $v) ?: [] as $w) {
            if (preg_match_all('~\p{Lu}~u', $w) >= 2 || preg_match('~\p{Ll}\p{Lu}~u', $w)) return true;
        }
        return false;
    }

    /** Sprache mit englischen statt deutschen Wortendungen? (en, en-gb …) */
    public static function english(string $lang): bool
    {
        return $lang === 'en' || str_starts_with($lang, 'en-');
    }

    /** Teil-Ausdruck einer Variante: [Quelle, Länge] */
    private static function alternative(string $v, string $lang = 'de'): array
    {
        $exact = false;
        if (preg_match('~^["„“](.+)["“”]$~u', $v, $m)) {
            $v = trim($m[1]);
            $exact = true;
        }
        if (mb_strlen($v) < 2) return ['', 0];
        $cs = $exact || self::caseSensitive($v);
        // Im HTML stehen &, <, > als Entity; Leerraum darf auch ein geschütztes Leerzeichen sein
        $src = preg_quote(htmlspecialchars($v, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'), '~');
        $src = str_replace(' ', '(?:\s|&nbsp;|&#160;|\x{00A0})+', $src);
        // Endungen nach dem letzten Wort: Abkürzung → Plural-s (CDNs), Wort ab 4 Buchstaben → übliche Endungen (DNS-Lookups)
        $suffix = '';
        $words = preg_split('~[\s\-/]+~u', $v) ?: [''];
        $last = (string) end($words);
        if (!$exact) {
            if (preg_match('~\p{Lu}$~u', $v) && preg_match_all('~\p{Lu}~u', $last) >= 2) $suffix = '(?:s)?';
            elseif (mb_strlen($last) >= 4 && preg_match('~\p{Ll}$~u', $v) && !preg_match('~\p{Ll}\p{Lu}~u', $last)) {
                if (!self::english($lang)) $suffix = self::SUFFIX_WORD;
                // Englisch: entry → entries (Konsonant + y), sonst -s/-es
                elseif (preg_match('~[^aeiouy]y$~iu', $last)) $src = substr($src, 0, -1) . '(?:y|ies)';
                else $suffix = self::SUFFIX_WORD_EN;
            }
        }
        return [($cs ? '(?:' . $src . ')' : '(?i:' . $src . ')') . $suffix, mb_strlen($v)];
    }

    /** Ganze Seite oder Ausschnitt markieren */
    public function annotate(string $html): string
    {
        $this->count = 0;
        $this->marked = [];
        $this->hits = [];
        $this->seen = [];
        if ($this->rx === null || $html === '') return $html;
        // Nur der <body> (Kopf, JSON-LD, Meta-Angaben bleiben unberührt)
        $start = 0;
        $end = strlen($html);
        if (($b = stripos($html, '<body')) !== false) {
            $start = (int) strpos($html, '>', $b) + 1;
            $close = strripos($html, '</body>');
            $end = $close !== false && $close > $start ? $close : $end;
        }
        $body = substr($html, $start, $end - $start);
        // Schon markierte Begriffe (zweiter Durchlauf, z. B. Erweiterung + Kern) gelten als gesehen – nie doppelt
        if (str_contains($body, 'class="gl" data-gl="') && preg_match_all('~class="gl" data-gl="([^"]+)"~', $body, $done)) {
            $keys = array_flip($done[1]);
            foreach ($this->byId as $id => $t) if (isset($keys[e((string) $t['key'])])) $this->seen[$id] = true;
            if (preg_match_all('~id="' . preg_quote((string) $this->o['prefix'], '~') . '(\d+)"~', $body, $ids)) $this->n = max($this->n, ...array_map('intval', $ids[1]));
        }
        $main = $this->o['main'] ?? (bool) preg_match('~<main[\s>]~i', $body);
        $out = $this->walk($body, $main);
        return $this->count ? substr($html, 0, $start) . $out . substr($html, $end) : $html;
    }

    private function walk(string $body, bool $mainOnly): string
    {
        $stack = [];      // [name, skip, main, landmark]
        $skip = 0;
        $inMain = 0;
        $inArticle = 0;   // main/article/section/aside: header/footer darin sind Inhalt
        $maxH = max(0, min(6, (int) $this->o['headings']));
        $out = '';
        $parts = preg_split(self::RAW, $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
        foreach ($parts as $i => $part) {
            if ($i % 3 === 2) continue;                       // Name des Rohtext-Elements (Gruppe 2)
            if ($i % 3 === 1) { $out .= $part; continue; }   // Rohtext-Bereich unverändert
            $tokens = preg_split('~(<[a-zA-Z/!?](?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part];
            foreach ($tokens as $j => $tok) {
                if ($tok === '') continue;
                if ($j % 2 === 1) {
                    $out .= $tok;
                    if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)~', $tok, $m)) continue;   // Kommentar, Doctype
                    $name = strtolower($m[2]);
                    if ($m[1] === '/') {
                        for ($k = count($stack) - 1; $k >= 0; $k--) {
                            if ($stack[$k][0] !== $name) continue;
                            while (count($stack) > $k) {
                                [, $s, $mn, $ar] = array_pop($stack);
                                $skip -= $s ? 1 : 0;
                                $inMain -= $mn ? 1 : 0;
                                $inArticle -= $ar ? 1 : 0;
                            }
                            break;
                        }
                        continue;
                    }
                    if (in_array($name, self::VOID, true) || str_ends_with($tok, '/>')) continue;
                    // Optional geschlossene Elemente (<p>, <li> …) – das offene gleichartige Element endet hier
                    if (isset(self::AUTO_CLOSE[$name]) && $stack && in_array($stack[count($stack) - 1][0], self::AUTO_CLOSE[$name], true)) {
                        [, $s, $mn, $ar] = array_pop($stack);
                        $skip -= $s ? 1 : 0;
                        $inMain -= $mn ? 1 : 0;
                        $inArticle -= $ar ? 1 : 0;
                    }
                    $isMain = $name === 'main' || preg_match('~\srole\s*=\s*["\']?main\b~i', $tok);
                    $isArticle = in_array($name, ['main', 'article', 'section', 'aside'], true);
                    $s = $this->skips($name, $tok, $maxH, $inArticle > 0);
                    $stack[] = [$name, $s, (bool) $isMain, $isArticle];
                    $skip += $s ? 1 : 0;
                    $inMain += $isMain ? 1 : 0;
                    $inArticle += $isArticle ? 1 : 0;
                    if ($this->o['mode'] === 'section' && in_array($name, ['section', 'article'], true)) $this->seen = [];
                    continue;
                }
                $out .= ($skip === 0 && (!$mainOnly || $inMain > 0)) ? $this->text($tok) : $tok;
            }
        }
        return $out;
    }

    /** Wird ein Element übersprungen? */
    private function skips(string $name, string $tag, int $maxH, bool $inContent): bool
    {
        if (in_array($name, self::SKIP_TAGS, true)) return true;
        if ($maxH > 0 && preg_match('~^h([1-6])$~', $name, $h) && (int) $h[1] <= $maxH) return true;
        // Seitenkopf/-fuß – innerhalb von main/article/section sind header/footer Teil des Inhalts (z. B. Einleitung eines Blocks)
        if (($name === 'header' || $name === 'footer') && !$inContent) return true;
        if (!str_contains($tag, '=') && !preg_match('~\s(hidden|inert|contenteditable)\b~i', $tag)) return false;
        if (preg_match('~\sdata-glossary\s*=\s*["\']?off\b~i', $tag)) return true;
        if (preg_match('~\s(hidden|inert|contenteditable)(?=[\s=/>])~i', $tag)) return true;
        if (preg_match('~\saria-hidden\s*=\s*["\']?true\b~i', $tag)) return true;
        if (preg_match('~\srole\s*=\s*["\']?([a-z]+)~i', $tag, $r)) {
            $role = strtolower($r[1]);
            if (in_array($role, self::SKIP_ROLES, true)) return true;
            if ($role === 'heading' && $maxH > 0 && (!preg_match('~aria-level\s*=\s*["\']?(\d)~', $tag, $lv) || (int) $lv[1] <= $maxH)) return true;
        }
        if (preg_match('~\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $c)) {
            $classes = preg_split('~\s+~', trim($c[1] . ($c[2] ?? '') . ($c[3] ?? ''))) ?: [];
            if (array_intersect($classes, self::SKIP_CLASSES)) return true;
            // Etiketten statt Fließtext: Dachzeilen, Schlagwörter, Badges (auch mit Präfix, z. B. cli-eyebrow, card__tag)
            foreach ($classes as $c) if (preg_match(self::LABEL_CLASS, $c)) return true;
        }
        return false;
    }

    /** Textknoten: erstes Vorkommen noch nicht markierter Begriffe ersetzen */
    private function text(string $t): string
    {
        if (!preg_match('~\p{L}{2}~u', $t)) return $t;
        if ($this->o['mode'] !== 'section' && count($this->seen) >= count($this->byId)) return $t;
        return (string) preg_replace_callback($this->rx, function (array $m): string {
            $id = (int) ($m['MARK'] ?? 0);
            if ($id === 0 || isset($this->seen[$id]) || !isset($this->byId[$id])) return $m[0];
            $this->seen[$id] = true;
            $this->marked[$id] = true;
            $this->hits[$id] ??= html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->count++;
            return $this->markup($this->byId[$id], $m[0]);
        }, $t);
    }

    /** Markup eines Treffers (Text des Treffers bleibt wie im Original – Entities inklusive) */
    public function markup(array $t, string $match): string
    {
        $id = $this->o['prefix'] . (++$this->n);
        $l = $this->o['labels'];
        $draft = !empty($t['draft']) ? ' <span class="gl-pop__draft">' . e($l['draft']) . '</span>' : '';
        // „Mehr im Glossar“ nur bei ausführlicher Erklärung; sonst – falls vorhanden – direkt die Quelle (neuer Tab)
        $src = (string) ($t['link'] ?? '');
        $more = !empty($t['url']) && ($t['more'] ?? true)
            ? '<a class="gl-pop__more" href="' . e((string) $t['url']) . '">' . e($l['more']) . ' <span aria-hidden="true">→</span></a>'
            : ($src !== '' && Glossary::safeLink($src)
                ? '<a class="gl-pop__more gl-pop__src" href="' . e($src) . '" target="_blank" rel="noopener">' . e(Glossary::linkHost($src)) . ' <span aria-hidden="true">↗</span><span class="gl-sr"> ' . e($l['newtab'] ?? '') . '</span></a>'
                : '');
        return '<span class="gl" data-gl="' . e((string) $t['key']) . '">'
            . '<button type="button" class="gl-term" popovertarget="' . $id . '" aria-expanded="false" aria-controls="' . $id . '">' . $match . '</button>'
            . '<span class="gl-pop" id="' . $id . '" popover>'
            . '<span class="gl-pop__head"><span class="gl-pop__t">' . e((string) $t['term']) . '</span>' . $draft
            . '<button type="button" class="gl-pop__x" popovertarget="' . $id . '" popovertargetaction="hide" aria-label="' . e($l['close']) . '"><span aria-hidden="true">×</span></button></span>'
            . '<span class="gl-pop__d">' . e((string) $t['short']) . '</span>' . $more
            . '</span></span>';
    }
}
