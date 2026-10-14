<?php
/**
 * Vor / Zurück für Detailseiten-Vorlagen (Kern-Block, vom Kit überschreibbar; Stile css/entry-nav.css): vorheriger und nächster
 * veröffentlichter Eintrag derselben Tabelle in der Reihenfolge der Tabelle (Sortierung der Tabelle). Im Editor: erster Eintrag.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$ctx = app()->entry;
$t = Tables::findContent((string) ($d['table'] ?? '')) ?? ($ctx['table'] ?? null);
if (!$t || ($t['settings']['route'] ?? '') === '' || empty($t['settings']['detail_page_id'])) {   // nur Tabellen mit Detailseiten
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">' . e(__('Bitte eine Tabelle mit Detailseiten wählen (oder den Block in deren Detailvorlage setzen).')) . '</p></div>';
    return;
}
$rows = Entries::query($t, ['limit' => 2000]);
$cur = $ctx && $ctx['table']['handle'] === $t['handle'] ? (int) $ctx['entry']['id'] : (is_editing() ? (int) ($rows[0]['id'] ?? 0) : 0);
$i = array_search($cur, array_map('intval', array_column($rows, 'id')), true);
if ($i === false) return;
$prev = $rows[$i - 1] ?? null;
$next = $rows[$i + 1] ?? null;
if (!$prev && !$next) return;
$link = function (?array $e, string $label, string $cls) use ($t): string {
    if (!$e || !($u = Entries::url($t, $e))) return '<span class="enav__slot"></span>';
    return '<a class="enav__link enav__link--' . $cls . '" href="' . e($u) . '" rel="' . ($cls === 'prev' ? 'prev' : 'next') . '"><span class="enav__label">'
        . ($cls === 'prev' ? '← ' : '') . e($label) . ($cls === 'next' ? ' →' : '') . '</span><span class="enav__title">' . e(strip_emphasis(Entries::title($t, $e))) . '</span></a>';
};
?>
<nav class="<?= e($wrap) ?> enav" aria-label="<?= e(lt('Weitere Einträge')) ?>">
  <?= $link($prev, trim((string) ($d['prev_label'] ?? '')) ?: lt('Vorheriger Eintrag'), 'prev') ?>
  <?= $link($next, trim((string) ($d['next_label'] ?? '')) ?: lt('Nächster Eintrag'), 'next') ?>
</nav>
