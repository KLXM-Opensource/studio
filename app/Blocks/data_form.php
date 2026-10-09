<?php
/**
 * Formular (Datentabelle): Besucher legen einen Eintrag an (Kern-Block, vom Theme überschreibbar).
 * Einstellungen je Tabelle unter Daten → Tabelle → Felder → „Öffentliches Formular“. Ausgabe: Core\Data\DataForms::render.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\DataForms;
use Core\Data\Tables;

$t = Tables::find((string) ($d['table'] ?? ''));
$wrap = app()->theme->def['container_class'] ?? 'wrap';
if (!$t || !DataForms::enabled($t)) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">' . e(!$t ? __('Bitte in der Seitenleiste eine Tabelle wählen.')
        : __('Für „{table}“ ist das öffentliche Formular ausgeschaltet (Daten → Tabelle → Felder → Öffentliches Formular).', ['table' => $t['name']])) . '</p></div>';
    return;
}
$state = DataForms::$state[$t['handle']] ?? [];
// Darstellung (Block-Optionen): Breite text|normal|full, Ausrichtung left|center – ältere Blöcke ohne Angabe: Textbreite, links
$width = in_array($d['form_width'] ?? '', ['text', 'normal', 'full'], true) ? $d['form_width'] : 'text';
$align = ($d['form_align'] ?? '') === 'center' ? 'center' : 'left';
?>
<div class="<?= e($wrap) ?> dff-wrap dff-wrap--w-<?= $width ?> dff-wrap--a-<?= $align ?>" data-width="<?= $width ?>" data-align="<?= $align ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="dff-head<?= $align === 'center' ? ' sec-head--center' : '' ?>">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m dff-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted dff-intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <?= DataForms::render($t, [
      'uid' => $b->domId() . '-f', 'submit' => $d['submit_label'] ?? '', 'success' => $d['success_text'] ?? '',
      'values' => $state['values'] ?? [], 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
      'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null,
  ]) ?>
</div>
