<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

/**
 * Was von einem Eintrag auf der Website erscheinen darf – eine Stelle für alle öffentlichen Ausgabewege.
 *
 * Heute (Stufe 0): Feld-Option „Nie öffentlich“ (Feld-Eigenschaft `private` im Tabellen-Designer). Solche Felder erscheinen in
 * keiner Website-Ausgabe: Blöcke (Personen, Datenliste, Datensatz-Felder), Detailseite samt Vorlage (app()->entry), Visitenkarte
 * (/vcard/…), strukturierte Daten (JSON-LD), Suchindex (damit auch Besucher-Chat/KI-Antwort), RSS-/iCal-Feeds, llms.txt und
 * geteilte Tabellen auf allen Mitglieds-Websites (die Option steht im Schema der Eigentümer-Website). Verwaltung, API mit Token,
 * MCP und CardDAV/CalDAV sehen weiter alles.
 *
 * Aufrufer nehmen statt des Eintrags publicEntry() (bereinigte Kopie, private Felder = null) bzw. publicRows(); wer Felder
 * auswählt (Blöcke, Suche), filtert mit publicNames()/isPrivate(). Spätere Stufen (Kontakt-Art, Freigabe je Eintrag/Angabe,
 * Sperrliste je Tabelle) erweitern publicEntry(), ohne dass sich die Aufrufer ändern.
 */
final class Exposure
{
    /** Namen/Beschriftungen, die nach privater Angabe aussehen (Health-Hinweis, Vorschlag im Designer) */
    public const PRIVATE_HINT = '~(mobil|handy|cell|privat|private|geburt|birth)~i';
    /** Feldtypen, für die der Hinweis gilt (Text, Telefon, E-Mail, Datum, Adresse) */
    public const HINT_TYPES = ['text', 'tel', 'email', 'date', 'address', 'textarea'];

    /** Ist das Feld „Nie öffentlich“? */
    public static function isPrivate(?array $f): bool
    {
        return $f !== null && !empty($f['private']);
    }

    /** Kurznamen der privaten Felder einer Tabelle */
    public static function privateFields(array $t): array
    {
        $out = [];
        foreach ((array) ($t['fields'] ?? []) as $f) if (self::isPrivate($f)) $out[] = (string) $f['name'];
        return $out;
    }

    /** Hat die Tabelle private Felder? (sonst bleibt jeder Ausgabeweg unverändert) */
    public static function hasPrivate(array $t): bool
    {
        return self::privateFields($t) !== [];
    }

    /** Bereinigte Kopie eines Eintrags für die Website: private Felder sind null */
    public static function publicEntry(array $t, array $e): array
    {
        foreach (self::privateFields($t) as $n) if (array_key_exists($n, $e)) $e[$n] = null;
        return $e;
    }

    /** publicEntry() für eine Liste von Einträgen (Schlüssel bleiben) */
    public static function publicRows(array $t, array $rows): array
    {
        if (!self::hasPrivate($t)) return $rows;
        foreach ($rows as $k => $e) if (is_array($e)) $rows[$k] = self::publicEntry($t, $e);
        return $rows;
    }

    /** Auswahl von Feldnamen ohne private Felder (Pseudo-Felder wie _title, published_at bleiben) */
    public static function publicNames(array $t, array $names): array
    {
        $priv = array_flip(self::privateFields($t));
        return $priv ? array_values(array_filter($names, fn($n) => !isset($priv[(string) $n]))) : array_values($names);
    }

    /** Sieht das Feld nach einer privaten Angabe aus (Mobil, Handy, privat, Geburtstag …), ohne als „Nie öffentlich“ markiert zu sein? */
    public static function looksPrivate(array $f): bool
    {
        if (self::isPrivate($f) || !in_array($f['type'] ?? '', self::HINT_TYPES, true)) return false;
        return (bool) preg_match(self::PRIVATE_HINT, (string) ($f['name'] ?? '') . ' ' . (string) ($f['label'] ?? ''));
    }

    /**
     * Tabellen, deren Einträge öffentlich einzeln erscheinen (Detailseite oder Schema-Typ „Person“ = Visitenkarte/Personen-Block),
     * nicht intern, nicht geschützt.
     */
    public static function exposed(array $t): bool
    {
        if (Tables::isInbox($t) || Purpose::of($t) === 'internal' || \Core\PageAccess::tableRestricted($t)) return false;
        $detail = ($t['settings']['route'] ?? '') !== '' && !empty($t['settings']['detail_page_id']);
        return $detail || \Core\StructuredData::typeOf($t) === 'Person';
    }

    /**
     * Health (Redaktion): öffentliche Tabellen mit Feldern, die nach Mobil/Privat aussehen und nicht „Nie öffentlich“ sind.
     * Nur Tabellen- und Feldnamen, keine Inhalte. Nichts wird automatisch umgestellt – der Hinweis schlägt es vor.
     * @return array<string, ?bool>
     */
    public static function health(): array
    {
        $out = [];
        try {
            foreach (Tables::content() as $t) {
                if (Tables::isShared($t) && !Shared::isOwner($t)) continue;   // Schema pflegt die Eigentümer-Website
                if (!self::exposed($t)) continue;
                $names = [];
                foreach ($t['fields'] as $f) if (self::looksPrivate($f)) $names[] = '„' . Tables::label($f) . '“';
                if ($names) {
                    $out[__('Datenschutz: „{table}“ zeigt {fields} öffentlich – im Tabellen-Designer „Nie öffentlich“ prüfen',
                        ['table' => (string) $t['name'], 'fields' => implode(', ', $names)])] = null;
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }
}
