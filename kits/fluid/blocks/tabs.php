<?php
/**
 * Reiter (Tabs). Ohne JavaScript: alle Inhalte mit Zwischenüberschrift untereinander. js/tabs.js macht daraus eine
 * Reiterleiste (role=tablist, Pfeiltasten, Pos1/Ende). „Reiter links“: Sidebar-Muster – die Leiste steht links, solange
 * daneben ≥ 60 % Platz bleiben, sonst oben (waagerecht scrollbar).
 * @var \Core\Block $b  @var array $d
 */
$items = array_values(array_filter((array) $d['items'], fn($i) => trim((string) ($i['label'] ?? '')) !== ''));
$id = $b->domId();
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <div class="tabs tabs--<?= $b->variant() === 'side' ? 'side' : 'top' ?>" data-tabs>
    <div class="tabs__list" role="tablist"<?= trim((string) $d['title']) !== '' ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?> hidden>
      <?php foreach ($items as $i => $it): ?><button type="button" class="tabs__tab" role="tab" id="<?= e("$id-tab-$i") ?>" aria-controls="<?= e("$id-panel-$i") ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i ? ' tabindex="-1"' : '' ?>><?= e($it['label']) ?></button><?php endforeach; ?>
    </div>
    <div class="tabs__panels">
      <?php foreach ($items as $i => $it):
          $pic = !empty($it['image']) ? fluid_image((int) $it['image'], '(min-width: 1080px) 480px, 100vw', '4:3', 'tabs__media') : ''; ?>
      <section class="tabs__panel<?= $pic !== '' ? ' tabs__panel--media' : '' ?>" id="<?= e("$id-panel-$i") ?>" aria-labelledby="<?= e("$id-tab-$i") ?>">
        <div class="tabs__text">
          <h3 class="tabs__title h3"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></h3>
          <div class="prose"<?= $b->edit("items.$i.text", 'rich') ?>><?= rich((string) ($it['text'] ?? '')) ?></div>
        </div>
        <?= $pic ?>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Reiter – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
