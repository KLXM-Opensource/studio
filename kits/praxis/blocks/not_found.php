<?php
/**
 * 404-Vorschläge im Praxis-Stil (Kern-Block not_found, Felder in theme.php) – wie templates/error.php: Dachzeile, Überschrift
 * mit Punkt und zweiter Zeile, Telefonzeile aus den Praxisdaten, dazu Vorschläge/Suche (Core\NotFound::boxes) und Startseiten-Button.
 * @var \Core\Block $b  @var array $d
 */
use Core\NotFound;

$phone = !empty($d['phone']) && trim((string) ($d['phone_text'] ?? '')) !== '';
$more = trim((string) ($d['link_label'] ?? '')) !== '' && !empty($d['link']) ? praxis_link((string) $d['link']) : '';
?>
<div class="wrap cms-404">
  <div>
    <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <?= praxis_heading($b, 'h1', 'h2') ?>
    <?php if ($phone): ?><p class="lead"><?= praxis_fill((string) $d['phone_text'], ['phone' => praxis_phone_link()]) ?></p><?php endif; ?>
  </div>
  <?= NotFound::boxes($b) ?>
  <?php if (trim((string) ($d['home_label'] ?? '')) !== '' || $more !== ''): ?>
  <div class="btn-row">
    <?php if (trim((string) ($d['home_label'] ?? '')) !== ''): ?><a class="btn btn--primary" href="<?= e(NotFound::homeUrl()) ?>"><span<?= $b->edit('home_label') ?>><?= e($d['home_label']) ?></span> <span aria-hidden="true">→</span></a><?php endif; ?>
    <?php if ($more !== ''): ?><a class="btn btn--white" <?= praxis_link_attrs((string) $d['link']) ?>><?= e($d['link_label']) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
