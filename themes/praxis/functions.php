<?php
/*
 * Theme-Helfer „praxis“. Werden von Templates und Block-Renderern genutzt.
 */

use Core\Forms;
use Core\Pages;

const PRAXIS_DAYS = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag'];

/** Telefonnummer zur Anzeige */
function praxis_phone(): string
{
    // In weiteren Sprachen automatisch international (+49 …), siehe phone_display()
    return phone_display((string) (setting('telefon_anzeige') ?: setting('telefon') ?: lt('[Telefonnummer]')));
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
        $am = $pm = [];
        foreach (praxis_raw_segments($rows) as [$from, $to]) {
            $label = praxis_clock($from) . '–' . praxis_clock($to);
            $from < '12:00' ? $am[] = $label : $pm[] = $label;
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
                'note' => $lines ? $h['note'] : '', 'time' => $h['time']];
        }
    }
    return ['name' => praxis_day_name($dow), 'lines' => [lt('Heute geschlossen')], 'note' => '', 'time' => lt('Heute geschlossen')];
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
        return lt('[Straße und Hausnummer]') . ' · ' . lt('[PLZ {city}]', ['city' => setting('ort') ?: lt('Ort')]);
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
