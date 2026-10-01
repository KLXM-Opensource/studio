<?php
/**
 * Stelle: Eckdaten (Kern-Block, vom Kit überschreibbar) – Detailseite eines Stellenangebots (Core\Data\Jobs).
 * Beschäftigungsart, Arbeitsort, Beginn, Gehalt, Fristen, Ansprechperson + Button „Jetzt bewerben“ (#bewerben);
 * abgelaufen: Hinweis „nicht mehr ausgeschrieben“ statt Button. Stile: css/jobs.css (Variablen --job-*).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Jobs;
use Core\Data\Tables;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
$ctx = app()->entry;
$t = $ctx && Jobs::is($ctx['table']) ? $ctx['table'] : null;
$e = $t ? $ctx['entry'] : null;
if (!$t && is_editing()) {
    // Editor ohne Eintrag (z. B. Vorlage direkt geöffnet): erste Stelle als Vorschau
    foreach (Jobs::tables() as $jt) { $t = $jt; $e = Entries::query($jt, ['status' => 'all', 'limit' => 1, 'expired' => true])[0] ?? null; break; }
}
if (!$t || !$e) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">' . e(__('Dieser Block gehört auf die Detailseite einer Stellen-Tabelle (schema.org-Typ „Stellenangebot“). Die Vorschau erscheint, sobald es eine Stelle gibt.')) . '</p></div>';
    return;
}
$expired = Jobs::expired($t, $e);
$facts = Jobs::facts($t, $e);
$apply = trim((string) ($d['apply_label'] ?? ''));
$form = Jobs::formTable($t);
?>
<div class="<?= e($wrap) ?> jf<?= $expired ? ' jf--expired' : '' ?>">
  <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m jf-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
  <?php if ($expired): ?>
  <p class="jf-expired" role="note"><strong><?= e(lt('Diese Stelle ist nicht mehr ausgeschrieben.')) ?></strong>
    <?php if ($t['settings']['route'] !== '' && ($list = \Core\Pages::byPath((string) $t['settings']['route'])) && empty($list['is_home'])): ?>
    <a href="<?= e(\Core\Pages::url($list)) ?>"><?= e(lt('Zu den aktuellen Stellenangeboten')) ?></a>
    <?php endif; ?></p>
  <?php endif; ?>
  <?php if ($facts): ?>
  <dl class="jf-list">
    <?php foreach ($facts as [$label, $html]): ?><div class="jf-item"><dt><?= e($label) ?></dt><dd><?= $html ?></dd></div><?php endforeach; ?>
  </dl>
  <?php endif; ?>
  <?php if (!$expired && $apply !== '' && $form): ?>
  <p class="jf-actions"><a class="<?= e($btn) ?>" href="#bewerben"<?= $b->edit('apply_label') ?>><?= e($apply) ?></a></p>
  <?php elseif (!$expired && $apply !== '' && is_editing()): ?>
  <p class="dl-empty"><?= e(__('Ohne Bewerbungsformular kein Button: Daten → Tabelle → Felder & Einstellungen → „Bewerbungsformular“.')) ?></p>
  <?php endif; ?>
</div>
