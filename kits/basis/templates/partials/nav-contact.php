<?php
/**
 * Kontakt im Mobilmenü (Modern, Klassisch, Ausführlich; nur < 960 px sichtbar): Öffnungsstatus, Telefon, E-Mail.
 * Gleiche Angaben wie in der Infoleiste – so sind sie auch ohne Infoleiste im Menü erreichbar.
 */
$phone = basis_phone();
$tel = basis_phone_href();
$email = basis_email();
$state = app()->theme->partial('openstate');
if (!(filled($phone) && $tel) && $email === '' && trim($state) === '') return;
?>
<div class="site-nav__contact">
  <p class="site-nav__label"><?= e(lt('Direkter Kontakt')) ?></p>
  <?= $state ?>
  <ul>
    <?php if (filled($phone) && $tel): ?><li><a href="<?= e($tel) ?>"><?= basis_icon('phone') ?><span><?= e($phone) ?></span></a></li><?php endif; ?>
    <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= basis_icon('mail') ?><span><?= e($email) ?></span></a></li><?php endif; ?>
  </ul>
</div>
