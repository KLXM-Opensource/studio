<?php /** Entwicklerhandbuch · „Video-Werkzeuge“ (Erweiterung video_tools, eingebunden über Extension::docs('technical')) */ ?>
  <p class="lead">Erweiterung <code>video_tools</code> (Paket <code>klxm/studio-video-tools</code>, MIT): Analyse, Web-Presets, Schnitt, Poster und animierte Vorschau für Videos der Mediathek – mit ffmpeg/ffprobe als Hintergrund-Aufträge. Ohne ffmpeg: Analyse aus dem MP4-Kopf (reines PHP) und Poster aus dem Browser. Details: <code>extensions/video_tools/README.md</code>.</p>

  <h3>Aktivieren &amp; Konfiguration</h3>
  <pre><code>// config/sites/{key}.php (Hauptwebsite: config/config.local.php)
'extensions'  =&gt; ['video_tools'],
'features'    =&gt; ['video.tools' =&gt; true],        // Funktion ist Standard AUS
'video_tools' =&gt; [
    'ffmpeg' =&gt; '', 'ffprobe' =&gt; '',   // leer = Erkennung des Kerns (Core\Ffmpeg: ffmpeg_path, PATH, /usr/bin …, per Aufruf – open_basedir-fest)
    'max_jobs' =&gt; 1,        // gleichzeitige Aufträge (installationsweit)
    'nice' =&gt; 10,           // CPU-Priorität (0 = aus)
    'timeout' =&gt; 3600,      // Sekunden je Auftrag
    'max_input_mb' =&gt; 2048, 'min_free_mb' =&gt; 500, 'threads' =&gt; 0,
    'preview' =&gt; false,     // animierte Hover-Vorschau (Standard AUS – das Raster zeigt das Standbild des Kerns, Core\VideoThumbs)
],</code></pre>
  <p>Recht <code>video.tools</code> (Gruppe „Medien“); Original ersetzen/löschen zusätzlich <code>media.delete</code>; Befehlsvorschau und Protokoll nur mit <code>system.manage</code>. Nach dem Aktivieren <code>php bin/console migrate --site=…</code> (Tabellen <code>video_jobs</code>, <code>video_meta</code>, <code>video_links</code> in der Datenbank der Website).</p>

  <h3>Hintergrund-Aufträge</h3>
  <ul>
    <li><b>Start:</b> Die Verwaltung legt den Auftrag an und startet losgelöst <code>php bin/console video:work --site=…</code> (nohup, Log <code>storage/logs/video-jobs.log</code>). Rückfall: <code>Extension::afterAdminResponse</code> (nach der Antwort eines Verwaltungsaufrufs, höchstens alle 20 s) und Cron <code>* * * * * php bin/console video:work --site={key} --all</code> (<code>--site</code> = eine Website, auf der die Erweiterung aktiv ist – nur dort gibt es ihre Befehle).</li>
    <li><b>Gleichzeitigkeit:</b> Plätze <code>storage/video/slot-{n}.lock</code> (installationsweit, <code>max_jobs</code>). <b>Sperre je Auftrag</b> <code>storage/video/locks/{site}-{id}.lock</code>: ist sie frei, obwohl der Auftrag „läuft“, gilt er als abgebrochen.</li>
    <li><b>Fortschritt</b> aus <code>ffmpeg -progress pipe:1</code> (<code>out_time_us</code> / Dauer), Abbruch über <code>cancel=1</code> (geprüft jede Sekunde, SIGTERM → SIGKILL), Zeitlimit, max. Eingangsgröße, freier Speicher vor dem Start, <code>nice -n</code>.</li>
    <li><b>Zwischendateien</b> in <code>storage/video/tmp</code> (außerhalb des Webroots), nach jedem Auftrag gelöscht; Reste älter als 1 Tag räumt <code>video:work</code> auf. Erledigte Aufträge werden nach 30 Tagen entfernt.</li>
  </ul>

  <h3>Sicherheit</h3>
  <ul>
    <li>ffmpeg/ffprobe laufen ausschließlich über <code>proc_open</code> mit <b>Argument-Array</b> (keine Shell) und fester, schlanker Umgebung. Befehle entstehen nur aus festen Presets; Eingaben der Oberfläche sind Preset-Schlüssel (Positivliste) und Zeiten (als Zahlen formatiert). Eigene Befehle gibt es bewusst nicht.</li>
    <li>Jede Datei wird vor der Bearbeitung mit ffprobe geprüft (muss eine Videospur haben), jedes Ergebnis danach; Ergebnisse gehen durch <code>Media::import</code>/<code>Media::replace</code> (Typprüfung, zufällige Dateinamen, Upload-Grenze). Metadaten (z. B. Aufnahmeort) werden bei Web-Presets entfernt (<code>-map_metadata -1</code>).</li>
    <li>Der einzige Shell-Aufruf ist der losgelöste Start des Arbeiters wie bei den KI-Aufträgen – nur mit Werten des Servers (PHP-Pfad, Website-Schlüssel), jeweils <code>escapeshellarg</code>.</li>
    <li>CSP unverändert: Skript und Stil kommen aus <code>/extensions/video_tools/</code> ('self'); Vorschau im Schneide-Fenster über <code>/admin/api/video-tools/media/{id}/stream</code> (HTTP-Range, nur angemeldet).</li>
  </ul>

  <h3>Schnittstellen</h3>
  <table class="doc-table">
    <tr><th>Weg</th><th>Zweck</th></tr>
    <tr><td><code>GET /admin/video-tools</code></td><td>Statusseite (ffmpeg, Einrichtung für Plesk, Grenzen, Presets, Aufträge)</td></tr>
    <tr><td><code>GET /admin/api/video-tools/media/{id}</code></td><td>Analyse, Punktzahl, Empfehlungen, Presets (Admin: Befehl), Versionen, Aufträge, Poster</td></tr>
    <tr><td><code>POST …/media/{id}/optimize</code> · <code>/trim</code> · <code>/poster</code> · <code>/loudness</code></td><td>Auftrag anlegen (<code>preset</code>, <code>mode</code> new|replace, <code>delete_original</code>) · Ausschnitte (<code>clips[{from,to}]</code> in ms, <code>precise</code>) · Poster (<code>at</code> in ms oder Bild <code>image</code> ohne ffmpeg) · Lautheit messen</td></tr>
    <tr><td><code>POST …/bulk</code> · <code>/previews</code></td><td>„Alle optimieren“ (<code>ids</code> oder <code>check</code>) · animierte Vorschau bei Bedarf</td></tr>
    <tr><td><code>GET …/jobs</code> · <code>POST …/jobs/{id}/cancel|retry</code> · <code>GET …/jobs/{id}/log</code></td><td>Aufträge</td></tr>
  </table>
  <p>CLI: <code>video:info &lt;id&gt; [--loudness] [--json]</code>, <code>video:optimize &lt;id&gt; --preset=… [--replace] [--wait]</code>, <code>video:work [--all] [--job=ID]</code>, <code>video:jobs [--open] [--cancel=ID] [--retry=ID] [--log=ID]</code>; <code>health</code> meldet ffmpeg/ffprobe und hängende Aufträge.</p>

  <h3>Kern-Haken, die die Erweiterung nutzt</h3>
  <p><code>Extension::adminAssets</code> (Skript/Stil nur in der Mediathek), <code>mediaJson</code> (Poster, Vorschau, Ring in der Liste), <code>mediaChecks</code> („Videos nicht optimiert“, „Videos ohne Poster“), <code>mediaPoster</code> (Poster in den Video-Blöcken der Kits und in <code>MediaTracks::player</code>), <code>mediaTypes</code> (WebM), <code>on('media.deleted'|'media.replaced')</code>, <code>afterAdminResponse</code>, <code>health</code>, <code>docs</code>, <code>feature(…, false)</code> sowie im Browser <code>window.CMSMedia.extend()</code> – beschrieben unter <a href="#funktionen">Funktionsumfang &amp; Erweiterungen</a>.</p>
