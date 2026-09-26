<?php /** Handbuch · Kapitel „Video-Werkzeuge“ (Erweiterung video_tools, eingebunden über Extension::docs('manual')) */ ?>
  <p class="lead">Mit den <b>Video-Werkzeugen</b> machen Sie Videos aus der <a href="<?= e(url('/admin/media')) ?>">Mediathek</a> fit für die Website: prüfen, verkleinern, schneiden und ein Vorschaubild (Poster) festlegen. Die Arbeit läuft <b>im Hintergrund auf dem Server</b> – Sie können weiterarbeiten oder den Browser schließen; das Ergebnis erscheint von selbst in der Mediathek.</p>

  <h3>Video prüfen</h3>
  <p>Video in der Mediathek anklicken. Rechts unter <b>Video-Werkzeuge</b> sehen Sie eine <b>Punktzahl</b> (0–100) und konkrete Hinweise, zum Beispiel:</p>
  <ul>
    <li><b>„Nicht für Web optimiert: kein faststart“</b> – der Browser muss erst die ganze Datei laden, bevor das Video startet.</li>
    <li><b>„Bitrate hoch für 1080p“</b> – die Datei ist viel größer als nötig; Besucher mit Handy warten lange.</li>
    <li><b>„Audio fehlt“</b> bzw. <b>„Audio zu laut“</b> (nach <b>Lautheit messen</b>).</li>
  </ul>
  <p>Darunter stehen Codec, Auflösung, Bildrate, Bitrate, Dauer, Ton, Größe und „faststart ja/nein“. Hinter jedem Hinweis führt <b>Beheben …</b> direkt zum passenden Preset. In der großen Vorschau (<kbd>Leertaste</kbd>) steht die Punktzahl unten in der Zeile.</p>

  <h3>Für Web optimieren</h3>
  <ol class="doc-steps">
    <li>Video auswählen → <b>Für Web optimieren …</b> (oder Rechtsklick).</li>
    <li>Ein <b>Preset</b> wählen: <b>Web 1080p</b> (Standard für die Website), <b>Web 720p</b> (kleiner), <b>Mobil 540p</b> (sehr klein), <b>Archiv</b> (hohe Qualität), <b>WebM</b> (zusätzliches offenes Format, falls verfügbar), <b>Nur faststart</b> (verlustfrei, sekundenschnell), <b>Ton entfernen</b> oder <b>Lautheit normalisieren</b>. Das empfohlene Preset ist markiert.</li>
    <li><b>Ergebnis:</b> als <b>neue Version</b> (Standard) – Titel, Beschreibung, Übersetzungen, Tags, Sammlungen, Untertitel und Transkripte werden übernommen, die neue Datei ist als „Version von …“ mit dem Original verknüpft. Oder das <b>Original ersetzen</b>: ID, Verwendungen auf der Website und Untertitel bleiben. Ersetzen und „Original danach löschen“ dürfen nur Rollen mit dem Recht <b>Medien löschen</b>; gelöscht wird nur, wenn das Original nirgends verwendet wird.</li>
    <li><b>Im Hintergrund starten</b>. Am Video erscheint ein <b>Ring mit Prozent</b>; ist der Auftrag fertig, meldet die Mediathek, wie viel Speicher gespart wurde.</li>
  </ol>

  <h3>Schneiden</h3>
  <ol class="doc-steps">
    <li>Video auswählen → <b>Schneiden …</b>.</li>
    <li>Mit der Leiste unter dem Video zur gewünschten Stelle spulen und <b>Anfang setzen</b> (<kbd>I</kbd>) bzw. <b>Ende setzen</b> (<kbd>O</kbd>) – oder die Zeiten eintippen (z. B. <code>1:02.5</code>). <b>Bereich testen</b> spielt nur den Ausschnitt, <b>Schleife</b> wiederholt ihn.</li>
    <li><b>Ausschnitt hinzufügen</b> – so entstehen auch <b>mehrere Ausschnitte</b> aus einem Video.</li>
    <li><b>Verlustfrei (schnell)</b> schneidet ohne Qualitätsverlust, beginnt aber am letzten Schlüsselbild (Keyframe) vor dem Anfang – der Hinweis unter den Zeiten sagt, wie viel früher. <b>Präzise</b> schneidet bildgenau und dauert länger.</li>
    <li>Jeder Ausschnitt wird eine neue Datei „… (Ausschnitt 00:12–00:34)“. <b>Untertitel werden passend zugeschnitten und verschoben.</b> Das Original bleibt unverändert.</li>
  </ol>
  <p>Tastatur im Schneide-Fenster: <kbd>Leertaste</kbd> Abspielen/Pause, <kbd>J</kbd>/<kbd>L</kbd> 5 Sekunden zurück/vor, <kbd>K</kbd> Pause, <kbd>←</kbd>/<kbd>→</kbd> ein Bild (mit <kbd>⇧</kbd> eine Sekunde), <kbd>I</kbd>/<kbd>O</kbd> Anfang/Ende.</p>

  <h3>Poster (Vorschaubild)</h3>
  <p><b>Poster …</b> öffnet das Video: zur passenden Stelle spulen, <b>Dieses Bild als Poster</b>. Das Standbild wird als Bild in der Mediathek abgelegt (dekorativ, Tag „poster“). <b>Video-Blöcke der Kits zeigen es automatisch</b>, wenn im Block kein eigenes Vorschaubild gewählt ist; in der Mediathek erscheint es statt des „MP4“-Symbols. Beim Überfahren eines Videos spielt eine kurze, stumme Vorschau.</p>

  <h3>Prüfen und alle auf einmal</h3>
  <p>Links unter <b>Prüfen</b> finden Sie <b>Videos nicht optimiert</b> und <b>Videos ohne Poster</b>. Ist einer der Filter gewählt (ohne Auswahl), bietet die rechte Spalte <b>Alle optimieren</b> mit einem Preset bzw. <b>Poster für alle erzeugen</b> an. Mehrere markierte Videos optimieren Sie über die Mehrfachauswahl oder den Rechtsklick.</p>

  <h3>Aufträge</h3>
  <p>Der Knopf mit der Warteschlange in der Werkzeugleiste der Mediathek (bzw. <b>Alle Video-Aufträge</b> rechts) zeigt alle Aufträge mit Stand: <b>Abbrechen</b> (auch während er läuft), <b>Wiederholen</b> nach einem Fehler, <b>Anzeigen</b> springt zum Ergebnis. Die Administration sieht zusätzlich das <b>Protokoll</b>.</p>

  <p class="doc-note">Die Video-Werkzeuge sind eine Erweiterung und müssen für die Website eingeschaltet sein (Recht „Videos optimieren, schneiden und Poster setzen“). Fehlt auf dem Server das Programm ffmpeg, sehen Sie nur die Prüfung und können Poster aus dem Browser setzen – die Administration findet unter <a href="<?= e(url('/admin/video-tools')) ?>">Video-Werkzeuge</a> den Status und die Einrichtung.</p>
