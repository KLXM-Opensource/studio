<?php
/**
 * Bühne – ein großes Bild, ein stummes Video oder eine Folge aus Bildern und Videos mit Überblendung (js/photo-hero.js), Überschrift als H1.
 * Videos: stumm, Schleife, starten nur ohne „Bewegung reduzieren“, Pause-Schaltfläche, Vorschaubild (Media::posterFor) als Rückfall.
 * Höhe: bildschirmfüllend (100svh abzüglich Kopf), hoch (75svh) oder 21:9. Text unten links/rechts, Mitte, oben links oder
 * unter dem Bild; Abdunkelung als Verlauf hinter dem Text (mindestens 55 % Schwarz am Text – Weiß bleibt auf jedem Bild ≥ 4,5:1).
 * Bildfolge: ohne JavaScript steht das erste Bild; mit JavaScript Überblendung nur ohne „Bewegung reduzieren“, Pause-Schaltfläche
 * (WCAG 2.2.2), Zurück/Weiter, hält bei Maus/Fokus an. Hinweis „weiter nach unten“ springt an das Ende der Bühne.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'slideshow' ? 'slideshow' : 'single';
$pos = in_array($d['position'] ?? '', ['bottom-left', 'bottom-right', 'center', 'top-left', 'below'], true) ? $d['position'] : 'bottom-left';
$height = in_array($d['height'] ?? '', ['screen', 'tall', 'wide'], true) ? $d['height'] : 'screen';
$overlay = in_array($d['overlay'] ?? '', ['soft', 'medium', 'strong'], true) ? $d['overlay'] : 'soft';
$title = trim((string) ($d['title'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
$show = !empty($d['show_title']) && ($title !== '' || is_editing());
$pageTitle = (string) (app()->currentPage['title'] ?? '');
// Bilder: eine Folie (Ein Bild) bzw. alle Folien mit Bild
$slides = [];
$visual = fn(?array $m) => $m && (str_starts_with((string) $m['mime'], 'image/') || str_starts_with((string) $m['mime'], 'video/'));
if ($v === 'slideshow') {
    foreach ((array) ($d['slides'] ?? []) as $i => $s) {
        $m = is_array($s) && !empty($s['image']) ? media((int) $s['image']) : null;
        if ($visual($m)) $slides[] = ['m' => $m, 'caption' => trim((string) ($s['caption'] ?? '')), 'path' => "slides.$i"];
    }
} elseif (!empty($d['image']) && $visual($m = media((int) $d['image']))) {
    $slides[] = ['m' => $m, 'caption' => '', 'path' => ''];
}
$multi = count($slides) > 1;
$hasVideo = (bool) array_filter($slides, fn($s) => str_starts_with((string) $s['m']['mime'], 'video/'));
$auto = $multi && !empty($d['autoplay']);
$interval = in_array((string) ($d['interval'] ?? ''), ['4', '6', '8', '12'], true) ? (int) $d['interval'] : 6;
$endId = $b->domId() . '-end';
$zone = $v === 'slideshow' ? foto_drop_zone('slides', 'list', ['accept' => 'visual']) : foto_drop_zone('image', 'single', ['switch' => ['slideshow', 'slides'], 'accept' => 'visual']);
$cls = 'stage stage--' . $height . ' stage--' . $pos . ' stage--ov-' . $overlay . (empty($d['full_width']) ? ' stage--inset' : '') . ($multi ? ' stage--multi' : '');
?>
<div class="<?= e($cls) ?>"<?= $multi || $hasVideo ? ' data-stage' . ($auto ? ' data-autoplay="' . $interval . '"' : '') : '' ?>>
  <div class="stage__text<?= $show ? '' : ' sr-only' ?>">
    <h1 id="<?= e($b->titleId()) ?>" class="stage__title"<?= $b->edit('title') ?>><?= $title !== '' ? foto_title($title) : e($pageTitle) ?></h1>
    <?php if ($show && ($text !== '' || is_editing())): ?><p class="stage__lead"<?= $b->edit('text') ?>><?= e($text) ?></p><?php endif; ?>
  </div>
  <div class="stage__media">
    <?php foreach ($slides as $k => $s): ?>
    <figure class="stage__slide<?= $k === 0 ? ' is-active' : '' ?>"<?= $multi ? ' data-slide' . ($k > 0 ? ' aria-hidden="true"' : '') : '' ?>>
      <?php if (str_starts_with((string) $s['m']['mime'], 'video/')):
          // Video als stimmungsvoller Hintergrund: stumm, Schleife, startet nur ohne „Bewegung reduzieren“ (js/photo-hero.js), Vorschaubild als Rückfall
          $poster = \Core\Media::posterFor($s['m']); ?>
      <?= $poster ? \Core\Media::pictureOf($poster, '100vw', ['eager' => $k === 0, 'alt' => '']) : '' ?>
      <video class="stage__video" data-stage-video muted loop playsinline preload="none" aria-hidden="true"<?= $poster ? ' poster="' . e(\Core\Media::url($poster, 1600)) . '"' : '' ?>><source src="<?= e(\Core\Media::url($s['m'])) ?>" type="<?= e((string) $s['m']['mime']) ?>"></video>
      <?php else: ?>
      <?= img((int) $s['m']['id'], '100vw', ['eager' => $k === 0] + ($s['path'] !== '' ? ['path' => $s['path'] . '.image'] : ['path' => 'image'])) ?>
      <?php endif; ?>
      <?php if ($s['caption'] !== ''): ?><figcaption class="stage__cap"<?= $b->edit($s['path'] . '.caption') ?>><?= e($s['caption']) ?></figcaption><?php endif; ?>
    </figure>
    <?php endforeach; ?>
    <?php if (!$slides && is_editing()): ?><div class="stage__slide is-active frame--empty"><span><?= e($v === 'slideshow' ? 'Bilder der Folge in der Seitenleiste wählen' : 'Bild in der Seitenleiste wählen') ?></span></div><?php endif; ?>
    <?php if ($multi || $hasVideo): ?>
    <div class="stage__ctrl" data-stage-ctrl hidden>
      <?php if ($multi): ?><button type="button" class="stage__btn" data-stage-prev><?= icon('arrow-right', ['class' => 'flip-x']) ?><span class="sr-only"><?= e(lt('Vorheriges Bild')) ?></span></button><?php endif; ?>
      <button type="button" class="stage__btn" data-stage-toggle aria-pressed="false" data-label-pause="<?= e($multi ? lt('Bildfolge anhalten') : lt('Hintergrundvideo anhalten')) ?>" data-label-play="<?= e($multi ? lt('Bildfolge abspielen') : lt('Hintergrundvideo abspielen')) ?>"><span class="stage__pause" aria-hidden="true"></span><span class="sr-only" data-stage-label><?= e($multi ? lt('Bildfolge anhalten') : lt('Hintergrundvideo anhalten')) ?></span></button>
      <?php if ($multi): ?><button type="button" class="stage__btn" data-stage-next><?= icon('arrow-right') ?><span class="sr-only"><?= e(lt('Nächstes Bild')) ?></span></button>
      <span class="stage__count" aria-live="polite" data-stage-count data-count="<?= e(lt('Bild {n} von {total}')) ?>"></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($d['scroll_hint']) && $pos !== 'below'): ?>
    <a class="stage__hint" href="#<?= e($endId) ?>"><span class="sr-only"><?= e(lt('Weiter nach unten')) ?></span><span class="stage__hint-line" aria-hidden="true"></span></a>
    <?php endif; ?>
  </div>
</div>
<?= $zone !== '' ? '<div class="fdz-wrap">' . $zone . '</div>' : '' ?>
<span id="<?= e($endId) ?>" class="stage__end"></span>
