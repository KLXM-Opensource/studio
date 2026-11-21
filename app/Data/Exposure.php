<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

/**
 * Was von einem Eintrag öffentlich erscheinen darf – eine Stelle für alle öffentlichen Ausgabewege: Blöcke (Personen,
 * Datenliste, Datensatz-Felder, Kalender …), Kits mit eigenen Abfragen, Detailseite samt Vorlage (app()->entry), Visitenkarte
 * (/vcard/…), strukturierte Daten (JSON-LD), Suchindex (damit auch Besucher-Chat/KI-Antwort), RSS-/iCal-Feeds, llms.txt und
 * geteilte Tabellen auf allen Mitglieds-Websites (Freigaben stehen am Eintrag bzw. im Schema der Eigentümer-Website).
 * Verwaltung, API mit Token, MCP und CardDAV/CalDAV sehen weiter alles. Jede Ebene kann nur einschränken:
 *
 *  1. Eintrag: Kontakte (Core\Data\Kinds, Art contacts) nur mit „Auf der Website zeigen“ (Feld oeffentlich). Auf der Website
 *     filtert das schon die Abfrage (Entries::query/count/facets/bySlug über visibleSql()), damit auch Kits mit eigenen Abfragen
 *     nichts anderes bekommen; publicRows() lässt solche Einträge weg (Suchindex, Kommandozeile).
 *  2. Feld: Freigabe je Feld im Tabellen-Designer (rule()) – Modus „immer“, „Redaktion entscheidet je Eintrag“ (mit Standard,
 *     Entscheidung in der Spalte _exposure) oder „nie“, jeweils für die Ziele web (Website), vcard (öffentliche Visitenkarte)
 *     und ics (öffentliche Kalender-Datei, Abo, Export). Ältere Angaben (private, website_only, public_ok) bleiben lesbar.
 *  3. Angabe: beschriftete Mehrfachfelder (Core\Combo::MULTI) nur mit Freigabe „Öffentlich“ (Adressen auch „nur Ort“).
 *
 * Aufrufer nehmen statt des Eintrags publicEntry($t, $e, $ziel) bzw. publicRows(); wer Felder auswählt (Blöcke, Suche), filtert
 * mit publicNames()/isPrivate(). Apps der Redaktion (CardDAV/CalDAV) bekommen forApps(): alles außer bewusst anderen Zielen
 * vorbehaltenen Feldern (z. B. „Nur Website“: Vita). Tabellen ohne diese Merkmale bleiben in jedem Weg byte-gleich.
 */
final class Exposure
{
    /** Namen/Beschriftungen, die nach privater Angabe aussehen (Health-Hinweis, Vorschlag im Designer) */
    public const PRIVATE_HINT = '~(mobil|handy|cell|privat|private|geburt|birth)~i';
    /** Feldtypen, für die der Hinweis gilt (Text, Telefon, E-Mail, Datum, Adresse) */
    public const HINT_TYPES = ['text', 'tel', 'email', 'date', 'address', 'textarea'];

    /** Ziele einer Freigabe: Website (Blöcke, Detailseite, JSON-LD, Suche/KI, RSS), öffentliche vCard, öffentliche Kalender-Datei (ICS) */
    public const TARGETS = ['web', 'vcard', 'ics'];
    /** Freigabe-Modi je Feld: immer (für die gewählten Ziele) · Redaktion entscheidet je Eintrag · nie öffentlich */
    public const MODES = ['always', 'editor', 'never'];
    /** Spalte mit der Entscheidung je Eintrag (Modus „editor“): JSON {feld: {web, vcard, ics}} */
    public const STATE = '_exposure';

    /**
     * Freigabe-Regel eines Feldes: ['mode' => always|editor|never, 'web' => bool, 'vcard' => bool, 'ics' => bool]. Bei „editor“
     * sind die Ziele der Standard für Einträge ohne eigene Entscheidung. Ältere Angaben bleiben lesbar: ohne Angabe = immer für
     * alle Ziele (bisheriges Verhalten), private = nie, website_only = immer nur Website, public_ok = immer (bewusst), legacy
     * (Altfeld nach data:convert) = nie.
     */
    public static function rule(?array $f): array
    {
        $all = ['mode' => 'always', 'web' => true, 'vcard' => true, 'ics' => true];
        if ($f === null) return $all;
        if (!empty($f['legacy']) || !empty($f['private'])) return ['mode' => 'never', 'web' => false, 'vcard' => false, 'ics' => false];
        if (is_array($f['exposure'] ?? null)) {
            $x = $f['exposure'];
            $mode = in_array($x['mode'] ?? '', self::MODES, true) ? (string) $x['mode'] : 'always';
            if ($mode === 'never') return ['mode' => 'never', 'web' => false, 'vcard' => false, 'ics' => false];
            $r = ['mode' => $mode];
            foreach (self::TARGETS as $k) $r[$k] = !array_key_exists($k, $x) || in_array($x[$k], [true, 1, '1', 'on', 'true'], true);
            return $r;
        }
        if (!empty($f['website_only'])) return ['mode' => 'always', 'web' => true, 'vcard' => false, 'ics' => false];
        return $all;
    }

    /**
     * Eingabe des Designers (fields[i][exposure][mode|web|vcard|ics], ältere Schalter private/website_only) → gespeicherte Angaben:
     * 'exposure' nur, wenn sie vom Standard (immer, alle Ziele) abweicht; 'private' => true bei „nie“ (lesbar für ältere Aufrufer).
     */
    public static function fieldDef(array $in): array
    {
        $raw = is_array($in['exposure'] ?? null) ? $in['exposure'] : null;
        $r = $raw !== null ? self::rule(['exposure' => $raw]) : self::rule($in);
        if (!empty($in['private']) && $raw === null) $r = self::rule(['private' => true]);
        $out = [];
        if ($r['mode'] === 'never') return ['private' => true];   // „nie“ wie bisher als private (Rundlauf unverändert)
        if ($r !== ['mode' => 'always', 'web' => true, 'vcard' => true, 'ics' => true]) $out['exposure'] = $r;
        return $out;
    }

    /** Ist das Feld „Nie öffentlich“ (Modus never, auch Altfelder nach data:convert)? */
    public static function isPrivate(?array $f): bool
    {
        return $f !== null && self::rule($f)['mode'] === 'never';
    }

    /** Entscheidet die Redaktion je Eintrag? */
    public static function isEditorChoice(?array $f): bool
    {
        return $f !== null && self::rule($f)['mode'] === 'editor';
    }

    /** Immer für dieses Ziel öffentlich (ohne Entscheidung je Eintrag)? – z. B. für die Besucher-Suche per LIKE */
    public static function alwaysFor(?array $f, string $target = 'web'): bool
    {
        $r = self::rule($f);
        return $r['mode'] === 'always' && $r[$target];
    }

    /** Feld „Nur Website“: immer auf der Website, nie in vCard oder ICS */
    public static function isWebsiteOnly(?array $f): bool
    {
        $r = self::rule($f);
        return $f !== null && $r['mode'] === 'always' && $r['web'] && !$r['vcard'] && !$r['ics'];
    }

    /** Darf der Wert dieses Feldes eines Eintrags an das Ziel gehen? (Modus, Entscheidung je Eintrag bzw. Standard) */
    public static function allowed(array $f, array $e, string $target = 'web'): bool
    {
        $r = self::rule($f);
        if ($r['mode'] === 'never') return false;
        if ($r['mode'] === 'always') return $r[$target];
        $st = self::entryState($e)[(string) $f['name']] ?? null;
        return is_array($st) && array_key_exists($target, $st) ? (bool) $st[$target] : $r[$target];
    }

    /** Entscheidungen je Eintrag (Spalte _exposure) als Array */
    public static function entryState(array $e): array
    {
        $v = $e[self::STATE] ?? null;
        if (is_string($v)) $v = json_decode($v, true);
        return is_array($v) ? $v : [];
    }

    /** Hat die Tabelle Felder mit Entscheidung je Eintrag? (dann gibt es die Spalte _exposure) */
    public static function hasEditorChoice(array $t): bool
    {
        foreach ((array) ($t['fields'] ?? []) as $f) if (self::isEditorChoice($f)) return true;
        return false;
    }

    /**
     * Entscheidungen aus der Eingabemaske ($in = [feld => [web, vcard, ics]]) bereinigen: nur Felder im Modus „editor“, nur bekannte
     * Ziele; fehlende Angaben behalten den bisherigen Stand bzw. – bei neuen Einträgen – den Standard des Feldes.
     */
    public static function cleanState(array $t, mixed $in, ?array $current): array
    {
        $in = is_array($in) ? $in : [];
        $cur = $current ? self::entryState($current) : [];
        $out = [];
        foreach ((array) ($t['fields'] ?? []) as $f) {
            if (!self::isEditorChoice($f)) continue;
            $n = (string) $f['name'];
            $r = self::rule($f);
            $row = [];
            foreach (self::TARGETS as $k) {
                $v = $in[$n][$k] ?? null;
                $row[$k] = $v !== null ? in_array($v, [true, 1, '1', 'on', 'true'], true) : (bool) ($cur[$n][$k] ?? $r[$k]);
            }
            $out[$n] = $row;
        }
        return $out;
    }

    /** Ziele, die für eine Tabelle eine Rolle spielen: Website immer, vCard bei Personen/Adressbüchern, ICS bei Kalendern */
    public static function targets(array $t): array
    {
        $out = ['web'];
        if (Kinds::isContacts($t) || \Core\StructuredData::typeOf($t) === 'Person') $out[] = 'vcard';
        if (Calendar::enabled($t)) $out[] = 'ics';
        return $out;
    }

    /** Bezeichnung eines Ziels (Verwaltung) */
    public static function targetLabel(string $k): string
    {
        return match ($k) { 'vcard' => __('Kontakt speichern (vCard)'), 'ics' => __('Kalender-Datei (ICS)'), default => __('Website') };
    }

    /** Kurzform eines Ziels für das Merkmal am Feld („Öffentlich · Website, vCard“) */
    public static function targetShort(string $k): string
    {
        return match ($k) { 'vcard' => 'vCard', 'ics' => 'ICS', default => __('Website') };
    }

    /**
     * Text des Merkmals zu reach(): „Öffentlich“ (bei mehreren Zielen der Tabelle mit Kurzform „· Website, vCard“), „Nur Ort“
     * (Adresse) oder „Intern“. $tg = Ziele der Tabelle.
     */
    public static function reachText(array $r, array $tg): string
    {
        if (!$r['targets']) return __('Intern');
        if ($r['scope'] === 'city') return __('Nur Ort');
        if ($r['mode'] === 'item' || count($tg) < 2) return __('Öffentlich');
        return __('Öffentlich') . ' · ' . implode(', ', array_map([self::class, 'targetShort'], $r['targets']));
    }

    /**
     * Felder, die an Adressbuch-/Kalender-Apps der Redaktion gehen (CardDAV $app = vcard, CalDAV $app = ics): alles außer Feldern,
     * die bewusst nur für andere Ziele freigegeben sind (z. B. „Nur Website“: Vita, Über mich) und Altfeldern. „Nie öffentlich“
     * und „Redaktion entscheidet“ sehen die Apps der Redaktion vollständig.
     */
    public static function appFields(array $t, string $app = 'vcard'): array
    {
        return array_values(array_filter((array) ($t['fields'] ?? []), fn($f) => self::forApp($f, $app)));
    }

    private static function forApp(array $f, string $app): bool
    {
        if (!empty($f['legacy'])) return false;
        $r = self::rule($f);
        return $r['mode'] !== 'always' || $r[$app];
    }

    /** Eintrag für die Apps der Redaktion (CardDAV/CalDAV): Felder, die nicht an diese Art App gehen, sind null */
    public static function forApps(array $t, array $e, string $app = 'vcard'): array
    {
        foreach ((array) ($t['fields'] ?? []) as $f) if (!self::forApp($f, $app) && array_key_exists($f['name'], $e)) $e[$f['name']] = null;
        return $e;
    }

    /**
     * Wohin gelangt ein Feld bzw. eine Angabe dieses Eintrags? Eine Stelle für alle Anzeigen der Redaktion (Merkmal am Feld,
     * Zusammenfassung im Formularkopf, Listen) – dieselben Regeln wie publicEntry(), nur als Auskunft statt Bereinigung.
     *  - $f = null: der Eintrag selbst (Kontakte: „Auf der Website zeigen“; sonst immer – Status/Zeitplan prüft der Aufrufer)
     *  - $f: das Feld (Modus, Entscheidung je Eintrag in $e['_exposure'] bzw. Standard)
     *  - $item: eine Angabe eines Mehrfachfeldes (Core\Combo::MULTI): Ziele des Feldes, nur wenn die Angabe freigegeben ist
     * Nur Ziele, die für die Tabelle eine Rolle spielen (targets()). Rückgabe: targets = Liste der Ziele (leer = intern),
     * mode = always|editor|never (Feld) bzw. item|entry, fixed = im Formular nicht umschaltbar (immer/nie, Tabellen-Designer),
     * scope = Angabe: full|city|'' (Adresse „nur Ort“).
     * @return array{targets: list<string>, mode: string, fixed: bool, scope: string}
     */
    public static function reach(array $t, ?array $f, array $e = [], ?array $item = null): array
    {
        $tg = self::targets($t);
        if ($f === null) return ['targets' => self::visible($t, $e) ? $tg : [], 'mode' => 'entry', 'fixed' => false, 'scope' => ''];
        return self::fieldReach($f, $e, $tg, $item);
    }

    /** reach() für ein Feld mit bekannten Zielen der Tabelle ($tg = targets($t)) – z. B. im Formular (Core\Fields) */
    public static function fieldReach(array $f, array $e, array $tg, ?array $item = null): array
    {
        $mode = self::rule($f)['mode'];
        $on = array_values(array_filter($tg, fn($k) => self::allowed($f, $e, $k)));
        if ($item === null) return ['targets' => $on, 'mode' => $mode, 'fixed' => $mode !== 'editor', 'scope' => ''];
        $scope = \Core\Combo::publicScope((string) ($f['type'] ?? ''), $item);
        return ['targets' => $scope === '' ? [] : $on, 'mode' => 'item', 'fixed' => false, 'scope' => $scope];
    }

    /**
     * Was von diesen Werten auf der Website steht (Formularkopf „Auf der Website: …“): Beschriftungen ausgefüllter Felder, die
     * reach() für die Website freigibt; Mehrfachfelder je freigegebener Angabe als „Telefon (Mobil)“ bzw. „Adresse (…, nur Ort)“.
     * Ja/Nein-Felder und das Feld „Auf der Website zeigen“ zählen nicht. Werte wie im Formular (gespeichert oder gesendet).
     * @return list<string>
     */
    public static function webSummary(array $t, array $values): array
    {
        $out = [];
        foreach (Tables::dataFields((array) ($t['fields'] ?? [])) as $f) {
            $n = (string) ($f['name'] ?? '');
            $type = (string) ($f['type'] ?? '');
            if ($n === '' || !empty($f['legacy']) || $type === 'bool' || (self::perEntry($t) && $n === Kinds::PUBLIC_FIELD)) continue;
            if (!in_array('web', self::reach($t, $f, $values)['targets'], true)) continue;
            $label = Tables::label($f);
            if (\Core\Combo::isMulti($type)) {
                foreach (\Core\Combo::items($values[$n] ?? null) as $i) {
                    if (\Core\Combo::itemText($type, $i) === '' && trim((string) ($i['city'] ?? '')) === '') continue;
                    $r = self::reach($t, $f, $values, $i);
                    if (!in_array('web', $r['targets'], true)) continue;
                    $bits = array_filter([\Core\Combo::labelText($type, $i), $r['scope'] === 'city' ? __('nur Ort') : '']);
                    $out[] = $label . ($bits ? ' (' . implode(', ', $bits) . ')' : '');
                }
                continue;
            }
            $v = $values[$n] ?? null;
            if ($v === null || $v === '' || $v === [] || $v === false || (is_string($v) && trim(strip_tags($v)) === '')) continue;
            $out[] = $label;
        }
        return $out;
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
        if (Subscriptions::inInternal()) return false;   // Abo-Token mit Umfang „intern“ (Core\Data\Subscriptions)
        $p = (string) $r->path;
        foreach (['/api/', '/mcp', '/dav', '/.well-known/'] as $pre) if ($p === rtrim($pre, '/') || str_starts_with($p, $pre)) return false;
        return true;
    }

    /** Kurznamen der Felder, die nie öffentlich sind */
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

    /** Muss ein Eintrag dieser Tabelle bereinigt werden? (sonst bleibt jeder Ausgabeweg unverändert) */
    public static function affects(array $t, string $target = 'web'): bool
    {
        if (self::perEntry($t)) return true;
        foreach ((array) ($t['fields'] ?? []) as $f) {
            $r = self::rule($f);
            if ($r['mode'] !== 'always' || !$r[$target] || \Core\Combo::isMulti((string) ($f['type'] ?? ''))) return true;
        }
        return false;
    }

    /**
     * Bereinigte Kopie eines Eintrags für ein Ziel (web = Website, vcard = öffentliche Visitenkarte, ics = öffentliche Kalender-Datei):
     * nicht freigegebene Felder sind null, Mehrfachfelder enthalten nur freigegebene Angaben, die Spalte _exposure fehlt.
     * Ein nicht freigegebener Kontakt (visible() = false) verliert alle Feldwerte und bekommt _hidden = true – Aufrufer, die
     * einzelne Einträge laden (find), prüfen visible() vorher.
     */
    public static function publicEntry(array $t, array $e, string $target = 'web'): array
    {
        if (!self::visible($t, $e)) {
            foreach ((array) ($t['fields'] ?? []) as $f) if (array_key_exists($f['name'], $e)) $e[$f['name']] = null;
            $e['_hidden'] = true;
            unset($e[self::STATE]);
            return $e;
        }
        foreach ((array) ($t['fields'] ?? []) as $f) {
            $n = (string) $f['name'];
            if (!array_key_exists($n, $e)) continue;
            if (!self::allowed($f, $e, $target)) $e[$n] = null;
            elseif (\Core\Combo::isMulti((string) $f['type'])) $e[$n] = \Core\Combo::publicItems((string) $f['type'], $e[$n]);
        }
        unset($e[self::STATE]);
        return $e;
    }

    /** publicEntry() für eine Liste von Einträgen (Schlüssel bleiben); nicht freigegebene Kontakte fallen weg */
    public static function publicRows(array $t, array $rows, string $target = 'web'): array
    {
        if (!self::affects($t, $target)) return $rows;
        $list = array_is_list($rows);
        foreach ($rows as $k => $e) {
            if (!is_array($e)) continue;
            if (!self::visible($t, $e)) { unset($rows[$k]); continue; }
            $rows[$k] = self::publicEntry($t, $e, $target);
        }
        return $list ? array_values($rows) : $rows;
    }

    /** Auswahl von Feldnamen ohne Felder, die für das Ziel nie erscheinen (Pseudo-Felder wie _title, published_at bleiben) */
    public static function publicNames(array $t, array $names, string $target = 'web'): array
    {
        $out = [];
        foreach ($names as $n) {
            $f = Tables::field($t, (string) $n);
            $r = self::rule($f);
            if ($f !== null && ($r['mode'] === 'never' || ($r['mode'] === 'always' && !$r[$target]))) continue;
            $out[] = $n;
        }
        return $out;
    }

    /**
     * Sieht das Feld nach einer privaten Angabe aus (Mobil, Handy, privat, Geburtstag …) und ist trotzdem immer öffentlich?
     * Kein Hinweis bei „nie“, „Redaktion entscheidet“ und „Bewusst öffentlich“ (public_ok).
     */
    public static function looksPrivate(array $f): bool
    {
        if (self::rule($f)['mode'] !== 'always' || !empty($f['public_ok']) || !in_array($f['type'] ?? '', self::HINT_TYPES, true)) return false;
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
