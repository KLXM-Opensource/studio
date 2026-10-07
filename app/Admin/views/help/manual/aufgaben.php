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
  <h3 id="app-icon">App-Icon (Favicon) ändern</h3>
  <ol class="doc-steps">
    <li><b>Grundeinstellungen → App-Icon &amp; PWA</b> (Administration): <b>Icon aus …</b> Buchstaben in der Hausschrift oder einem Bild bzw. Logo aus der Mediathek (quadratisch, mindestens 512 × 512 px) – auch aus geteilten Medien.</li>
    <li>Form, <b>Hintergrund</b> und Schriftfarbe wählen. Mit <b>Transparent</b> neben der Hintergrundfarbe bleibt die Fläche durchsichtig, etwa für ein freigestelltes Logo. Das gilt für Browser-Tab und Android; das iPhone-Icon und das „maskable“-Icon brauchen eine Fläche und werden weiß hinterlegt.</li>
    <li>Die Vorschau zeigt das Ergebnis sofort; <b>Speichern</b> erzeugt alle Größen neu. Browser zeigen das neue Icon manchmal erst nach einem Neuladen.</li>
  </ol>
  <h3>Neue Kollegin oder neuen Kollegen einladen</h3>
  <ol class="doc-steps">
    <li><b>Benutzer &amp; Rollen → Einladen &amp; Anlegen</b>: E-Mail-Adresse und Rolle eintragen → <b>Einladung senden</b>.</li>
    <li>Die Person wählt beim Annehmen selbst Passkey und/oder Passwort. Einzelheiten: <a href="#einladen">Personen einladen</a>.</li>
  </ol>
  <h3>Schief fotografiertes Bild gerade rücken</h3>
  <ol class="doc-steps">
    <li><b>Medien</b> → Bild doppelklicken → in der Werkzeugleiste <b>Ausrichten</b> (Horizont) oder <b>Entzerren</b> (Schild, Gebäude, Dokument).</li>
    <li><b>Speichern</b> – das Original bleibt erhalten. Einzelheiten: <a href="#bild-bearbeiten">Bild bearbeiten</a>.</li>
  </ol>
