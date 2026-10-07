<?php
/*
 * Theme-Helfer „praxis“. Werden von Templates und Block-Renderern genutzt.
 */

use Core\Forms;
use Core\Pages;

const PRAXIS_DAYS = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag'];

/**
 * Fehlende Angabe als Redaktionsnotiz „[# … #]“ (Core\EditorNotes): Besucher sehen nichts, die Redaktion sieht im
 * Bearbeiten-Modus „Notiz: …“. $label darf in eckigen Klammern stehen (bisherige Platzhalter, übersetzt über lt()).
 */
function praxis_note(string $label): string
{
    return '[# ' . trim($label, "[] \t\n") . ' #]';
}

/** Ist eine Telefonnummer hinterlegt? (Notizen „[# … #]“ zählen nicht) */
function praxis_has_phone(): bool
{
    return filled((string) setting('telefon')) || filled(\Core\EditorNotes::strip((string) setting('telefon_anzeige')));
}

/** Telefonnummer zur Anzeige – ohne Nummer eine Redaktionsnotiz (für Besucher leer) */
function praxis_phone(): string
{
    if (!praxis_has_phone()) return praxis_note(lt('[Telefonnummer]'));
    // In weiteren Sprachen automatisch international (+49 …), siehe phone_display()
    return phone_display((string) (\Core\EditorNotes::strip((string) setting('telefon_anzeige')) ?: setting('telefon')));
}

/** Satz nach dem Absenden eines Formulars: Bearbeitungsfrist aus den Praxisdaten – fehlt sie, nur der Rückfrage-Hinweis (+ Notiz) */
function praxis_done_text(): string
{
    $frist = trim(\Core\EditorNotes::strip((string) setting('bearbeitungsfrist_text')));
    return $frist !== '' ? lt('Bearbeitung: {time}. Bei Rückfragen melden wir uns telefonisch.', ['time' => $frist])
        : lt('Bei Rückfragen melden wir uns telefonisch.') . ' ' . praxis_note(lt('[Frist – noch zu bestätigen]'));
}

/** Telefon als Link (HTML) – ohne Nummer nur die Redaktionsnotiz, kein leerer Link */
function praxis_phone_link(string $class = ''): string
{
    if (!praxis_has_phone()) return e(praxis_phone());
    return '<a' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ' href="' . e(praxis_phone_href()) . '">' . e(praxis_phone()) . '</a>';
}

/** tel:-Link oder Fallback-Anker */
function praxis_phone_href(): string
{
    return tel_href((string) setting('telefon')) ?? tel_href((string) setting('telefon_anzeige')) ?? '#kontakt';
}

/**
 * Übersetzten Text (lt()) escapen und Platzhalter {name} durch fertiges HTML ersetzen.
 * Beispiel: praxis_fill(lt(…'… {phone}'), ['phone' => '<a …>…</a>']) – $html muss bereits escaped sein.
 */
function praxis_fill(string $text, array $html): string
{
    $map = [];
    foreach ($html as $k => $v) {
        $map['{' . $k . '}'] = (string) $v;
    }
    return strtr(e($text), $map);
}

/** Link mit Sonderwerten: "doctolib", "telefon", "rezept", "ueberweisung" */
function praxis_link(?string $link): string
{
    return match (trim((string) $link)) {
        'doctolib' => filled(setting('doctolib_url')) ? (string) setting('doctolib_url') : link_href('#kontakt'),
        'telefon' => praxis_phone_href(),
        'rezept' => url('/anfrage/rezept'),
        'ueberweisung' => url('/anfrage/ueberweisung'),
        default => link_href($link),
    };
}

function praxis_link_attrs(?string $link): string
{
    $href = praxis_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Zeitspanne eines Sprechzeiten-Eintrags */
/** Uhrzeit „07:30“ → „7:30“ */
function praxis_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Name des Wochentags (0 = Sonntag … 6 = Samstag) in der Sprache der Seite */
function praxis_day_name(int $dow): string
{
    return date_local(mktime(12, 0, 0, 1, 7 + $dow, 2024), 'weekday');   // 07.01.2024 = Sonntag
}

/**
 * Zeitfenster eines Sprechzeiten-Eintrags, z. B. ['7:30–11:00', '15:30–17:00'].
 * Pause teilt den Tag in Vor- und Nachmittag.
 */
function praxis_time_segments(array $r): array
{
    if (empty($r['von']) || empty($r['bis'])) {
        return [];
    }
    if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
        return [praxis_clock($r['von']) . '–' . praxis_clock($r['pause_von']), praxis_clock($r['pause_bis']) . '–' . praxis_clock($r['bis'])];
    }
    return [praxis_clock($r['von']) . '–' . praxis_clock($r['bis'])];
}

/** Einzeilige Darstellung: „7:30–11:00 · 15:30–17:00 Uhr · Notiz“ */
function praxis_time_range(array $r): string
{
    $seg = praxis_time_segments($r);
    $t = $seg ? lt('{time} Uhr', ['time' => implode(' · ', $seg)]) : '';
    $note = trim((string) ($r['notiz'] ?? ''));
    return implode(' · ', array_filter([$t, $note]));
}

/**
 * Sprechzeiten je Tag (Mo–So Reihenfolge), mehrere Einträge pro Tag möglich.
 * @return array<int, array{day: string, dow: int, time: string, segments: array, note: string}>
 */
function praxis_hours(): array
{
    $byDay = [];
    foreach ((array) setting('oeffnungszeiten', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if (!isset(PRAXIS_DAYS[$dow])) continue;
        $byDay[$dow][] = $r;
    }
    $out = [];
    foreach (PRAXIS_DAYS as $dow => $name) {
        if (!isset($byDay[$dow])) continue;
        $rows = $byDay[$dow];
        $note = implode(' · ', array_filter(array_map(fn($r) => trim((string) ($r['notiz'] ?? '')), $rows)));
        $am = $pm = $amSeg = $pmSeg = [];
        foreach (praxis_raw_segments($rows) as [$from, $to]) {
            $label = praxis_clock($from) . '–' . praxis_clock($to);
            if ($from < '12:00') { $am[] = $label; $amSeg[] = [$from, $to]; } else { $pm[] = $label; $pmSeg[] = [$from, $to]; }
        }
        // Notiz „nachmittags geschlossen“ wird zur Zelle „geschlossen“; andere Notizen erscheinen darunter
        $closedNote = (bool) preg_match('~geschlossen~iu', $note);
        $out[] = [
            'day' => praxis_day_name($dow), 'dow' => $dow,
            'time' => implode(' · ', array_filter(array_map('praxis_time_range', $rows))),
            'segments' => array_merge(...array_map('praxis_time_segments', $rows)),
            'note' => $note,
            'am' => $am ? implode(' · ', $am) : null,
            'pm' => $pm ? implode(' · ', $pm) : null,
            'am_seg' => $amSeg, 'pm_seg' => $pmSeg,                              // [[von, bis], …] für bündige Darstellung
            'raw' => praxis_raw_segments($rows),
            'extra' => $closedNote && (!$am || !$pm) ? '' : $note,
        ];
    }
    return $out;
}

/** Zeitfenster als [von, bis] (HH:MM), Pause berücksichtigt */
function praxis_raw_segments(array $rows): array
{
    $seg = [];
    foreach ($rows as $r) {
        if (empty($r['von']) || empty($r['bis'])) continue;
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = [$r['von'], $r['pause_von']];
            $seg[] = [$r['pause_bis'], $r['bis']];
        } else {
            $seg[] = [$r['von'], $r['bis']];
        }
    }
    usort($seg, fn($a, $b) => strcmp($a[0], $b[0]));
    return $seg;
}

/** true, wenn Samstag und Sonntag ohne Sprechzeiten sind */
function praxis_weekend_closed(): bool
{
    $days = array_column(praxis_hours(), 'dow');
    return !in_array(6, $days, true) && !in_array(0, $days, true);
}

/** Daten für das Live-Badge „Jetzt geöffnet“ (wird im Browser nach der Ortszeit der Praxis berechnet – Zeitzone der Website) */
function praxis_open_data(): array
{
    $days = [];
    foreach ((array) setting('oeffnungszeiten', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if (!isset(PRAXIS_DAYS[$dow])) continue;
        foreach (praxis_raw_segments([$r]) as $seg) {
            $days[$dow][] = $seg;
        }
    }
    return [
        'tz' => date_default_timezone_get(),
        'days' => (object) $days,
        'closedFrom' => (string) setting('urlaub_von', ''),
        'closedTo' => (string) setting('urlaub_bis', ''),
    ];
}

/** Live-Badge (ohne JavaScript unsichtbar) */
function praxis_open_badge(string $class = ''): string
{
    if (!setting('status_badge_aktiv', true) || is_editing()) {
        return '';
    }
    return '<p class="openb ' . e($class) . '" data-openb="' . json_attr(praxis_open_data()) . '" role="status" hidden>'
        . '<span class="openb__dot" aria-hidden="true"></span><span class="openb__text"></span></p>';
}

/** Übersetzte Texte für assets/js/site.js (als data-l10n am <html>-Element; Platzhalter {name} ersetzt das Script) */
function praxis_js_texts(): array
{
    return [
        'days' => array_map('praxis_day_name', range(0, 6)),
        'date' => lt('{dd}.{mm}.'),
        'open' => lt('Jetzt geöffnet · bis {time} Uhr'),
        'soon' => lt('Schließt bald · um {time} Uhr'),
        'later' => lt('Geschlossen · öffnet um {time} Uhr'),
        'next' => lt('Geschlossen · öffnet {day} um {time} Uhr'),
        'tomorrow' => lt('morgen'),
        'dayDate' => lt('{day}, {date}'),
        'holiday' => lt('Praxis geschlossen bis {date}'),
        'closed' => lt('Derzeit geschlossen'),
        'shut' => lt('Geschlossen'),
        'reopen' => lt('um {time} Uhr'),                // Kontaktkarte: „Wir öffnen wieder“ + „um 15:30 Uhr“
        'reopenDay' => lt('{day} um {time} Uhr'),       // … „morgen um 7:30 Uhr“ / „am Montag um 7:30 Uhr“
        'onDay' => lt('am {day}'),
        'play' => lt('Abspielen'),
        'pause' => lt('Pause'),
        'start' => lt('Themenwechsel starten'),
        'stop' => lt('Themenwechsel anhalten'),
        'confirm' => lt('Bitte bestätigen.'),
        'fill' => lt('Bitte ausfüllen.'),
        'check' => lt('Bitte prüfen Sie Ihre Angaben.'),
        'failed' => lt('Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es erneut oder rufen Sie uns an.'),
    ];
}

/** Heute: Wochentag, Zeitfenster (je eine Zeile) und Notiz */
function praxis_today(): array
{
    $dow = (int) date('w');
    foreach (praxis_hours() as $h) {
        if ($h['dow'] === $dow) {
            $lines = array_map(fn($s) => lt('{time} Uhr', ['time' => $s]), $h['segments']);
            return ['name' => praxis_day_name($dow), 'lines' => $lines ?: [$h['note'] ?: lt('Sprechzeiten siehe unten')],
                'note' => $lines ? $h['note'] : '', 'time' => $h['time'], 'seg' => $h['raw']];
        }
    }
    return ['name' => praxis_day_name($dow), 'lines' => [lt('Heute geschlossen')], 'note' => '', 'time' => lt('Heute geschlossen'), 'seg' => []];
}

/**
 * Zeitspanne bündig: Beginn rechtsbündig, Strich, Ende – in Tabellen/Rastern stehen die Striche untereinander.
 * $from/$to = „HH:MM“. Screenreader lesen „7:30 bis 11:00“.
 */
function praxis_range_html(string $from, string $to): string
{
    return '<span class="trange"><span class="trange__a">' . e(praxis_clock($from)) . '</span><span class="trange__d" aria-hidden="true">–</span>'
        . '<span class="sr-only"> ' . e(lt('bis')) . ' </span><span class="trange__b">' . e(praxis_clock($to)) . '</span></span>';
}

/** Mehrere Zeitspannen einer Tabellenzelle (Vormittag/Nachmittag) */
function praxis_ranges_html(array $segments): string
{
    return implode('<br>', array_map(fn($s) => praxis_range_html($s[0], $s[1]), $segments));
}

/**
 * Linien-Symbole des Kits (24er Raster, Strich = currentColor), dekorativ (aria-hidden).
 * termin = Kalender, rezept = Tablette/Kapsel, ueberweisung = Dokument mit Pfeil, tel = Telefon, anfahrt = Ort, extern = Pfeil nach außen.
 */
function praxis_icon(string $key, int $size = 22, float $stroke = 2): string
{
    static $paths = [
        'tel' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'termin' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8.5 15.5l2.2 2.2 4.8-4.7"/>',
        'rezept' => '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7z"/><path d="m8.5 8.5 7 7"/>',
        'ueberweisung' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 15h7M12.5 12l3 3-3 3"/>',
        'anfahrt' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'extern' => '<path d="M7 17 17 7M8 7h9v9"/>',
    ];
    return '<svg aria-hidden="true" focusable="false" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'
        . $stroke . '" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$key] ?? $paths['termin']) . '</svg>';
}

/** Anzeigename der Domain eines externen Links: „https://www.doctolib.de/…“ → „doctolib.de“ */
function praxis_host(string $url): string
{
    return preg_replace('~^www\.~i', '', (string) parse_url($url, PHP_URL_HOST));
}

/**
 * Hinweiszeile der Kontaktkarte (Praxisdaten → Notfall – Kurzform) sauber zusammengesetzt und in Sätze/Abschnitte geteilt.
 * Aufgeräumt werden Reste von Trennzeichen („erreichbar! ·. Außerhalb“ → „erreichbar! Außerhalb“), doppelte Leerzeichen und
 * doppelte Satzzeichen. Abschnitte mit den Notrufnummern (116 117 / 112) werden markiert: Ist die Praxis gerade geschlossen,
 * zeigt die Karte diese Nummern groß – die Wiederholung in der Hinweiszeile blendet site.js dann aus.
 * @return list<array{text: string, emergency: bool}>
 */
function praxis_card_note(?string $text = null): array
{
    $t = trim(preg_replace('~\s+~u', ' ', \Core\EditorNotes::strip((string) ($text ?? setting('notfall_kurz')))));
    $t = preg_replace('~\s*([.!?:;,])(?:\s*[·•|]\s*[.,;]?)+~u', '$1 ', $t);         // „! ·.“ → „!“
    $t = preg_replace('~\s*[·•|]\s*([.!?:;,])~u', '$1', $t);                           // „ ·.“ → „.“
    $t = preg_replace('~([.!?])[.,;]+~u', '$1', $t);                                     // „!.“ → „!“
    $t = trim(preg_replace(['~^[\s·•|.,;]+|[\s·•|,;]+$~u', '~ {2,}~'], ['', ' '], $t));
    if ($t === '') return [];
    $parts = preg_split('~\s+[·•|]\s+|(?<=[.!?])\s+(?=\p{Lu}|\d)~u', $t, -1, PREG_SPLIT_NO_EMPTY);
    return array_map(fn($p) => ['text' => trim($p), 'emergency' => (bool) preg_match('~116\s?117|(?<!\d)112(?!\d)~u', $p)], $parts);
}

/**
 * Hinweiszeile als HTML. Enthält sie die Notrufnummern und noch anderes, stehen zwei Fassungen übereinander im selben
 * Rasterfeld (Platz reserviert, die Karte bleibt gleich hoch): vollständig (geöffnet) und ohne die Notruf-Sätze (geschlossen –
 * dann stehen 116 117/112 groß oben). Besteht der Hinweis nur aus Notruf-Sätzen, bleibt er in beiden Zuständen stehen.
 */
function praxis_card_note_html(?string $text = null): string
{
    $parts = praxis_card_note($text);
    $join = function (array $list): string {
        $out = '';
        $prev = '';
        foreach (array_values($list) as $i => $p) {
            $out .= ($i ? (preg_match('~[.!?:]$~u', $prev) ? ' ' : ' · ') : '') . $p['text'];
            $prev = $p['text'];
        }
        return e($out);
    };
    $rest = array_filter($parts, fn($p) => !$p['emergency']);
    if (!$parts || !$rest || count($rest) === count($parts)) return $join($parts);
    return '<span data-now-open>' . $join($parts) . '</span><span data-now-closed hidden>' . $join($rest) . '</span>';
}

function praxis_greeting(): string
{
    $h = (int) date('G');
    return $h < 11 ? lt('Guten Morgen') : ($h < 18 ? lt('Guten Tag') : lt('Guten Abend'));
}

/** Aktive Hero-Themen (Datum geprüft), Hauptthema garantiert */
function praxis_slides(?array $override = null): array
{
    $today = date('Y-m-d');
    // _idx = Position in der Liste (für die Vorschau einzelner Themen in der Verwaltung)
    $all = array_values($override ?? (array) setting('hero_slides', []));
    foreach ($all as $i => &$one) { if (is_array($one)) $one['_idx'] = $i; }
    unset($one);
    $slides = array_values(array_filter($all, function ($s) use ($today) {
        if (empty($s['aktiv'])) return false;
        if (!empty($s['von_datum']) && $s['von_datum'] > $today) return false;
        if (!empty($s['bis_datum']) && $s['bis_datum'] < $today) return false;
        return true;
    }));
    $hasMain = false;
    foreach ($slides as &$s) {
        if (($s['typ'] ?? '') === 'main' && !$hasMain) {
            $hasMain = true;
            $s['is_main'] = true;
        }
    }
    unset($s);
    if (!$hasMain) {
        $slides[] = ['typ' => 'main', 'is_main' => true, 'eyebrow' => (string) setting('praxis_name'),
            'titel' => (string) setting('site_title', lt('Willkommen')), 'text' => '', 'button_label' => '', 'button_link' => ''];
    }
    return $slides;
}

function praxis_address_line(): string
{
    if (!filled(setting('strasse'))) {
        return praxis_note(trim(lt('[Straße und Hausnummer]'), '[]') . ', ' . trim(lt('[PLZ {city}]', ['city' => setting('ort') ?: lt('Ort')]), '[]'));
    }
    return implode(' · ', array_filter([(string) setting('strasse'), trim(setting('plz') . ' ' . setting('ort'))]));
}

/** Links Impressum / Datenschutz / Barrierefreiheit */
function praxis_legal_links(): array
{
    $out = [];
    foreach (['impressum_seite' => lt('Impressum'), 'datenschutz_seite' => lt('Datenschutz'), 'barrierefreiheit_seite' => lt('Barrierefreiheit')] as $k => $label) {
        $id = (int) setting($k);
        $p = $id ? Pages::find($id) : null;
        if ($p && ($p['status'] === 'published' || app()->auth->check())) {
            $out[] = ['label' => $label, 'href' => Pages::url($p), 'key' => $k];
        }
    }
    return $out;
}

function praxis_privacy_url(): string
{
    $id = (int) setting('datenschutz_seite');
    $p = $id ? Pages::find($id) : null;
    return $p ? Pages::url($p) : '#';
}

/** Online-Services für Kontaktkarte / Menü */
function praxis_services(): array
{
    $out = [];
    if (setting('doctolib_aktiv')) {
        $out['termin'] = ['label' => lt('Termin'), 'sub' => 'Doctolib', 'title' => lt('Termin reservieren'),
            'href' => filled(setting('doctolib_url')) ? (string) setting('doctolib_url') : '#kontakt'];
    }
    foreach (['rezept' => [lt('Rezept'), lt('Online-Rezept')], 'ueberweisung' => [lt('Überweisung'), lt('Online-Überweisung')]] as $k => [$label, $title]) {
        if (Forms::enabled($k)) {
            $ext = Forms::mode($k) === 'external' ? Forms::externalUrl($k) : '';
            $out[$k] = ['label' => $label, 'sub' => lt('Online'), 'title' => $title, 'external' => $ext,
                'href' => $ext !== '' ? $ext : url('/anfrage/' . $k)];
        }
    }
    return $out;
}

/** Schema.org MedicalClinic – nur befüllte Felder */
function praxis_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) {
        return null;
    }
    $d = ['@context' => 'https://schema.org', '@type' => 'MedicalClinic'];
    if (filled(setting('praxis_name'))) $d['name'] = setting('praxis_name');
    $d['url'] = absolute_url('/');
    if (filled(setting('telefon'))) $d['telephone'] = substr((string) tel_href((string) setting('telefon')), 4) ?: null;
    if (filled(setting('fax'))) $d['faxNumber'] = setting('fax');
    if (filled(setting('email'))) $d['email'] = setting('email');
    $addr = array_filter([
        'streetAddress' => filled(setting('strasse')) ? setting('strasse') : null,
        'postalCode' => filled(setting('plz')) ? setting('plz') : null,
        'addressLocality' => filled(setting('ort')) ? setting('ort') : null,
    ]);
    if ($addr) {
        $d['address'] = ['@type' => 'PostalAddress', 'addressCountry' => 'DE'] + $addr;
    }
    $map = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
    $spec = [];
    foreach ((array) setting('oeffnungszeiten', []) as $r) {
        if (empty($r['von']) || empty($r['bis']) || !isset($map[(int) $r['tag']])) continue;
        $ranges = !empty($r['pause_von']) && !empty($r['pause_bis'])
            ? [[$r['von'], $r['pause_von']], [$r['pause_bis'], $r['bis']]] : [[$r['von'], $r['bis']]];
        foreach ($ranges as [$o, $c]) {
            $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . $map[(int) $r['tag']], 'opens' => $o, 'closes' => $c];
        }
    }
    if ($spec) $d['openingHoursSpecification'] = $spec;
    if (setting('doctolib_aktiv') && filled(setting('doctolib_url'))) $d['sameAs'] = [setting('doctolib_url')];
    $d = array_filter($d, fn($v) => $v !== null && $v !== '');
    return count($d) > 3 ? $d : null;
}

/** Überschrift im Muster „Stichwort. leichte Zeile“ */
function praxis_heading(\Core\Block $b, string $tag = 'h2', string $class = 'h2', string $strong = 'title_strong', string $light = 'title_light', bool $block = true): string
{
    $d = $b->data;
    if (trim((string) ($d[$strong] ?? '')) === '' && trim((string) ($d[$light] ?? '')) === '') {
        return '';
    }
    $html = '<' . $tag . ' id="' . e($b->titleId()) . '" class="' . e($class) . '" data-reveal="up">'
        . '<span' . $b->edit($strong) . '>' . e($d[$strong] ?? '') . '</span><span class="dot">.</span>';
    if (trim((string) ($d[$light] ?? '')) !== '' || is_editing()) {
        $html .= ($block ? '' : ' ') . '<span class="' . ($block ? 'light' : 'light light--inline') . '"' . $b->edit($light) . '>' . e($d[$light] ?? '') . '</span>';
    }
    return $html . '</' . $tag . '>';
}

/** Bild oder Platzhalter-Fläche wie im Design */
function praxis_image(?int $id, string $sizes, string $placeholder, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, $opt);
    if ($pic !== '') {
        return $pic;
    }
    return '<span class="ph-label">' . e($placeholder) . '</span>';
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function praxis_app_info(): array
{
    $w1 = trim((string) setting('wortmarke_1'));
    $w2 = trim((string) setting('wortmarke_2'));
    $name = trim($w1 . ' ' . $w2) ?: (string) setting('praxis_name');
    $shortcuts = [['name' => lt('Sprechzeiten & Kontakt'), 'short_name' => lt('Kontakt'), 'url' => url('/') . '#kontakt']];
    foreach (array_keys(app()->theme->forms()) as $key) {
        if (Forms::enabled($key) && Forms::mode($key) === 'internal') {
            $shortcuts[] = ['name' => (string) Forms::def($key)['label'], 'url' => url('/anfrage/' . $key)];
        }
    }
    return [
        'name' => $name,
        'short_name' => $w2 !== '' ? lt('Praxis {name}', ['name' => $w2]) : mb_substr($name, 0, 12),
        'description' => (string) setting('default_meta_description'),
        'shortcuts' => $shortcuts,
    ];
}

/** Öffentliche Basisdaten der Praxis (GET /api/v1/public, ohne Token) */
function praxis_public_info(): array
{
    return [
        'address' => array_filter([
            'street' => filled(setting('strasse')) ? setting('strasse') : null,
            'zip' => filled(setting('plz')) ? setting('plz') : null,
            'city' => filled(setting('ort')) ? setting('ort') : null,
        ]),
        'phone' => filled(setting('telefon')) ? substr((string) tel_href((string) setting('telefon')), 4) : null,
        'phone_display' => filled(setting('telefon_anzeige')) ? setting('telefon_anzeige') : null,
        'email' => filled(setting('email')) ? setting('email') : null,
        'appointment_url' => setting('doctolib_aktiv') && filled(setting('doctolib_url')) ? setting('doctolib_url') : null,
        'emergency' => setting('notfall_kurz'),
    ];
}

/** Darstellung „Klassisch“ (Design → Darstellung) aktiv? */
function praxis_classic(): bool
{
    return (string) design('look') === 'klassisch';
}

/** Stylesheet-Satz der Darstellung: bei „Klassisch“ css/{name}.css → css/classic-{name}.css (Core\Theme::asset, 'asset_variant') */
function praxis_asset_variant(string $path): string
{
    if (!str_starts_with($path, 'css/') || !str_ends_with($path, '.css') || str_starts_with($path, 'css/classic-') || !praxis_classic()) return $path;
    return 'css/classic-' . substr($path, 4);
}
