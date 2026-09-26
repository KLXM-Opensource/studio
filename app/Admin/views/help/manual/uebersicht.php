<?php /** Handbuch · Kapitel „Die Übersicht“ (Startseite der Verwaltung, Core\Dashboard) – Variablen: siehe help/manual.php */ ?>
  <p class="lead">Nach der Anmeldung landen Sie auf der <a href="<?= e(url('/admin')) ?>">Übersicht</a>. Sie zeigt auf einen Blick, wie es um die Website steht, was zu tun ist und wo Sie weitermachen können. Welche Karten erscheinen, hängt von Ihrer Rolle ab – eine Autorin sieht z. B. keine Technik-Karte.</p>

  <h3>Kopfbereich</h3>
  <p>Begrüßung mit Datum und der Zahl offener Punkte, daneben <b>Schnellaktionen</b>: <b>Neue Seite</b>, <b>Medien hochladen</b>, <b>Neu: …</b> (ein Eintrag in der Tabelle, die zuletzt am meisten genutzt wurde) und <b>Website ansehen</b>. Es erscheint nur, was Ihre Rolle darf.</p>

  <h3>Kennzahlen</h3>
  <p>Die Rundinstrumente zeigen echte Zahlen dieser Website – es gibt <b>kein Besucher-Tracking</b>. Die Skala füllt sich je nach Anteil, die kleine Zeile darunter vergleicht die <b>letzten 30 Tage</b> mit den 30 Tagen davor (↑ mehr, ↓ weniger).</p>
  <table class="doc-table">
    <tr><th>Kachel</th><th>Bedeutung</th></tr>
    <tr><td>Seiten online</td><td>Veröffentlichte Seiten von allen Seiten; Trend = wie oft Seiten gespeichert wurden.</td></tr>
    <tr><td>Einträge</td><td>Alle Einträge der Datentabellen, die Sie bearbeiten dürfen; Skala = Anteil „Online“; Trend = neue Einträge.</td></tr>
    <tr><td>Medien</td><td>Dateien in der Mediathek; Skala = Anteil der Bilder mit Alt-Text (dekorative Bilder zählen als erledigt).</td></tr>
    <tr><td>Neue Anfragen</td><td>Noch nicht geöffnete Online-Anfragen (nur mit Recht „Anfragen lesen“). Inhalte bleiben verschlüsselt – gezählt wird nur.</td></tr>
    <tr><td>Eingereicht</td><td>Änderungen von API, MCP oder KI, die auf Ihre Freigabe warten.</td></tr>
    <tr><td>Aktualität</td><td>Anteil der veröffentlichten Seiten, die in den letzten 90 Tagen geändert wurden.</td></tr>
  </table>
  <p>Erweiterungen (z. B. eine spätere Besucherstatistik) können hier eigene Kacheln und Karten ergänzen.</p>

  <h3>Was ist zu tun?</h3>
  <p>Eine Liste nach Dringlichkeit – jeder Punkt mit Anzahl, kurzer Erklärung und einem Knopf, der direkt an die richtige Stelle führt: neue Anfragen, eingereichte Änderungen, Support-Antworten, ungelesene Chat-Nachrichten, offene Punkte der Einrichtung (z. B. „Impressum zugeordnet“), Entwürfe, die seit über 14 Tagen liegen, Bilder ohne Alt-Text (auch je Sprache), Videos ohne Untertitel, Seiten ohne SEO-Beschreibung und externe Quellen mit Fehler. Ist alles erledigt, sagt die Karte das auch: <b>Alles erledigt</b>.</p>

  <h3>Statistiken</h3>
  <ul>
    <li><b>Aktivität (30 Tage):</b> Änderungen je Tag – gespeicherte Seiten, neue Einträge und Änderungen über API, MCP und KI. „Als Tabelle anzeigen“ listet die Zahlen je Tag.</li>
    <li><b>Zuletzt bearbeitet:</b> Ihre eigenen Seiten zum Weiterbearbeiten und was im Team zuletzt geändert wurde.</li>
    <li><b>Anfragen je Woche</b>, <b>Meistbearbeitete Seiten</b> und <b>Termine</b> der nächsten 7 Tage aus Kalender-Tabellen.</li>
    <li><b>Gesucht, nicht gefunden:</b> Suchbegriffe auf der Website ohne Treffer – nur, wenn die Suche sie zählt (Grundeinstellungen → Suche); gespeichert werden nur Begriff und Anzahl, nichts über die Besucher.</li>
    <li><b>Technik &amp; Betrieb</b> (nur Administration): Datenbank, Ordner, Schlüssel, Sicherung, Suche, KI, externe Quellen.</li>
  </ul>
  <p>Diese Karten laden kurz nach der Seite; die Zahlen sind höchstens 5 Minuten alt.</p>

  <h3>Hilfe &amp; Einstieg</h3>
  <p>Ist die KI eingeschaltet, stellen Sie hier eine Frage an <b><?= e(\Core\AI\Assist::brand()) ?></b> – der Assistent öffnet sich mit Ihrer Frage. Darunter: der Trailer „KLXM Studio im Überblick“, drei passende Tutorials für Ihre Rolle und – für die Administration – was in der neuesten Version neu ist.</p>

  <h3>Übersicht anpassen</h3>
  <ol class="doc-steps">
    <li>Ein Klick auf den <b>Titel einer Karte</b> klappt sie zu oder auf.</li>
    <li><b>Übersicht anpassen</b> zeigt an jeder Karte Pfeile (nach oben/unten), einen Griff zum Ziehen und ein Auge zum Aus- und Einblenden. Ausgeblendete Karten bleiben im Anpassen-Modus blass sichtbar.</li>
    <li><b>Fertig</b> beendet den Modus, <b>Standard wiederherstellen</b> setzt alles zurück.</li>
  </ol>
  <p>Die Anordnung gilt nur für Ihr Konto und wird sofort gespeichert. Alles funktioniert auch mit der Tastatur (Pfeil-Knöpfe statt Ziehen).</p>
