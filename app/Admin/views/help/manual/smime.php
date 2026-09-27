<?php /** Handbuch · Kapitel „S/MIME einrichten“ – verschlüsselte Anfragen per E-Mail empfangen (Core\Data\Delivery, Funktion „requests.mail“).
 * Erzeugt aus resources/docs/smime-anleitung.md (pandoc); Druckfassung: public/assets/docs/smime-anleitung.html */ ?>
  <p class="lead">Stellt die Website Anfragen per E-Mail zu (<b>Daten → Tabelle → Einstellungen → Zustellung</b>), verschlüsselt sie jede Nachricht mit dem <b>S/MIME-Zertifikat</b> des Empfangspostfachs. Lesen kann sie dann nur, wer den privaten Schlüssel hat – nicht der Webhoster, nicht der Mailanbieter. Dieses Kapitel erklärt Schritt für Schritt, wie Sie ein Zertifikat besorgen, in Ihrem Mailprogramm einrichten und der Website den öffentlichen Teil geben.</p>
  <p><a href="<?= e(asset('docs/smime-anleitung.html')) ?>" target="_blank" rel="noopener">Druckfassung zum Weitergeben an Team und IT (HTML, A4) →</a></p>
<h3>Auf einen Blick</h3>
<ol type="1">
<li>Sie besorgen ein <strong>E-Mail-Zertifikat (S/MIME)</strong> für das
Postfach, in dem die Anfragen ankommen.</li>
<li>Sie <strong>installieren</strong> das Zertifikat (Datei
<code>.p12</code> oder <code>.pfx</code>) auf jedem Gerät, auf dem die
Anfragen gelesen werden.</li>
<li>Sie geben der Website nur den <strong>öffentlichen Teil</strong> des
Zertifikats (Datei <code>.cer</code> oder <code>.pem</code>).</li>
<li>Die Website verschlüsselt jede Anfrage damit. <strong>Nur</strong>
Ihre Geräte können sie öffnen.</li>
<li>Zum Schluss: <strong>Testmail senden</strong> und prüfen, ob sie mit
Schloss-Symbol lesbar ankommt.</li>
</ol>
<div class="doc-note doc-note--important"><strong>Wichtig</strong><p>Die Datei
<code>.p12</code>/<code>.pfx</code> und ihr Passwort bleiben immer bei
Ihnen. Weder die Website noch Ihre Website-Betreuung brauchen sie.</p></div>
<h3>1. Was ist S/MIME – und warum?</h3>
<p>S/MIME ist ein Standard für verschlüsselte E-Mails. Er ist in fast
allen Mailprogrammen eingebaut. Jedes Zertifikat hat zwei Teile: einen
<strong>öffentlichen Schlüssel</strong> und einen <strong>privaten
Schlüssel</strong>. Mit dem öffentlichen Schlüssel kann jeder eine
Nachricht <strong>verschließen</strong> – auch die Website.
<strong>Öffnen</strong> kann sie nur, wer den privaten Schlüssel hat –
also nur Ihre eigenen Geräte. Unterwegs (beim Mailanbieter, auf Servern,
bei Weiterleitungen) bleibt der Inhalt unlesbar.</p>
<p><strong>Warum das wichtig ist:</strong> Anfragen an eine Arztpraxis,
Therapie- oder Beratungsstelle enthalten oft Gesundheitsdaten. Das sind
„besondere Kategorien personenbezogener Daten“ nach <strong>Art. 9
DSGVO</strong>. Sie brauchen einen besonders hohen Schutz (Art. 32
DSGVO). Mit S/MIME können nur Sie die Inhalte lesen – nicht der
Webhoster, nicht der Mailanbieter und nicht die Website-Betreuung.</p>
<div class="doc-note doc-note--tip"><strong>Gut zu wissen</strong><p>S/MIME verschlüsselt den
<strong>Inhalt</strong> und die <strong>Anhänge</strong>. Absender,
Empfänger, Datum und <strong>Betreff</strong> bleiben sichtbar. Der
Betreff der Website-Mails enthält deshalb standardmäßig nur den
Formularnamen und eine Vorgangsnummer. Nehmen Sie dort keine Angaben aus
der Anfrage auf.</p></div>
<h3>2. Zertifikat besorgen</h3>
<h4>2.1 Für welche Adresse?</h4>
<p>Das Zertifikat wird für <strong>genau die E-Mail-Adresse</strong>
ausgestellt, an die die Website die Anfragen schickt.</p>
<ul>
<li>Ein Zertifikat gilt nur für <strong>eine</strong> Adresse.
Tippfehler oder ein anderer Alias (z. B. <code>info@</code> statt
<code>praxis@</code>) führen später zu Fehlern.</li>
<li>Lesen mehrere Personen dasselbe Postfach? Dann reicht
<strong>ein</strong> Zertifikat. Es wird auf allen Geräten installiert
(siehe Abschnitt 6).</li>
</ul>
<h4>2.2 Welche Art von Zertifikat?</h4>
<p>Sie brauchen ein einfaches <strong>E-Mail-Zertifikat</strong>
(S/MIME). Frühere Bezeichnungen sind „Klasse 1“ oder „Class 1“. Heute
heißt die einfachste Stufe meist <strong>„Mailbox-validiert“
(MV)</strong>: Der Anbieter prüft nur, ob Sie Zugriff auf das Postfach
haben. Höhere Stufen (mit Prüfung von Organisation oder Person)
funktionieren ebenso. Sie sind für die Verschlüsselung aber nicht
nötig.</p>
<div class="doc-note doc-note--important"><strong>Wichtig bei der Bestellung</strong><p>Wählen Sie einen
<strong>RSA-Schlüssel</strong> (z. B. RSA 2048 oder 3072 Bit), falls der
Anbieter fragt. Zertifikate mit <strong>ECC-/ECDSA-Schlüssel</strong>
kann KLXM Studio derzeit nicht zum Verschlüsseln verwenden. Das
Zertifikat muss außerdem für
<strong>E-Mail-Schutz/Verschlüsselung</strong> ausgestellt sein – bei
E-Mail-Zertifikaten ist das der Normalfall.</p></div>
<p><strong>Anbieter (Auswahl, neutral, ohne Wertung):</strong></p>
<div style="overflow-x:auto"><table class="doc-table">
<colgroup>
<col style="width: 50%" />
<col style="width: 50%" />
</colgroup>
<thead>
<tr>
<th>Anbieter</th>
<th>Hinweise</th>
</tr>
</thead>
<tbody>
<tr>
<td><strong>Actalis</strong> (Italien)</td>
<td>Bietet ein <strong>kostenloses</strong> Mailbox-validiertes
Zertifikat an (laut Anbieterseite im September 2026: eines pro Konto,
erstes Jahr kostenlos, Verlängerung kostenpflichtig). Kostenlose
Angebote können sich jederzeit ändern. Der private Schlüssel wird beim
Anbieter erzeugt und als <code>.p12</code> geliefert.</td>
</tr>
<tr>
<td><strong>Certum</strong> (Asseco, Polen)</td>
<td>S/MIME-Zertifikate in mehreren Stufen, Laufzeit bis zu 2 Jahre.</td>
</tr>
<tr>
<td><strong>Sectigo</strong></td>
<td>Vertrieb meist über Händler (Reseller), z. B. als „Personal
Authentication“/„S/MIME“.</td>
</tr>
<tr>
<td><strong>GlobalSign</strong></td>
<td>„PersonalSign“-Zertifikate in mehreren Stufen.</td>
</tr>
<tr>
<td><strong>D-Trust</strong> (Bundesdruckerei)</td>
<td>Deutscher Anbieter. Vor allem Zertifikate mit Organisationsprüfung,
auch für Team-Postfächer. Bestellung über ein Kundenportal oder
Händler.</td>
</tr>
</tbody>
</table></div>
<p>Preise nennen wir bewusst nicht. Sie ändern sich häufig und hängen
vom Händler ab.</p>
<p><strong>Laufzeit und Verlängerung:</strong></p>
<ul>
<li>Öffentlich vertrauenswürdige S/MIME-Zertifikate gelten nach den
Regeln des CA/Browser-Forums <strong>höchstens 825 Tage</strong> (gut 2
Jahre). Üblich sind <strong>1 oder 2 Jahre</strong>.</li>
<li>Tragen Sie das <strong>Ablaufdatum</strong> sofort in den
Teamkalender ein. Erinnerung: <strong>4 Wochen vorher</strong>.</li>
<li>Bei der Verlängerung erhalten Sie in der Regel ein
<strong>neues</strong> Zertifikat mit <strong>neuem</strong>
Schlüsselpaar. Dann müssen Sie der Website den <strong>neuen
öffentlichen Teil</strong> geben (Abschnitt 3). Und: <strong>Den alten
privaten Schlüssel nicht löschen</strong> – sonst sind ältere Mails
nicht mehr lesbar (Abschnitt 6).</li>
</ul>
<h4
id="für-arztpraxen-ist-die-telematikinfrastruktur-kim-eine-alternative">2.3
Für Arztpraxen: Ist die Telematikinfrastruktur (KIM) eine
Alternative?</h4>
<p><strong>Nein, nicht für Anfragen von der Website.</strong> KIM
(„Kommunikation im Medizinwesen“) ist der sichere E-Mail-Dienst der
Telematikinfrastruktur. Er verbindet <strong>nur</strong> angeschlossene
Einrichtungen: Praxen, Kliniken, Apotheken, Krankenkassen. Patientinnen
und Patienten – und eine Website – können keine KIM-Nachrichten senden.
KIM bleibt der richtige Weg für eAU, Arztbriefe und Ähnliches. Für das
Website-Formular brauchen Sie ein normales S/MIME-Zertifikat für ein
normales Postfach.</p>
<h4>2.4 Was Sie
bekommen – und wie Sie es aufbewahren</h4>
<p>Je nach Anbieter gibt es zwei Wege:</p>
<ul>
<li><strong>Der Anbieter erzeugt den Schlüssel</strong> (z. B. Actalis):
Sie laden eine Datei <strong><code>.p12</code></strong> oder
<strong><code>.pfx</code></strong> herunter. Das Passwort dazu wird
angezeigt oder separat geschickt.</li>
<li><strong>Ihr Browser oder Ihr Rechner erzeugt den Schlüssel</strong>
(Schlüsselanfrage, „CSR“): Das Zertifikat landet direkt im Browser bzw.
im Zertifikatspeicher. Exportieren Sie es dann einmal <strong>mit
privatem Schlüssel</strong> als <code>.p12</code>/<code>.pfx</code>
(siehe unten bei Windows bzw. macOS) – als Sicherung und für weitere
Geräte.</li>
</ul>
<p>Die <code>.p12</code>/<code>.pfx</code>-Datei enthält den
<strong>privaten Schlüssel</strong>. Behandeln Sie sie wie einen
Generalschlüssel:</p>
<ul>
<li><strong>Sicher speichern:</strong> im Passwortmanager Ihrer
Einrichtung (als Anhang) oder auf einem verschlüsselten USB-Stick im
Tresor. Passwort <strong>getrennt</strong> von der Datei
aufbewahren.</li>
<li><strong>Mindestens eine Sicherung</strong> an einem zweiten Ort.
Ohne diese Datei sind alle damit verschlüsselten Mails <strong>für immer
unlesbar</strong>.</li>
<li><strong>Nie</strong> per unverschlüsselter Mail, Messenger oder
Cloud-Freigabe verschicken. Nie an die Website-Betreuung oder den
Webhoster geben.</li>
<li>Alte Zertifikatsdateien <strong>nicht löschen</strong> (siehe
Abschnitt 6, Archivierung).</li>
</ul>
<h3>3.
Öffentliches Zertifikat für die Website bereitstellen</h3>
<p>Die Website braucht <strong>nur den öffentlichen Teil</strong> – eine
Datei mit der Endung <strong><code>.cer</code></strong>,
<strong><code>.crt</code></strong> oder
<strong><code>.pem</code></strong>. Sie ist nicht geheim.</p>
<p>KLXM Studio erwartet das Zertifikat im <strong>Textformat
„PEM“</strong> (auch „Base-64“ genannt).</p>
<p><strong>So erkennen Sie die richtige Datei:</strong> Öffnen Sie sie
mit einem Texteditor (Editor, TextEdit). Sie beginnt mit
<code>-----BEGIN CERTIFICATE-----</code> und endet mit
<code>-----END CERTIFICATE-----</code>. Dazwischen stehen Buchstaben und
Zahlen.</p>
<ul>
<li>Steht irgendwo <code>PRIVATE KEY</code>, ist es die
<strong>falsche</strong> Datei – bitte nicht weitergeben. (KLXM Studio
lehnt sie auch ab und speichert nichts davon.)</li>
<li>Sehen Sie nur unlesbare Zeichen, ist die Datei im Binärformat
(„DER“). Exportieren Sie dann erneut im Format <strong>Base-64</strong>
bzw. <strong>PEM</strong> wie unten beschrieben – oder bitten Sie Ihre
Website-Betreuung, sie umzuwandeln.</li>
</ul>
<h4>Windows (Windows 10/11)</h4>
<ol type="1">
<li>Die <code>.p12</code>/<code>.pfx</code>-Datei zuerst installieren
(Abschnitt 4, Windows).</li>
<li><strong>Start</strong> öffnen,
<code>Benutzerzertifikate verwalten</code> eintippen und öffnen
(entspricht <code>certmgr.msc</code>).</li>
<li><strong>Eigene Zertifikate → Zertifikate</strong> öffnen. Das
Zertifikat mit Ihrer Empfangsadresse suchen.</li>
<li>Rechtsklick → <strong>Alle Aufgaben → Exportieren…</strong></li>
<li><strong>Nein, privaten Schlüssel nicht exportieren</strong>
wählen.</li>
<li>Format <strong>Base-64-codiert X.509 (.CER)</strong> wählen,
Dateinamen vergeben (z. B. <code>praxis-zertifikat.cer</code>),
<strong>Fertig stellen</strong>.</li>
</ol>
<h4>macOS (Apple Mail, Outlook für
Mac)</h4>
<ol type="1">
<li>Die <code>.p12</code>-Datei zuerst installieren (Abschnitt 4,
macOS).</li>
<li>Die App <strong>Schlüsselbundverwaltung</strong> über die
Spotlight-Suche öffnen (<strong>⌘ + Leertaste</strong>, „Schlüsselbund“
tippen). Seit macOS 15 Sequoia liegt sie nicht mehr im Ordner
„Dienstprogramme“.</li>
<li>Links <strong>Anmeldung</strong> wählen, oben <strong>Meine
Zertifikate</strong>.</li>
<li>Das Zertifikat mit Ihrer Empfangsadresse <strong>anklicken</strong>
(die Zeile mit dem Zertifikat, <strong>nicht</strong> den aufgeklappten
Schlüssel darunter).</li>
<li><strong>Ablage → Objekte exportieren…</strong></li>
<li>Dateiformat <strong>Privacy Enhanced Mail (.pem)</strong> wählen,
<strong>Sichern</strong>. (Das Format „Zertifikat (.cer)“ speichert der
Mac binär – dafür nicht verwenden.) Fragt der Mac nach einem Passwort,
haben Sie den Schlüssel erwischt – abbrechen und Schritt 4
wiederholen.</li>
</ol>
<h4>Thunderbird (alle Systeme)</h4>
<ol type="1">
<li><strong>Einstellungen → Datenschutz &amp; Sicherheit →</strong> ganz
unten bei <strong>Zertifikate</strong> auf <strong>Zertifikate
verwalten…</strong></li>
<li>Reiter <strong>Ihre Zertifikate</strong>, das Zertifikat markieren,
<strong>Ansehen…</strong></li>
<li>In der Zertifikatsansicht unter <strong>Verschiedenes</strong> auf
<strong>PEM (Zertifikat)</strong> klicken und speichern.</li>
</ol>
<p><em>(Nicht „Sichern…“ verwenden – das exportiert den privaten
Schlüssel.)</em></p>
<h4>Für die IT: mit OpenSSL</h4>
<pre><code>openssl pkcs12 -in praxis.p12 -clcerts -nokeys -out praxis-zertifikat.pem</code></pre>
<p>Bei älteren <code>.p12</code>-Dateien meldet OpenSSL 3 ggf. einen
Fehler; dann zusätzlich <code>-legacy</code> angeben. Prüfen:
<code>openssl x509 -in praxis-zertifikat.pem -noout -subject -enddate</code>
zeigt Adresse und Ablaufdatum.</p>
<h4>Zertifikat an die Website
übergeben</h4>
<p><strong>Weg A – selbst eintragen</strong> (mit einem Konto, das
Anfragen verwalten und die Tabelle bearbeiten darf, meist die
Administration):</p>
<ol type="1">
<li>In KLXM Studio anmelden.</li>
<li><strong>Daten →</strong> die Tabelle bzw. den Eingang wählen (z. B.
Rezeptanfragen oder Kontaktanfragen) <strong>→ Einstellungen →
Zustellung</strong>.</li>
<li>Bei <strong>S/MIME-Zertifikat</strong> den Inhalt der
<code>.pem</code>/<code>.cer</code>-Datei einfügen (bzw. die Datei
hochladen) und speichern.</li>
<li>KLXM Studio prüft das Zertifikat sofort: Abgelaufene Zertifikate,
Zertifikate ohne Verschlüsselungs-Freigabe und Dateien mit privatem
Schlüssel werden abgelehnt – mit einer verständlichen Meldung.</li>
<li>Für jede weitere Tabelle, die per E-Mail zustellt, wiederholen.</li>
</ol>
<p>Bis zu <strong>5 Zertifikate</strong> lassen sich hinterlegen. Die
Website verschlüsselt dann für alle gleichzeitig. Das hilft beim Wechsel
(altes und neues Zertifikat für kurze Zeit parallel) und wenn Anfragen
an mehrere Adressen gehen, die jeweils ein eigenes Zertifikat haben.</p>
<p><strong>Weg B – an die Website-Betreuung schicken:</strong> Senden
Sie die Datei <code>.pem</code>/<code>.cer</code> an Ihre Ansprechperson
(bei KLXM-Kundinnen und -Kunden: KLXM). Der öffentliche Teil darf per
normaler E-Mail verschickt werden. Nennen Sie dazu die Empfängeradresse.
Sie trägt das Zertifikat ein und sendet eine Testmail.</p>
<h3>4.
Zertifikat installieren und verschlüsselte Mails lesen</h3>
<p>Der private Schlüssel muss auf <strong>jedem Gerät und in jedem
Programm</strong> vorhanden sein, mit dem die Anfragen gelesen werden.
Ist er installiert, <strong>entschlüsseln die Programme
automatisch</strong> – ganz ohne Zusatzschritte beim Lesen.</p>
<p>Beschrieben sind die Versionen, die im September 2026 aktuell sind.
Menünamen können sich mit Updates leicht ändern.</p>
<h4>4.1 macOS:
Apple Mail (macOS 15 Sequoia, macOS 26 Tahoe)</h4>
<ol type="1">
<li>Die <code>.p12</code>-Datei im Finder
<strong>doppelklicken</strong>.</li>
<li>Als Schlüsselbund <strong>Anmeldung</strong> wählen,
<strong>Hinzufügen</strong>.</li>
<li>Das <strong>Passwort der <code>.p12</code>-Datei</strong> eingeben.
(Eventuell fragt der Mac vorher nach Ihrem Mac-Anmeldepasswort.)</li>
<li><strong>Mail</strong> beenden und neu öffnen. Fertig: Apple Mail
findet das Zertifikat selbst und entschlüsselt automatisch.</li>
<li>Optional prüfen: In der <strong>Schlüsselbundverwaltung → Anmeldung
→ Meine Zertifikate</strong> steht das Zertifikat mit einem
aufklappbaren <strong>privaten Schlüssel</strong> darunter.</li>
</ol>
<h4
id="windows-zertifikat-in-den-windows-zertifikatspeicher-windows-1011">4.2
Windows: Zertifikat in den Windows-Zertifikatspeicher (Windows
10/11)</h4>
<p>Outlook (klassisch) und die meisten Windows-Programme nutzen diesen
Speicher.</p>
<ol type="1">
<li>Die <code>.pfx</code>/<code>.p12</code>-Datei
<strong>doppelklicken</strong>. Der
<strong>Zertifikatimport-Assistent</strong> startet.</li>
<li>Speicherort <strong>Aktueller Benutzer</strong>,
<strong>Weiter</strong>, Datei bestätigen, <strong>Weiter</strong>.</li>
<li><strong>Kennwort</strong> der Datei eingeben. Optionen:
<ul>
<li><strong>Schlüssel als exportierbar markieren</strong>: nur anhaken,
wenn Sie später von diesem PC aus eine Sicherung erstellen wollen. Sonst
leer lassen (sicherer).</li>
<li><strong>Hohe Sicherheit für den privaten Schlüssel
aktivieren</strong>: optional; Windows fragt dann bei jeder Nutzung
nach.</li>
</ul></li>
<li><strong>Zertifikatspeicher automatisch auswählen</strong>,
<strong>Weiter</strong>, <strong>Fertig stellen</strong>.</li>
<li>Jede Person, die sich mit einem <strong>eigenen
Windows-Konto</strong> anmeldet, muss den Import selbst
durchführen.</li>
</ol>
<h4
id="windows-outlook-klassisch-microsoft-365-apps-outlook-2021-outlook-2024">4.3
Windows: Outlook klassisch (Microsoft 365 Apps, Outlook 2021, Outlook
2024)</h4>
<p>Voraussetzung: Schritt 4.2 ist erledigt. <strong>Lesen</strong>
funktioniert danach sofort. Für verschlüsselte
<strong>Antworten</strong> hinterlegen Sie das Zertifikat
zusätzlich:</p>
<ol type="1">
<li><strong>Datei → Optionen → Trust Center → Einstellungen für das
Trust Center…</strong></li>
<li>Links <strong>E-Mail-Sicherheit</strong>, unter „Verschlüsselte
E-Mail-Nachrichten“ auf <strong>Einstellungen…</strong></li>
<li>Bei <strong>Signaturzertifikat</strong> und
<strong>Verschlüsselungszertifikat</strong> jeweils
<strong>Auswählen…</strong> und das Zertifikat des Postfachs wählen.
<strong>OK</strong>.</li>
<li>Haken <strong>Inhalt und Anlagen für ausgehende Nachrichten
verschlüsseln</strong> <strong>nicht</strong> setzen – sonst versucht
Outlook, jede Mail zu verschlüsseln, auch an Patienten ohne
Zertifikat.</li>
</ol>
<p>Hinweis: Outlook 2016 und 2019 werden seit Oktober 2025 nicht mehr
mit Sicherheitsupdates versorgt. Die Schritte sind dort gleich; wir
empfehlen trotzdem eine aktuelle Version.</p>
<h4>4.4 Windows: das neue
Outlook für Windows</h4>
<ul>
<li>Das neue Outlook kann S/MIME seit Anfang 2025 – laut Microsoft aber
nur für das <strong>primäre Geschäftskonto</strong> (Microsoft 365 /
Exchange Online). Eine separate „S/MIME-Steuerung“ ist für das neue
Outlook nicht nötig; sie betrifft nur <strong>Outlook im Web</strong> im
Browser.</li>
<li>Das Zertifikat muss vorher im Windows-Zertifikatspeicher liegen
(Schritt 4.2). Das neue Outlook importiert es nicht selbst.</li>
<li>Einstellungen: <strong>Einstellungen → E-Mail →
S/MIME</strong>.</li>
<li><strong>Läuft das Empfangspostfach über IMAP bei einem
Webhoster</strong> (kein Microsoft 365), unterstützt das neue Outlook
S/MIME dafür nach unserem Kenntnisstand <strong>nicht</strong>. Nutzen
Sie dann <strong>Outlook klassisch</strong> oder
<strong>Thunderbird</strong>. Tipp: Oben rechts im neuen Outlook lässt
sich meist zurück zur klassischen Version wechseln, solange Microsoft
diese anbietet.</li>
</ul>
<h4
id="macos-outlook-für-mac-version-16-neues-outlook-und-legacy-ansicht">4.5
macOS: Outlook für Mac (Version 16, „neues Outlook“ und
Legacy-Ansicht)</h4>
<ol type="1">
<li>Die <code>.p12</code>-Datei wie in 4.1 in den Schlüsselbund
<strong>Anmeldung</strong> importieren.</li>
<li><strong>Neues Outlook:</strong> <strong>Outlook → Einstellungen… →
Konten</strong> (bzw. <strong>Outlook → Konten</strong>), das Konto des
Empfangspostfachs wählen, <strong>Sicherheit</strong>, unter
<strong>Zertifikat</strong> das Zertifikat des Postfachs wählen,
<strong>OK</strong>. <strong>Legacy-Outlook:</strong> <strong>Extras →
Konten →</strong> Konto → <strong>Erweitert → Sicherheit</strong>.</li>
<li>Outlook neu starten. Beim ersten Öffnen einer verschlüsselten Mail
fragt macOS eventuell, ob Outlook den Schlüssel verwenden darf:
<strong>Immer erlauben</strong>.</li>
</ol>
<h4
id="thunderbird-windows-macos-linux-version-128-esr-140-esr-und-neuer">4.6
Thunderbird (Windows, macOS, Linux; Version 128 ESR, 140 ESR und
neuer)</h4>
<ol type="1">
<li><strong>≡ (Menü) → Einstellungen → Datenschutz &amp;
Sicherheit</strong>, nach unten zu <strong>Zertifikate</strong>,
<strong>Zertifikate verwalten…</strong></li>
<li>Reiter <strong>Ihre Zertifikate → Importieren…</strong>, die
<code>.p12</code>-Datei wählen, das <strong>Passwort der Datei</strong>
eingeben. Hinweis: Hat Thunderbird ein <strong>Hauptpasswort</strong>,
fragt es zuerst danach.</li>
<li><strong>≡ → Konten-Einstellungen</strong>, beim Konto des
Empfangspostfachs <strong>Ende-zu-Ende-Verschlüsselung</strong>
wählen.</li>
<li>Im Bereich <strong>S/MIME</strong> bei <strong>Persönliches
Zertifikat für digitale Unterschrift</strong> und <strong>Persönliches
Zertifikat für Verschlüsselung</strong> jeweils
<strong>Auswählen…</strong> und das Zertifikat des Postfachs
wählen.</li>
<li>Nicht einstellen, dass jede Mail verschlüsselt wird
(<strong>Standardmäßig verschlüsseln</strong> aus lassen).</li>
</ol>
<p>Thunderbird nutzt einen <strong>eigenen</strong> Zertifikatspeicher –
auch unter Windows und macOS. Der Import ist deshalb zusätzlich nötig,
selbst wenn das Zertifikat schon in Windows oder im Schlüsselbund
liegt.</p>
<h4>4.7 Linux</h4>
<ul>
<li><strong>Thunderbird:</strong> wie 4.6.</li>
<li><strong>Evolution (GNOME, Version 3.5x):</strong>
<ol type="1">
<li><strong>Bearbeiten → Einstellungen → Zertifikate →</strong> Reiter
<strong>Ihre Zertifikate → Importieren</strong>, Datei wählen, Passwort
eingeben.</li>
<li><strong>Bearbeiten → Einstellungen → E-Mail-Konten →</strong> Konto
<strong>Bearbeiten → Sicherheit</strong>, unter <strong>S/MIME</strong>
das <strong>Verschlüsselungszertifikat</strong> auswählen.</li>
<li>Meldet Evolution ein unbekanntes Zertifikat: unter
<strong>Zertifikate → Zertifizierungsstellen</strong> der ausstellenden
Stelle vertrauen („Vertrauen, um E-Mail-Benutzer zu
identifizieren“).</li>
<li>Bei der Flatpak-Version von Evolution gab es Berichte über verlorene
Zertifikate – im Zweifel die Paketversion der Distribution nutzen.</li>
</ol></li>
</ul>
<h4>4.8 iPhone und
iPad (Apple Mail, iOS/iPadOS 18 und 26)</h4>
<ol type="1">
<li>Die <code>.p12</code>-Datei <strong>sicher</strong> aufs Gerät
bringen: per <strong>AirDrop</strong> vom Büro-Mac oder über die
<strong>Dateien</strong>-App. Nicht per unverschlüsselter Mail.</li>
<li>Datei antippen. Es erscheint „Profil geladen“.</li>
<li><strong>Einstellungen →</strong> oben <strong>Profil
geladen</strong> (oder <strong>Allgemein → VPN und
Geräteverwaltung</strong>) → <strong>Installieren</strong>. Gerätecode
eingeben, dann das <strong>Passwort der
<code>.p12</code>-Datei</strong>.</li>
<li><strong>Einstellungen → Apps → Mail → Mail-Accounts →</strong> Konto
des Empfangspostfachs → <strong>Account → Erweitert</strong>. Dort
<strong>S/MIME</strong> einschalten und bei <strong>Verschlüsseln
standardmäßig</strong>/<strong>Signieren</strong> das Zertifikat wählen,
falls gewünscht. <em>(Bis iOS 17: <strong>Einstellungen → Mail →
Accounts → …</strong>.)</em></li>
<li>Verschlüsselte Mails öffnet Apple Mail danach automatisch.</li>
</ol>
<p>Einschränkung: Andere Mail-Apps auf dem iPhone (auch Outlook) haben
<strong>keinen</strong> Zugriff auf dieses Zertifikat.</p>
<h4>4.9 Android</h4>
<p>Android hat <strong>keine</strong> eingebaute S/MIME-Unterstützung in
einer Standard-Mail-App. Möglich sind:</p>
<ul>
<li><strong>FairEmail</strong> (kostenlos, quelloffen): unterstützt
S/MIME. Das Zertifikat wird in den Android-Schlüsselspeicher importiert
und in der App ausgewählt.</li>
<li><strong>R2Mail2</strong>: unterstützt S/MIME und PGP; die App wirkt
älter, funktioniert aber laut Berichten weiterhin.</li>
<li><strong>Outlook für Android</strong>: S/MIME nur für
<strong>Microsoft 365 / Exchange Online</strong> und in der Regel über
eine Geräteverwaltung (Intune). Für ein IMAP-Postfach beim Webhoster
<strong>nicht geeignet</strong>.</li>
<li><strong>Thunderbird für Android</strong> (früher K-9 Mail):
<strong>kein</strong> S/MIME, nur OpenPGP. Laut Thunderbird-Team ist
S/MIME für 2026 nicht geplant.</li>
</ul>
<p><strong>Unsere Empfehlung:</strong> Lesen Sie Anfragen am
<strong>Arbeitsplatz-PC</strong>. Den privaten Schlüssel auf private
Smartphones zu kopieren, erhöht das Risiko. Wenn mobil nötig: nur auf
dienstlichen, gesperrten Geräten.</p>
<h4>4.10 Webmail im Browser</h4>
<ul>
<li>Die meisten Webmail-Oberflächen <strong>können S/MIME nicht
entschlüsseln</strong>: z. B. <strong>Gmail (privat)</strong> und viele
Webmailer von Hostern. Die Mail erscheint dort als Anhang
<code>smime.p7m</code>.</li>
<li><strong>Ausnahmen mit Einschränkungen:</strong>
<ul>
<li><strong>Outlook im Web</strong> (Microsoft 365 / Exchange): mit der
<strong>S/MIME-Steuerung</strong> (Browser-Erweiterung für Edge oder
Chrome, unter <strong>Einstellungen → E-Mail → S/MIME</strong>) – nur
für Microsoft-365-/Exchange-Konten und nur auf Windows-PCs mit
installiertem Zertifikat.</li>
<li><strong>Gmail in Google Workspace</strong> („gehostetes S/MIME“):
nur in bestimmten Editionen (z. B. Enterprise Plus, Education). Dabei
liegt der <strong>private Schlüssel bei Google</strong> – für
Gesundheitsdaten datenschutzrechtlich sorgfältig abwägen.</li>
<li>Einzelne Mailanbieter bieten S/MIME im Webmail an. Fragen Sie Ihren
Anbieter.</li>
</ul></li>
<li>Webmail ist <strong>kein</strong> Ersatz für ein Mailprogramm mit
installiertem Zertifikat.</li>
</ul>
<h3>5. Testen</h3>
<ol type="1">
<li>Zertifikat hochladen (Abschnitt 3) und in mindestens einem
Mailprogramm installieren (Abschnitt 4).</li>
<li>In KLXM Studio: <strong>Daten →</strong> Tabelle <strong>→
Einstellungen → Zustellung → Testmail senden</strong>.</li>
<li>Im Empfangspostfach auf <strong>jedem</strong> Gerät öffnen, auf dem
Anfragen gelesen werden sollen.</li>
</ol>
<p>Die Testmail enthält <strong>erfundene Angaben</strong>. Ihr Betreff
beginnt mit <strong>[TEST]</strong>, unten steht „TESTNACHRICHT … bitte
nicht archivieren“. Im Kopf der Nachricht steht bei „Zustellung“ der
Zusatz <strong>S/MIME-verschlüsselt</strong>.</p>
<p><strong>So sieht ein korrektes Ergebnis aus:</strong></p>
<div style="overflow-x:auto"><table class="doc-table">
<colgroup>
<col style="width: 33%" />
<col style="width: 33%" />
<col style="width: 33%" />
</colgroup>
<thead>
<tr>
<th>Programm</th>
<th>Kennzeichen</th>
<th>Inhalt</th>
</tr>
</thead>
<tbody>
<tr>
<td>Apple Mail (Mac)</td>
<td>Im Kopf der Mail Zeile <strong>Sicherheit</strong> mit
<strong>Schloss</strong> („Verschlüsselt“)</td>
<td>normal lesbar</td>
</tr>
<tr>
<td>Outlook klassisch</td>
<td><strong>Schloss-Symbol</strong> in der Mailliste und im Kopf der
Mail</td>
<td>normal lesbar</td>
</tr>
<tr>
<td>Neues Outlook / Outlook für Mac</td>
<td>Hinweis bzw. Symbol „Verschlüsselt“ (S/MIME) im Kopf</td>
<td>normal lesbar</td>
</tr>
<tr>
<td>Thunderbird</td>
<td>Schloss bzw. <strong>S/MIME</strong>-Symbol rechts im Kopf; ein
Klick zeigt „Nachricht ist verschlüsselt“</td>
<td>normal lesbar</td>
</tr>
<tr>
<td>iPhone/iPad</td>
<td><strong>Schloss</strong> neben dem Absender</td>
<td>normal lesbar</td>
</tr>
<tr>
<td>Webmail ohne S/MIME</td>
<td>nur Anhang <code>smime.p7m</code></td>
<td><strong>nicht lesbar</strong> – erwartet, kein Fehler der
Website</td>
</tr>
</tbody>
</table></div>
<p><strong>Fehlerbild:</strong> Leere Mail, nur Anhang
<code>smime.p7m</code>, oder Meldung „Kann nicht entschlüsselt werden“ →
Abschnitt 6.</p>
<h3>6. Wenn es nicht klappt</h3>
<p><strong>„Kann nicht entschlüsselt werden“ / nur
<code>smime.p7m</code> sichtbar</strong></p>
<ul>
<li>Auf <strong>diesem</strong> Gerät bzw. in <strong>diesem</strong>
Programm ist der private Schlüssel nicht installiert. → Abschnitt 4 für
dieses Programm durchgehen. Thunderbird braucht einen eigenen
Import.</li>
<li>Das Programm kann kein S/MIME (z. B. Webmail, Thunderbird für
Android). → anderes Programm nutzen.</li>
<li>Die Website nutzt ein <strong>anderes</strong> Zertifikat als das
installierte (z. B. nach einer Verlängerung). → Vergleichen:
Name/Adresse und Ablaufdatum in KLXM Studio
(<strong>Zustellung</strong>) und im Mailprogramm müssen
übereinstimmen.</li>
</ul>
<p><strong>Zertifikat abgelaufen</strong></p>
<ul>
<li><strong>30 Tage vor Ablauf</strong> zeigt KLXM Studio bei der
Zustellung eine Warnung.</li>
<li>Ist das Zertifikat abgelaufen, verschickt die Website <strong>keine
Inhalte mehr per E-Mail</strong>. Bei „Nur per E-Mail“ wird die Anfrage
stattdessen verschlüsselt im System gesichert und die Administration
gewarnt – es geht also nichts verloren, kommt aber nicht mehr im
Postfach an.</li>
<li>Lösung: neues Zertifikat besorgen und den neuen öffentlichen Teil
eintragen (Abschnitt 3).</li>
<li><strong>Alte Mails</strong> lassen sich mit dem alten privaten
Schlüssel weiterhin öffnen. Deshalb das alte Zertifikat <strong>nicht
löschen</strong> – auch wenn das Mailprogramm es als „abgelaufen“
markiert.</li>
</ul>
<p><strong>Falsche Postfach-Adresse</strong></p>
<ul>
<li>Zertifikat und Empfängeradresse der Website müssen
<strong>exakt</strong> übereinstimmen. Eine Weiterleitung an eine andere
Adresse ist technisch möglich: Die Mail bleibt verschlüsselt und ist
dort nur mit <strong>demselben</strong> Schlüssel lesbar.</li>
<li>Neue Adresse? → neues Zertifikat für diese Adresse.</li>
</ul>
<p><strong>Mehrere Personen oder Geräte lesen dasselbe
Postfach</strong></p>
<ul>
<li>Der <strong>gleiche</strong> private Schlüssel (dieselbe
<code>.p12</code>-Datei) muss auf <strong>jedem</strong> Gerät und in
<strong>jedem</strong> Programm installiert sein. Das ist bei S/MIME
normal.</li>
<li>Alternative: Jede Person hat ein <strong>eigenes</strong> Postfach
mit <strong>eigenem</strong> Zertifikat. Die Website kann an bis zu 10
Adressen senden und für bis zu 5 Zertifikate gleichzeitig verschlüsseln.
Die Empfänger tragen Sie unter <strong>Zustellung</strong> ein.</li>
<li>Jede Installation ist ein weiterer Ort, an dem der Schlüssel liegt.
Installieren Sie ihn nur auf dienstlichen Geräten mit Bildschirmsperre
und Benutzerkonto.</li>
<li>Scheidet jemand aus, der die <code>.p12</code>-Datei und das
Passwort kannte: Zertifikat vorsorglich erneuern.</li>
</ul>
<p><strong>Verlängerung / neues Zertifikat</strong></p>
<ol type="1">
<li>Neues Zertifikat besorgen (Abschnitt 2).</li>
<li>Auf <strong>allen</strong> Geräten installieren (Abschnitt 4).
<strong>Das alte nicht entfernen.</strong></li>
<li>Neuen öffentlichen Teil an die Website geben (Abschnitt 3). Tipp:
Altes und neues Zertifikat können kurz parallel hinterlegt sein, bis
alle Geräte umgestellt sind.</li>
<li>Testmail senden (Abschnitt 5).</li>
<li>Alte <strong>und</strong> neue <code>.p12</code>-Datei samt Passwort
sicher aufbewahren.</li>
</ol>
<p><strong>Archivierung und Aufbewahrung</strong></p>
<ul>
<li>Verschlüsselte Mails bleiben <strong>auch im Archiv
verschlüsselt</strong> – im Postfach, in Backups und in
Mail-Archivsystemen.</li>
<li>Lesbar sind sie nur, solange der passende private Schlüssel
vorhanden ist. Ist der Schlüssel weg, ist der Inhalt <strong>dauerhaft
verloren</strong>. Auch die Website-Betreuung kann ihn nicht
wiederherstellen.</li>
<li>Für Arztpraxen: Für die ärztliche Dokumentation gelten lange
Aufbewahrungsfristen (in der Regel <strong>mindestens 10 Jahre</strong>
nach Abschluss der Behandlung, § 630f Abs. 3 BGB; teils länger). Ob und
welche Anfragen dazugehören, entscheiden Sie.</li>
<li><strong>Unsere Empfehlung:</strong>
<ul>
<li>Übernehmen Sie dokumentationspflichtige Inhalte in Ihre
<strong>Fachsoftware</strong> (in Arztpraxen: das
Praxisverwaltungssystem, PVS). Dort sind sie unabhängig vom
Mail-Schlüssel gesichert.</li>
<li>Bewahren Sie <strong>alle</strong> privaten Schlüssel (auch
abgelaufene) mindestens so lange auf wie die zugehörigen Mails.</li>
<li>Nutzen Sie ein <strong>Mail-Archivsystem</strong> (z. B. für die
GoBD): Klären Sie mit dem Anbieter, ob es verschlüsselte Mails
speichert, ob es sie mit Ihrem Schlüssel entschlüsselt ablegt und wie
das datenschutzrechtlich geregelt ist.</li>
<li>Stimmen Sie das Vorgehen mit Ihrer/Ihrem
<strong>Datenschutzbeauftragten</strong> ab.</li>
</ul></li>
</ul>
<h3>7. Checkliste für den Start</h3>
<p><strong>Bei Ihnen</strong></p>
<ul class="task-list">
<li><label><input type="checkbox" />Empfänger-Postfach festgelegt
(genaue Adresse): ______________________</label></li>
<li><label><input type="checkbox" />E-Mail-Zertifikat (S/MIME) für genau
diese Adresse bestellt und erhalten</label></li>
<li><label><input type="checkbox" /><code>.p12</code>/<code>.pfx</code>
und Passwort <strong>getrennt</strong> und sicher gespeichert,
<strong>Sicherung</strong> an zweitem Ort</label></li>
<li><label><input type="checkbox" />Ablaufdatum notiert: ____________ ·
Erinnerung 4 Wochen vorher im Kalender</label></li>
<li><label><input type="checkbox" />Zertifikat auf
<strong>allen</strong> Lesegeräten und in <strong>allen</strong>
Mailprogrammen installiert:</label>
<ul class="task-list">
<li><label><input type="checkbox" />Arbeitsplatz 1:
______________________</label></li>
<li><label><input type="checkbox" />Arbeitsplatz 2:
______________________</label></li>
<li><label><input type="checkbox" />weitere:
______________________</label></li>
</ul></li>
<li><label><input type="checkbox" />Zertifikat mit
<strong>RSA-Schlüssel</strong> (nicht ECC)</label></li>
<li><label><input type="checkbox" />Öffentlicher Teil als
<strong>PEM/Base-64</strong> exportiert und geprüft (beginnt mit
<code>-----BEGIN CERTIFICATE-----</code>, kein „PRIVATE
KEY“)</label></li>
<li><label><input type="checkbox" />Öffentlicher Teil hochgeladen
<strong>oder</strong> an die Website-Betreuung geschickt</label></li>
<li><label><input type="checkbox" />Testmail auf jedem Gerät geöffnet:
Schloss sichtbar, Inhalt lesbar</label></li>
<li><label><input type="checkbox" />Wer liest die Anfragen? Vertretung
geregelt (Urlaub, Krankheit)</label></li>
<li><label><input type="checkbox" />Umgang mit Aufbewahrung/Archiv
geklärt (PVS, Archivanbieter, Datenschutzbeauftragte)</label></li>
</ul>
<p><strong>Was Ihre Website-Betreuung (z. B. KLXM) von Ihnen
braucht</strong></p>
<ol type="1">
<li>Die <strong>Empfängeradresse</strong> für die Anfragen.</li>
<li>Den <strong>öffentlichen Teil</strong> des Zertifikats als
<code>.pem</code> oder Base-64-<code>.cer</code> (nicht die
<code>.p12</code>/<code>.pfx</code>!).</li>
<li>Eine <strong>Ansprechperson</strong> bei Ihnen oder Ihrer IT für den
Test.</li>
<li>Die <strong>Rückmeldung nach der Testmail</strong>: auf welchen
Geräten lesbar, wo nicht.</li>
<li>Später: rechtzeitig vor Ablauf den <strong>neuen öffentlichen
Teil</strong>.</li>
</ol>
<p>Die Website-Betreuung braucht <strong>nie</strong>: die
<code>.p12</code>/<code>.pfx</code>-Datei, deren Passwort oder Zugang
zum Empfangspostfach.</p>
<h3>Anhang: Stand,
Versionen und offene Punkte</h3>
<p><strong>Beschriebene Versionen (Stand September 2026):</strong>
Windows 10/11; Outlook klassisch (Microsoft 365 Apps, Outlook
2021/2024); neues Outlook für Windows; macOS 15 Sequoia und 26 Tahoe mit
Apple Mail; Outlook für Mac (Version 16); Thunderbird 128/140 ESR und
aktuelle Versionen; Evolution 3.5x; iOS/iPadOS 18 und 26; Android mit
FairEmail/R2Mail2.</p>
<p><strong>Punkte, die wir nicht abschließend prüfen
konnten:</strong></p>
<ul>
<li><strong>Neues Outlook für Windows mit IMAP-Konten:</strong>
Microsoft nennt S/MIME nur für das primäre (Microsoft-365-)Konto. Ob
IMAP-Konten inzwischen unterstützt werden, bitte am Gerät prüfen.</li>
<li><strong>Outlook für Mac mit IMAP-Konten:</strong> Die Einrichtung
ist dokumentiert; ob verschlüsselte Mails in IMAP-Konten zuverlässig
geöffnet werden, bitte mit der Testmail prüfen.</li>
<li><strong>Genaue Menünamen</strong> (v. a.
Thunderbird-Zertifikatsansicht, iOS-Einstellungen, Outlook für Mac)
ändern sich mit Updates leicht.</li>
<li><strong>Anbieter-Angebote</strong> (insbesondere kostenlose
Zertifikate, Laufzeiten) können sich jederzeit ändern.</li>
<li><strong>Android-Apps:</strong> Funktionsumfang und Pflege von
FairEmail und R2Mail2 bitte vor dem Einsatz prüfen.</li>
</ul>
<p><strong>Quellen (Auswahl):</strong> Microsoft Support „Outlook für
die Verwendung von S/MIME-Verschlüsselung einrichten“ und „Digital
signierte oder verschlüsselte Nachricht senden (Mac)“; Microsoft Learn
„S/MIME for Outlook for iOS and Android“; Apple Support 102245 (S/MIME
in iOS Mail); Mozilla Support (Thunderbird
Ende-zu-Ende-Verschlüsselung); GNOME Evolution Hilfe „Managing S/MIME
certificates“; Google Workspace Hilfe „Hosted S/MIME“; CA/Browser Forum
S/MIME Baseline Requirements; Actalis, Certum, GlobalSign, D-Trust
(Produktseiten); KBV „E-Mail-Dienst KIM“; Thunderbird Blog „Mobile
Progress Report“ (2026).</p>
