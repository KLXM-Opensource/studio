<?php
/**
 * Scrollytelling: Bildspalte bleibt stehen (position: sticky), Textabschnitte ziehen vorbei; js/scrolly.js blendet das
 * passende Bild ein (IntersectionObserver). Container-Query: Unter ~46 rem Blockbreite steht jedes Bild direkt über
 * seinem Text (die stehende Spalte entfällt). Ohne JavaScript bleibt das erste Bild stehen – alle Bilder stehen zusätzlich
 * am Text. Bei „Bewegung reduzieren“ wechseln die Bilder ohne Überblendung.
 * @var \Core\Block $b  @var array $d
 */
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = foto_htag($d);
?>
<div class="wrap">
  <?= foto_head($b) ?>
  <?php if ($items): ?>
  <div class="scrolly scrolly--<?= $b->variant() === 'right' ? 'right' : 'left' ?>" data-scrolly>
    <div class="scrolly__stage" aria-hidden="true">
      <div class="scrolly__frame r-<?= e(str_replace(':', '-', $ratio)) ?>">
        <?php foreach ($items as $i => $it): if (empty($it['image'])) continue; ?>
        <div class="scrolly__img<?= $i === 0 ? ' is-active' : '' ?>" data-step-img="<?= $i ?>"><?= img((int) $it['image'], '(min-width: 1080px) 560px, 50vw', ['ratio' => $ratio, 'alt' => '']) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <ol class="scrolly__steps" role="list">
      <?php foreach ($items as $i => $it): ?>
      <li class="scrolly__step<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>">
        <?= !empty($it['image']) ? foto_image((int) $it['image'], '100vw', $ratio, 'scrolly__inline') : '' ?>
        <span class="scrolly__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= foto_title((string) $it['eyebrow']) ?></p><?php endif; ?>
        <<?= $tag ?> class="scrolly__title"<?= $b->edit("items.$i.title") ?>><?= foto_title((string) ($it['title'] ?? '')) ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="scrolly__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(foto_title((string) $it['text']), false) ?></p><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Abschnitte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
