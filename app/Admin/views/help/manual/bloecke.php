<?php /** Handbuch · Kapitel „Alle Blöcke“ (Blocktexte: 'help' der Blockdefinition bzw. 'blocks' aus dem Kit-Handbuch) · @var array $blocks  @var array $blockInfo  @var array $vars */ ?>
  <p class="lead">Diese Bausteine stehen im Editor zur Verfügung<?= !empty($vars['blocks_page']) ? ' – die Seite <a href="' . e(url($vars['blocks_page'])) . '">' . e($vars['blocks_page']) . '</a> zeigt jeden als Beispiel' : '' ?>. Welche Blöcke es gibt, legt das Kit fest; die Administration kann einzelne abschalten.</p>
  <div class="doc-scroll"><table class="doc-table">
    <tr><th>Block</th><th>Wofür</th><th>Varianten</th></tr>
    <?php foreach ($blocks as $type => $b): ?>
    <tr><td><span aria-hidden="true" class="tag"><?= icon($b['icon'] ?? 'table') ?></span> <b><?= e($b['label']) ?></b></td>
      <td><?= e($blockInfo[$type] ?? '') ?><?= !empty($b['central']) ? ' <span class="tag tag--read">' . e($settingsTitle) . '</span>' : '' ?></td>
      <td><?= !empty($b['variants']) ? e(implode(' · ', $b['variants'])) : '–' ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (!empty($blocks['hero']['variant_help'])): $hero = $blocks['hero']; ?>
  <h3 id="einstieg-varianten"><?= e($hero['label']) ?>: welche Variante wann?</h3>
  <p>Der Einstieg steht einmal ganz oben auf der Seite und trägt die Hauptüberschrift (H1). Die Variante wählen Sie in der Seitenleiste unter „Variante“ – danach zeigt die Seitenleiste nur die Felder, die zu dieser Variante gehören; Eingaben der anderen Varianten bleiben erhalten, falls Sie zurückwechseln.</p>
  <div class="doc-scroll"><table class="doc-table">
    <tr><th>Variante</th><th>Passt, wenn …</th></tr>
    <?php foreach ((array) $hero['variant_help'] as $key => $when): if (!isset($hero['variants'][$key])) continue; ?>
    <tr><td><b><?= e($hero['variants'][$key]) ?></b></td><td><?= e($when) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <ul>
    <li><b>Such-Einstieg:</b> braucht die eingeschaltete Website-Suche (Grundeinstellungen → Funktionen). Vorschläge unter dem Feld: ein Suchbegriff pro Zeile, oder „Beschriftung | Link“ für einen direkten Link.</li>
    <li><b>Video:</b> nur eigene, kurze Dateien ohne Ton aus der Mediathek (keine Einwilligung nötig). Das Video läuft stumm in Schleife, hat immer eine Pause-Schaltfläche und bleibt bei „Bewegung reduzieren“ ein Standbild – das Bild des Einstiegs.</li>
    <li><b>Kennzahlen, Termine, Formular, Karte:</b> nutzen dieselben Bausteine wie die gleichnamigen Blöcke (Kennzahlen mit Skala, Nächste Termine, Formular aus einer Datentabelle, Karte mit dem Standort aus den <?= e($settingsTitle) ?>). Nur belegbare Zahlen verwenden; das Formular kurz halten (2–4 Felder).</li>
    <li><b>Vorher/Nachher:</b> zwei Bilder mit gleichem Ausschnitt und Format; der Regler lässt sich mit Maus, Finger und Pfeiltasten bedienen.</li>
    <li><b>Laufzeile, Bewegung:</b> bewegt sich langsam, hält per Schaltfläche, Maus oder Tastatur und steht bei „Bewegung reduzieren“ still.</li>
  </ul>
  <?php endif; ?>
  <?php if (isset($blocks['dials'])): ?>
  <h3 id="kennzahlen-skala">Kennzahlen mit Skala</h3>
  <p>Jede Kennzahl erscheint als Rundinstrument mit großer Zahl, Bezeichnung und optionalem Zusatz (z. B. Quelle oder Zeitraum). Der farbige Bogen zeigt, wie „voll“ die Skala ist:</p>
  <ul>
    <li><b>Füllstand in %</b> ausgefüllt → genau dieser Anteil (0–100).</li>
    <li>sonst <b>Zahl</b> und <b>Höchstwert</b> → Zahl ÷ Höchstwert (z. B. 12 von 20 Plätzen). Ist der Wert selbst eine Zahl, genügt der Höchstwert.</li>
    <li>sonst ein <b>Prozentwert</b> („92 %“ oder Einheit „%“) → 92 % der Skala.</li>
    <li>sonst bleibt die Skala neutral (nur Striche bzw. Ring) – z. B. für „seit 2014“ oder „0,4 s“.</li>
  </ul>
  <p>Größe (klein/mittel/groß) und Skala (Bogen mit Strichen, nur Striche, schlichter Ring) stellen Sie in der Seitenleiste ein. „Hochzählen“ lässt Bogen und ganze Zahlen beim ersten Erscheinen einmal anlaufen – nicht bei „Bewegung reduzieren“. Screenreader lesen jede Kennzahl als Satz vor, z. B. „92 % – Weiterempfehlung, Beispielumfrage“. Farben und Schrift kommen aus dem Design. Bitte nur belegbare Zahlen verwenden.</p>
  <?php endif; ?>
  <?php if (isset($blocks['partners'])): ?>
  <h3 id="partner-logos">Partner &amp; Logos</h3>
  <p>Zeigt Logos von Partnern, Kunden oder Förderern in gleich großen Kacheln. Breite Schriftzüge, quadratische Zeichen und hohe Logos wirken dabei gleich schwer: Das System gibt jedem Logo ungefähr dieselbe Fläche – nicht dieselbe Höhe. Kurzinfo und Link erscheinen erst, wenn jemand auf ein Logo klickt (oder es mit der Tastatur auswählt). Bitte nur Logos verwenden, für die eine Freigabe vorliegt.</p>
  <h4>Logos pflegen</h4>
  <ul>
    <li><b>Quelle „Logos hier pflegen“:</b> je Logo eine Datei (am besten SVG oder PNG mit transparentem Hintergrund), den <b>Namen</b> (er dient auch als Alternativtext), eine <b>Kurzinfo</b> (ein bis zwei Sätze), optional <b>Link</b> mit eigener Beschriftung und eine <b>Kategorie</b>.</li>
    <li><b>Größe feinjustieren:</b> nur nötig, wenn eine Datei viel leeren Rand hat oder ein Logo sehr fein ist („etwas größer“ bzw. „etwas kleiner“).</li>
    <li><b>Einfarbiges Logo:</b> anhaken, wenn das Logo nur aus einer dunklen Farbe besteht – dann erscheint es auf dunklen Abschnitten und im dunklen Farbschema hell. Bereits weiße Logos nicht anhaken.</li>
  </ul>
  <h4>Tabelle anbinden</h4>
  <p>Mit „Aus einer Datentabelle“ kommen die Logos aus einer Tabelle unter <b>Daten</b> (z. B. „Partner“ mit Name, Logo, Beschreibung, Website, Bereich). Unter „Datentabelle“ wählen Sie die Tabelle und ordnen die Felder zu: Logo, Name, Kurzinfo, Link, Kategorie und – falls vorhanden – ein Ja/Nein-Feld „einfarbig“. Leere Zuordnungen nehmen sinnvolle Vorgaben (Bildfeld, Titel, Beschreibung, erstes Link-Feld). Es erscheinen nur <b>veröffentlichte</b> Einträge; mit „Nur Einträge, bei denen …“ filtern Sie zusätzlich, z. B. nach einem Bereich oder einem Ja/Nein-Feld (Wert 1). „Höchstens“ begrenzt die Anzahl.</p>
  <h4>Sortierung</h4>
  <ul>
    <li><b>Wie angelegt:</b> Reihenfolge der Liste bzw. wie in der Tabelle eingestellt.</li>
    <li><b>Name A–Z</b> oder <b>Kategorie, dann Name</b> – mit „Überschrift je Kategorie“ entsteht je Kategorie eine eigene Gruppe.</li>
    <li><b>Nach Tabellenfeld:</b> ein Feld der Tabelle auf- oder absteigend.</li>
    <li><b>Zufällig:</b> bei jedem Seitenaufruf neu gemischt (ohne JavaScript einmal am Tag).</li>
  </ul>
  <h4>Darstellung</h4>
  <ul>
    <li><b>Spalten</b> (3–6) gelten für große Bildschirme; auf Tablets stehen 3, auf Smartphones 2 Logos nebeneinander. <b>Format der Kachel:</b> 3:2, 1:1 oder 2:1. <b>Logogröße:</b> klein, mittel oder groß.</li>
    <li><b>Kachel:</b> dezente Fläche (passt sich dem Abschnitt an), immer hell (für farbige Logos auf dunklem Grund) oder ohne Fläche.</li>
    <li><b>Graustufen:</b> Logos erscheinen grau und werden farbig, sobald die Maus darüber steht oder sie den Tastaturfokus haben.</li>
    <li><b>Details:</b> „Aufklappen unter der Reihe“ öffnet die Angaben direkt unter den Logos (immer eines), „Dialog“ in einem Fenster; „Keine Details“ macht aus jedem Logo direkt einen Link. Esc oder „Schließen“ schließt und springt zurück zum Logo. Ohne JavaScript stehen alle Angaben als Liste unter den Logos.</li>
    <li>Überschrift und Einleitung wie bei anderen Blöcken; den Hintergrund des Abschnitts wählen Sie wie gewohnt – auf dunklen Abschnitten passen sich Kacheln und Texte an.</li>
  </ul>
  <?php endif; ?>
