<?php
/** Standard-Hülle je Block. @var \Core\Block $b  @var string $inner */
// Block ohne Ausgabe (z. B. leerer Fließtext, Formular ohne Tabelle): für Besucher kein leerer Abschnitt mit Abstand
if (trim($inner) === '' && !is_editing()) return;
$titled = !empty($b->data['title_strong']) || !empty($b->data['title']);
?>
<section id="<?= e($b->domId()) ?>" class="<?= e($b->sectionClass()) ?>"<?= $titled ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
<?= $b->sectionBg() ?><?= $inner ?>
</section>
