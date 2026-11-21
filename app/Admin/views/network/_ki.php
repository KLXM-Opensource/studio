<?php
/**
 * Netzwerk → KI-Verbindungen (Teil von network/index.php, nur mit mehreren Websites): Verbindungen (Anbieter, Schlüssel), Modelle
 * und Verwendung der Installation (Core\AI\Profiles, storage/ai/config.json) – dieselbe Ansicht wie bisher in Grundeinstellungen → KI
 * (system/_ai.php, part top + forms). Was eine Website mit der KI macht (Datenschutz, Besucher-Chat), stellt sie selbst ein.
 */
?>
  <section class="adm-card" id="ki" aria-labelledby="net-ki-h">
    <h2 id="net-ki-h" class="adm-sr"><?= e(__('KI-Verbindungen')) ?></h2>
    <p class="adm-muted"><?= e(__('Verbindungen und Modelle gelten für alle Websites. Ob eine Website KI nutzt und was sie Besuchern dazu sagt, stellt sie in ihren Grundeinstellungen unter „KI“ ein.')) ?></p>
    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'top']) ?>
  </section>
  <?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'forms']) ?>
