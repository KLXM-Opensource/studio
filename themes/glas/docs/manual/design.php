<?php /** Handbuch „glas“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farbfeld, Glas, Schriften, Kopf- und Fußbereich ein. Die Vorschau zeigt jede Änderung sofort – hell und dunkel. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Vier Vorlagen</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle sind hell und dunkel, in jeder Glasdichte auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Aurora (Standard)</td><td>Violett, Türkis und Rosé, Mattglas, schwebender Kopf, Outfit und Figtree.</td></tr>
    <tr><td>Lagune</td><td>Türkis, Petrol und helles Blau, klares Glas, Leiste über die volle Breite, Sora.</td></tr>
    <tr><td>Dämmerung</td><td>Pfirsich, Koralle und Pflaume, kräftiges Feld, Pillen-Buttons, Aussage im Fuß, Urbanist.</td></tr>
    <tr><td>Graphit</td><td>Rauchglas auf Anthrazit – von Anfang an dunkel, Milchglas, minimaler Kopf.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent (Füllfarbe) und Akzent als Schrift, Schrift auf Akzent, Überschriften, Fließtext, Nebentext, Grundfarbe, Glastönung, drei Farbfeld-Farben, Nacht – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Glas &amp; Farbfeld:</b> Glasdichte (klar, Mattglas, Milchglas), Unschärfe, Stärke des Farbfelds (sanft, kräftig, nur im Einstieg), feine Körnung, „Ohne Transparenz“.</li>
    <li><b>Typografie:</b> Schriften für Fließtext und Überschriften (vier Schriften vom eigenen Server plus Systemschrift), Grundschrift, Verhältnis der Stufen, Stärke und Laufweite.</li>
    <li><b>Form &amp; Abstände:</b> Eckenradius der Glasflächen (Buttons und Felder behalten 8 px), Buttons weich oder als Pille, Abstand zwischen Abschnitten, Inhaltsbreite.</li>
    <li><b>Kopf &amp; Fuß:</b> schwebend, Leiste, zentriert oder minimal; mitlaufender Kopf; Button im Kopf (Text und Link in den <?= e($settingsTitle) ?>); Fußbereich Glaspaneel, Aussage oder schlicht.</li>
    <li><b>Bewegung &amp; Farbschema:</b> sanfte Aurora und Einblenden; dunkles Schema für Besucher mit dunkler Geräte-Einstellung. „Bewegung reduzieren“ im Gerät schaltet Animationen immer ab.</li>
  </ul>
  <h3>Menü auf kleinen Bildschirmen</h3>
  <p>Passt das Menü nicht in die Leiste, erscheint die Schaltfläche <b>Menü</b>. Sie öffnet ein Glasblatt (auf Telefonen von unten) mit Seiten, Unterseiten zum Aufklappen, Suche, Direktkontakt und Sprache. Kurze Menüpunkte halten das Menü länger in der Leiste.</p>
