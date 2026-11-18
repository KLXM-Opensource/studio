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
  <p class="eb-preview__note"><?= icon('squares-four') ?> <?= e($blocks ? __('Inhaltsblöcke des Eintrags – nur Vorschau ({n} Blöcke). Ändern lassen sie sich je Eintrag im Modus „Inhalt“.', ['n' => count($blocks)]) : __('Hier erscheinen die Inhaltsblöcke des jeweiligen Eintrags. Der Vorschau-Eintrag hat noch keine.')) ?></p>
</div>
<?php // Nur Vorschau: inert + data-cms-readonly – die Vorlage darf Inhalte des Eintrags nicht ändern (editor.js bindet hier kein [data-edit]) ?>
<?php if ($blocks && is_editing()): ?><div class="eb-preview__blocks" data-cms-readonly inert><?= EntryBlocks::render($blocks) ?></div><?php endif; ?>
