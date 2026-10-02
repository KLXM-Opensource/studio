# Changelog

Übersicht der Funktionsbereiche von **KLXM Studio**. Die Versionsnummer steht in
`app/bootstrap.php` (`CMS_VERSION`); Einzelheiten zu jedem Bereich im Entwicklerhandbuch (`/admin/hilfe/technik`)
und im Handbuch für die Redaktion (`/admin/hilfe`).

## 1.0.0

### Editor: ein Block-Menü statt zwei Leisten; Navigation neu gegliedert
- Eigenes Block-Menü (Stil wie „+ Block einfügen“), geöffnet über „⋯“ in der Blockleiste oder Klick auf den Griff ⠿ (Ziehen bleibt):
  Nach oben/unten, Duplizieren, Kopieren, Einfügen darunter, Einklappen, Abschnitt & Navigation, Löschen mit Rückfrage. Leiste:
  Name, ↑ ↓, Bearbeiten, ⋯. Das Menü von Editor.js und dessen zweites „+“ entfallen.
- Ein Klick unter den letzten Block legt keinen leeren Textblock mehr an (Editor.js-Bottom-Zone abgefangen, freie Fläche 80 px).
- Seitenleiste: Inhalte oben; neuer Abschnitt „Einrichtung“ mit „Website“ (Website-Angaben, Design, Seitenvorlagen, Blöcke,
  Landingpages, Weiterleitungen), „System“ (Grundeinstellungen, Funktionen & Erweiterungen, Einstellungen der Funktionen,
  Benutzer & Rollen) und „Werkzeuge“ (Statistiken, Werkzeuge der Erweiterungen).

### Verwaltung: Usability-Durchgang Desktop, Tablet, Mobil
- Seitenbaum: Spalten überlappten unter 900 px (Regel ohne Media-Query) – behoben, feste Status-Spalte, mobil nur Statuspunkt,
  kein eigener Scrollbereich mehr; Vorschau-Seitenleiste verkleinert den Inhalt statt ihn zu überdecken.
- Eintragslisten: Tabelle scrollt waagerecht; auf dem Handy Karten mit Titel, Status-Schalter, Ansehen-Link und beschrifteten
  Feldern; Titelzelle sauber (kein schwebender Rand mehr).
- Medien: Info-Panel mit Schließen-Knopf, Zustand am Info-Knopf (gemerkt), auf dem Desktop nur bei Auswahl, schmal mit Abdunkeln;
  Höhe nach verfügbarem Platz; Sammlungsnamen gekürzt; Suche mobil in eigener Zeile.
- Hilfe „?“ mobil als Blatt unten mit Schließen; Speichern-Leiste und Seitenleiste nicht mehr unter dem Staging-Banner.
- Reiter: Desktop umbrechend, schmal waagerecht scrollbar; aktiver Reiter im Dunkelmodus erkennbar.
- Touch: Tippflächen mindestens 40 px (Knöpfe, Menüs, Schalter, Checkboxen); „Ansehen“ ohne Hover sichtbar.
- Doppelte Navigation entfernt: Leiste mit den Einstellungs-Punkten auf „Einstellungen der Funktionen“ (steht in der Seitenleiste);
  Reiter „Website“ der Grundeinstellungen heißt jetzt „Allgemein“ (Verwechslung mit „Website“-Angaben).

### Indexierung & Crawler
- Grundeinstellungen → „Indexierung & Crawler“ (`Core\Indexing`): KI-Crawler erlauben / KI-Training sperren (GPTBot, ClaudeBot,
  Google-Extended, CCBot …; Antwort-Bots mit Quellenlink bleiben) / alle sperren; eigene Regeln für die robots.txt (geprüft);
  optional `/llms.txt`; Warnungen bei gesperrter Live-Website oder „Disallow: /“; „Suchmaschinen aussperren“ hierher verschoben.
- Seitenbaum: „⋯ → Nicht indexieren / Indexieren erlauben“ und Kennzeichen „noindex“ (z. B. Impressum).
- Datentabellen: „Detailseiten nicht indexieren“ – noindex, nicht in Sitemap und llms.txt.

### Seitenbaum: Vorschau als Seitenleiste
- Augen-Knopf neben Online/Offline jeder Seite (und „⋯ → Vorschau“) öffnet rechts eine Seitenleiste mit der Seite: Mobil (Breite 390, quer 844) oder
  Desktop (Breite 1280, hoch 800), nach der Breite skaliert und in voller Höhe der Leiste; Entwurf (Arbeitsstand) oder Live; „in neuem Tab öffnen“.
  Folgt der Auswahl im Baum, merkt sich die Einstellungen. Desktop 1280 × 800 mit automatisch breiterer Leiste, Breite per Griff ziehbar
  (Doppelklick = zurücksetzen); Auge vor dem Status (bündig). Neue Adresse `/admin/pages/{id}/vorschau[?stand=live]` (ohne
  Werkzeugleiste, noindex; Seitenvorlagen nur für die Administration).
- Hilfe „?“ neben „+ Neue Seite“ mit den Erklärungen zu Sonderseiten und Vorlagen samt Links.

### Seitenbaum: Symbole aus der Web-Welt, eckige Schalter
- Statt Ordner und Dokument: Browserfenster (Seite), gestapelte Fenster (Seite mit Unterseiten, Akzentfarbe), Haus (Startseite),
  gestricheltes Fenster (Sonderseiten/Vorlagen). Farben aus den Tokens der Verwaltung – passen im Dunkelmodus und bei „Kontrast erhöhen“.
- Eigene Symbole für Seitenvorlagen (gestricheltes Fenster mit Plus; im Menü und als Standard ein Stempel), Detailseiten-Vorlagen
  (Fenster mit Datenzeilen) und die 404-Seite (Fenster mit Fragezeichen). Seitenbaum: eigene Karte „Seitenvorlagen“ mit „Blöcke
  bearbeiten“ (nur Administration); Seitenvorlagen erschienen fälschlich unter „Detailseiten-Vorlagen“ (ohne Bearbeiten-Knopf).
- Seiten: eigener Reiter „Sonderseiten & Vorlagen“ (404-Seite mit `pages.manage`, Detailseiten-Vorlagen mit `data.schema`,
  Seitenvorlagen mit `system.manage`) – ohne diese Rechte nur der Seitenbaum; `/admin/pages#sonderseiten` öffnet den Reiter.
- Schalter (Seitenbaum „Im Menü“, Einstellungen) eckig mit quadratischem Knopf und Akzentfarbe statt iOS-Pille in Grün.

### Seitenvorlagen für die Redaktion, Blöcke kopieren und duplizieren
- **Seitenvorlagen** (`Core\PageTemplates`, Werkzeuge → Seitenvorlagen, nur `system.manage`): eigene Seiten wie die Sonderseiten
  (`type = 'template'`, `template_for = '@page'`) – ohne Adresse, nicht in Seitenbaum, Menü, Suche; Redakteure können sie nicht
  ändern. Anlegen leer oder als Kopie einer Seite (auch „⋯ → Als Vorlage speichern“ im Seitenbaum), gestalten unter
  `/_seitenvorlage/{id}?edit=1`; je Vorlage Name, Symbol, Beschreibung, „Vorschlagen unter“, Reihenfolge per ↑/↓. „Neue Seite“
  zeigt „Leere Seite“ und die Vorlagen als Kacheln mit Symbol, passend zur übergeordneten Seite vorgewählt.
- **Neue Seiten starten leer** statt mit einem erzwungenen Textblock „Neuer Inhalt.“ (Editor zeigt den Platzhalter „Leere Seite“).
- **Blöcke duplizieren** (⧉, Kopie darunter) und **kopieren** (⎘, Block-Zwischenablage für alle Seiten der Website; „+ Block einfügen“
  bietet „Kopierten Block einfügen“ an) – mit Abschnitts-Einstellungen, neue IDs auch für verschachtelte Blöcke.

### Seiten-Editor: leere Seite ohne festen Textblock
- Editor.js braucht immer einen Block und legte bei neuen Seiten (und nach dem Löschen des letzten Blocks) einen leeren
  „Text“-Block an, der sich nicht entfernen ließ. Jetzt erscheint er als Platzhalter „Leere Seite“; „+ Block einfügen“ ersetzt
  ihn, gespeichert wird eine wirklich leere Seite.

### Online-Anfragen: geheimer Schlüssel optional aus der Hosting-Umgebung
- Liegt der geheime Schlüssel als Umgebungsvariable vor (`KLXM_FORM_SECRET_{WEBSITE}` oder `KLXM_FORM_SECRET`, z. B. Plesk
  `env[…]` im PHP-FPM-Pool), sind Anfragen ohne Eingabe lesbar – nur wenn er zum öffentlichen Schlüssel passt. Schutz dann gegen
  Datenbank-Diebstahl (Sicherung, SQL-Lücke), nicht gegen eine Server-Übernahme; für kleine Websites gedacht. Systemseite zeigt
  Zustand (aus, aktiv, passt nicht) und Anleitung; Protokoll vermerkt automatisches Entschlüsseln. `'form_secret_env' => false` schaltet ab.

### Live-Aktualisierung (SSE) als zentraler Dienst, Live-Galerie und Live-Ticker
- **`Core\Live`:** Kanäle mit Versionen (Datentabellen, geteilte Tabellen, Sammlungen, Seiten, eigene `ext:…`), signierte Abos,
  ein SSE-Endpunkt `/api/live` für alle Elemente einer Seite, Nachladen einzelner Blöcke (`/api/live/block`), Begrenzung
  gleichzeitiger Streams (`live_max_streams`) mit Rückfall auf seltenes Nachfragen. `live.js`/`live.css` nur auf Seiten mit Live-Inhalt.
- **Live-Galerie:** Bilder einer Sammlung erscheinen ohne Neuladen (neueste zuerst), Lightbox, Anhalten für Besucher; alternativ
  einzeln ausgewählte Bilder – neue erscheinen bei Besuchern, sobald die Seite veröffentlicht wird (Kanal `page:{id}`).
- **Live-Text (Ticker):** Meldungen direkt im Block schreiben (erscheinen beim Veröffentlichen der Seite) oder veröffentlichte
  Einträge einer Datentabelle (sofort) – als Ticker oder „nur die aktuelle Meldung“, mit Uhrzeit.
- **Fehler behoben:** Galerien aus einer Sammlung zeigten geteilte Bilder (Medien-Pool) nicht an.
- `php bin/console live:selftest`; Entwicklerhandbuch „Live-Aktualisierung für Besucher (SSE)“, Handbuch „Live-Galerie & Live-Ticker“.

### Hinweisbalken: Zeitraum und Darstellung
- **Zeitraum:** „Anzeigen ab“ / „Anzeigen bis“ unter dem Hinweistext (Website → Hinweisbalken) – der Hinweis erscheint und
  verschwindet von selbst, auch bei Treffern im Seiten-Cache und auf offenen Seiten (`data-notice-from/-until`, `notice.js`).
- **Darstellung:** Balken wie im Design, linksbündig, zentriert oder als **schwebende Bubble** unten links, unten mittig oder in der Bildschirmmitte (Farben vom Kit,
  schließbar, für die Sitzung gemerkt, Esc schließt).
- **Optional auffälliger:** „Farbe“ (Signalgelb, Rot, Grün, Blau, Schwarz, Weiß statt Kit-Farben) und „Hervorheben“ (Pulsieren,
  kurz wackeln, Leuchten) – nur wenige Sekunden, still bei „Bewegung reduzieren“.
- Der Core ergänzt die Felder für jedes Kit mit `project.notice` (`Core\Notice`); Kits öffnen den Balken mit
  `notice_open('topnote')`, prüfen `notice_on()` und rufen `notice_late('topnote')` vor `</body>`. CSS/JS nur, wenn Zeitraum oder
  Darstellung gesetzt sind (CSP ohne Inline-Code). MCP `set_notice` nimmt optional `from`/`until`.

### Seitenbaum: Verschieben trifft die richtige Stelle, auch ohne Ziehen
- **Fehler behoben:** Ziehen zwischen zwei Seiten landete auf der obersten Ebene an der falschen Stelle (z. B. „AGB“ hinter
  „Impressum“ rutschte hinter „Agentur“ – oder schien gar nichts zu tun). Ursache: Der Baum schickte die Position als Index, gezählt
  ohne die Seiten, die er nicht zeigt (Detailseiten-Vorlagen, 404-Seiten, andere Sprachen); der Server zählte sie mit. Jetzt geht
  die Nachbarseite mit (`before_id`/`after_id`, `Pages::move(…, $before, $after)`), `index` bleibt für Aufrufer ohne Nachbarn.
- Unterkante einer aufgeklappten Seite = erste Unterseite (die blaue Linie steht dort); freie Fläche unter der letzten Zeile = ans
  Ende der obersten Ebene.
- **Ohne Maus:** <kbd>Alt</kbd> + Pfeiltasten (nach oben/unten, einrücken, ausrücken) und dieselben Befehle im Menü „⋯“; nach dem
  Neuladen bleibt die Seite gewählt, die Statuszeile sagt an, wo sie jetzt steht. Fehler erscheinen als Hinweis statt `alert`.

### KI: Verbindungen, Modelle per Klick, Verwendung je Zweck
- **Verbindungen:** beliebig viele benannte KI-Verbindungen („Ollama Büro“, „Mistral EU“, „OpenAI“) mit Art, Adresse, optionalem
  Schlüssel bzw. Token (nur schreibbar, nie wieder angezeigt), Region und Zeitlimit; neu: **Anthropic** (über die OpenAI-kompatible
  Schnittstelle) und **whisper.cpp** als Verbindungsart. **Verbindung prüfen** meldet „in Ordnung“ mit Version, Anzahl Modelle und
  Dauer bzw. Schlüssel falsch, nicht erreichbar, falsche Art; Statuspunkt je Verbindung.
- **Modelle anzeigen:** Ollama (`/api/tags`, `/api/show`: Größe, Parameter, Quantisierung, Kontext, Fähigkeiten), OpenAI/Mistral/
  OpenAI-kompatibel (`/v1/models`), Anthropic (Liste bzw. kuratiert), whisper.cpp (Modelle auf dem Server) – mit Filter und Eignung;
  **Übernehmen** trägt das Modell für den gewählten Zweck ein, Eingabe von Hand bleibt möglich.
- **Verwendung:** je Zweck (Texte & Redaktion, Besucher-Chat, Embeddings, Bilder, Sprache → Text) Verbindung + Modell, optional
  Ersatz-Verbindung bei Ausfall; Hinweis, wenn sich das Embedding-Modell ändert (Suchindex rechnet neu). `Ai::for($zweck)`,
  `Ai::platformFor()`; bestehende Aufrufe unverändert.
- **Altes Format bleibt gültig:** `provider`/`models`/`providers`/`transcribe` werden beim Lesen zur Verbindung „Standard“
  (`Core\AI\Profiles::upgrade()`), gespeichert wird Format 2 in `storage/ai/config.json`; Werte aus Konfigurationsdateien sind je
  Verbindung bzw. Zuordnung gesperrt.
- **Sicherheit:** Prüfungen serverseitig (`Core\AI\Probe`): nur die Adresse der Verbindung, keine Weiterleitungen, Zeitlimit, Größenlimit,
  Metadaten-Adressen nie, eigenes Netz nur für Ollama/OpenAI-kompatibel; Recht `system.manage`, CSRF, 20 Prüfungen je Minute; keine
  Schlüssel in Antworten, Seiten oder Protokollen.
- **Grundeinstellungen → KI** neu geordnet in aufklappbaren Abschnitten (Übersicht, Verbindungen, Verwendung, Website, Assistent,
  Besucher-Chat, Datenschutz, Nutzung); Überschriften mit `'collapse'` machen das für jedes Feld-Schema möglich.
- Selbsttest `ai:selftest` (Umwandlung, Ebenen, Sperren, Prüfen/Modelle mit vorgefertigten Antworten, Seitenauswahl).

### Seitenauswahl als Feldtyp `pages`
- Statt langer Kästchen-Listen: gewählte Seiten als Chips mit Pfad im Seitenbaum, **Seiten auswählen …** öffnet den Seitenbaum der
  Linkauswahl mit Kästchen, Suche, „Alle sichtbaren auswählen“, Sprachen, Anzahl und **+ Unterseiten**; Tastatur und WAI-ARIA wie die
  Linkauswahl, hell/dunkel, ab 390 px, auch in der Shadow-DOM-Ebene der Website.
- Werte wie bisher: Seiten-IDs (`12`, neu `12*` = mit Unterseiten, `Core\PagePicker::matches()`) bzw. Pfade (`/x`, `/x/*`, eigene Pfade).
- Umgestellt: Besucher-Chat „Auf diesen Seiten keinen Chat zeigen“ (jetzt auch mit Unterseiten), Glossar „Seiten ausnehmen“.
- Sprungziele innerhalb von Reitern (z. B. `/admin/system#ki-use`) öffnen Reiter und Abschnitt.

### Seitenleiste: „Administration“ in zwei aufklappbaren Gruppen
- **Einstellungen** (Grundeinstellungen, Funktionen & Erweiterungen, Einstellungen der Funktionen, Benutzer & Rollen, Design) und
  **Werkzeuge** (Blöcke, Landingpages, Weiterleitungen, Statistiken, Werkzeuge der Erweiterungen) statt einer losen Liste – die
  Sammelseite `/admin/einstellungen` heißt jetzt **„Einstellungen der Funktionen“** und zeigt oben die übrigen Punkte der Gruppe.
- Knopf mit `aria-expanded`, offen auf den eigenen Seiten, Zustand je Browser (`localStorage` `klxm-studio-navgroups`), <kbd>Esc</kbd>
  schließt; ohne JavaScript offen. Nur Punkte mit Recht, leere Gruppen entfallen, ein einzelner Punkt steht direkt da.
- `Core\AdminPages::groups()` liefert die Gruppen (Seitenleiste, Sammelseite, ⌘K-Suche); Seiten der Art `tool` landen ohne
  `place` automatisch unter „Werkzeuge“. Überschriften der Seiten zeigen den Weg („Administration › Einstellungen“ bzw.
  „› Werkzeuge“); Handbuch und Entwicklerhandbuch nennen die neuen Wege.

### Erweiterungen auf die Integrationspunkte umgestellt
- **Mehrere Rechte an einer Route:** `$r->get('/admin/x', $h, ['perm' => ['a', 'b']])` – eines davon genügt (für Seiten, die zwei
  Gruppen nutzen, z. B. `pages.edit` und `feedback.write`). `extensions:list` zeigt `a|b`; ältere Cores behandeln solche Routen von
  `AdminController`-Controllern als Altform. Selbsttest `extensions:selftest`, Entwicklerhandbuch › Erweiterungen › Routen.
- **Mitgelieferte Erweiterungen:** jede Verwaltungsroute nennt ihr Recht (`extensions:list` meldet keine Altform mehr), Seiten mit Art,
  `composer.json` vom Typ `klxm-studio-extension`, Changelog und Übersetzungen:
  - `consent_kit` 1.1.0 – Einstellungsseite (Administration → Einstellungen → Cookie-Einwilligung), Datum über `Core\Format`.
  - `dav` 1.1.0 – App-Passwörter als Abschnitt im **Konto** (Slot `account`), Einstellungen je Tabelle als Einstellungsseite
    (Recht `data.schema`), Datum über `Core\Format`.
  - `video_tools` 1.1.0 – Werkzeug-Seite (`adminPage`, Art `tool`), Zahlen über `Core\Format`, Altname `mycms-extension` entfernt.
- Funktionsübersicht, Handbuch (Cookies, Daten) und Entwicklerhandbuch (Funktionen, Consent-Kit) nennen die neuen Orte.

### Block-Designer: Beispiele und Einstieg
- **Sieben Beispiel-Blöcke** unter Verwaltung → Blöcke → **Beispiele** (`app/Blocks/demos/*.json`, `Core\Blocks\Demos`), vom
  Einfachen zum Fortgeschrittenen: Hinweisbox (Auswahlfeld, Bedingungen), Zitat mit Bild (Bildfeld, Alt-Text), Kennzahlen
  (Liste, `number`), Ablauf / Schritte (Symbol, `loop.index`), Ansprechpartner-Karte (`tel`, `mailto`, Linkfeld), FAQ /
  Aufklappliste (`<details>`, JSON-LD FAQPage), Termin-Hinweis (Datum, `<time>`). Kit-neutral über die `--cb-*`-Variablen,
  hell/dunkel, `prefers-reduced-motion`, ohne Inline-Styles.
- Galerie mit Live-Vorschau im aktiven Kit; **„Als Vorlage übernehmen“** legt eine Kopie als Entwurf an (eindeutiger Kurzname,
  nie überschreibend, je Klick genau einmal). Im Block-Designer erklären „Was zeigt dieses Beispiel?“ und Hinweise je Reiter
  das Beispiel (ausblendbar). Einstieg oben in drei Schritten (Felder → Vorlage → CSS) mit Links ins Handbuch.
- `php bin/console blocks:demos [--install=name|--all]` (wiederholbar); `blocks:selftest` prüft alle Beispiele.
- Neuer Vorlagen-Filter `mailto`. Block-Designer: gespeicherte Beispieldaten erscheinen beim Öffnen wieder (statt neu erzeugter),
  Feldangaben ohne eigenes Eingabefeld (Platzhalter, Standardwert, Zeilen) bleiben beim Bearbeiten erhalten; Kurznamen
  `new`, `import`, `library`, `demos` sind reserviert.

### Plattform für Erweiterungen: feste Integrationspunkte
- **Entwicklerhandbuch › Erweiterungen › Integrationspunkte und Regeln:** kuratierte Liste der Stellen, über die Erweiterungen
  eingreifen dürfen – alles andere gibt es nicht.
- **Typisierte Ereignisse** `Core\Events\*`: `PageSaved`, `PagePublished`, `PageUnpublished`, `PageDiscarded`, `PageDeleted`,
  `EntrySaved`, `EntryPublished`, `EntryUnpublished`, `EntryDeleted` – unveränderlich, mit `table`, `id`, `lang`, `userId`,
  `state` (`draft`|`live`). `$x->on(PageSaved::class, fn(PageSaved $e) => …)`; die Namen (`'page.saved'`) bleiben Alias:
  Listener mit Ereignis-Typ bekommen das Objekt, alle anderen die bisherigen Argumente. `EntryDeleted` trägt jetzt den Stand vor
  dem Löschen. `Extensions::listens()` versteht Name und Klasse.
- **Verwaltungsrouten von Erweiterungen geschützt ab Werk:** `Router::scoped()` – Routen unter `/admin` prüfen Anmeldung, Recht
  und CSRF (Nicht-GET), bevor der Handler läuft; das Recht steht an der Route (`$r->get('/admin/x', $h, 'x.view')` bzw.
  `['perm' => …]`). Benannte Ausnahmen `'csrf' => false` und `'public' => true`. Ohne Recht: in der Entwicklung Fehler beim
  Anmelden, in Produktion 403. Altform (Controller auf Basis von `AdminController`, die selbst `auth()` aufrufen) läuft weiter
  und wird – wie die Ausnahmen – in `extensions:list` gemeldet. Routen des Cores bleiben unverändert;
  `AdminController::routeGuard()` ist die gemeinsame Prüfung.
- **Tabellen deklarativ: `Core\Db\Table`** (nach `rex_sql_table`): `id()`, `column()`, `index()`, `unique()`, `foreignKey()`,
  `renameColumn()`, `dropColumn()`, `dropIndex()`, `ensure()` – idempotent und additiv, SQLite und MySQL über Doctrine DBAL auf
  derselben PDO-Verbindung (auch in Transaktionen). Manifest: `$x->table('name', fn(Table $t) => …)` – angeglichen beim Start nach
  geänderter Beschreibung (Fingerabdruck je Website) und bei jedem `migrate`, vor den `migration()`-Schritten.
  Selbsttest `db:selftest`.
- **Werte formatieren: `Core\Format`** (nach `rex_formatter`) mit Helfer `fmt()`: `date`, `time`, `datetime`, `relative`,
  `number`, `decimal`, `currency`, `bytes`, `duration`, `phone`, `host`, `excerpt` – Sprache der Seite (`Lang::current()`),
  `fmt('en')` bzw. `Format::admin()`. Umgestellt mit gleicher Ausgabe (Schnappschuss + `format:selftest`): `date_local()`,
  `Media::humanSize()` (ab 1 GB „GB“), `Links::ago()`, `Dashboard::ago()`, `MediaJobs::duration()`, `Clamp::excerpt()`,
  Block-Filter `number`. In englischer Verwaltung bzw. auf englischen Seiten folgen Trenner und Datum jetzt der Sprache.
  Neues Kapitel im Entwicklerhandbuch: **Werte formatieren**.
- **Slots der Verwaltung: `Core\Slots`** – sechs feste Stellen mit je einer Manifest-Methode, optionalem Recht und
  Escapen durch den Core: `pageList` (Seitenbaum), `pagePanel` (Seiteneinstellungen), **neu** `tableActions` (Datentabelle:
  Knöpfe im Kopf, Aktion je Zeile mit `{table}`/`{id}`), **neu** `mediaPanel` (Abschnitt unter „Informationen“ der Mediathek),
  `dashboard` (Karten jetzt auch als Daten `'body'`), **neu** `account` (Abschnitt auf „Konto“). Gemeinsamer Kartenvertrag
  (`title`, `text`, `tone`, `lines`, `actions` – nur Pfade dieser Installation). Altform-HTML von `pagePanel` und
  `'render'` bleibt erlaubt.
- **`kit:check [--kit=…|--all] [--strict] [-v]`:** verwaiste Block-Vorlagen eines Kits, Überschreibungen von Kern-Blöcken,
  die Angaben lesen, die der Kern nicht mehr kennt, und Kern-Fragmente mit geändertem Original – nur aus den Quellen, ohne Zustand.
### Altname „MyCMS“ aus dem Core entfernt
- Beispiele und Doku nennen nur noch KLXM Studio: MCP-Verbindung `claude mcp add --transport http klxm-studio …` (API-Seite,
  Entwicklerhandbuch, Tutorial, README), Composer-Beispiel `agentur/klxm-studio-shop`, Paket-Typ `klxm-studio-extension`
  (`mycms-extension` wird still weiter erkannt). Kit-Pakete heißen `klxm-studio-kit-{name}`; `kit:new` benennt auch alte Namen um.
- Block-Export im Format `klxm-studio-block`; Import und Netzwerk-Bibliothek lesen ältere `mycms-block`-Dateien weiter.
- Lokale Schlüssel im Browser (`klxm-studio-media-*`, `klxm-studio-{bereich}-*`, `klxm-studio-pt-collapsed`) lesen die alten
  `mycms-*`-Werte als Rückfall und räumen sie beim Speichern auf; Service-Worker-Cache `klxm-studio-{version}`, alte
  `mycms-*`-Caches werden gelöscht. Drag-&-Drop-Typ der Medien `application/x-klxm-studio-media`.
- Bleiben als historische technische Kennung: vCard-Felder `X-MYCMS-*` (in CardDAV-Clients gespeichert), Altname
  `mycms-extension`/`extra.mycms.entry`, Rückfall-Schlüssel `mycms-consent-*`.

### Kit „frameworks“: Tailwind CSS oder UIkit – und was ein Framework-Kit braucht
- Neues Demo-Kit `kits/frameworks` (fiktive Firma „Beispielwerk“): dieselben Blöcke mit **Tailwind CSS 4** (vorkompiliert mit
  `@tailwindcss/cli`, Preflight in `@layer base`, Variante `dark:`) oder **UIkit 3.25** (mitgeliefert, Brücke für Kontrast,
  Dunkelmodus und Rich-Text-Klassen); umschaltbar unter Design → „Framework“, zum Vergleichen `?fw=uikit` / `?fw=tailwind`.
  Startinhalte mit Seitenbaum, Datentabelle mit Detailseite, Formular, Glossar, Karte und Video.
- Neues Kapitel im Entwicklerhandbuch: **Frameworks (Tailwind, UIkit, Bootstrap)** – warum es funktioniert, empfohlene Setups,
  Fallstricke aus den Tests, eigenes Framework-Kit beginnen.
- `editor.css`: Overlays der Frameworks (UIkit `uk-offcanvas`/`uk-modal`, Bootstrap `.offcanvas`/`.modal` + Backdrops) liegen beim
  Bearbeiten über dem angehobenen Kopf des Kits (z-index 2147483055); Seitenmenüs rücken um die sichtbare Leistenhöhe ein – vorher
  verdeckten Kopf und Werkzeugleiste am Telefon den Schließen-Knopf.
- `dataform.css`: Felder des Formulars setzen `box-sizing:border-box` selbst – mit Frameworks ohne globalen Reset (UIkit) liefen sie
  bei 390 px über den Rand.
- Entwicklerhandbuch: Kapitelnummern mit eigenem Zähler (Kapitel-Dateien, die `$__n` benutzen, stellten sonst alle folgenden
  Nummern auf „00“).

### Geteilte Daten: Adresse der Ursprungs-Website auch beim Teilen per Kommandozeile
- `Shared::touch()` trägt die Adresse einer Website jetzt auch auf der Kommandozeile (Teilen/Beitreten per CLI, MCP) ins Register
  (`sites_info`) ein – aber nur eine eingestellte (`sys.site_url`, `base_url`), nie einen geratenen Host. Bisher blieb sie leer,
  bis jemand die Verwaltung der Tabelle im Browser öffnete; bis dahin zeigte das Canonical fremder Einträge nicht auf die
  Ursprungs-Website (die Website „default“ hat oft keine `hosts`, also keinen Rückfall).
- Selbsttests: `glossary:sharetest --sandbox` stellt die Adressen der Test-Websites ein und prüft das Canonical gegen die Adresse
  von A; `blocks:selftest` prüft das Layout mit einem Kind-Block, der ohne Kontext etwas ausgibt (kit-unabhängig – „Datenfelder“
  bleibt ohne Eintrag leer und hat dann keine Sprungmarke).

### Geteilte Daten: Medien-Pool nur bei Bedarf
- Der Pool `data-{key}` einer geteilten Tabelle entsteht erst, wenn sie ein Bild- oder Dateifeld hat (beim Anlegen, Teilen,
  Beitreten oder beim Speichern des Schemas, sobald das erste solche Feld dazukommt) bzw. wenn die erste Datei zugeordnet wird
  (`Shared::ensurePool($key, $force)`, `Shared::hasMediaFields()`). Tabellen nur mit Text – etwa das geteilte Glossar – bekommen
  keinen leeren Pool mehr. Neue Pools tragen `shared_table` in `pool.json`.
- **Aufräumen mit `migrate`** (`Shared::cleanupPools()`): leere, automatisch angelegte Pools geteilter Tabellen ohne Bild-/Dateifelder
  (z. B. `data-glossar`) werden entfernt – nie Pools mit Dateien (Datenbank, Ordner oder Verweise einer Website), nie selbst
  angelegte. Die Pool-Datenbank wandert nach `storage/pools/_removed/`, Protokoll in `storage/pools/_removed/removed.log`,
  idempotent.
- **Grundeinstellungen › Geteilte Medien:** Pools geteilter Tabellen heißen „Bilder der geteilten Tabelle „…““, verweisen zur
  Tabelle und werden mit ihr verwaltet – kein Löschen, nutzende Websites folgen den Beteiligten der Tabelle (nur „Pflegen dürfen“
  bleibt einstellbar); leere stehen zusammengeklappt.
- Selbsttest `data:selftest` prüft Anlegen nach Bedarf und das Aufräumen (`Core\Data\SharedPoolsTest`).

### CSS & JS: Referenz für alle Kits und Generator
- **Entwicklerhandbuch › CSS & JS** (`#css-js`): die vier Ebenen (Verwaltung, Editor im Shadow DOM, öffentliche Kern-Bausteine,
  Kit), Laden (`conditional_css` mit „typ:variante“ und `@rich`, Kern-Stylesheets je Block und wie ein Kit sie ersetzt,
  `.mjs`/`import()`, Versionierung `?v=`, `/assets/…`), CSP für Besucher (kein Inline-CSS/JS, keine Nonces – Klassen,
  `data-*`, CSSOM), Präfixe und was öffentlich bzw. intern ist, alle Variablen der Kern-Bausteine mit Vorgaben,
  `data-*`-Attribute, globale Objekte (`CMSAdmin`, `CMSEditor`, `CMSMedia` …), Ereignisse (`cms:*`, Muster `{kit}:inview`,
  `{kit}:content`), Pflichtregeln für Bewegung (Endbild bei „Bewegung reduzieren“, Pausenknopf nach WCAG 2.2.2, Fokus,
  `forced-colors`, Bearbeiten-Modus), Dos & Don'ts und die Z-Skala. Tabellen mit Variablen, Ereignissen, Ladern und
  Attributen entstehen zur Laufzeit aus den Quellen (gecacht).
- **`php bin/console docs:assets [--kit=…|--kit-dir=…] [--kit-only] [--out[=…]] [--update] [--json]`** (`Core\AssetDocs`):
  Markdown-Referenz aus CSS, JS, Vorlagen und `theme.php` – Custom Properties mit Vorgaben (auch Rückfälle aus `var()`),
  `conditional_css` → Block → Dateien, Ereignisse mit Payload, `data-*`, Bewegung/Barrierefreiheit je Datei (⚠ bei
  Animation ohne `prefers-reduced-motion`). `docs:selftest` prüft die Parser an Beispielen und am Kern.
- **Kit-Seite „CSS & JS“** (`kits/{name}/docs/css-js.md`): einheitlicher Aufbau (Überblick, Tokens, Blöcke → Dateien,
  Animationen, Overlays, JavaScript, Sonderfälle) plus generierter Anhang zwischen Markern, den `--update` erneuert.
  Für alle mitgelieferten Kits angelegt; Beschreibung unter Kits & Design.

### Externe Quellen: Zuordnung ohne Vorwissen
- **Felder in der Quelle:** Nach „Vorschau laden“ zeigt die Quelle alle Felder des ersten Eintrags mit Bedeutung („Datum der
  Veröffentlichung“), Pfad (`pubDate`) und Beispielwert – statt einer versteckten Auswahlliste. Ohne Vorschau steht dort, was zu tun ist.
- **Zuordnung:** je Zeile „Auswählen“ (Felder mit Filter und Beispiel, per Tastatur bedienbar) und darunter sofort das Ergebnis für den
  ersten Eintrag („Beispiel: 01.10.2026, 14:05“), live ohne erneuten Abruf. Standardansicht nur „Feld ← Wert aus der Quelle“ + Umwandlung
  mit Erklärung; Option/Vorlage, Standardwert und „Werte ersetzen“ je Zeile unter „Erweitert“. Hinweis je Feldtyp, welches Format erkannt
  wird (Datum: RFC 822, ISO 8601, Unix-Zeit). Kurze Anleitung mit RSS-Beispielen (`pubDate`, `|`, `@attribut`, `{…}`).
- **„Zuordnung vorschlagen“** füllt leere Zeilen nach Feldname und Feldtyp für RSS, Atom und JSON (Datum ← `pubDate | published |
  updated | dc:date`, Bild ← `enclosure@url | media:content@url …`, Autor ← `dc:creator | author.name` …); bei Wahl der Tabelle geschieht
  das automatisch. Pflichtfelder ohne Wert werden markiert (Zeile, Hinweis, „Stand“ der Quelle).
- **Probeabruf:** die ersten drei Einträge so, wie sie gespeichert würden – Feld → Wert, leere Pflichtfelder rot.
- **Neue Tabelle aus dieser Quelle:** in der Auswahl der Zieltabelle, ohne vorheriges Speichern. Name aus dem Feed-Titel, Felder und Typen
  aus den Beispielwerten (Datum, Webadresse, Bild, formatierter Text, Zahl …), je Feld an/aus, Bezeichnung und Typ änderbar; optional
  Detailseite, Übersichtsseite und Menüeintrag. Legt Tabelle an, setzt sie als Ziel, füllt die Zuordnung und zeigt den Probeabruf.
  Recht „Tabellen und Felder ändern“; vergebene Kurznamen/Adressen bekommen einen Zähler.
- Neue Umwandlungen **„Kürzen“** (ohne HTML, an Wortgrenze mit „…“, Standard 200 Zeichen – für Teaser aus Beschreibung/Volltext, bei
  Teaser-/Kurztext-Feldern vorgeschlagen), **„Nur erster Absatz“** und **„jetzt (Zeitpunkt des Abrufs)“** für Quellen ohne Datum (bleibt
  bei späteren Abrufen unverändert). `liste[*]` verbindet alle Werte mit Komma.
- Selbsttest `php bin/console sources:selftest`; Handbuch mit Schritt-für-Schritt-Beispiel für einen RSS-Feed.

### Datenliste: Textlänge und Titel kürzen
- Block **Datenliste** hat zwei neue Optionen: **Textlänge** (vollständig, 2, 3, 4 oder 6 Zeilen) für Text-, mehrzeilige und formatierte
  Felder in Karten und Listen sowie **Titel kürzen** (2 oder 3 Zeilen) – z. B. für Nachrichten aus RSS-Feeds mit sehr langem Teaser.
  Standard „vollständig“: bestehende Listen bleiben unverändert.
- Umsetzung über `Core\Data\Clamp` (Klassen `dl-clamp dl-clamp-{n}` mit `line-clamp`, `resources/css/_data-clamp.css`; ohne
  Inline-Stile wegen CSP). Gekürzt erscheint Rich-Text als reiner, maskierter Text mit Auszug vom Server (keine Listen, Bilder oder
  Überschriften im Teaser, Screenreader lesen keinen doppelten Text); der volle Text steht auf der Detailseite.
- Kern-Ausgabe, Kit „editorial“ (Titel, Vorspann, weitere Felder) und alle Kits mit eigenem `data.css` (essenz, fluid, glas, modern,
  nature; basis, praxis und starter über den Kern) unterstützen die Option. Selbsttest in `blocks:selftest`.
- Behoben: In den Kits essenz, fluid, glas und nature ergab „Spalten: 2“ auf breiten Bildschirmen 3 Karten je Zeile und „4“ bis zu 5 –
  die Spaltenwahl ist jetzt wie im Kern die Höchstzahl (`--cols`), schmal weiterhin weniger Spalten.

### Wording: neutrale Begriffe in Kern-Oberfläche
- Geteilte Daten: Standardbegriff **Eigentümer-Website** statt „Haupt-Website“ (kollidierte mit „Hauptwebsite“ = Standard-Website
  der Installation); Kits können `project → terms → shared_owner …` weiter umbenennen. „Dem {owner} vorschlagen“ (grammatisch nur für
  männliche Begriffe passend) heißt jetzt „Zur Übernahme vorschlagen ({owner})“.
- Branchenreste aus Kern-Texten entfernt bzw. als neutrale Beispiele: S/MIME-Hilfe („Mailprogramm der Empfänger“), Platzhalter für
  KI-Hinweise, KI-Glossar, Synonyme, Seiten-Generator, Alt-Text, Zustell-Adresse, Tutorials „Medien“ und „Geteilte Daten“.
- „Eintrag“ statt „Datensatz“ bei Feldbindungen im Vorlagen-Editor (jetzt übersetzt), „Kit“ statt „Kit (Theme)“; Englisch:
  „Basic settings“ einheitlich für „Grundeinstellungen“, „network website“.

### Geteiltes Glossar: ein Glossar für mehrere Websites einer Installation
- **Glossar → Prüfen & Einstellungen → Mit anderen Websites teilen** (`/admin/glossar/teilen`, `Core\Glossary\Sharing`) – gebaut auf den
  geteilten Datentabellen (Schlüssel `glossar`, `storage/shared/glossar/`), kein eigener Mechanismus. **Teilen** macht die Website zur
  Eigentümerin (IDs und Adressen bleiben, `entry:glossar:{id}` wirkt weiter) und **lädt** andere Websites ein; alle sehen alles
  (gegenseitig + automatische Übernahme). **Beitreten** nur auf Einladung, mit **Abgleich doppelter Begriffe** (Begriff/Variante ohne
  Groß-/Kleinschreibung, Akzente, Leer- und Satzzeichen, je Sprache): je Doppel „Vorhandenen nutzen“ (Standard), „Eigenen übernehmen,
  anderen hier ausblenden“ oder „Beide behalten“. Neue IDs werden in Seiten, Versionen und Erklärungen umgeschrieben, die alte Tabelle
  bleibt als Sicherung. **Verlassen** mit Kopie (eigene Begriffe + zuletzt gezeigte fremde, gleiche IDs); die Eigentümerin beendet das
  Teilen erst ohne Mitglieder. Rechte: „Grundeinstellungen“ + „Geteilte Daten verwalten“; Ausblenden: `data.publish`.
- Alles liest das gemeinsame Glossar: Markierung im Text, Übersicht A–Z, Detailseiten `/glossar/{slug}` auf jeder Website,
  `/_glossary.json`, Quick-Glossar (Suche mit „von {Website}“, „Auf dieser Seite“, Doppelprüfung jetzt ohne Akzente), Linkauswahl,
  Prüfungen. Begriffsliste mit Herkunft und **Ausblenden** je Website; CSV-Import lässt fremde Begriffe unberührt.
- **Geteilte Daten allgemein:** Einladungen (`share.json` → `invited`, `Shared::addMember()`), Zusammenführen als eingeladene Website
  (`shareLocal(…, merge: true, $map, $skip)`), `Shared::leave()` für Mitglieder. Neue Einstellung **Canonical fremder Einträge**
  (Anzeige auf dieser Website): Standard „Ursprungs-Website“ (wie bisher, nicht in der Sitemap), wahlweise „Diese Website“
  (eigene Adresse, dann auch in der Sitemap). Die Sitemap nimmt fremde Einträge auf, deren Canonical hier liegt.
- Kommandozeile `glossary:share|invite|join|leave`, Selbsttest `glossary:selftest` (Doppel-Erkennung) und
  `glossary:sharetest --sandbox` (Ende-zu-Ende mit zwei Websites, nur in einer Wegwerf-Kopie: Teilen ein/aus, Doppel, gemeinsames
  Lesen, Ausblenden, Markierung, Canonical, Rechte, Verweise, Verlassen).

### Quick-Glossar auch beim Ansehen: jeden Text als Begriff übernehmen
- Im Bearbeiten-Modus lassen sich nur Textfelder markieren – beim **Ansehen** (angemeldet, Werkzeugleiste sichtbar) jetzt jeder
  Text der Seite: markieren, dann <kbd>⌥G</kbd>, der Knopf „Glossar“ oder der schwebende Knopf **Als Glossar-Begriff** neben der
  Markierung (nur Zeiger/Touch, nicht im Tab-Fluss – per Tastatur gilt <kbd>⌥G</kbd>; Markieren und Kopieren bleiben ungestört).
  „Neuer Begriff“ ist vorbelegt und prüft sofort **Gibt es schon?** (gleich, Variante, ähnlich); Hinweis „Wird automatisch auf allen
  Seiten markiert, sobald veröffentlicht“, nach dem Anlegen **Seite neu laden**. „Einfügen“ nur beim Bearbeiten – beim Ansehen
  „Öffnen“ (Verwaltung) und „Zum Verlinken in den Bearbeiten-Modus wechseln“. „Auf dieser Seite“ zählt bereits markierte Begriffe mit.
  Rechte unverändert (`data.edit`/`data.publish` auf „glossar“, Funktion „glossary“); Besucher bekommen nichts.
- Schnittstelle `$x->frontendTool([...])`: `'view' => true` (bzw. `'view'` in `modes`) – Werkzeug auch beim Ansehen, Standard bleibt
  nur Bearbeiten; `'chip' => 'Beschriftung'` für den schwebenden Knopf. `ctx.mode` ist jetzt `'view'|'edit'` (aktuell, Detailseiten
  wechseln ohne Neuladen), die Bearbeiten-Art steht in `ctx.editKind`; beim Ansehen liefert `selection()` den markierten Text der
  Seite, `insertText`/`insertLink` sind ohne Wirkung. Neues Ereignis `cms:mode-change`. `FrontendTools::viewing()`/`when()`,
  `extensions:selftest` erweitert.
- Behoben: HTML-Kommentare im Seiteninhalt (z. B. Fragment-Kommentare im Debug-Modus) brachten die Zerlegung von Glossar-Markierung
  (`Annotator`) und Redaktionsnotizen (`EditorNotes::decorate`, Ansicht der angemeldeten Redaktion) durcheinander – danach wurde nichts
  mehr markiert bzw. das Markup beschädigt. Selbsttests `glossary:selftest` und `notes:selftest` prüfen das.

### Karten und Kacheln: verlinkte Seite bzw. Eintrag direkt bearbeiten
- Karten, die auf eine andere Seite oder einen Eintrag zeigen, haben für die angemeldete Redaktion oben rechts **✎ Bearbeiten**
  (bei Zeiger/Fokus, auf Touch-Geräten immer, unter 768 px nur das Symbol). Seite → Seiten-Editor (`?edit=1`), im Editor mit
  ungespeicherten Änderungen erst die gewohnte Rückfrage („Speichern & beenden“ · „Verwerfen“ · „Weiter bearbeiten“);
  Eintrag → Seitenleiste „Eintrag bearbeiten“ an Ort und Stelle (Strg/⌘-Klick: Detailseite bzw. Verwaltung im neuen Tab).
- Vorlagen: `edit_link($ref, ?$label)` bzw. `$b->targetEdit($ref, ?$label)` (`Core\TargetEdit`) – `page:ID`, `entry:{tabelle}:{id}`
  oder eine Adresse dieser Website (Pfad, eigene Domain, Detailseite). Rechte `pages.edit` bzw. `data.edit` der Tabelle; Besucher,
  Seiten-Cache und „Live-Ansicht“ bekommen kein Markup. Stil im Kern (`editor.css`, `.cms-target-edit`), Verhalten `_target_edit.js`.
- Eingebaut in die Karten-Blöcke der Kern-Kits (cards, bento, teasers, team) sowie die Kern-Blöcke „Stapelkarten“ und „Nächste Termine“.
  Selbsttest `php bin/console targetedit:selftest`.

### Quick-Glossar beim Bearbeiten auf der Website
- Knopf **Glossar** in der Werkzeugleiste (⌥G, Telefon: Menü „⋯“), nur mit Funktion „glossary“ und Recht auf die Tabelle „glossar“.
  **Suchen** (Begriff, Varianten, Kurz-Erklärung) mit „Einfügen“ = Link `entry:glossar:{id}` an der Schreibmarke bzw. um den markierten
  Text; **Neuer Begriff** (vorbelegt mit der Markierung, Entwurf bzw. veröffentlicht je nach `data.publish`, Doppel werden erkannt);
  **Auf dieser Seite** (was die automatische Markierung kennzeichnen würde, „Zur Stelle“ markiert den Treffer im Text).
  Tastatur vollständig, hell/dunkel, ab 390 px als Blatt unten.
- Erstes Werkzeug über die neue Schnittstelle (`Core\Glossary\QuickTool`, `resources/js/quick-glossary.mjs`) – Referenzbeispiel
  für Erweiterungen. Endpunkte `GET /admin/api/glossar/suche`, `POST /admin/api/glossar/begriff`, `POST /admin/api/glossar/seite`;
  `glossary:selftest` prüft Treffer und Endpunkte.

### Werkzeuge und Ereignisse beim Bearbeiten auf der Website
- **Werkzeuge:** `$x->frontendTool([...])` (`Core\FrontendTools`) – Knopf in der Werkzeugleiste oder Eintrag im Menü „⋯“, Tastenkürzel,
  Rechte (`perm`, `table`, `feature`, `visible`), Modi `page`/`entry`. Nur angemeldet und nur im Bearbeiten-Modus; das ES-Modul lädt
  erst beim ersten Öffnen (Besucher laden nichts). Oberfläche: Seitenleiste in der Shadow-DOM-Ebene (`role="dialog"`, Esc, Fokus zurück).
- **JavaScript:** `CMSAdmin.tools` (`register`, `open`, `close`, `toggle`, `unmount`) und `ctx` mit Seite/Eintrag, Sprache, CSRF,
  `fetch()`, gemerkter Schreibmarke (`selection()`), `insertText()`, `insertLink()` (gleiche Logik wie die Linkauswahl, jetzt
  `Rich.insertLink`), `toast()`, `announce()`, `on()`. Tasten in der Seitenleiste erreichen Editor.js nicht mehr.
- **Ereignisse im Browser:** `cms:editor-ready`, `cms:block-select`, `cms:before-save` (abbrechbar, `detail.waitUntil(promise)`),
  `cms:saved`, `cms:published`, `cms:status-changed`, `cms:tool-open`/`-close` (`CMSAdmin.events`).
- **Ereignisse auf dem Server** (`$x->on()`): `page.saved`, `page.published`, `page.unpublished`, `page.discarded`, `page.deleted`,
  `entry.saved`, `entry.published`, `entry.unpublished`, `entry.deleted` – unabhängig vom Weg (Verwaltung, Website, API, MCP, CLI);
  `Extensions::listens()`. Selbsttest `php bin/console extensions:selftest`. Doku: Technik → „Erweiterungen: Seiten, Werkzeuge & Ereignisse“.

### Verwaltung: Seiten nach Art – Einstellungen und Statistiken gesammelt
- **Arten:** Jede Seite, die eine Funktion oder Erweiterung in der Verwaltung anmeldet, hat eine Art (`Core\AdminPages`):
  `content` und `tool` stehen im Menü (Hauptmenü bzw. Administration), `settings` gesammelt unter **Administration → Einstellungen**
  (`/admin/einstellungen`, eine Karte je Seite mit Symbol und Beschreibung, nur was die Rolle öffnen darf), `stats` unter
  **Administration → Statistiken** (`/admin/statistiken`, Menüpunkt nur wenn vorhanden). Mit `'table' => 'handle'` erscheint eine
  Einstellungsseite zusätzlich an der Datentabelle (Knopf im Kopf der Liste, Unterpunkt in der Daten-Navigation).
- **Umgezogen:** **Glossar** (nicht mehr im Hauptmenü → Daten → Glossar → „Prüfen & Einstellungen“ und Einstellungen; die Seite
  zeigt die Daten-Navigation), **Chat-Einstellungen** und **API & MCP** (aus „Administration“ → Einstellungen). Adressen und Rechte
  bleiben gleich; alle Einstellungsseiten sind über die Suche (⌘K) direkt erreichbar, „Funktionen & Erweiterungen“ nennt den Ort.
- **Erweiterungen:** `$x->adminPage(['href', 'label', 'kind', 'icon', 'perm', 'table', 'tableLabel', 'description', 'feature', 'visible', 'place'])`;
  `$x->nav(…, $kindOderPlatz, $angaben)` als Kurzform. `nav()` ohne Art ist **veraltet** und bleibt an seinem Platz (`main` → content,
  `admin` → tool); `Extensions::adminNav()` ebenso (Layout: `AdminPages::nav()`).

### Linkauswahl: Ansicht „Daten“ und Glossar
- **Daten:** Im Reiter „Seiten & Inhalte“ gibt es jetzt **Suche | Struktur | Daten**. „Daten“ ist ein kleiner Datenbrowser: Eine
  Auswahl listet alle verlinkbaren Quellen (jede Inhaltstabelle mit URL-Basis und Detailseite, dazu „Glossar“ bei eingeschalteter
  Funktion), darunter die Einträge der gewählten Quelle neueste zuerst (Titel, kurzes Datum, Entwurf gekennzeichnet) mit Filter
  und „Weitere laden“ (je 30, über `offset`). <kbd>↑</kbd>/<kbd>↓</kbd> wie in der Suche, <kbd>Enter</kbd>/Klick übernimmt.
  Ansicht und Quelle merkt sich der Browser (`cms-links-view`, `cms-links-source`); beim Bearbeiten eines Eintrags-Links ist
  dessen Tabelle gewählt und das Ziel markiert – in allen Ansichten.
- **Glossar:** Begriffe sind Einträge mit eigener Detailseite – verlinkt wird stabil mit `entry:glossar:{id}` (→ `/glossar/{slug}`),
  nur veröffentlichte Begriffe (auch für die Redaktion), in der Suche auch über den Begriff; „glossar“ als Suchbegriff zeigt alle.
- **API:** `GET /admin/api/links?format=sources` (`Core\Links::dataSources()`); `format=groups&group=entries:{tabelle}&literal=1`
  filtert nur die Einträge. Einträge je Tabelle kommen neueste zuerst (Datumsfeld der Sortierung bzw. Anlagedatum) mit `date`/`day`.
  Entwürfe weiterhin nur mit `data.edit` für die Tabelle. Selbsttest `links:selftest` um Quellen, Blättern, Filter, Rechte und Glossar erweitert.

### Linkauswahl: Struktur, „Weitere laden“, neueste Einträge
- **Struktur:** Im Reiter „Seiten & Inhalte“ schaltet **Suche | Struktur** auf den echten Seitenbaum um – Reihenfolge und Ebenen
  wie unter „Seiten“, Status Offline/Entwurf, Anker als Unterpunkte (`page:ID#anker`), Sprache wählbar (DE/EN …, Vorgabe: Sprache
  der bearbeiteten Seite). WAI-ARIA-Baum mit <kbd>↑</kbd>/<kbd>↓</kbd>, <kbd>→</kbd>/<kbd>←</kbd> (auf-/zuklappen, Kind/Eltern),
  <kbd>Enter</kbd> übernimmt, Tippen wechselt zur Suche; aktuelles Ziel ist markiert und aufgeklappt. Die zuletzt gewählte Ansicht
  merkt sich der Browser (`cms-links-view`). Neu: `GET /admin/api/links?format=tree&lang=…&page=ID` (`Core\Links::tree()`).
- **Weitere laden:** Jede Gruppe mit mehr Treffern („15 von 37“) endet mit „Weitere laden“ (auch per Tastatur) – Seiten, Einträge
  je Tabelle, neueste Einträge, Dateien. API: `&group=…&offset=N&limit=N`, jede Gruppe liefert `total` und `offset`.
- **Neueste Einträge:** Ohne Suchbegriff die zuletzt geänderten verlinkbaren Einträge aller Tabellen mit Detailseite (Tabelle,
  kurzes Datum, Entwurf gekennzeichnet) statt der Gruppen je Tabelle; ein Suchbegriff, der auf den Tabellennamen passt, zeigt alle
  Einträge dieser Tabelle. Entwürfe nur mit `data.edit` für die Tabelle. Selbsttest `php bin/console links:selftest`.

### Visitenkarte (vCard) der Organisation und von Personen
- **`GET /vcard.vcf`:** Visitenkarte der Website-Organisation zum Speichern im Adressbuch (vCard 3.0 – iOS, Android, Outlook):
  `FN`/`ORG` (`org_name()`), `ADR` (Straße, PLZ, Ort, Land), `TEL;TYPE=WORK,VOICE`, `EMAIL;TYPE=INTERNET,WORK`, `URL`
  (Hauptadresse), `GEO` (Standort der Karte), `PHOTO` (Logo als PNG/JPEG, verkleinert, höchstens 48 KB, nie SVG), `NOTE` mit
  den Öffnungszeiten. Quelle sind die zentralen Angaben des Kits (`project.public_info`, JSON-LD der Organisation) – keine neue
  Ablage, keine Funktion zum Einschalten; ohne Telefon, E-Mail und Adresse 404. `?lang=en` für weitere Sprachen.
- **`GET /vcard/{tabelle}/{slug}.vcf`:** Personen aus Tabellen mit Schema-Typ „Person“ (z. B. Team): Name (N/FN), Organisation,
  Funktion (`TITLE`), Telefon, E-Mail, Detailseite, Foto – nur veröffentlichte Einträge.
- Texte maskiert, Zeilen nach 75 Oktetts gefaltet (UTF-8-sicher), CRLF; `Content-Disposition: attachment` mit Namen als
  Dateiname, `Cache-Control: public, max-age=900`, `noindex`; im Wartungsmodus nur für Angemeldete.
- Helfer für Kits: `vcard_url()` und `vcard_entry_url($table, $entry)` (jeweils `null` ohne Karte). `/vcard` ist als
  Seitenadresse gesperrt. Code: `Core\VCard`, `VCardController`; Selbsttest `php bin/console vcard:selftest`.

### Status Online ⇄ Offline direkt umschalten
- **Seitenbaum:** Der Status ist ein Knopf (Recht `pages.publish`, nicht die Startseite): **Online** → „Offline nehmen“ (gestaltete
  Rückfrage), **Offline** (war schon online) bzw. **Entwurf** (nie veröffentlicht) → „Online stellen“ über `Pages::publish`
  (Platzhalter-Sperre: Meldung über der Liste, Seite bleibt offline; offline mit offenem Entwurf fragt nach). Auch im Kontextmenü,
  per Tastatur (<kbd>Tab</kbd>/<kbd>Enter</kbd>, <kbd>Umschalt</kbd>+<kbd>F10</kbd>), ohne Neuladen, Meldung in der Live-Region,
  Zähler „Entwürfe“ in der Seitenleiste aktualisiert sich. Neu: `POST /admin/pages/{id}/offline` (`Pages::unpublish`: Status
  `draft`, `content_published` bleibt), `Pages::state()` (online | offline | draft).
- **Eintragsliste (Daten):** Status-Spalte als Knopf für Tabellen mit Freigabe (`data.publish`, nur eigene Einträge – nicht von
  anderen Websites geteilter Tabellen oder aus externen Quellen); nutzt `POST /admin/data/{tabelle}/bulk`, Zähler der Filter und
  „Entwürfe“ folgen ohne Neuladen. Anzeige „Offline“ für schon einmal veröffentlichte Einträge im Entwurf.
- **Werkzeugleiste der Website:** neuer Chip-Zustand **Offline**; das Erklärfeld bietet „Offline nehmen“ (online, mit Rückfrage)
  bzw. „Online stellen“ (offline ohne offenen Entwurf) für Seiten und Detailseiten von Einträgen – ohne Neuladen. Mit
  ungespeicherten/unveröffentlichten Änderungen bleibt es bei „Veröffentlichen“.
- Besucher erhalten für offline genommene Seiten/Einträge „Nicht gefunden“; Menü, Sitemap, Suche und Seiten-Cache folgen wie
  beim Veröffentlichen (`PageCache::clear` → `Search::changed`).

### Schreibweise „KLXM AI“ und „KI“
- Produktname durchgängig **„KLXM AI“** (vorher „KLXM Ai“): Oberfläche, Standard für `ai_brand`, Handbücher, Tutorials,
  Trailer-/Tutorial-Texte und Aussprache-Lexika; Abkürzungen „AI“/„KI“ immer groß. Code-Namen (`Core\AI\Ai`, `ai`, `/admin/ai`) bleiben.
  Gespeicherte Inhalte und ein eigener `ai_brand`-Wert werden nicht automatisch umgeschrieben.

### Layout: Blöcke in Spalten (ersetzt „Neben den vorigen Block stellen“)
- Neuer Kern-Block **„Layout (Spalten)“** (`layout`, alle Kits; `theme.php → 'layout' => false` schaltet ab): Raster ½+½, ⅔+⅓,
  ⅓+⅔, ⅓×3, ¼×4, ¼+¾, ¾+¼, Ausrichtung vertikal (oben/mitte/unten/gestreckt), Abstand (klein/normal/groß), Stapeln auf schmalen
  Bildschirmen (Standard < 768 px, „schon auf Tablets“ < 1024 px; Kits über `--lay-stack`), „Reihenfolge mobil umkehren“.
  Abschnitts-Optionen gelten für das ganze Layout. Daten `columns: [{blocks: [{id, type, data, tunes?}]}]`; weniger Spalten →
  Blöcke wandern in die letzte Spalte (nie Inhalt verlieren).
- **Nur verschachtelbare Blöcke** in Spalten: Block-Definition `'nestable' => true | ['variante', …]`; Prüfung beim Speichern
  (`Pages::sanitizeBlocks`, abgelehnte in `Pages::$rejected`, REST/MCP 422), eindeutige Block-IDs, kein Layout im Layout, keine
  Blöcke mit eigener Hülle. Kern: Formular, Datensatz-Felder, Stelle (Eckdaten/Bewerbung), Nächste Termine, Karte; Kits praxis,
  basis, starter markiert, sonst Standardliste (`Layout::DEFAULT_NESTABLE`).
- Ausgabe ohne eigenen Abschnitt je Kind (`Layout::render`, `css/layout.css`, Variablen `--lay-*`); Kind mit eigener Fläche = Karte.
  Seitenweite Auswertung über `Layout::flatten()`: Stylesheets je Typ, Suchindex, Sprungmarken, Mediennutzung, SEO-Prüfung; JSON-LD,
  Redaktionsnotizen und Glossar auch in Spalten.
- **Editor:** Spalten wie auf der Website; je Block in einer Spalte eine Leiste im Fluss (↑ ↓, ← → in die Nachbarspalte,
  Bearbeiten in der Seitenleiste mit Optionen „In der Spalte“, Löschen mit Rückfrage), „+ Block in diese Spalte“ (nur passende
  Blöcke), Direktbearbeitung über `columns.{s}.blocks.{n}.data.{feld}`, Tastatur, Live-Meldungen.
- Abschnitts-Option **„Neben den vorigen Block stellen“ abgeschafft** (Tune `row` nur noch zur Darstellung alter Inhalte;
  „Aus der Reihe lösen“ in der Seitenleiste). Umstellung `php bin/console layout:migrate-rows [--site=…] [--dry-run]`: Reihe aus
  verschachtelbaren Blöcken → ein Layout (nächstes Raster, Optionen des ersten Blocks, anderer Hintergrund = Karte), sonst Reihe
  aufheben (untereinander) und melden; veröffentlicht/Entwurf getrennt, Version „Reihen in Layout umgewandelt“, wiederholbar.
  Kit praxis: `praxis:migrate-team` erzeugt direkt ein Layout ⅔ + ⅓.
- Handbuch („Layout: Blöcke in Spalten“, Blockliste mit passenden Blöcken je Kit), Entwicklerhandbuch, `blocks:selftest` (+18).

### Glossar – Fachbegriffe im Text erklären (Funktion `glossary`, Standard aus)
- Begriffe als Datentabelle **„Glossar“** (Begriff, Varianten/Synonyme/Abkürzungen, Kurz-Erklärung ≤ 240 Zeichen, ausführliche
  Erklärung, Kategorie, Mehr erfahren, Status); einrichten unter **Verwaltung → Glossar** bzw. `glossary:install`. Detailseiten
  `/glossar/<begriff>` (JSON-LD `DefinedTerm`), Übersicht A–Z mit Buchstaben und Suchfilter (Kern-Block **„Glossar“**,
  `DefinedTermSet`).
- **Markierung auf der Website** (`Core\Glossary\Annotator`): erstes Vorkommen je Seite (oder je Abschnitt) im fertigen HTML,
  vor dem Seiten-Cache – auch in Ausgaben von Erweiterungen. Nur lesbarer Inhalt (nicht in Links, Buttons, Code, Formularen,
  Navigation, Kopf/Fuß, h1–h3, `[data-glossary=off]`, eigener Detailseite, Bearbeiten-Modus); Unicode-Wortgrenzen, Abkürzungen
  nur in genauer Schreibweise, Wörter mit üblichen Endungen, längste Variante zuerst, nie doppelt.
- Barrierearm: Schaltfläche mit `aria-expanded` + Popover („Toggletip“, kein `role=tooltip`), Tastatur (Enter/Leertaste/Esc),
  Touch (Blatt unten), Druck mit Erklärung in Klammern, „Bewegung reduzieren“, kein Layout-Verschieben. CSP-konform:
  `glossary.js` ≈ 1,2 KB, `glossary.css` ≈ 1,4 KB (gz) nur auf Seiten mit Begriffen. Kits passen über `--gl-*` bzw.
  `css/glossary.css` an; Standard passt sich hell/dunkel an.
- **Dynamische Bereiche** (Einstellung bzw. `data-glossary="live"`): Inhalte, die erst im Browser entstehen (z. B. Ergebnisse
  von KLXM Check), markiert `glossary-live.mjs` über `/_glossary.json`.
- Redaktion: Abschnitts-Option **„Glossar-Begriffe hier nicht markieren“**, Seiten ausnehmen, Hinweise zu doppelten/überlappenden
  Varianten, „Vorkommen“ je Begriff (Text des Suchindex), CSV-Import/-Export, „Begriff schnell hinzufügen“ – optional mit
  **KI-Vorschlag** (immer als Entwurf). Entwürfe sieht die angemeldete Redaktion im Text mit Hinweis „Entwurf“.
- Suche & Besucher-Chat: veröffentlichte Begriffe kommen über ihre Detailseiten automatisch in den Suchindex.
- Kommandozeile: `glossary:install`, `glossary:import`, `glossary:export`, `glossary:check`, `glossary:selftest [--bench]`.
- **Mehrsprachig:** Begriffe je Sprache als Übersetzung des Eintrags; eine Seite markiert nur die Begriffe ihrer Sprache, mit
  Wortendungen dieser Sprache (Englisch `-s`/`-es`, `y` → `ies`; sonst die deutschen Endungen), auch in `glossary-live.mjs`.
  Übersicht = Übersetzung der Glossar-Seite (z. B. `/en/glossary`, Link im Fußbereich nur, wenn sie veröffentlicht ist),
  Detailseiten `/en/glossar/<slug>`, JSON-LD mit `inLanguage`. Hinweise und „Vorkommen“ je Sprache, Kennzeichen der Sprache in
  der Liste, CSV mit Spalte `lang`.

### Stellenangebote & Google for Jobs – Vorlage „Stellenangebote“ + Eingang „Bewerbungen“
- Neue Tabellen-Vorlage **„Stellenangebote“** (Daten → Vorlage, alle Kits): Titel, Kurzbeschreibung, Beschreibung, Veröffentlicht am
  (heute vorbelegt), Gültig bis, Beschäftigungsart, Beginn, Arbeitsweise (vor Ort/hybrid/remote), Arbeitsort (leer = Adresse der
  Organisation), Gehalt von/bis/pro, Arbeitgeber, Kennung, Ansprechperson, Bild. Neuer schema.org-Typ `JobPosting`
  (`Core\Data\Jobs`): genau ein JobPosting je Detailseite nach den Vorgaben von Google (description als HTML, datePosted,
  validThrough = Tagesende mit Zeitzone, employmentType nach Google-Werten, jobLocation bzw. TELECOMMUTE +
  applicantLocationRequirements, baseSalary nur mit Angaben, identifier, directApply). Prüfung `Jobs::check()` – Hinweis
  „Google for Jobs“ im Eintrag.
- **Abgelaufene Stellen** (Gültig bis < heute): kein JSON-LD, `noindex`, Hinweis „Diese Stelle ist nicht mehr ausgeschrieben“,
  kein Formular; aus Datenliste, Sitemap, Suche und API ausgeblendet (`Entries::where`, nicht in der Verwaltung).
- Kern-Blöcke **„Stelle: Eckdaten“** (`job_facts`, Button „Jetzt bewerben“ → `#bewerben`) und **„Stelle: Bewerbung“**
  (`job_apply`); Stile `css/jobs.css` (Variablen `--job-*`, Kit kann ersetzen). Detailvorlage: Kopf · Eckdaten · Beschreibung ·
  Bewerbung. Datenliste: Kurzzeile „Vollzeit, Teilzeit · Ort“ bei Stellen; neue Option **„Abschnitt ausblenden, wenn nichts da ist“**.
- Vorlage **„Bewerbungen“** (Eingang): Stelle, Vorname, Nachname, E-Mail, Telefon, Nachricht, Lebenslauf und weitere Unterlagen
  (PDF/DOCX/ODT) – Standard „nur per E-Mail“ an die Website-Adresse. Auf Stellenseiten ist „Stelle“ vorbelegt und gesperrt
  (`DataForms::render` Option `locked`; den Wert setzt der Server aus `_job`), der Betreff der E-Mail nennt die Stelle.
- Kommandozeile: `data:template <vorlage> [--slug=…] [--form=…] [--with-detail-page] [--with-list-page] [--dry-run]` (alle
  Vorlagen, wiederholbar), `jobs:from-page <seite>` (alte Stellenseite → Eintrag, Formular verknüpfen, Links umstellen, Seite
  offline + 301), `jobs:selftest`. Handbuch „Stellenangebote & Google for Jobs“ (Checkliste Search Console, Rich-Results-Test),
  Entwicklerhandbuch mit Feldzuordnung.

### Blöcke nebeneinander (Reihen) – Abschnitts-Option „Neben den vorigen Block stellen“
- Neue Abschnitts-Option `row` (Abschnitt & Navigation): Breite **dieses** Blocks neben dem vorigen – ½, ⅓, ⅔, ¼, ¾ oder
  automatisch; der erste Block bekommt den Rest. Kein verschachtelter Editor, kein „Raster-Block“: Jeder Block bleibt ein
  normaler Block. `Theme::renderBlocks()` fasst aufeinanderfolgende Blöcke zu **einem Abschnitt** zusammen (`Theme::renderRow()`):
  Hintergrund, Anker, Navigation, Abstände, Trennlinie, Vollbild und Hintergrundbild vom ersten Block (Klasse `sec--row`),
  darin `.wrap.sec-row` mit einer Zelle je Block (Inhalt ohne eigene Hülle, `Theme::renderInner()`). Späterer Block mit
  **anderem Hintergrund → Karte** (`sec sec-row__cell--card bg-{name}`), gleicher Hintergrund → nahtlos; eigene Sprungmarke an
  der Zelle. Am ersten Block der Seite (und nach Blöcken mit eigener Hülle, `raw`) wird die Option ignoriert.
- `css/rows.css` (nur mit Reihe auf der Seite, Kit kann sie ersetzen): Flexbox ohne Media Query, Anteile in Zwölfteln,
  untereinander unter `--row-stack` (Standard 700px Inhaltsbreite ≈ Fenster < 768 px); `--row-gap`, `--row-align`,
  `--row-card-pad`, `--row-card-radius`. Funktioniert mit allen mitgelieferten Kits (Partial `section` + `.wrap`);
  `theme.php → 'rows' => false` schaltet ab, `['wrap' => …]` setzt die Container-Klasse. Block-Vorlagen: `$b->inRow()`, `$b->rowLead`.
- Editor: Blöcke einer Reihe ab 1100 px Fensterbreite auch im Editor nebeneinander (Anteile wie auf der Website, spätere Blöcke
  als Zelle/Karte mit Hintergrund und Abständen des ersten), schmaler untereinander; Markierung „In einer Reihe mit dem vorigen
  Block (½)“ bzw. „wird ignoriert“ am ersten Block der Seite. REST/MCP/OpenAPI: `section.row`. `blocks:selftest` prüft Anteile,
  Bereinigung und Gruppierung.
- Kit-Befehle: `kits/{kit}/console.php` (`Core\Kit::commands()`) – Befehle des aktiven Kits in `bin/console`.
- Kit „basis“: Karten-Fläche für „Standard“-Hintergrund, Box-Variante des Handlungsaufrufs in einer Karte ohne zweite Fläche.
- Kit „praxis“: „Handlungsaufruf“ mit Variante **Box / Karte** (kleine Überschrift – in einer Reihe h3 –, optionaler Text, Button;
  allein hell mit Rahmen, in einer Reihe mit anderem Hintergrund als farbige Karte) und optionalem Text auch im Band;
  „Text + Bild“ zusätzlich im Format 16:9. Reihen stehen in diesem Kit bis ca. 1080 px Fensterbreite untereinander
  (`--row-stack:1000px`). Block **„Teamfoto + Text“ veraltet** (nicht mehr einfügbar, bestehende werden weiter dargestellt):
  `php bin/console praxis:migrate-team [--dry-run] [--page=ID]` ersetzt ihn durch „Bild breit“ (16:9, mit Anker/Navigation des
  alten Blocks) + „Fließtext“ (⅔) + „Handlungsaufruf“ als dunkle Box daneben (⅓, nur mit Ausbildungs-/Stellen-Box) – Entwurf und
  veröffentlichte Fassung je für sich, Version „Team-Abschnitt umgestellt“, wiederholbar.

### Karten: „Route planen“ je Plattform, 3D-Ansicht
- `Core\Maps`: „Route planen“ führt standardmäßig zu Google Maps (auf Android öffnet dieselbe Adresse die App); auf
  iPhone/iPad/Mac tauscht das Kit-Skript (`a[data-route]` → `data-apple`; Kit „praxis“: `site.js`) auf Apple Karten, der Link nennt den Dienst
  („mit Apple Karten“). Aufklapper „Andere Karten-App“ (Apple Karten, Google Maps, OpenStreetMap) – `<details>`, auch ohne
  JavaScript. Ziel = Koordinaten des Standorts (`Maps::routeUrls()`); ein eigener Routenplaner-Link aus den Einstellungen hat
  weiter Vorrang. Bewusst ohne Bestätigungsdialog: Navigation ist ausdrücklich gewünscht, übermittelt wird nur das Ziel.
- 3D-Ansicht (`project → map → 3d` bzw. Option `3d`, schlank in `map.mjs`): Gebäude als Körper (fill-extrusion aus
  `render_height` der OpenMapTiles-Kacheln; flache Gebäude des Stils ausgeblendet), weiche Kamerafahrt auf 55° Neigung /
  −20° Drehung (maxPitch 60), bei „Bewegung reduzieren“ sofort; Steuerung mit Kompass/Neigung, Tastatur (Umschalt + Pfeile).
  Weiter nur nach Klick, alles über `/proxy/ofm`. Kit „praxis“: 3D an.

### Kit „praxis“: Kontaktkarte überarbeitet (Öffnungszeiten, Notfallnummern, externe Dienste, Drehung)
- Zeiten **bündig** (Beginn rechtsbündig, Strich, Ende; tabellarische Ziffern) in der Karte und in „Alle Öffnungszeiten“
  (`praxis_range_html()`); Vorderseite kompakter, eigene Linien-Symbole je Dienst (`praxis_icon()`: Kalender, Tablette,
  Dokument mit Pfeil), auch im Mobilmenü und in der Schnellkontakt-Leiste.
- **Geschlossen** (im Browser nach Ortszeit berechnet, seitencache-fest): „Wir öffnen wieder um … / morgen um … / am Montag um …“
  und „In dringenden Notfällen“ mit **116 117** und **112** als `tel:`-Links; Chip nur „Geschlossen“. Beide Varianten stehen im
  Markup (Platz reserviert, kein Layout-Shift). Hinweiszeile (`notfall_kurz`): Trennzeichen-Reste („! ·.“) aufgeräumt
  (`praxis_card_note()`), Sätze mit 116 117/112 werden bei „geschlossen“ ausgeblendet.
- **Externe Dienste** (Doctolib, externes Rezept/Überweisung): Kachel dreht zu „Sie verlassen unsere Website und wechseln zu …
  Dort gelten deren Datenschutzbestimmungen.“ mit „Weiter zu …“ / „Abbrechen“ (Esc, Fokus zurück); ohne JavaScript normaler Link mit ↗.
- **Drehung**: beide Seiten drehen einzeln (600 ms, symmetrische Kurve, Seitenwechsel genau bei 90°), Rückseite zieht synchron
  auf ihre Höhe auf und liegt als eigene Ebene über dem folgenden Inhalt – Hero und Seite verschieben sich nicht (CLS 0);
  „Bewegung reduzieren“: Überblenden. Desktop: Hero-Höhe nur nach Text, die Karte ragt nach unten. Rückseiten-Stile
  `css/card-back.css` erst beim Umdrehen. Hero-Steuerung reserviert ohne JavaScript ihren Platz (Layout-Shift beim Start behoben).
- **Online-Rezept**: Gruppe „Medikamente“ → ein mehrzeiliges Textfeld (`field_updates` ID `praxis-rezept-medikamente-text-2026-09-29`,
  wird beim nächsten Aufruf/`migrate` einmalig angewendet). Alte Anfragen mit der Gruppe zeigen weiter eine Tabelle mit den
  alten Beschriftungen (`Inbox::open` liest die frühere Definition aus `field_updates`). `form.js` prüft auch Textfelder.

### public/: Kits, Erweiterungen und Schriften unter /assets/ – Seitenadressen /kits, /themes, /extensions, /fonts frei
- Jeder Ordner ganz oben in `public/` sperrte die gleichnamige Seitenadresse (Apache/nginx: 301 → 403). Code- und
  Design-Dateien liegen jetzt unter `/assets/`: Kits `public/assets/kits/{kit}/` (vorher `public/kits/`, `public/themes/`),
  Erweiterungen `public/assets/ext/{name}/` (vorher `public/extensions/`), installierte Schriften
  `public/assets/fonts/installed/` (vorher `public/fonts/`; eigener Unterordner neben der Kern-Schrift Lato in
  `public/assets/fonts/`). Uploads (`media/`, `pools/`, `sites/`) bleiben unverändert.
- Zentrale Pfad-API `Core\PublicPaths` (Bereiche, Rückfall, gesperrte Adressen, Umstellung); `Kit::publicDir()/url()`,
  `Extension::asset()` + neu `publicDir()`, `Fonts::dir()/url()`, `extensions:publish`, `kit:create`, `tools/build.mjs`
  und `tools/licenses.mjs` nutzen sie. Gebaute Dateien liegen versioniert unter `public/assets/kits`, `public/assets/ext`.
- **Rückfall:** Liegt etwas nur am alten Ort (Code ausgerollt, Server noch nicht umgestellt), zeigen die Adressen dorthin;
  `health` meldet die alten Ordner als Hinweis.
- **Umstellung:** `php bin/console assets:migrate [--dry-run] [--no-backup]` – Sicherung
  `storage/backups/public-assets-<zeit>.tar.gz`, verschieben (bei doppelten Kits gewinnt die neueste Fassung, die andere
  nach `storage/backups/public-assets-alt-<zeit>/`), Übergangs-Links entfernen bzw. Links nach außen neu anlegen,
  Seiten-Cache leeren, fest eingetragene alte Adressen in Kits/Erweiterungen melden; idempotent. `deploy/deploy.sh`
  verlinkt `shared/public/fonts` jetzt als `public/assets/fonts/installed`.
- Alte Adressen `/kits/…`, `/themes/…`, `/extensions/…`, `/fonts/…` leitet `public/index.php` mit 301 auf `/assets/…` um,
  sobald die Datei dort liegt – eine Seite `/kits` bleibt erreichbar. nginx-Beispiel: Cache-Regel nur noch `^/assets/`,
  alte Präfixe an PHP.
- Gesperrte Seitenadressen ganz oben: eine Liste (`PublicPaths::RESERVED_SLUGS`: admin, api, anfrage, assets, media, pools,
  sites, sitemap-xml, robots-txt, home, index-php) plus jeder Ordner, der noch in `public/` liegt – genutzt von Seiten,
  KI-Seitengenerator, SEO-Vorschlag, API/MCP und Routen der Datentabellen. Kit-Tutorials mit `'video' => '/kits/…'`
  zeigen automatisch auf den neuen Ort.

### Seiten: Systemadressen verständlich gesperrt
- `/kits`, `/themes`, `/media`, `/pools`, `/assets` … sind Ordner in `public/` – der Webserver liefert dort den Ordner (301 → 403)
  statt der Seite. Gesperrt nur noch ganz oben und in der Hauptsprache (`/en/kits`, `/leistungen/kits` sind frei); die Meldung
  nennt den Grund. Bestehende Seiten mit so einer Adresse speichern weiter, das Formular warnt aber, dass sie nicht erreichbar sind.

### Seiten-Editor: „Felder bearbeiten“ bei Formular-Blöcken; Hilfetext und Dateihinweis getrennt
- **Neu:** Blöcke mit einem Formular aus einer Datentabelle („Formular (Datentabelle)“, Formular in `contact` der Kits,
  Newsletter in editorial) zeigen in der Block-Leiste zusätzlich **Felder bearbeiten** – nur mit dem Recht „Tabellen und
  Felder ändern“ (`data.schema`; geteilte Tabellen nur auf der Eigentümer-Website). Seitenleiste wie „Eintrag bearbeiten“
  (Shadow DOM, `resources/js/_form_fields.js`, `app/Views/formfields-panel.php`): Bezeichnung, Kurzname (automatisch),
  Typ, Pflichtfeld, halbe Breite, Hilfetext, Auswahlmöglichkeiten, erlaubte Dateitypen und Höchstgröße, Reihenfolge ↑ ↓,
  Entfernen mit Rückfrage in der Zeile, „Feld hinzufügen“; dazu Formular an/aus, „Felder im Formular“, Text nach dem
  Absenden, Button-Beschriftung (Inhaltstabellen: Datei-Uploads). Link „Alle Einstellungen der Tabelle“ zur Verwaltung.
- Speichern über `GET|POST /admin/api/formfields/{tabelle}` (`Admin\FormFieldsController`, CSRF) → `Core\Data\SchemaPanel`:
  die Angaben der Leiste werden über die gespeicherte Definition gelegt (`Tables::toInput`), alles andere bleibt; geprüft
  und gespeichert wie im Tabellen-Designer (`DataController::saveSchema` → `Tables::validate`, Rückfrage „Felder wirklich
  löschen“). Eingangs-Tabellen behalten alle Regeln (keine Bilder/Verknüpfungen/formatierten Texte, Dateifelder nur bei
  Zustellung per E-Mail); Zustellung und Verschlüsselung stehen nicht in der Leiste. Neue Felder kommen in eine
  bestehende Auswahl „Felder im Formular“, umbenannte bleiben ausgewählt. Typwechsel bei vorhandenen Einträgen: Hinweis.
- Nach dem Speichern stellt der Editor alle Blöcke mit dieser Tabelle neu dar (`loadPreview`) – ohne Neuladen.
  Tastatur: Esc, Strg/⌘+S, Ansage der Position beim Verschieben, Fokus zurück zum Knopf; mobil in voller Breite.
- Blockdefinition: optional `'formfields' => 'feldname'`; sonst das erste `datatable`-Feld mit `'inbox' => true`.
- **Behoben:** Hilfetext eines Dateifelds und der automatische Hinweis („PDF oder Bild …, höchstens 5 MB.“) wurden zu
  einem Satz verbunden („Gerne auch als PDF PDF oder Bild …“). Jetzt eigene Absätze (`…-h`, `…-k`, Klasse
  `dff-help--file`), beide in `aria-describedby` – im Kern (`DataForms::render`) und im Kit praxis (`partials/form.php`,
  zeigt Hilfetexte jetzt auch bei anderen Feldern).
- Selbsttest `php bin/console data:selftest` (Rundlauf aller Tabellen, Seitenleiste mit vorübergehenden Inhalts- und
  Eingangstabellen; `--roundtrip` nur Rundlauf, ändert nichts); `inbox:selftest` prüft die getrennten Hinweise. Handbuch „Daten → Felder direkt auf der Seite
  ändern“ und „Seiten bearbeiten“, Entwicklerhandbuch „Seiten-Editor“, „Formulare“, „Kommandozeile“, `lang/en.php`.

### Formulare: Dateifeld fehlte im Eingangs-Formular; erlaubte Dateitypen je Dateifeld
- **Behoben:** In Eingangs-Tabellen (Anfragen) mit Zustellung per E-Mail fehlte ein Dateifeld im Formular, wenn es nach
  dem ersten Speichern der Auswahl „Felder im Formular“ angelegt wurde – die Auswahl war dann eine feste Liste ohne das
  neue Feld, und die Einstellungen zeigten es erst nach dem Speichern (nicht angehakt). Jetzt merkt die Tabelle, welche
  Felder zur Wahl standen (`settings.form.known`); neu angelegte Felder sind im Formular, bis sie abgewählt werden.
  Bestehende Tabellen ohne diesen Merker: nicht gewählte Dateifelder sind automatisch dabei (`DataForms::selected`).
  `Tables::validate` übergibt den aus der Zustellung abgeleiteten Upload-Schalter jetzt direkt an
  `DataForms::validateSettings` – ein angehaktes Dateifeld wird nie verworfen, egal welche Werte das Formular schickt.
  Einstellungen: Hinweis „Datei – nur bei Zustellung per E-Mail“ in der Auswahl, Warnung bei Zustellung „Im System“.
- **Neu: Erlaubte Dateitypen je Dateifeld** (Feld-Einstellung `accept`): PDF, Bilder (JPG, PNG, WebP), Word (DOCX),
  OpenDocument-Text (ODT) – Standard PDF + Bilder (wie bisher). DOCX/ODT nur in Eingangs-Tabellen (Zustellung per E-Mail),
  weil die Mediathek sie nicht speichert; Inhaltstabellen wählen PDF und/oder Bilder. Optional **Höchstgröße je Feld**
  (`max_mb`, höchstens die Grenze der Tabelle).
- **Prüfung am Inhalt** (`DataForms::sniff`): finfo und Dateiendung müssen passen; PDF-Kopf, `getimagesize`; DOCX/ODT als
  ZIP mit `[Content_Types].xml` bzw. `mimetype`, Makros (DOCM, `vbaProject.bin`, `Basic/`, `Scripts/`) abgelehnt. Ein
  umbenanntes Programm („rechnung.pdf“) wird abgelehnt. Der Anhang der E-Mail trägt den erkannten Typ.
- **Formular:** `accept` passend zu den erlaubten Typen, sichtbarer Hinweis unter dem Feld („PDF oder Bild (JPG, PNG,
  WebP), höchstens 5 MB.“, per `aria-describedby`), Größenprüfung schon bei der Auswahl (`dataform.js`). Kit praxis:
  `partials/form.php` stellt Dateifelder dar (`multipart/form-data`; `Core\Forms::fields` liefert `accept`, `file_hint`).
- Selbsttest `inbox:selftest`: Auswahl mit Dateifeld, neue Felder, ältere Auswahl, Dateitypen (PDF, umbenanntes Programm,
  DOCX, DOCM, ODT mit Makros, Bild), E-Mail mit gewählten Feldern + DOCX-Anhang, nur per E-Mail mit S/MIME.
- Handbuch „Daten → Formular“ und „Anfragen → Dateien annehmen“, Entwicklerhandbuch „Formulare“, `lang/en.php`.
- Möglicher nächster Schritt: mehrere Dateien je Feld.

### Formular (Datentabelle): Breite und Ausrichtung; Kit praxis: Block „Fließtext“, engere Text-/Formular-Abschnitte
- **Kern-Block „Formular (Datentabelle)“** (`app/Blocks/blocks.php`, `app/Blocks/data_form.php`): neue Optionen unter
  „Darstellung“ – **Breite** Textbreite (Standard; auch für bestehende Blöcke ohne Angabe: in der Spalte der Fließtexte) /
  Normal (schmales Formular wie bisher) / Volle Breite, **Ausrichtung** Linksbündig (Standard) / Mittig (Kopf, Button,
  Hinweis zentriert). Ausgabe als Klassen `dff-wrap--w-*`/`dff-wrap--a-*` und `data-width`/`data-align`, kit-unabhängige
  Grundregeln in `resources/css/_dataform-layout.css` (von `dataform.css` und den Kits essenz, fluid, glas, modern, nature
  eingebunden; Textspalte über `--dff-w-text`). Zentrierter Kopf erhält `sec-head--center` (nature: Dachzeile nicht seitlich).
- **Kit praxis:** Block „Fließtext (Rechtstexte)“ heißt jetzt **„Fließtext“** (Hilfetext: Stellenanzeigen, Erläuterungen,
  Rechtstexte), neue Option **Breite** Textbreite (Standard, unverändert) / Breit; Überschriften H2–H4, Listen und Links im
  Text feiner abgestimmt; Leerzeilen direkt vor Zwischenüberschriften entfallen (Abstand kommt von der Überschrift); leere
  Fließtext-Blöcke erzeugen für Besucher keinen leeren Abschnitt mehr.
- **Kit praxis – Abstände:** folgen Fließtext- und Formular-Abschnitte mit gleichem Hintergrund aufeinander, halbiert sich
  der Abstand (unten `--sec-y-s`, oben 0; nicht bei Trennlinie, Hintergrundbild oder eigenen Abstands-Einstellungen).
  Startseite und übrige Seiten unverändert.
- **Kit praxis – Formular-Stil:** Kopf mit Praxis-Überschriften, Felder 52 px, Hover/Fokus, Button zentriert beschriftet,
  Hinweis zur Verschlüsselung mit Punkt, Erfolgsmeldung; auf Bordeaux/Dunkel transparente Felder mit heller Schrift
  (vorher weiße Schrift auf weißen Feldern).
- Handbuch „Daten → Formular“, Entwicklerhandbuch „Formulare“, Blockbeschreibung im Praxis-Handbuch, `lang/en.php`.

### Seiten-Editor: Seitenleiste nur über „Bearbeiten“, „+ Block einfügen“ unten am Block (`resources/js/editor.js`)
- **Klick wählt nur aus:** Ein Klick/Tipp in einen Block öffnet die Seitenleiste nicht mehr, sondern markiert den Block
  (`.is-selected`: Leiste und Einfügen-Knopf bleiben sichtbar – auch auf Touch-Geräten). Texte bleiben direkt bearbeitbar.
  Die Seitenleiste öffnen „Bearbeiten“ (Tastatur: Tab + Enter), „Inhalte eingeben“ in leeren Blöcken, „Abschnitt &
  Navigation …“ und der Sprung `?edit=1#b-{id}` / `&block=`. Klick auf nicht direkt bearbeitbare Inhalte (Symbole,
  Bilder, Listen, zentral gepflegte Angaben) zeigt einen kurzen Hinweis an „Bearbeiten“. Knöpfe am Bild (Anpassen, Rahmen,
  Zuschneiden) und der Stift je Eintrag funktionieren unverändert.
- **„+ Block einfügen“ unten mittig an jedem Block** (auf der Kante zum nächsten; bis 600 px im Block): sichtbar bei Hover,
  Auswahl oder Tastaturfokus, sonst ohne Klickfläche; beim letzten Block Einfügen am Seitenende. Auswahl der Blocktypen
  mit Suche, ↑/↓, Enter und Esc (Fokus zurück), gleiche Typen wie „+“ von Editor.js.
- **Neue Blöcke:** Schreibmarke im ersten direkt bearbeitbaren Text; ohne solchen öffnet sich die Seitenleiste.
- Handbuch „Inhalte bearbeiten“, Entwicklerhandbuch „Editor“, Texte in `lang/en.php`.

### Markdown einfügen und importieren (`resources/js/_markdown.js`, Seiten-Editor)
- **Einfügen in Rich-Text-Felder:** eindeutig als Markdown erkennbarer reiner Text (Überschriften, Listen, Aufgabenlisten,
  Zitate, **fett**, *kursiv*, Links) wird beim Einfügen in Rich-Text umgewandelt – Hinweis „Als Text einfügen“ bzw.
  ⌘/Strg+Z macht es rückgängig; normale Sätze und formatierter Text (Word, Websites) bleiben wie bisher reiner Text.
- **Formatierungsleiste „⋯ → Markdown einfügen …“:** Dialog mit Textfeld und Vorschau, Einfügen an der Schreibmarke
  (in Inline-Feldern nur fett/kursiv/Link/Umbruch).
- **Werkzeugleiste „⋯ → Markdown importieren …“:** Text oder `.md`-Datei (FileReader, nichts wird hochgeladen), Vorschau,
  „Als ein Textblock“ oder „Bei jeder ##-Überschrift einen neuen Textblock“, Position wählbar; es entstehen Textblöcke des
  Kits (`richtext`, sonst `text`, sonst passender Block mit Rich-Text-Feld) als ungespeicherte Änderung.
- Ausgabe nur mit Tags der Rich-Text-Whitelist; Überschriften relativ ab H2; Bilder werden nicht eingebunden, sondern als
  Redaktionsnotiz `[# Bild: … #]` vermerkt; Tabellen bleiben Text mit Notiz; YAML-Vorspann wird entfernt.
- Dialoge modal mit Beschriftungen, Esc und Fokus-Rückgabe; hell/dunkel. Handbuch „Seiten bearbeiten → Markdown“,
  Entwicklerhandbuch „Editor → Rich-Text“.

### Seite „Nicht gefunden (404)“ pflegbar (`Core\NotFound`, Seiten → Sonderseiten)
- **404-Seite mit Blöcken** je Website und Sprache: Seite mit `type = template`, `template_for = @404` – bearbeitet im
  Frontend-Editor unter `/404?edit=1` (Entwurf, Veröffentlichen, Versionen, „Entwürfe“), nie unter eigener Adresse öffentlich
  (`/404` antwortet selbst mit 404, ohne Weiterleitung und 404-Protokoll), nicht in Menü, Sitemap, Suchindex, Link-Auswahl
  und Seiten-Auswahlfeldern.
- Bei **404 und 410** rendert `SiteController::error()` die veröffentlichte Seite der Sprache (Rückfall: Standardsprache) im
  normalen Layout – Status bleibt 404/410, `noindex` (`Seo::forError`), kein Seiten-Cache; angemeldet mit Werkzeugleiste und
  Entwurf. Ohne veröffentlichte Seite sowie bei 403/405/419/500 wie bisher `templates/error.php` des Kits.
- **Anlegen:** Seiten → Sonderseiten „404-Seite anlegen“ (bzw. „Übersetzung anlegen“) und Weiterleitungen → Nicht gefunden
  (404) „404-Seite anlegen/bearbeiten“; Startinhalte vom Kit (`theme.php → 'not_found_blocks'`), sonst ein Block
  „404-Vorschläge“. Konsole: `notfound:create [--lang=…] [--publish]`, `notfound:selftest`.
- **Kern-Block „404-Vorschläge“** (`not_found`): Dachzeile, Überschrift (H1), Text, „Vielleicht meinten Sie …“ (ähnliche Seiten und
  Einträge mit Detailseite zur aufgerufenen Adresse, Vorschlag des 404-Protokolls – `NotFound::suggestions()`),
  Suchfeld, Button zur Startseite, zweiter Button; `resources/css/notfound.css` (Variablen `--nf-*`). Kits überschreiben Felder
  und Ausgabe (Helfer `NotFound::boxes()`, `::path()`).
- **Kit praxis:** eigener Renderer (Überschrift mit Punkt, Telefonzeile aus den Praxisdaten) und Startinhalte wie `error.php`.
- `Pages::translate()` übernimmt `type`/`template_for` (Übersetzungen von Vorlagen bleiben Vorlagen).
- Handbuch: „Seiten verwalten → Seite „Nicht gefunden (404)““; Entwicklerhandbuch: Kits → Seite „Nicht gefunden (404)“, CLI.

### Entwürfe (`Core\Review\Drafts`, Verwaltung → Entwürfe)
- **Neue Übersicht** `/admin/entwuerfe` (Menüpunkt „Entwürfe“ mit Zähler): Seiten im Entwurf (neu bzw. offline), veröffentlichte
  Seiten mit unveröffentlichten Änderungen (blockweise verglichen – erneutes Speichern ohne Änderung zählt nicht) und Einträge
  eigener Datentabellen im Status „Entwurf“ – nur aktuelle Website, nach Rechten gefiltert (`pages.edit`/`pages.publish`,
  `data.edit`/`data.publish`/`data.delete` je Tabelle).
- Je Zeile: Art, Titel, Sprache, letzte Änderung (relativ) und wer, **Herkunft** (Content-Sync, KI, MCP, REST-API mit Token,
  externe Quelle – aus Revisionsvermerk bzw. `change_log`), Status „neu“/„geändert“/„offline“, **„vergessen?“** nach 14 Tagen.
- **Unterschiede** Entwurf ↔ veröffentlicht mit der Darstellung der Prüf-Ebene (neu: gemeinsame View `review/_diff.php`).
- **Veröffentlichen / Verwerfen** einzeln und gesammelt (Rückfrage im Seiten-Dialog); Seiten-Entwürfe bleiben als Version
  erhalten, verworfene Einträge 90 Tage in `draft_discards` („Zuletzt verworfen“ → Wiederherstellen als Entwurf).
- **Notiz und zuständige Person** je Entwurf (Tabelle `draft_notes`), Filter Alle / Meine / Zur Prüfung / Vergessen? /
  Content-Sync & KI; Hinweis auf offene Einreichungen unter „Eingereicht“.
- **Seitenbaum**: veröffentlichte Seiten mit offenem Entwurf zeigen „Entwurf offen“. **Übersicht**: „Liegengebliebene Entwürfe“
  und „Einträge im Entwurf“ führen zu Entwürfe → Vergessen?.
- Handbuch: neues Kapitel „Entwürfe prüfen und aufräumen“; Entwicklerhandbuch → Prüf-Ebene.

### Begriffe: Tabellen-Designer, Block-Designer, Musterseiten
- **Wortwahl** in Verwaltung, Handbüchern, Rechten/Funktionen und Übersetzungen: der Editor für Tabellen und Felder
  (Verwaltung → Daten, Recht `data.schema`) heißt **Tabellen-Designer** (EN „table designer“), der Editor für eigene
  Blöcke (Verwaltung → Blöcke, `Core\Blocks\Custom`) **Block-Designer** (EN „block designer“), der Editor für
  Wiederholungsregeln **Regel-Editor**. KLXM Studio wird nirgends mehr als „Baukasten“ bezeichnet.
- **Kits basis und modern**: Die Beispielseiten mit allen Blöcken heißen **Musterseiten** (`/musterseiten`, Sammlung
  „Musterseiten (Demo)“, Schlagwort `musterseiten-demo`, Detailseiten der Demo-Projekte unter `/muster-projekte/…`).
  `tools/demo.php --remove`/`--force` entfernt auch die früheren Namen (`/baukasten`, „Baukasten (Demo)“,
  `baukasten-demo`); bestehende Websites behalten ihre Seiten, bis sie neu eingespielt werden.
- **Style-Editor**: `design.sample` darf eine Liste von Pfaden sein (jeder Treffer steht als Musterseite oben in der Vorschau-Auswahl) –
  basis und modern erkennen so neue und ältere Installationen.
- **Bearbeiten-Modus**: Bild-Werkzeuge überdecken den Stift „Eintrag bearbeiten“ nicht mehr.

### Anfragen per E-Mail zustellen (`Core\Data\Delivery`, Funktion `requests.mail`)
- **Zustellung je Eingang** (Daten → Eingang → Einstellungen → „Zustellung der Anfragen“, Recht `requests.manage`):
  „Im System (verschlüsselt)“ (Standard, wie bisher), „Im System und per E-Mail“, „Nur per E-Mail – nicht im System
  speichern“. Im Modus „nur per E-Mail“ bleibt nichts vom Inhalt in der Datenbank – nur ein Zustellprotokoll in `inbox_log`
  (Zeit, Tabelle, Empfänger als HMAC-Hash, Status, Vorgangsnummer, Message-ID; ohne IP, nie Inhalte).
- **Nichts geht verloren**: Scheitert der Versand (kein Empfänger, kein TLS, Zertifikat abgelaufen, SMTP-Fehler, Testumgebung
  ohne Zustellung), wird die Anfrage verschlüsselt gesichert (gleiche Vorgangsnummer, markiert), die Administration gewarnt
  („Zustellung fehlgeschlagen – Anfrage verschlüsselt gesichert“ unter Anfragen und in den Einstellungen, inhaltsfreie E-Mail,
  Fehlerprotokoll). Erfolgsmeldung für Besucher nur, wenn Versand oder Sicherung geklappt hat.
- **E-Mail**: HTML + Text, archivtauglich (`app/Admin/views/mail/request.php`, im Kit überschreibbar), alle Felder in
  Formular-Reihenfolge, Eingangszeit, Formular, Vorgangsnummer; Dateien als Anhang bis zur Gesamtgrenze (darüber Hinweis);
  optional JSON/XML-Anhang zum Import (`klxm-studio-request` v1); Betreff mit Platzhaltern (Standard `[{form}] {ref}` ohne
  Inhalte); Reply-To = E-Mail-Feld der Anfrage (abschaltbar); Weiterleitung nach Auswahlfeld (z. B. „Rezept“ → rezept@…);
  bis 10 Empfänger. „Testmail senden“ mit erfundener Anfrage.
- **Sicherheit**: Inhalte nur über SMTP mit TLS (`require_tls` für STARTTLS; ohne Verschlüsselung kein Versand, sendmail =
  Warnung). **S/MIME** Ende-zu-Ende mit dem Zertifikat der Empfänger (PEM, bis 5 RSA-Zertifikate, Prüfung von Ablauf und
  Schlüsselverwendung, Warnung 30 Tage vorher, private Schlüssel werden abgelehnt): `Mailer::send(…, ['smime' => …])`
  verschlüsselt die ganze Nachricht inkl. Anhängen (`openssl_pkcs7_encrypt`, AES-256-CBC), keine Klartext-Vorschau im
  `mail_dump`. PGP bewusst nicht (keine MIT-kompatible Umsetzung ohne externes Programm).
- **Dateifelder in Eingängen** – nur bei Zustellung per E-Mail: als Anhang bzw. versiegelt im Payload (Download nach dem
  Entschlüsseln unter Anfragen), nie in der Mediathek.
- **Buchungen** und andere Eingänge einer Erweiterung (`Extension::inbox`) brauchen gespeicherte Einträge: „nur per E-Mail“
  ist dort gesperrt, „System und E-Mail“ schickt nach dem `store`-Callback zusätzlich die Inhalts-E-Mail.
- `Mailer`: Optionen `reply_to`, `message_id`, `smime`, `require_tls`; `Mailer::$outcome` (sent|redirected|dumped|logged|failed).
  API `GET /data`: Eingänge mit `delivery`. Selbsttest `php bin/console inbox:selftest` (alle Modi, Rückfall, S/MIME mit
  selbst signiertem Test-Zertifikat inkl. Entschlüsseln, keine Inhalte in Datenbank/Protokoll, Weiterleitung, Anhang-Grenzen).

### Bild anpassen: Schärfe / Unschärfe (`Core\ImageFx`)
- **Neuer Regler „Schärfe“** von −100 (weicher) über 0 bis +100 (schärfer) im Dialog „Bild anpassen“ – in der Mediathek
  (global je Bild, `media.adjust`) und je Einbindung (`data._fx`), mit Live-Vorschau und „Schärfe zurücksetzen“.
  Speicherformat `sharp-60` / `sharp40` (10er-Schritte, nach Effekt und s/b/c), API/MCP auch `{"sharpness": 40}`.
- Klassen `ifx-sharp-m1…m10` / `ifx-sharp-p1…p10` am Ende derselben Filterkette; die SVG-Filter (Gauß-Unschärfe bis 4 px
  mit sauberem Rand, 3×3-Schärfekern) stehen in einem versteckten `<svg id="ifx-defs">`, das nur Seiten mit
  geschärften/weichgezeichneten Bildern bekommen (`ImageFx::inject()`) – CSP-konform, geprüft in Chromium und WebKit.
- Nur Darstellung im Browser, keine echte Bildbearbeitung; Kontrastmodus (`forced-colors`) zeigt Bilder ohne Filter.
  Selbsttest `blocks:selftest` um Format, Klassen und bedarfsweises Einbinden erweitert.

### Kit „praxis“: Budget, Platzhalter als Notizen, Barrierefreiheit (Handoff Praxis Moers)
- **Karten im Zwei-Klick-Modus** (`Core\Maps`): Einstellung `sys.map_click` (Grundeinstellungen → Karten; leer = Vorgabe des
  Kits `project.map.click`) – Hinweis mit Link zur Datenschutzerklärung und Knopf „Karte anzeigen“, MapLibre und Kacheln
  (weiter über den eigenen Proxy) erst nach Klick. Kits mit `'map_loader' => 'kit'` laden `map.mjs` selbst beim Klick.
- **Redaktionsnotizen auf allen öffentlichen Seiten entfernt**: `SiteController::respond()` filtert `[# … #]` auch auf
  Formularseiten `/anfrage/…`, Datenformularen, Suche, Fehler- und Wartungsseiten (dort stammen Notizen aus Einstellungen).
- `resources/css/data.css` in `_data-list.css` und `_data-fields.css` geteilt (gleiche Ausgabe); ein Kit kann die Teile
  einzeln laden – praxis lädt „Datensatz-Felder“ nur auf Detailseiten (`css/data-fields.css`).
- **praxis**: Startseite CSS 48 → 35 KB, JS 11,5 → 6,4 KB (minifiziert): Formulare der Kontaktkarte (`form.js`,
  `css/form.css`, Datenschutz-Dialog) laden erst beim Umdrehen, Mobilmenü-Stile beim ersten Öffnen (ohne JS per
  `<noscript>`), Suche in `css/hsearch.css` nur mit Funktion „search“, Karten-Modul erst beim Klick.
- **praxis**: Fehlende Praxisdaten erscheinen als Redaktionsnotiz statt als sichtbarer „[Platzhalter]“ (`praxis_note()`);
  Telefon-/E-Mail-Links nur mit Wert (kein leeres `tel:`/`mailto:`), leere Ärzt:innen-Karten und Akkordeon-Einträge nur im
  Bearbeiten-Modus. Pflichtfelder der Formulare mit Sternchen und Legende „* Pflichtfeld“.
- **praxis**: H1 auf Seiten ohne Hero (Übersichten, Rechtstexte, Detailseiten) als Seitentitel für Screenreader; die H1 im
  Hero bleibt beim Themenwechsel lesbar. Kein Layout-Sprung durch den Öffnungsstatus (Platz reserviert, CLS 0); Honeypot
  ohne −10 000-px-Versatz (auf der gespiegelten Kartenrückseite machte er die Seite mobil 10 000 px breit).

### Anmeldung: „Passwort vergessen“ (`Core\PasswordReset`)
- **Link auf der Anmeldeseite** → `/admin/passwort-vergessen`: E-Mail-Adresse eingeben, Antwort immer gleich („Wenn ein Konto
  zu dieser Adresse existiert, haben wir Ihnen einen Link geschickt.“) – unbekannte, gesperrte und Schatten-Konten, Begrenzung
  je Adresse und fehlender E-Mail-Versand verraten nichts; die E-Mail geht nach der Antwort hinaus (gleiche Antwortzeit).
- **Einmal-Link** (32 Zufallsbytes, nur als SHA-256 an Zweck und Website gebunden), 60 Minuten gültig (`'password_reset_minutes'`),
  nur der neueste gilt; Link-Adresse nie aus einem beliebigen Host-Header. Seite `/admin/passwort/{token}`: GET zeigt das
  Formular, POST setzt das Passwort (Richtlinie, Anzeigen/Verbergen, Stärke-Hinweis).
- Danach enden alle Sitzungen (`auth_ver`), Hinweis-E-Mail, Protokoll. **Der zweite Faktor wird nicht umgangen:** keine
  Anmeldung nach dem Zurücksetzen, TOTP und Passkeys bleiben. Konten nur mit Passkey können so ein Passwort ergänzen.
- Netzwerk-Konten setzen auf der Netzwerk-Website zurück; andere Websites schicken einen Hinweis dorthin (ohne Token).
  Ohne E-Mail-Versand (`Mailer::ready()`): gleiche Antwort, Warnung im Netzwerk-Protokoll. Begrenzung 5/15 min je Anschluss,
  3/h je Adresse. `user:password` bleibt; `account:selftest` prüft Tokens, Ablauf, keine Rückschlüsse und die 2FA-Pflicht.

### Netzwerk: weitere Netzwerk-Admins einladen
- **„+ Netzwerk-Admin einladen“** in der Netzwerk-Übersicht (E-Mail, Name, Nachricht) statt Startpasswort; die E-Mail im Stil
  der Einladungen nennt deutlich „Zugriff auf ALLE Websites“ und die Pflicht zur Zwei-Faktor-Anmeldung. Link 7 Tage, einmal.
- Annehmen mit Passkey und/oder Passwort, danach sofort die Pflicht-Einrichtung des zweiten Faktors (Passkey erfüllt sie),
  dann die Netzwerk-Übersicht. Offene Einladungen in der Kontenliste mit „Erneut senden“ und „Zurückziehen“.
- Nur aktive Netzwerk-Konten auf der Netzwerk-Website laden ein (einladendes Konto gesperrt → Link ungültig); nie über
  Benutzer & Rollen. Alles im Netzwerk-Protokoll (`network.invite*`). „Mit Startpasswort anlegen“ bleibt zugeklappt für
  Installationen ohne E-Mail. CLI `network:user <email> --invite [--name=…]`; `invites:selftest` prüft den Umfang.

### Anmeldung: Farbhimmel nach Tageszeit (`Core\AuthScreen`)
- **Neuer Rahmen für alle Seiten vor der Anmeldung** (Anmelden, Passkey, Zwei-Faktor/Wiederherstellungscode, Einladung,
  Links aus E-Mails, Einrichtung): ruhig bewegter Farbhimmel aus drei weichen Farbflächen (reines CSS, nur `transform`),
  Karte aus Milchglas, oben App-Icon und Name der Website, auf der Anmeldung ein kurzer Gruß („Guten Morgen“, „Schönen Abend“ …).
- **Tageszeit** serverseitig in der Zeitzone der Website: `auth--morning|day|evening|night` + `is-weekend` am `<body>` –
  morgens warm und hell, mittags hell, abends warm/violett, nachts tiefblau mit ein paar Sternen (immer dunkel). Ohne JavaScript.
- **Markenfarbe:** Farbton und Sättigung der App-Designfarbe (`sys.pwa_theme`, sonst Kit) fließen in die Farbflächen –
  erzeugte Datei `media/auth/auth-<hash>.css` (strenge CSP); Rückfall KLXM-Palette. Anpassbar über `--auth-*`-Variablen.
- Hell/Dunkel nach Gerät, `prefers-reduced-motion` (stiller Verlauf), `prefers-reduced-transparency`, `forced-colors`,
  deckende Karte ohne `backdrop-filter`; Fehler per `aria-describedby` an den Feldern (Anmelden, Zwei-Faktor, Einrichtung).
  `resources/css/auth.css` ≈ 7,5 KB (gzip ≈ 2 KB), nur auf diesen Seiten. Testen: `?tod=…` (nur debug/localhost).

### Bearbeiten-Modus: Kopf des Kits bleibt an seinem Platz
- **Kopfmenü verrutschte:** Die Werkzeugleiste schob jeden markierten Kit-Kopf (`data-cms-sticky`, `.cms-bar-host ~ .site-header`)
  per `top` um ihre Höhe nach unten – auch Köpfe, die das Kit im Bearbeiten nicht kleben lässt (klxm, klxm-agentur, basis
  800–960 px, editorial, essenz/glas/nature/starter ohne „Kopf mitlaufen lassen“). Diese standen dann 52–54 px tiefer und
  überdeckten den Anfang der Seite. Jetzt markiert `_shadow.js` den Kopf (`data-cms-header`) und nur solange er wirklich
  klebt/fest steht `data-cms-pinned` – nur dann gilt der Versatz. Kits ohne Markierung (modern, fluid) werden ebenfalls erkannt.
- **Ebenen:** Kopf des Kits samt Menüs (Untermenüs, Mega-Menü) liegt im Bearbeiten über Block-Leisten und „+“; geöffnete
  Editor.js-Menüs darüber, Werkzeugleiste ganz oben. Die Knöpfe am Bild („Anpassen“, „Rahmen“, „Zuschneiden“) rücken unter
  einen klebenden Kopf statt ihn zu verdecken.
- `--cms-toolbar-h` am `<html>` (sichtbare Unterkante der Leiste) für eigene Kit-Regeln, z. B. `top: var(--cms-toolbar-h, 0)`.

### Anmeldedaten selbst ändern (`Core\EmailChange`)
- **Konto → Anmeldedaten:** Übersicht (E-Mail-Adresse, Passwort festgelegt?, Passkeys, Zwei-Faktor) mit den Aktionen
  „E-Mail-Adresse ändern“ und „Passwort ändern/festlegen“; Name unter „Profil“ getrennt vom Passwort.
- **E-Mail-Adresse mit Bestätigung:** Anfrage mit aktuellem Passwort (Konten ohne Passwort: Passkey-Bestätigung bzw. frische
  Anmeldung, 15 min). Link an die neue Adresse (32 Zufallsbytes, nur SHA-256 mit Zweck und Website gespeichert, `hash_equals`,
  24 h, einmalig, `email_change_hours`), Hinweis mit „Das war ich nicht – Änderung abbrechen“ an die bisherige Adresse
  (bricht ab und beendet alle Sitzungen). Wirksam erst mit dem Link (Token allein genügt, Änderung muss noch gelten, Adresse wird
  erneut geprüft); danach `auth_ver` + 1 (eigene Sitzung bleibt), Protokoll `user.email`, Bestätigung an beide Adressen.
  Offene Änderung im Konto mit „Erneut senden“ und „Abbrechen“. Vergebene Adresse: gleiche Antwort, Hinweis an die Inhaberin,
  kein Bestätigungslink (keine Rückschlüsse auf Konten). Link-Seiten `/admin/konto/email/{token}` und
  `/admin/konto/email-abbrechen/{token}`: GET ändert nichts, POST mit CSRF, `Referrer-Policy: no-referrer`, Begrenzung je IP.
  Ohne zugestellte E-Mail ein deutlicher Hinweis – ohne Bestätigung keine Änderung. Tabelle `user_email_changes`.
- Passkeys bleiben gültig (an die Konto-ID gebunden, `Passkeys::userHandle`).
- **Passwort:** Konten nur mit Passkey (z. B. aus Einladungen) legen nach Passkey-Bestätigung ein erstes Passwort fest;
  Fehlversuche beim aktuellen Passwort begrenzt; Hinweis-E-Mail nach jeder Passwortänderung (auch `user:password`).
- **Mit Passkey bestätigen** statt Passwort (`/admin/account/reauth/passkey`, Benutzerprüfung verlangt) – auch beim Hinzufügen
  und Löschen von Passkeys; vorher waren Konten ohne Passwort nach 15 Minuten ausgesperrt.
- **Benutzer & Rollen → „E-Mail ändern“:** direkt ohne Bestätigung, Hinweis an beide Adressen, Sitzungen enden, Protokoll
  `user.email-admin`; nicht für das eigene Konto, Netzwerk-Konten oder Rollen mit mehr Rechten. CLI `user:email <alt> <neu>`.
- **Netzwerk-Konten:** auf anderen Websites nur Hinweis mit Link zur Netzwerk-Verwaltung; auf der Netzwerk-Website gleicher Ablauf,
  Schatten-Konten werden sofort nachgezogen (`Network::syncShadowEmail`), sonst bei der nächsten Anmeldung.
- E-Mail-Vorlage `mail/account(.txt).php` (Layout der Einladung, hell/dunkel, im Kit überschreibbar). Öffentliche Seiten ohne
  `user` (Anmeldung, Einladung, Links) erscheinen jetzt auch für Angemeldete ohne Seitenleiste. Selbsttest `account:selftest`.

### Redaktionsnotizen `[# … #]` (`Core\EditorNotes`)
- Versteckte Hinweise der Redaktion in jedem Text: `[# bitte ergänzen: Seminartermine #]` – mehrzeilig, mehrere je Text, auch in
  Rich Text, verschachtelten Blockdaten und Einträgen von Datentabellen. In `<code>`/`<pre>` und `` `Backticks` `` bleibt die
  Schreibweise stehen (Anleitungen).
- **Öffentlich nie sichtbar**, ohne Zutun der Kits: Blockdaten vor dem Rendern (`Theme::makeBlock`), ganze Seite vor dem
  Seiten-Cache (Einträge, Meta-/OG-Angaben, JSON-LD, Daten-Skripte), `previewHtml()` (Freigabe), Suchindex (`Documents::doc`,
  `Text::plain`) und damit Besucher-Chat, KI-Kontexte (Assist, Assistent), iCal-Abos, öffentliche API-Lesezugriffe
  (veröffentlichte Fassung, `publicInfo`). Entwürfe, Verwaltung, API/MCP-Entwurfszugriffe und Content-Sync behalten sie.
- **Redaktion:** im Bearbeitungsmodus und in der Entwurfsansicht als Hinweis „Notiz: …“ (`.cms-note`, `editor.css`, CSP-sicher,
  `role="note"`, nicht als HTML bearbeitbar); Editor und Einträge-Bearbeitung speichern ihn wieder als `[# … #]`.
- **Übersicht „Was ist zu tun?“:** Notizen neben den [Platzhaltern] in derselben Liste, gekennzeichnet „Notiz“, aus dem Entwurf der
  Seiten und aus Einträgen („Eintrag bearbeiten“), ohne „Ist gewollt“. Netzwerk-Übersicht zählt Notizen getrennt.
- `php bin/console notes:convert [--site=…] [--dry-run]`: alte Marker (`[bitte ergänzen: …]`, `[bitte prüfen: …]`,
  `[bitte rechtlich prüfen: …]`, `[bitte ergänzen nach Prüfung: …]`, `[Platzhalter]`, „NEU (bitte prüfen)“) in Seiten
  (veröffentlicht und Entwurf, an Ort und Stelle, Version „Notizen umgestellt“, nichts wird veröffentlicht) und Einträgen umstellen.
  Selbsttest `notes:selftest`. API für Erweiterungen: `EditorNotes::strip()`, `stripData()`, `publicHtml()`, `find()`, `has()`.

### Korrekturen
- Kalender/Nächste Termine: Akzentflächen ohne Kit-Farben kontrastsicher (getönte Fläche + Textfarbe statt currentColor + Canvas);
  `--cal-accent`/`--cal-accent-ink` weiter als Paar; Ort in „Nächste Termine“ nicht doppelt gedämpft.
- Slider: Region heißt „Bildfolge: {Überschrift}“ (vorher gleicher Name wie der Abschnitt, axe landmark-unique).
- Datenliste (Tabelle): Spaltenkopf „Termin“ statt „_when“.
- Kern-Blöcke: `*Betonung*` in Überschriften wie im Kit – neuer Helfer `emphasis()` (nutzt `{kit}_title()`), `strip_emphasis()`.
- Bild im Rahmen je Feldpfad statt je Medien-ID: dasselbe Bild kann in einem Block verschieden eingepasst sein
  (`img(…, ['path' => …])`, sonst n-tes Vorkommen); Editor stellt nur die angeklickte Stelle ein.
- Content-Sync: Export mit Pool-Medien brach ab (`Media::dir()` statt `Media::path()`); Pool-Verweise gehen als Verweis mit
  (live `MediaPools::mirror`, Kopie nur ohne Pool).

### Kits unter `kits/` statt `themes/` – zentrale Pfad-API `Core\Kit`
- Ordner `themes/` → `kits/`, `public/themes/` → `public/kits/`. Alle Pfade über `Core\Kit`: `Kit::dir($name)`,
  `Kit::publicDir($name)`, `Kit::url($name, $pfad)`, `Kit::all()`, `Kit::definitionFile()`, `Kit::fragment($name)` –
  genutzt von `Core\Theme`, Netzwerk-Kennzahlen, Suchseite, Kit-Layouts, `bin/console` (`kit:list` zeigt den Ordner,
  `kit:create` legt unter `kits/` an), `tools/build.mjs`, `tools/licenses.mjs`, Service Worker, reservierten Adressen.
- **Rückfall:** Kits unter `themes/{name}` und Assets unter `public/themes/{name}` (ältere Installationen, Kits von Dritten)
  werden weiter erkannt; alte Adressen `/themes/…` leitet `public/index.php` mit 301 auf `/kits/…` um (ohne App-Start).
  nginx: `location ^~ /themes/ { try_files $uri /index.php$is_args$args; }` (Installationsanleitung).
- **Aliase:** `kit.php` statt `theme.php`, Konfiguration `'kit'`/`'kits'` neben `'theme'`/`'themes'`, `app()->kit`.
  Unverändert aus Kompatibilitätsgründen: `theme.php`, `Core\Theme`, `app()->theme`, `sys.theme`, API-/MCP-Feld `theme`, `theme:*`.
- Umstellung bestehender Server ohne Releases: `deploy/migrate-kits.sh <ziel>` (Sicherung, Verschieben, Übergangs-Links,
  `cache:clear --all`, `health`); mit `deploy/deploy.sh` ist nichts zu tun.

### Kern-Fragmente statt Kopien in jedem Kit (`Core\Fragments`, `app/Views/fragments/`)
- Kits bleiben eigenständige Projekte ohne Vererbung; zentrale Bausteine liegen einmal im Kern (wie REDAXO-Fragmente).
- **Nur Kern:** `video-embed` (2-Klick-Video inkl. Hinweis, Datenschutz-Link, „künftig direkt laden“, Skript
  `resources/js/embed.js` mit `window.cmsConsent`/`cms:consent`), `editor`, `toolbar`. Kit-Dateien dafür werden ignoriert
  (Entwicklermodus: Warnung); Gestaltung nur per CSS (`.vembed`, `.vembed__gate`, `.vembed__info`, `.vembed__row`, `.vembed__play` …).
  Die 2-Klick-Hülle entspricht exakt dem Stand aus 0e01716 (Screenshots aller 9 Kits pixelgleich).
- **Überschreibbar:** `brand`, `langswitch`, `hours`, `legal`, `cookie-settings`, `breadcrumb`, `pagination`, `search-form`,
  `header-actions`. Suchreihenfolge `project/overrides/kits/{kit}/fragments` → `kits/{kit}/fragments` →
  `kits/{kit}/templates/partials` → `app/Views/fragments`. Optionen je Kit in `theme.php → 'fragments'`.
- Überschriebene Kern-Fragmente: Prüfsumme des Originals wird gemerkt (`storage/fragments.json`); ändert es sich, Hinweis in
  der Übersicht (Technik & Betrieb) und in `health`; `php bin/console fragments:list [--all] [--accept]`.
- Entwicklermodus (`debug`): HTML-Kommentar mit der Herkunft jedes Fragments (Projekt, Kit, Kern).
- Zentrale Helfer `privacy_url()`, `legal_links()`, `org_name()`, `fragment()` (`Core\Legal`) statt `{kit}_privacy_url()`.
- Aus den Kits gelöscht: `video-embed.php` (9), `editor.php` (9), `toolbar.php` (8), `brand.php` (7), `langswitch.php` (7),
  `hours.php` (6), `js/video.js` (6), praxis `js/embed.js`, Video-Teil von basis/editorial `js/blocks.js`.

### Formulare im Dunkelmodus lesbar
- `resources/css/dataform.css` folgt dem Farbschema des Kits (Canvas/CanvasText, Fehlerfarben per `light-dark()`); hell
  unverändert. Kits ohne eigene `css/dataform.css` (z. B. eigene Kits) brauchen keine Umgehung mehr.

### Mediathek: „Importieren aus …“ für Erweiterungen
- Neuer Browser-Haken `CMSMedia.extend({ sources(finder) })`: Erweiterungen tragen Quellen in den Knopf „Importieren aus …“
  neben „Hochladen“ ein – in der Mediathek und im Auswahldialog der Bild-/Datei-Felder (Verwaltung und Bearbeiten-Modus der
  Website), nur mit Schreibrecht am aktuellen Ort (Website bzw. Pool). Knopf mit Menü, Tastatur wie das Kontextmenü; der
  Knopf bleibt beim Neuladen der Liste erhalten (Fokus). Doku: Technik → Funktionen & Erweiterungen → Mediathek im Browser.
- Genutzt von der Erweiterung `assets_connect` (Nextcloud, Pexels, Pixabay – Paket `klxm/studio-assets-connect`).

### Haken für Erweiterungen: Eingänge und eigene Formulare (z. B. Buchungskalender)
- `Extension::inbox()` – eigene Status je Eingang (Beschriftung, Knopf, Ton, „done“ für die Aufbewahrung, „manual“),
  Zusatzzeile je Anfrage, Prüfung vor Statuswechseln (Verwaltung, API, MCP) in derselben Transaktion, `direct_form => false`.
  Ereignisse `inbox.status` und `inbox.deleted` (auch beim automatischen Löschen). Anfragen-Ansicht und API folgen dem Status-Satz.
- `DataForms::render/submit` für Formulare von Erweiterungen: `action`, `prepend`, `hidden`, `fields_legend`, `server_message`,
  `check`, `store`, `notify => false`; `dataform.js` meldet `dff:sent`. `Core\Mailer` kann kleine Anhänge (`attach`, z. B. .ics).
- Doku: Entwurf einer zentralen Zahlungs-Erweiterung `payments` (Technik → Funktionen & Erweiterungen → Zahlungen).
- Genutzt von der Erweiterung `booking` (Buchungskalender, Paket `klxm/studio-booking`).

### Kern-Block „Partner & Logos“ (alle Kits)
- Neuer Kern-Block `partners` für Logos von Partnern, Kunden oder Förderern – jedes Kit bekommt ihn automatisch
  (`app/Blocks/blocks.php`, Renderer `app/Blocks/partners.php`, abschaltbar mit `core_blocks => false`, überschreibbar je Kit).
- **Optisch gleich groß:** Logos erhalten in einer einheitlichen Kachel (3:2, 1:1 oder 2:1) dieselbe Fläche statt derselben
  Höhe – serverseitig aus Breite/Höhe berechnet (`Core\Blocks\PartnerLogos::width()`), ausgegeben als Klasse `pl-w-{n}`
  (keine Inline-Styles), Grenzen volle Breite und 90 % der Höhe; Logogröße klein/mittel/groß und Feinjustierung je Logo.
- **Quellen:** Logos im Block pflegen (Logo inkl. SVG, Name = Alternativtext, Kurzinfo, Link mit Beschriftung, Kategorie)
  oder aus einer Datentabelle mit Feldzuordnung (Logo, Name, Kurzinfo, Link, Kategorie, „einfarbig“), Filter und Anzahl;
  nur veröffentlichte Einträge. Feldtyp `datafield` kennt dafür `'types' => [...]` (auch Bild-, Text- und Rich-Text-Felder).
- **Sortierung:** wie angelegt, Name A–Z, Kategorie + Name (optional mit Überschrift je Kategorie), Tabellenfeld auf-/absteigend,
  zufällig je Seitenaufruf.
- **Darstellung:** 3–6 Spalten (Tablet 3, Smartphone 2), Kachel dezent/immer hell/ohne, Graustufen mit Farbe bei Maus/Fokus,
  einfarbige Logos auf dunklen Abschnitten und im Dunkelschema hell (gemessene Hintergrundhelligkeit).
- **Details erst nach Klick:** Logo = Schaltfläche mit `aria-expanded`/`aria-controls`, Angaben unter der Reihe (eine offen)
  oder im Dialog; Esc schließt, der Fokus kehrt zum Logo zurück; ohne JavaScript alle Angaben als Liste. Skript
  `js/partners.mjs` (ca. 3 KB) und `css/partners.css` nur auf Seiten mit dem Block; Bilder mit `loading="lazy"`,
  Breite/Höhe, kein Layoutsprung. Farben je Kit über `--partners-*` bzw. die Kit-Variablen (Doku: Technik → Kits).
- Selbsttest `blocks:selftest` prüft die Flächen-Normalisierung und die Sortierung.

### Bild im Rahmen: füllen, einpassen, Originalformat
- Je Einbindung (Knopf **Rahmen** am Bild im Editor, **Rahmen …** am Bild-Feld) oder als Standard des Bildes (Mediathek):
  **Füllen (zuschneiden)** wie bisher, **Einpassen** (ganzes Bild, Hintergrund transparent, Farbe des Kits, eigene Farbe
  oder **unscharf** aus der kleinsten Bildgröße) oder **Originalformat** (Rahmen im Seitenverhältnis des Bildes).
  Dialog mit Vorschau im Rahmen der Stelle.
- Gespeichert als `data._fit` je Feldpfad (wie `_fx`) bzw. Spalte `media.fit` (additiv); Format `contain blur`,
  `contain #1e2638`, `contain kit:surface`, `original`, `cover` (`Core\ImageFit`).
- Automatisch: **SVG und PNG-Logos mit transparentem Rand** werden in festen Rahmen eingepasst statt beschnitten (auch in
  Galerie-Rastern), außer es gibt einen eigenen Zuschnitt für das Format.
- Für alle Kits ohne Kit-Änderung: Klassen am `<picture>` (`img-fit`, `img-fit--contain|original|blur|auto`), Kern-CSS
  `image-fit.css` nur auf Seiten mit solchen Bildern, Variablen `--img-fit-bg`, `--img-fit-src`, `--img-fit-blur`; Werte je
  Bild ohne Inline-Styles (kleine erzeugte CSS-Datei, strenge CSP). Getestet mit basis, klxm, klxm-agentur.
- Handbuch (Bilder & Dateien → „Bild im Rahmen“), Entwicklerhandbuch (Medien), 9 neue Selbsttests in `blocks:selftest`.

### Erweiterungs-Haken für Seiten: Seitenbaum, Seiteneinstellungen, Werkzeugleiste, Entwurfs-Vorschau
- `Extension::pageList(fn($page))`: Hinweise in der Spalte „Status“ des Seitenbaums und Einträge im Kontextmenü.
- `Extension::pagePanel(fn($page))`: eigene Karten in der Seitenleiste der Seiteneinstellungen.
- `Extension::toolbar(fn($bar))`: Einträge im Menü „⋯“ der Werkzeugleiste (Link oder Button mit `data-…`), Skripte nach der
  Leiste (nur `'self'`) und ein Zusatz im Dialog „Änderungen jetzt veröffentlichen?“.
- `SiteController::previewHtml($page, true)` rendert den aktuellen Entwurf im Kit-Layout (ohne Werkzeugleiste, ohne
  Seiten-Cache, noindex). Genutzt von der Erweiterung „Entwurf teilen & freigeben“ (`klxm/studio-freigabe`).

### SVG-Grafiken in der Mediathek (bereinigt und optimiert)
- **SVG-Upload** (Verwaltung, REST, MCP, `content:import`) nur über den eigenen Bereiniger `Core\Svg` (Positivliste auf
  DOMDocument, keine GPL-Bibliothek): Skripte, Ereignisse (`on…`), `foreignObject`/`iframe`/`embed`/`object`,
  `image`/`feImage`, SMIL-Animationen, externe Verweise (`href` nur `#id`, `url()` nur `url(#id)`), `@import`,
  `expression()`, `behavior`, `-moz-binding`, Kommentare, Processing Instructions, DOCTYPE/Entities, Metadaten und
  Editor-Daten (Inkscape, Illustrator, Figma, Sketch, Serif) fallen weg. Gespeichert wird nur das Ergebnis.
- **Abgelehnt** mit klarer Meldung: eingebettete Pixelbilder (z. B. „Bildschirmfoto … .svg“ mit `data:image/png`) und
  eingebettete Schriften, Dateien über 2 MB, Entity- und use-Bomben, ungültige oder nach dem Bereinigen leere Dateien.
- **Optimiert:** ungenutzte ids/Definitionen, leere Gruppen, Leerraum, Zahlen in Pfaden/Koordinaten auf 3 Nachkommastellen;
  title/desc/aria bleiben. Upload-Meldung „SVG bereinigt und optimiert: 48 KB → 12 KB, 3 unsichere Elemente entfernt“.
- Maße aus der viewBox, keine Größen: `Media::url()` liefert immer die SVG. Kein Fokuspunkt, keine Zuschnitte, kein
  Bildeditor; Alt-Text-Regeln wie bei Bildern; kein `og:image` aus SVG. App-Icon aus SVG nur mit Imagick (sonst Hinweis).
- Abschaltbar als Funktion `media.svg` („SVG-Grafiken hochladen“, Standard an). Direkt aufgerufene SVGs: `.htaccess` mit
  CSP (`sandbox`) und `nosniff` im Medienordner (Apache), nginx-Zeile im Handbuch (Technik → Medien, Installation).
- Selbsttest `php bin/console svg:selftest` (Schadcode-Proben, Exporte aus Illustrator/Inkscape/Figma).

### Videos (YouTube/Vimeo): 2-Klick-Hinweis immer vollständig lesbar
- In allen Kits steht der Datenschutzhinweis im Fluss vor der Schaltfläche; der Kasten wächst mit (Seitenverhältnis als
  Mindesthöhe, `overflow:clip`), statt Text abzuschneiden. Hinweis, „künftig direkt laden“ und „Auf … ansehen“ werden bei
  schmalen Spalten nicht mehr ausgeblendet (vorher: Fluid/Modern nur noch Play-Button). Schaltfläche sichtbar
  „YouTube-Video laden“ statt „Video abspielen“.
- Erweiterung consent_kit 1.0.1: Korrekturen aus FriendsOfREDAXO/consent_kit 1.0.0 übernommen (Platzhalter auf schmalen
  Schirmen, fehlende Dienste nicht mehr ladbar, Links im Platzhaltertext, `cmsConsent.accept()`).

### Videos als „dekorativ (ohne Aussage)“ markieren
- Das Merkmal `media.decorative` gibt es jetzt auch für **Videos** (stumme Hintergrund-Schleifen, Bühnen-Clips,
  Stimmungs-Animationen): Checkbox beim Hochladen, rechts in der Mediathek und unter „Alle Details“ mit Hinweis
  „Hintergrund- oder Stimmungsvideo ohne Informationsgehalt – braucht keine Untertitel und wird für Screenreader ausgeblendet.“
- Dekorative Videos zählen nicht mehr als „Videos ohne Untertitel“ (Prüf-Filter `nocaptions` in Mediathek, Übersicht,
  KLXM AI → Untertitel, API/MCP); der Hinweis „Noch keine Untertitel“ entfällt.
- Website: `MediaTracks::player()` gibt dekorative Videos als `MediaTracks::decorativeVideo()` aus – `aria-hidden="true"`,
  `tabindex="-1"`, ohne `controls`/Spuren, stumme Schleife nur sichtbar und ohne „Bewegung reduzieren“, beschriftete
  Pause-Schaltfläche (WCAG 2.2.2). `['controls' => true]` erzwingt den normalen Player. Neu: `MediaTracks::isDecorative($m)`.
- Kits mit eigenem Video-Markup (`partials/video-embed.php`) können `$m['decorative']` auswerten – beschrieben im
  Entwicklerhandbuch (Medien → Untertitel).

### Bild bearbeiten: Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren
- **Zerstörungsfreier Bildeditor** in der Mediathek (Alle Details → Werkzeugleiste, Rechtsklick „Bild bearbeiten …“,
  `Core\ImageEdit`, `resources/js/_imageedit.js`): Zuschneiden mit Seitenverhältnissen (frei, 1:1, 4:3, 3:2, 16:9, 16:10,
  4:5, 9:16), Drehen in 90°-Schritten und frei (−45° bis +45°, 0,1°) mit Zuschnitt aufs größte einbeschriebene Rechteck
  oder Füllfarbe, Spiegeln, Ausrichten mit Raster und „Horizont ziehen“, Entzerren über vier Eckpunkte. Live-Vorschau im
  Canvas, Vorher/Nachher, Zurücksetzen je Werkzeug und „Alles zurücksetzen“; Tastatur (Pfeiltasten für Ecken, Ausschnitt
  und Winkel), Anfasser 32 px, hell/dunkel, Tablet.
- **Original bleibt unverändert:** Parameter in `media.edit_json` (neue Spalte, additiv), daraus bearbeitete Fassung und
  neue Größen mit neuem Namen (Cache-Busting), Seiten-Cache wird geleert. Reihenfolge: EXIF → Entzerren → Spiegeln/Drehen
  → Zuschnitt → Größen → CSS-Anpassung. Zuschnitte je Format arbeiten danach auf dem bearbeiteten Bild.
- **Entzerren** mit Imagick (`distortImage`, Perspektive), sonst GD (eigene Projektion, bis 2400 px, Zeitlimit);
  Arbeitskopie passend zum Speicherlimit. Pool-Dateien werden im Pool bearbeitet („Wirkt auf alle Websites, die dieses
  Bild nutzen“). SVG, GIF und Videos: Werkzeuge gesperrt mit Hinweis.
- EXIF-Ausrichtung beim Hochladen jetzt für alle acht Werte (auch gespiegelte 2, 4, 5, 7).
- Endpunkt `POST /admin/api/media/{id}/edit`, Konsole `media:selftest` (einbeschriebenes Rechteck, Entzerren, Format,
  Reihenfolge, GD), Handbuch „Bilder & Dateien → Bild bearbeiten“, Entwicklerhandbuch „Medien“.

### Netzwerk-Übersicht neu gestaltet (Netzwerk-Administration → Alle Websites)
- **App-Icon je Website** in der Kartenkopfzeile (44 px, Platz reserviert – kein Springen beim Laden) und im
  Website-Umschalter der Seitenleiste (16 px). Neu `Core\Network\SiteIcon`: liest `icons/icon-192.png` bzw. `icon-32.png`
  aus dem Medienordner der Website (von `Core\AppIcons` erzeugt). Weil sich alle Websites `public/` teilen, ist die
  Adresse auf jeder Domain der Installation gültig (`/media/icons/…`, `/sites/{key}/media/icons/…`); Cache-Busting über
  `sys.icon_version` der Website. Ohne Icon: Buchstaben-Kachel in der Markenfarbe (`sys.icon_bg` bzw. Vorgabe des Kits)
  als SVG-data-URI – ohne Inline-Styles.
- **Klarere Karten:** Name, Hauptadresse als Link (weitere Domains eingeklappt unter „+n weitere Adressen“, dort auch
  bearbeiten), Kurzname, Umgebung (Live/Staging), `noindex` und ein Status mit Farbe und Text („Alles in Ordnung“,
  „Hinweise“, „Wartung“, „Nicht live“, „Störung“). Kennzahlen in drei Gruppen: **Inhalte** (Seiten, Konten, letzte
  Änderung relativ), **Aktivität** (neue Anfragen, Freigaben, Support – große Zahlen nur, wenn etwas offen ist),
  **Betrieb** (Kit, Funktionen, Speicher, Sicherung mit Warnung ab 2 Tagen).
- **Geteilte Medien-Pools** erscheinen je Website mit Größe und Zahl der nutzenden Websites („Pool „KLXM“ 172,9 MB ·
  geteilt mit 2 Websites“) statt nur der eigenen Medien; Pool-Größe 15 min zwischengespeichert (`Stats::poolSize`),
  „Geteilte Ressourcen“ zeigt die Größe ebenfalls. Gesamtspeicher = Datenbanken + Medien der Websites + Pools.
- **Zusammenfassung als eine Zeile** statt sechs Kacheln; Nullen ruhig, Hinweise/Wartung/Nicht live filtern die Karten.
  Suche mit Symbol, Filter wie bisher, neue **Ansicht Kacheln/Liste** (im Browser gemerkt). Stile in
  `resources/css/network.css` (nur auf dieser Seite geladen), hell/dunkel, 1 Spalte auf dem Telefon.
- `Core\Network\Stats` liefert zusätzlich `noindex`, `icon_version`, `icon_bg`, `icon_text`, `pools` und `Stats::cached()`.
- **Website-Umschalter** („Website wechseln“ in der Seitenleiste) für Netzwerk-Konten jetzt auch auf der Netzwerk-Website;
  „Netzwerk-Übersicht“ führt dort direkt zu `/admin/network`, auf allen anderen Websites weiter per Einmal-Token.
- Offene `[Platzhalter]` in veröffentlichten Seiten einer Website erscheinen als Hinweis auf ihrer Karte.

### Personen einladen (Benutzer & Rollen)
- **Benutzer & Rollen → Person einladen** (`Core\Invites`, `InviteController`, Recht `users.manage`): E-Mail, Name
  (optional), Rolle, Sprache der Einladung (de/en) und persönliche Nachricht (reiner Text, höchstens 500 Zeichen).
  Zur Wahl stehen nur Rollen, die nicht mehr dürfen als die eigene (Rechte und Tabellenauswahl); nie „network“.
- **Offene Einladungen in der Benutzerliste:** „Eingeladen – wartet“ bzw. „Einladung abgelaufen“, Datum, wer eingeladen
  hat, Frist; **Erneut senden** (neuer Link, alte ungültig, neue Frist) und **Zurückziehen**.
- **Token:** 32 Zufallsbytes, gespeichert nur als SHA-256 mit dem Kürzel der Website, einmal verwendbar, 7 Tage gültig
  (config `invite_days`). Neue Tabelle `user_invites` (`Database::migrate` → nach dem Deploy `php bin/console migrate`).
  Das Konto entsteht erst beim Annehmen.
- **Annehmen unter `/admin/einladung/{token}`** (Stil der Anmeldung, App-Icon und Name der Website): Name, dann Passkey
  (angeboten, wenn der Browser es kann und die Richtlinie ihn erlaubt; Registrierung über `Core\Passkeys`) und/oder
  Passwort (Richtlinie, Anzeigen/Verbergen, Stärke-Hinweis). Danach Anmeldung und Begrüßung; verlangt die Rolle einen
  zweiten Faktor, folgt die bekannte Einrichtung. Ungültige, abgelaufene, benutzte und unbekannte Links sehen gleich aus
  („Bitten Sie um eine neue Einladung“). `Referrer-Policy: no-referrer`, CSRF auf allen Formularen und JSON-Aufrufen,
  Begrenzung je Anschluss, Protokoll `user.invite`, `user.invite-resend`, `user.invite-revoke`, `user.invite-accept`.
- **E-Mail „Einladung zu {Website}“** als HTML + Text (multipart): Tabellen-Layout für Outlook (VML-Schaltfläche), Gmail
  und Apple Mail, hell/dunkel (`color-scheme`, `prefers-color-scheme`, Outlook.com), App-Icon als eingebettetes Bild (CID),
  Markenfarbe aus dem Kit (Token `accent`, dunkel `accent@dark`), Nachricht als Zitat, Ersatz-Link, Frist, kurze
  Erklärung Passkey/Passwort. Vorlage `app/Admin/views/mail/invitation(.txt).php`, im Kit überschreibbar unter
  `templates/mail/`. Ohne zugestellte E-Mail zeigt die Verwaltung den Link einmal zum Kopieren.
- **`Core\Mailer::send()`** kann HTML mit eingebetteten Bildern (`['html' => …, 'inline' => …]`), meldet über
  `Mailer::$delivered`, ob die E-Mail wirklich an die Empfänger ging, und legt E-Mails für lokale Tests als Datei ab
  (config `mail_dump`). WebAuthn-Hilfen für Passkeys in `resources/js/_webauthn.js` (von `passkey.js` und `invite.js` genutzt).
- Konsole `user:invite <email> [rolle] [--name=…] [--lang=de|en]` (Status, Link bei fehlendem Versand) und `invites:selftest`.

### Trailer ohne Ton, Untertitel EN/DE/SL (Sprecher optional)
- **Standard ohne Ton** (`tools/trailer/voice.json` `"audio": false`): `trailer.mjs` erzeugt MP4/WebM ohne Tonspur;
  `--voice` (oder `"audio": true`) mit englischem Sprecher, `--voice-timing` stumm im Takt des Sprechers, `--silent` wie bisher.
  Die veröffentlichten Trailer-Dateien sind stumm (Videospur unverändert, Untertitel-Zeiten gelten weiter).
- **Neu gedreht** (`tools/trailer/trailer.mjs`): Kits, Bearbeiten auf der Seite mit Blöcken und KLXM AI, Mediathek mit
  geteilten Pools, Barrierefreiheit (Alt-Texte, Videos ohne Untertitel), Datentabellen und Formulare, Website-Suche,
  Block-Designer, KI optional/lokal, Funktionen & Erweiterungen, Netzwerk, Content-Sync. Wortwahl zeitlos, keine
  Ankündigungs-Formulierungen; Titelkarten und Einblendungen auf Englisch.
- **Sprecher Englisch, nur lokale TTS** (`tools/trailer/voice.mjs`): Stimme in einer Zeile von `tools/trailer/voice.json`
  (Standard Chatterbox mit britisch klingender Referenz aus der gemeinfreien Piper-Stimme `en_GB-cori-high`; Alternativen
  Chatterbox-Standard, Piper, macOS `say`), Takes per whisper.cpp geprüft, Aussprache-Lexikon `lexicon.en.json`,
  Hörproben mit `--samples`; Lautheit −16 LUFS. Drehbuch je Satz `vo: [[en, de, sl]]` → ein Untertitel je Satz,
  Zeiten aus der Sprechdauer; `--silent` erzeugt wie bisher eine stumme Fassung.
- Untertitelspuren: Bezeichnung „Slovenščina“ (und „Slovenčina“) für `sl`/`sk` in `Core\MediaTracks`.

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

### Platzhalter: alle Fundstellen mit Sprung in den Editor
- **„Was ist zu tun?“** in der Übersicht meldet alle `[Platzhalter]` veröffentlichter Seiten mit Anzahl und Seiten – auch
  für die Redaktion (`pages.edit`), nicht nur für die Administration. Erkannt werden auch kleingeschriebene wie
  `[bitte ergänzen: …]` (`Metrics::PLACEHOLDER_RX`), Markdown-Links `[Text](url)` nicht.
- **Aufklappbare Liste je Seite und Block** (`app/Admin/views/pages/_placeholders.php`): Blocktyp, Text (lange Texte
  aufklappbar), „Im Frontend bearbeiten“, „In der Verwaltung“ (Seiteneinstellungen zeigen die Platzhalter der Seite oben,
  `#platzhalter`) und „Ist gewollt“ (bis 1000 Zeichen, gilt für alle Seiten). `Metrics::placeholders()` sucht je Block in
  allen Texten, auch verschachtelt und ohne HTML.
- **Sprung in den Editor:** `?edit=1#b-{blockId}` (oder `?block=`) klappt den Block auf, scrollt hin, hebt ihn kurz hervor,
  markiert die Klammern (CSS Custom Highlight API) und öffnet auf breiten Bildschirmen die Felder in der Seitenleiste.

### Farbfelder: Option „Transparent“
- Feldtyp `color` mit `'transparent' => true` zeigt neben dem Farbwähler „Transparent“ und speichert dann `transparent`.
- Genutzt für den Hintergrund des App-Icons (Grundeinstellungen → App-Icon & PWA): Browser-Tab- und Android-Icons werden
  durchsichtig erzeugt, das iPhone- und das maskable-Icon brauchen eine Fläche und werden weiß hinterlegt.

### Fix: Dateien aus geteilten Pools
- Favicon/App-Icons aus einem Bild eines geteilten Pools wurden leer erzeugt, und KI-Medienaufträge fanden Pool-Dateien
  nicht, weil der Pfad immer im Medienordner der Website gesucht wurde. Neu `Media::path($m)` beachtet `_pool`.

### Dokumentation zu diesen Funktionen
- Handbuch: „Die Übersicht → Platzhalter finden und ersetzen“, „Funktionen & Erweiterungen → Netzwerk-Übersicht und
  Website-Umschalter“ sowie der Hinweis auf Erweiterungen als Pakete (z. B. „Entwurf teilen & freigeben“), „Hilfe &
  Support → Hinweise zu diesem Projekt“, „Häufige Aufgaben“ (App-Icon mit „Transparent“, Person einladen, Bild gerade rücken).
- Entwicklerhandbuch: Netzwerk-Übersicht (Karten, Icons, Pools, Umschalter), Sprung zum Block und Platzhalter-Suche
  (Editor), `transparent` bei Farbfeldern, `Media::path()` für Pools, Einladungs-Vorlage im Kit (`templates/mail/`,
  Variablen), Kommandozeile (`user:invite`, `invites:selftest`, `guide:list`, `guide:selftest`, `media:selftest`,
  `svg:selftest`); README-Übersicht ergänzt.

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
  (KLXM AI fragen, Tutorials der Rolle, Trailer, Neuigkeiten).
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
- **Theme-System**: Themes als eigenständige Pakete; mitgeliefert **basis** (neutral, Musterseiten, vier Navigationen,
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
- **KLXM AI** (Symfony AI): Schreiben, Übersetzen, SEO-Check und -Vorschläge, Alt-Texte, Seiten- und
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
