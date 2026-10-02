<?php /** Handbuch · Kapitel „Weiterleitungen“ (Core\Redirects; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Wenn sich Adressen ändern – nach einem Umzug der Website oder weil eine Seite umbenannt wurde – sorgen <b>Weiterleitungen</b> dafür, dass alte Links und Suchmaschinen-Treffer weiter funktionieren. Sie finden sie unter <b>Einrichtung › Website › Weiterleitungen</b> (Recht „Weiterleitungen verwalten“).</p>
  <ul>
    <li><b>Automatisch:</b> Benennen Sie eine veröffentlichte Seite um oder verschieben Sie sie, führt die alte Adresse von selbst zur Seite. Nichts zu tun.</li>
    <li><b>Von Hand:</b> „Neue Weiterleitung“ – alte Adresse (z. B. <code>/team/</code>) und Ziel wählen. Am besten eine Seite auswählen: Die Weiterleitung bleibt richtig, auch wenn die Seite später umbenannt wird.</li>
    <li><b>Art:</b> 301 für dauerhafte Änderungen (Standard), 302 für Vorübergehendes, 410 für Inhalte, die es nicht mehr gibt.</li>
    <li><b>Viele auf einmal:</b> Liste als CSV oder JSON importieren (zuerst „Nur prüfen“), mit <code>/alt/*</code> alle Adressen weiterleiten, die so beginnen.</li>
    <li><b>Adresse testen:</b> „Wohin führt …“ zeigt, ob eine Adresse eine Seite ist, weitergeleitet wird oder ins Leere läuft.</li>
    <li><b>Nicht gefunden (404):</b> zeigt Adressen, die Besucher vergeblich aufgerufen haben – mit Vorschlag und „Weiterleitung anlegen“ per Klick. „Interner Link“ heißt: Auf Ihrer eigenen Website zeigt ein Link ins Leere.</li>
    <li><b>404-Seite bearbeiten / anlegen</b> (oben im Reiter „Nicht gefunden“): gestaltet die Seite, die Besucher bei einer unbekannten Adresse sehen – siehe <a href="#<?= e($anchor('seiten')) ?>">Seiten verwalten → Seite „Nicht gefunden (404)“</a>.</li>
  </ul>
  <div class="doc-note doc-note--tip"><strong>Gut zu wissen</strong><p>Eine Weiterleitung greift nur, wenn es unter der alten Adresse keine Seite gibt – Ihre Seiten gehen immer vor.</p></div>
