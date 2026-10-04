<?php /** Handbuch · Kapitel „Cookie-Einwilligung“ (Erweiterung consent_kit; Variablen: siehe help/manual.php) */
$ckOn = \Core\Extensions::isActive('consent_kit') && \Core\Features::on('consent', false); ?>
  <p class="lead">Ihre Website setzt von sich aus keine Cookies und lädt keine Analyse- oder Werbedienste. Erst wenn Sie einen Dienst einbinden, der eine Einwilligung braucht (z. B. Matomo, Google Analytics, ein Meta-Pixel oder YouTube-Videos), fragt ein Hinweis Ihre Besucher. Dafür gibt es die Erweiterung „Cookie-Einwilligung“ – Ihre Agentur schaltet sie ein.</p>
  <?php if (!$ckOn): ?><div class="doc-note"><strong>Auf dieser Website nicht eingeschaltet</strong><p>Solange die Erweiterung aus ist, sehen Besucher keinen Hinweis. YouTube- und Vimeo-Videos laden trotzdem erst nach einem Klick (Zwei-Klick-Lösung).</p></div><?php endif; ?>
  <ol class="doc-steps">
    <li><b>Administration → Einstellungen → Cookie-Einwilligung → Dienst hinzufügen:</b> Vorlage wählen (38 Anbieter mit geprüften Cookie-Angaben), die Kennung eintragen (z. B. Mess-ID), „Aktiv“ anhaken, speichern.</li>
    <li><b>Einstellungen:</b> Form des Hinweises (Box, Leiste, Dialog, Seitenpanel), Links auf Datenschutzerklärung und Impressum (leer = Seiten aus den Einstellungen des Kits).</li>
    <li><b>Hinweis beim Seitenaufruf:</b> „Immer“ (Standard) fragt beim ersten Besuch. „Nur bei Bedarf“ passt, wenn Sie nur Karten oder Videos einbinden: Der Hinweis erscheint erst auf Seiten mit einem gesperrten Inhalt. „Nie“ zeigt ihn nur über Platzhalter, Schaltfläche oder den Link „Datenschutz-Einstellungen“. Dienste mit eigenem Code (z. B. Statistik) erzwingen die Abfrage – die Einstellungsseite nennt sie.</li>
    <li><b>Design:</b> Farben und Schrift kommen aus dem Design Ihrer Website – hell und dunkel. Abweichungen sehen Sie sofort in der Vorschau; zu schwache Kontraste werden markiert.</li>
  </ol>
  <ul>
    <li><b>Gleichwertig:</b> „Alle ablehnen“ und „Alle akzeptieren“ sehen immer gleich aus – das verlangen die Aufsichtsbehörden.</li>
    <li><b>Widerruf:</b> Besucher ändern ihre Auswahl jederzeit über „Datenschutz-Einstellungen“ im Fußbereich.</li>
    <li><b>Videos:</b> Ist YouTube als Dienst angelegt, gilt die Einwilligung auch für das Video („künftig direkt laden“) – und umgekehrt.</li>
    <li><b>Karten, Beiträge, Podcasts:</b> Block „Externer Inhalt (mit Einwilligung)“ zeigt erst einen Platzhalter mit „Inhalt einmal laden“ und „immer erlauben“. Die eigenen Karten der Website brauchen keine Einwilligung.</li>
    <li><b>Protokoll:</b> Jede Entscheidung wird als Nachweis gespeichert – ohne IP-Adresse und ohne Browserkennung – und nach der eingestellten Frist gelöscht. Export als CSV.</li>
  </ul>
  <div class="doc-note doc-note--tip"><strong>Keine Rechtsberatung</strong><p>Die Vorlagen sind sorgfältig geprüft, Anbieter ändern Cookies und Laufzeiten aber ohne Ankündigung. Die Dienste-Übersicht gehört zusätzlich in Ihre Datenschutzerklärung.</p></div>
