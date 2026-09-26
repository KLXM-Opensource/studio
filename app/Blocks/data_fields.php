<?php
/**
 * Datensatz-Felder für Detailseiten-Vorlagen (Kern-Block, vom Theme überschreibbar).
 * Zeigt Felder des aufgerufenen Eintrags; im Editor den gewählten Vorschau-Eintrag.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$ctx = app()->entry;
$t = Tables::findContent((string) ($d['table'] ?? '')) ?? ($ctx['table'] ?? null);
if (!$t) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">Bitte in der Seitenleiste die Tabelle wählen.</p></div>';
    return;
}
$e = $ctx && $ctx['table']['handle'] === $t['handle'] ? $ctx['entry'] : (is_editing() ? (Entries::query($t, ['status' => 'all', 'limit' => 1])[0] ?? null) : null);
if (!$e) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">Noch keine Einträge in „' . e($t['name']) . '“ – die Vorschau erscheint, sobald es einen gibt.</p></div>';
    return;
}
$fields = [];
foreach ((array) ($d['fields'] ?? []) as $v) {
    if (str_starts_with((string) $v, $t['handle'] . '.')) $fields[] = substr((string) $v, strlen($t['handle']) + 1);
}
$layout = $d['layout'] ?? 'prose';
if (!$fields) {
    $fields = $layout === 'head' ? array_values(array_filter(['_title', 'published_at', Tables::imageField($t)]))
        : array_values(array_filter(array_column($t['fields'], 'name'), fn($n) => $n !== $t['settings']['title_field']));
}
$label = fn(string $f) => $f === '_title' ? lt('Titel') : ($f === '_when' ? lt('Termin') : ($f === 'published_at' ? lt('Veröffentlicht') : Tables::label(Tables::field($t, $f) ?? ['label' => $f])));
$type = fn(string $f) => Tables::field($t, $f)['type'] ?? ($f === '_title' ? 'title' : 'date');
$ratio = (string) ($d['ratio'] ?? '16:9');
?>
<div class="<?= e($wrap) ?> df df--<?= e($layout) ?>">
  <?php if (!empty($d['back_label']) && !empty($d['back_link'])): ?><p class="df-back"><a href="<?= e(link_href((string) $d['back_link'])) ?>">← <?= e($d['back_label']) ?></a></p><?php endif; ?>

  <?php if ($layout === 'head'): $img = null; $meta = []; ?>
  <header class="df-head">
    <?php foreach ($fields as $f) {
        if ($type($f) === 'media') { $img = $f; continue; }
        if ($f !== '_title' && ($h = Entries::html($t, $e, $f)) !== '') $meta[] = ($ea = entry_edit_attr($t, $e, $f, 'plain')) !== '' ? '<span' . $ea . '>' . $h . '</span>' : $h;
    } ?>
    <?php if ($meta): ?><p class="df-meta"><?= implode('<span class="df-sep" aria-hidden="true"> · </span>', $meta) ?></p><?php endif; ?>
    <?php if (in_array('_title', $fields, true)): $ea = entry_edit_attr($t, $e, '_title'); ?><h1 class="h2 df-title"><?= $ea !== '' ? '<span' . $ea . '>' . e(Entries::title($t, $e)) . '</span>' : e(Entries::title($t, $e)) ?><span class="dot">.</span></h1><?php endif; ?>
    <?php if ($img && ($pic = Entries::html($t, $e, $img, ['ratio' => $ratio, 'sizes' => '(min-width: 1280px) 1200px, 100vw']))): ?>
      <div class="df-img ratio-<?= e(str_replace(':', '-', $ratio)) ?>"><?= $pic ?></div>
    <?php endif; ?>
  </header>

  <?php elseif ($layout === 'image'): foreach ($fields as $f): if ($type($f) !== 'media') continue; $pic = Entries::html($t, $e, $f, ['ratio' => $ratio, 'sizes' => '(min-width: 1280px) 1200px, 100vw']); ?>
    <?php if ($pic): ?><div class="df-img ratio-<?= e(str_replace(':', '-', $ratio)) ?>"><?= $pic ?></div><?php endif; break; endforeach; ?>

  <?php elseif ($layout === 'dl'): ?>
  <dl class="df-dl">
    <?php foreach ($fields as $f): $h = $f === '_title' ? e(Entries::title($t, $e)) : Entries::html($t, $e, $f); if ($h === '') continue; ?>
    <div><dt><?= e($label($f)) ?></dt><dd<?= entry_edit_attr($t, $e, $f) ?>><?= $h ?></dd></div>
    <?php endforeach; ?>
  </dl>

  <?php else: ?>
  <div class="df-prose">
    <?php foreach ($fields as $f): $h = $f === '_title' ? e(Entries::title($t, $e)) : Entries::html($t, $e, $f, ['ratio' => $ratio]); if ($h === '') continue; $tp = $type($f); $ea = entry_edit_attr($t, $e, $f); ?>
    <div class="df-f df-f--<?= e($tp) ?>">
      <?php if (!empty($d['show_labels'])): ?><p class="df-label"><?= e($label($f)) ?></p><?php endif; ?>
      <?= in_array($tp, ['richtext'], true) ? '<div class="prose"' . $ea . '>' . $h . '</div>' : ($tp === 'media' ? '<div class="df-img ratio-' . e(str_replace(':', '-', $ratio)) . '">' . $h . '</div>' : '<p' . $ea . '>' . $h . '</p>') ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
