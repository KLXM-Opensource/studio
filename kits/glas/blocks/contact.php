<?php
/**
 * Kontakt: Angaben (Adresse, Telefon/E-Mail) und Öffnungszeiten auf Glas mit Anzeige „Jetzt geöffnet“ (site.js),
 * daneben Formular und/oder Karte. Alles aus „Website“; Formular = öffentliches Formular einer Datentabelle
 * (Core\Data\DataForms, Spamschutz ohne Cookies).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\DataForms;
use Core\Data\Tables;

$address = glas_address_lines();
$phone = glas_phone();
$tel = glas_phone_href();
$email = glas_email();
$hours = !empty($d['show_hours']) ? glas_hours() : [];
$hoursNote = trim((string) setting('hours_note'));
$map = !empty($d['show_map']) && \Core\Maps::siteLocation() ? \Core\Maps::renderBlock(['location' => 'site', 'zoom' => 15, 'height' => 'm', 'route' => true]) : '';
$t = trim((string) ($d['form_table'] ?? '')) !== '' ? Tables::find((string) $d['form_table']) : null;
$form = '';
if ($t && DataForms::enabled($t)) {
    $state = DataForms::$state[$t['handle']] ?? [];
    $form = DataForms::render($t, ['uid' => $b->domId() . '-f', 'submit' => $d['submit_label'] ?? '',
        'values' => $state['values'] ?? [], 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
        'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null]);
} elseif ($t && is_editing()) {
    $form = '<p class="empty-hint">' . e('Für „' . $t['name'] . '“ ist das öffentliche Formular ausgeschaltet (Daten → Tabelle → Felder → Öffentliches Formular).') . '</p>';
}
$c = $b->central();
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <div class="contact<?= ($form !== '' || $map !== '') ? ' contact--aside' : '' ?>">
    <div class="contact__info panel"<?= $c ?>>
      <div class="contact__sec">
        <p class="contact__h label"><?= e(lt('Adresse')) ?></p>
        <address class="contact__address"><strong><?= e(glas_name()) ?></strong><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php elseif (is_editing()): ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif; ?></address>
      </div>
      <?php if (($tel && filled($phone)) || $email !== ''): ?>
      <div class="contact__sec">
        <p class="contact__h label"><?= e(lt('Telefon & E-Mail')) ?></p>
        <ul class="contact__list" role="list">
          <?php if ($tel && filled($phone)): ?><li><a href="<?= e($tel) ?>"><?= icon('phone') ?><span><?= e($phone) ?></span></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= icon('envelope-simple') ?><span><?= e($email) ?></span></a></li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="contact__sec contact__hours">
        <p class="contact__h label"><?= e(lt('Öffnungszeiten')) ?></p>
        <?= app()->theme->partial('hours', ['hours' => $hours, 'state' => true]) ?>
        <?php if ($hoursNote !== ''): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($form !== '' || $map !== ''): ?>
    <div class="contact__main">
      <?php if ($form !== ''): ?>
      <div class="contact__form dff-wrap panel">
        <?php if (trim((string) ($d['form_title'] ?? '')) !== ''): ?><h3 class="contact__formtitle h3"<?= $b->edit('form_title') ?>><?= e($d['form_title']) ?></h3><?php endif; ?>
        <?= $form ?>
      </div>
      <?php endif; ?>
      <?php if ($map !== ''): ?><div class="contact__map gframe gframe--map"><?= $map ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($d['note'] !== '' || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
