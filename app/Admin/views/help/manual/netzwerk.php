<?php /** Handbuch · Kapitel „Netzwerk-Verwaltung“ – eigener Bereich für alles, was die ganze Installation betrifft (Core\Network\Area) */ ?>
  <p class="lead">Laufen mehrere Websites in einer Installation, gibt es zwei klar getrennte Bereiche: die <b>Verwaltung jeder Website</b> – das gewohnte CMS mit Seiten, Medien, Daten, Benutzern und Grundeinstellungen – und den <b>Netzwerk-Bereich</b> für alles, was für die ganze Installation gilt. Den Netzwerk-Bereich sehen nur Konten mit der Rolle <b>Netzwerk-Administration</b>.</p>

  <h3 id="netzwerk-erkennen">So erkennen Sie, wo Sie sind</h3>
  <ul>
    <li><b>Netzwerk-Bereich:</b> dunkler Kopf mit dem gelben Kennzeichen <b>NETZWERK</b> und dem Satz „Einstellungen für die ganze Installation – gelten für alle … Websites“, dazu eine eigene, fast schwarze Seitenleiste.</li>
    <li><b>Verwaltung einer Website:</b> die gewohnte Seitenleiste in der Farbe der Verwaltung – ohne Netzwerk-Punkte.</li>
    <li><b>Umschalter</b> oben in jeder Seitenleiste (nur für Netzwerk-Konten): links <b>Netzwerk</b>, rechts <b>Website</b> mit der Liste aller Websites. Das aktive Feld ist hervorgehoben. Eine andere Website öffnen Sie mit einem Klick – Sie werden dort automatisch angemeldet und haben die vollen Rechte der Website.</li>
  </ul>

  <h3 id="netzwerk-was-wo">Was liegt wo?</h3>
  <table class="doc-table">
    <tr><th>Netzwerk-Bereich (ganze Installation)</th><th>Verwaltung jeder Website (nur diese Website)</th></tr>
    <tr><td><b>Websites</b> – Übersicht, neue Website, Name, Domains, Wartung, Testumgebung, Sicherung je Website</td><td>Übersicht, Seiten, Entwürfe, Medien, Daten, Anfragen, KI, Chat, Mitteilungen</td></tr>
    <tr><td><b>Administratoren</b> – Netzwerk-Konten, Einladungen, Sperren, 2FA zurücksetzen</td><td>Benutzer &amp; Rollen der Website (Netzwerk-Konten erscheinen dort nur als Zeile „wird im Netzwerk verwaltet“)</td></tr>
    <tr><td><b>Anmeldung &amp; Domain</b> – Anmelde-Richtlinie der Netzwerk-Konten, Netzwerk-Domain, Adresse der Verwaltung, Agentur-Zugang per OpenID Connect</td><td>Benutzer &amp; Rollen → Sicherheit: Zwei-Faktor-Regeln und ein eigener Anmelde-Anbieter für die Redaktion der Website</td></tr>
    <tr><td><b>E-Mail</b> – Netzwerk-Konto (Absender der Installation), <b>Systemmeldungen an</b>, Übersicht aller Websites</td><td>Grundeinstellungen → E-Mail-Versand: Konten, Absender je Zweck, Empfänger, Test und Umleitung, Impressum</td></tr>
    <tr><td><b>Kits</b>, <b>Schriften</b>, <b>Branding &amp; Support</b></td><td>Grundeinstellungen → Allgemein: welches Kit die Website nutzt; Einstellungen → Gestaltung</td></tr>
    <tr><td><b>Geteilte Medien &amp; Daten</b> – Medien-Pools anlegen und zuordnen, Überblick geteilter Tabellen</td><td>Mediathek (Umschaltung auf geteilte Medien), Grundeinstellungen → Geteilte Daten (Tabellen, an denen die Website teilnimmt)</td></tr>
    <tr><td><b>KI-Verbindungen</b> – Anbieter, Schlüssel, Modelle, Verwendung</td><td>Grundeinstellungen → KI: ob die Website KI nutzt, Datenschutz, Besucher-Chat</td></tr>
    <tr><td><b>Geplante Aufgaben</b>, <b>Sicherung außer Haus</b>, <b>Integrität der Dateien</b>, <b>Speicher</b>, <b>Protokoll</b></td><td>Grundeinstellungen → Integrität (Datenbank der Website), Überwachung, Umgebung, Systeminfo</td></tr>
  </table>
  <p>Lesezeichen und Favoriten auf die früheren Adressen (z. B. <code>/admin/system/aufgaben</code>, <code>/admin/system/kits</code>, <code>/admin/system/fonts</code>) funktionieren weiter: Sie landen automatisch auf der neuen Seite im Netzwerk-Bereich.</p>

  <h3 id="netzwerk-rechte">Wer darf was?</h3>
  <ul>
    <li><b>Netzwerk-Administration</b> (Rolle „network“): alles im Netzwerk-Bereich – und auf jeder Website die vollen Rechte wie bisher.</li>
    <li><b>Administration einer Website</b> (auch der Website, auf der das Netzwerk liegt): nur ihre Website. Installationsweite Seiten sieht sie nicht.</li>
    <li><b>Einzelinstallation</b> (nur eine Website): kein Netzwerk-Bereich – Geplante Aufgaben, Sicherung, Kits, Schriften, Geteilte Medien und Adresse der Verwaltung bleiben in den Grundeinstellungen.</li>
  </ul>

  <h3 id="systemmeldungen">Systemmeldungen</h3>
  <p>Unter <b>Netzwerk → E-Mail → Systemmeldungen an</b> tragen Sie ein, wer Meldungen der ganzen Installation bekommt: unerwartete Änderungen an den Dateien (Integrität), eine fehlgeschlagene Sicherung außer Haus und Fehler in Geplanten Aufgaben – je Fehler einmal, nach der nächsten erfolgreichen Ausführung wieder. Bleibt das Feld leer, gehen die Meldungen an alle aktiven Netzwerk-Konten. Hinweise zur <b>Datenbank</b> einer Website (z. B. ein neues Administrator-Konto) bekommt deren eigene Administration – nicht die Netzwerk-Konten.</p>
  <p>Mit mehreren Websites braucht das Netzwerk ein <b>Netzwerk-Konto</b> für E-Mails (Netzwerk → E-Mail). Fehlt es, meldet die Systemprüfung einen Fehler.</p>
