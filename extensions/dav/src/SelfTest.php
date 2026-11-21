<?php
declare(strict_types=1);

namespace Klxm\Dav;

/**
 * php bin/console dav:selftest – Abbildung ohne Datenbank: Namen (FN → N und zurück), feste Organisation (ORG), Rich-Text als reiner
 * Text (NOTE, X-MYCMS-*), Rückweg aus der App (unverändert/geändert), „Nur veröffentlichte Einträge“ und Standardwerte.
 * Arbeitet mit einer erfundenen Tabelle im Speicher und übergibt die Einstellungen direkt – nichts wird gespeichert.
 */
final class SelfTest
{
    public static function run(): int
    {
        $fail = 0;
        $eq = function (string $name, mixed $got, mixed $want) use (&$fail): void {
            $ok = $got === $want;
            if (!$ok) $fail++;
            echo ($ok ? '  ✓ ' : '  ✗ ') . $name . ($ok ? '' : ' – erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true)) . "\n";
        };

        echo "Name → N (Nachname;Vorname;;Präfix;Suffix)\n";
        foreach ([
            'Erika Musterfrau' => ['Musterfrau', 'Erika', '', '', ''],
            'Erika Maria Musterfrau' => ['Musterfrau', 'Erika Maria', '', '', ''],
            'Dr. Erika Musterfrau' => ['Musterfrau', 'Erika', '', 'Dr.', ''],
            'Prof. Dr. Max Mustermann' => ['Mustermann', 'Max', '', 'Prof. Dr.', ''],
            'Dr. med. Erika Musterfrau' => ['Musterfrau', 'Erika', '', 'Dr. med.', ''],
            'Dipl.-Ing. Max Mustermann' => ['Mustermann', 'Max', '', 'Dipl.-Ing.', ''],
            'Frau Erika Musterfrau' => ['Musterfrau', 'Erika', '', 'Frau', ''],
            'Erika von Musterstadt' => ['von Musterstadt', 'Erika', '', '', ''],
            'Max van der Muster' => ['van der Muster', 'Max', '', '', ''],
            'Max zu Musterburg' => ['zu Musterburg', 'Max', '', '', ''],
            'Erika Musterfrau, M.A.' => ['Musterfrau', 'Erika', '', '', 'M.A.'],
            'Musterfrau, Erika' => ['Musterfrau', 'Erika', '', '', ''],
            'Musterfrau' => ['Musterfrau', '', '', '', ''],
            'Dr. Musterfrau' => ['Musterfrau', '', '', 'Dr.', ''],
            '  Erika   Musterfrau ' => ['Musterfrau', 'Erika', '', '', ''],
            '' => ['', '', '', '', ''],
        ] as $in => $want) $eq('„' . $in . '“', CardBackend::splitName($in), $want);

        echo "N → Name\n";
        $eq('mit Präfix und Suffix', CardBackend::joinName(['Musterfrau', 'Erika', '', 'Prof. Dr.', 'M.A.']), 'Prof. Dr. Erika Musterfrau, M.A.');
        $eq('nur Nachname', CardBackend::joinName(['Musterfrau']), 'Musterfrau');
        $eq('leer ohne Vor- und Nachname', CardBackend::joinName(['', '', '', 'Dr.', '']), '');
        $eq('Hin und zurück', CardBackend::joinName(CardBackend::splitName('Dr. med. Erika von Musterstadt')), 'Dr. med. Erika von Musterstadt');

        echo "Ausgabe (vCard)\n";
        $t = self::table();
        $e = ['id' => 7, 'status' => 'published', 'updated_at' => '2026-11-21 10:00:00', 'name' => 'Dr. Erika Musterfrau', 'email' => 'erika@example.org',
            'notiz' => '<p>Sprechzeiten &amp; Termine</p><p>Mo–Fr<br>8–12 Uhr</p>', 'vita' => '<p><b>Fachärztin</b> für Innere&nbsp;Medizin</p><ul><li>Diabetologie</li><li>Sport</li></ul>'];
        $s = ['map' => ['fn' => 'name', 'note' => 'notiz'], 'org' => 'Musterpraxis'] + Dav::TABLE_DEFAULTS;
        $card = CardBackend::render($t, $e, null, $s);
        $eq('FN unverändert', self::val($card, 'FN'), 'Dr. Erika Musterfrau');
        $eq('N aus FN abgeleitet', self::parts($card, 'N'), ['Musterfrau', 'Erika', '', 'Dr.', '']);
        $eq('feste Organisation', self::val($card, 'ORG'), 'Musterpraxis');
        $eq('NOTE als reiner Text (Absätze, Umbruch, Entities)', self::val($card, 'NOTE'), "Sprechzeiten & Termine\n\nMo–Fr\n8–12 Uhr");
        $vita = (string) self::val($card, 'X-MYCMS-VITA');
        $eq('X-MYCMS-* aus Rich-Text ohne HTML', $vita !== '' && $vita === strip_tags($vita) && !str_contains($vita, '&'), true);
        $eq('X-MYCMS-* mit Zeilen', $vita, "Fachärztin für Innere\u{A0}Medizin\n\nDiabetologie\nSport");
        $blocks = CardBackend::render($t, $e + ['inhalt' => [['type' => 'richtext', 'data' => ['text' => '<p>x</p>']]]], null, $s);
        $eq('Inhaltsblöcke nicht als X-MYCMS-* (kein „Array“)', str_contains($blocks, 'X-MYCMS-INHALT') || str_contains($blocks, 'Array'), false);
        [$in] = CardBackend::parse($t, $e, self::vcard("FN:Dr. Erika Musterfrau\r\nX-MYCMS-INHALT:Array"), $s);
        $eq('Inhaltsblöcke nicht aus der App überschreibbar', array_key_exists('inhalt', $in), false);
        $eq('org => false: kein ORG', self::val(CardBackend::render($t, $e, null, ['org' => false] + $s), 'ORG'), null);
        $eq('org => true: Name der Organisation', self::val(CardBackend::render($t, $e, null, ['org' => true] + $s), 'ORG'), trim(strip_tags(org_name())));
        $eq('zugeordnetes Feld vor fester Organisation',
            self::val(CardBackend::render($t, $e + ['firma' => 'Muster GmbH'], null, ['map' => ['fn' => 'name', 'org' => 'firma']] + $s), 'ORG'), 'Muster GmbH');
        $nn = CardBackend::render($t, $e + ['vorname' => 'Erika', 'nachname' => 'Musterfrau'], null, ['map' => ['n_family' => 'nachname', 'n_given' => 'vorname']] + $s);
        $eq('Vor-/Nachname zugeordnet: N wie bisher', self::parts($nn, 'N'), ['Musterfrau', 'Erika', '', '', '']);

        echo "Rückweg aus der App\n";
        $data = CardBackend::render($t, $e, null, $s);
        [$in] = CardBackend::parse($t, $e, $data, $s);
        $eq('unverändert: Rich-Text bleibt (NOTE)', array_key_exists('notiz', $in), false);
        $eq('unverändert: Rich-Text bleibt (X-MYCMS-*)', array_key_exists('vita', $in), false);
        $eq('unverändert: Name bleibt', $in['name'] ?? null, 'Dr. Erika Musterfrau');
        [$in] = CardBackend::parse($t, $e, str_replace('X-MYCMS-VITA:', 'X-MYCMS-VITA:Neu <b> ', $data), $s);
        $eq('geändert: Rich-Text escaped als Absatz', $in['vita'] ?? null, '<p>Neu &lt;b&gt; Fachärztin für Innere' . "\u{A0}" . 'Medizin</p><p>Diabetologie<br>Sport</p>');
        $en = ['notiz' => '<p>Rückruf erbeten</p><p>[# Nummer prüfen #]</p>'] + $e;
        $noted = CardBackend::render($t, $en, null, $s);
        $eq('Redaktionsnotizen nicht in der App', str_contains($noted, '[#') || str_contains($noted, 'prüfen'), false);
        [$in] = CardBackend::parse($t, $en, $noted, $s);
        $eq('unverändert: Notiz im CMS bleibt', array_key_exists('notiz', $in), false);
        [$in] = CardBackend::parse($t, $en, str_replace('NOTE:Rückruf erbeten', 'NOTE:Rückruf am Montag', $noted), $s);
        $eq('geändert: Redaktionsnotiz wieder angehängt', $in['notiz'] ?? null, '<p>Rückruf am Montag</p><p>[# Nummer prüfen #]</p>');
        [$in] = CardBackend::parse($t, $e, self::vcard("FN:Dr. Erika Muster\r\nN:Muster;Erika;;Dr.;"), $s);
        $eq('FN hat Vorrang', $in['name'] ?? null, 'Dr. Erika Muster');
        [$in] = CardBackend::parse($t, $e, self::vcard("FN:Dr. Erika Musterfrau\r\nN:Beispiel;Erika;;Dr.;"), $s);
        $eq('nur N geändert → Name aus N', $in['name'] ?? null, 'Dr. Erika Beispiel');
        [$in] = CardBackend::parse($t, $e, self::vcard("FN:Dr. Erika Musterfrau\r\nN:Musterfrau;Erika;;Prof.;"), $s);
        $eq('nur Präfix in N anders → Name bleibt', $in['name'] ?? null, 'Dr. Erika Musterfrau');
        [$in] = CardBackend::parse($t, null, self::vcard("N:von Muster;Max;;;"), $s);
        $eq('ohne FN → Name aus N', $in['name'] ?? null, 'Max von Muster');

        echo "Nur veröffentlichte Einträge\n";
        $eq('Standard aus (bisheriges Verhalten)', Dav::TABLE_DEFAULTS['published_only'], false);
        $eq('Standard ORG = Name der Organisation', Dav::TABLE_DEFAULTS['org'], true);
        $eq('aus: Entwurf sichtbar', Dav::visible(['published_only' => false], ['status' => 'draft']), true);
        $eq('an: Entwurf/geplant unsichtbar', Dav::visible(['published_only' => true], ['status' => 'draft']), false);
        $eq('an: veröffentlicht sichtbar', Dav::visible(['published_only' => true], ['status' => 'published']), true);
        $eq('fehlende Einstellung = aus', Dav::visible([], ['status' => 'draft']), true);
        $eq('fester Text', Dav::fixedOrg(['org' => ' Muster GmbH ']), 'Muster GmbH');
        $eq('org null = Standard', Dav::fixedOrg(['org' => null]), trim(strip_tags(org_name())));

        echo $fail ? "\n$fail Prüfungen fehlgeschlagen\n" : "\nAlle Prüfungen bestanden\n";
        return $fail ? 1 : 0;
    }

    /** Erfundene Tabelle „Personen“ (nur im Speicher) */
    private static function table(): array
    {
        $f = fn(string $name, string $type) => ['name' => $name, 'label' => ucfirst($name), 'type' => $type];
        return ['id' => 0, 'handle' => 'dav_selftest', 'table' => 'data_dav_selftest', 'name' => 'Personen', 'singular' => 'Person',
            'settings' => ['title_field' => 'name', 'workflow' => false],
            'fields' => [$f('name', 'text'), $f('vorname', 'text'), $f('nachname', 'text'), $f('firma', 'text'), $f('email', 'email'),
                $f('notiz', 'richtext'), $f('vita', 'richtext'), $f('inhalt', 'blocks')]];
    }

    /** Erste Eigenschaft einer vCard als Text (null, wenn sie fehlt) */
    private static function val(string $vcard, string $name): ?string
    {
        $p = self::prop($vcard, $name);
        return $p ? (string) $p : null;
    }

    /** Teile einer zusammengesetzten Eigenschaft (N, ADR) */
    private static function parts(string $vcard, string $name): array
    {
        $p = self::prop($vcard, $name);
        return $p ? $p->getParts() : [];
    }

    private static function prop(string $vcard, string $name): ?\Sabre\VObject\Property
    {
        $list = \Sabre\VObject\Reader::read($vcard)->select($name);
        $p = $list ? reset($list) : null;
        return $p instanceof \Sabre\VObject\Property ? $p : null;
    }

    private static function vcard(string $lines): string
    {
        return "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:dav-selftest\r\n$lines\r\nEND:VCARD\r\n";
    }
}
