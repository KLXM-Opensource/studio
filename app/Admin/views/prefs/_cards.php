<?php
/** Karten der Sammelseiten (Core\AdminPages): Gruppe → Karten mit Symbol, Titel, Beschreibung, Hinweis „gehört zur Tabelle …“. @var array $groups */
?>
<?php foreach ($groups as $gi => $g): ?>
<section class="pf-group" aria-labelledby="pf-g<?= (int) $gi ?>">
  <h2 class="pf-group__h" id="pf-g<?= (int) $gi ?>"><?= e($g['label']) ?></h2>
  <ul class="pf-cards">
    <?php foreach ($g['pages'] as $p): $tbl = $p['table'] ? \Core\Data\Tables::findContent($p['table']) : null; ?>
    <li class="pf-card">
      <span class="pf-card__ico" aria-hidden="true"><?= \Core\Icons::render($p['icon'], ['fallback' => 'puzzle-piece']) ?></span>
      <span class="pf-card__body">
        <a class="pf-card__a" href="<?= e(url($p['href'])) ?>"><?= e($p['label']) ?></a>
        <?php if ($p['description'] !== ''): ?><span class="pf-card__d"><?= e($p['description']) ?></span><?php endif; ?>
        <?php if ($tbl): ?><span class="pf-card__t"><?= icon('table') ?> <?= e(__('Gehört zur Tabelle „{name}“', ['name' => $tbl['name']])) ?></span><?php endif; ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
