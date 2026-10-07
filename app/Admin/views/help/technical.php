<?php
/**
 * Entwicklerhandbuch (technische Dokumentation). Die Kapitel liegen in help/technical/{schlüssel}.php und erscheinen
 * in der Reihenfolge von $chapters (Nummern und Inhaltsverzeichnis entstehen automatisch; Schlüssel = Anker, auf die
 * andere Seiten verlinken – z. B. #api, #design, #symbole – nicht umbenennen).
 * @var array $openapi  @var array $mcp  @var array $fieldTypes  @var array $blocks
 */
$api = absolute_url('/api/v1');
$mcpUrl = absolute_url('/mcp');
$aiBrand = \Core\AI\Assist::brand();
$parts = ['Grundlagen & Betrieb', 'Plattform: Websites, Rechte, Sicherheit', 'Inhalte & Darstellung', 'Suche, KI & Schnittstellen', 'Verwaltung & Dienste'];
$chapters = [
    'architektur' => ['Architektur & Datenmodell', 0], 'installation' => ['Installation & Anforderungen', 0], 'konfiguration' => ['Konfiguration', 0],
    'build' => ['Build & Entwicklung', 0], 'deploy' => ['Staging & Deploy', 0], 'betrieb' => ['Betrieb: Cronjobs, Sicherungen, Updates', 0], 'cli' => ['Kommandozeile', 0],
    'websites' => ['Mehrere Websites', 1], 'netzwerk' => ['Netzwerk-Administration', 1], 'landingpages' => ['Landingpages mit eigenen Domains', 1], 'weiterleitungen' => ['Weiterleitungen & 404-Protokoll', 1], 'rechte' => ['Rollen, Rechte & Zwei-Faktor', 1],
    'funktionen' => ['Funktionsumfang & Erweiterungen', 1], 'erweiterungen' => ['Erweiterungen: Regeln, Routen, Slots & Ereignisse', 1], 'sicherheit' => ['Sicherheit & Datenschutz', 1], 'consent' => ['Cookie-Einwilligung (Erweiterung Consent-Kit)', 1],
    'kits' => ['Kits & Design', 2], 'css-js' => ['CSS & JS', 2], 'format' => ['Werte formatieren (Core\\Format)', 2], 'frameworks' => ['Frameworks (Tailwind, UIkit, Bootstrap)', 2], 'schriften' => ['Schriften aus Google Fonts (selbst gehostet)', 2], 'editor' => ['Bearbeiten auf der Website', 2], 'felder' => ['Feldtypen', 2], 'bloecke' => ['Eigene Blöcke (Block-Designer)', 2], 'daten' => ['Seitenbaum & Datentabellen', 2],
    'formulare' => ['Formulare & Eingänge', 2], 'kalender' => ['Kalender, iCal & CalDAV', 2], 'stellen' => ['Stellenangebote & Google for Jobs', 2], 'geteilt' => ['Geteilte Datentabellen', 2], 'quellen' => ['Externe Quellen (Feeds, APIs, OpenImmo)', 2],
    'medien' => ['Medien, Pools & Untertitel', 2], 'sprachen' => ['Sprachen & Übersetzung', 2], 'glossar' => ['Glossar', 2],
    'suche' => ['Website-Suche', 3], 'ki' => [$aiBrand . ': KI-Dienst & Funktionen', 3], 'ki-chat' => ['KI-Chats: Assistent & Besucher-Chat (SSE)', 3], 'live' => ['Live-Aktualisierung für Besucher (SSE)', 3], 'freigabe' => ['Prüf-Ebene „Eingereicht“', 3],
    'api' => ['REST-API', 3], 'mcp' => ['MCP-Server', 3],
    'verwaltung' => ['Verwaltungsoberfläche', 4], 'support' => ['Support & Wissensdatenbank', 4], 'chat' => ['Chat zwischen Benutzern (SSE)', 4], 'push' => ['Push-Benachrichtigungen (Web Push)', 4], 'symbole' => ['Symbole (Icons)', 4],
    'karten' => ['Karten & Proxy', 4], 'pwa' => ['Favicon & PWA', 4], 'tutorials' => ['Tutorials & Videos', 4],
];
// Kapitel aktiver Erweiterungen (Extension::docs('technical', ['key' => ['title', 'file', 'part', 'after']]))
$__extFiles = [];
foreach (\Core\Extensions::docs('technical') as $__key => $__spec) {
    if (isset($chapters[$__key])) continue;
    $__extFiles[$__key] = (string) $__spec['file'];
    $__pos = array_search((string) ($__spec['after'] ?? ''), array_keys($chapters), true);
    $__new = [$__key => [(string) ($__spec['title'] ?? $__key), max(0, min(count($parts) - 1, (int) ($__spec['part'] ?? 4)))]];
    $chapters = $__pos === false ? $chapters + $__new : array_slice($chapters, 0, $__pos + 1, true) + $__new + array_slice($chapters, $__pos + 1, null, true);
}
$fieldInfo = [
    'text' => 'Einzeilig', 'textarea' => 'Mehrzeilig (ohne HTML)', 'richtext' => 'HTML-Whitelist: p, b, strong, i, em, a, ul, ol, li, h2, h3, h4, blockquote, br',
    'inline' => 'HTML-Whitelist: b, strong, i, em, a, br', 'email' => 'E-Mail', 'tel' => 'Telefon (tel: wird normalisiert)', 'url' => 'nur https:// (http:// wird zu https:// angehoben)',
    'link' => 'https:, mailto:, tel:, #anker, /pfad, page:ID[#anker], entry:{tabelle}:{id}, media:{id}[:viewer] (+ Kit-Sonderwerte aus link_keywords); Linkauswahl „Auswählen …“', 'number' => 'Zahl', 'bool' => 'Ja/Nein', 'select' => 'Auswahl (options)',
    'date' => 'JJJJ-MM-TT', 'time' => 'HH:MM',
    'datetime' => 'JJJJ-MM-TT HH:MM (Ortszeit der Website; Eingabe datetime-local, akzeptiert auch „T“/Sekunden)',
    'recurrence' => 'Wiederholung: RFC-5545-RRULE (DAILY|WEEKLY|MONTHLY|YEARLY, max. 1000 Termine) + optional Zeile „EXDATE:JJJJ-MM-TT,…“; Regel-Editor in der Verwaltung', 'page' => 'Seiten-ID', 'pages' => 'Mehrere Seiten (Core\\PagePicker): Chips mit Pfad im Seitenbaum, Dialog mit Baum, Suche, „mit Unterseiten“, Tastatur wie die Linkauswahl. store \'ids\' (Standard) → Liste „12“ bzw. „12*“ (mit Unterseiten, PagePicker::matches()), store \'paths\' → Pfade je Zeile („/x“, „/x/*“, eigene Pfade); subpages => false ohne Unterseiten', 'media' => 'Bild-ID (Mediathek)', 'file' => 'Datei-ID (Mediathek)',
    'color' => 'Farbe #RRGGBB oder #RGB (Farbwähler + Hex-Eingabe)', 'multiselect' => 'Mehrfachauswahl (options) → Array',
    'collection' => 'Medien-Sammlung (ID)', 'datatable' => 'Datentabelle (Kurzname)', 'datafield' => 'Feld einer Tabelle „tabelle.feld“ (optional mit system-Sortierfeldern)',
    'datafields' => 'Mehrere Felder „tabelle.feld“; das Formular zeigt nur die der gewählten Tabelle',
    'repeater' => 'Liste von Einträgen (fields)', 'group' => 'Wiederholbare Gruppe der Datentabellen: fields (Unterfelder), min/max (Anzahl, Fehler statt Kürzen), item_label – leere Zeilen fallen weg, Fehler je Zeile unter feld.position.unterfeld',
'secret' => 'Passwort – leer = unverändert', 'heading' => 'Nur Zwischenüberschrift im Formular',
    'iban' => 'IBAN: gespeichert ohne Leerzeichen in Großbuchstaben, Prüfung Ländercode + Länge (SEPA-Raum) + Prüfziffer (ISO 13616, Mod 97); Anzeige in Vierergruppen, in Listen/Ausgaben maskiert (Feldoption mask, Standard an)',
    'icon' => 'Symbol (Core\\Icons): Wert = Symbolname wie „calendar-dots“; Auswahl als durchsuchbares Raster, alte Unicode-Zeichen werden abgebildet; options = zusätzlich erlaubte eigene Werte; Ausgabe im Kit: icon($wert)',
    'geo' => 'Ort „Breite, Länge“ – Adresssuche (Nominatim, serverseitig) + Karte zum Klicken; address_fields = Einstellungen für die Vorbelegung der Suche',
];
?>
<?php $helpTab = 'technical'; include ROOT . '/app/Admin/views/support/_helptabs.php'; ?>
<div class="doc">
  <header class="doc-hero">
    <span class="doc-hero__eyebrow"><?= e(CMS_NAME) ?> · Entwicklerhandbuch</span>
    <h1>Architektur, Betrieb &amp; Schnittstellen<i>.</i></h1>
    <p>Aufbau des Multi-Site-CMS, Installation auf Plesk ohne <code>.htaccess</code>, Netzwerk-Administration, Kits, Datentabellen, Suche und <?= e($aiBrand) ?>, REST-API (OpenAPI 3.1) und MCP-Server – geprüft gegen den Code von <?= e(CMS_NAME) ?> <?= e(CMS_VERSION) ?>.</p>
    <div class="doc-hero__links">
      <a href="#api">REST-API →</a>
      <a href="#mcp" class="ghost">MCP-Server</a>
      <a href="<?= e($api) ?>/openapi.json" class="ghost" target="_blank" rel="noopener">openapi.json ↗</a>
      <a href="<?= e(url('/admin/hilfe')) ?>" class="ghost">Benutzerhandbuch</a>
    </div>
  </header>

  <div class="doc-layout">
    <div>

<section class="doc-ch doc-overview" id="inhalt" aria-label="Inhaltsverzeichnis">
  <h2><span>Inhalt<span class="dot">.</span></span></h2>
  <div class="doc-cards">
    <?php $__n = 0; foreach ($parts as $__pi => $__pl): ?>
    <div class="doc-card"><span class="doc-card__title"><?= e($__pl) ?></span><span class="doc-card__sub">
      <?php foreach ($chapters as $__key => [$__title, $__part]): if ($__part !== $__pi) continue; $__n++; ?><a href="#<?= e($__key) ?>"><?= sprintf('%02d', $__n) ?> <?= e($__title) ?></a><br><?php endforeach; ?>
    </span></div>
    <?php endforeach; ?>
  </div>
</section>

<?php $__chapterNo = 0; foreach ($chapters as $__key => [$__title, $__part]): $__chapterNo++; /* eigener Zähler – Kapitel-Dateien dürfen $__n benutzen */ ?>
<section class="doc-ch" id="<?= e($__key) ?>">
  <h2><span class="no"><?= sprintf('%02d', $__chapterNo) ?></span><?= e($__title) ?><span class="dot">.</span></h2>
<?php include $__extFiles[$__key] ?? __DIR__ . '/technical/' . $__key . '.php'; ?>
</section>

<?php endforeach; ?>
      <p class="doc-foot">Entwicklerhandbuch · Stand <?= e(date('d.m.Y')) ?> · <?= e(CMS_NAME) ?> <?= e(CMS_VERSION) ?> · PHP <?= e(PHP_VERSION) ?></p>
    </div>
    <nav class="doc-toc" aria-label="Inhalt" data-doc-toc>
      <p>Inhalt</p>
      <ol><?php foreach ($chapters as $__key => [$__title]): ?><li><a href="#<?= e($__key) ?>"><?= e($__title) ?></a></li><?php endforeach; ?></ol>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost doc-print" data-print>Als PDF drucken</button>
    </nav>
  </div>
</div>
