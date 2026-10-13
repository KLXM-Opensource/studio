<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Kombifelder der Datentabellen: Ansprechperson (person), Fragen & Antworten (faq), Adresse (address), Downloads (downloads).
 * Gespeichert als JSON-Liste wie die Wiederholbare Gruppe; die Eingabe ist ein Wiederholer mit festen Unterfeldern (schema()),
 * bereinigt über Core\Fields (Unterfelder wie in Blöcken: Bild, Datei, formatierter Text …). Auf der Website eine passende
 * Darstellung mit schema.org-Auszeichnung (Microdata, CSP-sicher) – Klassen ct-*, faq*, adr*, dlf* in css/combo.css,
 * von Kits überschreibbar. Suche: text(); Medienverwendung: mediaIds().
 */
final class Combo
{
    public const TYPES = ['person', 'faq', 'address', 'downloads'];

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
            default => [__('Datei'), __('Datei hinzufügen'), 60],
        };
        return ['type' => 'repeater', 'fields' => $fields, 'item_label' => $item, 'add_label' => $add, 'max_items' => $max]
            + ($f['type'] === 'downloads' ? ['pick_many' => 'file'] : []);
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
