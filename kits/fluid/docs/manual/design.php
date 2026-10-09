<?php /** Handbuch „fluid“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Formen, Kopf- und Fußbereich ein. Die Vorschau rechts zeigt jede Änderung sofort – hell und dunkel, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Vorlagen</h3>
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
    <tr><td>Software &amp; Produkt · Aurora</td><td>Modern und leicht: Manrope, weicher Farbschleier im Hintergrund, Buttons im Farbverlauf, Karten mit Verlaufsrahmen, farbige Abschnitte als abgerundete Flächen, Lesefortschritt und „Nach oben“.</td></tr>
    <tr><td>Studio · Neo-Brutalismus</td><td>Plakativ und verspielt: Space Grotesk, kräftige Konturen mit harten Schatten, Konturschrift für betonte Wörter, feine Körnung, Farbband über dem Fuß.</td></tr>
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
  <h3 id="fluid-modern">Neuere Varianten im Überblick</h3>
  <ul>
    <li><b>Buttons:</b> zusätzlich „Farbverlauf mit Leuchten“ und „Neo-Brutalismus“ (Kontur, harter versetzter Schatten, drückt sich beim Klick ein).</li>
    <li><b>Karten:</b> „Getönt“, „Verlaufsrahmen“ (Rahmen im Verlauf der Markenfarben) und „Neo-Brutalismus“.</li>
    <li><b>Seitenhintergrund:</b> „Aurora“ (weiche Farbschleier oben), „Feine Körnung“ (Grain) und „Senkrechte Rasterlinien“.</li>
    <li><b>Farbige Abschnitte:</b> über die volle Breite (wie bisher) oder <b>als abgerundete Flächen mit Rand</b> – der moderne Karten-Look. Übergänge wie Welle oder Schräg entfallen dabei.</li>
    <li><b>Betonung *Wort*</b> in Überschriften zusätzlich als <b>Konturschrift</b>; <b>verlinkte Karten</b> mit langsam zoomendem Bild; <b>Menüpunkte</b> mit Punkt unter dem aktiven.</li>
    <li><b>Einblenden</b> zusätzlich „Aufdecken von unten“ und „Seitlich hereingleiten“.</li>
    <li><b>Lesefortschritt</b> (dünne Linie oben, wächst beim Scrollen) und Schaltfläche <b>„Nach oben“</b> unten rechts – beide unter „Kopf &amp; Fuß“ zuschaltbar.</li>
    <li><b>Menüpunkte mit Unterseiten:</b> „Link + Pfeil“ (Standard) – der Menüpunkt führt zu seiner Seite, der kleine Pfeil daneben öffnet die Unterseiten; „Link + Pfeil, öffnet auch beim Überfahren“ – zusätzlich mit der Maus; „Klick öffnet die Unterseiten“ – das frühere Verhalten mit dem Eintrag „Übersicht: …“. Gilt auch für das Menü auf dem Telefon.</li>
    <li><b>Dritte Menüebene:</b> „Eingerückt“ (Standard) – Unterseiten von Unterseiten stehen eingerückt mit Linie und etwas kleiner; „Gruppiert“ – die zweite Ebene wird zur kleinen Zwischenüberschrift, die dritte steht darunter.</li>
    <li><b>Höhe des Kopfbereichs:</b> normal, hoch oder sehr hoch – für große Logos. Beim Scrollen und auf schmalen Bildschirmen wird der Kopf mit weichem Übergang wieder normal hoch (nicht bei den Seitenleisten).</li>
    <li><b>Linie über dem Fußbereich:</b> wie Farbwirkung, keine, Linie, Linie mit Schatten nach unten, geprägt oder Farbband – mit <b>Farbe</b> (Linienfarbe, Akzent, zweite Markenfarbe, Schriftfarbe, Weiß) und <b>Dicke</b> (1–10 px).</li>
  </ul>

  <h3 id="fluid-bloecke">Blöcke: Zeitleiste, Stimmen, Text und Bild</h3>
  <ul>
    <li><b>Ablauf / Zeitleiste:</b> Felder <b>Kreise</b> (wie Design, gefüllt, zart gefüllt mit Rand, nur Rand, kleiner Punkt ohne Nummer), <b>Farben</b> (wie Design, farbig abwechselnd, eine Farbe, neutral) und <b>Animation</b> (wie Website, nacheinander einblenden, Linie wächst beim Scrollen, keine). Ohne Wahl richtet sich alles nach dem Design – bei „Farbenfroh“ sind die Kreise bunt.</li>
    <li><b>Zitat / Stimmen:</b> neue Darstellung <b>Laufband</b> – die Stimmen laufen langsam durch und halten an, wenn man mit der Maus darauf zeigt oder mit der Tastatur hineinspringt.</li>
    <li><b>Fließtext:</b> Feld <b>Einblenden, wenn der Text in den Blick kommt</b> – aus (Standard), ganzer Block oder Absätze nacheinander. Die Art der Bewegung (aufsteigen, Blende, wachsen …) kommt aus Design → Bewegung &amp; Farbschema; ohne Animationen bzw. mit „Bewegung reduzieren“ steht der Text sofort da. Im Bearbeiten-Modus wird nichts ausgeblendet.</li>
    <li><b>Fließtext „Artikel mit Inhaltsverzeichnis“:</b> Feld <b>Beim Scrollen mitlaufen</b> – „Aktuellen Abschnitt markieren“ lässt eine Markierung im Inhaltsverzeichnis weich zum Abschnitt gleiten, der gerade gelesen wird; „Markieren mit Lesefortschritt“ zeigt zusätzlich links eine Linie, die mit dem Lesen wächst. Standard: aus.</li>
    <li><b>Fließtext:</b> Feld <b>Textbreite</b> (schmal, Lesebreite, breit, volle Breite) – auch direkt auf der Seite am rechten Rand des Textes ziehbar.</li>
    <li><b>Text + Bild:</b> Feld <b>Aufteilung Bild/Text</b> (Text breiter, ausgewogen, Bild breiter, Bild deutlich breiter) – auch auf der Seite am inneren Bildrand ziehbar.</li>
    <li><b>Abstand oben/unten</b> jedes Abschnitts gibt es in Fluid zusätzlich als „Groß“ – in der Seitenleiste oder per Ziehen am Abschnittsrand (siehe <a href="<?= e(url('/admin/hilfe#ziehen')) ?>">Mit der Maus ziehen</a>).</li>
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
