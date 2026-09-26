<?php /** Handbuch · Kapitel „Seiten verwalten“ · @var callable $anchor */ ?>
  <p class="lead">Die <a href="<?= e(url('/admin/pages')) ?>">Seitenübersicht</a> funktioniert wie die Listenansicht im Finder: Seiten lassen sich beliebig verschachteln – z. B. <code>/leistungen/vorsorge</code>.</p>
  <ol class="doc-steps">
    <li><b>+ Neue Seite</b> (oder Rechtsklick auf eine Seite → <b>Neue Unterseite</b>). Titel eingeben – die Adresse entsteht automatisch, bei Unterseiten mit dem Pfad der übergeordneten Seite davor.</li>
    <li>Status <b>Entwurf</b> lassen, bis die Seite fertig ist. Nach dem Anlegen öffnet sich der Editor.</li>
    <li>Blöcke einfügen, Inhalte schreiben, <b>Veröffentlichen</b>.</li>
  </ol>
  <table class="doc-table">
    <tr><th>Aktion</th><th>So geht’s</th></tr>
    <tr><td>Ordnen</td><td>Seite <b>ziehen</b>: auf eine andere Seite = wird deren Unterseite (blauer Rahmen), zwischen zwei Seiten = neue Reihenfolge (blaue Linie). Die Adressen passen sich automatisch an; alte Einzeladressen leiten weiter.</td></tr>
    <tr><td>Auf- und zuklappen</td><td>Dreieck vor der Seite oder <kbd>→</kbd>/<kbd>←</kbd>. „Alle aufklappen/zuklappen“ oben.</td></tr>
    <tr><td>Öffnen</td><td>Doppelklick oder <kbd>Enter</kbd> öffnet den Editor.</td></tr>
    <tr><td>Weitere Aktionen</td><td>Rechtsklick oder <b><?= icon('dots-three', ['label' => 'Mehr']) ?></b>: Seiteneinstellungen, Ansehen, Neue Unterseite, Duplizieren, Änderungen veröffentlichen, Löschen (Unterseiten rücken dann eine Ebene nach oben).</td></tr>
    <tr><td>Menü</td><td>Schalter in der Spalte <b>Menü</b>: Seite erscheint im Hauptmenü, Unterseiten als Aufklappmenü.</td></tr>
  </table>
  <table class="doc-table">
    <tr><th>Einstellung</th><th>Bedeutung</th></tr>
    <tr><td>Übergeordnete Seite</td><td>Legt die Ebene fest (auch per Ziehen änderbar).</td></tr>
    <tr><td>Adresse (URL)</td><td>Entsteht aus dem Titel; bei veröffentlichten Seiten nur mit Bedacht ändern – alte Links leiten dann weiter, externe Verweise sollten angepasst werden.</td></tr>
    <tr><td>Status</td><td><b>Entwurf</b> = nur für Angemeldete sichtbar, <b>Online</b> = öffentlich.</td></tr>
    <tr><td>Im Hauptmenü zeigen, Beschriftung</td><td>Menüeintrag an/aus und ein kürzerer Menütext.</td></tr>
    <tr><td>Titel für Suchmaschinen</td><td>Optional ein eigener Titel für Google &amp; Co., wenn der Seitentitel dafür zu kurz oder zu lang ist.</td></tr>
    <tr><td>Beschreibung für Suchmaschinen</td><td>Der kurze Text unter dem Link bei Google (120–160 Zeichen). Der <b>SEO-Check</b> prüft Titel, Beschreibung, Überschriften und Alt-Texte.</td></tr>
    <tr><td>Vorschaubild</td><td>Bild, das soziale Netzwerke und Messenger beim Teilen der Seite zeigen.</td></tr>
    <tr><td>Nicht in Suchmaschinen</td><td>Für Seiten, die nur per Link erreichbar sein sollen (z. B. eine Beispielseite mit allen Blöcken).</td></tr>
    <tr><td>Versionen</td><td>Die letzten <?= (int) app()->config->get('revisions', 20) ?> gespeicherten Stände – „Wiederherstellen“ legt einen Entwurf an.</td></tr>
  </table>
  <div class="doc-note doc-note--important"><strong>Rechtstexte</strong><p>Impressum, Datenschutz und Barrierefreiheit enthalten Platzhalter. Bitte ausschließlich mit geprüften Texten (z. B. von Kammer, Verband, Rechtsberatung oder Datenschutzbeauftragten) füllen.</p></div>
  <h3 id="sprachen">Mehrere Sprachen</h3>
  <p>Sind in den Grundeinstellungen weitere Sprachen aktiviert, zeigt die Seitenübersicht oben Reiter je Sprache. Rechtsklick auf eine Seite → <b>Übersetzung anlegen: English</b> erstellt eine verknüpfte Kopie als Entwurf unter <code>/en/…</code> – Texte übersetzen, veröffentlichen, fertig. Die Kürzel <b>DE EN</b> neben dem Titel zeigen, welche Fassungen es gibt. Einträge unter <b>Daten</b> übersetzen Sie im Eintrag rechts über „+ English“. Feste Texte des Designs (z. B. „Kontakt“, Wochentage) übersetzt die Website selbst, sofern das Kit sie mitbringt; die übersetzbaren Angaben der zentralen Einstellungen pflegen Sie je Sprache. Mit eingeschalteter KI hilft <b>✦ Aus Deutsch übersetzen</b> (siehe <a href="#<?= e($anchor('assistent')) ?>">KI-Kapitel</a>).</p>
