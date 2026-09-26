<?php
/** Standard-Hülle je Block. @var \Core\Block $b  @var string $inner */
$titled = !empty($b->data['title_strong']) || !empty($b->data['title']);
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
