<?php
/**
 * Einstieg (H1) im Kit „NativeCSS“:
 *   split      Text neben Bild (.ncss-split, stapelt sich per Container Query)
 *   centered   zentriert, Bild darunter
 *   compact    Seitenkopf ohne Bild (Unterseiten) als getönte Kachel
 *   type       großer Schriftzug, Betonung *Wort* im Markenverlauf, Dachzeile als .ncss-badge
 *   fullbleed  Bild oder Hintergrundvideo vollflächig mit Text darauf (.ncss-hero; Video: js/video-bg.js von NativeCSS mit
 *              Pausieren/Abspielen, lädt erst in der Nähe, nie bei „Bewegung reduzieren“ oder „Daten sparen“)
 * Übrige Varianten (cards, scale, collage, compare) und „Vollflächig“ mit Bilderfolge oder Bildbewegung kommen aus dem Kit fluid.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'split';
$bleedFluid = $v === 'fullbleed' && (array_filter((array) ($d['gallery'] ?? []), fn($g) => !empty($g['image'])) || in_array($d['bleed_motion'] ?? '', ['parallax', 'zoom'], true));
if (!in_array($v, ['split', 'centered', 'compact', 'type', 'fullbleed'], true) || $bleedFluid) {
    require native_parent_view('hero');
    return;
}
native_mark('@nv-hero');
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = fluid_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));

$textHtml = function (string $titleClass = 'h1') use ($b, $d, $text, $points, $eyebrow, $v): string {
    $h = '';
    if ($eyebrow !== '' || is_editing()) {
        $h .= '<p class="' . ($v === 'type' ? 'ncss-badge nv-hero__badge' : 'eyebrow') . '"' . $b->edit('eyebrow') . '>' . fluid_title($eyebrow) . '</p>';
    }
    $h .= '<h1 id="' . e($b->titleId()) . '" class="' . $titleClass . ' nv-hero__title"' . $b->edit('title') . '>' . fluid_title((string) $d['title']) . '</h1>';
    if ($text !== '' || is_editing()) $h .= '<p class="nv-hero__lead"' . $b->edit('text') . '>' . nl2br(fluid_title($text), false) . '</p>';
    return $h . fluid_buttons($b, 'nv-hero__actions') . fluid_checks($points, 'hero__points');
};

if ($v === 'fullbleed'):
    $vid = !empty($d['video']) ? media((int) $d['video']) : null;
    $vid = $vid && str_starts_with((string) $vid['mime'], 'video/') ? $vid : null;
    $overlay = in_array($d['overlay'] ?? '', ['strong', 'medium', 'gradient'], true) ? $d['overlay'] : 'strong';
    $height = in_array($d['bleed_height'] ?? '', ['tall', 'medium', 'low'], true) ? $d['bleed_height'] : 'tall';
    // Hintergrundvideo nur für Besucher; beim Bearbeiten das Standbild
    $vid = is_editing() ? null : $vid;
    if ($vid) native_mark('@videobg');
?>
<div class="ncss-hero nv-hero nv-hero--bleed nv-hero--<?= e($overlay) ?> nv-hero--h-<?= e($height) ?>"<?= $vid ? ' data-ncss-video-bg' : '' ?>>
  <div class="ncss-hero-media" aria-hidden="true">
    <?= !empty($d['image']) ? img((int) $d['image'], '100vw', ['eager' => true, 'alt' => '']) : '' ?>
    <?php if ($vid): ?><video class="nv-hero__video" muted loop playsinline preload="none" aria-hidden="true"><?php /* kein poster: das Standbild liegt darunter */ ?><source data-src="<?= e(\Core\Media::url($vid)) ?>" type="<?= e((string) $vid['mime']) ?>"></video><?php endif; ?>
  </div>
  <div class="ncss-hero-content ncss-hero-content--<?= $overlay === 'gradient' ? 'transparent' : 'filled' ?>">
    <?= $textHtml() ?>
  </div>
</div>
<?php elseif ($v === 'type'): ?>
<div class="wrap nv-hero nv-hero--type">
  <div class="nv-hero__text"><?= $textHtml('nv-hero__display') ?></div>
  <?= fluid_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1280px) 1280px, 100vw', $ratio === '21:9' ? '21:9' : '16:9', 'nv-hero__media', $eager) ?>
</div>
<?php elseif ($v === 'split'):
    $media = fluid_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 600px, 100vw', $ratio, 'nv-hero__media', $eager);
?>
<div class="wrap">
  <div class="ncss-split-container">
    <div class="ncss-split nv-hero nv-hero--split<?= $media !== '' ? ' nv-hero--media' : '' ?>">
      <div class="ncss-split-content nv-hero__text"><?= $textHtml() ?></div>
      <?php if ($media !== ''): ?><div class="ncss-split-media"><?= $media ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php else:
    $media = $v === 'compact' ? '' : fluid_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1280px) 1280px, 100vw', '16:9', 'nv-hero__media', $eager);
?>
<div class="wrap nv-hero nv-hero--<?= e($v) ?>">
  <div class="nv-hero__text"><?= $textHtml() ?></div>
  <?= $media ?>
</div>
<?php endif;
