<?php /** Kontakt – alle Angaben aus „Website“, optional mit Öffnungszeiten und Karte. @var \Core\Block $b  @var array $d */
$address = basis_address_lines();
$phone = basis_phone();
$tel = basis_phone_href();
$email = basis_email();
$hours = !empty($d['show_hours']) ? basis_hours() : [];
$hoursNote = trim((string) setting('hours_note'));
$map = !empty($d['show_map']) && \Core\Maps::siteLocation() ? \Core\Maps::renderBlock(['location' => 'site', 'zoom' => 15, 'height' => 'm', 'route' => true]) : '';
$c = $b->central();
?>
<div class="wrap">
  <?= basis_head($b) ?>
  <div class="contact<?= $map !== '' ? ' contact--map' : '' ?>">
    <div class="contact__cards"<?= $c ?>>
      <?php if ($address || is_editing()): ?>
      <div class="contact__card">
        <span class="contact__icon"><?= basis_icon('pin') ?></span>
        <h3 class="contact__h"><?= e(lt('Adresse')) ?></h3>
        <address class="contact__address"><?= e(basis_name()) ?><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php else: ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif; ?></address>
      </div>
      <?php endif; ?>
      <?php if (($tel && filled($phone)) || $email !== '' || is_editing()): ?>
      <div class="contact__card">
        <span class="contact__icon"><?= basis_icon('chat') ?></span>
        <h3 class="contact__h"><?= e(lt('Telefon & E-Mail')) ?></h3>
        <ul class="contact__list">
          <?php if ($tel && filled($phone)): ?><li><span class="contact__label"><?= e(lt('Telefon')) ?></span><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><span class="contact__label"><?= e(lt('E-Mail')) ?></span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if (!($tel && filled($phone)) && $email === ''): ?><li>[<?= e('Telefon und E-Mail unter „Website“ eintragen') ?>]</li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="contact__card">
        <span class="contact__icon"><?= basis_icon('clock') ?></span>
        <h3 class="contact__h"><?= e(lt('Öffnungszeiten')) ?></h3>
        <?= app()->theme->partial('hours', ['hours' => $hours]) ?>
        <?php if ($hoursNote !== ''): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($map !== ''): ?><div class="contact__map"><?= $map ?></div><?php endif; ?>
  </div>
  <?php if ($d['note'] !== '' || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
