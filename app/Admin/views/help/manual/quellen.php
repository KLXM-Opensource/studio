<?php /** Handbuch · Kapitel „Externe Quellen“ (Core\Sources; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Nachrichten aus einem Feed, Kurse aus einer Buchungs-Schnittstelle oder Immobilien aus der Makler-Software: <b>Externe Quellen</b> holen solche Daten regelmäßig ab und legen sie als Einträge einer Datentabelle an. Auf der Website erscheinen sie wie selbst gepflegte Einträge – in der Datenliste, auf Detailseiten und in der Suche.</p>
  <ol class="doc-steps">
    <li><b>Daten → Externe Quellen → Vorlage wählen:</b> „RSS/Atom-Feed“, „OpenImmo“, „JSON-API“ oder „XML“. Name und Adresse der Quelle eintragen; braucht die Quelle eine Anmeldung, Token bzw. Benutzer und Passwort unter „Anmeldung“ angeben (sie werden verschlüsselt gespeichert und nie wieder angezeigt).</li>
    <li><b>Zieltabelle:</b> eine vorhandene Tabelle wählen oder nach dem ersten Speichern mit „Tabelle … anlegen“ eine passende Tabelle samt Detailseite erzeugen lassen (z. B. „Meldungen“ oder „Immobilien“).</li>
    <li><b>Vorschau &amp; Test:</b> ruft die Quelle ab und zeigt die ersten Einträge – so, wie sie gespeichert würden. Jede Zeile der Zuordnung sagt, aus welchem <em>Pfad</em> der Quelle ein Feld gefüllt wird; die gefundenen Pfade stehen in der Auswahlliste und unter „Gefundene Pfade“.</li>
    <li><b>Speichern und „Jetzt abrufen“.</b> Danach holt der Zeitplan (stündlich oder täglich) neue und geänderte Einträge automatisch. Das Protokoll zeigt die letzten 20 Abrufe mit Hinweisen.</li>
  </ol>
  <h3>Umwandlungen</h3>
  <ul>
    <li><b>Text</b> entfernt HTML und überflüssige Leerzeichen, <b>HTML bereinigen</b> lässt nur sichere Formatierungen stehen (Fett, Links, Listen …).</li>
    <li><b>Datum</b> und <b>Zahl</b> lesen übliche Schreibweisen automatisch („22.09.2026“, „Tue, 22 Sep 2026“, „1.234,50 €“); unter „Option“ lassen sich feste Formate angeben, z. B. <code>d.m.Y</code> oder <code>de</code>.</li>
    <li><b>Vorlage</b> setzt Werte zusammen, z. B. <code>{geo.strasse} {geo.hausnummer}</code>. <b>Werte ersetzen</b> übersetzt Kürzel, z. B. <code>VERBRAUCH=Verbrauchsausweis</code>. Der <b>Standardwert</b> greift, wenn die Quelle nichts liefert.</li>
    <li><b>Bilder</b> werden in die Mediathek übernommen (neu gespeichert, ohne Kamera- und Standortdaten) und landen in der Sammlung „Quelle: …“. Den Alt-Text liefert ein eigener Pfad oder der Titel des Eintrags.</li>
  </ul>
  <h3>Übernommene Einträge</h3>
  <p>Einträge aus einer Quelle tragen in der Liste das Zeichen <b>„aus Quelle“</b>. Sie sind nur lesbar, denn beim nächsten Abruf kämen die Werte ohnehin wieder aus der Quelle. Mit <b>Ausblenden</b> nehmen Sie einen Eintrag von der Website – er bleibt ausgeblendet, auch wenn die Quelle ihn weiter liefert. Fehlt ein Eintrag in der Quelle, wird er je nach Einstellung ausgeblendet, gelöscht oder behalten; taucht er wieder auf, erscheint er wieder.</p>
  <h3>OpenImmo</h3>
  <p>Makler-Software exportiert Angebote meist als ZIP mit <code>openimmo.xml</code> und Bildern. Laden Sie die Datei rechts unter „OpenImmo-Datei hochladen“ hoch – oder tragen Sie die Adresse ein, unter der die Software sie bereitstellt. Die Vorlage „Immobilien“ enthält Objektnummer, Art, Preis, Flächen, Adresse, Bilder, Texte, Energieausweis und Kontakt. <b>Datenschutz:</b> Ist die Adresse eines Objekts nicht zur Veröffentlichung freigegeben, übernimmt das System nur Postleitzahl und Ort.</p>
  <div class="doc-note doc-note--tip"><strong>Wer darf das?</strong><p>Die Funktion schaltet Ihre Agentur bzw. die Netzwerk-Administration je Website ein. Einrichten und Abrufen dürfen Rollen mit dem Recht „Externe Quellen einrichten und abrufen“; ein- und ausblenden darf, wer Einträge veröffentlichen darf.</p></div>
