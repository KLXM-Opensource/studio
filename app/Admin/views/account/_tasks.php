<?php
/**
 * Konto → Mitteilungen → Aufgaben (Core\Tasks\Notify): E-Mail bei neuer Zuweisung und Erinnerung an fällige Aufgaben – je Konto
 * abschaltbar (Core\UiPrefs „tasks.mail“, Standard an). Push-Mitteilungen dazu stehen unter „Benachrichtigungen“.
 * @var array $user
 */
$tkMail = (bool) \Core\UiPrefs::get($user, \Core\Tasks\Tasks::MAIL_PREF, true);
?>
<section class="set-group acc-group" id="aufgaben" aria-labelledby="tk-acc-h">
  <h2 class="set-group__title" id="tk-acc-h"><?= e(__('Aufgaben')) ?></h2>
  <form class="set-list acc-box" method="post" action="<?= e(url('/admin/account/aufgaben')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="mail" value="0">
    <div class="set-row set-row--keep">
      <div class="set-row__main"><label class="set-row__label" for="tk-acc-mail"><?= e(__('E-Mail bei neuen Aufgaben')) ?></label>
        <span class="set-row__sub" id="tk-acc-mail-h"><?= e(__('Wenn Ihnen oder Ihrer Rolle eine Aufgabe zugewiesen wird – und als Erinnerung, wenn sie fällig wird (sofern die Website Erinnerungen eingeschaltet hat).')) ?></span></div>
      <div class="set-row__ctl"><input type="checkbox" role="switch" id="tk-acc-mail" name="mail" value="1"<?= $tkMail ? ' checked' : '' ?> aria-describedby="tk-acc-mail-h" data-autosubmit></div>
    </div>
    <noscript><div class="set-row"><div class="set-row__ctl"><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button></div></div></noscript>
  </form>
</section>
