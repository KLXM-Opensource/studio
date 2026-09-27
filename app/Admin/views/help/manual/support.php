<?php /** Handbuch · Kapitel „Hilfe & Support“ · @var ?string $tutorials */ ?>
  <p class="lead">Wenn etwas nicht klappt oder Sie eine Frage haben: Erst in der <b>Wissensdatenbank</b> nachsehen, dann die anderen Redaktionen fragen oder dem <b>Support-Team</b> ein Problem melden – alles in der Verwaltung unter <b>Support</b>.</p>
  <?php if ($tutorials): ?><p>Kurze Videos zu den wichtigsten Abläufen finden Sie unter <a href="<?= e($tutorials) ?>">Handbuch &amp; Hilfe → Tutorials</a>. Die Videos sind ohne Ton und haben Untertitel auf Deutsch und Englisch (bei englischer Verwaltungssprache sind die englischen voreingestellt) – ein- und ausschalten über „CC“ im Player. Die Schritte unter jedem Video sind der vollständige Text zum Nachlesen.</p><?php endif; ?>
  <div class="doc-cards">
    <a class="doc-card" href="<?= e(url('/admin/support/wissen')) ?>"><span class="doc-card__icon"><?= icon('books') ?></span><span class="doc-card__title">Wissensdatenbank</span><span class="doc-card__sub">Anleitungen und gesammelte Lösungen – durchsuchbar, nach Tags sortiert.</span></a>
    <a class="doc-card" href="<?= e(url('/admin/support/fragen')) ?>"><span class="doc-card__icon"><?= icon('question') ?></span><span class="doc-card__title">Fragen &amp; Antworten</span><span class="doc-card__sub">Redaktionen helfen sich gegenseitig; die beste Antwort wird zur Lösung.</span></a>
    <a class="doc-card" href="<?= e(url('/admin/support/neu')) ?>"><span class="doc-card__icon"><?= icon('warning') ?></span><span class="doc-card__title">Problem melden</span><span class="doc-card__sub">Fehler, Fragen oder Wünsche direkt an das Support-Team.</span></a>
  </div>
  <h3 id="projekt-hinweise">Hinweise zu diesem Projekt</h3>
  <p>Was nur für diese Website gilt – eigene Blöcke, Bildformate, Abläufe wie „Sondermeldungen so planen“ –, steht im Kapitel <b>Hinweise zu diesem Projekt</b> gleich nach dem Überblick<?php if ($has('projekt')): ?> (<a href="#projekt">zum Kapitel</a>)<?php endif; ?>. Das Kapitel erscheint nur, wenn das Kit oder die Website solche Hinweise mitbringt; es ist außerdem über die Übersicht („Neu hier?“), die Suche (<kbd>⌘</kbd> <kbd>K</kbd>) und kleine Links „Hinweis zum Projekt“ in den betroffenen Bereichen erreichbar.</p>
  <ol class="doc-steps">
    <li>Die Administration öffnet <b>Handbuch → Projekt-Hinweise</b> (<a href="<?= e(url('/admin/hilfe/projekt')) ?>">/admin/hilfe/projekt</a>). Die Liste zeigt alle Hinweise mit Herkunft (Kit oder Website) und Bezug.</li>
    <li><b>Neuer Hinweis</b> legt einen eigenen Hinweis dieser Website an: Text in Markdown (erste Zeile <code># Titel</code>, dann Absätze, Listen, <code>**fett**</code>, Links; kein HTML). Die Zahl vorn im Dateinamen (<code>10-…</code>, <code>20-…</code>) bestimmt die Reihenfolge. Optional verknüpfen Sie ihn in den Kopfzeilen mit einem Bereich der Verwaltung oder einem Block.</li>
    <li>Hinweise aus dem Kit passen Sie mit <b>Für diese Website anpassen</b> an oder blenden sie für diese Website aus (Kopfzeile <code>ausblenden: ja</code>); <b>Anpassung entfernen</b> stellt den Hinweis des Kits wieder her.</li>
  </ol>
  <h3>Problem melden</h3>
  <ol class="doc-steps">
    <li>Links unten in der Seitenleiste auf <b>Problem melden</b> klicken (oder <kbd>⌘</kbd> <kbd>K</kbd> → „Problem melden“). Die Seite, auf der Sie gerade waren, wird als Kontext mitgenommen.</li>
    <li>Wählen Sie <b>Frage</b>, <b>Fehler</b> oder <b>Wunsch</b>, geben Sie einen kurzen Titel ein und beschreiben Sie, was passiert ist und was Sie erwartet hätten. Während Sie schreiben, schlägt die Seite rechts passende Artikel und bereits beantwortete Fragen vor – oft ist die Lösung schon da.</li>
    <li>Optional <b>Bildschirmfotos</b> anhängen: Datei wählen, in den Bereich ziehen oder ein kopiertes Bildschirmfoto mit <kbd>Strg</kbd>/<kbd>⌘</kbd>+<kbd>V</kbd> in die Beschreibung einfügen. Nur Bilder, höchstens 5 × 8 MB.</li>
    <li><b>Dringend</b> nur wählen, wenn die Website nicht erreichbar ist, Formulare nicht ankommen oder etwas Wichtiges falsch online ist.</li>
    <li>Vor dem Senden sehen Sie, welche <b>technischen Angaben</b> mitgehen (Website, aktuelle Seite, Browser, Fenstergröße, CMS-Version, Kit, Ihre Rolle). Mit dem Häkchen „Technische Angaben mitsenden“ können Sie das abwählen.</li>
  </ol>
  <p>Unter <b>Support › Meine Meldungen</b> sehen Sie alle Ihre Meldungen mit Status: <b>Neu</b> → <b>In Arbeit</b> → <b>Wartet auf Rückmeldung</b> → <b>Gelöst</b> → <b>Geschlossen</b>. Antwortet das Support-Team, bekommen Sie eine E-Mail, und in der Seitenleiste erscheint eine Zahl an „Support“. Wartet das Team auf Sie, antworten Sie einfach in der Meldung. Hat sich etwas erledigt, klicken Sie auf <b>Problem ist gelöst</b>.</p>
  <h3>Fragen &amp; Antworten</h3>
  <ul>
    <li><b>Frage stellen</b> (Support › Fragen &amp; Antworten): Titel als kurze Frage, Details, passende Tags. Sichtbar für alle Websites dieser Installation oder nur für Ihre Website.</li>
    <li>Gute Fragen und Antworten bekommen mit <b>▲</b> eine Stimme (eine je Person). Wer gefragt hat, markiert die beste Antwort <b>als Lösung</b>.</li>
    <li>Redaktionen anderer Websites sehen Ihren Namen nicht. Bitte trotzdem keine Kundendaten, Passwörter oder Gesundheitsdaten in Fragen oder Meldungen schreiben.</li>
  </ul>
  <div class="doc-note doc-note--info"><strong>Wer sieht was?</strong><p>Ihre Meldungen sehen Sie selbst, die Administration Ihrer Website und das Support-Team. Interne Notizen des Support-Teams sehen nur dessen Mitglieder. Bildschirmfotos sind nicht öffentlich – sie lassen sich nur angemeldet und nur mit Zugriff auf die Meldung öffnen.</p></div>
