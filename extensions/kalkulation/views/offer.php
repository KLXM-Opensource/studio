<?php
/**
 * Angebotsansicht (Kundensicht) zum Drucken bzw. „Als PDF speichern“ im Druckdialog des Browsers – A4, ohne interne
 * Kosten, Stunden, Sätze oder Notizen. Eigenständige Seite (ohne Verwaltungs-Layout), gleiche Sicherheitsköpfe.
 * @var array $c  @var array $run  @var array $sender  @var array $settings
 */
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Num;

$cur = (string) $c['params']['currency'];
$m = fn(?float $v) => Num::money($v, $cur);
$T = $run['totals'];
$groups = ['once' => __('Einmalige Leistungen'), 'monthly' => __('Monatliche Leistungen')];
$by = ['once' => [], 'monthly' => []];
foreach ($c['positions'] as $p) $by[$p['kind']][] = $p;
$para = fn(string $txt) => $txt === '' ? '' : implode('', array_map(fn($b) => '<p>' . nl2br(e(trim($b))) . '</p>', preg_split("~\n\s*\n~", trim($txt))));
$validity = str_replace('{datum}', Kalkulation::date($c['valid_until']) ?: '–', $c['validity']);
$addr = array_values(array_filter(array_map('trim', explode("\n", $c['address']))));
$title = __('Angebot') . ' ' . $c['number'] . ($c['customer'] !== '' ? ' – ' . $c['customer'] : '');
$n = 0;
?><!doctype html>
<html lang="<?= e(\Core\I18n::locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(Kalkulation::asset('css/offer.css')) ?>">
<script src="<?= e(Kalkulation::asset('js/kalkulation.js')) ?>" defer></script>
</head>
<body class="kx-offer">
<div class="kx-offer__bar">
  <a href="<?= e(url('/admin/kalkulation/' . $c['id'])) ?>">← <?= e(__('Zurück zur Kalkulation')) ?></a>
  <button type="button" data-kx-print><?= e(__('Drucken / Als PDF speichern')) ?></button>
  <span><?= e(__('Tipp: Im Druckdialog „Als PDF speichern“ wählen und „Kopf- und Fußzeilen“ ausschalten.')) ?></span>
  <?php if ($c['status'] === 'draft'): ?><strong class="kx-offer__draft"><?= e(__('Status: Entwurf')) ?></strong><?php endif; ?>
</div>

<article class="kx-doc">
  <header class="kx-doc__head">
    <div class="kx-doc__brand">
      <?php if ($sender['logo']): ?><img src="<?= e($sender['logo']) ?>" alt="<?= e($sender['name']) ?>"><?php else: ?><strong><?= e($sender['name']) ?></strong><?php endif; ?>
    </div>
    <address class="kx-doc__from">
      <?php if ($sender['logo']): ?><strong><?= e($sender['name']) ?></strong><br><?php endif; ?>
      <?= implode('<br>', array_map('e', $sender['lines'])) ?>
    </address>
  </header>

  <div class="kx-doc__top">
    <address class="kx-doc__to">
      <span class="kx-doc__return"><?= e(implode(' · ', array_filter([$sender['name'], $sender['lines'][0] ?? '', $sender['lines'][1] ?? '']))) ?></span>
      <?php if ($c['customer'] !== ''): ?><strong><?= e($c['customer']) ?></strong><br><?php endif; ?>
      <?= implode('<br>', array_map('e', $addr)) ?>
    </address>
    <dl class="kx-doc__meta">
      <div><dt><?= e(__('Angebot Nr.')) ?></dt><dd><?= e($c['number']) ?></dd></div>
      <div><dt><?= e(__('Datum')) ?></dt><dd><?= e(Kalkulation::date($c['calc_date'])) ?></dd></div>
      <?php if ($c['valid_until'] !== ''): ?><div><dt><?= e(__('Gültig bis')) ?></dt><dd><?= e(Kalkulation::date($c['valid_until'])) ?></dd></div><?php endif; ?>
      <?php if ($c['project'] !== ''): ?><div><dt><?= e(__('Projekt')) ?></dt><dd><?= e($c['project']) ?></dd></div><?php endif; ?>
      <?php if ($c['contact'] !== ''): ?><div><dt><?= e(__('Ansprechpartner')) ?></dt><dd><?= e($c['contact']) ?></dd></div><?php endif; ?>
    </dl>
  </div>

  <h1><?= e(__('Angebot')) ?><?= $c['title'] !== '' ? ': ' . e($c['title']) : '' ?></h1>
  <?php if ($c['intro'] !== ''): ?>
  <div class="kx-doc__text">
    <p><?= e($c['contact'] !== '' ? __('Guten Tag {name},', ['name' => $c['contact']]) : __('Sehr geehrte Damen und Herren,')) ?></p>
    <?= $para($c['intro']) ?>
  </div>
  <?php endif; ?>

  <?php foreach ($groups as $k => $label): if (!$by[$k]) continue; $x = $T[$k]; ?>
  <table class="kx-doc__table">
    <caption><?= e($label) ?></caption>
    <thead><tr><th scope="col" class="c-pos"><?= e(__('Pos.')) ?></th><th scope="col"><?= e(__('Leistung')) ?></th><th scope="col" class="c-num"><?= e(__('Menge')) ?></th>
      <th scope="col" class="c-num"><?= e($k === 'monthly' ? __('Einzelpreis mtl.') : __('Einzelpreis')) ?></th><th scope="col" class="c-num"><?= e($k === 'monthly' ? __('Gesamt mtl.') : __('Gesamt')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($by[$k] as $p): $l = $run['lines'][$p['id']]; $n++; $flag = $p['alternative'] ? __('Alternative') : ($p['optional'] ? __('optional') : ''); ?>
      <tr class="<?= $l['counted'] ? '' : 'is-extra' ?>">
        <td class="c-pos"><?= $n ?></td>
        <td><?php if ($p['group'] !== ''): ?><span class="kx-doc__grp"><?= e($p['group']) ?></span><?php endif; ?>
          <strong><?= e($p['name'] !== '' ? $p['name'] : __('Position')) ?></strong><?= $flag !== '' ? ' <em>(' . e($flag) . ')</em>' : '' ?>
          <?php if ($p['desc'] !== ''): ?><div class="kx-doc__desc"><?= nl2br(e($p['desc'])) ?></div><?php endif; ?></td>
        <td class="c-num"><?= e(Num::input($p['qty'])) ?> <?= e($p['unit']) ?></td>
        <td class="c-num"><?= e($m($l['unit_price'])) ?><?= $p['discount'] ? '<br><small>' . e(__('abzügl. {v} %', ['v' => Num::input($p['discount'])])) . '</small>' : '' ?></td>
        <td class="c-num"><?= $l['counted'] ? e($m($l['total'])) : '(' . e($m($l['total'])) . ')' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <?php if ($x['discount']): ?>
      <tr><th colspan="4" scope="row"><?= e(__('Summe')) ?></th><td class="c-num"><?= e($m($x['sum'])) ?></td></tr>
      <tr><th colspan="4" scope="row"><?= e(__('Nachlass {v} %', ['v' => Num::input($x['discount_pct'])])) ?></th><td class="c-num">−<?= e($m($x['discount'])) ?></td></tr>
      <?php endif; ?>
      <tr class="is-net"><th colspan="4" scope="row"><?= e($k === 'monthly' ? __('Monatlich netto') : __('Summe netto')) ?></th><td class="c-num"><?= e($m($x['net'])) ?></td></tr>
      <tr><th colspan="4" scope="row"><?= e(__('zzgl. USt {v} %', ['v' => Num::input($T['vat_pct'])])) ?></th><td class="c-num"><?= e($m($x['vat'])) ?></td></tr>
      <tr class="is-gross"><th colspan="4" scope="row"><?= e($k === 'monthly' ? __('Monatlich brutto') : __('Summe brutto')) ?></th><td class="c-num"><?= e($m($x['gross'])) ?></td></tr>
      <?php if ($k === 'monthly'): ?><tr><th colspan="4" scope="row"><?= e(__('entspricht jährlich netto')) ?></th><td class="c-num"><?= e($m($T['yearly']['net'])) ?></td></tr><?php endif; ?>
    </tfoot>
  </table>
  <?php endforeach; ?>

  <?php if (array_filter($c['positions'], fn($p) => $p['optional'] || $p['alternative'])): ?>
  <p class="kx-doc__small"><?= e(__('Positionen in Klammern (optional bzw. Alternative) sind nicht in den Summen enthalten.')) ?></p>
  <?php endif; ?>

  <div class="kx-doc__text kx-doc__terms">
    <?php if ($c['term'] !== '' && $by['monthly']): ?><h2><?= e(__('Laufzeit')) ?></h2><?= $para($c['term']) ?><?php endif; ?>
    <?php if ($c['payment'] !== ''): ?><h2><?= e(__('Zahlungsbedingungen')) ?></h2><?= $para($c['payment']) ?><?php endif; ?>
    <?php if (trim($validity) !== ''): ?><?= $para($validity) ?><?php endif; ?>
    <?= $para($c['closing']) ?>
    <p class="kx-doc__sign"><?= e(__('Mit freundlichen Grüßen')) ?><br><?= e($sender['name']) ?></p>
  </div>

  <footer class="kx-doc__foot"><?= e(implode(' · ', array_merge([$sender['name']], $sender['lines']))) ?></footer>
</article>
</body>
</html>
