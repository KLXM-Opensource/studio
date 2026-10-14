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
  <p id="mail-test"><b>E-Mails testen:</b> Unter <b>E-Mail-Versand → Test-Umleitung</b> tragen Sie eine (oder mehrere) eigene Adressen ein. Dann gehen <b>alle</b> E-Mails der Website – Formulare, Eingangsbestätigungen, Benachrichtigungen, Anfragen – stattdessen dorthin, mit „[TEST]“ im Betreff und den eigentlichen Empfängern in der Nachricht. Mails zur Kontosicherheit (Passwort zurücksetzen, Einladung, E-Mail-Änderung) gehen weiter an die Person. Solange die Umleitung an ist, zeigt ein violetter Streifen unten in der Verwaltung darauf hin („Ausschalten“ führt zur Einstellung). In Testumgebungen (Staging) werden E-Mails ohnehin nie an echte Empfänger geschickt.</p>
  <h3 id="mail-konten">E-Mail-Konten</h3>
  <p>Unter <b>Grundeinstellungen → E-Mail-Versand → E-Mail-Konten</b> legen Sie neben dem Konto <b>Standard</b> weitere Konten an – etwa ein eigenes für Newsletter oder für Nachrichten an Besucher. Ein Konto versendet entweder über einen <b>SMTP-Server</b> oder über einen <b>Dienst per API</b> wie Mailjet, Mailchimp Transactional, Brevo oder Postmark (Feld „DSN“, z. B. <code>mailjet+api://SCHLÜSSEL:GEHEIMNIS@default</code>). Passwörter und Zugangsschlüssel werden verschlüsselt gespeichert. <b>Testnachricht</b> prüft jedes Konto einzeln – fehlt für einen Dienst noch ein Baustein auf dem Server, nennt die Meldung ihn.</p>
  <p>Unter <b>Verwendung</b> wählen Sie je Zweck das Konto: <b>Konto &amp; Sicherheit</b> (Einladungen, Passwort), <b>Benachrichtigungen an die Redaktion</b>, <b>Nachrichten an Besucher</b> und <b>Newsletter</b>. Erweiterungen können weitere Zwecke ergänzen. Ohne Auswahl gilt „Standard“.</p>
  <p><b>Netzwerk-Konto</b> (nur Netzwerk-Administration, auf der Netzwerk-Website): ein Konto für die ganze Installation. Darüber gehen alle Mails an Netzwerk-Konten und die Mails von Websites ohne eigenes Konto. Wählen Sie einen neutralen Absender (z. B. den Namen des Systems) – so verrät keine Mail einem Kunden, welche anderen Kunden die Installation nutzen. Mails an Netzwerk-Konten laufen nie über das Konto einer Kunden-Website. Fehlt das Netzwerk-Konto, weist die Prüfung (Health) darauf hin.</p>
  <p>Versandarten „sendmail“ und „PHP mail()“ gibt es nicht mehr: Sie werden oft als Spam eingestuft. Nutzen Sie SMTP oder einen Versanddienst.</p>

  <h3 id="domain">Domain und Hauptadresse</h3>
  <p>Bei einer einzelnen Website (ohne Netzwerk) öffnet oben rechts der Knopf <b>Domain</b> die Liste der Adressen dieser Website:</p>
  <ol class="doc-steps">
    <li><b>weitere Domain</b> eintragen (z. B. <code>www.beispiel.de</code> neben <code>beispiel.de</code>) – die Domain muss beim Hoster auf diese Website zeigen.</li>
    <li><b>Erreichbarkeit prüfen</b> zeigt, ob die Domain hier ankommt (mit HTTPS oder nur HTTP).</li>
    <li><b>Als Hauptadresse festlegen</b>: Unter dieser Adresse erscheint die Website, alle anderen leiten dorthin weiter. Die gerade aufgerufene Adresse und die Hauptadresse lassen sich nicht entfernen.</li>
  </ol>
  <p>Gehört die Website zu einem Netzwerk, verwaltet die Netzwerk-Administration die Domains.</p>

  <h3 id="domainumzug">Domainumzug: neue Hauptadresse ohne Aussperren</h3>
  <p><b>Passkeys gelten nur für die Domain, auf der sie angelegt wurden.</b> Das ist eine Regel der Browser (Schutz gegen Phishing). Wer sich bisher nur per Passkey anmeldet, kann ihn nach einem Domainwechsel auf der neuen Adresse nicht verwenden. Gehen Sie deshalb in dieser Reihenfolge vor:</p>
  <ol class="doc-steps">
    <li><b>Neue Domain beim Hosting einrichten</b> (DNS, in Plesk als Alias bzw. zusätzliche Domain, mit SSL-Zertifikat) und unter <b>Domain</b> als <b>weitere Domain</b> eintragen – die bisherige Adresse bleibt Hauptadresse und funktioniert weiter.</li>
    <li><b>Erreichbarkeit prüfen.</b></li>
    <li><b>Dort anmelden und Passkey anlegen</b>: Neben jeder Domain, für die Sie noch keinen Passkey haben, steht dieser Knopf (auch unter <b>Konto › Passkeys › Weitere Domains dieser Website</b>). Er meldet Sie ohne erneute Eingabe auf der neuen Domain an und öffnet dort Ihr Konto – legen Sie unter <b>Passkeys</b> einen neuen an.</li>
    <li>Erst dann <b>Als Hauptadresse festlegen</b>. Fehlt Ihnen für die neue Domain noch ein Passkey, weist die Rückfrage darauf hin.</li>
    <li>Weitere Personen mit Passkey legen nach dem Wechsel auf der neuen Adresse einen neuen an – über ihr Konto („Dort anmelden …“), solange die alte Adresse noch erreichbar ist, oder nach der Anmeldung mit Passwort und App-Code bzw. Wiederherstellungscode.</li>
    <li>Die alte Domain erst entfernen, wenn alle umgezogen sind.</li>
  </ol>
  <div class="doc-note doc-note--info"><strong>Netzwerk: eigene Verwaltungsdomain</strong><p>Mit mehreren Websites meldet sich die Netzwerk-Administration auf der <b>Netzwerk-Website</b> an und wechselt von dort per „Öffnen“ bzw. „Website wechseln“ in die anderen Websites – dafür braucht es keinen Passkey der jeweiligen Domain. Behält die Netzwerk-Website eine feste Adresse (z. B. eine Verwaltungsdomain der Agentur), können die Domains aller anderen Websites wechseln, ohne dass sich an der Anmeldung etwas ändert. In der Netzwerk-Übersicht steht bei Domains der Netzwerk-Website ohne Passkey ebenfalls „Dort anmelden und Passkey anlegen“.</p></div>
  <div class="doc-note doc-note--warn"><strong>Notfall: ausgesperrt</strong><p>Wer gar nicht mehr hineinkommt, bittet die Administration um „2FA zurücksetzen“ (Benutzer &amp; Rollen). Für Netzwerk-Konten setzt die Agentur den zweiten Faktor auf dem Server zurück (<code>php bin/console network:user &lt;E-Mail&gt; --reset-2fa</code>); bei der nächsten Anmeldung wird er neu eingerichtet. Halten Sie deshalb neben dem Passkey immer die Wiederherstellungscodes bereit – mit der Richtlinie „nur Passkey“ ist der Notfallweg über den Server der einzige.</p></div>

  <h3 id="branding">Branding & Support (Agentur)</h3>
  <p>Unter <b>System → Branding &amp; Support</b> hinterlegt die betreuende Agentur ihr <b>Logo</b> (SVG, PNG, JPG oder WebP; optional eine Fassung für die dunkle Darstellung), ihren <b>Namen</b> und ihre <b>Support-Angaben</b> – Ansprechpartner, Telefon, E-Mail, Website, Erreichbarkeit und einen Hinweis. Das Logo ersetzt die Produktmarke auf der Anmeldeseite, bei Einrichtung und Begrüßung und in der Werkzeugleiste der Website; klein darunter steht auf Wunsch „Technik: KLXM Studio“. Die Support-Angaben erscheinen unter Hilfe &amp; Support, in „Hilfe &amp; Einstieg“ der Übersicht, bei „Neue Meldung“ und als kurze Zeile auf der Anmeldeseite.</p>
  <p>Das Branding gilt für die ganze Installation. In einer Einzel-Installation pflegt es die Administration; mit mehreren Websites die Netzwerk-Administration (gibt es keine Netzwerk-Konten, die Administration der Haupt-Website) – es gilt dann für alle Websites.</p>

  <h3 id="umgebung">Testumgebung (Staging) und Livebetrieb</h3>
  <ul>
    <li><b>Testumgebung (staging):</b> Suchmaschinen sind ausgesperrt, E-Mails gehen nicht an echte Empfänger, Push-Mitteilungen nicht an Besucher; auf der Website steht unten ein Hinweisbalken. Gut für den Aufbau einer neuen Website.</li>
    <li><b>Livebetrieb (production):</b> alles läuft normal. Umstellen unter <b>Umgebung → Umstellen</b> (Administration und Netzwerk-Administration).</li>
    <li>Solange die Testumgebung aktiv ist, <b>pulsiert ein oranger Punkt</b> an „Grundeinstellungen“ und an der Gruppe „System“ im Menü – damit niemand vergisst, sie vor dem Start aufzuheben.</li>
  </ul>

  <h3 id="warnungen">Warnpunkte im Menü</h3>
  <p>Ein oranger Punkt an einem Menüpunkt der Einrichtung weist auf etwas Wichtiges hin – derzeit die <b>aktive Testumgebung</b> oder einen <b>fehlenden Schlüssel</b> für verschlüsselte Anfragen. Ist die Gruppe zugeklappt, zeigt sie den Punkt mit; beim Darüberfahren steht der Grund daneben. Erledigt man die Sache, verschwindet der Punkt.</p>
