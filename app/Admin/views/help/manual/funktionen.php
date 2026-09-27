<?php /** Handbuch · Kapitel „Funktionen & Erweiterungen“ (Core\Features, Core\Extensions) – für den Haupt-Admin bzw. die Agentur */ ?>
  <p class="lead">Unter <b>Administration → Funktionen &amp; Erweiterungen</b> legen Sie fest, was diese Website kann – von der REST-API über KI-Funktionen bis zu Erweiterungen wie den Video-Werkzeugen. Die Seite sehen nur Personen mit dem Recht <i>Funktionen &amp; Erweiterungen ein- und ausschalten</i> (in einer Einzel-Installation hat die Rolle „Administration“ es; weitere Rollen bekommen es unter <b>Benutzer &amp; Rollen</b>).</p>
  <div class="doc-note doc-note--warn"><strong>Nur einschalten, was die Website wirklich braucht</strong><p>Jede Funktion ist zusätzliche Angriffsfläche, Pflege und ggf. Datenverarbeitung. Abgeschaltete Funktionen sperren ihre Rechte, Menüpunkte, API- und MCP-Werkzeuge automatisch – auch für die Administration. Im Zweifel aus lassen und erst einschalten, wenn es konkret gebraucht wird.</p></div>
  <h3>Funktionen</h3>
  <ul>
    <li><b>Gruppen:</b> Inhalte, Daten, Medien, KI, Schnittstellen, Kommunikation, Sicherheit &amp; Betrieb. Jede Zeile hat einen Schalter, eine Kurzbeschreibung und den Status <i>an</i>, <i>aus</i> oder <i>per Konfiguration festgelegt</i>.</li>
    <li><b>„Was passiert beim Einschalten“</b> zeigt neue Menüpunkte und Rechte, Anfragen an fremde Dienste (z. B. an einen KI-Anbieter), Hintergrund-Aufgaben (Cron), Auswirkungen auf der Website (Skripte, Cookies) und welche Daten gespeichert werden.</li>
    <li><b>Abhängigkeiten:</b> Manche Funktionen bauen aufeinander auf – z. B. brauchen Kalender und Formulare die Datentabellen, der KI-Assistent und der Besucher-Chat die KI-Funktionen. Fehlt die Voraussetzung, „ruht“ die Funktion und der Schalter bleibt gesperrt.</li>
    <li><b>Sicherheitsrelevant</b> (REST-API, MCP, KI, Besucher-Chat, Externe Quellen, Erweiterungen mit Server-Prozessen wie die Video-Werkzeuge): Beim Einschalten erscheint der konkrete Risiko-Hinweis; Sie bestätigen ihn und geben Ihr Passwort ein.</li>
    <li><b>Vorsicht bei „Benutzer und Rollen“ und „Grundeinstellungen“:</b> Aus bedeutet gesperrt für alle – bis Sie den Schalter hier wieder einschalten.</li>
  </ul>
  <h3>Erweiterungen</h3>
  <ul>
    <li>Die Liste zeigt alle installierten Erweiterungen mit Version, Autor, Lizenz, Beschreibung und Voraussetzungen. Die Prüfungen laufen live – bei den Video-Werkzeugen z. B. „ffmpeg gefunden?“ und „proc_open erlaubt?“.</li>
    <li><b>Aktivieren</b> startet die Erweiterung sofort und richtet ihre Datenbank ein; Menüpunkte erscheinen ab dem nächsten Seitenaufruf. Eigene Funktionen der Erweiterung (z. B. „Video-Werkzeuge“) werden mit eingeschaltet.</li>
    <li><b>Deaktivieren</b> löscht nichts: Daten und Einstellungen bleiben, die Erweiterung wird nur nicht mehr gestartet – ihre Menüpunkte, Adressen, Befehle und Skripte auf der Website entfallen.</li>
    <li>„Fester Bestandteil“ kennzeichnet Erweiterungen, ohne die diese Website nicht funktioniert; sie lassen sich hier nicht abschalten.</li>
    <li>Erweiterungen sind eigene Pakete, die die Agentur per Composer installiert – z. B. <b>Entwurf teilen &amp; freigeben</b> (<code>klxm/studio-freigabe</code>): den Entwurf einer Seite per geheimem Link zeigen, Kommentare, Dateien und eine Freigabe einsammeln, ohne Konto für die Empfänger und ohne automatische Veröffentlichung.<?php if ($has('entwurf-teilen')): ?> Anleitung: <a href="#<?= e($anchor('entwurf-teilen')) ?>">Entwurf teilen und freigeben lassen</a>.<?php endif; ?> Eine aktive Erweiterung bringt ihr eigenes Kapitel in dieses Handbuch mit.</li>
  </ul>
  <h3>„Per Konfiguration festgelegt“</h3>
  <p>Hat die Agentur eine Funktion oder Erweiterung in der Konfigurationsdatei der Website festgelegt, gilt dieser Wert und der Schalter ist gesperrt (der Hinweis nennt die Datei). Zum Freigeben entfernt die Agentur den Eintrag oder führt auf dem Server <code>php bin/console features:release</code> aus – danach steuern Sie den Schalter hier.</p>
  <h3>Netzwerk mit mehreren Websites</h3>
  <p>Betreibt eine Agentur mehrere Websites in einer Installation, schaltet die <b>Netzwerk-Administration</b> je Website. Die Administration einer Website sieht die Seite dann nur lesend („Freischaltung durch die Agentur“), bis die Netzwerk-Administration ihr das Schalten ausdrücklich freigibt.</p>
  <h3 id="netzwerk-uebersicht">Netzwerk-Übersicht und Website-Umschalter</h3>
  <p>Netzwerk-Konten der Agentur sehen auf der Hauptwebsite unter <b>Netzwerk</b> die Übersicht aller Websites der Installation:</p>
  <ul>
    <li><b>Eine Zeile Zusammenfassung</b> oben: Websites, davon mit Hinweisen, im Wartungsmodus und nicht live, neue Anfragen, Änderungen zur Freigabe (API, MCP, KI) und Speicher gesamt. Ein Klick auf „mit Hinweisen“, „im Wartungsmodus“ oder „nicht live“ filtert die Karten. Dazu Suche und die Wahl zwischen <b>Kacheln</b> und <b>Liste</b> (merkt sich der Browser).</li>
    <li><b>Je Website eine Karte</b> mit ihrem <b>App-Icon</b> (ohne eigenes Icon: Anfangsbuchstabe in der Markenfarbe), Name, Hauptadresse (weitere Adressen unter „+n weitere Adressen“), Umgebung (Live/Staging), „noindex“ und einem <b>Status</b> in Farbe und Text: „Alles in Ordnung“, „Hinweise“, „Wartung“, „Nicht live“ oder „Störung“. Hinweise nennen konkret, was fehlt – z. B. keine Sicherung seit 7 Tagen oder offene [Platzhalter] auf einer Seite.</li>
    <li><b>Kennzahlen in drei Gruppen:</b> Inhalte (Seiten, Konten, letzte Änderung), Aktivität (neue Anfragen, Freigaben, Support – hervorgehoben nur, wenn etwas offen ist) und Betrieb (Kit, Funktionen, Speicher, letzte Sicherung). Nutzt eine Website einen <b>geteilten Medien-Pool</b>, steht er mit Größe und Zahl der beteiligten Websites dabei; der Gesamtspeicher zählt jeden Pool einmal.</li>
  </ul>
  <p>Links in der Seitenleiste steht für Netzwerk-Konten auf <b>jeder</b> Website – auch auf der Hauptwebsite – der Umschalter <b>Website wechseln</b> mit „Netzwerk-Übersicht“ und allen Websites; ein Klick meldet Sie dort ohne erneute Eingabe an.</p>
  <h3 id="netzwerk-admins-einladen">Weitere Netzwerk-Admins einladen</h3>
  <p>Netzwerk-Konten laden weitere Netzwerk-Administratoren selbst ein – niemand muss ein Passwort weitergeben. Nur aktive Netzwerk-Konten können einladen, und nur auf der Hauptwebsite.</p>
  <ol class="doc-steps">
    <li>In der <b>Netzwerk-Übersicht</b> unter „Netzwerk-Administratoren“ <b>+ Netzwerk-Admin einladen</b> öffnen, E-Mail-Adresse, optional Name und eine kurze Nachricht eintragen und <b>Einladung senden</b>.</li>
    <li>Die E-Mail „Einladung zur Netzwerk-Administration“ weist deutlich darauf hin: Das Konto hat Zugriff auf <b>alle</b> Websites, und die Zwei-Faktor-Anmeldung ist Pflicht. Der Link gilt 7 Tage und nur einmal.</li>
    <li>Die eingeladene Person legt einen Passkey und/oder ein Passwort fest und richtet direkt danach den zweiten Faktor ein (ein Passkey erfüllt ihn bereits, sonst die Authenticator-App mit Wiederherstellungscodes). Erst dann öffnet sich die Netzwerk-Übersicht.</li>
  </ol>
  <p>Offene Einladungen stehen in der Liste der Netzwerk-Administratoren mit „Eingeladen – wartet“ bzw. „Einladung abgelaufen“. <b>Erneut senden</b> verschickt einen neuen Link (der alte gilt nicht mehr, die Frist beginnt neu), <b>Zurückziehen</b> macht ihn sofort ungültig. Wird das einladende Konto gesperrt, gilt seine Einladung nicht mehr. Kommt die E-Mail nicht an, zeigt die Übersicht den Link einmal zum Kopieren. Alles steht im Netzwerk-Protokoll.</p>
  <p>Ohne E-Mail-Versand gibt es weiterhin <b>Ohne E-Mail: mit Startpasswort anlegen</b> (zugeklappt). Auf dem Server: <code>php bin/console network:user &lt;email&gt; --invite [--name="…"]</code>.</p>
  <h3>Protokoll</h3>
  <p>Jede Änderung wird mit Zeit, Person und altem/neuem Stand protokolliert; die letzten zehn stehen unten auf der Seite.</p>
