<?php /** Handbuch · Kapitel „Glossar“ (Core\Glossary; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Mit dem <b>Glossar</b> erklären Sie Fachbegriffe direkt im Text: Das erste Vorkommen eines Begriffs auf einer Seite bekommt eine gepunktete Unterstreichung. Ein Klick oder Tippen öffnet ein kleines Fenster mit der Kurz-Erklärung und dem Link „Mehr im Glossar“. Dazu gibt es eine Übersicht von A bis Z und für jeden Begriff eine eigene Seite. Die Funktion schaltet die Administration unter <b>Funktionen &amp; Erweiterungen → Glossar</b> ein (Standard: aus).</p>
  <h3>Einrichten</h3>
  <ul>
    <li>Menü <b>Glossar</b> → „Glossar einrichten“: legt die Datentabelle „Glossar“ an, Detailseiten unter <code>/glossar/…</code> und die Übersichtsseite <code>/glossar</code> (als Entwurf – veröffentlichen Sie sie, wenn die ersten Begriffe stehen; im Menü erscheint sie erst, wenn Sie sie dort einhängen).</li>
  </ul>
  <h3>Begriffe pflegen</h3>
  <ul>
    <li><b>Begriff</b> (z. B. „SPF“) und <b>Kurz-Erklärung</b> sind Pflicht. Die Kurz-Erklärung ist Klartext mit höchstens 240 Zeichen – sie erscheint im Fenster. Schreiben Sie so, dass Laien sie verstehen, und in der Ansprache Ihrer Website.</li>
    <li><b>Varianten, Synonyme, Abkürzungen</b>: eine je Zeile, z. B. „Sender Policy Framework“. Abkürzungen mit mehreren Großbuchstaben (SPF, IPv6, MTA-STS) zählen nur in genau dieser Schreibweise, normale Wörter ohne Rücksicht auf Groß- und Kleinschreibung und mit üblichen Endungen („Zertifikat“ findet auch „Zertifikate“). Soll ein Wort nur genau so gelten, setzen Sie es in Anführungszeichen: <code>"Cookie"</code>.</li>
    <li><b>Ausführliche Erklärung</b>, <b>Kategorie</b> und <b>Mehr erfahren</b> (Link zu einer Quelle) sind freiwillig und erscheinen auf der Detailseite.</li>
    <li><b>Status</b>: Nur veröffentlichte Begriffe werden für Besucher markiert. Entwürfe sehen Sie angemeldet auf der Website mit dem Hinweis „Entwurf“ – so prüfen Sie neue Erklärungen im Zusammenhang.</li>
    <li>Bearbeitet wird wie bei jeder Datentabelle (<b>Daten → Glossar</b> oder der Link im Menü Glossar). „Begriff schnell hinzufügen“ legt einen Entwurf an; ist die KI eingerichtet, schlägt „Von der KI vorschlagen“ eine Erklärung vor – immer als Entwurf, den Sie prüfen.</li>
  </ul>
  <h3>Übersicht im Menü Glossar</h3>
  <ul>
    <li><b>Hinweise</b>: Varianten, die bei mehreren Begriffen stehen, fehlende oder zu lange Kurz-Erklärungen und Überschneidungen (z. B. „TLS“ in „TLS-RPT“ – dort gilt der längere Begriff).</li>
    <li><b>Vorkommen</b>: auf wie vielen Seiten der Begriff steht (nach dem Text der Website-Suche) – mit Liste der Seiten.</li>
    <li><b>Import &amp; Export</b> als CSV-Datei (Semikolon; erste Zeile <code>begriff;varianten;kurz;erklaerung;kategorie;link;status</code>). Neue Begriffe werden Entwürfe.</li>
  </ul>
  <h3>Wo nicht markiert wird</h3>
  <ul>
    <li>Nie in Links, Buttons, Formularen, Code, Navigation, Kopf- und Fußbereich, großen Überschriften (h1–h3, einstellbar) und auf der eigenen Seite des Begriffs; nie im Bearbeiten-Modus.</li>
    <li><b>Einzelner Abschnitt</b>: im Editor in den Abschnitts-Optionen „Glossar-Begriffe hier nicht markieren“ (z. B. bei Zitaten oder Werbetexten).</li>
    <li><b>Ganze Seiten</b>: im Menü Glossar unter Einstellungen → „Seiten ausnehmen“ (z. B. <code>/impressum</code> oder <code>/blog/*</code>).</li>
    <li><b>Je Seite oder je Abschnitt</b>: Standard ist das erste Vorkommen je Seite; „je Abschnitt“ markiert in jedem Abschnitt erneut.</li>
  </ul>
  <div class="doc-note doc-note--tip"><strong>Gut zu wissen</strong><p>Auch Inhalte von Erweiterungen werden markiert. Entstehen Inhalte erst im Browser – etwa Ergebnisse eines Prüf-Werkzeugs –, trägt die Administration den Bereich unter Einstellungen → „Dynamische Bereiche“ ein (z. B. <code>.ergebnisse</code>). Für Screenreader ist jeder markierte Begriff eine Schaltfläche, die ihre Erklärung aufklappt; beim Drucken steht die Erklärung in Klammern hinter dem Begriff.</p></div>
