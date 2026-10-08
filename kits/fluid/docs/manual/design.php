<?php /** Handbuch „fluid“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Formen, Kopf- und Fußbereich ein. Die Vorschau rechts zeigt jede Änderung sofort – hell und dunkel, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Sechzehn Vorlagen</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle Vorlagen sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Fluid Standard</td><td>Neutral: Indigo, Inter, Leiste mit Menü rechts.</td></tr>
    <tr><td>Kanzlei · Nachtblau &amp; Serife</td><td>Seriös: Serifen-Überschriften, eckig, zentrierter Kopf.</td></tr>
    <tr><td>Handwerk · Terrakotta</td><td>Kräftig: markante Grotesk, runde Buttons, schwebender Kopf, Punktraster, großer Schriftzug im Fuß.</td></tr>
    <tr><td>Praxis · Salbei</td><td>Beruhigend: weiche Karten mit Schatten, sanfter Farbverlauf, luftige Abstände.</td></tr>
    <tr><td>Agentur · Kontrast</td><td>Laut: sehr große Schriftstufen, Violett und Limette, Raster, Menü in der Mitte.</td></tr>
    <tr><td>Verein · Frisch</td><td>Fröhlich: Blau und Gelb, fette Schrift, Etiketten.</td></tr>
    <tr><td>Restaurant · Bordeaux &amp; Creme</td><td>Genussvoll: Fraunces, Cremetöne, Kontur-Buttons, zentrierter Kopf.</td></tr>
    <tr><td>Tech-Startup · Nacht</td><td>Dunkel von Anfang an: Glas-Karten, Monospace-Etiketten, Raster.</td></tr>
    <tr><td>Kultur · Museum</td><td>Plakativ: Instrument Serif in sehr großen Stufen, Signalrot, Menü als Seitenleiste.</td></tr>
    <tr><td>Sozialträger · Zwei Markenfarben</td><td>Warm und verlässlich: Orange und Grün, Lato, Textmarker-Betonung, Infoleiste, dunkler Fuß.</td></tr>
    <tr><td>Verband · Bordeaux &amp; Versalien</td><td>Kräftig: schmale Schrift in Versalien, Kopf in Markenfarbe, schräge Übergänge zwischen Abschnitten.</td></tr>
    <tr><td>Gesundheit · Klar &amp; barrierearm</td><td>Gut lesbar für alle: Atkinson Hyperlegible, größere Grundschrift, runde Bilder, deutliche Links.</td></tr>
    <tr><td>Betrieb &amp; Notdienst · Sachlich</td><td>Robust: Roboto mit Roboto Slab, eckige Formen, dunkler Kopf, rote Infoleiste für Telefon und Notdienst.</td></tr>
    <tr><td>Kommune &amp; Portal · Freundlich</td><td>Einladend: Poppins, Violett und Petrol, runde Formen, Wellen zwischen Abschnitten, zentrierter Fuß.</td></tr>
    <tr><td>Wissen &amp; Kampagne · Dunkel</td><td>Atmosphärisch: dunkel von Anfang an, Manrope, Glas-Karten, Betonung im Farbverlauf.</td></tr>
    <tr><td>Netzwerk &amp; Bildung · Farbenfroh mit Seitenleiste</td><td>Lebendig und gut sortiert: Kopfbanner über die volle Breite mit großem Logo und Claim, darunter Seitenleiste mit Menübaum, fünf Farben im Wechsel, Nunito, runde Formen – für Netzwerke, Bildungsträger und Verbände mit vielen Mitgliedern.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farbwirkung:</b> „Markenfarben“ (wie bisher) oder <b>„Farbenfroh“</b> – Akzent, zweite Markenfarbe und drei weitere Farben wechseln sich in Karten, Symbolen, Kennzahlen, Listenpunkten, getönten Abschnitten, Etiketten und der Seitenleiste ab; im Fußbereich erscheint ein Farbband. Die Farben färben nur Linien, Symbole und Flächen (zart getönt) – Schrift bleibt in Überschriften- und Textfarbe und damit lesbar. Die drei weiteren Farben brauchen mindestens 3 : 1 Kontrast zum Hintergrund (die Prüfung im Editor zeigt es an).</li>
    <li><b>Farben:</b> Akzent, Akzent kräftig, Schrift auf Akzent, Hervorhebung (Flächen, Marker), <b>zweite Markenfarbe</b> mit Schrift darauf und wo sie eingesetzt wird (Abschnitte, Details, Buttons oder überall), Überschriften, Fließtext, Nebentext, Hintergrund, getönte Fläche, Linien, dunkle Abschnitte – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schrift für Fließtext, Überschriften und Code (achtzehn Schriften vom eigenen Server – darunter Open Sans, Lato, Roboto, PT Sans, Poppins und die besonders gut lesbare Atkinson Hyperlegible), Schrift der Dachzeilen, Grundschrift klein/groß, Verhältnis der Stufen (ruhig bis plakativ), Bereich, in dem die Schrift fließt, Stärke (stufenlos), Laufweite und Schreibweise der Überschriften (normal oder Versalien), <b>Betonung</b> (Akzentfarbe, kursiv, fett, Textmarker, unterstrichen, Farbverlauf) und Darstellung von Links.</li>
    <li><b>Form &amp; Raum:</b> Eckenradius, Buttons (gefüllt, Pille, Kontur, getönt, eckig mit Pfeil), Karten (Fläche, Rahmen, Schatten, Glas), Schatten, Dichte, Abstand zwischen Abschnitten, maximale Inhaltsbreite, Dachzeilen, <b>Bilder</b> (Form: abgerundet, eckig, rund, Bogen; Wirkung: keine, Schwarzweiß, eingefärbt, Rahmen), Hover-Effekt, Symbole (Fläche, Kreis, Kontur, ohne) und ihre Anordnung (über dem Titel, in einer Zeile mit dem Titel, links neben dem Text) und <b>Übergänge zwischen Abschnitten</b> (gerade, Welle, schräg, Bogen, Zickzack – nur dort, wo der Hintergrund wechselt).</li>
    <li><b>Kopf &amp; Fuß:</b> Kopfbereich (siehe unten) und seine Farbe (Seite, getönt, Akzent, zweite Markenfarbe, dunkel, zarter Farbverlauf), <b>Größe von Logo bzw. Wortmarke</b> (normal, groß, sehr groß – auch für quadratische Logos), Breite der Seitenleiste und ob dort alle Unterseiten sichtbar sind, Stil des Menüs (schlicht, unterstrichen, Pille, Versalien), <b>Infoleiste</b> über dem Kopf mit Telefon, E-Mail, Social Media und einem kurzen Text (z. B. Notdienst – unter <?= e($settingsTitle) ?> → Darstellung), beim Scrollen sichtbar, Button im Kopfbereich (Beschriftung und Link unter <?= e($settingsTitle) ?> → Darstellung), Fußbereich (Spalten, schlicht, großer Schriftzug, zentriert) und seine Farbe, Seitenhintergrund (einfarbig, Raster, Punkte, Verlauf).</li>
    <li><b>Bewegung &amp; Farbschema:</b> dezente Animationen (Einblenden, Laufband) mit Stil (aufsteigen, einblenden, wachsen, scharfstellen) und dunkles Farbschema nach Geräte-Einstellung. „Bewegung reduzieren“ der Besucher hat immer Vorrang.</li>
  </ul>
  <h3>Sieben Kopfbereiche</h3>
  <table class="doc-table">
    <tr><th>Variante</th><th>So sieht sie aus</th></tr>
    <tr><td>Leiste</td><td>Logo links, Menü rechts – der Klassiker.</td></tr>
    <tr><td>Zentriert</td><td>Logo in der Mitte, Menü darunter; bei wenig Platz Logo mittig und Menü-Schaltfläche rechts.</td></tr>
    <tr><td>Geteilt</td><td>Menü in der Mitte zwischen Logo und Button.</td></tr>
    <tr><td>Schwebend</td><td>Abgerundete Leiste mit Abstand zum Rand und Schatten.</td></tr>
    <tr><td>Minimal</td><td>Nur Logo und Menü-Schaltfläche – das Menü öffnet immer als Seitenblatt.</td></tr>
    <tr><td>Seitenleiste</td><td>Menü senkrecht links neben dem Inhalt, solange das Fenster breit genug ist – sonst wie „Leiste“ oben.</td></tr>
    <tr><td>Seitenleiste mit Menübaum</td><td>Leiste links über die volle Höhe: großes Logo oben, Suche, alle Ebenen des Menüs (der Zweig der aufgerufenen Seite ist offen, die übrigen klappen Sie mit dem Pfeil neben dem Menüpunkt auf), unten Button, Telefon, E-Mail, Social Media und Sprachen. Die Leiste scrollt für sich. Auf schmalen Bildschirmen wird daraus eine Leiste oben mit Logo und Menü-Schaltfläche. Einstellbar: Breite (schmal, normal, breit), Unterseiten (aktueller Zweig offen oder immer alle sichtbar), Hintergrund und ein <b>Kopfbanner</b>: „Farbband“ (Logo, Claim aus den Stammdaten und Button auf der Farbe des Kopfbereichs) oder „Bild“ (Bannerbild aus <?= e($settingsTitle) ?> → Darstellung – ein breites Bild, z. B. 2400 × 600 px, erscheint ganz und ohne Beschnitt und bestimmt die Höhe; Logo und Claim stehen auf einer hellen Fläche, wahlweise wird das Bild leicht oder stark abgedunkelt und die Schrift weiß), Höhe des Farbbands normal oder hoch. Der Banner steht auf jeder Seite über die volle Breite, auf Unterseiten etwas niedriger; die Seitenleiste beginnt darunter und bleibt beim Scrollen oben stehen. Auf dem Handy wird der Banner zur Leiste mit Logo und Menü-Schaltfläche.</td></tr>
  </table>
  <p>Alle Varianten sind per Tastatur bedienbar (Tab, Pfeiltasten, Escape) und öffnen bei Platzmangel dasselbe Seitenblatt mit Menü, Suche, Kontakt und Sprache.</p>
  <h3>Verzeichnis für Mitglieder, Partner und Anbieter</h3>
  <p>Mit einer Datentabelle (z. B. „Mitglieder“ mit Logo, Name, Schwerpunkt als Mehrfachauswahl, Region, Adresse, Telefon, E-Mail, Website, Beschreibung) und den Blöcken <b>Datenliste</b> (Darstellung „Verzeichnis“, Filter-Schaltflächen, Suche, A–Z) und <b>Datensatz-Felder</b> (Darstellungen „Profil“ und „Kontaktkarte“) entsteht ein modernes Verzeichnis mit Detailseite je Eintrag – Einzelheiten im Handbuch unter <b>Daten</b>. Ein fertiges Beispiel mit zwölf erfundenen Mitgliedern legt die Technik mit <code>php kits/fluid/tools/demo.php --network</code> an.</p>
