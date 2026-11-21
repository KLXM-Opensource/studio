<?php /** Handbuch · Kapitel „Bilder & Dateien“ · @var array $vars ($vars['image_sizes'] = [[Verwendung, Format, Mindestgröße], …]) */ ?>
  <p class="lead">Die <a href="<?= e(url('/admin/media')) ?>">Mediathek</a> funktioniert wie der Finder auf dem Mac: links Orte, Sammlungen und Tags, in der Mitte die Dateien, rechts die Informationen. Erlaubt sind Bilder (JPG, PNG, WebP, GIF), SVG-Grafiken (<a href="#svg">bereinigt</a>), PDF-Dokumente, MP4-Videos und Audio (MP3, M4A). Die Website erzeugt aus jedem Bild automatisch alle Größen für Handy, Tablet und Bildschirm; Fotos werden dabei neu gespeichert, Kamera-Angaben wie der Aufnahmeort entfernt.</p>

  <h3>Hochladen</h3>
  <ol class="doc-steps">
    <li>Dateien vom Computer <b>in die Mediathek ziehen</b> (auch mehrere auf einmal) – oder <b>Hochladen</b> klicken. Große Dateien bis <?= \Core\Media::maxMb() ?> MB werden in Stücken übertragen; der Balken zeigt den Fortschritt. Die Grenze stellen Sie unter Grundeinstellungen → Mediathek ein.</li>
    <li>Für jedes Bild einen <b>Alt-Text</b> eintragen – ohne ihn startet der Upload nicht. Beschreiben Sie in einem Satz, was zu sehen ist („Zwei Personen im Gespräch am Empfang“). Rein schmückende Bilder markieren Sie als <b>dekorativ</b>.</li>
    <li>Ist links eine <b>Sammlung</b> oder ein <b>Tag</b> gewählt, landen neue Dateien direkt dort.</li>
    <li>Ein kopiertes Bild oder eine Datei fügen Sie auch mit <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>V</kbd> direkt in die Mediathek ein.</li>
  </ol>

  <p>Die Mediathek merkt sich, wo Sie zuletzt waren (Sammlung, Tag, Dateiart, geteilte Medien) – beim nächsten Öffnen und in den
    Auswahlfenstern der Bildfelder geht es dort weiter.</p>

  <h3 id="ansicht">Ansicht: Symbole, Mauerwerk, Liste</h3>
  <ul>
    <li>Oben in der Leiste wählen Sie die <b>Darstellung</b>: <b>Symbole</b> (gleich große Kacheln – Hochkant-Bilder werden eingepasst, nicht beschnitten), <b>Mauerwerk</b> (jedes Bild im eigenen Seitenverhältnis, lückenlos untereinander – gut für Fotostrecken) oder <b>Liste</b> (mit Größe, Datum und Verwendung).</li>
    <li>Der Regler <b>Symbolgröße</b> daneben macht die Kacheln größer oder kleiner. Darstellung und Größe merkt sich die Mediathek je Browser.</li>
    <li><b>Sortieren</b> in Symbolen und Mauerwerk über das Auswahlfeld daneben: neueste oder älteste zuerst, zuletzt geändert, Name A–Z bzw. Z–A, größte oder kleinste zuerst, Art. In der Liste sortieren die Spaltenköpfe – beide nutzen dieselbe Sortierung.</li>
    <li>Die Bereiche links (<b>Sammlungen</b>, <b>Tags</b> …) lassen sich mit dem Pfeil zuklappen, wenn die Liste lang wird.</li>
    <li>Beim Hochladen erscheint das Fenster mit Fortschritt und Alt-Texten in der Mitte des Bildschirms.</li>
    <li><b>Große Fotos</b> (z. B. vom Handy) verkleinert schon der Browser auf höchstens 3200 Pixel, bevor sie hochgeladen werden – das geht viel schneller. Die Lage des Fotos bleibt richtig, Kameradaten wie der Aufnahmeort werden dabei entfernt.</li>
  <li><b>Galerie und Slider:</b> Im Block steht neben „+ Bild“ der Knopf <b>Bilder auswählen …</b> – mehrere Bilder anklicken (die Nummer zeigt die Reihenfolge) und übernehmen; je Bild entsteht ein Eintrag.</li>
  </ul>

  <h3 id="nicht-verwendet">Aufräumen: „Nicht verwendet“</h3>
  <p>Links unter den Orten zeigt <b>Nicht verwendet</b> alle Dateien, die nirgends eingebunden sind – weder auf Seiten noch in Datensätzen, Einstellungen oder als Link im Text. Die Zahl daneben verrät, wie viele es sind. Ideal zum Aufräumen: prüfen, mehrere auswählen, löschen. Dateien in Entwürfen gelten als verwendet.</p>

  <h3 id="papierkorb">Papierkorb</h3>
  <p>Gelöschte Dateien landen im <b>Papierkorb</b> (links unter den Orten) und lassen sich dort mit <b>Wiederherstellen</b> zurückholen. Nach der eingestellten Zeit – Standard 30 Tage – werden sie endgültig gelöscht; im Papierkorb geht das auch sofort (<b>Endgültig löschen</b> bzw. <b>Papierkorb leeren</b>). Verwendete Dateien lassen sich wie bisher gar nicht erst löschen. Die Dauer – oder „Aus“ für sofortiges Löschen – legt die Administration unter <b>Grundeinstellungen → Mediathek</b> fest. Geteilte Medien haben keinen Papierkorb.</p>

  <h3 id="aufraeumen">Doppelte und zu große Dateien</h3>
  <ul>
    <li><b>Prüfen → Doppelte Dateien</b> zeigt Dateien, die mehrfach hochgeladen wurden (gleicher Inhalt, auch unter anderem Namen). Rechts steht bei jeder Datei, wo die Kopie liegt. Nicht verwendete Kopien einfach löschen; ist eine Kopie irgendwo eingebunden, dort zuerst die andere Datei auswählen.</li>
    <li><b>Prüfen → Zu große Dateien</b> listet Dateien über der Grenze aus den Grundeinstellungen (Standard 8 MB). Fotos lassen sich mit <b>Verkleinern</b> sparsamer speichern (PNG-Fotos ohne Transparenz werden zu JPG) – Aussehen, ID und Verwendungen bleiben. Große PDFs bitte vorher verkleinern und mit <b>Datei ersetzen</b> austauschen. <b>Große Videos</b> verkleinert die Website selbst: Sie erzeugt im Hintergrund eine Web-Fassung, die beim Besuch sofort startet; das Original bleibt in der Mediathek, eine zweite Datei erscheint dort nicht.</li>
  </ul>

  <h3 id="rechte">Bildrechte und Einwilligungen</h3>
  <p>Rechts unter <b>Rechte &amp; Einwilligung</b> (zugeklappt) tragen Sie zu jeder Datei ein: <b>Lizenz / Quelle</b> (z. B. „eigenes Foto“, „Pixabay-Lizenz“), <b>Nutzungsrecht bis</b> (leer = unbefristet) und bei Fotos, ob abgebildete Personen <b>eingewilligt</b> haben oder keine Personen erkennbar sind. Läuft ein Nutzungsrecht in 30 Tagen ab, erscheint die Datei unter <b>Prüfen → Nutzungsrecht läuft ab</b>.</p>
  <p>Die Administration entscheidet unter <b>Grundeinstellungen → Mediathek</b>, wie streng das ist: <b>ausblenden</b>, <b>optional</b> (Standard – die Felder stehen zugeklappt bereit, nichts wird angemahnt) oder <b>Pflicht</b> – dann geht kein Hochladen ohne Lizenz/Quelle und Angabe zu den Personen, und „Prüfen“ zeigt alle Dateien, denen etwas fehlt.</p>

  <h3 id="zip">Mehrere Dateien herunterladen</h3>
  <p>Mehrere Dateien auswählen (<kbd>⌘</kbd>/<kbd>Strg</kbd>-Klick) → rechts <b>Als ZIP herunterladen</b> (auch im Rechtsklick-Menü). Das ZIP enthält die Originale mit lesbaren Dateinamen.</p>

  <h3>Ordnen: Tags und Sammlungen</h3>
  <ul>
    <li><b>Tags</b> (farbige Punkte) tragen Sie rechts in den Informationen ein: Wort tippen, <kbd>Enter</kbd>. Links unter „Tags“ filtern Sie danach.</li>
    <li><b>Sammlungen</b> legen Sie links mit <b>+</b> an. Dateien einfach <b>auf die Sammlung ziehen</b>. Eine Datei kann in mehreren Sammlungen liegen. Mit <b><?= icon('dots-three', ['label' => 'Mehr']) ?></b> neben der Sammlung umbenennen oder löschen (die Dateien bleiben erhalten).</li>
    <li>Mehrere Dateien auswählen: <kbd>⌘</kbd>/<kbd>Strg</kbd>-Klick oder <kbd>⇧</kbd>-Klick, alle mit <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>A</kbd>. Rechts erscheinen dann Sammelaktionen (Tag hinzufügen, in Sammlung legen, löschen).</li>
    <li><b>Verwendete Dateien lassen sich nicht löschen.</b> Die Mediathek nennt, wo die Datei steckt (Seiten, Datensätze, Einstellungen, Links im Text) – dort zuerst entfernen. Soll nur das Bild ausgetauscht werden, nutzen Sie <b>Ersetzen</b>: Alle Verwendungen bleiben erhalten. Beim Löschen mehrerer Dateien bleiben verwendete einfach liegen. In <b>geteilten Medien</b> wird zusätzlich auf allen anderen Websites geprüft, die die Mediathek nutzen – die Meldung nennt dann auch die Website.</li>
    <li><kbd>Leertaste</kbd> öffnet die große Vorschau (Quick Look), Pfeiltasten blättern, <kbd>↵</kbd> öffnet das Bearbeitungsfenster, <kbd>Entf</kbd> löscht, <kbd>Esc</kbd> hebt die Auswahl auf. Rechtsklick zeigt weitere Aktionen (Quick Look, Original öffnen, aus Sammlung entfernen …).</li>
  </ul>

  <h3>Bearbeiten</h3>
  <ul>
    <li><b>Titel, Alt-Text und Tags</b> ändern Sie direkt rechts in der Seitenleiste – gespeichert wird automatisch.</li>
    <li><b>Doppelklick</b> (oder <b>Alle Details, Fokus &amp; Zuschnitt …</b>) öffnet das große Bearbeitungsfenster mit Fokuspunkt, Zuschnitten, Sammlungen und Fotonachweis. Hier speichern Sie mit <b>Speichern</b>.</li>
    <li><b>Fokuspunkt:</b> Ins Bild klicken, z. B. auf das Gesicht. Wird das Bild in einem anderen Format angezeigt, bleibt dieser Bereich sichtbar.</li>
    <li><b>Zuschneiden:</b> Unter „Zuschnitte je Format“ ein Format anklicken, dann mit Mausrad/Trackpad zoomen und das Bild verschieben. Jedes Format (16:9, 4:3, 1:1 …) kann einen eigenen Ausschnitt haben. <b>Automatisch (Fokuspunkt)</b> entfernt den eigenen Ausschnitt wieder.</li>
    <li><b>Direkt auf der Seite:</b> Im Bearbeitungsmodus erscheint beim Überfahren eines Bildes oben links ein runder Knopf mit Bild-Symbol; sein Menü enthält <b>Zuschneiden</b> – das Format der Stelle ist schon gewählt, das Ergebnis sehen Sie sofort.</li>
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

  <h3 id="bild-anpassen">Bild anpassen: Effekte, Farbe, Schärfe</h3>
  <p><b>Anpassen</b> ändert, wie ein Bild auf der Website erscheint – ohne die Datei zu verändern. Im großen Bearbeitungsfenster der Mediathek gilt die Anpassung <b>überall</b>, wo das Bild verwendet wird; im Bearbeitungsmodus am Bild (Knopf <b>Anpassen</b>) bzw. in der Seitenleiste am Bild-Feld gilt sie <b>nur für diese Stelle</b>. Die Vorschau im Fenster zeigt jede Änderung sofort, <b>Original zeigen</b> vergleicht mit dem unveränderten Bild.</p>
  <ul>
    <li><b>Effekt:</b> S/W, Sepia, Warm, Kühl, Entsättigt, Kräftig oder Kontrast.</li>
    <li><b>Sättigung, Helligkeit, Kontrast:</b> Regler in 10-%-Schritten.</li>
    <li><b>Schärfe:</b> ein Regler von <b>−100</b> (weicher) über <b>0</b> bis <b>+100</b> (schärfer). Nach links wird das Bild weichgezeichnet – z. B. für ein ruhiges Hintergrundbild hinter Text; die Ränder bleiben dabei sauber im Bildrahmen. Nach rechts wird es nachgeschärft – für leicht weiche Fotos. Sanfte Werte (−20 … +40) wirken meist am natürlichsten. <b>Schärfe zurücksetzen</b> stellt nur diesen Regler auf 0, <b>Zurücksetzen</b> alle Einstellungen.</li>
  </ul>
  <p>Gut zu wissen: Die Schärfe wirkt nur in der Anzeige des Browsers, sie ersetzt keine echte Bildbearbeitung. Für Druck oder stark verwackelte Fotos schärfen Sie das Bild vor dem Hochladen in einem Bildprogramm. Der Alt-Text bleibt unverändert. Im Kontrastmodus von Windows werden Bilder ohne Anpassungen gezeigt.</p>

  <h3 id="bild-im-rahmen">Bild im Rahmen: füllen, einpassen, Originalformat</h3>
  <p>Viele Blöcke zeigen Bilder in einem <b>Rahmen mit festem Format</b>, z. B. 16:10 bei Karten oder 4:3 in der Galerie. Normalerweise füllt das Bild den Rahmen und wird am Rand beschnitten – Fokuspunkt und Zuschnitt bestimmen, was sichtbar bleibt. Passt das nicht (Hochformat im Querformat-Rahmen, Logo, Grafik mit Schrift), wählen Sie im Bearbeitungsmodus am Bild <b>Rahmen</b> (oder in der Seitenleiste am Bild-Feld <b>Rahmen …</b>):</p>
  <ul>
    <li><b>Füllen (zuschneiden):</b> wie bisher – der Rahmen ist ganz gefüllt, Ränder werden abgeschnitten.</li>
    <li><b>Einpassen:</b> das ganze Bild ist im Rahmen zu sehen, nichts wird abgeschnitten. Die freie Fläche bekommt einen <b>Hintergrund</b>: <b>Transparent</b> (die Fläche des Kits scheint durch), eine <b>Farbe des Kits</b> (z. B. „Fläche“ oder „Band“), eine <b>eigene Farbe</b> oder <b>Unscharf</b> – eine vergrößerte, weichgezeichnete Kopie des Bildes, wie man es von Fotos im Hochformat kennt.</li>
    <li><b>Originalformat:</b> der Rahmen übernimmt das Seitenverhältnis des Bildes – ein Hochformat bleibt hoch, nichts wird abgeschnitten. Die Karte wird dadurch höher oder niedriger.</li>
  </ul>
  <p>Die Vorschau im Fenster zeigt das Bild im Rahmen genau dieser Stelle. Die Einstellung gilt <b>nur für diese Stelle</b> und wird mit dem Entwurf gespeichert (<b>Speichern</b>/<b>Veröffentlichen</b>). Kommt dasselbe Bild in einem Block mehrmals vor, gilt die Einstellung für alle diese Stellen des Blocks.</p>
  <p><b>Standard des Bildes:</b> Im großen Bearbeitungsfenster der Mediathek legen Sie unter <b>Darstellung im Rahmen</b> fest, wie das Bild überall dort erscheint, wo keine eigene Einstellung gewählt ist. Ohne Angabe gilt <b>Automatisch</b>: Fotos füllen den Rahmen, <b>SVG-Grafiken und PNG-Logos mit transparentem Rand</b> werden eingepasst (transparent) – so werden Logos in Galerien und Karten nicht mehr abgeschnitten. Ist für das Format ein eigener Zuschnitt gesetzt, bleibt es beim Füllen. Der Alt-Text bleibt unverändert; der unscharfe Hintergrund ist reine Dekoration und wird nicht vorgelesen.</p>

  <h3 id="bild-tauschen">Bild auf der Seite tauschen oder neu hochladen</h3>
  <p><b>Leerer Bildplatz:</b> Ein Klick auf den grauen Platzhalter („Bild · 4:3“) bietet gleich <b>Bild wählen …</b> und <b>Hochladen</b> an. <p>Im Bearbeitungsmodus öffnet der runde Knopf am Bild eines Blocks ein Menü (auch per Tastatur: Tab, Pfeil nach unten, Esc schließt): neben <b>Anpassen</b>, <b>Rahmen</b> und <b>Zuschneiden</b> stehen dort
    <b>Tauschen</b> (Auswahl aus der Mediathek) und <b>Hochladen</b> (öffnet gleich die Dateiauswahl – die neue Datei landet in der
    Mediathek und steht sofort an dieser Stelle). Anpassung und Rahmen der Stelle beginnen beim neuen Bild von vorn. Gespeichert
    wird wie immer mit dem Entwurf. Bilder von Einträgen (z. B. Referenzen) tauschen Sie auf deren Detailseite: <b>Bearbeiten</b>, dann
    <b>Bild wählen …</b> am Bild oder ein Klick aufs Bild.</p>

  <p id="link-kopieren"><b>Link kopieren:</b> Im Bearbeitungsfenster kopiert <b>Link kopieren</b> die öffentliche Adresse der Datei (z. B. <code>https://ihre-domain.de/media/2026/10/sommerfest-2026.pdf</code>) – direkt zum Einfügen in Newsletter oder E-Mails, ohne Umweg über die Website.</p>

  <h3 id="ersetzen">Datei ersetzen (z. B. neues Mitarbeiterfoto)</h3>
  <p>Datei auswählen → <b>Datei ersetzen …</b> (rechts in der Seitenleiste, im Bearbeitungsfenster oder per Rechtsklick). Daneben öffnet <b>⋯</b> alle weiteren Aktionen – dasselbe Menü wie der Rechtsklick → neue Datei wählen. Die neue Datei (gleiche Art, also Bild durch Bild) übernimmt den Platz der alten: <b>überall, wo sie verwendet wird, erscheint automatisch das neue Bild</b>. Alt-Text, Tags und Sammlungen bleiben; Fokuspunkt und eigene Zuschnitte werden zurückgesetzt – bitte kurz prüfen.</p>

  <h3 id="svg">SVG-Grafiken</h3>
  <p>Logos, Icons und Illustrationen laden Sie am besten als <b>SVG</b> hoch: Sie sind klein und in jeder Größe gestochen scharf. Eine SVG ist aber kein Bild wie ein Foto, sondern ein kleines Programm aus Text – darin könnten Skripte oder Verweise auf fremde Server stecken. Deshalb <b>bereinigt und optimiert</b> die Mediathek jede SVG beim Hochladen und speichert nur das Ergebnis (nie die Originaldatei). Die Meldung nach dem Hochladen zeigt, was passiert ist, z. B. „SVG bereinigt und optimiert: 48 KB → 12 KB, 3 unsichere Elemente entfernt“.</p>
  <ul>
    <li><b>Entfernt, weil unsicher:</b> Skripte, Ereignisse (<code>onclick</code>, <code>onload</code> …), eingebettete Webseiten (<code>foreignObject</code>, <code>iframe</code>), Animationen, Links und Verweise auf andere Dateien oder Server, nachgeladene Stile und Schriften (<code>@import</code>, <code>url(https://…)</code>), gefährliche CSS-Tricks, Entity- und DOCTYPE-Angaben.</li>
    <li><b>Entfernt, weil überflüssig:</b> Kommentare, Metadaten und Daten der Grafikprogramme (Illustrator, Inkscape, Figma, Sketch, Affinity), leere Gruppen, ungenutzte Verläufe und Kennungen, Leerraum. Zahlen in Pfaden werden auf drei Nachkommastellen gerundet – sichtbar ändert sich nichts.</li>
    <li><b>Bleibt erhalten:</b> Formen, Farben, Verläufe, Masken, Filter, Texte, Titel und Beschreibung (<code>title</code>, <code>desc</code>) sowie Angaben für Screenreader.</li>
    <li><b>Abgelehnt</b> werden SVGs mit <b>eingebetteten Bilddaten</b> (z. B. ein Bildschirmfoto, das als „.svg“ gespeichert wurde) oder eingebetteten Schriften: Sie wären nach dem Bereinigen leer. Bildschirmfotos bitte als PNG, JPG oder WebP hochladen; Texte im Grafikprogramm vorher in Pfade umwandeln. Ebenso abgelehnt: Dateien über 2 MB und ungültige Dateien.</li>
    <li>Wie bei Fotos braucht jede SVG einen <b>Alt-Text</b> – oder die Markierung „dekorativ“.</li>
    <li>Fokuspunkt, Zuschnitte und „Bild bearbeiten“ gibt es für SVG nicht: Die Grafik wird immer vollständig gezeigt – in Rahmen mit festem Format automatisch eingepasst (siehe <a href="#bild-im-rahmen">Bild im Rahmen</a>). „Anpassen“ (Effekte) geht.</li>
    <li>Als <b>App-Icon</b> lässt sich eine SVG nur nutzen, wenn der Server sie umwandeln kann (Imagick) – sonst weist die Seite darauf hin; dann ein PNG wählen. Als Vorschaubild für soziale Netzwerke wird eine SVG nicht verwendet.</li>
    <li>Wer keine SVGs möchte, schaltet sie unter <b>Funktionen &amp; Erweiterungen → „SVG-Grafiken hochladen“</b> ab.</li>
  </ul>

  <h3 id="grafik-editor">Grafiken zeichnen und bearbeiten (Grafik-Editor)</h3>
  <p>Infografiken, einfache Schaubilder, Symbole und Skizzen für die Blockauswahl entstehen direkt in der Verwaltung – ohne
    Grafikprogramm. Der Editor ist aufgebaut wie Affinity Designer, Illustrator oder Fireworks: oben die <b>Personas</b>
    (<b>Vektor</b>; <b>Motion</b> für Animationen folgt), darunter die <b>Kontextleiste</b> mit den Optionen des Werkzeugs und der
    Auswahl, links die <b>Werkzeuge</b>, rechts die Bereiche <b>Farbe · Farbfelder · Kontur · Darstellung</b>,
    <b>Ebenen · Bibliothek · Symbole</b> und <b>Transformieren · Protokoll</b>, unten eine Statuszeile mit Tipps zum Werkzeug.</p>
  <ol class="doc-steps">
    <li><b>Neue Grafik:</b> Mediathek → <b>Neu</b> → Infografik (16:9, quadratisch oder frei – die Größe lässt sich unter
      <b>Dokument …</b> ändern). Der Editor öffnet sich über der Mediathek; <b>Vollbild</b> oben rechts (oder <kbd>⇧</kbd><kbd>⌘</kbd><kbd>F</kbd>)
      nutzt den ganzen Bildschirm und bleibt beim nächsten Mal so.</li>
    <li><b>Vorhandene SVG bearbeiten:</b> in der Mediathek per Rechtsklick → <b>Im Grafik-Editor bearbeiten …</b> oder im großen
      Bearbeitungsfenster. Auch Logos und Exporte aus Illustrator, Inkscape oder Figma lassen sich öffnen – was Sie nicht anfassen,
      bleibt genau so, wie es war.</li>
    <li><b>Speichern</b> (<kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>S</kbd>): Beim ersten Speichern fragt der Editor nach Dateiname, Titel und
      Alt-Text. Danach ersetzt jedes Speichern die Datei – Adresse, Alt-Text und alle Verwendungen bleiben, die vorige Fassung
      steht unter <b>Dokument … → Frühere Fassungen</b>. „Speichern unter …“ (im Menü Exportieren) legt eine neue Datei an.</li>
  </ol>
  <table class="doc-table">
    <tr><th>Werkzeug</th><th>Taste</th><th>So geht es</th></tr>
    <tr><td>Verschieben</td><td><kbd>V</kbd></td><td>Klick wählt, Ziehen verschiebt, Rahmen ziehen wählt mehrere; Griffe ändern die Größe (<kbd>⇧</kbd> = Seitenverhältnis, <kbd>⌥</kbd> = von der Mitte), der runde Griff dreht (<kbd>⇧</kbd> = 15°-Schritte). <kbd>⌥</kbd>+Ziehen zieht eine Kopie, <kbd>⇧</kbd>+Ziehen verschiebt nur waagerecht bzw. senkrecht. Ein Klick (ohne Ziehen) auf ein Objekt einer Mehrfachauswahl wählt nur dieses. Doppelklick betritt eine Gruppe bzw. bearbeitet Text.</td></tr>
    <tr><td>Knoten</td><td><kbd>A</kbd></td><td>Zeigt die Punkte einer Kurve; Punkte und Griffe ziehen, <b>Klick auf die Kurve fügt einen Punkt ein</b> (die Form bleibt gleich), Doppelklick auf einen Punkt = Ecke/glatt, <kbd>⌥</kbd> beim Ziehen eines Griffs trennt die Griffe, <kbd>⌫</kbd> löscht Punkte. In der Kontextleiste: Knotenart Ecke/glatt/symmetrisch, an Knoten auftrennen, Endpunkte verbinden, Pfad schließen, Richtung umkehren, Teilpfade trennen, Vereinfachen. Rechtecke, Kreise und Linien werden dabei zu Kurven.</td></tr>
    <tr><td>Zeichenstift</td><td><kbd>P</kbd></td><td>Klick = Eckpunkt, Ziehen = glatter Punkt; Klick auf den ersten Punkt schließt die Form, <kbd>↵</kbd>/<kbd>Esc</kbd> beendet.</td></tr>
    <tr><td>Bleistift</td><td><kbd>N</kbd></td><td>Freihand zeichnen – die Linie wird geglättet (Stärke in der Kontextleiste).</td></tr>
    <tr><td>Rechteck, Ellipse, Vieleck/Stern</td><td><kbd>R</kbd> <kbd>E</kbd> <kbd>G</kbd></td><td>Ziehen; <kbd>⇧</kbd> = gleichmäßig, <kbd>⌥</kbd> = von der Mitte. Eckenradius, Zahl der Ecken und Stern in der Kontextleiste.</td></tr>
    <tr><td>Linie und Pfeil</td><td><kbd>L</kbd></td><td>Ziehen, <kbd>⇧</kbd> = 45°-Schritte; Pfeilspitzen am Anfang/Ende in der Kontextleiste.</td></tr>
    <tr><td>Verbinder</td><td><kbd>C</kbd></td><td>Von einer Form zur nächsten ziehen – die Enden haften an den Ankerpunkten und folgen, wenn Formen sich bewegen (siehe <a href="#grafik-diagramme">Diagramme</a>).</td></tr>
    <tr><td>Text</td><td><kbd>T</kbd></td><td>Klicken = Punkttext, <b>Rahmen ziehen = Textrahmen</b> (Zeilen brechen automatisch um). Tippen direkt auf der Fläche mit Schreibmarke; Ziehen wählt aus, Doppelklick ein Wort, <kbd>⌘</kbd><kbd>B</kbd>/<kbd>I</kbd>/<kbd>U</kbd> fett/kursiv/unterstrichen, eine Farbe wirkt auf die markierten Zeichen. <kbd>↵</kbd> = neuer Absatz, <kbd>Esc</kbd> beendet. <kbd>⌘</kbd><kbd>Z</kbd> nimmt beim Tippen Schritt für Schritt zurück – auch Formatierung wie Fett oder Farbe.</td></tr>
    <tr><td>Verlauf</td><td><kbd>F</kbd></td><td>Auf einem Objekt ziehen = linearen Verlauf anlegen bzw. neu ausrichten. Anfang/Ende (radial: Mitte/Radius) und Farbstopps auf der Fläche ziehen, Doppelklick auf die Linie = neuer Stopp, <kbd>⌫</kbd> löscht den gewählten.</td></tr>
    <tr><td>Pipette, Hand, Zoom</td><td><kbd>I</kbd> <kbd>H</kbd> <kbd>Z</kbd></td><td>Farbe eines Objekts übernehmen; Ansicht verschieben (auch Leertaste + Ziehen); vergrößern (<kbd>⌥</kbd> = verkleinern).</td></tr>
  </table>
  <ul>
    <li><b>Farbe:</b> Füllung oder Kontur wählen (Kreise unten in der Werkzeugleiste, <kbd>X</kbd> wechselt, <kbd>⇧</kbd><kbd>X</kbd> tauscht), dann
      Keine, Farbe, linearer oder radialer Verlauf. Verläufe: Farbstopps anklicken, ziehen, mit Klick auf den Balken ergänzen.
      <b>Farbmodell</b> umschalten wie in Affinity: <b>Hex, RGB, HSL, HSB</b> oder <b>OKLCH</b> – je Kanal ein Regler und ein Zahlenfeld,
      Deckkraft in Prozent; der gewählte Modus bleibt für Ihr Konto gemerkt. Ins Feld <b>Farbe</b> dürfen Sie jede CSS-Angabe schreiben
      (<code>#f80</code>, <code>rgb(10 20 30)</code>, <code>hsl(240 100% 50%)</code>, <code>oklch(0.7 0.1 200)</code>, Farbnamen, <code>#RRGGBBAA</code>) –
      sie wird umgerechnet. Gespeichert wird immer eine Hex-Farbe (Deckkraft getrennt), damit Desktop-Programme sie verstehen.
      Bei Text zeigt der Bereich den <b>Kontrast zum Hintergrund</b> nach WCAG (AA/AAA; großer Text ab 24 px bzw. 18,7 px fett).
      <b>Farbfelder</b> zeigt die Farben aus dem Design der Website, zuletzt verwendete Farben und die Farben der Grafik.
      „Schriftfarbe“ (A) übernimmt die Textfarbe der Umgebung – ideal für Symbole.</li>
    <li><b>Ebenen:</b> oben liegt, was vorn ist. Ziehen sortiert um (auch in Gruppen hinein), Doppelklick oder <kbd>F2</kbd> benennt um,
      Auge blendet aus, Schloss sperrt. Deckkraft und Mischmodus gelten für die Auswahl.</li>
    <li><b>Bibliothek:</b> fertige Bausteine (Flussdiagramm, Organigramm, Hinweise und Pfeile, Browserfenster, Smartphone, Karte,
      Diagramm, Zeichen …) anklicken oder auf die Fläche ziehen – sie landen dort, wo Sie loslassen. Eine Auswahl lässt sich unter Ebenen
      <b>als Baustein speichern</b>: mit Ordner und wahlweise für <b>diese Website</b> oder <b>alle Websites der Installation</b>.
      Das Menü am Baustein (Knopf <b>…</b>, Rechtsklick oder <kbd>⇧</kbd><kbd>F10</kbd>) merkt ihn als <b>Favorit</b> (oben in der Liste, je Konto),
      benennt um, legt in einen Ordner, ersetzt ihn durch die aktuelle Auswahl, gibt ihn für alle Websites frei, exportiert oder löscht ihn.
      <b>Austausch zwischen Installationen:</b> Pfeil nach unten = eigene Bausteine als Datei sichern, Pfeil nach oben = Datei einlesen
      (jede Grafik wird dabei geprüft und bereinigt). Bausteine mit Design-Farben (<code>var(--accent)</code>) bekommen beim Einfügen die
      Farbe der Website; „Farben der Website“ setzt bei den mitgelieferten Bausteinen die Akzentfarbe ein.
      <b>Symbole</b> fügt Symbole aus dem Symbolsatz von KLXM Studio als bearbeitbare Pfade ein.</li>
    <li><b>Kopieren zwischen Grafiken:</b> <kbd>⌘</kbd><kbd>C</kbd> / <kbd>⌘</kbd><kbd>V</kbd> überträgt Objekte samt Verläufen, Pfeilspitzen,
      Schatten und der Farbe aus ihrer Gruppe in eine andere Grafik (auch in einem anderen Fenster) – an dieselbe Stelle und ohne zusätzliche
      Gruppe. SVG-Text aus anderen Programmen wird bereinigt und als Gruppe in die Mitte gesetzt; eine SVG-Datei, die Sie auf die Fläche
      ziehen, landet dort, wo Sie loslassen. Strich-Symbole (z. B. aus Lucide oder Tabler) behalten dabei ihre Kontur, Klassen aus
      Illustrator-Dateien (<code>.cls-1</code> …) färben vorhandene Formen nicht um.</li>
    <li><b>Gruppierung aufheben</b> gibt Füllung, Kontur, Schrift und Deckkraft der Gruppe an die Teile weiter – auch, wenn sie aus einer
      Klasse der importierten Datei stammen. Gruppen mit Beschneidung, Maske, Effekt oder Alternativen (<code>switch</code>) bleiben
      erhalten, weil sich das Bild sonst ändern würde; Beschneidung und Maske lösen Sie unter <b>Objekt → Maske freigeben</b>.</li>
    <li><b>Ausrichten und Verteilen</b> in der Kontextleiste: bei mehreren Objekten aneinander, bei einem an der Zeichenfläche.
      Beim Verschieben rasten Objekte an Kanten und Mitten ein (rote Hilfslinien); <kbd>⌘</kbd> beim Ziehen schaltet das ab.</li>
    <li><b>Transformieren:</b> Position, Breite, Höhe und Drehung als Zahl, Bezugspunkt im Raster, Kette = Seitenverhältnis sperren.
      <b>Protokoll</b> zeigt alle Schritte – ein Klick springt zurück.</li>
    <li>Alle Tastaturkürzel zeigt <kbd>?</kbd>. Wichtig: <kbd>⌘</kbd><kbd>Z</kbd> / <kbd>⇧</kbd><kbd>⌘</kbd><kbd>Z</kbd> Rückgängig/Wiederholen,
      <kbd>⌘</kbd><kbd>D</kbd> duplizieren, <kbd>⌘</kbd><kbd>G</kbd> / <kbd>⇧</kbd><kbd>⌘</kbd><kbd>G</kbd> gruppieren/aufheben, <kbd>⌘</kbd><kbd>[</kbd> / <kbd>]</kbd> nach hinten/vorn,
      Pfeiltasten verschieben (<kbd>⇧</kbd> = 10), <kbd>Tab</kbd> wählt das nächste Objekt, <kbd>⌘</kbd><kbd>0</kbd> einpassen. Rückgängig wählt wieder, was vor dem Schritt gewählt war,
      und bleibt in einer betretenen Gruppe.</li>
    <li><b>Barrierefreiheit:</b> Unter <b>Dokument …</b> erhalten Infografiken einen <b>Titel</b> und eine ausführliche <b>Beschreibung</b>;
      der Alt-Text in der Mediathek bleibt Pflicht. Schriften: In einer SVG als Bild zeigt der Browser nur Schriften, die auf dem
      Gerät vorhanden sind – deshalb wandelt das Speichern Texte in Schriften der Website auf Wunsch in Kurven um (siehe unten); im Editor bleiben sie Text.</li>
  </ul>
  <h4 id="grafik-geometrie">Formen kombinieren, ausstanzen, umwandeln</h4>
  <p>Zwei oder mehr Objekte wählen, dann in der Kontextleiste bzw. im Menü <b>Objekt</b>. Gerechnet wird mit den echten Kurven
    (Kreise bleiben rund); Konturen zählen nicht zur Fläche.</p>
  <table class="doc-table">
    <tr><th>Befehl</th><th>Ergebnis</th><th>Wann?</th></tr>
    <tr><td>Vereinen</td><td>eine Form aus allen</td><td>Umriss aus mehreren Teilen, z. B. Sprechblase aus Kreis und Dreieck</td></tr>
    <tr><td><b>Abziehen</b> (Ausstanzen)</td><td>die oberen Formen schneiden Löcher in die unterste – der Hintergrund scheint durch</td><td>Schablonen, Stanz-Optik, Text aus einer Fläche ausstanzen (Text wird dafür in Kurven umgewandelt)</td></tr>
    <tr><td>Schnittmenge</td><td>nur der gemeinsame Teil</td><td>Ausschnitte, z. B. Halbkreis aus Kreis und Rechteck</td></tr>
    <tr><td>Ausschluss</td><td>alles außer dem gemeinsamen Teil</td><td>Muster, Logos mit Überlappung</td></tr>
    <tr><td>Teilen</td><td>einzelne Flächen an allen Schnittlinien</td><td>Teile getrennt einfärben</td></tr>
    <tr><td>Zusammengesetzt (bearbeitbar)</td><td>wie Abziehen bzw. Vereinen, die Originale bleiben erhalten</td><td>wenn sich Text oder Formen noch ändern: Doppelklick bzw. „Originale bearbeiten“, danach wird neu berechnet; „In Pfad umwandeln“ macht es endgültig. Auf der Website erscheint immer der fertige Pfad.</td></tr>
    <tr><td>Ausstanzen per Maske</td><td>Loch über eine umgekehrte Maske, nichts wird umgerechnet</td><td>wenn die Stanze ein Text bleiben soll (bearbeitbar) oder weiche Kanten/Verläufe hat</td></tr>
    <tr><td>Kontur in Fläche</td><td>die Kontur wird eine gefüllte Form</td><td>Schneide-/Plotter-Daten, Logos, bevor man die Kontur weiter kombiniert</td></tr>
    <tr><td>Pfad versetzen …</td><td>Umriss nach außen (+) oder innen (−), Ecken rund/spitz/abgeschrägt</td><td>Rand um ein Logo, Aufkleber-Kontur, Abstand für Ausstanzungen</td></tr>
  </table>
  <h4 id="grafik-text">Text: Schrift, Zeichen, Absatz, OpenType, Vorlagen</h4>
  <p>Ist ein Text gewählt, erscheint rechts der Bereich <b>Zeichen | Absatz | OpenType | Vorlagen</b>. Während Sie tippen, gelten
    Zeichen-Einstellungen nur für die markierten Zeichen, sonst für den ganzen Text.</p>
  <ul>
    <li><b>Schriftauswahl</b> (im Bereich Zeichen und in der Kontextleiste): jede Schrift erscheint in ihrem eigenen Schriftbild. Tippen filtert,
      <kbd>↑</kbd>/<kbd>↓</kbd> gehen durch die Liste, <kbd>→</kbd> zeigt rechts die <b>Schnitte</b> (Gewicht, kursiv) mit Vorschau, <kbd>↵</kbd> übernimmt,
      <kbd>Esc</kbd> verwirft. Schon beim Zeigen oder Durchblättern sehen Sie die Schrift am Text – erst <kbd>↵</kbd> bzw. ein Klick macht einen Schritt
      im Protokoll. Abschnitte: <b>Zuletzt verwendet</b>, <b>Favoriten</b> (Stern bzw. <kbd>⌘</kbd><kbd>D</kbd>, je Konto gemerkt), <b>Schriften der
      Website</b> (Schriften-Manager), <b>Systemschriften</b>. Bei <b>variablen Schriften</b> stehen darunter Regler für ihre Achsen
      (Gewicht, Breite, Neigung, optische Größe …).</li>
    <li><b>Zeichen:</b> Schnitt, Größe, Laufweite, Unterschneidung, Unterstrichen/Durchgestrichen, Groß-/Kleinschreibung (als echte Zeichen),
      <b>Grundlinienversatz</b>, <b>Breite/Höhe</b> in Prozent (horizontal/vertikal skalieren).</li>
    <li><b>Absatz:</b> Ausrichtung links, Mitte, rechts und <b>Blocksatz</b> (im Textrahmen; die letzte Zeile eines Absatzes bleibt links),
      Zeilen- und Wortabstand, <b>Abstand vor/nach Absätzen</b>, <b>Einzüge</b> links, rechts und für die erste Zeile, <b>Silbentrennung</b>
      Deutsch oder Englisch (im Textrahmen; die Trennstriche entstehen beim Umbruch, Ihr Text bleibt unverändert). Breite des Textrahmens.
      <kbd>↑</kbd>/<kbd>↓</kbd> springen beim Tippen in die Zeile darüber bzw. darunter – auch über automatisch umbrochene Zeilen.</li>
    <li><b>OpenType:</b> Ligaturen (Standard, bedingt, kontextbedingt), Kapitälchen, Ziffern (Versal-/Mediävalziffern, proportional/tabellarisch),
      Brüche, Null mit Schrägstrich, Ordinalzeichen, Hoch-/Tiefstellen, <b>Stilsätze</b> und <b>Zeichenvarianten</b> – angezeigt wird nur, was
      die gewählte Schrift kann, mit den Namen aus der Schrift. Kann eine Schrift kein echtes Hochstellen, wird es nachgebildet (kleiner, versetzt).
      „In Kurven umwandeln“ übernimmt alle Merkmale in die Umrisse.</li>
    <li><b>Vorlagen:</b> <b>Absatzvorlagen</b> (Schrift, Größe, Farbe, OpenType, Absatz-Einstellungen eines ganzen Textes) und
      <b>Zeichenvorlagen</b> (für markierte Zeichen) „Aus Auswahl“ anlegen, per Klick anwenden. „Aus Auswahl aktualisieren“ ändert alle
      Texte bzw. Zeichen mit dieser Vorlage auf einmal. Vorlagen gehören zur Grafik; über das Menü kommen sie in die <b>Bibliothek der
      Website</b> und stehen dann in jeder Grafik bereit.</li>
    <li><b>Text auf Pfad:</b> Text und Kurve wählen → „Text auf Pfad“, mit Startversatz verschieben, „Vom Pfad lösen“ macht wieder normalen Text.
      <b>In Kurven umwandeln</b> (<kbd>⌘</kbd><kbd>↵</kbd>) macht aus Text Pfade – nur mit einer Schrift, deren Datei auf der Website liegt.</li>
  </ul>
  <h4 id="grafik-diagramme">Diagramme: Verbinder, Flussdiagramm, Organigramm</h4>
  <ul>
    <li><b>Verbinder</b> (<kbd>C</kbd>): Zeigen Sie auf eine Form – ihre Ankerpunkte erscheinen (Seitenmitten; der Punkt in der Mitte bedeutet
      „automatisch“, die Seite richtet sich dann nach dem Gegenüber; bei Pfaden zusätzlich die Knoten als Magnetpunkte). Von dort zur nächsten Form
      ziehen. Verbinder bleiben angeheftet, wenn Sie Formen verschieben oder skalieren.</li>
    <li>In der Kontextleiste: <b>gerade</b>, <b>rechtwinklig</b> oder <b>gebogen</b>, Spitzen am Anfang/Ende (Pfeil, Dreieck, offenes Dreieck, Kreis,
      Raute, Strich) und ihre Größe. Mit dem Auswahlwerkzeug lassen sich die Enden an eine andere Form ziehen und beim rechtwinkligen Verbinder
      der mittlere Abschnitt verschieben. <b>Doppelklick</b> auf einen Verbinder = Beschriftung (bleibt in der Mitte).</li>
    <li>Wird eine verbundene Form gelöscht, bleibt der Verbinder an seiner Stelle stehen (das Ende ist dann frei); Rückgängig hängt ihn wieder an.</li>
    <li><b>Text in Formen:</b> mit dem Text-Werkzeug in eine gefüllte Form klicken – der Text steht mittig und bricht auf die Breite der Form um,
      auch wenn Sie die Form später verschieben oder größer ziehen. Die Formen des Flussdiagramms und die Karten des Organigramms bringen ihn mit;
      Doppelklick bearbeitet ihn.</li>
    <li><b>Organigramm:</b> Karten aus der Bibliothek ziehen, von der obersten Karte zu den übrigen verbinden, oberste Karte wählen und
      <b>Baum anordnen</b> (von oben nach unten bzw. von links nach rechts) – gleiche Abstände, Kinder mittig unter der Karte darüber.</li>
    <li><b>Barrierefreiheit:</b> Unter <b>Dokument …</b> fügt „Ablauf des Diagramms als Beschreibung einfügen“ die Reihenfolge der Verbinder als
      Text in die Beschreibung ein („1. Start → Prozess …“) – für alle, die die Grafik nicht sehen.</li>
  </ul>
  <h4 id="grafik-ansicht">Lineale, Hilfslinien, Raster</h4>
  <p>Menü <b>Ansicht</b> (Zoom-Knopf): Lineale (<kbd>⌘</kbd><kbd>R</kbd>), Hilfslinien (<kbd>⌘</kbd><kbd>;</kbd>), Raster (<kbd>⌘</kbd><kbd>'</kbd>),
    Einrasten. Hilfslinien aus dem Lineal ziehen, zum Verschieben anfassen, zurück aufs Lineal = löschen, Doppelklick = genaue
    Position; ohne Maus über „Hilfslinien …“. Abstand und Unterteilung des Rasters stehen unter <b>Dokument …</b>.
    Hilfslinien gehören nur zur Bearbeitung – auf der Website erscheinen sie nicht.</p>
  <h4 id="grafik-masken">Beschneiden und Masken</h4>
  <p>Das <b>oberste</b> Objekt der Auswahl wirkt auf die übrigen: <b>Beschneiden</b> (<kbd>⌘</kbd><kbd>7</kbd>) zeigt nur, was in der Form liegt;
    <b>Als Maske verwenden</b> blendet nach Helligkeit (weiß zeigt, schwarz verbirgt, Verläufe blenden weich aus);
    <b>Ausstanzen per Maske</b> schneidet ein Loch. Die Form erscheint in den Ebenen in der Gruppe und lässt sich dort wählen und
    bearbeiten; <b>Maske freigeben</b> (<kbd>⌥</kbd><kbd>⌘</kbd><kbd>7</kbd>) macht wieder einzelne Objekte daraus.</p>
  <h4 id="grafik-export">Speichern und Exportieren</h4>
  <ul>
    <li>Beim Speichern entsteht eine <b>für das Web optimierte</b> Fassung (der Dialog zeigt die Größe vorher/nachher und die Genauigkeit).
      Ebenennamen, Sperren, Hilfslinien und Textrahmen bleiben für den Editor erhalten, die Website lädt nur die schlanke Datei.
      Texte in Schriften der Website werden dafür auf Wunsch in Kurven umgewandelt (empfohlen – SVG-Bilder zeigen sonst eine Ersatzschrift);
      im Editor bleiben sie Text.</li>
    <li><b>Exportieren</b>: SVG für das Web, <b>SVG für Desktop-Programme</b> (Affinity, Illustrator, Inkscape, Figma – mit Ebenen und Namen),
      <b>PDF</b> als Vektor. Pixelbilder gibt es bewusst nicht: Der Editor arbeitet nur mit Vektoren.</li>
    <li><b>Importieren</b>: SVG-Dateien über „SVG-Datei importieren …“, per Ziehen auf die Fläche oder Einfügen aus der Zwischenablage.
      Pixelbilder (PNG, JPG) werden nicht übernommen – der Editor meldet das.</li>
  </ul>

  <p id="grafiken"><b>Symbole und Blockvorschauen</b> liegen nicht in der Mediathek, sondern unter <a href="<?= e(url('/admin/grafik')) ?>">Grafiken</a>
    (Mediathek → Neu → Grafiken verwalten). Eine <b>Blockvorschau</b> startet mit der Skizze des Kits für den gewählten Block; nach dem
    Speichern zeigt die Blockauswahl („+ Block einfügen“) auf dieser Website Ihre Fassung. „Vorschau des Kits zeigen“ stellt das
    Original wieder her. Schneller geht es direkt in der Blockauswahl: Ansicht <b>Liste mit großer Vorschau</b>, Block (und ggf.
    Variante) wählen, <b>Vorschau gestalten</b> – der Editor öffnet sich im Fenster, nach dem Speichern zeigt die Auswahl sofort die neue Vorschau.
    Weil Blockvorschauen für die ganze Website gelten, brauchen Sie dafür das Recht <b>Design</b>; Symbole und eigene Bausteine
    verlangen das Recht <b>Eigene Blöcke bauen</b>. Infografiken in der Mediathek gehen mit dem Recht zum Hochladen.</p>

  <h3>PDFs und Downloads</h3>
  <p>PDFs öffnen sich auf der Website <b>immer</b> in einem eigenen, schnellen Betrachter (Mozilla PDF.js) – ohne Programme von Dritten, egal ob über einen Link im Text, einen Button, ein Datei-Feld oder den Block Downloads; dort steht zusätzlich der Knopf zum Herunterladen. In der Karte auf der Website kopiert <b>„Link kopieren“</b> einen Link auf die Seite, der beim Öffnen genau diese Karte wieder zeigt – zum Weitergeben an Interessierte. Der Block <b>Downloads</b> zeigt entweder einzeln gewählte Dateien oder den Inhalt einer ganzen <b>Sammlung</b> (z. B. „Formulare“) – neue PDFs in der Sammlung erscheinen dann automatisch. Mit <b>„Nur Dateien mit Schlagworten“</b> zeigt er nur Dateien, die eines der Schlagworte der Mediathek tragen (z. B. „satzung, protokoll“). Im Kit fluid zeigt die Variante <b>„Liste“</b> ein kleines Vorschaubild je Datei, ab drei Dateien eine <b>Suche</b> und den Umschalter <b>Liste/Kacheln</b>; auf Wunsch kommen <b>Filter-Schaltflächen nach Schlagworten</b> dazu („Filter nach Schlagworten anbieten“, mit „Nur diese Schlagworte als Filter“ lassen sich interne Schlagworte ausblenden). Ist auf dem Server <i>poppler-utils</i> installiert, zeigt die Mediathek bei PDFs die <b>erste Seite als Vorschaubild</b> – sonst ein neutrales Dokument-Symbol.</p>
  <p id="dateiname"><b>Lesbare Dateinamen:</b> Dateien und Bilder behalten beim Hochladen ihren Namen – aus „Sommerfest 2026.pdf“ wird die Adresse <code>…/media/2026/10/sommerfest-2026.pdf</code> (Kleinbuchstaben, Umlaute umgeschrieben). Gibt es den Namen im selben Monat schon, kommt eine Zahl dazu (<code>-2</code>). Auch die Bildgrößen, die die Website zeigt, tragen den Namen, z. B. <code>hygienetipps-3fa9c1-800.webp</code> – der kurze Zusatz ändert sich bei jeder Bearbeitung, damit niemand eine alte Fassung aus dem Zwischenspeicher sieht. Das hilft Suchmaschinen (auch der Bildersuche); den Inhalt eines Bildes beschreibt aber vor allem der <b>Alt-Text</b>.</p>
  <ul>
    <li><b>Umbenennen:</b> im Bearbeitungsfenster unter <b>„Dateiname in der Adresse“</b>. Bei Bildern werden alle Größen und Zuschnitte mit umbenannt. Die bisherigen Adressen leiten automatisch weiter; Links in Texten und Blöcken passen sich ohnehin an.</li>
    <li><b>Herunterladen:</b> Wer eine Datei herunterlädt, bekommt sie unter dem Namen, mit dem sie hochgeladen wurde (im Bearbeitungsfenster unter „Hochgeladen als“).</li>
    <li><b>Ersetzen</b> behält den Namen – und damit die Adresse.</li>
    <li><b>Ältere Dateien</b> mit kryptischen Namen (z. B. <code>1075e42d1b045051.pdf</code>) benennen Sie einzeln um; die Agentur kann alle auf einmal umstellen (Bilder nach ihrem Titel). Verschickte oder von Google gefundene Links funktionieren danach weiter.</li>
    <li><b>Videos</b> spielt die Website in einer verkleinerten Web-Fassung ab; der lesbare Name gilt für die Originaldatei.</li>
    <li>Dateien in geschützten Bereichen (Mitglieder, Formular-Uploads) bekommen weiterhin zufällige Namen.</li>
  </ul>
  <p id="office"><b>Word, Excel, PowerPoint:</b> Standardmäßig nimmt die Mediathek keine Office-Dokumente an – speichern Sie sie als PDF (sicher und auf jedem Gerät lesbar). Die Administration kann unter <b>Grundeinstellungen → Mediathek → „Office-Dokumente erlauben“</b> moderne Formate zulassen: DOCX, XLSX, PPTX, ODT, ODS, ODP. Alte Formate (DOC, XLS, PPT) und Dateien mit Makros werden immer abgelehnt.</p>

  <?php $__sizes = $vars['image_sizes'] ?? null; if ($__sizes): ?>
  <table class="doc-table">
    <tr><th>Verwendung</th><th>Format</th><th>Mindestgröße</th></tr>
    <?php foreach ($__sizes as [$__u, $__f, $__m]): ?><tr><td><?= e($__u) ?></td><td><?= e($__f) ?></td><td><?= e($__m) ?></td></tr><?php endforeach; ?>
  </table>
  <?php else: ?>
  <p>Laden Sie Fotos möglichst groß hoch – mindestens 1600 px breit, für breite Bilder 2400 px. Eigene Zuschnitte sind in diesen Formaten möglich: <?= e(implode(', ', array_values((array) (app()->theme->def['image_ratios'] ?? ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4'])))) ?>. Dateien dürfen höchstens <?= \Core\Media::maxMb() ?> MB groß sein.</p>
  <?php endif; ?>
  <div class="doc-note doc-note--warn"><strong>Bildrechte und Einwilligungen</strong><p>Nur Fotos verwenden, für die Nutzungsrechte bestehen. Bei erkennbaren Personen (Team, Kundinnen und Kunden) ist eine schriftliche Einwilligung nötig.</p></div>
  <h3 id="pools">Geteilte Medien</h3>
  <p>Gehört Ihre Website zu einem Verbund, kann es <b>geteilte Mediatheken</b> geben (z. B. Markenbilder einer Unternehmensgruppe). Oben in der Mediathek schalten Sie dann um: <b>Diese Website | ⇄ Name der Mediathek</b>. Geteilte Dateien verwenden Sie wie eigene; ändern, hochladen und löschen dürfen dort nur Personen mit dem Recht „Geteilte Medien pflegen“. Wer es hat, kann eigene Dateien per Rechtsklick <b>In „…“ verschieben (geteilt)</b> – alle bisherigen Verwendungen auf Ihrer Website bleiben erhalten. Ebenso lassen sich Dateien <b>zwischen zwei geteilten Mediatheken</b> verschieben (Rechtsklick in der geteilten Mediathek → <b>In „…“ verschieben</b>, Pflegerecht in beiden): Alle Verwendungen auf allen Websites zeigen danach auf die neue Stelle, auf den Seiten ändert sich nichts. Verwendet eine Website die Datei, die die Ziel-Mediathek nicht nutzt, wird nicht verschoben – sonst fehlte sie dort. Wechselt die Datei zwischen geschützt und öffentlich, ändert sich bewusst, wer sie sehen darf (die Rückfrage weist darauf hin). So lässt sich z. B. ein über ein Formular eingereichtes Bild aus „Formular-Uploads“ in eine öffentliche Mediathek holen.</p>
  <p>Unter <b>Grundeinstellungen → Geteilte Medien</b> erscheinen außerdem die Bildbereiche geteilter Datentabellen als „Bilder der geteilten Tabelle „…““ mit Verweis <b>Zur Tabelle</b>. Sie werden mit der Tabelle verwaltet: Genutzt werden sie automatisch von allen beteiligten Websites, löschen lassen sie sich hier nicht; einstellbar ist nur, welche Websites Alt-Texte und Zuschnitte pflegen dürfen. Noch leere Bereiche stehen zusammengeklappt unter „Leere Pools geteilter Tabellen“.</p>
  <h3 id="pruefen">Prüfen: Was fehlt noch?</h3>
  <p>Links in der Mediathek steht immer die Gruppe <b>„Prüfen“</b> – mit der Anzahl der betroffenen Dateien (grau = alles erledigt):</p>
  <ul>
    <li><b>Ohne Alt-Text</b> – Bilder ohne Beschreibung (außer als „dekorativ“ markierte).</li>
    <li><b>Alt-Text fehlt in English</b> (je weiterer Sprache der Website) – die Übersetzung der Bildbeschreibung fehlt.</li>
    <li><b>Ohne Titel</b> – Dateien, die nur ihren Dateinamen zeigen.</li>
    <li><b>Videos ohne Untertitel</b> bzw. <b>Audio ohne Transkript</b> (siehe unten; als „dekorativ“ markierte Videos zählen nicht mit).</li>
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
  <p id="video-dekorativ"><b>Dekorative Videos:</b> Stumme Hintergrund-Schleifen, Bühnen-Clips oder Stimmungs-Animationen ohne eigene Aussage markieren Sie wie Bilder als <b>„Dekorativ (ohne Aussage)“</b> – beim Hochladen, rechts in der Mediathek oder unter „Alle Details“. Solche Videos brauchen keine Untertitel und kein Transkript, erscheinen nicht mehr unter „Videos ohne Untertitel“ (Mediathek, Übersicht, <?= e(\Core\AI\Assist::brand()) ?>) und werden auf der Website für Screenreader ausgeblendet. Wo der Kern das Video ausgibt (z. B. Datei-Felder von Einträgen), läuft es ohne Bedienelemente als stumme Schleife – nur wenn es sichtbar ist und niemand „Bewegung reduzieren“ eingestellt hat – und hat immer eine beschriftete Schaltfläche zum Anhalten. Spricht jemand im Video, zeigt es Text oder Informationen, ist es <b>nicht</b> dekorativ.</p>
