<?php
declare(strict_types=1);

namespace Core;

/**
 * CSS-&-JS-Referenz aus den Quellen (Entwicklerhandbuch „CSS & JS“, php bin/console docs:assets).
 *
 *   AssetDocs::core()            Kern: öffentliche Stylesheets mit Custom Properties (Vorgaben), Ereignisse (cms:* …), data-*-Attribute
 *                                der Kern-Vorlagen, Lader der Kern-Dateien (asset('css/…') im PHP-Code) – gecacht nach Änderungszeit
 *   AssetDocs::kit($dir)         Ein Kit (Ordner): Tokens je Datei, conditional_css (Block → Dateien), Block-Renderer, Ereignisse,
 *                                data-*-Attribute, Bewegung (Keyframes, prefers-reduced-motion, forced-colors je Datei)
 *   AssetDocs::markdown($c, $k)  Markdown für storage/docs/, --out oder kits/{name}/docs/css-js.md (Bereich zwischen den Markern)
 *
 * Nur statisches Lesen der Quellen (resources/, app/, kits/{name}/) – nichts wird ausgeführt außer theme.php des Kits (Definition).
 * Die Parser sind bewusst einfach (keine vollständige CSS-/JS-Grammatik), aber robust gegen Kommentare, Zeichenketten und
 * Klammern (url(data:…;…), var(--a, var(--b, 1px))). Selbsttest: php bin/console docs:selftest.
 */
final class AssetDocs
{
    /** Marker im Kit-Dokument (kits/{name}/docs/css-js.md): dazwischen schreibt docs:assets --update die generierte Referenz */
    public const MARK_START = '<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit={kit} --update, nicht von Hand ändern -->';
    public const MARK_END = '<!-- docs:assets:end -->';

    /**
     * Öffentliche Kern-Stylesheets (Website, für Besucher und Kits) → [Bereich, Klassen-Präfixe, Lader, Präfixe der öffentlichen Variablen].
     * Variablen mit diesen Präfixen sind Schnittstelle (Kits dürfen sie setzen); alle anderen sind intern oder Tokens der Kits, die der Kern liest.
     * Alles andere in resources/css (admin.css, editor.shadow.css, dashboard.css …) ist Oberfläche der Verwaltung bzw. des Editors.
     */
    public const CORE_PUBLIC = [
        'data.css' => ['Datenliste & Datenfelder', 'dl-, df-', 'Theme::conditionalCss (data_list, data_fields) – Kit-Datei css/data.css ersetzt sie', ['--dl-', '--df-']],
        'dataform.css' => ['Formular (Datentabelle)', 'dff-', 'Theme::conditionalCss (data_form, job_apply, Formularseite)', ['--dff-']],
        'glossary.css' => ['Glossar: Begriffe im Text, Hinweisfenster', 'gl-', 'Core\\Glossary\\Glossary (Seiten mit markierten Begriffen) – Kit ergänzt css/glossary.css', ['--gl-']],
        'glossary-list.css' => ['Glossar: Block „Glossar“ (A–Z)', 'glx-', 'Theme::conditionalCss (glossary, Glossary::stylesheets(true))', ['--gl-', '--glx-']],
        'map.css' => ['Karte (MapLibre über /proxy)', 'cms-map', 'Core\\Maps::html() – einmal je Seite', ['--cms-map-']],
        'media.css' => ['Galerie, Slider, Stapelkarten, Lightbox', 'cms-gallery, cms-slider, cms-slide, cms-stack, cms-lb', 'Theme::conditionalCss (gallery, slideshow, stack_cards) – Kit-Datei ersetzt sie', ['--cms-']],
        'calendar.css' => ['Kalender, Nächste Termine', 'cal-', 'Theme::conditionalCss (calendar, upcoming) – Kit-Datei ersetzt sie', ['--cal-']],
        'jobs.css' => ['Stellenangebote: Eckdaten, Bewerbung', 'jf-, job-', 'Theme::conditionalCss (job_facts, job_apply)', ['--job-']],
        'dials.css' => ['Kennzahlen mit Skala', 'cms-dials, cms-dial', 'Theme::conditionalCss (dials)', ['--dial-']],
        'partners.css' => ['Partner & Logos', 'cms-partners, pl-', 'Theme::conditionalCss (partners)', ['--partners-']],
        'notfound.css' => ['Seite „Nicht gefunden“', 'cms-404', 'Theme::conditionalCss (not_found)', ['--nf-']],
        'sections.css' => ['Abschnitte: Vollbild, Hintergrundbild', 'sec-, sec__', 'Theme::conditionalCss (Abschnitts-Optionen)', ['--cms-overlay-']],
        'layout.css' => ['Block „Layout“ (Spalten)', 'lay-', 'Theme::conditionalCss (layout)', ['--lay-']],
        'rows.css' => ['Alte Reihen (Tune row)', 'sec-', 'Theme::conditionalCss (nur alte Inhalte)', ['--row-']],
        'search.css' => ['Website-Suche, Vorschläge', 'srch, sf, ssug', 'Suchseite und Suchfeld im Kopf', ['--se-']],
        'legal-dialog.css' => ['Rechtstexte als Dialog', 'ldlg', 'Core\\LegalDialog', ['--ldlg-']],
        'image-fit.css' => ['Bild im Rahmen (Ausschnitt je Einbindung)', 'img-fit', 'Core\\ImageFit', ['--img-fit-']],
        'image-fx.css' => ['Bild anpassen (Filter je Einbindung)', 'ifx-', 'Core\\ImageFx', []],
        'visitor-chat-start.css' => ['Besucher-Chat: Starter-Knopf', 'cms-chat', 'Core\\AI\\VisitorChat (≤ 1 KB, vor dem Klick)', ['--cms-chat-', '--cb-theme-']],
        'visitor-chat.css' => ['Besucher-Chat: Fenster (Shadow DOM)', 'w__, m__ (im Schatten)', 'visitor-chat.mjs nach dem Klick', ['--cms-chat-', '--cb-theme-']],
        'header-actions.css' => ['Kopfbereich-Aktionen (Suche, Kontakt, Sprache …)', 'ha-', 'header_actions_head() – Teile header-actions-*.css', ['--ha-']],
        'pdfviewer.css' => ['PDF-Betrachter (eigene Seite)', 'pv-', 'eigene Seite app/Views/pdf-viewer.php', []],
        'editor.css' => ['Editor-Teile in der Seite (nur angemeldet)', 'cms-', 'Kit-Layout mit Werkzeugleiste ($toolbar) – Z-Skala, --cms-bar-h', ['--cms-bar-', '--cms-toolbar-']],
    ];

    /** Kern-Stylesheets, die Theme::conditionalCss je Blocktyp lädt (Kit-Datei gleichen Namens unter css/ ersetzt sie; Selbsttest prüft das) */
    public const CORE_CONDITIONAL = [
        'data.css' => ['data_list', 'data_fields'],
        'media.css' => ['gallery', 'slideshow', 'stack_cards'],
        'calendar.css' => ['calendar', 'upcoming'],
        'dataform.css' => ['data_form', 'job_apply'],
        'jobs.css' => ['job_facts', 'job_apply'],
        'notfound.css' => ['not_found'],
        'layout.css' => ['layout'],
        'dials.css' => ['dials'],
        'partners.css' => ['partners'],
        'glossary-list.css' => ['glossary'],
    ];

    // ================================================================== Parser

    /** Kommentare entfernen, Zeichenketten und Zeilen bleiben erhalten (Zeilennummern stimmen weiter) */
    public static function stripCssComments(string $css): string
    {
        return (string) preg_replace_callback('~/\*.*?\*/~s', fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $css);
    }

    /**
     * Custom Properties einer CSS-Datei.
     * @return array{defs: list<array{name:string,value:string,selector:string,at:string,line:int}>, uses: array<string, ?string>}
     *         defs = Deklarationen „--x: wert“ mit Selektor (und umgebender @-Regel), uses = var(--x[, Rückfall]) → erster Rückfall
     */
    public static function cssProperties(string $css): array
    {
        $css = self::stripCssComments($css);
        $defs = [];
        $stack = [];
        $buf = '';
        $line = 1;
        $startLine = 1;
        $depth = 0;      // runde Klammern
        $quote = '';
        $n = strlen($css);
        $flush = function () use (&$buf, &$stack, &$defs, &$startLine): void {
            $decl = trim($buf);
            if (preg_match('~^(--[A-Za-z0-9_-]+)\s*:(.*)$~s', $decl, $m)) {
                $sel = '';
                $at = [];
                foreach ($stack as $s) {
                    if (str_starts_with($s, '@')) $at[] = $s; else $sel = $s;
                }
                $defs[] = ['name' => $m[1], 'value' => self::squash(preg_replace('~\s*!important$~', '', trim($m[2])) ?? ''),
                    'selector' => self::squash($sel), 'at' => self::squash(implode(' ', $at)), 'line' => $startLine];
            }
            $buf = '';
        };
        for ($i = 0; $i < $n; $i++) {
            $c = $css[$i];
            if ($c === "\n") $line++;
            if ($quote !== '') {
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $n) { $buf .= $css[++$i]; continue; }
                if ($c === $quote) $quote = '';
                continue;
            }
            if ($c === '"' || $c === "'") { $quote = $c; $buf .= $c; continue; }
            if ($c === '(') { $depth++; $buf .= $c; continue; }
            if ($c === ')') { $depth = max(0, $depth - 1); $buf .= $c; continue; }
            if ($depth > 0) { $buf .= $c; continue; }
            if ($c === '{') {
                // Verschachteltes CSS: Deklarationen vor dem Kind-Selektor stehen mit „;“ davor – der Rest ist der Selektor
                $stack[] = trim($buf);
                $buf = '';
                $startLine = $line;
                continue;
            }
            if ($c === ';') { $flush(); $startLine = $line; continue; }
            if ($c === '}') { $flush(); array_pop($stack); $startLine = $line; continue; }
            if ($buf === '' || trim($buf) === '') $startLine = $line;
            $buf .= $c;
        }
        $uses = self::varUses($css, $nested);
        return ['defs' => $defs, 'uses' => $uses, 'nested' => $nested];
    }

    /**
     * var(--x, Rückfall) → [--x => Rückfall|null] (erster Fund je Name, Rückfall mit verschachtelten Klammern).
     * $nested: Namen, die nur innerhalb des Rückfalls eines anderen var() vorkommen (Rückfall-Ketten wie var(--gl-accent, var(--b-link, …)))
     */
    public static function varUses(string $src, ?array &$nested = null): array
    {
        $out = [];
        $offset = 0;
        $ranges = [];
        $outer = [];
        while (preg_match('~var\(\s*(--[A-Za-z0-9_-]+)\s*(,)?~', $src, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $name = $m[1][0];
            $at = $m[0][1];
            $offset = $m[0][1] + strlen($m[0][0]);
            $fallback = null;
            $inside = false;
            foreach ($ranges as [$a, $b]) if ($at >= $a && $at < $b) { $inside = true; break; }
            if (!$inside) $outer[$name] = true;
            if (!empty($m[2][0])) {
                $depth = 1;
                $j = $offset;
                $len = strlen($src);
                while ($j < $len && $depth > 0) {
                    if ($src[$j] === '(') $depth++;
                    elseif ($src[$j] === ')') $depth--;
                    $j++;
                }
                $fallback = self::squash(substr($src, $offset, max(0, $j - 1 - $offset)));
                $ranges[] = [$offset, $j];
            }
            if (!array_key_exists($name, $out) || ($out[$name] === null && $fallback !== null)) $out[$name] = $fallback;
        }
        $nested = array_values(array_diff(array_keys($out), array_keys($outer)));
        return $out;
    }

    /**
     * Namensraum-Ereignisse in JavaScript (Name mit Doppelpunkt, z. B. cms:saved, klxm:inview):
     * gesendet über new CustomEvent/Event(…), emit(…) bzw. CMSAdmin.events.emit(…); empfangen über addEventListener(…)/ctx.on(…).
     * @return list<array{name:string,kind:string,detail:?string,line:int}>
     */
    public static function jsEvents(string $js): array
    {
        $js = self::stripJsComments($js);
        $out = [];
        $q = '([\'"`])([a-z][A-Za-z0-9_-]*:[A-Za-z0-9:_-]+)\1';
        $rules = [
            'dispatch' => ['~new\s+(?:Custom)?Event\(\s*' . $q . '~', '~\bemit\(\s*' . $q . '~'],
            'listen' => ['~addEventListener\(\s*' . $q . '~', '~\.on\(\s*' . $q . '~'],
        ];
        foreach ($rules as $kind => $patterns) {
            foreach ($patterns as $p) {
                if (!preg_match_all($p, $js, $mm, PREG_OFFSET_CAPTURE)) continue;
                foreach ($mm[2] as $k => [$name, $pos]) {
                    $after = $mm[0][$k][1] + strlen($mm[0][$k][0]);
                    $out[] = ['name' => $name, 'kind' => $kind, 'detail' => $kind === 'dispatch' ? self::payload($js, $after) : null,
                        'line' => substr_count(substr($js, 0, $pos), "\n") + 1];
                }
            }
        }
        usort($out, fn($a, $b) => [$a['line'], $a['name']] <=> [$b['line'], $b['name']]);
        return $out;
    }

    /** Zweites Argument nach dem Ereignisnamen (Payload bzw. { detail: … }), gekürzt */
    private static function payload(string $js, int $pos): ?string
    {
        if (!preg_match('~\G\s*,\s*~', $js, $m, 0, $pos)) return null;
        $i = $pos + strlen($m[0]);
        $depth = 0;
        $start = $i;
        $len = strlen($js);
        for (; $i < $len; $i++) {
            $c = $js[$i];
            if ($c === '(' || $c === '{' || $c === '[') $depth++;
            elseif ($c === ')' || $c === '}' || $c === ']') { if ($depth === 0) break; $depth--; }
            elseif ($c === ',' && $depth === 0) break;
        }
        $p = self::squash(substr($js, $start, $i - $start));
        // new CustomEvent(name, { detail: X, bubbles, … }) → X (Kurzform { detail } → detail)
        if (str_starts_with($p, '{') && str_ends_with($p, '}') && preg_match('~(?:^|[{,])\s*detail\s*(?=[:,}])~', $p)) {
            foreach (self::members(substr($p, 1, -1)) as $mem) {
                if (preg_match('~^detail\s*(?::\s*(.+))?$~s', $mem, $d)) { $p = trim($d[1] ?? 'detail'); break; }
            }
        }
        return $p === '' ? null : (mb_strlen($p) > 140 ? mb_substr($p, 0, 139) . '…' : $p);
    }

    /** Glieder eines Objekt-Literals auf oberster Ebene (Komma außerhalb von Klammern) */
    private static function members(string $body): array
    {
        $out = [];
        $depth = 0;
        $cur = '';
        foreach (str_split($body) as $c) {
            if ($c === '(' || $c === '{' || $c === '[') $depth++;
            elseif ($c === ')' || $c === '}' || $c === ']') $depth--;
            if ($c === ',' && $depth === 0) { $out[] = trim($cur); $cur = ''; continue; }
            $cur .= $c;
        }
        if (trim($cur) !== '') $out[] = trim($cur);
        return $out;
    }

    /** Kommentare in JavaScript entfernen: Blockkommentare und Zeilen, die mit // beginnen (Zeilennummern bleiben) */
    public static function stripJsComments(string $js): string
    {
        $js = (string) preg_replace_callback('~/\*.*?\*/~s', fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $js);
        return (string) preg_replace('~^[ \t]*//.*$~m', '', $js);
    }

    /** data-*-Attribute in Vorlagen (HTML/PHP: data-x= bzw. 'data-x' => …) und Skripten (dataset.x, [data-x], getAttribute('data-x')) */
    public static function dataAttributes(string $src, bool $script = false): array
    {
        $names = [];
        if (preg_match_all('~(?<![\w-])data-([a-z][a-z0-9-]*[a-z0-9])(?=\s*=|[\s>\]\'"/]|$)~', $src, $m)) {
            foreach ($m[1] as $n) $names[$n] = true;
        }
        if ($script && preg_match_all('~\.dataset\.([a-zA-Z][a-zA-Z0-9]*)~', $src, $m)) {
            foreach ($m[1] as $n) $names[strtolower((string) preg_replace('~([A-Z])~', '-$1', $n))] = true;
        }
        $names = array_keys($names);
        sort($names);
        return array_map(fn($n) => 'data-' . $n, $names);
    }

    /** Bewegung und Barrierefreiheit einer CSS-Datei: @keyframes, Animationen/Übergänge, prefers-reduced-motion, forced-colors, :focus-visible */
    public static function motion(string $css): array
    {
        $css = self::stripCssComments($css);
        preg_match_all('~@keyframes\s+([\w-]+)~', $css, $k);
        return [
            'keyframes' => array_values(array_unique($k[1])),
            'animates' => (bool) preg_match('~(?<![\w-])(animation(-name)?|transition)\s*:\s*(?!none\b)~', $css),
            'reduced' => str_contains($css, 'prefers-reduced-motion'),
            'forced' => str_contains($css, 'forced-colors'),
            'focus' => str_contains($css, ':focus-visible'),
            'scroll' => (bool) preg_match('~animation-timeline|view-timeline|scroll-timeline~', $css),
        ];
    }

    /** CSS-Datei mit aufgelösten lokalen @import "./_x.css" (wie esbuild beim Build) */
    public static function readCss(string $file, int $level = 0): string
    {
        $css = (string) @file_get_contents($file);
        if ($level > 4) return $css;
        return (string) preg_replace_callback('~@import\s+(?:url\()?["\'](\./[^"\']+\.css)["\']\)?\s*;~', function ($m) use ($file, $level) {
            $inc = dirname($file) . '/' . substr($m[1], 2);
            return is_file($inc) ? self::readCss($inc, $level + 1) : $m[0];
        }, $css);
    }

    private static function squash(string $s): string
    {
        return trim((string) preg_replace('~\s+~', ' ', $s));
    }

    // ================================================================== Sammeln

    /** Kern-Referenz (gecacht in storage/cache, Schlüssel = jüngste Änderung der Quellen) */
    public static function core(bool $fresh = false): array
    {
        $dirs = [ROOT . '/resources/css', ROOT . '/resources/js', ROOT . '/app/Blocks', ROOT . '/app/Views'];
        $stamp = 0;
        foreach ($dirs as $d) {
            foreach (self::files($d, ['css', 'js', 'mjs', 'php']) as $f) $stamp = max($stamp, (int) filemtime($f));
        }
        $stamp = max($stamp, (int) filemtime(__FILE__));
        $cache = ROOT . '/storage/cache/asset-docs-core.json';
        if (!$fresh && is_file($cache)) {
            $c = json_decode((string) file_get_contents($cache), true);
            if (is_array($c) && ($c['stamp'] ?? 0) === $stamp) return $c;
        }
        $r = self::collectCore();
        $r['stamp'] = $stamp;
        if (is_dir(dirname($cache)) && is_writable(dirname($cache))) @file_put_contents($cache, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $r;
    }

    private static function collectCore(): array
    {
        $css = [];
        foreach (self::CORE_PUBLIC as $file => [$label, $prefix, $loader, $vars]) {
            $path = ROOT . '/resources/css/' . $file;
            if (!is_file($path)) continue;
            $src = self::readCss($path);
            $css[$file] = ['label' => $label, 'prefix' => $prefix, 'loader' => $loader, 'vars' => $vars] + self::props($src) + ['motion' => self::motion($src)];
        }
        // Teile der Kopfbereich-Aktionen gehören zu header-actions.css
        foreach (glob(ROOT . '/resources/css/header-actions-*.css') ?: [] as $p) {
            if (!isset($css['header-actions.css'])) break;
            $more = self::props(self::readCss($p));
            $css['header-actions.css']['props'] += $more['props'];
            $css['header-actions.css']['chain'] = array_values(array_unique(array_merge($css['header-actions.css']['chain'], $more['chain'])));
        }
        $events = [];
        foreach (self::files(ROOT . '/resources/js', ['js', 'mjs']) as $f) {
            self::addEvents($events, (string) file_get_contents($f), 'resources/js/' . basename($f));
        }
        foreach (glob(ROOT . '/extensions/*/assets/js/*.{js,mjs}', GLOB_BRACE) ?: [] as $f) {
            self::addEvents($events, (string) file_get_contents($f), Kit::relative($f));
        }
        foreach ($css as &$c) {
            // Öffentlich = Präfix der Komponente; Rest (Hilfsvariablen, Tokens der Kits) als Liste
            foreach ($c['props'] as $n => $p) {
                if (!array_filter($c['vars'], fn($v) => str_starts_with($n, $v))) { $c['chain'][] = $n; unset($c['props'][$n]); }
            }
            $c['chain'] = array_values(array_unique($c['chain']));
            sort($c['chain']);
        }
        unset($c);
        ksort($events);
        $data = [];
        foreach (array_merge(self::files(ROOT . '/app/Blocks', ['php']), self::files(ROOT . '/app/Views', ['php']), self::frontendClasses()) as $f) {
            foreach (self::dataAttributes((string) file_get_contents($f)) as $a) $data[$a][] = Kit::relative($f);
        }
        ksort($data);
        // Wer lädt welche Kern-Datei? asset('css/…') / asset('js/…') im PHP-Code (ohne Verwaltung)
        $loaders = [];
        foreach (self::files(ROOT . '/app', ['php']) as $f) {
            $rel = Kit::relative($f);
            if (str_starts_with($rel, 'app/Admin/')) continue;
            if (preg_match_all('~(?:\basset|coreCss)\(\s*\'((?:css/|js/)?[a-z0-9._-]+\.(?:css|m?js))\'~', (string) file_get_contents($f), $m)) {
                foreach ($m[1] as $a) $loaders[str_contains($a, '/') ? $a : 'css/' . $a][] = $rel;
            }
        }
        ksort($loaders);
        foreach ($loaders as &$l) $l = array_values(array_unique($l));
        unset($l);
        return ['css' => $css, 'events' => $events, 'data' => $data, 'loaders' => $loaders, 'globals' => self::globals(self::files(ROOT . '/resources/js', ['js', 'mjs']))];
    }

    /** Kern-Klassen, die Website-HTML ausgeben (für data-*-Attribute): Karten, Medien, Glossar, Chat, Daten, Suche … */
    private static function frontendClasses(): array
    {
        $list = ['Maps.php', 'Embeds.php', 'Media.php', 'MediaBlocks.php', 'LegalDialog.php', 'HeaderActions.php', 'Block.php', 'Theme.php', 'ImageFit.php', 'ImageFx.php',
            'TargetEdit.php', 'EditorNotes.php', 'AI/VisitorChat.php', 'Glossary/Glossary.php', 'Data/EntryEdit.php', 'Data/DataForms.php', 'Search/Search.php', 'Toolbar.php'];
        return array_values(array_filter(array_map(fn($f) => ROOT . '/app/' . $f, $list), 'is_file'));
    }

    /** window.X = … in Skripten */
    private static function globals(array $files, string $base = ''): array
    {
        $out = [];
        foreach ($files as $f) {
            if (preg_match_all('~window\.([A-Za-z_$][\w$]*)\s*=(?!=)~', self::stripJsComments((string) file_get_contents($f)), $m)) {
                foreach ($m[1] as $g) $out[$g][] = $base !== '' && str_starts_with($f, $base . '/') ? substr($f, strlen($base) + 1) : Kit::relative($f);
            }
        }
        ksort($out);
        return array_map(fn($l) => array_values(array_unique($l)), $out);
    }

    private static function addEvents(array &$events, string $js, string $rel): void
    {
        foreach (self::jsEvents($js) as $e) {
            $events[$e['name']][$e['kind']][] = $rel . ':' . $e['line'];
            if ($e['detail'] !== null && !isset($events[$e['name']]['detail'])) $events[$e['name']]['detail'] = $e['detail'];
        }
    }

    /**
     * Öffentliche Variablen einer Datei: Definitionen (ohne private --_x) und Rückfälle aus var(--x, …), die die Datei nicht selbst setzt.
     * @return array{props: array<string, array{default:?string, where:string, kind:string}>, chain: list<string>}
     *         chain = Variablen, die nur in Rückfall-Ketten vorkommen (meist Tokens der Kits, z. B. --b-link)
     */
    private static function props(string $src): array
    {
        $p = self::cssProperties($src);
        $props = [];
        foreach ($p['defs'] as $d) {
            if (str_starts_with($d['name'], '--_') || isset($props[$d['name']])) continue;
            $props[$d['name']] = ['default' => $d['value'], 'where' => $d['selector'] . ($d['at'] !== '' ? ' (' . $d['at'] . ')' : ''), 'kind' => 'gesetzt'];
        }
        $chain = [];
        foreach ($p['uses'] as $name => $fb) {
            if (str_starts_with($name, '--_') || isset($props[$name])) continue;
            if (in_array($name, $p['nested'], true)) { $chain[] = $name; continue; }
            $props[$name] = ['default' => $fb, 'where' => '', 'kind' => $fb === null ? 'erwartet' : 'Rückfall'];
        }
        ksort($props);
        sort($chain);
        return ['props' => $props, 'chain' => $chain];
    }

    /**
     * Ein Kit aus seinem Ordner (kits/{name} oder beliebiger Pfad, z. B. aus einer anderen Installation).
     * Lädt theme.php (bzw. kit.php) für die Definition – keine Datenbank, keine Website nötig.
     */
    /** Erste Funktion, die das Kit deklariert und die es schon gibt (sonst null) – PHP-Dateien außer node_modules/vendor */
    private static function functionClash(string $dir): ?string
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            fn($f) => !in_array($f->getFilename(), ['node_modules', 'vendor', '.git'], true)));
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) !== 'php') continue;
            if (!preg_match_all('~^\s*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(~m', (string) file_get_contents($f->getPathname()), $m)) continue;
            foreach ($m[1] as $fn) if (function_exists($fn) && (new \ReflectionFunction($fn))->getFileName() !== $f->getPathname()) return $fn;
        }
        return null;
    }

    public static function kit(string $dir): array
    {
        $dir = rtrim($dir, '/');
        $defFile = Kit::definitionFile($dir);
        $def = [];
        if ($defFile) {
            // Doppelte Funktionsnamen (z. B. zwei Kits mit gleichem Präfix) wären ein nicht abfangbarer Fehler – dann nicht laden
            if ($clash = self::functionClash($dir)) $def = ['_error' => __('Nicht geladen: Funktion {fn}() gibt es schon (anderes Kit mit gleichen Namen).', ['fn' => $clash])];
            else try { $def = (array) (static fn($f) => require $f)($defFile); } catch (\Throwable $e) { $def = ['_error' => $e->getMessage()]; }
        }
        $name = basename($dir);
        $assets = $dir . '/assets';
        // Grundlage: Dateien, die das Layout immer lädt (theme_asset('…') / ->asset('…') in templates/)
        $base = [];
        foreach (self::files($dir . '/templates', ['php']) as $f) {
            if (preg_match_all('~(?:theme_asset|->asset)\(\s*\'([^\']+\.(?:css|m?js))\'~', (string) file_get_contents($f), $m)) {
                foreach ($m[1] as $a) $base[$a][] = substr($f, strlen($dir) + 1);
            }
        }
        ksort($base);
        $cond = [];
        foreach ((array) ($def['conditional_css'] ?? []) as $file => $needs) $cond[(string) $file] = array_values(array_map('strval', (array) $needs));
        // Blöcke: Definition + Renderer + bedingte Dateien (Typ oder Typ:Variante)
        $blocks = [];
        $types = array_keys((array) ($def['blocks'] ?? []));
        // Kern-Blöcke mit eigenem Renderer des Kits (blocks/{typ}.php überschreibt app/Blocks/{typ}.php); Teile wie _x.php/x-y.php sind keine Blöcke
        $coreTypes = array_keys((array) (require ROOT . '/app/Blocks/blocks.php')) + [];
        $coreTypes[] = Layout::TYPE;
        foreach (glob($dir . '/blocks/*.php') ?: [] as $f) {
            $t = basename($f, '.php');
            if (preg_match('~^[a-z][a-z0-9_]*$~', $t) && in_array($t, $coreTypes, true)) $types[] = $t;
        }
        foreach (array_unique($types) as $t) {
            $files = [];
            foreach ($cond as $file => $needs) {
                foreach ($needs as $n) {
                    if ($n === $t || str_starts_with($n, $t . ':')) { $files[$file][] = $n === $t ? '' : substr($n, strlen($t) + 1); }
                }
            }
            $blocks[$t] = [
                'label' => (string) ($def['blocks'][$t]['label'] ?? ''),
                'renderer' => is_file($dir . '/blocks/' . $t . '.php') ? 'blocks/' . $t . '.php' : (isset($def['blocks'][$t]) ? '' : 'blocks/' . $t . '.php'),
                'core' => !isset($def['blocks'][$t]),   // Kern-Block mit Renderer des Kits
                'files' => array_map(fn($v) => array_values(array_filter(array_unique($v))), $files),
                'variants' => array_keys((array) ($def['blocks'][$t]['variants'] ?? [])),
            ];
        }
        ksort($blocks);
        // CSS: Tokens (Variablen) und Bewegung je Datei
        $css = [];
        foreach (self::files($assets . '/css', ['css']) as $f) {
            $src = self::readCss($f);
            $p = self::cssProperties($src);
            $root = [];
            foreach ($p['defs'] as $d) {
                if (str_starts_with($d['name'], '--_')) continue;
                $key = $d['name'];
                $root[$key] ??= ['default' => $d['value'], 'where' => $d['selector'], 'variants' => 0];
                if ($root[$key]['default'] !== $d['value']) $root[$key]['variants']++;
            }
            ksort($root);
            $css['css/' . substr($f, strlen($assets . '/css') + 1)] = ['props' => $root, 'motion' => self::motion($src), 'bytes' => strlen($src)];
        }
        $events = [];
        $js = [];
        foreach (self::files($assets . '/js', ['js', 'mjs']) as $f) {
            $src = (string) file_get_contents($f);
            $rel = 'js/' . substr($f, strlen($assets . '/js') + 1);
            self::addEvents($events, $src, $rel);
            $js[$rel] = [
                'reduced' => str_contains($src, 'prefers-reduced-motion'),
                'observer' => str_contains($src, 'IntersectionObserver'),
                'raf' => str_contains($src, 'requestAnimationFrame'),
                'module' => str_ends_with($f, '.mjs') || str_contains($src, 'import('),
                'bytes' => strlen($src),
            ];
        }
        ksort($events);
        $data = [];
        foreach (['blocks', 'templates', 'partials', 'fragments'] as $sub) {
            foreach (self::files($dir . '/' . $sub, ['php']) as $f) {
                foreach (self::dataAttributes((string) file_get_contents($f)) as $a) $data[$a][] = substr($f, strlen($dir) + 1);
            }
        }
        foreach (self::files($assets . '/js', ['js', 'mjs']) as $f) {
            foreach (self::dataAttributes((string) file_get_contents($f), true) as $a) $data[$a][] = 'assets/' . substr($f, strlen($assets) + 1);
        }
        if (is_file($dir . '/functions.php')) {
            foreach (self::dataAttributes((string) file_get_contents($dir . '/functions.php')) as $a) $data[$a][] = 'functions.php';
        }
        ksort($data);
        foreach ($data as &$l) $l = array_values(array_unique($l));
        unset($l);
        $design = [];
        foreach ((array) ($def['design']['tokens'] ?? []) as $k => $t) {
            if (is_array($t)) $design[(string) $k] = ['label' => (string) ($t['label'] ?? ''), 'type' => (string) ($t['type'] ?? ''), 'var' => (string) ($t['var'] ?? ''), 'default' => is_scalar($t['default'] ?? null) ? (string) $t['default'] : ''];
        }
        return ['name' => $name, 'label' => (string) ($def['label'] ?? $name), 'version' => (string) ($def['version'] ?? ''), 'error' => $def['_error'] ?? null,
            'base' => $base, 'conditional' => $cond, 'blocks' => $blocks, 'css' => $css, 'js' => $js, 'events' => $events, 'data' => $data, 'design' => $design,
            'globals' => self::globals(self::files($assets . '/js', ['js', 'mjs']), $assets)];
    }

    /** Dateien eines Ordners (rekursiv) mit den Endungen, sortiert; node_modules/vendor ausgenommen */
    private static function files(string $dir, array $ext): array
    {
        if (!is_dir($dir)) return [];
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            fn($f) => !($f->isDir() && in_array($f->getFilename(), ['node_modules', 'vendor', 'fonts', 'img', '.git'], true))
        ));
        foreach ($it as $f) {
            if ($f->isFile() && in_array(strtolower($f->getExtension()), $ext, true)) $out[] = $f->getPathname();
        }
        sort($out);
        return $out;
    }

    // ================================================================== Ausgabe

    /** Markdown der Referenz: Kern (ohne $kit) bzw. Kit-Teil (mit $kit; $withCore = false nur das Kit) */
    public static function markdown(?array $core, ?array $kit): string
    {
        $md = [];
        $esc = fn(?string $s) => $s === null || $s === '' ? '–' : '`' . str_replace(['|', '`'], ['\\|', "'"], $s) . '`';
        $cell = fn(string $s) => str_replace('|', '\\|', $s);
        if ($core) {
            $md[] = '# CSS & JS – Kern-Referenz (generiert)';
            $md[] = '';
            $md[] = 'Erzeugt mit `php bin/console docs:assets` aus `resources/css`, `resources/js`, `app/Blocks`, `app/Views` (' . CMS_NAME . ' ' . CMS_VERSION . ').';
            $md[] = '';
            $md[] = '## Öffentliche Stylesheets und ihre Variablen';
            foreach ($core['css'] as $file => $c) {
                $md[] = '';
                $md[] = '### ' . $file . ' – ' . $c['label'];
                $md[] = '';
                $md[] = 'Präfix: ' . $esc($c['prefix']) . ' · geladen: ' . $cell($c['loader']);
                if (!$c['props']) {
                    $md[] = '';
                    $md[] = '_Keine öffentlichen Variablen._' . ($c['chain'] ? ' Intern: ' . implode(', ', array_map(fn($n) => '`' . $n . '`', $c['chain'])) : '');
                    continue;
                }
                $md[] = '';
                $md[] = '| Variable | Vorgabe | Art |';
                $md[] = '|---|---|---|';
                foreach ($c['props'] as $n => $p) $md[] = '| `' . $n . '` | ' . $esc($p['default']) . ' | ' . $p['kind'] . ($p['where'] !== '' ? ' an ' . $esc($p['where']) : '') . ' |';
                if ($c['chain']) { $md[] = ''; $md[] = 'Weitere Variablen (intern bzw. Tokens der Kits, die die Datei liest – keine Schnittstelle): ' . implode(', ', array_map(fn($n) => '`' . $n . '`', $c['chain'])); }
            }
            $md[] = '';
            $md[] = '## Ereignisse (CustomEvent am document)';
            $md[] = '';
            $md[] = '| Ereignis | Payload (Quelltext) | gesendet in | empfangen in |';
            $md[] = '|---|---|---|---|';
            foreach ($core['events'] as $n => $e) {
                $md[] = '| `' . $n . '` | ' . $esc($e['detail'] ?? null) . ' | ' . $cell(implode(', ', array_unique($e['dispatch'] ?? []))) . ' | ' . $cell(implode(', ', array_unique($e['listen'] ?? []))) . ' |';
            }
            $md[] = '';
            $md[] = '## Globale Objekte (window.*)';
            $md[] = '';
            foreach ($core['globals'] as $g => $files) $md[] = '- `' . $g . '` – ' . implode(', ', $files);
            $md[] = '';
            $md[] = '## Lader der Kern-Dateien';
            $md[] = '';
            $md[] = '| Datei | geladen von |';
            $md[] = '|---|---|';
            foreach ($core['loaders'] as $a => $by) $md[] = '| `' . $a . '` | ' . $cell(implode(', ', $by)) . ' |';
            $md[] = '';
            $md[] = '## data-*-Attribute in Kern-Vorlagen';
            $md[] = '';
            $md[] = '| Attribut | Fundstellen |';
            $md[] = '|---|---|';
            foreach ($core['data'] as $a => $files) $md[] = '| `' . $a . '` | ' . $cell(implode(', ', array_slice(array_unique($files), 0, 6)) . (count(array_unique($files)) > 6 ? ' …' : '')) . ' |';
        }
        if ($kit) {
            if ($md) $md[] = '';
            $md = array_merge($md, self::kitMarkdown($kit));
        }
        return implode("\n", $md) . "\n";
    }

    /** Kit-Teil als Markdown-Zeilen (Überschriften ab Ebene $h) */
    public static function kitMarkdown(array $k, int $h = 2): array
    {
        $H = fn(int $lvl, string $t) => str_repeat('#', $lvl) . ' ' . $t;
        $esc = fn(?string $s) => $s === null || $s === '' ? '–' : '`' . str_replace(['|', '`'], ['\\|', "'"], $s) . '`';
        $cell = fn(string $s) => str_replace('|', '\\|', $s);
        $md = [];
        $md[] = $H($h, 'Generierte Referenz: Kit „' . $k['label'] . '“ (' . $k['name'] . ($k['version'] !== '' ? ' ' . $k['version'] : '') . ')');
        if ($k['error']) { $md[] = ''; $md[] = '> theme.php konnte nicht gelesen werden: ' . $k['error']; }
        $md[] = '';
        $md[] = $H($h + 1, 'Von Vorlagen eingebunden (theme_asset() in templates/)');
        $md[] = '';
        if (!$k['base']) $md[] = '_Keine Dateien über theme_asset() in templates/ gefunden._';
        else $md[] = 'Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.';
        if ($k['base']) $md[] = '';
        foreach ($k['base'] as $f => $by) $md[] = '- `' . $f . '` – ' . implode(', ', array_unique($by));
        $md[] = '';
        $md[] = $H($h + 1, 'Bedingt geladen – theme.php → conditional_css');
        $md[] = '';
        if (!$k['conditional']) $md[] = '_Keine Einträge._';
        else {
            $md[] = '| Datei | lädt bei |';
            $md[] = '|---|---|';
            foreach ($k['conditional'] as $f => $needs) $md[] = '| `' . $f . '` | ' . $cell(implode(', ', $needs)) . ' |';
        }
        $md[] = '';
        $md[] = $H($h + 1, 'Blöcke → Dateien');
        $md[] = '';
        $md[] = '| Block | Renderer | Varianten | CSS/JS (Variante) |';
        $md[] = '|---|---|---|---|';
        foreach ($k['blocks'] as $t => $b) {
            $files = [];
            foreach ($b['files'] as $f => $vars) $files[] = '`' . $f . '`' . ($vars ? ' (' . implode(', ', $vars) . ')' : '');
            $md[] = '| `' . $t . '`' . ($b['label'] !== '' ? ' ' . $cell($b['label']) : '') . ($b['core'] ? ' · Kern-Block, eigener Renderer' : '') . ' | ' . ($b['renderer'] !== '' ? '`' . $b['renderer'] . '`' : '–')
                . ' | ' . ($b['variants'] ? $cell(implode(', ', $b['variants'])) : '–') . ' | ' . ($files ? implode(', ', $files) : '–') . ' |';
        }
        if ($k['design']) {
            $md[] = '';
            $md[] = $H($h + 1, 'Design-Tokens (Style-Editor, theme.php → design)');
            $md[] = '';
            $md[] = '| Token | Variable | Art | Vorgabe |';
            $md[] = '|---|---|---|---|';
            foreach ($k['design'] as $n => $t) $md[] = '| `' . $n . '` ' . $cell($t['label']) . ' | ' . $esc($t['var']) . ' | ' . $cell($t['type']) . ' | ' . $esc($t['default']) . ' |';
        }
        $md[] = '';
        $md[] = $H($h + 1, 'Variablen je Stylesheet');
        foreach ($k['css'] as $f => $c) {
            if (!$c['props']) continue;
            $md[] = '';
            $md[] = '**' . $f . '** (' . count($c['props']) . ')';
            $md[] = '';
            $md[] = '| Variable | erster Wert | an | weitere Werte |';
            $md[] = '|---|---|---|---|';
            foreach ($c['props'] as $n => $p) $md[] = '| `' . $n . '` | ' . $esc($p['default']) . ' | ' . $esc($p['where']) . ' | ' . ($p['variants'] ?: '–') . ' |';
        }
        $md[] = '';
        $md[] = $H($h + 1, 'Bewegung & Barrierefreiheit je Datei');
        $md[] = '';
        $md[] = '| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |';
        $md[] = '|---|---|---|---|---|---|';
        $yn = fn(bool $b) => $b ? 'ja' : '–';
        foreach ($k['css'] as $f => $c) {
            $m = $c['motion'];
            if (!$m['keyframes'] && !$m['animates'] && !$m['reduced'] && !$m['forced']) continue;
            $warn = ($m['keyframes'] || $m['scroll']) && !$m['reduced'] ? ' ⚠' : '';
            $md[] = '| `' . $f . '` | ' . ($m['keyframes'] ? $cell(implode(', ', $m['keyframes'])) : '–') . ' | ' . $yn($m['animates']) . ' | ' . $yn($m['reduced']) . $warn . ' | ' . $yn($m['forced']) . ' | ' . $yn($m['focus']) . ' |';
        }
        if ($k['js']) {
            $md[] = '';
            $md[] = '| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |';
            $md[] = '|---|---|---|---|---|---|';
            foreach ($k['js'] as $f => $j) {
                $md[] = '| `' . $f . '` | ' . $yn($j['reduced']) . ($j['raf'] && !$j['reduced'] ? ' ⚠' : '') . ' | ' . $yn($j['observer']) . ' | ' . $yn($j['raf']) . ' | ' . $yn($j['module']) . ' | ' . number_format($j['bytes'] / 1024, 1, ',', '') . ' KB (Quelle) |';
            }
        }
        $md[] = '';
        $md[] = '⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).';
        $md[] = '';
        $md[] = $H($h + 1, 'Ereignisse');
        $md[] = '';
        if (!$k['events']) $md[] = '_Keine Namensraum-Ereignisse._';
        else {
            $md[] = '| Ereignis | Payload | gesendet in | empfangen in |';
            $md[] = '|---|---|---|---|';
            foreach ($k['events'] as $n => $e) $md[] = '| `' . $n . '` | ' . $esc($e['detail'] ?? null) . ' | ' . $cell(implode(', ', array_unique($e['dispatch'] ?? []))) . ' | ' . $cell(implode(', ', array_unique($e['listen'] ?? []))) . ' |';
        }
        if ($k['globals']) {
            $md[] = '';
            $md[] = 'Globale Objekte: ' . implode(', ', array_map(fn($g, $f) => '`' . $g . '` (' . implode(', ', $f) . ')', array_keys($k['globals']), $k['globals']));
        }
        $md[] = '';
        $md[] = $H($h + 1, 'data-*-Attribute');
        $md[] = '';
        $md[] = '| Attribut | Fundstellen |';
        $md[] = '|---|---|';
        foreach ($k['data'] as $a => $files) $md[] = '| `' . $a . '` | ' . $cell(implode(', ', array_slice($files, 0, 5)) . (count($files) > 5 ? ' …' : '')) . ' |';
        return $md;
    }

    /**
     * Kit-Dokument kits/{name}/docs/css-js.md anlegen bzw. den generierten Bereich zwischen den Markern ersetzen.
     * Handgeschriebene Abschnitte außerhalb der Marker bleiben unverändert. Gibt 'created' | 'updated' | 'unchanged' zurück.
     */
    public static function updateKitDoc(string $dir, array $kit): string
    {
        $file = rtrim($dir, '/') . '/docs/css-js.md';
        $start = str_replace('{kit}', $kit['name'], self::MARK_START);
        $block = $start . "\n\n" . implode("\n", self::kitMarkdown($kit)) . "\n\n" . self::MARK_END;
        if (!is_file($file)) {
            if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
            file_put_contents($file, self::kitTemplate($kit) . "\n" . $block . "\n");
            return 'created';
        }
        $doc = (string) file_get_contents($file);
        $a = strpos($doc, '<!-- docs:assets:start');
        $b = strpos($doc, self::MARK_END);
        $new = $a !== false && $b !== false && $b > $a
            ? substr($doc, 0, $a) . $block . substr($doc, $b + strlen(self::MARK_END))
            : rtrim($doc) . "\n\n" . $block . "\n";
        if ($new === $doc) return 'unchanged';
        file_put_contents($file, $new);
        return 'updated';
    }

    /** Vorlage der einheitlichen Kit-Seite „CSS & JS“ (Abschnitte 1–7 von Hand, vorbelegt mit dem, was sich ablesen lässt; Anhang generiert) */
    public static function kitTemplate(array $k): string
    {
        // Präfix der Kit-Tokens: häufigster Präfix der Variablen an :root
        $count = [];
        foreach ($k['css'] as $c) {
            foreach ($c['props'] as $n => $p) {
                if (str_starts_with($p['where'], ':root') && preg_match('~^(--[a-z]+-)~', $n, $m)) $count[$m[1]] = ($count[$m[1]] ?? 0) + 1;
            }
        }
        arsort($count);
        $prefix = (string) array_key_first($count);
        $core = array_values(array_filter(array_keys(array_merge(...array_map(fn($c) => $c['props'], array_values($k['css']) ?: [[]]))),
            fn($n) => (bool) preg_match('~^--(dl|df|dff|gl|glx|cal|lay|row|cms-map|cms-chat|cms|job|dial|partners|nf|se|ha|rt)-~', $n)));
        sort($core);
        $site = $k['css']['css/site.css']['motion'] ?? null;
        $anim = array_keys(array_filter($k['css'], fn($c) => $c['motion']['keyframes'] || $c['motion']['scroll']));
        $base = $k['base'] ? implode(', ', array_map(fn($f) => '`' . $f . '`', array_keys($k['base']))) : '–';
        $lines = [
            '# CSS & JS – Kit „' . $k['label'] . '“ (' . $k['name'] . ')',
            '',
            'Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,',
            'der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=' . $k['name'] . ' --update`.',
            'Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.',
            '',
            '## 1. Überblick',
            '',
            '- Von den Vorlagen eingebunden (Layout, Wartung, offline): ' . $base . '.',
            '- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, ' . count($k['conditional']) . ' Einträge).',
            '- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/' . $k['name'] . '/`).',
            '',
            '## 2. Tokens & Farben',
            '',
            '- Kit-Variablen: ' . ($prefix !== '' ? '`' . $prefix . '*` an `:root`' : 'keine an `:root`') . ($k['design'] ? '; Design-Tokens des Style-Editors: ' . count($k['design']) . ' (Tabelle im Anhang).' : '.'),
            '- Gesetzte Kern-Variablen: ' . ($core ? implode(', ', array_map(fn($n) => '`' . $n . '`', array_slice($core, 0, 24))) . (count($core) > 24 ? ' …' : '') : '–') . '.',
            '',
            '## 3. Blöcke → Dateien',
            '',
            '- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.',
            '',
            '## 4. Animationen & Regeln',
            '',
            '- Dateien mit Keyframes/Scroll-Animation: ' . ($anim ? implode(', ', array_map(fn($f) => '`' . $f . '`', $anim)) : 'keine') . '.',
            '- `prefers-reduced-motion` in `css/site.css`: ' . ($site && $site['reduced'] ? 'ja' : 'nein') . ' · `forced-colors`: ' . ($site && $site['forced'] ? 'ja' : 'nein') . ' · `:focus-visible`: ' . ($site && $site['focus'] ? 'ja' : 'nein') . '.',
            '- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.',
            '',
            '## 5. Overlays, Sheets, Dialoge',
            '',
            '- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).',
            '',
            '## 6. JavaScript & Ereignisse',
            '',
            '- Skripte: ' . ($k['js'] ? implode(', ', array_map(fn($f) => '`' . $f . '`', array_keys($k['js']))) : 'keine') . '.',
            '- Ereignisse: ' . ($k['events'] ? implode(', ', array_map(fn($n) => '`' . $n . '`', array_keys($k['events']))) : 'keine eigenen') . '.',
            '',
            '## 7. Sonderfälle',
            '',
            '- _keine bekannt_',
            '',
        ];
        return implode("\n", $lines);
    }

    // ================================================================== Selbsttest

    /** Parser gegen Beispiele (Fixtures) und gegen den Kern selbst (bekannte Variablen, Ereignisse, Attribute, conditional_css) */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };

        // --- CSS-Fixture: Kommentare, Zeichenketten, url(data:…;…), verschachtelte var(), @media, Verschachtelung, private --_x
        $css = <<<'CSS'
        /* --fake: 1px; im Kommentar */
        :root{--k-ink:#123;--k-bg: url("data:image/svg+xml;utf8,<svg>;</svg>");--_private:1}
        @media (prefers-color-scheme:dark){:root{--k-ink:#eee}}
        .gl-pop{max-width:var(--gl-width,min(22rem,calc(100vw - 24px)));color:var(--gl-ink, var(--k-ink, #000));border:var(--gl-line)}
        .card{--card-pad: 1rem !important; .card__t{--card-t:2px}}
        @keyframes k-spin{to{transform:rotate(1turn)}}
        .spin{animation:k-spin 1s}
        @media (prefers-reduced-motion:reduce){.spin{animation:none}}
        CSS;
        $p = self::cssProperties($css);
        $names = array_column($p['defs'], 'name');
        $eq('CSS: Kommentar wird ignoriert', in_array('--fake', $names, true), false);
        $eq('CSS: --k-ink zuerst an :root', [$p['defs'][0]['name'], $p['defs'][0]['value'], $p['defs'][0]['selector']], ['--k-ink', '#123', ':root']);
        $eq('CSS: Semikolon in url(…) bricht nicht', $p['defs'][1]['value'] ?? null, 'url("data:image/svg+xml;utf8,<svg>;</svg>")');
        $dark = array_values(array_filter($p['defs'], fn($d) => $d['name'] === '--k-ink' && $d['at'] !== ''));
        $eq('CSS: @media-Kontext', $dark[0]['at'] ?? null, '@media (prefers-color-scheme:dark)');
        $eq('CSS: !important entfernt', array_values(array_filter($p['defs'], fn($d) => $d['name'] === '--card-pad'))[0]['value'] ?? null, '1rem');
        $eq('CSS: verschachtelter Selektor', array_values(array_filter($p['defs'], fn($d) => $d['name'] === '--card-t'))[0]['selector'] ?? null, '.card__t');
        $eq('CSS: Rückfall mit Klammern', $p['uses']['--gl-width'] ?? null, 'min(22rem,calc(100vw - 24px))');
        $eq('CSS: verschachtelter Rückfall', $p['uses']['--gl-ink'] ?? null, 'var(--k-ink, #000)');
        $eq('CSS: innerer Rückfall', $p['uses']['--k-ink'] ?? null, '#000');
        $eq('CSS: Rückfall-Kette erkannt', $p['nested'], ['--k-ink']);
        $eq('CSS: Kette nicht als eigene Zeile', [isset(self::props($css)['props']['--k-ink']), self::props('a{b:var(--x,var(--y,1px))}')['chain']], [true, ['--y']]);
        $eq('CSS: ohne Rückfall', array_key_exists('--gl-line', $p['uses']) && $p['uses']['--gl-line'] === null, true);
        $eq('CSS: private Variable bleibt intern', isset(self::props($css)['props']['--_private']), false);
        $m = self::motion($css);
        $eq('Bewegung: Keyframes, reduced-motion', [$m['keyframes'], $m['animates'], $m['reduced'], $m['forced']], [['k-spin'], true, true, false]);

        // --- JS-Fixture
        $js = <<<'JS'
        d.dispatchEvent(new CustomEvent('klxm:inview', { detail: { el, ratio: 1 }, bubbles: true }));
        CMSAdmin.events?.emit('cms:saved', ev);
        emit('cms:tool-open', { id });
        document.addEventListener('klxm:content', e => init(e.detail.root));
        el.dispatchEvent(new Event('change'));
        /* new CustomEvent('alt:kommentar') */
        form.dispatchEvent(new CustomEvent('dff:sent', { bubbles: true, detail: res }));
        const ev = new CustomEvent('cms:before-save', { detail, cancelable: true });
        // Kommentar 'nur:text' ohne Aufruf
        JS;
        $ev = self::jsEvents($js);
        $eq('JS: Ereignisse', array_map(fn($e) => $e['name'] . '/' . $e['kind'], $ev), ['klxm:inview/dispatch', 'cms:saved/dispatch', 'cms:tool-open/dispatch', 'klxm:content/listen', 'dff:sent/dispatch', 'cms:before-save/dispatch']);
        $eq('JS: detail nicht an erster Stelle, Kurzform', [$ev[4]['detail'], $ev[5]['detail']], ['res', 'detail']);
        $eq('JS: Payload aus detail', $ev[0]['detail'], '{ el, ratio: 1 }');
        $eq('JS: Payload aus emit', [$ev[1]['detail'], $ev[2]['detail']], ['ev', '{ id }']);
        $eq('JS: Zeilennummer', $ev[3]['line'], 4);

        // --- data-*-Attribute
        $tpl = '<div data-edit="title" data-edit-mode="rich" class="x" data-inview><a data-l-next>›</a></div><?= $attr(["data-cms-map" => 1]) ?>';
        $eq('Vorlage: data-*', self::dataAttributes($tpl), ['data-cms-map', 'data-edit', 'data-edit-mode', 'data-inview', 'data-l-next']);
        $eq('Skript: dataset + Selektor', self::dataAttributes("el.dataset.sheetHead; q('[data-loop-box]')", true), ['data-loop-box', 'data-sheet-head']);

        // --- Kern: bekannte Namen müssen gefunden werden (fängt Umbenennungen und Parser-Fehler)
        $core = self::core(true);
        foreach (['--cms-map-accent' => 'map.css', '--gl-line' => 'glossary.css', '--lay-stack' => 'layout.css', '--cms-chat-lift' => 'visitor-chat-start.css'] as $var => $file) {
            $eq("Kern: $var in $file", isset($core['css'][$file]['props'][$var]), true);
        }
        $eq('Kern: dl-Variablen in data.css', count(array_filter(array_keys($core['css']['data.css']['props'] ?? []), fn($n) => str_starts_with($n, '--dl-'))) > 0, true);
        foreach (['cms:editor-ready', 'cms:block-select', 'cms:before-save', 'cms:saved', 'cms:published', 'cms:status-changed', 'cms:mode-change', 'cms:tool-open', 'cms:tool-close', 'cms:consent'] as $e) {
            $eq("Kern: Ereignis $e gesendet", !empty($core['events'][$e]['dispatch']), true);
        }
        foreach (['CMSAdmin', 'CMSEditor', 'CMSMedia'] as $g) $eq("Kern: window.$g", isset($core['globals'][$g]), true);
        foreach (['data-edit', 'data-central', 'data-cms-map'] as $a) $eq("Kern: $a in Vorlagen", isset($core['data'][$a]), true);
        $eq('Kern: map.css hat einen Lader', isset($core['loaders']['css/map.css']), true);

        // --- conditional_css des Kerns gegen Theme::conditionalCss (aktive Website; Kit-Datei gleichen Namens zählt)
        try {
            $theme = app()->theme;
            foreach (self::CORE_CONDITIONAL as $file => $types) {
                if (in_array($file, ['dials.css', 'partners.css'], true) && empty($theme->block($types[0])['core'])) continue;
                if ($file === 'glossary-list.css' || ($file === 'layout.css' && $theme->block('layout') === null)) continue;
                foreach ($types as $t) {
                    $hit = (bool) array_filter($theme->conditionalCss([$t]), fn($u) => str_contains($u, '/css/' . $file . '?'));
                    $eq("conditional: $t → $file", $hit, true);
                }
            }
        } catch (\Throwable $e) {
            $fails[] = 'conditional: ' . $e->getMessage();
        }

        // --- Kit aus dem Ordner (erstes mitgeliefertes Kit) und Markdown mit Markern
        $dir = Kit::dir('basis');
        if (is_dir($dir)) {
            $k = self::kit($dir);
            $eq('Kit basis: conditional_css gelesen', isset($k['conditional']['css/blocks.css']), true);
            $eq('Kit basis: Block hat Dateien', array_key_exists('css/blocks.css', $k['blocks']['faq']['files'] ?? []), true);
            $eq('Kit basis: site.css immer geladen', isset($k['base']['css/site.css']), true);
            $tmp = sys_get_temp_dir() . '/assetdocs-' . bin2hex(random_bytes(4));
            mkdir($tmp . '/docs', 0775, true);
            file_put_contents($tmp . '/docs/css-js.md', "# Hand\n\nText\n\n" . self::MARK_START . "\nalt\n" . self::MARK_END . "\n\n## Nachtrag\n");
            $eq('Kit-Dokument: Bereich ersetzt', self::updateKitDoc($tmp, $k), 'updated');
            $doc = (string) file_get_contents($tmp . '/docs/css-js.md');
            $eq('Kit-Dokument: Handtext bleibt', [str_starts_with($doc, "# Hand\n\nText"), str_contains($doc, '## Nachtrag'), str_contains($doc, "\nalt\n")], [true, true, false]);
            $eq('Kit-Dokument: zweiter Lauf ohne Änderung', self::updateKitDoc($tmp, $k), 'unchanged');
            @unlink($tmp . '/docs/css-js.md');
            @rmdir($tmp . '/docs');
            @rmdir($tmp);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
