<?php
/**
 * Text + Bild (Tailwind) – Varianten right | left. Rich-Text mit dem Typography-Plugin (prose): Preflight setzt Listen,
 * Überschriften und Abstände zurück, „prose“ gestaltet Inhalte ohne Klassen wieder.
 * @var \Core\Block $b  @var array $d
 */
$media = frameworks_image($d['image'], '(min-width: 1024px) 560px, 100vw', (string) $d['ratio'], 'shadow-lg');
$left = $b->variant() === 'left';
?>
<div class="wrap<?= $media !== '' ? ' grid items-center gap-12 md:grid-cols-2' : '' ?>">
  <div class="<?= $left && $media !== '' ? 'md:order-2' : '' ?>">
    <?php if (filled($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h2 id="<?= e($b->titleId()) ?>" class="text-3xl font-bold tracking-tight sm:text-4xl"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
    <div class="prose prose-slate mt-6 max-w-none dark:prose-invert prose-a:text-accent"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
    <?php if (filled($d['button_label']) && $d['button_link'] !== ''): ?>
    <p class="mt-8"><a class="btn btn-secondary" <?= frameworks_link_attrs($d['button_link']) ?>><span<?= $b->edit('button_label') ?>><?= e($d['button_label']) ?></span></a></p>
    <?php endif; ?>
  </div>
  <?= $media ?>
</div>
