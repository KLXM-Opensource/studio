<?php /** Handbuch · Kapitel „Häufige Aufgaben“ (Kits ergänzen eigene Aufgaben mit mode => before) */ ?>
  <?php if (($__ch['files'][0] ?? '') === __FILE__): // Einleitung nur, wenn kein Kit-Teil davor steht ?>
  <p class="lead">Kurzanleitungen für das, was im Alltag am häufigsten vorkommt.</p>
  <?php endif; ?>
  <h3>Neuen Beitrag veröffentlichen</h3>
  <ol class="doc-steps">
    <li><b>Daten</b> → Tabelle (z. B. „Aktuelles“) → <b>+ Beitrag</b>.</li>
    <li>Titel, Text und Bild eintragen, Status „Online“ → <b>Speichern</b>. Listen und Detailseite sind sofort aktuell.</li>
  </ol>
  <h3>Bild austauschen</h3>
  <ol class="doc-steps">
    <li><b>Medien</b> → Bild doppelklicken → <b>Datei ersetzen …</b></li>
    <li>Neue Datei wählen – überall, wo das Bild verwendet wird, erscheint automatisch das neue.</li>
  </ol>
  <h3>Datei zum Download anbieten</h3>
  <ol class="doc-steps">
    <li>PDF unter <b>Medien</b> hochladen (optional in eine Sammlung legen).</li>
    <li>Im Editor einen Block <b>Downloads</b> einfügen und die Datei oder die Sammlung wählen → <b>Veröffentlichen</b>.</li>
  </ol>
  <h3 id="video">YouTube- oder Vimeo-Video einbinden</h3>
  <ol class="doc-steps">
    <li>Im Editor einen Video-Block einfügen und den Link des Videos eintragen.</li>
    <li>Vorschaubild und Titel holt die Website automatisch. <b>Veröffentlichen</b>.</li>
  </ol>
  <div class="doc-note doc-note--tip"><strong>Datenschutz automatisch erledigt</strong><p>Besucher sehen zunächst nur ein Vorschaubild, das vom eigenen Server kommt – ohne Verbindung zu YouTube oder Vimeo. Erst mit einem Klick wird der Player geladen (YouTube im erweiterten Datenschutzmodus). Bitte die Datenschutzerklärung um den Videoanbieter ergänzen. Eigene MP4-Videos brauchen Untertitel (siehe <a href="#<?= e($anchor('medien')) ?>">Bilder &amp; Dateien</a>).</p></div>
  <h3>Neue Unterseite im Menü</h3>
  <ol class="doc-steps">
    <li><b>Seiten</b> → Rechtsklick auf die übergeordnete Seite → <b>Neue Unterseite</b>.</li>
    <li>Inhalte eintragen, veröffentlichen und in der Seitenübersicht den Schalter <b>Menü</b> einschalten.</li>
  </ol>
