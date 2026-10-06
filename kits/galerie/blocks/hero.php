<?php
/**
 * Einstieg (H1) – sechs Varianten, alle intrinsisch (Flex-Umbruch bzw. Container-Query statt Breakpoints):
 * split (Text + Bild), centered (Bild darunter), fullbleed (Bild/Video vollflächig), cards (Bild mit gestapelten Karten),
 * type (großer Schriftzug in Container-Einheiten), compact (Seitenkopf).
 * Dazu collage: 1–5 Bilder (Bild + Feld „gallery“) im Raster mit schwebender Textkarte ab 52 rem (css/hx-collage.css, nur wo verwendet).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'split';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = galerie_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];

$textHtml = function (string $class = '') use ($b, $d, $text, $points): string {
    $h = '<div class="hero__text' . ($class !== '' ? ' ' . $class : '') . '">';
    if (($d['eyebrow'] ?? '') !== '') $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($d['eyebrow']) . '</p>';
    $h .= '<h1 id="' . e($b->titleId()) . '" class="h1 hero__title"' . $b->edit('title') . '>' . galerie_title((string) $d['title']) . '</h1>';
    if ($text !== '' || is_editing()) $h .= '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(e($text), false) . '</p>';
    $h .= galerie_buttons($b, 'hero__actions');
    $h .= galerie_checks($points, 'hero__points');
    return $h . '</div>';
};

if ($v === 'fullbleed'):
    $vid = !empty($d['video']) ? media((int) $d['video']) : null;
    $vid = $vid && str_starts_with((string) $vid['mime'], 'video/') ? $vid : null;
    $overlay = in_array($d['overlay'] ?? '', ['strong', 'medium', 'gradient'], true) ? $d['overlay'] : 'strong';
    $poster = !empty($d['image']) ? media((int) $d['image']) : null;
?>
<div class="hero-bleed hero-bleed--<?= e($overlay) ?>">
  <div class="hero-bleed__media" aria-hidden="true">
    <?= !empty($d['image']) ? img((int) $d['image'], '100vw', ['eager' => true, 'alt' => '']) : '' ?>
    <?php if ($vid): ?><video class="hero-bleed__video" data-bg-video muted loop playsinline preload="none"<?= $poster ? ' poster="' . e(\Core\Media::url($poster, 1600)) . '"' : '' ?>><source src="<?= e(\Core\Media::url($vid)) ?>" type="<?= e((string) $vid['mime']) ?>"></video><?php endif; ?>
  </div>
  <div class="wrap hero-bleed__inner">
    <?= $textHtml() ?>
    <?php if ($vid): ?><button type="button" class="hero-bleed__pause" data-bg-video-toggle hidden aria-pressed="false"><?= icon('play-circle') ?><span data-label-pause="<?= e(lt('Hintergrundvideo anhalten')) ?>" data-label-play="<?= e(lt('Hintergrundvideo abspielen')) ?>"><?= e(lt('Hintergrundvideo anhalten')) ?></span></button><?php endif; ?>
  </div>
</div>
<?php elseif ($v === 'type'): ?>
<div class="wrap hero-type">
  <?php if (($d['eyebrow'] ?? '') !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
  <h1 id="<?= e($b->titleId()) ?>" class="hero-type__title"<?= $b->edit('title') ?>><?= galerie_title((string) $d['title']) ?></h1>
  <div class="hero-type__foot">
    <?php if ($text !== '' || is_editing()): ?><p class="hero__lead"<?= $b->edit('text') ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
    <?= galerie_buttons($b, 'hero__actions') ?>
  </div>
  <?= galerie_checks($points, 'hero__points') ?>
  <?= galerie_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '21:9' === $ratio ? '21:9' : '16:9', 'hero-type__media', $eager) ?>
</div>
<?php elseif ($v === 'cards'):
    $cards = array_values(array_filter((array) ($d['cards'] ?? []), fn($c) => trim((string) ($c['title'] ?? '')) !== ''));
?>
<div class="wrap hero hero--split hero--cards">
  <?= $textHtml() ?>
  <div class="hero-cards">
    <?= galerie_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', $ratio, 'hero-cards__media', $eager) ?>
    <?php if ($cards): ?>
    <ul class="hero-cards__list" role="list">
      <?php foreach ($cards as $i => $c): ?>
      <li class="hero-card">
        <?php if (!empty($c['icon'])): ?><span class="hero-card__ico"><?= icon((string) $c['icon']) ?></span><?php endif; ?>
        <span class="hero-card__body"><strong class="hero-card__title"<?= $b->edit("cards.$i.title") ?>><?= e($c['title']) ?></strong>
        <?php if (trim((string) ($c['text'] ?? '')) !== ''): ?><span class="hero-card__text"<?= $b->edit("cards.$i.text") ?>><?= e($c['text']) ?></span><?php endif; ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($v === 'collage'):
    // Bild + bis zu 4 weitere (Feld „gallery“) – Anordnung je Anzahl (Klasse hx-collage--nN)
    $tiles = [];
    if (!empty($d['image'])) $tiles[] = ['id' => (int) $d['image'], 'caption' => '', 'edit' => ''];
    foreach ((array) ($d['gallery'] ?? []) as $i => $g) {
        if (is_array($g) && !empty($g['image'])) $tiles[] = ['id' => (int) $g['image'], 'caption' => trim((string) ($g['caption'] ?? '')), 'edit' => "gallery.$i.caption"];
    }
    $tiles = array_slice($tiles, 0, 5);
    $pics = [];
    foreach ($tiles as $k => $t) {
        $pic = img($t['id'], $k === 0 ? '(min-width: 1080px) 520px, 100vw' : '(min-width: 1080px) 300px, 50vw', $k === 0 ? $eager : []);
        if ($pic !== '') $pics[] = ['pic' => $pic] + $t;
    }
?>
<div class="wrap hx-collage-wrap">
  <div class="hx-collage hx-collage--n<?= count($pics) ?>">
    <?= $textHtml('hx-collage__card') ?>
    <?php foreach ($pics as $k => $t): ?>
    <figure class="hx-collage__tile hx-collage__tile--<?= $k + 1 ?>"><?= $t['pic'] ?><?php if ($t['caption'] !== ''): ?><figcaption class="hx-collage__cap"<?= $b->edit($t['edit']) ?>><?= e($t['caption']) ?></figcaption><?php endif; ?></figure>
    <?php endforeach; ?>
    <?php if (!$pics && is_editing()): ?><div class="frame frame--empty hx-collage__tile hx-collage__tile--1"><span><?= e(__('Bild')) ?> + <?= e(__('Weitere Bilder (2–4)')) ?></span></div><?php endif; ?>
  </div>
</div>
<?php else:
    $media = $v === 'compact' ? '' : galerie_image($d['image'] ?? null,
        $v === 'centered' ? '(min-width: 1280px) 1280px, 100vw' : '(min-width: 1080px) 600px, 100vw', $v === 'centered' ? '16:9' : $ratio, 'hero__media', $eager);
?>
<div class="wrap hero hero--<?= e($v === 'centered' ? 'centered' : ($v === 'compact' ? 'compact' : 'split')) ?><?= $media !== '' ? ' hero--media' : '' ?>">
  <?= $textHtml() ?>
  <?= $media ?>
</div>
<?php endif;
