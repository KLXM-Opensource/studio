<?php /** Handbuch · Kapitel „Eigene Blöcke bauen“ (Block-Baukasten, Core\Blocks\Custom) – für Administration und Agentur */ ?>
  <p class="lead">Unter <b>Verwaltung → Blöcke</b> bauen Administration und Agentur eigene Blöcke – z. B. eine Preistabelle, eine Teamkarte oder eine Hinweis-Box. Nach der Freigabe stehen sie der Redaktion beim Bearbeiten der Seiten neben den Blöcken des Kits zur Verfügung. Ohne Programmieren im engeren Sinn, aber mit etwas HTML und CSS. Recht: <i>Eigene Blöcke bauen und freigeben</i>; die Funktion kann je Website abgeschaltet sein.</p>
  <h3>In fünf Schritten</h3>
  <ol>
    <li><b>Felder</b> anlegen: Was pflegt die Redaktion? Text, formatierter Text, Bild, Datei, Link, Auswahl, Ja/Nein, Zahl, Datum, Symbol oder eine <b>Liste</b> (wiederholbare Einträge mit Unterfeldern, z. B. „Pakete“ mit Name und Preis). Der <i>Kurzname</i> eines Felds wird in der Vorlage verwendet.</li>
    <li><b>Vorlage</b> schreiben: HTML mit Platzhaltern wie <code>{{ title }}</code>. Ein Klick auf ein Feld über dem Editor fügt den passenden Platzhalter ein, bei Listen gleich die ganze Schleife. Fehler zeigt der Baukasten sofort mit Zeilennummer an.</li>
    <li><b>CSS</b> ergänzen: Die Regeln gelten nur innerhalb des Blocks. Mit den Farb-Variablen (<code>var(--cb-accent)</code> …) passt sich der Block an Design und dunkle Abschnitte an.</li>
    <li><b>Vorschau</b> prüfen: rechts die Startseite des aktiven Kits mit dem Block und Beispieldaten – Desktop/Mobil, verschiedene Hintergründe, helles/dunkles Farbschema. Die Beispieldaten ändern Sie im Reiter „Beispieldaten“.</li>
    <li><b>Speichern &amp; für Redaktion freigeben</b>. Erst dann erscheint der Block in der Block-Auswahl. Spätere Änderungen bleiben Entwurf, bis Sie erneut freigeben.</li>
  </ol>
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
