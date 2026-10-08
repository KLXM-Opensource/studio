<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Http\Controllers\Admin\DataController;

/**
 * Assistent „Neue Tabelle“ (/admin/data/new): Zweck → Vorlage → Grundeinstellungen → Einsetzen. Hier die Logik ohne HTTP
 * (der Zustand liegt während des Assistenten in der Sitzung, DataController::wizard*): Zustand aus einer Vorlage, Eingaben
 * der Grundeinstellungen übernehmen, daraus die Definition für Tables::validate bauen und anlegen.
 *
 * Zustand: purpose, preset ('' = leer beginnen, '_ai' = Vorschlag von KLXM AI), kind (content|inbox), name, singular, icon,
 * fields (Liste: on, label, type, required, options als Zeilen, übrige Angaben der Vorlage), detail (Detailseiten), route,
 * list (Übersicht auf der Website – Vorschlag unter „Einsetzen“), notify, to (Empfänger der E-Mail), mode (Zustellung eines
 * Eingangs: system | both | mail), receipt (Bestätigung an die Absender), retention (Tage), max (Obergrenze), push, ai (Beschreibung für die KI).
 */
final class Wizard
{
    public const STEPS = ['art', 'vorlage', 'einstellungen', 'einsetzen'];
    /** Feldtypen, die die Grundeinstellungen anbieten (alles Weitere unter „Felder & Einstellungen“) */
    private const CONTENT_TYPES = ['text', 'textarea', 'richtext', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect',
        'email', 'tel', 'url', 'link', 'media', 'file', 'geo', 'recurrence', 'color', 'iban'];

    /** Schrittnamen für die Fortschrittsanzeige */
    public static function stepLabels(): array
    {
        return ['art' => __('Art'), 'vorlage' => __('Vorlage'), 'einstellungen' => __('Grundeinstellungen'), 'einsetzen' => __('Einsetzen')];
    }

    /** Erlaubte Feldtypen je Zweck/Art (Eingang: Formular-Typen, Dateien nur bei Zustellung per E-Mail) */
    public static function types(array $st): array
    {
        if (($st['kind'] ?? 'content') === 'inbox') {
            $t = ['text', 'textarea', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect', 'email', 'tel'];
            if (self::mailMode($st) !== 'system') $t[] = 'file';
            return $t;
        }
        return self::CONTENT_TYPES;
    }

    /**
     * Zustellung eines Eingangs aus dem Zustand: Zweck „mail“ → nur E-Mail; sonst die gewählte (Vorlage bzw. Grundeinstellungen) –
     * „Anfragen“ kennt System und System + E-Mail, Bewerbungen alle drei. Ohne Funktion „Anfragen per E-Mail“ immer System.
     */
    public static function mailMode(array $st): string
    {
        if (($st['purpose'] ?? '') === 'mail') return 'mail';
        if (!Delivery::available()) return 'system';
        $m = (string) ($st['mode'] ?? 'system');
        return in_array($m, self::modes($st), true) ? $m : 'system';
    }

    /** Wählbare Zustellungen im Assistenten (Eingang, nicht „nur E-Mail“) */
    public static function modes(array $st): array
    {
        if (($st['purpose'] ?? '') === 'mail') return ['mail'];
        if (!Delivery::available()) return ['system'];
        return ($st['purpose'] ?? '') === 'registration' ? Delivery::MODES : ['system', 'both'];
    }

    /** Leere Felder je Zweck („Leer beginnen“) */
    private static function emptyFields(string $p): array
    {
        if (in_array($p, ['mail', 'inbox', 'registration'], true)) {
            return [['label' => 'Name', 'type' => 'text', 'required' => 1], ['label' => 'E-Mail', 'type' => 'email', 'required' => 1, 'width' => 'half'],
                ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half'], ['label' => 'Nachricht', 'type' => 'textarea']];
        }
        return [['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1]];
    }

    /** Zustand für Zweck + Vorlage (Schritt 2 → 3); Vorlage '' = leer beginnen */
    public static function start(string $purpose, string $preset): array
    {
        $p = $preset !== '' ? (DataController::presets()[$preset] ?? null) : null;
        $kind = $p ? (($p['kind'] ?? 'content') === 'inbox' ? 'inbox' : 'content') : Purpose::kind($purpose);
        $st = ['purpose' => $purpose, 'preset' => $p ? $preset : '', 'kind' => $kind,
            'name' => $p['name'] ?? '', 'singular' => $p['singular'] ?? '', 'icon' => $p['icon'] ?? (Purpose::all()[$purpose]['icon'] ?? 'table'),
            'route' => (string) ($p['route'] ?? ''), 'detail' => $purpose === 'content' && (string) ($p['route'] ?? '') !== '',
            'list' => in_array($purpose, ['content', 'source'], true), 'notify' => '', 'to' => (string) ($p['delivery']['to'] ?? ''),
            'mode' => (string) ($p['delivery']['mode'] ?? 'system'), 'retention' => (int) ($p['inbox']['retention_days'] ?? Inbox::DEFAULTS['retention_days']),
            'max' => 0, 'push' => false];
        if ($purpose === 'content' && $preset === '') $st['detail'] = true;
        // „Nur E-Mail“: Empfänger aus den Grundeinstellungen vorschlagen (E-Mail-Versand → Empfänger)
        if ($purpose === 'mail' && $st['to'] === '') $st['to'] = (string) setting('sys.mail_to', '');
        $st['fields'] = self::fieldRows($p ? $p['fields'] : self::emptyFields($purpose), $st);
        $st['receipt'] = in_array($purpose, ['mail', 'registration'], true) && self::hasEmail($st);
        return $st;
    }

    /** Felder einer Vorlage/KI → Zeilen des Assistenten (on = übernehmen; Typen, die die Art nicht kennt, werden ersetzt bzw. abgewählt) */
    public static function fieldRows(array $fields, array $st): array
    {
        $types = self::types($st);
        $map = ['richtext' => 'textarea', 'url' => 'text', 'link' => 'text', 'geo' => 'text', 'relation' => 'text', 'relations' => 'text', 'recurrence' => 'text',
            'iban' => 'text', 'color' => 'text'];
        $out = [];
        foreach (array_slice($fields, 0, 40) as $f) {
            if (!is_array($f) || trim((string) ($f['label'] ?? '')) === '') continue;
            $type = (string) ($f['type'] ?? 'text');
            $on = true;
            if (!in_array($type, $types, true)) {
                if (isset($map[$type]) && in_array($map[$type], $types, true)) $type = $map[$type];
                else { $on = false; $type = in_array($type, self::CONTENT_TYPES, true) ? $type : 'text'; }
            }
            $opts = $f['options'] ?? '';
            if (is_array($opts)) $opts = implode("\n", array_map(fn($k, $v) => is_int($k) ? (string) $v : "$k=$v", array_keys($opts), $opts));
            $out[] = ['on' => $on, 'label' => trim(strip_tags((string) $f['label'])), 'type' => $type, 'required' => !empty($f['required']), 'options' => (string) $opts,
                'extra' => array_diff_key($f, array_flip(['label', 'type', 'required', 'options', 'name', 'id', 'on']))];
        }
        return $out;
    }

    private static function hasEmail(array $st): bool
    {
        foreach ($st['fields'] as $f) if ($f['on'] && $f['type'] === 'email') return true;
        return false;
    }

    /**
     * Eingaben der Grundeinstellungen übernehmen: name, singular, f[i][on|label|type|required|options], new_label/new_type,
     * detail, list, notify, to, mode (Zustellung), receipt, retention, max, push. $go: up:N, down:N, add – Reihenfolge und neues Feld.
     */
    public static function apply(array $st, array $in, string $go = ''): array
    {
        $clip = fn($v, int $n) => mb_substr(trim(strip_tags((string) $v)), 0, $n);
        $st['name'] = $clip($in['name'] ?? $st['name'], 60);
        $st['singular'] = $clip($in['singular'] ?? $st['singular'], 60);
        if (isset($in['icon'])) $st['icon'] = \Core\Icons::clean((string) $in['icon']);
        $types = self::types($st);
        $rows = [];
        foreach ($st['fields'] as $i => $f) {
            $x = (array) ($in['f'][$i] ?? []);
            if (!$x) { $rows[] = $f; continue; }
            $label = $clip($x['label'] ?? $f['label'], 60);
            $type = in_array($x['type'] ?? '', $types, true) ? (string) $x['type'] : $f['type'];
            $rows[] = ['on' => !empty($x['on']), 'label' => $label !== '' ? $label : $f['label'], 'type' => $type, 'required' => !empty($x['required']),
                'options' => mb_substr((string) ($x['options'] ?? $f['options']), 0, 2000), 'extra' => $f['extra']];
        }
        $st['fields'] = $rows;
        // Neues Feld (Bezeichnung + Typ) – auch beim „Weiter“, damit nichts verloren geht
        $nl = $clip($in['new_label'] ?? '', 60);
        if ($nl !== '' && count($st['fields']) < 40) {
            $nt = in_array($in['new_type'] ?? '', $types, true) ? (string) $in['new_type'] : 'text';
            $st['fields'][] = ['on' => true, 'label' => $nl, 'type' => $nt, 'required' => false, 'options' => '', 'extra' => []];
        }
        if (preg_match('~^(up|down):(\d+)$~', $go, $m)) {
            $i = (int) $m[2];
            $j = $m[1] === 'up' ? $i - 1 : $i + 1;
            if (isset($st['fields'][$i], $st['fields'][$j])) [$st['fields'][$i], $st['fields'][$j]] = [$st['fields'][$j], $st['fields'][$i]];
        }
        foreach (['detail', 'list', 'receipt', 'push'] as $k) if (array_key_exists($k, $in)) $st[$k] = !empty($in[$k]);
        if (isset($in['mode']) && in_array($in['mode'], self::modes($st), true)) $st['mode'] = (string) $in['mode'];
        if (isset($in['notify'])) $st['notify'] = $clip($in['notify'], 400);
        if (isset($in['to'])) $st['to'] = $clip($in['to'], 600);
        if (isset($in['retention'])) $st['retention'] = max(0, min(3650, (int) $in['retention']));
        if (isset($in['max'])) $st['max'] = max(0, min(DataForms::MAX_ENTRIES, (int) $in['max']));
        if (isset($in['route'])) $st['route'] = trim((string) preg_replace('~[^a-z0-9\-]+~', '-', strtolower((string) $in['route'])), '-');
        return $st;
    }

    /** Prüfungen des Assistenten vor dem Anlegen (zusätzlich zu Tables::validate) – Fehler als [Schlüssel => Text] */
    public static function check(array $st): array
    {
        $errors = [];
        if (trim($st['name']) === '') $errors['name'] = __('Bitte einen Namen angeben (z. B. „Kontakt“ oder „Aktuelles“).');
        if (!array_filter($st['fields'], fn($f) => $f['on'])) $errors['fields'] = __('Bitte mindestens ein Feld übernehmen.');
        if (self::kindOf($st) === 'inbox' && self::mailMode($st) !== 'system') {
            $to = array_filter(array_map('trim', preg_split('~[,;\s]+~', (string) $st['to']) ?: []));
            if (!$to) $errors['to'] = __('Bitte mindestens eine E-Mail-Adresse angeben, an die die Einsendungen gehen.');
        }
        if (!empty($st['receipt']) && !self::hasEmail($st)) $errors['receipt'] = __('Für die Bestätigung an die Absender braucht das Formular ein Feld vom Typ „E-Mail“.');
        return $errors;
    }

    private static function kindOf(array $st): string
    {
        return ($st['kind'] ?? 'content') === 'inbox' ? 'inbox' : 'content';
    }

    /** Freier Kurzname bzw. freie Adresse (Zusatz -2, -3 …), damit der Assistent nicht an Dopplungen scheitert */
    public static function freeHandle(string $name): string
    {
        $base = Tables::normName($name) ?: 'tabelle';
        if (!preg_match('~^[a-z]~', $base)) $base = 't_' . $base;
        $base = substr($base, 0, 36);
        $h = $base;
        for ($n = 2; Tables::find($h) || Shared::meta($h); $n++) $h = $base . '_' . $n;
        return $h;
    }

    public static function freeRoute(string $route): string
    {
        $route = trim((string) preg_replace('~[^a-z0-9\-]+~', '-', strtolower($route)), '-');
        if ($route === '') return '';
        $r = $route;
        for ($n = 2; Tables::byRoute($r) || \Core\PublicPaths::isReserved($r, ['mcp', 'pdf', 'sw.js', 'offline', 'dav', '.well-known', 'formular']); $n++) $r = $route . '-' . $n;
        return $r;
    }

    /** Eingabe für Tables::validate aus dem Zustand (Vorlage als Grundlage, darüber die Grundeinstellungen) */
    public static function def(array $st): array
    {
        $preset = $st['preset'] !== '' && $st['preset'] !== '_ai' && isset(DataController::presets()[$st['preset']]) ? $st['preset'] : '';
        $d = DataController::presetDef($preset);
        $kind = self::kindOf($st);
        $purpose = (string) $st['purpose'];
        $d['name'] = $st['name'];
        $d['singular'] = $st['singular'] !== '' ? $st['singular'] : $st['name'];
        $d['icon'] = $st['icon'] ?: 'table';
        $d['handle'] = self::freeHandle($st['name']);
        $fields = [];
        foreach ($st['fields'] as $f) {
            if (!$f['on']) continue;
            $row = $f['extra'] + ['label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'] ? 1 : 0];
            $row['label'] = $f['label'];
            $row['type'] = $f['type'];
            $row['required'] = $f['required'] ? 1 : 0;
            $row['name'] = Tables::normName($f['label']);
            if (in_array($f['type'], ['select', 'multiselect'], true)) $row['options'] = $f['options'];
            else unset($row['options']);
            if ($kind === 'inbox') unset($row['in_list'], $row['searchable']);
            $fields[] = $row;
        }
        if ($fields && $kind === 'content' && !array_filter($fields, fn($f) => !empty($f['in_list']))) foreach (array_slice(array_keys($fields), 0, 3) as $k) $fields[$k]['in_list'] = 1;
        $d['fields'] = $fields;
        $names = array_column($fields, 'name');
        $s = $d['settings'];
        $s['kind'] = $kind;
        $s['purpose'] = $purpose;
        foreach (['title_field', 'image_field', 'description_field'] as $k) if (($s[$k] ?? '') !== '' && !in_array($s[$k], $names, true)) $s[$k] = '';
        if (!in_array($s['sort_field'] ?? 'sort', [...$names, 'sort', 'created_at', 'published_at'], true)) $s['sort_field'] = 'sort';
        // Kalender der Vorlage nur, wenn das Beginn-Feld übernommen wurde
        if (!empty($s['calendar']['enabled']) && !in_array($s['calendar']['start'] ?? '', $names, true)) $s['calendar'] = [];
        $email = '';
        foreach ($fields as $f) if ($f['type'] === 'email') { $email = $f['name']; break; }
        $receipt = ['enabled' => !empty($st['receipt']) && $email !== '' ? '1' : '0', 'field' => $email];

        if ($kind === 'inbox') {
            $mode = self::mailMode($st);
            $s['route'] = '';
            $s['form'] = ['enabled' => '1', 'notify' => $mode === 'system' ? $st['notify'] : '', 'receipt' => $receipt]
                + array_intersect_key((array) ($s['form'] ?? []), array_flip(['success', 'submit', 'upload_mb'])) + ['success' => '', 'submit' => '', 'upload_mb' => 5];
            $s['inbox'] = ['title' => (string) ($s['inbox']['title'] ?? '') ?: $st['name'], 'intro' => (string) ($s['inbox']['intro'] ?? ''),
                'retention_days' => (int) $st['retention'],
                'delivery' => ['mode' => $mode, 'to' => $mode === 'system' ? '' : $st['to'], 'reply_to' => '1']];
        } else {
            $route = $purpose === 'content' || $purpose === 'source' ? (!empty($st['detail']) ? self::freeRoute($st['route'] !== '' ? $st['route'] : $st['name']) : '') : '';
            $s['route'] = $route;
            $s['workflow'] = '1';
            if ($purpose === 'registration') {
                // Anmeldung: Formular an, Einträge als Entwurf (Teilnehmerliste in der Verwaltung), nicht in Suche/Sitemap
                $s['form'] = ['enabled' => '1', 'status' => 'draft', 'notify' => $st['notify'], 'receipt' => $receipt, 'max' => (int) $st['max']]
                    + array_intersect_key((array) ($s['form'] ?? []), array_flip(['success', 'submit']));
                $s['noindex'] = '1';
                $s['search'] = ['enabled' => '0'];
            } elseif ($purpose === 'internal') {
                $s['form'] = ['enabled' => '0'];
                $s['noindex'] = '1';
                $s['search'] = ['enabled' => '0'];
                $s['calendar'] = [];
            }
            if (!empty($st['push']) && \Core\Push\Push::on() && $route !== '') $s['push'] = ['enabled' => '1'];
        }
        $d['settings'] = $s;
        return $d;
    }

    /**
     * Tabelle anlegen: prüfen (check + Tables::validate), anlegen, Stellen-Bewerbungsformular verknüpfen, Detailseiten-Vorlage.
     * @return array{table: ?array, errors: array}
     */
    public static function create(array $st): array
    {
        $errors = self::check($st);
        if ($errors) return ['table' => null, 'errors' => $errors];
        [$def, $errors] = Tables::validate(self::def($st));
        if ($errors) return ['table' => null, 'errors' => $errors];
        Tables::create($def);
        $t = Tables::find($def['handle']);
        if (Jobs::is($t) && Jobs::config($t)['form'] === '_new') {
            Jobs::linkForm($t, Jobs::ensureForm('bewerbungen')['handle'] ?? '');
            $t = Tables::find($def['handle']);
        }
        if (!Tables::isInbox($t) && $t['settings']['route'] !== '' && !empty($st['detail'])) {
            DataController::makeTemplate($t);
            $t = Tables::find($def['handle']);
        }
        return ['table' => $t, 'errors' => []];
    }

    /** Zweck aus einer Beschreibung erraten (KI-Schritt) – nur Wortstämme, keine KI; nicht verfügbare Zwecke → Inhalte */
    public static function guess(string $text): string
    {
        $x = mb_strtolower($text);
        $p = match (true) {
            (bool) preg_match('~rückruf|kontaktformular|kontakt-formular|per e-?mail (schick|send|zustell)|nur (eine )?e-?mail~u', $x) => 'mail',
            (bool) preg_match('~anmeld|registrier|teilnehm|bewerb|einschreib~u', $x) => 'registration',
            (bool) preg_match('~anfrage|terminwunsch|rezept|bestellung|vertraulich|verschlüssel|gesundheit~u', $x) => 'inbox',
            (bool) preg_match('~intern|nur für (uns|die verwaltung|das team)|inventar|aufgaben|nicht öffentlich~u', $x) => 'internal',
            default => 'content',
        };
        return Purpose::unavailable($p) === null ? $p : 'content';
    }

    /** Zustand aus dem Vorschlag von KLXM AI (AI\Generator::proposeTable → def) */
    public static function fromAi(array $def, string $text): array
    {
        $purpose = self::guess($text);
        $st = self::start($purpose, '');
        $st['preset'] = '_ai';
        $st['ai'] = mb_substr($text, 0, 2000);
        $st['name'] = (string) ($def['name'] ?? '');
        $st['singular'] = (string) ($def['singular'] ?? '');
        $st['icon'] = (string) ($def['icon'] ?? $st['icon']);
        $route = (string) ($def['settings']['route'] ?? '');
        if ($purpose === 'content') { $st['route'] = $route; $st['detail'] = $route !== ''; }
        $rows = self::fieldRows((array) ($def['fields'] ?? []), $st);
        if ($rows) $st['fields'] = $rows;
        $st['receipt'] = in_array($purpose, ['mail', 'registration'], true) && self::hasEmail($st);
        return $st;
    }

    /**
     * Selbsttest (data:selftest): je verfügbarem Zweck eine Tabelle über den Assistenten anlegen und die wichtigsten Einstellungen
     * prüfen (Art, Zweck, Detailseiten, Formular, Zustellung, Suche); danach wieder löschen.
     * @return array{ok: int, fails: string[]}
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $sfx = bin2hex(random_bytes(2));
        $made = [];
        $cases = [
            'content' => ['blog', fn($t) => [$t['settings']['kind'], $t['settings']['route'] !== '', !empty($t['settings']['detail_page_id'])], ['content', true, true]],
            'mail' => ['contact', fn($t) => [$t['settings']['kind'], $t['settings']['inbox']['delivery']['mode'], $t['settings']['form']['enabled'], $t['settings']['route']], ['inbox', 'mail', true, '']],
            'inbox' => ['inbox', fn($t) => [$t['settings']['kind'], $t['settings']['inbox']['delivery']['mode'], $t['settings']['inbox']['retention_days']], ['inbox', 'system', 30]],
            'registration' => ['registration', fn($t) => [$t['settings']['kind'], $t['settings']['form']['enabled'], $t['settings']['form']['status'], $t['settings']['form']['max'],
                $t['settings']['route'], $t['settings']['search']['enabled'] ?? null, $t['settings']['form']['receipt']['enabled']], ['content', true, 'draft', 12, '', false, true]],
            'internal' => ['internal', fn($t) => [$t['settings']['kind'], $t['settings']['route'], $t['settings']['form']['enabled'], $t['settings']['noindex']], ['content', '', false, true]],
        ];
        try {
            foreach ($cases as $purpose => [$preset, $probe, $want]) {
                if (Purpose::unavailable($purpose) !== null) continue;
                if (!isset(DataController::presets()[$preset])) { $fails[] = "Vorlage „{$preset}“ fehlt für {$purpose}"; continue; }
                $eq("Vorlage {$preset} gehört zu {$purpose}", in_array($preset, Purpose::presetKeys($purpose, DataController::presets()), true), true);
                $st = self::start($purpose, $preset);
                $in = ['name' => 'Selbsttest ' . $purpose . ' ' . $sfx, 'retention' => '30', 'max' => '12', 'to' => 'empfang@example.org'];
                $st = self::apply($st, $in);
                // Feld umbenennen, eins abwählen (nicht das erste), eins ergänzen, Reihenfolge tauschen
                $st = self::apply($st, ['f' => [0 => ['on' => '1', 'label' => $st['fields'][0]['label'] . ' X', 'type' => $st['fields'][0]['type'], 'required' => '1']],
                    'new_label' => 'Zusatz ' . $sfx, 'new_type' => 'text'], 'down:0');
                $r = self::create($st);
                $eq("Assistent {$purpose}: ohne Fehler", $r['errors'], []);
                if (!$r['table']) continue;
                $t = $r['table'];
                $made[] = $t['handle'];
                $eq("Assistent {$purpose}: Zweck gespeichert", [Purpose::of($t), empty($t['purpose_derived'])], [$purpose, true]);
                $eq("Assistent {$purpose}: Einstellungen", $probe($t), $want);
                $names = array_column($t['fields'], 'name');
                $eq("Assistent {$purpose}: Feld umbenannt und verschoben", $names[1] ?? null, Tables::normName($st['fields'][1]['label']));
                $eq("Assistent {$purpose}: neues Feld", in_array(Tables::normName('Zusatz ' . $sfx), $names, true), true);
                $eq("Assistent {$purpose}: Block zum Einsetzen", Placement::blockType($t), $purpose === 'content' ? 'data_list' : ($purpose === 'internal' ? null : 'data_form'));
                // Rundlauf: Speichern ohne Änderung behält den Zweck
                [$def, $errs] = Tables::validate(Tables::toInput($t), $t);
                $eq("Assistent {$purpose}: Rundlauf behält Zweck", [$errs, $def['settings']['purpose']], [[], $purpose]);
            }
            // Pflicht: Empfänger bei „nur per E-Mail“
            if (Purpose::unavailable('mail') === null) {
                $st = self::apply(self::start('mail', 'contact'), ['name' => 'X', 'to' => '']);
                $eq('„Nur E-Mail“ ohne Empfänger abgelehnt', isset(self::check($st)['to']), true);
            }
            $eq('Erraten: Rückruf → nur E-Mail', self::guess('Ein Rückruf-Formular für die Praxis'), Purpose::unavailable('mail') === null ? 'mail' : 'content');
            $eq('Erraten: Kursanmeldung', self::guess('Anmeldung zum Yogakurs mit Teilnehmerliste'), Purpose::unavailable('registration') === null ? 'registration' : 'content');
            $eq('Erraten: Inventar → intern', self::guess('Inventar der Geräte, nur intern'), 'internal');
            $eq('Erraten: Blog → Inhalte', self::guess('Neuigkeiten mit Bild und Text'), 'content');
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            foreach ($made as $h) {
                if ($x = Tables::find($h)) Tables::delete($x);   // löscht auch die Detailseiten-Vorlage
            }
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
