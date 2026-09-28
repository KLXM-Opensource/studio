<?php /** Handbuch · Kapitel „Inhalte bearbeiten“ (Variablen: siehe help/manual.php; $vars['blocks_page'] = Pfad einer Beispielseite mit allen Blöcken) */
$__bgs = app()->theme->backgrounds();
$__sample = $vars['blocks_page'] ?? null;
?>
  <p class="lead">Sie bearbeiten die Website dort, wo sie steht – mit genau dem Aussehen, das Besucher später sehen.</p>
  <?= $img('toolbar.webp', 'Website mit eingeblendeter Redaktionsleiste', '<b>Redaktionsleiste:</b> „Bearbeiten“ öffnet den Editor, „' . e($settingsTitle) . '“ die zentralen Angaben.') ?>

  <h3>Bearbeitungsmodus starten</h3>
  <ol class="doc-steps">
    <li>Seite aufrufen (z. B. die Startseite) und in der Leiste oben auf <b>Bearbeiten</b> klicken (oder im Umschalter <b>Ansehen · Bearbeiten</b> auf „Bearbeiten“).</li>
    <li>Fahren Sie mit der Maus über die Seite: Jeder <b>Block</b> (Abschnitt) bekommt einen Rahmen, oben rechts seinen Namen und den Knopf <b>Bearbeiten</b>. Kleine Hinweise zeigen Besonderheiten wie „ausgeblendet“, die Sprungmarke oder „Navigation“.</li>
    <li>Links im Block erscheinen <b>+</b> (Block einfügen) und <b>⋮⋮</b> (Menü und Verschieben).</li>
  </ol>
  <?= $img('editor.webp', 'Editor mit geöffneter Seitenleiste', '<b>Editor:</b> links die Live-Vorschau, rechts die Seitenleiste mit allen Feldern des gewählten Blocks.') ?>

  <h3 id="leiste">Die Leiste oben</h3>
  <p>Angemeldet sehen Sie auf jeder Seite der Website eine dunkle Leiste. Sie ist immer gleich aufgebaut:</p>
  <ul>
    <li><b>Links:</b> das Logo (zurück zur Verwaltung), die Art („Seite“ oder der Name der Tabelle), der Titel und <b>ein</b> Status: <b>Veröffentlicht</b>, <b>Entwurf</b>, <b>Geändert – nicht veröffentlicht</b>, <b>Ungespeichert</b> oder <b>Live-Fassung</b>. Ein Klick auf den Status erklärt, was er bedeutet.</li>
    <li><b>Mitte – der Modus:</b> <b>Ansehen</b> zeigt die Seite wie Besucher (mit Ihrem Arbeitsstand), <b>Bearbeiten</b> öffnet den Editor. Auf Detailseiten (z. B. einem Beitrag) gibt es zusätzlich <b>Vorlage</b> – damit gestalten Sie das Aussehen <b>aller</b> Detailseiten dieser Tabelle.</li>
    <li><b>Rechts – die Aktionen:</b> beim Ansehen <b>Bearbeiten</b>; beim Bearbeiten <b>Abbrechen</b>, <b>Speichern</b> und <b>Veröffentlichen</b>. Daneben die Suche (<kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>K</kbd>) und das Menü <b>⋯</b> mit allem Weiteren: Vorschau, Kompakt, Live-Fassung ansehen, Entwurf verwerfen, Seiteneinstellungen, <?= e($settingsTitle) ?>, Hilfe.</li>
    <li><b>Abbrechen</b> (oder <kbd>Esc</kbd>) beendet das Bearbeiten. Gibt es ungespeicherte Änderungen, fragt ein Dialog nach: <b>Weiter bearbeiten</b>, <b>Speichern &amp; beenden</b> oder <b>Verwerfen</b>. Verwerfen betrifft nur, was noch nicht gespeichert ist – ein gespeicherter Entwurf bleibt erhalten.</li>
    <li><b>Auf dem Handy</b> und in schmalen Fenstern steht der Modus im Menü <b>⋯</b>. Beim Bearbeiten liegen <b>Abbrechen · Speichern · Veröffentlichen</b> als Leiste am unteren Bildschirmrand – gut mit dem Daumen erreichbar. Eigene Leisten des Designs (z. B. „Anrufen / Termin“) werden so lange ausgeblendet.</li>
  </ul>

  <h3>Texte ändern</h3>
  <ul>
    <li><b>Direkt im Text:</b> Überschriften und kurze Texte mit gestricheltem Rahmen können Sie anklicken und sofort überschreiben. <kbd>Enter</kbd> beendet die Eingabe. Eingefügter Text verliert seine fremde Formatierung.</li>
    <li><b>Fließtexte direkt formatieren:</b> In längeren Texten erscheint beim Hineinklicken eine dunkle <b>Formatierungsleiste</b> über dem Text. Einfach Text markieren und Knopf drücken.</li>
    <li><b>In der Seitenleiste:</b> Klick auf den Block oder <b>Bearbeiten</b> öffnet alle Felder – auch Links, Bilder, Listen und Varianten. Die Vorschau aktualisiert sich automatisch; <kbd>Esc</kbd> schließt die Seitenleiste.</li>
    <li><b>Formatierung:</b> Die Leiste hat von links nach rechts: <b>Stil ▾</b> (Absatzart), <b>B</b> Fett, <i>I</i> Kursiv, <b>ab</b> Textmarker, <b>A ▾</b> Textfarbe, <b>Link</b>, drei Listenarten (<b>– Liste</b>, <b>1. Liste</b>, <b>✓ Liste</b>) und <b>⋯</b> für Seltenes. Ist die KI eingeschaltet, steht dort zusätzlich <b>✦ KI</b>. Gedrückte Knöpfe und Häkchen im Menü zeigen, was an der Schreibmarke gilt; erneut wählen hebt ein Format auf. Details im Abschnitt <a href="#formatieren">Text formatieren</a>.</li>
    <li><b>Listen</b> (z. B. Leistungen, Fragen, Personen) bestehen aus Einträgen: mit <b>+ … hinzufügen</b> erweitern, mit ↑ ↓ sortieren, mit ✕ entfernen.</li>
  </ul>
  <h3 id="formatieren">Text formatieren: Stile, Farben, Marker</h3>
  <ul>
    <li><b>Stil ▾</b> gilt für den ganzen Absatz: <b>Normaler Text</b>, <b>Hervorgehoben</b> (etwas größer für Einleitungen und Kernaussagen – keine Überschrift, zählt also nicht zur Gliederung), <b>Klein</b> (Anmerkungen, Kleingedrucktes), <b>Hinweis-Box</b> (abgesetzter Kasten mit Linie), <b>Überschrift H2</b>, <b>Zwischenüberschrift H3</b>, <b>Unterüberschrift H4</b> und <b>Zitat</b>. Nach einem hervorgehobenen Absatz geht es mit <kbd>Enter</kbd> normal weiter. In Listen sind nur Überschriften und Zitat möglich.</li>
    <li><b>ab – Textmarker</b> (<kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>Umschalt</kbd>+<kbd>H</kbd>) hinterlegt markierten Text gelb – sparsam für das Wichtigste.</li>
    <li><b>A ▾ – Textfarbe</b>: fünf Farben aus dem Design der Website (<b>Akzent</b>, <b>Gedämpft</b>, <b>Grün</b>, <b>Orange</b>, <b>Rot</b>) und <b>Keine Farbe</b>. Eigene Farben gibt es bewusst nicht: So bleiben Texte lesbar und passen zum Design – auch im dunklen Farbschema und auf farbigen Abschnitten, wo die Farben automatisch hellere Varianten bekommen. Erscheint im Menü <b>⚠ Kontrast</b>, ist die Farbe auf diesem Hintergrund zu schwach: bitte eine andere wählen. Farbe nie allein als Information verwenden („rot markierte Termine entfallen“) – Blinde und Farbenblinde sehen sie nicht.</li>
    <li><b>⋯</b> enthält <b>Hochgestellt</b> (m², 1. Fußnote), <b>Tiefgestellt</b> (H₂O), <b>Link entfernen</b>, <b>Formatierung entfernen</b> (zurück zu normalem Text ohne Farbe, Marker, Liste oder Überschrift) und <b>Markdown einfügen …</b> (siehe <a href="#markdown">Markdown</a>). Auf schmalen Bildschirmen stehen hier auch die Listen und Ein-/Ausrücken.</li>
    <li><b>Tastatur:</b> <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>B</kbd> fett, +<kbd>I</kbd> kursiv, +<kbd>K</kbd> Link, +<kbd>Umschalt</kbd>+<kbd>H</kbd> Marker. <kbd>Alt</kbd>+<kbd>F10</kbd> springt vom Text in die Leiste, <kbd>←</kbd>/<kbd>→</kbd> wandern dort, <kbd>↓</kbd> oder <kbd>Enter</kbd> öffnet ein Menü, <kbd>Esc</kbd> führt zurück in den Text.</li>
  </ul>

  <h3 id="markdown">Markdown einfügen und importieren</h3>
  <p>Texte aus Markdown-Dateien, Notiz-Apps oder von der KI (z.&nbsp;B. <code>## Überschrift</code>, <code>- Punkt</code>, <code>**fett**</code>) müssen Sie nicht von Hand formatieren:</p>
  <ul>
    <li><b>Einfach einfügen:</b> Sieht eingefügter Text eindeutig nach Markdown aus, wird er in einem Textfeld mit Formatierungsleiste sofort umgewandelt. Unten erscheint kurz <b>„Markdown in Formatierung umgewandelt. · Als Text einfügen“</b> – ein Klick darauf (oder <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>Z</kbd>) holt den reinen Text zurück. Normale Sätze mit einem Bindestrich oder Sternchen bleiben unverändert; Text mit echter Formatierung (z.&nbsp;B. aus Word) wird wie bisher als reiner Text eingefügt.</li>
    <li><b>⋯ → Markdown einfügen …</b> öffnet ein Fenster mit Textfeld und Vorschau; <b>Einfügen</b> setzt das Ergebnis an die Schreibmarke.</li>
    <li><b>Ganze Texte als Blöcke:</b> Im Bearbeiten-Modus Menü <b>⋯</b> der Leiste oben → <b>Markdown importieren …</b>. Text einfügen oder eine <b>.md-Datei</b> wählen (sie wird nur im Browser gelesen), dann wählen: <b>Als ein Textblock</b> oder <b>Bei jeder ##-Überschrift einen neuen Textblock</b>, und wo die Blöcke hinkommen (am Anfang oder nach einem Block). Die Vorschau zeigt, welche Blöcke entstehen. <b>Importieren</b> fügt sie als ungespeicherte Änderung ein – danach wie gewohnt speichern oder veröffentlichen.</li>
    <li><b>Was übernommen wird:</b> Überschriften (die oberste Ebene wird H2 – H1 ist der Seitentitel –, darunter H3 und H4), Absätze, Aufzählungen und nummerierte Listen (auch eingerückt), Aufgabenlisten <code>- [ ]</code>/<code>- [x]</code> als Häkchen-Liste, Zitate, fett, kursiv und Links (Web-Adressen, E-Mail, Telefon, <code>/pfad</code>, <code>#anker</code>).</li>
    <li><b>Was nicht übernommen wird:</b> <b>Bilder</b> werden nicht von fremden Servern eingebunden – an ihrer Stelle steht eine Redaktionsnotiz <code>[# Bild: Beschreibung – Adresse #]</code>; laden Sie das Bild in die Mediathek und setzen Sie es z.&nbsp;B. mit einem Bild-Block ein. <b>Tabellen</b> bleiben als Textzeilen mit einer Notiz stehen (Textfelder kennen keine Tabellen). Code erscheint als normaler Text, eigenes HTML als sichtbarer Text, ein YAML-Vorspann (<code>---</code> … <code>---</code>) wird weggelassen.</li>
  </ul>

  <h3 id="links">Links setzen</h3>
  <ol class="doc-steps">
    <li>Text markieren und <b>Link</b> drücken (oder <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>K</kbd>). Ohne Markierung wird der Name des Ziels als Linktext eingefügt.</li>
    <li>Im Reiter <b>Seiten &amp; Inhalte</b> einfach lostippen: Die Liste zeigt passende <b>Seiten</b> (mit Position im Seitenbaum und Sprache), <b>Einträge</b> aller Tabellen mit eigener Seite (z. B. Beiträge, Termine), <b>Dateien</b> (PDFs öffnen wahlweise im PDF-Viewer oder direkt) und <b>Anker</b> – Abschnitte dieser oder anderer Seiten. Oben stehen die zuletzt verwendeten Ziele. Mit <kbd>↑</kbd>/<kbd>↓</kbd> wählen, <kbd>Enter</kbd> übernimmt.</li>
    <li>Für andere Ziele die Reiter <b>Web-Adresse</b> (https:// wird ergänzt, unverschlüsselte http-Adressen werden gemeldet), <b>E-Mail</b> (optional mit Betreff) und <b>Telefon</b> (die Nummer wird automatisch international gewählt, z. B. +49…). Tippen Sie eine Adresse oder E-Mail direkt ins Suchfeld, wird sie als erster Treffer vorgeschlagen.</li>
    <li>Optional: <b>In neuem Tab öffnen</b> (bei fremden Websites vorausgewählt) und ein <b>Linktitel</b>, der beim Überfahren erscheint.</li>
    <li><b>Ändern oder entfernen:</b> Schreibmarke in den Link setzen und wieder <b>Link</b> drücken – der Dialog zeigt das aktuelle Ziel, <b>Link entfernen</b> löst ihn auf.</li>
  </ol>
  <p>Links auf Seiten, Einträge und Dateien bleiben gültig, auch wenn deren Adresse später geändert wird. Dieselbe Auswahl steckt hinter jedem Link-Feld (z. B. „Button – Link“, „Link“ einer Karte): <b>Auswählen …</b> öffnet sie, darunter steht lesbar, wohin der Link führt; <b>×</b> leert das Feld. Adressen lassen sich weiterhin direkt eintippen.</p>
  <div class="doc-note doc-note--info"><strong>„Zentral gepflegt → <?= e($settingsTitle) ?>“</strong><p>Angaben, die an mehreren Stellen erscheinen (z. B. Kontaktdaten), sind im Editor mit einem farbigen Hinweis markiert. Sie ändern sie nicht im Text, sondern unter <a href="<?= e(url('/admin/settings')) ?>"><?= e($settingsTitle) ?></a> – dann stimmen sie überall gleichzeitig.</p></div>

  <h3>Blöcke einfügen, verschieben, löschen</h3>
  <ol class="doc-steps">
    <li><b>Einfügen:</b> Auf <b>+</b> klicken, Blocktyp wählen (Suche möglich). Der neue Block erscheint unterhalb, die Seitenleiste öffnet sich direkt.</li>
    <li><b>Verschieben:</b> Mit den Pfeilen <b>↑ ↓</b> oben rechts im Block eine Position nach oben oder unten – oder <kbd>Alt</kbd>+<kbd>↑</kbd>/<kbd>↓</kbd>, während die Seitenleiste des Blocks offen ist. Alternativ den Griff <b>⋮⋮</b> ziehen.</li>
    <li><b>Übersicht behalten:</b> <b>Kompakt</b> im Menü <b>⋯</b> der Leiste oben klappt alle Blöcke zu schmalen Zeilen zusammen (mit Farbe, Titel, Sprungmarke) – ideal zum Umsortieren langer Seiten. Einzelne Blöcke klappt <b>▾</b> ein und aus. Der Browser merkt sich beides für die Seite.</li>
    <li><b>Löschen:</b> Menü ⋮⋮ → <b>Löschen</b> (zweimal klicken zur Bestätigung). Wiederherstellen lässt sich ein gelöschter Block über die <b>Versionen</b> der Seite.</li>
  </ol>
  <?= $img('blockmenu.webp', 'Auswahl der Blocktypen im Editor', '<b>Blöcke einfügen:</b> alle verfügbaren Bausteine' . ($__sample ? ' – die Seite <a href="' . e(url($__sample)) . '">' . e($__sample) . '</a> zeigt jeden als Beispiel' : '') . '.') ?>

  <h3>Abschnitt &amp; Navigation</h3>
  <p>Unten in der Seitenleiste (oder Menü ⋮⋮ → <b>Abschnitt &amp; Navigation</b>) legen Sie fest, wie der Abschnitt erscheint:</p>
  <table class="doc-table">
    <tr><th>Option</th><th>Wirkung</th></tr>
    <tr><td>Hintergrund</td><td><?= e(implode(', ', array_values($__bgs))) ?>. Tipp: Helle und getönte Abschnitte abwechseln lässt Seiten ruhig wirken.</td></tr>
    <tr><td>Sprungmarke (Anker)</td><td>Kurzer Name ohne Leerzeichen, z. B. <code>leistungen</code>. Damit lässt sich der Abschnitt verlinken (<code>#leistungen</code>).</td></tr>
    <tr><td>In Hauptnavigation anzeigen</td><td>Zeigt den Abschnitt im Menü oben – mit der Beschriftung aus „Beschriftung in der Navigation“ (Sprungmarke erforderlich).</td></tr>
    <tr><td>Sichtbar</td><td>Ausblenden, ohne zu löschen – praktisch für saisonale Inhalte. Ausgeblendete Blöcke sehen Sie im Editor blass.</td></tr>
    <tr><td>Abstand oben/unten, Trennlinie oben</td><td>Feinabstimmung, wenn zwei Blöcke optisch zusammengehören.</td></tr>
    <tr><td>Höhe, Inhalt vertikal</td><td>„Vollbild“ macht den Abschnitt so hoch wie den Bildschirm; der Inhalt steht dann oben, mittig oder unten.</td></tr>
    <tr><td>Hintergrundbild, abdunkeln/aufhellen</td><td>Ein Bild aus der Mediathek hinter dem Abschnitt; „Abdunkeln“ sorgt für helle, „Aufhellen“ für dunkle, gut lesbare Schrift.</td></tr>
  </table>

  <h3>Speichern, Veröffentlichen, Versionen</h3>
  <ol class="doc-steps">
    <li><b>Speichern</b> (oder <kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd>) sichert einen <b>Entwurf</b>. Besucher sehen weiterhin den alten Stand; Sie sehen den Entwurf, solange Sie angemeldet sind. Der Knopf zeigt danach „Gespeichert ✓“, der Status „Geändert – nicht veröffentlicht“.</li>
    <li><b>Vorschau</b> (Menü <b>⋯</b>) zeigt die Seite ohne Bearbeitungsrahmen. Ungespeicherte Änderungen werden dabei automatisch als Entwurf gesichert – Sie sehen also immer Ihren aktuellen Stand.</li>
    <li><b>Live-Fassung ansehen</b> (Menü <b>⋯</b>, beim Ansehen): zeigt genau das, was Besucher gerade sehen – sobald die Seite schon einmal veröffentlicht wurde. <b>Arbeitsstand ansehen</b> führt zurück.</li>
    <li><b>Veröffentlichen</b> stellt den Entwurf nach einer kurzen Rückfrage online (nur mit dem Recht „Veröffentlichen“; sonst bleibt es beim Entwurf, bis jemand anderes veröffentlicht).</li>
    <li><b>Entwurf verwerfen …</b> (Pfeil neben „Veröffentlichen“ oder Menü <b>⋯</b>) setzt nach einer Rückfrage alle unveröffentlichten Änderungen auf die Online-Fassung zurück. Der verworfene Entwurf bleibt als Version gesichert.</li>
    <li>Etwas schiefgegangen? Unter <b>Seiteneinstellungen → Versionen</b> lassen sich die letzten <?= (int) app()->config->get('revisions', 20) ?> Stände wiederherstellen.</li>
  </ol>

  <h3 id="menue">Menü (Hauptnavigation) ändern</h3>
  <p>Das Menü oben hat zwei Quellen: <b>Seiten</b> mit eingeschaltetem Schalter „Menü“ in der <a href="<?= e(url('/admin/pages')) ?>">Seitenübersicht</a> (Unterseiten erscheinen als Aufklappmenü) und <b>Abschnitte</b> mit Sprungmarke und „In Hauptnavigation anzeigen“. Abschnitte ins Menü aufnehmen:</p>
  <ol class="doc-steps">
    <li>Seite (meist die Startseite) → <b>Bearbeiten</b> → gewünschten Block öffnen → unten <b>Abschnitt &amp; Navigation</b>.</li>
    <li><b>Sprungmarke</b> vergeben (z. B. <code>leistungen</code>), Haken bei <b>In Hauptnavigation anzeigen</b>, <b>Beschriftung in der Navigation</b> eintragen.</li>
    <li>Die <b>Reihenfolge</b> im Menü entspricht der Reihenfolge der Blöcke – also mit ↑ ↓ bzw. in der Kompaktansicht sortieren.</li>
    <li>Menüpunkt entfernen: Haken wieder herausnehmen. <b>Veröffentlichen</b> nicht vergessen.</li>
  </ol>
  <div class="doc-note doc-note--warn"><strong>Vor dem Verlassen speichern</strong><p>Der Browser warnt, wenn ungespeicherte Änderungen vorliegen. Der Status in der Leiste zeigt „Ungespeichert“, bis Sie speichern; „Abbrechen“ fragt vorher nach.</p></div>
