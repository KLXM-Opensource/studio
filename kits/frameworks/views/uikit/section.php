<?php
/**
 * Abschnitt (UIkit): Kern-Klassen bleiben, dazu uk-section und eine UIkit-Fläche je Hintergrund. Dunkle Flächen bekommen
 * uk-light („inverse“): UIkit stellt darin Schrift, Links, Buttons und Formularfelder auf hell um.
 * @var \Core\Block $b  @var string $inner
 */
$titled = trim((string) ($b->data['title'] ?? '')) !== '';
$uk = ['plain' => 'uk-section-default', 'tint' => 'uk-section-muted', 'band' => 'uk-section-primary uk-light', 'ink' => 'uk-section-secondary uk-light'][$b->bg()] ?? 'uk-section-default';
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?> uk-section <?= $uk ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
