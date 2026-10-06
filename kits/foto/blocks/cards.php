<?php
/**
 * Karten: image (Bild oben) · overlay (Text auf dem Bild) · horizontal (quer – jede Karte ist ein Container und stellt sich
 * erst ab ~30 rem Kartenbreite quer) · reel (wischbares Band mit scroll-snap, ohne Bibliothek; js/reel.js ergänzt Pfeile).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'image';
$ratio = (string) ($d['ratio'] ?? '') ?: '3:2';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = foto_htag($d);
$id = $b->domId() . '-reel';
?>
<div class="wrap">
  <?php if ($v === 'reel'): ?>
  <div class="reel-head">
    <?= foto_head($b) ?>
    <div class="reel-ctrl" data-reel-ctrl="<?= e($id) ?>" hidden>
      <button type="button" class="reel-btn" data-reel-prev aria-controls="<?= e($id) ?>"><?= icon('arrow-right', ['class' => 'flip-x']) ?><span class="sr-only"><?= e(lt('Zurück')) ?></span></button>
      <button type="button" class="reel-btn" data-reel-next aria-controls="<?= e($id) ?>"><?= icon('arrow-right') ?><span class="sr-only"><?= e(lt('Weiter')) ?></span></button>
    </div>
  </div>
  <?php else: ?><?= foto_head($b) ?><?php endif; ?>
  <?php if ($items): ?>
  <ul class="<?= $v === 'reel' ? 'reel ' : 'grid ' ?><?= e(foto_min($d)) ?> cards cards--<?= e($v) ?>" role="list"<?= $v === 'reel' ? ' id="' . e($id) . '" tabindex="0" aria-label="' . e(trim((string) $d['title']) ?: lt('Karten')) . '"' : '' ?>>
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $pic = foto_image(!empty($it['image']) ? (int) $it['image'] : null, $v === 'horizontal' ? '(min-width: 1080px) 320px, 100vw' : '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 90vw', $ratio, 'tcard__media');
    ?>
    <li class="tcard tcard--<?= e($v) ?><?= $v === 'overlay' ? '' : ' card' ?><?= $link !== '' ? ' tcard--link' : '' ?>" data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <div class="tcard__in">
        <?= $pic ?>
        <div class="tcard__body">
          <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="tcard__eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= e($it['eyebrow']) ?></p><?php endif; ?>
          <<?= $tag ?> class="tcard__title"><?php if ($link !== '' && !is_editing()): ?><a class="cover-link" <?= foto_link_attrs($link) ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
          <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="tcard__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
          <?php if ($link !== '' && $label !== ''): ?><span class="more tcard__more" aria-hidden="true"><?= e($label) ?><?= icon('arrow-right', ['class' => 'more__ico']) ?></span><?php endif; ?>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="more-row"><a class="btn btn--secondary" <?= foto_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Karten – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
