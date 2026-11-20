<?php
/**
 * Hülle je Block – wie bei fluid, dazu das Attribut des Einblend-Effekts (Abschnitt → „Einblend-Effekt“, theme.php → 'reveal':
 * data-ncss-reveal für js/ncss-reveal.js; die Klassen setzt Block::sectionClass).
 * @var \Core\Block $b  @var string $inner
 */
$titled = trim((string) ($b->data['title'] ?? '')) !== '';
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?><?= $b->revealAttrs() ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
