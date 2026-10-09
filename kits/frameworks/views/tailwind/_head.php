<?php
/**
 * Kopf eines Abschnitts (Dachzeile, Überschrift, Einleitung) – eingebunden mit $b, $d; optional $center (bool).
 * $b->edit('feld') macht den Text im Editor direkt bearbeitbar (für Besucher leer).
 */
$center ??= false;
if (!filled($d['eyebrow'] ?? '') && !filled($d['title'] ?? '') && !filled($d['intro'] ?? '') && !is_editing()) return;
?>
<header class="mb-10 max-w-3xl<?= $center ? ' mx-auto text-center' : '' ?>">
  <?php if (filled($d['eyebrow'] ?? '')): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
  <?php if (filled($d['title'] ?? '') || is_editing()): ?><h2 id="<?= e($b->titleId()) ?>" class="text-3xl font-bold tracking-tight sm:text-4xl"<?= $b->edit('title') ?>><?= emphasis((string) ($d['title'] ?? '')) ?></h2><?php endif; ?>
  <?php if (filled($d['intro'] ?? '')): ?><p class="mt-4 text-lg text-slate-600 dark:text-slate-400"<?= $b->edit('intro') ?>><?= nl2br(emphasis((string) $d['intro']), false) ?></p><?php endif; ?>
</header>
