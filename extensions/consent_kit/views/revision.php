<?php
/** Stand (Schnappschuss): was zur Auswahl stand, als Einwilligungen mit diesem Stand erteilt wurden. @var array $rev  @var array $domains */
$snap = $rev['snapshot'];
?>
<p><a class="adm-link" href="<?= e(url('/admin/consent/log')) ?>">← <?= e(__('Protokoll')) ?></a></p>
<section class="adm-card">
  <h2><?= e(__('Stand #{id}', ['id' => (int) $rev['id']])) ?></h2>
  <p class="adm-muted"><?= e(__('Angelegt am {date} für {domain} · Sprache {lang} · Epoche {epoch}', ['date' => date('d.m.Y H:i', strtotime((string) $rev['created_at'])),
      'domain' => $domains[$rev['host']] ?? $rev['host'], 'lang' => (string) ($snap['lang'] ?? ''), 'epoch' => (int) ($snap['epoch'] ?? 0)])) ?></p>
  <?php foreach ((array) ($snap['groups'] ?? []) as $g): ?>
  <h3><?= e((string) $g['name']) ?><?= !empty($g['required']) ? ' · ' . e(__('immer aktiv')) : '' ?></h3>
  <?php foreach ((array) $g['services'] as $s): ?>
  <div class="ck-snap">
    <p><strong><?= e((string) $s['name']) ?></strong> <code class="adm-muted"><?= e((string) $s['key']) ?></code><?= !empty($s['h']) ? ' · ' . e(__('Fingerabdruck')) . ' <code>' . e((string) $s['h']) . '</code>' : '' ?></p>
    <?php if (!empty($s['provider'])): ?><p class="adm-muted"><?= e((string) $s['provider']) ?></p><?php endif; ?>
    <?php if (!empty($s['items'])): ?>
    <table class="adm-table"><thead><tr><th scope="col"><?= e(__('Name')) ?></th><th scope="col"><?= e(__('Art')) ?></th><th scope="col"><?= e(__('Domain')) ?></th><th scope="col"><?= e(__('Laufzeit')) ?></th><th scope="col"><?= e(__('Zweck')) ?></th></tr></thead><tbody>
      <?php foreach ($s['items'] as $i): ?><tr><td><code><?= e((string) $i['name']) ?></code></td><td><?= e((string) ($i['typeLabel'] ?? $i['type'])) ?></td><td><?= e((string) ($i['host'] ?: __('eigene'))) ?></td><td><?= e((string) $i['duration']) ?></td><td><?= e((string) $i['purpose']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
  <?php endforeach; endforeach; ?>
</section>
