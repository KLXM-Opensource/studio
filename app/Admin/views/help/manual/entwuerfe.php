<?php /** Handbuch · Kapitel „Entwürfe prüfen und aufräumen“ (Verwaltung → Entwürfe, Core\Review\Drafts) · @var callable $anchor */ ?>
  <p class="lead">Unter <a href="<?= e(url('/admin/entwuerfe')) ?>">Entwürfe</a> steht alles, was noch nicht – oder nicht in dieser Fassung – online ist. So geraten Zwischenstände nicht in Vergessenheit, und vor dem Veröffentlichen lässt sich in Ruhe vergleichen. Die Zahl am Menüpunkt zeigt, wie viele Entwürfe offen sind.</p>
  <table class="doc-table">
    <tr><th>In der Liste</th><th>Bedeutung</th></tr>
    <tr><td><b>Seite · neu</b></td><td>Eine Seite im Status „Entwurf“, die noch nie online war (z. B. frisch angelegt oder vom Seiten-Generator).</td></tr>
    <tr><td><b>Seite · geändert</b></td><td>Die Seite ist online, im Editor wurden aber Änderungen gespeichert und noch nicht veröffentlicht. Im Seitenbaum steht dazu <b>Entwurf offen</b>. Nur erneut gespeichert ohne inhaltliche Änderung zählt nicht.</td></tr>
    <tr><td><b>Eintrag · neu / offline</b></td><td>Ein Eintrag einer Datentabelle (z. B. Aktuelles, Termine) im Status „Entwurf“ – noch nie online bzw. wieder offline genommen.</td></tr>
    <tr><td><b>vergessen?</b></td><td>Seit über 14 Tagen nicht mehr geändert – bitte prüfen: veröffentlichen, weiter bearbeiten oder verwerfen.</td></tr>
    <tr><td>Herkunft</td><td><b>Content-Sync</b> (vom Test-System übernommen), <b>KI</b>, <b>MCP</b> oder <b>REST-API</b> mit Name des Zugangs, <b>Externe Quelle</b> – ohne Angabe stammt der Entwurf aus der Redaktion. Darunter steht, wer zuletzt geändert hat.</td></tr>
  </table>
  <table class="doc-table">
    <tr><th>Aktion</th><th>So geht’s</th></tr>
    <tr><td>Im Editor öffnen / Bearbeiten</td><td>Seiten öffnen sich auf der Website im Bearbeiten-Modus, Einträge im Formular.</td></tr>
    <tr><td>Unterschiede</td><td>Vergleicht den Entwurf blockweise mit der veröffentlichten Fassung – gestrichene Wörter durchgestrichen, neue unterstrichen. Bei neuen Seiten und Einträgen steht der vollständige Inhalt da.</td></tr>
    <tr><td>Veröffentlichen</td><td>Wie im Editor bzw. in der Datentabelle – mit denselben Rechten. Seiten mit Platzhaltern „[bitte ergänzen: …]“ werden nicht veröffentlicht.</td></tr>
    <tr><td>Verwerfen</td><td><b>Seite:</b> zurück zur veröffentlichten Fassung; der verworfene Entwurf bleibt unter „Versionen“ der Seite erhalten und lässt sich wiederherstellen. <b>Eintrag:</b> wird gelöscht (Recht „Einträge löschen“) und 90 Tage lang unter <b>Zuletzt verworfen</b> gesichert – „Wiederherstellen“ legt ihn wieder als Entwurf an. Neue, nie veröffentlichte Seiten löschen Sie unter „Seiten“.</td></tr>
    <tr><td>Mehrere auf einmal</td><td>Häkchen setzen (oder „Alle auswählen“), dann <b>Ausgewählte veröffentlichen</b> bzw. <b>Ausgewählte verwerfen</b> – vorher kommt eine Rückfrage. Was nicht geht (fehlendes Recht, Platzhalter), wird übersprungen und gemeldet.</td></tr>
    <tr><td>Notiz &amp; Zuständigkeit</td><td>„Notiz hinzufügen“: kurzer Hinweis wie „wartet auf Freigabe durch die Praxisleitung“ und optional eine zuständige Person. Die Notiz verschwindet, sobald der Entwurf veröffentlicht oder verworfen ist.</td></tr>
  </table>
  <p>Filter oben: <b>Meine</b> (zuletzt von Ihnen geändert oder Ihnen zugewiesen), <b>Zur Prüfung</b> (mit Notiz oder zuständiger Person), <b>Vergessen?</b> (älter als 14 Tage) und <b>Content-Sync &amp; KI</b> (automatisch entstandene Entwürfe). Der Hinweis „Liegengebliebene Entwürfe“ auf der Übersicht führt direkt hierher.</p>
  <div class="doc-note"><strong>Eingereicht ist etwas anderes</strong><p>Änderungen, die ein KI-Assistent oder eine Schnittstelle nur <i>vorschlägt</i> (Zugang „Zur Freigabe“), stehen unter <?= e(\Core\AI\Assist::brand()) ?> → <b>Eingereicht</b> – sie sind noch gar nicht gespeichert. Die Entwürfe-Seite weist darauf hin, wenn dort etwas wartet.</p></div>
