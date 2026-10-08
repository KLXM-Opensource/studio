<?php /** Handbuch · Kapitel „Grundeinstellungen (Administration)“ – technische Einstellungen der Website unter /admin/system */ ?>
  <p class="lead">Unter <b>System → Grundeinstellungen</b> stehen die technischen Einstellungen der Website – aufgebaut wie die Systemeinstellungen am Mac: links die Bereiche, rechts die Einstellungen in Gruppen. Sichtbar für die Administration (Recht „Grundeinstellungen“).</p>

  <h3 id="ge-bereiche">Die Bereiche</h3>
  <ul>
    <li><b>Allgemein, Indexierung &amp; Crawler, App-Icon &amp; PWA, Karten &amp; externe Quellen, Sprachen, E-Mail-Versand, Spamschutz, Suche, KI</b> – jede Einstellung erklärt sich darunter.</li>
    <li><b>Verschlüsselung:</b> den <b>Schlüssel für verschlüsselte Anfragen</b> erzeugen. Der geheime Teil wird nur <b>einmal</b> angezeigt – sofort im Passwortmanager ablegen. Ohne Schlüssel nehmen verschlüsselte Formulare nichts an (siehe <a href="<?= e(url('/admin/hilfe#verschluesselung')) ?>">Eigene Daten → Verschlüsselung</a>).</li>
    <li><b>Mediathek:</b> wie lange gelöschte Dateien im <b>Papierkorb</b> bleiben (oder „Aus“), ob <b>Bildrechte und Einwilligungen</b> ausgeblendet, optional oder Pflicht sind, und ab welcher Größe eine Datei als „zu groß“ gilt.</li>
    <li><b>Geteilte Medien</b> und <b>Geteilte Daten</b> – Bereiche, die mehrere Websites gemeinsam nutzen.</li>
    <li><b>Umgebung</b> – Livebetrieb oder Testumgebung (siehe unten).</li>
    <li><b>Adresse der Verwaltung</b> – statt <code>/admin</code> eine eigene, schwer zu erratende Adresse.</li>
    <li><b>Systeminfo</b> – Version, PHP, Speicher, Cache leeren, Testmail.</li>
    <li>Eigene Unterseiten: <b>Schriften</b> (Schriften vom eigenen Server verwalten) und <b>Kits</b> (Designs installieren und aktualisieren).</li>
  </ul>

  <h3 id="domain">Domain und Hauptadresse</h3>
  <p>Bei einer einzelnen Website (ohne Netzwerk) öffnet oben rechts der Knopf <b>Domain</b> die Liste der Adressen dieser Website:</p>
  <ol class="doc-steps">
    <li><b>weitere Domain</b> eintragen (z. B. <code>www.beispiel.de</code> neben <code>beispiel.de</code>) – die Domain muss beim Hoster auf diese Website zeigen.</li>
    <li><b>Erreichbarkeit prüfen</b> zeigt, ob die Domain hier ankommt (mit HTTPS oder nur HTTP).</li>
    <li><b>Als Hauptadresse festlegen</b>: Unter dieser Adresse erscheint die Website, alle anderen leiten dorthin weiter. Die gerade aufgerufene Adresse und die Hauptadresse lassen sich nicht entfernen.</li>
  </ol>
  <p>Gehört die Website zu einem Netzwerk, verwaltet die Netzwerk-Administration die Domains.</p>

  <h3 id="umgebung">Testumgebung (Staging) und Livebetrieb</h3>
  <ul>
    <li><b>Testumgebung (staging):</b> Suchmaschinen sind ausgesperrt, E-Mails gehen nicht an echte Empfänger, Push-Mitteilungen nicht an Besucher; auf der Website steht unten ein Hinweisbalken. Gut für den Aufbau einer neuen Website.</li>
    <li><b>Livebetrieb (production):</b> alles läuft normal. Umstellen unter <b>Umgebung → Umstellen</b> (Administration und Netzwerk-Administration).</li>
    <li>Solange die Testumgebung aktiv ist, <b>pulsiert ein oranger Punkt</b> an „Grundeinstellungen“ und an der Gruppe „System“ im Menü – damit niemand vergisst, sie vor dem Start aufzuheben.</li>
  </ul>

  <h3 id="warnungen">Warnpunkte im Menü</h3>
  <p>Ein oranger Punkt an einem Menüpunkt der Einrichtung weist auf etwas Wichtiges hin – derzeit die <b>aktive Testumgebung</b> oder einen <b>fehlenden Schlüssel</b> für verschlüsselte Anfragen. Ist die Gruppe zugeklappt, zeigt sie den Punkt mit; beim Darüberfahren steht der Grund daneben. Erledigt man die Sache, verschwindet der Punkt.</p>
