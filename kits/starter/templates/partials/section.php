<?php
/**
 * Hülle je Block (Core\Theme::renderBlock): <section> mit Klassen aus den Abschnitts-Optionen –
 * sec, sec--{typ}, bg-{hintergrund}, v-{variante}, pt-/pb-small|none, sec--divider, sec--screen, sec--has-bg …
 * sectionBg(): optionales Hintergrundbild (ohne Inline-Styles). Blöcke mit 'raw' => true rendern ihre Hülle selbst.
 * @var \Core\Block $b  @var string $inner
 */
$titled = trim((string) ($b->data['title'] ?? '')) !== '';
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
