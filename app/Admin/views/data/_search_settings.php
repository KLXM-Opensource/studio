<?php
/**
 * Daten → Felder & Einstellungen → Bereich „Suche“ (Core\Search\TableSearch): Tabelle in der Website-Suche, Gewichtung je Feld,
 * Filter (Facetten), Anzeige im Treffer, nur künftige Termine, Ausschluss. Gespeichert werden nur Abweichungen (settings.search).
 * @var ?array $table  @var array $def
 */
use Core\Search\TableSearch;

if (!\Core\Features::on('search')) return;
$s = (array) ($def['settings']['search'] ?? []);
$probe = ['fields' => (array) ($def['fields'] ?? []), 'settings' => ($def['settings'] ?? []) + ['title_field' => '', 'route' => '', 'kind' => 'content', 'image_field' => '', 'description_field' => '']];
if (($probe['settings']['title_field'] ?? '') === '') {
    foreach ($probe['fields'] as $f) if (in_array($f['type'] ?? '', ['text', 'textarea'], true)) { $probe['settings']['title_field'] = $f['name']; break; }
}
$probe['settings']['search'] = $s;
$cfg = TableSearch::config($probe);
$eligible = array_values(array_filter($probe['fields'], fn($f) => TableSearch::eligible($f)));
$byType = fn(array $types) => array_column(array_filter($probe['fields'], fn($f) => in_array($f['type'] ?? '', $types, true)), 'label', 'name');
$select = function (string $name, string $label, array $opts, string $cur, string $auto) {
    $h = '<div class="f f--inline"><label for="ts-' . $name . '">' . e($label) . '</label><select id="ts-' . $name . '" name="settings[search][' . $name . ']"><option value="">' . e($auto) . '</option>';
    foreach ($opts as $k => $l) $h .= '<option value="' . e((string) $k) . '"' . ($cur === (string) $k ? ' selected' : '') . '>' . e((string) $l) . '</option>';
    return $h . '</select></div>';
};
$hasRoute = ($probe['settings']['route'] ?? '') !== '';
$en = array_key_exists('enabled', $s) ? ($s['enabled'] ? '1' : '0') : '';
?>
<section class="set-group" id="table-search" aria-labelledby="ts-h">
  <h3 class="set-group__title" id="ts-h"><?= e(__('Website-Suche')) ?></h3>
  <input type="hidden" name="settings[search][_]" value="1">
  <div class="set-list">
  <div class="f f--inline"><label for="ts-enabled"><?= e(__('In der Website-Suche')) ?></label>
    <p class="f-help"><?= e(__('Gefunden werden nur veröffentlichte Einträge mit Detailseite (Adresse unter „Website“).')) ?><?= !empty($table['shared']) ? ' ' . e(__('Geteilte Tabelle: gilt für alle beteiligten Websites; welche fremden Einträge eine Website zeigt (und findet), stellt jede Website selbst ein.')) : '' ?></p>
    <select id="ts-enabled" name="settings[search][enabled]">
      <option value=""<?= $en === '' ? ' selected' : '' ?>><?= e(__('Automatisch ({state})', ['state' => $hasRoute ? __('an – hat Detailseiten') : __('aus – keine Detailseiten')])) ?></option>
      <option value="1"<?= $en === '1' ? ' selected' : '' ?>><?= e(__('An')) ?></option>
      <option value="0"<?= $en === '0' ? ' selected' : '' ?>><?= e(__('Aus')) ?></option>
    </select></div>
  </div>
  <p class="set-group__note"><?= e(__('Nach dem Speichern wird der Suchindex automatisch aktualisiert.')) ?></p>
</section>

  <?php if ($eligible): ?>
  <details class="set-group"<?= !empty($s['fields']) || !empty($s['facets']) ? ' open' : '' ?>>
    <summary class="set-group__title"><?= e(__('Felder durchsuchen & gewichten')) ?></summary>
    <div class="set-list"><div class="set-scroll">
    <table class="adm-table px-table">
      <thead><tr><th><?= e(__('Feld')) ?></th><th><?= e(__('Gewichtung')) ?></th><th><?= e(__('Filter')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($eligible as $f): $def0 = TableSearch::defaultWeight($probe, $f); $cur = $cfg['fields'][$f['name']] ?? $def0; $facet = in_array($f['type'], TableSearch::FACET_TYPES, true); ?>
        <tr><td><label for="ts-w-<?= e($f['name']) ?>"><?= e($f['label']) ?></label><br><small class="adm-muted"><?= e(\Core\Data\Tables::TYPES[$f['type']][0] ?? $f['type']) ?></small></td>
          <td><select id="ts-w-<?= e($f['name']) ?>" name="settings[search][fields][<?= e($f['name']) ?>]">
            <?php foreach (TableSearch::WEIGHTS as $w => $wl): ?><option value="<?= e($w) ?>"<?= $cur === $w ? ' selected' : '' ?>><?= e(__($wl)) ?><?= $w === $def0 ? ' ' . e(__('(Standard)')) : '' ?></option><?php endforeach; ?>
          </select><?php if (in_array($f['type'], TableSearch::OPT_IN, true)): ?><br><small class="adm-muted"><?= e(__('Personenbezogen? Nur bewusst einschalten.')) ?></small><?php endif; ?></td>
          <td><?php if ($facet): ?><label class="f-check"><input type="checkbox" name="settings[search][facets][]" value="<?= e($f['name']) ?>"<?= in_array($f['name'], $cfg['facets'], true) ? ' checked' : '' ?>> <span class="adm-sr"><?= e(__('Als Filter anbieten')) ?></span></label><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div></div>
    <p class="set-group__note"><?= e(__('„Hoch“ zählt wie eine Überschrift. IBAN, Bilder und Dateien werden nie durchsucht; E-Mail, Telefon, Links und Zahlen nur, wenn Sie sie einschalten. Gruppen gehen ohne IBAN/E-Mail/Telefon ein. Filter: Auswahl- und Verknüpfungsfelder erscheinen auf der Ergebnisseite als Filter, sobald nach dieser Tabelle gefiltert wird.')) ?></p>
  </details>
  <?php endif; ?>

  <details class="set-group"<?= array_intersect_key($s, array_flip(['title', 'summary', 'image', 'date', 'label', 'labels'])) ? ' open' : '' ?>>
    <summary class="set-group__title"><?= e(__('Anzeige im Treffer')) ?></summary>
    <div class="set-list">
    <?= $select('title', __('Titel'), $byType(['text', 'textarea']), (string) ($s['title'] ?? ''), __('– Titelfeld der Tabelle –')) ?>
    <?= $select('summary', __('Kurztext'), $byType(['text', 'textarea', 'richtext']), (string) ($s['summary'] ?? ''), __('– Fundstelle mit markierten Treffern –')) ?>
    <?= $select('image', __('Bild'), $byType(['media']), (string) ($s['image'] ?? ''), __('– Bildfeld der Tabelle –')) ?>
    <?= $select('date', __('Datum'), $byType(['date', 'datetime']), (string) ($s['date'] ?? ''), __('– Termin bzw. erstes Datumsfeld –')) ?>
    <div class="f f--inline"><label for="ts-label"><?= e(__('Art (Beschriftung im Treffer)')) ?></label>
      <input id="ts-label" name="settings[search][label]" value="<?= e((string) ($s['label'] ?? '')) ?>" maxlength="40" placeholder="<?= e((string) (($def['singular'] ?? '') ?: ($def['name'] ?? ''))) ?>"></div>
    <?php foreach (\Core\Lang::all() as $code => $lname): if ($code === \Core\Lang::default()) continue; ?>
    <div class="f f--inline"><label for="ts-label-<?= e($code) ?>"><?= e(__('Art ({lang})', ['lang' => $lname])) ?></label>
      <input id="ts-label-<?= e($code) ?>" name="settings[search][labels][<?= e($code) ?>]" value="<?= e((string) ($s['labels'][$code] ?? '')) ?>" maxlength="40"></div>
    <?php endforeach; ?>
    </div>
  </details>

  <details class="set-group"<?= !empty($s['future']) || !empty($s['exclude']) ? ' open' : '' ?>>
    <summary class="set-group__title"><?= e(__('Welche Einträge?')) ?></summary>
    <div class="set-list">
    <?php if (TableSearch::datable($probe)): ?>
    <div class="f f--bool"><input type="hidden" name="settings[search][future]" value="0">
    <label class="f-check"><input type="checkbox" name="settings[search][future]" value="1"<?= $cfg['future'] ? ' checked' : '' ?>> <span><?= e(__('Nur künftige Termine (vergangene nicht finden)')) ?></span></label></div>
    <?php endif; ?>
      <div class="f f--inline"><label for="ts-xf"><?= e(__('Nicht finden, wenn Feld …')) ?></label>
        <select id="ts-xf" name="settings[search][exclude][field]"><option value=""><?= e(__('– keine Bedingung –')) ?></option>
        <?php foreach ($byType(['text', 'select', 'multiselect', 'bool']) as $k => $l): ?><option value="<?= e($k) ?>"<?= ($cfg['exclude']['field'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f f--inline"><label for="ts-xv"><?= e(__('… diesen Wert hat')) ?></label>
        <input id="ts-xv" name="settings[search][exclude][value]" value="<?= e((string) ($cfg['exclude']['value'] ?? '')) ?>" maxlength="120" placeholder="<?= e(__('z. B. intern')) ?>"></div>
    </div>
  </details>
