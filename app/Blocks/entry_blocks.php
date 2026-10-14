<?php
/**
 * Inhaltsblöcke des Eintrags (Kern-Block). Auf der Website ersetzt Core\Theme::renderBlocks diesen Block durch die Blöcke des
 * aufgerufenen Eintrags (eigene Abschnitte). Diese Datei zeichnet nur die Vorschau im Seiten-Editor der Detailvorlage.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\EntryBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$blocks = EntryBlocks::forContext($d);
?>
<div class="<?= e($wrap) ?> eb-preview">
  <?php if (!is_editing()): return; endif; ?>
  <p class="eb-preview__note"><?= icon('squares-four') ?> <?= e($blocks ? __('Inhaltsblöcke des Eintrags – Vorschau ({n} Blöcke). Bearbeitet werden sie im Eintrag.', ['n' => count($blocks)]) : __('Hier erscheinen die Inhaltsblöcke des jeweiligen Eintrags. Der Vorschau-Eintrag hat noch keine.')) ?></p>
</div>
<?php if ($blocks && is_editing()) echo EntryBlocks::render($blocks); ?>
