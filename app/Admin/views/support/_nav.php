<?php
/**
 * Bereichsnavigation „Support“: Meldungen, Wissensdatenbank, Fragen & Antworten, Tags.
 * Das Layout zeigt sie links anstelle der Hauptnavigation (breite Bildschirme), sonst oben in der Seite (resources/js/_drill.js).
 * @var string $cur  mine|all|new|kb|questions|ask|tags
 */
use Core\Support\Support;
use Core\Support\Tickets;

$ac = fn(bool $on) => $on ? ' aria-current="page"' : '';
$unread = Support::canReport() ? Tickets::unreadCount() : 0;
$items = [];
if (Support::canReport()) $items[] = ['/admin/support', '✉', __('Meine Meldungen'), 'mine', null];
if (Support::isSiteManager()) {
    $c = Tickets::counts(false);
    $items[] = ['/admin/support/alle', '▤', Support::isStaff() ? __('Alle Meldungen') : __('Meldungen dieser Website'), 'all', $c['offen']];
}
$know = [
    ['/admin/support/wissen', '❖', __('Wissensdatenbank'), 'kb', null],
    ['/admin/support/fragen', '?', __('Fragen & Antworten'), 'questions', null],
    ['/admin/support/tags', '#', __('Tags'), 'tags', null],
];
?>
<nav class="dt-nav sp-nav" data-drill-panel aria-label="<?= e(__('Support')) ?>">
  <?php if ($items): ?>
  <p class="sp-nav__label" id="sp-nav-issues"><?= e(__('Meldungen')) ?></p>
  <ul class="dt-nav__list" aria-labelledby="sp-nav-issues">
    <?php foreach ($items as [$href, $ico, $label, $key, $n]): ?>
    <li><a class="dt-nav__item" href="<?= e(url($href)) ?>"<?= $ac($cur === $key) ?>><span class="dt-nav__ico" aria-hidden="true"><?= e($ico) ?></span><span class="dt-nav__name"><?= e($label) ?></span>
      <?php if ($unread && $key === (Support::isSiteManager() ? 'all' : 'mine')): ?><span class="adm-count" title="<?= e(__('ungelesen')) ?>"><?= $unread ?><span class="sr-only"> <?= e(__('ungelesen')) ?></span></span>
      <?php elseif ($n): ?><small class="dt-nav__count"><span aria-hidden="true"><?= (int) $n ?></span><span class="sr-only"><?= e(__('{n} offen', ['n' => (int) $n])) ?></span></small><?php endif; ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (Support::canReport()): ?>
  <a class="dt-nav__new" href="<?= e(url('/admin/support/neu')) ?>"<?= $ac($cur === 'new') ?>><span aria-hidden="true">+</span> <?= e(__('Problem melden')) ?></a>
  <?php endif; ?>
  <p class="sp-nav__label" id="sp-nav-know"><?= e(__('Wissen')) ?></p>
  <ul class="dt-nav__list" aria-labelledby="sp-nav-know">
    <?php foreach ($know as [$href, $ico, $label, $key]): ?>
    <li><a class="dt-nav__item" href="<?= e(url($href)) ?>"<?= $ac($cur === $key) ?>><span class="dt-nav__ico" aria-hidden="true"><?= e($ico) ?></span><span class="dt-nav__name"><?= e($label) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if (Support::canAnswer()): ?>
  <a class="dt-nav__new" href="<?= e(url('/admin/support/fragen/neu')) ?>"<?= $ac($cur === 'ask') ?>><span aria-hidden="true">+</span> <?= e(__('Frage stellen')) ?></a>
  <?php endif; ?>
  <?php if (Support::isStaff()): ?>
  <a class="dt-nav__new" href="<?= e(url('/admin/support/wissen/neu')) ?>"><span aria-hidden="true">+</span> <?= e(__('Neuer Wissensartikel')) ?></a>
  <?php endif; ?>
</nav>
