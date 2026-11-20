<?php
/**
 * Verwaltung → Medien → Grafiken: eigene Ablage des Grafik-Editors (Symbole, Blockvorschauen, Bausteine) – nicht in der Mediathek.
 * @var array $groups  @var array $blocks
 */
$labels = \Core\Graphics\Graphics::formatLabels();
$titles = ['preview' => __('Blockvorschauen'), 'icon' => __('Symbole'), 'part' => __('Eigene Bausteine')];
$help = [
    'preview' => __('Skizzen für die Blockauswahl („+ Block einfügen“). Eine zugeordnete Vorschau ersetzt auf dieser Website die des Kits.'),
    'icon' => __('Symbole im Format 24 × 24 mit der Schriftfarbe (currentColor) – für Erweiterungen und eigene Blöcke.'),
    'part' => __('Im Editor gespeicherte Auswahl („Als Baustein speichern“) – erscheint in der Bibliothek des Editors.'),
];
?>
<header class="adm-head">
  <h1><?= e(__('Grafiken')) ?></h1>
  <div class="adm-row">
    <a class="adm-btn" href="<?= e(url('/admin/media')) ?>"><?= e(__('Zur Mediathek')) ?></a>
    <button type="button" class="adm-btn" data-ge-open data-format="icon"><?= icon('plus') ?> <?= e(__('Neues Symbol')) ?></button>
    <button type="button" class="adm-btn adm-btn--primary" data-ge-open data-format="preview"><?= icon('plus') ?> <?= e(__('Neue Blockvorschau')) ?></button>
  </div>
</header>
<p class="adm-muted ge-intro"><?= e(__('Diese Grafiken liegen in einer eigenen Ablage und erscheinen nicht in der Mediathek oder in Bildfeldern. Infografiken für Seiten legen Sie in der Mediathek an (Neu → Grafik).')) ?></p>

<?php foreach ($groups as $kind => $items): ?>
<section class="adm-card ge-store" aria-labelledby="ge-h-<?= e($kind) ?>">
  <h2 id="ge-h-<?= e($kind) ?>"><?= e($titles[$kind]) ?> <span class="adm-muted">(<?= count($items) ?>)</span></h2>
  <p class="adm-muted"><?= e($help[$kind]) ?></p>
  <?php if (!$items): ?>
    <p class="ge-store__empty"><?= e(__('Noch keine.')) ?></p>
  <?php else: ?>
  <ul class="ge-store__grid">
    <?php foreach ($items as $g): ?>
    <li class="ge-store__item ge-store__item--<?= e($kind) ?>">
      <span class="ge-store__pv" aria-hidden="true"><?php if ($g['url']): ?><img src="<?= e($g['url']) ?>" alt="" loading="lazy"><?php else: ?><?= icon('shapes') ?><?php endif; ?></span>
      <strong class="ge-store__name"><?= e($g['name']) ?></strong>
      <?php if ($kind === 'preview'): ?>
        <small class="adm-muted"><?= $g['block'] ? e($blocks[$g['block']] ?? $g['block']) : e(__('keinem Block zugeordnet')) ?><?= $g['active'] ? ' · ' . e(__('gilt')) : '' ?></small>
      <?php endif; ?>
      <span class="ge-store__acts">
        <?php if ($kind !== 'part'): ?><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/grafik/editor?id=' . $g['id'])) ?>" data-ge-open data-id="<?= (int) $g['id'] ?>"><?= icon('pencil-simple') ?> <?= e(__('Bearbeiten')) ?></a><?php endif; ?>
        <?php if ($kind === 'preview' && $g['block']): ?>
          <form method="post" action="<?= e(url('/admin/api/grafik/assign')) ?>"><?= csrf_field() ?><input type="hidden" name="block" value="<?= e($g['block']) ?>"><input type="hidden" name="id" value="<?= $g['active'] ? '' : (int) $g['id'] ?>">
            <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e($g['active'] ? __('Vorschau des Kits zeigen') : __('In der Blockauswahl verwenden')) ?></button></form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/api/grafik/' . $g['id'] . '/delete')) ?>" data-confirm="<?= e(__('„{name}“ löschen?', ['name' => $g['name']])) ?>"><?= csrf_field() ?>
          <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit"><?= e(__('Löschen')) ?></button></form>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endforeach; ?>
