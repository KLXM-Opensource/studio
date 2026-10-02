<?php
/**
 * Reiter (Tailwind) – Tabs nach WAI-ARIA (tablist/tab/tabpanel, Pfeiltasten) mit js/tailwind.js. Ohne Skript (html.no-js)
 * und im Bearbeiten-Modus stehen alle Inhalte mit Überschrift untereinander – nichts ist versteckt.
 * @var \Core\Block $b  @var array $d
 */
$id = $b->domId() . '-tab';
$items = array_values($d['items']);
$interactive = !is_editing() && count($items) > 1;
?>
<div class="wrap">
  <?php include __DIR__ . '/_head.php'; ?>
  <div<?= $interactive ? ' data-fw-tabs' : '' ?>>
    <?php if ($interactive): ?>
    <div role="tablist" aria-label="<?= e((string) ($d['title'] ?: lt('Reiter'))) ?>" class="mb-6 hidden flex-wrap gap-1 border-b border-slate-200 in-[.js]:flex dark:border-white/10">
      <?php foreach ($items as $i => $it): ?>
      <button type="button" role="tab" id="<?= e("$id-$i") ?>" aria-controls="<?= e("$id-p$i") ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i ? ' tabindex="-1"' : '' ?>
        class="-mb-px cursor-pointer border-b-2 border-transparent px-4 py-3 font-semibold text-slate-600 hover:text-slate-900 aria-selected:border-accent aria-selected:text-accent dark:text-slate-400 dark:hover:text-white"><?= e($it['label']) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php foreach ($items as $i => $it): ?>
    <div<?= $interactive ? ' role="tabpanel" id="' . e("$id-p$i") . '" aria-labelledby="' . e("$id-$i") . '" tabindex="0"' . ($i ? ' data-fw-later' : '') : '' ?> class="mb-8 max-w-3xl focus-visible:outline-3">
      <h3 class="mb-3 text-xl font-semibold<?= $interactive ? ' in-[.js]:sr-only' : '' ?>"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></h3>
      <div class="prose prose-slate max-w-none dark:prose-invert prose-a:text-accent"<?= $b->edit("items.$i.text", 'rich') ?>><?= rich((string) ($it['text'] ?? '')) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
