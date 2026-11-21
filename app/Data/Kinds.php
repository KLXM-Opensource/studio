<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

/**
 * Tabellenarten mit festem Grundschema (settings.kind). Neben content und inbox gibt es Arten wie contacts (Adressbuch): eine
 * normale Inhaltstabelle – Route, Detailseiten, Revisionen, Workflow, geteilte Tabellen, Suche, API und Blöcke laufen wie immer –
 * deren Grundfelder (System-Felder) feste Namen und Typen haben. Der Tabellen-Designer lässt sie stehen (nicht löschbar, Typ fest,
 * Beschriftung änderbar); eigene Zusatzfelder kommen wie gewohnt dazu.
 *
 * Das Grundschema ist versioniert (settings.kind_version) und wächst nur additiv: eine neue Version ergänzt Felder, entfernt oder
 * ändert nie welche – so passen Tabellen verschiedener Websites (Verband, Vereine) ohne Feldzuordnung zusammen. Tabellen ohne
 * besondere Art (content) verhalten sich unverändert.
 *
 * Kontakte (contacts): Freigabe je Kontakt (Feld oeffentlich = „Auf der Website zeigen“, Standard aus) und je Angabe
 * (Mehrfachfelder Core\Combo::MULTI) – ausgewertet in Core\Data\Exposure. Bestehende Tabellen wandelt data:convert um
 * (Core\Data\Convert, mit Probelauf und Zurückdrehen).
 */
final class Kinds
{
    public const CONTACTS = 'contacts';
    /** Aktuelle Version des Grundschemas je Art */
    public const VERSIONS = [self::CONTACTS => 1];
    /** Feld „Auf der Website zeigen“ (Freigabe des ganzen Kontakts) */
    public const PUBLIC_FIELD = 'oeffentlich';

    /** content | inbox | contacts – Unbekanntes wird content */
    public static function normalize(string $kind): string
    {
        return $kind === 'inbox' || isset(self::VERSIONS[$kind]) ? $kind : 'content';
    }

    /** Art mit Grundschema (nicht content/inbox)? */
    public static function isSpecial(string $kind): bool
    {
        return isset(self::VERSIONS[$kind]);
    }

    public static function of(array $t): string
    {
        return self::normalize((string) ($t['settings']['kind'] ?? 'content'));
    }

    public static function isContacts(array $t): bool
    {
        return self::of($t) === self::CONTACTS;
    }

    public static function version(string $kind): int
    {
        return self::VERSIONS[$kind] ?? 0;
    }

    /** Bezeichnung der Art (Verwaltung) */
    public static function label(string $kind): string
    {
        return match ($kind) {
            self::CONTACTS => __('Adressbuch'),
            'inbox' => __('Eingang'),
            default => __('Inhalte'),
        };
    }

    /**
     * Grundschema einer Art bis zur Version $version (Standard: aktuelle) als Eingabe für Tables::validate – nur Datenfelder.
     * Jede Version hängt Felder an (nur additiv). 'since' = Version, mit der das Feld dazukam.
     * @return list<array<string, mixed>>
     */
    public static function schema(string $kind, ?int $version = null): array
    {
        $version ??= self::version($kind);
        $all = match ($kind) {
            self::CONTACTS => [
                ['name' => self::PUBLIC_FIELD, 'label' => 'Auf der Website zeigen', 'type' => 'bool', 'in_list' => 1, 'since' => 1,
                    'help' => 'Nur dann erscheint der Kontakt auf der Website – mit den Angaben, die auf „Öffentlich“ stehen. Neue Kontakte sind zuerst intern.'],
                ['name' => 'name', 'label' => 'Anzeigename', 'type' => 'text', 'in_list' => 1, 'searchable' => 1, 'since' => 1,
                    'help' => 'Leer lassen: wird aus Titel, Vor- und Nachname gebildet (bei Organisationen aus dem Namen der Organisation).'],
                ['name' => 'praefix', 'label' => 'Titel', 'type' => 'text', 'width' => 'half', 'since' => 1, 'help' => 'z. B. Dr.'],
                ['name' => 'vorname', 'label' => 'Vorname', 'type' => 'text', 'width' => 'half', 'since' => 1],
                ['name' => 'nachname', 'label' => 'Nachname', 'type' => 'text', 'width' => 'half', 'since' => 1],
                ['name' => 'ist_firma', 'label' => 'Ist eine Organisation', 'type' => 'bool', 'width' => 'half', 'since' => 1],
                ['name' => 'position', 'label' => 'Funktion', 'type' => 'text', 'in_list' => 1, 'width' => 'half', 'since' => 1, 'help' => 'z. B. 1. Vorsitzende'],
                ['name' => 'firma', 'label' => 'Organisation', 'type' => 'text', 'width' => 'half', 'since' => 1],
                ['name' => 'abteilung', 'label' => 'Abteilung', 'type' => 'text', 'width' => 'half', 'since' => 1],
                ['name' => 'foto', 'label' => 'Foto', 'type' => 'media', 'width' => 'half', 'since' => 1],
                ['name' => 'telefon', 'label' => 'Telefon', 'type' => 'phones', 'since' => 1],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'emails', 'since' => 1],
                ['name' => 'adresse', 'label' => 'Adresse', 'type' => 'addresses', 'since' => 1],
                ['name' => 'web', 'label' => 'Web', 'type' => 'urls', 'since' => 1],
                ['name' => 'geburtstag', 'label' => 'Geburtstag', 'type' => 'date', 'width' => 'half', 'private' => 1, 'since' => 1],
                ['name' => 'listen', 'label' => 'Listen', 'type' => 'multiselect', 'in_list' => 1, 'options' => '', 'since' => 1,
                    'help' => 'Gruppen wie „Vorstand“ oder „Presse“ – in Adressbuch-Apps als Gruppen bzw. Kategorien.'],
                ['name' => 'notiz', 'label' => 'Notiz', 'type' => 'textarea', 'private' => 1, 'since' => 1],
            ],
            default => [],
        };
        return array_values(array_filter($all, fn($f) => (int) $f['since'] <= $version));
    }

    /** Namen der System-Felder einer Art */
    public static function systemNames(string $kind, ?int $version = null): array
    {
        return array_column(self::schema($kind, $version), 'name');
    }

    public static function isSystem(string $kind, string $name): bool
    {
        return self::isSpecial($kind) && in_array($name, self::systemNames($kind), true);
    }

    /** Auswahlfeld, das ohne Optionen gespeichert werden darf (Listen einer Kontakte-Tabelle) */
    public static function allowsEmptyOptions(string $kind, string $name): bool
    {
        return $kind === self::CONTACTS && $name === 'listen';
    }

    /**
     * Eingabe des Designers (Tables::validate) an das Grundschema anpassen: fehlende System-Felder anhängen, Typ eines vorhandenen
     * System-Feldes festhalten (mit Hinweis). Beschriftung, Hilfe, Breite, „In der Liste“ und Optionen der Listen bleiben änderbar.
     */
    public static function enforceInput(string $kind, array $fields, array &$errors): array
    {
        $schema = array_column(self::schema($kind), null, 'name');
        $seen = [];
        foreach ($fields as $i => $f) {
            if (!is_array($f)) continue;
            $n = Tables::normName((string) (($f['name'] ?? '') ?: ($f['label'] ?? '')));
            if (!isset($schema[$n]) || isset($seen[$n])) continue;
            $seen[$n] = true;
            if (($f['type'] ?? '') !== $schema[$n]['type']) {
                if (($f['type'] ?? '') !== '' && empty($f['legacy'])) {
                    $errors["fields.$i"] = __('Feld „{label}“ gehört zum Grundschema dieser Tabellenart – der Typ bleibt „{type}“.',
                        ['label' => (string) ($f['label'] ?? $n), 'type' => __(Tables::TYPES[$schema[$n]['type']][0])]);
                }
                $fields[$i]['type'] = $schema[$n]['type'];
            }
            $fields[$i]['name'] = $n;
            unset($fields[$i]['legacy']);
        }
        foreach ($schema as $n => $def) {
            if (isset($seen[$n])) continue;
            unset($def['since']);
            $fields[] = $def;
        }
        return $fields;
    }

    /**
     * Vollständige Vorlage für „Neue Tabelle → Adressbuch“: Grundschema mit Abschnitten wie in Adressbuch-Apps
     * (Name & Organisation, Kontakt, Weitere Angaben). Eigene Felder ergänzt die Redaktion danach.
     */
    public static function presetFields(string $kind): array
    {
        $by = array_column(self::schema($kind), null, 'name');
        $pick = function (string ...$names) use ($by): array {
            $out = [];
            foreach ($names as $n) {
                $f = $by[$n];
                unset($f['since']);
                $out[] = $f;
            }
            return $out;
        };
        if ($kind !== self::CONTACTS) return [];
        return [
            ...$pick(self::PUBLIC_FIELD),
            ['label' => 'Name & Organisation', 'name' => 'abschnitt_name', 'type' => 'section'],
            ...$pick('name', 'praefix', 'vorname', 'nachname', 'position', 'firma', 'abteilung', 'ist_firma', 'foto'),
            ['label' => 'Kontakt', 'name' => 'abschnitt_kontakt', 'type' => 'section', 'help' => 'Jede Angabe ist zuerst intern – „Öffentlich“ zeigt sie auf der Website.'],
            ...$pick('telefon', 'email', 'adresse', 'web'),
            ['label' => 'Weitere Angaben', 'name' => 'abschnitt_weiteres', 'type' => 'section'],
            ...$pick('geburtstag', 'listen', 'notiz'),
        ];
    }

    /**
     * Beim Speichern (Entries::save): Anzeigename aus Titel, Vor- und Nachname bzw. Organisation, wenn leer.
     * Neue Kontakte: „Auf der Website zeigen“ ohne Angabe = aus.
     */
    public static function beforeSave(array $t, ?array $current, array $in): array
    {
        if (!self::isContacts($t)) return $in;
        if (!$current && !array_key_exists(self::PUBLIC_FIELD, $in)) $in[self::PUBLIC_FIELD] = false;
        $get = fn(string $k) => trim((string) (array_key_exists($k, $in) ? $in[$k] : ($current[$k] ?? '')));
        if ($get('name') === '') {
            $person = trim(implode(' ', array_filter([$get('praefix'), $get('vorname'), $get('nachname')], fn($s) => $s !== '')));
            $org = $get('firma');
            $in['name'] = ($get('ist_firma') !== '' && $get('ist_firma') !== '0' && $org !== '') ? $org : ($person !== '' ? $person : $org);
        }
        return $in;
    }

    /** Selbsttest (Teil von data:selftest): Grundschema, Versionen, Durchsetzen im Designer – ohne Datenbank */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Tabellenarten – ');
        $eq('normalize', [self::normalize('contacts'), self::normalize('inbox'), self::normalize('quatsch'), self::normalize('')], ['contacts', 'inbox', 'content', 'content']);
        $names = self::systemNames(self::CONTACTS);
        $eq('Grundschema Kontakte', array_values(array_intersect(['oeffentlich', 'name', 'vorname', 'nachname', 'position', 'foto', 'telefon', 'email', 'adresse', 'web', 'listen'], $names)),
            ['oeffentlich', 'name', 'vorname', 'nachname', 'position', 'foto', 'telefon', 'email', 'adresse', 'web', 'listen']);
        $eq('Version 1 = aktuell, nur additiv', [self::version(self::CONTACTS), count(self::schema(self::CONTACTS, 1)) <= count(self::schema(self::CONTACTS))], [1, true]);
        $eq('Mehrfachfelder sind Combo-Typen', array_values(array_filter(array_column(self::schema(self::CONTACTS), 'type'), fn($t) => \Core\Combo::isMulti($t))), ['phones', 'emails', 'addresses', 'urls']);
        $eq('Geburtstag und Notiz nie öffentlich', [Tables::field(['fields' => self::schema(self::CONTACTS)], 'geburtstag')['private'] ?? 0, Tables::field(['fields' => self::schema(self::CONTACTS)], 'notiz')['private'] ?? 0], [1, 1]);
        $err = [];
        $in = self::enforceInput(self::CONTACTS, [['label' => 'Telefon', 'name' => 'telefon', 'type' => 'tel'], ['label' => 'Vita', 'type' => 'richtext']], $err);
        $by = array_column($in, null, 'name');
        $eq('Typ eines Grundfelds bleibt fest (mit Hinweis)', [$by['telefon']['type'] ?? null, isset($err['fields.0'])], ['phones', true]);
        $eq('Fehlende Grundfelder ergänzt, Zusatzfeld bleibt', [isset($by['vorname'], $by['oeffentlich']), ($in[1]['type'] ?? '') === 'richtext'], [true, true]);
        $eq('Kein Grundfeld doppelt', count($in), count(self::schema(self::CONTACTS)) + 1);
        $s = self::beforeSave(['settings' => ['kind' => 'contacts']], null, ['vorname' => 'Erika', 'nachname' => 'Musterfrau', 'praefix' => 'Dr.']);
        $eq('Anzeigename aus Titel/Vor-/Nachname, neu = nicht öffentlich', [$s['name'], $s['oeffentlich']], ['Dr. Erika Musterfrau', false]);
        $s = self::beforeSave(['settings' => ['kind' => 'contacts']], null, ['firma' => 'Musterverein e. V.', 'ist_firma' => '1']);
        $eq('Anzeigename einer Organisation', $s['name'], 'Musterverein e. V.');
        $eq('Inhaltstabellen unverändert', self::beforeSave(['settings' => ['kind' => 'content']], null, ['a' => 1]), ['a' => 1]);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
