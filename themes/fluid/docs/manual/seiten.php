<?php /** Handbuch „fluid“ · Hinweise zu Seiten & Menü · @var string $settingsTitle */ ?>
  <h3>Tipps für dieses Design</h3>
  <ul>
    <li>Beginnen Sie jede Seite mit einem <b>Einstieg (Hero)</b> – er liefert die Hauptüberschrift. Für Unterseiten eignet sich „Seitenkopf“.</li>
    <li>Wechseln Sie Hintergründe ab (Standard, Getönt, Akzent hell, Dunkel) – gleiche Hintergründe hintereinander rücken automatisch zusammen.</li>
    <li>Impressum und Datenschutz gehören nicht ins Menü – sie werden über <?= e($settingsTitle) ?> → Recht automatisch im Fußbereich verlinkt.</li>
  </ul>
