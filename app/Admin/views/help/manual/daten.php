<?php /** Handbuch · Kapitel „Eigene Daten“ · @var callable $anchor */ ?>
  <p class="lead">Unter <a href="<?= e(url('/admin/data')) ?>">Daten</a> legen Sie eigene Inhaltstypen an – etwa Aktuelles, Team, Produkte, Termine oder Fragen &amp; Antworten. Ohne Programmieren, mit eigener Übersicht, Detailseiten und Ausgabe an beliebiger Stelle der Website.</p>

  <h3>1. Tabelle anlegen <small>(Recht „Tabellen und Felder ändern“, Standard: Administration)</small></h3>
  <ol class="doc-steps">
    <li><b>Daten</b> → Vorlage wählen (<b>Aktuelles</b>, <b>Team</b>, <b>Produkte</b>, <b>Termine</b>, <b>Fragen &amp; Antworten</b> oder <b>Anfragen</b> für einen verschlüsselten Eingang) oder <b>Leere Tabelle</b>. Mit eingeschalteter KI schlägt <b>✦ Felder vorschlagen</b> passende Felder aus einer kurzen Beschreibung vor; der <b>Tabellen-Generator</b> im KI-Bereich entwirft eine ganze Tabelle.</li>
    <li>Felder anpassen: Bezeichnung, Typ (Text, Formatierter Text, Zahl, Ja/Nein, Datum, Datum &amp; Uhrzeit, Uhrzeit, Auswahl, Mehrfachauswahl, Bild, Datei, Link, E-Mail, Telefon, Webadresse, Farbe, Ort (Karte), Verknüpfung, Wiederholung, IBAN, Wiederholbare Gruppe), Pflichtfeld, „In der Liste zeigen“, „Durchsuchbar“ (Suche in der Verwaltungsliste). Reihenfolge mit ↑ ↓. Bezeichnungen und Auswahltexte lassen sich unter „Übersetzungen“ je Sprache pflegen.</li>
    <li>Rechts unter <b>Tabelle</b> ein <b>Symbol</b> wählen: Klick auf das Symbol öffnet eine Auswahl nach Themen (Kalender &amp; Zeit, Personen &amp; Team, Gesundheit &amp; Medizin, Sport &amp; Verein …). Einfach tippen, z. B. „Termin“, „Team“ oder „Arzt“ – Vorschläge passend zum Tabellennamen stehen oben. Mit der Tastatur: ↓ ins Raster, Pfeiltasten, Enter übernimmt, Esc schließt. Das Symbol erscheint in der Seitenleiste, in Listen, Favoriten und in der Suche. Alle Symbole: <a href="<?= e(url('/admin/hilfe/symbole')) ?>">Symbole</a>.</li>
    <li>Rechts unter <b>Website</b> die <b>Adresse der Detailseiten</b> festlegen, z. B. <code>aktuelles</code> – jeder Eintrag bekommt dann automatisch eine eigene Seite wie <code>/aktuelles/titel-des-beitrags</code>.</li>
    <li><b>Tabelle anlegen</b>. Felder lassen sich später jederzeit ergänzen, umbenennen oder entfernen (beim Entfernen fragt das System nach, weil Inhalte verloren gehen).</li>
  </ol>

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
  <p>Links im Menü unter <b>Daten</b> steht jede Tabelle einzeln. Die Liste lässt sich durchsuchen, nach Spalten sortieren und – bei manueller Sortierung – per Ziehen ordnen. Mehrere Einträge markieren: gemeinsam online stellen, auf Entwurf setzen oder löschen. <b>Speichern &amp; neu</b> beschleunigt das Anlegen mehrerer Einträge. Auf mehrsprachigen Websites legt „+ English“ im Eintrag eine verknüpfte Übersetzung an. Unter <b>Felder &amp; Einstellungen</b> stellen Sie außerdem Listenbild, Einträge je Seite und die <b>Suche</b> für diese Tabelle ein (siehe <a href="#<?= e($anchor('suche')) ?>">Website-Suche</a>).</p>

  <h3>3. Auf der Website ausgeben</h3>
  <ol class="doc-steps">
    <li>Seite im Editor öffnen → <b>+</b> → Block <b>Datenliste</b>.</li>
    <li>Tabelle wählen, <b>angezeigte Felder</b> ankreuzen, Darstellung wählen: Karten, Liste mit Bild, kompakte Liste oder Tabelle.</li>
    <li>Optional filtern (z. B. nur Kategorie „Neuigkeiten“ oder nur Termine ab „heute“), sortieren, Anzahl begrenzen, seitenweise blättern, Button „Alle Beiträge“.</li>
  </ol>

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
  <p>Tipp: Folgen Fließtext und Formular mit derselben Hintergrundfarbe aufeinander (z. B. Stellenanzeige mit Bewerbungsformular), rückt das Kit die Abschnitte enger zusammen – so wirkt die Seite wie aus einem Guss.</p>
  <p>Das Formular ist mehrstufig gegen Spam geschützt und setzt keine Cookies. Besucher bestätigen immer den Datenschutzhinweis. Datei-Uploads (Bildfelder: JPG, PNG, WebP; Dateifelder: PDF und/oder Bilder; 1–10 MB) sind nur möglich, wenn Sie sie ausdrücklich erlauben; hochgeladene Dateien liegen in der Mediathek (Tag „formular“) und sind über ihre Adresse öffentlich erreichbar.</p>
  <p><b>Erlaubte Dateitypen je Dateifeld:</b> Beim Feld vom Typ „Datei“ wählen Sie unter <b>Erlaubte Dateitypen</b>, was Besucher hochladen dürfen: PDF, Bilder (JPG, PNG, WebP) und – nur in Eingangs-Tabellen mit Zustellung per E-Mail – Word (DOCX) und OpenDocument-Text (ODT). Standard: PDF und Bilder. Unter <b>Höchstgröße</b> können Sie für das Feld eine kleinere Grenze als die der Tabelle setzen (leer = wie Tabelle). Das Formular zeigt unter dem Feld einen Hinweis wie „PDF oder Bild (JPG, PNG, WebP), höchstens 5 MB.“ Geprüft wird der <b>Inhalt</b> der Datei, nicht nur die Endung: Ein umbenanntes Programm („rechnung.pdf“) oder ein Word-Dokument mit Makros wird abgelehnt. Pro Feld ist eine Datei möglich.</p>
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
  <p>Unter <b>Kalender &amp; Kontakte in Apps</b> erzeugen Sie ein <b>App-Passwort</b> (nur einmal sichtbar, „Lesen und ändern“ oder „Nur lesen“). Damit verbinden Sie Apple Kalender/Kontakte, Thunderbird oder DAVx⁵ (Android) mit der Website: Kalender-Tabellen erscheinen als Kalender, freigegebene Tabellen als Adressbuch. Benutzername ist Ihre E-Mail-Adresse, Server die Adresse der Website. Was Sie in der App ändern, landet im CMS – und umgekehrt. Löschen in der App setzt einen Eintrag normalerweise nur auf Entwurf.</p>
  <?php endif; ?>

  <h3 id="geteilt">Geteilte Tabellen (mehrere Websites)</h3>
  <p>Manche Tabellen tragen in der Daten-Navigation den Hinweis <b>geteilt</b>. Sie gehören zu mehreren Websites derselben Installation – zum Beispiel Neuigkeiten oder Termine, die eine Dachorganisation und ihre Mitglieder gemeinsam nutzen. Die Felder legt die Website fest, der die Tabelle gehört; jede Website pflegt <b>nur ihre eigenen Einträge</b>.</p>
  <ul class="doc-list">
    <li><b>Eigene</b> – Ihre Einträge, wie bei jeder Tabelle: anlegen, ändern, veröffentlichen.</li>
    <li><b>Vom …</b> / <b>Von …</b> – Einträge der anderen Websites. Sie sind nur lesbar; Sie können sie für Ihre Website <b>ausblenden</b> oder <b>hervorheben</b>. „Auf Ursprungs-Website öffnen“ zeigt das Original.</li>
    <li><b>Vorschlagen</b> – im Eintrag setzen Sie ein Häkchen bei „… vorschlagen“. Die Website, der die Tabelle gehört, sieht den Eintrag unter <b>Vorschläge</b> und kann ihn <b>übernehmen</b>, <b>hervorheben</b> oder <b>ablehnen</b>. Unter dem Häkchen sehen Sie, wie entschieden wurde.</li>
    <li><b>Anzeige auf dieser Website</b> – hier legen Sie fest, welche fremden Einträge Ihre Website zeigt: die der Eigentümer-Website (an/aus), die der übrigen Websites (falls freigegeben: alle, ausgewählte, optional mit Bedingungen wie „Kategorie = Turniere“) und ob Links direkt zur Ursprungs-Website führen.</li>
    <li>In den Blöcken <b>Datenliste</b>, <b>Kalender</b> und <b>Nächste Termine</b> können Sie mit <b>Quelle</b> einzeln abweichen, z. B. „Nur eigene Einträge“ auf der Startseite und „Alle freigegebenen Einträge“ auf einer Übersichtsseite.</li>
  </ul>
  <p>Bilder in geteilten Tabellen liegen automatisch in den geteilten Medien, damit alle Websites sie anzeigen können. Welche Websites beteiligt sind, verwaltet die Eigentümer-Website unter <b>Grundeinstellungen → Geteilte Daten</b>.</p>
