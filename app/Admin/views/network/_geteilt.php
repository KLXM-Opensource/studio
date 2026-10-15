<?php /** Netzwerk → Geteilte Ressourcen (Teil von network/index.php, Variablen von dort) */ ?>
  <section class="adm-card" id="geteilt" aria-labelledby="net-shared-h">
    <h2 id="net-shared-h"><?= e(__('Geteilte Ressourcen')) ?></h2>
    <?php if (!$shared['pools'] && !$shared['tables']): ?><p class="adm-muted"><?= e(__('Keine geteilten Medien oder Daten.')) ?></p><?php endif; ?>
    <?php if ($shared['pools']): ?>
    <h3 class="net-h3"><?= e(__('Geteilte Medien')) ?></h3>
    <ul class="adm-list net-list"><?php foreach ($shared['pools'] as $p): ?><li><strong><?= e($p['label']) ?></strong> <code><?= e($p['key']) ?></code><span class="adm-muted"><?= $p['files'] === null ? '–' : e(__('{n} Dateien', ['n' => $p['files']])) ?><?= isset($p['size']) ? ' · ' . e($size((int) $p['size'])) : '' ?><?= $p['sites'] ? ' · ' . e(__('genutzt von {sites}', ['sites' => implode(', ', $p['sites'])])) : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($shared['tables']): ?>
    <h3 class="net-h3"><?= e(__('Geteilte Daten')) ?></h3>
    <ul class="adm-list net-list"><?php foreach ($shared['tables'] as $t): ?><li><strong><?= e($t['label']) ?></strong> <code><?= e($t['key']) ?></code><span class="adm-muted"><?= $t['entries'] === null ? '–' : e(__('{n} Einträge', ['n' => $t['entries']])) ?><?= $t['owner'] ? ' · ' . e(__('Eigentümer: {site}', ['site' => $t['owner']])) : '' ?><?= $t['members'] ? ' · ' . e(implode(', ', $t['members'])) : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
