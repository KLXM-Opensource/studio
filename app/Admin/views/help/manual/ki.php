<?php /** Handbuch · Kapitel „KI-Assistenten über MCP & Freigabe“ (Variablen: siehe help/manual.php; $vars['mcp_examples'] = [[Auftrag, Wirkung], …]) */
$__examples = $vars['mcp_examples'] ?? [
    ['„Leg einen Beitrag zu unserem Sommerfest als Entwurf an.“', 'legt einen Eintrag an'],
    ['„Ändere die Telefonnummer auf …“', 'ändert die zentralen Angaben'],
    ['„Welche Platzhalter sind noch offen?“', 'erstellt eine Prüfliste'],
];
$__brand = \Core\AI\Assist::brand();
?>
  <p class="lead">Über den MCP-Server und die REST-API kann ein externer KI-Assistent wie Claude die Website in Ihrem Auftrag pflegen – Sie formulieren einfach, was geändert werden soll. Die KI-Funktionen in der Verwaltung selbst beschreibt das Kapitel <a href="#<?= e($anchor('assistent')) ?>"><?= e($__brand) ?></a>.</p>
  <div class="doc-cards">
    <?php foreach ($__examples as [$__q, $__a]): ?><div class="doc-card"><span class="doc-card__icon"><?= icon('chat-circle-text') ?></span><span class="doc-card__title"><?= e($__q) ?></span><span class="doc-card__sub"><?= e($__a) ?></span></div><?php endforeach; ?>
  </div>
  <ul>
    <li>Die Einrichtung übernimmt die Administration unter <b>Administration → Einstellungen → API &amp; MCP</b> (Zugangstoken erzeugen, Assistent verbinden).</li>
    <li>Seitenänderungen des Assistenten landen als <b>Entwurf</b> – Sie prüfen und veröffentlichen. Änderungen an <?= e($settingsTitle) ?> und Einträgen wirken sofort, sofern der Zugang nicht „Zur Freigabe“ eingestellt ist (siehe unten).</li>
    <li>Jede Änderung erscheint unter <b>Versionen</b> mit dem Namen des Zugangs („API „Claude – Redaktion““).</li>
    <li>Online-Anfragen bleiben verschlüsselt: Der Assistent sieht nur, <i>dass</i> Anfragen vorliegen, nie deren Inhalt.</li>
  </ul>
  <h3 id="eingereicht">Eingereicht: Vorschläge prüfen und freigeben</h3>
  <ul>
    <li><b>Zur Freigabe</b>: Ist ein Zugang so eingestellt (Standard für neue Zugänge mit Schreibrecht), ändert der Assistent nichts direkt. Seine Vorschläge stehen unter <b><?= e($__brand) ?> → Eingereicht</b> – mit Vorher/Nachher, Herkunft und Zeitpunkt. Dort <b>Übernehmen</b>, <b>Bearbeiten &amp; übernehmen</b> oder mit Begründung <b>Ablehnen</b>; der Assistent erfährt die Entscheidung. Eine Zahl im Menü zeigt, wie viele Vorschläge warten.</li>
    <li>Wurde der Inhalt inzwischen von jemand anderem geändert, zeigt „Eingereicht“ einen <b>Konflikt</b> mit allen drei Ständen. „Neu prüfen“ wendet den Vorschlag auf den aktuellen Stand an.</li>
    <li>Auch direkt übernommene Änderungen von API, MCP und KI stehen dort als Verlauf (Reiter „Übernommen“ bzw. „Alle“).</li>
    <li>Wer prüfen darf, legt die Administration fest (Recht „Eingereichte Änderungen von API, MCP und KI prüfen und freigeben“; Standard: nur Administration). Auf Wunsch laufen auch Übernahmen aus <?= e($__brand) ?> (Texte, Übersetzungen, SEO, Alt-Texte) über die Freigabe.</li>
  </ul>
  <div class="doc-note doc-note--warn"><strong>Die Verantwortung bleibt bei Ihnen</strong><p>Lassen Sie den Assistenten keine Fakten, Preise oder rechtlichen Aussagen erfinden. Texte vor dem Veröffentlichen lesen.</p></div>
