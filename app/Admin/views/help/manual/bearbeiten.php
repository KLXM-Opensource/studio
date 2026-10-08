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
    <li>Links im Block erscheinen <b>+</b> (Block einfügen) und <b>⋮⋮</b> (Menü und Verschieben), unten mittig auf der Kante zum nächsten Block <b>+ Block einfügen</b>.</li>
    <li>Ein <b>Klick in den Block</b> wählt ihn nur aus – Texte schreiben Sie direkt an Ort und Stelle. Die <b>Seitenleiste</b> mit allen Feldern öffnet sich erst mit <b>Bearbeiten</b>. Auf Handy und Tablet zeigt ein Tipp auf den Block seine Knöpfe.</li>
  </ol>
  <?= $img('editor.webp', 'Editor mit geöffneter Seitenleiste', '<b>Editor:</b> links die Live-Vorschau, rechts die Seitenleiste mit allen Feldern des gewählten Blocks.') ?>

  <h3 id="leiste">Die Leiste oben</h3>
  <p>Angemeldet sehen Sie auf jeder Seite der Website eine dunkle Leiste. Sie ist immer gleich aufgebaut:</p>
  <ul>
    <li><b>Links:</b> das Logo (zurück zur Verwaltung), die Art („Seite“ oder der Name der Tabelle), der Titel und <b>ein</b> Status: <b>Veröffentlicht</b>, <b>Entwurf</b>, <b>Offline</b>, <b>Geändert – nicht veröffentlicht</b>, <b>Ungespeichert</b> oder <b>Live-Fassung</b>. Ein Klick auf den Status erklärt, was er bedeutet – und bietet mit dem Recht zum Veröffentlichen <b>Offline nehmen</b> (bei online, mit Rückfrage) bzw. <b>Online stellen</b> (bei offline) an, für Seiten und Detailseiten von Einträgen, ohne Neuladen. Gibt es unveröffentlichte oder ungespeicherte Änderungen, gilt wie gewohnt <b>Veröffentlichen</b>; Platzhalter <code>[bitte ergänzen: …]</code> verhindern das Online-Stellen (Hinweis im Erklärfeld).</li>
    <li><b>Mitte – der Modus:</b> <b>Ansehen</b> zeigt die Seite wie Besucher (mit Ihrem Arbeitsstand), <b>Bearbeiten</b> öffnet den Editor. Auf Detailseiten (z. B. einem Beitrag) gibt es zusätzlich <b>Vorlage</b> – damit gestalten Sie das Aussehen <b>aller</b> Detailseiten dieser Tabelle.</li>
    <li><b>Rechts – die Aktionen:</b> beim Ansehen <b>Bearbeiten</b>; beim Bearbeiten <b>Abbrechen</b>, <b>Speichern</b> und <b>Veröffentlichen</b>. Daneben die Suche (<kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>K</kbd>) und das Menü <b>⋯</b> mit allem Weiteren: Vorschau, Kompakt, Live-Fassung ansehen, Entwurf verwerfen, Seiteneinstellungen (Titel, Adresse, Suchmaschinen und Vorschaubild direkt im Fenster ändern), <?= e($settingsTitle) ?>, Hilfe.</li>
    <li><b>Abbrechen</b> (oder <kbd>Esc</kbd>) beendet das Bearbeiten. Gibt es ungespeicherte Änderungen, fragt ein Dialog nach: <b>Weiter bearbeiten</b>, <b>Speichern &amp; beenden</b> oder <b>Verwerfen</b>. Verwerfen betrifft nur, was noch nicht gespeichert ist – ein gespeicherter Entwurf bleibt erhalten.</li>
    <li><b>Auf dem Handy</b> und in schmalen Fenstern steht der Modus im Menü <b>⋯</b>. Beim Bearbeiten liegen <b>Abbrechen · Speichern · Veröffentlichen</b> als Leiste am unteren Bildschirmrand – gut mit dem Daumen erreichbar. Eigene Leisten des Designs (z. B. „Anrufen / Termin“) werden so lange ausgeblendet.</li>
  </ul>

  <h3>Texte ändern</h3>
  <ul>
    <li><b>Direkt im Text:</b> Überschriften und kurze Texte mit gestricheltem Rahmen können Sie anklicken und sofort überschreiben. <kbd>Enter</kbd> beendet die Eingabe. Eingefügter Text verliert seine fremde Formatierung.</li>
    <li><b>Fließtexte direkt formatieren:</b> In längeren Texten erscheint beim Hineinklicken eine dunkle <b>Formatierungsleiste</b> über dem Text. Einfach Text markieren und Knopf drücken.</li>
    <li><b>In der Seitenleiste:</b> <b>Bearbeiten</b> oben rechts im Block öffnet alle Felder – auch Links, Bilder, Listen und Varianten. Ein Klick auf Inhalte, die sich nicht direkt im Text ändern lassen (Symbole, Bilder, Listen), öffnet nichts, sondern weist kurz auf <b>Bearbeiten</b> hin. Leere Blöcke haben dafür den Knopf <b>Inhalte eingeben</b>. Mit der Tastatur: <kbd>Tab</kbd> bis <b>Bearbeiten</b>, dann <kbd>Enter</kbd>. Die Vorschau aktualisiert sich automatisch; <kbd>Esc</kbd> schließt die Seitenleiste.</li>
    <li><b>Formatierung:</b> Die Leiste hat von links nach rechts: <b>Stil ▾</b> (Absatzart), <b>B</b> Fett, <i>I</i> Kursiv, <b>ab</b> Textmarker, <b>A ▾</b> Textfarbe, <b>Link</b>, drei Listenarten (<b>– Liste</b>, <b>1. Liste</b>, <b>✓ Liste</b>) und <b>⋯</b> für Seltenes. Ist die KI eingeschaltet, steht dort zusätzlich <b>✦ KI</b>. Gedrückte Knöpfe und Häkchen im Menü zeigen, was an der Schreibmarke gilt; erneut wählen hebt ein Format auf. Details im Abschnitt <a href="#formatieren">Text formatieren</a>.</li>
    <li><b>Listen</b> (z. B. Leistungen, Fragen, Personen) bestehen aus Einträgen: mit <b>+ … hinzufügen</b> erweitern, mit ↑ ↓ sortieren, mit ✕ entfernen.</li>
    <li id="ziel-bearbeiten"><b>Verlinkte Seite oder Eintrag bearbeiten:</b> Karten und Kacheln, die auf eine andere Seite oder einen Eintrag zeigen (z. B. Leistungs-Kacheln, Team-Karten, Arbeiten in einer Datenliste), zeigen beim Darauf-Zeigen oben rechts <b>✎ Bearbeiten</b> – auf Tablet und Telefon immer, mit der Tastatur per <kbd>Tab</kbd>. Bei einer <b>Seite</b> öffnet sie sich im Bearbeitungsmodus; haben Sie auf der aktuellen Seite noch nicht gespeichert, fragt das CMS vorher („Speichern &amp; beenden“, „Verwerfen“ oder „Weiter bearbeiten“). Ein <b>Eintrag</b> öffnet sich in der Seitenleiste, die aktuelle Seite bleibt offen. Mit <kbd>Strg</kbd>/<kbd>⌘</kbd>-Klick öffnet sich das Ziel in einem neuen Tab. Den Knopf sieht nur, wer die Seite bzw. die Tabelle bearbeiten darf.</li>
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
    <li>Im Reiter <b>Seiten &amp; Inhalte</b> einfach lostippen: Die Liste zeigt passende <b>Seiten</b> (mit Position im Seitenbaum und Sprache), <b>Einträge</b> aller Tabellen mit eigener Seite (z. B. Beiträge, Termine – tippen Sie den Namen der Tabelle, erscheinen alle ihre Einträge), Begriffe aus dem <b>Glossar</b>, <b>Dateien</b> (PDFs öffnen wahlweise im PDF-Viewer oder direkt) und <b>Anker</b> – Abschnitte dieser oder anderer Seiten. Ohne Suchbegriff stehen oben die zuletzt verwendeten Ziele, darunter die Seiten und die <b>neuesten Einträge</b> (zuletzt geändert zuerst, mit Tabelle und Datum). Mit <kbd>↑</kbd>/<kbd>↓</kbd> wählen, <kbd>Enter</kbd> übernimmt.</li>
    <li><b>Mehr Treffer:</b> Zeigt eine Gruppe nur einen Teil (z. B. „15 von 37“), steht an ihrem Ende <b>Weitere laden</b> – anklicken oder mit den Pfeiltasten ansteuern und <kbd>Enter</kbd> drücken.</li>
    <li><b>Struktur:</b> Der Umschalter <b>Suche | Struktur | Daten</b> neben dem Suchfeld zeigt den echten Seitenbaum – dieselbe Reihenfolge und dieselben Ebenen wie unter „Seiten“, Seiten im Status Offline oder Entwurf sind gekennzeichnet. Mit <kbd>↑</kbd>/<kbd>↓</kbd> bewegen, <kbd>→</kbd> klappt auf (bzw. springt zur ersten Unterseite), <kbd>←</kbd> klappt zu (bzw. springt zur übergeordneten Seite), <kbd>Enter</kbd> übernimmt; mit der Maus über das kleine Dreieck auf- und zuklappen. Unter einer Seite stehen auch ihre <b>Anker</b>. Bei mehreren Sprachen wählen Sie die Sprache über <b>DE</b>/<b>EN</b> … über dem Baum. Wer lostippt, landet in der Suche. Die zuletzt gewählte Ansicht merkt sich der Browser.</li>
    <li><b>Daten:</b> Die Ansicht <b>Daten</b> blättert in Ihren Inhalten: Oben wählen Sie die Quelle – jede Tabelle mit eigener Seite (z. B. Aktuelles, Termine) und, wenn das Glossar eingeschaltet ist, das <b>Glossar</b>. Darunter stehen ihre Einträge, die neuesten zuerst, mit Datum; Einträge im Entwurf sind gekennzeichnet (nur sichtbar, wenn Sie die Tabelle bearbeiten dürfen – im Glossar erscheinen nur veröffentlichte Begriffe). Das Feld daneben filtert die Liste, <b>Weitere laden</b> holt die nächsten. Mit <kbd>↑</kbd>/<kbd>↓</kbd> wählen, <kbd>Enter</kbd> oder Klick übernimmt. Ansicht und Quelle merkt sich der Browser.</li>
    <li><b>Glossar-Begriffe verlinken:</b> Jeder Begriff hat eine eigene Seite (z. B. <code>/glossar/anamnese</code>). Der Link zeigt auf diese Seite und bleibt gültig, auch wenn der Begriff später umbenannt wird.</li>
    <li>Für andere Ziele die Reiter <b>Web-Adresse</b> (https:// wird ergänzt, unverschlüsselte http-Adressen werden gemeldet), <b>E-Mail</b> (optional mit Betreff) und <b>Telefon</b> (die Nummer wird automatisch international gewählt, z. B. +49…). Tippen Sie eine Adresse oder E-Mail direkt ins Suchfeld, wird sie als erster Treffer vorgeschlagen.</li>
    <li>Optional: <b>In neuem Tab öffnen</b> (bei fremden Websites vorausgewählt) und ein <b>Linktitel</b>, der beim Überfahren erscheint.</li>
    <li><b>Adressen werden automatisch verlinkt:</b> Tippen Sie eine E-Mail-Adresse (<code>info@beispiel.de</code>), eine Web-Adresse (<code>www.beispiel.de</code> oder <code>https://…</code>) oder eine Telefonnummer (<code>02841 35656</code>, <code>+49 2841 35656</code>, <code>0800 123 456</code>), wird sie zum Link, sobald danach ein Leerzeichen, Satzzeichen oder Enter folgt – Telefonnummern, sobald das nächste Wort beginnt. Markierte Adressen (auch <code>beispiel.de</code>) werden mit <b>Link</b> sofort verlinkt, ohne Dialog. Betreff, neuer Tab usw. danach über <b>Link</b>.</li>
    <li><b>Ändern oder entfernen:</b> Schreibmarke in den Link setzen und wieder <b>Link</b> drücken – der Dialog zeigt das aktuelle Ziel, <b>Link entfernen</b> löst ihn auf.</li>
  </ol>
  <p>Links auf Seiten, Einträge und Dateien bleiben gültig, auch wenn deren Adresse später geändert wird. Dieselbe Auswahl steckt hinter jedem Link-Feld (z. B. „Button – Link“, „Link“ einer Karte): <b>Auswählen …</b> öffnet sie, darunter steht lesbar, wohin der Link führt; <b>×</b> leert das Feld. Adressen lassen sich weiterhin direkt eintippen.</p>
  <div class="doc-note doc-note--info"><strong>„Zentral gepflegt → <?= e($settingsTitle) ?>“</strong><p>Angaben, die an mehreren Stellen erscheinen (z. B. Kontaktdaten), sind im Editor mit einem farbigen Hinweis markiert. Sie ändern sie nicht im Text, sondern unter <a href="<?= e(url('/admin/settings')) ?>"><?= e($settingsTitle) ?></a> – dann stimmen sie überall gleichzeitig.</p></div>

  <h3>Blöcke einfügen, verschieben, löschen</h3>
  <ol class="doc-steps">
    <li><b>Einfügen:</b> Unten am Block auf <b>+ Block einfügen</b> klicken (erscheint beim Überfahren oder Antippen des Blocks, per <kbd>Tab</kbd> erreichbar) und den Blocktyp wählen – Tippen filtert die Liste, <kbd>↑</kbd>/<kbd>↓</kbd> und <kbd>Enter</kbd> wählen, <kbd>Esc</kbd> bricht ab. Der neue Block steht direkt <b>unter</b> diesem Block; beim letzten Block also am Ende der Seite. Alternativ fügt <b>+</b> oben links im Block ein. Im neuen Block steht die Schreibmarke im ersten Text; Blöcke ohne direkt bearbeitbaren Text (z. B. Bild) öffnen gleich die Seitenleiste.</li>
    <li><b>Blockleiste:</b> Jeder Block hat oben rechts eine Leiste: <b>⠿ Name</b> (zum Ziehen), <b>↑ ↓</b>, <b>Bearbeiten</b> und <b>⋯</b>. Unter <b>⋯</b> stehen alle weiteren Aktionen: Nach oben/unten, <b>Duplizieren</b>, <b>Kopieren</b>, <b>Einfügen darunter</b> (sobald etwas kopiert ist – auch auf anderen Seiten), <b>Einklappen</b>, <b>Abschnitt &amp; Navigation</b> und <b>Löschen</b> (zweiter Klick bestätigt).</li>
    <li><b>Verschieben:</b> mit <b>↑ ↓</b>, am Namen <b>⠿</b> ziehen (eine Linie zeigt, wo der Block landet) oder <kbd>Alt</kbd>/<kbd>⌥</kbd>+<kbd>↑</kbd>/<kbd>↓</kbd> – für den Block unter der Maus bzw. mit offener Seitenleiste. Ein Klick unter den letzten Block legt keinen neuen Block an – dafür gibt es <b>+ Block einfügen</b>.</li>
    <li><b>Übersicht behalten:</b> <b>Kompakt</b> im Menü <b>⋯</b> der Leiste oben klappt alle Blöcke zu schmalen Zeilen zusammen (mit Farbe, Titel, Sprungmarke) – ideal zum Umsortieren langer Seiten. Einzelne Blöcke klappen Sie im Block-Menü (<b>Einklappen</b>) bzw. per Klick auf die eingeklappte Zeile ein und aus. Der Browser merkt sich beides für die Seite.</li>
    <li><b>Löschen:</b> Menü ⋮⋮ → <b>Löschen</b> (zweimal klicken zur Bestätigung). Wiederherstellen lässt sich ein gelöschter Block über die <b>Versionen</b> der Seite.</li>
  </ol>
  <?= $img('blockmenu.webp', 'Auswahl der Blocktypen im Editor', '<b>Blöcke einfügen:</b> alle verfügbaren Bausteine' . ($__sample ? ' – die Seite <a href="' . e(url($__sample)) . '">' . e($__sample) . '</a> zeigt jeden als Beispiel' : '') . '.') ?>

  <div class="doc-note doc-note--info"><strong>Formular-Blöcke: „Felder bearbeiten“</strong><p>Bei Blöcken mit einem Formular aus einer Datentabelle (z. B. „Formular (Datentabelle)“) steht in der Leiste neben <b>Bearbeiten</b> zusätzlich <b>Felder bearbeiten</b> – sofern Ihre Rolle Tabellen und Felder ändern darf. Damit ändern, sortieren, ergänzen und entfernen Sie die Felder des Formulars direkt auf der Seite; nach dem Speichern zeigt der Block das neue Formular sofort. Einzelheiten: <a href="<?= e(url('/admin/hilfe#felder-im-editor')) ?>">Daten → Felder direkt auf der Seite ändern</a>.</p></div>

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

  <h3 id="ziehen">Mit der Maus ziehen</h3>
  <p>Einige Einstellungen lassen sich direkt auf der Seite ziehen – zeigen Sie mit der Maus auf einen Abschnitt, dann erscheinen kleine Griffe:</p>
  <ul>
    <li><b>Abstand oben/unten:</b> waagrechter Griff links am oberen bzw. unteren Rand des Abschnitts. Nach unten ziehen = mehr Abstand (Kein, Klein, Normal, Groß – „Groß“ je nach Design).</li>
    <li><b>Textbreite</b> (Fließtext, je nach Design): senkrechter Griff am rechten Rand des Textes – schmal, Lesebreite, breit, volle Breite.</li>
    <li><b>Aufteilung Bild/Text</b> (Text + Bild, je nach Design): Griff am inneren Rand des Bildes.</li>
    <li><b>Spaltenbreiten</b> im Block „Layout (Spalten)“: Griff zwischen zwei Spalten (siehe unten).</li>
  </ul>
  <p>Jede Funktion hat ihre eigene Farbe: <b>violett ↕</b> = Abstand, <b>bernstein ↔</b> = Textbreite, <b>pink ↔</b> = Aufteilung Bild/Text, <b>grün ↔</b> = Spaltenbreiten. Beim Zeigen nennt der Griff, was er ändert, und den aktuellen Wert; mit der Tastatur: Griff mit Tab ansteuern, Pfeiltasten. Griffe erscheinen nur dort, wo die Einstellung auch wirkt – Kopfbereiche haben z. B. eigene Abstände, dort gibt es keinen Abstand-Griff. Alles gilt erst nach <b>Speichern</b> – die gleichen Einstellungen stehen auch in der Seitenleiste.</p>
  <p><b>Leere Felder und Abstände:</b> Im Bearbeiten-Modus zeigen leere Felder ein „…“, damit man schnell hineinschreiben kann – sie nehmen dabei Platz ein. Beim Ziehen eines Griffs verschwinden sie automatisch, damit Sie die echten Abstände sehen. Dauerhaft ausblenden: Menü <b>⋯ → Leere Felder ausblenden</b> (zum Eintippen wieder einschalten; der Browser merkt sich die Wahl). Ganz ohne Bearbeitungsleisten zeigt <b>⋯ → Vorschau</b> die Seite wie für Besucher.</p>

  <h3 id="layout">Layout: Blöcke in Spalten</h3>
  <p>Für Blöcke <b>nebeneinander</b> – z. B. ein Text (⅔) und daneben eine Box mit Button (⅓), zwei Texte je ½ oder drei kurze Blöcke je ⅓ – gibt es den Block <b>„Layout (Spalten)“</b>. Sie wählen ein <b>Raster</b> und stellen die Blöcke in die Spalten; der Rest der Seite bleibt davon unberührt.</p>
  <ol class="doc-steps">
    <li><b>+ Block einfügen</b> → <b>Layout (Spalten)</b>. Die Seitenleiste öffnet sich: <b>Raster</b> per Klick auf eine der Kacheln wählen – ½ + ½, ⅔ + ⅓, ⅓ + ⅔, ⅓ × 3, ¼ × 4, ¼ + ¾ oder ¾ + ¼.</li>
    <li><b>Breiten fein einstellen:</b> Zwischen zwei Spalten sitzt ein schmaler <b>Griff</b>. Ziehen Sie ihn nach links oder rechts – die Spalte wird schmaler oder breiter, die Nachbarspalte gleicht aus. Der Griff rastet auf Zwölftel ein und zeigt die Aufteilung an (z. B. „⅓ · ⅔“ oder „5/12 · 7/12“). Mit der Tastatur: Griff mit Tab ansteuern, Pfeiltasten. Doppelklick (bzw. Entf) stellt die Breiten des Rasters wieder her; ein anderes Raster wählen ebenso.</li>
    <li>In jeder Spalte <b>+ Block in diese Spalte</b> wählen. Angeboten werden nur Blöcke, die in eine Spalte passen (z. B. Fließtext, Zitat, Hinweisbox, Handlungsaufruf als Box, Downloads, Video, Formular) – große Blöcke wie Kopfbereiche oder breite Bilder nicht.</li>
    <li>Texte schreiben Sie wie gewohnt direkt in der Seite. Über jedem Block in einer Spalte steht eine kleine Leiste: <b>↑ ↓</b> verschieben innerhalb der Spalte, <b>← →</b> in die Nachbarspalte, <b>Bearbeiten</b> öffnet alle Felder in der Seitenleiste, <b>✕</b> löscht den Block (mit Rückfrage).</li>
  </ol>
  <table class="doc-table">
    <tr><th>Einstellung des Layouts</th><th>Wirkung</th></tr>
    <tr><td>Raster</td><td>Aufteilung der Spalten. Wählen Sie ein Raster mit <b>weniger Spalten</b>, wandern die Blöcke der wegfallenden Spalten in die letzte Spalte – es geht nichts verloren.</td></tr>
    <tr><td>Ausrichtung vertikal</td><td>Spalten oben, mittig oder unten ausrichten – oder „gestreckt“: alle Spalten gleich hoch (z. B. Karten auf einer Linie).</td></tr>
    <tr><td>Abstand zwischen den Spalten</td><td>Klein, normal oder groß.</td></tr>
    <tr><td>Auf schmalen Bildschirmen</td><td>Auf dem Handy stehen die Spalten untereinander (Standard: unter ca. 768 px – manche Designs schon früher). „Schon auf Tablets untereinander“ stapelt bereits unter ca. 1024 px.</td></tr>
    <tr><td>Reihenfolge mobil umkehren</td><td>Untereinander steht die letzte Spalte zuerst – z. B. ein Bild rechts, das auf dem Handy über dem Text stehen soll.</td></tr>
    <tr><td>Abschnitt &amp; Navigation</td><td>Hintergrund, Sprungmarke, Navigation, Abstände, Trennlinie und Hintergrundbild gelten für das <b>ganze Layout</b> als ein Abschnitt.</td></tr>
  </table>
  <ul>
    <li><b>Hintergrund:</b> Blöcke in einer Spalte haben den Hintergrund des Layouts. Unter <b>Bearbeiten → In der Spalte → Eigene Fläche</b> wird ein Block zur <b>Karte</b> (eigene Fläche mit runden Ecken) – z. B. eine dunkle Box neben hellem Text. Dort lassen sich auch eine eigene Sprungmarke und „Sichtbar“ einstellen.</li>
    <li><b>Im Editor</b> sehen die Spalten aus wie auf der Website (nebeneinander bzw. auf schmalen Fenstern untereinander); gestrichelte Linien zeigen die Spalten.</li>
    <li><b>Tastatur:</b> Alle Knöpfe der Leisten sind mit <kbd>Tab</kbd> erreichbar; nach dem Verschieben bleibt der Fokus am verschobenen Block.</li>
    <li><b>Frühere „Reihen“:</b> Die alte Abschnitts-Option „Neben den vorigen Block stellen“ gibt es nicht mehr. Bestehende Reihen werden weiter dargestellt, bis die Technik sie umstellt (<code>layout:migrate-rows</code>); in der Seitenleiste lässt sich ein Block mit <b>Aus der Reihe lösen</b> wieder als eigener Abschnitt anzeigen.</li>
  </ul>

  <h3 id="rueckgaengig">Rückgängig und Wiederholen</h3>
  <p>Die Pfeile <b>↶ ↷</b> links neben „Abbrechen“ (oder <kbd>⌘</kbd>/<kbd>Strg</kbd> + <kbd>Z</kbd>, Wiederholen mit <kbd>⇧</kbd> + <kbd>⌘</kbd> + <kbd>Z</kbd> bzw. <kbd>Strg</kbd> + <kbd>Y</kbd>) nehmen die letzten Änderungen dieser Bearbeitung zurück – bis zu 50 Schritte: Texte, Felder der Seitenleiste, Abschnitts-Einstellungen, eingefügte, gelöschte und verschobene Blöcke. Mehrere getippte Wörter zählen als ein Schritt. Während Sie in einem Text schreiben, gilt dort das gewohnte Rückgängig des Browsers. Der Verlauf gilt bis zum Verlassen der Seite; ältere Stände holen Sie über <b>Versionen</b> zurück.</p>
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
