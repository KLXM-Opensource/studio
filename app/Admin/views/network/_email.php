<?php /** Netzwerk → E-Mail-Konto des Netzwerks (Teil von network/index.php, Variablen von dort) */ ?>
  <?php $nm = \Core\Mail\Accounts::network(); $nmErr = $mailErrors ?? []; $nmDraft = $mailDraft ?? null; // E-Mail-Konto des Netzwerks (Core\Mail\Accounts) ?>
  <section class="adm-card" id="email" aria-labelledby="net-mail-h">
    <h2 id="net-mail-h" class="adm-sr"><?= e(__('E-Mail-Konto des Netzwerks')) ?></h2>
    <p class="adm-muted"><?= e(__('Für die ganze Installation: Mails an Netzwerk-Konten (Einladungen, Passwort) und die Mails von Websites ohne eigenes Konto. Mit neutralem Absender verrät keine Mail einem Kunden, welche anderen Kunden die Installation nutzen. Websites wählen ihre eigenen Konten in den Grundeinstellungen.')) ?></p>
    <?php if ($nm): ?>
    <p class="net-mail__state"><span class="adm-badge adm-badge--<?= \Core\Mail\Accounts::ready($nm) ? 'ok' : 'warn' ?>"><?= e(\Core\Mail\Accounts::ready($nm) ? __('bereit') : __('nicht eingerichtet')) ?></span> <?= e(\Core\Mail\Accounts::describe($nm)) ?></p>
    <form method="post" action="<?= e(url('/admin/network/mail/test')) ?>" class="mk-test">
      <?= csrf_field() ?><label class="adm-sr" for="net-mail-to"><?= e(__('Empfänger der Testnachricht')) ?></label>
      <input type="email" id="net-mail-to" name="to" placeholder="<?= e((string) (app()->auth->user()['email'] ?? '')) ?>">
      <button class="adm-btn adm-btn--small"><?= e(__('Testnachricht')) ?></button>
      <?php if (environment() !== 'production'): // Testumgebung: Mails gehen sonst nicht hinaus (Mailer) – für den Test bewusst umgehen ?>
      <label class="mk-test__real" title="<?= e(__('Diese Website ist eine Testumgebung ({env}) und verschickt sonst keine E-Mails. Mit dieser Option geht nur die Testnachricht wirklich an die eingegebene Adresse – so lassen sich die Zugangsdaten prüfen.', ['env' => environment()])) ?>"><input type="checkbox" name="real" value="1" checked> <?= e(__('Testumgebung umgehen – wirklich senden')) ?></label>
      <?php endif; ?>
    </form>
    <?php else: ?>
    <p class="adm-flash adm-flash--info" role="note"><?= e(__('Noch kein E-Mail-Konto des Netzwerks: Mails an Netzwerk-Konten gehen bis dahin über das Konto der jeweiligen Website.')) ?></p>
    <?php endif; ?>
    <details class="net-add"<?= $nmErr || !$nm ? ' open' : '' ?>>
      <summary class="adm-btn adm-btn--small"><?= e($nm ? __('Konto bearbeiten') : __('Konto einrichten')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/mail')) ?>" class="adm-fields" novalidate>
        <?= csrf_field() ?>
        <?php $nv = $nmDraft ?? ($nm ?? ['transport' => 'smtp', 'verify_peer' => true, 'encryption' => 'tls', 'from_name' => 'KLXM Studio']); $nv['port'] = !empty($nv['port']) ? $nv['port'] : ''; ?>
        <?= \Core\Fields::renderForm(\Core\Http\Controllers\Admin\MailAccountsController::fields(true), $nv, $nmErr, 'n') ?>
        <div class="adm-form-actions">
          <button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
          <?php if ($nm): ?><button class="adm-btn adm-btn--ghost adm-btn--danger-text" name="remove" value="1" formnovalidate data-confirm="<?= e(__('Netzwerk-Konto entfernen? Netzwerk-Mails gehen dann über das Konto der jeweiligen Website.')) ?>"><?= e(__('Entfernen')) ?></button><?php endif; ?>
        </div>
      </form>
    </details>
  </section>
