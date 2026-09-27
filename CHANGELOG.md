# Changelog

Übersicht der Funktionsbereiche von **KLXM Studio** (früher „MyCMS.dev light“). Die Versionsnummer steht in
`app/bootstrap.php` (`CMS_VERSION`); Einzelheiten zu jedem Bereich im Entwicklerhandbuch (`/admin/hilfe/technik`)
und im Handbuch für die Redaktion (`/admin/hilfe`).

## 1.0.0

### Hinweise zu diesem Projekt (Ergänzung zum Handbuch)
- **Kits bringen projektbezogene Hinweise für die Redaktion mit:** Markdown-Dateien in `themes/{kit}/guide/NN-name.md`
  (`Core\Guide`), sortiert nach Dateinamen, Titel aus der ersten Überschrift `# …`, optional Front Matter `bereich:`,
  `block:`, `titel:`, `ausblenden:`. Markdown-lite wie im Support (HTML nie, Links über `Sanitizer::safeHref`), Bilder als
  eigene Zeile `![Alt](bild.png)` aus dem guide-Ordner (nur für Angemeldete, kein SVG). Optional `theme.php → 'guide'`
  (`title`, `author`, `lead`, `dir`, `false` = aus).
- **Nur wenn es Hinweise gibt:** Kapitel „Hinweise zu diesem Projekt“ im Handbuch gleich nach dem Überblick (mit Website-
  und Kit-Namen, Einleitungssatz „ergänzen das Handbuch“), Link im Kopf des Handbuchs, Eintrag „Projekt-Hinweise“ in der
  Übersicht („Neu hier?“) und unter „Hilfe & Support“, Treffer in der Suche (⌘K) und im Hilfe-Index des Assistenten.
- **Kontext-Links:** `bereich: medien` bzw. `daten/{tabelle}` zeigt über dem Inhalt des Bereichs „Hinweis zum Projekt: …“,
  `block: stage` einen Link im Block-Formular.
- **Je Website anpassen:** Handbuch → Projekt-Hinweise (`/admin/hilfe/projekt`, Recht `system.manage`, CSRF) – eigene
  Hinweise in `{storage}/guide/*.md`, Kit-Hinweise mit gleichem Dateinamen ersetzen oder ausblenden, Anpassung entfernen.
- Konsole `guide:list` und `guide:selftest`; Entwicklerhandbuch „Kits & Design → Hinweise zu diesem Projekt“.

### Weiterleitungen & 404-Protokoll
- **Administration → Weiterleitungen** (`Core\Redirects`, Funktion `redirects`, Recht `redirects.manage`): alte Adresse →
  Seite (`page:ID[#anker]`, folgt Umbenennungen), Pfad oder `https://…`; 301 (Standard), 302 oder 410. Suche, Filter,
  Sammelaktionen (löschen, aktivieren, deaktivieren), Treffer und letzter Aufruf, „Adresse testen“ („Wohin führt …?“).
- **Nur wenn keine Seite passt:** aufgelöst im 404-Weg (`App::handle`) – bestehende Seiten und Routen gehen immer vor.
  Vergleich ohne Domain, Schrägstrich am Ende und ASCII-Groß/Kleinschreibung, Prozent-Kodierung/UTF-8 egal
  (`/agentur/m%C3%BCntel/`); `*` am Ende als Präfix, `*` im Ziel übernimmt den Rest; Query der Anfrage bleibt erhalten;
  keine Ketten, Schleifenschutz; mehrsprachig mit Sprachpräfix.
- **Automatisch:** Umbenennen/Verschieben veröffentlichter Seiten (auch ganzer Zweige) legt „alter Pfad → page:ID“ an
  (`Pages::rebuildPaths`); ohne Duplikate und Ketten, Regeln auf wieder vergebenen Adressen entfallen.
- **404-Protokoll:** die letzten 200 nicht gefundenen Pfade mit Anzahl (ohne IP), Hinweis „interner Link“, Vorschlag und
  Anlegen per Klick.
- **Import/Export** CSV (`quelle;ziel;code;notiz`) und JSON (`[{"from","to"}]` wie Umzugs-Mappings), Probelauf;
  Konsole `redirects:import|list|test|selftest`; `GET /api/v1/redirects` und MCP `list_redirects` (nur lesen).

### Vorschaubilder für Videos (automatisch, mit ffmpeg)
- **Videos bekommen automatisch ein Standbild als Vorschaubild**, wenn ffmpeg auf dem Server installiert ist
  (`Core\VideoThumbs`): ≈ 10 % der Laufzeit (1–10 s), Filter `thumbnail`, Schwarzblenden werden übersprungen; WebP in
  den Breiten aus `media.sizes` (bis 1600 px) unter `{medien}/cache/{zufall}-v-{breite}.webp`, vermerkt in
  `variants_json['poster']` – unveränderliche Dateinamen, lange cachebar. Kein bewegtes Bild.
- **Wo:** Mediathek (Raster, Liste, Mehrfachauswahl, Quick Look, Informationen), Auswahldialog, Medien-Felder in Blöcken
  und Datentabellen (`Fields::mediaPreview`), Website: `Media::posterFor()` fällt auf das Vorschaubild zurück – die
  Video-Blöcke aller Kits, das Hero-Hintergrundvideo und `MediaTracks::player` bekommen ohne eigenes Poster eins.
- **Wann:** nach Upload/Import/Ersetzen im Hintergrund (nach der Antwort); ältere Videos lazy beim Ansehen in der
  Verwaltung (`GET /admin/api/media/{id}/thumb`, IntersectionObserver, Platzhalter mit Video-Symbol ohne Springen);
  `php bin/console media:thumbs [--missing|--all] [--pool=key]` zum Nachholen. Die Website erzeugt nie selbst.
- **Grenzen & Sicherheit:** Sperre je Datei, höchstens 2 gleichzeitig (`media.video_thumbs_parallel`), 10 s Zeitlimit
  (`media.video_thumbs_timeout`), `nice`, Fehlschläge 6 h gemerkt; nur `proc_open` mit Argument-Array, nur Videos der
  Mediathek, Anmeldung + „Dateien hochladen“, Ausgabe nur im Medien-Cache. Abschalten: `media.video_thumbs = false`.
- **Vorrang:** gewähltes Poster (Block, Video-Werkzeuge „Poster wählen“) > automatisches Vorschaubild > Platzhalter.
- **ffmpeg-Erkennung im Kern** (`Core\Ffmpeg`): per Aufruf statt Dateiprüfung – funktioniert mit `open_basedir`
  (Plesk). Konfiguration `ffmpeg_path`/`ffprobe_path`, sonst PATH, `/usr/bin`, `/usr/local/bin`, `/opt/homebrew/bin`,
  `storage/video/bin` (`ffmpeg_search => false`: nur der Pfad); Ergebnis 1 h in `storage/cache/ffmpeg.json`.
  `health`: „ffmpeg für Video-Vorschaubilder: gefunden/fehlt (optional)“ – nur Warnung. Die Erweiterung video_tools und
  die lokale Transkription nutzen dieselbe Erkennung.
- **open_basedir-Korrekturen:** Unterprozesse (ffmpeg, whisper, Hintergrund-Arbeiter der KI und von video_tools)
  öffnen kein `/dev/null` mehr (Pipes). `Stats::phpBinary()` prüft Kandidaten per Aufruf (mind. PHP 8.4) statt per
  `is_executable` – unter Plesk-FPM wurde sonst `/usr/bin/php` (System-PHP 8.1) gestartet und Arbeiter liefen nie.
  Empfohlen: `'php_cli' => '/opt/plesk/php/8.x/bin/php'` in `config.local.php`.
- **Video-Werkzeuge:** animierte Vorschau (Hover-Schleife) jetzt Standard AUS (`video_tools.preview`); wartende
  Aufträge ohne Arbeiter zeigen nach 2 Minuten „wartet auf Hintergrunddienst“ mit Hinweis statt eines endlosen Kreisels.

### Funktionen & Erweiterungen in der Verwaltung schalten
- Neue Seite **Administration → Funktionen & Erweiterungen** (`/admin/funktionen`, `FeaturesController`, `Core\FeatureInfo`):
  Funktionen gruppiert (Inhalte, Daten, Medien, KI, Schnittstellen, Kommunikation, Sicherheit & Betrieb) mit Schalter,
  Kurzbeschreibung, „Was passiert beim Einschalten“ (Rechte, Menü, externe Anfragen, Cron, Website, gespeicherte Daten),
  Abhängigkeiten und Status (an / aus / ruht / „per Konfiguration festgelegt“). Deutlicher Hinweis oben: nur einschalten,
  was die Website wirklich braucht.
- **Wer schaltet:** neues Recht `system.features` („Haupt-Admin“; Administration hat es). Netzwerk-Installationen: Netzwerk-
  Administration und Integratoren schalten je Website; die Website-Administration sieht die Seite nur lesend („Freischaltung
  durch die Agentur“), bis das Netzwerk sie freigibt (`sys.features_delegate`, Standard aus).
- **Sicherheitsrelevante Schalter** (REST-API, MCP, KI, Besucher-Chat, Externe Quellen, Video-Werkzeuge, CalDAV/CardDAV):
  Dialog mit konkretem Risiko-Text, Bestätigung und Passwort (ohne JavaScript direkt in der Zeile).
- **Erweiterungen** (lokal und Composer) mit Version, Autor, Lizenz, Beschreibung, Voraussetzungen (live, z. B. ffmpeg/ffprobe,
  proc_open), Gesundheitsprüfungen, Dokumentation und „Wird verwendet von …“; Aktivieren/Deaktivieren. Aktivieren startet
  sofort (Migrationen, einmaliger `install`-Haken, eigene Standard-AUS-Funktionen mit), Deaktivieren ist nicht destruktiv
  (Routen, Menü, Rechte, CSP, Befehle entfallen; Cron-Befehle aus `'commands'` beenden sich mit Exit 0).
  Neue Manifest-Angaben: `author`, `license`, `homepage`, `risk`, `provides`, `requirements`, `usage`, `commands`, `docs`,
  `install`, `deactivate`, `required`.
- **Vorrang bleibt:** Werte aus `config/sites/{key}.php` bzw. `config.local.php` (Preset, `features`, `extensions`) gehen vor
  und sperren den Schalter. `'extensions' => ['dav' => false]` sperrt jetzt auch „aus“.
- **`php bin/console features:release [--dry-run] [--only=features|extensions]`** überträgt festgelegte Werte in die Schalter
  der Verwaltung und entfernt sie aus der Datei (Sicherung `.bak`, Prüfung, Diff; wirksamer Stand bleibt gleich).
  Dazu `features:list`; `extensions:list` zeigt die Quelle.
- **Protokoll:** Tabelle `feature_log` (letzte 10 Änderungen auf der Seite), im Netzwerk zusätzlich `network_log`.
  Netzwerk-Übersicht: Erweiterungen und Freigabe je Website, Schnellzugriff „Funktionen & Erweiterungen“.

### Tutorials: Videos auf studio.klxm.de, Text in der Verwaltung
- **Tutorial-Videos und Trailer werden nicht mehr mit dem CMS ausgeliefert** (≈ 123 MB weniger: `public/assets/tutorials`
  ≈ 72 MB, `public/assets/trailer` ≈ 51 MB). Sie liegen auf der Produkt-Website https://studio.klxm.de
  (`/tutorials/{kurzname}`, Englisch `/en/tutorials/{kurzname}`, Trailer `/#trailer`); `.gitignore` und `deploy/deploy.sh`
  schließen beide Ordner aus.
- Neue Einstellung **`docs_url`** (Standard `https://studio.klxm.de`): Handbuch & Hilfe › Tutorials, die Tutorial-Seiten und
  „Hilfe & Einstieg“ auf der Übersicht verlinken dorthin („Video ansehen ↗“, neuer Tab, `rel="noopener"`) – ohne Einbettung,
  Vorladen oder Anfrage nach außen. Agenturen setzen eine eigene Adresse (White-Label) oder `''` (keine Links nach außen).
  Die Schritte, Tipps und Stolperfallen bleiben als Text offline in der Verwaltung; Kit-Tutorials mit eigenem Video
  (`'video' => '/themes/…'`) werden weiter lokal eingebettet.
- Tutorials jetzt auch **auf Englisch** (`app/Admin/tutorials.en.php`: Titel, Ziele, Schritte = englische Untertitel, Tipps).
  Neue Klasse `Core\Tutorials` (Katalog, Links, Export) für HelpController, Übersicht und Kommandozeile.
- **`php bin/console tutorials:export [--out=datei.json] [--videos=ordner]`**: Katalog als JSON für die Produkt-Website
  (Schritte DE/EN, Tipps, Voraussetzungen, Handbuch-Anker, Dauer, Dateinamen).
- Aufnahme-Werkzeuge bleiben in `tools/` (nur Entwicklung); Ausgabe über `TUT_OUT` bzw. `TRAILER_OUT`, Standard
  `../klxm-studio-website/site-tools/tutorials` bzw. `…/trailer` neben dem Projektordner.

### Kopfbereich: Suche, Aktionen, Kontakt (Kopfbereich-Aktionen)
- Neu `Core\HeaderActions` mit `header_actions()`, `header_actions_head()`, `header_actions_lang()`, `header_cta()` und
  Markup `app/Views/header-actions.php` (je Kit überschreibbar: `templates/partials/header-actions.php`). Einstellbar pro
  Website unter **Design › Kopfbereich: Suche & Aktionen** (Gruppe `HeaderActions::designGroup()`), Texte und Links unter
  **Website › Darstellung** (`HeaderActions::settingsFields()`).
- **Handlungsaufruf:** gefüllt, Kontur, Textlink mit Pfeil, Symbol + Text, geteilt (Aktion + Anruf/E-Mail-Taste), Chip mit
  Statuspunkt oder keiner – immer 8 px Ecken, Look vom Kit. Inhalt: eigene Beschriftung/Link oder Vorgaben Kontakt, Termin,
  Anrufen, E-Mail, Newsletter (suchen Seite bzw. Abschnitt selbst); optional eine zweite Aktion.
- **Suche:** Lupe mit Popover, Suchfeld in der Leiste mit Vorschlägen, aufziehende Lupe, Befehlsfeld „Suchen … ⌘K“,
  Suchleiste unter dem Kopf; Anordnung „Aktionen rechts“, „Suche mittig“, „Suche darunter“. Tastatur: `/` und ⌘K/Strg+K
  springen in die Suche, Escape schließt; Vorschläge mit `aria-live`. `Search::form()` kennt dafür `panelOnly` und `Search::panelId()`.
- **Kontakt-Chip** (Telefon/E-Mail) mit Öffnungsstatus aus den Öffnungszeiten („Hofladen geöffnet“ über
  `header_status_label`), **Sprachumschalter** als Kürzel, Aufklappliste oder Namen (ohne Flaggen), **Social-Symbole**,
  Link **„Anmelden“** für Mitgliederbereiche.
- **Nicht immer ein Button:** Stil **„Kontakt-Menü“** – ein Symbol (Sprechblasen, Telefon, Adressbuch, Headset) öffnet eine
  Liste aller Kontaktwege aus den Website-Angaben: Anrufen mit Nummer, E-Mail, Kontaktformular, Anfahrt (Adresse + Karte),
  Öffnungszeiten mit „jetzt geöffnet“, WhatsApp/Signal nur bei vorhandenem Feld; `<details>` (ohne JavaScript bedienbar),
  Escape/Klick daneben schließen, Fokus zurück, `aria-expanded`. Stil **„Menüpunkt“** – die Aktion als abgesetzter letzter
  Punkt der Navigation (Trennlinie, Akzent, Pfeil); steht das Ziel schon im Menü, rückt es ans Ende (`HeaderActions::menu()`).
- **Eigene Standards je Kit** (nur zwei mit klassischem Button): basis Menüpunkt „Kontakt“ + Lupe · fluid Suchfeld + Textlink
  „Projekt besprechen →“ · editorial Befehlsfeld ⌘K + Newsletter-Link (zur Datumszeile) · essenz Kontakt-Menü als
  Geräte-Paneel + Telefon-Chip mit Statuspunkt · modern geteilte Aktion „Beratung buchen“ + Anruf, aufziehende Suche · glas
  Glas-Befehlsfeld im Dock + Kontakt-Menü als Glaspaneel · nature Kontakt-Chip „Hofladen geöffnet“ + Textlink „Termine →“ ·
  starter gefüllter Button „Kontakt“ + Lupe (kommentierte Vorlage). Die basis-Vorlagen zeigen weitere Kombinationen.
- Mobil (390 px): Felder werden zur Lupe bzw. stehen im Menü, Text-Aktionen wandern ins Menü, Symbol-Aktionen bleiben
  (44 px), kein seitliches Scrollen; Glas-Dock: Suche und Aktion im Glasblatt „Mehr“.
- CSS und JavaScript nur mit den benötigten Teilen, gebündelt zu je einer Datei `public/assets/ha/{hash}.css|js` (typisch
  3–5 KB CSS, JavaScript ≈ 1 KB für die Tastenkürzel, Status/Aufklappliste nur bei Bedarf), nie JavaScript im
  Bearbeitungsmodus; Kit-Regeln optional in `css/header-actions-kit.css` (`'kit_css'`), schlichte Auswahl ohne Kern-Datei (`'base_css' => false`); kein Inline-Skript/-Stil, keine externen Anfragen. Die frühere Einstellung „Button im Kopfbereich“
  (`nav_cta`/`header_cta`) geht in „Handlungsaufruf – Stil“ auf (aus → „Kein“).
- Handbuch „Kopfbereich: Suche, Aktionen, Kontakt“ (Kapitel Design), Entwicklerhandbuch → Kits & Design „Kopfbereich-Aktionen“.

### Einstieg (Hero): neue Varianten in allen Kits
- Jedes Kit hat mindestens drei neue Varianten des Blocks „Einstieg (Hero)“ – je im Charakter des Kits, mit
  Musterseite „Hero-Varianten“ (`tools/demo.php --heroes`, neue Websites automatisch):
  basis `search` (Such-Einstieg mit Vorschlägen und Chips), `form` (Text + Rückruf-Formular), `map` (Karte + Kontaktkarte
  mit „jetzt geöffnet“) · fluid `scale` (fließende Typo-Skala, Container-Query-Layout), `collage` (3–5 Bilder mit
  schwebender Textkarte), `compare` (Vorher/Nachher) · editorial `issue` (Titelgeschichte mit Ausgabe-Zeile und
  „Außerdem in dieser Ausgabe“), `agenda` (nächste Termine/Meldungen in Zeitungsspalten), `voice` (Stimme mit Initialen
  und Bewertungszeile) · essenz `console` (Bedienfeld mit Anzeigen), `dials` (Kennzahlen als Rundinstrumente), `monitor`
  (Video im Geräterahmen) · modern `product` (Bildbühne mit Datenblatt), `marquee` (Riesenschrift mit Laufzeile),
  `figures` (Aussage mit Zahlen-Kacheln) · glas `command` (Aurora + gläserne Befehlssuche), `video` (Hintergrundvideo mit
  Glaspaneel), `stack` (Karten-Stapel, fächert auf) · nature `season` (Jahreszeiten-Illustration + Öffnungsstatus),
  `dates` (Markttage/Termine), `form` (Anfrage auf Papierkarte) · starter `search` als kommentiertes Beispiel.
- Bestehende Varianten und Inhalte rendern unverändert; CSS der neuen Varianten lädt nur bei `hero:variante`
  (Grundlage je Kit weiter < 30 KB CSS, < 8 KB JS).
- Kern: `Core\Blocks\Hero` (Feld-Voreinstellungen und Bausteine für Suche, Video, Kennzahlen, Termine, Formular, Karte,
  Vorher/Nachher, Laufzeile), `public/assets/js/hero.mjs` (≈ 1,3 KB: Video nur sichtbar und ohne „Bewegung reduzieren“,
  Pause-Schaltflächen, Regler), `Search::form('hero', '', ['label', 'placeholder'])`, Kern-Kennzahlen eingebettet (`_bare`).
- Felder je Variante: `'variants' => [...]` an einem Blockfeld – die Seitenleiste zeigt es nur bei diesen Varianten.
  Blockdefinition: `'uses'` (Variante bringt Stylesheets anderer Blöcke mit), `'variant_help'` (Handbuch → Alle Blöcke:
  „Einstieg: welche Variante wann?“). `conditional_css` mit „Typ:Variante“ gilt jetzt auch für Skripte.
- `i18n:missing en` berücksichtigt die `lang/en.php` des aktiven Kits.

### Neue Übersicht für die Redaktion
- `/admin` neu gestaltet (`Core\Dashboard\Dashboard`, `Core\Dashboard\Metrics`): Begrüßung mit Schnellaktionen nach
  Rolle, **Kennzahlen** als Rundinstrumente (Seiten online, Einträge, Medien mit Alt-Text-Anteil, neue Anfragen,
  Eingereicht, Aktualität) mit Trend 30 Tage gegen die 30 Tage davor – nur echte Zahlen, kein Besucher-Tracking.
- **Was ist zu tun?**: Aufgaben nach Dringlichkeit mit Anzahl, Erklärung und Direktlink (Anfragen, Freigaben,
  Support, Chat, Einrichtung inkl. Impressum, alte Entwürfe, Alt-Texte je Sprache, Videos ohne Untertitel, SEO-Beschreibung,
  externe Quellen); „Alles erledigt“, wenn nichts offen ist. Mediathek-Prüfung per `#check=noalt|missing:en|nocaptions`.
- Statistiken als Inline-SVG mit Tabellen-Alternative, nachgeladen über `GET /admin/api/dashboard/{karte}` und
  5 Minuten je Website zwischengespeichert: Aktivität, Anfragen je Woche, meistbearbeitete Seiten, Termine (7 Tage),
  Suchbegriffe ohne Treffer, Technik & Betrieb (Administration). Dazu „Zuletzt bearbeitet“ und „Hilfe & Einstieg“
  (KLXM Ai fragen, Tutorials der Rolle, Trailer, Neuigkeiten).
- Karten zuklappen, verschieben (Pfeile, Ziehen) und ausblenden – je Konto in `users.ui_prefs`, zurücksetzbar, auch ohne JavaScript.
- Neuer Erweiterungs-Haken `Extension::dashboard(fn($user))` für eigene Kacheln und Karten (z. B. eine spätere Matomo-Erweiterung).
- Handbuch-Kapitel „Die Übersicht“, Entwicklerhandbuch → Verwaltung und Funktionsumfang & Erweiterungen.

### Begriff „Kit“ statt „Theme“
- In Verwaltung, Handbuch, Entwicklerhandbuch, Tutorials, CLI-Hilfe und READMEs heißt das Paket aus Design-Tokens,
  Blöcken, zentralen Angaben, Datenlisten, JSON-LD, Startinhalten, Sprachdateien und Logik jetzt **Kit** (DE + EN,
  Mehrzahl „Kits“). Der gestalterische Teil darin heißt weiterhin **Design** (Style-Editor).
- Technisch bleibt alles kompatibel: Ordner `themes/{name}/`, Datei `theme.php`, Konfiguration `'theme' => …`,
  `app()->theme`, `Core\Theme`, `sys.theme`, API-/MCP-Felder `theme` und die Befehle `theme:*`.
  Neu als Alias: `'kit' => …` in `config/sites/{key}.php` bzw. `config.local.php`, `kit:list` und `kit:create`.

### Start-Kit „starter“ für eigene Kits
- Neues Kit **`themes/starter`**: das kleinste vollständige Kit als Ausgangspunkt – `theme.php` in Abschnitten § 1–11
  kommentiert (Meta, Abschnitte, SEO/JSON-LD, Projekt, Assets/conditional_css, Design-Tokens mit Voreinstellungen und
  Navigationsvarianten, Website, Blöcke, Daten, Startinhalte, Sprachen), sechs Beispielblöcke plus Video/Downloads,
  Systemschrift, eine Akzentfarbe, hell/dunkel, Kern-Blöcke nur über Variablen (`css/core.css`), Deutsch/Englisch,
  `tools/contrast.php`, README mit Schritt-für-Schritt-Anleitung. Budget der Startseite ≈ 15,7 KB CSS / 0,4 KB JS.
- `kit:create <name> [--from=starter|basis|…]`: Standardvorlage ist jetzt „starter“ (alte Form `theme:create <name>
  <vorlage>` geht weiter); benennt zusätzlich `description`, `version` (0.1.0), Paketnamen und Pfade um und zeigt die
  nächsten Schritte. Entwicklerhandbuch → Kits & Design: „Eigenes Kit entwickeln – mit dem Start-Kit“.
- Kits als Composer-Pakete (Typ `klxm-studio-kit`) sind vorgesehen, aber noch nicht umgesetzt (Stub im Start-Kit).

### Tutorials und Trailer ohne Ton – neu aufgenommen
- Alle Tutorial-Videos und der Trailer „KLXM Studio im Überblick“ sind jetzt **ohne Ton** (keine Tonspur), mit Untertiteln
  Deutsch und Englisch; eine Videodatei je Tutorial für beide Sprachen (`{name}.en.mp4` entfällt, Englisch = `.en.vtt`
  voreingestellt). Neu aufgenommen mit der aktuellen Oberfläche: Seitenleiste mit Bereichsfarben, „Hilfe & Support“,
  neue Übersicht, Begriff „Kit“.
- Neue Tutorials: „Die neue Übersicht“, „Videos optimieren und schneiden“, „Externe Quellen anbinden“,
  „Cookie-Einwilligung mit dem Consent Kit“, „Eigenes Kit mit dem Start-Kit“ (`kit:create`).
- `tools/tutorials/record.mjs` und `tools/trailer/trailer.mjs`: Standard ohne Ton, Dauer je Schritt aus der Lesezeit des
  Untertitels (≈ 15 Zeichen/s, 2,2–6 s); Sprecher (Piper/Chatterbox) nur noch optional mit `--voice` als
  Entwicklungswerkzeug. Cookie-Hinweis des Consent-Kits wird in Aufnahmen ausgeblendet (außer im Consent-Tutorial).
- `videos.json`/`trailer.json` mit `"audio": false`; `HelpController` und die Hilfeseiten unterstützen weiterhin auch die
  ältere Aufteilung (`silent/`, `.en.mp4`). Sprecher-Nachweis und „gesprochen“-Hinweise erscheinen nur noch bei Videos mit
  Ton; THIRD-PARTY-NOTICES Abschnitt 5: keine Stimmdaten mehr ausgeliefert.

### Erweiterungen: Mediathek, Hintergrund, Betrieb (generische Haken)
- Neue Haken in `Core\Extension`: `feature(…, false)` (Funktion Standard aus), `adminAssets` (CSS/JS je
  Verwaltungsansicht, CSP unverändert), `health` (Zeilen für `bin/console health`), `afterAdminResponse` (Arbeit nach
  der Antwort, z. B. Hintergrund-Aufträge), `on('media.imported'|'media.replaced'|'media.deleted')`, `mediaChecks`
  (Filter unter „Prüfen“, API `check=…`, `counts.x_…`, `ext_checks`), `mediaJson` (`ext.{name}` je Datei),
  `mediaTypes` (weitere Video-/Audio-Typen mit Prüfung des Dateianfangs), `mediaPoster` (`Media::posterFor()` – von
  den Video-Blöcken aller Kits und `MediaTracks::player` genutzt), `docs('manual'|'technical')` (Kapitel im Handbuch
  und Entwicklerhandbuch).
- Mediathek im Browser: `window.CMSMedia.extend()` mit Haken `loaded`, `badge`, `panel`, `multi`, `summary`, `menu`,
  `quickLook` und Helfern `CMSMedia.ui`.
- Composer: Paket-Typ `klxm-studio-extension` wird zusätzlich zu `mycms-extension` erkannt; `extensions:publish`
  kopiert ohne `public/` die `assets/` (nur wenn noch nichts veröffentlicht ist); `i18n:missing en --extension=name`.
- Neue Erweiterung **`video_tools`** (Video-Werkzeuge, eigenes Paket `klxm/studio-video-tools`): Videos analysieren,
  fürs Web optimieren (Presets), schneiden (Untertitel werden mitgeschnitten), Poster/animierte Vorschau, Hintergrund-
  Aufträge mit ffmpeg; Details in `extensions/video_tools/CHANGELOG.md`.

### Lizenz
- Lizenz: **MIT** statt GPL-3.0-or-later (joomla/string durch eigene MIT-Implementierung ersetzt,
  `lib/compat/joomla-string`). Gilt für Kern, Themes und Erweiterungen (`LICENSE`, `COPYRIGHT`, SPDX-Zeile in den
  Einstiegspunkten); eigene Themes und Erweiterungen dürfen jede Lizenz tragen.
- `THIRD-PARTY-NOTICES.md`: vollständige Liste der Drittsoftware, Schriften, Symbole, KI-Modelle/Stimmen, Daten und
  Medien; in der Verwaltung unter Handbuch & Hilfe → **Lizenzen**. Lizenzprüfung für CI: `node tools/licenses.mjs`.

### Externe Quellen
- **Feeds, JSON-APIs, XML und OpenImmo** als Datenquelle: Daten → Externe Quellen (`Core\Sources`, Funktion
  `sources`, Standard aus; Recht `sources.manage`). Zuordnung Quelle → Tabellenfelder mit Umwandlungen (Text, HTML
  bereinigen, Datum, Zahl, Ja/Nein, Slug, Vorlage, Werte ersetzen, Standardwert), Bildübernahme in die Mediathek
  (neu kodiert, ohne EXIF), Vorschau & Test vor dem Speichern, Vorlagen „RSS/Atom-Feed“ (Tabelle „Meldungen“),
  „OpenImmo“ (Tabelle „Immobilien“ mit Detailseite, ZIP-Upload oder Abholadresse, Adressfreigabe beachtet) und
  „JSON-API“/„XML“.
- Abgleich per Prüfsumme (nur Geändertes), fehlende Einträge ausblenden/löschen/behalten, übernommene Einträge
  schreibgeschützt (Verwaltung, API, MCP, DAV) mit Badge „aus Quelle“, Protokoll der letzten 20 Abrufe, Zeitplan
  (stündlich/täglich) per `sources:sync [--all]` oder nebenbei; `sources:list`; `health` meldet fehlerhafte Quellen.
- Sicherheit: SSRF-Schutz (nur öffentliche Adressen, feste IP, geprüfte Weiterleitungen), Größen-/Zeitlimits,
  Content-Type-Prüfung, XML ohne Entities (XXE), ZIP ohne Pfade (Zip-Slip, Zip-Bombe), Zugangsdaten verschlüsselt
  (`Settings::encrypt`), Rate-Limits. Testdaten: `tools/fixtures/sources/`.

### Plattform
- Framework-freier PHP-8.4-Core mit Front-Controller, ohne `.htaccess`; nur `public/` im Webroot.
- **Multi-Site**: beliebig viele Websites je Installation (`config/sites/{key}.php`) mit eigener Domain, Datenbank
  (SQLite oder MySQL), Medien, Benutzern und Schlüsseln; `site:create`, `fallback_site`.
- **Netzwerk-Administration**: zentrale Konten mit Übersicht aller Websites (Status, Hinweise, Anfragen, Einreichungen,
  Speicher, Sicherungen), Wartungsmodus, „Sicherung jetzt“, Single Sign-on per Einmal-Token und Protokoll.
- **Zwei-Faktor-Anmeldung** (TOTP, Wiederherstellungscodes), Pflicht für Netzwerk-Konten und wählbare Rollen.
- **Rollen & Rechte** frei zusammenstellbar, Datenrechte je Tabelle; Funktionsumfang je Website über Presets
  (`full`, `content`, `minimal`), einzelne Funktionen und Block-Listen; Integratoren.
- **Erweiterungen** (lokal oder als Composer-Paket) mit Blöcken, Routen, Rechten, Migrationen, CLI-Befehlen und
  Proxy-Quellen, HTML-Filtern, CSP-Quellen je Anfrage und Fußbereich-Links; mitgeliefert: `dav` (CalDAV/CardDAV) und
  `consent_kit` (Cookie-Einwilligung, Port des REDAXO-AddOns consent_kit, MIT): Dienste aus 38 Vorlagen, barrierefreie
  Web Component im Design der Website, 2-Klick-Platzhalter mit den Video-Blöcken der Themes, Consent Mode v2, GPC,
  Conversions ohne Code, Protokoll ohne IP/User-Agent – fremde Hosts erst nach Einwilligung in der CSP, kein Inline-Code.
- **Staging & Deploy** mit Releases, atomarem Umschalten, Rollback, Sicherung vor production und GitHub-Workflow.
- Kommandozeile `bin/console` für Konten, Websites, Netzwerk, Themes, Migration, Health, Sicherungen, geteilte
  Daten, Suche und KI.

### Inhalte & Darstellung
- **Blockeditor auf der Website** (Editor.js) mit Inline-Editing, Formatierungsleiste, Abschnitts-Optionen
  (Hintergrund, Anker, Navigation, Vollbild, Hintergrundbild), Kompaktansicht, Entwurf/Live, Versionen.
- **Seitenbaum** mit Verschachtelung, Menü, Weiterleitungen alter Adressen, SEO-Feldern und strukturierten Daten.
- **Theme-System**: Themes als eigenständige Pakete; mitgeliefert **basis** (neutral, Baukasten, vier Navigationen,
  sieben geprüfte Design-Vorlagen, Dunkelmodus) und **praxis** (Arztpraxis).
- **Style-Editor** (Design-Tokens) mit Vorlagen, Kontrastprüfung, Vorschau, Verlauf, Export/Import; auch über API/MCP.
- **Einträge direkt auf der Website bearbeiten** (Seitenleiste, Felder im Text); CMS-Oberfläche per Shadow DOM
  vom Theme isoliert.
- **Handbuch für die Redaktion** aus Kern-Kapiteln, die ein Theme ergänzen oder ersetzen kann.

### Daten
- **Datentabellen** ohne Code mit 23 Feldtypen (u. a. Verknüpfungen, wiederholbare Gruppe, IBAN, Ort,
  Wiederholung), Bedingungen, Übersetzungen, Detailseiten mit Feldbindung und Kern-Blöcken (Datenliste,
  Datensatz-Felder, Formular, Kalender, Nächste Termine, Karte, Galerie, Slideshow, Stapelkarten).
- **Öffentliche Formulare** für Datentabellen mit Spamschutz ohne Cookies.
- **Eingänge**: verschlüsselte Anfragen (libsodium) mit Protokoll, Zuweisung, Aufbewahrungsfrist; Theme-Formulare
  werden automatisch zu Eingängen.
- **Kalender** mit Wiederholungen, Monats-/Listenansicht, iCal-Feeds und CalDAV/CardDAV-Abgleich (Erweiterung).
- **Geteilte Datentabellen** mehrerer Websites mit Vorschlägen, Übernahme, Hervorhebung und Quellen-Auswahl.

### Medien
- Mediathek im Finder-Stil mit Upload in Stücken, Alt-Text-Pflicht, Tags, Sammlungen, Fokuspunkt, Zuschnitten je
  Format, Datei ersetzen, Prüf-Filter; Bilder, PDF (eigener Viewer), MP4, MP3, M4A.
- **Geteilte Medien-Pools** für mehrere Websites, inkl. Verschieben vorhandener Dateien.
- **Untertitel, Kapitel und Transkripte** (WebVTT/SRT, Editor, KI-Transkription, Übersetzung, Prüfpflicht).

### Suche, KI & Schnittstellen
- **Website-Suche** mit Loupe (Tippfehlertoleranz, Synonyme, Gewichtung, Filter), optional semantisch/hybrid.
- **KLXM Ai** (Symfony AI): Schreiben, Übersetzen, SEO-Check und -Vorschläge, Alt-Texte, Seiten- und
  Tabellen-Generator, Untertitel; Anbieter Ollama, Mistral, OpenAI (EU-Region) oder OpenAI-kompatibel; lokale
  Transkription mit whisper.cpp; Tageslimit, Nutzungsübersicht, Glossar.
- **Prüf-Ebene „Eingereicht“**: Herkunftsprotokoll aller Änderungen aus API, MCP und KI; Tokens „Zur Freigabe“
  mit Vorher/Nachher, Konflikterkennung und Bearbeiten vor dem Übernehmen.
- **REST-API** (OpenAPI 3.1) und **MCP-Server** (Streamable HTTP) mit gemeinsamer Fachlogik und Tokens.

### Verwaltung & Dienste
- Verwaltung mit Drill-down-Navigation, mobiler Schublade, Spotlight-Suche (⌘K), Favoriten, Symbolen (Phosphor
  duotone) und vollständiger englischer Oberfläche.
- **Support & Wissensdatenbank**: Meldungen mit Bildschirmfotos, Fragen & Antworten, Wissensartikel – zentral für
  alle Websites.
- **Karten** (MapLibre, OpenFreeMap) und externe Quellen über den eigenen Proxy; Favicon/App-Icon-Generator und
  installierbare Web-App (PWA).
- Sicherheit: strenge CSP, CSRF, Rate-Limits, IP-Hashes statt IP-Adressen, keine Cookies für Besucher.

### Kits
- Neues Kit **`nature`** („Nature – organisch & ruhig“) für Höfe, Gärtnereien, Naturschutz und Outdoor: erdige
  Palette (Moos, Sand, Ton, Borke, Himmel) mit Waldnacht als dunklem Schema, Fraunces (SOFT-Achse) + Nunito Sans,
  Young Serif und Source Sans 3 (alle lokal, OFL); Blattmarken, Wellen-/Hügel-Übergänge, Papierstruktur, Höhenlinien
  und Blattadern als erzeugte SVG (`tools/patterns.php`), Landschafts-Illustration im Einstieg mit Details je
  Jahreszeit. Style-Editor-Vorlagen Moos, Frühling, Sommer, Herbst, Winter, Waldnacht (alle WCAG 2.2 AA, hell +
  dunkel, `tools/contrast.php`). 17 Blöcke (neu: `team`), Website-Formular mit Saisonzeiten und Anfahrt, vier
  Kopf-Varianten. Demo „Hofgut Wiesengrund (fiktiv)“ mit Hofladen, Terminen (Liste + Kalender), Gruppen-Formular
  und erzeugten Illustrationen (`tools/demo-content.php`). Details: `themes/nature/README.md`.
- Neues Kit **`modern`** („Modern – klar und selbstbewusst“) für Studios, Beratungen und Unternehmen: große
  Grotesk-Typografie (Space Grotesk + Plus Jakarta Sans, dazu Inter Tight und Manrope – alle lokal, OFL), viel
  Weißraum, kräftige Blockfarbe, 12-Spalten-Bento-Raster, präzise Karten mit feiner Kante, Bedienelemente mit 8 px
  Radius; responsiv in klaren Stufen (bewusst nicht breakpointlos). Einstieg „Geteilt“, „Große Aussage“ und „Mosaik“,
  19 Blöcke (neu: `team`, `pricing` als „Angebote“), vier Navigationen (modern, klassisch, minimal, ausführlich mit
  Kontaktzeile und „Jetzt geöffnet“), drei Fußbereiche, hell/dunkel, Vorlagen Nordlicht, Kobalt, Koralle, Graphit (alle
  WCAG 2.2 AA, `tools/contrast.php`). Demo „Studio Nordlicht – Produktdesign & Beratung (fiktiv)“ mit Projekten
  (Datentabelle mit Detailseiten), Team ohne Fotos, Formular und prozedural erzeugten Bildern
  (`tools/demo-content.php`). Details: `themes/modern/README.md`.
- Neues Kit **`glas`** („Glas – Mattglas über Farbfeldern“): Glassmorphism mit Lesbarkeit zuerst – matte,
  durchscheinende Glasflächen (backdrop-filter) über einem weichen Farbfeld aus Verläufen (keine Bilddateien),
  Lichtkanten, schwebender Glaskopf, Glasblatt als Mobilmenü, gläserne Such- und Aufklappmenüs, „Aurora“ im Einstieg
  (nur transform-animiert, pausiert außer Sicht und bei „Bewegung reduzieren“). Deckkraft je Farbschema und Glasdichte
  so gewählt, dass Text auf jeder Glasfläche ≥ 4,5:1 hält (`tools/contrast.php` rechnet Glas über der ungünstigsten
  Feldfarbe inkl. Sättigung); deckend bei `prefers-reduced-transparency`, `prefers-contrast: more`, „Ohne Transparenz“
  und ohne backdrop-filter; auf Touch-Geräten nur getönt. Outfit + Figtree, dazu Sora und Urbanist (alle lokal, OFL).
  15 Blöcke (neu: `team`, Karten „Bild mit Glasleiste“, Fließtext „Auf Glas“), vier Kopf-Varianten, drei Fußbereiche,
  Vorlagen Aurora, Lagune, Dämmerung, Graphit. Demo „Lumen Labs – App-Entwicklung & UX (fiktiv)“ mit Projekten
  (Detailseiten), Terminen, Formular, Team ohne Fotos und erzeugten Glas-/App-Bildern (`tools/demo-content.php`).
  Details: `themes/glas/README.md`.
- Kit **`glas`**: neue Standard-Navigation **„Glas-Dock“** – mittig schwebende Glasinsel mit Lichtbrechung, eine
  gleitende Glaslinse (ein Element, `translate`, folgt Zeiger und Tastaturfokus, ruht auf der aktuellen Seite),
  Marke als Glasperle, rechts der Bereich „Aktionen“ (`.dock__actions`: Suche, Glastaste für den Button im Kopf –
  Andockstelle für Kopfbereich-Aktionen), Verdichten beim Scrollen ohne Verschiebung, Aufklappmenü als Glaspanel mit
  Symbolen und Kurzbeschreibungen (zweispaltig ab vier Unterseiten, Aurora-Schimmer dahinter). Auf Telefonen eine
  Tab-Leiste unten (Start, drei Hauptseiten, „Mehr“ mit Glasblatt und Suche; Safe-Area, `--cms-chat-lift`,
  `data-cms-hide-editing`, blendet beim Scrollen nach unten aus). Kontrast der Menüschrift über beliebigem Inhalt und
  auf der Linse geprüft; deckend bei reduzierter Transparenz/mehr Kontrast; die bisherigen Kopf-Varianten bleiben
  wählbar. Details: `themes/glas/README.md` (Abschnitt „Glas-Dock“).
