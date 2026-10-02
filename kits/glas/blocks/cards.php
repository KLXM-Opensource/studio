<?php
/**
 * Produkte / Leistungen: glass (Bild oben, Text auf Glas, Kennwert und Pfeil) · service (Symbol in der Glasperle, ohne Bild) ·
 * overlay (Bild füllt die Karte, Text auf einer Glasleiste darüber). Ohne Linktext ist die ganze Karte klickbar.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['glass', 'service', 'overlay'], true) ? $b->variant() : 'glass';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = glas_htag($d);
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($items): ?>
  <ul class="grid <?= e(glas_min($d)) ?> pcards pcards--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $meta = trim((string) ($it['meta'] ?? ''));
        $pic = $v === 'service' ? '' : glas_image(!empty($it['image']) ? (int) $it['image'] : null, '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 90vw', $ratio, 'pcard__media');
    ?>
    <li class="pcard card<?= $link !== '' ? ' pcard--link' : '' ?>" data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <?= $pic ?>
      <?php if ($v === 'service' && !empty($it['icon'])): ?><span class="orb pcard__ico"><?= icon((string) $it['icon']) ?></span><?php endif; ?>
      <div class="pcard__body<?= $v === 'overlay' ? ' glass' : '' ?>">
        <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="pcard__eyebrow label"<?= $b->edit("items.$i.eyebrow") ?>><?= e($it['eyebrow']) ?></p><?php endif; ?>
        <<?= $tag ?> class="pcard__title"><?php if ($link !== '' && !is_editing()): ?><a class="cover-link" <?= glas_link_attrs($link) ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="pcard__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      </div>
      <?php if ($meta !== '' || $link !== ''): ?>
      <div class="pcard__foot">
        <span class="pcard__meta"<?= $b->edit("items.$i.meta") ?>><?= e($meta) ?></span>
        <?php if ($link !== ''): ?><span class="pcard__go" aria-hidden="true"><?php if ($label !== ''): ?><span class="pcard__golabel"><?= e($label) ?></span><?php endif; ?><?= icon('arrow-right') ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="more-row"><a class="btn btn--secondary" <?= glas_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Karten – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
