<?php
/** Übersicht · „Mitteilungen“ (Core\Push\Stats::card, nur Zähler) – nachgeladen. @var array $data */
use Core\Dashboard\Dashboard;

$d = (array) $data;
$days = (array) ($d['days'] ?? []);
$in = array_sum(array_column($days, 'sub'));
$out = array_sum(array_column($days, 'lost'));
$rows = array_map(fn($k, $v) => [date('d.m.', (int) strtotime($k)) . ' · +' . $v['sub'] . ' / −' . $v['lost'], (int) $v['sub']], array_keys($days), $days);
?>
<p class="dash-big"><strong><?= (int) ($d['visitors'] ?? 0) ?></strong> <?= e(__('Abos von Besuchern')) ?> <span class="adm-muted">· <?= e(__('+{in} / −{out} in 14 Tagen', ['in' => $in, 'out' => $out])) ?></span></p>
<?php if ($in > 0): ?>
<figure class="dash-chart dash-chart--small">
  <?= Dashboard::bars($rows, __('Neue Abos je Tag, letzte 14 Tage: insgesamt {n}', ['n' => $in])) ?>
  <figcaption class="dash-axis" aria-hidden="true"><span><?= e(date('d.m.', (int) strtotime((string) array_key_first($days)))) ?></span><span><?= e(__('heute')) ?></span></figcaption>
</figure>
<?php endif; ?>
<?php if (!empty($d['last'])): ?><p class="adm-muted"><?= e(__('Zuletzt: „{title}“ – {when}, {sent} von {n} zugestellt', ['title' => (string) $d['last']['title'], 'when' => Dashboard::ago((string) $d['last']['created_at']), 'sent' => (int) $d['last']['sent'], 'n' => (int) $d['last']['recipients']])) ?></p><?php endif; ?>
<?php if (!empty($d['scheduled'])): ?><p class="adm-muted"><?= e(__('{n} geplant', ['n' => (int) $d['scheduled']])) ?></p><?php endif; ?>
<p class="dash-links"><a href="<?= e(url('/admin/mitteilungen/statistik')) ?>"><?= e(__('Statistik')) ?></a><?php if (can('push.send')): ?> · <a href="<?= e(url('/admin/mitteilungen/neu')) ?>"><?= e(__('Neue Mitteilung')) ?></a><?php endif; ?></p>
