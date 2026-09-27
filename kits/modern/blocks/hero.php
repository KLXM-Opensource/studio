<?php
/**
 * Einstieg (H1) – sieben Varianten:
 *   split      Text links, Bild rechts auf einer versetzten Farbfläche (Blockfarbe), optional Kennzahl-Karte über dem Bild
 *   statement  Schriftzug über die volle Breite, darunter Text und Buttons nebeneinander, optional Bild 21:9
 *   mosaic     Text neben einem Bildraster (drei Bilder + Kennzahl-Kachel in Blockfarbe)
 *   compact    Seitenkopf ohne Bild (Unterseiten)
 *   product    Produktbühne: Hauptbild mit Preis-Sticker, Bento-Reihe aus dunkler Datenblatt-Karte (Hero::pairs) und
 *              zwei Detailbildern (image, image2, image3) – css/hero-product.css
 *   marquee    Typo-Einstieg ohne Bild: übergroßer Schriftzug, darunter eine schräge Laufzeile in Blockfarbe über die
 *              volle Breite (Core\Blocks\Hero::marquee, Pause-Schaltfläche, steht bei „Bewegung reduzieren“) – css/hero-marquee.css
 *   figures    Aussage + 3–4 Kennzahlen als farbige Kacheln mit den Kern-Rundinstrumenten (Hero::dials) – css/hero-figures.css
 * Mobil stehen Text und Bild untereinander; das Mosaik bleibt ein kleines Raster.
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = in_array($b->variant(), ['split', 'statement', 'mosaic', 'compact', 'product', 'marquee', 'figures'], true) ? $b->variant() : 'split';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = modern_lines($d['points'] ?? '');
$text = trim((string) ($d['text'] ?? ''));
$eager = ['eager' => true];
$statValue = trim((string) ($d['stat_value'] ?? ''));
$statLabel = trim((string) ($d['stat_label'] ?? ''));

$eyebrow = fn() => ($d['eyebrow'] ?? '') !== '' ? '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($d['eyebrow']) . '</p>' : '';
$lead = fn() => ($text !== '' || is_editing()) ? '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(e($text), false) . '</p>' : '';
$stat = fn(string $class) => $statValue !== '' || is_editing()
    ? '<p class="' . $class . '"><span class="hstat__value"' . $b->edit('stat_value') . '>' . e($statValue) . '</span><span class="hstat__label"' . $b->edit('stat_label') . '>' . e($statLabel) . '</span></p>' : '';
$textHtml = function (string $class = '') use ($b, $d, $points, $eyebrow, $lead): string {
    return '<div class="hero__text' . ($class !== '' ? ' ' . $class : '') . '">' . $eyebrow()
        . '<h1 id="' . e($b->titleId()) . '" class="h1 hero__title"' . $b->edit('title') . '>' . modern_title((string) $d['title']) . '</h1>'
        . $lead() . modern_buttons($b, 'hero__actions') . modern_checks($points, 'hero__points') . '</div>';
};

if ($v === 'statement'): ?>
<div class="wrap hero-type">
  <?= $eyebrow() ?>
  <h1 id="<?= e($b->titleId()) ?>" class="hero-type__title"<?= $b->edit('title') ?>><?= modern_title((string) $d['title']) ?></h1>
  <div class="hero-type__foot">
    <?= $lead() ?>
    <div class="hero-type__side"><?= modern_buttons($b, 'hero__actions') ?><?= modern_checks($points, 'hero__points') ?></div>
  </div>
  <?= modern_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '21:9', 'hero-type__media', $eager) ?>
</div>
<?php elseif ($v === 'mosaic'):
    $tiles = [
        modern_image($d['image'] ?? null, '(min-width: 1080px) 340px, 50vw', '', 'mosaic__a', $eager),
        modern_image($d['image2'] ?? null, '(min-width: 1080px) 300px, 50vw', '', 'mosaic__b', $eager),
        modern_image($d['image3'] ?? null, '(min-width: 1080px) 640px, 100vw', '', 'mosaic__c', $eager),
    ];
?>
<div class="wrap hero hero--mosaic">
  <?= $textHtml() ?>
  <div class="mosaic<?= $tiles[2] === '' ? ' mosaic--two' : '' ?>">
    <?= implode('', $tiles) ?>
    <?= $stat('mosaic__s hstat') ?>
  </div>
</div>
<?php elseif ($v === 'product'):
    $specs = Hero::pairs($d['specs'] ?? '');
    $specTitle = trim((string) ($d['spec_title'] ?? ''));
    $price = trim((string) ($d['price'] ?? ''));
    $priceNote = trim((string) ($d['price_note'] ?? ''));
    $badge = trim((string) ($d['badge'] ?? ''));
    $main = modern_image($d['image'] ?? null, '(min-width: 1280px) 720px, (min-width: 1024px) 56vw, 100vw', '4:3', 'hpr__img', $eager);
    $thumbs = array_values(array_filter([
        modern_image($d['image2'] ?? null, '(min-width: 1280px) 300px, (min-width: 1024px) 24vw, 50vw', '1:1', 'hpr__thumb'),
        modern_image($d['image3'] ?? null, '(min-width: 1280px) 300px, (min-width: 1024px) 24vw, 50vw', '1:1', 'hpr__thumb'),
    ]));
    $sheet = $specs || $specTitle !== '' || is_editing();
?>
<div class="wrap hpr<?= $sheet ? '' : ' hpr--nosheet' ?> hpr--t<?= count($thumbs) ?>">
  <?= $textHtml('hpr__text') ?>
  <?php if ($main !== '' || $price !== '' || $badge !== ''): ?>
  <div class="hpr__stage">
    <?= $main ?>
    <?php if ($badge !== '' || is_editing()): ?><p class="hpr__badge"<?= $b->edit('badge') ?>><?= e($badge) ?></p><?php endif; ?>
    <?php if ($price !== '' || is_editing()): ?>
    <p class="hpr__tag"><span class="hpr__price"<?= $b->edit('price') ?>><?= e($price) ?></span><?php if ($priceNote !== '' || is_editing()): ?><span class="hpr__note"<?= $b->edit('price_note') ?>><?= e($priceNote) ?></span><?php endif; ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($sheet): ?>
  <section class="hpr__sheet bg-dark" aria-labelledby="<?= e($b->titleId()) ?>-spec">
    <div class="hpr__sheet-head">
      <p class="hpr__kicker" aria-hidden="true"><?= e(lt('Datenblatt')) ?></p>
      <h2 id="<?= e($b->titleId()) ?>-spec" class="hpr__sheet-title"<?= $b->edit('spec_title') ?>><?= $specTitle !== '' ? e($specTitle) : e(lt('Datenblatt')) ?></h2>
    </div>
    <?php if ($specs): ?>
    <dl class="hpr__specs">
      <?php foreach ($specs as $s): ?>
      <div class="hpr__spec"><?php if ($s['label'] !== ''): ?><dt><?= e($s['label']) ?></dt><?php endif; ?><dd><?= e($s['value']) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php elseif (is_editing()): ?>
    <p class="hpr__empty"><?= e(__('Technische Angaben in der Seitenleiste eintragen – eine pro Zeile, z. B. „Gewicht: 1,2 kg“.')) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <?= implode('', $thumbs) ?>
</div>
<?php elseif ($v === 'marquee'):
    $band = Hero::marquee((string) ($d['marquee'] ?? ''), 'hero-marquee');
?>
<div class="wrap hmq">
  <?= $eyebrow() ?>
  <h1 id="<?= e($b->titleId()) ?>" class="hmq__title"<?= $b->edit('title') ?>><?= modern_title((string) $d['title']) ?></h1>
  <div class="hmq__foot">
    <?= $lead() ?>
    <div class="hmq__side"><?= modern_buttons($b, 'hero__actions') ?><?= modern_checks($points, 'hero__points') ?></div>
  </div>
</div>
<?php if ($band !== ''): ?>
<div class="hero-marquee-band"><?= $band ?></div>
<?php elseif (is_editing()): ?>
<p class="wrap hmq__empty"><?= e(__('Laufzeile in der Seitenleiste eintragen – Begriffe mit „·“ trennen.')) ?></p>
<?php endif; ?>
<?php elseif ($v === 'figures'):
    $dials = Hero::dials($b, $d, 'm');
    $n = count(array_filter((array) ($d['items'] ?? []), fn($i) => is_array($i) && trim((string) ($i['value'] ?? '')) !== ''));
?>
<div class="wrap hfg hfg--n<?= max(1, min(4, $n)) ?>">
  <?= $textHtml('hfg__text') ?>
  <?php if ($dials !== ''): ?><div class="hfg__tiles"><?= $dials ?></div><?php endif; ?>
</div>
<?php elseif ($v === 'compact'): ?>
<div class="wrap hero hero--compact">
  <?= $textHtml() ?>
</div>
<?php else:
    $media = modern_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', $ratio, 'hero__img', $eager);
?>
<div class="wrap hero hero--split<?= $media !== '' ? ' hero--media' : '' ?>">
  <?= $textHtml() ?>
  <?php if ($media !== ''): ?>
  <div class="hero__media">
    <?= $media ?>
    <?= $stat('hero__stat hstat') ?>
  </div>
  <?php endif; ?>
</div>
<?php endif;
