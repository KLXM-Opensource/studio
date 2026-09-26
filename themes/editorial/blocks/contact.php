<?php /** Kontakt – alle Angaben aus „Website“, optional mit Geschäftszeiten und Karte. @var \Core\Block $b  @var array $d */
$address = editorial_address_lines();
$phone = editorial_phone();
$tel = editorial_phone_href();
$email = editorial_email();
$hours = !empty($d['show_hours']) ? editorial_hours() : [];
$hoursNote = trim((string) setting('hours_note'));
$map = !empty($d['show_map']) && \Core\Maps::siteLocation() ? \Core\Maps::renderBlock(['location' => 'site', 'zoom' => 15, 'height' => 'm', 'route' => true]) : '';
$c = $b->central();
?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <div class="contact<?= $map !== '' ? ' contact--map' : '' ?>">
    <dl class="contact__facts"<?= $c ?>>
      <?php if ($address || is_editing()): ?>
      <div class="contact__row"><dt><?= e(lt('Adresse')) ?></dt>
        <dd><address><?= e(editorial_name()) ?><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php else: ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif; ?></address></dd></div>
      <?php endif; ?>
      <?php if ($tel && filled($phone)): ?><div class="contact__row"><dt><?= e(lt('Telefon')) ?></dt><dd><a href="<?= e($tel) ?>"><?= e($phone) ?></a></dd></div><?php endif; ?>
      <?php if ($email !== ''): ?><div class="contact__row"><dt><?= e(lt('E-Mail')) ?></dt><dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div><?php endif; ?>
      <?php if ($hours): ?>
      <div class="contact__row"><dt><?= e(lt('Geschäftszeiten')) ?></dt><dd>
        <ul class="hours"><?php foreach ($hours as $h): ?><li><span class="hours__days"><?= e($h['days']) ?></span> <span class="hours__time"><?= e($h['time']) ?></span></li><?php endforeach; ?></ul>
        <?php if ($hoursNote !== ''): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif; ?>
      </dd></div>
      <?php endif; ?>
    </dl>
    <?php if ($map !== ''): ?><div class="contact__map"><?= $map ?></div><?php endif; ?>
  </div>
  <?php if ($d['note'] !== '' || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
