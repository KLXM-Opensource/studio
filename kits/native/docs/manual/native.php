<?php /** Handbuch „NativeCSS“ · Kapitel „Effekte und Bausteine“ */ ?>
  <p class="lead">Das Kit NativeCSS sieht aus wie die Website von NativeCSS und nutzt deren Bausteine: Kopfleiste mit Menü, Einblend-Effekte, Morphing, Video-Player. Alles Übrige – Blöcke, Design, Einstellungen – kennen Sie aus dem Kit Fluid. Beispiele für jede Funktion zeigt die Seite <a href="/bausteine">Bausteine</a> der Demo.</p>
  <h3>Einblend-Effekt je Abschnitt</h3>
  <p>Öffnen Sie einen Block, dann <b>Abschnitt &amp; Navigation</b> → <b>Einblend-Effekt</b>: <i>Sanft einblenden</i>, <i>Von unten</i>, <i>Kreis</i> oder <i>Vorhang</i>. Der Effekt spielt einmal, wenn der Abschnitt hereinscrollt. Beim Bearbeiten sehen Sie ihn nicht – nur Besucher. Der erste Block einer Seite blendet nie ein (er ist sofort zu sehen), Blöcke in Spalten ebenso wenig.</p>
  <ul>
    <li>Sparsam einsetzen: ein, zwei Effekte je Seite wirken stärker als ein Effekt an jedem Abschnitt.</li>
    <li>Besucher mit „Bewegung reduzieren“ sehen alles sofort. Unter <b>Design → Bewegung &amp; Farbschema</b> schaltet „Dezente Animationen“ alle Effekte auf einmal ab.</li>
  </ul>
  <h3>Morphing beim Scrollen</h3>
  <p>Bei <b>Text + Bild</b> und <b>Video</b> gibt es die Darstellung „Morphing beim Scrollen“: Das Bild (bzw. eine kurze, stumme Videoschleife) startet groß und rückt beim Scrollen zur Seite, der Text erscheint daneben. Die Scroll-Strecke ist lang – der Block eignet sich als Höhepunkt einer Seite, nicht für jede zweite Stelle. Beim Bearbeiten erscheint er wie „Bild rechts“ bzw. „Mit Text daneben“.</p>
  <h3>Bewegte Bausteine</h3>
  <ul>
    <li><b>Kennzahlen</b> zählen hoch, wenn sie in den Blick kommen. Die echte Zahl steht immer im Text – auch für Screenreader.</li>
    <li><b>Logos „Laufband“</b> hat eine Schaltfläche „Laufband anhalten“.</li>
    <li><b>Einstieg „Vollflächig“ mit Hintergrundvideo</b>: Das Video lädt erst kurz vor dem Sichtbarwerden, hat eine Pause-Schaltfläche und spielt nie bei „Bewegung reduzieren“ oder „Daten sparen“ – dann bleibt das Bild stehen. Kurz und klein halten (unter 8 MB), ohne Ton.</li>
    <li><b>Ablauf „Zeitleiste“</b> mit der Animation „Linie wächst beim Scrollen“.</li>
  </ul>
  <h3>Video-Player und Vorher/Nachher</h3>
  <p>Ein eigenes Video (MP4 aus der Mediathek) bekommt einen Player mit Kapiteln, Untertitel-Menü und Tastaturbedienung. Untertitel und Kapitel pflegen Sie in der <b>Mediathek</b> an der Datei. YouTube und Vimeo laden wie immer erst nach Klick. Der Block <b>Vorher/Nachher</b> legt zwei Bilder übereinander – ein Regler zeigt mehr vom einen oder anderen, auch mit Tab und Pfeiltasten.</p>
  <p>Bei <b>Video &amp; Audio → Audio (Podcast, Musik)</b> erscheint ein Player mit Cover („Vorschaubild“), Kapitelliste und – ab zwei Dateien – Playlist mit Vor/Zurück. Kapitel und Transkript pflegen Sie in der <b>Mediathek</b> an jeder Audiodatei; „Transkript anzeigen“ steht dann unter dem Player.</p>
  <h3>Darstellungs-Knopf für Besucher</h3>
  <p>Unter <b>Design → Bewegung &amp; Farbschema</b> schalten Sie den <b>Darstellungs-Knopf für Besucher</b> ein (Standard: aus). Besucher stellen dann selbst Farbschema, Kontrast, Bewegung, Schriftgröße, eine Lese-Schrift (z. B. für Legasthenie), Textabstände, Links und Fokus ein. Der Browser merkt sich die Wahl, ohne Cookie. Die Lese-Schriften (Atkinson Hyperlegible, Lexend, OpenDyslexic) bietet nur dieses Kit an; abschalten mit <b>Lese-Schriften im Darstellungs-Knopf anbieten</b>.</p>
  <h3>Kopf und Fuß</h3>
  <p>Die Kopfleiste kommt aus NativeCSS: Menü rechts neben dem Logo, immer in einer Zeile. Passt es nicht mehr daneben (schmaler Bildschirm, viele Menüpunkte, größere Schrift), öffnet die Menü-Schaltfläche das Menü von links – das gilt auch ohne JavaScript. Seiten mit Unterseiten öffnen ein Aufklappmenü, dessen erster Eintrag „Übersicht“ zur Seite selbst führt. Suche, Handlungsaufruf und Kontakt stellen Sie wie gewohnt unter <b>Design → Kopfbereich: Suche &amp; Aktionen</b> ein; die Gruppe „Kopf &amp; Fuß“ von Fluid heißt hier <b>Seite</b> und enthält nur Seitenhintergrund, Abschnitte, Lesefortschritt, „Nach oben“ und Seitenwechsel.</p>
