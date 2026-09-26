<?php /** Handbuch · Kapitel „KLXM Check“ (Erweiterung klxm_check, eingebunden über Extension::docs('manual')) */ ?>
  <p class="lead">Mit <b>KLXM Check</b> prüfen Besucher Ihrer Website eine Domain: Ist SPF richtig, schützt DMARC, gibt es DKIM-Schlüssel, sind Mailserver und Reverse-DNS sauber, wie sicher ist die Website (HTTPS, Sicherheits-Header) und ist das Zertifikat gültig? Dazu gibt es einen <b>SPF-</b> und einen <b>DMARC-Generator</b> mit Schritt-für-Schritt-Anleitung.</p>

  <h3>Auf einer Seite einfügen</h3>
  <ol class="doc-steps">
    <li>Seite bearbeiten → <b>Block hinzufügen</b> → Gruppe <b>Werkzeuge</b> → <b>KLXM Check</b>.</li>
    <li>Optional <b>Überschrift</b> und <b>Einleitung</b> eintragen, den <b>Start-Reiter</b> wählen (Domain-Analyse, SSL/TLS-Checker, SPF- oder DMARC-Generator) und festlegen, welche Werkzeuge angeboten werden.</li>
    <li><b>Einsteiger-Anleitungen</b> (SPF, DMARC, Vorgehen) lassen sich ein- und ausblenden.</li>
    <li>Veröffentlichen. Das Werkzeug übernimmt Schrift und Farben des Kits und funktioniert in hellen und dunklen Abschnitten.</li>
  </ol>
  <p>Ohne Block geht es auch: Unter <a href="<?= e(url('/admin/klxm-check')) ?>">Administration → KLXM Check</a> lässt sich eine eigene Seite <code>/check</code> einschalten.</p>

  <h3>Links teilen</h3>
  <p>Nach einer Analyse steht die Domain in der Adresse (<code>?domain=beispiel.de</code>) – „Link kopieren“ legt sie in die Zwischenablage. <code>?tab=ssl-checker</code>, <code>?tab=spf-generator</code> und <code>?tab=dmarc-generator</code> öffnen direkt den passenden Reiter (wie beim bisherigen Werkzeug auf klxm.de). <kbd>⌘</kbd>/<kbd>Strg</kbd> + <kbd>K</kbd> springt in das Eingabefeld.</p>

  <h3>Gut zu wissen</h3>
  <ul>
    <li>Die Prüfungen laufen auf dem Server Ihrer Website. Jeder Besucher darf nur eine begrenzte Zahl von Prüfungen pro Minute und Tag starten.</li>
    <li>Abgefragte Domains werden nicht gespeichert; Ergebnisse werden nur wenige Minuten zwischengespeichert. Gezählt wird nur, <em>wie oft</em> welche Prüfung lief (Übersicht, Kachel „KLXM Check“).</li>
    <li>Meldet die Mailserver-Prüfung „Port 25 nicht erreichbar“, sperrt meist Ihr Hoster ausgehende Verbindungen auf diesem Port – das ist kein Fehler der geprüften Domain.</li>
    <li>Alle Angaben ohne Gewähr: Erzeugte Einträge sollten vor dem Eintragen ins DNS geprüft werden.</li>
  </ul>
