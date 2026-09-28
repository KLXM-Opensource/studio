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
  <h3 id="nicht-gefunden">Seite „Nicht gefunden (404)“</h3>
  <p>Ruft jemand eine Adresse auf, die es nicht gibt (Tippfehler, alter Link), zeigt die Website eine Fehlerseite. Diese Seite können Sie selbst gestalten – mit Blöcken wie jede andere Seite. Sie finden sie in der <a href="<?= e(url('/admin/pages#sonderseiten')) ?>">Seitenübersicht</a> unten unter <b>Sonderseiten</b> und unter <b>Administration → Weiterleitungen → Nicht gefunden (404)</b>.</p>
  <ol class="doc-steps">
    <li><b>404-Seite anlegen</b> – die Seite entsteht als Entwurf mit den Texten, die Ihr Design bisher gezeigt hat, und öffnet sich im Editor.</li>
    <li>Texte anpassen, Blöcke ergänzen (z. B. ein Bild oder Kontaktdaten), <b>Veröffentlichen</b>. Bis dahin sehen Besucher die bisherige Fehlerseite des Designs.</li>
    <li>Später ändern: <b>404-Seite bearbeiten</b> (oder angemeldet eine beliebige nicht vorhandene Adresse aufrufen und in der Leiste <b>Bearbeiten</b> wählen). Entwurf, Veröffentlichen und Versionen funktionieren wie gewohnt; offene Änderungen stehen auch unter „Entwürfe“.</li>
  </ol>
  <ul>
    <li>Der Block <b>404-Vorschläge</b> zeigt „Vielleicht meinten Sie …“ – Seiten (und Einträge mit Detailseite), deren Adresse der aufgerufenen ähnelt – sowie ein Suchfeld und einen Button zur Startseite. Die Vorschläge erscheinen nur bei echten Aufrufen; im Editor steht dort ein Hinweis.</li>
    <li>Die Seite hat keine eigene Adresse für Besucher: <code>/404</code> antwortet selbst mit „nicht gefunden“. Sie steht nie im Menü, in der Sitemap, in der Suche oder in der Link-Auswahl; Suchmaschinen erhalten weiterhin den Status 404 (bzw. 410 bei „entfernt“) und nehmen sie nicht auf.</li>
    <li>Mehrere Sprachen: je Sprache eine eigene Fassung („Übersetzung anlegen“) – ohne sie gilt die Seite der Standardsprache.</li>
    <li>Andere Fehler (keine Berechtigung, Serverfehler, abgelaufenes Formular) zeigen weiterhin die Fehlerseite des Designs.</li>
  </ul>
  <h3 id="notizen">Redaktionsnotizen <code>[# … #]</code></h3>
  <p>Hinweise für das Team lassen sich direkt in jeden Text schreiben – in Überschriften, Absätze, Listen, Bildunterschriften, auch in Einträge von Datentabellen: <code>[# bitte ergänzen: Seminartermine #]</code>. Eckige Klammer, Raute, Leerzeichen am Anfang, Raute und Klammer am Ende; mehrere Zeilen und mehrere Notizen je Text sind möglich.</p>
  <ul>
    <li><b>Besucher sehen Notizen nie</b> – auch nicht in der Suche, in Vorschautexten für Suchmaschinen und soziale Netzwerke, in Kalender-Abos, im KI-Chat der Website oder in einer geteilten Vorschau. Eine Seite mit Notizen lässt sich also veröffentlichen.</li>
    <li><b>Angemeldet</b> erscheinen sie im Bearbeitungsmodus und in der Entwurfsansicht als gelber Hinweis <b>„Notiz: …“</b>. Der Hinweis ist nicht wie Text bearbeitbar: <b>Erledigt?</b> Hinweis anklicken und mit <kbd>Entf</kbd> bzw. <kbd>⌫</kbd> löschen – oder den Text in der Seitenleiste ändern, dort steht die Notiz als <code>[# … #]</code>. In der <b>Live-Fassung</b> (<code>?live=1</code>) sehen Sie die Seite wie Besucher – ohne Notizen.</li>
    <li>Alle offenen Notizen stehen in der <a href="#<?= e($anchor('uebersicht')) ?>">Übersicht</a> unter „Was ist zu tun?“ – mit Sprung an die Stelle.</li>
    <li>In einer Anleitung, die die Schreibweise zeigen soll, bleibt sie stehen, wenn sie als Code formatiert oder in <code>`Backticks`</code> steht.</li>
  </ul>
  <h3 id="sprachen">Mehrere Sprachen</h3>
  <p>Sind in den Grundeinstellungen weitere Sprachen aktiviert, zeigt die Seitenübersicht oben Reiter je Sprache. Rechtsklick auf eine Seite → <b>Übersetzung anlegen: English</b> erstellt eine verknüpfte Kopie als Entwurf unter <code>/en/…</code> – Texte übersetzen, veröffentlichen, fertig. Die Kürzel <b>DE EN</b> neben dem Titel zeigen, welche Fassungen es gibt. Einträge unter <b>Daten</b> übersetzen Sie im Eintrag rechts über „+ English“. Feste Texte des Designs (z. B. „Kontakt“, Wochentage) übersetzt die Website selbst, sofern das Kit sie mitbringt; die übersetzbaren Angaben der zentralen Einstellungen pflegen Sie je Sprache. Mit eingeschalteter KI hilft <b>✦ Aus Deutsch übersetzen</b> (siehe <a href="#<?= e($anchor('assistent')) ?>">KI-Kapitel</a>).</p>
