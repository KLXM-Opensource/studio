<?php
/** Vollständige PHP-API-Referenz (alle Klassen unter app/, Core\ApiReference). @var array $ref */
$__n = array_sum(array_map('count', $ref));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/hilfe/technik#php-api')) ?>"><?= e(__('Entwicklerhandbuch')) ?></a></p><h1><?= e(__('PHP-API-Referenz')) ?></h1>
    <p class="adm-muted"><?= e(__('{n} Klassen des Kerns mit ihren öffentlichen Methoden – live aus dem Code. Mit „API“ markiert: stabile Schnittstellen für Erweiterungen und Kits; alles andere ist intern.', ['n' => $__n])) ?></p></div>
</header>
<div class="doc api-ref">
  <nav class="api-toc" aria-label="<?= e(__('Namespaces')) ?>"><?php foreach ($ref as $__ns => $__l): ?><a href="#ns-<?= e(strtolower(str_replace('\\', '-', $__ns))) ?>"><?= e($__ns) ?> <small>(<?= count($__l) ?>)</small></a><?php endforeach; ?></nav>
  <?php foreach ($ref as $__ns => $__list): ?>
  <section id="ns-<?= e(strtolower(str_replace('\\', '-', $__ns))) ?>">
    <h2><code><?= e($__ns) ?></code></h2>
    <?php foreach ($__list as $cls => $c): include __DIR__ . '/_php-api-class.php'; endforeach; ?>
  </section>
  <?php endforeach; ?>
</div>
