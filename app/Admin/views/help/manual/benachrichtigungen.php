<?php /** Handbuch · Kapitel „Push-Benachrichtigungen“ (Core\Push; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Mit <b>Push-Benachrichtigungen</b> meldet sich die Website auf Ihrem Telefon oder Computer – auch wenn der Browser-Tab geschlossen ist: bei neuen Online-Anfragen, Chat-Nachrichten oder Einreichungen zur Freigabe. Besucher können außerdem neue Einträge einer Datentabelle abonnieren, z. B. „Aktuelles“. Die Funktion ist optional; Ihre Agentur bzw. Netzwerk-Administration schaltet sie je Website ein.</p>
  <h3 id="push-konto">Für Sie: Konto → Benachrichtigungen</h3>
  <ol class="doc-steps">
    <li><b>Gerät anmelden:</b> unten links auf Ihren Namen bzw. <b>Konto</b>, Abschnitt <b>Benachrichtigungen</b>, Schalter <b>„Mitteilungen auf diesem Gerät“</b> einschalten. Erst jetzt fragt der Browser, ob die Website Mitteilungen senden darf – mit <b>Erlauben</b> bestätigen.</li>
    <li><b>Auswählen, worüber:</b> Unter <b>„Worüber?“</b> schalten Sie einzelne Anlässe an oder aus. Sie sehen nur, was Ihre Rolle auch in der Verwaltung sieht (z. B. Anfragen nur mit dem Recht, Anfragen zu lesen). Die Auswahl gilt für alle Ihre Geräte und wird sofort gespeichert.</li>
    <li><b>Weitere Geräte:</b> Auf dem Telefon die Verwaltung öffnen, anmelden und den Schalter dort ebenfalls einschalten. Unter <b>„Angemeldete Geräte“</b> sehen Sie alle Geräte; <b>Entfernen</b> meldet ein Gerät ab (z. B. ein altes Telefon).</li>
    <li><b>Ausprobieren:</b> <b>„Testnachricht an meine Geräte“</b> schickt sofort eine Mitteilung. Ein Tipp darauf öffnet die passende Stelle in der Verwaltung.</li>
  </ol>
  <ul>
    <li><b>Anfragen</b> kommen ohne Inhalt – die Mitteilung sagt nur, in welchem Eingang etwas angekommen ist. Lesen können Sie Anfragen weiterhin nur in der Verwaltung.</li>
    <li><b>Chat:</b> Direktnachrichten und Erwähnungen (@Name). Solange die Verwaltung in einem sichtbaren Fenster offen ist, kommt keine zusätzliche Mitteilung – der Chat zeigt dann seine eigenen Hinweise (Glocke im Chat).</li>
    <li><b>Erweiterungen</b> können eigene Anlässe ergänzen, z. B. neues Feedback; sie erscheinen in derselben Liste.</li>
    <li><b>iPhone und iPad:</b> Mitteilungen gibt es dort nur in der installierten Web-App: in Safari <b>Teilen → „Zum Home-Bildschirm“</b>, die Website vom Home-Bildschirm öffnen, dann wie oben einschalten.</li>
    <li><b>Kommt nichts an?</b> Steht unter dem Schalter „im Browser blockiert“, erlauben Sie Mitteilungen in den Website-Einstellungen des Browsers (Schloss-Symbol neben der Adresse) und laden die Seite neu. Auch der Fokus- bzw. „Nicht stören“-Modus des Geräts kann Mitteilungen zurückhalten. Im privaten Fenster funktionieren Push-Mitteilungen nicht.</li>
  </ul>
  <h3 id="push-cron">Einmalig für die Agentur: Cronjob einrichten</h3>
  <p>Damit Mitteilungen pünktlich ankommen – auch wenn gerade niemand die Website aufruft und bei vielen Abos –, verschickt ein <b>Cronjob</b> die Warteschlange jede Minute. Ohne Cron gehen kleine Mengen nebenbei nach Seitenaufrufen raus; das ist nur ein Notbehelf. Den fertigen Befehl (mit dem richtigen Pfad und PHP) zeigt <b>Grundeinstellungen → Push-Benachrichtigungen</b> mit Knopf <b>Kopieren</b>.</p>
  <ol class="doc-steps">
    <li><b>Plesk:</b> Websites &amp; Domains → die Domain → <b>Geplante Aufgaben</b> → <b>Aufgabe hinzufügen</b>.</li>
    <li>Aufgabentyp <b>„Befehl ausführen“</b>, Befehl einfügen, z. B. <code>cd ~/httpdocs &amp;&amp; /opt/plesk/php/8.5/bin/php bin/console push:send --all</code>.</li>
    <li>Ausführung <b>Cron-Stil</b> <code>* * * * *</code> (jede Minute; <code>*/5 * * * *</code> genügt auch). E-Mail-Benachrichtigung: nicht senden bzw. nur bei Fehlern. Speichern.</li>
    <li><b>Ohne Plesk:</b> <code>crontab -e</code> als Benutzer der Website und die Zeile <code>* * * * * cd /pfad/zur/installation &amp;&amp; php bin/console push:send --all</code> eintragen.</li>
    <li><b>Prüfen:</b> In <b>Grundeinstellungen → Push-Benachrichtigungen</b> steht nach ein, zwei Minuten bei „Letzter Versandlauf“ eine aktuelle Zeit; <code>php bin/console push:status --all</code> zeigt dasselbe auf der Kommandozeile.</li>
  </ol>
  <p>Eine Aufgabe reicht für alle Websites der Installation. Ohne Push-Funktion oder ohne Abos tut der Lauf nichts und kostet kaum Zeit.</p>
  <h3 id="push-besucher">Für Besucher: neue Einträge abonnieren</h3>
  <ol class="doc-steps">
    <li><b>Tabelle freigeben:</b> unter <b>Daten</b> die Tabelle öffnen, <b>Felder &amp; Einstellungen</b>, Kasten <b>„Benachrichtigungen (Push)“</b> → „Besucher können neue Einträge abonnieren (Push)“ anhaken. Optional: <b>Titel der Mitteilung</b> (z. B. „Neu: {title}“), woher der <b>Kurztext</b> kommt und ein <b>Filter</b> (z. B. nur Einträge der Kategorie „presse“). Speichern.</li>
    <li><b>Knopf auf die Website:</b> Block <b>„Benachrichtigungen abonnieren“</b> (Gruppe Daten) auf der Übersichtsseite oder in der Detailseiten-Vorlage einfügen und die Tabelle wählen (in der Vorlage leer lassen = Tabelle des Eintrags).</li>
    <li><b>Veröffentlichen:</b> Sobald ein Eintrag <b>zum ersten Mal</b> veröffentlicht wird, erhalten alle Abos eine Mitteilung mit Titel, Kurztext und Link zur Detailseite. Spätere Änderungen lösen nichts aus – auch nicht Zurückziehen und erneutes Veröffentlichen. Höchstens 10 Mitteilungen je Stunde und Tabelle.</li>
  </ol>
  <p>Besucher sehen zuerst eine kurze Erklärung; erst nach dem Klick auf den Knopf fragt der Browser nach der Erlaubnis. Derselbe Knopf bestellt wieder ab. Gespeichert wird nur die Zustelladresse des Browsers mit Thema und Sprache – kein Konto, keine IP-Adresse, keine Cookies. <b>Datenschutz:</b> Ergänzen Sie die Datenschutzerklärung; einen Textvorschlag finden Administratoren unter <b>Grundeinstellungen → Push-Benachrichtigungen</b>. Einträge in Sprachen gehen nur an Abos, die die Seite in dieser Sprache abonniert haben.</p>
