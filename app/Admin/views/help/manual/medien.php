<?php /** Handbuch · Kapitel „Bilder & Dateien“ · @var array $vars ($vars['image_sizes'] = [[Verwendung, Format, Mindestgröße], …]) */ ?>
  <p class="lead">Die <a href="<?= e(url('/admin/media')) ?>">Mediathek</a> funktioniert wie der Finder auf dem Mac: links Orte, Sammlungen und Tags, in der Mitte die Dateien, rechts die Informationen. Erlaubt sind Bilder (JPG, PNG, WebP, GIF), PDF-Dokumente, MP4-Videos und Audio (MP3, M4A). Die Website erzeugt aus jedem Bild automatisch alle Größen für Handy, Tablet und Bildschirm; Fotos werden dabei neu gespeichert, Kamera-Angaben wie der Aufnahmeort entfernt.</p>

  <h3>Hochladen</h3>
  <ol class="doc-steps">
    <li>Dateien vom Computer <b>in die Mediathek ziehen</b> (auch mehrere auf einmal) – oder <b>Hochladen</b> klicken. Große Dateien bis <?= (int) app()->config->get('media.max_upload_mb', 50) ?> MB werden in Stücken übertragen; der Balken zeigt den Fortschritt.</li>
    <li>Für jedes Bild einen <b>Alt-Text</b> eintragen – ohne ihn startet der Upload nicht. Beschreiben Sie in einem Satz, was zu sehen ist („Zwei Personen im Gespräch am Empfang“). Rein schmückende Bilder markieren Sie als <b>dekorativ</b>.</li>
    <li>Ist links eine <b>Sammlung</b> oder ein <b>Tag</b> gewählt, landen neue Dateien direkt dort.</li>
    <li>Ein kopiertes Bild oder eine Datei fügen Sie auch mit <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>V</kbd> direkt in die Mediathek ein.</li>
  </ol>

  <h3>Ordnen: Tags und Sammlungen</h3>
  <ul>
    <li><b>Tags</b> (farbige Punkte) tragen Sie rechts in den Informationen ein: Wort tippen, <kbd>Enter</kbd>. Links unter „Tags“ filtern Sie danach.</li>
    <li><b>Sammlungen</b> legen Sie links mit <b>+</b> an. Dateien einfach <b>auf die Sammlung ziehen</b>. Eine Datei kann in mehreren Sammlungen liegen. Mit <b><?= icon('dots-three', ['label' => 'Mehr']) ?></b> neben der Sammlung umbenennen oder löschen (die Dateien bleiben erhalten).</li>
    <li>Mehrere Dateien auswählen: <kbd>⌘</kbd>/<kbd>Strg</kbd>-Klick oder <kbd>⇧</kbd>-Klick, alle mit <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>A</kbd>. Rechts erscheinen dann Sammelaktionen (Tag hinzufügen, in Sammlung legen, löschen).</li>
    <li><kbd>Leertaste</kbd> öffnet die große Vorschau (Quick Look), Pfeiltasten blättern, <kbd>↵</kbd> öffnet das Bearbeitungsfenster, <kbd>Entf</kbd> löscht, <kbd>Esc</kbd> hebt die Auswahl auf. Rechtsklick zeigt weitere Aktionen (Quick Look, Original öffnen, aus Sammlung entfernen …).</li>
  </ul>

  <h3>Bearbeiten</h3>
  <ul>
    <li><b>Titel, Alt-Text und Tags</b> ändern Sie direkt rechts in der Seitenleiste – gespeichert wird automatisch.</li>
    <li><b>Doppelklick</b> (oder <b>Alle Details, Fokus &amp; Zuschnitt …</b>) öffnet das große Bearbeitungsfenster mit Fokuspunkt, Zuschnitten, Sammlungen und Fotonachweis. Hier speichern Sie mit <b>Speichern</b>.</li>
    <li><b>Fokuspunkt:</b> Ins Bild klicken, z. B. auf das Gesicht. Wird das Bild in einem anderen Format angezeigt, bleibt dieser Bereich sichtbar.</li>
    <li><b>Zuschneiden:</b> Unter „Zuschnitte je Format“ ein Format anklicken, dann mit Mausrad/Trackpad zoomen und das Bild verschieben. Jedes Format (16:9, 4:3, 1:1 …) kann einen eigenen Ausschnitt haben. <b>Automatisch (Fokuspunkt)</b> entfernt den eigenen Ausschnitt wieder.</li>
    <li><b>Direkt auf der Seite:</b> Im Bearbeitungsmodus erscheint beim Überfahren eines Bildes der Knopf <b>Zuschneiden</b> – das Format der Stelle ist schon gewählt, das Ergebnis sehen Sie sofort.</li>
  </ul>

  <h3 id="bild-bearbeiten">Bild bearbeiten: Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren</h3>
  <p>Im großen Bearbeitungsfenster (Doppelklick auf ein Bild) steht unter der Vorschau die Werkzeugleiste <b>Zuschneiden · Drehen · Spiegeln · Ausrichten · Entzerren</b>; per Rechtsklick geht es auch mit <b>Bild bearbeiten …</b>. Das <b>Original bleibt immer unverändert</b> – gespeichert werden nur Ihre Einstellungen, die Website erzeugt daraus alle Größen neu. Jede Bearbeitung lässt sich deshalb jederzeit ändern oder mit <b>Alles zurücksetzen</b> rückgängig machen.</p>
  <ul>
    <li><b>Zuschneiden:</b> Rahmen verschieben oder an den Ecken ziehen; Seitenverhältnis frei oder 1:1, 4:3, 3:2, 16:9, 16:10, 4:5, 9:16. Der Zuschnitt gilt für das gedrehte und entzerrte Bild. Der Fokuspunkt bleibt erhalten.</li>
    <li><b>Drehen:</b> in 90°-Schritten oder frei von −45° bis +45° (in 0,1°-Schritten, Regler oder Pfeiltasten). Damit keine leeren Ecken entstehen, wird automatisch auf das größte passende Rechteck zugeschnitten – oder Sie wählen <b>Mit Farbe füllen</b>.</li>
    <li><b>Spiegeln:</b> horizontal oder vertikal. Vorsicht bei Schrift und Logos.</li>
    <li><b>Ausrichten:</b> Ein Raster hilft, schiefe Horizonte gerade zu stellen. Mit <b>Horizont ziehen</b> ziehen Sie eine Linie entlang des Horizonts oder einer Hauswand – der Winkel wird daraus berechnet.</li>
    <li><b>Entzerren:</b> Die vier gelben Ecken auf die Ecken einer schräg fotografierten Fläche ziehen (Gebäude, Schild, Bildschirm, Dokument) – sie wird gerade gerückt. Die kleine Vorschau rechts zeigt das Ergebnis.</li>
  </ul>
  <p><b>Vorher/Nachher</b> zeigt das Original zum Vergleich, jedes Werkzeug hat ein eigenes „… zurücksetzen“. Mit <b>Speichern</b> entstehen die neuen Bildgrößen (kann einige Sekunden dauern); überall, wo das Bild verwendet wird, erscheint die neue Fassung. <b>Eigene Zuschnitte je Format</b> (siehe oben) werden dabei zurückgesetzt, weil sich das Bild geändert hat – bitte kurz prüfen. Bei Bildern aus <b>geteilten Medien</b> wirkt die Bearbeitung auf allen Websites, die das Bild nutzen. Bedienung auch mit Tastatur: Ecken bzw. Ausschnitt auswählen (<kbd>Tab</kbd>) und mit den Pfeiltasten verschieben, mit <kbd>⇧</kbd> in großen Schritten. Nicht bearbeitbar sind GIF-Dateien und Videos; Handy-Fotos werden schon beim Hochladen richtig herum gedreht.</p>

  <h3 id="ersetzen">Datei ersetzen (z. B. neues Mitarbeiterfoto)</h3>
  <p>Datei auswählen → <b>Datei ersetzen …</b> (im Bearbeitungsfenster oder per Rechtsklick) → neue Datei wählen. Die neue Datei (gleiche Art, also Bild durch Bild) übernimmt den Platz der alten: <b>überall, wo sie verwendet wird, erscheint automatisch das neue Bild</b>. Alt-Text, Tags und Sammlungen bleiben; Fokuspunkt und eigene Zuschnitte werden zurückgesetzt – bitte kurz prüfen.</p>

  <h3>PDFs und Downloads</h3>
  <p>PDFs öffnen sich auf der Website in einem eigenen, schnellen Betrachter (Mozilla PDF.js) – ohne Programme von Dritten. Der Block <b>Downloads</b> zeigt entweder einzeln gewählte Dateien oder den Inhalt einer ganzen <b>Sammlung</b> (z. B. „Formulare“) – neue PDFs in der Sammlung erscheinen dann automatisch.</p>

  <?php $__sizes = $vars['image_sizes'] ?? null; if ($__sizes): ?>
  <table class="doc-table">
    <tr><th>Verwendung</th><th>Format</th><th>Mindestgröße</th></tr>
    <?php foreach ($__sizes as [$__u, $__f, $__m]): ?><tr><td><?= e($__u) ?></td><td><?= e($__f) ?></td><td><?= e($__m) ?></td></tr><?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>Laden Sie Fotos möglichst groß hoch – mindestens 1600 px breit, für breite Bilder 2400 px. Eigene Zuschnitte sind in diesen Formaten möglich: <?= e(implode(', ', array_values((array) (app()->theme->def['image_ratios'] ?? ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4'])))) ?>. Dateien dürfen höchstens <?= (int) app()->config->get('media.max_upload_mb', 50) ?> MB groß sein.</p>
  <?php endif; ?>
  <div class="doc-note doc-note--warn"><strong>Bildrechte und Einwilligungen</strong><p>Nur Fotos verwenden, für die Nutzungsrechte bestehen. Bei erkennbaren Personen (Team, Kundinnen und Kunden) ist eine schriftliche Einwilligung nötig.</p></div>
  <h3 id="pools">Geteilte Medien</h3>
  <p>Gehört Ihre Website zu einem Verbund, kann es <b>geteilte Mediatheken</b> geben (z. B. Markenbilder einer Unternehmensgruppe). Oben in der Mediathek schalten Sie dann um: <b>Diese Website | ⇄ Name der Mediathek</b>. Geteilte Dateien verwenden Sie wie eigene; ändern, hochladen und löschen dürfen dort nur Personen mit dem Recht „Geteilte Medien pflegen“. Wer es hat, kann eigene Dateien per Rechtsklick <b>In „…“ verschieben (geteilt)</b> – alle bisherigen Verwendungen auf Ihrer Website bleiben erhalten.</p>
  <h3 id="pruefen">Prüfen: Was fehlt noch?</h3>
  <p>Links in der Mediathek steht immer die Gruppe <b>„Prüfen“</b> – mit der Anzahl der betroffenen Dateien (grau = alles erledigt):</p>
  <ul>
    <li><b>Ohne Alt-Text</b> – Bilder ohne Beschreibung (außer als „dekorativ“ markierte).</li>
    <li><b>Alt-Text fehlt in English</b> (je weiterer Sprache der Website) – die Übersetzung der Bildbeschreibung fehlt.</li>
    <li><b>Ohne Titel</b> – Dateien, die nur ihren Dateinamen zeigen.</li>
    <li><b>Videos ohne Untertitel</b> bzw. <b>Audio ohne Transkript</b> (siehe unten).</li>
  </ul>
  <p>Ist die KI eingeschaltet, führen <b>„Mit KI ergänzen“</b> und <b>„Untertitel mit KI“</b> direkt zu den Sammel-Vorschlägen im Bereich <?= e(\Core\AI\Assist::brand()) ?>. Die Zahlen gelten jeweils für die gerade gewählte Mediathek (diese Website oder ein geteilter Pool).</p>

  <h3 id="video-vorschau">Vorschaubilder für Videos</h3>
  <p><b>Vorschaubilder für Videos entstehen automatisch, wenn ffmpeg auf dem Server installiert ist.</b> Die Mediathek nimmt dafür ein Standbild aus dem Video (etwa bei einem Zehntel der Laufzeit, Schwarzblenden am Anfang werden übersprungen) – kein bewegtes Bild. Beim Hochladen entsteht es gleich im Hintergrund; bei älteren Videos erscheint es, sobald Sie sie in der Mediathek ansehen: Bis dahin zeigt die Kachel ein ruhiges Video-Symbol. Das Vorschaubild erscheint im Raster, in der Liste, in Quick Look, bei „Auswählen …“ in Blöcken und Datentabellen – und auf der Website als Standbild im Video-Player, wenn der Block kein eigenes Vorschaubild hat.</p>
  <ul>
    <li><b>Eigenes Bild geht vor:</b> Ein im Block gewähltes Vorschaubild oder ein mit den Video-Werkzeugen gesetztes Poster („Poster wählen“) hat immer Vorrang vor dem automatischen.</li>
    <li><b>Ohne ffmpeg</b> bleibt alles wie bisher: Videos zeigen das Dateisymbol, auf der Website erscheint ohne eigenes Vorschaubild keines.</li>
    <li>Wird die Videodatei ersetzt, entsteht automatisch ein neues Vorschaubild.</li>
  </ul>

  <h3 id="untertitel">Untertitel &amp; Transkripte (Videos und Audio)</h3>
  <p>Wer nicht hören kann, braucht zu jedem Video <b>Untertitel</b>, zu Audio (z. B. Podcast) ein <b>Transkript</b> – so verlangen es die Barrierefreiheits-Regeln (WCAG 1.2.1, 1.2.2, 1.2.3; BITV/BFSG). Ein Video oder eine Audiodatei auswählen – rechts erscheint <b>„Untertitel &amp; Transkript“</b>:</p>
  <ul>
    <li><b>Hochladen (.vtt/.srt):</b> fertige Untertitel-Dateien, z. B. vom Videoschnitt. SRT wird automatisch in WebVTT umgewandelt; Formatierungen außer fett/kursiv werden entfernt. Sprache und Art wählen („Untertitel“ oder „für Gehörlose“ mit Geräuschen wie [Musik], dazu „Kapitel“).</li>
    <li><b>Neu schreiben:</b> Der Editor zeigt links das Video, rechts die Liste mit Anfangs- und Endzeit und Text. Video abspielen, an passender Stelle <b>„+ Untertitel an aktueller Zeit“</b>; mit ⇤/⇥ übernehmen Sie die aktuelle Zeit als Beginn/Ende. Tastatur: <kbd>Alt</kbd>+<kbd>Enter</kbd> neuer Untertitel, <kbd>Alt</kbd>+<kbd>P</kbd> Abspielen/Pause, <kbd>Alt</kbd>+<kbd>↑</kbd>/<kbd>↓</kbd> blättern, <kbd>Strg</kbd>/<kbd>⌘</kbd>+<kbd>S</kbd> speichern. Die Vorschau im Video zeigt sofort, wie es aussieht.</li>
    <li><b>Mit KI transkribieren:</b> Die KI hört die Tonspur ab (auf dem eigenen Server, ohne Datenweitergabe – sofern so eingerichtet) und schreibt Untertitel mit Zeiten. Das läuft im Hintergrund; bei langen Videos kann es dauern, die Seite dürfen Sie verlassen. Ergebnis ist ein <b>Entwurf „KI-Transkript – bitte prüfen“</b>.</li>
    <li><b>Mit KI übersetzen</b> (Menü ⋯ an einer Untertitel-Spur): Übersetzt Eintrag für Eintrag in eine andere Sprache, die Zeiten bleiben gleich – ebenfalls als Entwurf.</li>
    <li><b>Transkript:</b> Der ganze Text zum Nachlesen. Er erscheint unter dem Video als aufklappbares <b>„Transkript anzeigen“</b> und wird von der Website-Suche gefunden. Nach einer KI-Transkription ist er schon vorausgefüllt.</li>
  </ul>
  <div class="doc-note doc-note--warn"><strong>Prüfpflicht bei KI-Untertiteln</strong><p>Entwürfe erscheinen <b>nicht</b> auf der Website. Bitte das Video vollständig ansehen und die Untertitel korrigieren – vor allem Namen, Fachbegriffe, Medikamente, Zahlen und Uhrzeiten –, dann <b>„Geprüft – veröffentlichen“</b>. Erst damit gehen Untertitel und Transkript online. Gut lesbar: höchstens zwei Zeilen à etwa 42 Zeichen, jeder Untertitel mindestens eine Sekunde sichtbar.</p></div>
  <p>Auf der Website schalten Besucher die Untertitel im Player ein; in der Sprache der Seite sind sie schon eingeschaltet. Hintergrundvideos im Kopfbereich (stumm, nur Stimmung) brauchen keine Untertitel. Im Bereich <b><?= e(\Core\AI\Assist::brand()) ?> → Untertitel</b> sehen Sie alle Videos ohne Untertitel, offene Entwürfe und den Stand laufender KI-Aufträge.</p>
