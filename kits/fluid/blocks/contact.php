<?php
/**
 * Kontakt: Angaben (Adresse, Telefon/E-Mail, Öffnungszeiten) als Karten-Raster, daneben Karte und/oder Formular.
 * Sidebar-Muster: Formular/Karte nehmen den großen Teil ein und rutschen bei wenig Platz unter die Angaben.
 * Alles aus „Website“; Formular = öffentliches Formular einer Datentabelle (Core\Data\DataForms, Spamschutz ohne Cookies).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\DataForms;
use Core\Data\Tables;

$address = fluid_address_lines();
$phone = fluid_phone();
$tel = fluid_phone_href();
$email = fluid_email();
$hours = !empty($d['show_hours']) ? fluid_hours() : [];
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
  <?= fluid_head($b) ?>
  <div class="contact<?= ($form !== '' || $map !== '') ? ' contact--aside' : '' ?>">
    <div class="contact__info"<?= $c ?>>
      <?php if ($address || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('map-pin') ?></span>
        <h3 class="contact__h"><?= e(lt('Adresse')) ?></h3>
        <address class="contact__address"><?= e(fluid_name()) ?><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php else: ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif; ?></address>
      </div>
      <?php endif; ?>
      <?php if (($tel && filled($phone)) || $email !== '' || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('chat-circle-text') ?></span>
        <h3 class="contact__h"><?= e(lt('Telefon & E-Mail')) ?></h3>
        <ul class="contact__list" role="list">
          <?php if ($tel && filled($phone)): ?><li><span class="contact__label"><?= e(lt('Telefon')) ?></span><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><span class="contact__label"><?= e(lt('E-Mail')) ?></span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if (!($tel && filled($phone)) && $email === ''): ?><li>[<?= e('Telefon und E-Mail unter „Website“ eintragen') ?>]</li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('clock') ?></span>
        <h3 class="contact__h"><?= e(lt('Öffnungszeiten')) ?></h3>
        <?= app()->theme->partial('hours', ['hours' => $hours]) ?>
        <?php if ($hoursNote !== ''): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($form !== '' || $map !== ''): ?>
    <div class="contact__main">
      <?php if ($form !== ''): ?>
      <div class="contact__form dff-wrap card">
        <?php if (trim((string) ($d['form_title'] ?? '')) !== ''): ?><h3 class="contact__formtitle h3"<?= $b->edit('form_title') ?>><?= fluid_title((string) $d['form_title']) ?></h3><?php endif; ?>
        <?= $form ?>
      </div>
      <?php endif; ?>
      <?php if ($map !== ''): ?><div class="contact__map"><?= $map ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($d['note'] !== '' || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= fluid_title((string) $d['note']) ?></p><?php endif; ?>
</div>
