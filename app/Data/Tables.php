<?php
declare(strict_types=1);

namespace Core\Data;

use Core\PageCache;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * Datentabellen (ähnlich REDAXO YForm / Webflow CMS Collections) – ohne Programmieren anlegbar.
 *
 * Definition in `data_tables` (Felder als JSON), Einträge in echten Tabellen `data_{handle}`:
 *   id, slug, status (draft|published), sort, created_at, updated_at, published_at + eine Spalte je Feld.
 * Das Schema wird mit Doctrine DBAL abgeglichen (neue Felder → Spalte, umbenannt → RENAME, gelöscht → DROP).
 */
final class Tables
{
    /** Feldtypen der Datentabellen: Bezeichnung, DBAL-Spaltentyp, Symbol */
    public const TYPES = [
        'text' => ['Text (einzeilig)', 'string', 'text-t'],
        'textarea' => ['Text (mehrzeilig)', 'text', 'paragraph'],
        'richtext' => ['Formatierter Text', 'text', 'text-b'],
        'number' => ['Zahl', 'float', 'hash'],
        'bool' => ['Ja / Nein', 'boolean', 'toggle-left'],
        'date' => ['Datum', 'string10', 'calendar-blank'],
        'datetime' => ['Datum & Uhrzeit', 'string20', 'calendar-dots'],
        'time' => ['Uhrzeit', 'string10', 'clock'],
        'select' => ['Auswahl', 'string', 'caret-down'],
        'multiselect' => ['Mehrfachauswahl', 'text', 'list-checks'],
        'media' => ['Bild', 'integer', 'image'],
        'file' => ['Datei', 'integer', 'paperclip'],
        'link' => ['Link', 'string', 'link'],
        'email' => ['E-Mail', 'string', 'at'],
        'tel' => ['Telefon', 'string', 'phone'],
        'url' => ['Webadresse', 'string', 'globe'],
        'color' => ['Farbe', 'string10', 'palette'],
        'geo' => ['Ort (Karte)', 'string', 'map-pin'],
        'relation' => ['Verknüpfung (ein Eintrag)', 'integer', 'tree-structure'],
        'relations' => ['Verknüpfung (mehrere)', 'pivot', 'share-network'],
        'recurrence' => ['Wiederholung', 'text', 'repeat'],
        'iban' => ['IBAN (Bankverbindung)', 'string', 'bank'],
        // Wiederholbare Gruppe: Liste gleichartiger Einträge (z. B. mehrere Medikamente) – JSON-Array von Objekten
        'group' => ['Wiederholbare Gruppe', 'text', 'copy'],
    ];

    /** Erlaubte Typen der Unterfelder einer wiederholbaren Gruppe */
    public const GROUP_TYPES = ['text', 'textarea', 'number', 'select', 'date', 'email', 'tel', 'bool', 'iban'];

    public const SYSTEM = ['id', 'slug', 'status', 'sort', 'created_at', 'updated_at', 'published_at', 'lang', 'translation_group'];

    private static array $cache = [];
    /** Geteilte Tabellen dieser Website (je Anfrage zwischengespeichert) */
    private static ?array $shared = null;

    public static function flush(): void
    {
        self::$cache = [];
        self::$shared = null;
        Shared::flush();
    }

    /** Alle Tabellen: eigene (data_tables) und geteilte, an denen die Website teilnimmt (Core\Data\Shared) */
    public static function all(): array
    {
        return array_merge(array_map([self::class, 'hydrate'], app()->db->fetchAll('SELECT * FROM data_tables ORDER BY sort, name')), self::shared());
    }

    /** Geteilte Tabellen, an denen diese Website teilnimmt */
    public static function shared(): array
    {
        if (self::$shared !== null) return self::$shared;
        $out = [];
        foreach (array_keys(Shared::forSite()) as $key) {
            if ($t = self::sharedTable($key)) $out[] = $t;
        }
        return self::$shared = $out;
    }

    /** Geteilte Tabelle mit den Angaben dieser Website (Detailseite) – unabhängig davon, ob sie teilnimmt */
    public static function sharedTable(string $key): ?array
    {
        $meta = Shared::meta($key);
        if (!$meta) return null;
        try {
            $row = Shared::row($key);
        } catch (\Throwable $e) {
            error_log('[shared] ' . $key . ': ' . $e->getMessage());
            return null;
        }
        if (!$row) return null;
        $t = self::hydrate($row);
        $t['shared'] = ['key' => $key, 'owner' => (string) $meta['owner'], 'members' => (array) $meta['members'],
            'members_see_members' => (bool) $meta['members_see_members'], 'row_id' => (int) $row['id'], 'label' => (string) $meta['label']];
        $t['id'] = Shared::tableId($key);
        $t['settings']['detail_page_id'] = Shared::localConfig($key)['detail_page_id'];
        return $t;
    }

    public static function isShared(array $table): bool
    {
        return isset($table['shared']);
    }

    /** Datenbank der Einträge: Website oder – bei geteilten Tabellen – storage/shared/{key}/share.sqlite */
    public static function db(array $table): \Core\Database
    {
        return isset($table['shared']) ? Shared::db($table['shared']['key']) : app()->db;
    }

    /** Doctrine-Verbindung für den Schema-Abgleich der Tabelle */
    public static function conn(array $table): \Doctrine\DBAL\Connection
    {
        return isset($table['shared']) ? Shared::conn($table['shared']['key']) : Dbal::conn();
    }

    /** Eingangs-Tabelle (settings.kind = inbox, verschlüsselte Anfragen – siehe Core\Data\Inbox)? */
    public static function isInbox(array $table): bool
    {
        return ($table['settings']['kind'] ?? 'content') === 'inbox';
    }

    /** Inhaltstabellen (ohne Eingangs-Tabellen) – für alles, was Inhalte ausgibt: Blöcke, Sitemap, Suche, Kalender, DAV, Feldbindung */
    public static function content(): array
    {
        return array_values(array_filter(self::all(), fn($t) => !self::isInbox($t)));
    }

    /** Wie find(), aber nie eine Eingangs-Tabelle */
    public static function findContent(int|string $ref): ?array
    {
        $t = self::find($ref);
        return $t && !self::isInbox($t) ? $t : null;
    }

    public static function find(int|string $ref): ?array
    {
        $key = (string) $ref;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $byId = is_int($ref) || ctype_digit($key);
        if ($byId && (int) $ref >= Shared::ID_BASE) {
            foreach (self::shared() as $t) if ($t['id'] === (int) $ref) return self::$cache[$key] = $t;
            return null;
        }
        $row = $byId
            ? app()->db->fetch('SELECT * FROM data_tables WHERE id = ?', [(int) $ref])
            : app()->db->fetch('SELECT * FROM data_tables WHERE handle = ?', [$key]);
        if (!$row && !$byId) {
            foreach (self::shared() as $t) if ($t['handle'] === $key) return self::$cache[$key] = $t;
        }
        return $row ? self::$cache[$key] = self::hydrate($row) : null;
    }

    /** Tabelle anhand der URL-Basis (für Detailseiten-Routing) */
    public static function byRoute(string $route): ?array
    {
        foreach (self::content() as $t) {
            if ($t['settings']['route'] !== '' && $t['settings']['route'] === $route) {
                return $t;
            }
        }
        return null;
    }

    private static function hydrate(array $row): array
    {
        $row['fields'] = json_decode((string) $row['fields_json'], true) ?: [];
        $row['settings'] = (json_decode((string) $row['settings_json'], true) ?: []) + [
            'route' => '', 'title_field' => '', 'image_field' => '', 'description_field' => '',
            'sort_field' => 'sort', 'sort_dir' => 'asc', 'detail_page_id' => null, 'workflow' => true, 'per_page' => 50, 'kind' => 'content',
        ];
        if ($row['settings']['kind'] === 'inbox') {
            $row['settings']['inbox'] = (array) ($row['settings']['inbox'] ?? []) + Inbox::DEFAULTS;
        }
        $row['settings']['calendar'] = (array) ($row['settings']['calendar'] ?? []) + Calendar::DEFAULTS;
        $row['settings']['form'] = (array) ($row['settings']['form'] ?? []) + DataForms::DEFAULTS;
        if ($row['settings']['title_field'] === '' || !self::field($row, $row['settings']['title_field'])) {
            foreach ($row['fields'] as $f) {
                if (in_array($f['type'], ['text', 'textarea'], true)) { $row['settings']['title_field'] = $f['name']; break; }
            }
        }
        $row['table'] = 'data_' . $row['handle'];
        return $row;
    }

    /** Bild-Feld der Tabelle: eingestellt oder – bei „automatisch“ – das erste Bildfeld */
    public static function imageField(array $table): string
    {
        $f = (string) ($table['settings']['image_field'] ?? '');
        if ($f !== '') return $f;
        foreach ($table['fields'] as $fd) {
            if (($fd['type'] ?? '') === 'media') return (string) $fd['name'];
        }
        return '';
    }

    /** Beschriftung eines Feldes in der Sprache der Seite */
    public static function label(array $f, ?string $lang = null): string
    {
        $lang ??= \Core\Lang::current();
        return (string) ($f['labels'][$lang] ?? $f['label'] ?? $f['name'] ?? '');
    }

    /** Text einer Auswahlmöglichkeit in der Sprache der Seite */
    public static function optionLabel(array $f, string $key, ?string $lang = null): string
    {
        $lang ??= \Core\Lang::current();
        return (string) ($f['options_i18n'][$lang][$key] ?? $f['options'][$key] ?? $key);
    }

    public static function field(array $table, string $name): ?array
    {
        foreach ($table['fields'] as $f) {
            if ($f['name'] === $name) return $f;
        }
        return null;
    }

    // ================================================================= Anlegen / Ändern

    public static function normName(string $s): string
    {
        $s = strtr(mb_strtolower(trim($s)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = trim((string) preg_replace('~[^a-z0-9]+~', '_', $s), '_');
        if ($s !== '' && !preg_match('~^[a-z]~', $s)) $s = 'f_' . $s;
        return substr($s, 0, 40);
    }

    /**
     * Definition prüfen und bereinigen.
     * @return array{0: array, 1: array} [def, errors]
     */
    public static function validate(array $in, ?array $existing = null): array
    {
        $errors = [];
        // Geteilte Tabelle (bestehend oder neu anzulegen): Eigentümer-Website – Verknüpfungen nur zu geteilten Tabellen dieser Website
        $sharedOwner = $existing['shared']['owner'] ?? (isset($in['_shared_owner']) ? (string) $in['_shared_owner'] : null);
        $name = trim(strip_tags((string) ($in['name'] ?? '')));
        $handle = $existing['handle'] ?? self::normName((string) ($in['handle'] ?? '') ?: $name);
        if ($name === '') $errors['name'] = 'Bitte einen Namen angeben (z. B. „Aktuelles“ oder „Produkte“).';
        if (!$existing) {
            if (!preg_match('~^[a-z][a-z0-9_]{1,40}$~', $handle)) $errors['handle'] = 'Kurzname: nur a–z, 0–9 und _ (mind. 2 Zeichen).';
            elseif (self::find($handle) || ($sharedOwner !== null && Shared::meta($handle))) $errors['handle'] = 'Diesen Kurznamen gibt es schon.';
        }
        // Art der Tabelle: beim Anlegen wählbar; später nur, solange die Tabelle leer ist
        $s = (array) ($in['settings'] ?? []);
        $kind = ($s['kind'] ?? ($existing['settings']['kind'] ?? 'content')) === 'inbox' ? 'inbox' : 'content';
        if ($sharedOwner !== null && $kind === 'inbox') {
            $errors['settings.kind'] = __('Geteilte Tabellen können keine Eingangs-Tabellen sein.');
            $kind = 'content';
        }
        if ($existing && $kind !== ($existing['settings']['kind'] ?? 'content')
            && (int) self::db($existing)->fetchValue("SELECT COUNT(*) FROM {$existing['table']}") > 0) {
            $errors['settings.kind'] = 'Die Art der Tabelle lässt sich nur ändern, solange sie keine Einträge hat.';
            $kind = $existing['settings']['kind'] ?? 'content';
        }
        $inbox = $kind === 'inbox';
        $fields = [];
        $seen = [];
        $rawFields = [];
        foreach ((array) ($in['fields'] ?? []) as $i => $f) {
            $label = trim(strip_tags((string) ($f['label'] ?? '')));
            if ($label === '' && trim((string) ($f['name'] ?? '')) === '') continue;
            $fname = self::normName((string) ($f['name'] ?? '') ?: $label);
            $type = (string) ($f['type'] ?? 'text');
            if (!isset(self::TYPES[$type])) $type = 'text';
            if ($inbox && !in_array($type, [...DataForms::TYPES, 'file'], true)) {
                $errors["fields.$i"] = "Feld „{$label}“: Der Feldtyp „" . self::TYPES[$type][0] . '“ ist in Eingangs-Tabellen nicht möglich (keine Bilder, Verknüpfungen, Karten oder formatierten Texte; Dateien nur bei Zustellung per E-Mail).';
                continue;
            }
            if ($fname === '' || in_array($fname, array_merge(self::SYSTEM, $inbox ? Inbox::META : [], $sharedOwner !== null ? Shared::COLUMNS : []), true)) {
                $errors["fields.$i"] = "Feld „{$label}“: Der Name „{$fname}“ ist reserviert.";
                continue;
            }
            if (isset($seen[$fname])) {
                $errors["fields.$i"] = "Feld „{$label}“: Der Name „{$fname}“ kommt doppelt vor.";
                continue;
            }
            $seen[$fname] = true;
            $def = [
                'id' => preg_match('~^[a-z0-9]{6,16}$~', (string) ($f['id'] ?? '')) ? $f['id'] : bin2hex(random_bytes(4)),
                'name' => $fname, 'label' => $label ?: $fname, 'type' => $type,
                'required' => !empty($f['required']), 'in_list' => !empty($f['in_list']), 'searchable' => !empty($f['searchable']),
                'help' => mb_substr(trim(strip_tags((string) ($f['help'] ?? ''))), 0, 200),
                'width' => ($f['width'] ?? '') === 'half' ? 'half' : '',
            ];
            if (in_array($type, ['select', 'multiselect'], true)) {
                $opts = [];
                foreach (preg_split('~\R~', (string) ($f['options'] ?? '')) as $line) {
                    $line = trim(strip_tags($line));
                    if ($line === '') continue;
                    [$k, $v] = str_contains($line, '=') ? array_map('trim', explode('=', $line, 2)) : [self::normName($line) ?: $line, $line];
                    $opts[$k] = $v;
                }
                if (!$opts) $errors["fields.$i"] = "Feld „{$label}“: Bitte Auswahlmöglichkeiten angeben (eine pro Zeile).";
                $def['options'] = $opts;
            }
            // Übersetzungen der Beschriftung und der Auswahltexte (Frontend in weiteren Sprachen)
            $labels = [];
            foreach ((array) ($f['labels'] ?? []) as $lc => $lv) {
                $lv = trim(strip_tags((string) $lv));
                if ($lv !== '' && \Core\Lang::valid((string) $lc)) $labels[$lc] = $lv;
            }
            if ($labels) $def['labels'] = $labels;
            if (in_array($type, ['select', 'multiselect'], true)) {
                foreach ((array) ($f['options_i18n'] ?? []) as $lc => $txt) {
                    if (!\Core\Lang::valid((string) $lc)) continue;
                    foreach (preg_split('~\R~', is_array($txt) ? implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($txt), $txt)) : (string) $txt) as $line) {
                        if (!str_contains($line, '=')) continue;
                        [$k, $v] = array_map(fn($x) => trim(strip_tags($x)), explode('=', $line, 2));
                        if (isset($def['options'][$k]) && $v !== '') $def['options_i18n'][$lc][$k] = $v;
                    }
                }
            }
            if (in_array($type, ['relation', 'relations'], true)) {
                $target = (string) ($f['target'] ?? '');
                if (!self::findContent($target) && $target !== $handle) $errors["fields.$i"] = "Feld „{$label}“: Bitte die verknüpfte Tabelle wählen (Eingangs-Tabellen sind nicht verknüpfbar).";
                elseif ($sharedOwner !== null && $target !== $handle && ((self::findContent($target)['shared']['owner'] ?? null) !== $sharedOwner)) {
                    $errors["fields.$i"] = __('Feld „{label}“: Geteilte Tabellen können nur mit geteilten Tabellen derselben Website verknüpft werden.', ['label' => $label]);
                }
                $def['target'] = $target;
            }
            // IBAN: in Listen und Ausgaben maskieren (Standard: an)
            if ($type === 'iban') $def['mask'] = !array_key_exists('mask', $f) || !empty($f['mask']);
            // Wiederholbare Gruppe: Unterfelder, Anzahl, Beschriftungen
            if ($type === 'group') {
                $def = self::groupDef($f, $def);
                if (!empty($def['_error'])) $errors["fields.$i"] = "Feld „{$label}“: " . $def['_error'];
                unset($def['_error']);
            }
            $fields[] = $def;
            $rawFields[$fname] = [$f, $i];
        }
        if (!$fields && !$errors) $errors['fields'] = 'Bitte mindestens ein Feld anlegen.';
        // Bedingungen (anzeigen wenn, Pflicht wenn, Vergleich) – erst jetzt, weil sie auf spätere Felder verweisen dürfen
        $types = array_column($fields, 'type', 'name');
        foreach ($fields as $k => $def) {
            [$raw, $i] = $rawFields[$def['name']];
            $fields[$k] = Rules::normalize((array) $raw, $def, $types, $errors, "fields.$i");
        }

        $route = trim((string) preg_replace('~[^a-z0-9\-/]+~', '-', strtolower(trim((string) ($s['route'] ?? ''), '/ '))), '-/');
        $reserved = ['admin', 'api', 'mcp', 'anfrage', 'assets', 'media', 'kits', 'themes', 'pdf', 'sw.js', 'offline', 'dav', '.well-known', 'formular'];
        if ($route !== '' && in_array(explode('/', $route)[0], $reserved, true)) $errors['settings.route'] = 'Diese Adresse ist reserviert.';
        if ($route !== '' && ($other = self::byRoute($route)) && (!$existing || (int) $other['id'] !== (int) $existing['id'])) {
            $errors['settings.route'] = 'Diese Adresse nutzt schon „' . $other['name'] . '“.';
        }
        $names = array_column($fields, 'name');
        $pick = fn($k) => in_array((string) ($s[$k] ?? ''), $names, true) ? (string) $s[$k] : '';
        // Eingang: Dateifelder nur bei Zustellung per E-Mail (Core\Data\Delivery) – dann nimmt das Formular Dateien an
        $inboxFiles = $inbox && array_filter($fields, fn($f) => $f['type'] === 'file');
        if ($inbox) {
            $dmode = (string) ($s['inbox']['delivery']['mode'] ?? ($existing['settings']['inbox']['delivery']['mode'] ?? 'system'));
            if ($inboxFiles && $dmode === 'system') {
                $errors['fields'] = __('Dateifelder gibt es in Eingangs-Tabellen nur mit Zustellung per E-Mail (Einstellung „Zustellung der Anfragen“) – die Datei geht als Anhang hinaus und wird nie in der Mediathek gespeichert.');
            }
            if (isset($s['form']) && is_array($s['form'])) $s['form']['uploads'] = $inboxFiles && $dmode !== 'system';
        }
        $settings = [
            'route' => $route,
            'title_field' => $pick('title_field'),
            'image_field' => $pick('image_field'),
            // Bilder in der Eintragsliste der Verwaltung: small (neben dem Titel), large, none
            'list_image' => in_array($s['list_image'] ?? '', ['small', 'large', 'none'], true) ? $s['list_image'] : 'small',
            // schema.org-Typ der Detailseiten (StructuredData::TYPES); leer = automatisch
            'schema_type' => array_key_exists((string) ($s['schema_type'] ?? ''), \Core\StructuredData::TYPES) ? (string) ($s['schema_type'] ?? '') : '',
            'description_field' => $pick('description_field'),
            'sort_field' => in_array((string) ($s['sort_field'] ?? ''), array_merge($names, ['sort', 'created_at', 'published_at']), true) ? (string) $s['sort_field'] : 'sort',
            'sort_dir' => ($s['sort_dir'] ?? '') === 'desc' ? 'desc' : 'asc',
            'detail_page_id' => ctype_digit((string) ($s['detail_page_id'] ?? '')) ? (int) $s['detail_page_id'] : ($existing['settings']['detail_page_id'] ?? null),
            'workflow' => !isset($s['workflow']) || !empty($s['workflow']),
            'per_page' => max(5, min(200, (int) ($s['per_page'] ?? 50))),
            // Kalender: Feldzuordnung (Beginn, Ende, ganztägig, Wiederholung, Ort …) – siehe Core\Data\Calendar
            'calendar' => Calendar::validateSettings((array) ($s['calendar'] ?? []), $fields, $errors, $existing['settings']['calendar'] ?? null),
            // Öffentliches Formular (Besucher legen Einträge an) – siehe Core\Data\DataForms
            'form' => DataForms::validateSettings((array) ($s['form'] ?? []), $fields, $errors, $existing['settings']['form'] ?? null),
        ];
        $settings['kind'] = $kind;
        // Website-Suche je Tabelle (Core\Search\TableSearch) – ohne Formularabschnitt bleibt die bisherige Einstellung
        $settings['search'] = \Core\Search\TableSearch::validate((array) ($s['search'] ?? ($existing['settings']['search'] ?? [])), $fields, $settings);
        if ($inbox) {
            // Eingang: keine Detailseiten, keine strukturierten Daten, kein Kalender, keine Uploads; Einträge nur über das Formular
            $settings = array_merge($settings, ['route' => '', 'image_field' => '', 'description_field' => '', 'schema_type' => '', 'detail_page_id' => null,
                'workflow' => false, 'sort_field' => 'created_at', 'sort_dir' => 'desc', 'list_image' => 'none', 'calendar' => Calendar::DEFAULTS]);
            $settings['inbox'] = Inbox::validateSettings((array) ($s['inbox'] ?? []), $existing['settings']['inbox'] ?? null);
            // Zustellung der Anfragen (System / System + E-Mail / nur E-Mail) – braucht die Felder (Weiterleitung nach Auswahlfeld)
            $settings['inbox']['delivery'] = Delivery::validateSettings((array) ($s['inbox']['delivery'] ?? []), $fields, $errors,
                $existing['settings']['inbox']['delivery'] ?? null, $existing);
            $settings['form']['uploads'] = $inboxFiles && $settings['inbox']['delivery']['mode'] !== 'system';
            $settings['form']['status'] = 'draft';
            unset($errors['settings.route'], $errors['settings.calendar']);
        }
        return [[
            'handle' => $handle, 'name' => $name, 'singular' => trim(strip_tags((string) ($in['singular'] ?? ''))) ?: $name,
            // Symbolname (Core\Icons, Phosphor duotone); alte Zeichen (◷ ✎ ▦ …) werden abgebildet, Unbekanntes → „table“
            'icon' => \Core\Icons::clean((string) ($in['icon'] ?? '')),
            'description' => mb_substr(trim(strip_tags((string) ($in['description'] ?? ''))), 0, 500),
            'fields' => $fields, 'settings' => $settings,
        ], $errors];
    }

    /**
     * Wiederholbare Gruppe bereinigen: fields (Unterfelder: name, label, type, required, width, options, labels, options_i18n),
     * min (0–20, Standard 1 bei Pflicht, sonst 0), max (1–50, Standard 10), item_label, add_label. Fehler in $def['_error'].
     */
    public static function groupDef(array $f, array $def): array
    {
        $subs = [];
        $err = '';
        $rows = is_array($f['fields'] ?? null) ? $f['fields'] : (json_decode((string) ($f['fields'] ?? ''), true) ?: []);
        foreach (array_values((array) $rows) as $sf) {
            if (!is_array($sf)) continue;
            $sl = trim(strip_tags((string) ($sf['label'] ?? '')));
            if ($sl === '' && trim((string) ($sf['name'] ?? '')) === '') continue;
            $sn = self::normName((string) ($sf['name'] ?? '') ?: $sl);
            $st = in_array($sf['type'] ?? 'text', self::GROUP_TYPES, true) ? (string) ($sf['type'] ?? 'text') : 'text';
            if ($sn === '' || isset($subs[$sn])) { $err = "Unterfeld „{$sl}“: Der Name „{$sn}“ ist leer oder doppelt."; continue; }
            $sd = ['name' => $sn, 'label' => $sl ?: $sn, 'type' => $st, 'required' => !empty($sf['required']), 'width' => ($sf['width'] ?? '') === 'half' ? 'half' : ''];
            if ($st === 'select') {
                $opts = [];
                $src = $sf['options'] ?? '';
                $lines = is_array($src) ? array_map(fn($k, $v) => is_int($k) ? (string) $v : "$k=$v", array_keys($src), $src) : preg_split('~\R~', (string) $src);
                foreach ($lines as $line) {
                    $line = trim(strip_tags((string) $line));
                    if ($line === '') continue;
                    [$k, $v] = str_contains($line, '=') ? array_map('trim', explode('=', $line, 2)) : [self::normName($line) ?: $line, $line];
                    $opts[$k] = $v;
                }
                if (!$opts) $err = "Unterfeld „{$sl}“: Bitte Auswahlmöglichkeiten angeben (eine pro Zeile).";
                $sd['options'] = $opts;
                foreach ((array) ($sf['options_i18n'] ?? []) as $lc => $txt) {
                    if (!\Core\Lang::valid((string) $lc)) continue;
                    if (!is_array($txt)) {
                        $pairs = [];
                        foreach (preg_split('~\R~', (string) $txt) as $line) {
                            if (str_contains($line, '=')) { [$k, $v] = explode('=', $line, 2); $pairs[trim($k)] = $v; }
                        }
                        $txt = $pairs;
                    }
                    foreach ($txt as $k => $v) {
                        if (isset($opts[$k]) && trim((string) $v) !== '') $sd['options_i18n'][$lc][$k] = trim(strip_tags((string) $v));
                    }
                }
            }
            foreach ((array) ($sf['labels'] ?? []) as $lc => $lv) {
                $lv = trim(strip_tags((string) $lv));
                if ($lv !== '' && \Core\Lang::valid((string) $lc)) $sd['labels'][$lc] = $lv;
            }
            $subs[$sn] = $sd;
            if (count($subs) >= 12) break;
        }
        if (!$subs) $err = 'Bitte mindestens ein Unterfeld anlegen.';
        $max = max(1, min(50, (int) (($f['max'] ?? '') !== '' ? $f['max'] : 10)));
        $min = max(0, min(20, (int) (($f['min'] ?? '') !== '' ? $f['min'] : ($def['required'] ? 1 : 0))));
        if ($def['required'] && $min < 1) $min = 1;
        if ($min > $max) $err = "Mindestanzahl ({$min}) ist größer als die Höchstanzahl ({$max}).";
        $clip = fn($v) => mb_substr(trim(strip_tags((string) $v)), 0, 60);
        return $def + ['fields' => array_values($subs), 'min' => $min, 'max' => $max,
            'item_label' => $clip($f['item_label'] ?? '') ?: 'Eintrag', 'add_label' => $clip($f['add_label'] ?? ''), '_error' => $err];
    }

    /** Neue Tabelle anlegen (Definition + echte Datenbanktabelle) */
    public static function create(array $def): int
    {
        $id = app()->db->insert('data_tables', [
            'handle' => $def['handle'], 'name' => $def['name'], 'singular' => $def['singular'], 'icon' => $def['icon'],
            'description' => $def['description'], 'fields_json' => json_encode($def['fields'], JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($def['settings'], JSON_UNESCAPED_UNICODE),
            'sort' => (int) app()->db->fetchValue('SELECT COUNT(*) FROM data_tables') * 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        self::$cache = [];
        self::sync(self::find($id));
        return $id;
    }

    /** Definition ändern; umbenannte Felder (gleiche Feld-ID) werden als RENAME COLUMN übernommen */
    public static function update(array $table, array $def): void
    {
        $oldById = array_column($table['fields'], 'name', 'id');
        $renames = [];
        foreach ($def['fields'] as $f) {
            if (isset($oldById[$f['id']]) && $oldById[$f['id']] !== $f['name']) {
                $renames[$oldById[$f['id']]] = $f['name'];
            }
        }
        $settings = $def['settings'];
        // Geteilt: Detailseite ist Sache jeder Website (Shared::localConfig), nicht des gemeinsamen Registers
        if (isset($table['shared'])) $settings['detail_page_id'] = null;
        self::db($table)->update('data_tables', [
            'name' => $def['name'], 'singular' => $def['singular'], 'icon' => $def['icon'], 'description' => $def['description'],
            'fields_json' => json_encode($def['fields'], JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
        ], 'id = :id', ['id' => (int) ($table['shared']['row_id'] ?? $table['id'])]);
        self::flush();
        if (($def['settings']['kind'] ?? 'content') !== ($table['settings']['kind'] ?? 'content')) {
            // Art geändert (nur bei leerer Tabelle erlaubt, siehe validate): Datenbanktabelle neu aufbauen
            $sm = self::conn($table)->createSchemaManager();
            if ($sm->tablesExist([$table['table']])) $sm->dropTable($table['table']);
            $renames = [];
        }
        self::sync(self::find((int) $table['id']), $renames);
        PageCache::clear();
        if (isset($table['shared'])) Shared::clearCaches($table['shared']['key']);
    }

    /** Welche Spalten fielen beim Speichern weg? (Warnung vor Datenverlust) */
    public static function droppedFields(array $table, array $def): array
    {
        if (self::isInbox($table)) return [];             // Eingang: Werte stecken im verschlüsselten payload und bleiben erhalten
        $keep = array_column($def['fields'], 'id');
        return array_values(array_filter($table['fields'], fn($f) => !in_array($f['id'], $keep, true)));
    }

    public static function delete(array $table): void
    {
        if (isset($table['shared'])) {
            throw new \RuntimeException(__('Geteilte Tabellen werden nicht hier gelöscht – die Freigabe beendet die Eigentümer-Website (Kommandozeile data:unshare).'));
        }
        $sm = Dbal::conn()->createSchemaManager();
        foreach ($table['fields'] as $f) {
            if ($f['type'] === 'relations' && $sm->tablesExist([self::pivot($table, $f['name'])])) {
                $sm->dropTable(self::pivot($table, $f['name']));
            }
        }
        if ($sm->tablesExist([$table['table']])) {
            $sm->dropTable($table['table']);
        }
        app()->db->query('DELETE FROM data_tables WHERE id = ?', [(int) $table['id']]);
        if (!empty($table['settings']['detail_page_id'])) {
            app()->db->query("DELETE FROM pages WHERE id = ? AND type = 'template'", [(int) $table['settings']['detail_page_id']]);
        }
        self::$cache = [];
        PageCache::clear();
    }

    /** Datenbanktabelle an die Definition anpassen */
    public static function sync(array $table, array $renames = []): void
    {
        if (self::isInbox($table)) {
            Inbox::sync($table);                          // Eingang: feste Metadaten-Spalten + verschlüsselter payload, keine Feldspalten
            return;
        }
        $conn = self::conn($table);
        $sm = $conn->createSchemaManager();
        $name = $table['table'];
        if ($renames && $sm->tablesExist([$name])) {
            $have = array_map(fn($c) => $c->getName(), $sm->listTableColumns($name));
            foreach ($renames as $from => $to) {
                if (in_array($from, $have, true) && !in_array($to, $have, true)) {
                    $conn->executeStatement(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $name, $conn->quoteSingleIdentifier($from), $conn->quoteSingleIdentifier($to)));
                }
            }
        }
        $t = new Table($name);
        $t->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $t->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $t->addColumn('slug', 'string', ['length' => 191, 'notnull' => false]);
        $t->addColumn('status', 'string', ['length' => 20, 'default' => 'published']);
        $t->addColumn('sort', 'integer', ['default' => 0]);
        $t->addColumn('created_at', 'string', ['length' => 25, 'notnull' => false]);
        $t->addColumn('updated_at', 'string', ['length' => 25, 'notnull' => false]);
        $t->addColumn('published_at', 'string', ['length' => 25, 'notnull' => false]);
        // Mehrsprachigkeit: Sprache (NULL = Standardsprache) und Übersetzungsgruppe
        $t->addColumn('lang', 'string', ['length' => 10, 'notnull' => false]);
        $t->addColumn('translation_group', 'integer', ['notnull' => false]);
        // Geteilte Tabelle: Herkunft (Website-Schlüssel) und „dem Eigentümer vorschlagen“
        if (isset($table['shared'])) {
            $t->addColumn('origin_site', 'string', ['length' => 40, 'notnull' => false]);
            $t->addColumn('suggest', 'integer', ['default' => 0]);
        }
        foreach ($table['fields'] as $f) {
            $kind = self::TYPES[$f['type']][1] ?? 'string';
            if ($kind === 'pivot') continue;              // eigene Verknüpfungstabelle, keine Spalte
            match ($kind) {
                'string' => $t->addColumn($f['name'], 'string', ['length' => 255, 'notnull' => false]),
                'string10' => $t->addColumn($f['name'], 'string', ['length' => 10, 'notnull' => false]),
                'string20' => $t->addColumn($f['name'], 'string', ['length' => 20, 'notnull' => false]),
                'text' => $t->addColumn($f['name'], 'text', ['notnull' => false]),
                'float' => $t->addColumn($f['name'], 'float', ['notnull' => false]),
                'boolean' => $t->addColumn($f['name'], 'boolean', ['notnull' => false]),
                'integer' => $t->addColumn($f['name'], 'integer', ['notnull' => false]),
            };
        }
        $t->addIndex(['slug'], $name . '_slug');
        $t->addIndex(['status', 'sort'], $name . '_status');
        if (isset($table['shared'])) $t->addIndex(['origin_site'], $name . '_origin');
        foreach ($table['fields'] as $f) {
            if ($f['type'] === 'relation') $t->addIndex([$f['name']], $name . '_' . $f['name']);
        }
        $exists = $sm->tablesExist([$name]);
        // Verknüpfungstabellen (n:m) anlegen, umbenennen, alte JSON-Werte übernehmen
        $keep = [];
        foreach ($table['fields'] as $f) {
            if ($f['type'] !== 'relations') continue;
            $pivot = self::pivot($table, $f['name']);
            $keep[] = $pivot;
            $old = array_search($f['name'], $renames, true);
            if ($old !== false && $sm->tablesExist([$name . '__' . $old]) && !$sm->tablesExist([$pivot])) {
                $sm->renameTable($name . '__' . $old, $pivot);
            }
            if (!$sm->tablesExist([$pivot])) {
                $p = new Table($pivot);
                $p->addColumn('entry_id', 'integer');
                $p->addColumn('target_id', 'integer');
                $p->addColumn('sort', 'integer', ['default' => 0]);
                $p->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entry_id', 'target_id')->create());
                $p->addIndex(['target_id'], $pivot . '_target');
                $sm->createTable($p);
                // Früher als JSON-Spalte gespeichert? → übernehmen
                if ($exists && in_array($f['name'], array_map(fn($c) => $c->getName(), $sm->listTableColumns($name)), true)) {
                    foreach ($conn->fetchAllAssociative("SELECT id, {$f['name']} AS v FROM $name") as $row) {
                        foreach (array_values(array_unique(array_map('intval', json_decode((string) $row['v'], true) ?: []))) as $i => $tid) {
                            $conn->insert($pivot, ['entry_id' => (int) $row['id'], 'target_id' => $tid, 'sort' => $i]);
                        }
                    }
                }
            }
        }
        foreach ($sm->introspectTableNames() as $tn) {
            $n = method_exists($tn, 'getUnqualifiedName') ? $tn->getUnqualifiedName()->getValue() : (string) $tn;
            if (str_starts_with($n, $name . '__') && !in_array($n, $keep, true)) {
                $sm->dropTable($n);
            }
        }
        if (!$exists) {
            $sm->createTable($t);
            return;
        }
        $diff = $sm->createComparator()->compareTables($sm->introspectTable($name), $t);
        if (!$diff->isEmpty()) {
            $sm->alterTable($diff);
        }
    }

    /** Detailseiten-Vorlage festlegen (geteilte Tabellen: nur für diese Website) */
    public static function setDetailPage(array $table, ?int $pageId): void
    {
        if (isset($table['shared'])) {
            Shared::saveLocal($table['shared']['key'], ['detail_page_id' => $pageId]);
            self::flush();
            if ($t = self::find($table['handle'])) Shared::touch($t);
            return;
        }
        $settings = $table['settings'];
        $settings['detail_page_id'] = $pageId;
        app()->db->update('data_tables', ['settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => (int) $table['id']]);
        self::flush();
    }

    /** Name der n:m-Verknüpfungstabelle eines Feldes */
    public static function pivot(array $table, string $field): string
    {
        return $table['table'] . '__' . $field;
    }

    /** Felder anderer Tabellen, die auf diese Tabelle verweisen: [[table, field], …] */
    public static function referencing(array $target): array
    {
        $out = [];
        foreach (self::content() as $t) {
            foreach ($t['fields'] as $f) {
                if (in_array($f['type'], ['relation', 'relations'], true) && ($f['target'] ?? '') === $target['handle']) {
                    $out[] = [$t, $f];
                }
            }
        }
        return $out;
    }
}
