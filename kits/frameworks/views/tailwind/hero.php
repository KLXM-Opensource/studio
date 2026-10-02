<?php
/**
 * Einstieg (Tailwind) – H1 der Seite. Varianten: center | split (Text + Bild, Bild eager = LCP).
 * @var \Core\Block $b  @var array $d
 */
$split = $b->variant() === 'split';
$image = $split ? frameworks_image($d['image'], '(min-width: 1024px) 560px, 100vw', '4:3', 'shadow-xl ring-1 ring-slate-900/5', ['eager' => true]) : '';
?>
<div class="wrap<?= $image !== '' ? ' grid items-center gap-12 lg:grid-cols-2' : '' ?>">
  <div class="<?= $image !== '' ? '' : 'mx-auto max-w-3xl text-center' ?>">
    <?php if (filled($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h1 id="<?= e($b->titleId()) ?>" class="text-4xl font-extrabold tracking-tight text-balance sm:text-6xl"<?= $b->edit('title') ?>><?= e($d['title']) ?></h1>
    <?php if (filled($d['text']) || is_editing()): ?><p class="mt-6 text-lg/8 text-slate-600 dark:text-slate-400"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    <?php if ($btn = frameworks_buttons($b, ['btn btn-primary', 'btn btn-secondary'])): ?>
    <div class="mt-10 flex flex-wrap gap-3<?= $image !== '' ? '' : ' justify-center' ?>"><?= $btn ?></div>
    <?php endif; ?>
  </div>
  <?= $image ?>
</div>
