<?php
/**
 * Karten (Tailwind). Ganze Karte klickbar über after:absolute am Link – im Bearbeiten-Modus abgeschaltet
 * (in-[.is-editing]:after:hidden), damit Titel und Text direkt bearbeitbar bleiben. $b->targetEdit(): „Bearbeiten“ für
 * das Ziel (Seite/Eintrag) – nur für die Redaktion, als erstes Kind der Karte.
 * @var \Core\Block $b  @var array $d
 */
$h = filled($d['title']) ? 'h3' : 'h2';
?>
<div class="wrap">
  <?php include __DIR__ . '/_head.php'; ?>
  <ul role="list" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($d['items'] as $i => $it): $href = trim((string) ($it['link'] ?? '')) !== '' ? frameworks_link($it['link']) : ''; ?>
    <li class="relative flex flex-col overflow-hidden rounded-kit border border-slate-200 bg-white shadow-sm motion-safe:transition hover:shadow-md dark:border-white/10 dark:bg-white/5"><?= $b->targetEdit((string) ($it['link'] ?? ''), (string) ($it['title'] ?? '')) ?>
      <?php if (!empty($it['image'])): ?><?= frameworks_image($it['image'], '(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw', '3:2', 'rounded-none', ['alt' => '']) ?><?php endif; ?>
      <div class="flex flex-1 flex-col p-6">
        <?php if (empty($it['image']) && filled($it['icon'] ?? '')): ?><span class="mb-4 inline-grid size-12 place-items-center rounded-lg bg-accent/10 text-2xl text-accent"><?= icon($it['icon']) ?></span><?php endif; ?>
        <<?= $h ?> class="text-lg font-semibold"><?php if ($href !== ''): ?><a class="text-slate-900 no-underline after:absolute after:inset-0 hover:text-accent hover:underline in-[.is-editing]:after:hidden dark:text-white" href="<?= e($href) ?>"<?= ext_attrs($href) ?><?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span><?php endif; ?></<?= $h ?>>
        <?php if (filled($it['text'] ?? '') || is_editing()): ?><p class="mt-2 text-slate-600 dark:text-slate-400"<?= $b->edit("items.$i.text") ?>><?= nl2br(e((string) ($it['text'] ?? '')), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= e(lt('Noch keine Karten – in der Seitenleiste hinzufügen.')) ?></p><?php endif; ?>
</div>
