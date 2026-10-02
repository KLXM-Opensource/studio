<?php /** Handbuch · Kapitel „Externe Quellen“ (Core\Sources; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Nachrichten aus einem Feed, Kurse aus einer Buchungs-Schnittstelle oder Immobilien aus der Makler-Software: <b>Externe Quellen</b> holen solche Daten regelmäßig ab und legen sie als Einträge einer Datentabelle an. Auf der Website erscheinen sie wie selbst gepflegte Einträge – in der Datenliste, auf Detailseiten und in der Suche.</p>
  <ol class="doc-steps">
    <li><b>Daten → Externe Quellen → Vorlage wählen:</b> „RSS/Atom-Feed“, „OpenImmo“, „JSON-API“ oder „XML“. Name und Adresse der Quelle eintragen; braucht die Quelle eine Anmeldung, Token bzw. Benutzer und Passwort unter „Anmeldung“ angeben (sie werden verschlüsselt gespeichert und nie wieder angezeigt).</li>
    <li><b>Vorschau laden:</b> Das System ruft die Quelle ab und zeigt unter <b>„Felder in der Quelle“</b> alles, was ein Eintrag enthält – mit Bedeutung (z. B. „Datum der Veröffentlichung“), dem <em>Pfad</em> (z. B. <code>pubDate</code>) und einem Beispielwert aus dem ersten Eintrag. Sie müssen das XML oder JSON der Quelle also nie selbst lesen.</li>
    <li><b>Zieltabelle:</b> eine vorhandene Tabelle wählen – oder <b>„+ Neue Tabelle aus dieser Quelle anlegen …“</b>. Dann schlägt das System Name, Felder und Feldtypen aus den Daten vor (Datum → „Datum &amp; Uhrzeit“, Bildadresse → „Bild“, Link → „Webadresse“, HTML → „Formatierter Text“). Haken entfernen, was Sie nicht brauchen, Bezeichnungen anpassen, auf Wunsch Detailseite und Übersichtsseite (auch im Menü) anlegen lassen – „Tabelle anlegen und zuordnen“ legt alles an und füllt die Zuordnung aus. Dafür braucht Ihre Rolle das Recht „Tabellen und Felder ändern“.</li>
    <li><b>Zuordnung prüfen:</b> Jede Zeile füllt ein Feld Ihrer Tabelle. Mit <b>„Auswählen“</b> übernehmen Sie einen Wert der Quelle (Tastatur: Pfeiltasten, Enter, Esc); darunter steht sofort, was im ersten Eintrag ankommt („Beispiel: 01.10.2026, 14:05“). <b>„Zuordnung vorschlagen“</b> füllt alle leeren Zeilen passend zu Feldname und Feldtyp. Pflichtfelder ohne Wert werden rot markiert.</li>
    <li><b>Probeabruf:</b> zeigt die ersten drei Einträge so, wie sie gespeichert würden – Feld für Feld, Fehler hervorgehoben. Es wird nichts gespeichert.</li>
    <li><b>Speichern und „Jetzt abrufen“.</b> Danach holt der Zeitplan (stündlich oder täglich) neue und geänderte Einträge automatisch. Das Protokoll zeigt die letzten 20 Abrufe mit Hinweisen.</li>
  </ol>
  <h3>Beispiel: einen RSS-Feed übernehmen</h3>
  <ol class="doc-steps">
    <li>Vorlage „RSS/Atom-Feed“, Name „Nachrichten“, Adresse des Feeds eintragen (meist endet sie auf <code>/feed</code>, <code>/rss</code> oder <code>.xml</code>) und <b>Vorschau laden</b>.</li>
    <li>Unter „Felder in der Quelle“ erscheinen z. B. <code>title</code> (Titel), <code>link</code> (Link zum Beitrag), <code>pubDate</code> (Datum der Veröffentlichung), <code>description</code> (Beschreibung), <code>enclosure@url</code> (Bild) und <code>dc:creator</code> (Autor).</li>
    <li>Ihre Tabelle „Aktuelles“ hat ein Pflichtfeld <b>„Veröffentlichen ab“</b> (Datum &amp; Uhrzeit)? Tabelle wählen – die Zuordnung wird vorgeschlagen: „Veröffentlichen ab“ ← <code>pubDate</code>, Titel ← <code>title</code>, Bild ← <code>enclosure@url</code>. Unter jeder Zeile prüfen Sie das Beispiel. Liefert der Feed kein Datum, wählen Sie als Umwandlung <b>„jetzt (Zeitpunkt des Abrufs)“</b>.</li>
    <li>Kurztext oder Teaser aus der langen Beschreibung: Umwandlung <b>„Kürzen“</b> (Standard 200 Zeichen, unter „Erweitert“ änderbar) oder <b>„Nur erster Absatz“</b>. Bei Feldern namens Teaser, Kurztext oder Zusammenfassung schlägt das System „Kürzen 200“ selbst vor.</li>
    <li>Probeabruf ansehen, speichern, „Jetzt abrufen“.</li>
  </ol>
  <h3>Pfade lesen</h3>
  <ul>
    <li><code>pubDate</code> – ein Element des Eintrags; <code>author.name</code> – ein Element in einem anderen (Punkt).</li>
    <li><code>enclosure@url</code> – ein Attribut (mit <code>@</code>): die Adresse aus <code>&lt;enclosure url="…"&gt;</code>.</li>
    <li><code>content:encoded | description</code> – Alternativen mit <code>|</code>: der erste nicht leere Wert zählt.</li>
    <li><code>category[*]</code> – alle Werte einer Liste, mit Komma verbunden („Politik, Sport“); <code>category[0]</code> nur der erste.</li>
  </ul>
  <h3>Umwandlungen</h3>
  <ul>
    <li><b>automatisch</b> passt zum Feldtyp. <b>Text</b> entfernt HTML und überflüssige Leerzeichen, <b>HTML bereinigen</b> lässt nur sichere Formatierungen stehen (Fett, Links, Listen …).</li>
    <li><b>Datum</b> erkennt RSS-Datum (RFC 822, „Thu, 01 Oct 2026 12:05:00 +0000“), ISO 8601 („2026-10-01T12:05:00Z“), Unix-Zeit und „01.10.2026“ automatisch; <b>Zahl</b> liest „1.234,50 €“ oder „1,234.50“. Unter „Erweitert → Option“ lassen sich feste Formate angeben, z. B. <code>d.m.Y</code> oder <code>de</code>.</li>
    <li><b>Kürzen</b> entfernt HTML und kürzt an einer Wortgrenze mit „…“ (Option = Zeichenzahl, Standard 200). <b>Nur erster Absatz</b> nimmt den ersten Absatz (Option: zusätzlich kürzen). In formatierten Feldern entsteht daraus ein einfacher Absatz.</li>
    <li><b>jetzt (Zeitpunkt des Abrufs)</b> setzt den Zeitpunkt, zu dem ein Eintrag zum ersten Mal abgerufen wurde – er ändert sich bei späteren Abrufen nicht. Ist zusätzlich ein Pfad eingetragen und liefert er ein Datum, zählt dieses.</li>
    <li><b>Vorlage</b> setzt Werte zusammen, z. B. <code>{dc:creator} · {category}</code> oder <code>{geo.strasse} {geo.hausnummer}</code>. <b>Werte ersetzen</b> übersetzt Kürzel, z. B. <code>VERBRAUCH=Verbrauchsausweis</code>. Der <b>Standardwert</b> greift, wenn die Quelle nichts liefert. Alle drei stehen je Zeile unter <b>„Erweitert“</b>.</li>
    <li><b>Bilder</b> werden in die Mediathek übernommen (neu gespeichert, ohne Kamera- und Standortdaten) und landen in der Sammlung „Quelle: …“. Den Alt-Text liefert ein eigener Pfad oder der Titel des Eintrags.</li>
  </ul>
  <h3>Übernommene Einträge</h3>
  <p>Einträge aus einer Quelle tragen in der Liste das Zeichen <b>„aus Quelle“</b>. Sie sind nur lesbar, denn beim nächsten Abruf kämen die Werte ohnehin wieder aus der Quelle. Mit <b>Ausblenden</b> nehmen Sie einen Eintrag von der Website – er bleibt ausgeblendet, auch wenn die Quelle ihn weiter liefert. Fehlt ein Eintrag in der Quelle, wird er je nach Einstellung ausgeblendet, gelöscht oder behalten; taucht er wieder auf, erscheint er wieder.</p>
  <h3>OpenImmo</h3>
  <p>Makler-Software exportiert Angebote meist als ZIP mit <code>openimmo.xml</code> und Bildern. Laden Sie die Datei rechts unter „OpenImmo-Datei hochladen“ hoch – oder tragen Sie die Adresse ein, unter der die Software sie bereitstellt. Die Vorlage „Immobilien“ enthält Objektnummer, Art, Preis, Flächen, Adresse, Bilder, Texte, Energieausweis und Kontakt. <b>Datenschutz:</b> Ist die Adresse eines Objekts nicht zur Veröffentlichung freigegeben, übernimmt das System nur Postleitzahl und Ort.</p>
  <div class="doc-note doc-note--tip"><strong>Wer darf das?</strong><p>Die Funktion schaltet Ihre Agentur bzw. die Netzwerk-Administration je Website ein. Einrichten und Abrufen dürfen Rollen mit dem Recht „Externe Quellen einrichten und abrufen“; ein- und ausblenden darf, wer Einträge veröffentlichen darf.</p></div>
