<?php
/** Alle Tags aus Wissensartikeln und Fragen. @var array $tags [tag => Anzahl] */
$helpTab = 'kb';
include __DIR__ . '/_helptabs.php';
ksort($tags, SORT_NATURAL | SORT_FLAG_CASE);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Handbuch & Hilfe')) ?></p><h1><?= e(__('Tags')) ?></h1>
    <p class="adm-muted"><?= e(__('Themen aus Wissensdatenbank und Fragen & Antworten.')) ?></p></div>
</header>
<?php if (!$tags): ?>
<p class="adm-card adm-muted"><?= e(__('Noch keine Tags.')) ?></p>
<?php else: ?>
<ul class="sp-taggrid">
  <?php foreach ($tags as $t => $n): ?>
  <li class="adm-card"><b>#<?= e($t) ?></b>
    <span class="adm-muted"><?= e(__('{n}× verwendet', ['n' => (int) $n])) ?></span>
    <span class="adm-row"><a href="<?= e(url('/admin/support/wissen') . '?tag=' . rawurlencode($t)) ?>"><?= e(__('Artikel')) ?></a> · <a href="<?= e(url('/admin/support/fragen') . '?tag=' . rawurlencode($t)) ?>"><?= e(__('Fragen')) ?></a></span></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
