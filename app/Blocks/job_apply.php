<?php
/**
 * Stelle: Bewerbung (Kern-Block, vom Kit überschreibbar) – Bewerbungsformular auf der Detailseite eines Stellenangebots.
 * Formular = Eingang der Stellen-Tabelle (settings.jobs.form, Core\Data\Jobs); Feld „Stelle“ vorbelegt und gesperrt,
 * `_job` ({tabelle}:{id}) lässt den Server den Wert selbst setzen. Abgelaufen: keine Ausgabe (Hinweis steht bei den Eckdaten).
 * Aussehen wie „Formular (Datentabelle)“ (dff-*, css/dataform.css + Kit-Stile für data_form).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\DataForms;
use Core\Data\Entries;
use Core\Data\Jobs;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$ctx = app()->entry;
$t = $ctx && Jobs::is($ctx['table']) ? $ctx['table'] : null;
$e = $t ? $ctx['entry'] : null;
if (!$t && is_editing()) {
    foreach (Jobs::tables() as $jt) { $t = $jt; $e = Entries::query($jt, ['status' => 'all', 'limit' => 1, 'expired' => true])[0] ?? null; break; }
}
$form = $t ? Jobs::formTable($t) : null;
if (!$t || !$e || !$form) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">' . e(!$t || !$e
        ? __('Dieser Block gehört auf die Detailseite einer Stellen-Tabelle. Die Vorschau erscheint, sobald es eine Stelle gibt.')
        : __('Noch kein Bewerbungsformular: Daten → {table} → Felder & Einstellungen → „Bewerbungsformular“ (Eingang mit eingeschaltetem Formular).', ['table' => $t['name']])) . '</p></div>';
    return;
}
if (Jobs::expired($t, $e) && !is_editing()) return;
$field = (string) Jobs::config($t)['field'];
$state = DataForms::$state[$form['handle']] ?? [];
$width = in_array($d['form_width'] ?? '', ['text', 'normal', 'full'], true) ? $d['form_width'] : 'text';
$align = ($d['form_align'] ?? '') === 'center' ? 'center' : 'left';
?>
<div class="<?= e($wrap) ?> dff-wrap dff-wrap--w-<?= $width ?> dff-wrap--a-<?= $align ?> job-apply" data-width="<?= $width ?>" data-align="<?= $align ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="dff-head<?= $align === 'center' ? ' sec-head--center' : '' ?>">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m dff-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted dff-intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <?= DataForms::render($form, [
      'uid' => $b->domId() . '-f', 'submit' => $d['submit_label'] ?? '', 'success' => $d['success_text'] ?? '',
      'values' => $state['values'] ?? [], 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
      'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null,
      'locked' => \Core\Data\Tables::field($form, $field) ? [$field => Jobs::label($t, $e)] : [],
      'hidden' => ['_job' => Jobs::ref($t, $e)],
  ]) ?>
</div>
