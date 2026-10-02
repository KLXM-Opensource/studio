<?php
/**
 * Abschnitt (Tailwind): Abstände und Hintergründe kommen aus @layer components (tailwind/_kit.css: .sec, .bg-plain|tint|band|ink),
 * weil die Klassen vom Kern stammen ($b->sectionClass()) und nicht im Quelltext stehen.
 * @var \Core\Block $b  @var string $inner
 */
$titled = trim((string) ($b->data['title'] ?? '')) !== '';
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
