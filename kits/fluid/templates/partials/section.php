<?php
/** Standard-Hülle je Block. @var \Core\Block $b  @var string $inner */
$titled = trim((string) ($b->data['title'] ?? '')) !== '';
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
