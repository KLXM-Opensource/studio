<?php
/**
 * Slider (Kern-Block, vom Theme überschreibbar: kits/{name}/blocks/slideshow.php).
 * Grundlage ist CSS-Scroll-Snap (funktioniert ohne JavaScript: wischen/scrollen); js/media.mjs ergänzt Pfeile, Punkte,
 * Tastatur, Überblenden und – nur wenn eingeschaltet – automatisches Weiterblättern mit Pause-Schaltfläche.
 * Muster: WAI-ARIA APG „Carousel“ (region + aria-roledescription, Folien als group „n von m“).
 * @var \Core\Block $b  @var array $d
 */
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
$height = in_array($d['height'] ?? '', ['16:9', '21:9', '3:2', '4:3', 'screen'], true) ? $d['height'] : '16:9';
$fade = ($d['transition'] ?? '') === 'fade';
$slides = [];
foreach (MediaBlocks::images($d, 'slides') as $it) {
    $s = $it['item'] ?? [];
    $slides[] = [
        'm' => $it['m'], 'path' => $it['path'],
        'eyebrow' => trim((string) ($s['eyebrow'] ?? '')), 'title' => trim((string) ($s['title'] ?? '')), 'text' => trim((string) ($s['text'] ?? '')),
        'button_label' => trim((string) ($s['button_label'] ?? '')), 'button_link' => trim((string) ($s['button_link'] ?? '')),
        'position' => in_array($s['position'] ?? '', ['bottom-left', 'bottom-center', 'center-left', 'center', 'top-left'], true) ? $s['position'] : 'bottom-left',
        'overlay' => in_array($s['overlay'] ?? '', ['dark', 'light', 'box', 'none'], true) ? $s['overlay'] : 'dark',
    ];
}
$total = count($slides);
$id = $b->domId() . '-slides';
$title = trim((string) ($d['title'] ?? ''));
$hTag = $title !== '' ? 'h3' : 'h2';
$bleed = $height === 'screen';
$cfg = ['autoplay' => !empty($d['autoplay']) && $total > 1 ? max(4, (int) ($d['interval'] ?? 6)) * 1000 : 0, 'loop' => !empty($d['loop'])];
$texts = ['pause' => lt('Automatisches Weiterblättern anhalten'), 'play' => lt('Automatisches Weiterblättern starten')];
$sizes = $bleed ? '100vw' : '(min-width: 1280px) 1200px, 100vw';
$icon = fn(string $p) => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="' . $p . '"/></svg>';
?>
<?php if ($bleed): ?><?php if (($head = MediaBlocks::head($b)) !== ''): ?><div class="<?= e($wrap) ?>"><?= $head ?></div><?php endif; ?>
<?php else: ?><div class="<?= e($wrap) ?>">
  <?= MediaBlocks::head($b) ?>
<?php endif; ?>
  <?php if ($slides): ?>
  <div class="cms-slider cms-slider--<?= $fade ? 'fade' : 'slide' ?> cms-slider--h-<?= e(str_replace(':', '-', $height)) ?><?= $bleed ? ' cms-slider--bleed' : '' ?>"
       role="region" aria-roledescription="<?= e(lt('Karussell')) ?>" aria-label="<?= e($title !== '' ? $title : lt('Bilder')) ?>"
       <?= is_editing() ? '' : 'data-cms-slider="' . e((string) json_encode($cfg + $texts, JSON_UNESCAPED_UNICODE)) . '"' ?>>
    <?php if ($cfg['autoplay']): ?>
    <button type="button" class="cms-slider__play" aria-controls="<?= e($id) ?>" hidden>
      <span class="cms-slider__ico-pause"><?= $icon('M7 5h3v14H7zM14 5h3v14h-3z') ?></span><span class="cms-slider__ico-play"><?= $icon('M8 5v14l11-7z') ?></span>
      <span class="sr-only" data-label><?= e($texts['pause']) ?></span>
    </button>
    <?php endif; ?>
    <div class="cms-slider__track" id="<?= e($id) ?>" tabindex="0">
      <?php foreach ($slides as $n => $s): $hasText = $s['eyebrow'] !== '' || $s['title'] !== '' || $s['text'] !== '' || $s['button_label'] !== ''; ?>
      <div class="cms-slide cms-slide--<?= e($s['position']) ?> cms-slide--ov-<?= e($hasText ? $s['overlay'] : 'none') ?>" id="<?= e($id . '-' . ($n + 1)) ?>"
           role="group" aria-roledescription="<?= e(lt('Folie')) ?>" aria-label="<?= e(lt('{n} von {total}', ['n' => $n + 1, 'total' => $total])) ?>">
        <div class="cms-slide__media"><?= img((int) $s['m']['id'], $sizes, ['ratio' => $height === 'screen' ? null : $height, 'eager' => $n === 0 && $b->prev === null]) ?></div>
        <?php if ($hasText): $p = $s['path']; ?>
        <div class="cms-slide__body<?= $bleed ? ' ' . e($wrap) : '' ?>"><div class="cms-slide__box">
          <?php if ($s['eyebrow'] !== ''): ?><p class="cms-slide__eyebrow"<?= $p ? $b->edit("$p.eyebrow") : '' ?>><?= e($s['eyebrow']) ?></p><?php endif; ?>
          <?php if ($s['title'] !== ''): ?><<?= $hTag ?> class="cms-slide__title"<?= $p ? $b->edit("$p.title") : '' ?>><?= e($s['title']) ?></<?= $hTag ?>><?php endif; ?>
          <?php if ($s['text'] !== ''): ?><p class="cms-slide__text"<?= $p ? $b->edit("$p.text") : '' ?>><?= nl2br(e($s['text']), false) ?></p><?php endif; ?>
          <?php if ($s['button_label'] !== '' && $s['button_link'] !== ''): ?><p class="cms-slide__cta"><a class="<?= e($btn) ?>" href="<?= e(link_href($s['button_link'])) ?>"<?= $p ? $b->edit("$p.button_label") : '' ?>><?= e($s['button_label']) ?></a></p><?php endif; ?>
        </div></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($total > 1 && (!empty($d['arrows']) || !empty($d['dots']))): ?>
    <div class="cms-slider__nav<?= $bleed ? ' ' . e($wrap) : '' ?>" hidden>
      <?php if (!empty($d['arrows'])): ?>
      <button type="button" class="cms-slider__arrow" data-dir="-1" aria-controls="<?= e($id) ?>" aria-label="<?= e(lt('Vorherige Folie')) ?>"><?= $icon('M15.4 5.4 14 4l-8 8 8 8 1.4-1.4L8.8 12z') ?></button>
      <?php endif; ?>
      <?php if (!empty($d['dots'])): ?>
      <div class="cms-slider__dots">
        <?php for ($n = 1; $n <= $total; $n++): ?><button type="button" class="cms-slider__dot" aria-controls="<?= e($id) ?>" aria-label="<?= e(lt('Folie {n}', ['n' => $n])) ?>"<?= $n === 1 ? ' aria-current="true"' : '' ?>></button><?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($d['arrows'])): ?>
      <button type="button" class="cms-slider__arrow" data-dir="1" aria-controls="<?= e($id) ?>" aria-label="<?= e(lt('Nächste Folie')) ?>"><?= $icon('M8.6 5.4 10 4l8 8-8 8-1.4-1.4 6.6-6.6z') ?></button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?= MediaBlocks::script() ?>
  <?php elseif (is_editing()): ?>
  <p class="cms-empty-hint">Noch keine Folien – in der Seitenleiste Folien anlegen oder eine Sammlung wählen.</p>
  <?php endif; ?>
<?php if (!$bleed): ?></div><?php endif; ?>
