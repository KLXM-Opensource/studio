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
