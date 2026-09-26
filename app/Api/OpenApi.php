<?php
declare(strict_types=1);

namespace Core\Api;

/** OpenAPI-3.1-Beschreibung der REST-API (GET /api/v1/openapi.json). */
final class OpenApi
{
    public static function spec(): array
    {
        $ref = fn(string $n) => ['$ref' => "#/components/schemas/$n"];
        $json = fn(array $schema) => ['content' => ['application/json' => ['schema' => $schema]]];
        $ok = fn(array $schema, string $desc = 'OK') => ['description' => $desc] + $json(['type' => 'object', 'properties' => ['data' => $schema]]);
        $err = ['401' => ['$ref' => '#/components/responses/Unauthorized'], '422' => ['$ref' => '#/components/responses/Invalid']];
        $pageParam = ['name' => 'page', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'],
            'description' => 'Seiten-ID, Slug (z. B. „impressum“) oder „home“'];
        $blockParam = ['name' => 'block', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Block-ID'];
        $upOpt = ['alt' => ['type' => 'string', 'description' => 'Alt-Text – Pflicht bei Bildern (mind. 3 Zeichen)'], 'decorative' => ['type' => 'boolean'],
            'title' => ['type' => 'string'], 'tags' => ['type' => 'string', 'description' => 'Komma-getrennt'], 'collection' => ['type' => 'integer']];
        $publish = ['publish' => ['type' => 'boolean', 'default' => false, 'description' => 'Nach dem Speichern sofort veröffentlichen']];
        $op = fn(string $tag, string $summary, array $extra = []) => ['tags' => [$tag], 'summary' => $summary] + $extra;
        $st = app()->theme->settingsTitle();
        $hl = (string) project('hours.label', 'Öffnungszeiten');

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => CMS_NAME . ' API', 'version' => '1.0.0',
                'description' => "REST-API für Inhalte, zentrale Einstellungen („" . $st . "“), Seiten, Daten, Medien und Online-Anfragen (nur Metadaten).\n\n"
                    . "Authentifizierung: `Authorization: Bearer cms_…` (Tokens unter Admin → API & MCP). Fallback-Header: `X-Api-Key`.\n"
                    . "Änderungen an Seiteninhalten werden als Entwurf gespeichert; veröffentlicht wird mit `publish` bzw. `/pages/{page}/publish`.\n"
                    . 'Rate-Limit: ' . Tokens::RATE_LIMIT . ' Anfragen je ' . (Tokens::RATE_WINDOW / 60) . ' Minuten und Token.' . "\n\n"
                    . 'Sprachen (aktiv: ' . implode(', ', array_keys(\Core\Lang::all())) . ', Standard: ' . \Core\Lang::default() . '): Mit `?lang=en` arbeitet jede Anfrage in dieser Sprache – '
                    . 'Seiten (Pfad/„home“), Einträge, übersetzbare Einstellungen (Feld `translatable` im Schema) und Medientexte. Neue Sprachfassungen: `POST /pages/{page}/translate` bzw. `/data/{table}/{id}/translate`.' . "\n"
                    . 'Multi-Site: Jede Website hat eigene Tokens – die API gilt immer für die Domain, unter der sie aufgerufen wird.' . "\n\n"
                    . 'Freigabe (Prüf-Ebene): Tokens im Modus „Zur Freigabe“ führen Schreibzugriffe NICHT aus. Die Anfrage wird geprüft (Validierung wie sonst, Fehler → 422) '
                    . 'und als Einreichung gespeichert: HTTP 202 mit `{"data": {"status": "pending_review", "id", "url", "status_url", "summary"}}` und `Content-Location`-Header. '
                    . 'Den Stand liefert `GET /changes/{id}` (pending_review | applied | rejected mit reason). Uploads (`POST /media`) gelten immer sofort. '
                    . 'Jede Änderung über die API wird mit Token, Zeitpunkt und Unterschied protokolliert (Verwaltung → ' . \Core\AI\Assist::brand() . ' → Eingereicht).',
            ],
            'servers' => [['url' => absolute_url('/api/v1')]],
            'security' => [['bearer' => []]],
            'tags' => array_map(fn($t) => ['name' => $t], ['Allgemein', 'Einstellungen', 'Design', 'Seiten', 'Blöcke', 'Daten', 'Medien', 'Karten', 'Anfragen', 'Freigabe', 'Suche', 'Support']),
            'paths' => [
                '/public' => ['get' => $op('Allgemein', 'Öffentliche Basisdaten (ohne Token): Name, URL und die vom Kit freigegebenen Angaben',
                    ['security' => [], 'responses' => ['200' => $ok(['type' => 'object'])]])],
                '/me' => ['get' => $op('Allgemein', 'Übersicht: Website, Token-Berechtigung, Seiten, Blocktypen', ['responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                '/settings' => [
                    'get' => $op('Einstellungen', 'Alle Einstellungen „' . $st . '“ (optional ?group=…)', [
                        'parameters' => [['name' => 'group', 'in' => 'query', 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => $ok(['type' => 'object', 'additionalProperties' => true])] + $err]),
                    'patch' => $op('Einstellungen', 'Einstellungen teilweise ändern (nur übergebene Felder)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'additionalProperties' => true,
                            'example' => ['feldname' => 'Wert']]),
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/design' => [
                    'get' => $op('Design', 'Design-Werte (Style-Editor): Gruppen/Tokens, Schriften, Vorlagen, aktuelle Werte, Standardwerte, Kontrastprüfung, Verlauf. 404, wenn das Kit keine anbietet oder die Funktion abgeschaltet ist.',
                        ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                    'patch' => $op('Design', 'Design ändern und sofort veröffentlichen (mit Verlauf). Braucht ein write-Token, dessen Benutzer-Rolle das Recht „design.edit“ hat (sonst 403).', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => [
                            'values' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Nur zu ändernde Werte; Farben #RRGGBB, dunkle Werte als „name@dark“'],
                            'preset' => ['type' => 'string', 'description' => 'Vorlage als Grundlage (Schlüssel aus GET /design → presets)'],
                            'reset' => ['type' => 'boolean', 'description' => 'Kit-Standard als Grundlage']],
                            'example' => ['values' => ['accent' => '#0F5E63']]]),
                        'responses' => ['200' => $ok(['type' => 'object']), '403' => ['description' => 'Token-Benutzer ohne Recht design.edit bzw. read-Token']] + $err]),
                ],
                '/settings/schema' => ['get' => $op('Einstellungen', 'Feld-Schema der Einstellungen (Gruppen, Typen, Pflichtfelder)', ['responses' => ['200' => $ok(['type' => 'array'])] + $err])],
                '/landings' => [
                    'get' => $op('Einstellungen', 'Landingpages mit eigenen Domains (Funktion „landings“): Domains, zugeordnete Seite, Unterseiten, Canonical-Modus (own|mirror), Weiterleitungen, Suche, Layout, Marke (Name, Logo, Favicon, Vorschaubild, Design-Überschreibungen) und die Seiten mit ihren Adressen. Nur lesen; 404, wenn die Funktion aus ist.',
                        ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/hours' => [
                    'get' => $op('Einstellungen', $hl . ' (nur wenn das Kit sie anbietet, sonst 404)', ['responses' => ['200' => $ok(['type' => 'array', 'items' => $ref('Hours')])] + $err]),
                    'put' => $op('Einstellungen', $hl . ' vollständig ersetzen', [
                        'requestBody' => ['required' => true] + $json(['type' => 'array', 'items' => $ref('Hours'), 'example' => [
                            ['tag' => 'Mo', 'von' => '09:00', 'bis' => '18:00', 'pause_von' => '13:00', 'pause_bis' => '14:00'],
                            ['tag' => 'Sa', 'von' => '10:00', 'bis' => '14:00', 'notiz' => ''],
                        ]]),
                        'responses' => ['200' => $ok(['type' => 'array', 'items' => $ref('Hours')])] + $err]),
                ],
                '/notice' => ['put' => $op('Einstellungen', 'Hinweisbalken setzen (nur wenn das Kit ihn anbietet)', [
                    'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['text'], 'properties' => [
                        'text' => ['type' => 'string', 'description' => 'Erlaubt: <b> <i> <a href>'], 'active' => ['type' => 'boolean', 'default' => true]]]),
                    'responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                '/block-types' => ['get' => $op('Blöcke', 'Verfügbare Blocktypen mit Feld-Schema und Abschnitts-Optionen', ['responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                '/pages' => [
                    'get' => $op('Seiten', 'Alle Seiten', ['responses' => ['200' => $ok(['type' => 'array', 'items' => $ref('PageMeta')])] + $err]),
                    'post' => $op('Seiten', 'Seite anlegen (optional mit Blöcken)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['title'], 'properties' => [
                            'title' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                            'meta_description' => ['type' => 'string'], 'noindex' => ['type' => 'boolean'],
                            'blocks' => ['type' => 'array', 'items' => $ref('Block')]]]),
                        'responses' => ['201' => $ok($ref('Page'), 'Angelegt')] + $err]),
                ],
                '/pages/{page}' => [
                    'parameters' => [$pageParam],
                    'get' => $op('Seiten', 'Seite mit Blöcken (Entwurf; ?version=published für den öffentlichen Stand)', [
                        'parameters' => [['name' => 'version', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['draft', 'published']]]],
                        'responses' => ['200' => $ok($ref('Page'))] + $err]),
                    'patch' => $op('Seiten', 'Seiteneinstellungen ändern (Titel, Slug, Status, SEO)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => [
                            'title' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                            'meta_description' => ['type' => 'string'], 'noindex' => ['type' => 'boolean']]]),
                        'responses' => ['200' => $ok($ref('PageMeta'))] + $err]),
                    'delete' => $op('Seiten', 'Seite löschen (nicht die Startseite)', ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/pages/{page}/translate' => ['parameters' => [$pageParam], 'post' => $op('Seiten', 'Übersetzung anlegen (Kopie als Entwurf in der Zielsprache, verknüpft; vorhandene wird zurückgegeben)', [
                    'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['lang'], 'properties' => ['lang' => ['type' => 'string', 'enum' => array_keys(\Core\Lang::all())]]]),
                    'responses' => ['201' => $ok($ref('Page'), 'Übersetzung')] + $err])],
                '/pages/{page}/publish' => ['parameters' => [$pageParam], 'post' => $op('Seiten', 'Entwurf veröffentlichen', ['responses' => ['200' => $ok($ref('PageMeta'))] + $err])],
                '/pages/{page}/discard' => ['parameters' => [$pageParam], 'post' => $op('Seiten', 'Entwurf verwerfen (zurück zur veröffentlichten Fassung; Entwurf bleibt als Version gesichert)', ['responses' => ['200' => $ok($ref('Page'))] + $err])],
                '/pages/{page}/revisions' => ['parameters' => [$pageParam], 'get' => $op('Seiten', 'Gespeicherte Versionen (Anzahl je Konfiguration, Standard 20)', ['responses' => ['200' => $ok(['type' => 'array'])] + $err])],
                '/pages/{page}/revisions/{rev}/restore' => ['parameters' => [$pageParam, ['name' => 'rev', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => $op('Seiten', 'Version als Entwurf wiederherstellen', ['responses' => ['200' => $ok($ref('Page'))] + $err])],
                '/pages/{page}/blocks' => [
                    'parameters' => [$pageParam],
                    'get' => $op('Blöcke', 'Blöcke einer Seite', ['responses' => ['200' => $ok(['type' => 'array', 'items' => $ref('Block')])] + $err]),
                    'put' => $op('Blöcke', 'Alle Blöcke ersetzen (Reihenfolge = Array-Reihenfolge)', [
                        'parameters' => [['name' => 'publish', 'in' => 'query', 'schema' => ['type' => 'boolean']]],
                        'requestBody' => ['required' => true] + $json(['type' => 'array', 'items' => $ref('Block')]),
                        'responses' => ['200' => $ok($ref('Page'))] + $err]),
                    'post' => $op('Blöcke', 'Block einfügen', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['type'], 'properties' => [
                            'type' => ['type' => 'string', 'example' => 'notice'], 'data' => ['type' => 'object'], 'section' => $ref('Section'),
                            'position' => ['type' => 'integer', 'description' => '0-basiert; ohne Angabe ans Ende'],
                            'after' => ['type' => 'string', 'description' => 'Alternativ: nach dieser Block-ID einfügen']] + $publish]),
                        'responses' => ['201' => $ok($ref('Page'))] + $err]),
                ],
                '/pages/{page}/blocks/{block}' => [
                    'parameters' => [$pageParam, $blockParam],
                    'patch' => $op('Blöcke', 'Block ändern (data/section werden zusammengeführt; replace=true ersetzt data)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => [
                            'data' => ['type' => 'object'], 'section' => $ref('Section'), 'replace' => ['type' => 'boolean']] + $publish]),
                        'responses' => ['200' => $ok($ref('Page'))] + $err]),
                    'delete' => $op('Blöcke', 'Block entfernen', ['responses' => ['200' => $ok($ref('Page'))] + $err]),
                ],
                '/pages/{page}/blocks/{block}/move' => ['parameters' => [$pageParam, $blockParam],
                    'post' => $op('Blöcke', 'Block verschieben', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['position'], 'properties' => ['position' => ['type' => 'integer']] + $publish]),
                        'responses' => ['200' => $ok($ref('Page'))] + $err])],
                '/media' => [
                    'get' => $op('Medien', 'Mediathek – Filter: ?kind=image|pdf|video|audio, ?q=, ?tag=, ?collection=ID, ?noalt=1, ?missing_lang=en, ?notitle=1, ?nocaptions=1, ?notranscript=1', ['responses' => ['200' => $ok(['type' => 'array', 'items' => $ref('Media')])] + $err]),
                    'post' => $op('Medien', 'Datei hochladen (multipart „file“ + „alt“ oder JSON {filename, base64, alt})', [
                        'requestBody' => ['required' => true, 'content' => [
                            'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']] + $upOpt]],
                            'application/json' => ['schema' => ['type' => 'object', 'properties' => ['filename' => ['type' => 'string'], 'base64' => ['type' => 'string']] + $upOpt]],
                        ]],
                        'responses' => ['201' => $ok($ref('Media'))] + $err]),
                ],
                '/media/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'patch' => $op('Medien', 'Alt-Text, Titel, Fotonachweis, Tags, Sammlungen, Fokuspunkt ändern', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => ['alt' => ['type' => 'string'], 'decorative' => ['type' => 'boolean'],
                            'title' => ['type' => 'string'], 'credit' => ['type' => 'string'], 'tags' => ['type' => 'string', 'description' => 'Komma-getrennt'],
                            'collections' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'i18n' => ['type' => 'object', 'description' => 'Übersetzungen je Sprache, z. B. {"en": {"alt": "…", "title": "…"}}; leere Werte = Standardsprache',
                                'additionalProperties' => ['type' => 'object', 'properties' => ['alt' => ['type' => 'string'], 'title' => ['type' => 'string']]]],
                            'focus' => ['type' => 'object', 'properties' => ['x' => ['type' => 'integer'], 'y' => ['type' => 'integer']]]]]),
                        'responses' => ['200' => $ok($ref('Media'))] + $err]),
                    'delete' => $op('Medien', 'Datei löschen', ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/media/{id}/crop' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => $op('Medien', 'Zuschnitt je Bildformat setzen (rect als Anteile 0–1) oder entfernen (rect: null)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['ratio'], 'properties' => ['ratio' => ['type' => 'string', 'example' => '16:9'],
                            'rect' => ['type' => ['object', 'null'], 'properties' => ['x' => ['type' => 'number'], 'y' => ['type' => 'number'], 'w' => ['type' => 'number'], 'h' => ['type' => 'number']]]]]),
                        'responses' => ['200' => $ok($ref('Media'))] + $err]),
                ],
                '/data' => ['get' => $op('Daten', 'Datentabellen mit Feld-Schema (kind: content | inbox – Eingangs-Tabellen nur mit Metadaten; GET /data/{inbox} liefert Status/Vorgangsnummer, POST/PATCH/DELETE → 403)', ['responses' => ['200' => $ok(['type' => 'array'])] + $err])],
                '/data/{table}' => [
                    'parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Kurzname, z. B. aktuelles']],
                    'get' => $op('Daten', 'Einträge – ?q=, ?status=published|draft|all, ?filter[feld]=wert, ?sort=, ?dir=asc|desc, ?limit=, ?offset=; geteilte Tabellen zusätzlich ?source= (site = wie auf der Website [Standard], own, owner, members, own_owner, all, featured) – Einträge tragen dann origin_site, _foreign, _origin, _canonical, _pick', [
                        'parameters' => [['name' => 'source', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => \Core\Data\Shared::SOURCES, 'default' => 'site'], 'description' => 'Nur geteilte Tabellen']],
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                    'post' => $op('Daten', 'Eintrag anlegen (Feldwerte als JSON, optional slug, status). Formate: date „JJJJ-MM-TT“, datetime „JJJJ-MM-TT HH:MM“ (Ortszeit), recurrence „FREQ=WEEKLY;BYDAY=MO;UNTIL=20261231“ + optional „\nEXDATE:2026-10-12,…“ oder {"rrule": "…", "exdates": ["2026-10-12"]}, iban „DE89370400440532013000“ (Leerzeichen erlaubt; Land, Länge und Prüfziffer werden geprüft), group (wiederholbare Gruppe) als Array von Objekten [{"unterfeld": "wert"}, …] – Unterfelder, min und max unter fields[].group in GET /data; leere Zeilen fallen weg. Bedingungen der Felder (visible_if, required_if, compare – siehe GET /data) gelten auch hier: ausgeblendete Felder werden geleert, Verstöße → 422.', ['requestBody' => ['required' => true] + $json(['type' => 'object', 'additionalProperties' => true]),
                        'responses' => ['201' => $ok(['type' => 'object'])] + $err]),
                ],
                '/data/{table}/occurrences' => [
                    'parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Kurzname einer Tabelle mit Kalender']],
                    'get' => $op('Daten', 'Termine im Zeitraum (Wiederholungen einzeln, Ausnahmen berücksichtigt) – ?from=, ?to= (JJJJ-MM-TT, einschließlich; Standard heute bis +1 Monat, max. 366 Tage), ?status=, ?filter[feld]=, ?limit= (max. 1000)', [
                        'parameters' => [
                            ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string'], 'example' => '2026-10-01'],
                            ['name' => 'to', 'in' => 'query', 'schema' => ['type' => 'string'], 'example' => '2026-10-31'],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['published', 'draft', 'all'], 'default' => 'published']],
                            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 500, 'maximum' => 1000]],
                            ['name' => 'source', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => \Core\Data\Shared::SOURCES, 'default' => 'site'], 'description' => 'Nur geteilte Tabellen'],
                        ],
                        'responses' => ['200' => $ok(['type' => 'object', 'properties' => [
                            'from' => ['type' => 'string'], 'to' => ['type' => 'string'], 'timezone' => ['type' => 'string', 'example' => 'Europe/Berlin'], 'total' => ['type' => 'integer'],
                            'occurrences' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                                'entry_id' => ['type' => 'integer'], 'title' => ['type' => 'string'],
                                'start' => ['type' => 'string', 'description' => 'ISO 8601 mit Zeitzone; ganztägig: JJJJ-MM-TT', 'example' => '2026-10-05T09:00:00+02:00'],
                                'end' => ['type' => 'string', 'description' => 'ISO 8601; ganztägig: letzter Tag (einschließlich)'],
                                'all_day' => ['type' => 'boolean'], 'recurring' => ['type' => 'boolean'], 'location' => ['type' => ['string', 'null']],
                                'category' => ['description' => 'Kurzname (Auswahl) bzw. Liste (Mehrfachauswahl)'], 'url' => ['type' => ['string', 'null']], 'lang' => ['type' => 'string']]]]]])]
                            + ['404' => ['description' => 'Funktion „Kalender“ abgeschaltet oder Tabelle unbekannt']] + $err]),
                ],
                '/data/{table}/suggestions' => [
                    'parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Kurzname einer geteilten Tabelle']],
                    'get' => $op('Daten', 'Geteilte Tabelle (nur Eigentümer-Website): Vorschläge der übrigen Websites – ?state=pending (offen, Standard) | visible | featured | rejected | all', [
                        'parameters' => [['name' => 'state', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['pending', 'visible', 'featured', 'rejected', 'all'], 'default' => 'pending']]],
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/data/{table}/picks' => [
                    'parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Kurzname einer geteilten Tabelle']],
                    'post' => $op('Daten', 'Geteilte Tabelle: Auswahl DIESER Website setzen – visible (übernehmen) und rejected (ablehnen) nur Eigentümer für Einträge anderer Websites, featured (hervorheben), hidden (fremde ausblenden), reset. Fremde Einträge selbst sind nur lesbar (PATCH/DELETE → 403).', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['state'], 'properties' => [
                            'ids' => ['type' => 'array', 'items' => ['type' => 'integer']], 'entry_id' => ['type' => 'integer'],
                            'state' => ['type' => 'string', 'enum' => [...\Core\Data\Shared::STATES, 'reset']]]]),
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/data/{table}/{id}' => [
                    'parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'ID oder Slug (Ändern und Löschen nur per ID)']],
                    'get' => $op('Daten', 'Eintrag lesen', ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                    'patch' => $op('Daten', 'Eintrag ändern (nur übergebene Felder)', ['requestBody' => ['required' => true] + $json(['type' => 'object', 'additionalProperties' => true]),
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                    'delete' => $op('Daten', 'Eintrag löschen', ['responses' => ['200' => $ok(['type' => 'object'])] + $err]),
                ],
                '/data/{table}/{id}/translate' => ['parameters' => [['name' => 'table', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => $op('Daten', 'Übersetzung eines Eintrags anlegen (Entwurf in der Zielsprache, verknüpft)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['lang'], 'properties' => ['lang' => ['type' => 'string', 'enum' => array_keys(\Core\Lang::all())]]]),
                        'responses' => ['201' => $ok(['type' => 'object'])] + $err])],
                '/geocode' => ['get' => $op('Karten', 'Adresse → Koordinaten (OpenStreetMap, serverseitig). „value“ passt direkt in Felder vom Typ geo.', [
                    'parameters' => [['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'example' => 'Hauptstraße 1, 47441 Moers']],
                    'responses' => ['200' => $ok(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'label' => ['type' => 'string'], 'lat' => ['type' => 'number'], 'lng' => ['type' => 'number'], 'value' => ['type' => 'string', 'example' => '51.451646, 6.626294']]]])] + $err])],
                '/media-meta' => ['get' => $op('Medien', 'Sammlungen, Tags und Bildformate', ['responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                '/search' => ['get' => $op('Suche', 'Website-Suche wie für Besucher: veröffentlichte Seiten, Einträge mit Detailseite (inkl. geteilter), ggf. PDF-Dokumente. Tippfehlertolerant; semantisch (KI), wenn eingeschaltet – mode: keyword | hybrid, fallback: true, wenn der KI-Anbieter nicht antwortete.', [
                    'parameters' => [['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'maxLength' => 200], 'example' => 'Öffnungszeiten'],
                        ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 10, 'maximum' => 50]],
                        ['name' => 'type', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'page, file oder Kurzname einer Datentabelle']],
                    'responses' => ['200' => $ok(['type' => 'object', 'properties' => [
                        'q' => ['type' => 'string'], 'lang' => ['type' => 'string'], 'mode' => ['type' => 'string', 'enum' => ['keyword', 'hybrid']], 'fallback' => ['type' => 'boolean'],
                        'total' => ['type' => 'integer'], 'page' => ['type' => 'integer'], 'pages' => ['type' => 'integer'], 'types' => ['type' => 'object'],
                        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'id' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => ['page', 'entry', 'file']], 'table' => ['type' => ['string', 'null']],
                            'badge' => ['type' => 'string'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                            'date' => ['type' => ['string', 'null']], 'date_label' => ['type' => ['string', 'null']], 'origin' => ['type' => ['string', 'null']],
                            'excerpt' => ['type' => 'string'], 'match' => ['type' => 'string', 'enum' => ['keyword', 'semantic', 'both']]]]]]])] + $err])],
                '/kb' => ['get' => $op('Support', 'Wissensdatenbank: veröffentlichte Artikel, die für diese Website sichtbar sind (?q= Suchbegriffe, ?limit=1–50). Ohne q: neueste Artikel. Text im Format markdown-lite.', [
                    'parameters' => [['name' => 'q', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']], ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 10]]],
                    'responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                '/requests' => ['get' => $op('Anfragen','Anfragen aller Eingangs-Tabellen – nur Metadaten (id, table, ref, status, created_at …); Inhalte bleiben Ende-zu-Ende verschlüsselt (?status=neu|in_bearbeitung|erledigt|alle, ?table=, ?ciphertext=1)', [
                    'responses' => ['200' => $ok(['type' => 'array'])] + $err])],
                '/requests/{id}' => ['parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'patch' => $op('Anfragen', 'Status setzen (protokolliert); table angeben, wenn die ID in mehreren Eingangs-Tabellen vorkommt (sonst 409)', [
                        'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['status'], 'properties' => ['status' => ['type' => 'string', 'enum' => ['neu', 'in_bearbeitung', 'erledigt']],
                            'table' => ['type' => 'string']]]),
                        'responses' => ['200' => $ok(['type' => 'object'])] + $err])],
                // Prüf-Ebene (Core\Review\Queue)
                '/changes' => ['get' => $op('Freigabe', 'Einreichungen und protokollierte Änderungen DIESES Tokens, neueste zuerst (?status=pending_review|applied|rejected); review_mode zeigt den Modus des Tokens', [
                    'parameters' => [['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['pending_review', 'applied', 'rejected']]]],
                    'responses' => ['200' => $ok(['type' => 'object', 'properties' => ['review_mode' => ['type' => 'string', 'enum' => ['direct', 'review']],
                        'changes' => ['type' => 'array', 'items' => $ref('Change')]]])] + $err])],
                '/changes/{id}' => ['parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => $op('Freigabe', 'Stand einer Einreichung (nur eigene): pending_review, applied (ggf. mit note „bearbeitet“) oder rejected (mit reason)', [
                        'responses' => ['200' => $ok($ref('Change')), '404' => ['description' => 'Unbekannt oder von einem anderen Token']] + $err])],
            ],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'API-Token „cms_…“']],
                'parameters' => ['Lang' => ['name' => 'lang', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => array_keys(\Core\Lang::all())],
                    'description' => 'Sprache der Anfrage (Standard: ' . \Core\Lang::default() . ')']],
                'responses' => [
                    'Unauthorized' => ['description' => 'Token fehlt/ungültig'] + $json($ref('Error')),
                    'Invalid' => ['description' => 'Validierungsfehler'] + $json($ref('Error')),
                    'Pending' => ['description' => 'Zur Freigabe eingereicht (Token im Modus „Zur Freigabe“) – nichts ausgeführt'] + $json(['type' => 'object', 'properties' => ['data' => $ref('Pending')]]),
                ],
                'schemas' => [
                    'Pending' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'const' => 'pending_review'], 'id' => ['type' => 'integer'],
                        'url' => ['type' => 'string', 'format' => 'uri', 'description' => 'Einreichung in der Verwaltung'], 'status_url' => ['type' => 'string', 'format' => 'uri'],
                        'summary' => ['type' => 'string'], 'entity' => ['type' => 'object'], 'message' => ['type' => 'string']]],
                    'Change' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'status' => ['type' => 'string', 'enum' => ['pending_review', 'applied', 'rejected']],
                        'action' => ['type' => 'string'], 'summary' => ['type' => 'string'], 'channel' => ['type' => 'string', 'enum' => ['api', 'mcp', 'ai']],
                        'entity' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string'], 'id' => ['type' => ['string', 'null']], 'label' => ['type' => ['string', 'null']]]],
                        'submitted_at' => ['type' => 'string'], 'reviewed_at' => ['type' => ['string', 'null']], 'reason' => ['type' => ['string', 'null']], 'note' => ['type' => ['string', 'null']],
                        'url' => ['type' => 'string', 'format' => 'uri'], 'changes' => ['type' => 'array', 'items' => ['type' => 'object']]]],
                    'Error' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => [
                        'status' => ['type' => 'integer'], 'message' => ['type' => 'string'], 'errors' => ['type' => 'object']]]]],
                    'Hours' => ['type' => 'object', 'required' => ['tag'], 'properties' => [
                        'tag' => ['description' => '1 = Montag … 6 = Samstag, 0 = Sonntag – oder Name („Mo“, „Montag“)', 'oneOf' => [['type' => 'integer'], ['type' => 'string']]],
                        'von' => ['type' => 'string', 'example' => '07:30'], 'bis' => ['type' => 'string', 'example' => '17:00'],
                        'pause_von' => ['type' => 'string'], 'pause_bis' => ['type' => 'string'], 'notiz' => ['type' => 'string']]],
                    'Section' => ['type' => 'object', 'description' => 'Abschnitts-Optionen', 'properties' => [
                        'background' => ['type' => 'string', 'enum' => array_keys(app()->theme->backgrounds())], 'anchor' => ['type' => 'string'],
                        'visible' => ['type' => 'boolean'], 'showInNav' => ['type' => 'boolean'], 'navLabel' => ['type' => 'string'],
                        'spaceTop' => ['type' => 'string', 'enum' => ['normal', 'small', 'none']], 'spaceBottom' => ['type' => 'string', 'enum' => ['normal', 'small', 'none']],
                        'divider' => ['type' => 'boolean'],
                        'height' => ['type' => 'string', 'enum' => ['auto', 'screen'], 'description' => 'screen = Vollbild (mindestens 100svh, Inhalt vertikal ausgerichtet)'],
                        'bgImage' => ['type' => ['integer', 'null'], 'description' => 'Medien-ID eines Hintergrundbilds (responsiv, hinter dem Inhalt)'],
                        'overlay' => ['type' => 'string', 'enum' => ['none', 'light', 'dark'], 'description' => 'Aufhellung/Abdunkelung über dem Hintergrundbild für lesbaren Text'],
                        'align' => ['type' => 'string', 'enum' => ['top', 'center', 'bottom'], 'description' => 'Vertikale Ausrichtung bei height = screen']]],
                    'Block' => ['type' => 'object', 'required' => ['type'], 'properties' => [
                        'id' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(app()->theme->blocks())],
                        'data' => ['type' => 'object', 'description' => 'Felder laut /block-types'], 'section' => $ref('Section')]],
                    'PageMeta' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'], 'slug' => ['type' => 'string'], 'title' => ['type' => 'string'], 'url' => ['type' => 'string'],
                        'status' => ['type' => 'string'], 'is_home' => ['type' => 'boolean'], 'noindex' => ['type' => 'boolean'],
                        'has_unpublished_changes' => ['type' => 'boolean'], 'updated_at' => ['type' => 'string'], 'published_at' => ['type' => ['string', 'null']],
                        'lang' => ['type' => 'string'], 'translations' => ['type' => ['object', 'null'], 'description' => 'Sprache → Seiten-ID', 'additionalProperties' => ['type' => 'integer']]]],
                    'Page' => ['allOf' => [$ref('PageMeta'), ['type' => 'object', 'properties' => [
                        'blocks' => ['type' => 'array', 'items' => $ref('Block')], 'warnings' => ['type' => 'array', 'items' => ['type' => 'string']]]]]],
                    'Media' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer'], 'url' => ['type' => 'string'], 'thumb' => ['type' => ['string', 'null']], 'alt' => ['type' => 'string'],
                        'name' => ['type' => 'string'], 'mime' => ['type' => 'string'], 'width' => ['type' => 'integer'], 'height' => ['type' => 'integer'], 'size' => ['type' => 'string'],
                        'decorative' => ['type' => 'boolean'], 'title' => ['type' => 'string'], 'display' => ['type' => 'string'], 'kind' => ['type' => 'string'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']], 'focus' => ['type' => 'object'], 'crops' => ['type' => 'object'],
                        'pages' => ['type' => ['integer', 'null']], 'viewer' => ['type' => ['string', 'null']], 'missing_alt' => ['type' => 'boolean'],
                        'i18n' => ['type' => 'object', 'description' => 'Übersetzungen: {"en": {"alt": "…", "title": "…"}}'],
                        'missing_translations' => ['type' => 'array', 'items' => ['type' => 'string']]]],
                ],
            ],
        ];
        // Sprachparameter für alle Endpunkte mit Token
        foreach ($spec['paths'] as $path => $item) {
            if ($path === '/public') continue;
            $spec['paths'][$path]['parameters'] = array_merge($item['parameters'] ?? [], [['$ref' => '#/components/parameters/Lang']]);
            // Schreibzugriffe können zur Freigabe eingereicht werden (202) – außer Uploads und Anfrage-Status
            foreach (['post', 'put', 'patch', 'delete'] as $m) {
                if (isset($item[$m]) && !in_array($path, ['/media', '/requests/{id}'], true)) {
                    $spec['paths'][$path][$m]['responses']['202'] = ['$ref' => '#/components/responses/Pending'];
                }
            }
        }
        return $spec;
    }
}
