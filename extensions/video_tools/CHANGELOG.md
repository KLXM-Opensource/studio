# Changelog – Video-Werkzeuge (`video_tools`)

## 1.0.1 – 2026-09-26

- ffmpeg/ffprobe-Erkennung und Aufrufe kommen aus dem Kern (`Core\Ffmpeg`): per Aufruf statt Dateiprüfung, damit auch
  mit `open_basedir` (Plesk) gefunden; eigener Pfad `video_tools.ffmpeg`/`ffprobe` geht weiter vor. Statusseite zeigt
  `open_basedir` und einen Hinweis, wenn nichts gefunden wird.
- Unterprozesse und der losgelöste Arbeiter öffnen kein `/dev/null` mehr (open_basedir); `nice` wird per Aufruf erkannt.
- Animierte Vorschau (`preview`) ist jetzt Standard **aus**; wartende `preview`-Aufträge werden bei ausgeschalteter
  Vorschau verworfen. Das Standbild im Raster liefert der Kern (`Core\VideoThumbs`), „Poster wählen“ hat Vorrang.
- Aufträge, die über 2 Minuten ohne Arbeiter warten, zeigen „wartet auf Hintergrunddienst“ (Uhr-Symbol, Hinweis auf
  Cron/`php_cli`) statt eines endlosen Kreisels.

## 1.0.0 – 2026-09-25

Erste Version (Ideen aus FriendsOfREDAXO/ffmpeg, eigene Umsetzung im Stil der KLXM-Studio-Mediathek).

- **Analyse** je Video: Codec, Profil, Farbformat, Auflösung, Bildrate, Bitrate, Dauer, Tonspuren, Größe, faststart
  (moov vor mdat), optional Lautheit (EBU R128); Punktzahl 0–100 mit konkreten Empfehlungen und passendem Preset.
  Ohne ffmpeg: Analyse aus dem MP4-Kopf in reinem PHP (`Mp4Info`).
- **Presets**: Web 1080p, Web 720p, Mobil 540p (H.264/AAC, faststart), Archiv (CRF 18), WebM VP9/Opus (falls
  vorhanden), Nur faststart (verlustfrei), Ton entfernen, Lautheit normalisieren. Ergebnis als neue, verknüpfte
  Version (Angaben, Übersetzungen, Tags, Sammlungen, Untertitel, Transkripte übernommen) oder Original ersetzen;
  optional Original danach löschen (nur unbenutzt). Gesparter Speicher wird gemeldet. Befehlsvorschau für Admins.
- **Schneiden**: Player mit Leiste, Anfang/Ende (Knöpfe, I/O, Eingabe), J/K/L, Leertaste, Einzelbild, Bereich testen,
  Schleife, mehrere Ausschnitte, verlustfrei (Keyframe-Hinweis) oder präzise; Untertitel werden zugeschnitten und
  verschoben (auch um den Keyframe-Vorlauf).
- **Poster** aus einem Standbild (ffmpeg oder – ohne ffmpeg – aus dem Browser); Video-Blöcke der Kits und
  `MediaTracks::player` nutzen es automatisch. Animierte Vorschau (3 s, stumm) für das Raster, bei Bedarf erzeugt.
- **Hintergrund-Aufträge** (`video_jobs`): losgelöster Arbeiter, Rückfall nach Verwaltungsaufrufen und Cron,
  installationsweite Plätze, Sperre je Auftrag, Fortschritt aus `-progress`, Abbrechen/Wiederholen/Protokoll,
  Zeitlimit, max. Eingangsgröße, Speicherprüfung, `nice`. Fortschrittsring in der Mediathek, Aufträge-Dialog.
- **Prüfen**: „Videos nicht optimiert“, „Videos ohne Poster“ mit Sammelaktionen; Mehrfachauswahl „n Videos optimieren“.
- Statusseite mit Einrichtungshilfe (Plesk), `health`-Prüfungen, CLI `video:info`, `video:optimize`, `video:work`,
  `video:jobs`; Handbuch- und Technik-Kapitel; englische Übersetzung.
