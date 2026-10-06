<?php /** Handbuch „galerie“ · Kapitel „Galerie: Künstler, Ausstellungen, Werke“ · @var string $settingsTitle */ ?>
  <p class="lead">Künstlerinnen und Künstler, Ausstellungen und Werke pflegen Sie einmal unter <b>Daten</b> – sie erscheinen automatisch auf der Startseite, in Listen, auf ihren eigenen Seiten und in der Suche.</p>
  <h3>Der Status einer Ausstellung rechnet sich selbst</h3>
  <p>Es gibt kein Feld „aktuell“. Aus <b>Beginn</b> und <b>Ende</b> ergibt sich jeden Tag neu:</p>
  <table class="doc-table">
    <tr><th>Etikett</th><th>Wann</th></tr>
    <tr><td>Jetzt</td><td>Beginn ist erreicht, Ende noch nicht vorbei (ohne Ende: „bis auf Weiteres“). In der letzten Woche steht „Letzte Tage“.</td></tr>
    <tr><td>Demnächst</td><td>Beginn liegt in der Zukunft. Die Eröffnung (Vernissage) erscheint, solange sie bevorsteht.</td></tr>
    <tr><td>Archiv</td><td>Ende ist vorbei – die Ausstellung wandert ins Archiv, nach Jahren sortiert.</td></tr>
  </table>
  <p>Der Block <b>Aktuelle Ausstellung</b> zeigt deshalb von selbst immer die laufende Ausstellung – oder, wenn gerade keine läuft, die nächste.</p>
  <h3>Die drei Tabellen</h3>
  <table class="doc-table">
    <tr><th>Tabelle</th><th>Wichtige Felder</th><th>Adresse</th></tr>
    <tr><td>Künstler</td><td>Name, Sortiername (für die alphabetische Liste, meist der Nachname), hervorheben, Porträt, Geboren, Lebt/arbeitet, Kurzbiografie, Biografie, Website, Bilder oder Videos, Video-Link</td><td>/kuenstler/…</td></tr>
    <tr><td>Ausstellungen</td><td>Titel, Untertitel, Künstler (mehrere), Beginn, Ende, Eröffnung, Ort (Galerie, Kabinett, Showroom, Kunstmesse, Extern) mit Ortsangabe (z. B. Messestand), Kurztext, Text, Hauptbild, Ansichten oder Videos, gezeigte Werke, Video-Link, Pressetext (PDF)</td><td>/ausstellungen/…</td></tr>
    <tr><td>Werke</td><td>Titel, Künstler, Jahr, Technik, Maße, Auflage, Verfügbarkeit (verfügbar, reserviert, verkauft), Abbildung, weitere Ansichten oder Videos, Preis, Preisanzeige (Preis zeigen, „Preis auf Anfrage“, ausblenden), Beschreibung, Video-Link</td><td>/werke/…</td></tr>
  </table>
  <ul>
    <li><b>Verfügbarkeit:</b> erscheint als Wort mit Punkt – grün verfügbar, halb gefüllt reserviert, rot verkauft. Verkaufte Werke zeigen nie einen Preis und keinen Anfrage-Button.</li>
    <li><b>Preise</b> steuern Sie je Werk; unter Design → Galerie → „Preise“ lassen sie sich für alle Werke auf „auf Anfrage“ oder „nie anzeigen“ stellen.</li>
    <li><b>Kunstmessen</b> sind Ausstellungen mit dem Ort „Kunstmesse“ – Halle und Stand stehen in der Ortsangabe. Der Block „Ausstellungen“ in der Variante „Messen &amp; Termine“ listet sie kompakt.</li>
    <li><b>Entwürfe:</b> Einträge lassen sich als Entwurf vorbereiten und später veröffentlichen.</li>
  </ul>
  <h3>Die Galerie-Blöcke</h3>
  <table class="doc-table">
    <tr><th>Block</th><th>Wofür</th></tr>
    <tr><td>Aktuelle Ausstellung</td><td>Startseite: laufende (oder gewählte) Ausstellung groß – Vollbild, Bild und Text oder typografisch. Auf Wunsch nur an einem Ort (z. B. Kabinett).</td></tr>
    <tr><td>Ausstellungen</td><td>Liste, Karten, Zeitleiste oder „Messen &amp; Termine“; alle mit Reitern „Aktuell · Demnächst · Archiv“ oder nur laufende/kommende/vergangene.</td></tr>
    <tr><td>Künstlerinnen und Künstler</td><td>Typografische Liste (beim Zeigen erscheint ein Bild), Raster mit Porträts oder mit einem Werk, A–Z-Register.</td></tr>
    <tr><td>Werke</td><td>Raster, Mauerwerk, Salonhängung oder <b>Viewing Room</b> (ein Werk je Bildschirm) – mit Filtern für Besucher und Anfrage-Button.</td></tr>
    <tr><td>Besuch</td><td>Heute geöffnet/geschlossen, abweichende Öffnungszeiten, alle Orte mit Kartenlink, Eintritt, Termine nach Vereinbarung.</td></tr>
    <tr><td>Künstler / Ausstellung / Werk (Detailseite)</td><td>Gehören auf die Detailseiten der Tabellen (Seitenliste → Vorlagen). „Werke“ und „Ausstellungen“ zeigen dort automatisch nur die passenden Einträge.</td></tr>
  </table>
  <h3>Anfragen zu Werken</h3>
  <p>Unter <?= e($settingsTitle) ?> → Galerie → „Anfragen zu Werken“ legen Sie fest, wohin der Button „Anfrage zu diesem Werk“ führt: auf eine Seite mit Formular (das Feld „Werk“ ist dann schon ausgefüllt) oder in eine E-Mail mit dem Werk im Betreff. In der Demo ist das die Seite „Besuch“ mit der Tabelle „Anfragen“.</p>
