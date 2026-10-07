<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Http\Controllers\Admin\DataController;

/**
 * „Felder bearbeiten“ im Seiten-Editor: die Felder eines Formulars (Block „Formular (Datentabelle)“ und Formulare in Kit-Blöcken)
 * direkt auf der Seite ändern – Seitenleiste resources/js/_form_fields.js, Endpunkt Admin\FormFieldsController, Ansicht
 * app/Views/formfields-panel.php. Recht „data.schema“ (geteilte Tabellen: nur die Eigentümer-Website, Shared::canSchema).
 *
 * Die Seitenleiste zeigt nur einen Teil der Definition (Bezeichnung, Kurzname, Typ, Pflicht, halbe Breite, Hilfetext,
 * Auswahlmöglichkeiten, erlaubte Dateitypen/Höchstgröße, Reihenfolge; Formular: an/aus, Felder im Formular, Text nach dem Absenden,
 * Button-Beschriftung, bei Inhaltstabellen Datei-Uploads). input() legt diese Angaben über die gespeicherte Definition
 * (Tables::toInput) – alles Übrige (Bedingungen, Übersetzungen, Gruppen-Unterfelder, Liste/Suche, Zustellung, Verschlüsselung …)
 * bleibt unverändert. Geprüft und gespeichert wird auf demselben Weg wie im Tabellen-Designer (DataController::saveSchema →
 * Tables::validate, Bestätigung beim Löschen von Feldern mit Inhalten).
 */
final class SchemaPanel
{
    /** Feldtypen, die die Seitenleiste anbietet (Formular-Felder; Gruppen nur, wenn schon vorhanden – Unterfelder in der Verwaltung) */
    public static function types(array $t, string $current = ''): array
    {
        $types = Tables::isInbox($t) ? [...DataForms::TYPES, 'file'] : [...DataForms::TYPES, ...DataForms::UPLOAD_TYPES];
        $types = array_values(array_filter($types, fn($x) => $x !== 'group' || $current === 'group'));
        if ($current !== '' && !in_array($current, $types, true)) $types[] = $current;   // bestehender Typ bleibt wählbar
        return array_values(array_filter(array_keys(Tables::TYPES), fn($x) => in_array($x, $types, true)));
    }

    /** Typen der Knöpfe „Feld hinzufügen“ – im Eingang Dateifelder nur bei Zustellung per E-Mail (wie Tables::validate) */
    public static function addTypes(array $t): array
    {
        $out = array_values(array_filter(self::types($t), fn($x) => $x !== 'group'));
        if (Tables::isInbox($t) && !Delivery::mails($t)) $out = array_values(array_diff($out, ['file']));
        return $out;
    }

    /**
     * Eingabe der Seitenleiste ($post: fields[i][id|label|name|type|required|width|help|options|accept|max_mb], form[…]) über die
     * gespeicherte Definition legen → Eingabe für Tables::validate. Felder, die fehlen, werden gelöscht (mit Bestätigung).
     * Neue Felder kommen ins Formular, auch wenn dort eine Auswahl besteht; umbenannte Felder bleiben ausgewählt.
     */
    public static function input(array $t, array $post): array
    {
        $in = Tables::toInput($t);
        $old = [];
        foreach ($in['fields'] as $f) $old[(string) ($f['id'] ?? '')] = $f;
        $fields = [];
        $renamed = [];
        $added = [];
        foreach (array_values((array) ($post['fields'] ?? [])) as $pf) {
            if (!is_array($pf)) continue;
            $id = (string) ($pf['id'] ?? '');
            $f = $id !== '' && isset($old[$id]) ? $old[$id] : ['id' => ''];
            foreach (['label', 'name', 'type', 'help'] as $k) {
                if (array_key_exists($k, $pf)) $f[$k] = (string) $pf[$k];
            }
            $f['required'] = !empty($pf['required']);
            $f['width'] = ($pf['width'] ?? '') === 'half' ? 'half' : '';
            if (array_key_exists('options', $pf)) $f['options'] = (string) $pf['options'];
            if (array_key_exists('accept', $pf)) $f['accept'] = array_map('strval', (array) $pf['accept']);
            if (array_key_exists('max_mb', $pf)) $f['max_mb'] = (int) $pf['max_mb'];
            $name = Tables::normName((string) ($f['name'] ?? '') ?: (string) ($f['label'] ?? ''));
            if ($f['id'] !== '' && $name !== '' && $name !== $old[$f['id']]['name']) $renamed[$old[$f['id']]['name']] = $name;
            if ($f['id'] === '' && $name !== '') $added[] = $name;
            $fields[] = $f;
        }
        $in['fields'] = $fields;
        $pform = (array) ($post['form'] ?? []);
        if ($pform) {
            $s = (array) ($in['settings']['form'] ?? []);
            $s['enabled'] = !empty($pform['enabled']);
            $sel = array_values(array_filter(array_map('strval', (array) ($pform['fields'] ?? [])), fn($v) => $v !== ''));
            $sel = array_map(fn($n) => $renamed[$n] ?? $n, $sel);
            if ($sel) $sel = array_values(array_unique([...$sel, ...$added]));
            $s['fields'] = $sel;
            foreach (['success', 'submit'] as $k) $s[$k] = (string) ($pform[$k] ?? '');
            if (!Tables::isInbox($t)) {
                $s['uploads'] = !empty($pform['uploads']);
                if (isset($pform['upload_mb'])) $s['upload_mb'] = (int) $pform['upload_mb'];
            }
            $in['settings']['form'] = $s;
        }
        return $in;
    }

    /**
     * Prüfen und speichern (gleicher Weg wie der Tabellen-Designer).
     * @return array{ok: bool, errors: array, askDrop: bool, input: array}
     */
    public static function save(array $t, array $post): array
    {
        $in = self::input($t, $post);
        [, $errors, $askDrop] = DataController::saveSchema($t, $in, !empty($post['confirm_drop']));
        return ['ok' => !$errors, 'errors' => $errors, 'askDrop' => $askDrop, 'input' => $in];
    }

    // ================================================================= Selbsttest (php bin/console data:selftest)

    /** Schlüssel verschachtelter Einstellungen sortieren (Reihenfolge ist für Einstellungen ohne Bedeutung) */
    private static function sorted(mixed $v): mixed
    {
        if (!is_array($v)) return $v;
        if (!array_is_list($v)) ksort($v);
        return array_map([self::class, 'sorted'], $v);
    }

    /**
     * Rundlauf und Seitenleiste: validate(toInput($t), $t) ergibt für jede Tabelle dieselbe Definition; vorübergehende Tabellen
     * (Inhalt + Eingang) prüfen Hinzufügen, Reihenfolge, Pflicht, Dateitypen, Bestätigung beim Löschen, Umbenennen in der Auswahl
     * und die Regeln des Eingangs (Typen, Dateien nur per E-Mail), dazu die Medien-Pools geteilter Tabellen (SharedPoolsTest).
     * Die vorübergehenden Tabellen werden danach gelöscht.
     * $readOnly (Konsole --roundtrip): nur der Rundlauf – ändert nichts, auch auf Live-Websites unbedenklich.
     */
    public static function selftest(bool $readOnly = false): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        // Rundlauf aller Tabellen, deren Felder diese Website ändern darf. Erlaubte Abweichungen = was auch der Tabellen-Designer
        // beim Speichern bereinigt: fehlende Einstellungen älterer Tabellen (Standardwert), Übersetzungen in Sprachen, die die
        // Website nicht (mehr) hat, das automatisch ergänzte Titel-Feld.
        $langs = fn(array $a) => array_filter($a, fn($lc) => \Core\Lang::valid((string) $lc), ARRAY_FILTER_USE_KEY);
        foreach (Tables::all() as $t) {
            if (isset($t['shared']) && !Shared::isOwner($t)) continue;
            [$def, $errors] = Tables::validate(Tables::toInput($t), $t);
            $eq("Rundlauf {$t['handle']}: keine Fehler", $errors, []);
            $want = array_map(function (array $f) use ($langs) {
                foreach (['labels', 'options_i18n'] as $k) {
                    if (isset($f[$k])) { $f[$k] = $langs((array) $f[$k]); if (!$f[$k]) unset($f[$k]); }
                }
                // Ältere Definitionen: Hilfetext mit Leerzeichen am Ende (wird beim Speichern getrimmt), Dateifeld ohne Typen/Höchstgröße (Standard)
                if (isset($f['help']) && is_string($f['help'])) $f['help'] = trim($f['help']);
                if (($f['type'] ?? '') === 'file') {
                    if (!array_key_exists('accept', $f)) $f['accept'] = DataForms::FILE_KINDS_DEFAULT;
                    if (!array_key_exists('max_mb', $f)) $f['max_mb'] = 0;
                }
                return $f;
            }, $t['fields']);
            $eq("Rundlauf {$t['handle']}: Felder", $def['fields'], $want);
            $want = $t['settings'];
            if (isset($want['inbox'])) $want['inbox']['delivery'] = (array) ($want['inbox']['delivery'] ?? []) + Delivery::DEFAULTS;   // ältere Eingänge ohne Zustellung
            if (isset($want['search']['labels'])) { $want['search']['labels'] = $langs($want['search']['labels']); if (!$want['search']['labels']) unset($want['search']['labels']); }
            foreach ($def['settings'] as $k => $v) {
                if (!array_key_exists($k, $want) || ($k === 'title_field' && $v === '')) continue;
                if ($k === 'form') { unset($v['known'], $want['form']['known']); }
                $eq("Rundlauf {$t['handle']}: settings.$k", self::sorted($v), self::sorted($want[$k]));
            }
        }

        // Eingangsbestätigung: ausgeschaltet bleibt „kein Feld“ (stabiler Rundlauf), eingeschaltet ohne Feld = erstes E-Mail-Feld
        $mailFields = [['name' => 'name', 'type' => 'text', 'label' => 'Name'], ['name' => 'mail', 'type' => 'email', 'label' => 'E-Mail']];
        $re = [];
        $eq('Eingangsbestätigung aus: kein Feld vorbelegt', DataForms::validateSettings(['receipt' => ['field' => '']], $mailFields, $re, null)['receipt']['field'], '');
        $eq('Eingangsbestätigung an: erstes E-Mail-Feld', DataForms::validateSettings(['receipt' => ['enabled' => '1', 'field' => '']], $mailFields, $re, null)['receipt']['field'], 'mail');
        $eq('Eingangsbestätigung: keine Fehler', $re, []);
        $re = [];
        DataForms::validateSettings(['receipt' => ['enabled' => '1']], [$mailFields[0]], $re, null);
        $eq('Eingangsbestätigung ohne E-Mail-Feld: Fehler', isset($re['settings.form']), true);

        if ($readOnly) return ['ok' => $ok, 'fails' => $fails];     // --roundtrip: nur prüfen, nichts anlegen
        $sfx = bin2hex(random_bytes(3));
        $made = [];
        try {
            // ---------------------------------------------------------- Inhaltstabelle
            $h = 'selftest_felder_' . $sfx;
            [$def, $errors] = Tables::validate(['name' => 'Selbsttest Felder', 'handle' => $h, 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => 1],
                ['label' => 'Thema', 'type' => 'select', 'options' => "a=Alpha\nb=Beta", 'visible_if' => ['rules' => [['field' => 'name', 'op' => 'filled']]]],
                ['label' => 'Notiz', 'type' => 'textarea', 'in_list' => 1, 'searchable' => 1, 'labels' => ['en' => 'Note']],
            ], 'settings' => ['form' => ['enabled' => 1, 'fields' => ['name', 'notiz']]]]);
            $eq('Inhaltstabelle angelegt', $errors, []);
            Tables::create($def);
            $made[] = $h;
            $t = Tables::find($h);
            $ids = array_column($t['fields'], 'id', 'name');
            $row = fn(string $n, array $x = []) => $x + ['id' => $ids[$n], 'label' => Tables::field($t, $n)['label'], 'name' => $n, 'type' => Tables::field($t, $n)['type']];
            // Reihenfolge, Pflicht, Hilfetext, neues Dateifeld, Umbenennen (bleibt im Formular)
            $r = self::save($t, ['fields' => [
                $row('notiz', ['name' => 'nachricht', 'required' => '1', 'help' => 'Kurz bitte']),
                $row('name', ['required' => '1', 'width' => 'half']),
                $row('thema', ['options' => "a=Alpha\nb=Beta"]),
                ['id' => '', 'label' => 'Lebenslauf', 'name' => '', 'type' => 'file', 'accept' => ['', 'pdf'], 'max_mb' => '3'],
            ], 'form' => ['enabled' => '1', 'fields' => ['', 'name', 'notiz'], 'success' => 'Danke!', 'submit' => 'Senden', 'uploads' => '1', 'upload_mb' => '5']]);
            $eq('Speichern ohne Fehler', $r['errors'], []);
            $t = Tables::find($h);
            $eq('Reihenfolge', array_column($t['fields'], 'name'), ['nachricht', 'name', 'thema', 'lebenslauf']);
            $eq('Umbenannt, gleiche ID', Tables::field($t, 'nachricht')['id'] ?? null, $ids['notiz']);
            $eq('Pflicht gesetzt', Tables::field($t, 'nachricht')['required'], true);
            $eq('Hilfetext', Tables::field($t, 'nachricht')['help'], 'Kurz bitte');
            $eq('Übrige Angaben bleiben (Liste, Suche, Übersetzung)', [Tables::field($t, 'nachricht')['in_list'], Tables::field($t, 'nachricht')['searchable'], Tables::field($t, 'nachricht')['labels'] ?? null], [true, true, \Core\Lang::valid('en') ? ['en' => 'Note'] : null]);
            $eq('Bedingung bleibt', Tables::field($t, 'thema')['visible_if']['rules'][0]['field'] ?? null, 'name');
            $eq('Halbe Breite', Tables::field($t, 'name')['width'], 'half');
            $eq('Dateitypen', Tables::field($t, 'lebenslauf')['accept'] ?? null, ['pdf']);
            $eq('Höchstgröße', Tables::field($t, 'lebenslauf')['max_mb'] ?? null, 3);
            $eq('Formular: umbenanntes und neues Feld ausgewählt', $t['settings']['form']['fields'], ['nachricht', 'name', 'lebenslauf']);
            $eq('Formular: Texte', [$t['settings']['form']['success'], $t['settings']['form']['submit'], $t['settings']['form']['uploads']], ['Danke!', 'Senden', true]);
            // Kein Dateityp gewählt → Fehler; unbekannter Typ wird Text; Word nur im Eingang
            $ids = array_column($t['fields'], 'id', 'name');
            $keep = fn(array $x = []) => array_map(fn($f) => ['id' => $f['id'], 'label' => $f['label'], 'name' => $f['name'], 'type' => $f['type'],
                'required' => $f['required'] ? '1' : '', 'options' => is_array($f['options'] ?? null) ? Tables::fieldInput($f)['options'] : null] + ($x[$f['name']] ?? []), $t['fields']);
            $r = self::save($t, ['fields' => $keep(['lebenslauf' => ['accept' => ['', 'docx']]])]);
            $eq('Dateifeld ohne erlaubten Typ (DOCX nur im Eingang) abgelehnt', isset($r['errors']['fields.3']), true);
            // Feld löschen → erst mit Bestätigung
            $fs = array_values(array_filter($keep(), fn($f) => $f['name'] !== 'thema'));
            $r = self::save($t, ['fields' => $fs]);
            $eq('Löschen verlangt Bestätigung', [$r['askDrop'], isset($r['errors']['_drop'])], [true, true]);
            $eq('Ohne Bestätigung unverändert', count(Tables::find($h)['fields']), 4);
            $r = self::save($t, ['fields' => $fs, 'confirm_drop' => '1']);
            $eq('Bestätigtes Löschen', [$r['errors'], array_column(Tables::find($h)['fields'], 'name')], [[], ['nachricht', 'name', 'lebenslauf']]);

            // ---------------------------------------------------------- Eingang (nur mit Funktion „requests“)
            if (Inbox::available()) {
                $hi = 'selftest_eingang_' . $sfx;
                [$def, $errors] = Tables::validate(['name' => 'Selbsttest Eingang', 'handle' => $hi, 'fields' => [
                    ['label' => 'Name', 'type' => 'text', 'required' => 1],
                    ['label' => 'Anliegen', 'type' => 'textarea'],
                ], 'settings' => ['kind' => 'inbox', 'form' => ['enabled' => 1], 'inbox' => ['title' => 'Selbsttest', 'delivery' => ['mode' => 'system']]]]);
                $eq('Eingang angelegt', $errors, []);
                Tables::create($def);
                $made[] = $hi;
                $ti = Tables::find($hi);
                $ib = fn(array $extra) => array_merge(array_map(fn($f) => ['id' => $f['id'], 'label' => $f['label'], 'name' => $f['name'], 'type' => $f['type'], 'required' => $f['required'] ? '1' : ''], $ti['fields']), $extra);
                $eq('Eingang: kein Bild-Typ in der Seitenleiste', in_array('media', self::types($ti), true), false);
                $eq('Eingang: kein Dateiknopf ohne Zustellung per E-Mail', in_array('file', self::addTypes($ti), true), false);
                $r = self::save($ti, ['fields' => $ib([['id' => '', 'label' => 'Foto', 'type' => 'media']])]);
                $eq('Eingang: Bildfeld abgelehnt', isset($r['errors']['fields.2']), true);
                $r = self::save($ti, ['fields' => $ib([['id' => '', 'label' => 'Text', 'type' => 'richtext']])]);
                $eq('Eingang: formatierter Text abgelehnt', isset($r['errors']['fields.2']), true);
                $r = self::save($ti, ['fields' => $ib([['id' => '', 'label' => 'Befund', 'type' => 'file', 'accept' => ['pdf']]])]);
                $eq('Eingang: Dateifeld ohne Zustellung per E-Mail abgelehnt', isset($r['errors']['fields']), true);
                $r = self::save($ti, ['fields' => $ib([['id' => '', 'label' => 'Telefon', 'type' => 'tel']]), 'form' => ['enabled' => '1', 'fields' => [''], 'success' => 'Danke', 'submit' => '']]);
                $eq('Eingang: Feld ergänzt', $r['errors'], []);
                $ti2 = Tables::find($hi);
                $eq('Eingang bleibt Eingang', [Tables::isInbox($ti2), $ti2['settings']['form']['status'], $ti2['settings']['inbox']['title'], $ti2['settings']['inbox']['delivery']['mode']], [true, 'draft', 'Selbsttest', 'system']);
                $eq('Eingang: neues Feld im Formular', DataForms::selected($ti2, Tables::field($ti2, 'telefon')), true);
                // Löschen im Eingang braucht keine Bestätigung (Werte stecken im verschlüsselten payload)
                $r = self::save($ti2, ['fields' => array_slice(array_map(fn($f) => ['id' => $f['id'], 'label' => $f['label'], 'name' => $f['name'], 'type' => $f['type']], $ti2['fields']), 0, 2)]);
                $eq('Eingang: Löschen ohne Rückfrage', [$r['askDrop'], $r['errors']], [false, []]);
            }
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            foreach ($made as $h) if ($x = Tables::find($h)) Tables::delete($x);
        }
        // Medien-Pools geteilter Tabellen: nur bei Bedarf anlegen, leere automatische aufräumen
        $sp = SharedPoolsTest::run();
        $ok += $sp['ok'];
        array_push($fails, ...$sp['fails']);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
