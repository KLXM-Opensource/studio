<?php
/**
 * Chat-Einstellungen (Core\Chat): Ein/Aus für diese Website und den Netzwerk-Bereich, Aufbewahrung (Netzwerk/Integratoren),
 * Kanäle (chat.manage), eigene E-Mail-Hinweise. @var array $channels  @var array $roles  @var array $users  @var ?array $me  @var int $edit
 */
use Core\Chat\Chat;

$configured = Chat::configured();
$editRoom = null;
foreach ($channels as $c) if ((int) $c['id'] === $edit) $editRoom = $c;
$acc = $editRoom ? (json_decode((string) ($editRoom['access_json'] ?? ''), true) ?: []) : [];
$selRoles = (array) ($acc['roles'] ?? []);
$selUsers = (array) ($acc['users'] ?? []);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Chat-Einstellungen')) ?></h1>
    <p class="adm-muted"><?= e(__('Direktnachrichten und Kanäle zwischen den Personen, die die Verwaltung nutzen. Der Chat ist optional und standardmäßig ausgeschaltet.')) ?></p></div>
  <?php if (Chat::canUse()): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/chat')) ?>"><?= icon('chats') ?> <?= e(__('Chat öffnen')) ?></a><?php endif; ?>
</header>

<?php if (Chat::canConfigure()): ?>
<form class="adm-card uc-cfg" method="post" action="<?= e(url('/admin/chat/einstellungen')) ?>">
  <?= csrf_field() ?>
  <h2><?= e(__('Ein- und ausschalten')) ?></h2>
  <p class="adm-muted"><?= e(__('Nur die Netzwerk-Administration und Integratoren sehen diesen Abschnitt.')) ?></p>
  <div class="f">
    <label class="f-check"><input type="checkbox" name="site_on" value="1"<?= Chat::siteOn() ? ' checked' : '' ?><?= $configured !== null ? ' disabled' : '' ?>>
      <span><?= e(__('Chat auf dieser Website ({site})', ['site' => site()->label()])) ?></span></label>
    <?php if ($configured !== null): ?><p class="f-help"><?= e(__('Festgelegt in der Konfiguration (\'features\' => [\'chat\' => …]) – hier nicht änderbar.')) ?></p>
    <?php else: ?><p class="f-help"><?= e(__('Beim ersten Einschalten entsteht der Kanal „#redaktion“ für alle mit dem Recht „Chat nutzen“ (Standard: Redaktion und Administration).')) ?></p><?php endif; ?>
  </div>
  <div class="f">
    <label class="f-check"><input type="checkbox" name="network_on" value="1"<?= Chat::networkOn() ? ' checked' : '' ?>>
      <span><?= e(__('Netzwerk-Bereich: Kanäle und Direktnachrichten der Netzwerk-Administration und Integratoren über alle Websites')) ?></span></label>
  </div>
  <div class="f uc-cfg__num">
    <label for="uc-ret"><?= e(__('Nachrichten aufbewahren (Tage, 0 = unbegrenzt)')) ?></label>
    <input id="uc-ret" name="retention" type="number" min="0" max="3650" step="1" inputmode="numeric" value="<?= Chat::retentionDays() ?>" aria-describedby="uc-ret-h">
    <p class="f-help" id="uc-ret-h"><?= e(__('Gilt für alle Websites. Ältere Nachrichten und ihre Bilder werden automatisch gelöscht (auch per Cron: php bin/console chat:purge).')) ?></p>
  </div>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button>
</form>
<?php endif; ?>

<?php if ($me): ?>
<form class="adm-card" method="post" action="<?= e(url('/admin/api/chat/prefs')) ?>">
  <?= csrf_field() ?>
  <h2><?= e(__('Meine Benachrichtigungen')) ?></h2>
  <div class="f">
    <label class="f-check"><input type="checkbox" name="mail" value="1"<?= (int) $me['notify_mail'] ? ' checked' : '' ?>>
      <span><?= e(__('E-Mail, wenn ich erwähnt werde und die Nachricht nach 10 Minuten noch nicht gelesen habe (höchstens stündlich)')) ?></span></label>
    <p class="f-help"><?= e(__('Hinweise im Browser schalten Sie im Chat selbst ein (Glocke) – erst nach Ihrer Zustimmung.')) ?></p>
  </div>
  <button class="adm-btn" type="submit"><?= e(__('Speichern')) ?></button>
</form>
<?php endif; ?>

<?php if (Chat::canManage() && (Chat::siteOn() || Chat::networkOn())): ?>
<section class="adm-card" id="kanaele" aria-labelledby="uc-ch-h">
  <h2 id="uc-ch-h"><?= e(__('Kanäle')) ?></h2>
  <?php if ($channels): ?>
  <div class="adm-card--flush uc-cfg__table">
  <table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Kanal')) ?></th><th scope="col"><?= e(__('Mitglieder')) ?></th><th scope="col"><?= e(__('Nachrichten')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($channels as $c): $a = json_decode((string) ($c['access_json'] ?? ''), true) ?: [];
      $who = [];
      foreach ((array) ($a['roles'] ?? []) as $rk) $who[] = $roles[$rk]['name'] ?? $rk;
      $n = count((array) ($a['users'] ?? []));
      if ($n) $who[] = __('{n} Personen', ['n' => $n]); ?>
      <tr<?= (int) $c['archived'] ? ' class="is-muted"' : '' ?>>
        <td><strong>#<?= e($c['slug']) ?></strong><?php if ($c['scope'] === 'network'): ?> <span class="adm-badge"><?= e(__('Netzwerk')) ?></span><?php endif; ?><?php if ((int) $c['archived']): ?> <span class="adm-badge adm-badge--muted"><?= e(__('archiviert')) ?></span><?php endif; ?>
          <?php if ($c['topic'] !== ''): ?><br><span class="adm-muted"><?= e($c['topic']) ?></span><?php endif; ?></td>
        <td class="adm-muted"><?= e($c['scope'] === 'network' ? __('Netzwerk-Administration und Integratoren') : ($who ? implode(', ', $who) : __('Alle mit Chat-Recht'))) ?></td>
        <td class="adm-muted"><?= (int) $c['n'] ?></td>
        <td class="adm-actions">
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/chat/einstellungen?kanal=' . (int) $c['id'] . '#kanal-form')) ?>"><?= e(__('Bearbeiten')) ?><span class="sr-only"> #<?= e($c['slug']) ?></span></a>
          <form method="post" action="<?= e(url('/admin/chat/kanaele/' . (int) $c['id'] . '/archivieren')) ?>"<?= (int) $c['archived'] ? '' : ' data-confirm="' . e(__('Kanal #{name} archivieren? Er verschwindet aus dem Chat; Nachrichten bleiben bis zum Ablauf der Aufbewahrung erhalten.', ['name' => $c['slug']])) . '"' ?>><?= csrf_field() ?>
            <button class="adm-btn adm-btn--small adm-btn--ghost"><?= e((int) $c['archived'] ? __('Wiederherstellen') : __('Archivieren')) ?><span class="sr-only"> #<?= e($c['slug']) ?></span></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="adm-muted"><?= e(__('Noch keine Kanäle.')) ?></p><?php endif; ?>

  <form class="uc-cfg__form" id="kanal-form" method="post" action="<?= e(url('/admin/chat/kanaele' . ($editRoom ? '/' . (int) $editRoom['id'] : ''))) ?>">
    <?= csrf_field() ?>
    <h3><?= e($editRoom ? __('Kanal #{name} bearbeiten', ['name' => $editRoom['slug']]) : __('Neuer Kanal')) ?></h3>
    <div class="adm-fields">
      <div class="f f--half"><label for="uc-name"><?= e(__('Name')) ?> <span class="req">*</span></label>
        <input id="uc-name" name="name" required maxlength="40" value="<?= e($editRoom['slug'] ?? '') ?>" placeholder="redaktion" aria-describedby="uc-name-h">
        <p class="f-help" id="uc-name-h"><?= e(__('Kleinbuchstaben, Ziffern und Bindestriche – erscheint als #name.')) ?></p></div>
      <div class="f f--half"><label for="uc-topic"><?= e(__('Thema (optional)')) ?></label><input id="uc-topic" name="topic" maxlength="200" value="<?= e($editRoom['topic'] ?? '') ?>"></div>
    </div>
    <?php if (Chat::isNet() && !$editRoom): ?>
    <fieldset class="f uc-cfg__set"><legend class="f-label"><?= e(__('Bereich')) ?></legend>
      <label class="f-check"><input type="radio" name="scope" value="site" checked> <span><?= e(__('Diese Website ({site})', ['site' => site()->label()])) ?></span></label>
      <label class="f-check"><input type="radio" name="scope" value="network"<?= Chat::networkOn() ? '' : ' disabled' ?>> <span><?= e(__('Netzwerk (alle Websites, nur Netzwerk-Administration und Integratoren)')) ?></span></label>
    </fieldset>
    <?php endif; ?>
    <?php if (!$editRoom || $editRoom['scope'] === 'site'): ?>
    <fieldset class="f uc-cfg__set"><legend class="f-label"><?= e(__('Mitglieder nach Rolle')) ?></legend>
      <p class="f-help"><?= e(__('Ohne Auswahl (Rollen und Personen) gehört der Kanal allen, die den Chat nutzen dürfen.')) ?></p>
      <div class="uc-cfg__checks">
      <?php foreach ($roles as $rk => $ro): if ($rk === 'network') continue; ?>
        <label class="f-check"><input type="checkbox" name="roles[]" value="<?= e($rk) ?>"<?= in_array($rk, $selRoles, true) ? ' checked' : '' ?>> <span><?= e($ro['name']) ?></span></label>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <?php if ($users): ?>
    <fieldset class="f uc-cfg__set"><legend class="f-label"><?= e(__('Weitere Personen')) ?></legend>
      <div class="uc-cfg__checks">
      <?php foreach ($users as $u): ?>
        <label class="f-check"><input type="checkbox" name="users[]" value="<?= e($u['key']) ?>"<?= in_array($u['key'], $selUsers, true) ? ' checked' : '' ?>> <span><?= e($u['name']) ?> <small class="adm-muted"><?= e($u['email']) ?></small></span></label>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endif; ?>
    <?php endif; ?>
    <div class="adm-row">
      <button class="adm-btn adm-btn--primary" type="submit"><?= e($editRoom ? __('Speichern') : __('Kanal anlegen')) ?></button>
      <?php if ($editRoom): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/chat/einstellungen#kanaele')) ?>"><?= e(__('Abbrechen')) ?></a><?php endif; ?>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="adm-card">
  <h2><?= e(__('Gut zu wissen')) ?></h2>
  <ul class="uc-cfg__facts">
    <li><?= e(__('Übertragung: {mode}', ['mode' => Chat::transport() === 'sse' ? __('Server-Sent Events (Live-Verbindung, je 25 Sekunden neu)') : __('Abfrage alle 3 Sekunden (Entwicklungsserver)')])) ?></li>
    <li><?= e(__('Nachrichten liegen zentral für alle Websites in storage/chat/chat.sqlite; Bilder nur für Beteiligte abrufbar.')) ?></li>
    <li><a href="<?= e(url('/admin/hilfe#chat')) ?>"><?= e(__('Handbuch: Chat')) ?></a></li>
  </ul>
</section>
