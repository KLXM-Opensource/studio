<?php
/**
 * Aufklappliste (Tailwind) – <details>/<summary>: ohne Skript, per Tastatur bedienbar. Im Bearbeiten-Modus alle offen
 * (sonst klappte ein Klick zum Bearbeiten den Eintrag zu). Pfeil dreht sich nur ohne „Bewegung reduzieren“.
 * @var \Core\Block $b  @var array $d
 */
$open = is_editing() ? ' open' : '';
?>
<div class="wrap">
  <div class="mx-auto max-w-3xl">
    <?php include __DIR__ . '/_head.php'; ?>
    <div class="divide-y divide-slate-200 rounded-kit border border-slate-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-white/5">
      <?php foreach ($d['items'] as $i => $it): ?>
      <details class="group"<?= $open ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-lg font-semibold text-slate-900 hover:text-accent dark:text-white [&::-webkit-details-marker]:hidden">
          <span<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span>
          <?= icon('caret-down', ['class' => 'size-5 shrink-0 motion-safe:transition-transform group-open:rotate-180']) ?>
        </summary>
        <div class="prose prose-slate max-w-none px-5 pb-5 dark:prose-invert prose-a:text-accent"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= e(lt('Noch keine Einträge – in der Seitenleiste hinzufügen.')) ?></p><?php endif; ?>
  </div>
</div>
