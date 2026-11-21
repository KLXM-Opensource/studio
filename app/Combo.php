<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
// Beschriftungen der Mehrfachfelder (LABELS, vCard-TYPE): Teile übernommen aus KLXM/contacts (MIT, © 2026 KLXM Crossmedia GmbH)
declare(strict_types=1);

namespace Core;

/**
 * Kombifelder der Datentabellen: Ansprechperson (person), Fragen & Antworten (faq), Adresse (address), Downloads (downloads) und
 * die beschrifteten Mehrfachfelder (MULTI: phones, emails, addresses, urls) – je Angabe Beschriftung (Mobil, Arbeit, Privat,
 * Geschäftsstelle … oder eigene) und Freigabe „Öffentlich“ (public, Standard aus; Adressen zusätzlich „nur Ort“). Auf der Website
 * erscheinen nur freigegebene Angaben – bereinigt zentral in Core\Data\Exposure::publicEntry(), html()/text() sehen danach nur sie.
 * Gespeichert als JSON-Liste wie die Wiederholbare Gruppe; die Eingabe ist ein Wiederholer mit festen Unterfeldern (schema()),
 * bereinigt über Core\Fields (Unterfelder wie in Blöcken: Bild, Datei, formatierter Text …). Auf der Website eine passende
 * Darstellung mit schema.org-Auszeichnung (Microdata, CSP-sicher) – Klassen ct-*, faq*, adr*, dlf* in css/combo.css,
 * von Kits überschreibbar. Suche: text(); Medienverwendung: mediaIds().
 */
final class Combo
{
    public const TYPES = ['person', 'faq', 'address', 'downloads', 'phones', 'emails', 'addresses', 'urls'];
    /** Beschriftete Mehrfachfelder mit Freigabe je Angabe (Kontakte, auch in normalen Tabellen nutzbar) */
    public const MULTI = ['phones', 'emails', 'addresses', 'urls'];
    /** Einfacher Feldtyp, dem ein Mehrfachfeld entspricht (StructuredData::field, Personen-Block, Visitenkarte) */
    public const COMPAT = ['phones' => 'tel', 'emails' => 'email', 'urls' => 'url', 'addresses' => 'address'];
    /** Eigene Beschriftung (Text im Unterfeld label_custom) */
    public const CUSTOM = 'custom';
    /**
     * Vorgegebene Beschriftungen je Mehrfachfeld (Schlüssel → deutscher Text, übersetzt mit __()/lt()). Reihenfolge = Auswahl im
     * Formular, erste = Standard. Beschriftungen und vCard-TYPE nach KLXM/contacts (ItemKind, VCard/Labels), ergänzt um „Geschäftsstelle“.
     */
    public const LABELS = [
        'phones' => ['mobile' => 'Mobil', 'office' => 'Geschäftsstelle', 'work' => 'Arbeit', 'home' => 'Privat', 'main' => 'Zentrale', 'fax' => 'Fax', 'other' => 'Sonstige'],
        'emails' => ['work' => 'Arbeit', 'office' => 'Geschäftsstelle', 'home' => 'Privat', 'other' => 'Sonstige'],
        'addresses' => ['office' => 'Geschäftsstelle', 'work' => 'Arbeit', 'home' => 'Privat', 'other' => 'Sonstige'],
        'urls' => ['homepage' => 'Website', 'work' => 'Arbeit', 'home' => 'Privat', 'other' => 'Sonstige'],
    ];
    /** vCard-TYPE-Werte je Beschriftung; Beschriftungen ohne Eintrag (und eigene) reisen als Apple-Beschriftung X-ABLabel */
    public const VCARD_TYPES = [
        'phones' => ['mobile' => ['CELL', 'VOICE'], 'home' => ['HOME', 'VOICE'], 'work' => ['WORK', 'VOICE'], 'main' => ['MAIN'], 'fax' => ['WORK', 'FAX'], 'other' => ['OTHER', 'VOICE']],
        'emails' => ['home' => ['INTERNET', 'HOME'], 'work' => ['INTERNET', 'WORK'], 'other' => ['INTERNET']],
        'addresses' => ['home' => ['HOME'], 'work' => ['WORK']],
        'urls' => ['home' => ['HOME'], 'work' => ['WORK']],
    ];

    public static function isMulti(string $type): bool
    {
        return in_array($type, self::MULTI, true);
    }

    /** Beschriftung einer Angabe als Text (vorgegeben übersetzt, eigene wie eingegeben); $site = Sprache der Website (lt) */
    public static function labelText(string $type, array $item, bool $site = false): string
    {
        $k = (string) ($item['label'] ?? '');
        if ($k === self::CUSTOM || ($k !== '' && !isset(self::LABELS[$type][$k]))) {
            return trim((string) ($item['label_custom'] ?? '')) ?: ($k !== self::CUSTOM ? $k : '');
        }
        $de = self::LABELS[$type][$k] ?? (string) (array_values(self::LABELS[$type] ?? [''])[0]);
        return $site ? lt($de) : __($de);
    }

    /**
     * Freigabe einer Angabe: 'full' (öffentlich), 'city' (Adresse: nur Ort) oder '' (intern). Fehlt die Angabe, gilt „intern“ –
     * neue Angaben (auch aus Adressbuch-Apps) sind nie von selbst öffentlich.
     */
    public static function publicScope(string $type, array $item): string
    {
        $p = $item['public'] ?? false;
        if ($type === 'addresses' && $p === 'city') return 'city';
        return in_array($p, [true, 1, '1', 'on', 'true', 'full'], true) ? 'full' : '';
    }

    /** Nur die freigegebenen Angaben (Adressen mit „nur Ort“ ohne Straße/PLZ) – für jede Website-Ausgabe */
    public static function publicItems(string $type, mixed $raw): array
    {
        $out = [];
        foreach (self::items($raw) as $i) {
            $scope = self::publicScope($type, $i);
            if ($scope === '') continue;
            if ($scope === 'city') $i = array_diff_key($i, ['street' => 1, 'zip' => 1]);
            $out[] = $i;
        }
        return $out;
    }

    /** Wert einer Angabe als Text: Telefon/E-Mail/Web = value, Adresse = „Straße, PLZ Ort, Land“ */
    public static function itemText(string $type, array $i): string
    {
        if ($type !== 'addresses') return trim((string) ($i['value'] ?? ''));
        return implode(', ', array_filter([trim((string) ($i['street'] ?? '')), trim(trim((string) ($i['zip'] ?? '')) . ' ' . trim((string) ($i['city'] ?? ''))), trim((string) ($i['country'] ?? ''))]));
    }

    /**
     * Kontakt-Knöpfe aus einem Mehrfachfeld (Telefon, E-Mail, Web) für Profil-/Porträt-Ansichten: [[href, Symbol, Text], …] –
     * wie die einfachen Felder tel/email/url. Bekommt den bereinigten Eintrag (nur freigegebene Angaben).
     */
    public static function actions(string $type, mixed $raw): array
    {
        $out = [];
        foreach (self::items($raw) as $i) {
            $v = trim((string) ($i['value'] ?? ''));
            if ($v === '') continue;
            $href = match ($type) { 'emails' => filter_var($v, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $v : '', 'phones' => (string) tel_href($v), 'urls' => preg_match('~^https?://~i', $v) ? $v : '', default => '' };
            if ($href === '') continue;
            $out[] = [$href, ['emails' => 'envelope-simple', 'phones' => 'phone', 'urls' => 'globe'][$type], $type === 'urls' ? (string) preg_replace('~^https?://(www\.)?~', '', rtrim($v, '/')) : $v];
        }
        return $out;
    }

    /** Erster Wert eines Mehrfachfeldes; $prefer: diese Beschriftungen zuerst (z. B. ['work', 'office']) */
    public static function firstValue(string $type, mixed $raw, array $prefer = []): ?string
    {
        $items = self::items($raw);
        foreach ($prefer as $want) foreach ($items as $i) if (($i['label'] ?? '') === $want && self::itemText($type, $i) !== '') return self::itemText($type, $i);
        foreach ($items as $i) if (self::itemText($type, $i) !== '') return self::itemText($type, $i);
        return null;
    }

    public static function is(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /** Eingabe als Wiederholer (Core\Fields) – Beschriftungen in der Sprache der Verwaltung */
    public static function schema(array $f): array
    {
        $fields = match ($f['type']) {
            'person' => [
                ['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'required' => true, 'width' => 'half'],
                ['name' => 'role', 'label' => __('Funktion'), 'type' => 'text', 'width' => 'half'],
                ['name' => 'photo', 'label' => __('Foto'), 'type' => 'media'],
                ['name' => 'phone', 'label' => __('Telefon'), 'type' => 'tel', 'width' => 'half'],
                ['name' => 'email', 'label' => __('E-Mail'), 'type' => 'email', 'width' => 'half'],
                ['name' => 'note', 'label' => __('Hinweis (z. B. Sprechzeiten)'), 'type' => 'textarea'],
            ],
            'faq' => [
                ['name' => 'question', 'label' => __('Frage'), 'type' => 'text', 'required' => true],
                ['name' => 'answer', 'label' => __('Antwort'), 'type' => 'richtext', 'required' => true],
            ],
            'address' => [
                ['name' => 'label', 'label' => __('Bezeichnung (optional, z. B. Hauptsitz)'), 'type' => 'text'],
                ['name' => 'street', 'label' => __('Straße und Hausnummer'), 'type' => 'text', 'required' => true],
                ['name' => 'zip', 'label' => __('PLZ'), 'type' => 'text', 'width' => 'half'],
                ['name' => 'city', 'label' => __('Ort'), 'type' => 'text', 'required' => true, 'width' => 'half'],
                ['name' => 'country', 'label' => __('Land (optional)'), 'type' => 'text'],
            ],
            'phones', 'emails', 'urls', 'addresses' => self::multiFields($f['type']),
            'downloads' => [
                ['name' => 'file', 'label' => __('Datei'), 'type' => 'file', 'required' => true],
                ['name' => 'title', 'label' => __('Titel (optional – sonst der Name der Datei)'), 'type' => 'text'],
                ['name' => 'note', 'label' => __('Beschreibung (optional)'), 'type' => 'textarea'],
            ],
            default => [],
        };
        [$item, $add, $max] = match ($f['type']) {
            'person' => [__('Person'), __('Person hinzufügen'), 20],
            'faq' => [__('Frage'), __('Frage hinzufügen'), 60],
            'address' => [__('Adresse'), __('Adresse hinzufügen'), 10],
            'phones' => [__('Telefon'), __('Nummer hinzufügen'), 20],
            'emails' => [__('E-Mail'), __('E-Mail hinzufügen'), 20],
            'addresses' => [__('Adresse'), __('Adresse hinzufügen'), 10],
            'urls' => [__('Webadresse'), __('Webadresse hinzufügen'), 20],
            default => [__('Datei'), __('Datei hinzufügen'), 60],
        };
        return ['type' => 'repeater', 'fields' => $fields, 'item_label' => $item, 'add_label' => $add, 'max_items' => $max]
            + ($f['type'] === 'downloads' ? ['pick_many' => 'file'] : [])
            // Mehrfachfelder: kompakte Zeilen (Beschriftung · Wert · Freigabe · Entfernen) wie in Adressbuch-Apps (Fields 'rows')
            + (self::isMulti($f['type']) ? ['rows' => true, 'blank_ignore' => ['label', 'public']] : []);
    }

    /** Unterfelder eines Mehrfachfeldes: Beschriftung (Auswahl + eigene), Wert(e), Freigabe */
    private static function multiFields(string $type): array
    {
        $opts = [];
        foreach (self::LABELS[$type] as $k => $de) $opts[$k] = __($de);
        $opts[self::CUSTOM] = __('Eigene Beschriftung …');
        $fields = [
            ['name' => 'label', 'label' => __('Beschriftung'), 'type' => 'select', 'options' => $opts, 'required' => true, 'default' => array_key_first(self::LABELS[$type])],
        ];
        // Eigene Beschriftung zuletzt: steht in der Zeile unter der Auswahl (nur bei „Eigene Beschriftung …“)
        $custom = ['name' => 'label_custom', 'label' => __('Eigene Beschriftung'), 'type' => 'text', 'max' => 60, 'show_if' => ['label' => [self::CUSTOM]]];
        if ($type === 'addresses') {
            array_push($fields,
                ['name' => 'street', 'label' => __('Straße und Hausnummer'), 'type' => 'text'],
                ['name' => 'zip', 'label' => __('PLZ'), 'type' => 'text', 'width' => 'half'],
                ['name' => 'city', 'label' => __('Ort'), 'type' => 'text', 'width' => 'half'],
                ['name' => 'country', 'label' => __('Land (optional)'), 'type' => 'text'],
                self::exposeField(['1' => __('Öffentlich'), 'city' => __('Nur Ort'), '0' => __('Intern')]),
                $custom);
            return $fields;
        }
        $fields[] = ['name' => 'value', 'label' => match ($type) { 'phones' => __('Nummer'), 'emails' => __('E-Mail-Adresse'), default => __('Webadresse') },
            'type' => match ($type) { 'phones' => 'tel', 'emails' => 'email', default => 'url' }, 'required' => true];
        $fields[] = self::exposeField(['1' => __('Öffentlich'), '0' => __('Intern')]);
        $fields[] = $custom;
        return $fields;
    }

    /**
     * Freigabe je Angabe: Auswahl (1 = öffentlich, city = nur Ort, 0 = intern), im Formular als Merkmal rechts in der Zeile
     * (Core\Fields 'expose_item' – dasselbe Bedienelement wie die Freigabe am Feld). Ältere Werte true/false bleiben lesbar.
     */
    private static function exposeField(array $opts): array
    {
        return ['name' => 'public', 'label' => __('Sichtbarkeit'), 'type' => 'select', 'options' => $opts, 'default' => '0', 'expose_item' => true];
    }

    /** Gespeicherter Wert (JSON oder Liste) → Liste der Einträge */
    public static function items(mixed $raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /** Darstellung auf der Website */
    public static function html(array $f, mixed $raw): string
    {
        $items = self::items($raw);
        if (!$items) return '';
        return match ($f['type']) {
            'person' => self::people($items),
            'faq' => self::faq($items),
            'address' => self::addresses($items),
            'downloads' => self::downloads($items),
            'phones', 'emails', 'urls', 'addresses' => self::multi($f['type'], $items),
            default => '',
        };
    }

    /** Kurzfassung für Listen der Verwaltung, z. B. „Erika Musterfrau, Max Mustermann“ bzw. „3 Fragen“ */
    public static function summary(array $f, mixed $raw): string
    {
        $items = self::items($raw);
        if (!$items) return '';
        return match ($f['type']) {
            'person' => implode(', ', array_filter(array_map(fn($i) => trim((string) ($i['name'] ?? '')), $items))),
            'faq' => count($items) === 1 ? __('1 Frage') : __('{n} Fragen', ['n' => count($items)]),
            'address' => implode(' · ', array_map(fn($i) => trim(($i['zip'] ?? '') . ' ' . ($i['city'] ?? '')), $items)),
            'downloads' => count($items) === 1 ? __('1 Datei') : __('{n} Dateien', ['n' => count($items)]),
            'phones', 'emails', 'urls', 'addresses' => mb_strimwidth(implode(' · ', array_filter(array_map(fn($i) => self::itemText($f['type'], $i), $items))), 0, 80, '…'),
            default => '',
        };
    }

    /** Text für die Suche (Website-Suche, Suche in Listen) */
    public static function text(array $f, mixed $raw): string
    {
        $parts = [];
        foreach (self::items($raw) as $i) {
            $parts[] = match ($f['type']) {
                'person' => implode(' ', [$i['name'] ?? '', $i['role'] ?? '', $i['note'] ?? '']),
                'faq' => ($i['question'] ?? '') . ' ' . Search\Text::plain((string) ($i['answer'] ?? '')),
                'address' => implode(' ', [$i['label'] ?? '', $i['street'] ?? '', $i['zip'] ?? '', $i['city'] ?? '', $i['country'] ?? '']),
                'downloads' => implode(' ', [self::fileTitle($i), $i['note'] ?? '']),
                'phones', 'emails', 'urls', 'addresses' => self::itemText($f['type'], $i),
                default => '',
            };
        }
        return trim((string) preg_replace('~\s+~u', ' ', implode(' ', $parts)));
    }

    /** Verwendete Medien (Fotos, Dateien) – für „Wird verwendet“ und die Suche nach verwendeten PDFs */
    public static function mediaIds(array $f, mixed $raw): array
    {
        $key = ['person' => 'photo', 'downloads' => 'file'][$f['type']] ?? null;
        if ($key === null) return [];
        return array_values(array_filter(array_map(fn($i) => (int) ($i[$key] ?? 0), self::items($raw))));
    }

    // ------------------------------------------------------------------ Darstellungen

    private static function people(array $items): string
    {
        $h = '<div class="ct-people">';
        foreach ($items as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') continue;
            $photo = !empty($p['photo']) && ($m = Media::find((int) $p['photo'])) ? Media::pictureOf($m, '(min-width: 700px) 160px, 120px', ['ratio' => '1:1', 'alt' => $name]) : '';
            $tel = trim((string) ($p['phone'] ?? ''));
            $mail = trim((string) ($p['email'] ?? ''));
            $h .= '<div class="ct-person" itemscope itemtype="https://schema.org/Person">'
                . ($photo !== '' ? '<div class="ct-person__photo">' . $photo . '</div>' : '')
                . '<div class="ct-person__body"><p class="ct-person__name" itemprop="name">' . e($name) . '</p>'
                . (($r = trim((string) ($p['role'] ?? ''))) !== '' ? '<p class="ct-person__role" itemprop="jobTitle">' . e($r) . '</p>' : '')
                . ($tel !== '' && ($href = tel_href($tel)) ? '<p class="ct-person__line">' . icon('phone', ['label' => lt('Telefon')]) . ' <a href="' . e($href) . '" itemprop="telephone">' . e($tel) . '</a></p>' : '')
                . ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) ? '<p class="ct-person__line">' . icon('envelope-simple', ['label' => lt('E-Mail')]) . ' <a href="mailto:' . e($mail) . '" itemprop="email">' . e($mail) . '</a></p>' : '')
                . (($n = trim((string) ($p['note'] ?? ''))) !== '' ? '<p class="ct-person__note">' . nl2br(e($n), false) . '</p>' : '')
                . '</div></div>';
        }
        return $h . '</div>';
    }

    /** Akkordeon aus <details> (ohne Skript, Tastatur und Screenreader verstehen es) – schema.org FAQPage */
    private static function faq(array $items): string
    {
        $h = '<div class="faq" itemscope itemtype="https://schema.org/FAQPage">';
        foreach ($items as $i) {
            $q = trim((string) ($i['question'] ?? ''));
            if ($q === '') continue;
            $h .= '<details class="faq__item" itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">'
                . '<summary class="faq__q"><span itemprop="name">' . e($q) . '</span></summary>'
                . '<div class="faq__a" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer"><div class="prose" itemprop="text">' . rich((string) ($i['answer'] ?? '')) . '</div></div>'
                . '</details>';
        }
        return $h . '</div>';
    }

    /** Postanschrift (schema.org PostalAddress) mit Link „Auf der Karte ansehen“ (OpenStreetMap, öffnet erst beim Klick) */
    private static function addresses(array $items): string
    {
        $h = '<div class="adrs">';
        foreach ($items as $a) {
            $street = trim((string) ($a['street'] ?? ''));
            $city = trim((string) ($a['city'] ?? ''));
            if ($street === '' && $city === '') continue;
            $zip = trim((string) ($a['zip'] ?? ''));
            $country = trim((string) ($a['country'] ?? ''));
            $q = implode(', ', array_filter([$street, trim($zip . ' ' . $city), $country]));
            $map = 'https://www.openstreetmap.org/search?query=' . rawurlencode($q);
            $h .= '<address class="adr" itemscope itemtype="https://schema.org/PostalAddress">'
                . (($l = trim((string) ($a['label'] ?? ''))) !== '' ? '<strong class="adr__label">' . e($l) . '</strong>' : '')
                . ($street !== '' ? '<span itemprop="streetAddress">' . e($street) . '</span>' : '')
                . '<span>' . ($zip !== '' ? '<span itemprop="postalCode">' . e($zip) . '</span> ' : '') . '<span itemprop="addressLocality">' . e($city) . '</span></span>'
                . ($country !== '' ? '<span itemprop="addressCountry">' . e($country) . '</span>' : '')
                . '<a class="adr__map" href="' . e($map) . '"' . ext_attrs($map) . '>' . icon('map-pin') . ' ' . e(lt('Auf der Karte ansehen')) . '</a>'
                . '</address>';
        }
        return $h . '</div>';
    }

    /**
     * Beschriftete Angaben als Liste (Beschriftung + Link): tel:, mailto:, Webadresse, Postanschrift (schema.org PostalAddress).
     * Bekommt auf der Website nur freigegebene Angaben (Exposure::publicEntry) – die Freigabe selbst steht nicht im HTML.
     */
    private static function multi(string $type, array $items): string
    {
        $h = '';
        foreach ($items as $i) {
            $text = self::itemText($type, $i);
            if ($text === '') continue;
            $val = match ($type) {
                'phones' => ($href = tel_href($text)) ? '<a href="' . e($href) . '">' . e($text) . '</a>' : e($text),
                'emails' => filter_var($text, FILTER_VALIDATE_EMAIL) ? '<a href="mailto:' . e($text) . '">' . e($text) . '</a>' : e($text),
                'urls' => preg_match('~^https?://~i', $text) ? '<a href="' . e($text) . '"' . ext_attrs($text) . '>' . e((string) preg_replace('~^https?://(www\.)?~i', '', rtrim($text, '/'))) . '</a>' : e($text),
                default => '<span itemscope itemtype="https://schema.org/PostalAddress">'
                    . (($st = trim((string) ($i['street'] ?? ''))) !== '' ? '<span itemprop="streetAddress">' . e($st) . '</span>, ' : '')
                    . (($z = trim((string) ($i['zip'] ?? ''))) !== '' ? '<span itemprop="postalCode">' . e($z) . '</span> ' : '')
                    . '<span itemprop="addressLocality">' . e(trim((string) ($i['city'] ?? ''))) . '</span>'
                    . (($c = trim((string) ($i['country'] ?? ''))) !== '' ? ', <span itemprop="addressCountry">' . e($c) . '</span>' : '') . '</span>',
            };
            $h .= '<div class="ctm__item"><dt class="ctm__label">' . e(self::labelText($type, $i, true)) . '</dt><dd class="ctm__value">' . $val . '</dd></div>';
        }
        return $h !== '' ? '<dl class="ctm ctm--' . e($type) . '">' . $h . '</dl>' : '';
    }

    /** Dateiliste: Titel (PDF im Viewer), Art und Größe, Beschreibung */
    private static function downloads(array $items): string
    {
        $h = '<ul class="dlf" role="list">';
        foreach ($items as $i) {
            $m = !empty($i['file']) ? Media::find((int) $i['file']) : null;
            if (!$m) continue;
            $pdf = $m['mime'] === 'application/pdf';
            $href = $pdf ? (string) (Media::viewerUrl($m) ?? Media::url($m)) : Media::url($m);
            $thumb = $pdf ? PdfLinks::thumb($m, 160) : null;   // erste Seite als Vorschau (sonst Symbol)
            $h .= '<li class="dlf__item">' . ($thumb !== null ? '<img class="dlf__thumb" src="' . e($thumb) . '" alt="" width="56" height="72" loading="lazy">' : '<span class="dlf__ico" aria-hidden="true">' . icon($pdf ? 'file-pdf' : 'file') . '</span>')
                . '<span class="dlf__body"><a class="dlf__link" href="' . e($href) . '"' . ($pdf ? PdfLinks::attrs($m) : ' download') . '>' . e(self::fileTitle($i, $m)) . '</a>'
                . '<span class="dlf__meta">' . e(Media::typeLabel((string) $m['mime']) . ' · ' . Media::humanSize((int) $m['size'])) . '</span>'
                . (($n = trim((string) ($i['note'] ?? ''))) !== '' ? '<span class="dlf__note">' . nl2br(e($n), false) . '</span>' : '')
                . '</span></li>';
        }
        return $h . '</ul>';
    }

    private static function fileTitle(array $i, ?array $m = null): string
    {
        $t = trim((string) ($i['title'] ?? ''));
        if ($t !== '') return $t;
        $m ??= !empty($i['file']) ? Media::find((int) $i['file']) : null;
        return $m ? Media::displayName($m) : '';
    }

    /** Selbsttest (Teil von `php bin/console data:selftest`): Darstellung, Escaping, schema.org, Suche, Kurzfassung – ohne Datenbank */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $is = function (string $what, bool $cond) use (&$ok, &$fails): void {
            if ($cond) $ok++; else $fails[] = 'Kombifelder – ' . $what;
        };
        $p = ['type' => 'person'];
        $h = self::html($p, json_encode([['name' => 'Erika <b>Muster</b>', 'role' => 'Leitung', 'phone' => '0201 123', 'email' => 'x" onclick="a', 'note' => "Zeile 1\nZeile 2"], ['name' => '']]));
        $is('Person: Name escaped', str_contains($h, 'Erika &lt;b&gt;Muster&lt;/b&gt;'));
        $is('Person: schema.org Person', substr_count($h, 'schema.org/Person') === 1);
        $is('Person: Telefon als tel-Link', str_contains($h, 'href="tel:'));
        $is('Person: ungültige E-Mail ohne Link', !str_contains($h, 'mailto:') && !str_contains($h, 'onclick'));
        $is('Person: Hinweis mit Zeilenumbruch', str_contains($h, 'Zeile 1<br>'));
        $f = ['type' => 'faq'];
        $h = self::html($f, [['question' => 'Wie?', 'answer' => '<p>So.</p>'], ['question' => '', 'answer' => '<p>x</p>']]);
        $is('FAQ: FAQPage einmal, eine Frage', substr_count($h, 'schema.org/FAQPage') === 1 && substr_count($h, 'schema.org/Question') === 1);
        $is('FAQ: <details>/<summary>', str_contains($h, '<details class="faq__item"') && str_contains($h, '<summary class="faq__q">'));
        $is('FAQ: Suche mit Antworttext', self::text($f, [['question' => 'Wie?', 'answer' => '<p>So <b>geht</b> es.</p>']]) === 'Wie? So geht es.');
        $is('FAQ: Kurzfassung', self::summary($f, [['question' => 'a'], ['question' => 'b']]) !== '');
        $a = ['type' => 'address'];
        $h = self::html($a, [['street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Musterstadt']]);
        $is('Adresse: PostalAddress', str_contains($h, 'schema.org/PostalAddress') && str_contains($h, 'itemprop="postalCode">12345'));
        $is('Adresse: Kartenlink kodiert', str_contains($h, 'openstreetmap.org/search?query=Musterstra%C3%9Fe%201%2C%2012345%20Musterstadt'));
        $is('Adresse: leer → nichts', self::html($a, [['label' => 'nur Name']]) === '<div class="adrs"></div>');
        $is('Ungültiges JSON → leer', self::items('{kaputt') === [] && self::html($a, '{kaputt') === '');
        $is('Medien: Foto und Dateien', self::mediaIds($p, [['photo' => 7], ['photo' => 0]]) === [7] && self::mediaIds(['type' => 'downloads'], [['file' => 3]]) === [3]);
        $is('Schema: Wiederholer, Downloads mit Mehrfachauswahl', self::schema(['type' => 'downloads'])['type'] === 'repeater' && self::schema(['type' => 'downloads'])['pick_many'] === 'file');
        return ['ok' => $ok, 'fails' => $fails];
    }
}
