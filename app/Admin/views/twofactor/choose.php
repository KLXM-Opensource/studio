<?php
/** Zweiten Faktor einrichten (verlangt oder Erinnerung während der Übergangsfrist) – Core\Mfa.
 *  @var array $pol  @var ?string $req  @var bool $forced  @var int $until  @var array $factors  @var string $next  @var bool $recent  @var bool $available */
$offerTotp = $pol['totp'] && $req !== 'passkey' && !$factors['totp'];
?>
<header class="adm-head"><div><p class="adm-eyebrow"><a href="<?= e(url('/admin/account')) ?>"><?= e(__('Konto')) ?></a></p><h1><?= e(__('Anmeldung absichern')) ?></h1>
  <p class="adm-muted"><?php if ($req === 'passkey'): ?><?= e(__('Für Ihr Konto ist ein Passkey vorgeschrieben – er schützt zuverlässig vor gefälschten Anmeldeseiten. Eine Authenticator-App genügt hier nicht.')) ?>
  <?php elseif ($req !== null): ?><?= e(__('Für Ihr Konto ist ein zweiter Faktor vorgeschrieben. Wählen Sie ein Verfahren – danach geht es weiter.')) ?>
  <?php else: ?><?= e(__('Schützen Sie Ihr Konto zusätzlich zum Passwort.')) ?><?php endif; ?></p>
  <?php if (!$forced && $until > 0): ?><p class="adm-flash pk-warn" role="status"><?= e(__('Bitte bis {date} einrichten – danach ist die Anmeldung ohne zweiten Faktor nicht mehr möglich.', ['date' => date('d.m.Y', $until)])) ?></p><?php endif; ?>
</div></header>
<div class="pk-choose">
  <?php if ($pol['passkey']): ?>
  <section class="adm-card" aria-labelledby="ch-pk-h">
    <h2 id="ch-pk-h"><?= e(__('Passkey')) ?> <span class="adm-badge"><?= e(__('empfohlen')) ?></span></h2>
    <p class="adm-muted"><?= e(__('Anmelden mit Fingerabdruck, Gesicht oder Geräte-PIN – auf Mac und iPhone (iCloud-Schlüsselbund), Android (Google Passwortmanager), Windows Hello, in Passwortmanagern oder mit einem Sicherheitsschlüssel.')) ?></p>
    <?php if ($factors['passkeys'] > 0): ?><p class="f-help"><?= e(__('{n} Passkey(s) eingerichtet.', ['n' => $factors['passkeys']])) ?></p><?php endif; ?>
    <?php $pkRecent = $recent; $pkAvail = $available; $pkBack = 'choose'; $pkId = 'ch'; include __DIR__ . '/_passkey_add.php'; ?>
  </section>
  <?php endif; ?>
  <?php if ($offerTotp): ?>
  <section class="adm-card" aria-labelledby="ch-tf-h">
    <h2 id="ch-tf-h"><?= e(__('Authenticator-App')) ?></h2>
    <p class="adm-muted"><?= e(__('6-stelliger Code aus einer App auf Ihrem Telefon (z. B. 2FAS, Aegis, Google oder Microsoft Authenticator, 1Password).')) ?></p>
    <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/account/2fa')) ?>"><?= e(__('App einrichten')) ?></a>
  </section>
  <?php endif; ?>
</div>
<?php if ($forced): ?><form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--ghost" type="submit"><?= e(__('Abmelden')) ?></button></form>
<?php else: ?><p><a class="adm-btn adm-btn--ghost" href="<?= e(url($next)) ?>"><?= e(__('Später erinnern')) ?></a></p><?php endif; ?>
