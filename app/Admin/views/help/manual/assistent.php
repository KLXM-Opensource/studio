<?php /** Handbuch · Kapitel „KLXM AI“ – KI-Funktionen der Redaktion (Variablen: siehe help/manual.php) */ $__brand = \Core\AI\Assist::brand(); ?>
  <p class="lead">Wenn die Administration die KI eingeschaltet hat (<b>Grundeinstellungen → KI</b>) und Ihre Rolle „KI-Funktionen nutzen“ darf, erscheinen überall ein <b>✦ KI</b>-Knopf und ✦-Vorschläge sowie im Hauptmenü der Bereich <b><?= e($__brand) ?></b>. Die KI macht immer nur <b>Vorschläge</b>: Nichts wird ohne Ihre Prüfung gespeichert oder veröffentlicht.</p>
  <h3>Texte schreiben und überarbeiten</h3>
  <ul>
    <li><b>✦ KI</b> in der Formatierungsleiste (auch direkt im Text auf der Website) und an mehrzeiligen Textfeldern: <b>Verbessern, Kürzen, Erweitern, Einfacher</b> (angelehnt an Leichte Sprache), <b>Korrigieren</b> (nur Rechtschreibung), <b>Ton ändern</b> (sachlich, freundlich, förmlich) und <b>Freier Auftrag</b> („Schreibe eine kurze Einleitung zu …“).</li>
    <li>Ist Text markiert, wird nur die Markierung bearbeitet, sonst das ganze Feld. Der Vorschlag erscheint als <b>Vorschau</b> und unter <b>Änderungen</b> (gelöscht rot, neu grün). <b>Übernehmen</b> setzt ihn ins Feld, <b>Erneut</b> fragt noch einmal, <b>Verwerfen</b> lässt alles, wie es war. Gespeichert wird wie immer mit „Speichern“.</li>
    <li>Die KI erfindet keine Fakten: Fehlt eine Angabe, schreibt sie <code>[bitte ergänzen: …]</code>. Der Assistent warnt außerdem, wenn Zahlen, E-Mail- oder Web-Adressen aus dem Original fehlen oder neu hinzukommen. Seiten mit solchen offenen Platzhaltern lassen sich <b>nicht veröffentlichen</b> – das CMS nennt die offenen Stellen.</li>
  </ul>
  <h3>Übersetzen</h3>
  <ul>
    <li>In einer Übersetzung (Seite, Eintrag, zentrale Angaben in einer weiteren Sprache): <b>✦ Aus Deutsch übersetzen</b> (bzw. aus der Standardsprache der Website). Sie sehen je Text links das Original und rechts den Vorschlag, den Sie direkt ändern können. Übernommen wird nur, was angehakt ist.</li>
    <li>Schon vorhandene, von Menschen übersetzte Texte sind <b>nicht angehakt</b> und werden nur nach Rückfrage ersetzt.</li>
    <li>Seiteninhalte landen als <b>Entwurf</b> (unter „Versionen“ als „KI-Übersetzung übernommen“), Titel und Beschreibung im Formular – prüfen, speichern, veröffentlichen.</li>
    <li>Im Bereich <b><?= e($__brand) ?> → Übersetzen</b> stehen je Sprache alle fehlenden Seiten, Einträge, Alt-Texte und Website-Texte. „Anlegen &amp; übersetzen“ legt eine fehlende Übersetzung als Entwurf an.</li>
    <li>Marken- und Eigennamen, die nie übersetzt werden sollen, trägt die Administration ins <b>Glossar</b> ein (Grundeinstellungen → KI).</li>
  </ul>
  <h3>Suchmaschinen (SEO)</h3>
  <ul>
    <li><b>Seiteneinstellungen → SEO-Check</b> prüft ohne KI: Länge von Titel und Beschreibung, doppelte Titel, Überschriften-Struktur, Bilder ohne Alt-Text, Textmenge und Vorschaubild.</li>
    <li><b>✦ SEO-Vorschläge</b> schlägt einen Titel für Suchmaschinen (mit Zähler), eine Beschreibung (bis 155 Zeichen), eine Adresse und Suchbegriffe vor. Eine neue Adresse einer veröffentlichten Seite macht alte Links ungültig – der Assistent fragt deshalb nach.</li>
    <li>Bei Einträgen (z. B. Aktuelles) gibt es zusätzlich <b>✦ „Teaser“ vorschlagen</b> für das Kurztext-Feld.</li>
    <li><b><?= e($__brand) ?> → SEO</b>: alle Seiten und Einträge mit fehlender oder unpassender Beschreibung; Zeilen auswählen, Vorschläge erzeugen, prüfen, „Ausgewählte übernehmen“.</li>
  </ul>
  <h3>Bilder: Alt-Texte</h3>
  <ul>
    <li>Beim <b>Hochladen</b>: <b>✦ Alt-Text vorschlagen</b> füllt das Pflichtfeld mit einem gekennzeichneten „KI-Vorschlag – bitte prüfen“. Hochladen geht erst, wenn Sie den Text geändert oder mit <b>Geprüft ✓</b> bestätigt haben.</li>
    <li>In der <b>Mediathek</b> (Info rechts): Alt-Text in allen Sprachen, Titel und Tags vorschlagen lassen; „Alt-Text übernehmen“ speichert erst nach Ihrem Klick.</li>
    <li><b><?= e($__brand) ?> → Alt-Texte</b> (oder <b>✦ Alt-Texte vorschlagen</b> in der Mediathek): Die KI geht alle Bilder ohne Alt-Text nacheinander durch, Sie prüfen jede Zeile und speichern nur die angehakten.</li>
    <li>Rein schmückende Bilder (Muster, Flächen) markiert man besser als „dekorativ“ – die KI weist darauf hin.</li>
  </ul>
  <h3>Untertitel und Transkripte</h3>
  <p>Für Videos und Audio schreibt die KI Untertitel mit Zeiten und ein Transkript – immer als <b>Entwurf</b>, der erst nach Ihrer Prüfung online geht. Einzelheiten stehen im Kapitel <a href="#<?= e($anchor('medien')) ?>">Bilder &amp; Dateien</a> unter „Untertitel &amp; Transkripte“; <b><?= e($__brand) ?> → Untertitel</b> zeigt alle Videos ohne Untertitel, Audio ohne Transkript, offene Entwürfe und laufende Aufträge.</p>
  <h3>Der Bereich „<?= e($__brand) ?>“</h3>
  <p>Im Hauptmenü bündelt <b><?= e($__brand) ?></b> alles an einem Ort. Sie sehen nur, was Ihre Rolle nutzen darf:</p>
  <table class="doc-table">
    <tr><th>Bereich</th><th>Wofür</th></tr>
    <tr><td>Übersicht</td><td>Status der KI, verbleibende Aufrufe heute, offene Aufgaben.</td></tr>
    <tr><td>Texte</td><td>Freier Schreib-Assistent; Ergebnis kopieren oder als Entwurf in eine Seite einfügen.</td></tr>
    <tr><td>Übersetzen</td><td>Fehlende Übersetzungen je Sprache (nur bei mehrsprachigen Websites).</td></tr>
    <tr><td>SEO · Alt-Texte · Untertitel</td><td>Sammel-Vorschläge wie oben beschrieben.</td></tr>
    <tr><td>Seiten-Generator</td><td>Thema, Ziel und Fakten beschreiben – die KI entwirft eine Seite aus den Blöcken Ihres Designs. Sie ändern, sortieren oder entfernen Abschnitte und legen die Seite als <b>Entwurf</b> an.</td></tr>
    <tr><td>Tabellen-Generator</td><td>Nur mit dem Recht „Tabellen und Felder ändern“: Die KI entwirft eine Datentabelle mit passenden Feldtypen; Sie prüfen die Felder und legen die Tabelle an. Beispieleinträge nur auf Wunsch – als Entwurf und deutlich als „Beispiel – bitte ersetzen“ markiert.</td></tr>
    <tr><td>Eingereicht</td><td>Vorschläge von KI-Assistenten, REST-API und MCP, die auf Freigabe warten (siehe <a href="#<?= e($anchor('ki')) ?>">nächstes Kapitel</a>).</td></tr>
    <tr><td>Verlauf</td><td>Ihre eigenen KI-Vorschläge der letzten 90 Tage – ohne Inhalte.</td></tr>
    <tr><td>Einstellungen</td><td>Überblick über Anbieter und Schalter; ändern lässt sich das unter Grundeinstellungen → KI.</td></tr>
  </table>
  <h3 id="ki-einrichten">Einrichten: Verbindungen und Verwendung (Administration)</h3>
  <p>Unter <b>Grundeinstellungen → KI</b> ist alles in aufklappbaren Abschnitten geordnet: <b>Übersicht</b> (je Zweck Verbindung, Modell, Status, „Verbindung testen“), <b>Verbindungen</b>, <b>Verwendung</b>, danach die Schalter dieser Website, der KI-Assistent, der Besucher-Chat, Datenschutz und Nutzung.</p>
  <ol>
    <li><b>Verbindung hinzufügen</b> (nur Agentur bzw. Netzwerk-Administration): Bezeichnung (z. B. „Ollama Büro“), Anbieter, Adresse – bei Ollama z. B. <code>http://localhost:11434</code> oder die Adresse Ihres Servers – und, falls nötig, API-Schlüssel bzw. Token. Mit <b>Verbindung prüfen</b> sehen Sie sofort, ob alles stimmt: „Verbindung in Ordnung“ mit Version, Anzahl der Modelle und Dauer, sonst „Schlüssel falsch“, „nicht erreichbar“ oder „falsche Art“. Gespeicherte Schlüssel werden nie wieder angezeigt; leer lassen = unverändert, „-“ löscht ihn.</li>
    <li><b>Modelle anzeigen</b> listet die Modelle der Verbindung mit Größe, Parametern, Quantisierung, Kontextlänge und Eignung (Text, Bilder, Embeddings, Audio) – mit Filter. <b>Übernehmen</b> trägt das Modell für den gewählten Zweck ein.</li>
    <li>Unter <b>Verwendung</b> wählen Sie je Zweck – Texte &amp; Redaktion, Besucher-Chat, Embeddings (Suche), Bilder (Alt-Texte), Sprache → Text – die Verbindung und mit <b>Modelle …</b> das Modell per Klick (oder tippen den Namen ein). Optional springt eine <b>Ersatz-Verbindung</b> ein, wenn die erste nicht antwortet. Dann <b>Verwendung speichern</b>.</li>
  </ol>
  <p>So lassen sich Anbieter mischen, z. B. Texte und Bilder über den eigenen Ollama-Server, der Besucher-Chat über einen EU-Anbieter. Ein Statuspunkt zeigt je Verbindung das letzte Prüfergebnis. Ein neues Embedding-Modell bedeutet: Der Suchindex wird neu berechnet – bis dahin findet die semantische Suche weniger. Bereiche, die in einer Konfigurationsdatei festgelegt sind, sind gesperrt.</p>
  <h3>Weitere Helfer</h3>
  <ul>
    <li><b>Support-Team</b>: ✦ Antwortvorschlag in einer Meldung (stützt sich auf den Verlauf und passende Wissensartikel), ✦ Mit KI überarbeiten beim Wissensartikel (entfernt Namen und Kontaktdaten).</li>
    <li><b>Daten → Neue Tabelle</b>: ✦ Felder vorschlagen aus einer kurzen Beschreibung – Sie wählen aus, was hinzugefügt wird.</li>
  </ul>
  <div class="doc-note doc-note--warn"><strong>Ihre Prüfpflicht</strong><p>Lesen Sie jeden Vorschlag vollständig, bevor Sie ihn übernehmen – besonders Zahlen, Namen, Termine, medizinische oder rechtliche Aussagen. Platzhalter <code>[bitte ergänzen: …]</code> müssen vor dem Veröffentlichen ersetzt oder gelöscht werden.</p></div>
  <div class="doc-note"><strong>Datenschutz</strong><p>Texte, Bilder und Tonspuren gehen nur an den KI-Anbieter, wenn Sie eine KI-Funktion auslösen. Läuft die KI auf einem eigenen Server (z. B. Ollama, whisper.cpp), verlassen sie die Infrastruktur nicht. Geben Sie keine Patientendaten oder andere besonders schützenswerte Daten in Aufträge ein. Protokolliert werden nur Zähler (Anzahl, Dauer), nie Inhalte. Je Website kann ein Tageslimit gelten; die Anzeige „Heute noch n KI-Aufrufe“ steht in jedem KI-Fenster.</p></div>
