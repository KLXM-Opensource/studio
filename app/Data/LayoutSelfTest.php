<?php
declare(strict_types=1);

namespace Core\Data;

/**
 * Selbsttest der Gestaltungs-Elemente im Formular (Feldtypen „Abschnitt“ und „Freitext“, Tables::LAYOUT) – Teil von
 * `php bin/console data:selftest`. Legt eine vorübergehende Tabelle an und löscht sie danach wieder.
 *
 * Geprüft: keine Spalte, nur Datenfelder in $t['fields'] (Liste, Suche, Schnittstelle, Eingabeprüfung), Reihenfolge in allFields(),
 * bereinigter Freitext, eindeutige Kurznamen, Rundlauf validate(toInput()), Hinzufügen/Entfernen ohne Rückfrage, Typwechsel Feld →
 * Abschnitt fragt nach, Formular mit <fieldset>/<legend> und Freitext, Absenden ignoriert die Gestaltung, Eingabemaske der Redaktion.
 */
final class LayoutSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = 'Gestaltung – ' . $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $h = 'selftest_layout_' . bin2hex(random_bytes(3));
        try {
            [$def, $errors] = Tables::validate(['name' => 'Selbsttest Abschnitte', 'handle' => $h, 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => 1],
                ['label' => 'Kontakt', 'type' => 'section', 'style' => 'fieldset', 'help' => 'So erreichen wir Sie.', 'required' => 1, 'in_list' => 1],
                ['label' => 'E-Mail', 'type' => 'email'],
                ['label' => 'Hinweis', 'type' => 'content', 'text' => '<p>Bitte <b>vollständig</b> ausfüllen.<script>alert(1)</script></p><img src="x.png">'],
                ['label' => 'Ihre Nachricht', 'name' => 'abschnitt_nachricht', 'type' => 'section'],
                ['label' => 'Nachricht', 'type' => 'textarea', 'in_list' => 1, 'searchable' => 1],
            ], 'settings' => ['form' => ['enabled' => 1, 'fields' => ['name', 'e_mail', 'nachricht']]]]);
            $eq('Tabelle angelegt', $errors, []);
            $dup = Tables::validate(['name' => 'X', 'handle' => $h . 'x', 'fields' => [['label' => 'Name', 'type' => 'text'], ['label' => 'Name', 'type' => 'section']]])[1];
            $eq('Kurznamen eindeutig (auch mit Abschnitten)', isset($dup['fields.1']), true);
            $empty = Tables::validate(['name' => 'X', 'handle' => $h . 'y', 'fields' => [['label' => 'Name', 'type' => 'text'], ['label' => 'Leer', 'type' => 'content', 'text' => '<p> </p>']]])[1];
            $eq('Leerer Freitext abgelehnt', isset($empty['fields.1']), true);
            Tables::create($def);
            $t = Tables::find($h);

            // Keine Spalte, nur Datenfelder
            $cols = array_map(fn($c) => $c->getName(), Dbal::conn()->createSchemaManager()->listTableColumns($t['table']));
            $eq('Keine Spalte für Abschnitt/Freitext', array_values(array_intersect(['kontakt', 'hinweis', 'abschnitt_nachricht'], $cols)), []);
            $eq('Spalten der Datenfelder', array_values(array_intersect(['name', 'e_mail', 'nachricht'], $cols)), ['name', 'e_mail', 'nachricht']);
            $eq('fields = nur Datenfelder', array_column($t['fields'], 'name'), ['name', 'e_mail', 'nachricht']);
            $eq('allFields = Reihenfolge samt Gestaltung', array_column(Tables::allFields($t), 'name'), ['name', 'kontakt', 'e_mail', 'hinweis', 'abschnitt_nachricht', 'nachricht']);
            $sec = Tables::allFields($t)[1];
            $eq('Abschnitt: Darstellung, Beschreibung, keine Pflicht/Liste', [$sec['style'], $sec['help'], $sec['required'], $sec['in_list']], ['fieldset', 'So erreichen wir Sie.', false, false]);
            $eq('Abschnitt ohne Darstellung = Zwischenüberschrift', Tables::allFields($t)[4]['style'], 'heading');
            $text = (string) Tables::allFields($t)[3]['text'];
            $eq('Freitext bereinigt (Formatierung bleibt, kein Skript, kein Bild)', [str_contains($text, '<b>vollständig</b>'), str_contains($text, '<script'), str_contains($text, '<img')], [true, false, false]);
            $eq('Eingabeprüfung ohne Gestaltung', array_column(Entries::schema($t), 'name'), ['name', 'e_mail', 'nachricht']);
            $eq('Formular-Auswahl nur mit Datenfeldern', $t['settings']['form']['fields'], ['name', 'e_mail', 'nachricht']);
            $eq('Schnittstelle (Schema) ohne Gestaltung', array_column(array_values(array_filter((new \Core\Api\CmsService(['user_id' => null, 'name' => 'Selbsttest']))->dataTables(),
                fn($x) => $x['handle'] === $h))[0]['fields'] ?? [], 'name'), ['name', 'e_mail', 'nachricht']);
            $eq('Suche ohne Gestaltung', array_values(array_intersect(array_keys((array) (\Core\Search\TableSearch::config($t)['fields'] ?? [])), ['kontakt', 'hinweis', 'abschnitt_nachricht'])), []);

            // Rundlauf und Speichern: Gestaltung hinzufügen/entfernen ohne Rückfrage, Feld → Abschnitt fragt nach
            [$def2, $errors] = Tables::validate(Tables::toInput($t), $t);
            $eq('Rundlauf ohne Fehler', $errors, []);
            $eq('Rundlauf: Felder unverändert', $def2['fields'], Tables::allFields($t));
            $in = Tables::toInput($t);
            $in['fields'] = array_values(array_filter($in['fields'], fn($f) => $f['name'] !== 'hinweis'));
            [$def3] = Tables::validate($in, $t);
            $eq('Freitext entfernen: keine Rückfrage', Tables::droppedFields($t, $def3), []);
            $in = Tables::toInput($t);
            foreach ($in['fields'] as &$f) if ($f['name'] === 'nachricht') $f['type'] = 'section';
            unset($f);
            [$def4] = Tables::validate($in, $t);
            $eq('Feld wird Abschnitt: Rückfrage (Inhalte gehen verloren)', array_column(Tables::droppedFields($t, $def4), 'name'), ['nachricht']);

            // Öffentliches Formular
            $html = DataForms::render($t, ['uid' => 'st']);
            $eq('Formular: Gruppe mit Rahmen als fieldset/legend', str_contains($html, '<fieldset class="dff-sec dff-sec--fieldset" id="st-kontakt" aria-describedby="st-kontakt-d"><legend class="dff-sec__title">Kontakt</legend>'), true);
            $eq('Formular: Beschreibung des Abschnitts', str_contains($html, '<p class="dff-sec__desc" id="st-kontakt-d">So erreichen wir Sie.</p>'), true);
            $eq('Formular: Zwischenüberschrift', str_contains($html, '<fieldset class="dff-sec dff-sec--heading" id="st-abschnitt_nachricht"><legend class="dff-sec__title">Ihre Nachricht</legend>'), true);
            $eq('Formular: Freitext bereinigt', [str_contains($html, '<div class="dff-text prose"><p>Bitte <b>vollständig</b>'), str_contains($html, 'alert(1)')], [true, false]);
            $eq('Formular: Reihenfolge', preg_match('~name="name".*st-kontakt.*name="e_mail".*dff-text.*st-abschnitt_nachricht.*name="nachricht"~s', $html), 1);
            $eq('Formular: Gestaltung ohne Eingabefeld', preg_match('~name="(kontakt|hinweis|abschnitt_nachricht)"~', $html), 0);
            $eq('Formular: Felder vor dem ersten Abschnitt ohne fieldset', str_starts_with(substr($html, strpos($html, '<div class="dff-grid">')), '<div class="dff-grid"><div class="dff-f dff-f--req" data-cf="name">'), true);
            $eq('Leere Abschnitte entfallen', array_map(fn($g) => $g[0]['name'] ?? null, DataForms::sections([['type' => 'text', 'name' => 'a'], ['type' => 'section', 'name' => 's1'], ['type' => 'section', 'name' => 's2'], ['type' => 'content', 'name' => 'c']])), [null, 's2']);
            $eq('Datenfelder des Formulars', array_column(DataForms::fields($t), 'name'), ['name', 'e_mail', 'nachricht']);

            // Absenden: Gestaltung wird ignoriert (Prüfung vor dem Spamschutz), Speichern ohne Spalten der Gestaltung
            if (DataForms::enabled($t)) {                                  // Funktion „forms.data“ an
                $res = DataForms::submit($t, ['name' => '', 'kontakt' => 'x', 'hinweis' => 'y', 'abschnitt_nachricht' => 'z'], [], '127.0.0.1');
                $eq('Absenden: Fehler nur an Datenfeldern', array_values(array_intersect(array_keys((array) ($res['errors'] ?? [])), ['kontakt', 'hinweis', 'abschnitt_nachricht'])), []);
                $eq('Absenden: Pflichtfeld gemeldet', isset($res['errors']['name']), true);
            }
            [$id, $serr] = Entries::save($t, null, ['name' => 'Erika Musterfrau', 'e_mail' => 'erika@example.org', 'nachricht' => 'Hallo', 'kontakt' => 'x', 'hinweis' => 'y', 'status' => 'draft']);
            $eq('Speichern ohne Fehler', $serr, []);
            $row = Entries::find($t, (int) $id) ?? [];
            $eq('Eintrag ohne Werte der Gestaltung', [array_key_exists('kontakt', $row), array_key_exists('hinweis', $row), $row['name'] ?? null], [false, false, 'Erika Musterfrau']);

            // Eingabemaske der Redaktion: Abschnitte als Zwischenüberschrift, Freitext als Hinweis
            $fs = Entries::formSchema($t);
            $eq('Redaktion: Gliederung', array_map(fn($f) => $f['type'] === 'heading' ? 'h:' . $f['label'] : $f['name'], $fs), ['name', 'h:Kontakt', 'e_mail', 'h:', 'h:Ihre Nachricht', 'nachricht']);
            $form = \Core\Fields::renderForm($fs, [], [], 'f');
            $eq('Redaktion: Überschrift und Hinweis', [str_contains($form, '<h3 class="f-heading">Kontakt</h3>'), str_contains($form, 'f-richnote"><p>Bitte <b>vollständig</b>')], [true, true]);
        } catch (\Throwable $e) {
            $fails[] = 'Gestaltung – Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            if ($x = Tables::find($h)) Tables::delete($x);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
