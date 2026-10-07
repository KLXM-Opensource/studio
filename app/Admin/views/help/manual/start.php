<?php /** Handbuch · Kapitel „Überblick“ (Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Die Website besteht aus zwei Bereichen: der <b>Website selbst</b>, die Sie direkt im Browser bearbeiten, und der <b>Verwaltung</b> (diese Oberfläche) für zentrale Angaben, Seiten, Einträge, Bilder und Einstellungen.</p>
  <div class="doc-cards">
    <a class="doc-card" href="#<?= e($anchor('bearbeiten')) ?>"><span class="doc-card__icon"><?= icon('pencil-simple') ?></span><span class="doc-card__title">Inhalte</span><span class="doc-card__sub">Texte, Bilder und Abschnitte direkt auf der Website ändern.</span></a>
    <a class="doc-card" href="#<?= e($anchor('einstellungen')) ?>"><span class="doc-card__icon"><?= icon('phone') ?></span><span class="doc-card__title"><?= e($settingsTitle) ?></span><span class="doc-card__sub"><?= e($vars['settings_sub'] ?? 'Zentrale Angaben, die überall erscheinen – einmal eintragen, überall aktuell.') ?></span></a>
    <a class="doc-card" href="#<?= e($anchor('medien')) ?>"><span class="doc-card__icon"><?= icon('images') ?></span><span class="doc-card__title">Medien</span><span class="doc-card__sub">Fotos, PDFs, Videos und Audio hochladen, Bildbeschreibungen und Untertitel pflegen.</span></a>
    <?php if ($has('anfragen')): ?><a class="doc-card" href="#<?= e($anchor('anfragen')) ?>"><span class="doc-card__icon"><?= icon('lock') ?></span><span class="doc-card__title">Anfragen</span><span class="doc-card__sub">Verschlüsselte <?= e(term('requests')) ?> lesen.</span></a><?php endif; ?>
  </div>
  <?= $img('dashboard.webp', 'Übersicht der Verwaltung mit Kennzahlen und Einrichtungs-Checkliste', '<b>Die Übersicht</b> zeigt Kennzahlen, was zu tun ist, Statistiken und Hilfe – mehr unter <a href="#' . e($anchor('uebersicht')) . '">Die Übersicht</a>.') ?>
  <div class="doc-note doc-note--tip"><strong>Das Wichtigste in einem Satz</strong><p>Seiten werden erst sichtbar, wenn Sie auf <b>Veröffentlichen</b> klicken – bis dahin arbeiten Sie gefahrlos an einem Entwurf. <?= e($settingsTitle) ?> und Einträge mit dem Status „Online“ gelten dagegen sofort nach dem Speichern.</p></div>
  <div class="doc-note doc-note--info"><strong>Alles finden mit <kbd>⌘</kbd> <kbd>K</kbd> (Windows: <kbd>Strg</kbd> <kbd>K</kbd>)</strong><p>Die Suche öffnet sich überall in der Verwaltung, im Editor und in der Leiste auf der Website – wie Spotlight am Mac. Ganz oben stehen Ihre Favoriten, darunter Seiten (auch Texte darin), Einträge aller Datentabellen, Bilder und Dateien, einzelne Einstellungen, Aktionen wie „Neue Seite“ oder „Neu: Beitrag“ sowie passende Artikel aus „Hilfe &amp; Support“. Pfeiltasten wählen, <kbd>↵</kbd> öffnet, <kbd>⌘</kbd><kbd>↵</kbd> zeigt die Alternative (z. B. die Seite auf der Website). Auf dem Handy öffnet die Lupe oben rechts dieselbe Suche.</p></div>
  <h3>Erster Start: Willkommen</h3>
  <p>Bei einer ganz neuen Website erscheint nach dem ersten Anmelden der <b>Willkommen-Bildschirm</b>. Dort wählen Sie zwei Dinge – beides lässt sich später ändern:</p>
  <ol>
    <li><b>Das Kit</b> – Gestaltung und Funktionen der Website (Blöcke, Design, Musterseiten). Jede Karte beschreibt kurz, wofür das Kit gemacht ist.</li>
    <li><b>Mit oder ohne Startinhalte</b> – <b>mit</b>: Musterseiten und Beispieltexte zum Kennenlernen; <b>ohne</b>: eine leere Startseite sowie Impressum und Datenschutz als Vorlagen.</li>
  </ol>
  <p>Bis dahin sehen Besucher nur „Hier entsteht eine neue Website“. Danach führt die Übersicht mit ihrer Checkliste weiter (Verschlüsselung, E-Mail-Versand, Domain, Angaben der Website). Das Kit wechseln Sie später unter Grundeinstellungen; Startinhalte werden nur beim ersten Einrichten eingespielt.</p>
  <h3>Die Verwaltung</h3>
  <ul>
    <li><b>Seitenleiste:</b> links alle Bereiche, die Ihre Rolle nutzen darf. Große Bereiche wie <b>Daten</b>, <b>Medien</b>, <b>Support</b> und <b><?= e(\Core\AI\Assist::brand()) ?></b> zeigen beim Öffnen ihr eigenes Menü; <b>‹ Hauptmenü</b> führt zurück. Darunter fasst <b>Administration</b> zwei aufklappbare Gruppen zusammen: <b>Einstellungen</b> (Grundeinstellungen, Funktionen &amp; Erweiterungen, Einstellungen der Funktionen, Benutzer &amp; Rollen, Design) und <b>Werkzeuge</b> (Blöcke, Landingpages, Weiterleitungen, Statistiken, Werkzeuge der Erweiterungen).</li>
    <li><b>Handy und Tablet:</b> Oben erscheint eine dunkle Leiste mit Menü-Knopf (öffnet die Seitenleiste von links), dem Namen des Bereichs und der Suche. <kbd>Esc</kbd> oder ein Tipp daneben schließt das Menü.</li>
    <li><b>Handbuch &amp; Hilfe</b> (links unten) enthält dieses Handbuch, die Wissensdatenbank, Fragen &amp; Antworten, die Übersicht aller Symbole und die technische Dokumentation.</li>
  </ul>
