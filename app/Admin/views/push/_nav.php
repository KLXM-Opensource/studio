<?php
/**
 * Mitteilungen – Bereichsnavigation in der Seitenleiste (Layout: $drill, resources/js/_drill.js; schmal in der Schublade).
 * @var string $section  @var array $counts  @var bool $canSend
 */
$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$row = function (string $key, string $href, string $label, string $ico, ?int $n = null) use ($section): string {
    $on = $section === $key;
    return '<li><a class="fx-src pm-src' . ($on ? ' is-active' : '') . '" href="' . e(url($href)) . '"' . ($on ? ' aria-current="page"' : '') . '>'
        . icon($ico) . '<span>' . e($label) . '</span>' . ($n ? '<small>' . (int) $n . '</small>' : '') . '</a></li>';
};
?>
<nav class="fx-side pm-side" data-drill-panel aria-label="<?= e(__('Mitteilungen')) ?>">
  <?php if ($canSend): ?>
  <ul class="pm-side__new"><?= $row('neu', $b . '/neu', __('Neue Mitteilung'), 'pencil-simple') ?></ul>
  <?php endif; ?>
  <h3><?= e(__('Verlauf')) ?></h3>
  <ul>
    <?= $row('verlauf:alle', $b, __('Alle'), 'tray', $counts['alle']) ?>
    <?= $row('verlauf:manuell', $b . '?ordner=manuell', __('Von Hand'), 'paper-plane-tilt', $counts['manuell']) ?>
    <?= $row('verlauf:automatisch', $b . '?ordner=automatisch', __('Automatisch'), 'lightning', $counts['automatisch']) ?>
    <?= $row('verlauf:geplant', $b . '?ordner=geplant', __('Geplant'), 'clock', $counts['geplant']) ?>
  </ul>
  <h3><?= e(__('Abos')) ?></h3>
  <ul>
    <?= $row('kanaele', $b . '/kanaele', __('Kanäle'), 'broadcast', $counts['kanaele']) ?>
    <?= $row('statistik', $b . '/statistik', __('Statistik'), 'chart-bar') ?>
    <?= $row('website', $b . '/website', __('Auf der Website'), 'browser') ?>
  </ul>
  <?php if (can('system.manage')): ?>
  <h3><?= e(__('Einrichtung')) ?></h3>
  <ul><?= $row('system', '/admin/system#push', __('Technik & Schlüssel'), 'gear-six') ?></ul>
  <?php endif; ?>
</nav>
