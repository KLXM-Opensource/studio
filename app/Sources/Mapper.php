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
            '' => __('automatisch (nach Feldtyp)'),
            'text' => __('Text (HTML entfernen, Leerraum kürzen)'),
            'html' => __('HTML bereinigen (formatierter Text)'),
            'date' => __('Datum lesen'),
            'number' => __('Zahl lesen'),
            'bool' => __('Ja/Nein'),
            'slug' => __('Kurzform (slug)'),
            'template' => __('Vorlage / zusammensetzen'),
            'raw' => __('unverändert'),
        ];
    }

    /** Feldtypen, die sich zuordnen lassen (Verknüpfungen, Gruppen, Wiederholungen nicht) */
    public const UNSUPPORTED = ['relation', 'relations', 'group', 'recurrence', 'iban'];

    /**
     * Einen Eintrag der Quelle zuordnen.
     * @return array{values: array, ext_id: string, slug: string, media: array<string, array{ref: string, alt: string}>, warnings: list<string>}
     */
    public static function map(array $table, array $mapping, array $item): array
    {
        $values = [];
        $media = [];
        $warn = [];
        $rows = (array) ($mapping['rows'] ?? []);
        foreach ($table['fields'] as $f) {
            $rule = $rows[$f['name']] ?? null;
            if (!is_array($rule) || in_array($f['type'], self::UNSUPPORTED, true)) continue;
            if (trim((string) ($rule['path'] ?? '')) === '' && ($rule['tx'] ?? '') !== 'template' && trim((string) ($rule['default'] ?? '')) === '') continue;
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
            [$v, $w] = self::value($f, $rule, $item);
            if ($w !== null) $warn[] = $w;
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
        return ['values' => $values, 'ext_id' => mb_substr($id, 0, 190), 'slug' => $slug !== '' ? Pages::slugify($slug) : '', 'media' => $media, 'warnings' => $warn];
    }

    /** Erster nicht leerer Wert aus „pfad | pfad2“ als Text */
    public static function first(array $item, string $paths): ?string
    {
        foreach (explode('|', $paths) as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $s = Parser::scalar(Parser::get($item, $p));
            if ($s !== null && trim($s) !== '') return $s;
        }
        return null;
    }

    /** @return array{0: mixed, 1: ?string} [Wert, Hinweis] */
    private static function value(array $f, array $rule, array $item): array
    {
        $type = $f['type'];
        $tx = (string) ($rule['tx'] ?? '');
        $opt = (string) ($rule['opt'] ?? '');
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
            return [array_values(array_unique($out)), null];
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
        if ($type === 'url' && is_string($v) && $v !== '' && !preg_match('~^https?://~i', $v)) $v = null;
        return [$v, $warn];
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
        $v = $mapped['values'];
        ksort($v);
        $m = $mapped['media'];
        ksort($m);
        return hash('sha256', json_encode([$v, $m, $mapped['slug']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** Vorschlag: Zuordnung aus den gefundenen Pfaden (gleichnamig bzw. typische Feed-Felder) */
    public static function suggest(array $table, array $paths, string $format): array
    {
        $alias = [
            'titel' => ['title', 'name', 'headline'], 'title' => ['title', 'name'], 'name' => ['name', 'title'],
            'datum' => ['pubDate', 'published', 'updated', 'dc:date', 'date', 'created_at'], 'date' => ['pubDate', 'published', 'date'],
            'teaser' => ['description', 'summary', 'excerpt', 'teaser'], 'text' => ['content:encoded', 'content', 'description', 'body', 'text'],
            'link' => ['link@href', 'link', 'url'], 'url' => ['link@href', 'link', 'url'], 'bild' => ['enclosure@url', 'media:content@url', 'media:thumbnail@url', 'image', 'image.url'],
            'autor' => ['dc:creator', 'author.name', 'author'], 'kategorie' => ['category', 'categories'],
        ];
        $rows = [];
        foreach ($table['fields'] as $f) {
            if (in_array($f['type'], self::UNSUPPORTED, true)) continue;
            $cands = array_merge([$f['name']], $alias[$f['name']] ?? []);
            foreach ($cands as $c) {
                foreach ([$c, $c . '[0]', $c . '#text'] as $p) {
                    if (array_key_exists($p, $paths)) { $rows[$f['name']] = ['path' => $p, 'tx' => '', 'opt' => '']; break 2; }
                }
            }
        }
        $id = '';
        foreach (['guid', 'id', 'guid#text', 'link', 'link@href', 'uuid', 'objektnummer'] as $c) if (array_key_exists($c, $paths)) { $id = $c === 'guid#text' ? 'guid' : $c; break; }
        return ['id_path' => $id, 'slug_path' => '', 'rows' => $rows];
    }

    /** Beispiel-Zuordnung einer Liste von Einträgen (Vorschau) */
    public static function preview(array $table, array $mapping, array $items, int $n = 5): array
    {
        $out = [];
        foreach (array_slice($items, 0, $n) as $item) {
            $m = self::map($table, $mapping, $item);
            $shown = [];
            foreach ($table['fields'] as $f) {
                if (isset($m['media'][$f['name']])) { $shown[$f['name']] = ['label' => $f['label'], 'value' => $m['media'][$f['name']]['ref'], 'media' => true, 'alt' => $m['media'][$f['name']]['alt']]; continue; }
                if (!array_key_exists($f['name'], $m['values'])) continue;
                $v = $m['values'][$f['name']];
                $shown[$f['name']] = ['label' => $f['label'], 'value' => $f['type'] === 'richtext' ? mb_strimwidth(self::plain((string) $v), 0, 220, '…')
                    : (is_array($v) ? implode(', ', array_map(fn($k) => Tables::optionLabel($f, (string) $k), $v)) : ($f['type'] === 'select' ? Tables::optionLabel($f, (string) $v) : (string) $v))];
            }
            $out[] = ['ext_id' => $m['ext_id'], 'fields' => $shown, 'warnings' => $m['warnings'], 'title' => (string) ($m['values'][$table['settings']['title_field']] ?? '')];
        }
        return $out;
    }

    /** Pflichtfelder ohne Zuordnung (Speichern würde scheitern) */
    public static function missingRequired(array $table, array $mapping): array
    {
        $out = [];
        foreach ($table['fields'] as $f) {
            if (!$f['required']) continue;
            $r = $mapping['rows'][$f['name']] ?? [];
            if (trim((string) ($r['path'] ?? '')) === '' && ($r['tx'] ?? '') !== 'template' && trim((string) ($r['default'] ?? '')) === '') $out[] = $f['label'];
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
