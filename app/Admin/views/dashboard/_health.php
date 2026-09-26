<?php
/** Übersicht · „Technik & Betrieb“ (nur Administration) – nachgeladen. @var array $data [[Bezeichnung, true|false|null], …] */
$data = (array) $data;
$bad = array_values(array_filter($data, fn($r) => $r[1] === false));
$warn = array_values(array_filter($data, fn($r) => $r[1] === null));
$ok = count($data) - count($bad) - count($warn);
?>
<p class="dash-big"><?php if (!$bad && !$warn): ?><span class="dash-state is-ok"><?= icon('check-circle') ?> <?= e(__('Alles in Ordnung')) ?></span><?php else: ?>
  <span class="dash-state <?= $bad ? 'is-err' : 'is-warn' ?>"><?= icon($bad ? 'prohibit' : 'warning') ?> <?= e($bad ? __('{n} Fehler', ['n' => count($bad)]) : (count($warn) === 1 ? __('1 Hinweis') : __('{n} Hinweise', ['n' => count($warn)]))) ?></span><?php endif; ?>
  <span class="adm-muted"><?= e(__('{n} von {m} Prüfungen bestanden', ['n' => $ok, 'm' => count($data)])) ?></span></p>
<ul class="dash-checks" role="list">
  <?php foreach ([...$bad, ...$warn] as [$label, $v]): ?>
  <li class="<?= $v === false ? 'is-err' : 'is-warn' ?>"><?= icon($v === false ? 'prohibit' : 'warning') ?><span><span class="sr-only"><?= e($v === false ? __('Fehler:') : __('Hinweis:')) ?> </span><?= e($label) ?></span></li>
  <?php endforeach; ?>
</ul>
<details class="dash-table"><summary><?= e(__('Alle Prüfungen')) ?></summary>
  <ul class="dash-checks" role="list"><?php foreach ($data as [$label, $v]): ?><li class="<?= $v === true ? 'is-ok' : ($v === false ? 'is-err' : 'is-warn') ?>"><?= icon($v === true ? 'check-circle' : ($v === false ? 'prohibit' : 'warning')) ?><span><?= e($label) ?></span></li><?php endforeach; ?></ul>
</details>
<p class="dash-links"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a> · <span class="adm-muted"><?= e(__('Vollständig: php bin/console health')) ?></span></p>
