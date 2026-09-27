<?php
declare(strict_types=1);

namespace Core\Search;

use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Lang;
use Core\Media;
use Core\Pages;

/**
 * Quellen des Suchindex einer Website in einer Sprache:
 *   Seiten      veröffentlicht, nicht „noindex“, Typ page; Titel, Beschreibung, sichtbare Blocktexte (Felder text,
 *               textarea, richtext, inline, repeater; Überschriften gewichtet)
 *   Einträge    Tabellen mit Detailseite (URL-Basis + Vorlage), veröffentlicht, wie auf der Website eingestellt
 *               (geteilte Tabellen: eigene + gewählte fremde, mit Herkunft „_origin“)
 *   Dokumente   PDFs der Mediathek (Titel, Alt-Text, Dateiname) – nur wenn eingeschaltet
 *
 * Dokument: id, type (page|entry|file), table, badge, title, headings (hoch), keywords (Synonyme), text (normal), extra (niedrig),
 *           summary, image (Medien-ID), facets (JSON), url, date, date_label, origin
 * Einträge folgen den Such-Einstellungen der Tabelle (Core\Search\TableSearch).
 */
final class Documents
{
    /** Blöcke, deren Inhalt dynamisch ist oder anderswo indexiert wird */
    private const SKIP_BLOCKS = ['data_list', 'data_fields', 'data_form', 'calendar', 'upcoming', 'map', 'gallery', 'slideshow', 'stack_cards'];
    private const HEADING = '~^(title|titel|heading|headline|ueberschrift|title_strong|title_light|subtitle|untertitel|q|frage|question)$~';
    private const TEXT_TYPES = ['text', 'textarea', 'richtext', 'inline'];

    /** @return array<string, array> id => Dokument */
    public static function build(string $lang): array
    {
        $prev = app()->lang;
        $notes = \Core\EditorNotes::$show;
        \Core\EditorNotes::$show = false;   // Redaktionsnotizen [# … #] nie in den Index (auch beim Neuaufbau aus der Verwaltung)
        app()->lang = $lang === Lang::default() ? null : $lang;
        try {
            $docs = [];
            foreach (self::pages($lang) as $d) $docs[$d['id']] = $d;
            foreach (self::entries($lang) as $d) $docs[$d['id']] = $d;
            if (Search::settings()['media']) {
                foreach (self::files() as $d) $docs[$d['id']] = $d;
            }
            $syn = Search::synonyms($lang);
            foreach ($docs as &$d) {
                $d['keywords'] = $syn ? self::expand($d, $syn) : '';
                $d['hash'] = md5(json_encode($d, JSON_UNESCAPED_UNICODE));
            }
            return $docs;
        } finally {
            app()->lang = $prev;
            \Core\EditorNotes::$show = $notes;
        }
    }

    /** Dokument mit allen Feldern als Text – Redaktionsnotizen [# … #] entfernt (Titel, Überschriften, Text, Auszug …) */
    private static function doc(array $d): array
    {
        return array_map(fn($v) => \Core\EditorNotes::strip((string) $v), $d + ['type' => 'page', 'table' => '', 'badge' => '', 'title' => '', 'headings' => '', 'keywords' => '',
            'text' => '', 'extra' => '', 'summary' => '', 'image' => '', 'facets' => '', 'url' => '', 'date' => '', 'date_label' => '', 'origin' => '']);
    }

    // ------------------------------------------------------------------ Seiten

    private static function pages(string $lang): \Generator
    {
        $rows = app()->db->fetchAll("SELECT * FROM pages WHERE status = 'published' AND type = 'page' AND noindex = 0 AND " . Lang::sql(), [$lang]);
        foreach ($rows as $p) {
            [$head, $text] = self::blocksText(Pages::blocks($p), $p);
            yield self::doc([
                'id' => 'p-' . $p['id'], 'type' => 'page', 'badge' => lt('Seite'),
                'title' => $p['title'], 'headings' => implode(' · ', array_unique($head)),
                'text' => trim(trim((string) $p['meta_description']) . ' ' . implode(' ', $text)),
                'url' => Pages::url($p), 'image' => $p['og_image'] ? (string) (int) $p['og_image'] : '',
            ]);
        }
    }

    /** @return array{0: list<string>, 1: list<string>} [Überschriften, Text] sichtbarer Blöcke */
    public static function blocksText(array $blocks, ?array $page = null): array
    {
        $theme = app()->theme;
        $head = $text = [];
        foreach ($blocks as $b) {
            $type = (string) ($b['type'] ?? '');
            if (in_array($type, self::SKIP_BLOCKS, true) || !(($b['tunes']['section']['visible'] ?? true))) continue;
            $def = $theme->block($type);
            if (!$def || !is_array($b['data'] ?? null)) continue;
            self::fieldsText((array) ($def['fields'] ?? []), $b['data'], $head, $text);
            // Blöcke mit zentralen Angaben (Kontakt, Sprechzeiten …): sichtbaren Text der Ausgabe übernehmen
            if (!empty($def['central']) && $page) {
                $rendered = self::rendered($b, $page);
                if ($rendered !== '') $text[] = $rendered;
            }
        }
        return [$head, $text];
    }

    /** Sichtbarer Text eines gerenderten Blocks (ohne Skripte, Styles, SVG, versteckte Hilfstexte) */
    private static function rendered(array $b, array $page): string
    {
        $app = app();
        $prev = [$app->currentPage, $app->editing];
        $app->currentPage = $page;
        $app->editing = false;
        try {
            $block = $app->theme->makeBlock($b);
            $html = $block ? $app->theme->renderBlock($block) : '';
        } catch (\Throwable $e) {
            error_log('[search] block ' . ($b['type'] ?? '?') . ': ' . $e->getMessage());
            $html = '';
        } finally {
            [$app->currentPage, $app->editing] = $prev;
        }
        $html = (string) preg_replace('~<(script|style|svg|template|noscript)\b.*?</\1>~is', ' ', $html);
        $html = (string) preg_replace('~<[^>]+\b(hidden|aria-hidden="true")[^>]*>.*?</[^>]+>~is', ' ', $html);
        return mb_substr(Text::plain($html), 0, 3000);
    }

    private static function fieldsText(array $fields, array $data, array &$head, array &$text, int $depth = 0): void
    {
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            $v = $data[$name] ?? null;
            if ($name === '' || $v === null || $v === '' || $v === []) continue;
            $type = (string) ($f['type'] ?? 'text');
            if ($type === 'repeater' && is_array($v) && $depth < 3) {
                foreach ($v as $item) {
                    if (is_array($item)) self::fieldsText((array) ($f['fields'] ?? []), $item, $head, $text, $depth + 1);
                }
                continue;
            }
            if ($type === 'file' && is_numeric($v)) {
                // Video/Audio im Block: veröffentlichtes Transkript mitsuchen (Core\MediaTracks)
                if (($tr = self::transcript((int) $v)) !== '') $text[] = $tr;
                continue;
            }
            if (!in_array($type, self::TEXT_TYPES, true) || !is_string($v) || (str_ends_with($name, 'label') && empty($f['search'])) || str_contains($v, '{{')) continue;   // 'search' => true: sichtbare Bezeichnung trotz Name *label indexieren
            if (in_array($type, ['richtext', 'inline'], true)) {
                if (preg_match_all('~<h[1-6][^>]*>(.*?)</h[1-6]>~is', $v, $m)) {
                    foreach ($m[1] as $h) if (($h = Text::plain($h)) !== '') $head[] = $h;
                }
                $v = Text::plain($v);
            } else {
                $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            }
            if ($v === '') continue;
            if (preg_match(self::HEADING, $name) && mb_strlen($v) <= 160) $head[] = $v; else $text[] = $v;
        }
    }

    // ------------------------------------------------------------------ Einträge

    /** Tabellen, deren Einträge gefunden werden: Suche der Tabelle an (TableSearch), mit Detailseite, nicht auf dieser Website ausgenommen */
    public static function tables(): array
    {
        $exclude = Search::settings()['exclude'];
        return array_values(array_filter(Tables::content(), fn($t) => ($t['settings']['route'] ?? '') !== '' && !empty($t['settings']['detail_page_id'])
            && TableSearch::config($t)['enabled'] && !in_array($t['handle'], $exclude, true)));
    }

    private static function entries(string $lang): \Generator
    {
        foreach (self::tables() as $t) {
            $cfg = TableSearch::config($t);
            try {
                $rows = Entries::query($t, ['status' => 'published', 'lang' => $lang, 'source' => 'site', 'limit' => 5000]);
                // Nur künftige Termine: nächstes Vorkommen je Eintrag (Wiederholungen berücksichtigt)
                $next = $cfg['future'] ? self::nextDates($t, $cfg, $rows, $lang) : null;
            } catch (\Throwable $e) {
                error_log('[search] ' . $t['handle'] . ': ' . $e->getMessage());
                continue;
            }
            $shared = Tables::isShared($t);
            $badge = TableSearch::label($t, $cfg);
            foreach ($rows as $e) {
                if ($next !== null && !isset($next[$e['id']])) continue;
                if ($cfg['exclude'] && self::matches($t, $e, $cfg['exclude'])) continue;
                $url = Entries::href($t, $e);
                if (!$url) continue;
                $bucket = ['high' => [], 'normal' => [], 'low' => []];
                foreach ($t['fields'] as $f) {
                    $w = $cfg['fields'][$f['name']] ?? 'off';
                    if ($w === 'off' || $f['name'] === $cfg['title']) continue;
                    $v = self::fieldText($t, $e, $f);
                    if ($v !== '') $bucket[$w][] = $v;
                }
                [$date, $label] = isset($next[$e['id']]) ? $next[$e['id']] : self::entryDate($t, $e, $cfg);
                $facets = [];
                foreach ($cfg['facets'] as $fn) {
                    if ($vals = self::facetValues($t, $e, Tables::field($t, $fn) ?? [])) $facets[$fn] = $vals;
                }
                $title = $cfg['title'] !== '' ? trim(strip_tags((string) ($e[$cfg['title']] ?? ''))) : '';
                yield self::doc([
                    'id' => 'e-' . $t['handle'] . '-' . $e['id'], 'type' => 'entry', 'table' => $t['handle'], 'badge' => $badge,
                    'title' => $title !== '' ? $title : Entries::title($t, $e),
                    'headings' => implode(' · ', $bucket['high']), 'text' => implode(' ', $bucket['normal']), 'extra' => implode(' ', $bucket['low']),
                    'summary' => $cfg['summary'] !== '' ? Text::plain(Entries::html($t, $e, $cfg['summary'])) : '',
                    'image' => $cfg['image'] !== '' && !empty($e[$cfg['image']]) ? (string) (int) $e[$cfg['image']] : '',
                    'facets' => $facets ? json_encode($facets, JSON_UNESCAPED_UNICODE) : '',
                    'url' => $url, 'date' => $date, 'date_label' => $label,
                    'origin' => $shared && Shared::isForeign($t, $e) ? Entries::html($t, $e, '_origin') : '',
                ]);
            }
        }
    }

    /** Klartext eines Feldes für die Suche (Auswahl als Beschriftung, Verknüpfungen als Titel, Gruppen ohne IBAN/E-Mail/Telefon) */
    private static function fieldText(array $t, array $e, array $f): string
    {
        $n = (string) ($f['name'] ?? '');
        if ($n === '') return '';
        if (($f['type'] ?? '') === 'group') {
            $out = [];
            foreach (Entries::groupRows($f, $e[$n] ?? null) as $row) {
                foreach ((array) ($f['fields'] ?? []) as $sf) {
                    if (in_array($sf['type'] ?? 'text', ['iban', 'email', 'tel'], true)) continue;
                    $c = Entries::groupCell($sf, $row[$sf['name']] ?? null);
                    if ($c !== '' && $c !== '✓') $out[] = $c;
                }
            }
            return implode(' ', $out);
        }
        $v = in_array($f['type'], ['relation', 'relations', 'richtext'], true) ? Text::plain(Entries::html($t, $e, $n, ['link' => false])) : Entries::text($t, $e, $n);
        if ($f['type'] === 'file' && is_numeric($e[$n] ?? null)) $v .= ' ' . self::transcript((int) $e[$n]);
        return trim((string) preg_replace('~\s+~u', ' ', $v));
    }

    /** Veröffentlichtes Transkript eines Videos/Audios in der Sprache des Index (ohne Rückfall auf andere Sprachen) */
    private static function transcript(int $mediaId): string
    {
        $m = $mediaId ? Media::find($mediaId) : null;
        if (!$m || !\Core\MediaTracks::enabled() || !\Core\MediaTracks::supports($m)) return '';
        return mb_substr((string) preg_replace('~\s+~u', ' ', \Core\MediaTracks::transcriptText($m, Lang::current(), false)), 0, 20000);
    }

    /** Werte eines Filterfeldes (Auswahl-Beschriftungen bzw. Titel verknüpfter Einträge) */
    private static function facetValues(array $t, array $e, array $f): array
    {
        $raw = $e[$f['name'] ?? ''] ?? null;
        if ($raw === null || $raw === '' || $raw === []) return [];
        if (in_array($f['type'] ?? '', ['relation', 'relations'], true)) {
            $target = Tables::findContent($f['target'] ?? '');
            if (!$target) return [];
            return array_values(array_map(fn($r) => Entries::title($target, $r), Entries::query($target, ['ids' => (array) $raw, 'source' => 'all', 'status' => 'all'])));
        }
        return array_values(array_map(fn($v) => Tables::optionLabel($f, (string) $v), (array) $raw));
    }

    /** Ausschluss-Bedingung: Feld hat diesen Wert (Auswahl: Schlüssel oder Beschriftung; Mehrfach: enthält) */
    private static function matches(array $t, array $e, array $x): bool
    {
        $raw = $e[$x['field']] ?? null;
        $vals = is_array($raw) ? array_map('strval', $raw) : [(string) $raw];
        $f = Tables::field($t, $x['field']);
        if ($f && in_array($f['type'], ['select', 'multiselect'], true)) {
            foreach ($vals as $v) $vals[] = Tables::optionLabel($f, $v);
        }
        if ($f && $f['type'] === 'bool') $vals = [$raw ? '1' : '0', $raw ? 'ja' : 'nein'];
        return in_array(mb_strtolower($x['value']), array_map('mb_strtolower', $vals), true);
    }

    /** Nächstes Datum je Eintrag ab heute: [id => [iso, anzeige]] (Kalender: Vorkommen bis 1 Jahr; sonst Datumsfeld ≥ heute) */
    private static function nextDates(array $t, array $cfg, array $rows, string $lang): array
    {
        $out = [];
        if (Calendar::enabled($t)) {
            foreach (Calendar::upcoming($t, 1000, 366, ['lang' => $lang, 'source' => 'site']) as $o) {
                $id = (int) $o['entry']['id'];
                if (!isset($out[$id])) $out[$id] = [$o['start']->format('Y-m-d H:i'), Calendar::when(array_replace($o, ['recurring' => false]), 'short')];
            }
            return $out;
        }
        $today = date('Y-m-d');
        foreach ($rows as $e) {
            [$d, $l] = self::entryDate($t, $e, $cfg);
            if ($d !== '' && substr($d, 0, 10) >= $today) $out[(int) $e['id']] = [$d, $l];
        }
        return $out;
    }

    /** Datum für die Anzeige: gewähltes Datumsfeld, sonst Termin (Kalender), sonst erstes Datumsfeld */
    private static function entryDate(array $t, array $e, array $cfg = []): array
    {
        if (($cfg['date'] ?? '') === '' && Calendar::enabled($t) && ($span = Calendar::span($t, $e))) {
            return [$span['start']->format('Y-m-d H:i'), Calendar::when($span + ['recurring' => false], 'short')];
        }
        foreach ($t['fields'] as $f) {
            if (($cfg['date'] ?? '') !== '' && $f['name'] !== $cfg['date']) continue;
            if (in_array($f['type'], ['date', 'datetime'], true) && !empty($e[$f['name']]) && ($ts = strtotime((string) $e[$f['name']]))) {
                return [date('Y-m-d H:i', $ts), date_local($ts, 'short') . ($f['type'] === 'datetime' ? ', ' . lt('{time} Uhr', ['time' => date('H:i', $ts)]) : '')];
            }
        }
        return ['', ''];   // Veröffentlichungsdatum allein ist kein Inhaltsdatum (z. B. Schlagwörter)
    }

    // ------------------------------------------------------------------ Dokumente (PDF)

    private static function files(): \Generator
    {
        foreach (Media::all(['kind' => 'pdf']) as $m) {
            $url = Media::viewerUrl($m) ?? Media::url($m);
            yield self::doc([
                'id' => 'm-' . $m['id'], 'type' => 'file', 'badge' => lt('Dokument'),
                'title' => Media::displayName($m), 'text' => trim(Media::alt($m) . ' ' . (string) $m['original_name'] . ' ' . str_replace(',', ' ', (string) ($m['tags'] ?? ''))),
                'url' => $url, 'date' => substr((string) ($m['created_at'] ?? ''), 0, 10),
            ]);
        }
    }

    // ------------------------------------------------------------------ Synonyme

    /** Begriffe der Synonym-Gruppen, die im Dokument vorkommen, um die übrigen Begriffe der Gruppe ergänzen */
    private static function expand(array $d, array $groups): string
    {
        $hay = ' ' . Text::fold($d['title'] . ' ' . $d['headings'] . ' ' . $d['text'] . ' ' . $d['extra']) . ' ';
        $hay = (string) preg_replace('~[^\p{L}\p{N}]+~u', ' ', $hay);
        $add = [];
        foreach ($groups as $g) {
            $hit = false;
            foreach ($g as $term) {
                if (str_contains($hay, ' ' . Text::fold($term) . ' ')) { $hit = true; break; }
            }
            if ($hit) foreach ($g as $term) $add[$term] = true;
        }
        return implode(' ', array_keys($add));
    }

    /** Selbsttest (notes:selftest): Redaktionsnotizen landen nicht im Suchindex */
    public static function notesSelftest(array $res): array
    {
        $fields = [['name' => 'title', 'type' => 'text'], ['name' => 'text', 'type' => 'richtext'],
            ['name' => 'items', 'type' => 'repeater', 'fields' => [['name' => 'q', 'type' => 'text'], ['name' => 'a', 'type' => 'textarea']]]];
        $data = ['title' => 'Seminare [# Platzhalter #]', 'text' => '<h3>Termine [# prüfen #]</h3><p>Ab März.[# bitte ergänzen:\nOrt #]</p>',
            'items' => [['q' => 'Frage', 'a' => "Antwort [# geheim #]"]]];
        $head = $text = [];
        self::fieldsText($fields, $data, $head, $text);
        $d = self::doc(['id' => 'p-1', 'title' => 'Kurs [# Titel prüfen #]', 'headings' => implode(' · ', $head), 'text' => implode(' ', $text), 'summary' => 'x [# y #]']);
        $all = implode(' | ', $d);
        $check = function (string $name, bool $ok) use (&$res) { if ($ok) $res['ok']++; else $res['fail'][] = $name; };
        $check('Suchindex ohne Notizen: ' . $all, !str_contains($all, '[#') && !str_contains($all, 'geheim') && !str_contains($all, 'Platzhalter'));
        $check('Suchindex behält Text', str_contains($all, 'Seminare') && str_contains($all, 'Termine') && str_contains($all, 'Ab März.') && str_contains($all, 'Antwort'));
        $check('Text::plain ohne Notizen', Text::plain('<p>A [# b #]</p><p>C</p>') === 'A C');
        return $res;
    }
}
