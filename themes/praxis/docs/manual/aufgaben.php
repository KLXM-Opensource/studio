<?php /** Handbuch „praxis“ · Aufgaben aus dem Praxisalltag (vor den allgemeinen Aufgaben des Kerns) · @var string $settingsTitle */ ?>
  <p class="lead">Kurzanleitungen für das, was im Praxisalltag am häufigsten vorkommt.</p>
  <h3>Urlaub oder Vertretung ankündigen</h3>
  <ol class="doc-steps">
    <li><?= e($settingsTitle) ?> → Reiter <b>Hinweise</b>.</li>
    <li>Text bei <b>Aktueller Hinweis</b> eingeben, z. B. „Urlaub vom 21.–25.10. · Vertretung: Praxis Muster, Tel. 02841 1111“.</li>
    <li>Haken bei <b>Aktuellen Hinweis oben anzeigen</b> setzen. Nach dem Urlaub den Haken wieder entfernen.</li>
    <li>Darunter bei <b>Praxis geschlossen von / bis</b> die Urlaubstage eintragen → <b>Speichern</b>. Das Status-Badge zeigt dann „Praxis geschlossen bis …“ und nach dem Urlaub automatisch wieder die normalen Zeiten.</li>
    <li>Optional auf der Startseite im Block „Hinweise“ den Eintrag „Vertretung und Urlaubszeiten“ aktualisieren und veröffentlichen.</li>
  </ol>

  <h3>Sprechzeiten ändern</h3>
  <ol class="doc-steps">
    <li><?= e($settingsTitle) ?> → <b>Erreichbarkeit</b> → Eintrag des Wochentags anpassen (Uhrzeiten im Format 07:30).</li>
    <li>Für einen geschlossenen Nachmittag: Pause leer lassen, „Bis“ = Ende des Vormittags, Notiz „nachmittags geschlossen“.</li>
    <li><b>Speichern</b> – fertig. Kontaktkarte, Kontaktbereich und Google-Daten sind sofort aktuell.</li>
  </ol>

  <h3>Neues Thema im Kopfbereich (z. B. Grippeimpfung)</h3>
  <ol class="doc-steps">
    <li><?= e($settingsTitle) ?> → <b>Hero-Themen</b> → <b>+ Thema hinzufügen</b>, Typ „Aktuelles Thema“.</li>
    <li>Dachzeile, Titel, Text und optional einen Button (z. B. „Impftermin vereinbaren“ → <code>#kontakt</code>).</li>
    <li><b>Sichtbar ab/bis</b> setzen – das Thema erscheint und verschwindet dann automatisch.</li>
  </ol>

  <h3>Neue Ärztin / neuen Arzt vorstellen</h3>
  <ol class="doc-steps">
    <li>Startseite → <b>Bearbeiten</b> → Block „Ärztinnen und Ärzte“ anklicken.</li>
    <li><b>+ Person hinzufügen</b>: Fach, Titel, Name, Zusatzqualifikationen, kurzer Text (max. 400 Zeichen), Foto 1:1.</li>
    <li>Mit ↑ ↓ die Reihenfolge festlegen → <b>Veröffentlichen</b>.</li>
  </ol>

  <h3>Stellenanzeige veröffentlichen</h3>
  <ol class="doc-steps">
    <li>Neue Seite „Karriere“ anlegen oder auf der Startseite einen Block <b>Stellenangebot</b> einfügen.</li>
    <li>Schlagworte (z. B. „Ausbildung, Start 01.08.“), Titel, Beschreibung, Link (z. B. <code>mailto:bewerbung@…</code>).</li>
    <li>In der Team-Box der Startseite den Button auf die neue Seite verlinken.</li>
  </ol>
  <h3>Weitere Aufgaben</h3>
