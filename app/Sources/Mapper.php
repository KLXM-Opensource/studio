<?php
declare(strict_types=1);

namespace Core\Sources;

use Core\Data\Tables;
use Core\Fields;
use Core\Pages;
use Core\Sanitizer;

/**
 * Zuordnung Quelle → Felder einer Datentabelle.
 *
 * mapping = ['id_path' => 'guid', 'slug_path' => '', 'rows' => [feldname => [
 *     'path'    => 'title | dc:title',   Pfad(e) in der Quelle, „|“ = Alternativen (erster nicht leerer Wert)
 *     'tx'      => '',                   Umwandlung: text | html | date | number | bool | slug | template | raw ('' = passend zum Feldtyp)
 *     'opt'     => '',                   Option der Umwandlung: Datumsformate „d.m.Y|Y-m-d“, Zahlenformat de|en, Vorlage „{a} {b}“
 *     'lookup'  => "A=B\nC=D",           Werte ersetzen (vor der Umwandlung; Groß-/Kleinschreibung egal)
 *     'default' => '',                   Standardwert, wenn leer
 *     'alt'     => 'title',              nur Bild-/Dateifelder: Pfad für den Alt-Text
 * ]]]
 * Bild-/Dateifelder liefern keine Medien-ID, sondern einen Verweis (Adresse bzw. Dateiname im ZIP) – den Import
 * übernimmt Core\Sources\Sync (Mediathek, EXIF entfernt, neu kodiert).
 */
final class Mapper
{
    /** Umwandlungen (Bezeichnungen übersetzbar) */
    public static function transforms(): array
    {
        return [
            '' => __('automatisch'),
            'text' => __('Text (ohne HTML)'),
            'html' => __('HTML bereinigen'),
            'date' => __('Datum lesen'),
            'number' => __('Zahl lesen'),
            'bool' => __('Ja/Nein'),
            'slug' => __('Kurzform (slug)'),
            'truncate' => __('Kürzen'),
            'para' => __('Nur erster Absatz'),
            'template' => __('Vorlage (zusammensetzen)'),
            'now' => __('jetzt (Zeitpunkt des Abrufs)'),
            'raw' => __('unverändert'),
        ];
    }

    /** Kurze Erklärung je Umwandlung (unter der Auswahl) */
    public static function transformHelp(): array
    {
        return [
            '' => __('Passend zum Feldtyp: Datumsangaben werden gelesen, Zahlen erkannt, HTML wird bei einfachem Text entfernt.'),
            'text' => __('Entfernt HTML und doppelte Leerzeichen – für Titel, Namen und kurze Texte.'),
            'html' => __('Behält sichere Formatierungen (Absätze, Fett, Links, Listen) – für formatierten Text.'),
            'date' => __('Liest ein Datum: RSS-Datum (RFC 822), ISO 8601, Unix-Zeit oder 01.10.2026. Feste Formate unter „Erweitert“.'),
            'number' => __('Liest Zahlen wie 1.234,56 oder 1,234.56 – Währungszeichen und Einheiten werden ignoriert.'),
            'bool' => __('Ja bei 1, true, ja, yes oder x – sonst Nein.'),
            'slug' => __('Macht aus dem Wert eine Kurzform für Adressen, z. B. „mein-beitrag“.'),
            'truncate' => __('Entfernt HTML und kürzt an einer Wortgrenze mit „…“ – Länge unter „Erweitert“ (Standard 200 Zeichen). Für Teaser aus Beschreibung oder Volltext.'),
            'para' => __('Nimmt nur den ersten Absatz (ohne HTML) – optional unter „Erweitert“ zusätzlich auf eine Zeichenzahl gekürzt.'),
            'template' => __('Setzt mehrere Werte zusammen – Vorlage unter „Erweitert“, z. B. {author.name} · {category}.'),
            'now' => __('Zeitpunkt, zu dem der Eintrag zum ersten Mal abgerufen wurde – für Quellen ohne Datum. Liefert der Pfad ein Datum, zählt dieses.'),
            'raw' => __('Übernimmt den Wert genau so, wie er in der Quelle steht.'),
        ];
    }

    /** Was die Quelle für einen Feldtyp liefern sollte (unter dem Feldnamen) */
    public static function typeHint(string $type): string
    {
        return match ($type) {
            'date', 'datetime' => __('RSS-Datum (RFC 822), ISO 8601 oder Unix-Zeit werden erkannt'),
            'time' => __('Uhrzeit wie 14:05 oder ein vollständiges Datum'),
            'number' => __('Zahl wie 1.234,56 oder 1,234.56'),
            'bool' => __('ja/nein, true/false oder 1/0'),
            'select', 'multiselect' => __('Kurzname oder Bezeichnung einer Auswahlmöglichkeit'),
            'url' => __('vollständige Adresse mit https://'),
            'email' => __('E-Mail-Adresse'),
            'media' => __('Adresse eines Bildes (JPG, PNG, WebP, GIF)'),
            'file' => __('Adresse einer Datei (PDF oder Bild)'),
            'richtext' => __('Text oder HTML – wird bereinigt'),
            'textarea' => __('Text – HTML wird entfernt, Absätze bleiben'),
            default => __('Text – HTML wird entfernt'),
        };
    }

    /** Feldtypen, die sich zuordnen lassen (Verknüpfungen, Gruppen, Wiederholungen nicht) */
    public const UNSUPPORTED = ['relation', 'relations', 'group', 'recurrence', 'iban'];

    /**
     * Einen Eintrag der Quelle zuordnen.
     * @return array{values: array, ext_id: string, slug: string, media: array<string, array{ref: string, alt: string}>, warnings: list<string>, now: array<string, true>}
     * now: Felder mit dem Zeitpunkt des Abrufs (Umwandlung „jetzt“) – zählen nicht zur Prüfsumme, bestehende Einträge behalten ihren Wert
     */
    public static function map(array $table, array $mapping, array $item): array
    {
        $values = [];
        $media = [];
        $warn = [];
        $now = [];
        $rows = (array) ($mapping['rows'] ?? []);
        foreach ($table['fields'] as $f) {
            $rule = $rows[$f['name']] ?? null;
            if (!is_array($rule) || in_array($f['type'], self::UNSUPPORTED, true)) continue;
            if (!self::isSet($rule)) continue;
            if (in_array($f['type'], ['media', 'file'], true)) {
                $ref = self::first($item, (string) ($rule['path'] ?? ''));
                $ref = $ref !== null ? trim($ref) : '';
                if ($ref === '') $ref = trim((string) ($rule['default'] ?? ''));
                if ($ref !== '') {
                    $alt = trim(strip_tags(html_entity_decode((string) (self::first($item, (string) ($rule['alt'] ?? '')) ?? ''), ENT_QUOTES | ENT_HTML5)));
                    $media[$f['name']] = ['ref' => $ref, 'alt' => mb_substr($alt, 0, 250)];
                }
                continue;
            }
            [$v, $w, $clock] = self::value($f, $rule, $item);
            if ($w !== null) $warn[] = $w;
            if ($clock) $now[$f['name']] = true;
            if ($v === null || $v === '' || $v === []) continue;
            // Feldprüfung wie beim Speichern – ungültige Werte fallen mit Hinweis weg, statt den ganzen Eintrag zu verwerfen
            $schema = ['name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'options' => $f['options'] ?? []];
            [$clean, $err] = Fields::clean($schema, $v);
            if ($err !== null) {
                $warn[] = $err;
                continue;
            }
            $values[$f['name']] = $clean;
        }
        $id = trim((string) (self::first($item, (string) ($mapping['id_path'] ?? '')) ?? ''));
        if ($id === '') {
            // Ohne eindeutigen Schlüssel: Adresse bzw. Titel – sonst Prüfsumme des Eintrags
            $id = trim((string) (self::first($item, 'link@href | link | url | id | guid') ?? ''));
            if ($id === '') $id = 'h:' . substr(hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE) ?: ''), 0, 32);
        }
        $slug = trim((string) (self::first($item, (string) ($mapping['slug_path'] ?? '')) ?? ''));
        return ['values' => $values, 'ext_id' => mb_substr($id, 0, 190), 'slug' => $slug !== '' ? Pages::slugify($slug) : '', 'media' => $media, 'warnings' => $warn,
            'now' => array_intersect_key($now, $values)];
    }

    /** Zeile der Zuordnung belegt? (Pfad, Vorlage, „jetzt“ oder Standardwert) */
    public static function isSet(array $rule): bool
    {
        return trim((string) ($rule['path'] ?? '')) !== '' || in_array((string) ($rule['tx'] ?? ''), ['template', 'now'], true) || trim((string) ($rule['default'] ?? '')) !== '';
    }

    /** Erster nicht leerer Wert aus „pfad | pfad2“ als Text; „liste[*]“ verbindet alle Werte mit Komma */
    public static function first(array $item, string $paths): ?string
    {
        foreach (explode('|', $paths) as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (str_contains($p, '[*]')) {
                // alle Werte einer Liste, mit Komma verbunden (z. B. category[*] → „Politik, Sport“)
                $vals = array_values(array_unique(array_filter(array_map('trim', Parser::scalars(Parser::get($item, $p))), fn($x) => $x !== '')));
                if ($vals) return implode(', ', $vals);
                continue;
            }
            $s = Parser::scalar(Parser::get($item, $p));
            if ($s !== null && trim($s) !== '') return $s;
        }
        return null;
    }

    /** @return array{0: mixed, 1: ?string, 2: bool} [Wert, Hinweis, Zeitpunkt des Abrufs] */
    private static function value(array $f, array $rule, array $item): array
    {
        $type = $f['type'];
        $tx = (string) ($rule['tx'] ?? '');
        $opt = (string) ($rule['opt'] ?? '');
        if ($tx === 'now') {
            // Datum aus der Quelle, falls vorhanden – sonst Zeitpunkt des Abrufs
            $raw = self::first($item, (string) ($rule['path'] ?? ''));
            $v = $raw !== null ? self::date($raw, $opt, $type) : null;
            if ($v !== null) return [$v, null, false];
            return [self::date((string) time(), '', in_array($type, ['date', 'datetime', 'time'], true) ? $type : 'datetime'), null, true];
        }
        if ($tx === '') {
            $tx = match ($type) {
                'richtext' => 'html', 'text', 'textarea', 'select', 'email', 'tel' => 'text', 'date', 'datetime', 'time' => 'date',
                'number' => 'number', 'bool' => 'bool', default => 'raw',
            };
        }
        if ($type === 'multiselect') {
            $raw = [];
            foreach (explode('|', (string) ($rule['path'] ?? '')) as $p) {
                if (trim($p) === '') continue;
                $raw = Parser::scalars(Parser::get($item, trim($p)));
                if ($raw) break;
            }
            if (count($raw) === 1 && str_contains($raw[0], ',')) $raw = array_map('trim', explode(',', $raw[0]));
            $out = [];
            foreach ($raw as $r) {
                $k = self::option($f, self::lookup($rule, self::plain($r)));
                if ($k !== null) $out[] = $k;
            }
            if (!$out && trim((string) ($rule['default'] ?? '')) !== '') $out = array_filter(array_map(fn($d) => self::option($f, trim($d)), explode(',', (string) $rule['default'])));
            return [array_values(array_unique($out)), null, false];
        }
        $raw = $tx === 'template' ? self::template($item, $opt !== '' ? $opt : (string) ($rule['path'] ?? '')) : self::first($item, (string) ($rule['path'] ?? ''));
        $raw = $raw === null ? null : self::lookup($rule, $raw);
        $v = $raw === null || trim($raw) === '' ? null : match ($tx) {
            'text', 'template' => self::plain($raw, $type === 'textarea'),
            'html' => self::html($raw),
            'date' => self::date($raw, $opt, $type),
            'number' => self::number($raw, $opt),
            'bool' => in_array(mb_strtolower(trim($raw)), ['1', 'true', 'ja', 'yes', 'wahr', 'on', 'y', 'j', 'x'], true) ? '1' : '0',
            'slug' => Pages::slugify(self::plain($raw)),
            'truncate' => self::asType(self::truncate(self::plain($raw), (int) $opt ?: 200), $type),
            'para' => self::asType(($p = self::firstParagraph($raw)) !== '' && (int) $opt > 0 ? self::truncate($p, (int) $opt) : $p, $type),
            default => trim($raw),
        };
        $warn = null;
        if ($v === null && $raw !== null && trim($raw) !== '' && in_array($tx, ['date', 'number'], true)) {
            $warn = __('„{label}“: Wert „{value}“ ließ sich nicht umwandeln.', ['label' => $f['label'], 'value' => mb_strimwidth(trim($raw), 0, 40, '…')]);
        }
        if (($v === null || $v === '') && trim((string) ($rule['default'] ?? '')) !== '') {
            $v = (string) $rule['default'];
            $warn = null;
        }
        if ($v !== null && $v !== '' && $type === 'select') {
            $k = self::option($f, (string) $v);
            if ($k === null) {
                $warn = __('„{label}“: „{value}“ ist keine Auswahlmöglichkeit.', ['label' => $f['label'], 'value' => mb_strimwidth((string) $v, 0, 40, '…')]);
                $d = trim((string) ($rule['default'] ?? ''));
                $v = $d !== '' ? self::option($f, $d) : null;
            } else {
                $v = $k;
            }
        }
        if (is_string($v) && in_array($type, ['text', 'email', 'tel', 'url', 'link'], true)) $v = mb_substr($v, 0, 255);
        if ($type === 'url' && is_string($v) && $v !== '' && !preg_match('~^https?://~i', $v)) {
            $warn = __('„{label}“: „{value}“ ist keine vollständige Adresse (https://…).', ['label' => $f['label'], 'value' => mb_strimwidth($v, 0, 40, '…')]);
            $v = null;
        }
        return [$v, $warn, false];
    }

    /** Auswahlfeld: Schlüssel zu Wert (Schlüssel oder Bezeichnung, Groß-/Kleinschreibung egal) */
    private static function option(array $f, string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return null;
        foreach ((array) ($f['options'] ?? []) as $k => $label) {
            if (strcasecmp((string) $k, $v) === 0 || mb_strtolower((string) $label) === mb_strtolower($v)) return (string) $k;
        }
        return null;
    }

    private static function lookup(array $rule, string $v): string
    {
        $lines = trim((string) ($rule['lookup'] ?? ''));
        if ($lines === '') return $v;
        foreach (preg_split('~\R~', $lines) as $line) {
            if (!str_contains($line, '=')) continue;
            [$from, $to] = array_map('trim', explode('=', $line, 2));
            if ($from === '*' || mb_strtolower($from) === mb_strtolower(trim($v))) return $to;
        }
        return $v;
    }

    /** Vorlage „{geo.strasse} {geo.hausnummer}“ – leere Platzhalter fallen weg, doppelte Trenner werden gekürzt */
    public static function template(array $item, string $tpl): ?string
    {
        if (!str_contains($tpl, '{')) return self::first($item, $tpl);
        $out = (string) preg_replace_callback('~\{([^{}]+)\}~u', fn($m) => self::plain((string) (self::first($item, $m[1]) ?? '')), $tpl);
        $out = trim((string) preg_replace(['~[ \t]{2,}~u', '~(\s+[,;·|/–-]\s*|\s*[,;·|–-]\s+){2,}~u', '~^[\s,;·|/–-]+|[\s,;·|/–-]+$~u'], [' ', ', ', ''], $out));
        return $out === '' ? null : $out;
    }

    /** Kürzen an einer Wortgrenze mit „…“ (Text ohne HTML) */
    public static function truncate(string $s, int $max): string
    {
        $s = trim($s);
        $max = max(10, min(5000, $max));
        if (mb_strlen($s) <= $max) return $s;
        $cut = mb_substr($s, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $max * 0.6) $cut = mb_substr($cut, 0, $sp);
        return (string) preg_replace('~[\s,;:.\-–—(]+$~u', '', $cut) . '…';
    }

    /** Erster Absatz als Text: erstes <p>…</p> bzw. Text bis zur ersten Leerzeile oder zum ersten <br><br> */
    public static function firstParagraph(string $raw): string
    {
        $raw = trim($raw);
        if (!preg_match('~<[a-z][\s\S]*>~i', $raw)) {
            $dec = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~<[a-z][\s\S]*>~i', $dec)) $raw = $dec;
        }
        if (preg_match('~<p\b[^>]*>(.*?)</p>~is', $raw, $m) && trim(self::plain($m[1])) !== '') return self::plain($m[1]);
        $parts = preg_split('~(\R\s*\R|<br\s*/?>\s*<br\s*/?>|</(?:div|h[1-6]|li|blockquote)>)~i', $raw) ?: [$raw];
        foreach ($parts as $part) if (($t = self::plain($part)) !== '') return $t;
        return '';
    }

    /** Gekürzten Text für formatierte Felder als einen Absatz ausgeben */
    private static function asType(string $text, string $type): string
    {
        return $type === 'richtext' && $text !== '' ? '<p>' . e($text) . '</p>' : $text;
    }

    /** HTML → Text in einer Zeile, Absätze und Listenpunkte durch Leerzeichen getrennt (Beispiele, Probeabruf) */
    public static function oneLine(string $s): string
    {
        return trim((string) preg_replace('~\s*\n\s*~u', ' ', self::plain($s, true)));
    }

    /** HTML → Text (Zeilenumbrüche optional erhalten) */
    public static function plain(string $s, bool $lines = false): string
    {
        if ($lines) $s = (string) preg_replace('~<\s*(br|/p|/li|/h\d|/div)\s*/?>~i', "\n", $s);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = $lines ? (string) preg_replace(["~[ \t\x{00A0}]+~u", "~\n\s*\n\s*\n+~"], [' ', "\n\n"], $s) : (string) preg_replace('~\s+~u', ' ', $s);
        return trim($s);
    }

    /** HTML bereinigen (Whitelist des Kerns); reiner Text wird zu Absätzen */
    public static function html(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        if (!preg_match('~<[a-z][\s\S]*>~i', $s)) {
            // Entities wie &lt;p&gt; (doppelt kodiert in manchen Feeds)
            $dec = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~<[a-z][\s\S]*>~i', $dec)) $s = $dec;
            else return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p)), false) . '</p>', array_filter(preg_split('~\R{2,}~', $dec), fn($p) => trim($p) !== '')));
        }
        return Sanitizer::block($s);
    }

    /**
     * Datum lesen: feste Formate „d.m.Y|Y-m-d H:i“ (PHP DateTime) oder automatisch (RFC 822/2822, ISO 8601, Unix-Zeit).
     * Ausgabe je Feldtyp: date → JJJJ-MM-TT, datetime → JJJJ-MM-TT HH:MM (Zeitzone der Website), time → HH:MM, Text → TT.MM.JJJJ
     */
    public static function date(string $raw, string $formats, string $type): ?string
    {
        $raw = trim(self::plain($raw));
        if ($raw === '') return null;
        $tz = new \DateTimeZone((string) app()->config->get('timezone', 'Europe/Berlin'));
        $dt = null;
        foreach (array_filter(array_map('trim', explode('|', $formats))) as $fmt) {
            $d = \DateTimeImmutable::createFromFormat('!' . ltrim($fmt, '!'), $raw, $tz);
            if ($d && (\DateTimeImmutable::getLastErrors() === false || !(\DateTimeImmutable::getLastErrors()['warning_count'] ?? 0))) { $dt = $d; break; }
        }
        if (!$dt) {
            try {
                if (ctype_digit($raw) && strlen($raw) >= 9) $dt = (new \DateTimeImmutable('@' . $raw));
                elseif (preg_match('~^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?~', $raw, $m)) $dt = new \DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d', $m[3], $m[2], $m[1], $m[4] ?? 0, $m[5] ?? 0), $tz);
                elseif (strtotime($raw) !== false) $dt = new \DateTimeImmutable($raw, $tz);
            } catch (\Throwable) {
                $dt = null;
            }
        }
        if (!$dt) return null;
        $dt = $dt->setTimezone($tz);
        return match ($type) {
            'date' => $dt->format('Y-m-d'),
            'datetime' => $dt->format('Y-m-d H:i'),
            'time' => $dt->format('H:i'),
            default => $dt->format('d.m.Y'),
        };
    }

    /** Zahl lesen: de „1.234,56“, en „1,234.56“, leer = automatisch; Währungszeichen und Einheiten werden ignoriert */
    public static function number(string $raw, string $locale = ''): ?string
    {
        $s = (string) preg_replace('~[^\d,.\-]~u', '', self::plain($raw));
        if ($s === '' || $s === '-') return null;
        if ($locale === '') {
            // automatisch: bei beiden Zeichen ist das letzte das Dezimalzeichen; nur Komma → Dezimalkomma (außer mehrfach);
            // nur Punkt → Dezimalpunkt (außer mehrfach, dann Tausender)
            $lc = strrpos($s, ',');
            $ld = strrpos($s, '.');
            $locale = match (true) {
                $lc !== false && $ld !== false => $lc > $ld ? 'de' : 'en',
                $lc !== false => substr_count($s, ',') > 1 ? 'en' : 'de',
                $ld !== false => substr_count($s, '.') > 1 ? 'de' : 'en',
                default => 'en',
            };
        }
        $s = $locale === 'de' ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
        return is_numeric($s) ? (string) ($s + 0) : null;
    }

    /** Prüfsumme der zugeordneten Werte (inkl. Bildverweise) – nur geänderte Einträge werden gespeichert */
    public static function hash(array $mapped): string
    {
        $v = array_diff_key($mapped['values'], $mapped['now'] ?? []);
        ksort($v);
        $m = $mapped['media'];
        ksort($m);
        return hash('sha256', json_encode([$v, $m, $mapped['slug']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** Typische Pfade je Bedeutung (RSS, Atom, JSON) – Reihenfolge = Vorrang, mehrere vorhandene werden mit „|“ verbunden */
    public const CANDIDATES = [
        'id' => ['guid', 'id', 'uuid'],
        'title' => ['title', 'dc:title', 'headline', 'name'],
        'date' => ['pubDate', 'published', 'updated', 'dc:date', 'date', 'datePublished', 'date_published', 'published_at', 'created_at', 'updated_at', 'modified'],
        'author' => ['dc:creator', 'author.name', 'author', 'creator'],
        'media' => ['enclosure[@type!=audio/mpeg]@url', 'media:content@url', 'media:thumbnail@url', 'media:group.media:content@url', 'link[@rel=enclosure]@href', 'image.url', 'image', 'thumbnail', 'featured_image'],
        'url' => ['link[@rel=alternate]@href', 'link@href', 'link', 'url', 'permalink'],
        'teaser' => ['description', 'summary', 'excerpt', 'teaser', 'media:description', 'content_text'],
        'content' => ['content:encoded', 'content', 'content_html', 'body', 'description', 'summary', 'text'],
        'category' => ['category@term', 'category', 'dc:subject', 'categories', 'tags'],
    ];

    /** Felder, die einen gekürzten Text erwarten (Vorschlag „Kürzen 200“) */
    public const TEASER = '~(teaser|kurz|excerpt|zusammenfassung|anriss|vorspann)~';

    /** Bedeutung aus Feldname/Bezeichnung (Schlüsselwörter) und erlaubte Feldtypen je Bedeutung */
    private const KEYWORDS = [
        'id' => ['~^(id|guid|uuid|externe?_id)$~', ['text']],
        'title' => ['~(titel|title|ueberschrift|headline|schlagzeile)~', ['text', 'textarea']],
        'date' => ['~(datum|date|zeit|veroeffentl|published|erschienen|seit|_ab$|^ab_)~', ['date', 'datetime', 'time', 'text']],
        'author' => ['~(autor|author|verfasser|creator)~', ['text', 'select']],
        'media' => ['~(bild|image|foto|photo|thumbnail|grafik|vorschau)~', ['media', 'file']],
        'url' => ['~(link|url|webadresse|original|permalink)~', ['url', 'link', 'text']],
        'teaser' => ['~(teaser|beschreibung|zusammenfassung|description|summary|excerpt|kurz|anriss|einleitung|vorspann)~', ['textarea', 'text', 'richtext']],
        'content' => ['~(text|inhalt|content|body|artikel|volltext|meldung)~', ['richtext', 'textarea']],
        'category' => ['~(kategorie|category|rubrik|tags?|schlagw|thema)~', ['text', 'select', 'multiselect', 'textarea']],
    ];

    /**
     * Vorschlag: leere Zeilen der Zuordnung aus den gefundenen Pfaden füllen – nach gleichem Namen, Schlüsselwörtern in
     * Feldname/Bezeichnung und zuletzt nach Feldtyp (Datum → pubDate | published …, Bild → enclosure@url …).
     * Belegte Zeilen bleiben unverändert. Pflichtfelder mit Datum ohne passenden Pfad bekommen „jetzt (Zeitpunkt des Abrufs)“.
     * @return array{id_path: string, slug_path: string, rows: array, filled: list<string>}  filled = Bezeichnungen der gefüllten Felder
     */
    public static function suggest(array $table, array $paths, string $format, ?array $current = null): array
    {
        $current ??= ['id_path' => '', 'slug_path' => '', 'rows' => []];
        $rows = (array) ($current['rows'] ?? []);
        $filled = [];
        $usedByType = [];
        foreach ($table['fields'] as $f) {
            if (in_array($f['type'], self::UNSUPPORTED, true)) continue;
            $old = (array) ($rows[$f['name']] ?? []);
            if (self::isSet($old)) continue;
            $found = [];
            $group = null;
            // 1. gleichnamiger Pfad (z. B. Feld „preis“ ← preis)
            $found = self::present($paths, [$f['name']]);
            // 2. Schlüsselwörter in Name/Bezeichnung, passend zum Feldtyp
            if (!$found) {
                $key = $f['name'] . ' ' . \Core\Data\Tables::normName((string) $f['label']);
                foreach (self::KEYWORDS as $g => [$re, $types]) {
                    if (!in_array($f['type'], $types, true) || !preg_match($re, $key)) continue;
                    if ($found = self::present($paths, self::CANDIDATES[$g])) { $group = $g; break; }
                }
            }
            // 3. nach Feldtyp (jede Bedeutung nur einmal, damit nicht drei Textfelder dieselbe Beschreibung bekommen)
            if (!$found) {
                $g = match ($f['type']) {
                    'date', 'datetime', 'time' => 'date', 'media', 'file' => 'media', 'url' => 'url', 'richtext' => 'content', 'textarea' => 'teaser',
                    'text' => $f['name'] === ($table['settings']['title_field'] ?? '') ? 'title' : null, default => null,
                };
                if ($g !== null && !isset($usedByType[$g]) && ($found = self::present($paths, self::CANDIDATES[$g]))) { $group = $g; $usedByType[$g] = true; }
            }
            if ($found) {
                $row = ['path' => implode(' | ', array_slice($found, 0, 3))];
                // Datum in ein Textfeld: trotzdem als Datum lesen (TT.MM.JJJJ statt „Wed, 01 Oct …“)
                if ($group === 'date' && $f['type'] === 'text') $row['tx'] = 'date';
                // Teaser/Kurztext aus Beschreibung oder Volltext: auf 200 Zeichen kürzen
                if ($group === 'teaser' || ($group === 'content' && in_array($f['type'], ['text', 'textarea'], true) && preg_match(self::TEASER, $f['name'] . ' ' . \Core\Data\Tables::normName((string) $f['label'])))) $row += ['tx' => 'truncate', 'opt' => '200'];
                if (in_array($f['type'], ['media', 'file'], true) && ($t = self::present($paths, self::CANDIDATES['title']))) $row['alt'] = $t[0];
                $rows[$f['name']] = $row + $old;
                $filled[] = (string) $f['label'];
            } elseif (!empty($f['required']) && in_array($f['type'], ['date', 'datetime', 'time'], true)) {
                $rows[$f['name']] = ['path' => '', 'tx' => 'now'] + $old;
                $filled[] = (string) $f['label'];
            }
        }
        $id = trim((string) ($current['id_path'] ?? ''));
        if ($id === '') {
            // erste Kennung, als Rückfall der Link (Leer = Link bzw. Prüfsumme, siehe map())
            $ids = self::present($paths, array_merge(self::CANDIDATES['id'], ['objektnummer', 'objektnr_extern']));
            $id = implode(' | ', array_merge(array_slice($ids, 0, 1), array_slice(self::present($paths, self::CANDIDATES['url']), 0, 1)));
        }
        return ['id_path' => $id, 'slug_path' => (string) ($current['slug_path'] ?? ''), 'rows' => $rows, 'filled' => $filled];
    }

    /** Welche der Kandidaten kommen in den gefundenen Pfaden vor? (Indizes [0] und Filter [@…] werden beim Vergleich ignoriert) */
    public static function present(array $paths, array $cands): array
    {
        static $norm = [];
        $key = md5(implode("\n", array_keys($paths)));
        $norm[$key] ??= array_flip(array_map(fn($p) => (string) preg_replace('~\[[^\]]*\]~', '', (string) $p), array_keys($paths)));
        $out = [];
        foreach ($cands as $c) {
            $base = (string) preg_replace('~\[[^\]]*\]~', '', $c);
            if (isset($norm[$key][$base]) && !in_array($c, $out, true)) {
                // derselbe Pfad mit und ohne Filter (link[@rel=alternate]@href, link@href): beide behalten – der Filter greift zuerst
                $out[] = $c;
            }
        }
        return $out;
    }

    /** Verständliche Bezeichnung bekannter Feed-Pfade (RSS, Atom, Media RSS, Dublin Core) – '' = unbekannt */
    public static function pathLabel(string $path): string
    {
        $k = (string) preg_replace('~\[(\d+|\*)\]~', '', $path);
        return match ($k) {
            'title', 'dc:title' => __('Titel'),
            'link', 'link@href', 'link[@rel=alternate]@href', 'url', 'permalink' => __('Link zum Beitrag'),
            'description' => __('Beschreibung (Teaser)'),
            'summary' => __('Zusammenfassung'),
            'content:encoded' => __('Volltext (HTML)'),
            'content', 'content_html' => __('Inhalt'),
            'pubDate', 'published', 'datePublished', 'date_published', 'published_at' => __('Datum der Veröffentlichung'),
            'updated', 'modified', 'updated_at' => __('Datum der letzten Änderung'),
            'dc:date', 'date' => __('Datum'),
            'guid', 'id', 'uuid' => __('Eindeutige ID'),
            'guid@isPermaLink' => __('ID ist zugleich Link (true/false)'),
            'author', 'dc:creator', 'author.name', 'creator' => __('Autor'),
            'author.email' => __('Autor (E-Mail)'),
            'author.uri' => __('Autor (Webseite)'),
            'category', 'category@term', 'dc:subject', 'categories', 'tags' => __('Kategorie'),
            'category@domain', 'category@scheme' => __('Kategorie (Schema)'),
            'enclosure@url' => __('Anhang / Bild (Adresse)'),
            'enclosure@type' => __('Anhang (Dateityp)'),
            'enclosure@length' => __('Anhang (Größe in Byte)'),
            'media:content@url', 'media:group.media:content@url', 'image', 'image.url', 'featured_image' => __('Bild (Adresse)'),
            'media:content@type', 'media:content@medium' => __('Bild (Art)'),
            'media:content@width', 'media:content@height' => __('Bild (Maße)'),
            'media:thumbnail@url', 'thumbnail' => __('Vorschaubild (Adresse)'),
            'media:description', 'media:title' => __('Bildbeschreibung'),
            'media:credit' => __('Bildnachweis'),
            'comments' => __('Kommentare (Adresse)'),
            'slash:comments' => __('Anzahl Kommentare'),
            'source', 'source@url' => __('Ursprüngliche Quelle'),
            'rights', 'dc:rights' => __('Rechte'),
            'link@rel' => __('Art des Links'),
            'link@type' => __('Link (Dateityp)'),
            'name' => __('Name'),
            default => '',
        };
    }

    /**
     * Felder der Quelle für Übersicht und Auswahl: Pfad, Bezeichnung, Beispiel (erster Eintrag).
     * Mehrfach vorkommende Elemente (category[0], category[1] …) erscheinen einmal mit [0] und zusätzlich als [*] („alle Werte“).
     * @return list<array{path: string, label: string, sample: string}>
     */
    public static function sourceFields(array $paths): array
    {
        $out = [];
        $multi = [];
        foreach ($paths as $p => $sample) {
            $p = (string) $p;
            if (preg_match('~^(.*?)\[(\d+)\](.*)$~', $p, $m)) {
                $star = $m[1] . '[*]' . $m[3];
                $multi[$star][] = (string) $sample;
                if ((int) $m[2] > 0) continue;
            }
            $out[$p] = ['path' => $p, 'label' => self::pathLabel($p), 'sample' => (string) $sample];
        }
        foreach ($multi as $star => $vals) {
            if (count($vals) < 2) continue;
            $base = (string) preg_replace('~\[\*\]~', '[0]', $star, 1);
            $entry = ['path' => $star, 'label' => trim(self::pathLabel($star) . ' ' . __('(alle Werte)')), 'sample' => mb_strimwidth(implode(', ', array_unique($vals)), 0, 120, '…')];
            // direkt hinter dem ersten Wert einsortieren
            $pos = array_search($base, array_keys($out), true);
            $out = $pos === false ? $out + [$star => $entry] : array_slice($out, 0, $pos + 1, true) + [$star => $entry] + array_slice($out, $pos + 1, null, true);
        }
        return array_values($out);
    }

    /** Ergebnis je Feld für den ersten Eintrag („Beispiel: …“ unter jeder Zeile der Zuordnung) */
    public static function explain(array $table, array $mapping, array $item): array
    {
        $out = [];
        foreach ($table['fields'] as $f) {
            if (in_array($f['type'], self::UNSUPPORTED, true)) continue;
            $name = $f['name'];
            $rule = (array) ($mapping['rows'][$name] ?? []);
            $req = !empty($f['required']);
            if (!self::isSet($rule)) {
                $out[$name] = $req ? ['state' => 'error', 'text' => __('Pflichtfeld – bitte eine Quelle wählen oder unter „Erweitert“ einen Standardwert angeben.')]
                    : ['state' => 'none', 'text' => __('Nicht zugeordnet – das Feld bleibt leer.')];
                continue;
            }
            $m = self::map($table, ['rows' => [$name => $rule]], $item);
            if (isset($m['media'][$name])) {
                $out[$name] = ['state' => 'ok', 'text' => $m['media'][$name]['ref']];
            } elseif (array_key_exists($name, $m['values'])) {
                $txt = self::display($f, $m['values'][$name]);
                if (isset($m['now'][$name])) $txt .= ' · ' . __('Zeitpunkt des Abrufs');
                $out[$name] = $m['warnings'] ? ['state' => 'warn', 'text' => $txt . ' – ' . $m['warnings'][0]] : ['state' => 'ok', 'text' => $txt];
            } else {
                $why = $m['warnings'][0] ?? __('Im ersten Eintrag leer.');
                $out[$name] = ['state' => $req ? 'error' : ($m['warnings'] ? 'warn' : 'empty'),
                    'text' => $req ? $why . ' ' . __('Pflichtfeld: Einträge ohne Wert werden nicht gespeichert.') : $why];
            }
        }
        return $out;
    }

    /** Zugeordneten Wert lesbar darstellen (Datum deutsch, Auswahl mit Bezeichnung, formatierter Text gekürzt) */
    public static function display(array $f, mixed $v): string
    {
        if (is_array($v)) return implode(', ', array_map(fn($k) => Tables::optionLabel($f, (string) $k), $v));
        $v = (string) $v;
        return match ($f['type']) {
            'datetime' => ($d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $v)) ? $d->format('d.m.Y, H:i') : $v,
            'date' => ($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v)) ? $d->format('d.m.Y') : $v,
            'select' => Tables::optionLabel($f, $v),
            'bool' => $v === '1' ? __('Ja') : __('Nein'),
            'richtext', 'textarea' => mb_strimwidth(self::oneLine($v), 0, 220, '…'),
            default => mb_strimwidth($v, 0, 220, '…'),
        };
    }

    /**
     * Probeabruf: die ersten $n Einträge so, wie sie gespeichert würden (Feld → Wert), mit Hinweisen und Fehlern.
     * errors: leere Pflichtfelder – solche Einträge würden beim Abruf nicht gespeichert.
     */
    public static function preview(array $table, array $mapping, array $items, int $n = 5): array
    {
        $out = [];
        foreach (array_slice($items, 0, $n) as $item) {
            $m = self::map($table, $mapping, $item);
            $shown = [];
            $errors = [];
            foreach ($table['fields'] as $f) {
                $rule = (array) ($mapping['rows'][$f['name']] ?? []);
                if (isset($m['media'][$f['name']])) { $shown[$f['name']] = ['label' => $f['label'], 'value' => $m['media'][$f['name']]['ref'], 'media' => true, 'alt' => $m['media'][$f['name']]['alt']]; continue; }
                if (!array_key_exists($f['name'], $m['values'])) {
                    if (!empty($f['required']) && !in_array($f['type'], self::UNSUPPORTED, true)) {
                        $errors[] = __('Pflichtfeld „{label}“ ist leer – dieser Eintrag würde nicht gespeichert.', ['label' => $f['label']]);
                        $shown[$f['name']] = ['label' => $f['label'], 'value' => '', 'error' => true];
                    } elseif (self::isSet($rule)) {
                        $shown[$f['name']] = ['label' => $f['label'], 'value' => '', 'empty' => true];
                    }
                    continue;
                }
                $shown[$f['name']] = ['label' => $f['label'], 'value' => self::display($f, $m['values'][$f['name']]) . (isset($m['now'][$f['name']]) ? ' · ' . __('Zeitpunkt des Abrufs') : '')];
            }
            $out[] = ['ext_id' => $m['ext_id'], 'fields' => $shown, 'warnings' => $m['warnings'], 'errors' => $errors, 'title' => (string) ($m['values'][$table['settings']['title_field']] ?? '')];
        }
        return $out;
    }

    // ================================================================== Neue Tabelle aus der Quelle

    /** Feldtypen, die „Neue Tabelle aus dieser Quelle“ anbietet */
    public const NEW_TABLE_TYPES = ['text', 'textarea', 'richtext', 'number', 'bool', 'date', 'datetime', 'url', 'email', 'media', 'file'];

    /**
     * Feldtyp aus Beispielwerten ableiten: Datum → datetime/date, Adresse → url (Bild-Adresse → media), Zahl → number,
     * HTML → richtext, langer Text → textarea, mehrere Werte ([*]) → text (mit Komma), sonst text.
     */
    public static function inferType(string $path, array $values): string
    {
        $vals = array_values(array_filter(array_map(fn($v) => trim((string) $v), $values), fn($v) => $v !== ''));
        $p = strtolower($path);
        if (str_contains($p, '[*]')) return 'text';
        $all = fn(string $re) => $vals && count(array_filter($vals, fn($v) => preg_match($re, $v))) === count($vals);
        $isImgPath = (bool) preg_match('~(enclosure|media:content|media:thumbnail|image|thumbnail|bild|foto|photo|picture|logo)~', $p);
        if ($all('~^https?://\S+$~i')) {
            if ($all('~\.(jpe?g|png|gif|webp|avif)(\?\S*)?$~i')) return 'media';
            if ($isImgPath && !$all('~\.(mp3|m4a|ogg|wav|mp4|pdf|zip)(\?\S*)?$~i') && preg_match('~(@url|@href|url|src)$~', $p)) return 'media';
            return 'url';
        }
        if (!$vals) return $isImgPath && preg_match('~(@url|@href)$~', $p) ? 'media' : 'text';
        if ($all('~^[^@\s<>]+@[^@\s<>]+\.[a-z]{2,}$~i')) return 'email';
        if ($all('~^(true|false)$~i')) return 'bool';
        // Datum: ISO 8601, RFC 822 („Wed, 01 Oct 2026 14:05:00 +0200“), deutsch, Unix-Zeit bei passendem Namen
        if ($all('~^\d{4}-\d{2}-\d{2}$~') || $all('~^\d{1,2}\.\d{1,2}\.\d{4}$~')) return 'date';
        if ($all('~^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}~') || $all('~^([A-Z][a-z]{2},\s*)?\d{1,2}\s+[A-Z][a-z]{2}\s+\d{2,4}\s+\d{1,2}:\d{2}~')
            || $all('~^\d{1,2}\.\d{1,2}\.\d{4},?\s+\d{1,2}:\d{2}~')) return 'datetime';
        $dateName = (bool) preg_match('~(date|datum|time|zeit|published|updated|modified|created)~', $p);
        if ($dateName && $all('~^\d{9,11}$~')) return 'datetime';
        if ($dateName && count(array_filter($vals, fn($v) => strtotime($v) !== false)) === count($vals) && !$all('~^\d+$~')) return 'datetime';
        // Zahl (keine Postleitzahl/Telefonnummer mit führender Null, keine langen Kennungen)
        if ($all('~^-?\d{1,9}([.,]\d+)?$~') && !$all('~^0\d~') && !preg_match('~(id|guid|nr|nummer|plz|zip|tel|phone)$~', $p)) return 'number';
        if (count(array_filter($vals, fn($v) => preg_match('~<(p|br|div|a|img|ul|ol|li|strong|em|b|i|h[1-6]|span|figure|blockquote)\b~i', $v)))) return 'richtext';
        $long = max(array_map('mb_strlen', $vals));
        if ($long > 160 || count(array_filter($vals, fn($v) => str_contains($v, "\n")))) return 'textarea';
        return 'text';
    }

    /** Lesbare Bezeichnung eines unbekannten Pfads: letztes Segment, ohne Präfix und Index, „@attr“ in Klammern */
    public static function humanize(string $path): string
    {
        $p = (string) preg_replace('~\[[^\]]*\]~', '', $path);
        $attr = '';
        if (preg_match('~^(.*)@([\w:.-]+)$~', $p, $m)) { $p = $m[1]; $attr = $m[2]; }
        $seg = (string) (array_slice(explode('.', $p), -1)[0] ?? $p);
        $seg = (string) preg_replace('~^[\w-]+:~', '', $seg);
        $nice = fn(string $s) => ucfirst(trim((string) preg_replace(['~([a-z])([A-Z])~', '~[_-]+~'], ['$1 $2', ' '], $s)));
        $out = $nice($seg !== '' ? $seg : $attr);
        if ($attr !== '' && $seg !== '') $out .= ' (' . $nice($attr) . ')';
        return $out !== '' ? $out : $path;
    }

    /**
     * Vorschlag „Neue Tabelle aus dieser Quelle“: Name, Kurzname, Adresse und Felder (Bezeichnung, Typ, Pfad, Beispiel, an/aus)
     * aus den Einträgen der Vorschau. RSS/Atom: Titel, Datum, Autor, Teaser, Text, Bild, Link, Kategorie vorn und angehakt.
     * @return array{name: string, handle: string, route: string, fields: list<array{on: bool, label: string, type: string, path: string, sample: string}>}
     */
    public static function proposeTable(array $items, string $format, string $name): array
    {
        $items = array_slice($items, 0, 5);
        $paths = Parser::paths($items);
        $feed = in_array($format, ['rss', 'atom'], true);
        $fields = [];
        $used = [];
        $sample = function (string $path) use ($items): string {
            foreach ($items as $it) {
                $v = self::first($it, $path);
                if ($v !== null && trim($v) !== '') return mb_strimwidth(self::oneLine($v), 0, 120, '…');
            }
            return '';
        };
        $values = function (string $path) use ($items): array {
            $out = [];
            foreach ($items as $it) { $v = self::first($it, (string) explode('|', $path)[0]); if ($v !== null) $out[] = $v; }
            return $out;
        };
        if ($feed) {
            $defs = [['Titel', 'text', 'title'], ['Datum', 'datetime', 'date'], ['Autor', 'text', 'author'], ['Teaser', 'textarea', 'teaser'],
                ['Text', 'richtext', 'content'], ['Bild', 'media', 'media'], ['Link', 'url', 'url'], ['Kategorie', 'text', 'category']];
            foreach ($defs as [$label, $type, $g]) {
                $cands = self::CANDIDATES[$g];
                // Teaser = description/summary; Text nur aus echten Volltext-Pfaden (sonst doppelt)
                if ($g === 'teaser') $cands = ['description', 'summary'];
                if ($g === 'content') $cands = ['content:encoded', 'content'];
                $found = array_values(array_filter(self::present($paths, $cands), fn($c) => !isset($used[self::normPath($c)])));
                if (!$found) continue;
                // mehrere Kategorien: alle Werte übernehmen
                if ($g === 'category' && array_filter(array_keys($paths), fn($k) => preg_match('~^category\[1\]~', (string) $k))) $found = [str_contains($found[0], '@') ? str_replace('@', '[*]@', $found[0]) : $found[0] . '[*]'];
                $path = implode(' | ', array_slice($found, 0, 3));
                foreach ($found as $c) $used[self::normPath($c)] = true;
                $fields[] = ['on' => true, 'label' => $label, 'type' => $type, 'path' => $path, 'sample' => $sample($path)];
            }
            foreach (['guid', 'id'] as $c) $used[$c] = true;   // Kennung → „Eindeutige ID“ der Zuordnung, kein eigenes Feld
        }
        $on = 0;
        foreach (self::sourceFields($paths) as $sf) {
            $p = $sf['path'];
            $norm = self::normPath($p);
            if (isset($used[$norm]) || isset($used[$p])) continue;
            // [0] eines mehrfachen Elements nicht zusätzlich zu [*] anbieten
            if (preg_match('~\[0\]~', $p) && isset($paths[preg_replace('~\[0\]~', '[1]', $p, 1)])) continue;
            $type = self::inferType($p, $values($p));
            $label = $sf['label'] !== '' ? $sf['label'] : self::humanize($p);
            $depth = substr_count($p, '.') + substr_count($p, '[');
            $check = !$feed && $on < 12 && $depth <= 1 && !preg_match('~^(id|guid|uuid)$~i', $p);
            if ($check) $on++;
            $fields[] = ['on' => $check, 'label' => $label, 'type' => $type, 'path' => $p, 'sample' => $sf['sample']];
            $used[$norm] = true;
            if (count($fields) >= 40) break;
        }
        // Bezeichnungen eindeutig machen (werden zu Feldnamen)
        $seen = [];
        foreach ($fields as &$f) {
            $base = $f['label'];
            for ($i = 2; isset($seen[Tables::normName($f['label'])]); $i++) $f['label'] = $base . ' ' . $i;
            $seen[Tables::normName($f['label'])] = true;
        }
        unset($f);
        $name = trim(mb_substr(strip_tags($name), 0, 60)) ?: __('Einträge');
        [$handle, $route] = self::freeHandle($name);
        return ['name' => $name, 'handle' => $handle, 'route' => $route, 'fields' => $fields];
    }

    /** Freier Kurzname und freie Adresse für eine neue Tabelle (bei Kollision _2, _3 … bzw. -2, -3 …) */
    public static function freeHandle(string $name): array
    {
        $base = Tables::normName($name);
        if (strlen($base) < 2) $base = 'quelle';
        $base = substr($base, 0, 36);
        $handle = $base;
        for ($n = 2; Tables::find($handle) || \Core\Data\Shared::meta($handle); $n++) $handle = $base . '_' . $n;
        $rbase = str_replace('_', '-', $base);
        $route = $handle === $base ? $rbase : str_replace('_', '-', $handle);
        for ($n = 2; Tables::byRoute($route) || Pages::byPath($route); $n++) $route = $rbase . '-' . $n;
        return [$handle, $route];
    }

    private static function normPath(string $p): string
    {
        return (string) preg_replace('~\[[^\]]*\]~', '', $p);
    }

    /** Pflichtfelder ohne Zuordnung (Speichern würde scheitern) */
    public static function missingRequired(array $table, array $mapping): array
    {
        $out = [];
        foreach ($table['fields'] as $f) {
            if (!$f['required']) continue;
            $r = $mapping['rows'][$f['name']] ?? [];
            if (!self::isSet((array) $r) && !in_array($f['type'], self::UNSUPPORTED, true)) $out[$f['name']] = $f['label'];
        }
        return $out;
    }

    /** Eingaben aus dem Formular bereinigen */
    public static function clean(array $table, array $in): array
    {
        $rows = [];
        $clip = fn($v, int $n = 300) => mb_substr(trim((string) $v), 0, $n);
        foreach ($table['fields'] as $f) {
            $r = (array) ($in['rows'][$f['name']] ?? []);
            $row = ['path' => $clip($r['path'] ?? ''), 'tx' => array_key_exists((string) ($r['tx'] ?? ''), self::transforms()) ? (string) ($r['tx'] ?? '') : '',
                'opt' => $clip($r['opt'] ?? '', 500), 'default' => $clip($r['default'] ?? '', 500), 'lookup' => $clip($r['lookup'] ?? '', 4000)];
            if (in_array($f['type'], ['media', 'file'], true)) $row['alt'] = $clip($r['alt'] ?? '');
            $row = array_filter($row, fn($v) => $v !== '');
            if ($row) $rows[$f['name']] = $row;
        }
        return ['id_path' => $clip($in['id_path'] ?? ''), 'slug_path' => $clip($in['slug_path'] ?? ''), 'rows' => $rows];
    }

    /** Titel eines zugeordneten Eintrags (Protokoll) */
    public static function title(array $table, array $mapped): string
    {
        $t = (string) ($mapped['values'][$table['settings']['title_field']] ?? '');
        return $t !== '' ? mb_strimwidth(strip_tags($t), 0, 80, '…') : $mapped['ext_id'];
    }
}
