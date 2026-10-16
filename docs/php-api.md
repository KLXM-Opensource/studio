# PHP-API-Referenz – KLXM Studio 1.0.0

Automatisch erzeugt aus dem Code (`php bin/console docs:api`) – nicht von Hand bearbeiten. 361 Klassen.
Schnittstellen für Erweiterungen und Kits sind mit **API** markiert; alles andere ist intern und kann sich ändern.
Weitere Schnittstellen: REST-API (OpenAPI unter `/api/v1/openapi.json`), MCP-Server, Kommandozeile – Entwicklerhandbuch in der Verwaltung.

## Core

### `Accent` (class, `app/Accent.php`)

Persönliche Akzentfarbe der Verwaltung (Konto → Darstellung & Sprache).

Konstanten: `DEFAULT`, `PRESETS`

- `static allowed(): array` – Auf dieser Website erlaubte Vorlagen (Grundeinstellungen); Standard ist immer dabei
- `static attrs(?array $user = null): string` – Attribute für <html class="adm-ui"> bzw. Schatten-Hosts: ' data-accent="petrol" data-side="tint"' (Standard: leer)
- `static checks(): array` – Kontrastprüfung aller Vorlagen (WCAG 2.x): Text 4,5:1, Bedienelemente/Fokus 3:1 – hell, dunkel, Seitenleiste.
- `static client(?array $user = null): array` – Dieselben Werte für JavaScript (Seitenleiste „Eintrag bearbeiten“)
- `static current(?array $user = null): array` – Wirksame Wahl (nicht erlaubte Vorlagen fallen auf den Standard zurück)
- `static labels(): array` – Vorlagen mit übersetzter Bezeichnung [key => label]
- `static locked(): bool`
- `static prefs(?array $user): array` – Gespeicherte Wahl eines Kontos (roh, ungeprüft gegen die Website-Einstellung)
- `static save(int $userId, string $accent, bool $side): void` – Wahl speichern (nur erlaubte Vorlagen)

### `Activity` (class, `app/Activity.php`)

Aktionslog (Verwaltung → Werkzeuge → Aktionslog): wer hat wann Seiten, Einträge und Medien angelegt, geändert, veröffentlicht, zurückgezogen oder gelöscht – mit Link zum Objekt. Tabelle activity_log.

Konstanten: `TYPES`, `ACTIONS`, `GROUPS`

- `static actionLabel(string $a): string`
- `static canView(): bool` – Darf der angemeldete Benutzer das Log sehen?
- `static fromEvent(Events\Event $e): void` – Typisierte Ereignisse (Extensions::emit): Seiten speichern/veröffentlichen/zurückziehen/verwerfen/löschen, Einträge
- `static fromMedia(string $name, array $args): void` – Medien-Ereignisse (Media: media.imported, .replaced, .edited, .trashed, .deleted – erstes Argument = Datensatz)
- `static groupLabels(): array`
- `static link(array $r): ?string` – Link zum Objekt in der Verwaltung (null: gelöscht bzw. nicht mehr vorhanden)
- `static log(string $action, string $type, int $id, string $label, array $o = []): void` – Eintrag schreiben. $o: tbl (Kurzname der Datentabelle), lang, user (id; Standard: angemeldet), detail (kurze Notiz)
- `static pause(bool $off = true): void` – Für Selbsttests und Massenimporte: Protokollieren aussetzen
- `static prune(): void` – Ältere Einträge entfernen
- `static query(array $f, int $limit = 100, int $offset = 0): array` – Einträge mit Filtern: type (page|entry|media), group (new|changed|live|gone), user (id; 0 = System), q (Suche im Namen),
- `static seed(): void` – Einmalig: Log aus vorhandenen Versionen und Medien füllen (letzte 90 Tage), damit es nicht leer beginnt
- `static typeLabel(string $t): string`
- `static typeLabels(): array`
- `static users(): array` – Personen, die im Log vorkommen: id → Name (0 = System)
- `static viewUrl(array $r): ?string` – Ansehen auf der Website (nur Seiten, die es gibt)

### `AdminPages` (class, `app/AdminPages.php`)

Seiten der Verwaltung, die Funktionen des Cores und Erweiterungen anmelden – und WO sie erscheinen.

Konstanten: `KINDS`, `HUB`, `STATS`

- `static all(): array` – Alle Anmeldungen: Core + aktive Erweiterungen (noch ohne Rechteprüfung)
- `static forTable(string $handle): array` – Einstellungsseiten, die zu einer Datentabelle gehören ('table' => …) – Reiter/Knöpfe an der Tabelle
- `static groups(): array` – Gruppen des Abschnitts „Einrichtung“ der Seitenleiste: „Website“, „System“ (inkl. Werkzeuge der Erweiterungen) und „Statistiken“,
- `static hasSettings(): bool` – Gibt es etwas für die Sammelseite? (Menüpunkt nur dann)
- `static hasStats(): bool`
- `static match(string $path): ?array` – Angemeldete Seite zu einer Adresse (längster passender Pfad) – z. B. um „Einstellungen“ im Menü als aktuell zu markieren
- `static nav(string $place = 'main'): array` – Einträge für die Seitenleiste: nur content und tool, je Platz ('main' = Hauptmenü, 'admin' = Administration).
- `static normalize(array $def, string $source = 'core', string $sourceLabel = ''): ?array` – Anmeldung prüfen und vervollständigen – null bei ungültiger Adresse/Beschriftung.
- `static ofKind(string ...$kinds): array` – Sichtbare Seiten einer oder mehrerer Arten
- `static register(array $def): void` – Seite des Cores anmelden (Erweiterungen: Extension::adminPage)
- `static reset(): void` – Anmeldungen des Cores zurücksetzen (Selbsttest)
- `static settings(): array` – Einstellungsseiten für die Sammelseite, gruppiert: [['label' => 'Funktionen'|Erweiterung, 'pages' => [...]], …]
- `static stats(): array` – Statistik-Seiten für „Statistiken“, gruppiert wie settings()
- `static visible(array $p): bool` – Darf der angemeldete Benutzer die Seite sehen? (Recht, Funktion, weitere Bedingung)

### `AdminPath` (class, `app/AdminPath.php`)

Eigene Adresse der Verwaltung statt /admin (z. B. /werkstatt-k7m2x). - Einstellung: Umgebungsvariable KLXM_ADMIN_PATH, sonst config 'admin_path' (config.local.php, je Website config/sites/{key}.php); setzen in Grundeinstellungen → „Adresse der Verwaltung“ (Administration; bei mehreren Websites nur die Netzwerk-Administration,

Konstanten: `INTERNAL`, `RESERVED`

- `static canManage(): bool` – Ändern in der Verwaltung: gilt für alle Websites der Installation → bei mehreren Websites nur die Netzwerk-Administration
- `static custom(): bool`
- `static directAllowed(string $internalPath, ?Http\Router $router = null, string $method = 'GET'): bool` – Darf /admin… ohne Anmeldung direkt erreichbar bleiben? (Netzwerk-SSO, öffentliche Routen von Erweiterungen)
- `static error(string $slug): ?string` – Fehlermeldung für eine gewünschte Adresse (ohne Schrägstrich) oder null
- `static fromEnv(): bool` – Über die Umgebungsvariable festgelegt (dann nicht in der Verwaltung änderbar)
- `static isInternal(string $path): bool` – /admin oder /admin/… (auch mit ?Abfrage oder #Anker)
- `static normalize(string $raw): string`
- `static prefix(): string` – Öffentliche Adresse der Verwaltung, z. B. '/werkstatt-k7m2x' – ohne eigene Einstellung '/admin'
- `static random(): string` – Zufällige, gut lesbare Adresse, z. B. „kontor-x4m9q“ (ohne 0/o/1/l)
- `static reset(?string $prefix = null): void` – Nur für Tests und den Konsolenbefehl: zwischengespeicherte Adresse vergessen
- `static save(string $slug): ?string` – Adresse speichern (config/config.local.php, alle Websites der Installation); '' = wieder /admin.
- `static toInternal(string $path): ?string` – '/werkstatt-k7m2x/x' → '/admin/x'; null, wenn der Pfad nicht zur eigenen Adresse gehört
- `static toPublic(string $path): string` – '/admin/x?y' → '/werkstatt-k7m2x/x?y'; andere Pfade unverändert

### **API** `AdminUi` (class, `app/AdminUi.php`)

Baukasten der Verwaltung (Vorbild: macOS-Systemeinstellungen) – für Kernseiten und Erweiterungen, immer gleich:

- `static close(): string`
- `static group(string $title, array|string $rows, string $note = '', string $id = ''): string` – Gruppe von Zeilen: Titel über der Karte, Zeilen darin, Hinweis darunter
- `static head(string $title, string $eyebrow = '', string $text = '', string $actions = ''): string` – Kopf einer Verwaltungsseite: Dachzeile, Titel, eine Zeile Beschreibung, Aktionen rechts (fertiges HTML)
- `static link(string $label, string $href, string $sub = '', string $value = ''): string` – Ganze Zeile als Link (öffnet eine Unterseite)
- `static nav(array $items, string $current, string $label = ''): string` – Bereichsliste (Links): $items key => [href, label, icon, badge?, warn?] oder '-' für eine Trennlinie
- `static open(array $items, string $current, string $label = '', bool $title = true): string` – Rahmen öffnen: Bereichsliste links, Inhalt rechts (mit Überschrift des Bereichs)
- `static row(string $label, string $control = '', string $sub = '', array $opt = []): string` – Eine Zeile: Beschriftung und Erklärung links, Bedienung rechts ($control = fertiges HTML, z. B. Schalter, Knopf, Wert).
- `static savebar(string $note = '', string $label = ''): string` – Feste Speichern-Leiste (innerhalb eines Formulars)
- `static tabs(array $sections, array $form = [], string $label = ''): string` – Bereiche auf derselben Seite (Wechsel ohne Neuladen, Adresse #bereich): $sections id => [label, icon, html, badge?, warn?, error?] oder '-' (Trennlinie).
- `static toolbar(string $html): string` – Werkzeugzeile über dem Inhalt, rechtsbündig (fertiges HTML)

### `ApiReference` (class, `app/ApiReference.php`)

PHP-API-Referenz aus dem Code (Reflection): alle Klassen unter app/ (Namespace Core\) mit ihren öffentlichen Methoden, Signatur und erster Zeile des Doc-Kommentars. Immer aktuell – Entwicklerhandbuch → PHP-API-Referenz und `php bin/console docs:api` (Markdown). Klassen in PUBLIC sind die Schnittstellen für Erweiterungen und Kits; alles andere ist intern (kann sich ändern).

Konstanten: `PUBLIC`

- `static build(bool $all = true): array` – Gruppen nach Namespace: [Namespace => [Klassenname => ['doc' => …, 'kind' => class|interface|enum|trait, 'methods' => [[name, signature, doc, static]], 'consts' => [...]]]]
- `static classes(): array` – Alle Klassennamen unter app/ (PSR-4 Core\ → app/), ohne Ansichten und Block-Vorlagen
- `static isPublic(string $cls): bool` – Gehört die Klasse zur stabilen Schnittstelle? (PUBLIC sowie alle typisierten Ereignisse unter Core\Events)
- `static markdown(): string` – Markdown für docs/php-api.md (GitHub)
- `static signature(ReflectionMethod|ReflectionFunction $m): string` – Signatur „name(Type $a, ?int $b = 5): Rückgabe“
- `static summary(string $doc, int $lines = 1): string` – Erste Absatz-Zeile(n) eines Doc-Kommentars (ohne @-Angaben)

### `App` (class, `app/App.php`)

Minimaler Service-Container. Zugriff überall per app().

- `static boot(Config $config, ?Site $site = null): self`
- `static get(): self`
- `handle(Http\Request $request): Http\Response`
- `key(): string`

### `AppIcons` (class, `app/AppIcons.php`)

Favicon- und App-Icon-Generator + Web-App-Manifest (PWA).

Konstanten: `SIZES`

- `static appInfo(): array` – Name, Kurzname, Kurzbefehle aus dem Theme (Funktion in functions.php) + Einstellungen
- `static config(?array $over = null): array` – Einstellungen mit Theme-Vorgaben zusammenführen
- `static dir(): string`
- `static ensure(): bool` – Sicherstellen, dass Icons existieren (Erstinstallation)
- `static generate(): ?string` – Alle Dateien erzeugen und Version erhöhen. @return ?string Fehlermeldung
- `static headTags(): string` – <link>/<meta>-Tags für den <head>
- `static landingIcon(Landing $l): ?array` – Favicon einer Landingpage: ['url' => …, 'mime' => …] oder null
- `static manifest(): array` – Web-App-Manifest
- `static preview(array $over): array` – Vorschau als data-URIs, ohne zu speichern (für die Grundeinstellungen)
- `static render(array $cfg, int $size, string $purpose = 'any'): GdImage` – Ein Icon zeichnen.
- `static svgProblem(?array $cfg = null): ?string` – Hinweis, wenn das gewählte Bild eine SVG ist, die dieser Server nicht rastern kann (sonst null)
- `static url(string $file): string`
- `static version(): string`

### `AssetDocs` (class, `app/AssetDocs.php`)

CSS-&-JS-Referenz aus den Quellen (Entwicklerhandbuch „CSS & JS“, php bin/console docs:assets).

Konstanten: `MARK_START`, `MARK_END`, `CORE_PUBLIC`, `CORE_CONDITIONAL`

- `static core(bool $fresh = false): array` – Kern-Referenz (gecacht in storage/cache, Schlüssel = jüngste Änderung der Quellen)
- `static cssProperties(string $css): array` – Custom Properties einer CSS-Datei.
- `static dataAttributes(string $src, bool $script = false): array` – data-*-Attribute in Vorlagen (HTML/PHP: data-x= bzw. 'data-x' => …) und Skripten (dataset.x, [data-x], getAttribute('data-x'))
- `static jsEvents(string $js): array` – Namensraum-Ereignisse in JavaScript (Name mit Doppelpunkt, z. B. cms:saved, klxm:inview):
- `static kit(string $dir): array`
- `static kitMarkdown(array $k, int $h = 2): array` – Kit-Teil als Markdown-Zeilen (Überschriften ab Ebene $h)
- `static kitTemplate(array $k): string` – Vorlage der einheitlichen Kit-Seite „CSS & JS“ (Abschnitte 1–7 von Hand, vorbelegt mit dem, was sich ablesen lässt; Anhang generiert)
- `static markdown(?array $core, ?array $kit): string` – Markdown der Referenz: Kern (ohne $kit) bzw. Kit-Teil (mit $kit; $withCore = false nur das Kit)
- `static motion(string $css): array` – Bewegung und Barrierefreiheit einer CSS-Datei: @keyframes, Animationen/Übergänge, prefers-reduced-motion, forced-colors, :focus-visible
- `static readCss(string $file, int $level = 0): string` – CSS-Datei mit aufgelösten lokalen @import "./_x.css" (wie esbuild beim Build)
- `static selftest(): array` – Parser gegen Beispiele (Fixtures) und gegen den Kern selbst (bekannte Variablen, Ereignisse, Attribute, conditional_css)
- `static stripCssComments(string $css): string` – Kommentare entfernen, Zeichenketten und Zeilen bleiben erhalten (Zeilennummern stimmen weiter)
- `static stripJsComments(string $js): string` – Kommentare in JavaScript entfernen: Blockkommentare und Zeilen, die mit // beginnen (Zeilennummern bleiben)
- `static updateKitDoc(string $dir, array $kit): string` – Kit-Dokument kits/{name}/docs/css-js.md anlegen bzw. den generierten Bereich zwischen den Markern ersetzen.
- `static varUses(string $src, ?array &$nested = null): array` – var(--x, Rückfall) → [--x => Rückfall|null] (erster Fund je Name, Rückfall mit verschachtelten Klammern).

### `Auth` (class, `app/Auth.php`)

Konstanten: `ROLES`, `PENDING_2FA`

- `__construct(Database $db, Session $session)`
- `attempt(string $email, string $password, string $ip): ?string`
- `can(string $perm, ?string $table = null): bool`
- `check(): bool`
- `createUser(string $email, string $password, string $role = 'editor', string $name = '', string $via = 'admin'): int` – Konto anlegen; $via für Erweiterungen (user.created): 'admin' | 'setup'
- `isAdmin(): bool`
- `login(int $uid, array $extra = []): void` – Sitzung für ein Konto starten (nach Passwort und ggf. zweitem Faktor bzw. Netzwerk-Token)
- `logout(): void`
- `static passwordProblem(string $pw): ?string`
- `reset(): void` – Anmeldung neu lesen (z. B. nach dem Ende einer Ansicht als Rolle/Person)
- `role(): ?array` – Rolle des angemeldeten Benutzers inkl. Rechten
- `static siteMark(): string` – Kennung der Website für die Sitzung (aus dem Schlüssel der Website – nicht übertragbar)
- `user(): ?array`

### `AuthScreen` (class, `app/AuthScreen.php`)

Bildschirme vor der Anmeldung (Anmelden, Passkey, Zwei-Faktor, Einladung, Links aus E-Mails, Einrichtung): bewegter Farbhimmel hinter einer Karte aus Milchglas, der sich mit der Tageszeit ändert (resources/css/auth.css).

Konstanten: `PHASES`

- `static bodyClass(): string` – Klassen für <body>
- `static cssUrl(): ?string` – Erzeugte CSS-Datei mit den Farbvariablen der Website → URL, null = KLXM-Palette
- `static forceDark(): bool` – Nachts immer dunkel
- `static greeting(): string` – Kurzer Gruß zur Tageszeit (Anmeldeseite)
- `static head(): string` – Stylesheets für <head>
- `static palette(?string $hex = null): ?array` – Markenfarbe → [Farbton, zweiter Farbton, Sättigung %, Hex] oder null (KLXM-Palette aus auth.css).
- `static sky(): string` – Hintergrund (dekorativ) direkt nach <body>
- `static state(): array`

### `Avatar` (class, `app/Avatar.php`)

Profilbilder der Verwaltung (Konto → Profilbild). - Gespeichert installationsweit je E-Mail-Adresse: storage/avatars/{sha256(email), 32 Zeichen}.webp, 256 × 256 px, mittig quadratisch zugeschnitten, EXIF-Drehung berücksichtigt. Netzwerk-Konten haben so auf allen Websites dasselbe Bild.

Konstanten: `SIZE`, `MAX_BYTES`

- `static html(array $user, string $cls = 'adm-ava'): string` – Bild oder Initialen; $cls z. B. 'adm-ava adm-ava--s'
- `static initials(array $user): string`
- `static path(string $email): string`
- `static remove(array $user): void`
- `static store(array $user, array $file): ?string` – Hochgeladenes Bild übernehmen → null oder Fehlertext
- `static url(array $user): ?string`
- `static version(array $user): int` – Zeitstempel des Bildes (0 = keins)

### `Block` (class, `app/Block.php`)

Wird an jeden Block-Renderer übergeben ($b). $d enthält die Daten mit Defaults.

- `__construct(string $id, string $type, array $data, array $tunes, array $def, ?Block $prev = null)`
- `bg(): string` – Hintergrund des Abschnitts. Mit Hintergrundbild und Abdunkelung gilt ein dunkler Hintergrund des Themes
- `central(?string $label = null): string` – Markiert zentral gepflegte Inhalte (Einstellungen des Themes) im Bearbeitungsmodus.
- `dark(): bool` – Dunkler Hintergrund? (für Akzentfarben)
- `domId(): string`
- `edit(string $path, string $mode = 'plain'): string` – Im Bearbeitungsmodus: macht ein Textfeld direkt im Frontend editierbar.
- `inRow(): bool` – Steht der Block in einer Spalte (Block „Layout“, Core\Layout) bzw. – alte Inhalte – neben dem vorigen (Tune „row“)?
- `nested(): bool` – Steht der Block in einer Spalte eines Layouts?
- `sectionBg(): string` – Hintergrundbild des Abschnitts: absolut positioniertes <picture> hinter dem Inhalt (keine Inline-Styles, CSP).
- `sectionClass(): string`
- `targetEdit(?string $ref, ?string $label = null): string` – „Ziel bearbeiten“ an einer Karte/Kachel, die auf eine Seite oder einen Eintrag zeigt (Core\TargetEdit):
- `titleId(): string`
- `tune(string $key): mixed`
- `variant(): string`
- `withTunes(array $tunes): self` – Kopie mit geänderten Abschnitts-Optionen

### `Branding` (class, `app/Branding.php`)

Branding der Agentur (System → Branding & Support): eigenes Logo und eigener Name statt der Produktmarke (Anmeldung, Einrichtung, Werkzeugleiste der Website, Begrüßung) und Support-Angaben (Hilfe & Support, Übersicht, Anmeldeseite). Gilt für die ganze Installation: Einzel-Installation → Administration (system.manage), Netzwerk → nur die Netzwerk-Administration

Konstanten: `KINDS`, `MAX_BYTES`

- `static active(): bool` – Eigenes Logo oder eigener Name gesetzt?
- `static canEdit(): bool` – Wer darf das Branding ändern? Einzel-Installation: Administration; Netzwerk: Netzwerk-Administration. Mehrere Websites ohne
- `static dir(): string`
- `static get(): array`
- `static hasSupport(): bool` – Support-Angaben vorhanden?
- `static icon(): string` – Kleines Zeichen (cms_logo): Logo bzw. Anfangsbuchstabe des Namens
- `static logoHtml(string $class = 'cms-mark__img'): string` – Logo als <img> (helle und – falls vorhanden – dunkle Fassung)
- `static logoUrl(bool $dark = false): ?string` – Adresse des Logos (null = keins); dunkle Fassung fällt auf das helle zurück
- `static mark(): string` – Ersatz für die Produktmarke (cms_mark): Logo und/oder Name, darunter klein „Technik: KLXM Studio“
- `static name(): string` – Name für Titel/Beschriftungen: Agentur, sonst Produkt
- `static removeLogo(string $which): void`
- `static save(array $c): void`
- `static serve(string $which): Http\Response` – Logo ausliefern (öffentlich – erscheint auch auf der Anmeldeseite), lange zwischenspeicherbar (Adresse enthält ?v=)
- `static storeLogo(array $file, string $which): ?string` – Logo übernehmen (Upload): SVG wird bereinigt (Core\Svg), sonst PNG/JPG/WebP, höchstens 1 MB. $which 'logo' | 'logo_dark'.
- `static supportHtml(bool $compact = false): string` – Support-Angaben als Karte (Hilfe & Support, Übersicht) bzw. kurze Zeile (Anmeldeseite)

### **API** `Charts` (class, `app/Charts.php`)

Diagramme für Verwaltung und Website – mit austauschbarer Engine.

Konstanten: `TYPES`, `FORMATS`

- `static blockFields(): array` – Felder des Blocks „Diagramm“ (app/Blocks/blocks.php)
- `static engine(): string` – Gewählte Engine: Kit (Website) → Konfiguration 'charts.engine' → echarts
- `static engines(): array`
- `static format(int|float|null $v, string $format = 'number', string $unit = ''): string` – Ein Wert lesbar (gleiche Regeln wie die Engine)
- `static fromBlock(array $d): array` – Beschreibung aus den Feldern des Blocks (von Hand oder aus einer angemeldeten Quelle)
- `static loaderPrinted(): void` – Lader ist schon auf der Seite (z. B. im Layout der Verwaltung) – render() gibt ihn nicht noch einmal aus
- `static normalize(array $spec): array` – Beschreibung prüfen und vereinheitlichen (Typ, Zahlen, Grenzen)
- `static number(string $v): ?float` – Zahl aus einer Eingabe: „1.250“, „12,5“, „1 250“, „3.5“ → float; leer/ungültig → null
- `static registerEngine(string $name, string $url): void` – Weitere Engine anmelden (Erweiterung, Kit): Adresse eines ES-Moduls mit render(el, spec, theme)
- `static registerSource(string $key, string $label, callable $fn): void` – Datenquelle für den Block „Diagramm“ anmelden: $fn(array $blockData): array liefert type/labels/series (wie render()).
- `static render(array $spec, array $opt = []): string` – Diagramm ausgeben. $opt: class (zusätzliche Klasse), table (true = Tabelle aufgeklappt, 'none' = ohne, wenn die Seite die Werte schon als Tabelle zeigt), caption (false = ohne Titel)
- `static sources(): array`
- `static table(array $s): string` – Werte als Tabelle (Fallback ohne JavaScript, Screenreader, Ausdruck)

### `ChartsSelfTest` (class, `app/ChartsSelfTest.php`)

php bin/console charts:selftest – Core\Charts: Prüfen der Beschreibung (Typ, Zahlen, Grenzen), Zahlen aus Eingaben, Ausgabe ohne style-Attribute und ohne ausführbares Skript (CSP), Tabelle, Quellen und Engines.

- `static run(): array`

### `Cli` (class, `app/Cli.php`)

Kleine Helfer für die Kommandozeile (bin/console, Befehle von Kern, Kits und Erweiterungen).

- `static eachSite(string $cmd, array $args = [], bool $header = true): int` – Befehl für jede Website als eigenen Prozess ausführen (bin/console {cmd} {args} --site={key}).
- `static flag(array $args, string $name): bool` – Schalter --name gesetzt?
- `static opt(array $args, string $name): ?string` – Wert einer Option --name=wert (letzte Angabe gilt) oder null
- `static positional(array $args): array` – Argumente ohne „-“ am Anfang, in Reihenfolge

### `Combo` (class, `app/Combo.php`)

Kombifelder der Datentabellen: Ansprechperson (person), Fragen & Antworten (faq), Adresse (address), Downloads (downloads). Gespeichert als JSON-Liste wie die Wiederholbare Gruppe; die Eingabe ist ein Wiederholer mit festen Unterfeldern (schema()), bereinigt über Core\Fields (Unterfelder wie in Blöcken: Bild, Datei, formatierter Text …). Auf der Website eine passende

Konstanten: `TYPES`

- `static html(array $f, mixed $raw): string` – Darstellung auf der Website
- `static is(string $type): bool`
- `static items(mixed $raw): array` – Gespeicherter Wert (JSON oder Liste) → Liste der Einträge
- `static mediaIds(array $f, mixed $raw): array` – Verwendete Medien (Fotos, Dateien) – für „Wird verwendet“ und die Suche nach verwendeten PDFs
- `static schema(array $f): array` – Eingabe als Wiederholer (Core\Fields) – Beschriftungen in der Sprache der Verwaltung
- `static selftest(): array` – Selbsttest (Teil von `php bin/console data:selftest`): Darstellung, Escaping, schema.org, Suche, Kurzfassung – ohne Datenbank
- `static summary(array $f, mixed $raw): string` – Kurzfassung für Listen der Verwaltung, z. B. „Erika Musterfrau, Max Mustermann“ bzw. „3 Fragen“
- `static text(array $f, mixed $raw): string` – Text für die Suche (Website-Suche, Suche in Listen)

### `Config` (class, `app/Config.php`)

Lädt config/config.php (Standardwerte, versioniert) und überschreibt sie mit config/config.local.php (Geheimnisse, NICHT versioniert). Fehlt die lokale Datei, wird sie beim ersten Aufruf mit zufälligen Schlüsseln angelegt.

- `all(): array`
- `get(string $key, mixed $default = null): mixed`
- `static load(string $dir, string $site = Core\Site::DEFAULT): self` – Reihenfolge: config.php ← config.local.php ← config/sites/{site}.php (Multi-Site).
- `static writeLocal(string $file, array $overrides = []): bool` – Legt config.local.php mit zufälligem App-Key und Setup-Token an.

### `ContentSync` (class, `app/ContentSync.php`)

Inhalte zwischen Live und einer lokalen Kopie abgleichen – Seiten hin und zurück, ohne fremde Änderungen zu überschreiben.

- `static console(string $cmd, array $args): int`
- `static fingerprint(array $page, bool $draft = false): string` – Fingerabdruck einer Seite: Blöcke (ohne Zeitstempel) + Seitenfelder

### **API** `Cron` (class, `app/Cron.php`)

Geplante Aufgaben mit einem einzigen Cronjob für die ganze Installation:

Konstanten: `STALE`, `TIMEOUT`

- `static boot(): void` – Aufgaben von Kern und installierten Erweiterungen anmelden (einmal je Prozess)
- `static canManage(): bool` – Wer darf die geplanten Aufgaben verwalten? (installationsweit: Netzwerk-Website bzw. Einzel-Installation)
- `static configure(string $key, ?bool $enabled = null, ?string $schedule = null): void` – Zustand einer Aufgabe setzen (Verwaltung): enabled, schedule ('' = Standard)
- `static core(): void` – Aufgaben des Kerns (bisher einzeln als Cronjob dokumentiert)
- `static db(): Database`
- `static describe(string $expr): string` – Zeitplan in Worten: „jede Minute“, „alle 15 Minuten“, „täglich um 04:15“, „montags um 06:00“ … sonst der Ausdruck
- `static dir(): string`
- `static due(string $expr, int $ts): bool`
- `static enqueue(string $key, string $by = ''): int` – Einmaligen Lauf einreihen (läuft beim nächsten cron:run)
- `static exec(string $key, string $trigger = 'manual'): int` – cron:exec – eine Aufgabe ausführen (Sperre, Zeitlimit, Protokoll). Rückgabe: Exit-Code
- `static extensions(): void` – Aufgaben aus den Manifesten installierter Erweiterungen ('schedule')
- `static health(): array` – Systemprüfung: Cronjob läuft? Fehlgeschlagene Aufgaben? (nur Hinweise, nie Fehler)
- `static heartbeat(): ?int`
- `static line(): string` – Zeile für den einzigen Cronjob
- `static next(string $expr, ?int $from = null): ?int` – Nächster Zeitpunkt (bis 32 Tage voraus) oder null
- `static presets(): array` – Auswahl für die Verwaltung
- `static register(string $key, string $label, string $command, string $schedule, array $opt = []): void` – Aufgabe anmelden. $opt: group (Überschrift in der Verwaltung), owner (core | Name der Erweiterung), timeout (Sekunden), help.
- `static run(?int $now = null): array` – cron:run – fällige Aufgaben und Warteschlange im Hintergrund starten; gibt gestartete Schlüssel zurück
- `static running(): bool`
- `static sitesFor(string $key): array` – Websites, auf denen die Erweiterung einer Aufgabe aktiv ist (Kern: [])
- `static tasks(): array`
- `static valid(string $expr): bool`

### `CronSelfTest` (class, `app/CronSelfTest.php`)

php bin/console cron:selftest – Core\Cron: Zeitausdrücke prüfen, Fälligkeit (inkl. Tag ODER Wochentag wie cron), nächster Lauf, Beschreibung in Worten, Anmeldung von Kern und Erweiterungen, Warteschlange ohne Doppel, Ausführen mit Protokoll.

- `static run(): int`

### **API** `Csrf` (class, `app/Csrf.php`)

CSRF-Schutz für alle Admin-POSTs (sessionbasiert).

- `static field(): string`
- `static token(): string`
- `static valid(Http\Request $r): bool`

### **API** `Database` (class, `app/Database.php`)

PDO-Wrapper. SQLite (Standard) oder MySQL/MariaDB.

- `__construct(array $cfg)`
- `ensureColumns(string $table, array $columns): void` – Fügt fehlende Spalten hinzu (idempotent, SQLite und MySQL).
- `fetch(string $sql, array $params = []): ?array`
- `fetchAll(string $sql, array $params = []): array`
- `fetchValue(string $sql, array $params = []): mixed`
- `insert(string $table, array $data): int`
- `migrate(): void` – Legt fehlende Tabellen an (idempotent).
- `query(string $sql, array $params = []): PDOStatement`
- `transaction(callable $fn): mixed`
- `update(string $table, array $data, string $where, array $params = []): int` – UPDATE mit Platzhaltern; Rückgabe: Zahl der geänderten Zeilen

### `Design` (class, `app/Design.php`)

Design-Werte (Style-Editor): Themes erklären in theme.php → 'design', was sich einstellen lässt; Admins ändern es unter Verwaltung → Design. Pro Website entsteht daraus eine CSS-Datei mit Variablen.

Konstanten: `PREVIEW_KEY`, `MINE_MAX`

- `static applySessionPreview(): bool` – Persönliche Vorschau aus der Sitzung anwenden: ungespeicherte Werte, die das Markup ändern (Kopf-/Fußvariante …), rendert
- `static checks(?array $values = null): array` – Kontrastprüfungen für Tokens mit 'contrast' => ['with' => Farbe|Token, 'min' => 4.5, 'dark_with' => Farbe|Token (optional)].
- `static classes(?array $values = null): string` – Klassen für <html> aus choice-/bool-Werten mit 'class' ($values: andere Werte statt der gespeicherten, z. B. Live-Vorschau)
- `static color(mixed $v): ?string`
- `static contrast(string $a, string $b): float` – Kontrast nach WCAG 2.x zwischen zwei Farben (1–21)
- `static css(?array $values = null): string` – CSS mit allen Variablen (hell + dunkel) für die aktuellen Werte
- `static darkFirst(array $schema): array` – Style-Editor (Verwaltung und Website): Schalter „dark“ (alle Kits) ganz vorn in den ersten Bereich, einheitlich benannt
- `static def(): array`
- `static defaults(): array` – Standardwerte des Themes (normalisiert)
- `static deleteMine(string $id): bool`
- `static enabled(): bool`
- `static fileUrl(): ?string` – Öffentliche CSS-Datei der Website (bei Bedarf neu erzeugt) → URL
- `static fontAssets(array $values): array` – Schriften für die Live-Vorschau: Stylesheets (installierte Schriften, Schrift-CSS des Kits) und @font-face-Regeln für noch
- `static fonts(): array` – Schriften für Tokens vom Typ „font“: die des Themes ('fonts') + installierte Schriften (Core\Fonts, Schlüssel
- `static get(string $name): mixed`
- `static head(): string` – <link>-Tags: gewählte Schriften des Themes + Design-Variablen (nach dem Theme-CSS einbinden)
- `static history(): array`
- `static htmlClasses(array $values): string` – Alle Klassen, die das Kit für diese Werte an <html> setzt: Funktion {kit}_html_class() des Kits (z. B. fluid_html_class()
- `static live(array $values): array` – Alles, was die Website für diese Werte live braucht (Werkzeug „Design“ der Werkzeugleiste): css (Variablen hell + dunkel,
- `static markupTokens(): array` – Tokens, deren Wert das Markup der Seite ändert ('markup' => true, z. B. Kopfvariante) – in der Live-Vorschau erst nach dem Speichern
- `static mine(): array` – Eigene Designs dieses Kits: id => [name, values, at, by] (neueste zuerst)
- `static minePresets(): array` – Eigene Designs als Vorlagen (Schlüssel „mine:{id}“) – Verwaltung und Werkzeug „Design“ wenden sie wie Kit-Vorlagen an
- `static normalize(array $in): array` – Eingaben prüfen und auf erlaubte Werte begrenzen; Unbekanntes fällt weg
- `static optionCss(?array $values = null): array` – Stylesheets der gewählten Varianten (Kit-Konvention css/opt-{token}-{wert}.css, z. B. opt-header-sidebar.css) – nur
- `static override(?array $values): void` – Werte nur für diese Anfrage ersetzen (Vorschau im Editor)
- `static preloadUrls(array $tokens = []): array` – Adressen der Schriftdateien zum Vorladen: Hauptschnitt (lateinisch, aufrecht) der gewählten Schrift je Token.
- `static preloads(array $tokens = []): string` – <link rel="preload"> für preloadUrls() – im Kit-Layout vor dem CSS (z. B. Design::preloads(['font_body', 'font_head']))
- `static save(array $values, string $note = '', ?string $by = null): array` – Werte speichern (mit Verlauf der letzten 10 Stände). Neu gewählte Kit-Schriften ('fontsource') werden danach
- `static schema(): array` – Definition für Editor und API (Gruppen, Schriften, Vorlagen, Dunkel-Modus) – JSON-tauglich
- `static storeMine(string $name, array $values): string` – Aktuelle bzw. übergebene Werte unter einem Namen speichern – gleicher Name (ohne Groß/klein) ersetzt den bisherigen. @return string id
- `static tokens(): array` – Alle Token-Definitionen [name => def]
- `static values(): array` – Gespeicherte Werte der Website (theme-bezogen), ergänzt um Standardwerte

### `DesignTool` (class, `app/DesignTool.php`)

„Design“: Style-Editor als Werkzeug der Werkzeugleiste auf der Website (Core\FrontendTools) – Vorlagen und alle Design-Werte des Kits (Core\Design) in einer Seitenleiste rechts; die Website wird schmaler (body.has-tdrawer) und zeigt jede Änderung sofort auf der aktuellen Seite (ohne iframe, ohne Neuladen). „Speichern“ gilt für die ganze Website, „Abbrechen“

Konstanten: `ID`

- `static definition(): array`

### `Domains` (class, `app/Domains.php`)

Verwaltung → System → Domain (Admin\DomainController): Domains, Hauptadresse, Weiterleitung und Umgebung einer Einzel-Installation. Gibt es mehrere Websites oder Netzwerk-Konten, verwaltet die Netzwerk-Administration die Domains (/admin/network, site:hosts) – die Seite gibt es dann nicht.

Konstanten: `CHECK_KEY`, `CHECK_TTL`

- `static answer(string $q): ?string` – Antwort für /health?domain_check=… – nur mit gültiger, nicht abgelaufener Frage
- `static available(): bool` – Seite „Domain“ verfügbar? Nur Einzel-Installation ohne Netzwerk-Konten
- `static check(string $host): array` – Zeigt die Domain auf diese Installation? HTTPS zuerst (mit Zertifikatsprüfung), dann HTTP.
- `static isLocal(string $host): bool` – Lokale Entwicklungsadresse (localhost, *.localhost, *.test, 127.0.0.1, ::1) – nie Ziel/Quelle einer Weiterleitung
- `static redirectOn(?Site $site = null): bool` – Weiterleitung auf die Hauptadresse eingeschaltet?
- `static redirectTarget(Site $site, string $method, string $host, string $path, string $uri, bool $secure): ?string` – Ziel der 301-Weiterleitung auf die Hauptadresse oder null. Nur GET/HEAD, nur weitere Domains aus 'hosts'

### `EditLock` (class, `app/EditLock.php`)

Bearbeitungssperre (Grundeinstellung sys.edit_lock, Standard aus; braucht die Präsenz sys.presence).

- `static actor(): ?int` – Wer handelt gerade? null = niemand (System)
- `static apiActor(): ?int` – Handelnde Person der REST-API/MCP (nur dort gesetzt)
- `static assert(string $type, string $id, ?int $userId): void` – Speichern verhindern, wenn jemand anderes sperrt (HTTP 423)
- `static blockedFor(string $type, string $id, ?int $userId = null): ?array` – Sperrt jemand anderes das Objekt für diese Person? → Präsenz-Eintrag des Inhabers, sonst null
- `static canTake(): bool` – Darf die Person eine fremde Sperre übernehmen? (Administration)
- `static holder(string $type, string $id): ?array` – Inhaber der Sperre (aktiver Präsenz-Eintrag mit dem frühesten „seit“) oder null
- `static message(array $holder): string` – Meldung für eine Sperre
- `static on(): bool`
- `static selftest(): array` – Selbsttest (presence:selftest): Inhaber, Sperre beim Speichern, System ohne Sperre, Übernehmen, abgeschaltet – Transaktion wird zurückgerollt
- `static setActor(?int $userId): void`
- `static take(string $type, string $id, int $userId): bool` – Sperre übernehmen: eigener Präsenz-Eintrag (muss bestehen) rückt vor den bisherigen Inhaber

### `EditLocked` (class, `app/EditLocked.php`)

Speichern abgelehnt – jemand anderes bearbeitet das Objekt gerade (Core\EditLock). HTTP 423 Locked.

- `__construct(string $message, array $holder = [])`

### `EditorNotes` (class, `app/EditorNotes.php`)

Redaktionsnotizen „[# … #]“ – versteckte Hinweise der Redaktion mitten im Inhalt, z. B. „[# bitte ergänzen: Seminartermine #]“.

Konstanten: `RX`

- `static badge(string $note): string` – Hinweis-Element der Redaktionsansicht
- `static decorate(string $html): string` – Ansicht der Redaktion: Notizen im sichtbaren Text als Hinweis „Notiz: …“ zeigen (nicht in Attributen, Skripten, Titel).
- `static find(?string $s): array` – Texte aller Notizen (ohne HTML, Leerraum zusammengefasst) – außerhalb von Code-Bereichen
- `static has(?string $s): bool`
- `static label(string $inner): string` – Notiztext für Anzeige/Übersicht: ohne Tags und Entities, Leerraum zusammengefasst
- `static publicHtml(string $html): string` – Ganze Seite für Besucher: Notizen überall entfernen (Text, Attribute, Meta-Angaben, JSON-LD, Daten-Skripte)
- `static selftest(): array`
- `static strip(?string $s): string` – Notizen entfernen – Klartext oder HTML (Rich Text). Tags innerhalb einer Notiz bleiben stehen (die Struktur bleibt
- `static stripData(mixed $v): mixed` – Notizen in verschachtelten Daten (Blockdaten, Listen) entfernen – Bild-/Rahmen-Einstellungen bleiben unberührt

### `EmailChange` (class, `app/EmailChange.php`)

Anmeldedaten selbst ändern: E-Mail-Adresse mit Bestätigung, Hinweis-E-Mails zu Adresse und Passwort (Konto → „Anmeldedaten“).

Konstanten: `HOURS`, `PER_HOUR`

- `static adminChange(array $target, string $newEmail, ?string $by): ?string` – Adresse direkt ändern (Benutzer & Rollen, CLI user:email) – ohne Bestätigung, mit Hinweis an beide Adressen.
- `static cancelByToken(array $row): bool` – „Das war ich nicht“ (Link an die bisherige Adresse): abbrechen und alle Sitzungen des Kontos beenden
- `static cancelOwn(array $user): bool` – Abbrechen im Konto (angemeldet) – Links werden ungültig
- `static compose(array $user, string $kind, array $v): array`
- `static confirm(array $row): ?array` – Neue Adresse übernehmen (Link aus der E-Mail an die neue Adresse). Prüft in einer Transaktion erneut, ob die Änderung
- `static ensureTable(Database $db): void`
- `static find(string $kind, string $token): ?array` – Änderung zum Token ($kind confirm|cancel) – nur, wenn sie noch gilt; sonst null (keine Unterscheidung nach außen:
- `static get(int $id): ?array`
- `static hasPassword(array $row): bool` – Hat das Konto ein eigenes Passwort? ('!passkey' = nur Passkey aus einer Einladung, '!network' = Schatten-Konto)
- `static hash(string $kind, string $token): string` – Hash des Tokens – an Zweck (confirm|cancel) und Website gebunden
- `static hours(): int` – Gültigkeit in Stunden (config 'email_change_hours')
- `static invalid(string $email, string $current): ?string` – Gültige, freie Adresse? @return string|null Fehlertext (ohne Auskunft, ob die Adresse vergeben ist)
- `static log(string $action, ?string $by, string $detail): void`
- `static mail(string $to, array $user, string $kind, array $v): array` – E-Mail zu einem Ereignis ($kind confirm|notice|taken|changed|admin|password|reset|reset-network|reset-done) in der Sprache des Kontos.
- `static newToken(): string`
- `static passwordChanged(array $user, bool $first = false, ?string $by = null): void` – Hinweis an das Konto nach jeder Passwortänderung (auch erstes Passwort eines Passkey-Kontos, CLI user:password)
- `static pending(int $uid): ?array` – Offene Änderung des Kontos (auch abgelaufen – dann mit expired = true), sonst null
- `static request(array $user, string $newEmail): array` – Änderung anlegen und E-Mails senden. Vorher geprüft: Anmeldung, Passwort/Passkey, Begrenzung, invalid().
- `static resend(array $user): ?array` – „Erneut senden“: neuer Bestätigungslink (der vorige gilt nicht mehr), neue Frist. Der Abbrechen-Link an die bisherige
- `static selftest(): array` – Selbsttest (Konsole account:selftest): Token, Hash, Ablauf, Abbrechen, Adresse vergeben (keine Rückschlüsse), auth_ver.
- `static taken(Database $db, string $email, int $uid): bool` – Adresse von einem anderen Konto dieser Website belegt?
- `static url(string $kind, string $token): string`
- `static usable(array $row): bool` – Noch offen und gültig? (Frist, einmalig, Konto vorhanden und nicht gesperrt, Adresse des Kontos unverändert)
- `static wellFormed(string $token): bool`

### `Embeds` (class, `app/Embeds.php`)

Externe Videos (YouTube, Vimeo) datenschutzkonform einbinden.

Konstanten: `PROVIDERS`

- `static meta(array $v): array` – Lokales Vorschaubild + Titel (per Server-Proxy geholt und zwischengespeichert).
- `static parse(?string $url): ?array`
- `static playerUrl(array $v): string` – URL des Players (erst nach Einwilligung laden)
- `static registerProxy(): void` – Serverseitige Quellen im zentralen Proxy (nicht öffentlich abrufbar)
- `static script(): string` – <script> der Zwei-Klick-Lösung (resources/js/embed.js → public/assets/js/embed.js) – einmal je Seite, direkt nach dem
- `static watchUrl(array $v): string` – Link zum Video beim Anbieter (Alternative ohne Einbettung)

### **API** `Extension` (class, `app/Extension.php`)

Schnittstelle für eine aktive Erweiterung (wird an 'boot' übergeben).

- `__construct(string $name, string $dir, array $manifest)`
- `account(callable $fn, ?string $perm = null): self` – Konto (/admin/account): fn(array $user): ?array – Karte (Core\Slots::card) als eigener Abschnitt, z. B. persönliche Einstellungen der Erweiterung
- `adminAssets(callable $fn): self` – CSS/JS aus {dir}/assets (gebaut nach public/assets/ext/{name}) in der Verwaltung laden: fn(string $view): ['css/x.css', 'js/x.js'] – $view z. B. 'media'
- `adminPage(array $def): self` – Seite der Verwaltung anmelden (Core\AdminPages): ['href' => '/admin/…', 'label' => …, 'kind' => 'content'|'tool'|'settings'|'stats',
- `afterAdminResponse(callable $fn): self` – Arbeit nach der Antwort eines Verwaltungsaufrufs (z. B. wartende Hintergrund-Aufträge): fn(): ?callable.
- `asset(string $path): string` – Öffentliche Adresse einer Datei aus {dir}/public bzw. {dir}/assets (nach extensions:publish bzw. pnpm build): /assets/ext/{name}/…
- `blockPresets(callable $fn): self` – Vorlagen in der Blockauswahl des Editors (vorbelegte Blöcke): fn(): list<array{type: string, label: string, data: array,
- `blocks(array $defs, ?string $dir = null): self`
- `blocksBeforeSave(callable $fn): self` – Blöcke vor dem Speichern eines Seiten-Entwurfs (Pages::saveDraft – Editor, API/MCP, KI, Import) prüfen oder ändern:
- `blocksResolve(callable $fn): self` – Blöcke für Leser auflösen – Suche, REST/MCP, Export lesen die Blöcke einer Seite nach diesem Filter (gerendert wird über die
- `command(string $name, string $description, callable $run): self`
- `csp(callable $fn): self` – Quellen zur Website-CSP ergänzen (je Anfrage, auch bei Treffern im Seiten-Cache): fn(): ['script-src' => [...], 'frame-src' => [...]]
- `dashboard(callable $fn, ?string $perm = null): self` – Übersicht der Verwaltung (/admin, Core\Dashboard\Dashboard): fn(array $user): [
- `docs(string $book, array $chapters): self` – Kapitel im Handbuch ('manual', Format wie kits/{name}/docs/manual.php → 'chapters') bzw. im Entwicklerhandbuch
- `ensureTables(bool $force = false): array` – Tabellen (table()) angleichen – nur bei geändertem Fingerabdruck bzw. $force (migrate). @return array<string, string> Name → created|altered|unchanged
- `feature(string $key, string $label, array $permissions = [], bool $default = true): self` – Funktion je Website; $default = false: aus, bis die Konfiguration sie mit 'features' => [$key => true] einschaltet
- `filter(string $name, callable $fn): self` – Filter an einer festen Stelle des Cores (Core\Filters::ALL): fn(mixed $wert, array $ctx): mixed – neuer Wert, null =
- `footerLinks(callable $fn): self` – Links für die Rechtliches-Zeile im Fußbereich der Themes (footer_links()): fn(): [['label' => …, 'href' => …], …]
- `frontendTool(array $def): self` – Werkzeug für das Bearbeiten auf der Website (Core\FrontendTools): Knopf in der Werkzeugleiste ('placement' => 'main') bzw.
- `health(callable $fn): self` – Prüfungen für `php bin/console health`: fn(): ['Bezeichnung' => true|false|null] (null = Warnung, kein Deploy-Fehler)
- `htmlFilter(callable $fn): self` – HTML einer Website-Seite nachbearbeiten: fn(string $html, array $ctx): string – $ctx: page, editing, loggedIn, status
- `inbox(callable $fn): self` – Eingangs-Tabellen, die die Erweiterung verantwortet (z. B. Buchungen): fn(array $t): ?array – null = nicht zuständig, sonst [
- `mailPurpose(string $key, string $label, string $help = ''): self` – Eigener Zweck für den E-Mail-Versand (Core\Mail\Purposes) – die Website wählt dafür ein Konto (Grundeinstellungen →
- `mailTransport(string $scheme, string $label, callable $factory): self` – Eigener Versandweg für E-Mail-Konten, z. B. die API eines Newsletter-Dienstes: fn(array $konto): Symfony\Component\Mailer\Transport\TransportInterface.
- `mediaAccess(callable $fn): self` – Zugriff auf Dateien geschützter Medien-Pools (Core\MediaPools, Adresse /geschuetzt/…): fn(array $ctx, Http\Request $r): bool|Http\Response
- `mediaChecks(callable $fn): self` – Filter unter „Prüfen“ in der Mediathek: fn(): ['schlüssel' => ['label' => …, 'icon' => Symbolname, 'where' => SQL-Bedingung auf
- `mediaJson(callable $fn): self` – Zusatzangaben je Datei in der Mediathek-API (Media::toJson → ext.{name}): fn(array $m): ?array
- `mediaPanel(callable $fn, ?string $perm = null): self` – Mediathek (eine Datei, „Informationen“): fn(array $m): ?array – Karte (Core\Slots::card) als eigener Abschnitt
- `mediaPoster(callable $fn): self` – Vorschaubild für ein Video ohne eigenes Poster (Bild-ID aus der Mediathek): fn(array $m): ?int
- `mediaTypes(array $defs): self` – Weitere Dateitypen der Mediathek: ['video/webm' => ['ext' => 'webm', 'magic' => Dateianfang (optional), 'label' => 'WebM']]
- `migration(int $version, callable $step): self`
- `nav(string $href, string $label, string $icon = 'ext', ?string $perm = null, string $place = 'main', array $opts = []): self` – Kurzform für adminPage(): Eintrag in der Verwaltung. $place: 'main' = Hauptmenü, 'admin' = Abschnitt „Administration“ –
- `on(string $event, callable $fn): self` – Ereignis des Cores abonnieren. Typisiert (empfohlen): on(Core\Events\PageSaved::class, fn(PageSaved $e) => …) – Seiten:
- `pageAccess(array $def): self`
- `pageList(callable $fn, ?string $perm = null): self` – Seitenbaum (Verwaltung → Seiten) je Seite: fn(array $page): [
- `pagePanel(callable $fn, ?string $perm = null): self` – Seiteneinstellungen (Verwaltung → Seiten → Seite): fn(array $page): ?array – Karte in der Seitenleiste (Core\Slots::card:
- `permissions(string $group, array $perms): self`
- `proxy(string $key, array $def): self`
- `publicDir(): string` – Öffentlicher Ordner der Erweiterung: public/assets/ext/{name} (Core\PublicPaths)
- `pushEvent(string $key, string $label, ?string $perm = null, array $o = []): self` – Ereignis für Push-Benachrichtigungen der Verwaltung (Core\Push): erscheint unter Konto → Benachrichtigungen bei allen, die
- `routes(callable $cb): self`
- `runMigrations(): void` – Offene Datenbank-Schritte ausführen (Stand je Website in den Einstellungen)
- `table(string $name, callable $define): self` – Tabelle der Erweiterung deklarativ beschreiben (Core\Db\Table): fn(Table $t) => $t->id()->column(…)->index(…).
- `tableActions(callable $fn, ?string $perm = null): self` – Datentabelle (Verwaltung → Daten → Tabelle): fn(array $table): ?array – [
- `toolbar(callable $fn): self` – Redaktions-Werkzeugleiste auf der Website (Core\Toolbar, nur angemeldet): fn(array $bar): [

### **API** `Extensions` (class, `app/Extensions.php`)

Erweiterungen (Module) – zusätzlich zu Core und Theme.

Konstanten: `UI_KEY`

- `static accountSections(array $user): string` – Konto: Abschnitte aktiver Erweiterungen (Extension::account) – escapte Karten
- `static activate(string $name): array` – In der Verwaltung einschalten: Schalter setzen und sofort starten (Datenbank-Schritte und 'install' laufen jetzt).
- `static active(): array`
- `static adminHead(string $view): string` – <link>/<script>-Tags der aktiven Erweiterungen für eine Verwaltungsansicht (Layout, nur 'self' → CSP unverändert)
- `static adminNav(string $place = 'main'): array` – Veraltet (seit Core\AdminPages): Menüeinträge der Erweiterungen [href, label, key, sichtbar] – nur Inhalte/Werkzeuge,
- `static afterAdminResponse(): void` – Nach der Antwort eines Verwaltungsaufrufs: fällige Arbeiten der Erweiterungen (billige Prüfung jetzt, Arbeit nach
- `static available(): array` – Alle installierten Erweiterungen [name => manifest + dir]
- `static blockPresets(array $types): array` – Vorlagen für die Blockauswahl (Extension::blockPresets), nur für vorhandene Blocktypen
- `static boot(): void` – Aktive Erweiterungen dieser Website starten (nach Theme, vor dem Routing)
- `static commands(): array` – CLI-Befehle: [name => [beschreibung, callable]]
- `static compatible(array $m): bool`
- `static configured(?Config $cfg = null): array` – Konfiguration der Website: 'extensions' => ['dav'] (an) bzw. ['dav' => false] (aus). @return array<string, bool>
- `static contributions(string $name): array` – Was eine aktive Erweiterung beiträgt (Menü, Rechte, Funktionen, Befehle, Blöcke, CSP) – für die Übersicht
- `static cspSources(): array` – Zusätzliche CSP-Quellen der aktiven Erweiterungen je Direktive. Erlaubt sind nur Hosts/Schemes (https://…, *.host);
- `static dashboard(array $user): array` – Beiträge zur Übersicht (Extension::dashboard): ['tiles' => [...], 'cards' => ['x-{erweiterung}-{schlüssel}' => spec]].
- `static deactivate(string $name): void` – In der Verwaltung abschalten – nicht destruktiv: Daten bleiben, die Erweiterung startet ab der nächsten Anfrage nicht mehr
- `static docs(string $book): array` – Kapitel der Erweiterungen für 'manual' bzw. 'technical' (Dateien liegen im Erweiterungsordner)
- `static emit(Events\Event|string $event, mixed ...$args): void` – Ereignis an alle Erweiterungen melden (Fehler einer Erweiterung brechen den Ablauf nicht ab).
- `static enabled(): array` – Namen der Erweiterungen, die auf dieser Website laufen sollen (Konfiguration vor Verwaltung)
- `static enabledForSite(string $site, string $name): bool` – Ist die Erweiterung auf einer (anderen) Website eingeschaltet? Konfiguration, sonst Schalter der Verwaltung dieser Website
- `static filter(string $name, mixed $value, array $ctx = []): mixed` – Filter anwenden (Extension::filter): Wert durch alle Erweiterungen reichen. null = unverändert, false = verworfen
- `static filterBlocksBeforeSave(array $blocks, array $ctx = []): array` – Blöcke vor dem Speichern (Extension::blocksBeforeSave) – $ctx: page_id, user_id
- `static filterHtml(string $html, array $ctx = []): string`
- `static filters(string $name): bool` – Hat eine aktive Erweiterung einen Filter für diesen Namen? – teure Vorbereitung nur dann
- `static footerLinks(): array` – Links der aktiven Erweiterungen für den Fußbereich (Rechtliches): [['label' => …, 'href' => …], …]
- `static health(): array` – Zeilen für `health`: ['Bezeichnung' => true|false|null]
- `static healthOf(string $name): array` – Prüfungen einer aktiven Erweiterung (Extension::health)
- `static inactiveCommand(string $cmd): ?string` – CLI: Befehl einer installierten, hier aber nicht aktiven Erweiterung (Manifest 'commands')? → Name der Erweiterung
- `static inbox(array $t): array` – Einstellungen einer Erweiterung für eine Eingangs-Tabelle (Extension::inbox) – erste zuständige Erweiterung gewinnt.
- `static isActive(string $name): bool`
- `static listens(string $event): bool` – Hört eine aktive Erweiterung auf das Ereignis? – teure Angaben (z. B. Eintrag neu laden) nur dann berechnen.
- `static lock(string $name): ?array` – Legt die Konfiguration die Erweiterung fest? ['file' => …, 'value' => bool] oder null
- `static mailPurposes(): array` – E-Mail-Zwecke der Erweiterungen (Extension::mailPurpose): Schlüssel → {label, help, owner}
- `static mailTransport(string $scheme): ?array` – Versandweg einer Erweiterung (Extension::mailTransport) bzw. null; ohne Schema: alle (schema → {label, factory, owner})
- `static mailTransports(): array`
- `static mediaAccess(array $ctx, Http\Request $r): Http\Response|bool` – Zugriff auf eine Datei eines geschützten Pools: erste Erweiterung, die true oder eine Antwort liefert, entscheidet
- `static mediaChecks(): array` – Filter unter „Prüfen“: ['schlüssel' => ['label', 'icon', 'where', 'params', 'kind', 'extension']]
- `static mediaJson(array $m): array` – Zusatzangaben je Datei für Media::toJson: [name => array]
- `static mediaPanels(array $m): array` – Mediathek: Abschnitte aktiver Erweiterungen für eine Datei (Extension::mediaPanel) – Liste fertiger, escapter HTML-Abschnitte
- `static mediaTypes(): array` – Zusätzliche Dateitypen der Mediathek: [mime => ['ext', 'magic', 'label']]
- `static meta(string $name): array` – Angaben für „Funktionen & Erweiterungen“: Manifest, composer.json, Lizenz, Risiko (übersetzt)
- `static pageList(array $page): array` – Seitenbaum: Hinweise und Kontextmenü-Einträge aktiver Erweiterungen für eine Seite (Extension::pageList).
- `static pagePanels(array $page): string` – Seiteneinstellungen: Karten aktiver Erweiterungen für die Seitenleiste (Extension::pagePanel) – Karte (Slots::card) oder Altform-HTML
- `static permissions(): array` – Zusätzliche Rechte-Gruppen: [Gruppe => [recht => Bezeichnung]]
- `static poster(array $m): ?int` – Bild-ID eines Vorschaubilds für ein Video (erste Erweiterung mit Ergebnis)
- `static requirements(string $name): array` – Voraussetzungen ohne Start (Manifest 'requirements') plus Core-/PHP-Version. @return array<string, ?bool>
- `static resolveBlocks(array $blocks, array $ctx = []): array` – Blöcke für Leser auflösen (Extension::blocksResolve) – $ctx: page, draft, purpose
- `static routeReport(): array` – Verwaltungsrouten aktiver Erweiterungen für extensions:list: [name => ['routes' => n, 'perm' => n, 'legacy' => [...],
- `static routes(Http\Router $r): void` – Routen aller aktiven Erweiterungen (vor der Seiten-Route registrieren)
- `static rowActions(array $row, int $id): array` – Aktionen je Zeile für einen Eintrag ({id} ersetzt, Pfade geprüft)
- `static tableActions(array $t): array` – Datentabelle: Knöpfe im Kopf und Aktionen je Zeile (Extension::tableActions), Pfade geprüft, {table} ersetzt; {id} ersetzt rowActions().
- `static toolbar(array $bar): array` – Werkzeugleiste der Website: Menüeinträge, Skripte und Hinweis zum Veröffentlichen aktiver Erweiterungen (Extension::toolbar).
- `static tr(string $name, string $text): string` – Text einer Erweiterung übersetzen – auch wenn sie nicht läuft (dann aus {dir}/lang/{locale}.php)
- `static ui(): array` – Schalter der Verwaltung (sys.extensions_ui). @return array<string, bool>
- `static usage(string $name): array` – „Wird verwendet von …“: Manifest 'usage' plus Seiten mit Blöcken der Erweiterung (nur aktive)

### `ExtensionsSelfTest` (class, `app/ExtensionsSelfTest.php`)

php bin/console extensions:selftest – Schnittstellen für Erweiterungen ohne echte Erweiterung prüfen: - Verwaltungsseiten (Core\AdminPages): Arten, Ableitung alter nav()-Aufrufe, Menü nur für content/tool, Sammelseite, Tabelle („glossar“), Rechte/Funktion/visible, match(), ungültige Angaben;

- `static run(): array`

### `Favorites` (class, `app/Favorites.php`)

Favoriten je Benutzer (Spalte users.favorites, JSON-Liste [{url, title, icon, added}], höchstens 30). Jede per GET aufrufbare Seite der Verwaltung kann gemerkt werden – inkl. Query und #Hash (z. B. /admin/data/termine/12, /admin/settings#hero, /admin/media#m123). Benutzer gehören zur Website,

Konstanten: `MAX`, `TITLE_MAX`

- `static add(int $uid, string $url, string $title, string $icon): ?string`
- `static all(int $uid): array`
- `static icon(string $i): string` – Symbol: Schlüssel eines Menüpunkts (data-ico, z. B. „data“) oder ein kurzes Zeichen (Symbol einer Datentabelle)
- `static migrateBasisAnchors(): int` – Einmalige Migration (migrate): Im Handbuch des Themes „basis“ stehen die Grundeinstellungen jetzt unter #einstellungen,
- `static move(int $uid, string $url, int $dir): void` – Einen Favoriten um eine Position verschieben (-1 hoch, 1 runter) – Formular ohne JavaScript
- `static normalize(string $url): ?string` – Lokale Verwaltungsadresse „/admin…?query#hash“ oder null.
- `static remove(int $uid, string $url): void`
- `static rename(int $uid, string $url, string $title): void`
- `static reorder(int $uid, array $order): void` – Neue Reihenfolge (Liste von URLs; fehlende bleiben hinten in bisheriger Reihenfolge)
- `static save(int $uid, array $list): void`
- `static title(string $t): string`

### `FeatureInfo` (class, `app/FeatureInfo.php`)

Beschreibungen für „Administration → Funktionen & Erweiterungen“: Gruppe, Kurzbeschreibung, „Was passiert beim Einschalten“ (Menü, externe Anfragen, Hintergrund/Cron, Website, gespeicherte Daten), Sicherheitshinweis und Vorsicht-Hinweise. Rechte je Funktion kommen aus Features::catalog(), Abhängigkeiten aus Features::REQUIRES.

- `static effectLabels(): array` – Bezeichnungen der Wirkungen in „Was passiert beim Einschalten“
- `static get(string $key): array` – Beschreibung einer Funktion: group, desc, effects (menu|external|cron|frontend|data => Text), risk (Sicherheitshinweis:
- `static groups(): array` – Gruppen in Anzeigereihenfolge: Schlüssel => [Bezeichnung, Symbol]
- `static risky(string $key): bool` – Sicherheitsrelevant (Bestätigung mit Risiko-Text und Passwort beim Einschalten)?

### `FeatureLog` (class, `app/FeatureLog.php`)

Protokoll „Funktionen & Erweiterungen“ (Tabelle feature_log je Website): wer hat wann welche Funktion bzw. Erweiterung geschaltet oder die Freigabe geändert. In Netzwerk-Installationen zusätzlich im Netzwerk-Protokoll (network_log).

- `static add(string $kind, string $target, ?bool $old, ?bool $new, string $detail = ''): void` – $kind: feature | extension | delegate | release
- `static recent(int $n = 10): array`

### `FeatureRelease` (class, `app/FeatureRelease.php`)

`php bin/console features:release [--site=key] [--dry-run] [--only=features|extensions]`

- `static diff(string $a, string $b): array` – Einfacher Zeilen-Diff (LCS) mit 2 Zeilen Kontext
- `static run(array $args, callable $out): int`

### **API** `Features` (class, `app/Features.php`)

Funktionsumfang je Website.

Konstanten: `OFF_BY_DEFAULT`, `UI_KEY`, `DELEGATE_KEY`, `SWITCH_KEYS`, `REQUIRES`, `PRESETS`

- `static all(): array` – Wirksamer Zustand aller Funktionen
- `static allowsBlock(string $type): bool` – Blocktyp für die Redaktion freigegeben?
- `static allowsPath(string $path): bool` – Pfade, die eine Funktion voraussetzen (Website-Routen ohne Rechteprüfung)
- `static allowsPermission(string $perm): bool` – Darf ein Recht auf dieser Website überhaupt vergeben werden?
- `static canManage(): bool` – Darf das angemeldete Konto Funktionen und Erweiterungen dieser Website schalten?
- `static canView(): bool` – Seite „Funktionen & Erweiterungen“ sichtbar (im Netzwerk: Website-Administration liest mit)
- `static catalog(): array` – Katalog: Schlüssel => [Bezeichnung, zugehörige Rechte]
- `static configFile(string $top, ?string $site = null): string` – Datei, die einen Schlüssel der obersten Ebene für diese Website setzt (relativ zu ROOT)
- `static delegated(): bool` – Hat die Netzwerk-Administration der Website-Administration das Schalten freigegeben?
- `static dormant(string $key): bool` – Eingestellt „an“, wirkt aber nicht, weil die Voraussetzung (REQUIRES) aus ist
- `static flush(): void` – Zwischenspeicher verwerfen (nach dem Umschalten in der Verwaltung, Erweiterungen)
- `static integrator(): bool` – Angemeldetes Konto steht in 'integrators' (z. B. die Agentur). In Installationen mit mehreren Websites gilt die Adresse nur auf der
- `static integratorEmail(string $email): bool` – Steht die Adresse in 'integrators'? Solche Konten legt nur an bzw. benennt nur um, wer selbst Integrator ist.
- `static lock(string $key): ?array` – Legt die Konfiguration den Wert fest? ['via' => 'features'|'preset', 'file' => 'config/sites/kunde.php', 'value' => bool] oder null.
- `static networked(): bool` – Netzwerk-Installation (mehrere Websites): Schalten nur mit Freigabe der Netzwerk-Administration
- `static on(string $key, bool $respectIntegrator = true): bool` – Ist die Funktion auf dieser Website verfügbar? (Integratoren: immer)
- `static overview(): array` – Übersicht für die Systeminfo: [key => [label, on]]
- `static register(string $key, string $label, array $permissions = [], bool $default = true): void` – Erweiterungen können eigene Funktionen anmelden (Standard: an; $default = false → erst per 'features' => [$key => true])
- `static setUi(string $key, bool $on): void` – UI-Schalter einer Funktion setzen (Chat/Externe Quellen: deren eigener Schalter)
- `static ui(): array` – Schalter der Verwaltung (sys.features_ui) – ohne Datenbank/Einstellungen: []

### `Ffmpeg` (class, `app/Ffmpeg.php`)

ffmpeg/ffprobe finden und aufrufen (optional – ohne ffmpeg läuft alles wie bisher).

- `static available(): bool` – ffmpeg und ffprobe gefunden und proc_open erlaubt?
- `static bin(string $name, string $override = ''): string` – Wirksamer Aufruf für 'ffmpeg' bzw. 'ffprobe' ('' = nicht gefunden): absoluter Pfad oder der Name (über PATH).
- `static canExec(): bool` – proc_open erlaubt (nicht in disable_functions)?
- `static configured(string $name): string` – Konfigurierter Pfad ('' = automatisch suchen)
- `static env(): array` – Schlanke Umgebung für Unterprozesse (kein Erbe von Geheimnissen aus $_ENV)
- `static exec(array $argv, int $timeout = 30, int $nice = 0): array` – Programm mit Argument-Array ausführen (synchron, mit Zeitlimit): [exit, stdout, stderr].
- `static forget(): void` – Für Tests: Zwischenspeicher der Anfrage leeren
- `static hasNice(): bool` – `nice` aufrufbar? (per Aufruf geprüft, zwischengespeichert wie die Programme)
- `static health(): array` – Zeile für `health`: Warnung, nie Fehler (ffmpeg ist optional)
- `static hint(): string` – Hinweis, warum nichts gefunden wurde (Status, health)
- `static misconfigured(string $name, string $override = ''): bool` – Konfigurierter Pfad, der beim Aufruf nicht funktioniert? (Statusanzeige – evtl. wurde ein anderer gefunden)
- `static openBasedir(): string` – Ist open_basedir gesetzt? (dann keine Dateiprüfungen außerhalb)
- `static pdfAvailable(): bool` – pdftoppm (poppler-utils) gefunden und proc_open erlaubt? → Vorschaubild der 1. Seite von PDF-Dokumenten
- `static pdfHealth(): array` – Zeile für `health`: pdftoppm (poppler-utils) für PDF-Vorschaubilder – Warnung, nie Fehler
- `static pdfText(string $file): string` – Text eines PDFs (UTF-8, höchstens 200 Seiten, ohne Seitenumbruch-Zeichen) – leer bei Fehler, verschlüsselten oder gescannten PDFs
- `static pdfTextAvailable(): bool` – pdftotext (poppler-utils) gefunden? → Volltext von PDF-Dokumenten für die Suche (Media::pdfText)
- `static refresh(): void` – Nächste Aufrufe prüfen neu (CLI: health, media:thumbs) und schreiben den Zwischenspeicher
- `static version(string $name = 'ffmpeg', string $override = ''): string` – Versionszeile des gefundenen Programms („ffmpeg version 7.1.1 …“)
- `static works(string $bin, string $name = 'ffmpeg'): bool` – Funktioniert genau dieser Aufruf? (z. B. Pfad aus einer anderen Konfiguration, Core\AI\Transcriber)

### **API** `Fields` (class, `app/Fields.php`)

Schema-basierte Felder – gemeinsame Grundlage für - zentrale Einstellungen des Themes, Grundeinstellungen (System), - Block-Daten (Editor.js-Tools werden aus demselben Schema generiert),

Konstanten: `TYPES`, `MT_POS`, `MT_WIDTH`, `MT_VIEW`, `MT_MAX`

- `static clean(array $f, mixed $raw): array`
- `static defaults(array $fields): array` – Standardwerte eines Schemas als flaches Array
- `static emptyValue(array $f): mixed`
- `static forTranslation(array $fields, array $source = []): array` – Felder für die Übersetzungsansicht: nur übersetzbare (ohne Pflicht, Vorgabe = Wert der Standardsprache als Platzhalter),
- `static linkChip(string $v): string` – Anzeige des gewählten Linkziels (Art · Titel · Adresse) mit Entfernen-Knopf
- `static mediaPreview(?int $id): string`
- `static mediaText(mixed $raw): array` – Ein Abschnitt von „Medien + Text“: title (Zwischenüberschrift, optional, nur bei mehreren Abschnitten), text (Rich-Text,
- `static mediaTextItem(int $mid, string $name): string` – Ein Medium in der Leiste von „Medien + Text“ (auch für admin.js: gleiche Struktur)
- `static mediaTextSections(mixed $raw, bool $multi = true): array` – Wert von „Medien + Text“: Liste von Abschnitten (gespeichert als JSON). Nimmt Formular, JSON, eine Liste oder einen einzelnen
- `static msg(string $text, array $params = []): string`
- `static options(array $f): array` – Optionen: ['a'=>'A'] oder ['a','b']
- `static pageOptions(): array`
- `static renderField(array $f, mixed $value, array $errors, string $prefix, string $path = ''): string`
- `static renderForm(array $fields, array $values, array $errors = [], string $prefix = 'f', string $path = ''): string`
- `static renderGroups(array $fields, array $values, array $errors = [], string $prefix = 'f'): string` – Einstellungen als gruppierte Listen (Grundeinstellungen, angelehnt an die macOS-Systemeinstellungen): jede Zwischenüberschrift
- `static renderLink(string $id, string $name, string $v, string $attrs = ''): string` – Link: Eingabefeld (Adresse von Hand) + „Auswählen …“ (Linkauswahl, resources/js/_links.js) + lesbare Anzeige des Ziels.
- `static sanitize(array $fields, array $input, array $current = [], string $path = ''): array` – Bereinigt & validiert Eingaben.
- `static translatable(array $f): bool` – Wird das Feld je Sprache gepflegt? 'translate' => true|false überschreibt; Standard: Textfelder ja, alles andere nein.

### `Filters` (class, `app/Filters.php`)

Filter für Erweiterungen (Extension::filter): Werte des Cores an festen Stellen ändern, ergänzen oder verwerfen.

Konstanten: `ALL`

- `static known(string $name): bool` – Ist der Name erlaubt? Bekannte Filter des Cores oder eigene einer Erweiterung (ext:{name}.…)

### `Fonts` (class, `app/Fonts.php`)

Schriften aus dem Google-Fonts-Katalog installieren und selbst ausliefern (Grundeinstellungen → Schriften, CLI fonts:*).

Konstanten: `API`, `CDN`, `LICENSES`, `DEFAULT_SUBSETS`, `CATEGORIES`, `BUDGET_KB`, `PREFIX`, `PENDING`

- `static canManage(): bool` – Schriften installieren/entfernen: wirkt auf ALLE Websites der Installation – daher nur Integratoren/Netzwerk-Konten
- `static catalog(bool $refresh = false): array` – Katalog der Google-Fonts-Familien mit erlaubter Lizenz (1 Tag zwischengespeichert).
- `static cleanFamily(string $family): string` – Familienname für CSS und Anzeige: nur Buchstaben, Ziffern, Leerzeichen, Bindestrich, Punkt, Apostroph-frei
- `static console(string $cmd, array $args): int`
- `static covers(array $have, array $need): bool` – Erfüllt die Installation ($have) die Anforderung ($need)?
- `static cssUrl(string $id): string` – Adresse von font.css (mit Versionsparameter)
- `static designOptions(array $def): array` – Zusätzliche Optionen für Tokens vom Typ „font“: [installed:{id} => label, stack, href, kb, installed]
- `static detail(string $id): ?array` – Katalogeintrag + Details (Dateien je Schnitt, unicode-range, Achsen der variablen Fassung)
- `static dir(string $sub = ''): string` – public/assets/fonts/installed (Core\PublicPaths)
- `static ensure(array $fonts, bool $dryRun = false, string $by = 'system'): array` – Schriften sicherstellen: fehlende installieren, unvollständige erweitern (mit der vorhandenen Installation
- `static get(string $id): ?array`
- `static health(): array` – Zeile für `health` und die Übersicht: fehlende Kit-Schriften als Hinweis (nie Fehler – es gilt die Ersatzschrift)
- `static install(string $id, array $opt = [], string $by = ''): array` – Schrift installieren (ersetzt eine vorhandene Installation derselben Familie).
- `static installed(): array` – Installierte Schriften [id => Metadaten]
- `static isWoff2(string $bin): bool`
- `static licensesSection(): string` – Abschnitt „Installierte Schriften“ für die Seite Lizenzen & Danksagungen (help/licenses.php) – leer ohne Schriften
- `static manifest(): array`
- `static mergeSpec(array $a, array $b): array` – Zwei Anforderungen derselben Schrift vereinen (Kursive, Stärken, Zeichensätze; verschiedene Achsen → 'full')
- `static missing(): array` – Benötigte, aber nicht (vollständig) installierte Kit-Schriften der aktuellen Website → IDs
- `static needed(): array` – Kit-Schriften, die die aktuelle Website braucht: Schriften mit 'fontsource' in den aktuellen Werten, den
- `static pageBytes(array $f): int` – Bytes, die eine Seite höchstens lädt (lateinische Zeichensätze aller Schnitte)
- `static previewFace(array $f): string`
- `static previewFile(string $id): ?string` – Pfad zu einer zwischengespeicherten woff2-Datei (lateinisch, Normalschnitt) für die Vorschau – oder null
- `static primaryFile(array $f): ?string` – Datei zum Vorladen (lateinisch, Normalschnitt 400 bzw. nächstliegend)
- `static recordUsage(string $site, string $theme, array $values, array $tokens, array $fonts = []): void` – Verwendung merken (nach dem Speichern im Style-Editor, fonts:sync): Website · Theme → installierte Schriften und
- `static registerProxy(): void` – Proxy-Quelle (nur serverseitig) – aufgerufen von Proxy::sources()
- `static remove(string $id, bool $force = false): array` – Schrift entfernen; in Verwendung nur mit $force
- `static requestSync(): void` – Abgleich vormerken (Kit gewählt/gewechselt, Erststart) – läuft beim nächsten Aufruf der Verwaltung bzw. mit fonts:sync
- `static runPending(): void` – Vorgemerkten Abgleich ausführen (AdminController::view – nie bei Seitenaufrufen der Besucher)
- `static search(string $q, string $category = '', int $limit = 48): array` – Suche im Katalog (Name, optional Kategorie); ohne Begriff: beliebte Familien
- `static setPreload(string $id, bool $on): bool`
- `static spec(array $f): ?array` – Anforderung aus einer Schrift-Angabe des Kits (design.php → fonts → ['fontsource' => id, 'styles', 'variable',
- `static stack(array $f): string` – CSS-Schriftstapel: 'Familie', passende Ersatzschriften
- `static stamp(): int`
- `static syncSite(bool $dryRun = false, string $by = 'system'): array` – Kit-Schriften der aktuellen Website abgleichen (installieren, Verwendung merken)
- `static url(string $path): string` – /assets/fonts/installed/…
- `static usage(string $id): array`
- `static validId(string $id): bool`

### `FormCrypto` (class, `app/FormCrypto.php`)

Gesundheitsdaten (Art. 9 DSGVO): Anfragen werden mit libsodium „sealed box“ verschlüsselt gespeichert. Auf dem Server liegt NUR der öffentliche Schlüssel. Der geheime Schlüssel bleibt beim Betreiber (z. B. Passwortmanager) und wird

- `static envNames(): array` – Namen der Umgebungsvariablen in Prüf-Reihenfolge ([] = abgeschaltet)
- `static envSecret(): ?string` – Geheimer Schlüssel aus der Hosting-Umgebung – nur wenn er zum öffentlichen Schlüssel passt
- `static envStatus(): array` – Zustand für die Systemseite: ['state' => off|missing|mismatch|active, 'name' => Variable]
- `static fingerprint(): string`
- `static generate(): array`
- `static keyMatches(string $secretB64): bool`
- `static open(string $payload, string $secretB64): ?array`
- `static publicKey(): ?string`
- `static ready(): bool`
- `static seal(array $data): string`

### **API** `Format` (class, `app/Format.php`)

Werte für die Anzeige formatieren – eine Stelle für Datum, Zahl, Größe, Dauer, Telefon, Adresse und Auszug (nach rex_formatter). Sprache: Website-Sprache (Lang::current()) bzw. ausdrücklich Format::for('en'); die Verwaltung nutzt Format::admin() (I18n::locale()).

- `static admin(): self` – Formatierer für die Verwaltung (Sprache der angemeldeten Person, I18n::locale())
- `bytes(int|float|null $bytes): string` – Dateigröße: unter 1 MB ganze KB (mindestens 1 KB), dann MB bzw. GB mit einer Nachkommastelle
- `currency(string|int|float|null $v, string $currency = 'EUR', ?int $decimals = 2): string` – Betrag: 19,90 € (de) bzw. €19.90; $decimals null = 2, bei ganzen Beträgen 0
- `date(DateTimeInterface|string|int|null $when, string $style = 'short'): string` – Datum: short (24.09.2026 / 24/09/2026), long (24. September 2026), weekday (Donnerstag), day_month (24. September)
- `datetime(DateTimeInterface|string|int|null $when, string $style = 'short'): string` – Datum und Uhrzeit „24.09.2026, 14:05“ (Stil des Datums wie date())
- `decimal(string|int|float|null $v, int $max = 2): string` – Zahl mit höchstens $max Nachkommastellen, ohne Nullen am Ende: 2,5 · 3 · 1.234,57
- `duration(int|float|null $seconds, bool $unit = true): string` – Dauer: 3:05 min bzw. 1:02:03 h; ohne Einheit 3:05 / 1:02:03 (z. B. für Player)
- `excerpt(?string $text, int $max = 160, bool $html = true): string` – Auszug als reiner Text: Tags entfernen (Rich-Text → Text, Entities aufgelöst), *Betonung*-Sternchen entfernen, Leerraum zusammenfassen, an einer Wortgrenze
- `static for(?string $lang = null): self` – Formatierer für eine Sprache (null = Sprache der aufgerufenen Seite, Lang::current())
- `host(?string $url): string` – Host einer Adresse ohne „www.“: https://www.example.org/pfad → example.org
- `number(string|int|float|null $v, int $decimals = 0): string` – Zahl mit festen Nachkommastellen: 1.234,5 (de) bzw. 1,234.5
- `phone(?string $number): string` – Telefonnummer für die Anzeige (Standardsprache wie eingegeben, sonst international – phone_display())
- `relative(DateTimeInterface|string|int|null $when, ?int $now = null, string $unit = 'minute'): string` – Relative Angabe. $unit 'minute' (Standard): „gerade eben“, „vor 5 Min.“, „heute, 14:05“, „gestern, 09:12“, „vor 3 Tagen“
- `time(DateTimeInterface|string|int|null $when): string` – Uhrzeit 24-Stunden „14:05“

### `FormatSelfTest` (class, `app/FormatSelfTest.php`)

php bin/console format:selftest – Core\Format: Datum (Stile, DE/EN), Uhrzeit, relative Angaben (Minute/Tag), Zahlen, Beträge, Größen, Dauer, Host, Auszug – und dieselben Ausgaben wie die früheren Helfer (Media::humanSize, Links::ago, Dashboard::ago, MediaJobs::duration, Clamp::excerpt, date_local, Block-Filter „number“), die jetzt Format nutzen.

- `static run(): array`

### `Forms` (class, `app/Forms.php`)

Theme-Formulare (theme.php → 'forms', z. B. Rezept / Überweisung) – Kompatibilitätsschicht für Themes.

Konstanten: `PRIVACY_FIELD`

- `static def(string $key): ?array` – Theme-Definition, ergänzt um Titel/Einleitung/Erfolgstext der Tabelle (übersetzt)
- `static enabled(string $key): bool` – Angeboten? Schalter des Themes (z. B. rezept_aktiv) + Funktion „requests“ + (intern) Tabelle mit eingeschaltetem Formular
- `static externalUrl(string $key): string`
- `static fields(string $key): array` – Felder im Format der Theme-Vorlagen: name, label, type, required, options (Schlüssel => Text), width (half|full).
- `static mode(string $key): string` – 'internal' (eigenes Formular) oder 'external' (Link zu Dienst)
- `static submit(string $key, array $post, string $ip, array $files = []): array` – Einsendung über /anfrage/{form} (Theme-Formular) – geprüft, spamgeschützt und verschlüsselt wie jedes Eingangs-Formular.
- `static table(string $key): ?array` – Eingangs-Tabelle des Formulars

### `Fragments` (class, `app/Fragments.php`)

Kern-Fragmente (wie REDAXO-Fragmente): kleine Vorlagen, die der Kern mitbringt und jedes Kit nutzt. Kits sind eigenständige Projekte ohne Eltern/Kind-Vererbung – der Kern ist der Werkzeugkasten darunter.

Konstanten: `CORE_ONLY`, `CORE_VIEWS`, `LAYERS`

- `static accept(?string $kit = null, ?string $name = null): int` – Geänderte Originale als geprüft markieren (neue Prüfsumme merken). Rückgabe: Anzahl
- `static candidates(string $name, ?string $kit = null): array` – Kandidaten der Suchreihenfolge: [ebene => datei] (Nur-Kern-Fragmente: nur die Kern-Ebenen)
- `static coreDir(): string`
- `static coreFragments(): array` – Alle Kern-Fragmente (überschreibbare), die es als Datei im Kern gibt: [name => datei]
- `static devMode(): bool`
- `static exists(string $name, ?string $kit = null): bool` – Gibt es das Fragment (in irgendeiner Ebene)?
- `static find(string $name, ?string $kit = null): ?array` – Erste vorhandene Datei: ['name', 'file', 'layer'] oder null
- `static health(): array` – Prüfungen für health und die Übersicht (Technik & Betrieb): null = Hinweis (blockiert keinen Deploy).
- `static ignored(?string $kit = null): array` – Kit-Dateien für Nur-Kern-Fragmente (werden ignoriert): [name => datei]
- `static isCoreOnly(string $name): bool`
- `static overrides(?string $kit = null, bool $record = true): array` – Überschriebene Kern-Fragmente aller installierten Kits (bzw. eines Kits): Liste mit
- `static render(string $name, array $vars = [], ?string $kit = null): string` – Fragment rendern. $vars werden zu Variablen der Vorlage; dazu $options = Optionen des Kits

### `FrontendTools` (class, `app/FrontendTools.php`)

Werkzeuge für das Bearbeiten auf der Website (Werkzeugleiste, Core\Toolbar) – angemeldet von Funktionen des Cores (register) und Erweiterungen ($x->frontendTool([...])).

Konstanten: `MODES`, `ALL_MODES`

- `static all(): array` – Alle angemeldeten Werkzeuge (Core + aktive Erweiterungen)
- `static config(array $bar, array $tools): array` – JSON-Konfiguration für resources/js/_tools.js (<script type="application/json" id="cms-tools">)
- `static forBar(array $bar): array` – Werkzeuge für eine Werkzeugleiste (Core\Toolbar::context): nur angemeldet, nur mit Recht; Standard nur im Bearbeiten-Modus,
- `static mode(array $bar): ?string` – Bearbeiten-Art der Werkzeugleiste: 'page' (Seiten-Editor, auch Vorlage), 'entry' (Eintrag direkt im Text) oder null
- `static normalize(array $def, string $source = 'core', ?Extension $x = null): ?array` – Angaben prüfen – null, wenn id, label oder module fehlen bzw. ungültig sind
- `static register(array $def): void` – Werkzeug des Cores anmelden (Erweiterungen: Extension::frontendTool)
- `static reset(): void`
- `static shortcut(string $s): ?array` – Tastenkürzel prüfen und vereinheitlichen: „Alt+G“ → ['keys' => 'Alt+G' (aria-keyshortcuts), 'label' => '⌥G', 'code' => 'KeyG',
- `static viewing(array $bar): bool` – Ansehen mit Werkzeugleiste? Seite ohne Bearbeiten (auch Live-Fassung) bzw. Detailseite eines Eintrags (dort wechselt
- `static visible(array $t, array $bar): bool` – Darf der angemeldete Benutzer das Werkzeug in dieser Werkzeugleiste sehen?
- `static when(array $t, array $bar): ?string` – Wann gilt ein Werkzeug in dieser Werkzeugleiste? 'edit' | 'view' | 'both' (Eintrag: Werkzeug für beide Modi) | null.

### `Guide` (class, `app/Guide.php`)

„Hinweise zu diesem Projekt“: projektbezogene Anleitungen der Agentur für die Redaktion – Ergänzung zum Handbuch, sichtbar nur, wenn es welche gibt (Kapitel im Handbuch, Übersicht, Suche, Hinweis in Blöcken und Bereichen).

Konstanten: `MAX`, `IMAGES`, `AREAS`

- `static all(): array` – Alle Hinweise inkl. ausgeblendeter (für die Verwaltung) @return list<array>
- `static anchor(string $key): string` – Anker im Handbuch
- `static areaMatch(array $areas, string $section, ?string $table = null): bool` – Passt eine der Angaben „bereich“ (medien, daten/arbeiten …) zum Abschnitt der Verwaltung und zur Tabelle?
- `static author(): string` – Wer die Hinweise schreibt (theme.php → guide.author, sonst „der Agentur“)
- `static canEdit(): bool` – Darf der Benutzer Hinweise der Website bearbeiten?
- `static config(): array` – Einstellungen des Kits (theme.php → 'guide')
- `static delete(string $key): bool` – Website-Datei löschen (ein gleichnamiger Kit-Hinweis gilt dann wieder)
- `static exists(): bool`
- `static files(): array` – Dateien für die Signatur des Hilfe-Index (Core\AI\HelpIndex)
- `static find(string $key): ?array`
- `static forArea(string $section, ?string $table = null): array` – Hinweise zu einem Bereich der Verwaltung ($section = erstes Segment der Ansicht, $table = Handle der Datentabelle)
- `static forBlock(string $type): array` – Hinweise zu einem Blocktyp
- `static html(array $note): string` – HTML eines Hinweises (Bilder aus dem guide-Ordner über die Verwaltung)
- `static image(string $file): ?string` – Pfad eines Bildes aus dem guide-Ordner der Website bzw. des Kits (nur Dateiname, nur Bildformate)
- `static kitDir(): ?string` – Kit-Ordner (null: keiner oder abgeschaltet)
- `static markDone(int $userId, string $key): void` – Aufgabe für diese Person abhaken (beim Öffnen über „Lesen“)
- `static notes(): array` – Alle sichtbaren Hinweise, sortiert. Je Eintrag: key, anchor, title, source (kit|site), overrides (bool),
- `static parse(string $src, string $key): array` – Quelltext zerlegen (ohne Dateisystem – auch für den Selbsttest).
- `static plain(array $note, int $max = 0): string` – Klartext eines Hinweises (Suche)
- `static render(string $md, ?Closure $img = null): string` – Markdown-lite → HTML (Core\Support\Markdown): Überschriften im Text werden h4, eine Zeile nur mit ![Alt](datei)
- `static save(string $key, string $src): void` – Quelltext der Website-Datei speichern (legt den Ordner an)
- `static selftest(): array` – Selbsttest (php bin/console guide:selftest): Front Matter, Titel, Reihenfolge der Schlüssel, Escaping, Links,
- `static siteDir(): string` – Ordner der Website (muss nicht existieren)
- `static source(array $note): string` – Quelltext einer Datei (Website vor Kit)
- `static tasks(int $userId): array` – Hinweise mit „aufgabe: ja“, die diese Person noch nicht gelesen hat (Übersicht → Was ist zu tun?) @return list<array>
- `static title(): string` – Überschrift des Kapitels
- `static url(array $note): string`
- `static validKey(string $key): bool` – Erlaubter Dateiname (ohne .md)

### `HeaderActions` (class, `app/HeaderActions.php`)

Kopfbereich-Aktionen: Handlungsaufruf (CTA), zweite Aktion, Suche, Kontakt-Chip mit Öffnungsstatus, Sprachumschalter, Social-Symbole und Anmelde-Link – einheitlich einstellbar unter Verwaltung → Design → „Kopfbereich: Suche & Aktionen“, gestaltet vom Kit.

Konstanten: `DEFAULTS`, `LANG_MENU_FROM`

- `static config(): array` – Eingestellte Werte (Design) mit Voreinstellungen; Kits ohne Gruppe bekommen DEFAULTS
- `static designGroup(array $defaults = [], array $opt = []): array` – Gruppe für den Style-Editor. $defaults überschreibt DEFAULTS je Kit; $opt['exclude'] = Tokens, die das Kit nicht anbietet
- `static head(array $opt = []): string` – <link>/<script> für <head> – VOR dem CSS des Kits einbinden. Nur die benötigten Teile, gebündelt zu je EINER Datei
- `static hoursList(): array` – Öffnungszeiten kurz, gleiche Zeiten an Folgetagen zusammengefasst: [['days' => 'Mo–Fr', 'time' => '9:00–17:00'], …]
- `static lang(array $langs, string $class = ''): string`
- `static langStyle(): string` – Wirksamer Stil: Einstellung „Sprachumschalter“, ab LANG_MENU_FROM Sprachen immer „dropdown“
- `static late(): string` – Späte Teile am Ende von <body> (Kit-Layout: <?= header_actions_late() ?>, theme.php 'header_actions' => ['late' => true]):
- `static menu(array $menu): array` – Stil „Menüpunkt“: steht der Handlungsaufruf schon als Punkt im Hauptmenü (gleiches Ziel, ohne Unterseiten), wird er dort
- `static model(): array` – Alles, was das Markup braucht (einmal je Anfrage und Sprache berechnet)
- `static parts(): array` – Benötigte Teile für die aktuelle Einstellung: ['css' => [teil, …], 'js' => [teil, …]] – Teil '' = Grundlage
- `static ranges(): array` – Öffnungszeiten (Website → Stammdaten „hours“) als [Wochentag 0–6, von, bis] – Pausen teilen den Tag
- `static render(string $slot = 'bar', array $opt = []): string` – Markup einer Stelle im Kopf: bar (Leiste) | center (Suche mittig) | below (Suchleiste unter dem Kopf).
- `static settingsFields(): array` – Felder für Website → Darstellung (ersetzen die bisherigen zwei Felder des Buttons; Namen bleiben gleich)
- `static socialIcon(string $label, string $url): string` – Symbol für ein Social-Profil nach Bezeichnung bzw. Adresse
- `static widthRem(): float` – Geschätzte Breite der Aktionen in der Leiste (rem) – für Kits, die per Container-Query entscheiden, ob das Menü passt.

### `HostHandover` (class, `app/HostHandover.php`)

Angemeldet auf eine andere Domain DERSELBEN Website wechseln – für den Domainwechsel: Passkeys gelten nur für die Domain, auf der sie angelegt wurden. Vor dem Umstellen der Hauptadresse meldet man sich so auf der neuen Domain an und legt dort einen Passkey an. Einmal-Token (HMAC mit dem Schlüssel der Website, 60 s, an Browser und Ziel-Domain gebunden, Nonce einmalig in host_handover);

Konstanten: `TTL`, `TARGET`, `PATH`

- `static consume(string $token, string $host, string $ua): string|int`
- `static ensure(Database $db): void`
- `static issue(int $uid, string $host, string $ua): string`
- `static link(string $host, string $path): string` – Adresse auf der Ziel-Domain (Schema wie die aktuelle Anfrage – lokal http, sonst https)
- `static missing(array $user, string $host, ?array $pkHosts = null): bool` – Hat das Konto Passkeys, aber keinen für $host? (Dann gilt der Passkey dort nicht – vorher dort einen anlegen)
- `static page(string $to, string $host): Http\Response` – Zwischenseite mit <meta refresh> statt 303 (CSP der Verwaltung: form-action 'self' gilt auch für Weiterleitungen nach Formularen).
- `static passkeyHosts(array $user): array` – Domains, für die das Konto einen Passkey hat (RP-ID = Domain ohne Port) – Hinweise beim Domainwechsel.
- `static rp(string $host): string` – RP-ID einer Domain (ohne Port, kleingeschrieben)
- `static targets(): array` – Domains dieser Website, auf die gewechselt werden kann (ohne die aktuell aufgerufene, ohne Landing-Domains)

### **API** `I18n` (class, `app/I18n.php`)

Übersetzungen der Oberfläche (Verwaltung, Editor, Core-Texte).

Konstanten: `SOURCE`

- `static available(): array` – Verfügbare Oberflächensprachen: ['de' => 'Deutsch', 'en' => 'English', …]
- `static dictionary(): array` – Wörterbuch für das JavaScript der Oberfläche (nur Einträge, die im Frontend gebraucht werden)
- `static locale(): string`
- `static setLocale(string $locale): void`
- `static site(string $text, array $params = []): string` – Feste Texte der Website (Templates, Blöcke, Core-Ausgaben) in der Sprache der aufgerufenen Seite – lt('Anfahrt').
- `static translate(string $text, array $params = []): string`

### `Iban` (class, `app/Iban.php`)

IBAN (ISO 13616): Normalisieren, Prüfen, Formatieren, Maskieren. Gespeichert wird die Kurzform ohne Leerzeichen in Großbuchstaben („DE89370400440532013000“), angezeigt in Vierergruppen („DE89 3704 0044 0532 0130 00“).

Konstanten: `LENGTHS`

- `static checksum(string $iban): bool` – Prüfziffer nach ISO 7064 Mod 97-10
- `static error(string $iban): ?string` – null = gültig, sonst Fehlercode: format | country | length | checksum
- `static format(string $iban): string`
- `static mask(string $iban): string` – Maskiert: Land, Prüfziffer und die letzten zwei Zeichen bleiben sichtbar („DE89 **** **** **** **** 00“)
- `static normalize(string $s): string`

### **API** `Icons` (class, `app/Icons.php`)

Symbole (Phosphor Icons, Stil „duotone“, MIT – siehe THIRD-PARTY-NOTICES.md).

Konstanten: `NAV`, `LEGACY`, `DEFAULT`, `STYLES`, `ALWAYS`

- `static applyStyle(string $html): string` – Angemeldete Redaktion auf der Website: Symbole im fertigen HTML auf den gewählten Stil umstellen (volles Stil-Sprite),
- `static catalog(): array` – Katalog (catalog.json): ['topics' => [[key, de, en, icons[]]], 'icons' => [name => [de, en, suchbegriffe, tags (en)]]]
- `static clean(?string $value, ?string $default = self::DEFAULT): string` – Wert für die Speicherung bereinigen: Symbolname bleibt, altes Zeichen wird abgebildet, sonst $default
- `static enabledTopics(): ?array` – Aktivierte Symbolbereiche (Grundeinstellungen → „Symbolbereiche“); null = alle
- `static exists(string $name): bool` – Gibt es das Symbol im Sprite?
- `static isName(?string $value): bool` – Ist der Wert ein gültiger Symbolname (für Speichern/Validieren)?
- `static map(): array` – Index icons-map.json: Symbolname → Sprite-Datei (ohne .svg), z. B. ['car' => 'transport', 'x' => 'core']
- `static migrateTables(): array` – Einmalige Übernahme: Symbole der Datentabellen (Unicode-Zeichen) → Symbolnamen. Alte Werte bleiben in
- `static nav(string $key, string $class = ''): string` – Menüsymbol der Verwaltung: '' für Schlüssel ohne Sprite-Symbol (z. B. „ai“ = KLXM-Logo als CSS-Maske)
- `static picker(string $id, string $name, ?string $value, array $opt = []): string` – Eingabe mit Symbolauswahl (resources/js/_iconpicker.js). Ohne JavaScript: Textfeld für den Symbolnamen.
- `static render(?string $value, array $opt = []): string` – Symbol ausgeben – für gespeicherte Werte: Symbolname/Menü-Schlüssel/altes Zeichen → SVG,
- `static resolve(?string $value): ?string` – Wert → Symbolname: Symbolname, Menü-Schlüssel oder altes Unicode-Zeichen; sonst null
- `static siteSprite(string $html): string` – Website-Sprite für Besucherseiten: alle Verweise auf Symbol-Sprites im fertigen HTML durch ein Sprite der Website
- `static siteSpriteUrl(array $names): ?string` – Adresse des Website-Sprites, das mindestens $names enthält (legt es bei Bedarf an); null = nicht möglich
- `static sprite(?string $name = null): string` – Adresse eines Sprites (mit Versionsparameter wie alle Assets): ohne Namen das vollständige icons.svg
- `static style(): string` – Gewählter Stil der Website (nur wenn das Stil-Sprite existiert)
- `static styleOptions(): array` – Auswahl für die Einstellung (Bezeichnungen übersetzt)
- `static suggest(string $text, int $max = 8): array` – Vorschläge passend zu einem Text (z. B. Tabellenname): Symbolnamen, deren Bezeichnung/Suchbegriffe passen
- `static svg(string $name, array $opt = []): string` – SVG-Markup für einen Symbolnamen. $opt: class, label (zugänglicher Name, sonst dekorativ), title
- `static topicOptions(): array` – Auswahl für die Einstellung „Symbolbereiche“: Themen-Schlüssel → Bezeichnung (ohne die immer aktiven)

### `ImageEdit` (class, `app/ImageEdit.php`)

Bild bearbeiten – zerstörungsfrei: Entzerren, Spiegeln, Drehen (90°-Schritte und frei), Ausrichten, Zuschneiden.

Konstanten: `OPS`, `MAX_ANGLE`, `RATIOS`, `EDIT_MAX`, `EDIT_GD_MAX`

- `static apply(GdImage $img, array $e): GdImage` – Bearbeitung auf ein Bild anwenden (Reihenfolge OPS). Große Bilder werden vorher verkleinert (Speicher),
- `static cropRect(int $w, int $h, array $c): array` – Zuschnitt in Pixeln für ein Bild w×h (Seitenverhältnis wird exakt erzwungen). @return array{x: int, y: int, w: int, h: int}
- `static editable(array $m): ?string` – Kann dieses Medium bearbeitet werden? null = ja, sonst Hinweis
- `static engine(): string` – Womit wird entzerrt?
- `static exifOps(int $o): array` – EXIF-Ausrichtung 1–8 → [spiegeln (h|null), Drehung im Uhrzeigersinn] (erst spiegeln, dann drehen)
- `static homography(array $from, array $to): array` – Projektive Abbildung (3×3, h8 = 1), die die Punkte $from auf $to abbildet (je 4 Punkte [x, y]).
- `static inscribed(float $w, float $h, float $deg): array` – Größtes achsparalleles Rechteck in einem um $deg gedrehten Rechteck w×h (zentriert). @return array{0: float, 1: float}
- `static normalize(mixed $in): ?array` – Bearbeitung prüfen und kanonisch machen. @return ?array null = ungültig, [] = keine Bearbeitung
- `static ops(array $m): array` – Bearbeitung aus der Spalte media.edit_json (nur die Schritte)
- `static orient(GdImage $img, int $o): GdImage` – EXIF-Ausrichtung anwenden (alle 8 Werte, auch gespiegelte)
- `static outputSize(int $w, int $h, array $e): array` – Maße nach jedem Schritt für ein Bild w×h – Ergebnis [w, h] (für Oberfläche und Selbsttest)
- `static perspectiveSize(array $q, string $fit = 'edges', float $ratio = 0): array` – Ausgabegröße beim Entzerren aus den Ecken in Pixeln (TL, TR, BR, BL).
- `static project(array $h, float $x, float $y): array` – Punkt mit einer Homographie abbilden. @return array{0: float, 1: float}
- `static rotatedBox(float $w, float $h, float $deg): array` – Umgebendes Rechteck nach Drehung um $deg. @return array{0: float, 1: float}
- `static selftest(): array`
- `static stored(array $m): array` – Gespeicherte Angaben (orig, master)

### `ImageFit` (class, `app/ImageFit.php`)

Bild im Rahmen: füllen, einpassen, Originalformat – je Einbindung im Block oder als Standard des Bildes.

Konstanten: `MODES`

- `static auto(array $m, ?string $ratio = null): string` – Automatischer Standard: SVG und Bilder mit transparentem Rand einpassen – nicht bei eigenem Zuschnitt für das Format
- `static blurUrl(array $m): ?string` – Weichgezeichnete Kopie des Bildes für „Einpassen · unscharf“ (URL im Medienordner der Website, fit/blur-<hash>.webp).
- `static css(): string` – Inhalt der erzeugten CSS-Datei für die gesammelten Regeln ('' = keine)
- `static cssUrl(): string`
- `static edgeAlpha(GdImage $img): bool` – Transparenter Rand: mindestens ein Viertel der Randpunkte (verkleinert) überwiegend durchsichtig
- `static enter(array $data, array $fields = []): void` – Zuordnung eines Blocks setzen (Core\Theme::renderBlock) – wie ImageFx::enter(), mit leave() beenden.
- `static hasAlpha(array $m, bool $compute = true): bool` – Hat das Bild einen (teilweise) transparenten Rand? Typisch für Logos und Freisteller. Ergebnis wird in
- `static inject(string $html, bool $force = false): string` – Fügt Stylesheet und erzeugte Regeln vor </head> ein, wenn die Ausgabe eingepasste Bilder enthält (oder $force,
- `static label(?string $v): string` – Lesbare Kurzbeschreibung (Verwaltung), z. B. „Einpassen · unscharf“
- `static leave(): void`
- `static mediaPaths(array $fields, array $data, string $prefix = '', int $depth = 0): array` – Pfade aller Bild-Felder eines Blocks in Dokument-Reihenfolge (auch in Listen/Gruppen), z. B. image, items.0.image
- `static normalize(mixed $in): ?string` – Kanonische Schreibweise (null = ungültig, '' = erben). Nimmt auch {mode, bg} (z. B. aus der API).
- `static parse(?string $v): ?array` – Zerlegen. @return ?array{mode: string, bg: string} null = ungültig; mode '' = erben
- `static pictureClass(array $a, array $m, ?string $ratio = null): string` – Klassen am <picture> (und Regeln für die erzeugte CSS-Datei).
- `static presets(): array` – Farben des Kits als Vorschläge: [{name, label, value}] – Farb-Tokens aus theme.php → design (ohne Schrift/Linien)
- `static reset(): void` – Nur für Selbsttests: gesammelte Regeln verwerfen
- `static resolve(array $m, ?string $ratio = null, ?string $path = null): ?array` – Wirksame Einstellung für ein Bild: Einbindung → Standard des Bildes → automatisch (SVG, transparenter Rand).
- `static rulesLink(): string` – <link> der erzeugten Regeln (Live-Vorschau eines Blocks im Editor) – '' ohne Regeln
- `static rulesUrl(): ?string` – Erzeugte CSS-Datei (je Satz Regeln einmal geschrieben, Name = Prüfsumme) → URL, null = keine Regeln
- `static sanitize(mixed $fit, array $fields, array $data): array` – Bereinigt data._fit eines Blocks wie ImageFx::sanitize(): nur Bild-Felder des Schemas (auch in Listen) mit Bild

### `ImageFx` (class, `app/ImageFx.php`)

Bild anpassen (Effekte, Sättigung, Helligkeit, Kontrast, Schärfe/Unschärfe) – zerstörungsfrei per CSS-Filter.

Konstanten: `PRESETS`, `RANGES`, `STEP`, `SHARP`, `BLUR_PX`, `SHARPEN`, `NONE`

- `static classFor(array $m): string` – Klassen für ein Bild: Einbindung im aktuellen Block vor globaler Einstellung des Mediums
- `static classes(?string $v): string` – CSS-Klassen einer Anpassung („ifx ifx-sepia ifx-s12 …“), leer = keine
- `static cssUrl(): string` – Adresse des Stylesheets
- `static defs(): string` – Verstecktes <svg> mit den Filtern für Schärfe/Unschärfe (ids = Klassen, ifx-sharp-m1…m10, ifx-sharp-p1…p10).
- `static enter(array $data): void` – Zuordnung eines Blocks setzen (Core\Theme::renderBlock). Ohne eigene Einträge erbt ein verschachtelter Block die
- `static inject(string $html, bool $force = false): string` – Fügt das Stylesheet vor </head> ein, wenn die Ausgabe angepasste Bilder enthält (oder $force, z. B. im
- `static isMediaPath(array $fields, string $path): bool` – Zeigt der Pfad im Schema auf ein Bild-Feld? Listen (repeater/group) verlangen danach eine Position.
- `static label(?string $v): string` – Lesbare Kurzbeschreibung (Verwaltung), z. B. „Sepia · Sättigung 120 %“
- `static leave(): void`
- `static normalize(mixed $in): ?string` – Kanonische Schreibweise (oder null = ungültig). Leer = keine Anpassung.
- `static parse(?string $v): ?array` – Anpassung zerlegen. @return ?array{p: ?string, s: int, b: int, c: int, k: int, none: bool} null = ungültig
- `static presetLabel(string $p): string` – Bezeichnung eines Effekts in der Sprache der Verwaltung
- `static rawAt(array $data, string $path): mixed`
- `static sanitize(mixed $fx, array $fields, array $data): array` – Bereinigt data._fx eines Blocks: Schlüssel müssen auf ein Medien-Feld (Typ „media“) des Block-Schemas zeigen –
- `static sharpClass(int $k): string` – Klasse der Schärfe-Stufe: 40 → „ifx-sharp-p4“, -60 → „ifx-sharp-m6“ (zugleich id des SVG-Filters)
- `static valueAt(array $data, string $path): int` – Medien-ID am Feldpfad („image“, „items.2.image“) – 0, wenn keine (auch für Core\ImageFit)

### `Impersonation` (class, `app/Impersonation.php`)

Ansicht als Rolle bzw. als Person (Support, Prüfen von Rechten) – nur für Administration und Netzwerk-Administration.

Konstanten: `TTL`, `BLOCKED`, `ALLOWED`

- `static active(): bool`
- `static blocked(string $path): bool` – Pfad während der Ansicht gesperrt?
- `static canStart(?array $real): bool` – Darf diese (echte) Person die Ansicht wechseln?
- `static canUser(array $real, array $target): bool` – Darf $real als $target arbeiten?
- `static data(): ?array` – Sitzungsdaten der laufenden Ansicht oder null
- `static effective(array $real, array $imp, Database $db): ?array` – Wirksames Konto für Auth::user() (echtes Konto + Sitzungsdaten) oder null, wenn die Ansicht nicht (mehr) gilt.
- `static roles(): array` – Rollen für „Als Rolle ansehen“: alle außer Administration und Netzwerk
- `static selftest(): array` – Selbsttest (impersonate:selftest): Regeln, wirksames Konto, Sperren, Zeitlimit – Transaktion wird zurückgerollt
- `static start(array $real, string $mode, string $value): ?string` – Ansicht starten (Prüfung durch den Aufrufer: echte Person, canStart)
- `static stop(): void`

### `Indexing` (class, `app/Indexing.php`)

Indexierung & Crawler (Grundeinstellungen → „Indexierung & Crawler“): - robots.txt: Standardregeln (Verwaltung, Formulare, API, MCP gesperrt; Sitemap) + KI-Crawler + eigene Zeilen - KI-Crawler (sys.ai_crawlers): 'allow' (alle erlaubt), 'training' (Bots zum Trainieren gesperrt, Such-/Antwort-Bots

Konstanten: `TRAINING_BOTS`, `ANSWER_BOTS`

- `static aiMode(): string`
- `static extraLines(): array` – Eigene Zeilen: nur bekannte Anweisungen und Kommentare, ohne Steuerzeichen
- `static fields(): array` – Felder für die Grundeinstellungen
- `static llmsEnabled(): bool`
- `static llmsTxt(): string` – Inhalt der llms.txt (Markdown): Name, Beschreibung, indexierbare Seiten, Datentabellen mit Detailseiten
- `static robotsTxt(): string` – Inhalt der robots.txt
- `static warnings(): array` – Warnungen für die Grundeinstellungen

### `Invites` (class, `app/Invites.php`)

Einladungen für Konten dieser Website (Benutzer & Rollen → „Person einladen“, CLI user:invite).

Konstanten: `DAYS`, `MESSAGE_MAX`, `NAME_MAX`

- `static accept(array $inv, string $name, ?string $password, string $method, ?int $uid = null, ?callable $then = null): string|int` – Konto anlegen und Einladung als verwendet markieren – in einer Transaktion; $then (z. B. Passkey speichern) läuft
- `static assignable(?array $target, ?array $mine): bool` – Darf die eigene Rolle ($mine) in $target einladen? Nie mehr Rechte oder Datentabellen als die eigene Rolle; null = CLI.
- `static base(): string` – Adresse der Website (sys.site_url bzw. aufgerufene Domain; Kommandozeile ohne sys.site_url: erste Domain der Website)
- `static brand(): array` – Markenfarbe: Token „accent“ (bzw. primary/brand) des Kits aus dem Style-Editor, sonst Farbe der App (PWA/Icon).
- `static clean(string $s, int $max): string`
- `static cleanMessage(string $s): string` – Persönliche Nachricht: reiner Text, Zeilenumbrüche bleiben (höchstens zwei Leerzeilen), keine Steuerzeichen
- `static create(array $in, ?array $by, ?array $byRole): array` – Einladung anlegen (ohne Versand).
- `static createNetwork(array $in, ?array $by): array` – Einladung als Netzwerk-Administration anlegen (ohne Versand) – nur auf der Netzwerk-Website, nur durch ein aktives
- `static date(int $ts, string $lang): string` – Datum + Uhrzeit in der Sprache der Einladung
- `static days(): int` – Gültigkeit in Tagen (config 'invite_days')
- `static ensureTable(Database $db): void`
- `static find(string $token): ?array` – Einladung zum Token – nur, wenn sie noch angenommen werden kann (sonst null; keine Unterscheidung nach außen)
- `static get(int $id): ?array`
- `static hash(string $token): string` – Hash des Tokens – an die Website gebunden (ein Token einer anderen Website passt nie)
- `static isNetwork(array $inv): bool` – Einladung als Netzwerk-Administration?
- `static log(string $action, ?string $by, string $detail): void` – Protokoll wie andere Anmelde-Ereignisse (Netzwerk-Protokoll mit Kürzel der Website)
- `static mail(array $inv, string $token): array` – Betreff, Text, HTML und eingebettete Bilder in der Sprache der Einladung.
- `static networkInviter(int $uid): bool` – Aktives Netzwerk-Konto auf der Netzwerk-Website? (darf Netzwerk-Administratoren einladen)
- `static newToken(): string`
- `static nextUserId(Database $db): int` – ID des nächsten Kontos – für einen Passkey vor dem Anlegen (die Benutzerkennung im Passkey hängt an der ID, Mfa::handle).
- `static open(bool $network = false): array` – Offene Einladungen (auch abgelaufene, bis sie erneut gesendet oder zurückgezogen werden), neueste zuerst.
- `static renew(int $id): ?string` – Neuer Token und neue Frist (der alte Link gilt nicht mehr) → Token oder null
- `static revoke(int $id): ?array` – Zurückziehen: Token ungültig (Zeile bleibt für das Protokoll bis zur nächsten Einladung derselben Adresse)
- `static roleOptions(?array $mine): array` – Rollen, in die $mine einladen darf: [key => Name]
- `static selftest(): array` – Selbsttest (Konsole invites:selftest): Token, Hash, Form, Rollenprüfung, Nachricht; Netzwerk-Administration einladen
- `static send(array $inv, string $token): array` – Einladung versenden (neuer Token bei „Erneut senden“ über renew()).
- `static template(string $file): string` – Vorlage: Kit (kits/{kit}/templates/mail/…) vor Core (app/Admin/views/mail/…)
- `static url(string $token): string`
- `static usable(array $inv): bool` – Noch annehmbar? (Frist, einmalig, nicht zurückgezogen, Rolle vorhanden, Adresse frei, einladendes Konto aktiv)
- `static wellFormed(string $token): bool` – Form des Tokens (vor jeder Datenbankabfrage)

### **API** `Kit` (class, `app/Kit.php`)

Zentrale Pfad-API für Kits – die einzige Stelle, die weiß, wo Kits liegen.

Konstanten: `ROOTS`, `UPLOADS`, `PACKAGE_TYPE`, `DEFINITIONS`

- `static all(): array` – Alle installierten Kits: [name => ordner] alphabetisch
- `static clean(string $name): string` – Gültiger Kit-Name (Ordnername)
- `static commands(): array` – Befehle des aktiven Kits für bin/console: kits/{kit}/console.php gibt ['name' => ['Beschreibung', fn(array $args): int], …]
- `static compatible(string $req): bool` – Passt eine Angabe wie '>=1.0.0' zur Core-Version? (leer bzw. unlesbar = ja)
- `static definitionFile(string $dir): ?string` – Definitionsdatei im Kit-Ordner: theme.php, sonst kit.php, sonst null
- `static dir(string $name = ''): string` – Ordner eines Kits (kits/, storage/kits/ oder Composer-Paket) – ohne Namen der Hauptordner kits/.
- `static exists(string $name): bool` – Gibt es das Kit (mit theme.php bzw. kit.php)?
- `static fragment(string $name, ?string $kit = null): ?string` – Datei eines Fragments für das aktive (bzw. angegebene) Kit nach der Suchreihenfolge – siehe Core\Fragments::find()
- `static info(string $name): ?array` – Angaben zu einem Kit: dir, source, package (Eintrag aus installed.json bzw. composer.json), public (Ordner der fertigen Assets)
- `static meta(string $dir): array` – Einfache Angaben der Definitionsdatei, ohne sie auszuführen (Kits können gleichnamige Helfer definieren):
- `static publicDir(string $name = ''): string` – Öffentlicher Asset-Ordner eines Kits: public/assets/kits/{name}; ohne Namen der Hauptordner public/assets/kits
- `static publicFolder(array $composer): string` – Ordner der fertigen Assets im Paket: composer.json → extra.klxm-studio.public (Standard: public) – ohne „..“
- `static relative(string $path): string` – Pfad relativ zur Installation (für Meldungen, Kommentare im Entwicklermodus)
- `static reset(): void` – Zwischenspeicher leeren (nach Installation/Entfernen eines Pakets)
- `static shadowed(): array` – Verdeckte Kits (gleicher Name an mehreren Orten): [name => [[dir, source], …]] – es gilt kits/ vor storage/kits/ vor Composer
- `static source(string $name): ?string` – Herkunft eines Kits: 'local' (kits/), 'upload' (storage/kits/), 'composer' – null, wenn es das Kit nicht gibt
- `static url(string $name, string $path = ''): string` – Öffentliche Adresse (ohne Versions-Parameter) einer Datei des Kits, z. B. für Schriften, die exakt der URL im CSS entsprechen müssen

### `KitCheck` (class, `app/KitCheck.php`)

php bin/console kit:check [--kit=name] [--all] [--strict] – Überschreibungen eines Kits prüfen (ohne Zustand, nur Quellen): - verwaiste Block-Vorlagen: kits/{kit}/blocks/{typ}.php ohne Block dieses Namens (Kit, Kern, Layout, Erweiterung) – wird nie gerendert; - Überschreibungen von Kern-Blöcken (Datenliste, Partner …): Angaben ($d['…'], $b->data['…'], $b->edit('…')), die der Kern-Block

Konstanten: `CONTRACT`, `CONTRACT_OPTIONAL`

- `static keys(string $php): array` – Gelesene Angaben einer Vorlage: $d['x'], $d["x"], $b->data['x'], $b->edit('x…'), $b->val('x') – ohne interne (_x)
- `static missingContract(Theme $kit): array` – Fehlende Pflicht-Rollen des Kit-Vertrags (--kit-*) in den Stylesheets des Kits
- `static run(Theme $kit, bool $verbose = false): array`

### `KitPackages` (class, `app/KitPackages.php`)

Kits als Pakete (Core\Kit findet sie): ZIP-Upload bzw. kit:install, Entfernen, Veröffentlichen der fertigen Assets.

Konstanten: `MARKER`, `MAX_ZIP`, `MAX_UNPACKED`, `MAX_ENTRIES`, `PUBLIC_TYPES`

- `static canManage(): bool` – Darf diese Person Kits installieren/entfernen? Kits gelten für die ganze Installation (alle Websites) –
- `static check(string $dir): array` – Kit-Ordner prüfen, ohne Code auszuführen: Definitionsdatei mit 'label', Core-Version ('requires'), Syntax (php -l,
- `static health(): array` – Für health: verdeckte Kits, Pakete ohne veröffentlichte Assets, unpassende Core-Version (nur Hinweise)
- `static install(string $path, bool $force = false, string $by = '', string $filename = ''): array` – Kit-Paket installieren: ZIP-Datei oder Ordner → prüfen → storage/kits/{name} (atomar) → Assets veröffentlichen.
- `static marker(string $name): array` – Merkdatei eines hochgeladenen Kits
- `static overview(): array` – Alle Kits mit Herkunft, Version und Verwendung (Verwaltung → Grundeinstellungen → Kits, kit:list).
- `static publish(?string $only = null): array` – Fertige Assets eines Pakets (upload/composer) nach public/assets/kits/{name} kopieren – ersetzt den Ordner vollständig
- `static remove(string $name): array` – Hochgeladenes Kit entfernen (storage/kits/{name} und public/assets/kits/{name}). Nie, solange eine Website es nutzt;
- `static uploadAllowed(): bool` – ZIP-Upload in der Verwaltung erlaubt? (config 'kit_upload' => false schaltet ihn ab; kit:install geht weiter)
- `static uploadDir(string $name = ''): string` – Ordner hochgeladener Kits: storage/kits
- `static usage(string $name): array`
- `static usageAll(): array` – Welche Websites nutzen das Kit als aktives Kit? [kit => [website-schlüssel …]]

### `Landing` (class, `app/Landing.php`)

Eine Landingpage mit eigener Domain (Core\Landings): zeigt eine Seite (optional mit Unterseiten) der Website unter einer weiteren Domain. Für Themes über landing() erreichbar – z. B. landing()?->logo, landing()?->name.

- `__construct(int $id, string $label, array $hosts, int $pageId, bool $includeSubpages, string $mode, bool $redirectMain, bool $redirectOther, string $search, string $layout, bool $noindex, bool $active, ?string $name = null, ?string $tagline = null, ?int $logo = null, ?int $favicon = null, ?int $ogImage = null, array $design = [])`
- `absUrl(array $page): ?string` – Absolute Adresse einer Seite auf der Landing-Domain (null = gehört nicht dazu)
- `contains(array $page): bool`
- `designCss(): string` – CSS-Variablen der Überschreibungen (nur Farben und Schriften des Themes)
- `designUrl(): ?string` – Öffentliche CSS-Datei der Überschreibungen (bei Bedarf neu erzeugt) → URL, null = keine Überschreibungen
- `fontFor(string $token): ?string` – Schrift-Schlüssel, die die Landingpage für ein Token setzt (für die Schriftdateien in Design::head)
- `static fromRow(array $r): self`
- `host(): string` – Hauptdomain der Landingpage (erste Domain)
- `href(array $page): string` – Link auf eine Seite, gerendert auf der Landing-Domain: eigene Seiten relativ, alle anderen absolut zur Hauptdomain
- `mapHref(string $mainRel): string` – Adresse (relativ zur Hauptdomain, wie im Suchindex) auf die Landing-Domain abbilden:
- `origin(): string` – Ursprung ohne Pfad, z. B. https://reisemedizin-musterstadt.de – Schema wie die aktuelle Anfrage (Kommandozeile: https)
- `pages(): array` – Veröffentlichte Seiten der Landingpage (alle Sprachen)
- `relPath(array $page): ?string` – Pfad einer Seite auf der Landing-Domain („/“, „/impfungen“, „/en/“ …) oder null, wenn sie nicht dazugehört
- `resolve(string $path, ?string $lang = null): ?array` – Seite zu einem Pfad auf der Landing-Domain (ohne Sprachpräfix)
- `root(?string $lang = null): ?array` – Einstiegsseite in einer Sprache (Übersetzung der gewählten Seite; null = keine Übersetzung)
- `rootPage(): ?array`
- `searchAllows(array $doc): bool` – Suche auf der Landing-Domain: gehört der Treffer (Zeile aus dem Suchindex) dazu?
- `subtree(array $tree, ?string $lang = null): array` – Seitenbaum (Pages::tree) auf die Unterseiten der Einstiegsseite begrenzen – für das Menü
- `toArray(): array` – Für API, MCP und Verwaltung

### `Landings` (class, `app/Landings.php`)

Landingpages mit eigenen Domains innerhalb einer Website (Funktion „landings“).

Konstanten: `MODES`, `SEARCH`, `LAYOUTS`

- `static all(): array`
- `static canonical(array $page): ?string` – Canonical einer Seite, wenn eine Landingpage ihn bestimmt (sonst null → normale Adresse):
- `static check(Landing $l, string $host): array` – Status einer Domain: DNS-Auflösung und Selbsttest (/health?landing=…) – zeigt, ob die Domain auf diese Installation zeigt.
- `static current(): ?Landing` – Landingpage, deren Regeln gerade gelten (null auf der Hauptdomain oder innerhalb von suspend)
- `static delete(int $id): void`
- `static enabled(): bool`
- `static fields(?int $id = null): array` – Formularfelder (Core\Fields) für Anlegen/Bearbeiten
- `static find(int $id): ?Landing`
- `static flush(): void`
- `static forHost(string $host): ?Landing` – Aktive Landingpage zur Domain (exakt mit Port, dann ohne Port)
- `static forPage(array $page): array` – Alle Landingpages, zu denen eine Seite gehört (Seiteneinstellungen)
- `static healthToken(Landing $l): string` – Prüf-Token für /health?landing=… (Erreichbarkeit der Domain)
- `static isLandingHost(string $host): bool`
- `static mainOrigin(): string` – Ursprung der Hauptdomain (ohne Pfad): Grundeinstellung „Kanonische Adresse“ → config base_url → erste Domain der
- `static matched(): ?Landing` – Landingpage der aktuellen Anfrage (unabhängig von suspend)
- `static owner(array $page): ?Landing` – Landingpage im Modus „own“, zu der eine Seite gehört (tiefste Einstiegsseite gewinnt)
- `static pageForPath(string $path): ?array` – Seite zu einem Pfad der Website („/leistungen/x“, „/en/…“) – für Links in Inhalten
- `static redirectFor(array $page, Http\Request $r): ?string` – Hauptdomain → Landing-Domain (301) für Besucher, wenn die Landingpage das verlangt
- `static rememberOrigin(): void` – Adresse der Verwaltung merken (Rückfall für mainOrigin, wenn weder kanonische Adresse noch Domains gesetzt sind)
- `static rewriteLinks(string $html): string` – Links in HTML (Rich-Text) für die Landing-Domain umschreiben: Seitenpfade der Website („/leistungen/x“) zeigen
- `static save(array $input, ?int $id = null): array` – Prüfen und speichern. $input = Formularwerte (Core\Fields). @return array{0: ?int, 1: array} [id, Fehler]
- `static siteHosts(): array` – Domains, die für Landingpages infrage kommen: nur die Landing-Domains der Website ('landing_hosts') – die Domains unter
- `static suspend(callable $fn): mixed` – Code „wie auf der Hauptdomain“ ausführen (z. B. Canonical und JSON-LD im Spiegel-Modus)
- `static template(): string` – Template für die Seite: „landing“, wenn gewählt und vom Theme angeboten, sonst „layout“
- `static values(?Landing $l): array` – Formularwerte einer Landingpage

### **API** `Lang` (class, `app/Lang.php`)

Sprachen der Website-Inhalte (nicht der Verwaltung – siehe I18n).

- `static all(): array` – Aktive Sprachen [code => Bezeichnung], Standardsprache zuerst
- `static current(): string` – Sprache der aktuellen Anfrage
- `static default(): string`
- `static flush(): void`
- `static multi(): bool`
- `static norm(?string $code): string` – NULL/leer → Standardsprache
- `static prefix(?string $code): string` – URL-Präfix: '' für die Standardsprache, sonst '/en'
- `static sql(string $col = 'lang'): string` – SQL-Bedingung „Datensatz gehört zur Sprache“ (Spalte lang, NULL = Standard)
- `static valid(?string $code): bool`

### `Layout` (class, `app/Layout.php`)

Kern-Block „Layout“ (Typ layout): Blöcke in Spalten nebeneinander – Raster wählen, Blöcke in die Spalten stellen. Ersetzt die frühere Abschnitts-Option „Neben den vorigen Block stellen“ (Tune row, Theme::renderRow – nur noch zur Darstellung alter Inhalte bis zur Umstellung mit php bin/console layout:migrate-rows).

Konstanten: `TYPE`, `PRESETS`, `PRESET_LABELS`, `UNITS`, `MIN_UNITS`, `DEFAULT_NESTABLE`

- `static childTunes(array $t, Theme $theme): array` – Abschnitts-Optionen eines Kinds: Sprungmarke, sichtbar, eigener Hintergrund ('' = wie der Abschnitt)
- `static cleanWidths(mixed $w, string $preset): ?array` – Gezogene Breiten prüfen: {preset, w: [Zwölftel je Spalte]} – nur gültig für dieses Raster (anderes Raster gewählt → verworfen)
- `static closestPreset(array $spans): ?string` – Nächstes Raster zu Spaltenanteilen in Zwölfteln (Theme::rowSpans)
- `static count(string $preset): int`
- `static definition(): array` – Block-Definition (Theme fügt sie jedem Kit hinzu, außer theme.php → 'layout' => false)
- `static fitColumns(array $columns, int $n): array` – Spalten auf die Anzahl des Rasters bringen: fehlende leer ergänzen, überzählige in die letzte Spalte zusammenführen.
- `static flatten(array $blocks): array` – Alle Blöcke einer Seite einschließlich der Blöcke in Layouts (Kinder direkt nach ihrem Layout; unsichtbares Layout → Kinder unsichtbar)
- `static isLayout(array $b): bool`
- `static migrateRows(array $blocks, Theme $theme): array` – Reihen (Block + folgende Blöcke mit Tune „row“) einer Blockliste in Layout-Blöcke umwandeln.
- `static migrateSite(bool $dry): array` – Alle Seiten der aktuellen Website umstellen (veröffentlichte Fassung und offener Entwurf getrennt; gleiche Fassungen bleiben gleich).
- `static preset(array $data): string`
- `static render(Theme $theme, Block $layout): string` – Inhalt des Layout-Blocks (ohne Abschnitts-Hülle – die setzt Theme::renderBlock über das Partial section)
- `static units(array $data): array` – Breiten in Zwölfteln (für den Griff im Editor)
- `static weights(array $data): array` – Gewichte der Spalten: gezogene Breiten oder die des Rasters

### `Legal` (class, `app/Legal.php`)

Rechtliche Seiten zentral (für Kern-Fragmente und Kits – statt praxis_privacy_url(), basis_legal_links() …): Impressum, Datenschutzerklärung, Erklärung zur Barrierefreiheit in der Sprache der aufgerufenen Seite.

Konstanten: `KEYS`

- `static links(): array` – Links Impressum / Datenschutz / Barrierefreiheit: [['key' => 'privacy_page', 'type' => 'privacy', 'label', 'href']]
- `static orgName(bool $short = false): string` – Name der Organisation (zentrale Angaben org_name / short_name, sonst Name der Website)
- `static page(string $which): ?array` – Seite einer Art (übersetzt in die aktuelle Sprache) – nur veröffentlichte, für Angemeldete auch Entwürfe
- `static privacyUrl(): string` – Adresse der Datenschutzerklärung – ohne Seite die Startseite (ein Link muss immer da sein, z. B. im 2-Klick-Video)

### `LegalDialog` (class, `app/LegalDialog.php`)

Rechtstexte im Dialog: Der Link „Datenschutzhinweise“ an Formularen öffnet die Seite über dem Formular statt in einem neuen Tab – Eingaben bleiben erhalten. GET /_legal/privacy?lang=xx liefert Titel + bereinigtes HTML der Datenschutzseite (Überschriften, Absätze, Listen, Links; ohne Knöpfe, Bilder, Formulare), unabhängig vom Seitenaufbau des Kits.

- `static attrs(string $kind = 'privacy'): string` – Attribute für den Link zur Datenschutzseite (Quelle des Dialogs, Stylesheet)
- `static fragment(array $page): string` – Textinhalt der veröffentlichten Blöcke: Einleitung, Überschriften, Fließtext (HTML bereinigt), Einträge (z. B. FAQ)
- `static handle(Http\Request $r, string $kind): Http\Response`

### `Links` (class, `app/Links.php`)

Linkziele: stabile Verweise, Auflösung und Quellen für die Linkauswahl (resources/js/_links.js, GET /admin/api/links).

Konstanten: `REF`, `PAGED`

- `static ago(string $ts, ?int $now = null): string` – Kurzes Datum: „heute, 14:05“, „gestern“, „vor 3 Tagen“, sonst 12.09.2026
- `static dataSources(): array` – Quellen der Ansicht „Daten“: alle Inhaltstabellen mit Detailseite (Glossar nur bei eingeschalteter Funktion), je Quelle
- `static describe(string $v): array` – Lesbare Beschreibung eines Link-Werts (Feld „link“: Anzeige des gewählten Ziels).
- `static href(string $ref): ?string` – Aktuelle Adresse eines Verweises (null: unbekannt oder Ziel gelöscht)
- `static isRef(string $v): bool`
- `static selftest(): array` – Selbsttest (links:selftest) in einer Transaktion, die am Ende zurückgerollt wird: Seitenbaum für „Struktur“ (Reihenfolge,
- `static sources(array $o = []): array` – Quellen für die Linkauswahl, nach Art gruppiert.
- `static tableEntries(array $t, string $q, int $offset, int $limit): array` – Einträge einer Tabelle für die Linkauswahl (Suche je Tabelle, Ansicht „Daten“): neueste zuerst, mit Titel, kurzem Datum
- `static tree(?string $lang = null, int $pageId = 0): array` – Seitenbaum für den Modus „Struktur“ der Linkauswahl – wie der Seitenbaum der Verwaltung (Reihenfolge, Ebenen), eine Sprache.

### **API** `Live` (class, `app/Live.php`)

Live-Aktualisierung für Besucher (Server-Sent Events) – zentral für Blöcke, Kits und Erweiterungen.

- `static assets(): string` – Skript und Stile (einmal je Seite) – im Block nach dem Element ausgeben
- `static attrs(array $channels, string $src = ''): string` – Attribute fürs Element: Abo, aktuelle Version, optional Adresse zum Nachladen. Im Bearbeiten leer (kein Stream).
- `static block(Http\Request $r): Http\Response` – GET /api/live/block?p=…&b=…[&weitere] – Block der veröffentlichten Seite neu rendern (nur Blöcke mit 'live' bzw. 'reload').
- `static blockSrc(Block $b): string` – Adresse, unter der ein Block der veröffentlichten Seite neu gerendert wird
- `static channels(string $token): ?array` – Kanäle eines Abos oder null (Signatur falsch)
- `static controls(): string` – Pause-Schalter und Statuszeile (WCAG 2.2.2: sich selbst aktualisierende Inhalte anhalten können)
- `static fromEvent(Events\Event $e): void` – Typisierte Ereignisse (Extensions::emit) → Kanäle; Veröffentlichen/Zurückziehen ruft Pages zusätzlich direkt auf
- `static region(Block $b): string` – Neu laden auf Aktion: Attribute für das äußere Element eines Blocks mit 'reload' => true (Kennung = domId, Adresse wie
- `static selftest(): array`
- `static stream(Http\Request $r): Http\Response` – GET /api/live?t=…&t=… (EventSource) – Ereignis „v“: {token: version}
- `static token(array $channels): string` – Signiertes Abo für Kanäle (URL-sicher)
- `static touch(string ...$channels): void` – Kanäle als geändert markieren (Version = Zeitstempel in ms, steigt immer)
- `static version(string $channel): int`
- `static versionOf(array $channels): int` – Höchste Version mehrerer Kanäle

### **API** `Mailer` (class, `app/Mailer.php`)

E-Mail-Versand über Symfony Mailer, konfiguriert aus den Grundeinstellungen.

- `static dsn(bool $requireTls = false, string $purpose = 'account', bool $network = false): string` – DSN des Kontos für einen Zweck (Core\Mail\Accounts) – Standard: Konto & Sicherheit der Website
- `static ready(string $purpose = 'account', bool $network = false): bool` – Versand für einen Zweck eingerichtet? Gültiger Absender und Versandweg des gewählten Kontos (Core\Mail\Accounts); lokal
- `static send(string $subject, string $text, ?array $to = null, array $o = []): ?string`
- `static testRedirect(): array` – Test-Umleitung aus den Grundeinstellungen (sys.mail_redirect): gültige Adressen, leer = aus

### `Maps` (class, `app/Maps.php`)

Karten mit MapLibre GL und OpenFreeMap – vollständig über den eigenen Proxy.

Konstanten: `STYLES`, `HEIGHTS`

- `static allowTile(string $path): bool` – Guard für /proxy/ofm: Kachel liegt im erlaubten Gebiet?
- `static blockFields(): array` – Felder für Karten-Blöcke (Kern-Block und Themes: [...eigene Überschrift, ...Maps::blockFields()])
- `static clickToLoad(): bool` – Zwei-Klick für Karten: Einstellung sys.map_click, sonst Vorgabe des Kits (theme.php → project → map → click)
- `static format(?array $p): string`
- `static geocode(string $q): array`
- `static parse(mixed $v): ?array` – "51.16, 10.45" | ['lat'=>…, 'lng'=>…] → [lat, lng] oder null
- `static radiusKm(): float`
- `static registerProxy(): void`
- `static remember(array $p): void` – Ort merken, damit der Proxy Detailkacheln dafür ausliefert
- `static render(array $o = []): string` – Karte ausgeben.
- `static renderBlock(array $d, array $extra = []): string` – Karten-Block ausgeben (Werte aus blockFields)
- `static routeUrls(array $p, int $zoom = 15): array` – Routen-Links zu den Koordinaten (nur Ziel, keine weiteren Daten): Google Maps (Browser; auf Android öffnet dieselbe Adresse
- `static siteAddress(): string` – Postanschrift aus den Einstellungen (für Adresssuche und Bildunterschrift)
- `static siteLabel(): string`
- `static siteLocation(): ?array` – Standort der Website (Theme: 'project' → 'map' → 'location' = Name der Einstellung)
- `static styleUrl(?string $style = null): string`

### **API** `Media` (class, `app/Media.php`)

Medienverwaltung.

Konstanten: `IMAGE_MIMES`, `FILE_MIMES`, `OFFICE_MIMES`, `TEXT_MAX`

- `static addToCollection(int $collectionId, array $mediaIds): void`
- `static all(array|string|null $f = null): array` – Mediathek mit Filtern.
- `static allTags(): array` – Alle Tags mit Anzahl
- `static alt(array $m, ?string $lang = null): string` – Alt-Text in der Sprache der Seite (Rückfall: Standardsprache)
- `static autoPoster(?array $m): ?array` – Automatisches Vorschaubild eines Videos als Bild-Datensatz (null = noch keins)
- `static cleanTranslations(mixed $in): ?string` – Übersetzungen speichern (nur aktive Nicht-Standardsprachen; leere Werte = Standardsprache)
- `static collectionIds(int $mediaId): array`
- `static collections(): array`
- `static counts(): array` – Anzahl je Art für die Seitenleiste der Mediathek
- `static createCollection(string $name, string $description = ''): int`
- `static crops(array $m): array` – Festgelegte Zuschnitte: ['16:9' => ['x','y','w','h' (Anteile 0–1), 'base', 'sizes', 'W', 'H'], …]
- `static db(): Database`
- `static delete(int $id, bool $force = false): void` – Löschen: mit Papierkorb (nur Mediathek der Website, nicht geteilte Pools) wird die Datei nur markiert und bleibt
- `static deleteBlocked(int $id, ?array $u = null): ?string` – Löschen verweigern, solange die Datei verwendet wird (auch auf anderen Websites bei geteilten Medien): Meldung, sonst null
- `static dir(): string`
- `static displayName(array $m): string` – Anzeigename: Titel (in der Sprache der Seite) oder aufbereiteter Dateiname
- `static duplicatesOf(array $m): array` – Andere Dateien mit gleichem Inhalt (ohne Papierkorb)
- `static ensureHashes(int $limit = 400): void` – Prüfsummen nachtragen (ältere Dateien) – je Aufruf höchstens $limit, damit die Mediathek schnell bleibt
- `static fileList(array $m): array` – Alle Dateien eines Mediums relativ zu seinem Medienordner (z. B. zum Kopieren zwischen Pools, siehe Core\Data\Shared)
- `static fileMimes(): array` – Erlaubte Nicht-Bild-Typen: FILE_MIMES plus Typen aktiver Erweiterungen (Extension::mediaTypes, z. B. video/webm)
- `static find(int $id): ?array`
- `static focusClass(array $m): string` – CSS-Klassen für den Fokuspunkt (10-%-Raster, siehe site.css: Klassen fx0…fx100 und fy0…fy100)
- `static forget(int $id): void`
- `static humanSize(int $bytes): string` – Dateigröße für die Anzeige (Core\Format::bytes in der Sprache der Verwaltung bzw. Seite) – „1,5 MB“, „12 KB“
- `static idsInBlocks(array $blocks): array` – Datei-IDs in Blöcken (Felder der Blöcke, Hintergrundbild, Links „media:ID“ im Text) – für die Suche (Search\Documents)
- `static idsInEntry(array $table, array $e): array` – Datei-IDs eines Eintrags (Bild-/Dateifelder, Gruppen, Links im Text)
- `static import(string $path, string $originalName, string $alt = '', array $opt = []): array` – Importiert eine lokale Datei als neues Medium.
- `static kind(string $mime): string`
- `static largeBytes(): int` – Grenze für „Zu große Dateien“ in Bytes
- `static localFile(array $m, ?int $width = null, string $format = 'webp'): string` – Absoluter Pfad der Datei, die url() mit denselben Angaben liefert (Website oder Pool)
- `static maxBytes(): int`
- `static normTag(string $t): string`
- `static officeMime(string $path, string $name): ?string` – Office-Datei am Inhalt prüfen: Endung passt, ZIP-Container mit erwarteter Kennung, keine Makros
- `static officeOn(): bool` – Office-Dokumente in der Mediathek erlaubt? (Grundeinstellungen → Mediathek, Standard aus)
- `static originalUrl(array $m): string` – Adresse der unveränderten Originaldatei (Vorher/Nachher im Bildeditor)
- `static pages(array $m): ?int`
- `static path(array $m): string` – Absoluter Pfad der Originaldatei – auch für Verweise auf geteilte Pools (_pool aus find())
- `static pdfText(array $m, bool $extract = true): string` – Volltext eines PDFs für die Suche: gespeichert in media.text_content (beim Hochladen, sonst beim ersten Bedarf nachgeholt).
- `static pdfTextBackfill(array $ids, int $limit = 15): void` – PDFs ohne ausgelesenen Text nachholen (höchstens $limit je Aufruf) – z. B. vor einer Listensuche
- `static picture(?int $id, string $sizes = '100vw', array $opt = []): string` – <picture> mit AVIF/WebP-srcset, width/height gegen CLS, lazy außer $eager.
- `static pictureOf(array $m, string $sizes = '100vw', array $opt = []): string` – Wie picture(), aber für einen Datensatz (z. B. das automatische Vorschaubild eines Videos, Media::posterFor())
- `static pool(): ?string`
- `static posterFor(?array $m): ?array` – Vorschaubild eines Videos für Themes/Player ohne eigenes Poster: gewähltes Poster einer Erweiterung
- `static purgeTrash(bool $all = false): int` – Abgelaufene Dateien im Papierkorb endgültig löschen ($all: alle, „Papierkorb leeren“); höchstens einmal am Tag automatisch
- `static ratios(): array` – Bildformate des Themes, z. B. ['16:9' => 'Querformat 16:9', …]
- `static recentDuplicate(string $hash, int $minutes = 30): ?array` – Dieselbe Datei (Prüfsumme) in den letzten $minutes Minuten hochgeladen? – gegen doppeltes Hochladen (MediaController::finalize)
- `static removeFromCollection(int $collectionId, array $mediaIds): void`
- `static replace(int $id, string $path, string $originalName, array $opt = []): array` – Ersetzt die Datei eines Mediums. ID, Alt-Text, Tags, Sammlungen und alle
- `static restore(int $id): bool` – Aus dem Papierkorb zurückholen
- `static rightsFields(array $in): array` – Rechte-Angaben aus Eingaben: license, rights_until (Y-m-d), consent (yes|none|no) – nur übergebene Schlüssel
- `static rightsMissing(array $row): ?string` – Pflichtangaben bei „Rechte: Pflicht“ – Fehlertext oder null
- `static rightsMode(): string` – Bildrechte: off | optional | required (Grundeinstellungen → Mediathek)
- `static setCollections(int $mediaId, array $collectionIds): void`
- `static setCrop(int $id, string $ratio, ?array $rect): array` – Legt den Zuschnitt eines Bildes für ein Format fest (oder entfernt ihn mit $rect = null).
- `static setEdit(int $id, mixed $edit): array` – Bearbeitung festlegen (oder mit leerem $edit zurücksetzen) – zerstörungsfrei: aus dem Original entstehen eine
- `static shareToPool(int $id, string $pool): int` – Datei der Website in einen geteilten Pool verschieben: Dateien und Angaben (Alt-Texte, Übersetzungen,
- `static shrink(int $id): array` – Zu großes Bild sparsamer speichern: PNG-Fotos ohne Transparenz → JPG, sonst neu komprimiert (höchstens 3200 px wie beim Hochladen).
- `static sources(array $m, ?string $ratio = null): array` – srcsets für ein Bild – mit festgelegtem Zuschnitt für $ratio, sonst das ganze Bild.
- `static tagList(?string $stored): array`
- `static tagString(array|string $tags): string` – Speicherformat: ",tag eins,tag zwei," (einfach per LIKE durchsuchbar)
- `static title(array $m, ?string $lang = null): string` – Titel in der Sprache der Seite (Rückfall: Standardsprache)
- `static toJson(array $m): array`
- `static translations(array $m): array` – Übersetzungen von Alt-Text und Titel: ['en' => ['alt' => …, 'title' => …]]
- `static trashDays(): int` – Tage im Papierkorb (Grundeinstellungen → Mediathek); 0 = sofort endgültig löschen
- `static typeLabel(string $mime): string`
- `static upload(array $file, string $alt = '', array $opt = []): array`
- `static url(array $m, ?int $width = null, string $format = 'webp'): string`
- `static usages(int $id): array` – Wo wird das Medium verwendet? Seiten (auch Sonderseiten und Seitenvorlagen; Blöcke, Vorschaubild, Abschnitts-Hintergrund,
- `static usagesForDelete(array $ids): array` – Verwendungen für das Löschen: diese Website und – bei Dateien geteilter Medien (Pool) – alle anderen Websites, die den Pool
- `static usePool(?string $key): void`
- `static usedIds(): array` – IDs aller verwendeten Dateien dieser Website in einem Durchlauf – dieselben Stellen wie usages() (Seiten, Datensätze,
- `static viewerUrl(array $m): ?string`
- `static zip(array $ids): ?string` – Mehrere Dateien als ZIP (Originale, lesbare Namen) – Pfad der temporären Datei oder null

### `MediaBlocks` (class, `app/MediaBlocks.php`)

Gemeinsame Helfer der Kern-Blöcke „Bildergalerie“, „Slider“ und „Stapelkarten“ (app/Blocks/*.php). Styles: resources/css/media.css (Theme überschreibt mit public/assets/kits/{name}/css/media.css oder ergänzt per conditional_css), Verhalten (Lightbox, Slider): resources/js/media.mjs – wird einmal je Seite als Modul eingebunden.

Konstanten: `RATIOS`

- `static aspectClass(array $m): string` – Klasse für „Zeilen“-Layout: Seitenverhältnis × 10, gerundet (CSS: flex-grow je Klasse)
- `static head(Block $b): string` – Kopf des Abschnitts (Klassen, die beide Themes kennen: eyebrow, h2, lead); *Betonung* in der Überschrift wie im Kit (emphasis())
- `static headFields(): array` – Dachzeile, Überschrift, Einleitung – wie bei den Theme-Blöcken
- `static images(array $d, string $list = 'images'): array` – Bilder aus Sammlung oder Einzelauswahl.
- `static lightbox(): string` – Lightbox-Dialog (einmal je Seite, von js/media.mjs befüllt) – Beschriftungen in der Sprache der Seite
- `static lightboxLink(array $m, string $caption): string` – Link-Attribute zur großen Fassung (ohne JS: normaler Link; mit JS: Lightbox mit srcset)
- `static script(): string` – Modul-Skript einmal je Seite (nicht im Bearbeiten-Modus)

### `MediaPools` (class, `app/MediaPools.php`)

Geteilte Medien (Pools): zentrale Mediatheken einer Installation, die mehrere Websites nutzen.

Konstanten: `PROTECTED_PATH`

- `static all(): array` – Alle Pools der Installation [key => Bezeichnung]
- `static available(string $key): bool`
- `static canEdit(?string $key): bool` – Darf die angemeldete Person in diesem Pool ändern (hochladen, löschen, Alt-Texte, Sammlungen)?
- `static canManage(): bool` – Darf die angemeldete Person Pools verwalten? (Hauptwebsite mit Grundeinstellungen-Recht oder Integrator)
- `static count(string $key): int`
- `static create(string $key, string $label, array $sites = [], array $extra = []): void` – $extra: weitere Angaben für pool.json (z. B. 'shared_table' => Kurzname bei automatisch angelegten Pools geteilter Tabellen)
- `static db(string $key): Database` – Datenbank eines Pools (gleiches Schema wie eine Website)
- `static delete(string $key): void` – Leeren Pool entfernen (mit Dateien nur über die Kommandozeile, damit nichts versehentlich verschwindet)
- `static dir(?string $key = null): string`
- `static forSite(): array` – Pools, die diese Website nutzen darf [key => Bezeichnung] – aus der Konfiguration ('media_pools') oder der Zuordnung in den Grundeinstellungen
- `static forget(): void`
- `static isProtected(?string $key): bool` – Geschützter Pool? (pool.json 'protected') – je Anfrage gemerkt
- `static mediaDir(string $key): string`
- `static mediaProtected(?array $m): bool` – Gehört eine Mediendatei (Website-Verweis mit pool_ref oder Pool-Zeile mit _pool) zu einem geschützten Pool?
- `static meta(string $key): array` – Angaben eines Pools aus pool.json: label, sites (Websites, die ihn nutzen), editors (Websites, die ihn pflegen dürfen), created,
- `static mirror(string $key, int $poolId): int` – Verweis-Eintrag dieser Website für eine Pool-Datei (anlegen, falls nötig) → lokale Medien-ID
- `static retire(string $key): void` – Leeren Pool beiseitelegen (Aufräumen automatisch angelegter Pools, Core\Data\Shared::cleanupPools): pool.json und Datenbank
- `static row(string $key, int $id): ?array` – Datensatz einer Pool-Datei (zwischengespeichert je Anfrage)
- `static sitesUsing(string $key): array` – Websites, die den Pool nutzen (pool.json 'sites' oder Konfiguration 'media_pools' der Website)
- `static update(string $key, string $label, array $sites, ?array $editors = null): void` – Bezeichnung, nutzende und pflegende Websites ändern
- `static url(string $key, string $rel): string`
- `static usageSig(string $key, string $ids, int $exp): string` – Signatur der Abfrage „Verwendungen von Pool-Dateien“ zwischen Websites einer Installation (Netzwerk-Schlüssel)
- `static usagesElsewhere(string $key, array $poolIds): array` – Verwendungen von Pool-Dateien auf den ANDEREN Websites, die den Pool nutzen – jede Website prüft im eigenen Kontext

### `MediaText` (class, `app/MediaText.php`)

Feldtyp „Medien + Text“ auf der Website (Core\Data\Entries::html): formatierter Text und mehrere Bilder/Videos – über dem Text oder links/rechts daneben (Breite � � oder ½, wahlweise mit Textumlauf), als Liste untereinander, Raster mit Großansicht oder Slider.

- `static href(string $link): string` – Linkziel wie gewählt: „Anzeigen“ (media:ID:viewer) öffnet den PDF-Viewer, „Download“ (media:ID) die Datei
- `static html(array $v, array $o = []): string`
- `static sections(array $sections, array $o = []): string` – Alle Abschnitte eines Feldes – mit Zwischenüberschrift (h3), sonst nur Inhalt

### `MediaTracks` (class, `app/MediaTracks.php`)

Untertitel, Kapitel und Transkripte für Videos und Audio der Mediathek (Barrierefreiheit, WCAG 1.2.2/1.2.3).

Konstanten: `KINDS`, `MAX_BYTES`, `MAX_CUES`

- `static cleanCues(array $in): array` – Cues prüfen: Zeiten (ms, Ende > Anfang), Text säubern, sortieren; leere Cues entfallen
- `static cleanText(string $t): string` – Cue-Text: nur erlaubte WebVTT-Tags, Entities normalisiert, höchstens 500 Zeichen und 4 Zeilen
- `static ctx(array $m): array` – Datenbank, ID, Medienordner und Pool einer Datei (Verweise auf Pool-Dateien → Pool)
- `static cues(array $track): array` – Cues einer Spur für den Editor
- `static decorativeVideo(array $m, array $opt = []): string` – Dekoratives Video: <video muted loop playsinline aria-hidden="true" tabindex="-1"> ohne Bedienelemente, ohne Textspuren.
- `static defaultLabel(string $kind, string $lang): string` – Standardbezeichnung einer Spur im Player, z. B. „Deutsch“, „English (CC)“, „Deutsch – Kapitel“
- `static delete(array $m, int $trackId): void`
- `static deleteAll(array $m): void` – Alle Spuren einer Datei entfernen (beim Löschen des Mediums)
- `static enabled(): bool`
- `static find(array $m, int $trackId): ?array`
- `static forMedia(array $m, bool $publishedOnly = false, bool $withVtt = false): array` – Spuren einer Datei (ohne VTT-Inhalt, außer $withVtt) – veröffentlichte zuerst
- `static isDecorative(?array $m): bool` – Dekoratives Video (Hintergrund-/Stimmungsvideo ohne Informationsgehalt): keine Untertitel nötig, für Screenreader ausgeblendet
- `static langLabel(string $code): string` – Bezeichnung einer Sprache: Sprachen der Website, sonst Eigenbezeichnung, sonst Kürzel
- `static moveToPool(int $siteId, string $pool, int $poolId): void` – Spuren beim Verschieben in einen Pool mitnehmen (Media::shareToPool)
- `static parse(string $raw): array` – WebVTT oder SRT einlesen. Liefert [$cues, $fehler]; Cue = ['start' => ms, 'end' => ms, 'text' => '…', 'settings' => '…'].
- `static plain(string $t): string` – Sichtbarer Text eines Cues (ohne Tags)
- `static player(array $m, array $opt = []): string` – Video bzw. Audio mit Textspuren und Transkript (z. B. Datei-Felder von Einträgen).
- `static publish(array $m, int $trackId, ?int $userId = null): array` – Entwurf prüfen und veröffentlichen (ersetzt eine veröffentlichte Spur gleicher Art und Sprache)
- `static save(array $m, array $in, ?int $trackId = null, ?int $userId = null): array` – Spur anlegen oder ändern. $in: kind, lang, label, cues (Liste [start, end, text, settings] in ms) ODER vtt/srt (Text),
- `static setTranscript(array $m, string $lang, string $text, string $status = 'published', string $source = 'editor'): array`
- `static stamp(int $ms): string`
- `static summary(array $m): array` – Kurzfassung für Media::toJson (Liste): Anzahl veröffentlichter/Entwurfs-Spuren und Transkript-Sprachen
- `static supports(array $m): bool` – Hat die Datei Ton (Video/Audio)?
- `static toVtt(array $cues, string $kind = 'subtitles'): string`
- `static trackJson(array $m, array $t, bool $cues = false): array`
- `static trackTags(?array $m): string` – <track>-Elemente für ein <video>/<audio>: nur veröffentlichte Spuren; Standard = Sprache der Seite
- `static transcriptFromCues(array $cues): string` – Fließtext aus Cues: Sätze zusammenfügen, nach längeren Pausen (≥ 2 s) neuer Absatz
- `static transcriptHtml(?array $m, string $class = 'media-transcript'): string` – „Transkript anzeigen“ (<details>) – nur mit veröffentlichtem Transkript; Sprache der Seite, sonst Standardsprache
- `static transcriptText(array $m, ?string $lang = null, bool $fallback = true): string` – Veröffentlichter Transkript-Text in einer Sprache (für Suche und Ausgabe), Rückfall Standardsprache
- `static transcripts(array $m): array` – ['de' => ['text' => …, 'status' => 'draft'|'published', 'source' => …, 'updated_at' => …], …]
- `static url(array $m, array $track): ?string` – Öffentliche Adresse einer veröffentlichten Spur (mit Versionskennung gegen Browser-Cache)
- `static validLang(string $code): bool`

### `Mfa` (class, `app/Mfa.php`)

Anmelde-Richtlinie: erlaubte Verfahren (Authenticator-App/TOTP, Passkeys, Anmeldung ohne Passwort), zweiter Faktor je Rolle („verlangt“ = App oder Passkey, „nur Passkey“ = phishing-resistent) und Übergangsfrist.

Konstanten: `KEY`, `NET_KEY`, `REAUTH`, `SETUP_PATHS`

- `static afterLogin(int $uid): void` – Nach jeder vollständigen Anmeldung (Auth::login): Pflicht offen? → Einrichtung verlangen bzw. daran erinnern
- `static allowed(array $u): array` – Erlaubte Verfahren für ein Konto
- `static deadline(array $row): int` – Frist für die Einrichtung (0 = sofort)
- `static due(mixed $need): bool` – Einrichtung jetzt erzwungen (Frist abgelaufen oder ohne Frist)?
- `static factors(array $row, ?Database $db = null): array`
- `static forget(): void`
- `static handle(array $u): string` – Benutzerkennung für Passkeys (Netzwerk-Konten: gleiche Kennung auf allen Websites, andere Domain = anderer Passkey)
- `static hasFactor(array $row, ?Database $db = null): bool` – Hat das Konto überhaupt einen zweiten Faktor (→ nach dem Passwort abfragen)?
- `static isNet(array $u): bool` – Netzwerk-Konto (Zeile der Netzwerk-Datenbank oder Schatten-Konto)?
- `static loginMethods(array $row, ?Database $db = null): array` – Verfahren für den zweiten Schritt nach dem Passwort, bevorzugtes zuerst (zuletzt verwendet, sonst Passkey).
- `static netPolicy(): array` – Richtlinie der Netzwerk-Konten (Netzwerk-Website) @return array{totp:bool, passkey:bool, passwordless:bool, passkey_only:bool}
- `static networkStrong(array $n): bool` – Netzwerk-Konto darf sich auf Websites anmelden (SSO, direkt): immer TOTP oder Passkey – bzw. nur Passkey
- `static policy(): array`
- `static recentAuth(): bool` – Kürzlich angemeldet bzw. Passwort bestätigt (Passkeys verwalten ohne erneute Eingabe)
- `static remember(Database $db, int $uid, string $method): void`
- `static requirement(array $u): ?string` – Pflicht für ein Konto: null (freiwillig), 'any' (App oder Passkey) oder 'passkey'
- `static row(array $u): ?array` – Zeile des Kontos in der zuständigen Datenbank (Schatten-Konten: Netzwerk-Konto)
- `static satisfied(array $row, ?string $req = null, ?Database $db = null): bool` – Pflicht erfüllt? ($req: Standard = Pflicht des Kontos)
- `static saveNetPolicy(array $in): ?string`
- `static savePolicy(array $in, array $roleKeys): ?string`
- `static setupPath(array $u): string` – Wohin zur Einrichtung? Nur App erlaubt → bisherige TOTP-Seite, sonst Auswahl
- `static store(array $u): array` – Datenbank und ID, unter denen Passkeys/TOTP eines Kontos liegen: Netzwerk-Konten auf anderen Websites → Netzwerk-Datenbank.

### `NotFound` (class, `app/NotFound.php`)

Gepflegte Seite „Nicht gefunden (404)“ je Website und Sprache.

Konstanten: `MARK`, `SLUG`

- `static all(): array` – Alle 404-Seiten: [sprache => seite]
- `static begin(string $path): void` – Beginn einer 404-Darstellung: aufgerufene Adresse (ohne Installations-Unterordner) für die Blöcke merken
- `static blockFields(): array` – Felder des Blocks „404-Vorschläge“ – für Kits, die den Block mit eigenem Aussehen überschreiben (theme.php → blocks →
- `static boxes(Block $b): string` – Vorschläge und Suchfeld eines Blocks (Felder aus blockFields()) als Raster zweier Kästen – für den Kern-Block und für
- `static console(string $cmd, array $args): int` – notfound:create [--lang=xx] [--publish] | notfound:selftest (php bin/console, --site=…)
- `static create(string $lang): array` – 404-Seite einer Sprache anlegen (Entwurf) – gibt es sie schon, wird sie zurückgegeben. Weitere Sprachen: Übersetzung der
- `static defaultBlocks(string $lang): array` – Startinhalte: vom Kit (theme.php → 'not_found_blocks') oder der Kern-Block „404-Vorschläge“ mit allen Teilen
- `static end(): void`
- `static exact(string $lang): ?array` – 404-Seite genau dieser Sprache (ohne Rückfall, auch Entwürfe)
- `static homeUrl(): string` – Adresse der Startseite in der aktuellen Sprache
- `static isOwnPath(string $path): bool` – Ist $path (ohne Sprachpräfix) die Adresse der 404-Seite dieser Sprache?
- `static isPage(?array $page): bool` – Ist die Seite die 404-Seite?
- `static page(?string $lang = null, bool $withDrafts = false): ?array` – 404-Seite der Sprache (Standard: aktuelle), Rückfall auf die Standardsprache. Ohne $withDrafts nur veröffentlichte
- `static path(): ?string` – Aufgerufene Adresse, während eine 404 gerendert wird – sonst null (z. B. im Editor unter /404)
- `static selfTest(): array` – Selbsttest (notfound:selftest) in einer Transaktion, die am Ende zurückgerollt wird: Anlegen, nur veröffentlicht für Besucher,
- `static sqlPagesAnd404(): string` – SQL-Bedingung „normale Seite oder 404-Seite“ (z. B. Entwürfe-Übersicht)
- `static suggestions(?string $path = null, int $max = 3): array` – „Vielleicht meinten Sie …“: Seiten und Einträge (mit Detailseite) der aktuellen Sprache, deren Adressteil dem letzten Teil

### `NotesConvert` (class, `app/NotesConvert.php`)

Alte Marker in Redaktionsnotizen „[# … #]“ umstellen (Core\EditorNotes) – php bin/console notes:convert [--site=…] [--dry-run].

- `static convert(string $s): string` – Einen Text umstellen (Klartext oder HTML)
- `static convertData(mixed $v, int &$n): mixed` – Verschachtelte Daten umstellen; $n zählt geänderte Texte
- `static run(bool $dry): array` – Umstellung der aktuellen Website. @return array{pages: int, versions: int, texts: int, entries: int, entry_texts: int, notes: int, log: list<string>}

### `Notice` (class, `app/Notice.php`)

Hinweisbalken der Website (theme.php 'project' => ['notice' => ['text' => …, 'active' => …]]): Zeitraum und Darstellung.

Konstanten: `STYLES`, `COLORS`, `EFFECTS`

- `static bar(): bool` – Balken im Kit-Layout rendern? (Bubble rendert der Core in late())
- `static extendGroups(array $groups): array` – Fügt fields() hinter dem Hinweistext des Kits ein (Theme::settingsGroups)
- `static fields(): array` – Zusatzfelder fürs Einstellungsformular (hinter dem Textfeld des Kits)
- `static has(): bool`
- `static late(string $class = 'topnote'): string` – Vor </body>: Bubble (Kit-Klasse für Farben) und Skript für Zeitraum/Schließen
- `static open(string $class = 'topnote', string $tag = 'div'): string` – Öffnendes Element des Balkens (Klasse des Kits), inkl. Ausrichtung und Zeitraum
- `static pending(): bool` – Eingeschaltet, Text vorhanden und Zeitraum nicht abgelaufen (Beginn darf in der Zukunft liegen → per JS)
- `static style(): string`
- `static text(): string` – Text (Inline-HTML) oder ''
- `static visible(): bool` – Jetzt sichtbar (für API/MCP/Vorschau)

### `Onboarding` (class, `app/Onboarding.php`)

Erststart einer Website: Kit und Startinhalte wählt die Administration im Willkommen-Bildschirm (/admin/willkommen) – oder sie stehen schon fest (Konfiguration, site:create, Netzwerk „Neue Website“). Erst wenn beides entschieden ist, spielt Core\Seeder die Inhalte ein (App::boot, solange die Website noch keine Seiten hat).

Konstanten: `SEED_KEY`, `MODES`, `CATEGORIES`

- `static categoryLabel(string $cat): string` – Bezeichnungen der Gruppen (Willkommen-Bildschirm, Netzwerk „Neue Website“)
- `static choose(string $kit, string $mode): void` – Auswahl speichern – die nächste Anfrage spielt die Inhalte mit diesem Kit ein
- `static decide(string $setTheme, string $setSeed, string $cfgKit, mixed $cfgSeed): ?string` – Entscheidung aus Einstellungen (sys.theme, sys.seed) und Konfiguration ('kit'/'theme', 'seed') – auch für andere
- `static kits(?array $themes = null): array` – Kits zur Auswahl (erlaubte Kits der Website) mit Bezeichnung und Kurzbeschreibung aus theme.php ('label', 'description',
- `static mode(App $app): ?string` – Wie eingespielt wird – null = noch offen (Kit oder Inhalte nicht entschieden)
- `static pending(): bool` – Website noch ohne Seiten und Kit/Inhalte nicht entschieden? (je Anfrage gemerkt)
- `static preset(): array` – Vorauswahl im Willkommen-Bildschirm: [kit, inhalte] aus Einstellung bzw. Konfiguration
- `static reset(): void`

### `PageAccess` (class, `app/PageAccess.php`)

Zugriffsschutz für Seiten der Website durch Erweiterungen (z. B. Mitgliederbereich): $x->pageAccess([...]) in Core\Extension.

- `static active(): bool` – Gibt es überhaupt Schutzregeln (sonst müssen Listen nicht geprüft werden)?
- `static audience(array $table): string` – Wer sieht Einträge dieser Tabelle in der aktuellen Anfrage? 'all' = ungefiltert (Verwaltung, API, MCP, Konsole, angemeldete
- `static deny(array $page, Http\Request $r, ?array $table = null): ?Http\Response` – Prüfung beim Ausliefern einer Seite: null = frei zugänglich bzw. erlaubt, sonst die Antwort für Besucher ohne Zugriff.
- `static filterEntries(array $table, array $rows, string $audience): array` – Einträge einer geschützten Tabelle für das Publikum filtern: 'entry' => fn(array $table, array $entry, string $audience): ?array
- `static hidesTable(array $table): bool` – Ganze Tabelle für diese Anfrage leer? (öffentliche Seite, keine Erweiterung gibt einzelne Einträge frei)
- `static reset(): void` – Nach Änderungen an den Regeln (Erweiterung) bzw. für Tests
- `static restricted(array $page): bool` – Gehört die Seite zu einem geschützten Bereich (irgendeiner aktiven Erweiterung)?
- `static tableRestricted(array $table): bool` – Datentabelle geschützt (eigene Regel einer Erweiterung) oder ihre Detailseite in einem geschützten Bereich?
- `static visible(array $pages): array` – Für Besucher sichtbare Seiten einer Liste (geschützte entfernt; Redaktion sieht alle)

### `PageCache` (class, `app/PageCache.php`)

Ganzseiten-Cache für anonyme Besucher. Wird bei jeder Änderung im Admin geleert. Schlüssel enthält das Datum, damit zeitgesteuerte Hero-Themen korrekt wechseln.

- `static clear(): void`
- `static clearSite(string $siteKey): void` – Seiten-Cache einer anderen Website dieser Installation leeren (z. B. nach Änderungen an geteilten Daten)
- `static enabled(): bool`
- `static get(string $key): ?string`
- `static put(string $key, string $html): void`

### `PagePicker` (class, `app/PagePicker.php`)

Feldtyp „pages“ – mehrere Seiten auswählen (Chips mit Pfad im Seitenbaum + Dialog mit Baum, Suche, „mit Unterseiten“). Oberfläche: resources/js/_pages.js (Baum aus GET /admin/api/links?format=tree, wie die Linkauswahl).

Konstanten: `MAX`

- `static chip(array $it, string $name): string` – Ein Chip (auch Vorlage für das Skript)
- `static clean(array $f, mixed $raw): array|string` – Bereinigter Wert (ids: Liste, paths: Zeilen)
- `static items(array $f, mixed $value): array` – Chips für die Anzeige: value, label, trail (Elternseiten), href, sub (mit Unterseiten), lang, missing
- `static matches(array $list, ?array $page): bool` – Ist $page gemeint? (Format ids: „12“ = genau diese Seite, „12*“ = Seite und alle Unterseiten)
- `static pathOf(array $p): string` – Pfad einer Seite relativ zur Website (ohne Basis-Pfad), z. B. /en/kontakt; Startseite: / bzw. /en
- `static render(array $f, mixed $value, string $id, string $inputName, string $label, string $help, string $errHtml, string $describedBy, string $width): string` – Feld rendern (Core\Fields::renderField)
- `static values(mixed $value): array` – Werte als Liste (beide Formate)

### `PageSettingsTool` (class, `app/PageSettingsTool.php`)

„Seiteneinstellungen“: Werkzeug in der Werkzeugleiste der Website (Core\FrontendTools) – Redaktion mit Recht pages.manage ändert Titel, Adresse, Titel/Beschreibung für Suchmaschinen, Vorschaubild, Status und Menü der aktuellen Seite, ohne in die Verwaltung zu wechseln. Eintrag im Menü „⋯“ (ersetzt dort den Link in die Verwaltung), beim Ansehen und Bearbeiten; Modal wie

Konstanten: `ID`

- `static applies(array $bar): bool` – Nur auf echten Seiten (nicht auf Detailseiten von Einträgen oder im Vorlagen-Editor); Seitenvorlagen nur für die Administration
- `static definition(): array`

### `PageTemplates` (class, `app/PageTemplates.php`)

Seitenvorlagen für die Redaktion – eigene Seiten wie die Sonderseiten (type = 'template', template_for = '@page'): keine Adresse für Besucher, nicht im Seitenbaum, Menü, Sitemap, Suche, Entwürfe oder Linkauswahl. Anlegen, gestalten, benennen und anordnen nur mit „system.manage“ (Einrichtung › Website › Seitenvorlagen, /admin/seitenvorlagen); bearbeitet wird im

Konstanten: `KEY`, `MARK`, `PATH`

- `static all(): array` – Vorlagen in Reihenfolge: [['i', 'label', 'description', 'icon', 'page', 'parents', 'source' => Vorlagenseite], …]
- `static blocks(int $index): array` – Blöcke der Vorlage mit neuen IDs – auch in Spalten des Blocks „Layout“
- `static create(string $label, ?int $fromPage = null): int` – Neue Vorlage anlegen – leer oder als Kopie einer Seite (Arbeitsstand, neue Block-IDs); hängt sie ans Ende der Liste.
- `static editUrl(int $pageId): string` – Bearbeiten im Block-Editor (nur Administration)
- `static fields(): array` – Formular der Verwaltungsseite (ein Repeater – Reihenfolge per ↑/↓)
- `static isTemplatePage(?array $page): bool`
- `static save(array $templates): void` – Liste speichern; Titel der Vorlagenseiten = Name; nicht mehr gelistete Vorlagenseiten löschen
- `static suggested(?int $parentId): ?int` – Vorlage, die unter dieser übergeordneten Seite vorgeschlagen wird (Index) oder null
- `static templatePages(): array` – Alle Vorlagenseiten (auch nicht mehr gelistete)
- `static values(): array` – Gespeicherte Rohwerte fürs Formular

### `PageTool` (class, `app/PageTool.php`)

„Neue Seite“: Werkzeug in der Werkzeugleiste der Website (Core\FrontendTools) – Redaktion mit Recht pages.manage legt Seiten an, ohne in die Verwaltung zu wechseln. Knopf „Neue Seite“ (⌥N), auch beim Ansehen; öffnet ein Modal (panel size 'modal') mit Seitenbaum: Ort wählen (unter einer Seite, davor oder danach), Titel, Adresse, Vorlage, „Im Menü zeigen“.

- `static definition(): array`

### **API** `Pages` (class, `app/Pages.php`)

Seiten mit Editor.js-JSON als Inhaltsquelle. content_draft = Arbeitsstand, content_published = öffentlich sichtbarer Stand.

- `static addRevision(int $pageId, string $json, ?int $userId, string $note): void`
- `static all(): array`
- `static ancestors(array $page): array` – Vorfahren (von oben nach unten), z. B. für Brotkrumen
- `static anchors(array $page, bool $draft = false): array` – Alle Anker einer Seite (für Link-Autocomplete & Link-Auflösung)
- `static blocks(array $page, bool $draft = false): array` – Blöcke (Editor.js-Format) einer Seite
- `static byPath(string $path, ?string $lang = null): ?array` – Seite anhand des vollständigen Pfads, z. B. „leistungen/vorsorge“
- `static bySlug(string $slug): ?array` – Erste Seite mit diesem Slug (bevorzugt oberste Ebene) – für Altverweise; eindeutig ist der Pfad
- `static create(array $fields, array $blocks = []): int`
- `static db(): Database`
- `static descendantIds(int $id): array` – IDs aller Nachfahren (verhindert Zyklen beim Verschieben)
- `static discardDraft(int $id, ?int $userId, string $note = 'Entwurf verworfen'): bool` – Entwurf verwerfen: Arbeitsstand = veröffentlichte Fassung.
- `static find(int $id): ?array`
- `static flat(bool $withTemplates = false): array` – Flache Liste in Baumreihenfolge mit Tiefe (für Auswahllisten)
- `static hasUnpublished(array $page): bool`
- `static home(?string $lang = null): ?array` – Startseite der Sprache (Standard: aktuelle Sprache; Rückfall auf die Startseite der Standardsprache)
- `static menu(bool $includeDrafts = false): array` – Menübaum aus Seiten mit „Im Menü“ (öffentlich: nur veröffentlichte).
- `static move(int $id, ?int $parentId, int $index, ?int $before = null, ?int $after = null): ?string` – Seite im Baum verschieben: neuer Elternteil ($parentId null = oberste Ebene) und Position.
- `static openMarkers(?string $json): array` – Offene Platzhalter „[bitte ergänzen: …]“ (KI-Assistent, Core\AI) im JSON bzw. Text einer Seite
- `static plainUrl(array $page): string` – Adresse einer Seite auf der Hauptdomain (relativ, ohne Landing-Regeln)
- `static publish(int $id, ?int $userId): void` – Veröffentlichen – bei „Online ab“ in der Zukunft nur die Fassung übernehmen (geplant, Core\Schedule). Enthält der Entwurf noch Platzhalter „[bitte ergänzen: …]“ (z. B. aus dem Seiten-Generator), wird nicht
- `static published(): array`
- `static readBlocks(array $page, bool $draft = false, string $purpose = 'search'): array` – Blöcke für Leser (Suche, REST/MCP, Export) – nach Extension::blocksResolve; gerendert wird weiter mit blocks()
- `static rebuildPaths(): void` – Pfade aller Seiten aus Slugs und Eltern neu berechnen. Geänderte Pfade veröffentlichter Seiten legen eine
- `static revisions(int $pageId): array`
- `static sanitizeBlocks(array $blocks, ?array &$seen = null, bool $nested = false): array` – Bereinigt eingehendes Editor.js-JSON anhand der Block-Schemata. Block „Layout“ (Core\Layout): Spalten passend zum Raster,
- `static saveDraft(int $id, array $blocks, ?int $userId, string $note = 'Gespeichert'): void`
- `static slugTaken(string $slug, ?int $parentId, ?int $exceptId = null, ?string $lang = null): bool` – Slug auf gleicher Ebene schon vergeben?
- `static slugify(string $s): string`
- `static state(array $p): string` – Veröffentlichungsstand für Listen und die Werkzeugleiste: online | offline (war schon online, Status „Entwurf“) |
- `static translate(int $id, string $lang): array` – Übersetzung anlegen: Kopie der Seite in einer anderen Sprache (Entwurf), in der Übersetzungsgruppe verknüpft.
- `static translations(array $page): array` – Übersetzungen einer Seite: [sprache => seite] (inkl. der Seite selbst)
- `static tree(bool $withTemplates = true, ?string $lang = null): array` – Seitenbaum; mit $lang nur Seiten dieser Sprache (bei nur einer Sprache: alle)
- `static unpublish(int $id): bool` – Offline nehmen: Status „Entwurf“, die veröffentlichte Fassung (content_published) bleibt erhalten – „Online stellen“
- `static url(array $page): string` – Adresse einer Seite – auf Landing-Domains (Core\Landings) relativ zur Landingpage bzw. absolut zur Hauptdomain

### `PagesTool` (class, `app/PagesTool.php`)

„Seiten“: Seitenstruktur auf der Website (Werkzeug der Werkzeugleiste, Core\FrontendTools) – Redaktion mit Recht pages.manage sortiert und verschiebt Seiten (Ziehen oder ↑ ↓ ← →), schaltet Menü und Online/Offline, legt Unterseiten an und löscht – ohne in die Verwaltung zu wechseln. Seitenleiste in voller Höhe (panel drawer), beim Ansehen und Bearbeiten. Unter Seiten, die eine

Konstanten: `ID`

- `static definition(): array`

### `Passkeys` (class, `app/Passkeys.php`)

Passkeys (WebAuthn Level 2): Registrierung und Anmeldung mit Touch ID, Face ID, Windows Hello, Android-Bildschirmsperre, Passwortmanagern (iCloud-Schlüsselbund, Google, 1Password, Bitwarden …) oder Sicherheitsschlüsseln.

Konstanten: `TTL`, `MAX_PER_USER`, `NAME_MAX`, `TABLE`

- `static available(): bool` – Passkeys sind nur unter einem Domainnamen in einem sicheren Kontext möglich (HTTPS oder localhost)
- `static cleanName(string $name): string`
- `static count(Database $db, int $uid, ?string $rpId = null, string $table = self::TABLE): int`
- `static counts(Database $db, string $table = self::TABLE): array`
- `static creationOptions(string $handle, string $userName, string $displayName, array $exclude, array $pending = [], string $slot = 'reg'): array` – Optionen für navigator.credentials.create() (JSON, Binärwerte base64url). Challenge in der Sitzung.
- `static credHash(string $rawId): string`
- `static credentialHash(array $in): ?string` – Kennung (base64url) der Antwort – um das Konto zu finden (Anmeldung ohne Passwort)
- `static delete(Database $db, int $uid, int $id, string $table = self::TABLE): bool`
- `static deleteAll(Database $db, int $uid, string $table = self::TABLE): int`
- `static ensureTable(Database $db, string $table = self::TABLE): void` – Tabelle anlegen (Database::migrate für jede Website, Network::ensureSchema für die Netzwerk-Datenbank) – idempotent
- `static find(Database $db, string $credHash, ?int $uid = null, string $table = self::TABLE): ?array` – Zeile zur Kennung dieser Domain (optional nur für ein Konto)
- `static list(Database $db, int $uid, ?string $rpId = null, string $table = self::TABLE): array`
- `static origin(): string` – Erwartete Herkunft (Schema, Host, Port) – muss exakt passen
- `static pending(string $slot = 'reg'): ?array` – Offene Registrierung (Sitzung) – z. B. um den gewünschten Namen zu lesen
- `static providerName(?string $aaguid): ?string` – Namensvorschlag aus dem Anbieter (AAGUID)
- `static register(Database $db, int $uid, array $in, string $name, string $table = self::TABLE, string $slot = 'reg'): array|string` – Antwort von navigator.credentials.create() prüfen und speichern.
- `static rename(Database $db, int $uid, int $id, string $name, string $table = self::TABLE): bool`
- `static requestOptions(string $purpose, array $creds = [], bool $uv = false): array` – Optionen für navigator.credentials.get(). Ohne $creds: auffindbare Anmeldedaten (Anmeldung ohne Passwort, Autofill).
- `static rpId(): string` – RP-ID: Host der aktuellen Anfrage ohne Port (kleingeschrieben)
- `static unb64(mixed $s): ?string` – Base64url aus dem Browser (WebAuthn) prüfen und dekodieren – null bei fremdem Typ, zu lang oder ungültig
- `static userHandle(string $scope, int $uid, string $key): string` – Benutzerkennung für den Authenticator (keine E-Mail, keine fortlaufende ID) – 32 Byte
- `static verify(Database $db, string $purpose, array $in, ?array $row, string $handle, bool $requireHandle = false, string $table = self::TABLE): ?string` – Antwort von navigator.credentials.get() für eine gespeicherte Zeile prüfen; Zähler und „zuletzt verwendet“ aktualisieren.

### `PasswordReset` (class, `app/PasswordReset.php`)

„Passwort vergessen“: Link per E-Mail, neues Passwort festlegen (Anmeldeseite → /admin/passwort-vergessen, PasswordController).

Konstanten: `MINUTES`, `PER_IP`, `IP_WINDOW`, `PER_ADDRESS`

- `static base(): ?string` – Vertrauenswürdige Adresse dieser Website für Links in E-Mails – nie der Host-Header einer beliebigen Anfrage
- `static ensureTable(Database $db): void`
- `static find(string $token): ?array` – Anfrage zum Token – nur, wenn sie noch gilt; sonst null (keine Unterscheidung nach außen)
- `static hash(string $token): string` – Hash des Tokens – an Zweck und Website gebunden
- `static log(string $action, ?string $email, string $detail): void`
- `static minutes(): int` – Gültigkeit in Minuten (config 'password_reset_minutes')
- `static newToken(): string`
- `static request(string $email, string $ip): string` – „Passwort vergessen“ anfragen. Nach außen immer dieselbe Antwort; der Rückgabewert dient nur Protokoll und Selbsttest.
- `static reset(array $row, string $password): ?array` – Neues Passwort setzen (Richtlinie vorher geprüft). Prüft in einer Transaktion erneut; alle Sitzungen enden (auth_ver),
- `static selftest(): array` – Selbsttest (Konsole account:selftest): Token, Hash, Frist, einmalig, keine Rückschlüsse, 2FA/Passkeys bleiben,
- `static url(string $token): ?string`
- `static usable(array $row): bool` – Noch gültig? (Frist, einmalig, Konto vorhanden, nicht gesperrt, Adresse unverändert, kein Schatten-Konto)
- `static wellFormed(string $token): bool`

### **API** `Pdf` (class, `app/Pdf.php`)

PDF aus HTML – für Kern und Erweiterungen (Report, AVV, Rechnungen …), mit austauschbarer Engine wie Core\Charts.

Konstanten: `BUNDLED`

- `static available(): bool`
- `static dir(): string`
- `static engine(): ?string`
- `static font(string $id, array $weights = []): ?string` – Schrift laden (falls nötig): Normal, Fett, Kursiv, Fett kursiv als TTF → Fehlertext oder null
- `static fontCss(string $id, string $family = 'PdfFont', array $weights = []): string`
- `static registerEngine(string $name, callable $fn): void`
- `static render(string $html, array $opt = []): string` – HTML → PDF-Bytes. $opt: paper (A4), orientation (portrait|landscape), font (Standard 'lato' aus dem Kern mit 300/400/700/900;
- `static response(string $bytes, string $filename, bool $inline = false): Http\Response` – Antwort mit PDF (Download oder inline), nie zwischengespeichert

### `PdfLinks` (class, `app/PdfLinks.php`)

PDF-Links auf der Website: Klick öffnet eine kleine Auswahl „Ansehen“ (PDF-Viewer im Modal) | „Herunterladen“ (Datei). Links auf PDFs der Mediathek tragen beide Ziele als data-pdf-view / data-pdf-file; href bleibt das im Linkdialog gewählte Ziel (ohne JavaScript, Neuer Tab, Kopieren). Rich-Text (Core\Sanitizer), „Medien + Text“ und Downloads setzen die Attribute selbst;

- `static attrs(?array $m): string` – Zusätzliche Attribute für einen Link auf diese Datei – leer, wenn es keine PDF ist
- `static attrsForRef(string $ref): string` – Attribute für einen Verweis „media:ID“ bzw. „media:ID:viewer“
- `static enhance(string $html): string` – Links auf PDFs der Website ohne data-pdf-view ergänzen: href „{Medienordner}/….pdf“ (Datei) oder „/pdf/ID“ (Viewer).
- `static inject(string $html): string` – Skript einfügen, wenn die Seite PDF-Links hat (Beschriftungen in der Sprache der Seite)
- `static thumb(array $m, int $width = 480): ?string` – Vorschau der ersten Seite (Core\VideoThumbs, pdftoppm) – null, solange keine erzeugt ist

### `Permissions` (class, `app/Permissions.php`)

Rollen und Berechtigungen.

- `static all(): array`
- `static allows(?array $role, string $perm, ?string $table = null): bool`
- `static catalog(): array` – Alle Rechte, gruppiert (Bezeichnungen übersetzbar)
- `static defaults(): array` – Vorgaben beim ersten Start: Standardrollen – Rechte danach frei änderbar (außer admin/network), aber nie löschbar (builtin)
- `static installationAdmin(): bool` – Darf die angemeldete Person Einstellungen ändern, die für die ganze Installation gelten (Kits, Schriften, geteilte Medien,
- `static role(string $key): ?array`
- `static roles(): array`
- `static seed(Database $db): void` – Fehlende Standardrollen anlegen und als Standardrolle markieren (idempotent). Ältere Installationen hatten nur admin und

### `Presence` (class, `app/Presence.php`)

Präsenz der Redaktion: wer arbeitet gerade wo. Bewusst klein und ohne Bezug zu Seiten/Einträgen – kennt nur die Schlüssel site/type/id/user und Zeitstempel (übertragbar in andere Systeme, z. B. fluid für REDAXO 6).

Konstanten: `TTL`, `KEYS`, `ONLINE`, `ONLINE_TTL`, `ONLINE_EVERY`

- `static beat(array $who, array $meta = [], ?int $now = null): bool` – Eintrag melden/auffrischen. $meta: name, initials, avatar, label, url (frei wählbare Anzeige-Angaben). Ergebnis: neu?
- `static key(array $who): string`
- `static leave(array $who): void` – Abmelden (Seite verlassen)
- `static list(array $filter = [], ?int $now = null): array` – Aktive Einträge, gefiltert nach beliebigen Schlüsseln (z. B. ['site' => 'default'] oder ['site' => …, 'type' => 'page', 'id' => '12']),
- `static online(string $site, string $user, array $meta, ?int $now = null): void` – Online melden: $user = Personen-Schlüssel (Netzwerk-Konten installationsweit gleich, sonst {site}:{id}), $meta wie beat()
- `static onlineList(?string $site = null): array` – Wer ist online (alle Websites oder eine), neueste Meldung zuerst
- `static selftest(): array`
- `static setSince(array $who, int $since): void` – Zeitpunkt „seit“ eines aktiven Eintrags setzen (Bearbeitungssperre: Übernahme stellt sich vor den bisherigen Inhaber)

### `Proxy` (class, `app/Proxy.php`)

Zentraler Proxy für externe Quellen (Karten-Kacheln, Schriften, Vorschaubilder, APIs …).

- `static clear(?string $key = null): int`
- `static handle(string $key, string $path, array $server = []): Http\Response`
- `static http(string $url, array $headers = []): ?array` – HTTP-GET an eine registrierte Quelle (serverseitig, z. B. Geocoding, oEmbed).
- `static prune(): void` – Cache verkleinern, wenn er die eingestellte Größe überschreitet (älteste Einträge zuerst)
- `static register(string $key, array $def): void`
- `static source(string $key): ?array`
- `static sources(): array` – Alle registrierten Quellen (Kern + Theme)
- `static stats(): array`
- `static url(string $key, string $path = ''): string` – Öffentliche Adresse einer Quelle (absolut, da z. B. MapLibre-Styles absolute URLs brauchen)

### `PublicPaths` (class, `app/PublicPaths.php`)

Ablage im Webroot public/ – die einzige Stelle, die weiß, wo Code- und Design-Dateien öffentlich liegen.

Konstanten: `KITS`, `EXT`, `FONTS`, `AREAS`, `UPLOADS`, `RESERVED_SLUGS`

- `static dir(string $area, string $name = ''): string` – Absoluter Ordner, z. B. dir('kits', 'praxis') → …/public/assets/kits/praxis
- `static isReserved(string $slug, array $extra = []): bool`
- `static relative(string $area, string $name = ''): string` – Ort eines Bereichs relativ zu public/, z. B. 'assets/kits/praxis'; ohne $name der Hauptordner
- `static reservedSlugs(array $extra = []): array` – Gesperrte Adressen ganz oben: die feste Liste plus jeder Ordner/jede Datei in public/
- `static root(): string` – Absoluter Pfad von public/
- `static url(string $area, string $name, string $path = ''): string` – Öffentliche Adresse ohne Versions-Parameter, z. B. url('kits', 'praxis', 'css/site.css') → /assets/kits/praxis/css/site.css

### `RateLimiter` (class, `app/RateLimiter.php`)

Einfaches Sliding-Window-Rate-Limit in der Datenbank (keine IP im Klartext).

- `__construct(Database $db)`
- `clear(string $key): void`
- `count(string $key, int $windowSec): int`
- `hit(string $key): void`
- `tooMany(string $key, int $max, int $windowSec): bool`

### `RichText` (class, `app/RichText.php`)

Rich-Text-Stile für die Formatierungsleiste (resources/js/_rte.js): Namen der Stile und die Farbpalette des aktiven Themes.

- `static clientScript(): string` – <script type="application/json" id="cms-rich"> für Verwaltung und Werkzeugleiste der Website
- `static labels(): array` – Bezeichnungen der Farben (Oberfläche)
- `static palette(): array`

### **API** `Sanitizer` (class, `app/Sanitizer.php`)

Rich-Text-Whitelist. - inline:  <b> <strong> <i> <em> <a href> <br> <mark> <sup> <sub> <span class="c-…"> - block:   zusätzlich <p> <ul> <ol> <li> <h2> <h3> <h4> <blockquote>

Konstanten: `P_CLASSES`, `P_EXTRA`, `COLORS`

- `static block(?string $html): string`
- `static extra(): bool` – Wurden neuere Absatz-Stile (P_EXTRA) ausgegeben?
- `static inline(?string $html): string`
- `static safeHref(string $href): ?string`
- `static styled(): bool` – Wurden in dieser Anfrage Rich-Text-Stile (h2–h4, Zitat, t-*, c-*, mark, sup/sub) ausgegeben?

### `Schedule` (class, `app/Schedule.php`)

Zeitgesteuert veröffentlichen: „Online ab“ (online_from) und „Offline ab“ (online_until) für Seiten und Einträge eigener Tabellen. Beide Angaben sind geplante Aktionen: Zum Zeitpunkt wird der Inhalt veröffentlicht (Pages::publish, Entries::setStatus) bzw. offline genommen, danach ist das Feld wieder leer. Bis dahin gilt der Status – damit wirken alle Prüfungen auf „published“

Konstanten: `KEY`

- `static check(mixed $from, mixed $until): array` – Eingaben prüfen: „Online ab“ in der Vergangenheit entfällt (sofort), „Offline ab“ muss in der Zukunft und nach „Online ab“ liegen.
- `static ensure(array $table): bool` – Spalten einer Datentabelle anlegen (additiv); false für Eingangs-Tabellen
- `static input(?string $v): string` – Für <input type="datetime-local">
- `static label(array $row): string` – Hinweis für Listen und Werkzeugleiste, z. B. „Online ab 01.11.2026, 08:00“ – leer ohne Zeitplan
- `static norm(mixed $v): ?string` – Eingabe (datetime-local „2026-11-01T08:00“ oder „2026-11-01 08:00“) → „Y-m-d H:i:s“, leer/ungültig → null
- `static pending(array $row): bool` – Wartet der Inhalt auf „Online ab“?
- `static refresh(): void` – Nächsten fälligen Zeitpunkt neu berechnen (nach dem Speichern eines Zeitplans und nach run)
- `static run(): array` – Fällige Aktionen ausführen – unter Sperre, damit parallele Anfragen warten und danach den neuen Stand sehen
- `static tick(): void` – Je Anfrage vor der Auslieferung (App::handle): fällige Aktionen ausführen

### `Seeder` (class, `app/Seeder.php`)

Spielt die Startinhalte des aktiven Kits ein (kits/{name}/seed.php) – beim Erststart (Core\Onboarding entscheidet, wann). full   alles: Einstellungen, Seiten, Seitenreferenzen und 'after' (weitere Inhalte, z. B. Demo mit Bildern) empty  ohne Startinhalte: Einstellungen des Kits ohne Beispiel-Angaben (Demo-Name, Platzhalter, example.com, Links auf

- `__construct(App $app)`
- `run(string $mode = 'full'): void`

### `Seo` (class, `app/Seo.php`)

Meta-Daten, Canonical, Open Graph und JSON-LD (JSON-LD liefert das Theme).

- `static forEntry(array $template, array $table, array $entry): array` – Detailseite eines Datensatzes: Titel, Beschreibung, Bild und Canonical aus dem Eintrag
- `static forError(int $code): array`
- `static forPage(array $page, bool $filter = true): array`

### `Session` (class, `app/Session.php`)

Session nur für Redakteur:innen. Besucher bekommen kein Cookie. Cookie: HttpOnly; Secure (bei HTTPS); SameSite=Lax.

- `__construct(array $cfg)`
- `destroy(): void`
- `flash(string $type, string $message): void` – Einmal-Nachricht für die nächste Seite
- `forget(string $key): void`
- `get(string $key, mixed $default = null): mixed`
- `hasCookie(): bool`
- `name(): string`
- `regenerate(): void`
- `set(string $key, mixed $value): void`
- `start(bool $secure): void`
- `started(): bool`
- `takeFlash(): array`

### **API** `Settings` (class, `app/Settings.php`)

Key/Value-Einstellungen (JSON). Theme-Felder (z. B. „Praxisdaten“) ohne Präfix, System-Grundeinstellungen mit Präfix "sys.". Standardwerte kommen aus den Schemata (Theme bzw. SystemSchema).

Konstanten: `SECRETS`

- `__construct(Database $db)`
- `static decrypt(string $value): string` – Gegenstück zu encrypt(); Klartext-Werte kommen unverändert zurück, Fehler → ''
- `delete(string $key): void`
- `static encrypt(string $value): string` – Beliebiges Geheimnis verschlüsseln (wie das SMTP-Passwort), z. B. Zugangsdaten externer Quellen (Core\Sources)
- `forget(): void` – Zwischenspeicher verwerfen (nach einem zurückgerollten Probelauf, Core\Review\Queue)
- `get(string $key, mixed $default = null): mixed`
- `has(string $key): bool`
- `override(array $values): void` – Werte nur für diese Anfrage ersetzen (Vorschau) – nichts wird gespeichert
- `registerDefaults(array $defaults): void`
- `sealSecrets(): array` – Noch im Klartext gespeicherte Geheimnisse verschlüsseln (migrate) – idempotent. @return list<string> geänderte Schlüssel
- `set(string $key, mixed $value): void`
- `setMany(array $values): void`

### **API** `Site` (class, `app/Site.php`)

Eine Website der Installation (Multi-Site).

Konstanten: `DEFAULT`

- `__construct(string $key, array $cfg = [])`
- `allowedThemes(): array` – Themes, die diese Website verwenden darf [name => Bezeichnung]
- `defaultTheme(): string` – Theme beim Erststart bzw. wenn keines gewählt ist
- `hosts(): array`
- `isDefault(): bool`
- `label(): string`
- `landingHosts(): array` – Weitere Domains für Landingpages (Core\Landings) – gehören zur Website, sind aber nicht ihre Hauptadresse
- `mediaDir(string $sub = ''): string` – Öffentlicher Medienordner (Dateisystem)
- `mediaUrl(string $path = ''): string` – Öffentliche Adresse im Medienordner
- `storage(string $sub = ''): string` – Beschreibbarer Datenordner (Datenbank, Seiten-Cache, Uploads, Logs)

### `SiteExtract` (class, `app/SiteExtract.php`)

php bin/console site:extract <key> [--out=ordner] [--archive] [--all-kits] – eine Website einer Multi-Site als eigenständige Installation herauslösen. Die Quelle bleibt unverändert; es entsteht eine vollständige Kopie, in der die gewählte Website die einzige ist (Website „default“):

- `__construct($log)`
- `run(array $o = []): array`

### **API** `Sites` (class, `app/Sites.php`)

Verzeichnis der Websites einer Installation (config/sites/*.php) und Auflösung über die Domain. Ohne Dateien in config/sites gibt es genau eine Website „default“ (Einzel-Installation).

- `static all(): array`
- `static create(string $key, array $hosts, string $theme = '', array $extra = []): array` – Neue Website anlegen: schreibt config/sites/{key}.php mit eigenen Schlüsseln.
- `static dir(): string`
- `static flush(): void`
- `static fromCli(array &$argv): string` – Website von Kommandozeile/Umgebung: --site=key oder CMS_SITE=key
- `static multi(): bool`
- `static normalizeHost(string $host): ?string` – Domain prüfen und normalisieren (klein, ohne Schema/Pfad, optional :port) – null = ungültig
- `static removeTopLevel(string $src, string $key, bool $leadingComment = false): ?string` – Schlüssel samt Wert aus dem obersten return-Array einer PHP-Datei entfernen (Tokenizer, keine Auswertung).
- `static replaceTopLevel(string $src, string $key, string $phpValue): ?string` – Wert eines Schlüssels im obersten return-Array einer PHP-Datei ersetzen bzw. ergänzen (Tokenizer, keine Auswertung).
- `static resolve(string $host, ?string $fallback = Core\Site::DEFAULT): ?string` – Website zur Domain. Reihenfolge: exakter Host (mit Port) → Host ohne Port → Rückfall.
- `static setHosts(string $key, string $action, string $host, bool $landing = false): array` – Domain einer Website hinzufügen oder entfernen (site:hosts, Netzwerk-Übersicht).
- `static setOption(string $key, string $field, string|bool $value): void` – Einfachen Wert (bool/string) in config/sites/{key}.php setzen, z. B. 'environment' oder 'redirect_to_primary' (Verwaltung → Domain)
- `static setPrimary(string $key, string $host): array` – Hauptadresse einer Website festlegen: $host (schon in 'hosts') an die erste Stelle, Reihenfolge der übrigen bleibt.
- `static validKey(string $key): bool`

### `Slots` (class, `app/Slots.php`)

Slots der Verwaltung: die festen Stellen, an denen Erweiterungen Inhalte in Core-Seiten einhängen (Extension::pageList, pagePanel, tableActions, mediaPanel, dashboard, account). Erweiterungen liefern Daten, der Core rendert und escaped hier.

Konstanten: `ALL`, `TONES`

- `static actions(array $list, array $vars = []): array` – Aktionen prüfen: Beschriftung, Pfad dieser Installation (beginnt mit „/“, kein „//“), Platzhalter aus $vars (kodiert).
- `static card(array $spec, string $variant = 'card', string $owner = ''): string` – Karte rendern (alles escaped). $variant: 'card' (adm-card mit h2), 'media' (Abschnitt der Mediathek, h3),
- `static localPath(string $href): bool` – Pfad dieser Installation? („/…“, kein „//“, kein Backslash, keine Steuerzeichen, keine offenen Platzhalter)
- `static tone(mixed $tone): string`

### `SpamGuard` (class, `app/SpamGuard.php`)

Mehrstufiger Spamschutz ohne Cookies und ohne externe Dienste: 1. Honeypot-Feld 2. Signiertes Zeit-Token (Mindest-Ausfüllzeit, Ablauf, Einmalverwendung)

Konstanten: `HONEYPOT`

- `static challenge(string $form): array` – Konfiguration für das Frontend-Script
- `static check(string $form, array $post, string $ip): array`
- `static difficulty(): int`
- `static leadingZeroBits(string $bin, int $bits): bool`
- `static token(string $form): string`

### `StorageUsage` (class, `app/StorageUsage.php`)

Speicherverbrauch einer Website, aufgeschlüsselt (Übersicht der Website, Netzwerk → Speicher): Medien nach Art (Bilder, Videos, Audio, Dokumente, Sonstiges – Summe der Originale laut Mediathek), Papierkorb, erzeugte Bildgrößen & Vorschaubilder (Rest des Medienordners), Datenbank, Suchindex, KI, Zwischenspeicher,

Konstanten: `TTL`

- `static forget(?string $key = null): void` – Zwischenspeicher verwerfen (nach „Neu zählen“)
- `static installation(bool $fresh = false): array` – Installation (nur Netzwerk): geteilte Pools und Ordner, die keiner Website gehören (Protokolle, Sitzungen, Chat, Support …).
- `static kind(string $mime): string` – Art einer Datei aus dem MIME-Typ
- `static parts(): array` – Teile: Schlüssel => [Bezeichnung, Symbol, Farbton für den Balken]
- `static site(string $key, bool $fresh = false): array`

### `StructuredData` (class, `app/StructuredData.php`)

Strukturierte Daten (schema.org, JSON-LD) als verknüpfter @graph je Seite.

Konstanten: `TYPES`

- `static add(array $node): void`
- `static collect(Block $b): void` – Nach dem Rendern eines Blocks: deklarierte Daten des Blocks übernehmen
- `static events(array $table, array $occurrences, int $max = 20): void` – Kalender/Nächste Termine: Termine als Event
- `static faq(array $items, string $q, string $a): void`
- `static field(array $table, array $e, array $types, array $names = []): mixed` – Wert des ersten passenden Feldes (Typ + optional Namensmuster) als Text bzw. Rohwert – auch für Core\VCard
- `static graph(array $page, ?array $table = null, ?array $entry = null, array $seo = []): ?array` – Vollständiger Graph für eine Seite bzw. Detailseite
- `static itemList(array $table, array $rows, string $name = ''): void` – Datenliste: verlinkte Einträge als ItemList
- `static orgOf(array $table, array $e): array` – Veranstalter/Herausgeber eines Eintrags: die eigene Organisation – bei fremden Einträgen geteilter Tabellen
- `static organization(): ?array` – Organisation aus dem Theme (+ Geo-Koordinaten der Karte)
- `static reset(): void`
- `static typeOf(array $table): ?string` – Schema.org-Typ einer Tabelle (Einstellung oder automatisch)

### `Svg` (class, `app/Svg.php`)

SVG-Grafiken für die Mediathek: bereinigen (Positivliste) und leicht optimieren – eigener Code auf DOMDocument, ohne fremde Bibliothek (der Kern ist MIT; enshrined/svg-sanitize wäre GPL).

Konstanten: `MIME`, `MAX_BYTES`, `MAX_NODES`, `CSP`

- `static canRasterize(): bool` – Kann der Server SVG in Pixel umwandeln? (Imagick mit SVG-Unterstützung)
- `static clean(string $raw): array` – Bereinigt und optimiert eine SVG-Datei.
- `static detect(string $path, string $mime, string $name = ''): bool` – Ist das (laut finfo, Name und Dateianfang) eine SVG-Datei?
- `static guard(string $dir): void` – Kopfzeilen für direkt aufgerufene .svg-Dateien im Medienordner (Apache, .htaccess): CSP mit sandbox + nosniff.
- `static note(array $stats): string` – Meldung für die Oberfläche: „SVG bereinigt und optimiert: 48 KB → 12 KB, 3 unsichere Elemente entfernt“
- `static rasterize(string $file, int $size = 1024): ?GdImage` – SVG (bereinigt, aus der Mediathek) als quadratisch eingepasstes GD-Bild – null ohne Imagick oder bei Fehlern
- `static samples(): array` – Typische Exporte aus Illustrator, Inkscape und Figma: [SVG, Teile, die erhalten bleiben müssen]
- `static selftest(?string $dump = null): array`

### `SystemSchema` (class, `app/SystemSchema.php`)

Schema der Grundeinstellungen (Admin → System). Theme-unabhängig.

- `static defaults(): array`
- `static fields(): array`
- `static groups(): array` – Gruppen gefiltert nach dem Funktionsumfang der Website (Features)

### `TargetEdit` (class, `app/TargetEdit.php`)

„Ziel bearbeiten“: kleine Aktion an Karten und Kacheln, die auf eine andere Seite oder einen Eintrag zeigen (Leistungs-Kacheln, Team-Karten, Datenlisten …). Nur für die angemeldete Redaktion mit Recht – Besucher, der Seiten-Cache und die Ansicht „wie Besucher“ (?live=1) bekommen nichts (keine Abfrage, kein Markup).

- `static active(): bool` – Zeigt die Website gerade Bearbeiten-Aktionen? (angemeldet, nicht „wie Besucher“ – im Seiten-Editor immer)
- `static html(?string $ref, ?string $label = null, array $o = []): string` – Die Aktion als HTML (leer für Besucher, ohne Recht und ohne internes Ziel).
- `static reset(): void` – Zwischenspeicher leeren (Selbsttest)
- `static resolve(string $ref): ?array` – Verweis bzw. Adresse → Ziel. ['kind' => 'page', 'ref' => 'page:12', 'page' => […]] bzw.
- `static selftest(): array` – Selbsttest (targetedit:selftest) in einer Transaktion, die zurückgerollt wird: Auflösen (page:ID, Anker, Pfad, Pfad mit
- `static target(string $ref, ?string $label = null): ?array` – Ziel samt Rechteprüfung und Adressen – null ohne Recht bzw. ohne internes Ziel.

### **API** `Theme` (class, `app/Theme.php`)

Kit-System (Klassenname „Theme“ bleibt aus Kompatibilitätsgründen; Pfade liefert Core\Kit).

Konstanten: `TUNES`, `ROW_WIDTHS`

- `__construct(string $name)`
- `addBlocks(array $defs, string $dir, string $extension): void` – Blöcke einer Erweiterung hinzufügen (Theme-Blöcke gleichen Namens haben Vorrang)
- `asset(string $path): string`
- `static available(): array`
- `backgrounds(): array`
- `block(string $type): ?array`
- `blocks(): array`
- `static capture(string $__file, array $__vars): string`
- `compatible(): bool` – Passt das Theme zur Core-Version? (theme.php → 'requires' => '>=1.0')
- `conditionalCss(?array $types): array` – Stylesheets, die für die übergebenen Blocktypen zusätzlich nötig sind (null = alle).
- `conditionalJs(array $types): array` – Skripte, die nur für bestimmte Blocktypen geladen werden
- `editorConfig(): array`
- `editorJs(): array` – Skripte, die auch im Seiten-Editor laufen dürfen (theme.php → 'editor_js' => ['js/b-loop.js', …]). Sonst lädt der Editor
- `fontPreloads(): string` – <link rel="preload"> für die wichtigsten Schnitte (theme.php → 'fonts' → 'preload', Dateien des Kits) und für
- `fontUrl(string $path): string` – Öffentliche Adresse einer Theme-Schrift ohne Versions-Parameter (muss exakt der URL im CSS entsprechen)
- `forms(): array`
- `hasAsset(string $path): bool` – Bringt das Kit die öffentliche Datei mit (public/assets/kits/{name}/…)?
- `iconFont(): ?string` – TTF für den App-Icon-Generator (theme.php → 'fonts' → 'icon', relativ zum Theme-Ordner)
- `label(): string`
- `makeBlock(array $b, ?Block $prev = null, ?array $next = null): ?Block`
- `navigation(): array` – Navigation aus Blöcken der Startseite mit Tune „showInNav“
- `nestable(string $type, string $variant = ''): bool` – Darf der Block in einer Spalte eines Layouts stehen? theme.php → blocks → {typ} → 'nestable' => true | false | ['variante', …]
- `nestableVariants(string $type): array|bool` – true = alle Varianten, Liste = nur diese Varianten, false = nicht verschachtelbar
- `partial(string $name, array $vars = []): string` – Fragment rendern (Core\Fragments): Nur-Kern-Fragmente (video-embed, editor, toolbar) immer aus dem Kern,
- `previewBlock(array $b): ?string` – Vorschau eines Blocks im Editor (Seitenstart und EditorController::preview): Erweiterungen lösen ihn auf (Extension::blocksResolve,
- `render(string $template, array $vars = []): string`
- `renderBlock(Block $block): string`
- `renderBlocks(array $blocks): string`
- `renderInner(Block $block): ?string` – Inhalt eines Blocks ohne Abschnitts-Hülle (Block-Vorlage des Kits, Kern-Block, Erweiterung, eigener Block); null = kein Renderer
- `renderRow(array $row): string` – Reihe „Blöcke nebeneinander“ ausgeben: ein Abschnitt mit den Optionen des ersten Blocks (Hintergrund, Anker, Abstände,
- `static rowSpans(array $widths): array` – Spaltenanteile einer Reihe (Zwölftel als flex-grow): feste Breiten wie gewählt, „auto“ und der erste Block teilen den Rest.
- `rowable(Block $block): bool` – Unterstützt das Kit Reihen (theme.php → 'rows' => false schaltet sie ab) – und rendert der Block keine eigene Hülle?
- `sanitizeTunes(array $t, array $def = []): array`
- `settingsFields(): array`
- `settingsGroups(): array` – Gruppen des zentralen Einstellungsformulars (Titel vom Theme)
- `settingsTitle(): string`
- `spacing(): array` – Abstände eines Abschnitts (oben/unten): „Groß“ nur, wenn das Kit es anbietet (theme.php → 'space_large' => true, Klassen pt-large/pb-large)
- `static toolbarHost(string $html): string` – Werkzeugleiste als Declarative Shadow DOM (<template shadowrootmode="open">, resources/js/_shadow.js rüstet

### `Toolbar` (class, `app/Toolbar.php`)

Redaktions-Werkzeugleiste auf der Website (nur angemeldet) – ein Aufbau für alle Themes und Modi.

- `static context(array $v): array` – Alles, was die Ansicht braucht (Rechte, Adressen, Zustände)
- `static render(array $vars): string`
- `static texts(string $kind = 'page'): array` – Texte für _bar.js (Sprache der Verwaltung)

### `Totp` (class, `app/Totp.php`)

Zwei-Faktor-Anmeldung per Einmalcode (TOTP, RFC 6238 – SHA-1, 6 Stellen, 30 Sekunden; kompatibel mit Authenticator-Apps wie 2FAS, Aegis, Google/Microsoft Authenticator, 1Password).

Konstanten: `DIGITS`, `PERIOD`

- `static base32(string $bin): string`
- `static base32Decode(string $s): string`
- `static code(string $secret, int $step): string` – Code für einen Zeitschritt (RFC 4226 HOTP mit Zähler = Zeitschritt)
- `static hashRecovery(string $code): string`
- `static match(string $secret, string $code, ?int $now = null): ?int` – Zeitschritt des Codes (±1 Schritt Toleranz für Uhrabweichung) oder null
- `static newSecret(): string`
- `static open(?string $sealed, string $appKey): ?string`
- `static qrDataUri(string $text): string` – QR-Code als data:-URI (SVG) – für <img>, ohne Inline-Styles/Skripte im Dokument
- `static recoveryCodes(int $n = 10): array`
- `static required(string $role): bool` – Verlangt diese Website für die Rolle die Zwei-Faktor-Anmeldung?
- `static reset(int $userId): void` – Zwei-Faktor-Anmeldung (App und Passkeys) eines lokalen Kontos dieser Website zurücksetzen (Verwaltung „Benutzer & Rollen“, user:2fa-reset).
- `static seal(string $secret, string $appKey): string`
- `static uri(string $secret, string $account, string $issuer): string`
- `static verifyRecovery(Database $db, array $row, string $input): ?string` – Nur Wiederherstellungscode prüfen und verbrauchen – auch für Konten, die nur Passkeys haben (Core\Passkeys).
- `static verifyUser(Database $db, array $row, string $input, string $appKey): ?string` – Prüft einen Code für einen Benutzer-Datensatz und vermerkt ihn (totp_last bzw. verbrauchter Wiederherstellungscode)

### `Tutorials` (class, `app/Tutorials.php`)

Tutorials (Handbuch & Hilfe › Tutorials, Übersicht „Hilfe & Einstieg“, php bin/console tutorials:export).

Konstanten: `LEVELS`

- `static catalogue(?bool $en = null): array` – Katalog in der Sprache der Verwaltung: ['tracks' => …, 'tutorials' => [kurzname => [… 'web' => Link zum Video auf der
- `static docsHost(): string` – Hostname der Produkt-Website für Beschriftungen („studio.klxm.de“)
- `static docsUrl(): string` – Adresse der Produkt-Website ohne abschließenden Schrägstrich; '' = keine Links nach außen (White-Label oder ausgeschaltet)
- `static en(?string $locale = null): bool` – Englische Verwaltung? (Links und Texte des Katalogs)
- `static export(string $videos): array` – Katalog als JSON-Struktur für die Produkt-Website (php bin/console tutorials:export): Kern-Tutorials DE/EN mit Schritten
- `static levelEn(string $level): string`
- `static overviewUrl(?bool $en = null): ?string` – Übersicht aller Tutorials auf der Produkt-Website
- `static recommended(array $tracks): string` – Empfohlene Zielgruppe nach Rolle/Rechten (alle bleiben sichtbar)
- `static trailerUrl(?bool $en = null): ?string` – Trailer „KLXM Studio im Überblick“ auf der Startseite der Produkt-Website
- `static videoUrl(string $slug, ?bool $en = null): ?string` – Video eines Kern-Tutorials auf der Produkt-Website (null ohne docs_url)

### `VCard` (class, `app/VCard.php`)

Visitenkarten (vCard 3.0, RFC 2426) zum Speichern im Adressbuch – iOS, Android, Outlook, Thunderbird.

Konstanten: `ORG_PATH`, `PERSON_PREFIX`, `IMAGE_MAX_BYTES`, `IMAGE_MAX_SIDE`

- `static build(array $d): string` – vCard 3.0 aus Rohangaben (Werte ungefiltert, null/leer = weglassen):
- `static fileName(string $name): string` – Dateiname aus einem Namen: Kleinbuchstaben, Umlaute umschrieben, Bindestriche
- `static fold(string $line): string` – Zeile nach 75 Oktetts falten (Folgezeilen mit Leerzeichen), ohne UTF-8-Zeichen zu teilen
- `static hoursNote(): ?string` – Öffnungszeiten als Notiz: „Öffnungszeiten:\nMo–Fr: 8:00–12:00\nSa: nach Vereinbarung“ (Wochentage zusammengefasst)
- `static image(string $path): ?array` – Bilddatei → ['type' => 'PNG'|'JPEG', 'data' => …] oder null
- `static imageOf(int $id): ?array` – Bild einer Medien-ID für PHOTO: PNG/JPEG unverändert, wenn klein genug, sonst (auch WebP/GIF) mit GD auf
- `static org(): ?string` – vCard der Organisation oder null (keine Angaben)
- `static orgData(): ?array` – Angaben der Organisation für die Karte oder null, wenn weder Telefon, E-Mail noch Adresse eingetragen sind.
- `static orgFile(): string` – Dateiname der Organisations-Karte, z. B. „praxis-dr-muster.vcf“
- `static person(array $table, array $e): string` – vCard einer Person aus einem veröffentlichten Eintrag
- `static personTable(string $handle): ?array` – Tabelle für Personen-Karten: Inhaltstabelle mit Schema-Typ „Person“ (Einstellung der Tabelle), sonst null
- `static personUrl(array $table, array $e): ?string` – Adresse der Personen-Karte eines Eintrags oder null (Tabelle ohne Schema-Typ „Person“, Entwurf, ohne Slug)
- `static reset(): void` – Zwischenspeicher der Anfrage leeren (Selbsttest, geänderte Einstellungen in derselben Anfrage)
- `static response(string $body, string $file): Http\Response` – HTTP-Antwort für eine Karte (Download, kurz zwischenspeicherbar, nicht indexieren)
- `static selftest(): array` – Erzeugen + Zurücklesen: Aufbau, Maskierung, Faltung, CRLF, UTF-8, Bild; dazu die Karten dieser Website
- `static text(string $s): string` – TEXT-Wert maskieren (RFC 2426 §4): \ ; , und Zeilenumbrüche; Redaktionsnotizen [# … #] fallen weg

### `VideoThumbs` (class, `app/VideoThumbs.php`)

Automatische Vorschaubilder für Videos (mit ffmpeg) und PDF-Dokumente (1. Seite, mit pdftoppm aus poppler-utils) der Mediathek – ohne das jeweilige Programm wirkungslos. PDF: abschaltbar mit media.pdf_thumbs = false; Ablage, Sperren, Grenzen und Ablauf wie bei Videos (variants_json['poster'], Dateien {zufall}-p-{breite}.webp).

- `static console(array $args): int` – media:thumbs [--all] [--missing] [--pool=key] – Vorschaubilder nachträglich erzeugen
- `static data(array $m): ?array` – Vorhandenes automatisches Vorschaubild: ['base', 'sizes' => [['w', 'f']], 'W', 'H', 'at'] oder null
- `static enabled(): bool` – Eingeschaltet (Standard) und ffmpeg/ffprobe vorhanden?
- `static enabledFor(array $m): bool` – Für diese Datei eingeschaltet und das nötige Programm vorhanden?
- `static failedRecently(array $m): bool` – Zuletzt fehlgeschlagen (innerhalb der Wartezeit)?
- `static files(array $m): array` – Dateien eines Vorschaubilds relativ zum Medienordner
- `static generate(array $m, bool $wait = false, bool $force = false): string` – Vorschaubild erzeugen. $wait: auf Sperre/freien Platz warten (Hintergrund, CLI), sonst sofort 'busy'.
- `static handles(?array $m): bool` – Datei, für die es automatische Vorschaubilder gibt (Video oder PDF)
- `static isPdf(?array $m): bool`
- `static isVideo(?array $m): bool`
- `static pending(array $m): bool` – Fehlt ein Vorschaubild, das sich jetzt erzeugen ließe?
- `static queue(array $m): void` – Nach der Antwort erzeugen (Upload, Import, Ersetzen) – billig, falls ffmpeg fehlt

### `Welcome` (class, `app/Welcome.php`)

Begrüßungsfenster der Verwaltung („Willkommen“ + nächste Schritte, app/Admin/views/_welcome-modal.php): erscheint einmal je Konto automatisch, sobald der Erststart erledigt ist (Core\Onboarding) – für alle, die die Grundeinstellungen pflegen dürfen – und lässt sich jederzeit über Hilfe & Support → „Erste Schritte“ wieder öffnen (?erste-schritte=1).

Konstanten: `QUERY`

- `static markSeen(int $userId): void` – Als gezeigt merken (beim automatischen Anzeigen)
- `static state(?array $user, array $query): array` – Fenster auf dieser Seite zeigen? [zeigen, automatisch]
- `static steps(): array` – Nächste Schritte mit Stand: Kit, die Prüfungen der Übersicht (Dashboard\Metrics::setupChecks), im Netzwerk „weitere

## Core\AI

### **API** `Ai` (class, `app/AI/Ai.php`)

KI-Dienst des Kerns auf Basis von Symfony AI (symfony/ai-platform + Bridges Ollama, Mistral, OpenAI, OpenAI-kompatibel). Standard: AUS. Drei Fähigkeiten (CAPS) mit eigenem Modell – optional eigenem Anbieter: text    Texte erzeugen/umschreiben/übersetzen, SEO (chat(), complete())

Konstanten: `CAPS`, `PROVIDERS`, `VISION_MAX`

- `static boot(): void` – Beim Start: Recht „ai.use“ einmalig der Redaktion geben; semantische Suche für die Wissensdatenbank einsetzen
- `static callsToday(array $caps = […]): int` – Aufrufe dieser Website heute (Standard: Texte + Bilder, inkl. Fehler; ohne Suche) – für das Tageslimit des KI-Assistenten.
- `static capability(string $cap, ?array $cfg = null): array` – Angaben einer Fähigkeit bzw. eines Zwecks: provider, base_url, api_key, region, model, profile, label, timeout, fallback,
- `static chat(array|string $messages, array $o = []): array` – Text erzeugen. $messages: Anweisung (string) oder Verlauf [['role' => 'system'|'user'|'assistant', 'content' => '…'], …]
- `static complete(string $prompt, string $system = '', array $o = []): string` – Kurzform: Anweisung → Text
- `static config(?array $raw = null): array` – Normalisierte Konfiguration dieser Website (Verwaltung ← config.local.php ← config/sites/{key}.php), siehe Core\AI\Profiles.
- `static embed(array $texts, string $kind = 'document', array $o = []): array` – Embeddings für Texte (normiert nicht). $kind: document | query (manche Modelle erwarten Präfixe).
- `static embedder(?array $cfg = null, ?float $timeout = null): ?AI\Embedder` – Embeddings-Dienst (ohne Schalter der Website – Aufrufer prüft enabled('embed') bzw. Search::semanticActive()).
- `static enabled(string $cap = 'text'): bool` – Darf diese Website die Fähigkeit nutzen? Funktion „ai“ + Anbieter/Modell konfiguriert + Schalter der Website.
- `static endpoint(array $cap): string` – Adresse, an die Anfragen gehen
- `static failed(Throwable $e, ?array $cap = null): void`
- `static fileConfig(): array` – Werte aus den Konfigurationsdateien der Installation (config.php ← config.local.php) – in der Verwaltung gesperrt
- `static fileLayer(): array` – Konfigurations-Ebene aus den Dateien dieser Website (config.php ← config.local.php ← config/sites/{key}.php)
- `static for(string $purpose): array` – Zweck → Verbindung → Modell (Kurzform): Ai::for('vision')
- `static health(): array` – Prüfungen für `health`: nur konfigurierte und auf dieser Website eingeschaltete Fähigkeiten
- `static imageForVision(string|int $image): array` – Verkleinerte Bilddaten für vision(): vorhandene Größe ≥ 1024 px (WebP) oder per GD verkleinertes JPEG
- `static installationConfig(): array` – Konfiguration der Installation (Verwaltung ← Dateien), ohne Werte einzelner Websites – z. B. für die Wissensdatenbank
- `static isExternal(array $cap): bool` – Verlassen Daten den Server? (Anbieter nicht auf localhost/privatem Netz)
- `static isLocalHost(string $host): bool`
- `static paused(?array $cap = null): bool` – Anbieter hatte gerade einen Fehler → vorerst nicht fragen. $cap: capability() oder config() (dann: Embeddings)
- `static platform(array $cap, float $timeout): Symfony\AI\Platform\PlatformInterface` – Symfony-AI-Platform für eine Fähigkeit (Anbieter-Bridge mit Positivliste und Zeitlimit)
- `static platformFor(string $purpose): Symfony\AI\Platform\PlatformInterface` – Symfony-AI-Platform für einen Zweck (Verbindung + Zeitlimit der Verbindung bzw. für Redaktionsaufgaben)
- `static raw(): array` – Rohwerte dieser Website im Format 2 (Verwaltung ← Dateien), Grundlage für config() und die Verwaltung
- `static recovered(?array $cap = null): void`
- `static shortError(Throwable $e): string` – Fehlermeldung ohne Schlüssel und ohne Anfrageinhalt
- `static siteSettings(): array` – Schalter dieser Website: KI an/aus und je Fähigkeit (Grundeinstellungen → KI)
- `static store(array $values): void` – Installations-Konfiguration speichern (nur Agentur/Netzwerk; Datei außerhalb des Webroots, 0600)
- `static stored(): array` – In der Verwaltung gespeicherte Installations-Konfiguration (storage/ai/config.json)
- `static stream(array|string $messages, array $o, callable $onDelta): array` – Text erzeugen und Stück für Stück ausgeben (Streaming, z. B. für Server-Sent Events – Core\Http\Sse).
- `static test(string $cap = 'embed', ?array $cfg = null): array` – Verbindung einer Fähigkeit prüfen (unabhängig vom Schalter der Website). $cfg: eigene Konfiguration (z. B. Installation)
- `static track(string $cap, array $c, int $in, int $out, int $ms, bool $error, int $seconds = 0): void` – Aufruf zählen (Website, Tag, Fähigkeit, Anbieter, Modell) – nie Inhalte
- `static usage(int $days = 30): array` – Nutzung der letzten $days Tage je Fähigkeit und Modell, mit Kostenschätzung (config 'prices', USD je 1 Mio. Tokens)
- `static vision(string|int $image, string $prompt, array $o = []): array` – Bild beschreiben lassen (z. B. Alt-Text). $image: Medien-ID der Website oder Dateipfad.

### `AiException` (class, `app/AI/AiException.php`)

Fehler der KI-Funktionen – Meldung ist für die Oberfläche geeignet (ohne Schlüssel, ohne Inhalte).


### `Assist` (class, `app/AI/Assist.php`)

KI-Assistent für die Redaktion (Texte, Übersetzungen, SEO, Alt-Texte, Support, Tabellen-Felder).

Konstanten: `DEFAULT_DAILY_CAP`

- `static alt(string|int $image, array $langs, string $context = ''): array` – Alt-Text-Vorschlag für ein Bild (Medien-ID oder Dateipfad) in den gewünschten Sprachen.
- `static available(string $cap = 'text'): bool` – Darf die angemeldete Person den Assistenten für diese Fähigkeit nutzen?
- `static brand(): string` – Name des KI-Bereichs in der Verwaltung (config 'ai_brand', für Agenturen anpassbar)
- `static call(string $cap, array $pack, string $fake = '', string|int|null $image = null): array` – Aufruf mit Prüfung von Recht, Schalter und Tageslimit. $pack aus Prompts::…; $fake: Antwort des Test-Anbieters
- `static clean(string $out, string $format): string` – Antwort des Modells säubern und auf die erlaubten Tags des Feldes bringen
- `static client(): ?array` – Konfiguration für resources/js/_ai.js (null = kein Assistent für diese Person/Website)
- `static clientScript(): string` – <script type="application/json" id="cms-ai"> für Verwaltung und Werkzeugleiste der Website (leer ohne Assistent)
- `static context(?int $pageId, ?array $entry = null): string` – Kurzer Zusammenhang für „Freier Auftrag“ (Seite oder Eintrag)
- `static ctx(): array` – Hinweise der Website und Glossar für alle Anweisungen
- `static cut(string $s, int $max): string` – An Wortgrenze kürzen (ohne „…“ – Suchmaschinen kürzen selbst)
- `static entryText(array $t, array $e, int $max = 6000): string` – Textfelder eines Eintrags als Klartext
- `static friendly(string $m): string` – Technische Fehlermeldungen des Anbieters in verständliche Hinweise übersetzen (Details stehen im Fehlerprotokoll)
- `static glossary(): array` – Glossar aus Grundeinstellungen → KI: je Zeile „Begriff“ (nie übersetzen) oder „Begriff = feste Übersetzung“
- `static history(int $userId, int $limit = 200): array` – Verlauf einer Person (neueste zuerst)
- `static json(string $s): array` – JSON-Objekt aus einer Modell-Antwort lesen (auch mit Codeblock oder Text drumherum)
- `static kbPolish(string $title, string $body): array` – Wissensartikel-Entwurf überarbeiten (Titel + Markdown)
- `static log(string $kind, string $action, string $target, string $status = 'suggested', string $model = '', int $ms = 0): void` – Verlauf des KI-Assistenten protokollieren – nur Metadaten (Zeit, Person, Bereich, Ziel, Status, Modell, Dauer), nie Inhalte.
- `static logo(string $class = 'kia-logo'): string` – KLXM-Logo (übernimmt die Textfarbe) für den Kopf des KI-Bereichs
- `static navVisible(): bool` – Menüpunkt „KLXM AI“: Funktion „ai“, Recht „ai.use“ und KI eingeschaltet – die Administration sieht ihn auch ohne Einrichtung (Hinweis)
- `static pageText(array $page, int $max = 6000): string` – Titel + sichtbarer Text einer Seite (Entwurf)
- `static placeholders(string $s): int`
- `static quota(): array` – Tageslimit: cap (0 = unbegrenzt), used (heute), left (null = unbegrenzt)
- `static schema(string $description, array $types): array` – Feldvorschlag für eine Datentabelle. $types: erlaubte Typen (Schlüssel aus Tables::TYPES)
- `static seo(string $title, string $text, string $lang, int $titleMax = 60, int $descMax = 155): array` – SEO-Vorschläge für einen Text. @return array{title: string, description: string, slug: string, focus: string, keywords: list<string>, warnings: list<string>}
- `static summary(string $title, string $text, int $max = 200, ?string $lang = null): array` – Teaser/Zusammenfassung (reiner Text, höchstens $max Zeichen)
- `static supportReply(array $issue): array` – Antwortvorschlag für eine Support-Meldung (nur Support-Team)
- `static teaserField(array $t): ?array` – Feld für Teaser/Zusammenfassung einer Tabelle (Beschreibungsfeld, Zusammenfassung der Suche oder bekannter Name)
- `static text(string $action, string $input, string $format, array $o = []): array` – $action: improve|shorten|expand|simple|fix|tone|free, $input: HTML (rich/inline) bzw. Text (plain)
- `static titleBudget(): int` – Zeichen für den eigenen Titelteil (Suchmaschinen zeigen etwa SeoCheck::TITLE_MAX Zeichen inklusive Titel-Zusatz).
- `static titleSuffix(): string` – Länge des Titel-Zusatzes („ | Website“), den Seo::forPage anhängt
- `static titleSuffixTooLong(): bool` – Titel-Zusatz lässt weniger als TITLE_MIN_OWN Zeichen für den Seitentitel übrig
- `static translate(array $items, string $from, string $to): array` – $items: key => ['text' => string, 'html' => bool]; Ergebnis: key => ['text' => …, 'warnings' => […]]
- `static warnings(string $source, string $result, string $action = 'improve'): array` – Hinweise zum Vorschlag: Platzhalter, fehlende oder neue Zahlen/E-Mail-Adressen (Namen/Zahlen sollen unverändert bleiben).

### `Assistant` (class, `app/AI/Assistant.php`)

Assistent der Redaktion (Funktion „chat.assistant“ + „ai“, Recht „ai.use“, Schalter sys.ai_assistant): Chat-Fenster überall in der Verwaltung (resources/js/_assistant.js → assistant.mjs).

Konstanten: `MAX_QUESTION`, `MAX_HISTORY`, `KEEP_DAYS`

- `static actionLabel(string $name): string`
- `static actions(): array` – Erlaubte Aktionen dieser Person: Name => Beschreibung für die KI
- `static ask(string $q, array $history, array $ctxIn, string $conv, callable $emit): array` – Antwort erzeugen. $emit(event, data): „sources“ (Quellen vorab), „delta“ (Textstück). Rückgabe für „done“.
- `static available(): bool`
- `static client(): ?array` – Konfiguration für resources/js/_assistant.js (null = kein Assistent)
- `static clientScript(): string`
- `static context(array $in): array` – $in: route (Pfad + Abfrage), title (Fenstertitel), page, entry {table, id}, media – alles optional.
- `static db(): Database`
- `static delete(string $id): bool` – Löschen: gespeicherte Inhalte und Metadaten
- `static deleteAll(): int` – Alle eigenen Unterhaltungen löschen (Konto → Datenschutz, Verlauf)
- `static execute(string $action, array $args, string $sig): array` – Aktion nach „Ausführen“: Signatur, Rechte und Ziel erneut prüfen, dann über CmsService (Kanal ai, Funktion chat).
- `static get(string $id): ?array`
- `static list(int $limit = 50): array` – Gespeicherte Unterhaltungen (angeheftete zuerst) + Metadaten der letzten ungespeicherten
- `static prepare(string $action, array $args): array` – Vorschlag prüfen und als Karte aufbereiten: Bezeichnung, Ziel, Felder vorher/nachher, erlaubt?, Signatur.
- `static reviewMode(): bool` – Gehen Aktionen zur Freigabe („Eingereicht“)?
- `static save(string $id, array $messages, ?string $title = null, ?bool $pinned = null): array` – Unterhaltung speichern (Inhalte) bzw. anheften – nur eigene
- `static sources(string $q): array` – Quellen: Hilfe-Abschnitte + passende Artikel der Wissensdatenbank
- `static split(string $raw): array` – Antwort in Text und Aktionen teilen (Markierung @@AKTIONEN@@ oder – Rückfall – ein JSON-Codeblock am Ende)
- `static textBlock(): ?array` – Blocktyp für Textabschnitte: bevorzugt „richtext“, sonst der erste Block mit einem richtext-Feld → [Typ, Textfeld, Überschriftfeld]

### `Center` (class, `app/AI/Center.php`)

Daten für den Bereich „KLXM AI“ (Core\Http\Controllers\Admin\AiController): fehlende Übersetzungen je Sprache (Seiten, Einträge, Alt-Texte, Website-Texte) und Bilder ohne Alt-Text. Ohne KI-Aufrufe.

- `static missingAlt(): array` – Bilder dieser Website ohne Alt-Text (nicht dekorativ)
- `static missingAltLang(string $lang): array` – Bilder mit Alt-Text in der Standardsprache, denen er in $lang fehlt (nicht dekorativ) – Quelle für „Übersetzen“
- `static translationGaps(): array` – Lücken je weiterer Sprache.

### `Embedder` (interface, `app/AI/Embedder.php`)

Erzeugt Vektoren (Embeddings) für Texte. Standard: PlatformEmbedder (Symfony AI Platform); FakeEmbedder für Tests ohne Anbieter.

- `embed(array $texts, string $kind = 'document'): array`
- `id(): string` – Kennung „anbieter:modell“ – ändert sie sich, sind gespeicherte Vektoren ungültig

### `FakeEmbedder` (class, `app/AI/FakeEmbedder.php`)

Deterministische Embeddings ohne Anbieter (config 'ai' => ['provider' => 'fake']) – nur für Tests und Entwicklung. Wörter und Wortanfänge (4 Zeichen) werden per Hash auf 256 Dimensionen verteilt: Texte mit gemeinsamen Wörtern liegen nah beieinander. Keine echte Semantik, aber derselbe Code-Pfad wie mit einem echten Modell.

Konstanten: `DIMS`

- `__construct(bool $fail = false)`
- `embed(array $texts, string $kind = 'document'): array`
- `id(): string`

### `Generator` (class, `app/AI/Generator.php`)

Generatoren im Bereich „KLXM AI“: Tabellen-Generator und Seiten-Generator. Die KI liefert einen Entwurf, den die Person in einer Vorschau bearbeitet; angelegt wird über die normalen Wege (Tables::validate/create, Pages::create als Entwurf) – eine spätere Prüf-Ebene kann dort eingreifen.

- `static blockCatalog(): array` – Blöcke, die der Seiten-Generator füllen darf: freigegeben (Features::allowsBlock), mit Textfeldern, ohne dynamische Inhalte
- `static createPage(array $in): array` – Seite als ENTWURF anlegen (Pages::create) – öffnet danach im Editor; veröffentlicht wird von Hand
- `static createTable(array $in, int $examples = 0): array` – Tabelle anlegen (Tables::validate/create). Optional 1–3 deutlich markierte Beispiel-Einträge als ENTWURF – ohne KI,
- `static pageDraft(array $d, ?array $catalog = null): array` – KI-Antwort bzw. bearbeitete Vorschau bereinigen: nur bekannte Blöcke/Felder, Texte je Feldart gesäubert
- `static proposeBlock(string $description): array` – Block-Designer: Vorschlag für einen eigenen Block aus einer Beschreibung. Der Vorschlag wird mit dem Compiler geprüft
- `static proposePage(array $o): array` – Seiten-Entwurf vorschlagen. $o: topic, audience, tone, language, blocks (erlaubte Typen, leer = alle)
- `static proposeTable(string $description): array` – Vorschlag für eine neue Tabelle → ['def' => Formularwerte für Tables::validate, 'errors' => Prüfhinweise, 'types' => …]
- `static siteFacts(string $topic, string $level = 'full'): string` – Angaben der Website als einzige Faktenquelle: zentrale Textangaben + Titel/Ausschnitte passender Seiten
- `static tableInput(array $d): array` – KI-Antwort bzw. bearbeitete Vorschau → Formularwerte wie im Tabellen-Designer
- `static tableTypes(): array` – Feldtypen, die der Tabellen-Generator vorschlagen darf (Gruppen brauchen Unterfelder – die legt man von Hand an)

### `GuardedHttpClient` (class, `app/AI/GuardedHttpClient.php`)

HTTP-Client für KI-Anbieter mit Positivliste: nur die Hosts des konfigurierten Anbieters, externe Hosts nur per https, keine Weiterleitungen, kurze Zeitlimits. (Core\Proxy::http kann nur GET über https – Embeddings brauchen POST, lokale Anbieter wie Ollama laufen über http://127.0.0.1.)

- `__construct(Symfony\Contracts\HttpClient\HttpClientInterface $client, array $hosts)`
- `request(string $method, string $url, array $options = []): Symfony\Contracts\HttpClient\ResponseInterface`
- `reset(): void`
- `stream(Symfony\Contracts\HttpClient\ResponseInterface|Traversable|array $responses, ?float $timeout = null): Symfony\Contracts\HttpClient\ResponseStreamInterface`
- `withOptions(array $options): static`

### `HelpIndex` (class, `app/AI/HelpIndex.php`)

Kleiner Suchindex über die Hilfe für den Redaktions-Assistenten (Core\AI\Assistant): Handbuch (help/manual, inkl. Theme-Kapitel), technische Dokumentation (help/technical) und Tutorials (app/Admin/tutorials.php + Theme). Abschnitte je Kapitel bzw. Zwischenüberschrift (h3) mit Link auf den Anker. Die Wissensdatenbank (Core\Support) fragt der

- `static db(): Database`
- `static documents(): array` – Alle Abschnitte: id => [kind, title, url, text, hash]
- `static ensure(): void` – Index aktuell? Sonst Stichwort-Teil neu bauen (schnell, ohne KI-Anbieter)
- `static query(string $q, int $k = 5, bool $tech = true): array` – Passende Abschnitte (Stichwort + – falls vorhanden – Vektoren, Reciprocal Rank Fusion).
- `static rebuild(bool $vectors = true, ?Closure $log = null, float $budget = 0): array` – Neu aufbauen. $vectors: fehlende Vektoren erzeugen (Embeddings der Website); $budget: Sekunden für Vektoren (0 = ohne Grenze)
- `static signature(): string` – Stand der Hilfe-Dateien (Änderung → Stichwort-Index neu)
- `static status(): array` – Stand für die Oberfläche/CLI

### `MediaJobs` (class, `app/AI/MediaJobs.php`)

Hintergrund-Aufträge für Medien (Tabelle media_jobs der Website): KI-Transkription (whisper.cpp / OpenAI-kompatibel) und Übersetzen von Untertiteln (Text-KI, Cue für Cue mit gleichen Zeiten). Ergebnis ist immer ein ENTWURF („KI – bitte prüfen“), der erst nach Prüfung im Untertitel-Editor auf der Website erscheint.

Konstanten: `TYPES`

- `static canTranscribe(): bool` – Darf die angemeldete Person transkribieren (Recht „ai.use“, KI-Fähigkeit an)?
- `static canTranslate(): bool`
- `static cancel(int $id): void`
- `static console(string $cmd, array $args): int` – ai:jobs [--all]                               wartende Aufträge abarbeiten (startet die Verwaltung selbst; Cron als Rückfall)
- `static create(string $type, array $m, array $o = []): array` – Auftrag anlegen und Arbeiter starten. $m = Medium im richtigen Kontext (Website/Pool)
- `static duration(int $s): string`
- `static get(int $id): ?array`
- `static list(?array $m = null, int $limit = 30): array` – Aufträge (neueste zuerst), optional für eine Datei
- `static spawn(): bool` – Arbeiter losgelöst starten (überlebt das Ende der Anfrage; Ausgabe: storage/logs/ai-jobs.log)
- `static work(?callable $log = null, ?int $only = null): int` – Wartende Aufträge dieser Website abarbeiten. Hält die installationsweite Sperre (nur ein Auftrag gleichzeitig).

### `OllamaImageNormalizer` (class, `app/AI/OllamaImageNormalizer.php`)

Bilder für Ollama: Die Ollama-Chat-API erwartet „content“ als Text und Bilder separat als Base64-Liste „images“ (die Ollama-Bridge von Symfony AI 0.14 sendet Inhalte als Liste – das lehnt Ollama ab). Nur für Nachrichten mit Bild.

- `getSupportedTypes(?string $format): array`
- `normalize(mixed $data, ?string $format = null, array $context = []): array`
- `supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool`

### `PlatformEmbedder` (class, `app/AI/PlatformEmbedder.php`)

Embeddings über Symfony AI (symfony/ai-platform + Bridge des Anbieters, Vectorizer aus symfony/ai-store). Große Mengen werden in Stapeln gesendet; manche Modelle erwarten Präfixe für Dokument bzw. Suchanfrage.

- `__construct(Symfony\AI\Platform\PlatformInterface $platform, string $provider, string $model, int $batch = 32)`
- `embed(array $texts, string $kind = 'document'): array`
- `id(): string`

### `Probe` (class, `app/AI/Probe.php`)

„Verbindung prüfen“ und „Modelle anzeigen“ für eine KI-Verbindung (Core\AI\Profiles) – Grundeinstellungen → KI.

Konstanten: `TIMEOUT`, `ANTHROPIC_CURATED`

- `static check(array $profile, ?Symfony\Contracts\HttpClient\HttpClientInterface $http = null): array`
- `static endpoint(array $p): string` – Adresse, an die die Prüfung geht (ohne Pfad)
- `static guessCaps(string $name, string $family = '', array $families = []): array` – Fähigkeiten aus Name/Familie raten (wenn der Dienst sie nicht nennt)
- `static models(array $profile, ?Symfony\Contracts\HttpClient\HttpClientInterface $http = null): array` – Modelle der Verbindung.
- `static parseAnthropic(array $j): array` – Anthropic /v1/models
- `static parseOllamaShow(array $j): array` – Ollama /api/show → [context, caps|null]
- `static parseOllamaTags(array $j): array` – Ollama /api/tags → Modelle
- `static parseOpenAi(array $j, string $type = 'openai'): array` – OpenAI-kompatibles /v1/models (auch Mistral mit capabilities und max_context_length)

### `Profiles` (class, `app/AI/Profiles.php`)

Verbindungen (Profile) und Verwendung der KI – Konfigurationsformat 2.

Konstanten: `PURPOSES`, `PURPOSE_HELP`, `TYPES`, `SUPPORTS`

- `static assignments(array $raw): array` – Wirksame Zuordnungen je Zweck: profile, model, fallback, fallback_model, language
- `static deleteProfile(string $id): array` – Verbindung löschen; Zuordnungen, die darauf zeigen, werden geleert. @return list<string> betroffene Zwecke
- `static lastChecks(): array`
- `static locks(array $fileLayer): array` – Was Konfigurationsdateien festlegen (config.local.php, config/sites/{key}.php) – in der Verwaltung gesperrt:
- `static merge(array ...$layers): array` – Mehrere Ebenen (jeweils altes oder neues Format) zusammenführen – spätere überschreiben frühere
- `static normalize(string $id, array $p): array`
- `static profiles(array $raw): array` – Wirksame Verbindungen (Format 2, zusammengeführt): je ID label, type, base_url (Vorgabe je Art), api_key, region,
- `static rememberCheck(string $id, array $res): void` – Ergebnis von „Verbindung prüfen“ merken (nur Status, Meldung, Dauer, Version – keine Schlüssel)
- `static resolve(array $raw, string $purpose, bool $fallback = false): ?array` – Zweck → Verbindung + Modell. Liefert die Felder einer Fähigkeit (provider, base_url, api_key, region, model, profile,
- `static saveAssign(array $in, array $effective, array $locks = []): array` – Zuordnungen und allgemeine Zeitlimits speichern. $effective = wirksame Konfiguration vorher (für den Hinweis bei
- `static saveProfile(?string $id, array $in, array $locks = []): array` – Verbindung anlegen bzw. ändern. $id = null → neu (ID aus der Bezeichnung). API-Schlüssel: leer = unverändert, „-“ = löschen.
- `static slug(string $label): string` – Verbindungs-Kennung: Kleinbuchstaben, Ziffern, _ (aus der Bezeichnung)
- `static storedV2(): array` – Gespeicherte Installations-Konfiguration im Format 2
- `static transcribe(array $raw): array` – Konfiguration für Core\AI\Transcriber aus der Zuordnung „transcribe“
- `static upgrade(array $c): array` – Eine Konfigurations-Ebene ins Format 2 bringen. Es entstehen nur Schlüssel, die die Ebene wirklich setzt – so überschreibt
- `static urlError(string $base): ?string` – Adresse prüfen: http nur für lokale/private Hosts, sonst https; keine Zugangsdaten in der Adresse

### `Prompts` (class, `app/AI/Prompts.php`)

Alle Anweisungen (Prompts) des KI-Assistenten an einer Stelle – Agenturen passen sie hier an.

Konstanten: `PLACEHOLDER`, `RULES`, `TEXT_ACTIONS`, `TONES`, `CHAT_UNKNOWN`, `CHAT_REFUSE`, `ACTIONS_MARK`

- `static alt(array $ctx): array` – Alt-Text für ein Bild (Vision). Antwort (JSON): alt, title, tags[], decorative
- `static assistant(string $question, array $sources, array $history, array $actions, string $context, array $ctx): array` – Redaktions-Assistent (Core\AI\Assistant): „Wie mache ich …?“ aus Handbuch, Technik, Tutorials und Wissensdatenbank
- `static blockGen(string $description, array $types, array $filters): array` – Block-Designer (Core\Blocks\Custom): Vorschlag für einen eigenen Block – Felder, Vorlage in der sicheren
- `static kbPolish(string $title, string $body, array $ctx): array` – Support: Wissensartikel aus einem Entwurf überarbeiten (JSON: title, body in Markdown)
- `static language(string $code): string`
- `static pageGen(string $topic, array $blocks, string $facts, array $ctx): array` – Seiten-Generator: Seite aus vorhandenen Blocktypen. $blocks: kompakter Katalog (Typ, Bezeichnung, Hilfe, Felder),
- `static schema(string $description, array $types, array $ctx): array` – Datentabellen: Feldvorschlag aus einer Beschreibung ($types: Typ → Bezeichnung)
- `static seo(string $title, string $text, array $ctx): array` – SEO-Vorschläge. $ctx: language, title_max (Zeichen für den Titel ohne Zusatz), desc_max, notes, glossary
- `static summary(string $title, string $text, array $ctx): array` – Teaser/Zusammenfassung für Übersichten ($ctx: language, max)
- `static supportReply(string $conversation, array $kb, array $ctx): array` – Support: Antwortvorschlag für das Support-Team ($kb: [['title','body'], …] aus der Wissensdatenbank)
- `static tableGen(string $description, array $types, array $tables, array $schemaTypes): array` – Tabellen-Generator: vollständige Tabellen-Definition (nur vorhandene Feldtypen und Funktionen des CMS).
- `static text(string $action, string $text, array $ctx): array` – Text-Assistent: Aktion auf einen Text anwenden.
- `static translateBatch(array $items, string $from, string $to, array $ctx): array` – Übersetzen mehrerer Texte auf einmal (JSON: {"1": "…", "2": "…"})
- `static translateOne(string $text, string $from, string $to, bool $html, array $ctx): array` – Übersetzen eines einzelnen Textes (Rückfall, wenn die JSON-Antwort unvollständig ist)
- `static visitorChat(string $question, array $sources, array $history, array $ctx): array` – Besucher-Chat (Core\AI\VisitorChat): Antwort NUR aus den nummerierten Quellen der Website.

### `SelfTest` (class, `app/AI/SelfTest.php`)

Selbsttest „ai:selftest“ – ohne Netz und ohne gespeicherte Konfiguration zu ändern: - Verbindungen/Verwendung (Core\AI\Profiles): Umwandlung des alten Formats, Zusammenführen der Ebenen, Zweck → Verbindung, Besucher-Chat erbt „Texte“, Ersatz-Verbindung, Sperren aus Konfigurationsdateien, Arten ohne passende Fähigkeit

- `static run(): array`

### `SeoCheck` (class, `app/AI/SeoCheck.php`)

SEO-Check ohne KI: prüft Seiten auf Titel- und Beschreibungslänge, doppelte Titel/Beschreibungen, Überschriften-Struktur, Bilder ohne Alt-Text, wenig Text und Vorschaubild. Grundlage für den SEO-Check im Seiten-Formular und die SEO-Übersicht (Core\Http\Controllers\Admin\AiController). KI-Vorschläge kommen zusätzlich aus Core\AI\Assist::seo().

Konstanten: `TITLE_MAX`, `TITLE_MIN_OWN`, `DESC_MIN`, `DESC_MAX`, `WORDS_MIN`

- `static duplicates(array $page): array` – Seiten derselben Sprache mit gleichem Titel bzw. gleicher Beschreibung
- `static effectiveTitle(array $page): string` – Titel, wie er im <title> erscheint (Meta-Titel oder Seitentitel + Zusatz)
- `static headings(array $page): array` – Überschriften-Struktur der gerenderten Seite: genau eine H1, keine übersprungenen Ebenen, keine leeren Überschriften
- `static mediaIds(array $blocks): array` – Medien-IDs in Blockdaten (Felder vom Typ media, auch in Wiederholungen)
- `static overview(): array` – SEO-Übersicht: Seiten (ohne Rendern) und Einträge mit Detailseiten, bei denen etwas fehlt.
- `static page(array $page, bool $render = true): array` – Prüfungen einer Seite. @return list<array{level: 'error'|'warn'|'info'|'ok', text: string}>

### `TrackingEmbedder` (class, `app/AI/TrackingEmbedder.php`)

Zählt Embedding-Aufrufe (Aufrufe, geschätzte Tokens, Dauer, Fehler) in den Nutzungszählern der Website – nie Inhalte.

- `__construct(AI\Embedder $inner, array $cap)`
- `embed(array $texts, string $kind = 'document'): array`
- `id(): string`

### `Transcriber` (class, `app/AI/Transcriber.php`)

KI-Fähigkeit „transcribe“ (Sprache → Text mit Zeitmarken) für Untertitel und Transkripte (Core\MediaTracks). Ollama kennt kein Speech-to-Text – daher zwei eigene Wege:

Konstanten: `BACKENDS`

- `static capability(array $t, bool $on): array` – Angaben wie Ai::capability(): provider, model, endpoint, configured, external
- `static check(array $t): array` – Einrichtung prüfen, ohne etwas zu starten (für `health`): Programme ausführbar, Modell vorhanden
- `static exec(array $cmd, int $timeout, ?callable $onErr = null, ?callable $cancelled = null): array` – Programm ohne Shell starten (Argumente als Liste), stderr zeilenweise an $onErr, Zeitlimit und Abbruch.
- `static modelPath(string $p): string` – Modell: absoluter Pfad oder relativ zur Installation; leer = bestes Modell in storage/ai/models
- `static normalize(array $c): array` – Normalisierte Konfiguration aus dem Abschnitt 'transcribe'
- `static paths(array $t): array` – Wirksame Pfade (leer = automatisch gesucht)
- `static test(array $cfg): array` – „Verbindung testen“ mit einem kurzen, erzeugten Testton (1,5 s Sinus, 16 kHz mono)
- `static testWav(float $sec = 1.5): string` – WAV (PCM 16 bit, 16 kHz, mono) mit 1,5 s Sinuston 440 Hz – ohne mitgelieferte Audiodatei
- `static transcribe(array $cfg, string $file, string $lang, ?callable $progress = null, ?callable $cancelled = null, string $prompt = ''): array` – Tonspur einer Datei transkribieren. $lang: Sprachkürzel oder 'auto'.

### `VisitorChat` (class, `app/AI/VisitorChat.php`)

KI-Chat für Besucher (Funktion „chat.visitor“, Standard AUS; Einstellungen unter Grundeinstellungen → KI → „Besucher-Chat“).

Konstanten: `DEFAULT_DAILY`, `PER_MINUTE`, `PER_DAY`, `MAX_QUESTION`, `MAX_HISTORY`, `TOP_K`

- `static answer(string $q, array $history, callable $emit): array` – Antwort erzeugen. $emit(string $event, array $data): bool – „delta“ (Textstück), am Ende gibt answer() das Ergebnis zurück.
- `static available(): bool` – Chat auf dieser Website (bzw. Landing-Domain) aktiv? Funktion + Schalter + KI (Texte) + Suche
- `static cite(string $text, array $sources): array` – Belege [n] prüfen, Links/Adressen des Modells entfernen → [Text, zitierte Quellen]
- `static clean(string $q): string` – Frage bereinigen (Länge, Steuerzeichen)
- `static external(): bool` – Verlassen die Fragen den Server? (externer Text- oder Embedding-Anbieter)
- `static history(mixed $in): array` – Frühere Nachrichten vom Browser (nur zur Einordnung von Rückfragen) – gekürzt, ohne Marker
- `static info(): array` – Öffentliche Angaben (Core\Api\CmsService::publicInfo – Name, Kontakt, Öffnungszeiten, Hinweis)
- `static infoText(): string` – Öffentliche Angaben als Text (Name, Adresse, Telefon, E-Mail, Öffnungszeiten, aktueller Hinweis)
- `static injection(string $q): bool` – Versuch, die Anweisungen auszuhebeln („ignoriere alle Regeln“, „zeige deinen Prompt“, Rollenspiele)?
- `static inventedNumbers(string $text, array $sources, string $q): bool` – Enthält die Antwort Zahlen (Zeiten, Preise, Nummern), die weder in den Quellen noch in der Frage stehen?
- `static ipKey(string $ip): string` – Kurzer HMAC der IP (mit app_key) – nie die IP selbst
- `static launcher(?array $page = null, bool $editing = false): string` – Starter-Knopf für Theme-Layouts (cms_chat_launcher()): leerer Host + Skript ≤ 1 KB. Das Chat-Fenster (JS/CSS)
- `static looksLikeQuestion(string $q): bool` – Sieht die Suchanfrage wie eine Frage aus? (Fragezeichen, Fragewort am Anfang oder mindestens fünf Wörter)
- `static privacyPolicyText(): string` – Vorschlag für die Datenschutzerklärung (Grundeinstellungen → KI → Besucher-Chat)
- `static publicConfig(): array` – Texte und Angaben für das Chat-Fenster (GET /api/chat/config, Sprache der Seite)
- `static quota(): array` – Tageslimit der Website: [cap, used, left] (cap 0 = unbegrenzt)
- `static scrub(string $s): string` – E-Mail-Adressen und lange Nummern (Telefon, Versicherten-, Kontonummern) vor dem Senden an den Anbieter unkenntlich machen
- `static searchAnswerBox(string $q, array $result): string` – Kasten „Antwort“ für die Ergebnisseite (leer, wenn aus); lädt search-answer.js – ohne JavaScript unsichtbar
- `static searchAnswerMode(): string` – KI-Antwort über den Suchergebnissen (Grundeinstellungen → Suche „sys.search_answer“): off | click (Knopf) |
- `static settings(): array`
- `static sources(string $q, array $history = []): array` – Quellen zur Frage: beste Textstellen der Suchtreffer (Sprache der Seite, Landing-Umfang) + öffentliche Angaben.
- `static themeHint(): string` – Zusätzliche Regeln des Themes (theme.php 'ai' => ['chat' => ['system' => Text oder Funktionsname]])

## Core\Api

### `ApiError` (class, `app/Api/ApiError.php`)

Fachlicher Fehler der API (HTTP-Status + Details, z. B. Feldfehler).

- `__construct(int $status, string $message, array $details = [])`

### `CmsService` (class, `app/Api/CmsService.php`)

Fachlogik für REST-API und MCP-Server (eine Implementierung, zwei Schnittstellen). Alle Änderungen laufen durch dieselben Validierungen wie im Admin. Inhaltsänderungen landen als Entwurf (mit Revision); veröffentlicht wird explizit.

- `__construct(array $token)`
- `applyingChange(int $id): void` – Freigabe einer Einreichung: direkt ausführen, Protokollzeile $id wird von Queue::approve aktualisiert
- `blockAdd(string|int $ref, string $type, array $data = [], array $section = [], ?int $position = null, ?string $after = null, bool $publish = false): array` – Neuer Block. Position: 0-basiert; ohne Angabe ans Ende. Alternativ after = Block-ID.
- `blockMove(string|int $ref, string $blockId, int $position, bool $publish = false): array`
- `blockRemove(string|int $ref, string $blockId, bool $publish = false): array`
- `blockTypes(): array`
- `blockUpdate(string|int $ref, string $blockId, ?array $data = null, ?array $section = null, bool $replace = false, bool $publish = false): array` – Block ändern. data wird standardmäßig zusammengeführt (nur übergebene Felder ändern sich);
- `blocksReplace(string|int $ref, array $blocks, bool $publish = false): array`
- `changeStatus(int $id): array` – Stand einer eigenen Einreichung (GET /changes/{id}, MCP get_change_status)
- `changesList(?string $status = null): array` – Eigene Einreichungen, neueste zuerst (?status=pending|applied|rejected)
- `dataDelete(string $handle, int $id): array`
- `dataEntries(string $handle, array $o): array` – Einträge: q, status (published|draft|all), filter {feld: wert}, sort, dir, limit, offset
- `dataEntry(string $handle, string|int $ref, bool $ciphertext = false): array`
- `dataOccurrences(string $handle, array $o): array` – Termine (Vorkommen inkl. Wiederholungen) einer Kalender-Tabelle im Zeitraum [from, to).
- `dataPick(string $handle, array $ids, string $state): array` – Auswahl dieser Website setzen: visible (übernehmen), rejected (ablehnen) – nur Eigentümer –, featured, hidden, reset
- `dataSave(string $handle, ?int $id, array $in): array` – Eintrag anlegen ($id null) oder ändern (nur übergebene Felder); slug und status optional
- `dataSuggestions(string $handle, string $state = 'pending'): array` – Vorschläge der übrigen Websites (nur Eigentümer-Website). state: pending (offen, Standard), visible, featured, rejected, all
- `dataTables(): array` – Tabellen mit Feld-Schema (für Einträge und den Block „Datenliste“)
- `dataTranslate(string $handle, int $id, string $lang): array` – Übersetzung eines Eintrags anlegen (Entwurf in der Zielsprache; vorhandene wird zurückgegeben)
- `designGet(): array` – Definition (Gruppen/Tokens, Schriften, Vorlagen), aktuelle Werte, Standardwerte und Kontrastprüfung
- `designUpdate(array $in): array` – Design ändern: values (Teil-Update), preset (Vorlage als Grundlage), reset (Theme-Standard als Grundlage).
- `discard(string|int $ref): array`
- `static forAi(string $feature): self` – KI-Übernahmen aus der Verwaltung (AiController): angemeldete Person als Urheber, Freigabe laut sys.review_ai
- `geocode(string $q): array` – Adresse → Koordinaten (für Felder vom Typ geo, z. B. Standort). Serverseitig über Nominatim, max. 1 Anfrage/Sekunde.
- `static hasHours(): bool` – Bietet das Theme Öffnungszeiten bzw. einen Hinweisbalken an?
- `static hasNotice(): bool`
- `hoursGet(): array`
- `hoursSet(array $rows): array` – Ersetzt alle Öffnungszeiten. Wochentag als Zahl (1 = Montag … 0 = Sonntag) oder Name („Mo“, „Montag“).
- `landingsGet(): array` – Landingpages mit eigenen Domains (Core\Landings): Domains, Seite, Canonical-Modus, Marke, Seiten mit Adressen
- `mediaCrop(int $id, string $ratio, ?array $rect): array` – Zuschnitt für ein Bildformat setzen (rect {x,y,w,h} als Anteile 0–1) oder mit rect = null entfernen
- `mediaDelete(int $id): array`
- `mediaList(array|string|null $f = null): array` – Mediathek mit Filtern: kind (image|pdf|video), q, tag, collection (ID), noalt
- `mediaMeta(): array` – Sammlungen, Tags und Bildformate (für Zuschnitte)
- `mediaUpdate(int $id, array $in): array` – Medien-Infos ändern: alt, decorative, title, credit, tags (Liste oder Komma-Text), focus {x,y} (0–100), collections (IDs),
- `mediaUploadBase64(string $filename, string $base64, string $alt = '', array $opt = []): array` – Upload per Base64 (für MCP) – gleiche Prüfungen wie der Admin-Upload
- `mediaUploadFile(array $file, string $alt = '', array $opt = []): array`
- `noticeSet(string $text, bool $active, ?string $from = null, ?string $until = null): array` – $from/$until: null = unverändert, '' = leeren, sonst „JJJJ-MM-TT HH:MM“ (Ortszeit der Website) – Core\Notice
- `origin(string $channel, ?string $client = null, ?string $feature = null): self`
- `page(string|int $ref): array`
- `pageCreate(array $in): array`
- `pageDelete(string|int $ref): array`
- `pageGet(string|int $ref, bool $published = false): array`
- `pageTranslate(string|int $ref, string $lang): array` – Übersetzung einer Seite anlegen (Kopie als Entwurf in der Zielsprache; vorhandene wird zurückgegeben)
- `pageUpdate(string|int $ref, array $in): array`
- `pagesList(): array`
- `static publicInfo(): array` – Öffentliche Basisdaten (ohne Token abrufbar): Name, URL + was das Theme beisteuert
- `publish(string|int $ref): array`
- `pushCancel(int $id): array` – Geplante Mitteilung zurückziehen
- `pushInfo(int $limit = 30): array` – Kanäle (mit Zahl der Abonnenten), aktuelle Zahlen und die letzten Mitteilungen
- `pushSend(array $in): array` – Mitteilung senden oder planen ($in wie Verwaltung → Mitteilungen → Verfassen; at = ISO-Zeitpunkt für „geplant“)
- `recheckChange(int $id): void` – Einreichung $id gegen den aktuellen Stand neu prüfen (Probelauf)
- `redirectsGet(?string $q = null, ?string $test = null): array` – Weiterleitungen (Core\Redirects) – nur lesen; optional Suche und „Wohin führt …?“
- `requestAssign(int $id, string|int|null $user, ?string $table = null): array` – Anfrage zuweisen: $user = Benutzer-ID oder E-Mail, null/'' = Zuweisung entfernen
- `requestAssignees(?string $table = null): array` – Personen, denen Anfragen dieser Tabelle zugewiesen werden können (Recht requests.read für die Tabelle)
- `requestDelete(int $id, ?string $table = null): array` – Anfrage endgültig löschen (wie in der Verwaltung; protokolliert in inbox_log)
- `requestStatus(int $id, string $status, ?string $table = null): array` – Status setzen; ohne table wird die ID in allen Eingangs-Tabellen gesucht (mehrdeutig → 409)
- `requestsList(string $status = 'neu', bool $withCiphertext = false, ?string $table = null): array` – Anfragen aller (bzw. einer) Eingangs-Tabelle(n), neueste zuerst – nur Metadaten; Chiffretext nur mit $withCiphertext.
- `restore(string|int $ref, int $revId): array`
- `reviewMode(): bool` – Werden Änderungen dieses Zugangs zur Freigabe eingereicht statt ausgeführt?
- `revisions(string|int $ref): array`
- `search(string $q, array $o = []): array` – Suche wie für Besucher: veröffentlichte Seiten, Einträge mit Detailseite (inkl. gewählter geteilter), ggf. PDF-Dokumente.
- `settingsGet(?string $group = null): array`
- `settingsSchema(): array`
- `settingsUpdate(array $values): array` – Teil-Update: nur übergebene Felder werden geprüft und gespeichert.
- `siteInfo(): array`
- `sourceSync(int $id): array` – Quelle jetzt abgleichen (wie „Jetzt abrufen“; höchstens 30 Abrufe je Stunde und Quelle)
- `sourcesList(): array` – Quellen (ohne Zugangsdaten) mit letztem Abgleich
- `tableCreate(array $in): array` – Tabelle oder Formular anlegen – gleiche Prüfung wie der Assistent (Wizard::check + Tables::validate), Detailseiten-Vorlage bei Inhalten.
- `tableTemplates(): array` – Zwecke (mit Verfügbarkeit) und Vorlagen je Zweck
- `useLang(?string $lang): void` – Sprache für diese Anfrage setzen – wirkt auf Seiten, Einträge, Einstellungen und Medientexte

### `OpenApi` (class, `app/Api/OpenApi.php`)

OpenAPI-3.1-Beschreibung der REST-API (GET /api/v1/openapi.json).

- `static spec(): array`

### `Tokens` (class, `app/Api/Tokens.php`)

API-Tokens für REST-API und MCP. Format: cms_<48 Hex>. Gespeichert wird nur der SHA-256-Hash – der Klartext wird genau einmal beim Erzeugen angezeigt.

Konstanten: `SCOPES`, `RATE_LIMIT`, `RATE_WINDOW`, `MODES`

- `static all(): array`
- `static authenticate(Http\Request $r): array` – Prüft Token, Ablauf und Rate-Limit.
- `static create(string $name, string $scope, ?int $userId, ?string $expires = null, string $mode = 'direct'): array`
- `static fromRequest(Http\Request $r): ?string` – Token aus Authorization-Header (Bearer) oder X-Api-Key lesen
- `static requireWrite(array $token): void`
- `static revoke(int $id): void`
- `static setMode(int $id, string $mode): void`

## Core\Blocks

### `Css` (class, `app/Blocks/Css.php`)

CSS eigener Blöcke: prüfen, auf .cblk-{schlüssel} begrenzen, verkleinern.

Konstanten: `MAX_SIZE`, `MAX_OUT`, `BEHAVIOURS`

- `static allowedUrl(string $path): bool` – Nur Dateien dieser Installation: Mediathek, Websites, Pools – ohne „..“
- `static behaviourCss(string $root, array $behaviours): string` – CSS der Verhalten (nur CSS – eigenes JavaScript gibt es in eigenen Blöcken nicht)
- `static compile(string $css, string $key, array $behaviours = []): array`
- `static tokens(string $root): string` – Design-Brücke: neutrale Variablen aus den Design-Werten des Themes (Style-Editor wirkt weiter)

### `Custom` (class, `app/Blocks/Custom.php`)

Eigene Blöcke (Block-Designer, Verwaltung → Blöcke): Admins und Integratoren bauen Blöcke aus Feldern, einer sicheren Vorlage (Core\Blocks\Template) und begrenztem CSS (Core\Blocks\Css) – ohne PHP, ohne eigenes JavaScript.

Konstanten: `PREFIX`, `TYPES`, `RESERVED`, `SCHEMAS`, `PROPS`, `MAX_VERSIONS`, `FORMAT`, `FORMATS`

- `static all(): array`
- `static assets(?array $types): array` – Stylesheets eigener Blöcke für die übergebenen Blocktypen (null = alle, z. B. im Bearbeiten-Modus)
- `static check(array $def): array` – Vorlage und CSS prüfen. @return array{errors: array<string, string>, warnings: list<string>, css: ?string}
- `static compiled(string $template, array $fields, array $behaviours): Blocks\Template`
- `static definition(array $row): array` – Nur die Definition (für Export, Versionen, Vorschau)
- `static definitions(): array` – Veröffentlichte (auch zurückgezogene) Blöcke als Theme-Blockdefinitionen: cblk_{schlüssel} => def
- `static delete(string $key): ?string` – Löschen (nur ohne Verwendung)
- `static ensureTable(Database $db): void`
- `static export(string $key): ?array`
- `static export_var(mixed $v, int $level = 0): string` – var_export mit kurzer Array-Schreibweise und Einrückung
- `static fieldTypes(): array` – Feldtypen des Block-Designers: Typ => [Bezeichnung, Symbol]
- `static find(string $key): ?array`
- `static forget(): void`
- `static hash(array $def): string` – Fingerabdruck der Definition (ohne Beispieldaten) – „Änderungen noch nicht freigegeben“
- `static import(string $json, int $userId, string $kind = 'import'): array` – JSON-Export übernehmen – immer als neuer Entwurf. @return array{key: ?string, errors: array}
- `static jsonld(Block $b, ?array $spec = null): ?array` – schema.org-Daten nach der Zuordnung im Block-Designer (FAQ oder Einträge eines Typs)
- `static libraryAllowed(): bool` – Netzwerk-Bibliothek: nur Netzwerk-Administration und Integratoren
- `static libraryDir(): string`
- `static libraryImport(string $file, int $userId): array`
- `static libraryList(): array`
- `static libraryPut(string $key): bool`
- `static normFields(array $in, bool $sub = false): array`
- `static normName(string $s): string`
- `static normSample(array $fields, array $sample): array` – Beispieldaten auf das Feld-Schema bringen (gleiche Bereinigung wie Blockdaten)
- `static normalize(array $in, ?string $key = null): array` – Eingaben des Block-Designers → Definition. Fehler in Name/Feldern verhindern das Speichern;
- `static publish(string $key, int $userId): array` – Für die Redaktion freigeben: prüfen, CSS-Datei schreiben, veröffentlichte Fassung festhalten.
- `static render(Block $b): string` – Ausgabe eines eigenen Blocks (innerhalb der normalen Abschnitts-Hülle des Themes)
- `static restore(string $key, int $versionId, int $userId): array`
- `static sample(array $fields, int $n = 0): array` – Beispieldaten für die Vorschau – deutlich als „Beispiel“ markiert
- `static save(?string $key, array $in, int $userId, string $kind = 'save', string $note = ''): array` – Arbeitsstand speichern (neuer Block, wenn $key null) und im Verlauf ablegen.
- `static themeDef(array $def, string $status = 'published'): array` – Definition im Format von theme.php → blocks
- `static themeExport(string $key): array` – „Als Theme-Block exportieren“: ZIP mit PHP-Renderer (aus der Vorlage übersetzt), CSS, theme.php-Ausschnitt, JSON.
- `static type(string $key): string`
- `static usages(string $key): array` – Seiten, die den Block verwenden (Entwurf oder veröffentlicht)
- `static versions(string $key): array`
- `static withdraw(string $key): bool` – Aus der Schublade nehmen – bestehende Seiten zeigen den Block weiter

### `Demos` (class, `app/Blocks/Demos.php`)

Beispiel-Blöcke des Block-Designers (Verwaltung → Blöcke → Beispiele, php bin/console blocks:demos).

Konstanten: `DIR`, `HINT_TABS`

- `static all(): array`
- `static block(string $name): ?Block` – Block mit Beispieldaten, gerendert wie auf einer Seite (ohne Hülle des Kits) – für Selbsttest und Vorschau
- `static css(string $name): string` – CSS des Beispiels für die Vorschau: Block-CSS plus Ausblenden von Kopf, Fuß und übrigen Inhalten der Seite
- `static definition(string $name): ?array` – Normalisierte Definition (wie nach dem Import) mit Herkunft und ergänzten Beispieldaten
- `static find(string $name): ?array`
- `static install(string $name, int $userId, bool $once = false): array` – Beispiel als neuen Entwurf übernehmen. Kurzname und Bezeichnung werden eindeutig gemacht (hinweisbox_2, „Hinweisbox 2“).
- `static installed(string $name): array` – Eigene Blöcke, die aus diesem Beispiel übernommen wurden (Schlüssel)
- `static page(string $name, string $cssUrl): ?string` – Ganze Seite des aktiven Kits mit dem Beispiel-Block (iframe der Übersicht); CSS über css()

### `Hero` (class, `app/Blocks/Hero.php`)

Bausteine für die Einstiegs-Varianten der Kits (Block „hero“): Suche, Hintergrundvideo, Kennzahlen, Termine, Formular, Karte, Vorher/Nachher, Laufzeile. Markup und Aussehen bestimmt jedes Kit selbst – hier liegen nur die Teile, die in allen Kits gleich funktionieren müssen (Daten, Kern-Komponenten, Barrierefreiheit, Feld-Voreinstellungen).

- `static chips(array $d): array` – Vorschläge: „Begriff“ → Ergebnisseite, „Beschriftung | Link“ → Link. Ohne Suche nur die direkten Links.
- `static compare(array $d, string $sizes = '100vw', string $ratio = '', string $class = 'hx-compare'): string` – Vergleich zweier Bilder: ohne Skript nebeneinander (beschriftet), mit Skript übereinander mit Schieberegler.
- `static dates(array $d): array` – Einträge für die Liste im Einstieg: Kalender-Tabelle → nächste Vorkommen, sonst neueste veröffentlichte Einträge.
- `static dials(Block $b, array $d, string $size = 'm'): string` – Kennzahlen des Einstiegs (Feld „items“) als Kern-Rundinstrumente – Stylesheet über 'uses' => [… => ['dials']]
- `static fields(string $part, array $variants): array` – Feld-Voreinstellungen je Baustein; $variants = Varianten, bei denen die Felder erscheinen.
- `static form(Block $b, array $d): string` – Öffentliches Formular (Feld „form_table“) – wie Block „data_form“, ohne Container; Stylesheet über 'uses' → data_form
- `static lines(mixed $s): array` – Nicht leere Zeilen eines Textfelds
- `static map(array $d, string $height = 'm', string $class = ''): string` – Karte mit dem Standort aus „Website“ (Core\Maps: Kacheln über den eigenen Server, lädt erst in Sichtweite)
- `static marquee(string $text, string $class = 'hx-marquee'): string` – Laufzeile: Begriffe mit „·“ getrennt; zweimal gesetzt für die Endlosschleife (Kopie aria-hidden)
- `static pairs(mixed $s): array` – „Bezeichnung: Wert“ je Zeile → [['label', 'value']] (ohne Doppelpunkt: nur value)
- `static script(): string` – Einmal je Seite: <script type="module"> für Video, Vorher/Nachher, Laufzeile (nicht im Bearbeitungsmodus)
- `static search(array $d): string` – Suchfeld (Search::form Variante „hero“) – '' wenn die Website-Suche ausgeschaltet ist
- `static video(array $d, array $o = []): ?array` – Hintergrundvideo (eigene Datei): Standbild (Feld „image“, sonst Poster aus der Mediathek) + <video muted loop playsinline>.

### `PartnerLogos` (class, `app/Blocks/PartnerLogos.php`)

Kern-Block „Partner & Logos“ (Renderer app/Blocks/partners.php, Styles resources/css/partners.css, Verhalten resources/js/partners.mjs).

Konstanten: `RATIOS`, `AREA`, `PAD`, `ADJUST`

- `static groups(array $items): array` – Einträge nach Kategorie gruppieren (Reihenfolge bleibt) → [kategorie => items]
- `static items(array $d): array` – Einträge aus der Liste („manual“) oder einer Datentabelle („table“), sortiert.
- `static linkLabel(array $it): string` – Link-Beschriftung: eigene Angabe, sonst „Website besuchen“ bzw. Domain
- `static sort(array $items, string $sort, bool $table = false): array` – manual = Reihenfolge der Liste/Tabelle, name = A–Z, category = Kategorie, dann Name, random = zufällig, field = Datenbank
- `static width(int $w, int $h, string $ratio = '3:2', string $size = 'm', int $adjust = 0): int` – Breite des Logos in Prozent der Innenfläche der Kachel (6–100, gerade Zahl).

### `Runtime` (class, `app/Blocks/Runtime.php`)

Laufzeit der Vorlagensprache eigener Blöcke (Block-Designer): Filter, kontextgerechtes Escaping, Schleifen-Grenzen, Hülle des Blocks. Wird vom Interpreter (Core\Blocks\Template::run) UND von als Theme-Block exportierten PHP-Renderern benutzt – beide Wege liefern dadurch dieselbe Ausgabe.

Konstanten: `FILTERS`, `DATE_STYLES`, `MAX_PER_LOOP`, `MAX_ITERATIONS`

- `__construct(?Block $b, array $cfg = [])`
- `attr(mixed $v, string $attr): string` – Ausgabe in einem Attributwert (in Anführungszeichen): immer escaped; class/id nur sichere Zeichen
- `block(string $k): mixed` – Angaben zum Block: block.id, block.dom_id, block.title_id, block.dark, block.editing, block.anchor, block.background
- `close(): string`
- `static cls(string $key): string` – CSS-Klasse eines eigenen Blocks: cblk-{schlüssel} (Unterstriche → Bindestriche)
- `cmp(string $op, mixed $a, mixed $b): bool`
- `each(mixed $v): array` – Schleife: Liste der Einträge (höchstens MAX_PER_LOOP, insgesamt MAX_ITERATIONS je Block)
- `edit(string $path, string $mode = 'plain'): string` – Im Bearbeiten-Modus: Text direkt auf der Seite bearbeitbar (data-edit), sonst leer
- `ext(): string` – Nach einem dynamischen href: target/rel für externe Adressen
- `f(string $name, mixed $v, array $args = []): mixed`
- `static for(Block $b): self`
- `get(mixed $base, string|int ...$segs): mixed` – Wert eines Pfads: get($item, 'name')
- `href(mixed $v): string` – href/cite: Link-Feld auflösen (Seiten, Anker, Sonderwerte des Themes); unsichere Adressen werden „#“
- `loop(int $i, int $n): array` – loop.index (ab 1), loop.index0, loop.first, loop.last, loop.length, loop.revindex
- `open(): string`
- `static resolveLink(string $link): string` – Link-Wert → sichere Adresse
- `static scalar(mixed $v): string` – Wert als einfacher Text (SafeHtml → ohne Tags, bool → „1“/„“)
- `text(mixed $v): string` – Ausgabe im Text: escaped, SafeHtml unverändert. *Wort* → <em class="hl"> wie in den Kit-Blöcken (emphasis_replace;
- `truthy(mixed $v): bool`

### `SafeHtml` (class, `app/Blocks/SafeHtml.php`)

Bereits sicheres HTML in der Vorlagensprache eigener Blöcke – entsteht nur durch Filter des Kerns (rich/inline → Sanitizer-Whitelist, image/zoom → Media::picture, icon → Icons, nl2br/paragraphs → escaped).

- `__construct(string $html)`

### `SelfTest` (class, `app/Blocks/SelfTest.php`)

Selbsttest des Block-Designers: php bin/console blocks:selftest Prüft Escaping und XSS-Abwehr der Vorlagensprache (Text, Attribute, Links, Rich-Text, Symbole), Übersetzungsfehler, CSS-Begrenzung und die Gleichheit von Interpreter und exportiertem PHP-Renderer. Dazu die Logo-Normalisierung des Blocks „Partner & Logos“.

- `static run(): array`

### `Template` (class, `app/Blocks/Template.php`)

Sichere Vorlagensprache für eigene Blöcke (Block-Designer) – nie PHP aus der Verwaltung.

Konstanten: `MAX_SIZE`, `MAX_DEPTH`, `MAX_LOOP_DEPTH`, `MAX_NODES`, `TAGS`, `VOID`, `GLOBAL_ATTRS`, `TAG_ATTRS`, `URL_ATTRS`, `BOOL_ATTRS`, `RESERVED_DATA`

- `static compile(string $src, array $fields = [], array $behaviours = []): self`
- `run(Blocks\Runtime $rt, array $data): string`
- `toPhp(string $label = ''): string` – PHP-Renderer für kits/{name}/blocks/{typ}.php – Ausgabe identisch zum Interpreter (gleiche Runtime)

### `TemplateError` (class, `app/Blocks/TemplateError.php`)

Übersetzungsfehler der Vorlagensprache eigener Blöcke – mit Zeilennummer der Vorlage (1-basiert, 0 = unbekannt).

- `__construct(string $message, int $templateLine = 0)`
- `display(): string` – Meldung für die Oberfläche: „Zeile 4: …“

## Core\Chat

### `Chat` (class, `app/Chat/Chat.php`)

Chat zwischen Benutzern der Verwaltung – optional (Funktion „chat“, Standard: aus).

Konstanten: `REACTIONS`, `MAX_BODY`, `RETENTION_DEFAULT`

- `static accessOk(array $r, ?array $who = null): bool` – Kanal-Mitgliedschaft: keine Auswahl = alle Chat-Nutzer der Website; sonst Rollen und/oder Personen. Verwaltende sehen alle Kanäle.
- `static archiveChannel(array $room, bool $archived = true): void`
- `static audience(array $r): array` – Wer gehört zum Raum? (für Erwähnungen und E-Mail-Hinweise) @return list<array{key,name,email}>
- `static canConfigure(): bool` – Chat ein-/ausschalten, Netzwerk-Bereich, Aufbewahrung
- `static canManage(): bool` – Kanäle dieser Website anlegen/ändern, fremde Nachrichten in Kanälen entfernen
- `static canNetwork(): bool`
- `static canSite(): bool` – Chat dieser Website nutzen
- `static canUse(): bool`
- `static configured(): ?bool` – Ist 'chat' in config 'features' ausdrücklich gesetzt? (dann gilt die Konfiguration, der Schalter ist gesperrt)
- `static db(): Database`
- `static dir(string $sub = ''): string`
- `static ensureDefaultChannel(): void` – Beim Einschalten: Kanal „#redaktion“ anlegen, falls die Website noch keinen hat
- `static ensurePerson(string $key, string $name, string $email, bool $network = false): void` – Person anlegen, falls unbekannt (z. B. Empfänger einer ersten Direktnachricht)
- `static ensureRoles(): void` – Bestehende Rollen einmalig ergänzen: „chat.use“ für die Redaktion (danach frei änderbar, Merker sys.userchat_roles)
- `static event(int $roomId, string $kind, ?int $messageId = null): int`
- `static eventsSince(int $after, int $limit = 100): array` – Ereignisse nach $after, die die angemeldete Person sehen darf – fertig für die Oberfläche.
- `static head(): string` – Konfiguration für resources/js/userchat.js + Stylesheet/Skript (leer ohne Recht)
- `static isMember(array $r, ?string $key = null): bool`
- `static isNet(): bool` – Netzwerk-Administration oder Integrator (config 'integrators')
- `static keyFor(array $u): string`
- `static lastEventId(): int`
- `static manageableChannels(): array` – Kanäle zur Verwaltung: diese Website (+ Netzwerk für Integratoren), inkl. archivierter
- `static markRead(int $roomId, int $lastId): void`
- `static maybeHousekeeping(): void` – Gelegentlich nebenbei (≈ 1 % der Aufrufe): Aufbewahrung + E-Mail-Hinweise
- `static me(): string` – Kennung der angemeldeten Person: „Website:ID“ bzw. „net:ID“ (Netzwerk-Konto)
- `static meta(string $k, ?string $default = null): ?string`
- `static myName(): string`
- `static navCount(): array` – Zahl für die Navigation (0 ohne Recht oder bei Fehlern)
- `static networkOn(): bool` – Netzwerk-Bereich (Kanäle/Direktnachrichten über alle Websites) eingeschaltet
- `static networkPeople(): array` – Personen des Netzwerk-Bereichs (Netzwerk-Administration und Integratoren, die den Chat schon einmal geöffnet haben)
- `static openDm(string $other, string $scope): array` – Direktnachricht öffnen oder anlegen. $scope 'site' | 'network'
- `static person(string $key): ?array`
- `static personLabel(string $key, ?string $name = null): array` – Anzeigename + Website (fremde Websites/Netzwerk mit Bezeichnung)
- `static purge(?int $days = null): int` – Nachrichten älter als die Aufbewahrungsfrist löschen (inkl. Bilder), alte Ereignisse aufräumen. → Anzahl gelöschter Nachrichten
- `static register(): void` – Person im Verzeichnis vormerken (Name, E-Mail, Netzwerk) – höchstens alle 5 Minuten schreiben
- `static reset(): void`
- `static retentionDays(): int` – Aufbewahrung in Tagen (0 = unbegrenzt) – zentral für alle Websites; Vorgabe config 'chat_retention_days'
- `static room(int $id): ?array`
- `static roomLabel(array $r): array` – Anzeigename eines Raums für die angemeldete Person
- `static rooms(bool $fresh = false): array` – Für die angemeldete Person sichtbare Räume (Kanäle zuerst), gecacht je Anfrage
- `static saveChannel(?array $room, array $in): ?string` – Kanal anlegen/ändern. $in: name, topic, scope, roles[], users[] → Fehlermeldung oder null
- `static setMeta(string $k, ?string $v): void`
- `static settingsVisible(): bool` – Einstellungsseite sichtbar (Menü „Administration“)
- `static siteOn(): bool` – Chat auf dieser Website eingeschaltet (ohne Integrator-Ausnahme)
- `static siteSwitch(): bool` – Schalter der Website (Chat-Einstellungen, nur Netzwerk-Administration/Integratoren) – wirkt, solange config 'features' nichts vorgibt
- `static siteUsers(): array` – Personen dieser Website, die den Chat nutzen dürfen (für Direktnachrichten, @Erwähnungen, Kanal-Mitglieder).
- `static slug(string $s): string` – Kurzname für Kanäle: klein, a–z, 0–9, Bindestrich
- `static streamSeconds(): int` – Dauer eines Streams in Sekunden (config 'chat_stream_seconds', 5–25)
- `static totals(): array` – Summe für die Seitenleiste: [unread, mentions]
- `static transport(): string` – Übertragung: 'sse' (Standard auf PHP-FPM) oder 'poll' (Entwicklungsserver php -S; config 'chat_transport' => 'auto'|'sse'|'poll')
- `static unreadByRoom(): array` – Ungelesene Nachrichten und Erwähnungen je Raum: [room_id => ['unread' => n, 'mentions' => n]]
- `static visible(array $r): bool` – Darf die angemeldete Person den Raum sehen?

### `LinkChips` (class, `app/Chat/LinkChips.php`)

Verweise im Chat: Adressen der Verwaltung dieser Installation werden beim Senden erkannt und mit Titel und Symbol gemerkt (Schnappschuss – der Titel stammt aus Sicht der sendenden Person, der Link führt zur jeweiligen Seite, die selbst die Rechte prüft). Erkannt werden:

- `static extract(string $body): array`
- `static html(array $l): string` – Chip-HTML (Link auf der Website des Verweises; fremde Websites mit absoluter Adresse)
- `static resolve(string $path): ?array` – Titel und Symbol zu einer Verwaltungs-Adresse (null = nicht verlinken)

### `Messages` (class, `app/Chat/Messages.php`)

Nachrichten des Chats: senden, bearbeiten, löschen (weich), Reaktionen, Erwähnungen, Bilder, Ausgabe, E-Mail-Hinweise.

Konstanten: `MAX_FILES`, `PAGE`

- `static delete(array $m): ?string` – Weich löschen: Text, Chips und Bilder entfernen, die Zeile bleibt („Nachricht gelöscht“). Eigene – oder als Verwaltende in Kanälen.
- `static digest(): int` – Ungelesene Erwähnungen (älter als 10 Minuten) als Sammel-E-Mail – höchstens einmal je Stunde und Person,
- `static edit(array $m, string $body): ?string`
- `static file(int $id): ?array` – Bild, falls die angemeldete Person den Raum sehen darf (noch nicht gesendete: nur die hochladende Person)
- `static find(int $id): ?array`
- `static list(array $room, int $before = 0, int $limit = self::PAGE): array` – Nachrichten eines Raums, neueste zuerst geladen, aufsteigend geliefert. $before: ältere als diese ID
- `static mentions(array $room, string $body): array` – Erwähnte Personen: „@Vollständiger Name“ von Personen, die zum Raum gehören (nicht man selbst)
- `static react(array $m, string $emoji): ?string` – Reaktion umschalten (kleine feste Auswahl)
- `static render(array $m): string` – Markdown-lite + Chips für Verwaltungs-Adressen + hervorgehobene Erwähnungen
- `static send(array $room, string $body, array $fileIds = []): array` – Nachricht senden. $fileIds: vorher hochgeladene Bilder (upload()). → [Nachricht, null] oder [null, Fehler]
- `static toJson(array $m, ?array $reactions = null, ?array $files = null): array` – Nachricht für die Oberfläche (aus Sicht der angemeldeten Person)
- `static upload(array $room, array $f): array` – Bild vor dem Senden hochladen (Einfügen/Auswahl im Eingabefeld). → [Datei, null] oder [null, Fehler]

## Core\Dashboard

### `Dashboard` (class, `app/Dashboard/Dashboard.php`)

Übersicht der Verwaltung (/admin): Karten, persönliche Anordnung, Zwischenspeicher der Statistiken, kleine SVG-Grafiken.

Konstanten: `TTL`, `SIZES`

- `static ago(string $at): string` – Relative Zeitangabe: „vor 5 Min.“, „gestern“, „12.09.“
- `static apply(array $user, string $do, string $card, array $visible, array $order = []): void` – Eine Änderung anwenden: up | down | hide | show | open | close | order (Liste) | reset.
- `static cached(string $key, int $ttl, callable $fn): mixed`
- `static cards(array $user): array` – Alle sichtbaren Karten in der Reihenfolge des Benutzers.
- `static data(string $key, array $user): mixed` – Daten einer nachgeladenen Karte (zwischengespeichert je Website + Rolle); HTML-Ausgabe über die Ansicht dashboard/_{karte}.php
- `static extensions(array $user): array` – Beiträge der Erweiterungen (je Anfrage einmal)
- `static forget(): void` – Zwischenspeicher leeren (z. B. nach Tests)
- `static gauge(?float $level): string` – Rundinstrument über 270° wie der Block „Kennzahlen mit Skala“ (dials); $level 0–100, null = neutrale Skala
- `static help(array $user): array` – Top-3-Tutorials der empfohlenen Zielgruppe (Rolle) mit Link zum Video (Produkt-Website, config 'docs_url'), Trailer-Link, Neuigkeiten aus CHANGELOG.md (nur Administration)
- `static isAdmin(array $user): bool`
- `static prefs(array $user): array`
- `static render(string $key, array $user, array $card): string` – HTML einer Karte (Kern: Ansicht; Erweiterung: render())
- `static savePrefs(int $userId, ?array $dash): void` – Speichern (null = Standard wiederherstellen) – andere Einträge in ui_prefs bleiben unverändert

### `Metrics` (class, `app/Dashboard/Metrics.php`)

Zahlen der Übersicht (/admin) – nur echte Werte aus der Datenbank der Website, keine Besucherstatistik (kein Tracking). Quellen: pages, revisions (je Seite die letzten 20 Stände – ältere fallen heraus), change_log (API, MCP, KI), media, Datentabellen (created_at/updated_at/status), Eingangs-Tabellen (nur Anzahl, Inhalte bleiben verschlüsselt),

Konstanten: `PLACEHOLDER_RX`

- `static activity(): array` – Änderungen je Tag der letzten 30 Tage: Seiten gespeichert (revisions), davon veröffentlicht, neue Einträge, über API/MCP/KI
- `static ago(int $days): string` – Datum/Uhrzeit vor $days Tagen im Speicherformat (Y-m-d H:i:s, vergleichbar als Zeichenkette)
- `static figures(array $user): array` – Kacheln „Kennzahlen“: key, label, value, text, level (0–100 | null), trend, href, icon, hot
- `static health(): array` – Betriebs-Prüfungen für die Administration (Auszug aus `bin/console health`, ohne Shell): [label => true|false|null]
- `static mediaCounts(): array`
- `static placeholder(): ?array` – Erster [Platzhalter] in veröffentlichten Seiten (ohne bestätigte, ohne Redaktionsnotizen): ['id', 'title', 'text'] oder null
- `static placeholderEditUrl(array $hit): string` – Sprung zur Fundstelle: Frontend-Editor am Block (resources/js/editor.js: #b-{id} scrollt hin, markiert und öffnet die Felder), Einträge in der Verwaltung
- `static placeholders(Database $db, array $ok, int $max = 100, ?int $page = null): array` – Offene Stellen in den Seiten einer Website (auch für die Netzwerk-Übersicht mit fremder Datenbank), je Block:
- `static placeholdersOk(): array` – Als gewollt bestätigte Klammer-Texte (Einstellung sys.placeholders_ok, z. B. „[Musik]“ in einer Anleitung)
- `static recent(array $user, int $limit = 5): array` – Eigene Seiten (Versionen mit user_id) und Team (Seiten + Einträge nach updated_at)
- `static requestsWeekly(): array` – Anfragen je Kalenderwoche (12 Wochen), nur lesbare Eingangs-Tabellen – nur Anzahlen
- `static searchMisses(int $limit = 8): ?array` – Anonyme Suchbegriffe ohne Treffer (nur wenn die Website-Suche sie zählt – Grundeinstellungen → Suche)
- `static setupChecks(): array` – Einrichtungs-Prüfungen (bisherige Checkliste der Übersicht); 'admin' = nur für die Administration relevant
- `static tables(): array` – Inhaltstabellen, die der Benutzer bearbeiten darf (ohne Eingangs-Tabellen)
- `static todos(array $user, array $checks): array` – Aufgaben mit Anzahl, Erklärung und Hauptaktion, nach Dringlichkeit sortiert (tone: err | warn | info).
- `static topPages(int $limit = 5): array` – Meistbearbeitete Seiten (Speicherungen in 30 Tagen)
- `static upcoming(int $limit = 8): array` – Termine der nächsten 7 Tage aus Kalender-Tabellen, die der Benutzer bearbeiten darf

## Core\Data

### `Calendar` (class, `app/Data/Calendar.php`)

Kalender für Datentabellen: Termine mit Beginn/Ende, ganztägig, Wiederholung (RFC 5545 RRULE) und Ausnahmen.

Konstanten: `DEFAULTS`, `MAP`, `MAX_OCCURRENCES`, `MAX_PER_ENTRY`, `MAX_COUNT`

- `static blockWhere(array $table, array $d, array $query = []): array` – Filter der Blöcke „calendar“/„upcoming“: fester Filter (filter_field „tabelle.feld“ + filter_value)
- `static config(array $table): array`
- `static dayLabel(DateTimeInterface $d, string $style = 'short'): string` – „Mo., 12.10.2026“ (short) bzw. „Montag, 12. Oktober 2026“ (long)
- `static describe(mixed $value, ?string $lang = null): string` – Lesbare Beschreibung in der Sprache der Seite, z. B. „Wöchentlich am Montag und Mittwoch, bis zum 31.12.2026“
- `static enabled(array $table): bool` – Ist die Tabelle ein Kalender (Funktion an, Beginn-Feld vorhanden)?
- `static feedUrls(array $table, ?string $lang = null): array` – Öffentliche Adressen des Feeds: ['https' => …, 'webcal' => …]
- `static fmt(DateTimeInterface $d, string $pattern): string` – Datum nach ICU-Muster in der Sprache der Seite, z. B. fmt($d, 'LLLL y') → „Oktober 2026“, 'EEEEEE' → „Mo“
- `static normalize(mixed $raw): array` – Eingabe prüfen und in die gespeicherte Form bringen.
- `static occurrences(array $table, DateTimeInterface $from, DateTimeInterface $to, array $opts = []): array` – Termine (einzelne Vorkommen) im Zeitraum [$from, $to), sortiert nach Beginn.
- `static parse(mixed $v): ?array` – Datum/Zeit aus einem Feldwert (Y-m-d oder Y-m-d H:i) in Ortszeit
- `static parseValue(mixed $v): array` – Gespeicherten Wert zerlegen: ['rrule' => 'FREQ=…', 'exdates' => ['2026-10-12', …]]
- `static recurrence(array $table, array $e): array` – Gespeicherte Wiederholung eines Eintrags
- `static span(array $table, array $e): ?array` – Beginn/Ende (Ende exklusiv, bei ganztägig = Folgetag 0 Uhr) eines Eintrags.
- `static tables(): array` – Alle Tabellen mit Kalender
- `static timeLabel(array $o): string` – Uhrzeit bzw. Zeitspanne eines Termins in der Sprache der Seite: „09:00–11:00 Uhr“, „ganztägig“
- `static tz(): DateTimeZone`
- `static uid(array $table, array $e): string` – Stabile UID eines Eintrags für iCal/CalDAV
- `static upcoming(array $table, int $limit = 5, int $days = 0, array $opts = []): array` – Nächste Termine ab jetzt (laufende eingeschlossen); $days = 0 → bis zu einem Jahr
- `static validateSettings(array $in, array $fields, array &$errors, ?array $old = null): array` – Tabellen-Einstellung prüfen (aus dem Formular bzw. der API)
- `static when(array $o, string $style = 'short'): string` – Datum + Uhrzeit: „Mo., 12.10.2026, 09:00–11:00 Uhr“ bzw. mehrtägig „03.10.2026 – 05.10.2026“

### `Clamp` (class, `app/Data/Clamp.php`)

Textlänge im Block „Datenliste“ (data_list): Texte und Titel in Karten und Listen auf wenige Zeilen kürzen.

Konstanten: `LINES`, `TITLE_LINES`, `TYPES`, `CHARS_PER_LINE`

- `static applies(array $table, string $field): bool` – Wird dieses Feld gekürzt?
- `static cls(int $lines): string` – Klassen für ein gekürztes Element (mit führendem Leerzeichen) oder ''
- `static excerpt(string $text, int $max): string` – Auszug: Leerraum zusammenfassen, an einer Wortgrenze kürzen, „…“ anhängen
- `static fields(): array` – Felddefinitionen für den Block (Seitenleiste)
- `static lines(array $d): int` – Gewählte Zeilenzahl für Texte (0 = vollständig)
- `static text(array $table, array $e, string $field, array $d, array $o = […]): array` – Feld für Karten/Listen: gekürzt → [Klassen, reiner Text (maskiert)], sonst ['', Entries::html()].
- `static textClass(array $d): string`
- `static titleClass(array $d): string`
- `static titleLines(array $d): int` – Gewählte Zeilenzahl für Titel (0 = vollständig)

### `DataForms` (class, `app/Data/DataForms.php`)

Öffentliche Formulare für Datentabellen: Besucher legen Einträge an (Funktion „forms.data“).

Konstanten: `DEFAULTS`, `RECEIPT`, `TYPES`, `UPLOAD_TYPES`, `REL_TYPES`, `PRIVACY`, `FILE_KINDS`, `FILE_KINDS_DEFAULT`, `FILE_KINDS_INBOX`, `MAX_MB`, `MAX_ENTRIES`

- `static acceptAttr(array $kinds): string` – accept-Attribut: MIME-Typen und Endungen (Auswahldialog – geprüft wird serverseitig am Inhalt)
- `static available(): bool`
- `static contentHtml(array $f): string` – Freitext im Formular: formatierter Text (Whitelist Core\Sanitizer), ohne Beschriftung
- `static eligible(array $f, bool $uploads): bool` – Darf der Feldtyp ins öffentliche Formular? Abschnitte und Freitext (Tables::LAYOUT) immer
- `static enabled(array $t, bool $direct = true): bool` – Nimmt die Tabelle Einsendungen an? $direct = true: über /formular/{handle} bzw. den Block „data_form“ – Eingänge einer Erweiterung
- `static fields(array $t): array` – Datenfelder des Formulars (Reihenfolge wie in der Tabelle): gewählte + alle Pflichtfelder; nichts gewählt = alle geeigneten
- `static fileHint(array $kinds, int $mb): string` – Sichtbarer Hinweis unter dem Dateifeld: „PDF oder Bild (JPG, PNG, WebP), höchstens 5 MB.“ (Sprache der Website)
- `static fileKinds(array $f, bool $inbox): array` – Erlaubte Dateitypen eines Upload-Feldes (Schlüssel aus FILE_KINDS); Bildfeld: nur Bilder, ohne Eingang kein DOCX/ODT
- `static fileMb(array $f, array $s): int` – Höchstgröße (MB) je Datei für das Feld: eigene Grenze des Feldes, höchstens die der Tabelle
- `static full(array $t): bool` – Obergrenze erreicht? (settings.form.max, nur Inhaltstabellen) – gezählt werden alle Einträge der Tabelle (auch Entwürfe,
- `static group(array $f, string $uid, mixed $v, array $errors, array $c = []): string` – Wiederholbare Gruppe als Fieldset: je Zeile ein Fieldset „{item} {n}“ mit den Unterfeldern, „+ …“ und „Entfernen“
- `static items(array $t): array` – Alle Elemente des Formulars in der Reihenfolge der Tabelle: Datenfelder wie fields() und dazwischen die gewählten
- `static key(array $t): string`
- `static privacyPage(?string $lang = null): ?array` – Datenschutzseite (in der Sprache $lang bzw. der aktuellen) oder null – auch für den Dialog (Core\LegalDialog)
- `static privacyUrl(): string` – Datenschutzseite: Einstellung des Themes (privacy_page / datenschutz_seite) oder Seite „datenschutz“
- `static relationOptions(array $f): array` – Auswahl für ein Verknüpfungsfeld im Formular: veröffentlichte Einträge der Zieltabelle (aktuelle Sprache), alphabetisch – id => Titel.
- `static render(array $t, array $o = []): string` – Formular-HTML (theme-neutral, Klassen dff-*). $o: uid, submit (Beschriftung), success (Text nach dem Absenden),
- `static sections(array $items): array` – Elemente in Abschnitte gliedern: [[Abschnitt|null, [Elemente …]], …] – Felder vor dem ersten Abschnitt stehen ohne Abschnitt.
- `static selected(array $t, array $f): bool` – Steht das Feld in der Auswahl „Felder im Formular“? Nichts gewählt = alle; Pflichtfelder, Abschnitte und Freitext immer
- `static sniff(string $path, string $name, array $kinds): ?string` – Dateityp am Inhalt erkennen (finfo) und mit der Endung abgleichen – das accept-Attribut ist nur ein Hinweis für den Dialog.
- `static submit(array $t, array $post, array $files, string $ip, array $o = []): array` – Einsendung prüfen und als Eintrag speichern.
- `static successText(array $t): string` – Text nach dem Absenden: Block/Tabelle, bei Eingangs-Tabellen sonst der Text des Theme-Formulars
- `static validateSettings(array $s, array $fields, array &$errors, ?array $existing, ?bool $uploads = null, ?array $known = null): array` – settings.form prüfen. $uploads: wirksamer Schalter „Datei-Uploads“ (Eingang: aus der Zustellung abgeleitet – Tables::validate),

### `Dbal` (class, `app/Data/Dbal.php`)

Doctrine-DBAL-Verbindung für die Schema-Verwaltung der Datentabellen (plattformneutral: SQLite und MySQL/MariaDB, inkl. Tabellen-Neuaufbau bei SQLite-ALTER). Lese-/Schreibzugriffe auf Einträge laufen über die gemeinsame PDO-Verbindung (Core\Database).

- `static conn(): Doctrine\DBAL\Connection`
- `static sqlite(string $path): Doctrine\DBAL\Connection` – Verbindung zu einer eigenen SQLite-Datei (z. B. storage/shared/{key}/share.sqlite)

### `Delivery` (class, `app/Data/Delivery.php`)

Zustellung der Anfragen einer Eingangs-Tabelle (settings.inbox.delivery, Funktion „requests.mail“).

Konstanten: `MODES`, `MACHINE`, `DEFAULTS`, `SUBJECT`, `ALERTS`

- `static accept(array $t, array $values, array $files = [], ?callable $store = null): array` – Einsendung einer Eingangs-Tabelle annehmen (DataForms::submit): je nach Modus speichern und/oder per E-Mail zustellen.
- `static alert(array $t, string $ref, string $reason, bool $stored): void` – Zustellung fehlgeschlagen: Hinweis in der Verwaltung (Anfragen, Tabelle), Fehlerprotokoll und – falls möglich – eine
- `static alerts(?array $handles = null): array` – Offene Hinweise (optional nur für bestimmte Tabellen)
- `static available(): bool` – Funktion „requests.mail“ (und „requests“) eingeschaltet?
- `static certInfo(string $pem): ?array` – Angaben zu einem Zertifikat (PEM) oder null
- `static certs(string $pem): array` – Alle Zertifikate einer PEM-Kette
- `static clearAlerts(array $handles): void`
- `static config(array $t): array`
- `static dummy(array $t): array` – Erfundene Anfrage für „Testmail senden“
- `static log(array $t, array $ids, string $mode, array $sent, string $ref, string $fallback = ''): void` – Zustellprotokoll in inbox_log (action „mail“): Modus, Status, Empfänger-Hash, Vorgangsnummer, Message-ID – ohne IP und Inhalte
- `static mails(array $t): bool` – Stellt die Tabelle Inhalte per E-Mail zu?
- `static managed(array $t): bool` – Verantwortet eine Erweiterung den Eingang (z. B. Buchungen)? Dann kein Modus „nur per E-Mail“.
- `static message(array $t, array $values, array $files, string $ref, string $at, string $mode, bool $test = false): array` – Inhalt der E-Mail. $files: [feld => ['name', 'type', 'data' (binär)]]; $at: Eingangszeit (Y-m-d H:i:s).
- `static mode(array $t): string` – Wirksamer Modus (Funktion aus → system; „mail“ bei Eingängen einer Erweiterung → both)
- `static modeLabel(string $m): string`
- `static normalizeCerts(string $pem): array` – Eingefügte Zertifikate (PEM, ein oder mehrere Blöcke) prüfen und normalisieren.
- `static problems(array $t): array` – Hinweise zur Einrichtung (Verwaltung, Anfragen): [['level' => ok|warn|error, 'text' => …], …] – leer bei Modus „system“.
- `static recipientHash(array $to): string` – Empfänger als Hash (HMAC mit dem App-Schlüssel, 12 Zeichen) – gleiche Adressen ergeben denselben Wert
- `static recipients(array $t, array $values): array` – Empfänger: Weiterleitung nach Auswahlfeld (z. B. „Rezept“ → rezept@…), sonst die Standard-Empfänger
- `static sealFiles(array $values, array $files): array` – Dateien für den versiegelten Payload: [feld => ['file', 'type', 'size', 'sha256', 'data' (Base64)]] in $values
- `static send(array $t, array $values, array $files, string $ref, ?string $mode = null, bool $test = false, ?string $at = null): array` – Inhalt per E-Mail zustellen (ohne Speichern, ohne Protokoll).
- `static split(string $list): array`
- `static test(array $t): array` – Testmail mit erfundener Anfrage an die gespeicherten Empfänger (inkl. S/MIME, Anhang-Beispiel bei Dateifeldern)
- `static tooLarge(array $t, array $files): ?string` – Dateien einer Einsendung zu groß für die E-Mail? (nur „nur per E-Mail“ lehnt ab – sonst bleiben sie im System)
- `static transport(?array $s = null): array` – Ist der E-Mail-Versand für Inhalte sicher genug? level: ok | warn | error (error = keine Inhalte per E-Mail).
- `static validateSettings(array $s, array $fields, array &$errors, ?array $existing, ?array $table): array` – settings.inbox.delivery prüfen. $fields: Felder der Tabelle (Weiterleitung nach Auswahlfeld), $table: bestehende Tabelle.

### `DeliverySelfTest` (class, `app/Data/DeliverySelfTest.php`)

Selbsttest der Zustellung per E-Mail (Core\Data\Delivery) – `php bin/console inbox:selftest`. Legt eine vorübergehende Eingangs-Tabelle an (danach gelöscht), nutzt einen Test-Schlüssel (nur im Speicher) und legt E-Mails in einem temporären Ordner ab (Mailer::$testDump) – nichts wird versendet, Einstellungen bleiben unverändert.

- `static run(): array`

### **API** `Entries` (class, `app/Data/Entries.php`)

Einträge einer Datentabelle: Abfragen, Speichern, Ausgabe-Formatierung, Detail-URLs.

- `static absUrl(array $table, array $e): ?string` – Absolute Adresse (für Canonical, JSON-LD, Feeds): fremde Einträge → Ursprungs-Website, falls sie Detailseiten hat – außer die
- `static backlinks(array $table, int $id): array` – Wer verweist auf diesen Eintrag? [['table' => …, 'field' => …, 'entries' => […]], …]
- `static bindValue(array $table, array $e, string $source, string $targetType): mixed` – Wert eines Datensatz-Feldes passend zum Typ des Blockfeldes liefern
- `static bindable(array $table, string $targetType): array` – Welche Datensatz-Felder passen zu einem Blockfeld-Typ? [quelle => Bezeichnung]
- `static bySlug(array $table, string $slug, bool $publishedOnly = true, ?string $lang = null, ?string $source = null): ?array` – $source (nur geteilte Tabellen): nur Einträge dieser Quelle, z. B. 'site' für Detailseiten der Website
- `static conditions(array $table, array $list, array &$params): array` – Bedingungen [[feld, op, wert], …] als SQL-Teile (Parameter werden an $params angehängt)
- `static count(array $table, array $o = []): int`
- `static delete(array $table, int $id): void`
- `static find(array $table, int $id): ?array`
- `static formSchema(array $table): array` – Eingabemaske der Redaktion (Verwaltung, Seitenleiste „Eintrag bearbeiten“): Datenfelder wie schema(), dazwischen die
- `static groupCell(array $sf, mixed $v, ?string $lang = null): string` – Unterfeld-Wert als Klartext (Verwaltung, Ausgabe). $lang: Sprache der Auswahltexte (null = aktuelle).
- `static groupRows(array $f, mixed $v): array` – Wert einer Gruppe als Liste von Zeilen (alte Einzelwerte = eine Zeile im ersten Unterfeld)
- `static groupSchema(array $f, bool $site = false): array` – Wiederholbare Gruppe als Fields-Schema: fields (Unterfelder), min, max, item_label, add_label.
- `static groupSummary(array $f, mixed $v): string` – Kurzfassung für Listen: „Ibuprofen, Paracetamol +1“ bzw. „3 Einträge“
- `static href(array $table, array $e): ?string` – Link für Listen, Kalender und Verknüpfungen: wie url(); fremde Einträge geteilter Tabellen auf Wunsch der Website
- `static html(array $table, array $e, string $name, array $o = []): string` – Feld als sicheres HTML ausgeben.
- `static query(array $table, array $o = []): array` – Einträge abfragen.
- `static quickCreate(array $table, string $title): array` – Schnell einen Eintrag nur mit Titel anlegen (z. B. neues Schlagwort im Formular)
- `static reorder(array $table, array $ids): void` – Manuelle Reihenfolge speichern
- `static replaceTokens(string $s, bool $html = false): string` – Platzhalter {{feld}} in einem Text durch Werte des aktuellen Eintrags ersetzen
- `static save(array $table, ?int $id, array $in, string $note = ''): array` – Eintrag anlegen oder ändern.
- `static schema(array $table): array` – Felder der Tabelle als Fields-Schema (für Formulare und Bereinigung)
- `static setStatus(array $table, array $ids, string $status): void`
- `static text(array $table, array $e, string $name): string` – Rohwert als Text (für Platzhalter {{feld}} in normalen Textfeldern)
- `static title(array $table, array $e): string`
- `static titles(array $table): array` – id → Titel aller Einträge (für Verknüpfungen)
- `static translate(array $table, int $id, string $lang): array` – Übersetzung eines Eintrags anlegen (Kopie als Entwurf in der Zielsprache, verknüpft)
- `static translations(array $table, array $e): array` – Übersetzungen eines Eintrags: [sprache => eintrag]
- `static url(array $table, array $e): ?string` – URL der Detailseite (nur wenn eine URL-Basis und eine Detailvorlage existieren)

### `EntryBlocks` (class, `app/Data/EntryBlocks.php`)

Feldtyp „Inhaltsblöcke“ (blocks): ein Eintrag hat eine eigene Folge von Blöcken des Kits – gespeichert wie der Inhalt einer Seite (Liste {id, type, data, tunes}, bereinigt über Pages::sanitizeBlocks). Erlaubte Blocktypen je Feld ('blocks' => [typ, …], leer = Standard: Inhalt und Medien). Eingabe in der Verwaltung: Blöcke mit den Feldern wie in der Seitenleiste des Seiten-Editors

Konstanten: `TYPE`, `MAX`

- `static allowed(array $f): array` – Erlaubte Blocktypen eines Feldes (in der Reihenfolge des Kits)
- `static choices(): array` – Wählbare Blöcke [typ => Bezeichnung] – einfügbar, ohne Daten-Blöcke
- `static contentEdit(array $ctx, array $templateBlocks): ?array` – Modus „Inhalt“ der Detailseite: Feld, Stelle in der Vorlage und Rechte – null, wenn nicht möglich (kein Feld „Inhaltsblöcke“,
- `static defaults(): array` – Vorbelegung: Blöcke der Gruppen „Inhalt“ und „Medien“
- `static editAttr(array $data): string` – Bearbeiten auf der Detailseite: Attribute für die Hülle der eingesetzten Blöcke (Knopf „Bearbeiten“ → Seitenleiste am Feld)
- `static fieldsOf(?array $t): array` – Für den Kern-Block entry_blocks: Felder dieses Typs einer Tabelle [name => Bezeichnung]
- `static forContext(array $data): array` – Blöcke des aufgerufenen Eintrags für den Kern-Block entry_blocks (data: table, field „tabelle.feld“) – leer ohne Eintrag
- `static items(mixed $raw): array` – Gespeicherter Wert (JSON oder Liste) → Liste der Blöcke
- `static mediaIds(mixed $raw): array` – Verwendete Medien (Bilder, Dateien, Links „media:ID“)
- `static render(array $blocks): string` – Darstellung (eigene Abschnitte des Kits)
- `static renderInput(array $f, string $name, mixed $value, array $errors, string $path, string $label, string $errHtml): string` – Feld „Inhaltsblöcke“ im Formular: Blöcke mit ihren Feldern, „+ Block hinzufügen“ mit den erlaubten Typen
- `static sanitize(array $f, mixed $raw): array` – Eingabe bereinigen: Formular ({i: {type, id, tunes (JSON), data}}) oder gespeicherte Liste → Blöcke wie auf Seiten.
- `static selftest(): array` – Selbsttest (Teil von `php bin/console data:selftest`): Feldvorlage des Tabellen-Designers (data/_field.php) für jeden Feldtyp
- `static summary(mixed $raw): string` – Kurzfassung für Listen, z. B. „3 Blöcke: Fließtext, Galerie“
- `static text(mixed $raw): string` – Text für die Suche

### `EntryEdit` (class, `app/Data/EntryEdit.php`)

Einträge direkt auf der Website bearbeiten (nur angemeldete Redaktion).

- `static adminUrl(array $t, ?array $e = null): string`
- `static attr(array $t, array $e, string $field, ?string $mode = null): string` – Attribute für ein Element, das den Wert eines Feldes enthält (nur im Bearbeitungsmodus der Detailseite, sonst '').
- `static blockAttr(Block $b, string $path): string` – Block einer Detailvorlage: an den Datensatz gebundenes Blockfeld (⛓) → Feld des Eintrags inline bearbeiten.
- `static button(array $t, array $e, string $title = ''): string` – Stift „Eintrag bearbeiten“ in Listen (Link zur Verwaltung als Rückfall ohne Skript)
- `static canEdit(array $t, array $e): bool` – Darf der Nutzer diesen Eintrag ändern? (fremd in geteilter Tabelle: nein; ohne Veröffentlichungsrecht nur Entwürfe)
- `static canTable(array $t): bool` – Darf der angemeldete Nutzer diese Tabelle überhaupt auf der Website bearbeiten?
- `static config(array $t, array $e): array` – Konfiguration für das Skript auf Detailseiten
- `static current(): ?array` – Aktiver Eintrag der Detailseite, falls er hier bearbeitet werden darf
- `static endpoint(array $t, ?array $e = null): string`
- `static mode(array $t, string $field): string` – Bearbeitungsart eines Tabellenfeldes ('' = nicht inline) – _title steht für das Titelfeld
- `static newButton(array $t): string` – „+ Neuer Eintrag“ für Datenlisten
- `static originEdit(array $t, array $e): ?array` – Bearbeiten auf der Ursprungs-Website (fremder Eintrag einer geteilten Tabelle): [url, sso?]
- `static reason(array $t, array $e): ?string` – Warum nicht? null = bearbeitbar
- `static texts(): array` – Texte für das Skript (in der Sprache der Verwaltung)
- `static toolbar(array $vars): string` – Werkzeugleiste für Detailseiten außerhalb des Vorlagen-Editors – für ältere Theme-Partials mit eigener Seiten-Leiste

### `Feed` (class, `app/Data/Feed.php`)

RSS-Feed (RSS 2.0) einer Datentabelle mit Detailseiten – Beiträge, News, Termine …: GET /feed/{handle}.xml (?lang=en). Nur veröffentlichte Einträge (Reihenfolge der Tabelle, höchstens LIMIT), nur Tabellen mit URL-Basis + Detailvorlage, die nicht auf „Nicht indexieren“ stehen; Eingänge nie. Seiten der Tabelle (Detailseiten und die Seite unter der URL-Basis) verweisen im

Konstanten: `LIMIT`

- `static available(?array $t): bool`
- `static inject(string $html, ?array $page, ?array $table): string` – Kopf der Website: Verweis auf den Feed der Tabelle, deren Einträge die Seite zeigt (Detailseite) bzw. deren URL-Basis die Seite ist.
- `static path(array $t): string`
- `static selftest(): array`
- `static xml(array $t): string`

### `ICal` (class, `app/Data/ICal.php`)

iCalendar (RFC 5545) aus Kalender-Tabellen: Feed einer Tabelle und einzelne Termine. Zeitangaben mit TZID der Website + VTIMEZONE (aus den Zeitzonen-Übergängen erzeugt), ganztägige Termine als DATE. Zeilen werden nach 75 Oktetts gefaltet (UTF-8-sicher), Texte nach RFC 5545 §3.3.11 maskiert, Zeilenende CRLF.

- `static calendar(array $table, array $entries, array $o = []): string` – Kompletter Kalender (VCALENDAR) mit allen übergebenen Einträgen
- `static fold(string $line): string` – Zeile nach 75 Oktetts falten, ohne UTF-8-Zeichen zu zerteilen
- `static plain(string $s): string` – Text aus HTML/Mehrzeilern: Tags weg, Entities auflösen, Zeilen bereinigen
- `static prodId(): string`
- `static text(string $s): string` – TEXT-Wert maskieren (RFC 5545 §3.3.11)
- `static vevent(array $table, array $e, array $o = []): array` – VEVENT-Zeilen eines Eintrags (ungefaltet). $o['uid'] überschreibt die UID (z. B. aus CalDAV),
- `static vtimezone(DateTimeZone $tz, int $fromYear): array` – VTIMEZONE aus den Übergängen der Zeitzone: Sommer-/Normalzeit mit jährlicher Regel (z. B. letzter Sonntag im März),

### `Inbox` (class, `app/Data/Inbox.php`)

Eingangs-Tabellen („Eingang“, settings.kind = inbox): Datentabellen, deren Einträge nur über das öffentliche Formular entstehen und Ende-zu-Ende verschlüsselt gespeichert werden (Ersatz für die früheren Online-Anfragen).

Konstanten: `STATUSES`, `DEFAULTS`, `META`

- `static allStatusKeys(): array` – Alle bekannten Status aller Eingänge (Filter über mehrere Tabellen, z. B. API)
- `static anyNeedsKey(): bool` – Gibt es einen Eingang, der verschlüsselt speichert? (Warnpunkt, Einrichtungs-Prüfung)
- `static assign(array $t, int $id, ?int $userId): void`
- `static available(): bool` – Funktion „requests“ (Anfragen) für diese Website eingeschaltet?
- `static config(array $t): array`
- `static count(array $t, string $status = 'alle'): int`
- `static delete(array $t, array $ids, string $via = ''): int`
- `static directForm(array $t): bool` – Nimmt die Tabelle Einsendungen über /formular/{handle} bzw. den Block „data_form“ an? (Erweiterung: 'direct_form' => false)
- `static display(array $f, mixed $v): string` – Wert als Klartext (Verwaltung, Standardsprache)
- `static ext(array $t): array` – Einstellungen der zuständigen Erweiterung (Extension::inbox) – [] = keine
- `static fieldsFromRepeater(string $setting): array` – Felder aus dem Repeater der Theme-Einstellungen (label, name, typ, breite, pflicht, optionen) – Namen bleiben stabil
- `static fileLabel(array $v): string` – Datei als Text: „befund.pdf (120 KB)“
- `static find(array $t, int $id, bool $payload = false): ?array`
- `static forForm(string $key): ?array` – Eingangs-Tabelle, die ein Theme-Formular (z. B. „rezept“) bedient
- `static groupTable(string $label, array $cols, array $rows): array` – Gruppe als Tabelle für die Anzeige: ['label', 'value' (Klartext für Kopieren), 'table' => ['cols' => [...], 'rows' => [[...], …]]].
- `static info(array $t, array $row): string` – Zusatzzeile der Erweiterung zu einer Anfrage (Klartext) oder ''
- `static is(array $t): bool`
- `static log(string $handle, array $ids, string $action, string $detail = ''): void`
- `static logQuery(array $o = []): array` – Letzte Ereignisse; $o: table, user (ID), limit
- `static maybePurge(): void` – Gelegentlich bei Verwaltungsaufrufen (≈ 1 %, höchstens alle 6 Stunden)
- `static meta(array $t, array $row, bool $ciphertext = false): array` – Metadaten für API/MCP – Inhalte nie, Chiffretext nur auf ausdrücklichen Wunsch
- `static migrate(): array` – Idempotent: legt für jedes Theme-Formular (theme.php → forms) eine Eingangs-Tabelle an (Felder aus dem Einstellungs-Repeater),
- `static needsKey(array $t): bool` – Braucht der Eingang den zentralen Schlüssel? Nur, wenn er Einsendungen verschlüsselt speichert (Zustellung „Im System“ bzw.
- `static needsMigration(): bool` – Muss die Umstellung (noch) laufen? – billige Prüfung beim Start
- `static newCount(): int` – Anzahl „neu“ in allen Eingangs-Tabellen, die der Benutzer lesen darf (Badge in Navigation und Übersicht)
- `static newRef(array $t): string` – Vorgangsnummer wie „K7QX-9MZA“ (zufällig, ohne Bezug zu Inhalten)
- `static open(array $t, array $row, string $secret): ?array` – Payload entschlüsseln und für die Anzeige aufbereiten.
- `static purge(): array` – Erledigte Anfragen nach N Tagen löschen (je Tabelle, 0 = nie) und alte Protokolleinträge entfernen.
- `static query(array $t, array $o = []): array` – $o: status (neu|in_bearbeitung|erledigt|alle), ids, limit, offset, payload (bool)
- `static readable(string $perm = 'requests.read'): array` – Eingangs-Tabellen, die der angemeldete Benutzer lesen darf (Recht requests.read, optional je Tabelle)
- `static setStatus(array $t, array $ids, string $status, string $via = ''): int` – Status setzen. Eigene Status einer Erweiterung sind erlaubt; deren 'guard' kann ablehnen (InvalidArgumentException mit Meldung).
- `static statusKeys(array $t): array` – Schlüssel der Status (für Prüfungen)
- `static statusLabel(string $s, ?array $t = null): string` – Bezeichnung eines Status – mit $t auch für eigene Status einer Erweiterung (Extension::inbox)
- `static statuses(array $t): array` – Status der Tabelle: [schlüssel => ['label', 'action', 'tone', 'done', 'manual' (false = kein Knopf in „Anfragen“, nur per Code/API)]]. Standard neu|in_bearbeitung|erledigt; eine Erweiterung kann
- `static store(array $t, array $values, ?string $ref = null, array $meta = []): array` – Einsendung versiegeln und speichern. $values: bereinigte Feldwerte (ohne Datenschutz-Checkbox); Dateifelder (nur bei Zustellung
- `static sync(array $table): void` – data_{handle} für Eingangs-Tabellen anlegen bzw. angleichen (keine Feldspalten)
- `static tables(): array` – Alle Eingangs-Tabellen
- `static text(array $t, string $key, ?string $lang = null): string` – Text der Tabelle in der Sprache der Seite: title, intro, success, submit.
- `static validateSettings(array $s, ?array $existing): array` – settings.inbox prüfen (Tables::validate)

### `Jobs` (class, `app/Data/Jobs.php`)

Stellenangebote (Google for Jobs, schema.org JobPosting) – Vorlage „Stellenangebote“ + Eingang „Bewerbungen“.

Konstanten: `TYPE`, `FIELD`, `DEFAULTS`, `MAP`, `EMPLOYMENT`, `GOOGLE_TYPES`, `UNITS`, `WORKPLACE`

- `static address(array $t, array $e): array` – Arbeitsort als PostalAddress-Werte: eigene Felder (sobald Straße, PLZ oder Ort ausgefüllt ist), sonst die Adresse der
- `static applicationsPreset(): array` – Vorlage „Bewerbungen“ (Eingang, verschlüsselt bzw. nur per E-Mail) – Dateien nur mit Zustellung per E-Mail
- `static applyPost(array $inbox, array $post): array` – Einsendung an einen Bewerbungs-Eingang: Feld „Stelle“ aus `_job` ({tabelle}:{id}) setzen – der Server bestimmt den Text
- `static check(array $n): array` – JobPosting gegen die Pflicht- und Formatvorgaben von Google prüfen. Rückgabe: Liste der Probleme (leer = gültig).
- `static config(array $t): array`
- `static country(array $t, array $e): string`
- `static currentSql(array $t, array &$params): ?string` – SQL-Bedingung „noch ausgeschrieben“ für Entries::where (null = Tabelle hat kein Feld „Gültig bis“)
- `static datePosted(array $t, array $e): string` – Veröffentlicht am (JJJJ-MM-TT): Feld, sonst Veröffentlichung des Eintrags
- `static employment(array $t, array $e): array` – Beschäftigungsart: [Texte (Sprache der Seite), Google-Werte]
- `static ensureForm(string $handle = 'bewerbungen', ?callable $log = null): ?array` – Bewerbungs-Eingang anlegen (Vorlage „Bewerbungen“) oder vorhandenen gleichen Kurznamens nutzen; Rückgabe: Eingang oder null
- `static expired(array $t, array $e): bool` – Abgelaufen: „Gültig bis“ liegt vor heute
- `static f(array $t, string $role): string` – Feldname einer Rolle in dieser Tabelle ('' = Feld gibt es nicht)
- `static facts(array $t, array $e): array` – Eckdaten für den Block job_facts: [[Beschriftung, HTML], …] in der Sprache der Seite
- `static fieldFor(array $inbox): ?string` – Feld „Stelle“ eines Bewerbungs-Eingangs (für Betreff und gesperrtes Feld) – null, wenn keine Stellen-Tabelle ihn nutzt
- `static formTable(array $t): ?array` – Bewerbungs-Eingang der Tabelle (nur wenn er Einsendungen annimmt)
- `static identifier(array $t, array $e): string` – Stellennummer: eigene Kennung oder automatisch {tabelle}-{id}
- `static is(?array $t): bool`
- `static jsonLd(array $t, array $e, string $url): ?array` – JobPosting für die Detailseite (StructuredData::entryNode). null = abgelaufen (keine strukturierten Daten).
- `static label(array $t, array $e): string` – Text im gesperrten Feld „Stelle“ des Formulars: Titel, mit eigener Kennung in Klammern
- `static linkForm(array $t, string $form): void` – Stellen-Tabelle mit dem Eingang verknüpfen (settings.jobs.form)
- `static makeListPage(array $t, string $title = ''): ?int` – Übersichtsseite unter der Adresse der Tabelle (nur einstufige Adresse, nicht im Menü): Datenliste der offenen Stellen
- `static makeTemplate(array $t): int` – Detailseiten-Vorlage: Kopf (Titel, Bild), Eckdaten mit „Jetzt bewerben“, Beschreibung, Bewerbungsformular (#bewerben)
- `static preset(): array` – Vorlage „Stellenangebote“ (Inhaltstabelle) im Format von DataController::PRESETS
- `static problems(array $t, array $e): array` – Probleme eines Eintrags für die Verwaltung (Hinweis im Eintrag): JSON-LD bauen und prüfen
- `static ref(array $t, array $e): string` – Für Formulare und Links: Eintrag als Link-Ziel
- `static salary(array $t, array $e): ?array` – Gehalt: ['min', 'max', 'unit', 'currency'] oder null (keine Angabe)
- `static salaryText(array $s): string`
- `static selftest(): array`
- `static summary(array $t, array $e): string` – Kurzzeile für Listen: „Vollzeit, Teilzeit · Moers“
- `static tables(): array` – Stellen-Tabellen dieser Website
- `static today(): string` – Heute in der Zeitzone der Website (JJJJ-MM-TT)
- `static v(array $t, array $e, string $role): mixed` – Rohwert einer Rolle
- `static validateSettings(array $s, array $fields, ?array $existing): array` – settings.jobs prüfen (Tables::validate): Bewerbungs-Eingang (Kurzname, „_new“ = beim Anlegen erzeugen) und Feld „Stelle“
- `static workplace(array $t, array $e): string`

### `LayoutSelfTest` (class, `app/Data/LayoutSelfTest.php`)

Selbsttest der Gestaltungs-Elemente im Formular (Feldtypen „Abschnitt“ und „Freitext“, Tables::LAYOUT) – Teil von `php bin/console data:selftest`. Legt eine vorübergehende Tabelle an und löscht sie danach wieder.

- `static run(): array`

### `Placement` (class, `app/Data/Placement.php`)

„Einsetzen“: Wie kommt eine Datentabelle auf die Website? – Block je Zweck (Formular bzw. Datenliste), Seiten, die die Tabelle schon verwenden, und die beiden Wege aus dem Assistenten bzw. „Felder & Einstellungen → Einsetzen“: page()   neue Seite mit dem passenden Block anlegen (Eltern-Seite über die Linkauswahl, page:ID; im Menü ja/nein)

Konstanten: `BLOCKS`

- `static append(array $t, string $ref, ?int $userId = null, bool $publish = false): array` – Block an eine bestehende Seite anhängen (am Ende, im Entwurf). $ref: Linkwert (page:ID) bzw. interner Pfad.
- `static blockData(array $t, ?string $type, string $title = ''): array` – Daten des Blocks für die Tabelle (Überschrift optional)
- `static blockLabel(?string $type): string` – Anzeigename des Blocks (wie in der Blockauswahl des Editors)
- `static blockType(array $t): ?string` – Passender Block für die Website: data_form (Formular, Anfragen, Anmeldung), data_list (Inhalte) oder null (intern)
- `static describe(string $ref): string` – Beschreibung des Linkziels für Meldungen (Titel der Seite)
- `static ensureTemplate(array $t): ?int` – Detailseiten-Vorlage anlegen, falls die Tabelle Detailseiten hat und noch keine (gültige) Vorlage
- `static page(array $t, array $o, ?int $userId = null): array` – Neue Seite mit dem passenden Block: $o title, parent (Linkwert, leer = oberste Ebene), menu (bool), publish (bool),
- `static pageRef(string $v): ?int` – Seiten-ID aus der Linkauswahl (page:12, page:12#anker) oder einem internen Pfad (/kontakt); sonst null
- `static usages(array $t): array` – Seiten, die die Tabelle verwenden: Blöcke mit data.table = Kurzname (auch in Layout-Spalten, veröffentlicht oder im Entwurf)

### `Purpose` (class, `app/Data/Purpose.php`)

Verwendungszweck einer Datentabelle (settings.purpose) – wofür die Tabelle da ist. Steuert den Assistenten „Neue Tabelle“ (Vorlagen, Grundeinstellungen, „Einsetzen“) und welche Bereiche „Felder & Einstellungen“ zuerst zeigt. Am Speichern und an den Rechten ändert der Zweck nichts – die Art (settings.kind: content | inbox) bleibt die technische Grundlage.

Konstanten: `KEYS`, `INBOX`, `CONTENT`, `SECTIONS`

- `static all(): array` – Beschriftung, Symbol, Erklärung und Beispiele je Zweck
- `static clean(mixed $in, array $settings, ?array $existing): string` – Zweck beim Speichern (Tables::validate): gewählt und passend → übernehmen, sonst der bisherige (falls passend), sonst abgeleitet.
- `static derive(array $s): string` – Zweck aus den übrigen Einstellungen ableiten (Tabellen von vor dem Assistenten): Eingang → „nur E-Mail“ bzw. „Anfragen“,
- `static fits(string $p, array $s): bool` – Passt der Zweck zur Art der Tabelle (Eingang/Inhalt) und – im Eingang – zur Zustellung?
- `static kind(string $p): string` – Art der Tabelle für einen Zweck (Anmeldung: je nach Vorlage – Bewerbungen sind ein Eingang)
- `static label(string $p): string`
- `static of(array $t): string` – Zweck einer Tabelle: gespeichert oder (ältere Tabellen) abgeleitet
- `static ofPreset(array $p): string` – Zweck einer Vorlage: angegeben oder aus der Art
- `static presetKeys(string $p, array $presets): array` – Vorlagen (Schlüssel aus DataController::presets()) je Zweck – der Assistent zeigt nur die passenden
- `static sections(string $p, bool $inbox): array` – Bereiche von „Felder & Einstellungen“: [Hauptbereiche, weitere Bereiche]. Eingänge haben keine Website-, Such- und Kalender-
- `static selftest(): array` – Selbsttest (data:selftest): Ableitung, Passung, Bereinigung beim Speichern und Bereiche je Zweck – ohne Datenbank.
- `static unavailable(string $p): ?string` – Kann der Zweck auf dieser Website gewählt werden? null = ja, sonst der Grund (für den Assistenten, mit Link zum Einschalten).
- `static valid(mixed $p): bool`

### `Revisions` (class, `app/Data/Revisions.php`)

Versionen von Einträgen – wie bei Seiten (Tabelle revisions): jeder gespeicherte Stand eines Eintrags landet in entry_revisions (Datenbank der Website, auch für geteilte Tabellen), höchstens config 'revisions' (Standard 20) je Eintrag.

- `static find(array $table, int $id, int $rev): ?array`
- `static forget(array $table, int $id): void` – Beim Löschen eines Eintrags
- `static has(array $table, int $id): bool` – Hat der Eintrag schon Versionen? (sonst sichert save() vorher den Ausgangsstand)
- `static keep(): int`
- `static list(array $table, int $id): array` – Versionen, neueste zuerst: [['id', 'created_at', 'note', 'user_email', 'status', 'data' => [...]], …]
- `static record(array $table, array $entry, string $note = ''): void` – Stand speichern (nach dem Speichern bzw. als Ausgangsstand)
- `static restore(array $table, int $id, int $rev): array` – Stand zurückschreiben (Status bleibt). @return array errors (leer = ok)
- `static snapshot(array $table, array $e): array` – Inhalt eines Eintrags für eine Version: Feldwerte + slug, lang, status

### `Rules` (class, `app/Data/Rules.php`)

Bedingungen je Feld einer Datentabelle – eine zentrale Auswertung für Verwaltung, API, MCP, CalDAV/CardDAV (alle über Entries::save) und öffentliche Formulare (DataForms). Spiegel im Browser: resources/js/_conditions.js.

Konstanten: `OPS`, `CMP`, `SOURCE_TYPES`, `COMPARE_TYPES`

- `static apply(array $fields, array $values, array $errors): array` – Regeln auf bereinigte Werte anwenden: ausgeblendete Felder leeren (und ihre Fehler verwerfen),
- `static client(array $fields): array` – Kompakte Konfiguration für das Formular-Skript: {feld: {v: [any, [[feld, op, wert], …]], r: […], q: 0|1}}
- `static has(array $f): bool` – Hat das Feld irgendeine Bedingung?
- `static normalize(array $in, array $def, array $all, array &$errors, string $key): array` – Bedingungen eines Feldes bereinigen. $all = [name => typ] aller Felder der Tabelle.
- `static test(mixed $value, string $op, string $x): bool`
- `static visibility(array $fields, array $values): array` – Sichtbarkeit aller Felder [name => bool]. Ausgeblendete Felder zählen für andere Bedingungen als leer

### `SchemaPanel` (class, `app/Data/SchemaPanel.php`)

„Felder bearbeiten“ im Seiten-Editor: die Felder eines Formulars (Block „Formular (Datentabelle)“ und Formulare in Kit-Blöcken) direkt auf der Seite ändern – Seitenleiste resources/js/_form_fields.js, Endpunkt Admin\FormFieldsController, Ansicht app/Views/formfields-panel.php. Recht „data.schema“ (geteilte Tabellen: nur die Eigentümer-Website, Shared::canSchema).

- `static addTypes(array $t): array` – Typen der Knöpfe „Feld hinzufügen“ – im Eingang Dateifelder nur bei Zustellung per E-Mail (wie Tables::validate)
- `static input(array $t, array $post): array` – Eingabe der Seitenleiste ($post: fields[i][id|label|name|type|required|width|help|options|accept|max_mb], form[…]) über die
- `static save(array $t, array $post): array` – Prüfen und speichern (gleicher Weg wie der Tabellen-Designer).
- `static selftest(bool $readOnly = false): array` – Rundlauf und Seitenleiste: validate(toInput($t), $t) ergibt für jede Tabelle dieselbe Definition; vorübergehende Tabellen
- `static types(array $t, string $current = ''): array` – Feldtypen, die die Seitenleiste anbietet (Formular-Felder; Gruppen nur, wenn schon vorhanden – Unterfelder in der Verwaltung)

### `Shared` (class, `app/Data/Shared.php`)

Geteilte Datentabellen („Geteilte Daten“, Verbund-Tabellen): eine Tabelle, mehrere Websites dieser Installation.

Konstanten: `ID_BASE`, `COLUMNS`, `STATES`, `SOURCES`, `CONFIG`, `CANONICAL`, `MEDIA_TYPES`

- `static addMember(string $key): void` – Diese Website tritt bei (nur auf Einladung des Eigentümers): Mitglied werden, Einladung erledigt. Ohne Konfliktprüfung –
- `static all(): array` – Alle geteilten Tabellen der Installation [key => meta]
- `static blockFields(bool $list = false): array` – Blockfeld „Quelle“ für Datenliste, Kalender und Nächste Termine – nur auf Websites, die an geteilten Tabellen teilnehmen.
- `static canAdmin(string $key): bool` – Diese geteilte Tabelle verwalten (Mitglieder, Schalter, Regeln): Eigentümer-Website oder Integrator
- `static canManage(): bool` – Geteilte Tabellen anlegen und Mitglieder zuordnen (Recht „data.shared.manage“ oder Integrator)
- `static canSchema(array $t): bool` – Felder ändern: Eigentümer-Website mit „Tabellen und Felder ändern“ oder Integrator
- `static cleanWhere(array $t, array $rows): array` – Filterbedingungen [[feld, op, wert], …] prüfen (nur echte Felder, bekannte Operatoren)
- `static cleanupPools(bool $dry = false, ?array $only = null): array` – Leere, automatisch angelegte Pools geteilter Tabellen ohne Bild-/Dateifelder entfernen (läuft mit `migrate`, idempotent).
- `static clearCaches(string $key): void` – Seiten-Caches aller beteiligten Websites leeren
- `static conflicts(string $key, array $sites, string $route = ''): array` – Prüft, ob Kurzname oder Adresse (URL-Basis) auf einer der Websites schon vergeben sind. @return list<string> Meldungen
- `static conn(string $key): Doctrine\DBAL\Connection` – Doctrine-Verbindung (Schema-Abgleich der Eintragstabelle)
- `static create(array $def, array $members, array $flags = []): string` – Neue geteilte Tabelle anlegen. $def aus Tables::validate (mit _shared_owner), Eigentümer = diese Website.
- `static db(string $key): Database`
- `static dir(?string $key = null): string`
- `static ensurePool(string $key, bool $force = false): ?string` – Pool „data-{key}“ bei Bedarf anlegen bzw. alle Beteiligten eintragen. Angelegt wird er nur, wenn die Tabelle Bild-/Dateifelder hat
- `static flush(): void`
- `static forSite(?string $site = null): array` – Geteilte Tabellen, an denen eine Website teilnimmt [key => meta]
- `static fromPool(array $t, mixed $poolId): ?int` – Pool-ID → Medien-ID dieser Website (Verweis wird bei Bedarf angelegt)
- `static hasMediaFields(string $key): bool` – Hat die geteilte Tabelle Bild- oder Dateifelder? (aus dem Register, ohne Hydrieren)
- `static health(): array` – Prüfungen für `health` [Bezeichnung => ok]
- `static isForeign(array $t, array $e): bool` – Eintrag einer anderen Website?
- `static isInvited(string $key, ?string $site = null): bool` – Ist diese (oder eine andere) Website zum Beitreten eingeladen (und noch nicht beteiligt)?
- `static isOwner(array $t, ?string $site = null): bool`
- `static isShared(array $t): bool`
- `static leave(string $key, bool $keepForeign = true): array` – Mitglied verlässt die geteilte Tabelle: sie wird wieder eine eigene Tabelle dieser Website – mit den eigenen Einträgen und mit
- `static localConfig(string $key): array`
- `static meta(string $key): ?array`
- `static migrate(): array` – Datenbanken anlegen/abgleichen (migrate) – idempotent. @return list<string>
- `static originUrl(array $t, array $e): ?string` – Adresse des Eintrags auf seiner Ursprungs-Website (nur fremde Einträge, wenn dort Detailseiten existieren)
- `static participants(array $meta): array` – Eigentümer + Mitglieder
- `static picks(array $t, array $ids, ?string $site = null): array` – Auswahl dieser (oder einer anderen) Website für Einträge [id => state]
- `static poolKey(string $key): string`
- `static poolTable(string $poolKey): ?string` – Geteilte Tabelle, zu der ein Pool gehört (Schlüssel) – oder null (frei angelegter Pool). Neue Pools tragen die Markierung
- `static row(string $key): ?array` – Registereintrag der Tabelle (roh)
- `static saveLocal(string $key, array $values): void`
- `static setPick(array $t, array $ids, ?string $state, string $by = ''): int` – Auswahl setzen (null = zurücksetzen). Regeln: übernehmen/ablehnen nur der Eigentümer für Einträge der Mitglieder,
- `static shareLocal(string $handle, array $members, bool $merge = false, array $flags = [], ?array &$map = null, array $skip = []): array` – Lokale Tabelle dieser Website in eine geteilte Tabelle verschieben (diese Website wird Eigentümer).
- `static siteDb(string $site): ?Database` – Datenbank einer anderen Website (nur lesend für Prüfungen; die eigene: app()->db)
- `static siteInfo(string $site, ?string $key = null): array` – Name und Adresse einer Website (aus dem Register, sonst aus ihrer Konfiguration)
- `static sourceSql(array $t, string $source, array &$params, callable $cond): string` – WHERE-Teil für eine Quelle (Parameter werden in derselben Reihenfolge angehängt).
- `static tableId(string $key): int`
- `static toPool(array $t, mixed $localId): ?int` – Medien-ID dieser Website → ID im Pool der Tabelle. Eigene Dateien wandern in den Pool (Media::shareToPool – die Website
- `static touch(array $t): void` – Angaben dieser Website (Name, Adresse, Detailseiten vorhanden?) im Register aktualisieren
- `static unshare(string $key, bool $all = false): array` – Freigabe beenden (nur Eigentümer): Tabelle wird wieder eine lokale Tabelle dieser Website mit den eigenen Einträgen
- `static update(string $key, array $in): void` – Einstellungen ändern: label, members, members_see_members, auto (enabled, sites, where). Neue Mitglieder werden auf Konflikte geprüft.
- `static validKey(string $key): bool`
- `static visibleAll(array $t, array $e): bool` – Darf diese Website den (fremden) Eintrag überhaupt sehen (Quelle „all“)?

### `SharedPoolsTest` (class, `app/Data/SharedPoolsTest.php`)

Selbsttest (Teil von `data:selftest`): Medien-Pools geteilter Tabellen („data-{key}“) entstehen nur bei Bedarf und Shared::cleanupPools() entfernt nur leere, automatisch angelegte Pools von Tabellen ohne Bild-/Dateifelder. Legt vorübergehende geteilte Tabellen (nur diese Website beteiligt) und Pools an und entfernt sie danach vollständig.

- `static run(): array`

### **API** `Tables` (class, `app/Data/Tables.php`)

Datentabellen (ähnlich REDAXO YForm / Webflow CMS Collections) – ohne Programmieren anlegbar.

Konstanten: `TYPES`, `LAYOUT`, `SECTION_STYLES`, `GROUP_TYPES`, `SYSTEM`

- `static all(): array` – Alle Tabellen: eigene (data_tables) und geteilte, an denen die Website teilnimmt (Core\Data\Shared)
- `static allFields(array $t): array` – Alle Elemente der Definition in ihrer Reihenfolge – Datenfelder und Gestaltungs-Elemente (Abschnitte, Freitext).
- `static byRoute(string $route): ?array` – Tabelle anhand der URL-Basis (für Detailseiten-Routing)
- `static conn(array $table): Doctrine\DBAL\Connection` – Doctrine-Verbindung für den Schema-Abgleich der Tabelle
- `static content(): array` – Inhaltstabellen (ohne Eingangs-Tabellen) – für alles, was Inhalte ausgibt: Blöcke, Sitemap, Suche, Kalender, DAV, Feldbindung
- `static create(array $def): int` – Neue Tabelle anlegen (Definition + echte Datenbanktabelle)
- `static dataFields(array $fields): array` – Nur die Datenfelder einer Feldliste (ohne Abschnitte und Freitext)
- `static db(array $table): Database` – Datenbank der Einträge: Website oder – bei geteilten Tabellen – storage/shared/{key}/share.sqlite
- `static delete(array $table): void`
- `static droppedFields(array $table, array $def): array` – Welche Spalten fielen beim Speichern weg? (Warnung vor Datenverlust)
- `static field(array $table, string $name): ?array`
- `static fieldInput(array $f): array` – Ein gespeichertes Feld im Eingabeformat von validate(): Auswahlmöglichkeiten als Zeilen „kurzname=Text“
- `static find(string|int $ref): ?array`
- `static findContent(string|int $ref): ?array` – Wie find(), aber nie eine Eingangs-Tabelle
- `static flush(): void`
- `static groupDef(array $f, array $def): array` – Wiederholbare Gruppe bereinigen: fields (Unterfelder: name, label, type, required, width, options, labels, options_i18n),
- `static imageField(array $table): string`
- `static isInbox(array $table): bool` – Eingangs-Tabelle (settings.kind = inbox, verschlüsselte Anfragen – siehe Core\Data\Inbox)?
- `static isLayout(array $f): bool` – Gestaltungs-Element (Abschnitt, Freitext) statt Datenfeld?
- `static isShared(array $table): bool`
- `static label(array $f, ?string $lang = null): string` – Beschriftung eines Feldes in der Sprache der Seite
- `static layoutDef(array $f, string $type, string $name, string $label): array` – Gestaltungs-Element bereinigen. section: label = Überschrift, help = Beschreibung darunter (optional), style heading|fieldset.
- `static normName(string $s): string`
- `static optionLabel(array $f, string $key, ?string $lang = null): string` – Text einer Auswahlmöglichkeit in der Sprache der Seite
- `static pivot(array $table, string $field): string` – Name der n:m-Verknüpfungstabelle eines Feldes
- `static referencing(array $target): array` – Felder anderer Tabellen, die auf diese Tabelle verweisen: [[table, field], …]
- `static setDetailPage(array $table, ?int $pageId): void` – Detailseiten-Vorlage festlegen (geteilte Tabellen: nur für diese Website)
- `static shared(): array` – Geteilte Tabellen, an denen diese Website teilnimmt
- `static sharedTable(string $key): ?array` – Geteilte Tabelle mit den Angaben dieser Website (Detailseite) – unabhängig davon, ob sie teilnimmt
- `static sync(array $table, array $renames = []): void` – Datenbanktabelle an die Definition anpassen
- `static toInput(array $t): array` – Gespeicherte Definition im Eingabeformat von validate() – für Teiländerungen, die alles Übrige unverändert lassen
- `static typeGroups(array $types): array` – Feldtypen nach Verwendung – für Palette und Typauswahl im Tabellen-Designer:
- `static update(array $table, array $def): void` – Definition ändern; umbenannte Felder (gleiche Feld-ID) werden als RENAME COLUMN übernommen
- `static usesType(string $type): bool` – Kommt der Feldtyp in einer Tabelle dieser Website vor? (z. B. bedingtes CSS für „Medien + Text“)
- `static validate(array $in, ?array $existing = null): array` – Definition prüfen und bereinigen.

### `Templates` (class, `app/Data/Templates.php`)

Tabellen aus Vorlagen über die Kommandozeile (php bin/console data:template …) und Übernahme einer alten Stellenseite in die Stellen-Tabelle (jobs:from-page). Beides wiederholbar: Vorhandenes bleibt, fehlende Teile werden ergänzt. Die Vorlagen selbst stehen in DataController::presets() (wie „Daten → Neue Tabelle aus Vorlage“).

- `static fromPage(string $ref, array $o, callable $log, bool $dry = false): bool` – Eine Seite mit einer Stellenanzeige (Fließtext-Blöcke, optional Formular-Block) in einen Eintrag der Stellen-Tabelle umwandeln.
- `static install(string $preset, array $o, callable $log, bool $dry = false): array` – Tabelle aus einer Vorlage anlegen bzw. ergänzen. $o: handle, name, slug (Adresse der Detailseiten, '' = keine),
- `static makeListPage(array $t): ?int` – Einfache Übersichtsseite für andere Vorlagen: Datenliste mit Titel, Bild, Beschreibung

### `Wizard` (class, `app/Data/Wizard.php`)

Assistent „Neue Tabelle“ (/admin/data/new): Zweck → Vorlage → Grundeinstellungen → Einsetzen. Hier die Logik ohne HTTP (der Zustand liegt während des Assistenten in der Sitzung, DataController::wizard*): Zustand aus einer Vorlage, Eingaben der Grundeinstellungen übernehmen, daraus die Definition für Tables::validate bauen und anlegen.

Konstanten: `STEPS`

- `static apply(array $st, array $in, string $go = ''): array` – Eingaben der Grundeinstellungen übernehmen: name, singular, f[i][on|label|type|required|options], new_label/new_type,
- `static check(array $st): array` – Prüfungen des Assistenten vor dem Anlegen (zusätzlich zu Tables::validate) – Fehler als [Schlüssel => Text]
- `static create(array $st): array` – Tabelle anlegen: prüfen (check + Tables::validate), anlegen, Stellen-Bewerbungsformular verknüpfen, Detailseiten-Vorlage.
- `static def(array $st): array` – Eingabe für Tables::validate aus dem Zustand (Vorlage als Grundlage, darüber die Grundeinstellungen)
- `static fieldRows(array $fields, array $st): array` – Felder einer Vorlage/KI → Zeilen des Assistenten (on = übernehmen; Typen, die die Art nicht kennt, werden ersetzt bzw. abgewählt)
- `static freeHandle(string $name): string` – Freier Kurzname bzw. freie Adresse (Zusatz -2, -3 …), damit der Assistent nicht an Dopplungen scheitert
- `static freeRoute(string $route): string`
- `static fromAi(array $def, string $text): array` – Zustand aus dem Vorschlag von KLXM AI (AI\Generator::proposeTable → def)
- `static guess(string $text): string` – Zweck aus einer Beschreibung erraten (KI-Schritt) – nur Wortstämme, keine KI; nicht verfügbare Zwecke → Inhalte
- `static mailMode(array $st): string` – Zustellung eines Eingangs aus dem Zustand: Zweck „mail“ → nur E-Mail; sonst die gewählte (Vorlage bzw. Grundeinstellungen) –
- `static modes(array $st): array` – Wählbare Zustellungen im Assistenten (Eingang, nicht „nur E-Mail“)
- `static selftest(): array` – Selbsttest (data:selftest): je verfügbarem Zweck eine Tabelle über den Assistenten anlegen und die wichtigsten Einstellungen
- `static start(string $purpose, string $preset): array` – Zustand für Zweck + Vorlage (Schritt 2 → 3); Vorlage '' = leer beginnen
- `static stepLabels(): array` – Schrittnamen für die Fortschrittsanzeige
- `static types(array $st): array` – Erlaubte Feldtypen je Zweck/Art (Eingang: Formular-Typen, Dateien nur bei Zustellung per E-Mail)

## Core\Db

### **API** `Table` (class, `app/Db/Table.php`)

Tabelle deklarativ beschreiben und angleichen (nach rex_sql_table) – für Erweiterungen und neue Tabellen des Cores. SQLite und MySQL/MariaDB über Doctrine DBAL (gleiche PDO-Verbindung wie Core\Database, also auch in Transaktionen).

- `column(string $name, string $type, array $opts = []): self`
- `static conn(Database $db): Doctrine\DBAL\Connection` – Doctrine-Verbindung auf derselben PDO-Verbindung wie Core\Database (gleiche Transaktion, keine zweite Sperre bei SQLite)
- `dropColumn(string $name): self`
- `dropIndex(string $name): self`
- `ensure(?Database $db = null): string` – Tabelle anlegen bzw. angleichen. @return string 'created' | 'altered' | 'unchanged'
- `static exists(string $name, ?Database $db = null): bool` – Gibt es die Tabelle?
- `fingerprint(): string` – Beschreibung als Zeichenkette (für den Fingerabdruck in Extension::schema)
- `foreignKey(array|string $cols, string $table, array|string $ref = 'id', ?string $onDelete = null): self` – Fremdschlüssel; $onDelete: CASCADE | SET NULL | RESTRICT | null (Standard der Datenbank)
- `id(string $name = 'id'): self` – Primärschlüssel „id“ (INTEGER, Autoincrement)
- `index(array|string $cols, ?string $name = null): self`
- `static named(string $name): self`
- `primary(array $cols): self` – Primärschlüssel aus mehreren Spalten (nur beim Anlegen)
- `renameColumn(string $from, string $to): self` – Spalte umbenennen (nur wenn die alte da und die neue noch nicht da ist – wiederholbar)
- `unique(array|string $cols, ?string $name = null): self`

### `TableSelfTest` (class, `app/Db/TableSelfTest.php`)

php bin/console db:selftest – Core\Db\Table in einer Wegwerf-Datenbank (SQLite, storage/cache): Anlegen, Idempotenz, Ergänzen, Umbenennen (Daten bleiben), Indizes ändern/entfernen, Fremdschlüssel, Spalte entfernen, NOT NULL ohne Vorgabe, Transaktion.

- `static run(): array`

## Core\Events

### **API** `EntryDeleted` (class, `app/Events/EntryDeleted.php`)

entry.deleted: Eintrag gelöscht (Entries::delete). $entry = Stand vor dem Löschen (nur geladen, wenn jemand zuhört), $id immer. Altform: fn(array $table, int $id)

Konstanten: `NAME`

- `__construct(array $definition, int $id, ?array $entry = null, ?int $userId = null)`
- `legacyArgs(): array`

### **API** `EntryEvent` (class, `app/Events/EntryEvent.php`)

Ereignis an einem Eintrag einer Datentabelle: $table = Kurzname (handle), $definition = Tabellen-Definition (Core\Data\Tables), $entry = Datensatz nach der Änderung (EntryDeleted: Stand vor dem Löschen, falls bekannt).

- `__construct(array $definition, ?array $entry, ?int $id = null, ?int $userId = null)`
- `legacyArgs(): array`

### **API** `EntryPublished` (class, `app/Events/EntryPublished.php`)

entry.published: Status wechselt auf veröffentlicht (Entries::save, setStatus – nur bei echter Änderung). Altform: fn(array $table, array $entry)

Konstanten: `NAME`

- `__construct(array $definition, array $entry, ?int $userId = null)`

### **API** `EntrySaved` (class, `app/Events/EntrySaved.php`)

entry.saved: Eintrag angelegt oder geändert (Entries::save – Verwaltung, Website, API/MCP, Quellen-Abgleich, Formulare). $created = neu angelegt, $old = Stand davor (null bei neuen). $state: 'live', wenn der Eintrag veröffentlicht ist. Altform: fn(array $table, array $entry, bool $created, ?array $old)

Konstanten: `NAME`

- `__construct(array $definition, array $entry, ?array $old = null, ?int $userId = null)`
- `legacyArgs(): array`

### **API** `EntryUnpublished` (class, `app/Events/EntryUnpublished.php`)

entry.unpublished: Status wechselt auf Entwurf (nur bei echter Änderung). Altform: fn(array $table, array $entry)

Konstanten: `NAME`

- `__construct(array $definition, array $entry, ?int $userId = null)`

### **API** `Event` (class, `app/Events/Event.php`)

Typisiertes Ereignis des Cores für Erweiterungen (Extension::on, Extensions::emit) – unveränderlich.

Konstanten: `NAME`, `ALL`

- `__construct(?int $userId = null)`
- `static classFor(string $name): ?string` – Klasse zu einem Namen ('page.saved' → PageSaved::class) bzw. null
- `legacyArgs(): array` – Argumente der Altform (Listener mit dem Namen statt der Klasse, z. B. fn(array $page, ?int $userId))
- `name(): string`
- `offsetExists(mixed $offset): bool`
- `offsetGet(mixed $offset): mixed`
- `offsetSet(mixed $offset, mixed $value): void`
- `offsetUnset(mixed $offset): void`

### **API** `FormSubmitted` (class, `app/Events/FormSubmitted.php`)

form.submitted: Besucher hat ein Formular abgeschickt, nach Prüfung, Spamschutz und Speichern (DataForms::submit). $table = Kurzname, $values = geprüfte Werte (Klartext – auch bei verschlüsselten Anfragen; nicht protokollieren), $entryId (Datentabelle) bzw. $ref (Anfrage-Eingang, verschlüsselt), $inbox = Eingangs-Tabelle.

Konstanten: `NAME`

- `__construct(array $definition, array $values, ?int $entryId = null, ?string $ref = null, bool $inbox = false)`

### **API** `InfoEvent` (class, `app/Events/InfoEvent.php`)

Ereignis ohne Seite/Eintrag als Gegenstand (Konten, Einstellungen, Formulare, Tabellen): Angaben als readonly-Eigenschaften der Unterklasse. Altform (Listener mit dem Namen ohne Typ) bekommt das Ereignis-Objekt selbst.

- `legacyArgs(): array`

### **API** `LoginFailed` (class, `app/Events/LoginFailed.php`)

user.login_failed: Anmeldung abgelehnt (Auth::attempt). $reason: 'password' | 'disabled' | 'limited' (zu viele Versuche). $email so eingegeben (kann es nicht geben) – nur für Sicherheits-Auswertung, nicht anzeigen.

Konstanten: `NAME`

- `__construct(string $email, string $ip, string $reason)`

### **API** `PageCreated` (class, `app/Events/PageCreated.php`)

page.created: Seite angelegt (Pages::create – Verwaltung, API/MCP, KI, Vorlagen, Import, Übersetzung). Stand: Entwurf.

Konstanten: `NAME`


### **API** `PageDeleted` (class, `app/Events/PageDeleted.php`)

page.deleted: Seite gelöscht (Verwaltung, API/MCP); $page = Stand vor dem Löschen. Altform: fn(array $page)

Konstanten: `NAME`


### **API** `PageDiscarded` (class, `app/Events/PageDiscarded.php`)

page.discarded: Entwurf verworfen, Arbeitsstand = veröffentlichte Fassung (Pages::discardDraft). Altform: fn(array $page)

Konstanten: `NAME`


### **API** `PageEvent` (class, `app/Events/PageEvent.php`)

Ereignis an einer Seite: $page = Datensatz (Stand nach der Änderung, bei PageDeleted vor dem Löschen)

- `__construct(array $page, ?int $userId = null)`
- `static langOf(?string $code): string`
- `legacyArgs(): array`

### **API** `PageMoved` (class, `app/Events/PageMoved.php`)

page.moved: übergeordnete Seite oder Reihenfolge geändert (Seitenbaum, Seiteneinstellungen, API/MCP). $page = neuer Stand.

Konstanten: `NAME`

- `__construct(array $page, ?int $oldParentId, ?int $userId = null)`
- `reparented(): bool` – Hat sich die übergeordnete Seite geändert (sonst nur die Reihenfolge)?

### **API** `PagePublished` (class, `app/Events/PagePublished.php`)

page.published: veröffentlicht bzw. wieder online (Pages::publish). Altform: fn(array $page)

Konstanten: `NAME`


### **API** `PageRestored` (class, `app/Events/PageRestored.php`)

page.restored: ältere Version als Entwurf wiederhergestellt (Verwaltung, API/MCP). Danach folgt wie immer page.saved.

Konstanten: `NAME`

- `__construct(array $page, int $revisionId, ?int $userId = null)`

### **API** `PageSaved` (class, `app/Events/PageSaved.php`)

page.saved: Entwurf gespeichert (Pages::saveDraft). Altform: fn(array $page, ?int $userId)

Konstanten: `NAME`

- `legacyArgs(): array`

### **API** `PageTranslated` (class, `app/Events/PageTranslated.php`)

page.translated: Übersetzung angelegt (Pages::translate). $page = neue Sprachfassung, $sourceId = Ausgangsseite.

Konstanten: `NAME`

- `__construct(array $page, int $sourceId, ?int $userId = null)`

### **API** `PageUnpublished` (class, `app/Events/PageUnpublished.php`)

page.unpublished: offline genommen – nur bei echter Änderung (Pages::unpublish). Altform: fn(array $page)

Konstanten: `NAME`


### **API** `SettingsSaved` (class, `app/Events/SettingsSaved.php`)

settings.saved: Einstellungen über die Verwaltung oder API/MCP gespeichert. $area: 'settings' (Website), 'system' (Grundeinstellungen), 'design'. $keys = geänderte Schlüssel (ohne Werte – Geheimnisse bleiben in den Einstellungen).

Konstanten: `NAME`

- `__construct(string $area, array $keys)`

### **API** `TableSchemaChanged` (class, `app/Events/TableSchemaChanged.php`)

table.schema_changed: Datentabelle angelegt, Felder geändert oder gelöscht (Core\Data\Tables). $action: 'created' | 'updated' | 'deleted'; $added/$dropped = Feldnamen (bei updated).

Konstanten: `NAME`

- `__construct(array $definition, string $action, array $added = [], array $dropped = [])`

### **API** `UserCreated` (class, `app/Events/UserCreated.php`)

user.created: Konto der Website angelegt (Verwaltung, Einrichtung, Einladung angenommen). $via: 'admin' | 'setup' | 'invite'.

Konstanten: `NAME`

- `__construct(int $id, string $email, string $role, string $via = 'admin')`

### **API** `UserDeleted` (class, `app/Events/UserDeleted.php`)

user.deleted: Konto gelöscht (Verwaltung → Benutzer) – Erweiterungen entfernen ihre Zugänge des Kontos (z. B. App-Passwörter).

Konstanten: `NAME`

- `__construct(int $id)`

### **API** `UserLoggedIn` (class, `app/Events/UserLoggedIn.php`)

user.login: Anmeldung in der Verwaltung erfolgreich (Auth::login – Passwort, Zwei-Faktor, Passkey, Einladung, Netzwerk).

Konstanten: `NAME`

- `__construct(int $id, string $email, string $role, string $ip = '')`

### **API** `UserLoggedOut` (class, `app/Events/UserLoggedOut.php`)

user.logout: Abmeldung (Auth::logout), vor dem Beenden der Sitzung.

Konstanten: `NAME`

- `__construct(int $id)`

### **API** `UserRoleChanged` (class, `app/Events/UserRoleChanged.php`)

user.role_changed: Rolle eines Kontos geändert (Verwaltung → Rollen).

Konstanten: `NAME`

- `__construct(int $id, string $role, string $oldRole)`

## Core\Glossary

### `Annotator` (class, `app/Glossary/Annotator.php`)

Markiert Glossar-Begriffe im fertigen HTML (serverseitig, ohne DOM-Bibliothek, vor dem Seiten-Cache).

- `__construct(array $terms, array $o = [])` – $o: mode (page|section), headings (0–6: h1…hN überspringen, Standard 3), exclude (Liste von Begriffs-IDs),
- `annotate(string $html): string` – Ganze Seite oder Ausschnitt markieren
- `static caseSensitive(string $v): bool` – Abkürzung bzw. Markenschreibweise? Ein Wort mit mindestens zwei Großbuchstaben (SPF, IPv6, DNSSEC, MTA-STS) oder einem
- `static english(string $lang): bool` – Sprache mit englischen statt deutschen Wortendungen? (en, en-gb …)
- `markup(array $t, string $match): string` – Markup eines Treffers (Text des Treffers bleibt wie im Original – Entities inklusive)
- `static pattern(array $terms, string $lang = 'de'): ?string` – Regulärer Ausdruck für alle Varianten (längste zuerst, Begriffs-ID als MARK) – null ohne Begriffe. $lang: Wortendungen
- `static variants(array $t): array` – Begriff + Varianten (ohne Leeres und Doppeltes)

### `Console` (class, `app/Glossary/Console.php`)

Kommandozeile des Glossars (bin/console, je Website mit --site=…): glossary:install [--publish] [--enable] [--dry-run]   Tabelle, Detailseiten und Übersicht /glossar anlegen (wiederholbar) glossary:import <datei.csv> [--overwrite] [--dry-run]  Begriffe aus CSV übernehmen (neue als Entwurf)

- `static run(string $cmd, array $args): int`

### `Glossary` (class, `app/Glossary/Glossary.php`)

Glossar (Funktion „glossary“, Standard aus): Fachbegriffe einer Website erklären.

Konstanten: `FEATURE`, `SET`, `HANDLE`, `SHORT_MAX`, `JSON_PATH`, `MODES`, `DEFAULTS`, `CSV_COLUMNS`

- `static aiAvailable(): bool` – Kann die angemeldete Person Erklärungen von der KI vorschlagen lassen? (Funktion „ai“ + KI eingerichtet)
- `static assets(string $html, array $live = []): string` – Stylesheet (Kern + optional css/glossary.css des Kits) und Skript einbinden
- `static checks(?array $terms = null): array` – Hinweise zur Pflege: doppelte Varianten (nur einer wird markiert), Überschneidungen (längere Variante gewinnt),
- `static definition(): array` – Definition der Tabelle im Eingabeformat von Tables::validate()
- `static enabled(): bool`
- `static excluded(string $path, string $list): bool` – Pfad ausgenommen? Zeilen „/pfad“ (genau) oder „/pfad/*“ (alles darunter)
- `static exportCsv(): string`
- `static fieldsOk(array $t): bool`
- `static flush(): void`
- `static importCsv(string $text, bool $dry = false, bool $overwrite = false): array` – CSV übernehmen (Trennzeichen ; oder , – erste Zeile mit Spaltennamen wie im Export). Vorhandene Begriffe (gleicher
- `static install(array $o = []): array` – Glossar einrichten (wiederholbar): Tabelle, Detailseiten-Vorlage (/glossar/{slug}) und Übersichtsseite /glossar.
- `static json(): array` – /_glossary.json: veröffentlichte Begriffe für dynamische Bereiche (ohne Entwürfe, öffentlich zwischenspeicherbar)
- `static jsonResponse(Http\Request $r): Http\Response` – Antwort für /_glossary.json (?lang=…) – 404 ohne Funktion bzw. Glossar
- `static labels(): array` – Feste Texte der Hinweisfenster (Sprache der Seite)
- `static linkHost(string $u): string` – Anzeigename einer Quelle: Host ohne „www.“ (opensource.org)
- `static liveIn(string $html, string $live): bool` – Kommt ein dynamischer Bereich auf der Seite vor? (einfacher Abgleich von Klasse, ID, Attribut bzw. Element)
- `static occurrences(bool $refresh = false): array` – „Wo kommt der Begriff vor?“ – Texte des Suchindex (Seiten und Einträge anderer Tabellen, Core\Search\Documents) mit den
- `static overviewUrl(): ?string` – Adresse der Übersicht (Seite mit dem Block „Glossar“) – sonst null
- `static page(string $html, int $status = 200): string` – Ganze Seite: Begriffe markieren und – nur wenn etwas markiert wurde oder dynamische Bereiche da sind – Stylesheet und Skript
- `static safeLink(string $u): bool` – Quelle eines Begriffs als Link in der Bubble: nur http(s)
- `static saveSettings(array $in): array` – Einstellungen aus dem Formular prüfen und speichern
- `static selectors(string $live): array` – Selektoren der dynamischen Bereiche (immer auch [data-glossary="live"])
- `static settings(): array`
- `static short(string $s): string` – Kurz-Erklärung als Klartext, höchstens 240 Zeichen (an einer Wortgrenze gekürzt)
- `static splitVariants(string $s): array` – Varianten: eine je Zeile, oder durch Komma bzw. Semikolon getrennt
- `static stylesheets(bool $list = false): array` – Stylesheets: Kern (resources/css/glossary.css – Begriffe im Text; mit $list glossary-list.css – Block „Glossar“), dazu
- `static suggest(string $term, array $variants = [], string $context = ''): string` – Kurz-Erklärung vorschlagen (Klartext ≤ 240 Zeichen, „Sie“-Form) – wird immer als Entwurf gespeichert und geprüft
- `static table(): ?array` – Glossar-Tabelle dieser Website (null = noch nicht eingerichtet oder ohne die nötigen Felder)
- `static term(array $t, array $e): array` – Ein Eintrag als Begriff
- `static terms(bool $drafts = false, ?string $lang = null): array` – Begriffe für Markierung, Übersicht und JSON: id, key (Slug), term, short, long (HTML), category, link, url (Detailseite),

### `QuickTool` (class, `app/Glossary/QuickTool.php`)

Quick-Glossar: Werkzeug beim Bearbeiten auf der Website (Core\FrontendTools) – zugleich das Referenzbeispiel für $x->frontendTool([...]) einer Erweiterung. Knopf „Glossar“ in der Werkzeugleiste (⌥G), Seitenleiste in der Shadow-DOM-Ebene, Modul resources/js/quick-glossary.mjs (erst beim ersten Öffnen geladen).

Konstanten: `LIMIT`, `HTML_MAX`

- `static create(array $in): array` – Neuen Begriff anlegen. $in: term, short, long (Klartext, Absätze durch Leerzeile), variants (Komma), publish (bool), lang.
- `static definition(): array` – Angaben für Core\FrontendTools – genau so, wie eine Erweiterung sie an $x->frontendTool() übergibt
- `static hits(array $terms, string $html, array $settings, string $lang = 'de', int $self = 0): array` – Treffer der Markierung in einem HTML-Ausschnitt: [Begriffs-ID => Text wie im Inhalt]. Bearbeitbare Bereiche (contenteditable
- `static item(array $t, array $x): array` – Begriff für das Werkzeug: Verweis entry:glossar:{id} (wie die Linkauswahl) + Adresse der Detailseite
- `static lang(string $l): string` – Sprache aus der Anfrage (gültig, sonst Standardsprache)
- `static onPage(string $html, string $lang, string $path = '/', int $self = 0): array` – „Auf dieser Seite“: Begriffe, die die automatische Markierung im übergebenen HTML (Inhalt der Seite im Editor, ohne
- `static search(string $q, string $lang, int $limit = self::LIMIT): array` – Suche: Begriffe (auch Entwürfe) einer Sprache; Treffer am Anfang des Begriffs zuerst, dann im Begriff, in Varianten,
- `static selftestDb(callable $eq): bool` – Selbsttest der Endpunkte mit Datenbank (glossary:selftest): nur wenn das Glossar eingerichtet ist; alles in einer Transaktion,

### `SelfTest` (class, `app/Glossary/SelfTest.php`)

php bin/console glossary:selftest [--bench] – Markierung ohne Datenbank prüfen: Wortgrenzen, Groß-/Kleinschreibung, Umlaute, Abkürzungen, längste Variante, erstes Vorkommen (Seite/Abschnitt), Ausnahmen, Escaping, keine Doppel-Markierung; geteiltes Glossar: Vergleichsform und Doppel-Erkennung (Sharing). Ende-zu-Ende mit zwei Websites: glossary:sharetest (ShareTest).

- `static run(bool $bench = false): int`
- `static terms(): array`

### `ShareTest` (class, `app/Glossary/ShareTest.php`)

php bin/console glossary:sharetest --sandbox – Ende-zu-Ende-Test des geteilten Glossars mit zwei Websites (A = aufrufende Website, B = nächste Website der Installation). Jeder Schritt läuft als eigener Prozess mit --site=… (eine Website je Prozess), der Ablauf prüft die Ergebnisse: Teilen ein/aus, Beitreten mit Doppeln (existing/mine), gemeinsames Lesen, Ausblenden je Website,

- `static run(bool $sandbox, ?string $step = null, ?string $state = null): int`

### `Sharing` (class, `app/Glossary/Sharing.php`)

Geteiltes Glossar: ein Glossar für mehrere Websites dieser Installation – gebaut auf den geteilten Datentabellen (Core\Data\Shared, Schlüssel = Kurzname „glossar“). Kein eigener Mechanismus: Speicher storage/shared/glossar/, Herkunft je Begriff (origin_site), Auswahl je Website (share_picks: ausblenden), Detailseiten jeder Website unter /glossar/{slug}.

Konstanten: `KEY`, `CHOICES`

- `static available(): bool` – Nur sinnvoll mit mehreren Websites in der Installation
- `static canManage(): bool` – Darf die angemeldete Person das Teilen des Glossars einrichten bzw. beitreten/verlassen?
- `static duplicates(array $mine, array $theirs): array` – Doppelte Begriffe: je Begriff aus $mine die Begriffe aus $theirs derselben Sprache mit gleicher Vergleichsform (Begriff oder
- `static hidden(): array` – Ausgeblendete fremde Begriffe dieser Website
- `static hide(int $id, bool $hide, string $by = ''): int` – Begriff auf dieser Website aus- bzw. wieder einblenden (nur fremde Begriffe)
- `static invite(array $sites): void` – Einladungen setzen (nur Eigentümer)
- `static join(array $choices = [], string $by = ''): array` – Beitreten (nur auf Einladung). $choices: [lokale Begriffs-ID => existing|mine|both] für die Doppel aus plan() – ohne Angabe
- `static keys(array $t): array` – Vergleichsformen eines Begriffs (Begriff + Varianten)
- `static leave(bool $keepForeign = true): array` – Verlassen bzw. (Eigentümer, ohne Mitglieder) Teilen beenden. $keepForeign: Kopien der zuletzt gezeigten fremden Begriffe behalten
- `static localTable(): ?array` – Lokale (nicht geteilte) Glossar-Tabelle dieser Website
- `static localTerms(): array` – Begriffe der lokalen Tabelle (alle, auch Entwürfe)
- `static norm(string $s): string` – Vergleichsform: ohne Groß-/Kleinschreibung, Akzente/Umlaute (ä → a, ß → ss), Leer- und Satzzeichen („E-Mail“ = „email“)
- `static plan(): array` – Vorschau des Beitretens: eigene Begriffe, Begriffe der anderen, Doppel
- `static rewriteRefs(array $map): int` – Verweise entry:glossar:{alt} → entry:glossar:{neu} umschreiben: Seiten (Arbeitsstand + veröffentlicht), Versionen und
- `static share(array $invite = []): array` – Glossar dieser Website teilen (diese Website wird Eigentümerin) und Websites einladen. @return list<string>
- `static sharedTerms(): array` – Veröffentlichte Begriffe der übrigen Websites im geteilten Glossar (direkt aus dem Speicher – auch vor dem Beitreten)
- `static status(): array` – Zustand dieser Website: role = local (nicht geteilt) | owner | member | invited (eingeladen, noch nicht beigetreten) |

## Core\Http

### `Fetch` (class, `app/Http/Fetch.php`)

Serverseitiger HTTP-Abruf frei einstellbarer Adressen – mit SSRF-Schutz. Einziger Weg des Kerns für solche Abrufe: externe Quellen (Feeds, APIs, OpenImmo, Bilder), Prüfung eigener Domains und Landingpages, Schriften für PDFs.

Konstanten: `PORTS`, `TYPES`

- `static allowPrivate(string $host, int $port): bool` – Nur Entwicklung: Host:Port aus config 'sources_allow_private' (bei 'environment' => 'development')
- `static check(string $url): array` – Adresse prüfen und auflösen. @return array{0: string, 1: string, 2: int, 3: string} [scheme, host, port, ip]
- `static get(string $url, array $o = []): array` – GET mit Schutzmaßnahmen.
- `static isPublicIp(string $ip): bool` – Öffentlich routbare Adresse? (IPv4 und IPv6, inkl. CGNAT, Benchmark-Netz, IPv4-gemappte IPv6-Adressen)
- `static selfCheck(string $host, string $query): ?array` – Erreicht eine eigene Domain diese Installation? Ruft {scheme}://{host}{base}/health?{query} ab (zuerst https, dann http) –
- `static typeAllowed(string $type, string $kind): bool`
- `static userAgent(): string`

### `FetchException` (class, `app/Http/FetchException.php`)

Fehler beim Abruf (Core\Http\Fetch) – Meldung ist für die Oberfläche geeignet (übersetzt).


### `HttpException` (class, `app/Http/HttpException.php`)

- `__construct(int $code, string $message = '')`

### **API** `Request` (class, `app/Http/Request.php`)

Konstanten: `PACKED`

- `__construct(string $method, string $path, array $query, array $post, array $files, array $server, bool $truncated = false)`
- `static fromGlobals(): self`
- `host(): string`
- `input(string $key, mixed $default = null): mixed`
- `ip(): string`
- `isAdminPath(): bool`
- `isPost(): bool`
- `isSecure(): bool`
- `str(string $key, string $default = ''): string`
- `static unpack(array $post): array` – Gebündeltes Formular (admin.js packt Formulare mit sehr vielen Feldern beim Absenden in ein Feld „_packed“, damit
- `wantsJson(): bool`
- `withPath(string $path): self` – Gleiche Anfrage mit anderem (internem) Pfad – Core\AdminPath bildet die eigene Verwaltungsadresse auf /admin ab
- `withQuery(array $query): self` – Gleiche Anfrage mit anderen GET-Parametern (Editor-Vorschau: Parameter der bearbeiteten Seite, z. B. ?seite=2)

### **API** `Response` (class, `app/Http/Response.php`)

- `__construct(string $body = '', int $status = 200, array $headers = [])`
- `static alreadySent(): self`
- `static fail(string $error, int $status = 422, array $extra = []): self` – Fehler als JSON in einheitlicher Form {ok: false, error: …} (+ weitere Felder)
- `header(string $name, string $value): self`
- `static json(mixed $data, int $status = 200): self`
- `static redirect(string $to, int $status = 303): self`
- `send(): void`

### **API** `Router` (class, `app/Http/Router.php`)

Routen. Der Core meldet seine Routen in app/routes.php an (Controller prüfen Anmeldung, Recht und CSRF selbst mit auth()).

- `__construct(?bool $strict = null)` – $strict: Routen ohne Recht als Fehler melden (null = nach Umgebung, siehe strict()) – für Selbsttests
- `add(string $method, string $pattern, callable|array $handler, array|string $opts = []): void` – $opts (nur für Routen von Erweiterungen unter /admin wirksam): Recht als Zeichenkette oder
- `any(string $pattern, callable|array $handler, array|string $opts = []): void` – Alle HTTP-Methoden (z. B. WebDAV: PROPFIND, REPORT, MKCALENDAR … für Erweiterungen wie „dav“)
- `delete(string $pattern, callable|array $handler, array|string $opts = []): void`
- `dispatch(Http\Request $request): Http\Response`
- `get(string $pattern, callable|array $handler, array|string $opts = []): void`
- `static isAdminPath(string $pattern): bool`
- `isPublicExtensionRoute(string $method, string $path): bool` – Bedient eine öffentliche Route einer Erweiterung ('public' => true) diesen Pfad? (Core\AdminPath: /admin direkt erreichbar)
- `match(string $method, string $path): ?string` – Muster der ersten Route, die Methode + Pfad bedient (ohne Aufruf), sonst null – z. B. für Favoriten
- `meta(?string $owner = null): array` – Angaben zu den Routen von Erweiterungen: [owner, method, pattern, perm, csrf, public, legacy, denied] – optional nur einer Herkunft
- `patch(string $pattern, callable|array $handler, array|string $opts = []): void`
- `post(string $pattern, callable|array $handler, array|string $opts = []): void`
- `put(string $pattern, callable|array $handler, array|string $opts = []): void`
- `scoped(string $owner, callable $register): void` – Routen einer Erweiterung anmelden: $register() läuft mit $owner als Herkunft (Schutz für /admin, Angaben für extensions:list)
- `static strict(): bool` – Entwicklung (environment development oder debug): Fehler früh und laut

### `Sse` (class, `app/Http/Sse.php`)

Server-Sent Events (text/event-stream) für kurzlebige Streams – z. B. eine KI-Antwort, die Wort für Wort kommt (Besucher-Chat Core\AI\VisitorChat, Redaktions-Assistent Core\AI\Assistant). Auch für andere Kanäle nutzbar (z. B. Chat zwischen Benutzern): ein Stream je Anfrage, danach schließen – der Browser verbindet sich bei Bedarf neu.

- `aborted(): bool` – Hat der Browser die Verbindung geschlossen?
- `comment(string $text = 'ping'): bool` – Kurzform von ping()
- `event(string $event, mixed $data = null, ?string $id = null): bool` – Kurzform von send()
- `ping(string $text = 'ping'): bool` – Kommentar (z. B. Lebenszeichen alle 15 s bei langen Pausen)
- `retry(int $ms): bool` – Wartezeit für eine automatische Neuverbindung (EventSource) in Millisekunden
- `send(string $event, mixed $data = null, ?string $id = null): bool` – Ereignis senden (Daten als JSON). false = Verbindung abgebrochen
- `start(array $headers = [], int $retry = 0): void` – Kopfzeilen senden, Puffer abschalten, Sitzung freigeben
- `static stream(callable $fn, array $headers = [], int $retry = 0): Http\Response` – Stream öffnen, $fn(Sse) ausführen, schließen. Liefert eine bereits gesendete Response (App::handle schreibt nichts mehr).
- `static supported(string $mode = 'auto'): bool` – Sind Streams hier sinnvoll? $mode: 'sse' (immer), 'poll' (nie) oder 'auto' – auf dem PHP-Entwicklungsserver (php -S,
- `static wanted(Http\Request $r): bool` – Möchte der Browser einen Stream? (fetch mit Accept: text/event-stream bzw. EventSource)

## Core\Http\Controllers

### `ApiController` (class, `app/Http/Controllers/ApiController.php`)

REST-API v1 (JSON). Authentifizierung: Authorization: Bearer cms_… (oder X-Api-Key). Antworten: {"data": …} bzw. {"error": {"status", "message", …}}

- `blockAdd(Http\Request $r, string $page): Http\Response`
- `blockDelete(Http\Request $r, string $page, string $block): Http\Response`
- `blockMove(Http\Request $r, string $page, string $block): Http\Response`
- `blockTypes(Http\Request $r): Http\Response`
- `blockUpdate(Http\Request $r, string $page, string $block): Http\Response`
- `blocks(Http\Request $r, string $page): Http\Response`
- `blocksReplace(Http\Request $r, string $page): Http\Response`
- `change(Http\Request $r, string $id): Http\Response` – GET /api/v1/changes/{id} – Stand einer Einreichung (pending_review | applied | rejected, bei Ablehnung mit reason)
- `changes(Http\Request $r): Http\Response` – GET /api/v1/changes?status=pending|applied|rejected – eigene Einreichungen und Protokoll dieses Tokens
- `dataCreate(Http\Request $r, string $table): Http\Response`
- `dataDelete(Http\Request $r, string $table, string $id): Http\Response`
- `dataEntries(Http\Request $r, string $table): Http\Response`
- `dataEntry(Http\Request $r, string $table, string $id): Http\Response`
- `dataOccurrences(Http\Request $r, string $table): Http\Response`
- `dataPick(Http\Request $r, string $table): Http\Response`
- `dataSuggestions(Http\Request $r, string $table): Http\Response`
- `dataTableCreate(Http\Request $r): Http\Response`
- `dataTables(Http\Request $r): Http\Response`
- `dataTemplates(Http\Request $r): Http\Response`
- `dataTranslate(Http\Request $r, string $table, string $id): Http\Response`
- `dataUpdate(Http\Request $r, string $table, string $id): Http\Response`
- `design(Http\Request $r): Http\Response`
- `designUpdate(Http\Request $r): Http\Response`
- `discard(Http\Request $r, string $page): Http\Response`
- `static fail(Api\ApiError $e): Http\Response`
- `geocode(Http\Request $r): Http\Response`
- `hours(Http\Request $r): Http\Response`
- `hoursUpdate(Http\Request $r): Http\Response`
- `index(Http\Request $r): Http\Response`
- `landings(Http\Request $r): Http\Response` – Landingpages mit eigenen Domains (Core\Landings) – nur lesen
- `me(Http\Request $r): Http\Response`
- `media(Http\Request $r): Http\Response`
- `mediaCrop(Http\Request $r, string $id): Http\Response`
- `mediaDelete(Http\Request $r, string $id): Http\Response`
- `mediaMeta(Http\Request $r): Http\Response`
- `mediaUpdate(Http\Request $r, string $id): Http\Response`
- `mediaUpload(Http\Request $r): Http\Response`
- `notFound(Http\Request $r): Http\Response`
- `notice(Http\Request $r): Http\Response`
- `static ok(mixed $data, int $status = 200): Http\Response`
- `openapi(Http\Request $r): Http\Response`
- `page(Http\Request $r, string $page): Http\Response`
- `pageCreate(Http\Request $r): Http\Response`
- `pageDelete(Http\Request $r, string $page): Http\Response`
- `pageTranslate(Http\Request $r, string $page): Http\Response`
- `pageUpdate(Http\Request $r, string $page): Http\Response`
- `pages(Http\Request $r): Http\Response`
- `publicInfo(Http\Request $r): Http\Response`
- `publish(Http\Request $r, string $page): Http\Response`
- `push(Http\Request $r): Http\Response`
- `pushCancel(Http\Request $r, string $id): Http\Response`
- `pushSend(Http\Request $r): Http\Response`
- `redirects(Http\Request $r): Http\Response` – Weiterleitungen (Core\Redirects) – nur lesen, ?q=…, ?test=/pfad
- `requestAssignees(Http\Request $r): Http\Response`
- `requestDelete(Http\Request $r, string $id): Http\Response`
- `requestUpdate(Http\Request $r, string $id): Http\Response` – Status und/oder Zuweisung (assignee: Benutzer-ID oder E-Mail, null = entfernen)
- `requests(Http\Request $r): Http\Response`
- `restore(Http\Request $r, string $page, string $rev): Http\Response`
- `revisions(Http\Request $r, string $page): Http\Response`
- `search(Http\Request $r): Http\Response` – GET /api/v1/search?q=…&lang=&page=&limit=&type= – Website-Suche wie für Besucher (Core\Search)
- `settings(Http\Request $r): Http\Response`
- `settingsSchema(Http\Request $r): Http\Response`
- `settingsUpdate(Http\Request $r): Http\Response`
- `sourceSync(Http\Request $r, string $id): Http\Response`
- `sources(Http\Request $r): Http\Response`

### `CalendarController` (class, `app/Http/Controllers/CalendarController.php`)

Öffentliche iCal-Dateien (nur veröffentlichte Einträge, nur Tabellen mit Kalender und aktivem Feed): GET /kalender/{handle}.ics            Feed zum Abonnieren (?lang=en für eine weitere Sprache) GET /kalender/{handle}/{slug}.ics     einzelner Termin zum Herunterladen

- `event(Http\Request $r, string $handle, string $slug): Http\Response`
- `feed(Http\Request $r, string $handle): Http\Response`

### `DataFormController` (class, `app/Http/Controllers/DataFormController.php`)

Öffentliche Formulare für Datentabellen (Funktion „forms.data“, Block „data_form“) und Eingangs-Tabellen (Funktion „requests“, verschlüsselt). Keine Session, keine Cookies – Schutz über SpamGuard (signiertes Token statt CSRF-Session), wie FormController. GET  /formular/{table}/challenge   Token + Proof-of-Work-Aufgabe (JSON, beim ersten Fokus im Formular)

- `challenge(Http\Request $r, string $table): Http\Response`
- `show(Http\Request $r, string $table, int $status = 200): Http\Response`
- `submit(Http\Request $r, string $table): Http\Response`

### `FormController` (class, `app/Http/Controllers/FormController.php`)

Theme-Formulare unter stabilen Adressen: /anfrage/{form} (z. B. /anfrage/rezept) und /api/form/{form} (Token). Dahinter steht die Eingangs-Tabelle des Formulars (Core\Data\Inbox) – Prüfung, Spamschutz und Verschlüsselung wie beim Block „Formular (Datentabelle)“ (DataForms). Keine Session, keine Cookies.

- `challenge(Http\Request $r, string $form): Http\Response` – Liefert Token + Proof-of-Work-Aufgabe (wird beim Öffnen des Formulars geholt)
- `show(Http\Request $r, string $form, array $values = [], array $errors = [], ?string $message = null, bool $sent = false, int $status = 200): Http\Response` – Eigenständige Formularseite (auch Fallback ohne JavaScript)
- `submit(Http\Request $r, string $form): Http\Response`
- `useLang(Http\Request $r): void` – Sprache setzen – auch für die Formulare der Datentabellen (DataFormController)

### `McpController` (class, `app/Http/Controllers/McpController.php`)

MCP-Server (Model Context Protocol) über „Streamable HTTP“ – zustandslos, JSON-Antworten. Endpunkt: POST /mcp · Auth: Authorization: Bearer cms_… (gleiche Tokens wie die REST-API)

- `static catalog(): array` – Katalog für die Dokumentation (Tools, Prompts, Resources)
- `get(Http\Request $r): Http\Response`
- `handle(Http\Request $r): Http\Response`

### `PdfController` (class, `app/Http/Controllers/PdfController.php`)

PDF-Anzeige im Browser mit Mozilla PDF.js (lokal ausgeliefert, keine Dritten).

- `static file(string $url, string $name, int $size = 0, ?int $pages = null, ?string $back = null): Http\Response` – Viewer für eine beliebige PDF-Adresse derselben Website – für Dateien außerhalb der Mediathek, z. B. geschützte PDFs
- `show(Http\Request $r, string $id): Http\Response`
- `showPool(Http\Request $r, string $pool, string $id): Http\Response` – PDF aus einem geteilten Pool, den diese Website nutzen darf (Mediathek-Ansicht „Geteilt“)

### `ProtectedMediaController` (class, `app/Http/Controllers/ProtectedMediaController.php`)

GET /geschuetzt/{pool}/{pfad} – Dateien geschützter Medien-Pools (Core\MediaPools::isProtected). Liegen außerhalb von public/, deshalb nur hier: Pool geschützt und für diese Website freigegeben, Pfad im Pool-Ordner, Zugriff erlaubt – angemeldete Redaktion oder eine Erweiterung (Extension::mediaAccess, z. B. angemeldete Mitglieder). Ohne Zugriff 404 (verrät nicht, dass es die Datei

- `static send(string $file, Http\Request $r): Http\Response` – Datei streamen (auch Byte-Bereiche, z. B. Springen in Videos)
- `serve(Http\Request $r, string $pool, string $path): Http\Response`

### `ProxyController` (class, `app/Http/Controllers/ProxyController.php`)

/proxy/{quelle}/{pfad} – externe Inhalte über die eigene Domain (siehe Core\Proxy)

- `handle(Http\Request $r, string $source, string $path = ''): Http\Response`

### `PushController` (class, `app/Http/Controllers/PushController.php`)

Öffentliche Seite der Push-Benachrichtigungen (Funktion „push“, sonst 404 – Features::allowsPath): GET  /push-sw.js              Service Worker (resources/sw/push.js) – Bereich {base}/push-sw/, steuert keine Seiten POST /api/push/subscribe      {subscription, topics[], offered[]?, lang, token} – Abo für öffentliche Kanäle (Core\Push\Channels)

- `renew(Http\Request $r): Http\Response` – Neues Abo eines Browsers (pushsubscriptionchange im Service Worker): Themen und Konto vom alten übernehmen
- `static sameOrigin(Http\Request $r): bool` – Anfrage von dieser Website? (Origin = Host; Browser ohne Origin: Sec-Fetch-Site same-origin)
- `serviceWorker(Http\Request $r): Http\Response`
- `status(Http\Request $r): Http\Response`
- `subscribe(Http\Request $r): Http\Response` – Abo für Kanäle: topics = gewählte Kanäle. Mit offered (die Kanäle, die das Formular angeboten hat – Mehrfachauswahl in Block,
- `unsubscribe(Http\Request $r): Http\Response`

### `PwaController` (class, `app/Http/Controllers/PwaController.php`)

Favicon, Web-App-Manifest, Service Worker und Offline-Seite. Der Service Worker wird nur in der installierten App registriert (Start über start_url ?pwa=1) – normale Besucher im Browser bekommen nichts auf ihrem Gerät gespeichert.

- `icon(Http\Request $r): Http\Response` – /favicon.ico und /apple-touch-icon.png (werden von Browsern direkt im Wurzelverzeichnis gesucht)
- `manifest(Http\Request $r): Http\Response`
- `offline(Http\Request $r): Http\Response` – Offline-Seite des Themes (vom Service Worker vorab gespeichert)
- `serviceWorker(Http\Request $r): Http\Response` – Service Worker – bei ausgeschaltetem Offline-Modus ein „Abschalter“, der sich selbst entfernt

### `SearchPageController` (class, `app/Http/Controllers/SearchPageController.php`)

Öffentliche Website-Suche: /suche?q=… (Standardsprache), /en/search?q=… (weitere Sprachen) und /suche/vorschlag?q=… (JSON für die Vorschläge beim Tippen). Serverseitig gerendert, funktioniert ohne JavaScript. Seiten mit demselben Pfad haben Vorrang; ist die Funktion „search“ aus, verhält sich die Adresse wie jede andere.

- `show(Http\Request $r, string $lang = '', string $slug = ''): Http\Response`
- `suggest(Http\Request $r, string $lang = '', string $slug = ''): Http\Response` – JSON: {q, items: [{title, url, badge, date}], all} – Vorschläge beim Tippen (nur Stichwortsuche)

### `SiteController` (class, `app/Http/Controllers/SiteController.php`)

- `static csp(bool $loggedIn = false): string`
- `error(int $code, string $message = ''): Http\Response`
- `home(Http\Request $r): Http\Response`
- `llms(Http\Request $r): Http\Response` – /llms.txt – Übersicht für KI-Assistenten (Grundeinstellungen → Indexierung & Crawler)
- `page(Http\Request $r, string $path): Http\Response` – Seiten nach vollständigem Pfad, Detailseiten von Datentabellen, Weiterleitung alter Kurz-Adressen
- `previewHtml(array $page, bool $draft = false): string` – HTML einer Seite für Vorschauen: veröffentlichte Fassung (bzw. Entwurf, solange nie veröffentlicht), mit $draft = true
- `respond(string $html, bool $loggedIn, int $status = 200, bool $private = false): Http\Response` – $private: Inhalt nur für diese Sitzung (geschützter Bereich) – nicht in geteilten Caches oder dem Service Worker
- `robots(Http\Request $r): Http\Response`
- `sitemap(Http\Request $r): Http\Response`

### `SupportApiController` (class, `app/Http/Controllers/SupportApiController.php`)

REST-API: Wissensdatenbank lesen – GET /api/v1/kb?q=…&limit=10 (Core\Support\Api)

- `kb(Http\Request $r): Http\Response`

### `VCardController` (class, `app/Http/Controllers/VCardController.php`)

Öffentliche Visitenkarten (Core\VCard, vCard 3.0): GET /vcard.vcf                       Organisation der Website (404 ohne Telefon, E-Mail und Adresse) GET /vcard/{handle}/{slug}.vcf       Person aus einer Tabelle mit Schema-Typ „Person“ (nur veröffentlichte Einträge)

- `org(Http\Request $r): Http\Response`
- `person(Http\Request $r, string $handle, string $slug): Http\Response`

### `VisitorChatController` (class, `app/Http/Controllers/VisitorChatController.php`)

Besucher-Chat (Core\AI\VisitorChat): GET /api/chat/config (Texte in der Sprache der Seite), POST /api/chat (Antwort als Server-Sent Events, ohne Accept: text/event-stream als JSON). Keine Cookies, keine Sitzung, keine Protokollierung der Inhalte; Ratenbegrenzung je HMAC der IP und Tageslimit der Website. Nur Anfragen von der eigenen Domain (Origin).

- `ask(Http\Request $r): Http\Response`
- `config(Http\Request $r): Http\Response`

## Core\Http\Controllers\Admin

### `AccountController` (class, `app/Http/Controllers/Admin/AccountController.php`)

Konto → Anmeldedaten (Core\EmailChange): Name, E-Mail-Adresse ändern (mit Bestätigung), offene Änderung erneut senden oder abbrechen; öffentliche Seiten zu den Links aus den E-Mails (/admin/konto/email/{token}, /admin/konto/email-abbrechen/{token}). Passwort ändern: UserController::saveAccount.

- `avatar(Http\Request $r, string $id): Http\Response` – Profilbild ausliefern – nur angemeldet, privat; ?v= macht die Adresse unveränderlich
- `cancel(Http\Request $r, string $token): Http\Response`
- `cancelEmail(Http\Request $r): Http\Response` – Offene Änderung abbrechen (angemeldet)
- `cancelPage(Http\Request $r, string $token): Http\Response` – „Das war ich nicht“ – Seite mit Schaltfläche (GET ändert nichts)
- `confirm(Http\Request $r, string $token): Http\Response`
- `static confirmIdentity(Http\Request $r, array $row): ?string` – Identität bestätigen: aktuelles Passwort (5 Fehlversuche in 15 Minuten, wie „Passwort bestätigen“) – Konten ohne Passwort
- `confirmPage(Http\Request $r, string $token): Http\Response` – Neue Adresse bestätigen – Seite mit Schaltfläche (GET ändert nichts)
- `requestEmail(Http\Request $r): Http\Response` – Neue E-Mail-Adresse anfragen → Bestätigungslink an die neue, Hinweis an die bisherige Adresse
- `resendEmail(Http\Request $r): Http\Response` – Offene Änderung: neuen Bestätigungslink senden
- `saveAvatar(Http\Request $r): Http\Response` – Profilbild hochladen oder entfernen (Core\Avatar) – auch für Netzwerk-Konten (gilt dann auf allen Websites)
- `saveProfile(Http\Request $r): Http\Response`

### `ActivityController` (class, `app/Http/Controllers/Admin/ActivityController.php`)

System → Aktionslog (Core\Activity): letzte Änderungen an Seiten, Datensätzen und Medien – filterbar nach Art, Aktion, Person, Zeitraum und Name, sortierbar. Nur mit Recht system.manage.

- `index(Http\Request $r): Http\Response`

### `AdminController` (class, `app/Http/Controllers/Admin/AdminController.php`)

Basis für alle Admin-Controller: Login-Pflicht, Rollen, CSRF, Views.

- `static routeGuard(Http\Request $r, string|bool $perm = false, bool $csrf = true): array` – Schutz der Verwaltungsrouten von Erweiterungen (Core\Http\Router): Anmeldung, Zwei-Faktor-Einrichtung, Recht und – bei
- `static secure(Http\Response $r): Http\Response`
- `static truncated(Http\Request $r): never` – Unvollständig angekommenes Formular (Request::$truncated): klare Meldung, zurück zum Formular – es wurde nichts gespeichert

### `AiController` (class, `app/Http/Controllers/Admin/AiController.php`)

KI-Assistent der Redaktion (JSON für resources/js/_ai.js) + SEO-Übersicht. Jeder KI-Endpunkt: Anmeldung, CSRF, Recht „ai.use“, Ai::enabled(Fähigkeit), Tageslimit (Core\AI\Assist::call) und das fachliche Recht des Gegenstands (Seiten, Einträge, Medien, Support). Antworten sind Vorschläge – gespeichert wird nur

- `alt(Http\Request $r): Http\Response` – Alt-Text vorschlagen: {media, pool?} (vorhandenes Bild) oder {image: data:-URL, name} (vor dem Hochladen)
- `altApply(Http\Request $r): Http\Response` – Geprüfte Alt-Texte speichern: {items: [{id, alt: {de: …, en: …}}], pool?} – nur mit Recht zum Ändern (auch in Pools)
- `applied(Http\Request $r): Http\Response` – Übernahme eines Vorschlags im Browser (Formular gefüllt) für den Verlauf melden – nur Metadaten
- `areaAlt(Http\Request $r): Http\Response`
- `areaCaptions(Http\Request $r): Http\Response` – Untertitel: Videos/Audio ohne Untertitel bzw. Transkript + Aufträge der KI-Transkription (views/ai/captions.php)
- `areaHistory(Http\Request $r): Http\Response`
- `areaIndex(Http\Request $r): Http\Response`
- `areaPages(Http\Request $r): Http\Response` – Seiten-Generator (Recht „pages.manage“)
- `areaSettings(Http\Request $r): Http\Response`
- `areaTables(Http\Request $r): Http\Response` – Tabellen-Generator (Recht „data.schema“)
- `areaTranslate(Http\Request $r): Http\Response`
- `areaWrite(Http\Request $r): Http\Response`
- `kbPolish(Http\Request $r): Http\Response`
- `overview(Http\Request $r): Http\Response` – SEO-Übersicht
- `pageCreate(Http\Request $r): Http\Response` – Geprüften Seiten-Entwurf als unveröffentlichte Seite anlegen
- `pageFields(Http\Request $r, string $id): Http\Response` – Textfelder einer Seite (Entwurf) für „In Seite einfügen“
- `pageInsert(Http\Request $r): Http\Response` – Geprüften Text als Entwurf in ein Textfeld einer Seite schreiben (ersetzen oder anhängen)
- `pagePropose(Http\Request $r): Http\Response`
- `pageTranslateApply(Http\Request $r, string $id): Http\Response` – Geprüfte Übersetzungen in den Entwurf der Seite schreiben (Formularfelder füllt die Oberfläche selbst)
- `pageTranslateItems(Http\Request $r, string $id): Http\Response` – Übersetzbare Texte der Seite in der Standardsprache + aktueller Stand dieser Übersetzung
- `schema(Http\Request $r): Http\Response`
- `seo(Http\Request $r): Http\Response`
- `seoApply(Http\Request $r): Http\Response` – Vorschläge aus der SEO-Übersicht übernehmen: [{page, meta_title?, meta_description?}] bzw. [{table, id, value}]
- `seoCheck(Http\Request $r, string $id): Http\Response` – SEO-Check einer Seite (ohne KI)
- `status(Http\Request $r): Http\Response` – Aktueller Stand für die Oberfläche (Tageslimit)
- `summary(Http\Request $r): Http\Response`
- `supportReply(Http\Request $r): Http\Response`
- `tableCreate(Http\Request $r): Http\Response` – Geprüften Tabellen-Entwurf anlegen (normale Prüfung Tables::validate)
- `tablePropose(Http\Request $r): Http\Response`
- `text(Http\Request $r): Http\Response`
- `translate(Http\Request $r): Http\Response`

### `AiSearchController` (class, `app/Http/Controllers/Admin/AiSearchController.php`)

Grundeinstellungen → Suche (Index neu aufbauen, Zähler leeren) und → KI (Verbindung testen, Verbindungen und Verwendung der Installation).

- `missesClear(Http\Request $r): Http\Response`
- `provider(Http\Request $r): Http\Response` – Verbindungen und Verwendung der Installation (Core\AI\Profiles) – eine Route, Aktion über „op“:
- `rebuild(Http\Request $r): Http\Response`
- `test(Http\Request $r): Http\Response`

### `ApiTokenController` (class, `app/Http/Controllers/Admin/ApiTokenController.php`)

Admin → API & MCP: Tokens verwalten, Einrichtungshilfe.

- `delete(Http\Request $r, string $id): Http\Response`
- `index(Http\Request $r, ?string $newToken = null, array $errors = []): Http\Response`
- `mode(Http\Request $r, string $id): Http\Response` – Änderungen dieses Tokens: direkt übernehmen | zur Freigabe (Core\Review)
- `store(Http\Request $r): Http\Response`

### `AssistantController` (class, `app/Http/Controllers/Admin/AssistantController.php`)

Assistent der Redaktion (Core\AI\Assistant): Frage (Server-Sent Events bzw. JSON), Aktion ausführen, Verlauf speichern/löschen und die Seite „KLXM AI → Assistent“. Jeder Aufruf: Anmeldung, CSRF (POST), Assistant::available().

- `ask(Http\Request $r): Http\Response`
- `delete(Http\Request $r, string $id): Http\Response`
- `execute(Http\Request $r): Http\Response`
- `history(Http\Request $r): Http\Response`
- `page(Http\Request $r): Http\Response` – Seite im KI-Bereich: Assistent im Hauptbereich + gespeicherte Unterhaltungen
- `save(Http\Request $r): Http\Response`
- `show(Http\Request $r, string $id): Http\Response`

### `AuthController` (class, `app/Http/Controllers/Admin/AuthController.php`)

- `login(Http\Request $r): Http\Response`
- `loginForm(Http\Request $r, string $error = ''): Http\Response`
- `logout(Http\Request $r): Http\Response`
- `setup(Http\Request $r): Http\Response`
- `setupForm(Http\Request $r, array $errors = [], array $old = []): Http\Response`

### `BlockController` (class, `app/Http/Controllers/Admin/BlockController.php`)

Verwaltung → Blöcke (Block-Designer): eigene Blöcke anlegen, prüfen, in der Vorschau testen, versionieren, für die Redaktion freigeben, exportieren/importieren, als Theme-Block ausgeben und mit KLXM AI vorschlagen lassen. Recht „blocks.build“ (Administration, Integratoren), Funktion „blocks.custom“.

- `ai(Http\Request $r): Http\Response` – KLXM AI: Vorschlag (wird nie gespeichert oder freigegeben – die Person prüft ihn im Block-Designer)
- `create(Http\Request $r): Http\Response`
- `delete(Http\Request $r, string $key): Http\Response`
- `demoCss(Http\Request $r, string $name): Http\Response`
- `demoInstall(Http\Request $r): Http\Response` – Beispiel „Als Vorlage übernehmen“: immer eine neue Kopie als Entwurf – je Klick genau einmal
- `demoPreview(Http\Request $r, string $name): Http\Response` – Vorschau eines Beispiels (iframe in der Übersicht): Seite des aktiven Kits, nur der Block mit Beispieldaten
- `edit(Http\Request $r, string $key): Http\Response`
- `export(Http\Request $r, string $key): Http\Response`
- `import(Http\Request $r): Http\Response`
- `index(Http\Request $r): Http\Response`
- `library(Http\Request $r, string $key): Http\Response`
- `libraryImport(Http\Request $r): Http\Response`
- `preview(Http\Request $r): Http\Response` – Prüfen + Vorschau: ganze Seite des aktiven Themes mit dem Block und Beispieldaten
- `previewCss(Http\Request $r): Http\Response` – Entwurfs-CSS der letzten Vorschau (aus der Sitzung)
- `publish(Http\Request $r, string $key): Http\Response`
- `restore(Http\Request $r, string $key, string $version): Http\Response`
- `sampleForm(Http\Request $r): Http\Response` – Formular für Beispieldaten (gleicher Feld-Renderer wie in der Seitenleiste des Editors)
- `store(Http\Request $r): Http\Response`
- `themeExport(Http\Request $r, string $key): Http\Response`
- `update(Http\Request $r, string $key): Http\Response`
- `withdraw(Http\Request $r, string $key): Http\Response`

### `BrandingController` (class, `app/Http/Controllers/Admin/BrandingController.php`)

System → Branding & Support (Core\Branding): Logo, Name, Hinweis „Technik“, Support-Angaben der Agentur – für die ganze Installation

- `index(Http\Request $r): Http\Response`
- `logo(Http\Request $r): Http\Response` – Öffentlich: Logo (/branding/logo, /branding/logo-dark) – auch auf der Anmeldeseite
- `logoDark(Http\Request $r): Http\Response`
- `save(Http\Request $r): Http\Response`

### `ChatController` (class, `app/Http/Controllers/Admin/ChatController.php`)

Chat zwischen Benutzern der Verwaltung (Core\Chat). JSON-Endpunkte unter /admin/api/chat/* für resources/js/userchat.js, Live-Aktualisierung per Server-Sent Events (/admin/api/chat/stream, ≤ 25 s je Verbindung) oder – auf dem Entwicklungsserver – per Abfrage (/admin/api/chat/poll). Seiten: /admin/chat (Vollbild) und /admin/chat/einstellungen.

- `channelArchive(Http\Request $r, string $id): Http\Response`
- `channelSave(Http\Request $r, string $id = ''): Http\Response`
- `channelUpdate(Http\Request $r, string $id): Http\Response`
- `delete(Http\Request $r, string $id): Http\Response`
- `dm(Http\Request $r): Http\Response` – Direktnachricht öffnen/anlegen: user=Kennung, scope=site|network
- `edit(Http\Request $r, string $id): Http\Response`
- `file(Http\Request $r, string $id): Http\Response` – Bild – nur für Personen, die die Unterhaltung sehen dürfen
- `messages(Http\Request $r, string $id): Http\Response`
- `page(Http\Request $r): Http\Response` – Vollbild-Ansicht (ohne JavaScript: Hinweis)
- `people(Http\Request $r): Http\Response` – Personen für Direktnachrichten und @Erwähnungen. scope=site|network, room=ID (nur Mitglieder dieses Raums)
- `poll(Http\Request $r): Http\Response` – Abfrage-Modus (Entwicklungsserver, Rückfall): Ereignisse seit last_id, sofort zurück
- `prefs(Http\Request $r): Http\Response` – Eigene E-Mail-Hinweise zu Erwähnungen (JSON oder Formular)
- `react(Http\Request $r, string $id): Http\Response`
- `read(Http\Request $r, string $id): Http\Response`
- `saveSettings(Http\Request $r): Http\Response`
- `send(Http\Request $r, string $id): Http\Response`
- `settings(Http\Request $r): Http\Response`
- `state(Http\Request $r): Http\Response` – Räume, Zähler, eigene Einstellungen, letzte Ereignis-ID, Übertragungsart
- `stream(Http\Request $r): Http\Response` – Server-Sent Events: hält höchstens chat_stream_seconds (≤ 25 s), fragt etwa jede Sekunde den Ereignis-Feed ab
- `unread(Http\Request $r): Http\Response` – Zähler für die Seitenleiste (langsame Abfrage bei geschlossenem Chat)
- `upload(Http\Request $r, string $id): Http\Response` – Bild hochladen (vor dem Senden) – Feld „file“

### `CronController` (class, `app/Http/Controllers/Admin/CronController.php`)

Grundeinstellungen → Geplante Aufgaben (/admin/system/aufgaben, Core\Cron): Zustand des einen Cronjobs, Aufgaben von Kern und Erweiterungen mit Zeitplan, nächstem und letztem Lauf; Zeitplan ändern, an/aus, „Jetzt ausführen“ (Warteschlange), Protokoll. Gilt für die ganze Installation – Recht wie bei Kits und Schriften.

- `index(Http\Request $r): Http\Response`
- `run(Http\Request $r, string $key): Http\Response` – Jetzt ausführen: in die Warteschlange (läuft beim nächsten Aufruf von cron:run, spätestens in einer Minute)
- `save(Http\Request $r, string $key): Http\Response` – Zeitplan und an/aus einer Aufgabe

### `DashboardController` (class, `app/Http/Controllers/Admin/DashboardController.php`)

Übersicht der Verwaltung (/admin) für die Redaktion: Begrüßung mit Schnellaktionen, Kennzahlen, „Was ist zu tun?“, Statistiken (nachgeladen) und Hilfe. Karten, Daten und Anordnung: Core\Dashboard\Dashboard und Core\Dashboard\Metrics. Die Netzwerk-Übersicht „Alle Websites“ bleibt unter /admin/network (NetworkController).

- `card(Http\Request $r, string $card): Http\Response` – Nachgeladene Karte als JSON {html} (GET /admin/api/dashboard/{card})
- `index(Http\Request $r): Http\Response`
- `placeholderOk(Http\Request $r): Http\Response` – Klammer-Text als gewollt bestätigen (POST /admin/api/dashboard/placeholder-ok, text, back) – wird auf keiner Seite mehr gemeldet.
- `prefs(Http\Request $r): Http\Response` – Anordnung ändern (POST /admin/api/dashboard/prefs): do = up|down|hide|show|open|close|order|reset, card, order[]

### `DataController` (class, `app/Http/Controllers/Admin/DataController.php`)

Daten: Tabellen ohne Programmieren anlegen (nur Administration) und Einträge pflegen (Redaktion).

Konstanten: `PRESETS`

- `bulk(Http\Request $r, string $handle): Http\Response` – Sammelaktionen und Sortierung (JSON)
- `create(Http\Request $r): Http\Response` – Neue Tabelle: Assistent (Zweck → Vorlage → Grundeinstellungen → Einsetzen, Core\Data\Wizard). ?expert=1 = bisheriger
- `deliveryTest(Http\Request $r, string $handle): Http\Response` – Eingang → Zustellung: Testmail mit erfundener Anfrage an die gespeicherten Empfänger (Core\Data\Delivery::test)
- `destroy(Http\Request $r, string $handle): Http\Response`
- `display(Http\Request $r, string $handle): Http\Response` – Anzeige auf dieser Website: welche fremden Einträge erscheinen (Eigentümer, Mitglieder, Filter, Verlinkung)
- `displaySave(Http\Request $r, string $handle): Http\Response`
- `entries(Http\Request $r, string $handle): Http\Response`
- `entryDelete(Http\Request $r, string $handle, string $id): Http\Response`
- `entryEdit(Http\Request $r, string $handle, string $id): Http\Response`
- `entryNew(Http\Request $r, string $handle): Http\Response`
- `entrySave(Http\Request $r, string $handle, ?string $id = null): Http\Response`
- `foreign(Http\Request $r, string $handle): Http\Response` – Fremde Einträge: ?src=owner (vom Eigentümer), members (von den übrigen Websites), suggestions (Vorschläge, nur Eigentümer)
- `index(Http\Request $r): Http\Response`
- `static makeTemplate(array $t): int`
- `pick(Http\Request $r, string $handle): Http\Response` – Auswahl setzen: übernehmen/ablehnen (Eigentümer), ausblenden, hervorheben, zurücksetzen
- `place(Http\Request $r, string $handle): Http\Response` – Wie kommt die Tabelle auf die Website? Block, Seite anlegen, in bestehende Seite einfügen, Verwendungen. ?neu=1: Schritt 4
- `placeSave(Http\Request $r, string $handle): Http\Response`
- `static presetDef(string $preset): array` – Definition einer neuen Tabelle aus einer Vorlage (leer = nur Titel) – auch für geteilte Tabellen (Grundeinstellungen)
- `static presets(): array` – Vorlagen, die auf dieser Website angeboten werden (Eingang nur mit Funktion „requests“). Dazu „Stellenangebote“
- `quick(Http\Request $r, string $handle): Http\Response` – Eintrag nur mit Titel anlegen (Verknüpfungsfelder: „+ Neu“)
- `static saveSchema(array $t, array $in, bool $confirmDrop): array` – Felder und Einstellungen einer bestehenden Tabelle prüfen und speichern – gemeinsamer Weg des Tabellen-Designers und der
- `schema(Http\Request $r, string $handle): Http\Response`
- `store(Http\Request $r): Http\Response`
- `template(Http\Request $r, string $handle): Http\Response` – Detailseiten-Vorlage anlegen und im Editor öffnen
- `translate(Http\Request $r, string $handle, string $id): Http\Response` – Übersetzung eines Eintrags anlegen oder öffnen
- `update(Http\Request $r, string $handle): Http\Response`
- `wizard(Http\Request $r): Http\Response` – Schritte des Assistenten absenden (ohne JavaScript bedienbar): step = aktueller Schritt, go = next | back | ai | add | up:N | down:N.

### `DesignController` (class, `app/Http/Controllers/Admin/DesignController.php`)

Verwaltung → Design (Style-Editor): Farben, Formen, Schriften des aktiven Themes (theme.php → 'design'). Werte gelten je Website und Theme; gespeichert über Design::save() (mit Verlauf).

- `edit(Http\Request $r): Http\Response`
- `static forceDark(string $html, array $values): string` – Vorschau „Dunkel“: dunkle Werte ohne Media-Query erzwingen, optional Theme-Schalter (dark.force) am <html>
- `import(Http\Request $r): Http\Response` – Import prüfen: JSON (Export oder reine Werte) → normalisierte Werte + Hinweise
- `live(Http\Request $r): Http\Response` – Werkzeug „Design“ der Werkzeugleiste (Core\DesignTool): Schema (übersetzt), gespeicherte Werte, Standardwerte und der
- `liveApply(Http\Request $r): Http\Response` – Live-Vorschau bzw. Speichern aus der Werkzeugleiste: v = Werte (wie die Vorschau der Verwaltung), save = 1 speichert für die
- `mineDelete(Http\Request $r, string $id): Http\Response`
- `mineSave(Http\Request $r): Http\Response` – Eigenes Design speichern: aktuelle Werte des Formulars (v) unter _mine_name – gleicher Name ersetzt
- `preview(Http\Request $r): Http\Response` – Live-Vorschau: Seite mit den ungespeicherten Werten (nur im Speicher); _dark = dunkle Werte erzwingen
- `save(Http\Request $r): Http\Response`

### `DomainController` (class, `app/Http/Controllers/Admin/DomainController.php`)

System → Domain (Core\Domains): Domains der Website, Hauptadresse (erst nach erfolgreicher Erreichbarkeitsprüfung), Weiterleitung weiterer Domains (301); außerdem Umgebung (staging ↔ production) für jede Website unter Grundeinstellungen. Schreibt config/sites/{key}.php (Sicherung .bak). Nur Einzel-Installation ohne Netzwerk – sonst 404 (Domains verwaltet dann die Netzwerk-Administration). Recht: system.manage.

- `add(Http\Request $r): Http\Response`
- `check(Http\Request $r): Http\Response` – Erreichbarkeit prüfen: zeigt die Domain auf diese Installation? Ergebnis 10 Minuten in der Sitzung (Voraussetzung für „Hauptadresse“)
- `environment(Http\Request $r): Http\Response` – Grundeinstellungen → Umgebung: staging ↔ production – für jede Website (auch im Netzwerk), Recht system.manage
- `index(Http\Request $r): Http\Response`
- `primary(Http\Request $r): Http\Response` – Hauptadresse festlegen – nur nach erfolgreicher Prüfung (prüft erneut, falls die letzte Prüfung älter als 10 Minuten ist)
- `redirect(Http\Request $r): Http\Response`
- `remove(Http\Request $r): Http\Response`

### `DraftController` (class, `app/Http/Controllers/Admin/DraftController.php`)

Verwaltung → Entwürfe (Core\Review\Drafts): alle offenen Entwürfe der Website mit Unterschieden, Notiz/Zuständigkeit, Veröffentlichen und Verwerfen (einzeln und gesammelt). Rechte wie in Seiten- und Datenverwaltung: pages.edit / pages.publish bzw. data.edit / data.publish / data.delete je Tabelle.

- `action(Http\Request $r, string $op): Http\Response` – Veröffentlichen bzw. Verwerfen: ein Entwurf („one“) oder die Auswahl („items[]“)
- `static anchor(string $key): string` – Anker einer Zeile (für den Rücksprung nach dem Speichern)
- `entry(Http\Request $r, string $table, string $id): Http\Response`
- `index(Http\Request $r): Http\Response`
- `note(Http\Request $r): Http\Response` – Notiz und zuständige Person speichern
- `page(Http\Request $r, string $id): Http\Response` – Unterschiede Entwurf ↔ veröffentlicht: /admin/entwuerfe/seite/{id} bzw. /admin/entwuerfe/eintrag/{table}/{id}
- `restore(Http\Request $r, string $id): Http\Response` – Verworfenen Eintrag als Entwurf wiederherstellen

### `EditorController` (class, `app/Http/Controllers/Admin/EditorController.php`)

JSON-API für den Inline-Editor (Editor.js).

- `discard(Http\Request $r, string $id): Http\Response` – Entwurf verwerfen (zurück zur veröffentlichten Fassung).
- `form(Http\Request $r): Http\Response` – Feldformular eines Blocks für die Seitenleiste (gleicher Renderer wie im Admin).
- `links(Http\Request $r): Http\Response` – Linkziele. Ohne Parameter: flache Liste [{value, label}] (<datalist id="cms-links">, kompatibel).
- `media(Http\Request $r): Http\Response`
- `preview(Http\Request $r): Http\Response`
- `save(Http\Request $r, string $id): Http\Response` – Speichert den Entwurf; mit publish=true zusätzlich veröffentlichen.

### `EntryEditController` (class, `app/Http/Controllers/Admin/EntryEditController.php`)

Einträge auf der Website bearbeiten (JSON für resources/js/_entry_edit.js): GET  /admin/api/entries/{table}/{id|new}  → Formular für die Seitenleiste (gleiche Feld-Widgets wie in der Verwaltung) POST /admin/api/entries/{table}/{id|new}  → speichern (Formular der Seitenleiste oder einzelne Felder aus dem Text)

- `form(Http\Request $r, string $handle, string $id): Http\Response`
- `save(Http\Request $r, string $handle, string $id): Http\Response`
- `saveBlocks(Http\Request $r, string $handle, string $id, string $field): Http\Response` – Modus „Inhalt“ der Detailseite (Seiten-Editor): Inhaltsblöcke eines Eintrags speichern – JSON {blocks}. Nur dieses Feld ändert sich,

### `FavoriteController` (class, `app/Http/Controllers/Admin/FavoriteController.php`)

Favoriten des angemeldeten Benutzers (Core\Favorites). JSON für die Seitenleiste/den Stern, Formulare ohne JavaScript (Konto-Seite) werden zurückgeleitet. CSRF über AdminController::auth().

- `add(Http\Request $r): Http\Response`
- `remove(Http\Request $r): Http\Response`
- `rename(Http\Request $r): Http\Response`
- `reorder(Http\Request $r): Http\Response` – order: [url, …] (Ziehen, Alt+↑/↓) oder url + dir=up|down (Formular)

### `FeaturesController` (class, `app/Http/Controllers/Admin/FeaturesController.php`)

Administration → Funktionen & Erweiterungen (/admin/funktionen): Funktionen der Website und installierte Erweiterungen ein- und ausschalten. Recht system.features (Haupt-Admin) bzw. Integratoren/Netzwerk-Administration; in Netzwerk- Installationen sieht die Website-Administration die Seite nur lesend, bis das Netzwerk die Freigabe erteilt.

- `delegate(Http\Request $r): Http\Response` – Netzwerk: der Website-Administration das Schalten freigeben (Recht system.features wirkt dann)
- `extension(Http\Request $r): Http\Response` – Erweiterung ein- oder ausschalten (nicht destruktiv)
- `feature(Http\Request $r): Http\Response` – Funktion ein- oder ausschalten
- `index(Http\Request $r): Http\Response`

### `FontsController` (class, `app/Http/Controllers/Admin/FontsController.php`)

Grundeinstellungen → Schriften (Core\Fonts): Google-Fonts-Katalog durchsuchen, Schriften installieren (selbst gehostet unter public/assets/fonts/installed, für alle Websites), Vorladen, Entfernen. Vorschau-Schriften kommen von der eigenen Domain. Recht: system.manage (oder Integrator), Funktion „fonts“.

- `index(Http\Request $r): Http\Response`
- `install(Http\Request $r): Http\Response`
- `preload(Http\Request $r, string $id): Http\Response`
- `preview(Http\Request $r, string $id): Http\Response` – Vorschau-Schrift (woff2) – serverseitig geladen und zwischengespeichert, ausgeliefert von der eigenen Domain
- `remove(Http\Request $r, string $id): Http\Response`

### `FormFieldsController` (class, `app/Http/Controllers/Admin/FormFieldsController.php`)

„Felder bearbeiten“ im Seiten-Editor (JSON für resources/js/_form_fields.js): GET  /admin/api/formfields/{table}  → Seitenleiste mit den Feldern und Formular-Einstellungen der Tabelle POST /admin/api/formfields/{table}  → speichern (gleicher Weg wie der Tabellen-Designer: DataController::saveSchema)

- `form(Http\Request $r, string $handle): Http\Response`
- `save(Http\Request $r, string $handle): Http\Response`

### `GlossaryController` (class, `app/Http/Controllers/Admin/GlossaryController.php`)

Verwaltung → Glossar (Funktion „glossary“, Core\Glossary): einrichten, Begriffe mit Vorkommen und Hinweisen, schnell hinzufügen (optional mit KI-Vorschlag als Entwurf), Import/Export (CSV), Einstellungen, Teilen mit anderen Websites der Installation (Core\Glossary\Sharing, /admin/glossar/teilen). Bearbeitet wird in der Datentabelle.

- `apiCreate(Http\Request $r): Http\Response` – POST /admin/api/glossar/begriff {term, short, long, variants, publish, lang}
- `apiPage(Http\Request $r): Http\Response` – POST /admin/api/glossar/seite {html, lang, path, self}
- `apiSearch(Http\Request $r): Http\Response` – GET /admin/api/glossar/suche?q=…&lang=…
- `export(Http\Request $r): Http\Response`
- `hide(Http\Request $r): Http\Response` – Begriff einer anderen Website hier aus- oder wieder einblenden (Recht data.publish)
- `import(Http\Request $r): Http\Response`
- `index(Http\Request $r): Http\Response`
- `install(Http\Request $r): Http\Response`
- `quick(Http\Request $r): Http\Response` – Begriff schnell hinzufügen – ohne Kurz-Erklärung mit KI-Vorschlag (immer als Entwurf)
- `settings(Http\Request $r): Http\Response`
- `sharing(Http\Request $r): Http\Response` – Teilen, Einladungen, Beitreten mit Abgleich doppelter Begriffe, ausgeblendete Begriffe, Verlassen
- `sharingSave(Http\Request $r): Http\Response`

### `GuideController` (class, `app/Http/Controllers/Admin/GuideController.php`)

„Hinweise zu diesem Projekt“ (Core\Guide): Hinweise der Website anlegen, bearbeiten, Kit-Hinweise für die Website anpassen oder ausblenden (Recht system.manage); Bilder aus den guide-Ordnern für alle Angemeldeten.

- `delete(Http\Request $r, string $key): Http\Response` – Website-Fassung löschen – ein gleichnamiger Hinweis aus dem Kit gilt dann wieder
- `edit(Http\Request $r, string $key = '', ?string $src = null, array $errors = []): Http\Response`
- `image(Http\Request $r, string $file): Http\Response` – Bild aus kits/{kit}/guide bzw. {storage}/guide (nur Bildformate, nur Dateiname)
- `index(Http\Request $r): Http\Response`
- `save(Http\Request $r, string $key = ''): Http\Response`
- `task(Http\Request $r, string $key): Http\Response` – „Lesen“ in „Was ist zu tun?“: Hinweis im Handbuch öffnen und für diese Person abhaken (jede angemeldete Person)

### `HandoverController` (class, `app/Http/Controllers/Admin/HandoverController.php`)

Domainwechsel: angemeldet auf eine andere Domain derselben Website wechseln (Core\HostHandover) – dort einen Passkey anlegen, bevor die Hauptadresse umgestellt wird. POST /admin/account/domain (angemeldet, CSRF) → Zwischenseite → GET /admin/sso/domain?t=… auf der Ziel-Domain (ohne Anmeldung erreichbar) → Konto-Seite, Bereich Passkeys.

- `land(Http\Request $r): Http\Response`
- `start(Http\Request $r): Http\Response`

### `HelpController` (class, `app/Http/Controllers/Admin/HelpController.php`)

Handbuch (Redaktion) und technische Dokumentation (Entwicklung, API, MCP).

- `icons(Http\Request $r): Http\Response` – Übersicht aller Symbole nach Themen (Core\Icons) – für die Symbolauswahl und Themes
- `licenses(Http\Request $r): Http\Response` – Lizenzen & Danksagungen: Projektlizenz (LICENSE, COPYRIGHT) und THIRD-PARTY-NOTICES.md (Drittsoftware, Schriften, Daten, Medien)
- `manual(Http\Request $r): Http\Response`
- `phpApi(Http\Request $r): Http\Response` – PHP-API-Referenz: alle Klassen und öffentlichen Methoden des Kerns, live aus dem Code (Core\ApiReference)
- `technical(Http\Request $r): Http\Response`
- `tutorial(Http\Request $r, string $slug): Http\Response`
- `tutorials(Http\Request $r): Http\Response` – Tutorials für Redaktion, Administration und Agenturen (Inhalte: app/Admin/tutorials.php, Englisch tutorials.en.php).
- `ui(Http\Request $r): Http\Response` – Musterseite: alle Bausteine von Core\AdminUi live, mit Code – Vorlage für Kernseiten und Erweiterungen (?teil=…)

### `ImpersonateController` (class, `app/Http/Controllers/Admin/ImpersonateController.php`)

Ansicht als Rolle bzw. als Person starten und beenden (Core\Impersonation)

- `start(Http\Request $r): Http\Response` – POST /admin/impersonate {mode: role|user, value} – nur Administration (echtes Konto)
- `stop(Http\Request $r): Http\Response` – POST /admin/impersonate/stop – zurück zum eigenen Konto

### `InboxController` (class, `app/Http/Controllers/Admin/InboxController.php`)

Anfragen: Eingangs-Tabellen (verschlüsselt). Liste zeigt nur Metadaten; „Entschlüsseln“ mit dem geheimen Schlüssel (POST, weder gespeichert noch protokolliert) öffnet die Einträge der aktuellen Seite – nur für diese Antwort. Jedes Entschlüsseln, jede Status-/Zuweisungsänderung und jedes Löschen landet im Protokoll (inbox_log, ohne Inhalte).

- `assign(Http\Request $r, string $table, string $id): Http\Response`
- `clearAlerts(Http\Request $r): Http\Response` – Hinweise „Zustellung fehlgeschlagen“ (Core\Data\Delivery::alert) für die Tabellen des Benutzers ausblenden
- `delete(Http\Request $r, string $table, string $id): Http\Response`
- `index(Http\Request $r): Http\Response`
- `log(Http\Request $r): Http\Response`
- `status(Http\Request $r, string $table, string $id): Http\Response`

### `InviteController` (class, `app/Http/Controllers/Admin/InviteController.php`)

Einladungen (Core\Invites): „Person einladen“ unter Benutzer & Rollen (Recht users.manage) und die öffentliche Seite /admin/einladung/{token} zum Annehmen – Passkey (resources/js/invite.js, Core\Passkeys) und/oder Passwort.

- `accept(Http\Request $r, string $token): Http\Response` – Annehmen mit Passwort (Formular ohne JavaScript bzw. „Mit Passwort fortfahren“)
- `passkeyOptions(Http\Request $r, string $token): Http\Response` – Passkey: Optionen für navigator.credentials.create() – Konto-ID vorab (Benutzerkennung im Passkey), noch kein Konto
- `passkeyStore(Http\Request $r, string $token): Http\Response` – Passkey speichern → Konto anlegen (mit optionalem Passwort) → anmelden
- `resend(Http\Request $r, string $id): Http\Response`
- `revoke(Http\Request $r, string $id): Http\Response`
- `show(Http\Request $r, string $token, array $errors = [], array $old = []): Http\Response`
- `store(Http\Request $r): Http\Response`

### `KitsController` (class, `app/Http/Controllers/Admin/KitsController.php`)

Grundeinstellungen → Kits (Core\KitPackages): installierte Kits mit Herkunft, Version und nutzenden Websites, Kit-Paket als ZIP hochladen, hochgeladene Kits entfernen. Kits gelten für alle Websites der Installation – Recht wie bei Schriften (Integratoren, Netzwerk-Administration, Administration der Netzwerk-Website).

- `index(Http\Request $r): Http\Response`
- `install(Http\Request $r): Http\Response`
- `remove(Http\Request $r, string $name): Http\Response`

### `LandingController` (class, `app/Http/Controllers/Admin/LandingController.php`)

Grundeinstellungen → Landingpages: weitere Domains zeigen eine Seite bzw. einen Seitenzweig (Core\Landings).

- `check(Http\Request $r, string $id): Http\Response` – Status: DNS und Selbsttest (/health?landing=…) je Domain – Ergebnis bleibt bis zur nächsten Prüfung in der Sitzung
- `delete(Http\Request $r, string $id): Http\Response`
- `edit(Http\Request $r, string $id = '', array $errors = [], ?array $values = null): Http\Response`
- `index(Http\Request $r): Http\Response`
- `save(Http\Request $r, string $id = ''): Http\Response`

### `MailAccountsController` (class, `app/Http/Controllers/Admin/MailAccountsController.php`)

Grundeinstellungen → E-Mail-Konten (/admin/system/mail): weitere Konten der Website, Verwendung je Zweck, Testnachricht je Konto; auf der Netzwerk-Website für die Netzwerk-Administration zusätzlich das Netzwerk-Konto (Core\Mail\Accounts). Das Konto „Standard“ bleibt in den Grundeinstellungen (Reiter E-Mail-Versand).

- `delete(Http\Request $r, string $id): Http\Response`
- `static fields(bool $network = false): array` – Felder eines Kontos (Core\Fields, show_if je Versandart)
- `index(Http\Request $r, array $errors = [], ?array $draft = null, string $draftId = ''): Http\Response`
- `network(Http\Request $r): Http\Response` – E-Mail-Konto des Netzwerks speichern bzw. entfernen (Netzwerk-Dashboard)
- `networkTest(Http\Request $r): Http\Response` – Testnachricht über das E-Mail-Konto des Netzwerks (Netzwerk-Dashboard)
- `routes(Http\Request $r): Http\Response` – Verwendung: Zweck → Konto
- `save(Http\Request $r): Http\Response` – Konto anlegen bzw. ändern (id leer = neu)
- `test(Http\Request $r, string $id): Http\Response` – Testnachricht über ein bestimmtes Konto an die eigene Adresse (bzw. eingegebene)

### `MediaController` (class, `app/Http/Controllers/Admin/MediaController.php`)

Mediathek: Oberfläche + JSON-API.

- `adjust(Http\Request $r, string $id): Http\Response` – Bild anpassen für alle Verwendungen ({adjust: "sepia s120"} – leer = Original), Core\ImageFx
- `bulk(Http\Request $r): Http\Response` – Sammelaktionen: tag, untag, collect, uncollect, delete
- `chunk(Http\Request $r): Http\Response` – Nimmt ein Stück entgegen (multipart: chunk, upload_id, index, total, size)
- `collectionCreate(Http\Request $r): Http\Response`
- `collectionDelete(Http\Request $r, string $id): Http\Response`
- `collectionUpdate(Http\Request $r, string $id): Http\Response`
- `crop(Http\Request $r, string $id): Http\Response` – Zuschnitt für ein Bildformat festlegen ({ratio, rect: {x,y,w,h}} – rect null = entfernen)
- `delete(Http\Request $r, string $id): Http\Response`
- `detail(Http\Request $r, string $id): Http\Response`
- `edit(Http\Request $r, string $id): Http\Response` – Bild bearbeiten ({edit: {quad, quad_fit, flip_h, flip_v, rot, angle, fill, crop}} – leer/null = Original), Core\ImageEdit.
- `emptyTrash(Http\Request $r): Http\Response` – Papierkorb leeren (alle Dateien darin endgültig löschen)
- `finalize(Http\Request $r): Http\Response` – Schließt einen Upload ab: prüft Alt-Text, importiert bzw. ersetzt die Datei
- `fit(Http\Request $r, string $id): Http\Response` – Bild im Rahmen: Standard für alle Verwendungen ({fit: "contain blur"} – leer = automatisch), Core\ImageFit
- `fitOptions(Http\Request $r): Http\Response` – Bild im Rahmen: Farben des Kits als Vorschläge für den Hintergrund beim Einpassen
- `index(Http\Request $r): Http\Response`
- `list(Http\Request $r): Http\Response`
- `save(Http\Request $r, string $id): Http\Response`
- `share(Http\Request $r): Http\Response` – Vorhandene Dateien der Website in einen Pool verschieben (bleiben überall verwendet)
- `thumb(Http\Request $r, string $id): Http\Response` – Automatisches Vorschaubild eines Videos (Core\VideoThumbs) – lazy aus Raster, Liste, Auswahl und Feldern.
- `upload(Http\Request $r): Http\Response` – Einfacher Upload (Fallback ohne JavaScript / Editor.js-Endpoint)
- `useShared(Http\Request $r): Http\Response` – Pool-Datei auf dieser Website verwenden → Verweis-Eintrag (lokale ID)
- `zip(Http\Request $r): Http\Response` – Mehrere Dateien als ZIP herunterladen (?ids=1,2,3) – Originale mit lesbaren Namen

### `MediaTrackController` (class, `app/Http/Controllers/Admin/MediaTrackController.php`)

Untertitel, Kapitel und Transkripte für Videos/Audio der Mediathek (JSON für resources/js/_captions.js). Lesen: angemeldet. Ändern: „media.upload“ (Website) bzw. MediaPools::canEdit (geteilte Pools). KI-Transkription/-Übersetzung: zusätzlich „ai.use“ und die Fähigkeit transcribe bzw. text (Core\AI\MediaJobs).

- `cancel(Http\Request $r, string $jid): Http\Response`
- `delete(Http\Request $r, string $id, string $tid): Http\Response`
- `download(Http\Request $r, string $id, string $tid): Http\Response` – WebVTT herunterladen (auch Entwürfe – nur angemeldet)
- `index(Http\Request $r, string $id): Http\Response`
- `jobs(Http\Request $r): Http\Response` – Stand von Aufträgen (Polling): ids=1,2,3
- `show(Http\Request $r, string $id, string $tid): Http\Response`
- `store(Http\Request $r, string $id): Http\Response` – Anlegen: Datei (multipart „file“: .vtt/.srt) oder Cues (JSON)
- `transcribe(Http\Request $r, string $id): Http\Response` – KI-Transkription starten (Hintergrund-Auftrag)
- `transcript(Http\Request $r, string $id): Http\Response` – Transkript einer Sprache speichern (leer = entfernen); publish=1 → auf der Website zeigen
- `translate(Http\Request $r, string $id, string $tid): Http\Response` – Untertitel mit der Text-KI übersetzen (Hintergrund-Auftrag)
- `update(Http\Request $r, string $id, string $tid): Http\Response` – Editor speichert (Cues, Art, Sprache, Bezeichnung; status published = geprüft und veröffentlicht)

### `MessagesController` (class, `app/Http/Controllers/Admin/MessagesController.php`)

Verwaltung → Mitteilungen (Funktion „push“): Verfassen (sofort/geplant, Bestätigung mit Empfängerzahl), Verlauf aller Nachrichten (von Hand und automatisch) mit Details, Abbrechen, Duplizieren; Kanäle; Statistik; Website-Einbindung (Banner, Glocke). Rechte: push.view (Verlauf, Kanäle, Statistik), push.send (Verfassen, Kanäle ändern, Website-Einbindung). Aufbau wie die

Konstanten: `BASE`, `FOLDERS`

- `cancel(Http\Request $r, string $id): Http\Response`
- `channelArchive(Http\Request $r, string $id): Http\Response`
- `channelSave(Http\Request $r, ?string $id = null): Http\Response`
- `channels(Http\Request $r): Http\Response`
- `compose(Http\Request $r): Http\Response`
- `static counts(): array` – Zähler der Seitenleiste
- `index(Http\Request $r): Http\Response`
- `reach(Http\Request $r): Http\Response` – Erreichbare Geräte für eine Empfängerauswahl (JSON, live im Formular)
- `reset(Http\Request $r): Http\Response` – Verlauf und Zahlen zurücksetzen (z. B. nach Tests) – nur Administration
- `send(Http\Request $r): Http\Response`
- `stats(Http\Request $r): Http\Response`
- `website(Http\Request $r): Http\Response`
- `websiteSave(Http\Request $r): Http\Response`

### `NetworkController` (class, `app/Http/Controllers/Admin/NetworkController.php`)

Netzwerk-Administration: Übersicht aller Websites (/admin/network, nur Netzwerk-Website), Anmeldung auf anderen Websites per Einmal-Token (open → /admin/sso der Ziel-Website), Wartungsaktionen, Netzwerk-Konten, neue Websites.

Konstanten: `SECTIONS`

- `accountAction(Http\Request $r, string $id, string $action): Http\Response`
- `accountCreate(Http\Request $r): Http\Response`
- `action(Http\Request $r, string $key, string $action): Http\Response`
- `authPolicy(Http\Request $r): Http\Response` – Anmelde-Richtlinie der Netzwerk-Konten: erlaubte Verfahren, „nur Passkey“ (Core\Mfa, Einstellung sys.net_auth)
- `createSite(Http\Request $r): Http\Response` – Neue Website (gleicher Code wie `site:create`)
- `index(Http\Request $r, string $section = ''): Http\Response`
- `inviteResend(Http\Request $r, string $id): Http\Response`
- `inviteRevoke(Http\Request $r, string $id): Http\Response`
- `inviteStore(Http\Request $r): Http\Response` – „Netzwerk-Admin einladen“: E-Mail mit Link (7 Tage), Konto entsteht erst beim Annehmen – nur durch aktive Netzwerk-Konten
- `mediaUsages(Http\Request $r): Http\Response` – Verwendungen von Pool-Dateien auf DIESER Website (für das Löschen in geteilten Medien auf einer anderen Website):
- `open(Http\Request $r): Http\Response` – Einmal-Token ausstellen und zur Ziel-Website weiterleiten (von jeder Website aus, z. B. „Website wechseln“)
- `section(Http\Request $r, string $section): Http\Response`
- `sso(Http\Request $r): Http\Response`

### `PageController` (class, `app/Http/Controllers/Admin/PageController.php`)

Konstanten: `RESERVED_SLUGS`

- `apiCreate(Http\Request $r): Http\Response` – Seite anlegen (JSON): title, slug (optional), parent (ID | leer = oberste Ebene), position 'end' | 'before' | 'after' mit
- `apiSettings(Http\Request $r, string $id): Http\Response` – Seiteneinstellungen lesen (JSON)
- `apiSettingsSave(Http\Request $r, string $id): Http\Response` – Seiteneinstellungen speichern (JSON): title, slug, meta_title, meta_description, og_image (ID | null), status, menu,
- `apiTree(Http\Request $r): Http\Response` – Seitenbaum für „Neue Seite“: flach in Baumreihenfolge, nur Seiten der Sprache der aktuellen Seite (ohne Vorlagen)
- `create(Http\Request $r): Http\Response`
- `delete(Http\Request $r, string $id): Http\Response`
- `discard(Http\Request $r, string $id): Http\Response`
- `duplicate(Http\Request $r, string $id): Http\Response` – Seite duplizieren (als Entwurf, direkt dahinter)
- `edit(Http\Request $r, string $id): Http\Response`
- `index(Http\Request $r): Http\Response`
- `move(Http\Request $r, string $id): Http\Response` – Verschieben: {parent_id: ?int, before_id?: int, after_id?: int, index?: int} – before_id/after_id (Nachbarseite) vor index
- `noindex(Http\Request $r, string $id): Http\Response` – Seitenbaum „⋯ → Nicht indexieren / Indexieren erlauben“ (JSON) – wie der Haken in den Seiteneinstellungen
- `notFound(Http\Request $r): Http\Response` – Seite „Nicht gefunden (404)“ der Sprache anlegen (Entwurf mit Startinhalten des Kits) bzw. öffnen → Frontend-Editor
- `offline(Http\Request $r, string $id): Http\Response` – Offline nehmen (Seitenbaum, Werkzeugleiste): Status „Entwurf“, die veröffentlichte Fassung bleibt erhalten (Pages::unpublish).
- `preview(Http\Request $r, string $id): Http\Response` – Vorschau für die Seitenleiste im Seitenbaum (ohne Werkzeugleiste, noindex): ?stand=live = veröffentlichte Fassung,
- `publish(Http\Request $r, string $id): Http\Response`
- `quick(Http\Request $r, string $id): Http\Response` – Schnelländerungen: menu (bool), status
- `static reservedSlug(string $slug, ?int $parentId, ?string $lang): bool` – Nur ohne übergeordnete Seite und ohne Sprachpräfix; dazu jeder Ordner bzw. jede Datei in public/
- `restore(Http\Request $r, string $id, string $rev): Http\Response`
- `store(Http\Request $r): Http\Response`
- `translate(Http\Request $r, string $id): Http\Response` – Übersetzung anlegen (JSON): {lang} → neue Seite als Entwurf
- `update(Http\Request $r, string $id): Http\Response`

### `PageTemplateController` (class, `app/Http/Controllers/Admin/PageTemplateController.php`)

Einrichtung › Website › Seitenvorlagen: Vorlagen anlegen, gestalten, anordnen (Core\PageTemplates) – nur Administration.

- `create(Http\Request $r): Http\Response` – Neue Vorlage (leer oder aus einer Seite kopiert) → gleich im Block-Editor öffnen
- `fromPage(Http\Request $r, string $id): Http\Response` – Seitenbaum „⋯ → Als Vorlage speichern“: Kopie der Seite als neue Vorlage (JSON)
- `index(Http\Request $r, array $errors = [], ?array $values = null): Http\Response`
- `save(Http\Request $r): Http\Response`

### `PasskeyController` (class, `app/Http/Controllers/Admin/PasskeyController.php`)

Passkeys (Core\Passkeys, Richtlinie Core\Mfa): Anmeldung ohne Passwort (Schaltfläche und Autofill auf der Anmeldeseite), Passkeys im Konto hinzufügen, umbenennen, löschen; Auswahl bei verlangter Einrichtung; Wiederherstellungscodes nach dem ersten Passkey. JSON-Endpunkte mit CSRF (Header X-CSRF-Token), Challenge in der Sitzung. Skript: resources/js/passkey.js.

- `choose(Http\Request $r): Http\Response`
- `codes(Http\Request $r): Http\Response`
- `delete(Http\Request $r, string $id): Http\Response`
- `login(Http\Request $r): Http\Response`
- `loginOptions(Http\Request $r): Http\Response`
- `options(Http\Request $r): Http\Response` – Optionen für navigator.credentials.create()
- `static passwordlessOffered(): bool` – Anmeldung ohne Passwort auf dieser Website angeboten? Richtlinie der Website; Netzwerk-Konten brauchen zusätzlich
- `reauth(Http\Request $r): Http\Response` – Passwort bestätigen (für Passkeys verwalten nach mehr als 15 Minuten seit der Anmeldung)
- `reauthOptions(Http\Request $r): Http\Response` – Mit Passkey bestätigen statt mit dem Passwort (z. B. Konten ohne Passwort aus einer Einladung): Optionen für
- `reauthPasskey(Http\Request $r): Http\Response` – Antwort prüfen → Bestätigung gilt 15 Minuten (reauth_at, Mfa::recentAuth)
- `rename(Http\Request $r, string $id): Http\Response`
- `store(Http\Request $r): Http\Response` – Antwort von navigator.credentials.create() speichern

### `PasswordController` (class, `app/Http/Controllers/Admin/PasswordController.php`)

„Passwort vergessen“ (Core\PasswordReset): Anfrage-Seite /admin/passwort-vergessen (Link auf der Anmeldeseite) und Seite zum Link aus der E-Mail /admin/passwort/{token} – GET zeigt das Formular (ändert nichts), POST setzt das neue Passwort.

- `forgot(Http\Request $r): Http\Response`
- `forgotForm(Http\Request $r, array $vars = [], int $status = 200): Http\Response`
- `reset(Http\Request $r, string $token): Http\Response`
- `resetForm(Http\Request $r, string $token, array $errors = []): Http\Response`

### `PrefsController` (class, `app/Http/Controllers/Admin/PrefsController.php`)

Sammelseiten der Verwaltung (Core\AdminPages): „Einstellungen der Funktionen“ (/admin/einstellungen, Gruppe „Einstellungen“) mit einer Karte je Einstellungsseite von Funktionen und Erweiterungen, „Statistiken“ (/admin/statistiken, Gruppe „Werkzeuge“) für Berichte. Sichtbar ist, was die Rolle öffnen darf – jede Zielseite prüft ihre Rechte trotzdem selbst.

- `settings(Http\Request $r): Http\Response`
- `stats(Http\Request $r): Http\Response`

### `PresenceController` (class, `app/Http/Controllers/Admin/PresenceController.php`)

Präsenz der Redaktion (Core\Presence) – Anbindung an Studio: Seiten (type „page“, id = Seiten-ID) und Einträge (type „entry“, id = „{tabelle}:{id}“). Antworten nach dem dokumentierten Schema (Entwicklerhandbuch → Präsenz):

- `beat(Http\Request $r): Http\Response`
- `static hereAttrs(array $b): string` – Attribute für [data-presence-here] in der Werkzeugleiste (Seite bzw. Eintrag) – leer, wenn aus oder ohne Objekt
- `index(Http\Request $r): Http\Response`
- `static listAttrs(string $type = '', string $prefix = ''): string` – Attribute für Übersichten ([data-presence-list] bzw. [data-presence-board])
- `static on(): bool`
- `online(Http\Request $r): Http\Response` – Herzschlag der offenen Seite (alle 2 Minuten, nur sichtbarer Tab)
- `static personKey(array $u): string` – Personen-Schlüssel für „online“: Netzwerk-Konten installationsweit gleich (net:{uid}), sonst {website}:{id}
- `static seen(string $label, string $url): void` – Angemeldete Person ist online – Ort = $label (Seitentitel), $url (Pfad). Aufgerufen von jeder Verwaltungsseite (Layout),
- `static texts(): array` – Texte für presence.mjs (Verwaltungssprache)

### `PushController` (class, `app/Http/Controllers/Admin/PushController.php`)

Push-Benachrichtigungen in der Verwaltung (Core\Push): Konto → Benachrichtigungen (Gerät an-/abmelden, Geräte, Schalter je Ereignis, Testnachricht) und Grundeinstellungen → Push-Benachrichtigungen (Schlüssel anlegen). Anmeldung und CSRF über auth().

- `deleteDevice(Http\Request $r, string $id): Http\Response` – Gerät aus der Liste entfernen (Formular)
- `events(Http\Request $r): Http\Response` – Schalter je Ereignis (Formular events[schlüssel] = 0|1; JSON für sofortiges Speichern)
- `keys(Http\Request $r): Http\Response` – Grundeinstellungen → Push-Benachrichtigungen: fehlende VAPID-Schlüssel anlegen
- `subscribe(Http\Request $r): Http\Response` – Gerät der angemeldeten Person anmelden (JSON: subscription = PushSubscription.toJSON())
- `test(Http\Request $r): Http\Response` – Testnachricht an alle eigenen Geräte (höchstens 5 in 10 Minuten)
- `unsubscribe(Http\Request $r): Http\Response` – Dieses Gerät abmelden (JSON: endpoint) → state deleted (Browser-Abo kann weg) | unlinked (bleibt für Website-Abos)

### `RedirectController` (class, `app/Http/Controllers/Admin/RedirectController.php`)

Administration → Weiterleitungen: Liste, Bearbeiten, Adresse testen, Import/Export, 404-Protokoll (Core\Redirects).

- `bulk(Http\Request $r): Http\Response` – Sammelaktion: löschen, aktivieren, deaktivieren
- `delete(Http\Request $r, string $id): Http\Response`
- `edit(Http\Request $r, string $id = '', array $errors = [], ?array $values = null): Http\Response`
- `export(Http\Request $r): Http\Response`
- `import(Http\Request $r): Http\Response` – Import: Datei (CSV/JSON) oder eingefügter Text; „Nur prüfen“ speichert nichts
- `index(Http\Request $r): Http\Response`
- `notFound(Http\Request $r): Http\Response`
- `notFoundAction(Http\Request $r): Http\Response` – 404-Protokoll: Vorschlag übernehmen (ein Klick), Einträge ignorieren, Protokoll leeren
- `save(Http\Request $r, string $id = ''): Http\Response`
- `settings(Http\Request $r): Http\Response`

### `RedirectException` (class, `app/Http/Controllers/Admin/RedirectException.php`)

Wird geworfen, um aus einem Controller heraus umzuleiten (z. B. zum Login).

- `__construct(string $to)`

### `ReviewController` (class, `app/Http/Controllers/Admin/ReviewController.php`)

Prüf-Ebene „Eingereicht“ (Bereich KLXM AI): Änderungen über REST-API, MCP und KI ansehen, übernehmen, bearbeiten, ablehnen. Recht „review.manage“ (Standard: Administration), Funktion „review“.

- `action(Http\Request $r, string $id, string $action): Http\Response` – Übernehmen, Ablehnen, Bearbeiten & übernehmen, Als Entwurf übernehmen, Neu prüfen
- `bulk(Http\Request $r): Http\Response` – Sammelaktion: ausgewählte übernehmen oder ablehnen
- `index(Http\Request $r): Http\Response`
- `preview(Http\Request $r, string $id): Http\Response` – Vorschau einer Seite mit den vorgeschlagenen Blöcken (nichts wird gespeichert)
- `settings(Http\Request $r): Http\Response` – Einstellungen der Website: E-Mail bei neuen Einreichungen, KI-Übernahmen ebenfalls zur Freigabe
- `show(Http\Request $r, string $id): Http\Response`

### `RoleController` (class, `app/Http/Controllers/Admin/RoleController.php`)

Rollen anlegen und Rechte zuweisen (Recht: users.manage).

- `assign(Http\Request $r, string $id): Http\Response` – Rolle eines Benutzers ändern
- `delete(Http\Request $r, string $key): Http\Response`
- `edit(Http\Request $r, ?string $key = null): Http\Response`
- `save(Http\Request $r, ?string $key = null): Http\Response`

### `SearchController` (class, `app/Http/Controllers/Admin/SearchController.php`)

Übergreifende Suche (Spotlight, ⌘K): Aktionen, Seiten, Datensätze, Medien, Einstellungen, Hilfe. Ohne Suchbegriff: Aktionen und zuletzt geänderte Inhalte.

- `geocode(Http\Request $r): Http\Response` – Adresse → Koordinaten (für Kartenfelder; serverseitig über den zentralen Proxy)
- `search(Http\Request $r): Http\Response`

### `SettingsController` (class, `app/Http/Controllers/Admin/SettingsController.php`)

Zentrales Einstellungsformular des Themes (Titel und Felder vom Theme).

- `edit(Http\Request $r, array $errors = [], ?array $values = null): Http\Response`
- `preview(Http\Request $r): Http\Response` – Live-Vorschau: Startseite (bzw. ?page=ID) mit den ungespeicherten Formularwerten – nur im Speicher.
- `save(Http\Request $r): Http\Response`

### `SourceController` (class, `app/Http/Controllers/Admin/SourceController.php`)

Daten → Externe Quellen (Core\Sources): Quellen anlegen, Zuordnung mit Vorschau, abrufen, ZIP hochladen, Protokoll. Funktion „sources“ (Standard aus; Netzwerk-Administration/Integratoren schalten sie hier ein), Recht „sources.manage“.

- `createTable(Http\Request $r, string $id): Http\Response`
- `delete(Http\Request $r, string $id): Http\Response`
- `edit(Http\Request $r, string $id = ''): Http\Response`
- `entryStatus(Http\Request $r, string $handle, string $id): Http\Response` – Übernommenen Eintrag ein-/ausblenden (Redaktion mit Veröffentlichungsrecht)
- `index(Http\Request $r): Http\Response`
- `save(Http\Request $r, string $id = ''): Http\Response`
- `sync(Http\Request $r, string $id): Http\Response`
- `toggle(Http\Request $r): Http\Response` – Funktion für diese Website ein-/ausschalten (nur Netzwerk-Administration/Integratoren, solange config nichts vorgibt)
- `upload(Http\Request $r, string $id): Http\Response` – OpenImmo: ZIP (openimmo.xml + Bilder) oder XML hochladen und sofort einlesen

### `SupportController` (class, `app/Http/Controllers/Admin/SupportController.php`)

Support & Wissensdatenbank (Core\Support): Meldungen, Fragen & Antworten, Wissensartikel. Alle Seiten bekommen die Bereichsnavigation „Support“ (views/support/_nav.php, Drill-down wie „Daten“).

- `accept(Http\Request $r, string $id): Http\Response`
- `all(Http\Request $r): Http\Response`
- `answerDelete(Http\Request $r, string $id): Http\Response`
- `answerSave(Http\Request $r, string $id): Http\Response`
- `answerUpdate(Http\Request $r, string $id): Http\Response`
- `article(Http\Request $r, string $id): Http\Response`
- `articleDelete(Http\Request $r, string $id): Http\Response`
- `articleEdit(Http\Request $r, ?string $id = null): Http\Response`
- `articleRestore(Http\Request $r, string $id, string $rev): Http\Response`
- `articleSave(Http\Request $r, ?string $id = null): Http\Response`
- `ask(Http\Request $r, ?string $id = null): Http\Response`
- `askSave(Http\Request $r, ?string $id = null): Http\Response`
- `assign(Http\Request $r, string $id): Http\Response`
- `create(Http\Request $r): Http\Response`
- `file(Http\Request $r, string $id): Http\Response` – Bildschirmfoto – nur für Personen, die die Meldung sehen dürfen
- `helpful(Http\Request $r, string $id): Http\Response`
- `issue(Http\Request $r, string $id): Http\Response`
- `issueDelete(Http\Request $r, string $id): Http\Response`
- `kb(Http\Request $r): Http\Response`
- `mine(Http\Request $r): Http\Response`
- `prefs(Http\Request $r): Http\Response` – Staff: Benachrichtigungen an/aus
- `publishForm(Http\Request $r, string $id): Http\Response` – Aus gelöster Meldung: anonymisierter Entwurf zum Bearbeiten
- `question(Http\Request $r, string $id): Http\Response`
- `questionDelete(Http\Request $r, string $id): Http\Response`
- `questions(Http\Request $r): Http\Response`
- `reply(Http\Request $r, string $id): Http\Response` – Antwort / interne Notiz (+ optional Status)
- `similar(Http\Request $r): Http\Response` – Vorschläge beim Melden: ähnliche Wissensartikel und beantwortete Fragen (JSON)
- `status(Http\Request $r, string $id): Http\Response`
- `store(Http\Request $r): Http\Response`
- `tags(Http\Request $r): Http\Response`
- `voteAnswer(Http\Request $r, string $id): Http\Response`
- `voteQuestion(Http\Request $r, string $id): Http\Response`

### `SystemController` (class, `app/Http/Controllers/Admin/SystemController.php`)

Grundeinstellungen (nur Rolle „admin“).

- `adminPath(Http\Request $r): Http\Response` – Adresse der Verwaltung ändern (Core\AdminPath) – gilt für alle Websites der Installation; bei mehreren Websites nur
- `clearCache(Http\Request $r): Http\Response`
- `clearProxy(Http\Request $r): Http\Response`
- `edit(Http\Request $r, array $errors = [], ?array $values = null, ?string $newSecret = null): Http\Response`
- `generateKeys(Http\Request $r): Http\Response`
- `iconPreview(Http\Request $r): Http\Response` – Live-Vorschau des App-Icons aus den (noch nicht gespeicherten) Formularwerten
- `poolCreate(Http\Request $r): Http\Response`
- `poolDelete(Http\Request $r, string $key): Http\Response`
- `poolUpdate(Http\Request $r, string $key): Http\Response`
- `save(Http\Request $r): Http\Response`
- `sharedCreate(Http\Request $r): Http\Response` – Geteilte Tabelle anlegen: neu (aus Vorlage) oder aus einer vorhandenen Tabelle dieser Website
- `sharedUnshare(Http\Request $r, string $key): Http\Response` – Freigabe beenden: wieder eigene Tabelle der Eigentümer-Website (Bestätigung mit dem Kurznamen)
- `sharedUpdate(Http\Request $r, string $key): Http\Response` – Bezeichnung, Mitglieder und Schalter einer geteilten Tabelle ändern (Eigentümer-Website oder Integrator)
- `testMail(Http\Request $r): Http\Response`

### `TwoFactorController` (class, `app/Http/Controllers/Admin/TwoFactorController.php`)

Zwei-Faktor-Anmeldung (TOTP): Code nach dem Passwort abfragen, im Konto einrichten/abschalten, Wiederherstellungscodes. Netzwerk-Konten richten 2FA auf der Netzwerk-Website ein; Schatten-Konten anderer Websites haben keine eigene 2FA.

- `challenge(Http\Request $r): Http\Response`
- `challengeForm(Http\Request $r, string $error = ''): Http\Response`
- `disable(Http\Request $r): Http\Response`
- `enable(Http\Request $r): Http\Response`
- `passkey(Http\Request $r): Http\Response`
- `passkeyOptions(Http\Request $r): Http\Response` – Passkey als zweiter Faktor: Optionen für navigator.credentials.get() (nur Passkeys dieses Kontos und dieser Domain)
- `recovery(Http\Request $r): Http\Response`
- `setup(Http\Request $r, string $error = ''): Http\Response`

### `UserController` (class, `app/Http/Controllers/Admin/UserController.php`)

Konstanten: `SECTIONS`

- `account(Http\Request $r, array $errors = [], array $old = []): Http\Response`
- `changeEmail(Http\Request $r, string $id): Http\Response` – E-Mail-Adresse eines Kontos direkt ändern (Benutzer & Rollen) – ohne Bestätigung; Hinweis an beide Adressen, Protokoll.
- `delete(Http\Request $r, string $id): Http\Response`
- `index(Http\Request $r, array $errors = [], array $old = [], string $section = ''): Http\Response` – Übersicht (Dashboard) bzw. eine Unterseite; Formularfehler aus „Einladen & Anlegen“ landen wieder dort
- `static isNetworkAccount(int $id): bool` – Netzwerk-Konto (Rolle network oder Schatten-Konto) – lokal nicht änderbar
- `static mayManageRole(?string $roleKey): bool` – Darf die angemeldete Person ein Konto mit dieser Rolle anlegen, ändern, löschen bzw. diese Rolle vergeben? Nur Rollen, deren Rechte
- `resetTwoFactor(Http\Request $r, string $id): Http\Response` – Zwei-Faktor-Anmeldung eines anderen lokalen Kontos zurücksetzen (z. B. Telefon verloren) – beendet dessen Sitzungen
- `static roleOf(int $id): string` – Rolle eines Kontos (leer, wenn es nicht existiert)
- `saveAccent(Http\Request $r): Http\Response` – Persönliche Akzentfarbe der Verwaltung (Core\Accent)
- `saveAccount(Http\Request $r): Http\Response` – Passwort ändern – oder als erstes Passwort festlegen (Konten nur mit Passkey, z. B. aus einer Einladung: statt des
- `saveLocale(Http\Request $r): Http\Response` – Oberflächensprache des eigenen Kontos
- `section(Http\Request $r, string $section): Http\Response` – /admin/users/{section}: Personen, Einladen & Anlegen, Rollen, Anmeldung & Sicherheit
- `store(Http\Request $r): Http\Response`
- `twoFactorRoles(Http\Request $r): Http\Response` – Anmeldung & Sicherheit: erlaubte Verfahren, zweiter Faktor je Rolle, Übergangsfrist (Core\Mfa, Einstellung sys.auth_policy;

### `VersionsController` (class, `app/Http/Controllers/Admin/VersionsController.php`)

Versionen ansehen und wiederherstellen (resources/js/versions.mjs) – Seiten (Tabelle revisions) und Einträge (Core\Data\Revisions).

- `static attrs(string $endpoint): string` – Attribute für einen Knopf, der die Versionen öffnet (admin.js lädt resources/js/versions.mjs erst beim Klick)
- `entryRestore(Http\Request $r, string $handle, string $id, string $rev): Http\Response`
- `entryVersions(Http\Request $r, string $handle, string $id): Http\Response`
- `pagePreview(Http\Request $r, string $id, string $rev): Http\Response` – HTML der Seite in einem Stand (rev 0 = jetzt); ?mark=1 markiert neue/geänderte Blöcke gegenüber dem vorherigen Stand
- `pageVersions(Http\Request $r, string $id): Http\Response`
- `static texts(): array` – Texte für resources/js/versions.mjs (Sprache der Verwaltung)

### `WelcomeController` (class, `app/Http/Controllers/Admin/WelcomeController.php`)

Willkommen-Bildschirm beim Erststart einer Website (Core\Onboarding): erklärt die nächsten Schritte und lässt die Administration Kit und Startinhalte wählen – für eine einzelne Installation genauso wie für jede Website eines Netzwerks. Nach der Wahl spielt die nächste Anfrage die Inhalte ein; danach führt die Übersicht mit ihrer Checkliste weiter.

- `index(Http\Request $r, array $errors = []): Http\Response`
- `save(Http\Request $r): Http\Response`

## Core\Mail

### `Accounts` (class, `app/Mail/Accounts.php`)

E-Mail-Konten: „Standard“ (Grundeinstellungen → E-Mail-Versand, sys.mail_*), weitere Konten der Website (sys.mail_accounts) und das Netzwerk-Konto der Installation (Netzwerk-Datenbank, Tabelle network_mail). Je Zweck (Core\Mail\Purposes) wählt die Website ein Konto (sys.mail_routes); Mails für Netzwerk-Konten laufen nur über das Netzwerk-Konto.

Konstanten: `TRANSPORTS`, `SECRET_KEYS`, `STANDARD_KEYS`

- `static all(): array` – Alle Konten, die diese Website wählen kann: standard, weitere, network (wenn eingerichtet)
- `static describe(array $a): string` – Anzeige ohne Geheimnisse (Verwaltung, Health)
- `static dsn(array $a, bool $requireTls = false): string`
- `static extra(): array` – Weitere Konten der Website (ohne Standard) – Geheimnisse entschlüsselt
- `static find(string $id): ?array`
- `static health(): array` – Prüfungen für health (null = Warnung, blockiert nichts): Netzwerk-Konto in Installationen mit mehreren Websites, Zwecke
- `static network(): ?array`
- `static normalize(array $a): array`
- `static ready(array $a): bool` – Konto versandbereit (ohne Verbindungstest)?
- `static route(string $purpose, bool $network = false): array` – Konto für einen Versand. $network = Mail für ein Netzwerk-Konto (Einladung, Sicherheit): nur das Netzwerk-Konto –
- `static routes(): array` – Zuordnung Zweck → Konto (sys.mail_routes)
- `static saveExtra(array $accounts): void` – Konten speichern (ohne Standard) – Geheimnisse verschlüsselt
- `static saveNetwork(?array $a): void` – Netzwerk-Konto speichern (null = entfernen)
- `static saveStandard(array $a): void` – Konto „Standard“ speichern (Grundeinstellungen sys.mail_*; Passwort verschlüsselt über Settings)
- `static standard(): array` – Konto „Standard“ aus den Grundeinstellungen (bisheriger Einzel-Versand)
- `static transport(array $a, bool $requireTls = false): Symfony\Component\Mailer\Transport\TransportInterface` – Symfony-Transport für ein Konto (Versandwege der Erweiterungen über Extension::mailTransport)

### `Purposes` (class, `app/Mail/Purposes.php`)

Zwecke des E-Mail-Versands – je Zweck wählt die Website ein Konto (Core\Mail\Accounts::route). Mailer::send(…, ['purpose' => …]); ohne Angabe: 'account' bei Mails zur Kontosicherheit, sonst 'notify'. Erweiterungen melden eigene Zwecke an (Extension::mailPurpose, Schlüssel „ext:{erweiterung}.{name}“, z. B. ein Newsletter).

Konstanten: `CORE`

- `static all(): array`
- `static valid(string $key): bool`

### `SelfTest` (class, `app/Mail/SelfTest.php`)

php bin/console mail:selftest [--site=…] – E-Mail-Konten: Zuordnung je Zweck, Rückfall, Netzwerk-Mails nur übers Netzwerk-Konto, verschlüsselte Ablage, DSN, Versandwege und Zwecke von Erweiterungen, Absender/Antwortadresse/Kopfzeilen in der fertigen Mail (Ablage statt Versand). Einstellungen werden danach zurückgesetzt; Datenbank in einer Transaktion.

- `static run(): int`

## Core\Network

### `Network` (class, `app/Network/Network.php`)

Netzwerk-Administration: zentrale Konten für alle Websites einer Installation.

Konstanten: `USER_COLUMNS`, `TOKEN_TTL`

- `static account(string $email): ?array` – Netzwerk-Konto nach E-Mail (nur Rolle network)
- `static accountActive(int $uid): bool` – Ist das Netzwerk-Konto (Netzwerk-Website) noch vorhanden und nicht gesperrt? – z. B. für App-Passwörter ohne Sitzung
- `static accountById(int $id): ?array`
- `static accounts(): array`
- `static appKey(): string` – app_key der Netzwerk-Website (verschlüsselt die 2FA-Geheimnisse der Netzwerk-Konten)
- `static config(string $site): Config` – Konfiguration einer Website (inkl. Standardwerten)
- `static consume(string $token, string $ua): array|string` – Token prüfen und verbrauchen. @return array{uid:int, path:string} oder Fehlertext
- `static createAccount(string $email, string $password, string $name = ''): int` – Netzwerk-Konto anlegen (Konsole, Übersicht)
- `static db(): Database` – Datenbank der Netzwerk-Website (auf der Netzwerk-Website: die eigene)
- `static ensureKey(): bool` – network_key in config.local.php ergänzen (idempotent). @return bool vorhanden bzw. angelegt
- `static ensureSchema(Database $db): void` – Tabellen der Netzwerk-Datenbank (nur dort; idempotent)
- `static install(): array` – Konfiguration der Installation (config.php ← config.local.php) – ohne Werte einzelner Websites
- `static isNetworkSite(): bool`
- `static isNetworkUser(?array $u = null): bool` – Ist das (angemeldete) Konto eine Netzwerk-Administration? Auf anderen Websites nur als geprüftes Schatten-Konto.
- `static issue(int $uid, string $site, string $path, string $ua): string` – Token für die Anmeldung auf $site ausstellen; Nonce landet in der Netzwerk-Datenbank
- `static key(): string` – Installationsschlüssel für Netzwerk-Tokens. Fehlt er, wird er in config/config.local.php ergänzt
- `static limiter(): RateLimiter` – Begrenzung in der Netzwerk-Datenbank (gilt über alle Websites hinweg, z. B. je Netzwerk-Konto)
- `static log(string $action, ?string $site, ?string $email = null, string $detail = ''): void`
- `static loginShadow(array $n, string $via): ?string` – Netzwerk-Konto auf dieser Website anmelden: Schatten-Konto anlegen bzw. aktualisieren und Sitzung starten.
- `static recentLog(int $n = 25): array`
- `static rememberUrl(): void` – Adresse der Verwaltung dieser Website merken, wenn sie keine eigene Domain hat (Rückweg „Netzwerk-Übersicht“)
- `static reset(): void` – Für Tests/CLI: zwischengespeicherte Verbindung verwerfen
- `static resetTwoFactor(int $id): void`
- `static safePath(string $p): string` – Nur Verwaltungspfade dieser Website als Ziel – oder „/“ (Startseite, Wechsel aus der Werkzeugleiste der Website)
- `static setDisabled(int $id, bool $off): void` – Sperren, entsperren, 2FA (App und Passkeys) zurücksetzen – beendet alle Sitzungen des Kontos auf allen Websites (auth_ver)
- `static siteKey(): string`
- `static siteLink(string $key, string $path): string` – Adresse eines Pfads auf einer anderen Website (berücksichtigt url_rewrite der Ziel-Website)
- `static siteUrl(string $key): string` – Basisadresse einer Website (config base_url → erste Domain; http nur für lokale Test-Domains ohne HTTPS)
- `static stillValid(int $uid, int $ver): bool`
- `static syncShadowEmail(int $nid, string $email): array` – Neue E-Mail-Adresse eines Netzwerk-Kontos sofort in die Schatten-Konten der anderen Websites übernehmen
- `static uaHash(string $ua): string`
- `static uid(array $u): int` – ID des Netzwerk-Kontos zum angemeldeten Konto

### `SiteIcon` (class, `app/Network/SiteIcon.php`)

App-Icon einer Website für die Netzwerk-Verwaltung (Übersicht, Website-Umschalter).

- `static for(string $key, ?array $stats = null): array`
- `static letters(string $key, string $label, string $text = '', string $bg = ''): string` – Buchstaben-Kachel als SVG (data-URI)

### `Stats` (class, `app/Network/Stats.php`)

Kennzahlen und Wartungsaktionen je Website für die Netzwerk-Übersicht (/admin/network). Liest die Datenbanken der Websites direkt (nur lesend, bis auf Wartungsmodus); Ergebnisse 60 s in storage/cache/network. Anfragen (Eingangs-Tabellen) werden nur gezählt – Inhalte bleiben verschlüsselt.

Konstanten: `TTL`, `SIZE_TTL`

- `static all(bool $fresh = false): array`
- `static backup(string $key): string` – Sicherung über die Kommandozeile (gleicher Code wie `site:backup`) in einem eigenen PHP-Prozess.
- `static cached(string $key): ?array` – Zuletzt gespeicherte Kennzahlen ohne Neuberechnung (z. B. für Symbole im Website-Umschalter)
- `static clearCache(string $key): void`
- `static db(string $key): Database`
- `static dirSize(string $dir): array`
- `static forget(string $key): void`
- `static lastBackup(string $key): ?array` – Letzte Sicherung (site:backup legt storage/backups/{key}-JJJJMMTT-HHMMSS.tar.gz an)
- `static phpBinary(): string` – PHP-Kommandozeile für Hintergrund-Arbeiter (unter PHP-FPM ist PHP_BINARY der FPM-Prozess).
- `static poolSize(string $key): array`
- `static setMaintenance(string $key, bool $on): void`
- `static shared(): array`
- `static site(string $key, bool $fresh = false): array`
- `static warnings(array $s): array` – Warnungen einer Website (für Zusammenfassung und Filter)

## Core\Push

### `Channels` (class, `app/Push/Channels.php`)

Kanäle der Push-Benachrichtigungen, die Besucher abonnieren können: Tabellen-Kanäle  „data:{tabelle}“ – automatisch aus Datentabellen mit „Besucher können neue Einträge abonnieren“ (Core\Push\Topics), neue Einträge lösen eine Mitteilung aus

Konstanten: `PREFIX`

- `static all(bool $publicOnly = false): array` – Alle Kanäle einheitlich: [Thema => ['topic', 'kind' (table|free), 'name', 'description', 'public', 'archived', 'id'|'handle', 'table'?]].
- `static archive(array $c, bool $on): void` – Archivieren bzw. wiederherstellen
- `static ensureTable(Database $db): void`
- `static find(int $id): ?array`
- `static free(): array` – Freie Kanäle (auch archivierte) in Reihenfolge
- `static label(string $topic): string` – Lesbare Bezeichnung eines Themas (auch für ehemalige Kanäle)
- `static options(): array` – Auswahl für Blöcke und Formulare: [Thema => Name] (öffentliche Kanäle; wirft nie – Blockdefinitionen laden früh)
- `static publicTopics(): array` – Themen, die Besucher abonnieren dürfen
- `static save(?array $cur, array $in): array` – Kanal anlegen bzw. ändern → [id, Fehler[]]
- `static subscribers(string $topic): int` – Abos eines Kanals (gültiger Schlüssel)

### `Compose` (class, `app/Push/Compose.php`)

Mitteilungen von Hand (Verwaltung → Mitteilungen → Verfassen): Titel, Text, Ziel (Linkauswahl: page:ID, entry:…, Pfad oder Adresse dieser Website), optional Bild (Mediathek → Mitteilung „image“, wo der Browser es zeigt), Empfänger (Kanäle für Besucher und/oder Personen der Verwaltung nach Rolle bzw. einzeln – mit ihrem Schalter „Mitteilungen der Redaktion“),

Konstanten: `EVENT`, `MAX_DAYS`

- `static available(): bool` – Features-Prüfung für Seiten und Routen
- `static cancel(int $id): bool` – Geplante Nachricht abbrechen → true, wenn sie noch geplant war
- `static create(array $d, ?int $userId): int` – Mitteilung anlegen: sofort (vorgemerkt + nebenbei gesendet) oder geplant → ID
- `static due(?Database $db = null): int` – Fällige geplante Nachrichten vormerken → Anzahl
- `static fanout(int $id, ?Database $db = null): int` – Empfänger einer Nachricht ermitteln und vormerken (jedes Gerät einmal)
- `static maybeDue(): void` – Ohne Cron: bei Verwaltungsaufrufen fällige Planungen nebenbei verschicken (billige Prüfung)
- `static people(array $targets): array` – Personen (IDs) nach Rollen und Auswahl, die den Schalter „Mitteilungen der Redaktion“ an haben und nicht gesperrt sind
- `static prefill(array $m): array` – Formularwerte aus einer bestehenden Nachricht (Duplizieren/Erneut senden als Entwurf)
- `static reach(array $targets): array` – Erreichbare Geräte für Empfänger (vor dem Senden angezeigt): ['visitors' => Geräte über Kanäle, 'staff' => Geräte der Redaktion,
- `static resolve(string $link): ?string` – Ziel → absolute Adresse dieser Website (null = fremd/ungültig)
- `static state(array $m): string` – Status zur Anzeige: geplant | wartet | sendet | gesendet | abgebrochen
- `static summary(array $m): string` – Empfänger in Worten (Verlauf): „Kanäle: News, Notdienst · Rollen: Redaktion · 2 Personen“
- `static validate(array $in): array` – Eingaben prüfen → [Daten, Fehler] (Daten: title, body, link, url, image_id, image, targets{topics, roles, users}, at|null)

### `Console` (class, `app/Push/Console.php`)

Kommandozeile der Push-Benachrichtigungen: push:send [--all] [--limit=N]          fällige Zustellungen senden (Cron: jede Minute bis alle 5 min, --all = alle Websites) push:status [--all]                    Funktion, Schlüssel, Abos, Warteschlange, letzter Versand

- `static run(string $cmd, array $args, string $console): int`

### `Hooks` (class, `app/Push/Hooks.php`)

Ereignisse des Cores → Push-Mitteilungen an Personen der Verwaltung (Schalter unter Konto → Benachrichtigungen). Inhalte bleiben knapp: Anfragen ohne Inhalt (nur Eingang und Website), Chat mit Absender und Textanfang (Ende-zu-Ende- verschlüsselt an das eigene Gerät), Freigaben mit Gegenstand. Alle Aufrufe werfen nie.

- `static chat(array $room, array $message, array $mentions): void` – Chat-Nachricht (Messages::send): Direktnachricht → die anderen Personen des Gesprächs, Kanal → erwähnte Personen.
- `static formEntry(array $t, int $id, string $status): void` – Neuer Eintrag über das Formular einer Inhaltstabelle (DataForms::submit) – an alle mit data.edit für diese Tabelle
- `static localUsers(array $keys): array` – Personen-Schlüssel des Chats → Benutzer-IDs dieser Website
- `static request(array $t): void` – Neue Anfrage in einem Eingang (DataForms::submit) – an alle mit requests.read für diese Tabelle
- `static review(int $id, string $summary, string $label): void` – Neue Einreichung unter „Eingereicht“ (Review\Queue::run) – an alle mit review.manage

### `Keys` (class, `app/Push/Keys.php`)

VAPID-Schlüssel der Installation (RFC 8292): ein P-256-Schlüsselpaar für alle Websites, abgelegt wie network_key in config/config.local.php ('push_vapid_public', 'push_vapid_private' – Base64url, nie im Repository, nie in config/sites/*.php; Website-Konfigurationen können die Werte nicht setzen). Erzeugt beim ersten Gebrauch (Funktion „push“ eingeschaltet,

Konstanten: `PUBLIC`, `PRIVATE`, `CREATED`

- `static ensure(): bool` – Schlüssel anlegen, falls sie fehlen → true, wenn danach welche da sind
- `static fingerprint(): string` – Kurzer Fingerabdruck des öffentlichen Schlüssels (je Abo gespeichert; wechselt mit neuen Schlüsseln)
- `static get(): ?array` – Schlüsselpaar roh: ['public' => 65 Byte, 'private' => 32 Byte, 'created' => ?string] oder null
- `static override(?array $keys): void` – Schlüssel nur im Speicher vorgeben (Selbsttest; null = wieder aus der Datei lesen)
- `static publicB64(): string` – Öffentlicher Schlüssel als Base64url (applicationServerKey der Browser) bzw. ''
- `static ready(): bool`
- `static regenerate(): bool` – Neues Schlüsselpaar (alle Abos werden ungültig) → true bei Erfolg
- `static reset(): void` – Zwischenspeicher verwerfen (Tests, nach dem Schreiben)
- `static subject(): string` – Kontakt für die Push-Dienste (VAPID „sub“): config 'push_subject' (mailto: oder https:), sonst Absender der E-Mails,

### **API** `Push` (class, `app/Push/Push.php`)

Push-Benachrichtigungen (Funktion „push“, Standard aus) – echte Web-Push-Mitteilungen auch bei geschlossenem Tab.

Konstanten: `FEATURE`, `SW_PATH`, `SW_SCOPE`, `API`, `HOSTS`, `MAX_ATTEMPTS`, `MAX_FAILURES`, `TOPIC_PER_HOUR`, `DEVICE_PER_10MIN`, `VISITOR_PER_HOUR`

- `static cronAt(): string` – Letzter Lauf von push:send per Cron/Kommandozeile ('Y-m-d H:i:s') oder ''
- `static cronBeat(): void` – Cron-Herzschlag: push:send hält eine Datei aktuell (keine Einstellung – das würde den Seiten-Cache leeren)
- `static delete(int $id, ?Database $db = null, ?string $stat = 'gone'): void` – Abo löschen (offene Zustellungen werden verworfen). $stat: Art für die Statistik ('gone' = Push-Dienst/Fehler; null = schon gezählt)
- `static deviceLabel(string $ua): string` – Kurzbezeichnung des Geräts aus dem User-Agent (ohne Versionsnummern), z. B. „Chrome · macOS“
- `static devices(int $userId): array` – Geräte einer Person (neueste zuerst)
- `static enabled(): bool` – Einsatzbereit: Funktion an und VAPID-Schlüssel vorhanden (fehlen sie, werden sie beim ersten Gebrauch angelegt)
- `static endpointAllowed(string $url): bool` – Endpunkt erlaubt? Nur https auf Port 443 und nur bekannte Push-Dienste (kein SSRF über erfundene Endpunkte)
- `static ensureTable(Database $db): void` – Tabellen anlegen (idempotent, nur ergänzend) – aus Database::migrate
- `static events(): array` – Ereignisse: key => [label, help, perm (Recht, das die Person haben muss), feature (Funktion), default (Schalter vorbelegt), owner].
- `static featureOn(?string $feature): bool` – Funktion eines Ereignisses an? (die eigene Funktion „push“ über on(), damit der Selbsttest sie erzwingen kann)
- `static find(string $endpoint, ?Database $db = null): ?array` – Abo-Zeile zu einem Endpunkt
- `static flush(): void` – Zwischenspeicher verwerfen (nach dem Umschalten der Funktion, Tests)
- `static health(): array` – Zeilen für `php bin/console health` (nur Hinweise)
- `static hosts(): array` – Erlaubte Hosts der Push-Dienste (Kern + config 'push_hosts')
- `static icon(): string` – Symbol der Mitteilungen: App-Icon der Website (192 px), absolut
- `static kick(): void` – Nach der Antwort eine kleine Menge senden (einmal je Anfrage; nicht auf der Kommandozeile – dort push:send)
- `static notifyTopic(string $topic, array $payload, string $event = 'topic', array $o = []): int` – Mitteilung an alle Abos eines Themas (Besucher und Personen, die es abonniert haben), z. B. „data:aktuelles“.
- `static notifyUsers(array|string $who, Closure|array $payload, string $event, array $o = []): int` – Mitteilung an Personen der Verwaltung (alle ihre Geräte), z. B. aus einer Erweiterung:
- `static on(): bool` – Funktion eingeschaltet (unabhängig von den Schlüsseln)
- `static origin(): string` – Ursprung der Website für Adressen in Mitteilungen: kanonische Adresse bzw. aufgerufene Domain, auf der Kommandozeile (Cron)
- `static owns(array $row, string $authRaw): bool` – Gehört das Auth-Geheimnis zum gespeicherten Abo? (Besucher weisen sich so aus – nur ihr Browser kennt es)
- `static parse(mixed $sub): ?array` – Abo aus dem Browser (PushSubscription.toJSON(): endpoint, keys.p256dh, keys.auth) prüfen
- `static payload(array $p): array` – Inhalt einer Mitteilung: nur title, body, url, tag, icon, image, quiet, lang, ts – Text ohne HTML, gekürzt; Adresse absolut auf dieser
- `static privacyText(): string` – Vorschlag für die Datenschutzerklärung (Grundeinstellungen → Push-Benachrichtigungen, Tabelleneinstellungen)
- `static process(int $limit = 500, float $budget = 50): array` – Fällige Zustellungen senden (eine Sperre je Website). → Zähler sent, retry, failed, gone, expired (+ locked/keys)
- `static registerEvent(string $key, string $label, ?string $perm = null, array $o = []): void` – Ereignis einer Erweiterung anmelden (Extension::pushEvent) – Schlüssel a–z, 0–9, Punkt, Bindestrich, Unterstrich
- `static removeTopics(array $row, ?array $topics): ?string` – Themen eines Abos entfernen (null = alle) → 'deleted', 'updated' oder null (unbekannt)
- `static saveUserEvents(int $userId, array $on): void` – Schalter speichern (nur bekannte Ereignisse)
- `static status(): array` – Stand für Grundeinstellungen → Push-Benachrichtigungen und push:status
- `static store(array $s, ?int $userId, array $topics = [], ?string $lang = null, string $ua = '', ?Database $db = null): array` – Abo speichern bzw. auffrischen → [id, null] oder [null, Fehlercode]. $userId: Gerät einer Person (null lässt eine bestehende
- `static test(int $userId): array` – Testnachricht an alle Geräte einer Person – sofort gesendet (ohne Schalter je Ereignis).
- `static topicsOf(array $row): array`
- `static topicsString(array $topics): ?string` – Themen als „,data:news,data:termine,“ (Suche mit LIKE '%,thema,%')
- `static unlinkUser(int $userId, ?int $id = null, ?string $endpoint = null): ?string` – Gerät einer Person abmelden → 'deleted' (keine Themen mehr), 'unlinked' (bleibt für abonnierte Themen) oder null
- `static unregisterEvent(string $key): void` – Ereignis wieder abmelden (Selbsttest)
- `static userCan(array $u, ?string $perm, ?string $table = null, ?array $roles = null): bool` – Darf das Konto das (Rolle + Funktionsumfang)? Gesperrte Konten nie.
- `static userEvents(int $userId): array` – Schalter einer Person je Ereignis (gespeichert oder Vorgabe)
- `static validTopic(string $t): bool`
- `static visibleEvents(array $user): array` – Ereignisse, die eine Person unter Konto → Benachrichtigungen sieht (Funktion an, Recht vorhanden)
- `static visitorsAllowed(): bool` – Darf in dieser Umgebung an Besucher gesendet werden? (staging: nur mit config 'push_staging_visitors' => true)

### `SelfTest` (class, `app/Push/SelfTest.php`)

php bin/console push:selftest – ohne Netz und ohne bleibende Änderungen: - Verschlüsselung: Testvektor aus RFC 8291 (Anhang A) Byte für Byte, Rundlauf mit Empfänger-Schlüsseln (wie ein Browser entschlüsselt), verschiedene Längen und Füllbytes, Manipulation und falsches Geheimnis werden erkannt, Höchstlänge;

- `static run(): array`

### `Stats` (class, `app/Push/Stats.php`)

Statistik der Push-Benachrichtigungen – datensparsam: nur Zähler je Tag, Kanal und Art (Tabelle push_stats), nie Endpunkte, IP-Adressen, Konten oder Geräte. Arten: sub (neues Abo eines Kanals), unsub (abbestellt), gone (vom Push-Dienst abgelaufen bzw. nach Fehlversuchen oder Schlüsselwechsel gelöscht). Kanal „@staff“ = Geräte der Redaktion (Konto → Benachrichtigungen).

Konstanten: `STAFF`, `KINDS`

- `static card(): array` – Kennzahlen für die Karte „Mitteilungen“ der Übersicht (zwischengespeichert von Core\Dashboard\Dashboard)
- `static chart(array $rows, string $label): string` – Zu- und Abgänge als maßstäbliche Säulen (SVG, Breite 100 %): Zugänge nach oben, Abgänge nach unten, Null-Linie, Achsenwerte
- `static current(): array` – Aktuelle Abos je Kanal (nur gültiger Schlüssel)
- `static daily(?string $topic, int $days = 30): array` – Zu- und Abgänge je Tag (die letzten $days Tage, ältester zuerst). $topic null = alle Kanäle (ohne Redaktion).
- `static deliveries(int $weeks = 12): array` – Versand je Woche: Nachrichten, zugestellt, nicht zugestellt (fehlgeschlagen + abgelaufen + Abo ungültig), Quote in %.
- `static ensureTable(Database $db): void`
- `static hit(string $topic, string $kind, int $n = 1, ?Database $db = null): void` – Zähler erhöhen (wirft nie – Statistik darf ein Abo nie verhindern)
- `static log(int $days = 60, int $limit = 200): array` – Protokoll: Zu-/Abgänge je Tag und Kanal (nur Zähler), neueste zuerst → [['day', 'topic', 'sub', 'unsub', 'gone'], …]
- `static lost(array $row, string $kind, ?Database $db = null): void` – Abgänge eines gelöschten Abos zählen (alle Kanäle + ggf. Gerät der Redaktion)
- `static purge(?Database $db = null): void`
- `static reset(bool $subscriptions = false): array` – Verlauf zurücksetzen (Verwaltung → Mitteilungen → Statistik, nur Administration): alle Mitteilungen samt Warteschlange und
- `static scale(array $rows): array` – Größte Werte nach oben/unten (Achsenbeschriftung)
- `static staff(): array` – Geräte der Redaktion je Ereignis (Schalter an + Recht) – ohne Namen: [Ereignis => ['label' => …, 'devices' => n, 'people' => n]]
- `static weekly(?string $topic, int $weeks = 12): array` – Wöchentlich zusammengefasst (Montag als Schlüssel, $weeks Wochen, älteste zuerst)

### `Topics` (class, `app/Push/Topics.php`)

Abos neuer Einträge einer Datentabelle (Funktion „push“): Einstellung settings.push der Tabelle enabled  Besucher können neue Einträge abonnieren (Standard aus) title    Titel der Mitteilung mit Platzhaltern {title} {table} {site} {feldname} (leer = Titel des Eintrags)

- `static enabled(array $table): bool` – Können Besucher die Tabelle abonnieren? (Funktion an, Inhaltstabelle, Einstellung an)
- `static matches(array $table, array $e): bool` – Erfüllt der Eintrag den Filter der Tabelle?
- `static payload(array $table, array $e): array` – Inhalt der Mitteilung: Titel (Vorlage), Kurztext, Detailseite (absolut), Symbol der Website
- `static published(array $table, int $id, ?string $wasPublishedAt): int` – Nach dem Speichern bzw. Statuswechsel: zum ersten Mal veröffentlicht? → Mitteilung an die Abos der Tabelle (Sprache des Eintrags).
- `static subscribers(array $table): int` – Anzahl der Abos einer Tabelle (alle Sprachen)
- `static tables(): array` – Tabellen, die Besucher abonnieren können (für Block-Auswahl und Status)
- `static topic(array|string $table): string` – Thema einer Tabelle
- `static validate(array $in, array $fields, array $settings): array` – Einstellung prüfen (Tables::validate) – gespeichert wird nur, was gesetzt ist

### `Visitor` (class, `app/Push/Visitor.php`)

Push-Abos für Besucher auf der Website: render()   Block „Benachrichtigungen abonnieren“ (push_subscribe) bzw. push_subscribe() im Kit – eine Tabelle (Knopf an/aus) oder mehrere Kanäle (Auswahl mit Kästchen; dieselbe Auswahl zeigt später das Abo und ändert bzw. bestellt ab)

Konstanten: `SITE_KEY`

- `static inject(string $html): string` – In eine fertige Seite einfügen (vor </body>)
- `static overlay(): string` – Banner + Glocke (unsichtbar ausgeliefert; das Skript entscheidet, ob und wann sie erscheinen) → '' wenn aus
- `static render(array|string|null $table, array $o = []): string` – Formular „Benachrichtigungen abonnieren“. $table: Kurzname/Definition einer Tabelle (null = Tabelle der Detailseite);
- `static saveSiteConfig(array $in): void` – Speichern aus dem Formular (Verwaltung → Mitteilungen → Website)
- `static siteChannels(?array $cfg = null): array` – Kanäle für Banner und Glocke: gewählte öffentliche, sonst alle öffentlichen
- `static siteConfig(): array` – Einstellung der Website (sys.push_site) mit Vorgaben
- `static token(): string` – Signiertes Merkmal der Website (keine Sitzung, keine Cookies): weist Anfragen an /api/push als von dieser Website aus

### `WebPush` (class, `app/Push/WebPush.php`)

Web Push ohne Zusatzbibliothek – nur PHP openssl, hash und curl (Rückfall: Streams):

Konstanten: `RECORD_SIZE`, `MAX_PAYLOAD`

- `static decrypt(string $body, string $uaPrivate, string $uaPublic, string $auth): ?string` – Gegenstück zu encrypt() aus Sicht des Browsers (für den Selbsttest): Nachricht mit dem privaten Schlüssel des Empfängers
- `static derToRaw(string $der): string` – DER-kodierte ECDSA-Signatur (SEQUENCE { INTEGER r, INTEGER s }) → 64 Byte r || s
- `static encrypt(string $plain, string $uaPublic, string $auth, ?array $as = null, ?string $salt = null, int $pad = 0): string` – Klartext für einen Browser verschlüsseln. $uaPublic = p256dh (65 Byte), $auth = Auth-Geheimnis (16 Byte).
- `static generateKeys(): array` – Neues P-256-Schlüsselpaar: ['public' => 65 Byte, 'private' => 32 Byte]
- `static jwt(array $claims, array $keys): string` – JWT (ES256) mit den Angaben $claims, signiert mit dem VAPID-Schlüsselpaar
- `static keysMatch(string $d, string $public): bool` – Gehören privater und öffentlicher Schlüssel zusammen? (öffentlichen Punkt aus d neu berechnen lassen)
- `static privateKey(string $d, string $public): OpenSSLAsymmetricKey` – Privater Schlüssel (roh d + öffentlicher Punkt) → openssl-Schlüssel (ECPrivateKey, RFC 5915)
- `static publicKey(string $raw, bool $throw = true): ?OpenSSLAsymmetricKey` – Öffentlicher Schlüssel (roh) → openssl-Schlüssel
- `static rawToDer(string $raw): string` – 64 Byte r || s → DER (für openssl_verify)
- `static sendAll(array $jobs, array $keys, string $subject, int $parallel = 8, int $timeout = 10): array` – Mehrere Nachrichten senden (curl_multi, gleichzeitig $parallel). Jede Aufgabe:
- `static validPublic(string $raw): bool` – Ist das ein gültiger öffentlicher P-256-Punkt (65 Byte, 0x04, auf der Kurve)?
- `static vapidHeader(string $endpoint, string $subject, array $keys, ?int $exp = null): string` – Kopfzeile Authorization für einen Endpunkt (aud = Ursprung des Push-Dienstes, exp ≤ 24 h, sub = Kontakt)
- `static verifyJwt(string $jwt, string $public): ?array` – JWT prüfen (Signatur ES256 mit dem öffentlichen Schlüssel) → Angaben oder null

## Core\Redirects

### `Console` (class, `app/Redirects/Console.php`)

Kommandozeile der Weiterleitungen (bin/console, je Website mit --site=…): redirects:import <datei.json|csv> [--dry-run] [--overwrite] [--keep-paths] redirects:list [--q=text] [--limit=N]

- `static run(string $cmd, array $args): int`

### `Redirects` (class, `app/Redirects/Redirects.php`)

Weiterleitungen je Website (Funktion „redirects“, Recht redirects.manage).

Konstanten: `CODES`, `MAX_404`, `SET_AUTO`, `SET_LOG`

- `static all(): array`
- `static appendQuery(string $url, string $query): string` – Query an eine Adresse hängen (vor einem #anker; vorhandene Query wird mit & ergänzt)
- `static bySource(string $source): ?array` – Regel mit genau dieser Quelle (Vergleichsschlüssel)
- `static count(): int`
- `static delete(array $ids): int`
- `static describeTarget(array $row): array` – Anzeige eines Ziels: ['label', 'href', 'missing']
- `static enabled(): bool`
- `static encodeLocation(string $url): string` – Location-Header: Zeichen außerhalb von ASCII (Umlaute, Leerzeichen) prozent-kodieren
- `static ensureTable(Database $db): void`
- `static explain(string $input): array` – Wohin führt eine Adresse? Für „Adresse testen“ und redirects:test:
- `static exportCsv(): string`
- `static exportJson(): string`
- `static find(int $id): ?array`
- `static handle404(Http\Request $r): ?Http\Response` – 404-Weg der Website: passende Weiterleitung als Antwort, sonst null (dann 404 wie bisher; der Pfad kommt ins Protokoll).
- `static hash(string $key): string`
- `static import(array $rows, array $o = []): array` – Importieren: ['created', 'updated', 'skipped', 'errors' => [zeile => text], 'linked'].
- `static isWildcard(string $source): bool` – Platzhalter-Quelle („/alt/*“)?
- `static key(string $normalized): string` – Vergleichsschlüssel: ohne Schrägstrich am Ende (außer „/“), ASCII-Kleinbuchstaben (Umlaute bleiben)
- `static list(array $f = []): array` – Liste mit Suche und Filter: ['rows', 'total', 'page', 'pages']
- `static logEnabled(): bool`
- `static match(string $path, string $query = ''): ?array` – Regel und Ziel zu einem Pfad: ['rule' => …, 'code' => 301|302|410, 'location' => …, 'rest' => …] oder null
- `static normalize(string $in): string` – Quelle bzw. Pfad vereinheitlichen: Domain weg, Fragment weg, Prozent-Kodierung aufgelöst (UTF-8), führender
- `static notFound(string $q = '', int $limit = self::MAX_404): array` – 404-Protokoll: häufigste zuerst, optional Suche
- `static notFoundClear(): void`
- `static notFoundCount(): int`
- `static notFoundDelete(array $ids): int`
- `static pageAt(string $path): ?array` – Veröffentlichte Seite unter einem Pfad (mit Sprachpräfix; nur Seiten, keine Detailseiten)
- `static pageRef(string $target): ?string` – Interner Pfad (ohne Query) einer veröffentlichten Seite → page:ID[#anker], sonst null
- `static parse(string $text, string $name = ''): array` – Zeilen aus CSV (quelle;ziel;code;notiz – Trenner ; , oder Tab, Kopfzeile optional) oder JSON
- `static pathsChanged(array $changes): void` – Von Pages::rebuildPaths(): $changes = [['id', 'old', 'new', 'lang', 'type', 'published'], …] (Pfade ohne Sprachpräfix).
- `static pick(array $rules, string $path, string $query = ''): ?array` – Passende Regel aus Kandidaten wählen (reine Funktion – auch für den Selbsttest).
- `static save(array $in, ?int $id = null, string $origin = 'manual'): array` – Speichern (neu oder ändern): [id, fehler, hinweise]
- `static setActive(array $ids, bool $on): int`
- `static suggest(string $path): ?array` – Vorschlag für einen 404-Pfad: Seite mit gleichem letzten Adressteil (Slug) – ['target' => 'page:ID', 'page' => …] oder null
- `static targetKind(string $target): string` – Art eines Ziels: ref (page:/entry:/media:), url (https://…), path (/…) oder '' (ungültig/leer)
- `static targetUrl(string $target, string $rest = ''): ?string` – Zieladresse (relativ oder absolut) oder null, wenn das Ziel fehlt bzw. unveröffentlicht ist
- `static toArray(array $r): array` – Für API und Konsole
- `static validate(array $in, ?int $ownId = null): array` – Eingaben prüfen und vereinheitlichen: [werte, fehler, hinweise].

### `SelfTest` (class, `app/Redirects/SelfTest.php`)

Selbsttest der Weiterleitungen (php bin/console redirects:selftest): Normalisieren, Vergleichsschlüssel, Auswahl der Regel (exakt vor Platzhalter, längster Präfix), Rest für „*“, Query, Location-Kodierung, Import-Formate. Ohne Datenbank.

- `static run(): array`

## Core\Review

### `Diff` (class, `app/Review/Diff.php`)

Unterschiede zweier Zustände (Core\Review\Snapshot) – feldweise, bei Seiten blockweise.

- `static compute(array $target, ?array $before, ?array $after): array`
- `static count(array $diff): int` – Anzahl geänderter Felder (Blöcke einzeln gezählt)
- `static text(mixed $v): string` – Wert als lesbarer Text (HTML ohne Tags, Listen/Objekte als JSON)
- `static words(string $a, string $b): array` – Wortweiser Vergleich → [HTML vorher, HTML nachher] mit <del>/<ins> (escaped).

### `Drafts` (class, `app/Review/Drafts.php`)

Entwürfe (Verwaltung → Entwürfe, /admin/entwuerfe): alles, was geprüft und veröffentlicht – oder verworfen – werden sollte.

Konstanten: `STALE_DAYS`, `FILTERS`

- `static canView(): bool` – Darf die angemeldete Person die Entwürfe-Übersicht sehen?
- `static count(): int` – Anzahl für die Seitenleiste (einmal je Aufruf, ohne Herkunft und Notizen – nur zählen)
- `static diff(array $i): array` – Entwurf gegen die veröffentlichte Fassung (Seite) bzw. alle Felder (Eintrag, noch nie online) – Format Core\Review\Diff
- `static discard(array $i, int $userId): ?string` – Verwerfen: Seite → zurück zur veröffentlichten Fassung (der Entwurf bleibt als Version erhalten);
- `static discards(int $limit = 20): array` – Verworfene Einträge der letzten Tage (nur Tabellen mit Bearbeitungsrecht)
- `static ensureTable(Database $db): void`
- `static filter(array $items, string $f, ?array &$counts = null): array` – Einträge je Filter; $counts erhält die Anzahl je Filter
- `static find(string $key): ?array`
- `static items(): array` – Alle Entwürfe, die die angemeldete Person bearbeiten darf, neueste Änderung zuerst.
- `static originFromNote(string $note): ?array` – Herkunft aus dem Vermerk der letzten Version: [schlüssel, beschriftung] oder null (Redaktion)
- `static originLabel(string $key): string`
- `static pageChanged(array $p): bool` – Arbeitsstand ≠ veröffentlichte Fassung (nur Blöcke – ein erneutes Speichern ohne Änderung zählt nicht)
- `static publish(array $i, int $userId): ?string` – Veröffentlichen mit denselben Rechten und Wegen wie in Seiten- bzw. Datenverwaltung. @return ?string Fehler
- `static restore(int $id): array` – Verworfenen Eintrag als Entwurf wiederherstellen. @return array{0: ?int, 1: ?string} [neue ID, Fehler]
- `static setNote(string $key, string $note, ?int $assignee, int $userId): void` – Notiz und zuständige Person setzen (beides leer = Notiz entfernen)
- `static stateLabel(string $state): string`
- `static userNames(): array` – Konten, die zuständig sein können: [id => Name] (ohne gesperrte)

### `Pending` (class, `app/Review/Pending.php`)

Änderung wurde nicht ausgeführt, sondern zur Freigabe eingereicht (Core\Review\Queue). REST-API: HTTP 202 mit $data; MCP: Tool-Ergebnis {status: "pending_review", id, url}.

- `__construct(array $data)`

### `Queue` (class, `app/Review/Queue.php`)

Prüf-Ebene „Eingereicht“: Herkunft aller Änderungen über REST-API, MCP und KI (Tabelle change_log je Website) und Freigabe von Änderungen, die nicht direkt übernommen werden sollen.

Konstanten: `STATUSES`, `CHANNELS`, `PER_PAGE`, `METHODS`, `EDIT_ARG`

- `static actionLabel(string $action): string`
- `static adminUrl(int $id): string`
- `static approve(int $id, int $reviewerId, array $values = [], bool $draftOnly = false, bool $force = false): array` – Einreichung übernehmen. $values: bearbeitete Feldwerte („Bearbeiten & übernehmen“), $draftOnly: Blöcke nur als Entwurf.
- `static canReview(): bool` – Darf die angemeldete Person Einreichungen sehen und freigeben?
- `static channelLabel(string $c): string`
- `static conflict(array $row): ?array` – Hat sich der Gegenstand seit der Einreichung geändert? → aktueller Stand, sonst null
- `static counts(array $f = []): array` – Anzahl je Status (mit den übrigen Filtern)
- `static decode(array $row): array`
- `static enabled(): bool`
- `static featureLabel(?string $f): string`
- `static find(int $id): ?array`
- `static list(array $f): array` – $f: status (pending|applied|rejected|all), channel, token (ID), type, q, page
- `static pendingCount(): int` – Offene Einreichungen (für Badge, Übersicht, Netzwerk)
- `static pendingJson(int $id): array` – Antwort bei Einreichung (REST 202, MCP)
- `static publicJson(array $row): array` – Stand einer Einreichung für API/MCP (Token sieht nur eigene)
- `static recheck(int $id): ?string` – Einreichung gegen den aktuellen Stand neu prüfen (Probelauf, neuer Diff, Konflikt aufgelöst). @return ?string Fehler
- `static record(array $ctx, string $method, array $target, ?array $before = null): void` – Direkt ausgeführte Aktion ohne Prüfung nachträglich protokollieren (z. B. Uploads)
- `static reject(int $id, int $reviewerId, string $reason): bool`
- `static reviewerEmails(): array` – E-Mail-Adressen aller Konten dieser Website mit Recht review.manage (ohne Netzwerk-Konten)
- `static run(array $ctx, string $method, array $args, array $target, ?callable $simulate, callable $call): mixed` – Schreibaktion ausführen, protokollieren oder einreichen.
- `static sources(): array` – Tokens bzw. Quellen, die Änderungen eingereicht haben (Filter)
- `static statusLabel(string $s): string`
- `static track(string $feature, string $action, array $target, callable $fn): mixed` – KI-Übernahme aus der Verwaltung protokollieren (direkt ausgeführt): Stand vor/nach $fn, Herkunft KI + Funktion.
- `static typeLabel(string $t): string`

### `Snapshot` (class, `app/Review/Snapshot.php`)

Zustand eines Gegenstands als vergleichbares Array (für Diff, Konflikterkennung und Vorschau der Prüf-Ebene).

Konstanten: `TYPES`

- `static dbs(array $target): array` – Datenbanken, die ein Probelauf für dieses Ziel in eine Transaktion nehmen muss
- `static exportBlocks(array $blocks): array`
- `static hash(?array $state): string` – Prüfsumme für die Konflikterkennung
- `static importBlocks(array $blocks): array` – Export-Format ({id, type, data, section}) → Editor.js-Format mit tunes
- `static label(array $target, ?array $state): string` – Name des Gegenstands für Listen (z. B. Seitentitel, Eintragstitel, Dateiname)
- `static labels(array $target): array` – Beschriftungen der Felder eines Gegenstands: Pfad → Label
- `static reset(array $target): void` – Zwischenspeicher nach einem zurückgerollten Probelauf verwerfen
- `static take(array $target): ?array` – Zustand oder null (existiert nicht bzw. noch nicht)

## Core\Search

### `AdminSettings` (class, `app/Search/AdminSettings.php`)

Grundeinstellungen → „Suche“ und „KI“: Felder der Website (über SystemSchema gespeichert) plus Status-/Test-Kästen (app/Admin/views/system/_search.php, _ai.php). Verbindungen und Verwendung der Installation (Core\AI\Profiles: Schlüssel, Modelle je Zweck) pflegt nur die Agentur/Netzwerk-Administration (Features::integrator()) – gespeichert in storage/ai/config.json.

- `static groups(): array`
- `static privacyText(): string` – Text für die Datenschutzerklärung (je nach Anbieter)

### `Console` (class, `app/Search/Console.php`)

Kommandozeile der Suche und KI (bin/console): search:index [--all] [--full] [--no-vectors] [--kb]   Index abgleichen (Standard: nur Änderungen) search:index --help [--no-vectors]                    Hilfe-Index des Assistenten (Core\AI\HelpIndex)

- `static run(string $cmd, array $args, string $script): int`

### `Documents` (class, `app/Search/Documents.php`)

Quellen des Suchindex einer Website in einer Sprache: Seiten      veröffentlicht, nicht „noindex“, Typ page; Titel, Beschreibung, sichtbare Blocktexte (Felder text, textarea, richtext, inline, repeater; Überschriften gewichtet)

- `static blocksText(array $blocks, ?array $page = null): array`
- `static build(string $lang): array`
- `static notesSelftest(array $res): array` – Selbsttest (notes:selftest): Redaktionsnotizen landen nicht im Suchindex
- `static tables(): array` – Tabellen, deren Einträge gefunden werden: Suche der Tabelle an (TableSearch), mit Detailseite, nicht auf dieser Website ausgenommen

### `Indexer` (class, `app/Search/Indexer.php`)

Abgleich des Suchindex einer Website: Dokumente je Sprache bauen (Documents), per Hash mit dem Stand vergleichen, nur geänderte in Loupe schreiben bzw. löschen; danach – bei semantischer Suche – fehlende Vektoren in Abschnitten (~500 Tokens mit Überlappung) über den KI-Anbieter erzeugen. Unveränderte Dokumente kosten keinen Anbieter-Aufruf.

- `__construct(?Closure $log = null)`
- `sync(bool $full = false, bool $vectors = true, float $budget = 0): array`

### `KeywordIndex` (interface, `app/Search/KeywordIndex.php`)

Stichwort-Index einer Website in einer Sprache. Standard: LoupeIndex (loupe/loupe, SQLite, tippfehlertolerant). Andere Verfahren (z. B. Meilisearch, SQLite FTS5) implementieren dieselbe Schnittstelle.

- `clear(): void`
- `count(): int`
- `delete(array $ids): void`
- `needsRebuild(): bool` – Index passt nicht mehr zur Konfiguration/Version → vollständig neu aufbauen
- `search(string $q, int $limit, array $filter = []): array` – Beste Treffer. $filter: type, table
- `upsert(array $docs): void`

### `LoupeIndex` (class, `app/Search/LoupeIndex.php`)

Stichwortsuche mit Loupe (loupe/loupe): SQLite-Datei je Website und Sprache unter storage/…/search/loupe-{lang}/. Tippfehlertolerant (State-Set-Index + Damerau-Levenshtein), Präfixsuche, Umlaute/Akzente egal, Stemming (de, en, fr …), Zerlegung deutscher/englischer Komposita, Relevanz nach Wortanzahl, Tippfehlern, Nähe, Attribut, Genauigkeit.

- `__construct(string $dir, string $lang)`
- `clear(): void`
- `count(): int`
- `delete(array $ids): void`
- `document(string $id): ?array` – Gespeichertes Dokument (title, headings, keywords, text, extra …) – z. B. Textstellen für den Besucher-Chat (Core\AI\VisitorChat)
- `needsRebuild(): bool`
- `search(string $q, int $limit, array $filter = []): array`
- `size(): int`
- `upsert(array $docs): void`

### `Ranker` (class, `app/Search/Ranker.php`)

Hybride Rangfolge: Reciprocal Rank Fusion (RRF, k = 60) über Stichwort- und semantische Treffer – Score = Σ 1 / (k + Rang). Dazu eine kleine Gewichtung, wenn Suchwörter im Titel bzw. in Überschriften stehen (Grundeinstellungen → Suche → Gewichtung; Loupe gewichtet selbst schon title > headings > text).

- `static fuse(array $lists, array $docs, array $words, array $boost, int $k = 60): array`
- `static legalLast(array $ranked, array $docs, array $words, array $legal): array` – Rechtsseiten (Impressum, Datenschutz, Barrierefreiheit) nennen fast alles einmal (Name, Adresse, Kontakt, Formulare) und
- `static minSimilarity(string $model): float` – Untergrenze der Kosinus-Ähnlichkeit, ab der ein Abschnitt „ähnlich“ ist – je Modellfamilie verschieden skaliert
- `static share(string $text, array $words): float` – Anteil der Suchwörter, die (als Wortanfang) im Text vorkommen: 0…1

### **API** `Search` (class, `app/Search/Search.php`)

Website-Suche für Besucher (Funktion „search“): Stichwortsuche (Loupe) und – wenn eingeschaltet – semantische Suche (Embeddings über Symfony AI, Core\AI\Ai). Beide Listen werden per Reciprocal Rank Fusion gemischt; fällt der KI-Anbieter aus oder antwortet er zu langsam, bleibt es bei der Stichwortsuche.

Konstanten: `SLUGS`, `SUGGEST`, `PER_PAGE`, `MAX_HITS`

- `static afterResponse(): void` – Nach der Antwort: Abgleich (Stichwort + fehlende Vektoren) – Besucher und Redaktion warten nicht
- `static changed(?string $siteKey = null): void` – Inhalte geändert (aus PageCache::clear/clearSite): Website zum Abgleich vormerken
- `static clean(string $q): string` – Suchbegriff bereinigen: max. 200 Zeichen, Steuerzeichen raus
- `static db(): Database`
- `static dir(?Site $site = null): string`
- `static enabled(): bool`
- `static form(string $variant = 'header', string $q = '', array $opt = []): string` – Suchformular (für Themes): Theme-Partial „search-form“, sonst das des Kerns. $variant: header | page | menu
- `static health(): array`
- `static isDirty(): bool`
- `static keyword(string $lang): Search\KeywordIndex`
- `static legalIds(): array` – Dokument-IDs von Impressum, Datenschutz und Barrierefreiheit (Core\Legal) – je Anfrage einmal
- `static panelId(string $variant = 'header'): string` – id des Popovers der Kopf-Suche (Ziel eines eigenen Auslösers mit popovertarget)
- `static pendingVectors(): int`
- `static potentialAction(): ?array` – schema.org SearchAction für den WebSite-Knoten (StructuredData)
- `static process(bool $force = false, bool $full = false, bool $vectors = true, float $budget = 0, ?Closure $log = null): ?array` – Abgleich ausführen, falls vorgemerkt (bzw. $force). Nur ein Prozess gleichzeitig (Sperrdatei).
- `static query(string $q, array $o = []): array` – Ergebnisse für die aktuelle Sprache.
- `static reset(): void` – Index löschen (z. B. nach Wiederherstellen einer Sicherung) – wird beim nächsten Abgleich neu aufgebaut
- `static routePattern(bool $suggest = false): string` – Muster der öffentlichen Routen (für routes.php): /suche, /en/search, /suche/vorschlag …
- `static semanticActive(): bool` – Semantische Suche wirklich aktiv? (Suche → semantisch an UND KI mit Embeddings für diese Website an – Grundeinstellungen → KI)
- `static settings(): array`
- `static slug(?string $lang = null): string`
- `static state(string $k, ?string $v = null): ?string`
- `static status(): array` – Übersicht für Verwaltung und CLI
- `static suggest(string $q, int $limit = 6): array` – Vorschläge beim Tippen (nur Stichwortsuche – schnell, ohne KI-Anbieter)
- `static suggestUrl(?string $lang = null): string`
- `static synonyms(string $lang): array` – Synonym-Gruppen einer Sprache: eine Zeile je Gruppe, Begriffe mit Komma getrennt
- `static url(string $q = '', ?string $lang = null, array $extra = []): string` – Adresse der Ergebnisseite, z. B. /suche?q=… bzw. /en/search?q=…
- `static vectors(): Search\VectorStore`

### `Stats` (class, `app/Search/Stats.php`)

Anonyme Zähler „Suchbegriffe ohne Treffer“ für die Redaktion (Grundeinstellungen → Suche). Gespeichert werden nur Begriff (Vergleichsform, max. 60 Zeichen), Sprache, Anzahl und Tag der letzten Suche – keine IP, kein Zeitpunkt, keine Sitzung. Begriffe mit @, langen Ziffernfolgen oder Adressen werden nie gespeichert

- `static clear(): void`
- `static miss(string $q, string $lang): void`
- `static top(int $limit = 20): array`

### `SupportSemantic` (class, `app/Search/SupportSemantic.php`)

Semantische Suche für Support & Wissensdatenbank (Core\Support\Search::use()): Stichwortsuche (SqliteSearch, FTS5) plus Embeddings über Symfony AI, gemischt per Reciprocal Rank Fusion. Vektoren liegen in der zentralen Support-Datenbank (Tabelle kb_vectors). Fällt der Anbieter aus, bleibt es bei der Stichwortsuche.

- `__construct(array $cfg)`
- `embedOne(string $kind, int $id, string $title, string $body, string $tags, ?float $timeout = null): int`
- `find(string $q, array $kinds, int $limit, bool $loose = false): array`
- `index(string $kind, int $id, string $title, string $body, string $tags): void`
- `reindex(?Closure $log = null): int` – Alle Artikel und Fragen (neu) einbetten – CLI `search:index --kb`
- `remove(string $kind, int $id): void`

### `TableSearch` (class, `app/Search/TableSearch.php`)

Such-Einstellungen je Datentabelle (Daten → Tabelle → Felder & Einstellungen → „Suche“), gespeichert in settings.search (nur Abweichungen; der Rest ergibt sich automatisch). Geteilte Tabellen: Einstellung der Eigentümer-Website (Schema); welche fremden Einträge eine Website zeigt, entscheidet weiter deren Quellen-Einstellung.

Konstanten: `WEIGHTS`, `TYPES`, `OPT_IN`, `FACET_TYPES`

- `static config(array $table): array` – Wirksame Einstellungen (gespeicherte Abweichungen + automatische Vorgaben)
- `static datable(array $table): bool` – Hat die Tabelle ein Datum für „nur künftige“? (Kalender oder Datumsfeld)
- `static defaultWeight(array $table, array $f): string`
- `static eligible(array $f): bool` – Unterfelder wiederholbarer Gruppen, die nie in die Suche gehen
- `static label(array $table, ?array $cfg = null): string` – Art im Treffer in der Sprache der Seite
- `static validate(array $in, array $fields, array $settings): array` – Eingabe aus dem Formular (settings[search]) bereinigen – gespeichert werden nur Abweichungen von den Vorgaben

### `Text` (class, `app/Search/Text.php`)

Text-Helfer der Suche: Klartext aus HTML, Vergleichsform (ohne Umlaute/Akzente), Wörter, Chunks, Auszüge.

Konstanten: `STOP`

- `static chunks(string $text, int $words = 380, int $overlap = 60): array` – Text in Abschnitte für Embeddings teilen: ~$words Wörter (≈ 500 Tokens bei 380 Wörtern) mit Überlappung,
- `static excerpt(string $text, array $words, int $len = 220): string` – Auszug um den ersten Treffer, Treffer mit <mark> (HTML-sicher). Wortanfänge zählen (Präfixsuche), Umlaute egal.
- `static fold(string $s): string` – Vergleichsform: klein, ohne Akzente, ß → ss, ä → a (wie Loupe/FTS „remove_diacritics“)
- `static mark(string $text, array $words): string` – Wörter (Vergleichsform) im Text markieren – Ausgabe ist HTML-escaped
- `static plain(?string $html): string` – HTML → Klartext mit Leerzeichen an Blockgrenzen – ohne Redaktionsnotizen [# … #] (Core\EditorNotes)
- `static stop(string $lang): array` – Füllwörter der Sprache (Vergleichsform)
- `static words(string $q, ?string $lang = null): array`

### `VectorStore` (class, `app/Search/VectorStore.php`)

Vektorspeicher in SQLite (Symfony-AI-Store-Schnittstelle): Vektoren als float32-BLOB (normiert), Suche per Kosinus-Ähnlichkeit ohne Index (brute force). Gut bis etwa 20 000 Abschnitte je Website und Sprache (≈ 60 MB bei 768 Dimensionen, Suche < 1 s); darüber einen Store mit Vektorindex einsetzen

- `__construct(Database $db, string $table = 'vectors')`
- `add(Symfony\AI\Store\Document\VectorDocumentInterface|array $documents): void`
- `clear(array $options = []): void`
- `count(): int`
- `docs(string $lang, string $model): array` – IDs der Dokumente, die Vektoren eines Modells haben
- `drop(array $options = []): void`
- `static normalize(array $v): array`
- `static pack(array $v): string`
- `query(Symfony\AI\Store\Query\QueryInterface $query, array $options = []): iterable` – Ähnlichste Abschnitte. $options: maxItems (Standard 50), lang, model (nur Vektoren dieses Embedders),
- `remove(array|string $ids, array $options = []): void`
- `removeDocs(array $docIds): void` – Alle Abschnitte eines Dokuments entfernen
- `setup(array $options = []): void`
- `stats(): array` – Anzahl Abschnitte je Sprache und Modell
- `supports(string $queryClass): bool`

## Core\Sources

### `Console` (class, `app/Sources/Console.php`)

Kommandozeile der externen Quellen (bin/console): sources:sync [--all] [--source=ID] [--force]   fällige Quellen abgleichen (--source: nur diese, sofort; --force: alle aktiven, Zwischenspeicher umgehen) sources:list [--all]                            Quellen mit Stand

- `static run(string $cmd, array $args, string $script): int`

### `Mapper` (class, `app/Sources/Mapper.php`)

Zuordnung Quelle → Felder einer Datentabelle.

Konstanten: `UNSUPPORTED`, `CANDIDATES`, `TEASER`, `NEW_TABLE_TYPES`

- `static clean(array $table, array $in): array` – Eingaben aus dem Formular bereinigen
- `static date(string $raw, string $formats, string $type): ?string` – Datum lesen: feste Formate „d.m.Y|Y-m-d H:i“ (PHP DateTime) oder automatisch (RFC 822/2822, ISO 8601, Unix-Zeit).
- `static display(array $f, mixed $v): string` – Zugeordneten Wert lesbar darstellen (Datum deutsch, Auswahl mit Bezeichnung, formatierter Text gekürzt)
- `static explain(array $table, array $mapping, array $item): array` – Ergebnis je Feld für den ersten Eintrag („Beispiel: …“ unter jeder Zeile der Zuordnung)
- `static first(array $item, string $paths): ?string` – Erster nicht leerer Wert aus „pfad | pfad2“ als Text; „liste[*]“ verbindet alle Werte mit Komma
- `static firstParagraph(string $raw): string` – Erster Absatz als Text: erstes <p>…</p> bzw. Text bis zur ersten Leerzeile oder zum ersten <br><br>
- `static freeHandle(string $name): array` – Freier Kurzname und freie Adresse für eine neue Tabelle (bei Kollision _2, _3 … bzw. -2, -3 …)
- `static hash(array $mapped): string` – Prüfsumme der zugeordneten Werte (inkl. Bildverweise) – nur geänderte Einträge werden gespeichert
- `static html(string $s): string` – HTML bereinigen (Whitelist des Kerns); reiner Text wird zu Absätzen
- `static humanize(string $path): string` – Lesbare Bezeichnung eines unbekannten Pfads: letztes Segment, ohne Präfix und Index, „@attr“ in Klammern
- `static inferType(string $path, array $values): string` – Feldtyp aus Beispielwerten ableiten: Datum → datetime/date, Adresse → url (Bild-Adresse → media), Zahl → number,
- `static isSet(array $rule): bool` – Zeile der Zuordnung belegt? (Pfad, Vorlage, „jetzt“ oder Standardwert)
- `static map(array $table, array $mapping, array $item): array` – Einen Eintrag der Quelle zuordnen.
- `static missingRequired(array $table, array $mapping): array` – Pflichtfelder ohne Zuordnung (Speichern würde scheitern)
- `static number(string $raw, string $locale = ''): ?string` – Zahl lesen: de „1.234,56“, en „1,234.56“, leer = automatisch; Währungszeichen und Einheiten werden ignoriert
- `static oneLine(string $s): string` – HTML → Text in einer Zeile, Absätze und Listenpunkte durch Leerzeichen getrennt (Beispiele, Probeabruf)
- `static pathLabel(string $path): string` – Verständliche Bezeichnung bekannter Feed-Pfade (RSS, Atom, Media RSS, Dublin Core) – '' = unbekannt
- `static plain(string $s, bool $lines = false): string` – HTML → Text (Zeilenumbrüche optional erhalten)
- `static present(array $paths, array $cands): array` – Welche der Kandidaten kommen in den gefundenen Pfaden vor? (Indizes [0] und Filter [@…] werden beim Vergleich ignoriert)
- `static preview(array $table, array $mapping, array $items, int $n = 5): array` – Probeabruf: die ersten $n Einträge so, wie sie gespeichert würden (Feld → Wert), mit Hinweisen und Fehlern.
- `static proposeTable(array $items, string $format, string $name): array` – Vorschlag „Neue Tabelle aus dieser Quelle“: Name, Kurzname, Adresse und Felder (Bezeichnung, Typ, Pfad, Beispiel, an/aus)
- `static sourceFields(array $paths): array` – Felder der Quelle für Übersicht und Auswahl: Pfad, Bezeichnung, Beispiel (erster Eintrag).
- `static suggest(array $table, array $paths, string $format, ?array $current = null): array` – Vorschlag: leere Zeilen der Zuordnung aus den gefundenen Pfaden füllen – nach gleichem Namen, Schlüsselwörtern in
- `static template(array $item, string $tpl): ?string` – Vorlage „{geo.strasse} {geo.hausnummer}“ – leere Platzhalter fallen weg, doppelte Trenner werden gekürzt
- `static title(array $table, array $mapped): string` – Titel eines zugeordneten Eintrags (Protokoll)
- `static transformHelp(): array` – Kurze Erklärung je Umwandlung (unter der Auswahl)
- `static transforms(): array` – Umwandlungen (Bezeichnungen übersetzbar)
- `static truncate(string $s, int $max): string` – Kürzen an einer Wortgrenze mit „…“ (Text ohne HTML)
- `static typeHint(string $type): string` – Was die Quelle für einen Feldtyp liefern sollte (unter dem Feldnamen)

### `OpenImmo` (class, `app/Sources/OpenImmo.php`)

OpenImmo (XML-Standard der Immobilienwirtschaft, Version 1.2.x) – Einträge = <immobilie>.

Konstanten: `OBJEKTARTEN`, `IMAGE_EXT`, `MAX_ZIP_FILES`, `MAX_ZIP_BYTES`

- `static mapping(): array` – Zuordnung passend zu tableDef()
- `static parse(string $body): array`
- `static tableDef(): array` – Tabellen-Definition für Tables::validate (Vorlage „Immobilien“)
- `static templateBlocks(array $t): array` – Detailseite: Kopf, Eckdaten (mit Beschriftung), Texte, weitere Bilder, Energieausweis und Kontakt
- `static unzip(string $zipFile, string $targetDir): array` – ZIP (openimmo.xml + Bilder) sicher entpacken: nur eine XML-Datei und Bilder, flach (nur Dateinamen – kein Zip-Slip),

### `Parser` (class, `app/Sources/Parser.php`)

Formate externer Quellen → Liste gleichartiger Einträge (verschachtelte Arrays), dazu Pfad-Zugriff für die Zuordnung.

Konstanten: `MAX_ITEMS`

- `static get(mixed $data, string $path): mixed` – Wert unter einem Pfad (null = nicht vorhanden); [*] liefert eine Liste
- `static node(DOMElement $el, int $depth = 0): array|string` – Element → Array/Text (siehe Klassenbeschreibung)
- `static parse(string $body, string $format, array $opt = []): array`
- `static paths(array $items, int $max = 300): array` – Gefundene Pfade mit Beispielwert (für die Zuordnung): ['title' => 'Beispiel', 'enclosure@url' => 'https://…', …]
- `static scalar(mixed $v): ?string` – Wert als Text: Element mit Attributen → #text, Link-Elemente → href/url, Listen → erstes Element
- `static scalars(mixed $v): array` – Alle Werte einer Liste als Texte (für Mehrfachauswahl, Bilder)
- `static xmlDoc(string $body): DOMDocument` – XML sicher laden (XXE, Entity-Expansion, Netzwerk) @throws SourceException

### `SourceException` (class, `app/Sources/SourceException.php`)

Fehler beim Entpacken oder Lesen einer externen Quelle (Abruf: Core\Http\FetchException) – Meldung ist für die Oberfläche geeignet (übersetzt).


### `Sources` (class, `app/Sources/Sources.php`)

Externe Quellen: Feeds (RSS, Atom), JSON-APIs, XML und OpenImmo werden serverseitig abgerufen und per Zuordnung als Einträge einer Datentabelle gespeichert – danach funktionieren Datenliste, Detailseiten, Suche, API/MCP und JSON-LD wie bei selbst gepflegten Einträgen. Besucher stellen nie Anfragen an die Quelle.

Konstanten: `FORMATS`, `AUTH`, `LOG_KEEP`, `DEFAULTS`

- `static all(): array`
- `static authOptions(array $src): array` – Kopfzeilen und Basic-Auth für den Abruf (Geheimnis wird erst hier entschlüsselt)
- `static canManage(): bool` – Einrichten und abrufen: Funktion an + Recht sources.manage + Datentabellen
- `static configured(): ?bool` – Ist 'sources' in config 'features' ausdrücklich gesetzt? (dann ist der Schalter gesperrt)
- `static delete(array $src, bool $entries): int` – Quelle löschen; $entries: auch die übernommenen Einträge löschen (sonst werden sie normale, bearbeitbare Einträge)
- `static due(): array` – Fällige Quellen
- `static enabled(): bool` – Funktion auf dieser Website eingeschaltet (ohne Integrator-Ausnahme) – gilt für Abruf, Cron und Verwaltung
- `static ensureTable(Database $db): void`
- `static feedMapping(string $format): array`
- `static feedTable(): array` – Tabellen-Vorlage „Meldungen“ für Feeds
- `static find(int $id): ?array`
- `static health(): array` – Für `health`: je aktiver Quelle mit Fehler eine Warnzeile (null = „!“ – gemeldet, aber kein Deploy-Fehler, denn die
- `static hydrate(array $r): array`
- `static isExternal(array $table, int $entryId): bool` – Stammt der Eintrag aus einer Quelle? (Schreibschutz in Entries::save)
- `static logs(int $sourceId, int $n = self::LOG_KEEP): array`
- `static maybeRun(): void` – Nebenbei abrufen (Verwaltungsaufrufe): fällige Quellen nach der Antwort abarbeiten – Cron ist zuverlässiger
- `static missingModes(): array`
- `static navVisible(): bool` – Menüpunkt sichtbar (Integratoren sehen ihn auch bei ausgeschalteter Funktion – zum Einschalten)
- `static nextDue(string $schedule, ?int $lastRun): ?int`
- `static normalize(?array $src, array $in): array` – Eingaben prüfen und bereinigen (auch für die Vorschau ungespeicherter Quellen).
- `static origin(array $table, int $entryId): ?array` – Herkunft eines Eintrags (Badge „aus Quelle …“) – null bei selbst gepflegten Einträgen
- `static originMap(array $table, array $ids): array` – Herkunft mehrerer Einträge (Listenansicht): entry_id → Name der Quelle
- `static presets(): array` – Vorlagen für neue Quellen: Format, Optionen, Tabellen-Vorlage, Zuordnung
- `static save(?array $src, array $in): array` – Quelle anlegen/ändern. @return array{0: ?int, 1: array, 2: array} [id, errors, bereinigte Werte]
- `static schedules(): array`
- `static siteSwitch(): bool` – Schalter der Website (Netzwerk-Administration/Integratoren) – wirkt, solange config 'features' nichts vorgibt
- `static touchDue(): void` – Frühester Termin aller aktiven Quellen merken (billige Prüfung bei jedem Verwaltungsaufruf)

### `Sync` (class, `app/Sources/Sync.php`)

Abruf und Abgleich einer Quelle: laden (mit Zwischenspeicher), lesen, zuordnen, Einträge anlegen/aktualisieren (nur bei geänderter Prüfsumme), fehlende ausblenden/löschen, Bilder in die Mediathek übernehmen, protokollieren. Eine Sperrdatei je Quelle verhindert parallele Läufe (Cron + „Jetzt abrufen“).

- `static clearFiles(int $id): void`
- `static dir(?int $id = null): string`
- `static load(array $src, bool $force = false, ?string $upload = null): array` – Rohdaten holen: Upload (OpenImmo) bzw. Adresse – mit Zwischenspeicher (Cache-Dauer der Quelle).
- `static preview(array $src, ?array $table, int $n = 5, bool $force = false): array` – Vorschau ohne Schreiben: Rohdaten laden, lesen, erste $n zuordnen.
- `static run(array $src, string $via = 'manual', array $o = []): array` – Quelle abgleichen. $via: manual | cron | auto | upload. $o: force (Zwischenspeicher umgehen), upload (Pfad einer Datei)
- `static runDue(string $via, float $budget = 0, ?Closure $out = null): array` – Fällige Quellen abarbeiten (Cron, nebenbei) – $budget: Sekunden
- `static sample(array $src): ?array`
- `static sampleKey(array $src): string` – Beispiel-Einträge der letzten Vorschau bzw. des letzten Abrufs (erste 5, gelesen) – für „Felder in der Quelle“, „Beispiel: …“
- `static storeSample(array $src, array $items, array $meta): void`
- `static uploadFile(int $id): ?string` – Hochgeladene ZIP-/XML-Datei (OpenImmo) – bleibt für erneutes Einlesen liegen

## Core\Support

### `Api` (class, `app/Support/Api.php`)

Support für REST-API und MCP (eine Implementierung, zwei Schnittstellen): GET /api/v1/kb?q=…            veröffentlichte Wissensartikel, die für diese Website sichtbar sind MCP search_knowledge / report_issue

- `static knowledge(string $q, int $limit = 10): array`
- `static report(array $token, array $args): array` – Meldung im Namen des Benutzers, dem das Token gehört

### `Knowledge` (class, `app/Support/Knowledge.php`)

Wissensdatenbank: Artikel mit Versionen, Tags, Sichtbarkeit (alle | bestimmte Websites | nur Support-Team), Aufrufen und „Hilfreich?“-Rückmeldungen. Anlegen und Bearbeiten: Support-Team.

- `static anonymise(string $text, array $issue = []): string`
- `static canSee(array $a): bool`
- `static countView(int $id): void` – Einmal je Sitzung zählen
- `static delete(int $id): void`
- `static draftFromIssue(array $issue): array` – Entwurf eines Wissensartikels aus einer gelösten Meldung: Problem + öffentliche Antworten des Teams,
- `static feedback(int $id, bool $helpful): void`
- `static find(int $id): ?array`
- `static list(array $f, int $per = 20): array`
- `static myFeedback(int $id): ?bool`
- `static newest(int $n = 5): array`
- `static popular(int $n = 5): array`
- `static related(array $a, int $n = 4): array`
- `static revisions(int $id): array`
- `static save(?int $id, array $in, string $note = ''): int` – Anlegen oder ändern (Support-Team). Jede gespeicherte Fassung landet in den Versionen.
- `static search(string $q, int $n = 5, bool $loose = false): array` – Für Spotlight und „ähnliche Artikel“
- `static tagCloud(): array` – Tags mit Anzahl (sichtbare Artikel + Fragen)

### `Markdown` (class, `app/Support/Markdown.php`)

Markdown-lite für Meldungen, Fragen, Antworten und Wissensartikel. Unterstützt: Absätze, Zeilenumbrüche, Listen (- * 1.), ### Zwischenüberschriften, ```Code-Blöcke```, `Code`, **fett**, *kursiv*, [Text](https://…) und nackte https-Adressen. Alles andere wird als Text ausgegeben

- `static inline(string $text): string` – Inline-Formatierung auf bereits maskiertem Text
- `static plain(?string $src, int $max = 0): string` – Klartext (für Auszüge, Suche, E-Mails)
- `static render(?string $src): string`

### `Questions` (class, `app/Support/Questions.php`)

Fragen & Antworten (wie Stack Overflow): Fragen mit Tags, Antworten, Stimmen (+1 je Person und Beitrag), akzeptierte Antwort (Fragesteller:in oder Support-Team), Aufrufe. Sichtbarkeit einer Frage: „all“ (alle Websites) oder „site“ (nur die eigene Website + Support-Team).

- `static accept(array $q, array $a): void` – Antwort akzeptieren – erneut = zurücknehmen
- `static addAnswer(array $q, string $body): int`
- `static answer(int $id): ?array`
- `static answers(array $q): array`
- `static canAccept(array $q): bool`
- `static canEdit(array $row): bool`
- `static canSee(array $q): bool`
- `static countView(int $id): void`
- `static delete(array $q): void`
- `static deleteAnswer(array $a): void`
- `static find(int $id): ?array`
- `static list(array $f, int $per = 20): array`
- `static myVotes(array $q, array $answers): array` – Stimmen der angemeldeten Person: ['q' => [ids], 'a' => [ids]]
- `static save(?array $q, array $in): int`
- `static scope(string $a = 'q'): array`
- `static search(string $q, int $n = 5, bool $loose = false, bool $answeredOnly = false): array` – Beantwortete Fragen zuerst (für Spotlight und Vorschläge beim Melden)
- `static toggleVote(string $target, array $row): bool` – +1 geben oder zurücknehmen. Eigene Beiträge zählen nicht. @return bool neuer Zustand (true = gestimmt)
- `static updateAnswer(array $a, string $body): void`

### `Search` (class, `app/Support/Search.php`)

Einheitlicher Zugang zur Suche (Wissensartikel, Fragen). Alle Aufrufer gehen über diese Klasse, das Verfahren dahinter ist austauschbar (Search::use()) – siehe SearchBackend.

- `static backend(): Support\SearchBackend`
- `static find(string $q, array $kinds = […], int $limit = 20, bool $loose = false): array`
- `static index(string $kind, int $id, string $title, string $body, string $tags): void`
- `static migrate(Database $db): void` – Suchindex anlegen (Teil der Migration der zentralen Datenbank)
- `static remove(string $kind, int $id): void`
- `static use(Support\SearchBackend $backend): void`
- `static words(string $q, bool $dropStop = true): array` – Suchbegriffe: Wörter ab 3 Zeichen ohne häufige Füllwörter (für „ähnliche Artikel“)

### `SearchBackend` (interface, `app/Support/SearchBackend.php`)

Suchverfahren für Support & Wissensdatenbank. Standard: SqliteSearch (FTS5, sonst LIKE). Eine semantische Suche (z. B. Symfony AI mit Embeddings) implementiert dieselbe Schnittstelle und wird mit Search::use(new MeineSuche()) eingesetzt – Aufrufer ändern sich nicht.

- `find(string $q, array $kinds, int $limit, bool $loose = false): array` – Treffer, bestes zuerst.
- `index(string $kind, int $id, string $title, string $body, string $tags): void` – Dokument (neu) aufnehmen. $kind: 'article' | 'question'
- `remove(string $kind, int $id): void`

### `Seed` (class, `app/Support/Seed.php`)

Startartikel der Wissensdatenbank – ausschließlich aus dem Handbuch (app/Admin/views/help/manual.php) abgeleitet. Werden einmalig beim Anlegen der zentralen Datenbank eingespielt und sind danach frei änderbar.

- `static run(): void`

### `SqliteSearch` (class, `app/Support/SqliteSearch.php`)

Volltextsuche in der zentralen Support-Datenbank: SQLite FTS5 mit „unicode61 remove_diacritics 2“ (Groß/klein und Umlaute/Akzente egal: „Uberschrift“ findet „Überschrift“), Präfix-Suche („bild*“), Gewichtung Titel > Tags > Text (bm25). Ohne FTS5 (seltene PHP-Builds): gleiche Tabelle als normale

- `__construct(Database $db)`
- `find(string $q, array $kinds, int $limit, bool $loose = false): array`
- `static fts(): bool`
- `index(string $kind, int $id, string $title, string $body, string $tags): void`
- `static migrate(Database $db): void`
- `remove(string $kind, int $id): void`

### `Support` (class, `app/Support/Support.php`)

Support & Wissensdatenbank – zentral für alle Websites einer Installation.

Konstanten: `STATUSES`, `OPEN`, `CATEGORIES`, `PRIORITIES`

- `static authorLabel(string $key, ?string $name, bool $staff = false): string` – Anzeigename einer Person für die Betrachtenden – fremde Websites bleiben anonym (Datenschutz)
- `static canAnswer(): bool` – Fragen stellen und beantworten
- `static canRead(): bool` – Wissensdatenbank und Fragen lesen, abstimmen, „hilfreich?“
- `static canReport(): bool`
- `static categoryLabel(string $c): string`
- `static db(): Database` – Zentrale Datenbank (legt Tabellen, Suchindex und die Startartikel beim ersten Öffnen an)
- `static dir(string $sub = ''): string`
- `static enabled(): bool` – Funktion „support“ auf dieser Website an (Integratoren: immer)
- `static ensureRoles(): void` – Bestehende Rollen einmalig ergänzen: „support.report“ für alle, „support.answer“ für die Redaktion.
- `static isSiteManager(): bool` – Alle Meldungen der eigenen Website sehen und beantworten (Website-Administration)
- `static isStaff(): bool` – Support-Team: alle Websites, Status, Zuweisung, interne Notizen, Wissensartikel, Moderation
- `static mail(array $to, string $subject, string $text): void` – Benachrichtigung über den Mailer der Website (respektiert 'mail_redirect' außerhalb der Produktion)
- `static me(): string` – Kennung der angemeldeten Person: „Website:ID“
- `static myName(): string`
- `static navCount(): int` – Ungelesenes für die Navigation: eigene Meldungen mit neuer Antwort + (Team) neue/aktualisierte Meldungen
- `static priorityLabel(string $p): string`
- `static registerStaff(): void` – Support-Team-Mitglied vormerken (für Zuweisung und Benachrichtigung)
- `static reset(): void` – Für Tests / nach Wiederherstellen
- `static siteLabel(string $key): string`
- `static sites(): array` – [key => Bezeichnung]
- `static spotlight(string $q, callable $score): array` – Gruppen für die übergreifende Suche (SearchController)
- `static staff(): array`
- `static statusLabel(string $s): string`
- `static tagList(string $stored): array`
- `static tagString(array $tags): string`
- `static tags(array|string $in): array` – Tags normalisieren: klein, ohne Sonderzeichen, höchstens 8
- `static urlOn(string $site, string $path): string` – Absolute Adresse auf einer bestimmten Website (für E-Mails)
- `static visibilityLabel(string $v): string`

### `Tickets` (class, `app/Support/Tickets.php`)

Meldungen („Issues“) der Redaktion an das Support-Team – zentral für alle Websites.

Konstanten: `MAX_FILES`, `MAX_BYTES`, `IMAGE_TYPES`

- `static assign(array $issue, ?string $key): void`
- `static canView(array $issue): bool`
- `static context(Http\Request $r, string $from = '', string $viewport = ''): array` – Automatischer Kontext – wird der meldenden Person vor dem Absenden vollständig angezeigt
- `static contextLabels(): array`
- `static counts(bool $mine = false): array` – Anzahl je Status in der sichtbaren Menge (für Filter)
- `static create(array $in, array $uploads, array $context, ?array $user = null): array`
- `static delete(array $issue): void` – Meldung endgültig löschen (Team; z. B. Tests oder Datenschutz-Anfragen)
- `static file(int $id): ?array` – Anhang, falls die angemeldete Person ihn sehen darf
- `static files(array $issue): array` – Anhänge [post_id|0 => list]
- `static find(int $id): ?array`
- `static isReporter(array $issue): bool`
- `static list(array $f, int $per = 25): array`
- `static markSeen(int $id): void`
- `static normalizeFiles(mixed $f): array` – Hochgeladene Dateien (name[]/tmp_name[] aus $_FILES) → Liste einzelner Dateien
- `static posts(array $issue): array` – Verlauf (Antworten, Notizen, Statuswechsel). Interne Einträge nur fürs Team.
- `static reencode(string $src, string $dest, int $type): bool`
- `static reply(array $issue, string $body, bool $internal, array $uploads = [], ?string $status = null): array` – Antwort (öffentlich) oder interne Notiz; optional Status ändern. @return list<string> Fehler bei Anhängen
- `static setStatus(array $issue, string $status, bool $notify = true): void`
- `static stats(?string $site = null): array` – Kennzahlen für Übersichten (z. B. Netzwerk-Dashboard): offen, neu, dringend (offen), wartet auf Rückmeldung.
- `static storeUploads(array $files, int $issueId, ?int $postId, bool $internal): array` – Nur Bilder (JPEG, PNG, WebP, GIF), höchstens 5 × 8 MB. JPEG/PNG/WebP werden neu kodiert – das entfernt
- `static unreadCount(): int` – Ungelesene Meldungen (Navigation): eigene mit neuer Antwort; Team: neue/aktive Meldungen; Website-Admin: öffentliche Aktivität der Website

### `Ui` (class, `app/Support/Ui.php`)

Kleine Bausteine für die Support-Ansichten (app/Admin/views/support/*)

- `static category(string $c): string`
- `static mdHint(string $id): string` – Hinweis zur Formatierung unter Textfeldern
- `static n(int $n, string $kind): string` – Anzahl mit Einzahl/Mehrzahl: Ui::n(1, 'Aufruf') → „1 Aufruf“, sonst „{n} Aufrufe“
- `static pager(int $page, int $pages, callable $qs): string`
- `static priority(string $p): string`
- `static snippet(?string $s): string` – Suchauszug mit Treffermarken (\x02 … \x03 aus FTS5 snippet())
- `static status(string $s): string`
- `static tags(string $stored, string $base = '/admin/support/wissen'): string`
- `static when(?string $at): string` – Relatives Datum mit maschinenlesbarem <time>

## Core\Testing

### `Check` (class, `app/Testing/Check.php`)

Vergleich für die Selbsttests (*:selftest): zählt Treffer in $ok und sammelt Abweichungen in $fails.

- `static eq(int &$ok, array &$fails, string $prefix = '', bool $verbose = false): Closure`
- `static show(mixed $v): string` – Wert lesbar für Meldungen

