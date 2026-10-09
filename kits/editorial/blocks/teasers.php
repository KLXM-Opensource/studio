<?php
/** Anreißer (von Hand): Raster, „erster groß“, nummerierte Liste oder „Kurz notiert“ – gleiche Teaser-Bausteine wie die Datenliste. @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['grid', 'lead', 'list', 'brief'], true) ? $b->variant() : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$ratio = (string) ($d['ratio'] ?: '3:2');
$hTag = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($it) => trim((string) ($it['title'] ?? '')) !== '' || is_editing()));
?>
<div class="wrap tzs tzs--<?= e($v) ?>">
  <?= editorial_head($b) ?>
  <ol class="tz-grid tz-grid--<?= e($v) ?> cols-<?= e($cols) ?>" role="list">
    <?php foreach ($items as $i => $it):
      $href = trim((string) ($it['link'] ?? '')) !== '' ? editorial_link((string) $it['link']) : null;
      $lead = $v === 'lead' && $i === 0;
      $pic = in_array($v, ['grid', 'lead'], true) ? editorial_image((int) ($it['image'] ?? 0) ?: null, $lead ? '(min-width: 1360px) 760px, 100vw' : '(min-width: 1080px) 400px, 50vw', $ratio, 'tz__media') : ''; ?>
    <li class="tz<?= $lead ? ' tz--lead' : '' ?>"><?= $b->targetEdit((string) ($it['link'] ?? ''), (string) ($it['title'] ?? '')) ?>
      <?php if ($v === 'list'): ?><span class="tz__num" aria-hidden="true"><?= $i + 1 ?></span><?php endif; ?>
      <?= $pic ?>
      <div class="tz__body">
        <?php if (trim((string) ($it['kicker'] ?? '')) !== ''): ?><p class="kicker tz__kicker"<?= $b->edit("items.$i.kicker") ?>><?= emphasis((string) $it['kicker']) ?></p><?php endif; ?>
        <<?= $hTag ?> class="tz__title"><?php if ($href): ?><a class="tz__link" href="<?= e($href) ?>"<?= ext_attrs($href) ?>><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span><?= editorial_ext_note($href) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span><?php endif; ?></<?= $hTag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== '' && $v !== 'list'): ?><p class="tz__dek"<?= $b->edit("items.$i.text") ?>><?= emphasis((string) $it['text']) ?></p><?php endif; ?>
        <?php if (trim((string) ($it['meta'] ?? '')) !== ''): ?><p class="tz__meta"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php if (!$items && is_editing()): ?><p class="empty-hint">Noch keine Anreißer – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="tzs__more"><a class="more" <?= editorial_link_attrs((string) $d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span> <span aria-hidden="true">→</span></a></p>
  <?php endif; ?>
</div>
