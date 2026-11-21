<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

/**
 * Was von einem Eintrag auf der Website erscheinen darf – eine Stelle für alle öffentlichen Ausgabewege: Blöcke (Personen,
 * Datenliste, Datensatz-Felder, Kalender …), Kits mit eigenen Abfragen, Detailseite samt Vorlage (app()->entry), Visitenkarte
 * (/vcard/…), strukturierte Daten (JSON-LD), Suchindex (damit auch Besucher-Chat/KI-Antwort), RSS-/iCal-Feeds, llms.txt und
 * geteilte Tabellen auf allen Mitglieds-Websites (Freigaben stehen am Eintrag bzw. im Schema der Eigentümer-Website).
 * Verwaltung, API mit Token, MCP und CardDAV/CalDAV sehen weiter alles. Jede Ebene kann nur einschränken:
 *
 *  1. Eintrag: Kontakte (Core\Data\Kinds, Art contacts) nur mit „Auf der Website zeigen“ (Feld oeffentlich). Auf der Website
 *     filtert das schon die Abfrage (Entries::query/count/facets/bySlug über visibleSql()), damit auch Kits mit eigenen Abfragen
 *     nichts anderes bekommen; publicRows() lässt solche Einträge weg (Suchindex, Kommandozeile).
 *  2. Angabe: beschriftete Mehrfachfelder (Core\Combo::MULTI) nur mit Freigabe „Öffentlich“ (Adressen auch „nur Ort“).
 *  3. Feld: Option „Nie öffentlich“ (private) und Altfelder nach einer Umwandlung (legacy) – in keiner Website-Ausgabe.
 *
 * Gegenstück „Nur Website“ (website_only): erscheint auf der Website, geht aber nie an Adressbuch-/Kalender-Apps (CardDAV/CalDAV),
 * Visitenkarte (.vcf) oder ICS (Feed, Export) – appOnly()/forApps() für diese Wege.
 *
 * Aufrufer nehmen statt des Eintrags publicEntry() (bereinigte Kopie) bzw. publicRows(); wer Felder auswählt (Blöcke, Suche),
 * filtert mit publicNames()/isPrivate(). Tabellen ohne diese Merkmale bleiben in jedem Weg byte-gleich.
 */
final class Exposure
{
    /** Namen/Beschriftungen, die nach privater Angabe aussehen (Health-Hinweis, Vorschlag im Designer) */
    public const PRIVATE_HINT = '~(mobil|handy|cell|privat|private|geburt|birth)~i';
    /** Feldtypen, für die der Hinweis gilt (Text, Telefon, E-Mail, Datum, Adresse) */
    public const HINT_TYPES = ['text', 'tel', 'email', 'date', 'address', 'textarea'];

    /** Ist das Feld „Nie öffentlich“ (oder ein ausgeblendetes Altfeld nach data:convert)? */
    public static function isPrivate(?array $f): bool
    {
        return $f !== null && (!empty($f['private']) || !empty($f['legacy']));
    }

    /** Feld „Nur Website“: nie in vCard, CardDAV, CalDAV, ICS */
    public static function isWebsiteOnly(?array $f): bool
    {
        return $f !== null && !empty($f['website_only']);
    }

    /** Felder einer Tabelle, die an Apps und Austauschformate gehen dürfen (ohne „Nur Website“ und ohne Altfelder) */
    public static function appFields(array $t): array
    {
        return array_values(array_filter((array) ($t['fields'] ?? []), fn($f) => !self::isWebsiteOnly($f) && empty($f['legacy'])));
    }

    /** Eintrag für Apps/Austausch (vCard, ICS): „Nur Website“-Felder sind null – Verwaltung, API und Website bleiben unberührt */
    public static function forApps(array $t, array $e): array
    {
        foreach ((array) ($t['fields'] ?? []) as $f) if ((self::isWebsiteOnly($f) || !empty($f['legacy'])) && array_key_exists($f['name'], $e)) $e[$f['name']] = null;
        return $e;
    }

    /** Ist die Tabelle ein Adressbuch (Freigabe je Kontakt)? */
    public static function perEntry(array $t): bool
    {
        return Kinds::isContacts($t);
    }

    /** Darf der Eintrag überhaupt auf der Website erscheinen? (Kontakte: „Auf der Website zeigen“) */
    public static function visible(array $t, array $e): bool
    {
        return !self::perEntry($t) || !empty($e[Kinds::PUBLIC_FIELD]);
    }

    /**
     * SQL-Bedingung für Website-Abfragen ('' = keine): Kontakte nur mit Freigabe. Gilt, solange eine Website-Anfrage läuft
     * (nicht Verwaltung, API, MCP, CardDAV/CalDAV, Kommandozeile – dort sehen Berechtigte alles; Suchindex und Feeds prüfen selbst).
     */
    public static function visibleSql(array $t): string
    {
        return self::perEntry($t) && self::websiteRequest() ? Kinds::PUBLIC_FIELD . ' = 1' : '';
    }

    /** Läuft gerade eine Anfrage der Website (Besucher, auch angemeldete Redaktion auf der Website)? */
    public static function websiteRequest(): bool
    {
        $r = app()->request;
        if (!$r || $r->isAdminPath()) return false;
        $p = (string) $r->path;
        foreach (['/api/', '/mcp', '/dav', '/.well-known/'] as $pre) if ($p === rtrim($pre, '/') || str_starts_with($p, $pre)) return false;
        return true;
    }

    /** Kurznamen der privaten Felder einer Tabelle */
    public static function privateFields(array $t): array
    {
        $out = [];
        foreach ((array) ($t['fields'] ?? []) as $f) if (self::isPrivate($f)) $out[] = (string) $f['name'];
        return $out;
    }

    /** Hat die Tabelle private Felder? */
    public static function hasPrivate(array $t): bool
    {
        return self::privateFields($t) !== [];
    }

    /** Muss ein Eintrag dieser Tabelle für die Website bereinigt werden? (sonst bleibt jeder Ausgabeweg unverändert) */
    public static function affects(array $t): bool
    {
        if (self::hasPrivate($t) || self::perEntry($t)) return true;
        foreach ((array) ($t['fields'] ?? []) as $f) if (\Core\Combo::isMulti((string) ($f['type'] ?? ''))) return true;
        return false;
    }

    /**
     * Bereinigte Kopie eines Eintrags für die Website: private Felder und Altfelder sind null, Mehrfachfelder enthalten nur
     * freigegebene Angaben. Ein nicht freigegebener Kontakt (visible() = false) verliert alle Feldwerte und bekommt _hidden = true
     * – Aufrufer, die einzelne Einträge laden (find), prüfen visible() vorher.
     */
    public static function publicEntry(array $t, array $e): array
    {
        if (!self::visible($t, $e)) {
            foreach ((array) ($t['fields'] ?? []) as $f) if (array_key_exists($f['name'], $e)) $e[$f['name']] = null;
            $e['_hidden'] = true;
            return $e;
        }
        foreach ((array) ($t['fields'] ?? []) as $f) {
            $n = (string) $f['name'];
            if (!array_key_exists($n, $e)) continue;
            if (self::isPrivate($f)) $e[$n] = null;
            elseif (\Core\Combo::isMulti((string) $f['type'])) $e[$n] = \Core\Combo::publicItems((string) $f['type'], $e[$n]);
        }
        return $e;
    }

    /** publicEntry() für eine Liste von Einträgen (Schlüssel bleiben); nicht freigegebene Kontakte fallen weg */
    public static function publicRows(array $t, array $rows): array
    {
        if (!self::affects($t)) return $rows;
        $list = array_is_list($rows);
        foreach ($rows as $k => $e) {
            if (!is_array($e)) continue;
            if (!self::visible($t, $e)) { unset($rows[$k]); continue; }
            $rows[$k] = self::publicEntry($t, $e);
        }
        return $list ? array_values($rows) : $rows;
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
