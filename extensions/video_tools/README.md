# Video-Werkzeuge für KLXM Studio (`video_tools`)

Videos der Mediathek **prüfen, fürs Web optimieren, schneiden und mit Poster versehen** – per Klick, als
Hintergrund-Auftrag mit ffmpeg. Der Browser darf geschlossen werden; Ergebnisse erscheinen in der Mediathek.
Ideen aus [FriendsOfREDAXO/ffmpeg](https://github.com/FriendsOfREDAXO/ffmpeg), eigene Umsetzung im Stil der
KLXM-Studio-Mediathek. Lizenz: MIT (`LICENSE`).

## Funktionen

- **Analyse** (Informationen rechts, Quick Look, `video:info`): Codec, Profil, Farbformat, Auflösung, fps, Bitrate,
  Dauer, Tonspuren, Größe, **faststart** (moov vor mdat), Lautheit (EBU R128, auf Knopfdruck). **Punktzahl** 0–100
  mit Empfehlungen („kein faststart“, „Bitrate hoch für 1080p“, „Audio fehlt/zu laut“ …) und passendem Preset.
- **Für Web optimieren** mit festen Presets: `web1080`, `web720`, `mobile540` (H.264/AAC, CRF 23/23/26, faststart),
  `archive` (CRF 18, Originalauflösung), `webm` (VP9/Opus, nur wenn ffmpeg es kann), `faststart` (verlustfrei
  umpacken), `mute` (Ton entfernen), `loudnorm` (−16 LUFS). Ergebnis als **neue Version** („Version von …“, alle
  Angaben, Übersetzungen, Tags, Sammlungen, Untertitel, Transkripte übernommen) oder **Original ersetzen** (ID und
  Verwendungen bleiben). Optional Original danach löschen (nur wenn unbenutzt). Gesparter Speicher wird gemeldet.
  Befehlsvorschau (nur lesen) für die Administration.
- **Schneiden**: Player mit Leiste, Anfang/Ende per Knopf, Eingabe oder Tastatur (`I`/`O`, `J`/`K`/`L`, Leertaste,
  `←`/`→` Einzelbild), Bereich testen, Schleife, mehrere Ausschnitte, **verlustfrei** (Hinweis auf den Keyframe) oder
  **präzise**. Jeder Ausschnitt wird „… (Ausschnitt 00:12–00:34)“; **Untertitel werden zugeschnitten und verschoben**.
- **Poster**: Standbild wählen (ffmpeg, ohne ffmpeg aus dem Browser). Die Video-Blöcke der Kits und
  `MediaTracks::player` verwenden es, wenn kein eigenes Vorschaubild gesetzt ist. Optional **animierte Vorschau** (3 s,
  stumm, `'preview' => true`, Standard aus) für das Raster; das Standbild im Raster erzeugt der Kern automatisch
  (`Core\VideoThumbs`), „Poster wählen“ überschreibt es.
- **Hintergrund-Aufträge** mit Fortschrittsring in der Mediathek, Aufträge-Dialog (Abbrechen, Wiederholen,
  Protokoll), Statusseite **Verwaltung → Video-Werkzeuge**.
- **Prüfen**: „Videos nicht optimiert“ und „Videos ohne Poster“ in der Seitenleiste der Mediathek, mit
  Sammelaktion „Alle optimieren (Preset …)“ bzw. „Poster für alle erzeugen“.

Ohne ffmpeg arbeitet die Erweiterung eingeschränkt weiter: Analyse aus dem MP4-Kopf in reinem PHP, Poster aus dem
Browser; Optimieren/Schneiden sind ausgegraut, die Statusseite erklärt die Einrichtung.

## Installation

Im Projekt: Ordner `extensions/video_tools/`. Als Paket (eigenes Repository):

```bash
composer require klxm/studio-video-tools
php bin/console extensions:publish        # Skript/Stil nach public/assets/ext/video_tools (oder: pnpm run build)
```

Paket-Typ ist `mycms-extension`; der geplante neue Typ `klxm-studio-extension` wird vom Core bereits erkannt
(Einstieg: `extra.klxm-studio.entry` bzw. `extra.mycms.entry`, Standard `extension.php`). Die Skripte/Stile in
`assets/` laufen ohne Bündeln – `extensions:publish` kopiert sie, wenn kein Build vorhanden ist.

Je Website aktivieren (`config/sites/{key}.php`, Hauptwebsite `config/config.local.php`):

```php
'extensions'  => ['video_tools'],
'features'    => ['video.tools' => true],   // Funktion ist Standard AUS
'video_tools' => [
    'ffmpeg' => '', 'ffprobe' => '',        // leer = Erkennung des Kerns (Core\Ffmpeg: 'ffmpeg_path', PATH, /usr/bin, /usr/local/bin, /opt/homebrew/bin, storage/video/bin – per Aufruf, auch mit open_basedir)
    'max_jobs' => 1,                        // gleichzeitige Aufträge (installationsweit)
    'nice' => 10,                           // CPU-Priorität der Aufträge (0 = aus)
    'timeout' => 3600,                      // Sekunden je Auftrag
    'max_input_mb' => 2048,                 // größte Eingangsdatei
    'min_free_mb' => 500,                   // so viel Speicher muss frei bleiben
    'threads' => 0,                         // ffmpeg -threads (0 = automatisch)
    'preview' => false,                     // animierte Hover-Vorschau im Raster (Standard AUS; Standbild kommt aus dem Kern)
],
```

Dann `php bin/console migrate --site=…` und Rollen das Recht **„Videos optimieren, schneiden und Poster setzen“**
(`video.tools`) geben. Original ersetzen/löschen braucht zusätzlich `media.delete`.

Cron (empfohlen, Rückfall für den losgelösten Start):

```
* * * * * php /pfad/zu/bin/console video:work --site=kunde --all
```

`--site=` muss eine Website nennen, auf der die Erweiterung aktiv ist (Befehle von Erweiterungen gibt es nur dort);
`--all` arbeitet dann die Aufträge aller Websites mit `video_tools` ab.

### ffmpeg auf Plesk

1. **Paketverwaltung** (Root): Debian/Ubuntu `apt install ffmpeg`, AlmaLinux/Rocky mit EPEL + RPM Fusion
   `dnf install ffmpeg` – liegt dann in `/usr/bin` und wird gefunden.
2. **Ohne Root**: statisches Build (z. B. johnvansickle.com/ffmpeg, BtbN/FFmpeg-Builds) herunterladen, `ffmpeg` und
   `ffprobe` nach `storage/video/bin/` legen, `chmod 755`.
3. **Eigener Pfad** über `'video_tools' => ['ffmpeg' => …, 'ffprobe' => …]`.
4. `proc_open` darf nicht in `disable_functions` stehen; `open_basedir` muss den Programmpfad erlauben.
5. Prüfen: `php bin/console health --site=…`.

ffmpeg wird **nicht mitgeliefert** (LGPL/GPL) und nur als externes Programm aufgerufen.

## Kommandozeile

```
video:info <id> [--loudness] [--json]                   Analyse, Punktzahl, Empfehlungen
video:optimize <id> --preset=web1080 [--replace] [--wait]   Auftrag anlegen (--wait: sofort ausführen)
video:work [--all] [--job=ID]                           wartende Aufträge abarbeiten (Cron)
video:jobs [--open] [--cancel=ID] [--retry=ID] [--log=ID]
```

## Technik

- Tabellen (Datenbank der Website): `video_jobs`, `video_meta` (Analyse-Cache, Poster, Vorschau), `video_links`
  (Version/Ausschnitt/Poster von …). Nur die Mediathek der Website; geteilte Pools werden nicht bearbeitet.
- Arbeiter: losgelöst per `nohup php bin/console video:work --site=…` (Log `storage/logs/video-jobs.log`), Rückfall
  nach Verwaltungsaufrufen (`Extension::afterAdminResponse`) und per Cron. Plätze `storage/video/slot-{n}.lock`,
  Sperre je Auftrag `storage/video/locks/{site}-{id}.lock` (freie Sperre bei „läuft“ = Prozess weg → fehlgeschlagen).
  Fortschritt aus `-progress pipe:1`, Abbruch (SIGTERM, dann SIGKILL), Zeitlimit, Speicherprüfung, `nice`.
- Sicherheit: nur `proc_open` mit Argument-Array, feste Presets (Positivliste), Zeiten als Zahlen; keine frei
  editierbaren Befehle. Eingang und Ergebnis werden mit ffprobe geprüft; Ergebnisse laufen durch `Media::import` bzw.
  `Media::replace`. Zwischendateien in `storage/video/tmp`, danach gelöscht. CSP unverändert (nur `'self'`).
- Genutzte Kern-Haken: `feature(…, false)`, `adminAssets`, `mediaJson`, `mediaChecks`, `mediaPoster`, `mediaTypes`,
  `on('media.deleted'|'media.replaced')`, `afterAdminResponse`, `health`, `docs`, im Browser
  `window.CMSMedia.extend({ loaded, badge, panel, multi, summary, menu, quickLook })`.
- Symbole: eigenes Sprite `assets/img/icons.svg` (Phosphor duotone, MIT) für Symbole, die der Kern nicht mitbringt –
  neu erzeugen mit `node tools/icons.mjs`.
- Übersetzungen: `lang/en.php` (Verwaltung). Prüfen: `php bin/console i18n:missing en --extension=video_tools`.

## Eigenes Repository

Der Ordner ist in sich geschlossen (Namespace `Klxm\VideoTools`, eigener Autoloader in `extension.php`, eigene
`composer.json`, `LICENSE`, `CHANGELOG.md`, `lang/`, `docs/`). Für das Aufteilen:

1. Ordner mit Historie herauslösen (`git subtree split -P extensions/video_tools`) → Repository
   `klxm/studio-video-tools`, Tag `v1.0.0`.
2. Im Projekt `extensions/video_tools/` entfernen und `composer require klxm/studio-video-tools` (Packagist oder
   `repositories` → `vcs`). Der Core findet das Paket über `vendor/composer/installed.json`.
3. Assets: `php bin/console extensions:publish` (kopiert `assets/`, da kein Build nötig) oder ein Release-Artefakt mit
   `public/` beilegen. Der Monorepo-Build (`pnpm run build`) erfasst nur `extensions/*/assets`.
4. Voraussetzung: Core ≥ 1.0.0 **mit** den generischen Haken aus dem Core-CHANGELOG („Erweiterungen: Mediathek,
   Hintergrund, Betrieb“).
