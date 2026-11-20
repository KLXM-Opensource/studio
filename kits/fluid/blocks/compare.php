<?php
/**
 * Vorher/Nachher: zwei Bilder übereinander, ein Regler (natives <input type="range">: Maus, Touch, Tastatur, Screenreader)
 * verschiebt die Trennlinie; ohne JavaScript stehen beide Bilder beschriftet nebeneinander. Markup und Skript wie beim Einstieg
 * „Vorher/Nachher“ (Core\Blocks\Hero::compare, public/assets/js/hero.mjs), Aussehen css/hx-compare.css. Fehlende Alt-Texte
 * meldet schon das Bildfeld in der Seitenleiste (Mediathek).
 * @var \Core\Block $b  @var array $d
 */
$ratio = in_array($d['ratio'] ?? '', ['16:9', '3:2', '4:3', '1:1', '4:5', '3:4'], true) ? (string) $d['ratio'] : '16:9';
$cmp = \Core\Blocks\Hero::compare($d, '(min-width: 1080px) 1000px, 100vw', $ratio, 'hx-cmp', null);
$cap = trim((string) ($d['caption'] ?? ''));
?>
<div class="wrap cmp-block">
  <?= fluid_head($b) ?>
  <?php if ($cmp !== ''): ?>
  <figure class="cmp-block__fig">
    <div class="cmp-block__stage hx-ar-<?= e(str_replace(':', '-', $ratio)) ?>"><?= $cmp ?></div>
    <?php if ($cap !== '' || is_editing()): ?><figcaption class="cmp-block__cap"<?= $b->edit('caption') ?>><?= fluid_title($cap) ?></figcaption><?php endif; ?>
  </figure>
  <?php endif; ?>
</div>
