<?php
/**
 * Eine Zeile im Bereich „Bedingungen“ des Tabellen-Baukastens.
 * @var string $p  Namenspräfix, z. B. fields[3][visible_if][rules][0]
 * @var array $r  ['field', 'op', 'value'] bzw. ['op', 'field', 'message'] (Vergleich)
 * @var string $kind  cond | cmp
 * @var array $all  Felder der Tabelle [['name', 'label', 'type', 'options'], …]
 * @var string $self  Kurzname des eigenen Feldes
 */
use Core\Data\Rules;

$fieldSelect = function (array $types) use ($p, $r, $all, $self): string {
    $h = '<select name="' . $p . '[field]" data-rule-field aria-label="' . e(__('Feld')) . '"><option value="">' . e(__('– Feld wählen –')) . '</option>';
    foreach ($all as $f) {
        if ($f['name'] === $self || $f['name'] === '' || !in_array($f['type'], $types, true)) continue;
        $h .= '<option value="' . e($f['name']) . '"' . (($r['field'] ?? '') === $f['name'] ? ' selected' : '') . '>' . e($f['label'] ?: $f['name']) . '</option>';
    }
    return $h . '</select>';
};
?>
<?php if ($kind === 'cmp'): ?>
<li class="dt-rule dt-rule--cmp" data-rule>
  <span class="dt-rule__text"><?= e(__('Dieser Wert muss')) ?></span>
  <select name="<?= $p ?>[op]" aria-label="<?= e(__('Vergleich')) ?>">
    <?php foreach (['>' => __('größer / später sein als'), '>=' => __('gleich oder größer / später sein als'), '<' => __('kleiner / früher sein als'), '<=' => __('gleich oder kleiner / früher sein als'), '=' => __('gleich sein wie'), '!=' => __('anders sein als')] as $k => $l): ?>
    <option value="<?= e($k) ?>"<?= ($r['op'] ?? '>') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <?= $fieldSelect(Rules::COMPARE_TYPES) ?>
  <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-rule-remove aria-label="<?= e(__('Vergleich entfernen')) ?>">✕</button>
  <label class="dt-in dt-rule__msg"><span><?= e(__('Fehlermeldung (optional)')) ?></span><input name="<?= $p ?>[message]" value="<?= e($r['message'] ?? '') ?>" placeholder="<?= e(__('z. B. Ende muss nach Beginn liegen')) ?>"></label>
</li>
<?php else: $src = null; foreach ($all as $f) if ($f['name'] === ($r['field'] ?? '')) $src = $f;
  $op = $r['op'] ?? '='; $val = (string) ($r['value'] ?? ''); ?>
<li class="dt-rule" data-rule>
  <span class="dt-rule__text"><?= e(__('Feld')) ?></span>
  <?= $fieldSelect(Rules::SOURCE_TYPES) ?>
  <select name="<?= $p ?>[op]" data-rule-op aria-label="<?= e(__('Bedingung')) ?>">
    <?php foreach (['=' => __('ist gleich'), '!=' => __('ist nicht'), 'filled' => __('ist ausgefüllt'), 'empty' => __('ist leer'), 'contains' => __('enthält'), '>' => __('ist größer / später als'), '<' => __('ist kleiner / früher als')] as $k => $l): ?>
    <option value="<?= e($k) ?>"<?= $op === $k ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="dt-rule__val" data-rule-val<?= in_array($op, ['filled', 'empty'], true) ? ' hidden' : '' ?>>
    <?php if ($src && in_array($src['type'], ['select', 'multiselect', 'bool'], true)):
      $opts = $src['type'] === 'bool' ? ['1' => __('Ja'), '0' => __('Nein')] : (array) ($src['options'] ?? []); ?>
    <select name="<?= $p ?>[value]" aria-label="<?= e(__('Wert')) ?>">
      <?php foreach ($opts as $k => $l): ?><option value="<?= e((string) $k) ?>"<?= (string) $k === ($src['type'] === 'bool' && $val === '' ? '0' : $val) ? ' selected' : '' ?>><?= e((string) $l) ?></option><?php endforeach; ?>
    </select>
    <?php else: ?>
    <input name="<?= $p ?>[value]" value="<?= e($val) ?>" aria-label="<?= e(__('Wert')) ?>" placeholder="<?= e(__('Wert')) ?>">
    <?php endif; ?>
  </span>
  <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-rule-remove aria-label="<?= e(__('Bedingung entfernen')) ?>">✕</button>
</li>
<?php endif; ?>
