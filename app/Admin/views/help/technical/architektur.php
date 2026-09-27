<?php /** Entwicklerhandbuch · Architektur & Datenmodell */ ?>
  <p class="lead"><b><?= e(CMS_NAME) ?></b> ist ein schlankes PHP-CMS ohne Framework (PHP ≥ 8.4) für eine oder viele Websites je Installation. Core und Kit sind strikt getrennt; nur <code>public/</code> ist per HTTP erreichbar.</p>
  <div class="doc-tree">
    <div class="doc-tree__box doc-tree__box--public"><strong>public/ · Document Root</strong><ul>
      <li><code>index.php</code> – Front-Controller (einzige PHP-Datei)</li><li><code>assets/</code> – Verwaltung, Editor, Schrift Lato, Symbole, Vendoren (Editor.js, PDF.js, MapLibre)</li>
      <li><code>kits/{name}/</code>, <code>extensions/{name}/</code> – gebaute Assets</li><li><code>media/</code> (Website <code>default</code>), <code>sites/{key}/media/</code>, <code>pools/{key}/</code> – Uploads, Bildgrößen, Untertitel</li></ul></div>
    <div class="doc-tree__box"><strong>app/ · Core (Namespace <code>Core\</code>)</strong><ul>
      <li><code>Http/</code> Router, Controller (Website, Formulare, API, MCP, Verwaltung) · <code>Api/</code> CmsService, Tokens, OpenApi</li>
      <li><code>Data/</code> Tabellen, Einträge, Regeln, Formulare, Eingänge, Kalender, Geteilte Daten · <code>Blocks/</code> Kern-Blöcke</li>
      <li><code>Network/</code> Netzwerk &amp; SSO · <code>Search/</code> Website-Suche · <code>AI/</code> <?= e($aiBrand) ?> · <code>Review/</code> Freigabe · <code>Support/</code> Support &amp; Wissen</li>
      <li>Pages, Media, MediaPools, MediaTracks, Fields, Settings, Auth, Totp, Passkeys, Mfa, SpamGuard, FormCrypto, Mailer, Seo, StructuredData, Design, Icons, Proxy, Maps, PageCache, AppIcons</li></ul></div>
    <div class="doc-tree__box"><strong>kits/ · extensions/</strong><ul>
      <li><code>kits/{name}/theme.php</code> Blöcke, Einstellungs-Schema, Formular-Vorlagen, Design-Tokens (Rückfall: <code>themes/{name}</code>, Pfade über <code>Core\Kit</code>)</li><li><code>app/Views/fragments/</code> Kern-Fragmente (<code>Core\Fragments</code>): Video, Editor, Werkzeugleiste nur Kern; Marke, Sprachumschalter, Öffnungszeiten … überschreibbar</li><li><code>blocks/</code>, <code>templates/</code>, <code>functions.php</code>, <code>seed.php</code>, <code>docs/manual.php</code></li>
      <li><code>extensions/{name}/extension.php</code> – optionale Funktionen (z. B. <code>dav</code>)</li></ul></div>
    <div class="doc-tree__box"><strong>Außerhalb des Webroots</strong><ul>
      <li><code>config/</code> config.php, config.local.php (Geheimnisse), <code>sites/{key}.php</code></li><li><code>storage/</code> Datenbanken, Caches, Sitzungen, Logs, Suchindex, Sicherungen, <code>pools/</code>, <code>shared/</code>, <code>support/</code>, <code>ai/</code></li>
      <li><code>vendor/</code> Composer · <code>resources/</code> Quellen (CSS/JS, Symbole, Service-Worker) · <code>tools/</code> Build · <code>bin/console</code> · <code>deploy/</code></li></ul></div>
  </div>
  <h3>Anfrage-Ablauf</h3>
  <ol class="doc-steps">
    <li><code>public/index.php</code> lädt <code>app/bootstrap.php</code>: Website anhand des Hosts wählen (<code>Core\Sites</code>), Konfiguration zusammenführen, Datenbank-Migration, Kit und Erweiterungen laden, beim ersten Start <code>seed.php</code>.</li>
    <li>Router (<code>app/routes.php</code>) → Controller. Sitzungen nur für <code>/admin</code> bzw. angemeldete Nutzer – Besucher bekommen kein Cookie.</li>
    <li>Routing <code>/{pfad*}</code>: Seitenbaum (<code>pages.path</code>) → Detailseite einer Datentabelle (<code>/{url-basis}/{slug}</code>) → Weiterleitung alter Adressen → 404. Eine Seite mit gleichem Pfad hat Vorrang vor Kern-Routen wie <code>/suche</code>.</li>
    <li>Seiten: Editor.js-JSON (<code>pages.content_draft</code> / <code>content_published</code>) → je Block <code>kits/{name}/blocks/{type}.php</code> (Kern-Blöcke: <code>app/Blocks/</code>) → <code>templates/layout.php</code>.</li>
    <li>Ganzseiten-Cache für Besucher (<code>{storage}/cache/pages</code>), wird bei jeder Änderung geleert; danach gleicht der Suchindex im Hintergrund ab.</li>
  </ol>
  <h3>Datenmodell (je Website)</h3>
  <table class="doc-table">
    <tr><th>Tabelle</th><th>Inhalt</th></tr>
    <tr><td><code>pages</code></td><td>slug (je Ebene eindeutig), parent_id, path, menu, nav_title, type (page | template), template_for, title, meta_title, meta_description, og_image, status, is_home, noindex, lang, translation_group, content_draft, content_published (Editor.js-JSON)</td></tr>
    <tr><td><code>revisions</code></td><td>Stände je Seite (Anzahl: <code>revisions</code>, Standard 20) inkl. Nutzer bzw. API-Token; identische Folgestände werden nicht doppelt gespeichert</td></tr>
    <tr><td><code>data_tables</code></td><td>Definition der Datentabellen: handle, name, singular, description, icon, fields_json, settings_json, sort, Zeitstempel</td></tr>
    <tr><td><code>data_{handle}</code></td><td>Einträge: id, slug, status, sort, created_at, updated_at, published_at, lang, translation_group + je Feld eine Spalte (Eingänge: nur Metadaten + verschlüsseltes <code>payload</code>)</td></tr>
    <tr><td><code>data_{handle}__{feld}</code></td><td>n:m-Verknüpfungen (entry_id, target_id, sort)</td></tr>
    <tr><td><code>settings</code></td><td>Key/Value (JSON): Kit-Einstellungen ohne Präfix (übersetzt: <code>feld@en</code>), System mit <code>sys.</code>, Design mit <code>design.{theme}</code></td></tr>
    <tr><td><code>media</code>, <code>media_collections</code>, <code>media_collection_items</code></td><td>Datei, Alt-Text/dekorativ, Titel, Fotonachweis, Tags, Fokuspunkt, Zuschnitte (<code>crops</code>), Übersetzungen (<code>i18n</code>), <code>pool_ref</code> (Verweis auf eine Pool-Datei), Transkripte; Sammlungen (n:m)</td></tr>
    <tr><td><code>media_tracks</code>, <code>media_jobs</code></td><td>Untertitel-/Kapitel-Spuren (WebVTT, Entwurf/veröffentlicht) und Hintergrund-Aufträge (Transkription, Übersetzung)</td></tr>
    <tr><td><code>users</code>, <code>roles</code></td><td>Konten (password_hash, role, locale, favorites, disabled, auth_ver, network_uid, totp_secret, mfa_last …), <code>user_passkeys</code> (Passkeys je Konto und Domain) und Rollen (permissions_json, tables_json)</td></tr>
    <tr><td><code>api_tokens</code></td><td>Tokens (nur SHA-256-Hash), Stufe read/write, Ablaufdatum, <code>review_mode</code> (direct | review), <code>mcp_client</code></td></tr>
    <tr><td><code>change_log</code></td><td>Herkunft und Freigabe aller Änderungen über API, MCP und KI (siehe <a href="#freigabe">Prüf-Ebene</a>)</td></tr>
    <tr><td><code>inbox_log</code></td><td>Protokoll der Eingänge: Entschlüsseln, Status, Zuweisung, Löschen (ohne Inhalte)</td></tr>
    <tr><td><code>hits</code></td><td>Rate-Limits und Einmal-Tokens (IP nur als HMAC)</td></tr>
  </table>
  <p>Installationsweit kommen hinzu: die Netzwerk-Tabellen <code>network_sso</code> und <code>network_log</code> in der Datenbank der Netzwerk-Website, <code>storage/pools/{key}</code> (geteilte Medien), <code>storage/shared/{key}</code> (geteilte Datentabellen), <code>storage/support/support.sqlite</code> und je Website <code>{storage}/search/</code> (Suchindex) sowie <code>{storage}/ai/</code> (KI-Zähler und Verlauf). Erweiterungen legen eigene Tabellen an (z. B. <code>dav_passwords</code>, <code>dav_objects</code>). Die frühere Tabelle <code>requests</code> wird nicht mehr angelegt; sie wird nur bei alten Installationen einmalig in Eingänge übernommen (siehe <a href="#formulare">Formulare &amp; Eingänge</a>).</p>
  <p>Schema-Änderungen sind <b>additiv</b> (neue Tabellen/Spalten über <code>Database::migrate</code> bzw. <code>ensureColumns</code>), damit ein Rollback auf das vorherige Release funktioniert. Die Website-Datenbank migriert beim ersten Aufruf nach einem Update selbst; geteilte, Netzwerk- und Support-Datenbanken aktualisiert <code>php bin/console migrate --all</code>.</p>
