# Changelog

Übersicht der Funktionsbereiche von **KLXM Studio** (früher „MyCMS.dev light“). Die Versionsnummer steht in
`app/bootstrap.php` (`CMS_VERSION`); Einzelheiten zu jedem Bereich im Entwicklerhandbuch (`/admin/hilfe/technik`)
und im Handbuch für die Redaktion (`/admin/hilfe`).

## 1.0.0

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
  Einträge mit Detailseite zur aufgerufenen Adresse, Vorschlag des 404-Protokolls zuerst – `NotFound::suggestions()`),
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
  KLXM Ai → Untertitel, API/MCP); der Hinweis „Noch keine Untertitel“ entfällt.
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
- **Neu gedreht** (`tools/trailer/trailer.mjs`): Kits, Bearbeiten auf der Seite mit Blöcken und KLXM Ai, Mediathek mit
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
