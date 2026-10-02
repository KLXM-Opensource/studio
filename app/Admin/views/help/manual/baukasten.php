<?php /** Handbuch · Kapitel „Eigene Blöcke bauen“ (Block-Designer, Core\Blocks\Custom) – für Administration und Agentur */ ?>
  <p class="lead">Unter <b>Verwaltung → Blöcke</b> bauen Administration und Agentur eigene Blöcke – z. B. eine Teamkarte, einen Ablauf oder eine Hinweis-Box. Nach der Freigabe stehen sie der Redaktion beim Bearbeiten der Seiten neben den Blöcken des Kits zur Verfügung. Ohne Programmieren im engeren Sinn, aber mit etwas HTML und CSS. Recht: <i>Eigene Blöcke bauen und freigeben</i>; die Funktion kann je Website abgeschaltet sein.</p>
  <h3>In fünf Schritten</h3>
  <ol>
    <li><b>Felder</b> anlegen: Was pflegt die Redaktion? Text, formatierter Text, Bild, Datei, Link, Auswahl, Ja/Nein, Zahl, Datum, Symbol oder eine <b>Liste</b> (wiederholbare Einträge mit Unterfeldern, z. B. „Schritte“ mit Titel und Text). Der <i>Kurzname</i> eines Felds wird in der Vorlage verwendet.</li>
    <li><b>Vorlage</b> schreiben: HTML mit Platzhaltern wie <code>{{ title }}</code>. Ein Klick auf ein Feld über dem Editor fügt den passenden Platzhalter ein, bei Listen gleich die ganze Schleife. Fehler zeigt der Block-Designer sofort mit Zeilennummer an.</li>
    <li><b>CSS</b> ergänzen: Die Regeln gelten nur innerhalb des Blocks. Mit den Farb-Variablen (<code>var(--cb-accent)</code> …) passt sich der Block an Design und dunkle Abschnitte an.</li>
    <li><b>Vorschau</b> prüfen: rechts die Startseite des aktiven Kits mit dem Block und Beispieldaten – Desktop/Mobil, verschiedene Hintergründe, helles/dunkles Farbschema. Die Beispieldaten ändern Sie im Reiter „Beispieldaten“.</li>
    <li><b>Speichern &amp; für Redaktion freigeben</b>. Erst dann erscheint der Block in der Block-Auswahl. Spätere Änderungen bleiben Entwurf, bis Sie erneut freigeben.</li>
  </ol>
  <h3>Mit den Beispielen lernen</h3>
  <p>Unter <b>Verwaltung → Blöcke → Beispiele</b> liegen sieben fertige Blöcke, die Schritt für Schritt zeigen, wie der Block-Designer funktioniert. Jede Karte zeigt eine Vorschau im aktiven Kit und nennt, was das Beispiel zeigt. <b>„Als Vorlage übernehmen“</b> legt eine Kopie als Entwurf an – mit eindeutigem Kurznamen (z. B. <code>hinweisbox_2</code>), bestehende Blöcke bleiben unberührt. Im Block-Designer erklärt der Kasten <i>„Was zeigt dieses Beispiel?“</i> und je Reiter ein Hinweis, worauf es ankommt; „Hinweise ausblenden“ blendet sie für diesen Block aus.</p>
  <table class="doc-table">
    <tr><th>Beispiel</th><th>Was es zeigt</th></tr>
    <tr><td>1 · Hinweisbox</td><td>Felder und ein <b>Auswahlfeld</b>: Die Art (Information, Achtung, Erledigt) wird zur CSS-Klasse und wählt mit <code>{% if %}</code> das Symbol.</td></tr>
    <tr><td>2 · Zitat mit Bild</td><td>Ein <b>Bildfeld</b> mit <code>{{ bild | image('4.5rem', '1:1') }}</code>; der Alt-Text kommt aus der Mediathek.</td></tr>
    <tr><td>3 · Kennzahlen</td><td>Eine <b>Liste</b> mit <code>{% for %}</code>; <code>number</code> formatiert Zahlen in der Sprache der Seite.</td></tr>
    <tr><td>4 · Ablauf / Schritte</td><td>Ein <b>Symbolfeld</b> und die Nummerierung mit <code>loop.index</code>; untereinander am Handy, nebeneinander am Desktop.</td></tr>
    <tr><td>5 · Ansprechpartner-Karte</td><td><b>Links</b>: Telefon mit <code>tel</code>, E-Mail mit <code>mailto</code>, eine Seite über ein Linkfeld; Text nur für Screenreader.</td></tr>
    <tr><td>6 · FAQ / Aufklappliste</td><td>Barrierefreies Aufklappen mit <code>&lt;details&gt;</code>/<code>&lt;summary&gt;</code> und FAQ-Daten für Suchmaschinen.</td></tr>
    <tr><td>7 · Termin-Hinweis</td><td>Ein <b>Datumsfeld</b> mit <code>date('long')</code> und eine Schaltfläche in den Farben des Kits.</td></tr>
  </table>
  <p><b>So gehen Sie vor:</b> „Hinweisbox“ übernehmen → im Reiter <i>Beispieldaten</i> die Art auf „Achtung“ stellen und in der Vorschau beobachten → im Reiter <i>CSS</i> eine Farbe ändern → <b>Speichern &amp; für Redaktion freigeben</b>. Danach eine Seite bearbeiten, „+ Block einfügen“ wählen und den Block aus der Gruppe <i>Beispiele</i> einfügen: Überschriften schreiben Sie direkt auf der Seite, die Art des Hinweises und den Text in der Seitenleiste („Bearbeiten“). Bezeichnung, Gruppe und alles andere dürfen Sie frei ändern – so wird aus dem Beispiel Ihr eigener Block.</p>
  <h3>Mit <?= e(\Core\AI\Assist::brand()) ?></h3>
  <p>„Block generieren“ erstellt aus einer Beschreibung einen Vorschlag für Felder, Vorlage und CSS. Der Vorschlag füllt nur das Formular – gespeichert und freigegeben wird nichts automatisch. Prüfen Sie ihn in der Vorschau; Beispieltexte sind als „Beispiel“ markiert.</p>
  <h3>Verlauf, Zurückziehen, Weitergeben</h3>
  <ul>
    <li><b>Verlauf:</b> Jede gespeicherte Fassung lässt sich wiederherstellen (als Entwurf).</li>
    <li><b>Zurückziehen:</b> Der Block lässt sich nicht mehr neu einfügen, bestehende Seiten zeigen ihn weiter. <b>Löschen</b> geht erst, wenn keine Seite ihn mehr verwendet.</li>
    <li><b>Kurznamen</b> freigegebener Felder sind fest, weil die Inhalte der Seiten daran hängen. Felder, die Sie entfernen, verlieren ihre Inhalte nach der nächsten Freigabe.</li>
    <li><b>Export/Import:</b> „JSON exportieren“ und unter „Blöcke → Importieren“ auf einer anderen Website einlesen. Netzwerk-Administration und Agentur können Blöcke zusätzlich in die <b>Netzwerk-Bibliothek</b> kopieren, aus der jede Website eine Kopie übernimmt.</li>
    <li><b>Als Kit-Block exportieren</b> erzeugt ein ZIP für Agenturen (PHP-Renderer, CSS, Ausschnitt für <code>theme.php</code>) – Details im Entwicklerhandbuch.</li>
  </ul>
  <p>Sicherheit: Vorlagen enthalten nie PHP oder JavaScript; alle Inhalte werden geschützt ausgegeben, unsichere Links werden zu „#“. Referenz der Vorlagensprache: <a href="<?= e(url('/admin/hilfe/technik#bloecke')) ?>">Entwicklerhandbuch → Eigene Blöcke</a>.</p>
