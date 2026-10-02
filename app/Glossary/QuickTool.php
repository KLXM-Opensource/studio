<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

use Core\Data\Entries;
use Core\Lang;

/**
 * Quick-Glossar: Werkzeug beim Bearbeiten auf der Website (Core\FrontendTools) – zugleich das Referenzbeispiel für
 * $x->frontendTool([...]) einer Erweiterung. Knopf „Glossar“ in der Werkzeugleiste (⌥G), Seitenleiste in der Shadow-DOM-Ebene,
 * Modul resources/js/quick-glossary.mjs (erst beim ersten Öffnen geladen).
 *
 *  - Suchen: Begriff, Varianten und Kurz-Erklärung; „Einfügen“ setzt einen Link entry:glossar:{id} an die Schreibmarke bzw. um
 *    den markierten Text (wie die Linkauswahl).
 *  - „Neuer Begriff“: vorbelegt mit dem markierten Text; Entwurf – veröffentlicht nur mit Recht data.publish auf die Tabelle.
 *  - „Auf dieser Seite“: Begriffe, die die automatische Markierung (Annotator) auf der Seite kennzeichnen würde, mit Sprung.
 * Endpunkte (GlossaryController::api*): Funktion „glossary“ an, Tabelle eingerichtet, Recht data.edit auf die Tabelle, CSRF bei POST.
 */
final class QuickTool
{
    public const LIMIT = 25;
    public const HTML_MAX = 1572864;   // 1,5 MB Seitentext für „Auf dieser Seite“

    /** Angaben für Core\FrontendTools – genau so, wie eine Erweiterung sie an $x->frontendTool() übergibt */
    public static function definition(): array
    {
        return [
            'id' => 'glossary', 'label' => __('Glossar'), 'icon' => 'book-open-text', 'module' => asset('js/quick-glossary.mjs'),
            'placement' => 'main', 'shortcut' => 'Alt+G', 'hint' => __('Begriffe suchen, verlinken und anlegen'),
            'feature' => Glossary::FEATURE,
            'visible' => fn(array $bar) => ($t = Glossary::table()) !== null && can('data.edit', $t['handle']),
            'panel' => ['title' => __('Quick-Glossar')],
            'endpoints' => ['search' => '/admin/api/glossar/suche', 'create' => '/admin/api/glossar/begriff', 'page' => '/admin/api/glossar/seite'],
            'data' => fn(array $bar) => self::data($bar),
            'texts' => self::texts(),
        ];
    }

    /** Angaben je Werkzeugleiste: Rechte, Sprache der Seite, Adressen */
    private static function data(array $bar): array
    {
        $t = Glossary::table();
        $page = $bar['page'] ?? [];
        $self = isset($bar['table'], $bar['entry']) && ($bar['table']['handle'] ?? '') === ($t['handle'] ?? '') ? (int) $bar['entry']['id'] : 0;
        return [
            'publish' => $t && $t['settings']['workflow'] && can('data.publish', $t['handle']),
            'workflow' => (bool) ($t['settings']['workflow'] ?? true),
            'lang' => Lang::norm($bar['entry']['lang'] ?? ($page['lang'] ?? null)),
            'path' => (string) (app()->request?->path ?? '/'),
            'self' => $self, 'shortMax' => Glossary::SHORT_MAX,
            'admin' => url('/admin/glossar'), 'tableUrl' => $t ? url('/admin/data/' . $t['handle']) : '',
            'mode' => Glossary::settings()['mode'],
        ];
    }

    private static function texts(): array
    {
        return [
            'search' => __('Begriffe durchsuchen'), 'searchPh' => __('Begriff suchen …'), 'tabSearch' => __('Suchen'), 'tabNew' => __('Neuer Begriff'),
            'tabPage' => __('Auf dieser Seite'), 'insert' => __('Einfügen'), 'insertAria' => __('„{term}“ als Link einfügen'),
            'inserted' => __('Link auf „{term}“ eingefügt.'), 'insertedText' => __('„{term}“ als Text eingefügt (dieses Feld kennt keine Links).'),
            'noResults' => __('Keine Begriffe gefunden.'), 'results' => __('{n} Begriffe'), 'result1' => __('1 Begriff'),
            'more' => __('Weitere Treffer – Suche verfeinern.'), 'draft' => __('Entwurf'), 'draftLink' => __('Entwurf – der Link wirkt erst nach dem Veröffentlichen.'),
            'newFrom' => __('„{term}“ als neuen Begriff anlegen'),
            'term' => __('Begriff'), 'short' => __('Kurz-Erklärung'), 'shortHelp' => __('Klartext, höchstens {n} Zeichen – erscheint im Hinweisfenster.'),
            'long' => __('Ausführliche Erklärung (optional)'), 'variants' => __('Varianten (optional)'), 'variantsHelp' => __('Mit Komma getrennt, z. B. Mehrzahl oder Abkürzung.'),
            'saveDraft' => __('Als Entwurf anlegen'), 'savePublish' => __('Anlegen & veröffentlichen'), 'saveOnly' => __('Anlegen'),
            'createdDraft' => __('„{term}“ als Entwurf angelegt.'), 'createdPub' => __('„{term}“ angelegt und veröffentlicht.'),
            'insertNew' => __('Jetzt im Text verlinken'), 'exists' => __('„{term}“ gibt es schon.'), 'showExisting' => __('Vorhandenen Begriff zeigen'),
            'draftOnly' => __('Neue Begriffe werden als Entwurf angelegt – veröffentlichen kann, wer das Recht dazu hat.'),
            'pageIntro' => __('Diese Begriffe würde die automatische Markierung auf dieser Seite kennzeichnen (jeweils das erste Vorkommen).'),
            'pageNone' => __('Auf dieser Seite würde kein Begriff markiert.'), 'pageOff' => __('Die automatische Markierung ist ausgeschaltet – so sähe sie aus.'),
            'pageExcluded' => __('Diese Seite ist von der Markierung ausgenommen – so sähe sie sonst aus.'), 'pageCheck' => __('Neu prüfen'),
            'jump' => __('Zur Stelle'), 'jumpAria' => __('Zur Stelle mit „{term}“ springen'), 'notFound' => __('Stelle nicht gefunden – vielleicht in einem Bereich, der gerade nicht sichtbar ist.'),
            'manage' => __('Glossar verwalten'), 'noText' => __('Bitte zuerst in einen Text klicken – dort wird eingefügt.'),
            'selected' => __('Markiert: „{text}“ – Einfügen verlinkt diesen Text.'), 'cursor' => __('Einfügen an der Schreibmarke in „{field}“.'), 'cursorAny' => __('Einfügen an der Schreibmarke im Text.'),
            'requiredTerm' => __('Bitte einen Begriff eingeben.'), 'requiredShort' => __('Bitte eine Kurz-Erklärung eingeben.'),
            'tooLong' => __('Höchstens {n} Zeichen.'), 'chars' => __('{n} von {max} Zeichen'),
        ];
    }

    // ================================================================= Endpunkte (GlossaryController)

    /** Sprache aus der Anfrage (gültig, sonst Standardsprache) */
    public static function lang(string $l): string
    {
        return $l !== '' && Lang::valid($l) ? $l : Lang::default();
    }

    /**
     * Suche: Begriffe (auch Entwürfe) einer Sprache; Treffer am Anfang des Begriffs zuerst, dann im Begriff, in Varianten,
     * in der Kurz-Erklärung. Ohne Suchtext: alphabetisch. @return array{items: list<array>, total: int}
     */
    public static function search(string $q, string $lang, int $limit = self::LIMIT): array
    {
        $t = Glossary::table();
        if (!$t) return ['items' => [], 'total' => 0];
        $q = mb_strtolower(trim($q));
        $scored = [];
        foreach (Glossary::terms(true, $lang) as $i => $x) {
            if ($q === '') { $scored[] = [0, $i, $x]; continue; }
            $term = mb_strtolower($x['term']);
            $s = match (true) {
                $term === $q => 100, str_starts_with($term, $q) => 80, str_contains($term, $q) => 60, default => 0,
            };
            if (!$s) foreach ($x['variants'] as $v) { $v = mb_strtolower($v); if (str_starts_with($v, $q)) { $s = 50; break; } if (str_contains($v, $q)) $s = max($s, 40); }
            if (!$s && str_contains(mb_strtolower($x['short']), $q)) $s = 20;
            if ($s) $scored[] = [-$s, $i, $x];
        }
        usort($scored, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $limit = max(1, min(100, $limit));
        return ['items' => array_map(fn($r) => self::item($t, $r[2]), array_slice($scored, 0, $limit)), 'total' => count($scored)];
    }

    /** Begriff für das Werkzeug: Verweis entry:glossar:{id} (wie die Linkauswahl) + Adresse der Detailseite */
    public static function item(array $t, array $x): array
    {
        return ['id' => (int) $x['id'], 'term' => $x['term'], 'short' => $x['short'], 'variants' => array_values(array_diff($x['variants'], [$x['term']])),
            'category' => $x['category'], 'draft' => (bool) $x['draft'], 'href' => (string) ($x['url'] ?? ''),
            'ref' => 'entry:' . $t['handle'] . ':' . (int) $x['id'], 'edit' => url('/admin/data/' . $t['handle'] . '/' . (int) $x['id'])];
    }

    /**
     * Neuen Begriff anlegen. $in: term, short, long (Klartext, Absätze durch Leerzeile), variants (Komma), publish (bool), lang.
     * Veröffentlicht nur mit Freigabe-Ablauf + Recht data.publish – sonst Entwurf. @return array{ok: bool, item?: array, error?: string, field?: string, exists?: array}
     */
    public static function create(array $in): array
    {
        $t = Glossary::table();
        if (!$t) return ['ok' => false, 'error' => __('Das Glossar ist noch nicht eingerichtet.')];
        $term = trim((string) preg_replace('~\s+~u', ' ', strip_tags((string) ($in['term'] ?? ''))));
        $shortRaw = trim((string) preg_replace('~\s+~u', ' ', strip_tags((string) ($in['short'] ?? ''))));
        $lang = self::lang((string) ($in['lang'] ?? ''));
        if ($term === '' || mb_strlen($term) > 120) return ['ok' => false, 'field' => 'term', 'error' => $term === '' ? __('Bitte einen Begriff eingeben.') : __('Höchstens {n} Zeichen.', ['n' => 120])];
        if ($shortRaw === '') return ['ok' => false, 'field' => 'short', 'error' => __('Bitte eine Kurz-Erklärung eingeben.')];
        if (mb_strlen($shortRaw) > Glossary::SHORT_MAX) return ['ok' => false, 'field' => 'short', 'error' => __('Höchstens {n} Zeichen.', ['n' => Glossary::SHORT_MAX])];
        // Gibt es den Begriff (oder eine gleichlautende Variante) in dieser Sprache schon? → kein Doppel, Vorhandenen zeigen
        foreach (Glossary::terms(true, $lang) as $x) {
            if (mb_strtolower($x['term']) === mb_strtolower($term) || in_array(mb_strtolower($term), array_map('mb_strtolower', $x['variants']), true)) {
                return ['ok' => false, 'field' => 'term', 'error' => __('„{term}“ gibt es schon.', ['term' => $x['term']]), 'exists' => self::item($t, $x)];
            }
        }
        $publish = !empty($in['publish']) && $t['settings']['workflow'] && can('data.publish', $t['handle']);
        $long = trim((string) ($in['long'] ?? ''));
        $html = '';
        foreach (preg_split('~\R{2,}~u', mb_substr($long, 0, 20000)) ?: [] as $para) {
            $para = trim($para);
            if ($para !== '') $html .= '<p>' . nl2br(e($para), false) . '</p>';
        }
        $variants = Glossary::splitVariants((string) ($in['variants'] ?? ''));
        $row = ['begriff' => $term, 'kurz' => $shortRaw, 'varianten' => implode("\n", $variants), 'status' => $publish ? 'published' : 'draft'];
        $names = array_column($t['fields'], 'name');
        if ($html !== '' && in_array('erklaerung', $names, true)) $row['erklaerung'] = $html;
        if (Lang::multi()) $row['lang'] = $lang;
        [$id, $errors] = Entries::save($t, null, $row);
        if ($errors) return ['ok' => false, 'error' => implode(' ', array_map('strval', $errors))];
        Glossary::flush();
        $e = Entries::find($t, (int) $id);
        return ['ok' => true, 'item' => self::item($t, Glossary::term($t, $e)), 'published' => ($e['status'] ?? '') === 'published'];
    }

    /**
     * Treffer der Markierung in einem HTML-Ausschnitt: [Begriffs-ID => Text wie im Inhalt]. Bearbeitbare Bereiche (contenteditable
     * des Editors) zählen als Text; Regeln, Überschriften und Modus wie auf der Website (Annotator). Ohne Datenbank (Selbsttest).
     */
    public static function hits(array $terms, string $html, array $settings, string $lang = 'de', int $self = 0): array
    {
        $a = new Annotator($terms, ['mode' => ($settings['mode'] ?? 'page') === 'section' ? 'section' : 'page', 'headings' => (int) ($settings['headings'] ?? 3),
            'exclude' => $self ? [$self] : [], 'labels' => Glossary::labels(), 'lang' => $lang, 'main' => false]);
        $html = (string) preg_replace('~\scontenteditable(\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?~i', '', mb_substr($html, 0, self::HTML_MAX));
        $a->annotate('<body>' . $html . '</body>');
        return $a->hits;
    }

    /**
     * „Auf dieser Seite“: Begriffe, die die automatische Markierung im übergebenen HTML (Inhalt der Seite im Editor, ohne
     * contenteditable) kennzeichnen würde – mit Einstellungen der Website (Modus, Überschriften). Ausgenommene Seiten und
     * „aus“ melden das, rechnen aber trotzdem (Vorschau). @return array{items: list<array>, mode: string, excluded: bool, count: int}
     */
    public static function onPage(string $html, string $lang, string $path = '/', int $self = 0): array
    {
        $t = Glossary::table();
        if (!$t) return ['items' => [], 'mode' => 'off', 'excluded' => false, 'count' => 0];
        $s = Glossary::settings();
        $terms = Glossary::terms(true, $lang);
        $byId = [];
        foreach ($terms as $x) $byId[(int) $x['id']] = $x;
        $items = [];
        foreach (self::hits($terms, $html, $s, $lang, $self) as $id => $match) {
            if (isset($byId[$id])) $items[] = self::item($t, $byId[$id]) + ['match' => (string) $match];
        }
        return ['items' => $items, 'mode' => (string) $s['mode'], 'excluded' => Glossary::excluded($path, (string) $s['exclude']), 'count' => count($items)];
    }

    /**
     * Selbsttest der Endpunkte mit Datenbank (glossary:selftest): nur wenn das Glossar eingerichtet ist; alles in einer Transaktion,
     * die zurückgerollt wird. Anlegen (Entwurf ohne data.publish), Doppel/Variante, Pflichtfelder, Länge, Suche, Verweis.
     * @param callable(string, mixed, mixed): void $eq
     */
    public static function selftestDb(callable $eq): bool
    {
        $t = Glossary::table();
        if (!$t) return false;
        $pdo = \Core\Data\Tables::db($t)->pdo;
        $pdo->beginTransaction();
        try {
            $lang = Lang::default();
            $r = self::create(['term' => 'QA-Selbsttestbegriff', 'short' => 'Nur für den Selbsttest.', 'variants' => 'QA-Selbsttestwort', 'long' => "Absatz 1\n\nAbsatz <b>2</b>", 'publish' => true, 'lang' => $lang]);
            $eq('Anlegen: ok', $r['ok'], true);
            $eq('Anlegen: ohne data.publish als Entwurf', [$r['published'] ?? null, $r['item']['draft'] ?? null], [can('data.publish', $t['handle']), !can('data.publish', $t['handle'])]);
            $eq('Anlegen: Verweis entry:glossar:{id}', $r['item']['ref'] ?? '', 'entry:' . $t['handle'] . ':' . ($r['item']['id'] ?? 0));
            $e = \Core\Data\Entries::find($t, (int) ($r['item']['id'] ?? 0));
            $eq('Anlegen: ausführlich als Absätze, maskiert', str_contains((string) ($e['erklaerung'] ?? ''), '<p>Absatz 1</p>') && !str_contains((string) ($e['erklaerung'] ?? ''), '<b>'), true);
            $d = self::create(['term' => 'qa-selbsttestbegriff', 'short' => 'x', 'lang' => $lang]);
            $eq('Doppelt (Groß/Klein): abgelehnt mit Vorhandenem', [$d['ok'], $d['field'] ?? null, $d['exists']['id'] ?? null], [false, 'term', $r['item']['id'] ?? null]);
            $v = self::create(['term' => 'QA-Selbsttestwort', 'short' => 'x', 'lang' => $lang]);
            $eq('Doppelt als Variante: abgelehnt', $v['ok'], false);
            $eq('Ohne Begriff', self::create(['term' => ' ', 'short' => 'x'])['field'] ?? null, 'term');
            $eq('Ohne Kurz-Erklärung', self::create(['term' => 'QA neu', 'short' => ''])['field'] ?? null, 'short');
            $eq('Kurz-Erklärung zu lang', self::create(['term' => 'QA neu', 'short' => str_repeat('x', Glossary::SHORT_MAX + 1)])['field'] ?? null, 'short');
            $eq('HTML im Begriff entfernt', self::create(['term' => '<script>x</script>', 'short' => 'y'])['item']['term'] ?? null, 'x');
            $s = self::search('QA-Selbsttest', $lang);
            $eq('Suche: Treffer am Anfang zuerst', $s['items'][0]['term'] ?? null, 'QA-Selbsttestbegriff');
            $eq('Suche: über Variante', array_column(self::search('selbsttestwort', $lang)['items'], 'term'), ['QA-Selbsttestbegriff']);
            $eq('Suche: Grenze', count(self::search('', $lang, 1)['items']) <= 1, true);
            $p = self::onPage('<p contenteditable="true">Hier steht der QA-Selbsttestbegriff im Text.</p>', $lang, '/x');
            $eq('Auf dieser Seite: Entwurf gefunden, Text wie im Inhalt', [array_column($p['items'], 'term'), $p['items'][0]['match'] ?? null], [['QA-Selbsttestbegriff'], 'QA-Selbsttestbegriff']);
            $eq('Auf dieser Seite: eigene Detailseite ausgenommen', self::onPage('<p>QA-Selbsttestbegriff</p>', $lang, '/x', (int) ($r['item']['id'] ?? 0))['items'], []);
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Glossary::flush();
        }
        return true;
    }
}
