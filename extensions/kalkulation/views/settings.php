<?php
/**
 * Einstellungen: Stundensätze (benannt, frei ergänzbar), Aufschläge, USt, Währung, Rundung, Nummern, Absender, Standardtexte,
 * Verschlüsselung und Protokoll. @var array $s  @var array $errors  @var ?array $raw  @var list<array> $log  @var bool $appKey
 */
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Num;

$raw ??= null;
$base = url('/admin/kalkulation/einstellungen');
$v = fn(string $k) => $raw !== null ? (string) ($raw[$k] ?? '') : (is_float($s[$k] ?? null) || is_int($s[$k] ?? null) ? Num::input((float) $s[$k]) : (string) ($s[$k] ?? ''));
$err = fn(string $k) => isset($errors[$k]) ? '<span class="f-error" id="e-' . e(str_replace('.', '-', $k)) . '">' . e($errors[$k]) . '</span>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="e-' . e(str_replace('.', '-', $k)) . '"' : '';
$rates = $raw !== null ? array_values(array_filter((array) ($raw['rates'] ?? []), 'is_array')) : $s['rates'];
$rateRow = function (array $r, string $n) use ($raw, $err, $inv): string {
    $rate = $raw !== null ? (string) ($r['rate'] ?? '') : Num::input($r['rate'] ?? null);
    return '<tr data-kx-item><td><input type="hidden" name="rates[' . $n . '][key]" value="' . e((string) ($r['key'] ?? '')) . '">'
        . '<input name="rates[' . $n . '][label]" value="' . e((string) ($r['label'] ?? '')) . '" maxlength="80" aria-label="' . e(__('Bezeichnung')) . '"' . $inv("rates.$n.label") . '>' . $err("rates.$n.label") . '</td>'
        . '<td><input class="kx-in--num" name="rates[' . $n . '][rate]" value="' . e($rate) . '" inputmode="decimal" data-num aria-label="' . e(__('Satz je Stunde netto')) . '"' . $inv("rates.$n.rate") . '>' . $err("rates.$n.rate") . '</td>'
        . '<td><code class="adm-muted">' . e((string) ($r['key'] ?? '')) . '</code></td>'
        . '<td><label class="f-check"><input type="checkbox" name="rates[' . $n . '][delete]" value="1"> ' . e(__('entfernen')) . '</label></td></tr>';
};
$keys = array_filter(array_map(fn($r) => (string) ($r['key'] ?? ''), $s['rates']));
$logLabels = ['create' => __('Kalkulation angelegt'), 'save' => __('Kalkulation gespeichert'), 'status' => __('Status geändert'), 'version' => __('Stand gesichert'),
    'restore' => __('Stand wiederhergestellt'), 'duplicate' => __('Kopie angelegt'), 'delete' => __('Kalkulation gelöscht'), 'export' => __('exportiert'),
    'settings' => __('Einstellungen geändert'), 'catalog' => __('Katalog geändert'), 'encrypt-on' => __('Verschlüsselung eingeschaltet'), 'encrypt-off' => __('Verschlüsselung ausgeschaltet')];
?>
<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte die markierten Felder prüfen – nichts gespeichert.')) ?></div><?php endif; ?>
<form method="post" action="<?= e($base) ?>" data-kx-checknum novalidate>
  <?= csrf_field() ?>
  <section class="adm-card" aria-labelledby="kx-rates-h">
    <h2 id="kx-rates-h"><?= e(__('Stundensätze')) ?></h2>
    <p class="adm-muted kx-mb"><?= e(__('Netto je Stunde. Bezeichnungen lassen sich ändern; der Schlüssel bleibt, damit Katalog und Kalkulationen passen.')) ?></p>
    <?= $err('rates') ?>
    <table class="kx-ratesedit">
      <caption class="sr-only"><?= e(__('Stundensätze')) ?></caption>
      <colgroup><col><col class="kx-w-rate"><col class="kx-w-key"><col class="kx-w-del"></colgroup>
      <thead><tr><th scope="col"><?= e(__('Bezeichnung')) ?></th><th scope="col"><?= e(__('Satz je Stunde netto')) ?></th><th scope="col"><?= e(__('Schlüssel')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Entfernen')) ?></span></th></tr></thead>
      <tbody id="kx-rate-rows"><?php foreach ($rates as $i => $r) echo $rateRow($r, (string) $i); ?></tbody>
    </table>
    <p><button type="button" class="adm-btn adm-btn--small" data-kx-add-row="kx-rate-tpl" data-kx-target="#kx-rate-rows"><?= icon('plus') ?><?= e(__('Stundensatz hinzufügen')) ?></button></p>
    <div class="kx-set-grid">
      <div class="f"><label for="kx-ro"><?= e(__('Satz für Einrichtungsstunden')) ?></label><select id="kx-ro" name="rate_once"><?php foreach ($s['rates'] as $r): ?><option value="<?= e($r['key']) ?>"<?= $s['rate_once'] === $r['key'] ? ' selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="kx-rm"><?= e(__('Satz für Betreuung (monatlich)')) ?></label><select id="kx-rm" name="rate_monthly"><?php foreach ($s['rates'] as $r): ?><option value="<?= e($r['key']) ?>"<?= $s['rate_monthly'] === $r['key'] ? ' selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
    </div>
  </section>

  <section class="adm-card" aria-labelledby="kx-calc-h">
    <h2 id="kx-calc-h"><?= e(__('Aufschläge, Steuer, Rundung')) ?></h2>
    <div class="kx-set-grid">
      <div class="f<?= isset($errors['markup']) ? ' f--error' : '' ?>"><label for="kx-markup"><?= e(__('Aufschlag Fremdkosten %')) ?></label><input id="kx-markup" name="markup" value="<?= e($v('markup')) ?>" inputmode="decimal" data-num<?= $inv('markup') ?>><?= $err('markup') ?>
        <span class="f-help"><?= e(__('auf Infrastruktur, Lizenzen und Fremdleistungen')) ?></span></div>
      <div class="f<?= isset($errors['buffer']) ? ' f--error' : '' ?>"><label for="kx-buffer"><?= e(__('Projektpuffer %')) ?></label><input id="kx-buffer" name="buffer" value="<?= e($v('buffer')) ?>" inputmode="decimal" data-num<?= $inv('buffer') ?>><?= $err('buffer') ?>
        <span class="f-help"><?= e(__('auf die Arbeit von Positionen mit „Puffer“ (Projekte)')) ?></span></div>
      <div class="f<?= isset($errors['vat']) ? ' f--error' : '' ?>"><label for="kx-vat"><?= e(__('Umsatzsteuer %')) ?></label><input id="kx-vat" name="vat" value="<?= e($v('vat')) ?>" inputmode="decimal" data-num<?= $inv('vat') ?>><?= $err('vat') ?></div>
      <div class="f"><label for="kx-cur"><?= e(__('Währung')) ?></label><select id="kx-cur" name="currency"><?php foreach (Kalkulation::currencies() as $k => $l): ?><option value="<?= e($k) ?>"<?= $s['currency'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="kx-round"><?= e(__('Einzelpreise runden')) ?></label><select id="kx-round" name="round_step">
        <?php foreach (['0' => __('nicht (auf Cent)'), '0.1' => __('auf 10 Cent'), '1' => __('auf 1'), '5' => __('auf 5'), '10' => __('auf 10'), '50' => __('auf 50')] as $k => $l): ?><option value="<?= e($k) ?>"<?= (float) $s['round_step'] === (float) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="kx-rmode"><?= e(__('Rundungsart')) ?></label><select id="kx-rmode" name="round_mode"><option value="nearest"<?= $s['round_mode'] !== 'up' ? ' selected' : '' ?>><?= e(__('kaufmännisch')) ?></option><option value="up"<?= $s['round_mode'] === 'up' ? ' selected' : '' ?>><?= e(__('immer aufrunden')) ?></option></select></div>
      <div class="f<?= isset($errors['cost_rate']) ? ' f--error' : '' ?>"><label for="kx-costrate"><?= e(__('Selbstkosten je Stunde')) ?></label><input id="kx-costrate" name="cost_rate" value="<?= e($v('cost_rate')) ?>" inputmode="decimal" data-num<?= $inv('cost_rate') ?>><?= $err('cost_rate') ?>
        <span class="f-help"><?= e(__('optional, intern: für die Marge nach Personalkosten; leer = Marge nur nach Fremdkosten')) ?></span></div>
    </div>
  </section>

  <section class="adm-card" aria-labelledby="kx-offer-h">
    <h2 id="kx-offer-h"><?= e(__('Angebote')) ?></h2>
    <div class="kx-set-grid">
      <div class="f"><label for="kx-prefix"><?= e(__('Nummernkreis (Präfix)')) ?></label><input id="kx-prefix" name="number_prefix" value="<?= e($v('number_prefix')) ?>" maxlength="30">
        <span class="f-help"><?= e(__('{Y} = Jahr, danach laufende Nummer: A-2026-001')) ?></span></div>
      <div class="f"><label for="kx-days"><?= e(__('Gültigkeit in Tagen')) ?></label><input id="kx-days" name="validity_days" type="number" min="0" max="365" value="<?= e($v('validity_days')) ?>"></div>
    </div>
    <div class="adm-fields">
      <div class="f f--half"><label for="kx-sender"><?= e(__('Absender im Angebotskopf')) ?></label><textarea id="kx-sender" name="sender" data-kia-off rows="4" maxlength="600" placeholder="<?= e(site_name()) ?>"><?= e($v('sender')) ?></textarea>
        <span class="f-help"><?= e(__('Erste Zeile = Name, danach Anschrift/Kontakt. Leer = Name, Anschrift, Telefon, E-Mail und Logo aus den zentralen Angaben der Website.')) ?></span></div>
      <div class="f f--half"><label for="kx-intro"><?= e(__('Einleitung')) ?></label><textarea id="kx-intro" name="text_intro" rows="4"><?= e($v('text_intro')) ?></textarea></div>
      <div class="f f--half"><label for="kx-pay"><?= e(__('Zahlungsbedingungen')) ?></label><textarea id="kx-pay" name="text_payment" rows="3"><?= e($v('text_payment')) ?></textarea></div>
      <div class="f f--half"><label for="kx-valid"><?= e(__('Gültigkeit')) ?></label><textarea id="kx-valid" name="text_validity" rows="3"><?= e($v('text_validity')) ?></textarea>
        <span class="f-help"><?= e(__('{datum} wird durch „Gültig bis“ ersetzt.')) ?></span></div>
      <div class="f f--half"><label for="kx-term"><?= e(__('Laufzeit und Kündigung (monatliche Leistungen)')) ?></label><textarea id="kx-term" name="text_term" rows="3" placeholder="<?= e(__('z. B. Mindestlaufzeit 12 Monate, danach monatlich kündbar')) ?>"><?= e($v('text_term')) ?></textarea></div>
      <div class="f f--half"><label for="kx-closing"><?= e(__('Schluss')) ?></label><textarea id="kx-closing" name="text_closing" rows="3"><?= e($v('text_closing')) ?></textarea></div>
    </div>
  </section>

  <div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Einstellungen speichern')) ?></button>
    <span class="adm-muted"><?= e(__('Gilt für neue Kalkulationen; bestehende behalten ihre Grundlage.')) ?></span></div>
</form>
<template id="kx-rate-tpl"><?= $rateRow(['key' => '', 'label' => '', 'rate' => null], '__N__') ?></template>

<section class="adm-card" id="kx-crypt" aria-labelledby="kx-crypt-h">
  <h2 id="kx-crypt-h"><?= e(__('Verschlüsselung und Datenschutz')) ?></h2>
  <div class="kx-crypt">
    <p class="adm-muted"><?= e(__('Kalkulationen liegen in der Datenbank dieser Website und sind nur mit dem Recht „Kalkulationen verwalten“ sichtbar – nicht über REST-API, MCP, Suche, Content-Sync oder den Seiten-Cache. Optional werden Inhalte (Kunden, Positionen, Beträge, Katalog, Einstellungen) zusätzlich verschlüsselt gespeichert (libsodium, Schlüssel aus app_key der Konfiguration). Das schützt Datenbank-Kopien und Sicherungen ohne Konfiguration; wer Server und Konfiguration hat, kann weiterhin lesen. Geht der app_key verloren, sind die Inhalte nicht mehr lesbar.')) ?></p>
    <?php if (!empty($s['encrypt'])): ?>
      <form method="post" action="<?= e($base . '/verschluesselung') ?>" data-kx-confirm="<?= e(__('Verschlüsselung ausschalten? Die Inhalte werden im Klartext neu geschrieben.')) ?>"><?= csrf_field() ?><input type="hidden" name="on" value="0">
        <p><span class="adm-badge"><?= e(__('verschlüsselt')) ?></span></p><button class="adm-btn" type="submit"><?= e(__('Verschlüsselung ausschalten')) ?></button></form>
    <?php elseif ($appKey): ?>
      <form method="post" action="<?= e($base . '/verschluesselung') ?>" data-kx-confirm="<?= e(__('Inhalte ab jetzt verschlüsselt speichern? Bitte sicherstellen, dass der app_key der Konfiguration gesichert ist.')) ?>"><?= csrf_field() ?><input type="hidden" name="on" value="1">
        <p><span class="adm-badge adm-badge--muted"><?= e(__('nicht verschlüsselt')) ?></span></p><button class="adm-btn" type="submit"><?= e(__('Verschlüsselt speichern')) ?></button></form>
    <?php else: ?>
      <p class="adm-badge adm-badge--adm-warn"><?= e(__('Kein app_key in der Konfiguration – Verschlüsselung nicht möglich.')) ?></p>
    <?php endif; ?>
  </div>
</section>

<details class="adm-card kx-fold">
  <summary><h2><?= e(__('Protokoll')) ?></h2><span class="adm-muted"><?= e(__('wer hat wann was geändert (letzte 40)')) ?></span></summary>
  <?php if ($log): ?>
  <table class="adm-table">
    <caption class="sr-only"><?= e(__('Protokoll')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Zeit')) ?></th><th scope="col"><?= e(__('Wer')) ?></th><th scope="col"><?= e(__('Was')) ?></th><th scope="col"><?= e(__('Kalkulation')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr><td class="kx-nowrap"><?= e(Kalkulation::dateTime($l['at'])) ?></td><td><?= e(Kalkulation::userName($l['user_id'] !== null ? (int) $l['user_id'] : null)) ?></td>
        <td><?= e($logLabels[$l['action']] ?? $l['action']) ?><?= $l['info'] ? ' <span class="adm-muted">(' . e((string) $l['info']) . ')</span>' : '' ?></td>
        <td><?php if ($l['calc_id'] && $l['action'] !== 'delete'): ?><a href="<?= e(url('/admin/kalkulation/' . (int) $l['calc_id'])) ?>">#<?= (int) $l['calc_id'] ?></a><?php elseif ($l['calc_id']): ?>#<?= (int) $l['calc_id'] ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="adm-muted"><?= e(__('Noch keine Einträge.')) ?></p><?php endif; ?>
</details>
