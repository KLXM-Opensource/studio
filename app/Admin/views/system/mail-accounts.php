<?php
/**
 * Grundeinstellungen → E-Mail-Versand → E-Mail-Konten (MailAccountsController, Core\Mail\Accounts): Seitenleiste der
 * Grundeinstellungen, Liste der Konten (Zeilen wie die Grundeinstellungen), darunter – nur bei „Bearbeiten“ bzw. „Konto
 * hinzufügen“ – eine Karte mit dem Formular.
 * @var array $accounts  @var array $extra  @var ?array $network  @var bool $canNetwork  @var array $fields  @var array $stdFields
 * @var array $errors  @var ?array $draft  @var string $draftId  @var array $me
 */
use Core\Fields;
use Core\Mail\Accounts;

$base = '/admin/system/mail';
$staging = environment() !== 'production';   // Testumgebung: Mailer versendet nicht (Testnachricht kann es mit „wirklich senden“)
$noPort = fn(array $a) => ['port' => !empty($a['port']) ? $a['port'] : ''] + $a;   // leerer Port statt „0“ (dann üblicher Port)
$edit = $draftId !== '' && ($draftId === 'neu' || $draftId === 'standard' || isset($extra[$draftId])) ? $draftId : '';
$formFields = []; $formValues = []; $formTitle = '';
if ($edit === 'standard') {
    $formFields = $stdFields;
    foreach ($formFields as $i => $f) if ($f['name'] === 'transport') $formFields[$i]['options'] = array_intersect_key($f['options'], ['smtp' => 1, 'null' => 1]);
    $formValues = $noPort($draft ?? $accounts['standard']);
    $formTitle = __('Konto „{name}“ bearbeiten', ['name' => __('Standard')]);
} elseif ($edit !== '') {
    $formFields = $fields;
    $formValues = $noPort($draft ?? ($edit === 'neu' ? ['transport' => 'smtp', 'verify_peer' => true, 'encryption' => 'tls'] : $extra[$edit]));
    $formTitle = $edit === 'neu' ? __('Neues Konto') : __('Konto „{name}“ bearbeiten', ['name' => $extra[$edit]['label']]);
    // Neues Konto: „Leer lassen = unverändert“ ergibt keinen Sinn
    if ($edit === 'neu') foreach ($formFields as $i => $f) if ($f['name'] === 'pass') unset($formFields[$i]['help']); elseif ($f['name'] === 'dsn') $formFields[$i]['help'] = str_replace([' Verschlüsselt gespeichert; leer lassen = unverändert.'], [' ' . __('Verschlüsselt gespeichert.')], $f['help']);
}
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a> › <a href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('E-Mail-Versand')) ?></a></p><h1><?= e(__('E-Mail-Konten')) ?></h1>
    <p class="adm-muted"><?= e(__('Konten anlegen, bearbeiten und testen. Welches Konto wofür versendet, wählen Sie unter E-Mail-Versand → Verwendung.')) ?></p></div>
</header>

<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'mail'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">
  <div class="set-page mk-page">
  <section class="set-group" id="konten">
    <h2 class="set-group__title"><?= e(__('Konten')) ?></h2>
    <?php if ($staging): ?><p class="adm-flash adm-flash--info mk-staging" role="note"><?= e(__('Diese Website ist eine Testumgebung ({env}): Sie verschickt keine E-Mails, sondern protokolliert sie nur. Mit „wirklich senden“ geht nur die Testnachricht an Ihre Adresse – so prüfen Sie die Zugangsdaten.', ['env' => environment()])) ?></p><?php endif; ?>
    <div class="set-list">
      <?php foreach ($accounts as $id => $a): $ready = Accounts::ready($a); ?>
      <div class="f f--inline mk-row<?= $edit === $id ? ' is-current' : '' ?>" id="konto-<?= e($id) ?>">
        <span class="mk-row__label"><strong><?= e($a['label']) ?></strong>
          <span class="f-help"><?= e(Accounts::describe($a)) ?><?= $id === 'network' ? ' · ' . e(__('für alle Websites, im Netzwerk-Dashboard')) : '' ?></span></span>
        <span class="mk-row__act">
          <span class="adm-badge adm-badge--<?= $ready ? 'ok' : 'warn' ?>"><?= e($ready ? __('bereit') : __('nicht eingerichtet')) ?></span>
          <?php if ($ready): ?>
          <form method="post" action="<?= e(url($base . '/test/' . $id)) ?>" class="mk-row__test"><?= csrf_field() ?>
            <?php if ($staging): // Testumgebung: nur die Testnachricht wirklich senden (Mailer force_real) ?><label class="mk-test__real"><input type="checkbox" name="real" value="1" checked> <?= e(__('wirklich senden')) ?></label><?php endif; ?>
            <button class="adm-btn adm-btn--small adm-btn--ghost" title="<?= e(__('Testnachricht an {to}', ['to' => (string) $me['email']])) ?>"><?= e(__('Testnachricht')) ?></button></form>
          <?php endif; ?>
          <?php if ($id === 'network'): ?>
            <?php if ($canNetwork): ?><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/network#email')) ?>"><?= e(__('Bearbeiten')) ?></a><?php endif; ?>
          <?php else: ?>
            <a class="adm-btn adm-btn--small" href="<?= e(url($base . '?konto=' . $id . '#formular')) ?>"><?= e(__('Bearbeiten')) ?></a>
          <?php endif; ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="set-group__note"><a class="adm-btn adm-btn--small" href="<?= e(url($base . '?neu=1#formular')) ?>"><?= icon('plus') ?> <?= e(__('Konto hinzufügen')) ?></a>
      <span class="adm-muted"><?= e(__('z. B. für Newsletter – per SMTP oder über einen Dienst wie Mailjet, Mailchimp Transactional oder Brevo.')) ?></span></p>
  </section>

  <?php if ($edit !== ''): ?>
  <form method="post" action="<?= e(url($base . '/konto')) ?>" id="formular" class="mk-form" novalidate>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($edit === 'neu' ? '' : $edit) ?>">
    <?= Fields::renderGroups(array_merge([['type' => 'heading', 'label' => $formTitle]], $formFields), $formValues, $errors, 'f') ?>
    <div class="mk-form__bar">
      <button class="adm-btn adm-btn--primary"><?= e($edit === 'neu' ? __('Konto anlegen') : __('Speichern')) ?></button>
      <a class="adm-btn adm-btn--ghost" href="<?= e(url($base)) ?>"><?= e(__('Abbrechen')) ?></a>
      <?php if (isset($extra[$edit])): ?>
      <button class="adm-btn adm-btn--ghost adm-btn--danger-text mk-form__del" type="submit" form="mk-del" formnovalidate><?= e(__('Konto entfernen')) ?></button>
      <?php endif; ?>
    </div>
  </form>
  <?php if (isset($extra[$edit])): ?>
  <form method="post" id="mk-del" action="<?= e(url($base . '/konto/' . $edit . '/loeschen')) ?>" data-confirm="<?= e(__('Konto „{name}“ entfernen? Zwecke, die es nutzen, versenden danach über „Standard“.', ['name' => $extra[$edit]['label']])) ?>"><?= csrf_field() ?></form>
  <?php endif; ?>
  <?php endif; ?>
  </div>
  </div>
</div>
