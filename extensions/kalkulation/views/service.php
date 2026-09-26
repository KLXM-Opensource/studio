<?php
/**
 * Leistung bearbeiten: Name, Beschreibung, Textbaustein, Kostentreiber und Pakete (Einmalig- und Monatsteil).
 * Bei Fehlern kommen die Eingaben aus $raw zurück (ungültige Zahlen bleiben sichtbar).
 * @var array $s  @var array $rates  @var array $errors  @var ?array $raw
 */
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Num;

$raw ??= null;
$base = url('/admin/kalkulation/katalog');
$items = $raw !== null ? array_values(array_filter((array) ($raw['items'] ?? []), 'is_array')) : $s['items'];
$val = fn(array $it, string $k) => $raw !== null ? (string) ($it[$k] ?? '') : (is_float($it[$k] ?? null) || is_int($it[$k] ?? null) ? Num::input((float) $it[$k]) : (string) ($it[$k] ?? ''));
$err = fn(string $k) => isset($errors[$k]) ? '<span class="f-error" id="e-' . e(str_replace('.', '-', $k)) . '">' . e($errors[$k]) . '</span>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="e-' . e(str_replace('.', '-', $k)) . '"' : '';
$basisSel = function (string $name, string $cur, string $id) use ($rates): string {
    $o = '<option value=""' . ($cur === '' ? ' selected' : '') . '>' . e(__('Standard')) . '</option>';
    foreach ($rates as $k => $r) $o .= '<option value="' . e($k) . '"' . ($cur === $k ? ' selected' : '') . '>' . e($r['label']) . ' (' . e(__('Std.')) . ')</option>';
    $o .= '<option value="fixed"' . ($cur === 'fixed' ? ' selected' : '') . '>' . e(__('Festpreis')) . '</option>';
    return '<select id="' . $id . '" name="' . $name . '">' . $o . '</select>';
};
$units = Kalkulation::units();
$item = function (array $it, string $n) use ($val, $err, $inv, $basisSel): string {
    ob_start(); ?>
  <fieldset class="kx-item" data-kx-item>
    <legend class="sr-only"><?= e(__('Paket')) ?> <?= e((string) ($it['name'] ?? '')) ?></legend>
    <input type="hidden" name="items[<?= $n ?>][id]" value="<?= e((string) ($it['id'] ?? '')) ?>">
    <div class="kx-item__head">
      <div class="f<?= $err("items.$n.name") ? ' f--error' : '' ?>"><label for="it-<?= $n ?>-name"><?= e(__('Paket')) ?></label><input id="it-<?= $n ?>-name" name="items[<?= $n ?>][name]" value="<?= e($val($it, 'name')) ?>" maxlength="200"<?= $inv("items.$n.name") ?>><?= $err("items.$n.name") ?></div>
      <div class="f"><label for="it-<?= $n ?>-bill"><?= e(__('Abrechnung')) ?></label>
        <select id="it-<?= $n ?>-bill" name="items[<?= $n ?>][bill]" data-kx-bill>
          <?php foreach (['both' => __('Einrichtung + monatlich'), 'once' => __('einmalig'), 'monthly' => __('monatlich')] as $k => $l): ?><option value="<?= $k ?>"<?= ($it['bill'] ?? 'once') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
      <div class="f"><label for="it-<?= $n ?>-unit"><?= e(__('Einheit')) ?></label><input id="it-<?= $n ?>-unit" name="items[<?= $n ?>][unit]" value="<?= e($val($it, 'unit')) ?>" list="kx-units" maxlength="40"></div>
      <div class="adm-row kx-item__move">
        <button type="button" class="icon-btn" data-kx-move="up" aria-label="<?= e(__('Paket nach oben')) ?>">↑</button>
        <button type="button" class="icon-btn" data-kx-move="down" aria-label="<?= e(__('Paket nach unten')) ?>">↓</button>
      </div>
    </div>
    <div class="f"><label for="it-<?= $n ?>-desc"><?= e(__('Enthalten (Beschreibung im Angebot)')) ?></label><textarea id="it-<?= $n ?>-desc" name="items[<?= $n ?>][desc]" rows="2" maxlength="2000"><?= e($val($it, 'desc')) ?></textarea></div>
    <div class="kx-item__parts">
      <?php foreach (['once' => __('Einmalig (Einrichtung / Projekt)'), 'monthly' => __('Monatlich (Betrieb)')] as $k => $legend): ?>
      <fieldset class="kx-part" data-kx-part="<?= $k ?>">
        <legend><?= e($legend) ?></legend>
        <div class="kx-part__grid">
          <div class="f"><label for="it-<?= $n ?>-<?= $k ?>b"><?= e(__('Preisbasis')) ?></label><?= $basisSel("items[$n][{$k}_basis]", (string) ($it[$k . '_basis'] ?? ''), "it-$n-{$k}b") ?></div>
          <div class="f<?= $err("items.$n.{$k}_amount") ? ' f--error' : '' ?>"><label for="it-<?= $n ?>-<?= $k ?>a"><?= e(__('Std. bzw. Preis')) ?></label><input id="it-<?= $n ?>-<?= $k ?>a" name="items[<?= $n ?>][<?= $k ?>_amount]" value="<?= e($val($it, $k . '_amount')) ?>" inputmode="decimal" data-num class="kx-in--num"<?= $inv("items.$n.{$k}_amount") ?>><?= $err("items.$n.{$k}_amount") ?></div>
          <div class="f<?= $err("items.$n.{$k}_cost") ? ' f--error' : '' ?>"><label for="it-<?= $n ?>-<?= $k ?>c"><?= e(__('Fremdkosten')) ?></label><input id="it-<?= $n ?>-<?= $k ?>c" name="items[<?= $n ?>][<?= $k ?>_cost]" value="<?= e($val($it, $k . '_cost')) ?>" inputmode="decimal" data-num class="kx-in--num"<?= $inv("items.$n.{$k}_cost") ?>><?= $err("items.$n.{$k}_cost") ?></div>
        </div>
      </fieldset>
      <?php endforeach; ?>
    </div>
    <div class="kx-item__foot">
      <label class="f-check"><input type="checkbox" name="items[<?= $n ?>][buffer]" value="1"<?= !empty($it['buffer']) ? ' checked' : '' ?>> <?= e(__('Projektpuffer auf die Stunden')) ?></label>
      <label class="f-check"><input type="checkbox" name="items[<?= $n ?>][example]" value="1"<?= !empty($it['example']) ? ' checked' : '' ?>> <?= e(__('Beispiel (noch nicht geprüft)')) ?></label>
      <span class="kx-bar__sp"></span>
      <label class="f-check adm-btn--danger-text"><input type="checkbox" name="items[<?= $n ?>][delete]" value="1"> <?= e(__('Paket entfernen')) ?></label>
    </div>
  </fieldset>
<?php return (string) ob_get_clean();
};
?>
<p class="adm-eyebrow kx-back"><a href="<?= e($base) ?>">← <?= e(__('Leistungskatalog')) ?></a></p>
<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte die markierten Felder prüfen – nichts gespeichert.')) ?></div><?php endif; ?>

<form method="post" action="<?= e($base . '/' . $s['id']) ?>" data-kx-checknum novalidate>
  <?= csrf_field() ?>
  <section class="adm-card">
    <div class="adm-fields">
      <div class="f f--half<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="kx-name"><?= e(__('Leistung')) ?></label><input id="kx-name" name="name" value="<?= e($s['name']) ?>" maxlength="120" required<?= $inv('name') ?>><?= $err('name') ?></div>
      <div class="f f--half"><label for="kx-desc"><?= e(__('Kurzbeschreibung (intern)')) ?></label><input id="kx-desc" name="desc" value="<?= e($s['desc']) ?>" maxlength="2000"></div>
      <div class="f f--half"><label for="kx-text"><?= e(__('Textbaustein für Angebote')) ?></label><textarea id="kx-text" name="text" rows="3" maxlength="4000"><?= e($s['text']) ?></textarea>
        <span class="f-help"><?= e(__('Im Editor unter „Texte“ an die Einleitung anhängbar.')) ?></span></div>
      <div class="f f--half"><label for="kx-notes"><?= e(__('Kostentreiber & Hinweise (intern)')) ?></label><textarea id="kx-notes" name="notes" data-kia-off rows="3" maxlength="4000"><?= e($s['notes']) ?></textarea></div>
    </div>
  </section>

  <section class="adm-card" aria-labelledby="kx-items-h">
    <h2 id="kx-items-h"><?= e(__('Pakete')) ?></h2>
    <p class="adm-muted kx-mb"><?= e(__('Einmalig = Einrichtung bzw. Projektbaustein; monatlich = Betrieb, Lizenzen, Betreuung. Stunden werden mit dem gewählten Stundensatz berechnet (Standard: Einrichtung und Betreuung aus den Einstellungen), Fremdkosten mit dem Aufschlag. Leere Zahlen bleiben leer.')) ?></p>
    <div class="kx-items" id="kx-items">
      <?php foreach ($items as $i => $it) echo $item($it, (string) $i); ?>
    </div>
    <button type="button" class="adm-btn" data-kx-add-row="kx-item-tpl" data-kx-target="#kx-items"><?= icon('plus') ?><?= e(__('Paket hinzufügen')) ?></button>
  </section>

  <div class="adm-savebar">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e($base) ?>"><?= e(__('Abbrechen')) ?></a>
  </div>
</form>
<template id="kx-item-tpl"><?= $item(['bill' => 'once', 'unit' => __('pauschal')], '__N__') ?></template>
<datalist id="kx-units"><?php foreach ($units as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist>

<details class="adm-card adm-card--danger kx-fold">
  <summary><h2><?= e(__('Leistung entfernen')) ?></h2></summary>
  <form method="post" action="<?= e($base . '/' . $s['id'] . '/loeschen') ?>" data-kx-confirm="<?= e(__('„{name}“ mit allen Paketen aus dem Katalog entfernen?', ['name' => $s['name']])) ?>"><?= csrf_field() ?>
    <p><?= e(__('Entfernt die Leistung aus dem Katalog. Bestehende Kalkulationen behalten ihre Positionen.')) ?></p>
    <button class="adm-btn adm-btn--danger" type="submit"><?= e(__('Aus dem Katalog entfernen')) ?></button>
  </form>
</details>
