<?php /** Handbuch · Kapitel „Eigene Daten“ · @var callable $anchor */ ?>
  <p class="lead">Unter <a href="<?= e(url('/admin/data')) ?>">Daten</a> legen Sie eigene Inhaltstypen an – etwa Aktuelles, Team, Produkte, Termine oder Fragen &amp; Antworten. Ohne Programmieren, mit eigener Übersicht, Detailseiten und Ausgabe an beliebiger Stelle der Website.</p>

  <h3 id="assistent">1. Tabelle oder Formular anlegen – mit dem Assistenten <small>(Recht „Tabellen und Felder ändern“, Standard: Administration)</small></h3>
  <p><b>Daten → + Neue Tabelle oder Formular</b> öffnet einen Assistenten in vier Schritten. Er fragt zuerst, <em>was</em> Sie anlegen möchten, und richtet die passenden Grundeinstellungen ein – so müssen Sie sich nicht durch alle Einstellungen arbeiten.</p>
  <ol class="doc-steps">
    <li><b>Art wählen</b> – eine Karte anklicken:
      <table class="doc-table">
        <tr><th>Art</th><th>Wofür</th><th>Was der Assistent einstellt</th></tr>
        <tr><td><b>Inhalte auf der Website</b></td><td>Aktuelles, Team, Termine, Verzeichnis, Produkte</td><td>Liste auf einer Seite, auf Wunsch eine Detailseite je Eintrag (mit Vorlage), Website-Suche</td></tr>
        <tr><td><b>Formular, das nur eine E-Mail schickt</b></td><td>Kontakt, Rückrufbitte</td><td>Eingang mit Zustellung „Nur per E-Mail“: Jede Einsendung geht mit vollem Inhalt an die angegebenen Adressen, auf der Website wird nichts gespeichert (nur ein Zustellprotokoll ohne Inhalte). Keine Detailseite, kein Eingang.</td></tr>
        <tr><td><b>Anfragen sammeln &amp; bearbeiten</b></td><td>Terminwunsch, Rezeptbestellung, Beratung</td><td>Verschlüsselter Eingang unter „Anfragen“ mit Status, Benachrichtigung ohne Inhalte und Löschfrist; auf Wunsch zusätzlich per E-Mail</td></tr>
        <tr><td><b>Anmeldung oder Bewerbung</b></td><td>Kurse, Veranstaltungen, Bewerbungen</td><td>Öffentliches Formular, Einträge als Entwurf = Teilnehmerliste in der Verwaltung, Bestätigung an die Absender, optional <b>Obergrenze</b> (danach zeigt das Formular „ausgebucht“). Nicht in Suche und Sitemap. Bewerbungen sind ein verschlüsselter Eingang.</td></tr>
        <tr><td><b>Interne Liste</b></td><td>Kontakte, Inventar, Aufgaben</td><td>Nur in der Verwaltung: keine Detailseiten, kein Formular, nicht in Suche und Sitemap</td></tr>
        <tr><td><b>Aus externer Quelle</b></td><td>Feed, API, OpenImmo</td><td>führt zu <b>Externe Quellen</b> (eigener Ablauf)</td></tr>
      </table>
      Ausgegraute Arten brauchen eine Funktion, die auf dieser Website aus ist (Funktionen &amp; Erweiterungen). Mit eingeschalteter KI können Sie stattdessen <b>beschreiben, was Sie brauchen</b> – KLXM AI schlägt Art und Felder vor, Sie prüfen alles im nächsten Schritt.</li>
    <li><b>Vorlage wählen</b> – passend zur Art (z. B. „Kontakt“ und „Rückrufbitte“ für E-Mail-Formulare) oder <b>Leer beginnen</b>.</li>
    <li><b>Grundeinstellungen</b>: Name, Felder (übernehmen oder abwählen, umbenennen, Typ, Pflicht, mit ↑ ↓ ordnen, neue ergänzen) und – je nach Art – Detailseite ja/nein, Empfänger der E-Mail, Bestätigung an die Absender, Löschfrist, Obergrenze, Push. Der Spamschutz ist immer an. <b>Anlegen</b>.</li>
    <li><b>Einsetzen</b> – siehe <a href="#<?= e($anchor('einsetzen')) ?>">Formular oder Liste einsetzen</a>.</li>
  </ol>
  <p>Wer lieber alles selbst einstellt, nimmt oben <b>Ohne Assistent (Expertenmodus)</b>. Felder lassen sich später jederzeit unter <b>Felder &amp; Einstellungen</b> ergänzen, umbenennen oder entfernen (beim Entfernen fragt das System nach, weil Inhalte verloren gehen).</p>

  <h3 id="einsetzen">Formular oder Liste einsetzen</h3>
  <p>Eine Tabelle erscheint erst auf der Website, wenn ein <b>Block</b> sie auf einer Seite zeigt: Formulare (E-Mail, Anfragen, Anmeldungen) über den Block <b>„Formular (Datentabelle)“</b>, Inhalte über den Block <b>„Datenliste“</b>. Der Schritt <b>Einsetzen</b> – später unter <b>Daten → Tabelle → Einsetzen</b> bzw. im Bereich „Einsetzen“ der Einstellungen – nimmt Ihnen das ab:</p>
  <ul>
    <li><b>Neue Seite anlegen</b>: Titel, Platz im Seitenbaum („Unterhalb von“ über <b>Auswählen …</b>), „Im Menü zeigen“ und auf Wunsch gleich veröffentlichen. Die Seite enthält den passenden Block mit der richtigen Tabelle; bei Inhalten mit Detailseiten entsteht auf Wunsch auch die Detailseiten-Vorlage.</li>
    <li><b>In bestehende Seite einfügen</b>: Seite über <b>Auswählen …</b> wählen – der Block kommt ans Ende (als Entwurf, auf Wunsch gleich veröffentlicht). Im Editor verschieben Sie ihn an die richtige Stelle.</li>
    <li><b>Später selbst</b>: Seite bearbeiten → „+“ → Block „Formular (Datentabelle)“ bzw. „Datenliste“ → in der Seitenleiste bei <b>Tabelle</b> die Tabelle wählen → veröffentlichen.</li>
  </ul>
  <p>Unter „Hier wird … verwendet“ sehen Sie jederzeit, auf welchen Seiten die Tabelle vorkommt. Welche Felder das Formular zeigt, wohin Einsendungen gehen und ob eine Bestätigung verschickt wird, stellen Sie bei der Tabelle ein – nicht im Block.</p>

  <h3 id="einstellungen">Felder &amp; Einstellungen</h3>
  <p>Die Einstellungen einer Tabelle sind wie die Grundeinstellungen in Bereiche gegliedert (links, auf dem Telefon als Auswahl oben): <b>Allgemein</b> (Name, Symbol, Zweck), <b>Felder</b>, <b>Auf der Website</b> (Detailseiten, Darstellung, Suchmaschinen), <b>Formular</b> bzw. <b>Formular &amp; Eingang</b> (Zustellung, Datenschutz), <b>Benachrichtigungen</b> (E-Mail an die Redaktion, Bestätigung an die Absender, Push), <b>Suche</b>, <b>Kalender</b>, <b>Einsetzen</b> und <b>Erweitert</b> (Sortierung, Freigabe, Löschen). Welche Bereiche oben stehen, richtet sich nach dem <b>Zweck</b> (Allgemein → „Wofür ist diese Tabelle?“); die übrigen finden Sie unter „Weitere Bereiche“. Bei älteren Tabellen wird der Zweck aus den Einstellungen abgeleitet und beim nächsten Speichern übernommen. Ein Klick auf <b>Speichern</b> sichert alle Bereiche.</p>

  <h3>Verknüpfungen – echte Beziehungen zwischen Tabellen</h3>
  <p>Mit den Feldtypen <b>Verknüpfung (ein Eintrag)</b> und <b>Verknüpfung (mehrere)</b> verbinden Sie Tabellen miteinander – z. B.:</p>
  <table class="doc-table">
    <tr><th>Beispiel</th><th>Feld in „Aktuelles“</th><th>Ergebnis</th></tr>
    <tr><td>Kategorien</td><td>Kategorie → Tabelle „Kategorien“ (ein Eintrag)</td><td>Jede Kategorie hat eine eigene Seite mit ihren Beiträgen.</td></tr>
    <tr><td>Schlagworte</td><td>Schlagworte → Tabelle „Schlagworte“ (mehrere)</td><td>Beiträge erscheinen auf jeder Themenseite, Schlagworte als klickbare Etiketten.</td></tr>
    <tr><td>Autor/in</td><td>Autor/in → Tabelle „Team“ (ein Eintrag)</td><td>Auf der Seite einer Person stehen „Beiträge von …“.</td></tr>
  </table>
  <ul>
    <li>Im Formular wählen Sie verknüpfte Einträge aus – oder legen mit <b>+ … neu</b> direkt einen neuen an (z. B. ein neues Schlagwort), ohne die Seite zu verlassen.</li>
    <li>Rechts im Formular zeigt <b>Verknüpft mit</b>, wo ein Eintrag verwendet wird (z. B. alle Beiträge einer Person).</li>
    <li>Wird ein Eintrag gelöscht, verschwinden die Verknüpfungen automatisch – es bleiben keine toten Verweise.</li>
    <li>In der <b>Datenliste</b> filtern Sie nach Verknüpfungen: „Kategorie ist gleich Neuigkeiten“ – oder auf Detailseiten mit <b>verknüpft mit dem aufgerufenen Eintrag</b> (Beiträge dieser Person / dieser Kategorie) bzw. <b>gleich wie beim aufgerufenen Eintrag</b> plus „Aufgerufenen Eintrag ausblenden“ (= „Weitere Beiträge aus dieser Kategorie“).</li>
  </ul>

  <h3>2. Einträge pflegen</h3>
  <p>Links im Menü unter <b>Daten</b> steht jede Tabelle einzeln. Die Liste lässt sich durchsuchen, nach Spalten sortieren und – bei manueller Sortierung – per Ziehen ordnen. Mehrere Einträge markieren: gemeinsam online stellen, auf Entwurf setzen oder löschen. Einzeln geht es schneller über die Spalte <b>Status</b> (bei Tabellen mit Freigabe): Klick auf <b>Online</b> nimmt den Eintrag nach einer Rückfrage offline, Klick auf <b>Offline</b> oder <b>Entwurf</b> stellt ihn online – ohne Neuladen, auch per <kbd>Tab</kbd> und <kbd>Enter</kbd>. <b>Offline</b> heißt: war schon online, Besucher sehen ihn nicht mehr (Listen, Detailseite, Sitemap, Suche), der Inhalt bleibt erhalten. Den Knopf gibt es nur mit dem Recht „Veröffentlichen“ für die Tabelle und nur für eigene Einträge (nicht für Einträge anderer Websites oder aus externen Quellen). <b>Speichern &amp; neu</b> beschleunigt das Anlegen mehrerer Einträge. Auf mehrsprachigen Websites legt „+ English“ im Eintrag eine verknüpfte Übersetzung an. Unter <b>Felder &amp; Einstellungen</b> stellen Sie außerdem Listenbild, Einträge je Seite und die <b>Suche</b> für diese Tabelle ein (siehe <a href="#<?= e($anchor('suche')) ?>">Website-Suche</a>).</p>

  <h3 id="versionen">Versionen von Einträgen</h3>
  <p>Jedes Speichern eines Eintrags legt eine Version an – in der Verwaltung, direkt auf der Website, über API und MCP; beim ersten Ändern
    wird zusätzlich der bisherige Stand gesichert (die letzten <?= (int) app()->config->get('revisions', 20) ?> Stände je Eintrag).
    <b>Versionen</b> oben im Eintrag bzw. auf der Detailseite <b>⋯ → Versionen</b> zeigt links die Stände als Zeitleiste (mit den jeweils geänderten Feldern), rechts den gewählten Stand;
    geänderte Felder sind markiert, „Nur Änderungen“ blendet den Rest aus. <b>Wiederherstellen</b> setzt die Felder sofort auf den
    gewählten Stand (der Status bleibt) – der bisherige Stand bleibt als Version erhalten. <b>Änderungen hervorheben</b> markiert die Felder, die sich gegenüber dem vorherigen Stand geändert haben; <b>Gegenüberstellen</b> zeigt links den jetzigen Eintrag.</p>

  <h3>3. Auf der Website ausgeben</h3>
  <ol class="doc-steps">
    <li>Seite im Editor öffnen → <b>+</b> → Block <b>Datenliste</b>.</li>
    <li>Tabelle wählen, <b>angezeigte Felder</b> ankreuzen, Darstellung wählen: Karten, Liste mit Bild, kompakte Liste, Tabelle oder <b>Verzeichnis</b>.</li>
    <li>Optional filtern (z. B. nur Kategorie „Neuigkeiten“ oder nur Termine ab „heute“), sortieren, Anzahl begrenzen, seitenweise blättern, Button „Alle Beiträge“.</li>
  </ol>
  <p><b>Verzeichnis (Mitglieder, Partner, Anbieter):</b> Die Darstellung <b>Verzeichnis</b> zeigt das Bild ganz – ideal für Logos – auf einer hellen Kachel; Auswahl- und Mehrfachauswahl-Felder (z. B. Schwerpunkt) erscheinen als farbige <b>Etiketten</b>. Unter <b>Bilder</b> können Sie in jeder Darstellung zwischen „Fläche füllen“ (Fotos) und „Ganz zeigen auf einer Kachel“ (Logos) wählen. Unter <b>Filter für Besucher</b> schalten Sie bis zu zwei Reihen <b>Filter-Schaltflächen</b> (je ein Auswahlfeld, z. B. Schwerpunkt und Region), ein <b>Suchfeld</b> und eine <b>A–Z-Sprungleiste</b> (alphabetisch gruppiert, nur ohne seitenweises Blättern) ein. Die Auswahl steht in der Adresse (z. B. <code>?kategorie=seminare</code>) und lässt sich teilen; alles funktioniert ohne JavaScript.</p>
  <p><b>Detailseite eines Verzeichnisses:</b> Im Block <b>Datensatz-Felder</b> zeigt die Darstellung <b>Profil</b> Logo, Name (als Hauptüberschrift), Etiketten und Kurztext; die <b>Kontaktkarte</b> fasst Ansprechperson und Adresse zusammen (Postleitzahl und Ort in einer Zeile), Telefon, E-Mail und Website als Links mit Symbol und – wenn die Tabelle ein Feld „Ort (Karte)“ hat – eine Karte. Mit dem Block <b>Layout</b> stehen Beschreibung und Kontaktkarte nebeneinander; darunter eine Datenliste „Weitere Mitglieder“ mit „gleich wie beim aufgerufenen Eintrag“ und „Aufgerufenen Eintrag ausblenden“.</p>
  <p><b>Lange Texte kürzen:</b> Bringt eine Tabelle sehr lange Texte mit – etwa Nachrichten aus einem RSS-Feed –, stellen Sie in der Datenliste unter <b>Textlänge</b> „2 Zeilen“, „3 Zeilen“, „4 Zeilen“ oder „6 Zeilen“ ein; <b>Titel kürzen</b> begrenzt lange Überschriften auf 2 oder 3 Zeilen. Das gilt für Karten und Listen (nicht für die Tabelle). Gekürzte Texte erscheinen ohne Formatierung (keine Aufzählungen, Bilder oder Zwischenüberschriften) und enden mit „…“; der vollständige Text steht auf der Detailseite, zu der Titel und Karte verlinken. Standard ist „vollständig“ – bestehende Listen bleiben unverändert.</p>

  <h3>4. Detailseite gestalten</h3>
  <p><b>Daten → Tabelle → Detailseite gestalten</b> öffnet die Vorlage im Editor – mit den echten Inhalten eines Eintrags. Die Vorlage gilt für <b>alle</b> Einträge (oben steht „Vorlage · Aktuelles“, daneben wählen Sie unter <b>Vorschau mit</b> einen anderen Eintrag zum Prüfen).</p>
  <ol class="doc-steps">
    <li>Beliebige Blöcke einfügen – z. B. „Text + Bild“, „Bild breit“, „Kopfbereich“.</li>
    <li>Block anklicken: Neben jedem passenden Feld steht das Ketten-Symbol <b><?= icon('link', ['label' => 'Kette']) ?></b>. Anklicken und das Feld des Eintrags wählen – z. B. <b>Bild</b> → „Bild“, <b>Überschrift</b> → „Titel“, <b>Text</b> → „Teaser“ oder „Text“, <b>Dachzeile</b> → „Datum“, <b>Button-Link</b> → „Adresse dieser Detailseite“.</li>
    <li>Es werden nur passende Felder angeboten (für ein Bildfeld nur Bilder, für ein Datum nur Datumsfelder). Verknüpfte Stellen sind in der Vorschau gestrichelt umrandet.</li>
    <li>Nochmals auf <b><?= icon('link', ['label' => 'Kette']) ?></b> klicken hebt die Verknüpfung auf – dann gilt wieder der fest eingetragene Inhalt.</li>
  </ol>
  <ul>
    <li>Block <b>Datensatz-Felder</b>: gibt mehrere Felder auf einmal aus (Kopf, Fließtext, Steckbrief).</li>
    <li>Block <b>Datenliste</b> auf der Vorlage: „verknüpft mit dem aufgerufenen Eintrag“ für „Beiträge dieser Person“ oder „Weitere Beiträge aus dieser Kategorie“.</li>
    <li>Für Fortgeschrittene: In Texten funktionieren auch Platzhalter wie <code>{{titel}}</code> – etwa „Beiträge von {{name}}“.</li>
  </ul>
  <?php include dirname(__DIR__) . '/_entry_edit.php'; ?>

  <div class="doc-note doc-note--info"><strong>Suchmaschinen</strong><p>Detailseiten erhalten automatisch Titel, Beschreibung (aus dem gewählten Beschreibungs-Feld), Vorschaubild und stehen in der Sitemap. Einträge im Entwurf sind nur für Angemeldete sichtbar.</p></div>

  <h3 id="bedingungen">Felder, die nur manchmal gebraucht werden (Bedingungen)</h3>
  <p>Im Tabellen-Designer hat jedes Feld den aufklappbaren Bereich <b>Bedingungen</b>. Dort formulieren Sie Sätze wie „Feld nur anzeigen, wenn <b>Anrede</b> <b>ist gleich</b> <b>Firma</b>“.</p>
  <ul>
    <li><b>Feld nur anzeigen, wenn …</b> – sonst ist das Feld im Formular ausgeblendet; ein Wert darin wird beim Speichern verworfen. Mehrere Bedingungen: „alle treffen zu“ oder „eine davon trifft zu“.</li>
    <li><b>Pflichtfeld, wenn …</b> – z. B. IBAN nur bei Zahlungsart „Lastschrift“ verpflichtend.</li>
    <li><b>Wert vergleichen</b> (Datum, Uhrzeit, Zahl, Text) – z. B. „Dieser Wert muss gleich oder später sein als Beginn“, mit eigener Fehlermeldung wie „Ende muss nach Beginn liegen“.</li>
  </ul>
  <p>Die Regeln gelten überall: im Eintragsformular, im Website-Formular und auch für Einträge, die über Schnittstellen oder Kalender-Apps kommen.</p>

  <h3 id="iban">Bankverbindung (IBAN)</h3>
  <p>Der Feldtyp <b>IBAN</b> prüft Ländercode, Länge und Prüfziffer – Tippfehler fallen sofort auf. Die Eingabe wird automatisch in Vierergruppen gegliedert. Mit „In Listen maskieren“ (Standard) zeigen Liste und Website nur z. B. <code>DE89 **** **** **** **** 00</code>; die vollständige IBAN sehen Sie im Eintrag.</p>

  <h3 id="gruppe">Mehrere gleichartige Angaben (Wiederholbare Gruppe)</h3>
  <p>Der Feldtyp <b>Wiederholbare Gruppe</b> fasst mehrere Unterfelder zu einer Zeile zusammen, die sich im Formular beliebig oft hinzufügen lässt – z. B. mehrere Medikamente mit Stärke und Packungsgröße oder mehrere Ansprechpersonen. Im Tabellen-Designer legen Sie die Unterfelder (Text, Zahl, Auswahl, Datum, E-Mail, Telefon, Ja/Nein, IBAN …), die Mindest- und Höchstzahl sowie die Beschriftungen („Medikament“, „+ Weiteres Medikament“) fest. Leere Zeilen werden beim Speichern ignoriert; in der Liste steht eine Kurzfassung wie „Ibuprofen, Paracetamol +1“.</p>

  <h3 id="ort">Orte auf der Karte</h3>
  <p>Der Feldtyp <b>Ort (Karte)</b> speichert einen Standort: Adresse suchen oder in die Karte klicken. Auf Detailseiten lässt sich der Block <b>Karte</b> per Kette <?= icon('link', ['label' => 'Kette']) ?> an dieses Feld binden; in Kalender-Tabellen dient es als Veranstaltungsort.</p>

  <h3 id="formular">Besucher legen Einträge an (Formular)</h3>
  <ol class="doc-steps">
    <li><b>Daten → Tabelle → Felder</b>, rechts <b>Öffentliches Formular</b> einschalten, Felder wählen und festlegen, ob neue Einträge als <b>Entwurf</b> (Sie prüfen und veröffentlichen) oder sofort online erscheinen.</li>
    <li>Optional: bis zu fünf E-Mail-Adressen für Benachrichtigungen – die Mail enthält keine Angaben der Besucher, nur einen Link zum neuen Eintrag.</li>
    <li>Seite öffnen → <b>+</b> → Block <b>Formular (Datentabelle)</b> → Tabelle wählen.</li>
    <li>Optional: <b>Dachzeile</b>, <b>Überschrift</b> und <b>Einleitung</b> über dem Formular. Unter <b>Darstellung</b> legen Sie die <b>Breite</b> fest – <b>Textbreite</b> (Standard: in derselben Spalte wie die Fließtexte darüber), <b>Normal</b> (schmales Formular im Inhaltsbereich) oder <b>Volle Breite</b> – und die <b>Ausrichtung</b>: <b>Linksbündig</b> (Standard) oder <b>Mittig</b> (Überschrift, Einleitung und Button zentriert).</li>
  </ol>
  <h4 id="felder-im-editor">Felder direkt auf der Seite ändern</h4>
  <p>Mit dem Recht „Tabellen und Felder ändern“ (Standard: Administration) zeigt die Leiste des Blocks im Bearbeitungsmodus zusätzlich <b>Felder bearbeiten</b>. Es öffnet rechts eine Seitenleiste mit den Feldern der gewählten Tabelle – Sie müssen die Seite dafür nicht verlassen:</p>
  <ul>
    <li>je Feld <b>Bezeichnung</b>, <b>Kurzname</b> (bei neuen Feldern automatisch aus der Bezeichnung), <b>Typ</b>, <b>Pflichtfeld</b>, <b>Halbe Breite</b> und <b>Hilfetext</b>; bei Auswahlfeldern die Auswahlmöglichkeiten, bei Dateifeldern die <b>erlaubten Dateitypen</b> und die <b>Höchstgröße</b>,</li>
    <li>Reihenfolge mit <b>↑ ↓</b>, <b>✕</b> entfernt ein Feld (Rückfrage direkt in der Zeile), <b>Feld hinzufügen</b> für alle Typen, die Besucher ausfüllen können,</li>
    <li>unter <b>Formular</b>: Formular an/aus, <b>Felder im Formular</b>, <b>Text nach dem Absenden</b> und <b>Beschriftung des Buttons</b> (bei Inhaltstabellen auch Datei-Uploads). Neu hinzugefügte Felder kommen beim Speichern automatisch ins Formular. Texte, die im Block selbst eingetragen sind, haben Vorrang.</li>
  </ul>
  <p><b>Speichern</b> (oder <kbd>Strg</kbd>/<kbd>⌘</kbd>+<kbd>S</kbd>) prüft alles wie im Tabellen-Designer und aktualisiert das Formular auf der Seite sofort – ohne Neuladen. Fehler stehen oben in der Leiste und am betroffenen Feld. Löschen Sie ein Feld, das schon Inhalte hat, fragt die Leiste nach („Felder wirklich löschen“); ändern Sie den Typ eines Feldes, dessen Tabelle schon Einträge hat, weist sie darauf hin, dass die Inhalte umgewandelt werden. Die Änderungen gelten für die Tabelle – also für jedes Formular und jede Liste, die sie nutzt. Alles Weitere (Bedingungen, Übersetzungen, Unterfelder von Gruppen, bei Eingängen Zustellung und Verschlüsselung) finden Sie über <b>Alle Einstellungen der Tabelle</b>. Bei Eingangs-Tabellen gelten dieselben Grenzen wie dort: keine Bilder, Verknüpfungen oder formatierten Texte, Dateifelder nur mit Zustellung per E-Mail.</p>
  <p>Tipp: Folgen Fließtext und Formular mit derselben Hintergrundfarbe aufeinander (z. B. Stellenanzeige mit Bewerbungsformular), rückt das Kit die Abschnitte enger zusammen – so wirkt die Seite wie aus einem Guss.</p>
  <p>Das Formular ist mehrstufig gegen Spam geschützt und setzt keine Cookies. Besucher bestätigen immer den Datenschutzhinweis. Datei-Uploads (Bildfelder: JPG, PNG, WebP; Dateifelder: PDF und/oder Bilder; 1–10 MB) sind nur möglich, wenn Sie sie ausdrücklich erlauben; hochgeladene Dateien liegen in der Mediathek (Tag „formular“) und sind über ihre Adresse öffentlich erreichbar.</p>
  <p><b>Erlaubte Dateitypen je Dateifeld:</b> Beim Feld vom Typ „Datei“ wählen Sie unter <b>Erlaubte Dateitypen</b>, was Besucher hochladen dürfen: PDF, Bilder (JPG, PNG, WebP) und – nur in Eingangs-Tabellen mit Zustellung per E-Mail – Word (DOCX) und OpenDocument-Text (ODT). Standard: PDF und Bilder. Unter <b>Höchstgröße</b> können Sie für das Feld eine kleinere Grenze als die der Tabelle setzen (leer = wie Tabelle). Das Formular zeigt unter dem Feld einen Hinweis wie „PDF oder Bild (JPG, PNG, WebP), höchstens 5 MB.“ – als eigene Zeile unter Ihrem Hilfetext. Geprüft wird der <b>Inhalt</b> der Datei, nicht nur die Endung: Ein umbenanntes Programm („rechnung.pdf“) oder ein Word-Dokument mit Makros wird abgelehnt. Pro Feld ist eine Datei möglich.</p>
  <div class="doc-note doc-note--important"><strong>Keine vertraulichen Daten</strong><p>Einträge aus diesem Formular werden nicht verschlüsselt gespeichert. Für Gesundheitsdaten oder ähnlich Vertrauliches einen verschlüsselten Eingang verwenden (siehe „Anfragen“).</p></div>

  <h3 id="termine">Termine und Wiederholungen</h3>
  <p>Ist eine Tabelle als <b>Kalender</b> eingerichtet (z. B. „Termine“), tragen Sie bei jedem Termin <b>Beginn</b> und – falls bekannt – <b>Ende</b> ein. Für Schließtage oder Ferien setzen Sie <b>Ganztägig</b>; dann zählt nur das Datum (Ende = letzter Tag).</p>
  <ol class="doc-steps">
    <li>Unter <b>Wiederholung</b> wählen Sie, wie oft der Termin stattfindet: täglich, wöchentlich (Wochentage anklicken, z. B. Mo und Mi), monatlich (am 15. oder z. B. „am zweiten Dienstag“) oder jährlich. „Intervall: alle 2 Wochen“ ergibt einen 14-täglichen Termin.</li>
    <li>Bei <b>Endet</b> legen Sie fest: nie, an einem Datum oder nach einer Anzahl von Terminen.</li>
    <li>Fällt ein einzelner Termin aus (Feiertag, Urlaub), fügen Sie das Datum unter <b>Ausnahmen</b> hinzu – nur dieser Tag entfällt.</li>
    <li>Die Zeile darunter fasst die Regel zusammen, z. B. „Jeden Montag und Mittwoch bis 31.12.2026“ – so sehen Sie vor dem Speichern, ob alles stimmt.</li>
  </ol>
  <p>In der Verwaltung schalten Sie über <b>Liste | Kalender</b> auf eine Monatsübersicht um: Serientermine stehen an jedem Tag, Entwürfe sind gestrichelt markiert. Ein Klick auf einen Termin öffnet ihn (bei Serien den ganzen Serientermin), ein Klick auf einen freien Tag legt dort einen neuen Termin an.</p>
  <p>Auf der Website zeigen die Blöcke <b>Kalender</b> (Monatsübersicht oder Terminliste) und <b>Nächste Termine</b> jeden Termin einzeln an. Besucher können den Kalender über <b>„Kalender abonnieren“</b> in ihre Kalender-App übernehmen; Änderungen erscheinen dort automatisch nach einigen Stunden. „Erweitert“ unter der Wiederholung ist für Sonderfälle gedacht und muss normalerweise nicht angefasst werden.</p>

  <?php if (\Core\Extensions::isActive('dav') && \Core\Features::on('dav')): ?>
  <h3 id="apps">Termine und Kontakte in Kalender-Apps</h3>
  <p>Unter <b>Konto → Kalender &amp; Kontakte in Apps</b> erzeugen Sie ein <b>App-Passwort</b> (nur einmal sichtbar, „Lesen und ändern“ oder „Nur lesen“). Damit verbinden Sie Apple Kalender/Kontakte, Thunderbird oder DAVx⁵ (Android) mit der Website: Kalender-Tabellen erscheinen als Kalender, freigegebene Tabellen als Adressbuch. Benutzername ist Ihre E-Mail-Adresse, Server die Adresse der Website. Was Sie in der App ändern, landet im CMS – und umgekehrt. Löschen in der App setzt einen Eintrag normalerweise nur auf Entwurf.</p>
  <?php endif; ?>

  <h3 id="geteilt">Geteilte Tabellen (mehrere Websites)</h3>
  <p>Manche Tabellen tragen in der Daten-Navigation den Hinweis <b>geteilt</b>. Sie gehören zu mehreren Websites derselben Installation – zum Beispiel Neuigkeiten oder Termine, die eine Dachorganisation und ihre Mitglieder gemeinsam nutzen. Die Felder legt die Website fest, der die Tabelle gehört; jede Website pflegt <b>nur ihre eigenen Einträge</b>.</p>
  <ul class="doc-list">
    <li><b>Eigene</b> – Ihre Einträge, wie bei jeder Tabelle: anlegen, ändern, veröffentlichen.</li>
    <li><b>Vom …</b> / <b>Von …</b> – Einträge der anderen Websites. Sie sind nur lesbar; Sie können sie für Ihre Website <b>ausblenden</b> oder <b>hervorheben</b>. „Auf Ursprungs-Website öffnen“ zeigt das Original.</li>
    <li><b>Vorschlagen</b> – im Eintrag setzen Sie ein Häkchen bei „… vorschlagen“. Die Website, der die Tabelle gehört, sieht den Eintrag unter <b>Vorschläge</b> und kann ihn <b>übernehmen</b>, <b>hervorheben</b> oder <b>ablehnen</b>. Unter dem Häkchen sehen Sie, wie entschieden wurde.</li>
    <li><b>Anzeige auf dieser Website</b> – hier legen Sie fest, welche fremden Einträge Ihre Website zeigt: die der Eigentümer-Website (an/aus), die der übrigen Websites (falls freigegeben: alle, ausgewählte, optional mit Bedingungen wie „Kategorie = Turniere“) und ob Links direkt zur Ursprungs-Website führen.</li>
    <li><b>Suchmaschinen (Canonical)</b> – ebenfalls unter „Anzeige auf dieser Website“. Fremde Einträge haben auf Ihrer Website eine eigene Detailseite mit demselben Text wie auf der Ursprungs-Website. Standard ist <b>Ursprungs-Website</b>: Die Seite verweist per Canonical dorthin und steht nicht in Ihrer Sitemap – Suchmaschinen werten den Text nur einmal, Besucher lesen ihn trotzdem bei Ihnen. Wählen Sie <b>Diese Website</b> nur, wenn die Seiten bei Ihnen selbst gefunden werden sollen (z. B. weil die Ursprungs-Website keine Detailseiten hat oder eine andere Sprache/Zielgruppe anspricht); dann stehen sie auch in Ihrer Sitemap.</li>
    <li>In den Blöcken <b>Datenliste</b>, <b>Kalender</b> und <b>Nächste Termine</b> können Sie mit <b>Quelle</b> einzeln abweichen, z. B. „Nur eigene Einträge“ auf der Startseite und „Alle freigegebenen Einträge“ auf einer Übersichtsseite.</li>
  </ul>
  <p>Bilder und Dateien in geteilten Tabellen liegen automatisch in den geteilten Medien, damit alle Websites sie anzeigen können. Dafür entsteht je Tabelle ein eigener Bereich („Bilder der geteilten Tabelle …“) – aber nur, wenn die Tabelle ein Bild- oder Dateifeld hat; Tabellen nur mit Text (z. B. das Glossar) bekommen keinen. Kommt später ein Bild- oder Dateifeld hinzu, entsteht der Bereich beim Speichern der Felder. Welche Websites beteiligt sind, verwaltet die Eigentümer-Website unter <b>Grundeinstellungen → Geteilte Daten</b>; der Bildbereich folgt automatisch.</p>
  <p>Das <b>Glossar</b> lässt sich ebenso teilen – mit eigenem Ablauf (Einladen, Beitreten mit Abgleich doppelter Begriffe, Verlassen mit Kopie): siehe <a href="#glossar-teilen">Glossar mit anderen Websites teilen</a>.</p>
  <h3 id="daten-push">Neue Einträge abonnieren (Push)</h3>
  <p>Ist die Funktion <b>Push-Benachrichtigungen</b> eingeschaltet, können Besucher neue Einträge einer Tabelle abonnieren: unter <b>Felder &amp; Einstellungen → Benachrichtigungen (Push)</b> freigeben und den Block <b>„Benachrichtigungen abonnieren“</b> auf die Seite setzen. Eine Mitteilung geht nur beim <b>ersten</b> Veröffentlichen eines Eintrags hinaus. Einzelheiten: <a href="#<?= e($anchor('benachrichtigungen')) ?>">Push-Benachrichtigungen</a>.</p>
