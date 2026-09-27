# Erweiterung „consent_kit“ – Cookie-Einwilligung

Einwilligungsverwaltung für KLXM Studio: Dienste aus geprüften Vorlagen, ein barrierefreier Hinweis im Design der
Website, 2-Klick-Platzhalter, Google Consent Mode v2, Global Privacy Control, Conversions ohne Code und ein
datensparsames Protokoll. Port des REDAXO-AddOns [consent_kit](https://github.com/FriendsOfREDAXO/consent_kit)
(MIT, KLXM Crossmedia) – angepasst an die Grundsätze des Kerns: keine Cookies/Tracker ohne Entscheidung, CSP ohne
Inline-Code, WCAG 2.2 AA.

## Aktivieren

```php
// config/sites/{key}.php bzw. config/config.local.php
'extensions' => ['consent_kit'],
// abschalten, ohne die Erweiterung zu entfernen:
'features' => ['consent' => false],
```

Die Migration legt `consent_services`, `consent_revisions` und `consent_log` in der Datenbank der Website an.
Recht: `consent.manage` (Rolle „Administration“ hat es). Assets: `cd tools && pnpm run build`
(→ `public/extensions/consent_kit`).

**Solange kein einwilligungspflichtiger Dienst aktiv ist, ändert sich für Besucher nichts**: kein Hinweis, kein Skript,
kein Cookie, CSP unverändert.

## In fünf Minuten

1. Verwaltung → **Cookie-Einwilligung → Dienst hinzufügen**: Vorlage wählen (z. B. Matomo), Kennungen eintragen,
   „Aktiv“ anhaken, speichern.
2. **Einstellungen**: Form (Box, Leiste, Dialog, Off-Canvas), Rechtstexte (Linkauswahl; leer = Seiten aus den
   Kit-Einstellungen), GPC, Consent Mode, Aufbewahrung.
3. **Design**: Farben kommen aus Verwaltung → Design; Abweichungen hell/dunkel mit Live-Vorschau und Kontrastprüfung.

## Wie es ausgeliefert wird (CSP)

| Teil | Auslieferung |
|---|---|
| Konfiguration | `<script type="application/json" id="cms-consent-config">` (Datenblock, wird nicht ausgeführt) |
| Kern | `/extensions/consent_kit/js/consent.js` (≈ 4,7 KB, `defer`, vor den Kit-Skripten) |
| Oberfläche | `consent-ui.mjs` (≈ 14 KB) + `/consent/style.css` – nur bei Bedarf (Hinweis, Einstellungen, Platzhalter, schwebende Schaltfläche) |
| Code der Dienste | Inline-`<script>` aus „HTML im head/body“ und die JS-Felder → `/consent/js/{dienst}.{teil}.js` (eigene Domain); externe `<script src>` bleiben extern |
| CSP | `script-src`/`connect-src`/`img-src`/`frame-src` eines Dienstes erst, wenn der Cookie der Anfrage die Einwilligung in seine aktuelle Fassung enthält; `frame-src` der „Domains eingebetteter Inhalte“ aktiver Dienste (für „einmal laden“). Nie `unsafe-inline`. |

Wird ein Dienst mit fremden Hosts neu erlaubt, lädt die Seite einmal neu (die CSP der laufenden Antwort kennt die Hosts
noch nicht). Dienste nur mit eigener Domain oder iframes starten sofort.

Hosts je Vorlage: `presets/_csp.json`; dazu automatisch Hosts aus dem Code und aus URL-Angaben; eigene unter
Dienst → Erweitert. Anbieter-Skripte, die selbst Inline-Code oder Inline-Styles einfügen, funktionieren unter der
strengen CSP nur eingeschränkt (bewusst kein `unsafe-inline`).

## Cookie und Speicher

- `cms_consent` (Cookie, erst nach einer Entscheidung): URL-kodiertes JSON `{id, e, rev, ts, a: {dienst: fp}, r: {…}}`,
  `SameSite=Lax`, `Secure` unter HTTPS, Laufzeit = Einstellung (≤ 400 Tage). Vom Browser gesetzt und vom Server per
  `Set-Cookie` bestätigt; der Server liest ihn für die CSP.
- `cms_consent_dismissed` (sessionStorage): Hinweis ohne Entscheidung geschlossen (× / Escape).
- Beide erscheinen automatisch in der Gruppe „Notwendig“ der Cookie-Einstellungen.
- Global Privacy Control („Als Ablehnung werten“): kein Hinweis, nichts wird geladen, **nichts gespeichert und nichts
  protokolliert** (Abweichung vom AddOn, das „Abgelehnt per GPC“ als Cookie + Protokoll ablegt); eine ausdrückliche
  Einwilligung über die Cookie-Einstellungen geht vor.

## Kits

- **Fußbereich**: Die mitgelieferten Kits hängen `footer_links()` an ihre Rechtliches-Links („Cookie-Einstellungen“,
  `href="#cookie-einstellungen"`). Eigene Kits: `<?= consent_settings_link() ?>` oder ein Link auf `#cookie-einstellungen`
  bzw. `[data-consent-open]`.
- **2-Klick-Videos** (Video-Block, YouTube/Vimeo): Ist ein Dienst für den Anbieter angelegt (Schlüssel `youtube`/`vimeo`
  oder passende Embed-Domains), entscheidet `window.cmsConsent.embed('youtube')`; „künftig direkt laden“ erteilt die
  Einwilligung in den Dienst und umgekehrt (Ereignis `cms:consent`). Ohne Dienst bleibt das bisherige Verhalten.
- **Andere Einbettungen**: Block „Externer Inhalt (mit Einwilligung)“ oder
  `<?= consent_embed('google_maps', '<iframe src="https://www.google.com/maps/embed?…" title="Anfahrt"></iframe>', ['title' => 'Anfahrt', 'ratio' => '4/3']) ?>`.
  Ist der Dienst nicht angelegt oder inaktiv, zeigt der Platzhalter „Dieser Inhalt ist derzeit nicht verfügbar“ ohne
  Schaltflächen (Name aus der gleichnamigen Vorlage; angemeldete Redakteure sehen, welcher Dienst fehlt). Im Platzhaltertext
  werden `{privacy}`, `{imprint}` und `{service_privacy}` zu Links.
- **Eigene Skripte**: `<script type="text/plain" data-consent="matomo" data-src="/pfad/datei.js"></script>` –
  Inline-Inhalt wird wegen der CSP nicht ausgeführt.
- **Karten** des Kerns (MapLibre über den eigenen Proxy) brauchen keine Einwilligung.
- **PHP**: `consent_has('matomo')` (liest den Cookie der Anfrage; nicht hinter dem Seiten-Cache für Inhalte verwenden).

## JavaScript

`window.cmsConsent` (Alias `window.ConsentKit` wie im AddOn): `has(key)`, `accepted()`, `accept(key|keys)` (Einwilligung für
einzelne Dienste wie „… immer erlauben“, für eigene 2-Klick-Lösungen), `open()`, `withdraw()`,
`reset()`, `onChange(fn)`, `embed(provider)`, `allowEmbed(provider, on)`. Ereignisse auf `document`:
`consentkit:ready`, `consentkit:change` (`detail: {accepted, rejected, action}`), `cms:consent`. Mit Consent Mode
zusätzlich `consentkit_ready`/`consentkit_change` im `dataLayer`.

## Protokoll

Einwilligungs-ID, Zeitpunkt, Domain (`main` bzw. `landing:{id}`), Stand (Schnappschuss der angebotenen Dienste mit
Cookies), Entscheidung, akzeptierte/abgelehnte Dienste, GPC-Signal, Sprache – **keine IP-Adresse, kein User-Agent,
keine aufgerufene Seite**. Kennzahlen (30 Tage, je ID die letzte Entscheidung), Filter, CSV (Semikolon, UTF-8 mit BOM).
Aufbewahrung: Einstellung (Standard 1095 Tage), bereinigt täglich nebenbei in der Verwaltung und per Cron:

```bash
php bin/console consent:purge --site=<key> [--days=90]
php bin/console consent:status --site=<key>     # aktive Dienste, Fingerabdrücke, CSP-Hosts
```

Endpunkt `POST /consent/save` (JSON, nur `Sec-Fetch-Site: same-origin` bzw. passender `Origin`, Rate-Limit mit
gehashter IP, IP wird nicht gespeichert).

## Vorlagen

`presets/*.json` (Format: `presets/FORMAT.md`, kompatibel mit dem AddOn), eigene je Website in
`storage/…/consent/presets/*.json` (Verwaltung → Eigene Vorlagen: Import/Export). Zusätzliches Feld `csp`
(`{script, connect, img, frame}`) für CSP-Hosts.

## Übernommen / nicht übernommen

Übernommen: Dienste/Gruppen, 38 Vorlagen, Varianten, Domain-Matrix, Fingerabdrücke + Stände, Consent Mode v2, GPC,
Anbieter-Aufrufe (UET, Clarity, Meta …), Conversions ohne Code, 2-Klick-Platzhalter, Protokoll/CSV/Aufbewahrung,
Design-Editor mit Kontrastprüfung, Import/Export.

Nicht übernommen: Cookie-Scanner, Open-Cookie-Database-Katalog, Übernahme aus consent_manager, Texte je Sprache im
Backend (Texte kommen aus `lang/site/*.php`), WriteAssist-Übersetzung, `<oembed>`-Umwandlung (der Rich-Text des Kerns
lässt keine Einbettungen zu), automatisches Sperren fremder iframes, Inline-`text/plain`-Skripte (CSP).

## Lizenz

MIT wie KLXM Studio; portierte Teile ebenfalls MIT (© Friends Of REDAXO, KLXM Crossmedia GmbH) – siehe `LICENSE`
und `THIRD-PARTY-NOTICES.md`.
