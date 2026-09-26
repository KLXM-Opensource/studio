<?php
/** Übersicht · „Gesucht, nicht gefunden“: anonyme Suchbegriffe ohne Treffer (Core\Search\Stats) – nachgeladen. @var ?array $data */
$data = (array) $data;
?>
<?php if ($data): ?>
<p class="adm-muted dash-note"><?= e(__('Besucher haben danach gesucht, aber nichts gefunden – ein Hinweis auf fehlende Inhalte oder Synonyme.')) ?></p>
<table class="adm-table dash-mini">
  <thead><tr><th scope="col"><?= e(__('Suchbegriff')) ?></th><th scope="col"><?= e(__('Sprache')) ?></th><th scope="col"><?= e(__('Anzahl')) ?></th></tr></thead>
  <tbody><?php foreach ($data as $m): ?><tr><td>„<?= e($m['term']) ?>“</td><td><?= e(strtoupper((string) $m['lang'])) ?></td><td><?= (int) $m['n'] ?></td></tr><?php endforeach; ?></tbody>
</table>
<?php else: ?>
<p class="adm-muted"><?= e(__('Bisher wurde alles gefunden, wonach gesucht wurde.')) ?></p>
<?php endif; ?>
<?php if (can('system.manage')): ?><p class="dash-links"><a href="<?= e(url('/admin/system#suche')) ?>"><?= e(__('Suche & Synonyme einstellen')) ?></a></p><?php endif; ?>
