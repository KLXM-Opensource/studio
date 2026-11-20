<?php
/**
 * Scrollytelling im Kit „NativeCSS“ mit <ncss-scrolly>: Die Abschnitte ziehen als Karten vorbei, das Bild daneben bleibt stehen
 * und wechselt zum aktiven Abschnitt; schmal steht das Bild oben und die Abschnitte laufen darunter durch. Ohne JavaScript
 * stehen Abschnitte und Bilder untereinander. Abschnitte ohne eigenes Bild behalten das vorige.
 * Beim Bearbeiten die Ausgabe von fluid (Bild direkt am Text).
 * @var \Core\Block $b  @var array $d
 */
if (is_editing()) {
    require native_parent_view('scrolly');
    return;
}
native_mark('@nv-scrolly');
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== ''));
$tag = fluid_htag($d);
$img = 0;
?>
<div class="wrap nv-scrolly">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ncss-scrolly<?= $b->variant() === 'right' ? '' : ' media-start' ?>>
    <ol class="ncss-scrolly-steps" role="list">
      <?php foreach ($items as $it): ?>
      <li class="ncss-scrolly-step"><div>
        <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif; ?>
        <<?= $tag ?> class="nv-scrolly__title"><?= fluid_title((string) $it['title']) ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif; ?>
      </div></li>
      <?php endforeach; ?>
    </ol>
    <div class="ncss-scrolly-media" aria-hidden="true">
      <?php foreach ($items as $it): $img = !empty($it['image']) ? (int) $it['image'] : $img; ?>
      <figure class="ncss-scrolly-figure"><?= $img ? img($img, '(min-width: 1080px) 560px, 90vw', ['ratio' => $ratio, 'alt' => '']) : '' ?></figure>
      <?php endforeach; ?>
    </div>
  </ncss-scrolly>
  <?php endif; ?>
</div>
