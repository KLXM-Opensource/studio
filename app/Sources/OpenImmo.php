<?php
declare(strict_types=1);

namespace Core\Sources;

/**
 * OpenImmo (XML-Standard der Immobilienwirtschaft, Version 1.2.x) – Einträge = <immobilie>.
 *
 * Zusätzlich zu den Rohdaten berechnete Felder (Pfade beginnen mit „_“):
 *   _id            openimmo_obid, sonst objektnr_extern bzw. objektnr_intern (eindeutiger Schlüssel)
 *   _objektnummer  objektnr_extern, sonst objektnr_intern
 *   _objektart     Wohnung, Haus, Grundstück … (Name des Kindelements von objektart, deutsch)
 *   _objekttyp     Untertyp (z. B. ETAGE, EINFAMILIENHAUS) aus dem Attribut *typ
 *   _vermarktungsart  kauf | miete | erbpacht | leasing
 *   _preis         Kaufpreis bzw. Kaltmiete (Zahl)
 *   _adresse_frei  Adresse darf veröffentlicht werden (verwaltung_objekt.objektadresse_freigeben; fehlt → nein)
 *   _adresse       „Straße Nr, PLZ Ort“ bzw. ohne Straße, wenn die Adresse nicht freigegeben ist
 *   _kontakt       „Vorname Name“ der Kontaktperson
 *   _bilder        Anhänge (Titelbild zuerst): [{pfad, titel, gruppe}, …] – Pfad = Dateiname im ZIP oder https-Adresse
 *   _loeschen      true bei verwaltung_techn.aktion aktionart="DELETE"
 * Datenschutz: Ist objektadresse_freigeben nicht „true“/„1“, entfernt der Parser Straße, Hausnummer und
 * Geokoordinaten aus den Daten – keine Zuordnung kann sie dann versehentlich veröffentlichen.
 * Übertragung umfang="TEIL" (Teilabgleich): fehlende Objekte bleiben unangetastet (meta.partial).
 */
final class OpenImmo
{
    public const OBJEKTARTEN = [
        'zimmer' => 'Zimmer', 'wohnung' => 'Wohnung', 'haus' => 'Haus', 'grundstueck' => 'Grundstück', 'buero_praxen' => 'Büro/Praxis',
        'einzelhandel' => 'Einzelhandel', 'gastgewerbe' => 'Gastgewerbe', 'hallen_lager_prod' => 'Halle/Lager/Produktion',
        'land_und_forstwirtschaft' => 'Land- und Forstwirtschaft', 'parken' => 'Parken', 'sonstige' => 'Sonstige',
        'freizeitimmobilie_gewerblich' => 'Freizeitimmobilie', 'zinshaus_renditeobjekt' => 'Zinshaus/Renditeobjekt',
    ];

    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    public const MAX_ZIP_FILES = 800;
    public const MAX_ZIP_BYTES = 300 * 1024 * 1024;

    /** @return array{items: list<array>, meta: array} */
    public static function parse(string $body): array
    {
        $doc = Parser::xmlDoc($body);
        $root = $doc->documentElement;
        if (strtolower($root->localName) !== 'openimmo') {
            throw new SourceException(__('Keine OpenImmo-Datei (Wurzelelement „{name}“ statt „openimmo“).', ['name' => $root->nodeName]));
        }
        $meta = ['format' => 'openimmo', 'partial' => false];
        foreach ($doc->getElementsByTagName('uebertragung') as $u) {
            $meta['partial'] = strtoupper($u->getAttribute('umfang')) === 'TEIL';
            $meta['version'] = $u->getAttribute('version');
            break;
        }
        $items = [];
        foreach ($doc->getElementsByTagName('immobilie') as $el) {
            $raw = Parser::node($el);
            if (!is_array($raw)) continue;
            $items[] = self::enrich($raw);
        }
        return ['items' => $items, 'meta' => $meta];
    }

    private static function truthy(mixed $v): bool
    {
        return in_array(strtolower(trim((string) Parser::scalar($v))), ['1', 'true', 'ja', 'yes'], true);
    }

    private static function enrich(array $i): array
    {
        $tech = (array) ($i['verwaltung_techn'] ?? []);
        $nr = trim((string) (Parser::scalar($tech['objektnr_extern'] ?? null) ?? '')) ?: trim((string) (Parser::scalar($tech['objektnr_intern'] ?? null) ?? ''));
        $i['_objektnummer'] = $nr;
        $i['_id'] = trim((string) (Parser::scalar($tech['openimmo_obid'] ?? null) ?? '')) ?: $nr;
        $i['_loeschen'] = strtoupper((string) (Parser::get($tech, 'aktion@aktionart') ?? '')) === 'DELETE';

        // Objektart: Kindelement von objektkategorie.objektart, Untertyp aus dessen *typ-Attribut
        $art = Parser::get($i, 'objektkategorie.objektart');
        $i['_objektart'] = $i['_objekttyp'] = '';
        if (is_array($art)) {
            foreach ($art as $k => $v) {
                if (str_starts_with((string) $k, '@') || $k === '#text') continue;
                $i['_objektart'] = self::OBJEKTARTEN[$k] ?? ucfirst(str_replace('_', ' ', (string) $k));
                if (is_array($v)) foreach ($v as $ak => $av) if (str_starts_with((string) $ak, '@') && str_ends_with((string) $ak, 'typ')) { $i['_objekttyp'] = (string) $av; break; }
                break;
            }
        }
        $vm = (array) (Parser::get($i, 'objektkategorie.vermarktungsart') ?? []);
        $i['_vermarktungsart'] = match (true) {
            self::truthy($vm['@KAUF'] ?? null) => 'kauf',
            self::truthy($vm['@MIETE_PACHT'] ?? null) => 'miete',
            self::truthy($vm['@ERBPACHT'] ?? null) => 'erbpacht',
            self::truthy($vm['@LEASING'] ?? null) => 'leasing',
            default => '',
        };
        $preise = (array) ($i['preise'] ?? []);
        $i['_preis'] = Parser::scalar($preise['kaufpreis'] ?? null) ?? Parser::scalar($preise['kaltmiete'] ?? null) ?? Parser::scalar($preise['nettokaltmiete'] ?? null) ?? Parser::scalar($preise['warmmiete'] ?? null) ?? '';

        // Adresse nur mit Freigabe veröffentlichen
        $frei = self::truthy(Parser::get($i, 'verwaltung_objekt.objektadresse_freigeben'));
        $i['_adresse_frei'] = $frei;
        if (!$frei && isset($i['geo']) && is_array($i['geo'])) {
            unset($i['geo']['strasse'], $i['geo']['hausnummer'], $i['geo']['geokoordinaten']);
        }
        $geo = (array) ($i['geo'] ?? []);
        $street = trim(Parser::scalar($geo['strasse'] ?? null) . ' ' . Parser::scalar($geo['hausnummer'] ?? null));
        $city = trim(Parser::scalar($geo['plz'] ?? null) . ' ' . Parser::scalar($geo['ort'] ?? null));
        $i['_adresse'] = trim(($frei && $street !== '' ? $street . ', ' : '') . $city, ', ');
        $lat = $frei ? Parser::get($geo, 'geokoordinaten@breitengrad') : null;
        $lon = $frei ? Parser::get($geo, 'geokoordinaten@laengengrad') : null;
        $i['_geo'] = $lat !== null && $lon !== null && is_numeric($lat) && is_numeric($lon) ? $lat . ', ' . $lon : '';

        $k = (array) ($i['kontaktperson'] ?? []);
        $i['_kontakt'] = trim(Parser::scalar($k['vorname'] ?? null) . ' ' . Parser::scalar($k['name'] ?? null));

        // Anhänge: Titelbild zuerst, dann Bilder, dann übrige (Grundrisse …); nur Bildformate
        $list = Parser::get($i, 'anhaenge.anhang[*]') ?? [];
        $pics = [];
        foreach ((array) $list as $a) {
            if (!is_array($a)) continue;
            $pfad = trim((string) (Parser::get($a, 'daten.pfad') ?? ''));
            if ($pfad === '') continue;
            $fmt = strtolower((string) (Parser::scalar($a['format'] ?? null) ?? ''));
            $ext = strtolower(pathinfo(parse_url($pfad, PHP_URL_PATH) ?: $pfad, PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMAGE_EXT, true) && !preg_match('~(jpe?g|png|webp|gif)~', $fmt)) continue;
            $g = strtoupper((string) ($a['@gruppe'] ?? ''));
            $pics[] = ['pfad' => $pfad, 'titel' => trim((string) (Parser::scalar($a['anhangtitel'] ?? null) ?? '')), 'gruppe' => $g,
                'rang' => match ($g) { 'TITELBILD' => 0, 'BILD', 'INNENANSICHTEN', 'AUSSENANSICHTEN' => 1, default => 2 }];
        }
        usort($pics, fn($a, $b) => $a['rang'] <=> $b['rang']);
        $i['_bilder'] = array_map(fn($p) => array_diff_key($p, ['rang' => 1]), $pics);
        return $i;
    }

    // ------------------------------------------------------------------ ZIP

    /**
     * ZIP (openimmo.xml + Bilder) sicher entpacken: nur eine XML-Datei und Bilder, flach (nur Dateinamen – kein Zip-Slip),
     * begrenzte Anzahl/Größe (Zip-Bombe), keine symbolischen Links.
     * @return array{xml: string, dir: string, files: array<string, string>} files: kleingeschriebener Dateiname → Pfad
     * @throws SourceException
     */
    public static function unzip(string $zipFile, string $targetDir): array
    {
        if (!class_exists(\ZipArchive::class)) throw new SourceException(__('Auf dem Server fehlt die PHP-Erweiterung zip.'));
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) throw new SourceException(__('Die ZIP-Datei lässt sich nicht öffnen.'));
        try {
            if ($zip->numFiles > self::MAX_ZIP_FILES) throw new SourceException(__('Die ZIP-Datei enthält zu viele Dateien (max. {n}).', ['n' => self::MAX_ZIP_FILES]));
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0770, true)) throw new SourceException(__('Temporärer Ordner lässt sich nicht anlegen.'));
            $total = 0;
            $xml = null;
            $files = [];
            for ($n = 0; $n < $zip->numFiles; $n++) {
                $st = $zip->statIndex($n);
                if (!$st) continue;
                $name = (string) $st['name'];
                if (str_ends_with($name, '/')) continue;
                // Zip-Slip: absolute Pfade, „..“, Laufwerke und Steuerzeichen ablehnen; gespeichert wird ohnehin nur der Dateiname
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\') || preg_match('~^[a-z]:~i', $name) || preg_match('~[\x00-\x1f]~', $name)) {
                    throw new SourceException(__('Die ZIP-Datei enthält einen unzulässigen Pfad ({name}).', ['name' => mb_substr($name, 0, 80)]));
                }
                // Symbolische Links (Unix-Attribute) überspringen
                if ($zip->getExternalAttributesIndex($n, $opsys, $attr) && $opsys === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) continue;
                $base = basename($name);
                if ($base === '' || str_starts_with($base, '.') || str_starts_with($name, '__MACOSX/')) continue;
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                $isXml = $ext === 'xml';
                if (!$isXml && !in_array($ext, self::IMAGE_EXT, true)) continue;
                $size = (int) $st['size'];
                $total += $size;
                if ($total > self::MAX_ZIP_BYTES || ($st['comp_size'] > 0 && $size / max(1, (int) $st['comp_size']) > 200 && $size > 10 * 1024 * 1024)) {
                    throw new SourceException(__('Die ZIP-Datei ist entpackt zu groß (max. {mb} MB).', ['mb' => (int) (self::MAX_ZIP_BYTES / 1048576)]));
                }
                $safe = preg_replace('~[^\w.\-]+~u', '_', $base);
                $dest = $targetDir . '/' . ($isXml ? 'openimmo.xml' : sprintf('%04d_', $n) . $safe);
                if ($isXml && $xml !== null) continue;   // nur die erste XML-Datei
                $in = $zip->getStream($name);
                if (!$in) continue;
                $out = fopen($dest, 'wb');
                $written = 0;
                while (!feof($in)) {
                    $chunk = (string) fread($in, 65536);
                    $written += strlen($chunk);
                    if ($written > $size + 1024 || $written > 50 * 1024 * 1024) { fclose($in); fclose($out); @unlink($dest); throw new SourceException(__('Eine Datei in der ZIP-Datei ist zu groß.')); }
                    fwrite($out, $chunk);
                }
                fclose($in);
                fclose($out);
                if ($isXml) $xml = $dest; else $files[mb_strtolower($base)] = $dest;
            }
        } finally {
            $zip->close();
        }
        if ($xml === null) throw new SourceException(__('In der ZIP-Datei fehlt die OpenImmo-XML-Datei.'));
        return ['xml' => $xml, 'dir' => $targetDir, 'files' => $files];
    }

    // ------------------------------------------------------------------ Vorlage „Immobilien“

    /** Tabellen-Definition für Tables::validate (Vorlage „Immobilien“) */
    public static function tableDef(): array
    {
        $f = fn(string $label, string $type = 'text', array $x = []) => ['label' => $label, 'type' => $type] + $x;
        return [
            'name' => 'Immobilien', 'singular' => 'Immobilie', 'icon' => 'house-line', 'handle' => 'immobilien',
            'description' => 'Aus einer OpenImmo-Quelle (Externe Quellen)',
            'fields' => [
                $f('Titel', 'text', ['required' => 1, 'in_list' => 1, 'searchable' => 1]),
                $f('Objektnummer', 'text', ['in_list' => 1, 'width' => 'half']),
                $f('Objektart', 'text', ['in_list' => 1, 'width' => 'half']),
                $f('Vermarktungsart', 'select', ['width' => 'half', 'options' => "kauf=Kauf\nmiete=Miete\nerbpacht=Erbpacht\nleasing=Leasing"]),
                $f('Preis', 'number', ['in_list' => 1, 'width' => 'half', 'help' => 'Kaufpreis bzw. Kaltmiete in Euro']),
                $f('Kaltmiete', 'number', ['width' => 'half']),
                $f('Nebenkosten', 'number', ['width' => 'half']),
                $f('Wohnfläche', 'number', ['width' => 'half', 'help' => 'm²']),
                $f('Zimmer', 'number', ['width' => 'half']),
                $f('PLZ', 'text', ['width' => 'half']),
                $f('Ort', 'text', ['in_list' => 1, 'width' => 'half', 'searchable' => 1]),
                $f('Straße', 'text', ['help' => 'Nur, wenn die Adresse freigegeben ist']),
                $f('Adresse', 'text'),
                $f('Titelbild', 'media'),
                $f('Bild 2', 'media', ['width' => 'half']),
                $f('Bild 3', 'media', ['width' => 'half']),
                $f('Beschreibung', 'richtext', ['searchable' => 1]),
                $f('Lage', 'richtext'),
                $f('Ausstattung', 'richtext'),
                $f('Baujahr', 'text', ['width' => 'half']),
                $f('Energieausweis', 'text', ['width' => 'half', 'help' => 'Art: Verbrauch oder Bedarf']),
                $f('Energiekennwert', 'text', ['width' => 'half', 'help' => 'kWh/(m²·a)']),
                $f('Energieträger', 'text', ['width' => 'half']),
                $f('Energieeffizienzklasse', 'text', ['width' => 'half']),
                $f('Energieausweis gültig bis', 'text', ['width' => 'half']),
                $f('Kontaktperson', 'text', ['width' => 'half']),
                $f('Kontakt E-Mail', 'email', ['width' => 'half']),
                $f('Kontakt Telefon', 'tel', ['width' => 'half']),
            ],
            'settings' => ['route' => 'immobilien', 'title_field' => 'titel', 'image_field' => 'titelbild', 'description_field' => 'objektart',
                'sort_field' => 'updated_at', 'sort_dir' => 'desc', 'workflow' => 1, 'schema_type' => ''],
        ];
    }

    /** Detailseite: Kopf, Eckdaten (mit Beschriftung), Texte, weitere Bilder, Energieausweis und Kontakt */
    public static function templateBlocks(array $t): array
    {
        $h = $t['handle'] . '.';
        $have = array_column($t['fields'], 'name');
        $pick = fn(array $names) => array_values(array_map(fn($n) => $h . $n, array_filter($names, fn($n) => in_array($n, $have, true) || $n === '_title')));
        $id = fn() => bin2hex(random_bytes(5));
        $route = (string) ($t['settings']['route'] ?? '');
        return [
            ['id' => $id(), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'head', 'fields' => $pick(['_title', 'objektart', 'vermarktungsart', 'ort', 'titelbild']),
                'back_label' => 'Zur Übersicht', 'back_link' => $route !== '' ? '/' . $route : '/', 'ratio' => '3:2'], 'tunes' => ['section' => ['spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'dl', 'fields' => $pick(['preis', 'kaltmiete', 'nebenkosten', 'wohnflaeche', 'zimmer', 'adresse', 'baujahr', 'objektnummer'])],
                'tunes' => ['section' => ['spaceTop' => 'none', 'spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'prose', 'show_labels' => true, 'fields' => $pick(['beschreibung', 'lage', 'ausstattung', 'bild_2', 'bild_3']), 'ratio' => '3:2'],
                'tunes' => ['section' => ['spaceTop' => 'none', 'spaceBottom' => 'small']]],
            ['id' => $id(), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'dl', 'fields' => $pick(['energieausweis', 'energiekennwert', 'energietraeger', 'energieeffizienzklasse', 'energieausweis_gueltig_bis', 'kontaktperson', 'kontakt_e_mail', 'kontakt_telefon'])],
                'tunes' => ['section' => ['spaceTop' => 'none']]],
        ];
    }

    /** Zuordnung passend zu tableDef() */
    public static function mapping(): array
    {
        $r = fn(string $path, string $tx = '', string $opt = '', array $x = []) => ['path' => $path, 'tx' => $tx, 'opt' => $opt] + $x;
        return [
            'id_path' => '_id',
            'rows' => [
                'titel' => $r('freitexte.objekttitel', 'text', '', ['default' => 'Immobilie']),
                'objektnummer' => $r('_objektnummer'),
                'objektart' => $r('_objektart'),
                'vermarktungsart' => $r('_vermarktungsart'),
                'preis' => $r('_preis', 'number'),
                'kaltmiete' => $r('preise.kaltmiete', 'number'),
                'nebenkosten' => $r('preise.nebenkosten', 'number'),
                'wohnflaeche' => $r('flaechen.wohnflaeche', 'number'),
                'zimmer' => $r('flaechen.anzahl_zimmer', 'number'),
                'plz' => $r('geo.plz'),
                'ort' => $r('geo.ort'),
                'strasse' => $r('', 'template', '{geo.strasse} {geo.hausnummer}'),
                'adresse' => $r('_adresse'),
                'titelbild' => $r('_bilder[0].pfad', '', '', ['alt' => '_bilder[0].titel']),
                'bild_2' => $r('_bilder[1].pfad', '', '', ['alt' => '_bilder[1].titel']),
                'bild_3' => $r('_bilder[2].pfad', '', '', ['alt' => '_bilder[2].titel']),
                'beschreibung' => $r('freitexte.objektbeschreibung', 'html'),
                'lage' => $r('freitexte.lage', 'html'),
                'ausstattung' => $r('freitexte.ausstatt_beschr', 'html'),
                'baujahr' => $r('zustand_angaben.baujahr'),
                'energieausweis' => $r('zustand_angaben.energiepass.epart', '', '', ['lookup' => "VERBRAUCH=Verbrauchsausweis\nBEDARF=Bedarfsausweis"]),
                'energiekennwert' => $r('zustand_angaben.energiepass.energieverbrauchkennwert | zustand_angaben.energiepass.endenergiebedarf'),
                'energietraeger' => $r('zustand_angaben.energiepass.primaerenergietraeger'),
                'energieeffizienzklasse' => $r('zustand_angaben.energiepass.wertklasse'),
                'energieausweis_gueltig_bis' => $r('zustand_angaben.energiepass.gueltig_bis', 'date', 'Y-m-d|d.m.Y'),
                'kontaktperson' => $r('_kontakt'),
                'kontakt_e_mail' => $r('kontaktperson.email_direkt | kontaktperson.email_zentrale'),
                'kontakt_telefon' => $r('kontaktperson.tel_durchw | kontaktperson.tel_zentrale'),
            ],
        ];
    }
}
