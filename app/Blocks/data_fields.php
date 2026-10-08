<?php
/**
 * Datensatz-Felder für Detailseiten-Vorlagen (Kern-Block, vom Theme überschreibbar).
 * Zeigt Felder des aufgerufenen Eintrags; im Editor den gewählten Vorschau-Eintrag.
 * Darstellungen: head, prose, dl, image – dazu „profile“ (Bild/Logo eingepasst auf einer Kachel, Titel als H1, Auswahlfelder
 * als Etiketten dl-chip dl-tone-1…5, übrige Felder als Einleitung) und „contact“ (Kontaktkarte: Textfelder als Adresse –
 * eine Postleitzahl steht mit der folgenden Zeile zusammen –, Telefon/E-Mail/Web als Links mit Symbol, Ort als Karte).
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

  <?php elseif ($layout === 'profile'): $img = null; $chips = $lead = []; $tone = fn(array $fd, string $k) => 'dl-tone-' . ((int) array_search($k, array_keys((array) ($fd['options'] ?? [])), true) % 5 + 1); ?>
  <?php foreach ($fields as $f) {
      $tp = $type($f);
      if ($tp === 'media') { $img ??= $f; continue; }
      if (in_array($tp, ['select', 'multiselect'], true)) {
          $fd = Tables::field($t, $f);
          foreach (array_filter((array) ($e[$f] ?? []), fn($v) => $v !== '' && $v !== null) as $k) $chips[] = '<span class="dl-chip ' . $tone($fd, (string) $k) . '">' . e(Tables::optionLabel($fd, (string) $k)) . '</span>';
          continue;
      }
      if ($f !== '_title' && ($h = Entries::html($t, $e, $f)) !== '') $lead[] = [$f, $tp, $h];
  } ?>
  <header class="df-profile<?= $img ? ' df-profile--img' : '' ?>">
    <?php if ($img && ($pic = Entries::html($t, $e, $img, ['ratio' => $ratio, 'sizes' => '(min-width: 1080px) 320px, 50vw']))): ?>
    <div class="df-img df-img--contain df-profile__img ratio-<?= e(str_replace(':', '-', $ratio)) ?>"<?= entry_edit_attr($t, $e, $img) ?>><?= $pic ?></div>
    <?php endif; ?>
    <div class="df-profile__body">
      <?php if ($chips): ?><p class="dl-chips df-profile__chips"><?= implode(' ', $chips) ?></p><?php endif; ?>
      <?php if (in_array('_title', $fields, true)): $ea = entry_edit_attr($t, $e, '_title'); ?><h1 class="h2 df-title"><?= $ea !== '' ? '<span' . $ea . '>' . e(Entries::title($t, $e)) . '</span>' : e(Entries::title($t, $e)) ?></h1><?php endif; ?>
      <?php foreach ($lead as [$f, $tp, $h]): ?><?= $tp === 'richtext' ? '<div class="prose df-profile__lead"' . entry_edit_attr($t, $e, $f) . '>' . $h . '</div>' : '<p class="df-profile__lead"' . entry_edit_attr($t, $e, $f) . '>' . $h . '</p>' ?><?php endforeach; ?>
    </div>
  </header>

  <?php elseif ($layout === 'contact'): $lines = $rows = []; $map = '';
      $icons = ['tel' => 'phone', 'email' => 'envelope-simple', 'url' => 'globe', 'link' => 'link'];
      foreach ($fields as $f) {
          $tp = $type($f);
          $h = $f === '_title' ? e(Entries::title($t, $e)) : Entries::html($t, $e, $f);
          if ($h === '') continue;
          if ($tp === 'geo') { $map = $h; continue; }
          if (isset($icons[$tp])) { $rows[] = [$f, $tp, $h]; continue; }
          // Adresse: Textzeilen untereinander; eine Postleitzahl steht mit der folgenden Zeile (Ort) zusammen
          $plain = trim(strip_tags($h));
          if ($lines && preg_match('~^\d{4,5}$~', $lines[count($lines) - 1][1])) { $lines[count($lines) - 1][0] .= ' ' . $h; $lines[count($lines) - 1][1] = $plain . 'x'; continue; }
          $lines[] = [$h, $plain];
      } ?>
  <div class="df-contact">
    <?php if ($lines): ?><address class="df-contact__addr"><?= implode('<br>', array_column($lines, 0)) ?></address><?php endif; ?>
    <?php if ($rows): ?><ul class="df-contact__list" role="list">
      <?php foreach ($rows as [$f, $tp, $h]): ?><li><?= icon($icons[$tp]) ?><span><span class="sr-only"><?= e($label($f)) ?>: </span><?= $h ?></span></li><?php endforeach; ?>
    </ul><?php endif; ?>
    <?php if ($map !== ''): ?><div class="df-contact__map"><?= $map ?></div><?php endif; ?>
  </div>

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
