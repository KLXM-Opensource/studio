<?php
/**
 * Infoleiste (Kopfbereich „Klassisch“/„Ausführlich“, Design → „Infoleiste oben“): Öffnungsstatus, Telefon, E-Mail, Sprache.
 * „Jetzt geöffnet“: partials/openstate.php (berechnet von site.js). @var array $langs
 */
$phone = basis_phone();
$tel = basis_phone_href();
$email = basis_email();
$hours = basis_hours();
?>
<div class="topbar">
  <div class="wrap topbar__inner">
    <ul class="topbar__info">
      <?php if ($hours): ?><li><?= app()->theme->partial('openstate') ?></li><?php endif; ?>
      <?php if (filled($phone) && $tel): ?><li><a href="<?= e($tel) ?>"><?= basis_icon('phone') ?><span><?= e($phone) ?></span></a></li><?php endif; ?>
      <?php if ($email !== ''): ?><li class="topbar__mail"><a href="mailto:<?= e($email) ?>"><?= basis_icon('mail') ?><span><?= e($email) ?></span></a></li><?php endif; ?>
    </ul>
    <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs, 'class' => 'langswitch--topbar']) ?><?php endif; ?>
  </div>
</div>
