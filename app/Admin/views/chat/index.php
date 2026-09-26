<?php /** Chat (Vollbild) – resources/js/userchat.js baut die Oberfläche in [data-chat-page] auf. @var int $room */ ?>
<header class="adm-head uc-pagehead">
  <div><p class="adm-eyebrow"><?= e(__('Zusammenarbeit')) ?></p><h1><?= e(__('Chat')) ?></h1>
    <p class="adm-muted"><?= e(__('Direktnachrichten und Kanäle für alle, die diese Website betreuen.')) ?></p></div>
  <?php if (\Core\Chat\Chat::settingsVisible()): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/chat/einstellungen')) ?>"><?= icon('gear-six') ?> <?= e(__('Einstellungen')) ?></a><?php endif; ?>
</header>
<div class="uc-page" data-chat-page data-room="<?= (int) $room ?>">
  <noscript><p class="adm-card"><?= e(__('Der Chat braucht JavaScript. Bitte aktivieren Sie JavaScript in Ihrem Browser.')) ?></p></noscript>
</div>
