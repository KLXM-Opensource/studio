<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Filter für Erweiterungen (Extension::filter): Werte des Cores an festen Stellen ändern, ergänzen oder verwerfen.
 *
 *   $x->filter('search.document', fn(array $doc, array $ctx) => $ctx['type'] === 'entry' && $ctx['table'] === 'intern' ? false : $doc);
 *
 * Regeln: fn($wert, array $ctx) gibt den neuen Wert zurück. null = unverändert (vergessenes return schadet nicht). false =
 * verwerfen – nur wo die Beschreibung es erlaubt. Fehler einer Erweiterung lassen den Wert unverändert (Protokoll). Filter
 * laufen in der Reihenfolge der Erweiterungen. Eigene Filter einer Erweiterung: Name „ext:{erweiterung}.{name}“.
 * Anders als Ereignisse (Extension::on, nur Mitteilung danach) wirken Filter auf das Ergebnis.
 */
final class Filters
{
    /** Name → [Wert, Kontext, Beschreibung] – Grundlage für Doku und Selbsttest */
    public const ALL = [
        'search.document' => ['array $doc (id, type, title, text, url, lang …)', 'type: page|entry|file, page|table|entry|media', 'Dokument für die Website-Suche ändern oder mit false aus der Suche nehmen.'],
        'sitemap.urls' => ['list<array{loc: string, lastmod?: string}>', 'host', 'Adressen der sitemap.xml ergänzen, ändern oder entfernen.'],
        'menu.items' => ['list<array{id, label, href, active, children, draft}> (Baum)', 'drafts: bool', 'Navigation der Kits (Pages::menu) ändern – Einträge ergänzen, umbenennen, ausblenden.'],
        'seo.meta' => ['array{title, description, canonical, og_image, noindex, jsonld}', 'page | entry + table', 'Angaben für den Kopf der Seite (Seo::forPage/forEntry) ändern.'],
        'form.validate' => ['array<string, string> $errors (Feld → Meldung, \'_form\' = allgemein)', 'table, values, ip', 'Eigene Prüfung von Formularen der Website vor dem Speichern – Fehler ergänzen bricht das Absenden ab.'],
        'mail.message' => ['array{subject, text, to, options}', 'account: false', 'Ausgehende E-Mail ändern (z. B. Fußzeile) oder mit false unterdrücken. Mails zur Kontosicherheit (Passwort, Einladung) laufen nie durch den Filter; Test-Umleitung gilt danach weiter.'],
        'data_list.query' => ['array $options für Entries::query (where, order, limit, q …)', 'table, block (Daten des Blocks)', 'Abfrage des Blocks „Datenliste“ ändern (z. B. zusätzliche Bedingung).'],
    ];

    /** Ist der Name erlaubt? Bekannte Filter des Cores oder eigene einer Erweiterung (ext:{name}.…) */
    public static function known(string $name): bool
    {
        return isset(self::ALL[$name]) || (bool) preg_match('~^ext:[a-z0-9_]+\.[a-z0-9_.-]+$~', $name);
    }
}
