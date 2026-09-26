<?php /** Wiederherstellungscodes (einmal angezeigt). @var list<string> $codes  @var bool $fresh */ ?>
<header class="adm-head"><div><p class="adm-eyebrow"><a href="<?= e(url('/admin/account')) ?>"><?= e(__('Konto')) ?></a></p><h1><?= e(__('Wiederherstellungscodes')) ?></h1>
  <?php if ($fresh): ?><p class="adm-muted"><?= e(__('Die Zwei-Faktor-Anmeldung ist aktiv.')) ?></p><?php endif; ?></div></header>
<section class="adm-card adm-card--secret adm-narrow" aria-labelledby="tf-codes-h">
  <h2 id="tf-codes-h"><?= e(__('Jetzt sicher aufbewahren')) ?></h2>
  <p><?= e(!empty($passkey) ? __('Mit jedem dieser Codes können Sie sich einmal ohne Passkey anmelden – z. B. wenn das Gerät verloren ist. Sie werden nur jetzt angezeigt – ausdrucken oder im Passwortmanager speichern.')
    : __('Mit jedem dieser Codes können Sie sich einmal ohne Telefon anmelden. Sie werden nur jetzt angezeigt – z. B. im Passwortmanager speichern. Ältere Codes gelten nicht mehr.')) ?></p>
  <ol class="tf-codes" id="tf-codes"><?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ol>
  <div class="adm-row"><button type="button" class="adm-btn adm-btn--small" data-copy="#tf-codes"><?= e(__('Kopieren')) ?></button>
    <a class="adm-btn adm-btn--primary adm-btn--small" href="<?= e(url(\Core\Network\Network::isNetworkSite() && \Core\Network\Network::isNetworkUser() ? '/admin/network' : (!empty($passkey) ? '/admin/account#passkeys' : '/admin/account#zwei-faktor'))) ?>"><?= e(__('Gespeichert – weiter')) ?></a></div>
</section>
