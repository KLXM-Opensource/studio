<?php
/** Kopf eines Abschnitts (UIkit) – eingebunden mit $b, $d; optional $center. */
$center ??= false;
if (!filled($d['eyebrow'] ?? '') && !filled($d['title'] ?? '') && !filled($d['intro'] ?? '') && !is_editing()) return;
?>
<header class="uk-margin-medium-bottom fw-head<?= $center ? ' uk-text-center uk-margin-auto' : '' ?>">
  <?php if (filled($d['eyebrow'] ?? '')): ?><p class="fw-eyebrow uk-margin-small-bottom"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
  <?php if (filled($d['title'] ?? '') || is_editing()): ?><h2 id="<?= e($b->titleId()) ?>" class="uk-h2 uk-margin-remove-top"<?= $b->edit('title') ?>><?= emphasis((string) ($d['title'] ?? '')) ?></h2><?php endif; ?>
  <?php if (filled($d['intro'] ?? '')): ?><p class="uk-text-lead"<?= $b->edit('intro') ?>><?= nl2br(emphasis((string) $d['intro']), false) ?></p><?php endif; ?>
</header>
