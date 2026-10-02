<?php
/**
 * Administration → Einstellungen → Einstellungen der Funktionen (Core\AdminPages, PrefsController): reine Einstellungsseiten von
 * Funktionen und Erweiterungen (kind „settings“) an einem Ort – statt je eines Menüpunkts. Oben die übrigen Punkte der Gruppe
 * „Einstellungen“ in derselben Reihenfolge wie in der Seitenleiste (Core\AdminPages::groups).
 * @var array $groups
 */
$siblings = [];
foreach (\Core\AdminPages::groups() as $g) if ($g['key'] === 'einstellungen') $siblings = $g['items'];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1><?= e(__('Einstellungen der Funktionen')) ?></h1>
    <p class="adm-muted"><?= e(__('Einstellungen der Funktionen und Erweiterungen dieser Website. Seiten, die zu einer Datentabelle gehören, finden Sie zusätzlich direkt an der Tabelle.')) ?></p></div>
</header>
<?php if (count($siblings) > 1): ?>
<nav class="pf-top" aria-label="<?= e(__('Einstellungen')) ?>">
  <?php foreach ($siblings as [$href, $label, $ico]): $here = $href === \Core\AdminPages::HUB; ?><a class="adm-btn adm-btn--<?= $here ? 'primary' : 'ghost' ?> adm-btn--small" href="<?= e(url($href)) ?>"<?= $here ? ' aria-current="page"' : '' ?>><?= \Core\Icons::render($ico) ?> <?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (!$groups): ?>
<div class="adm-card rv-empty"><p><b><?= e(__('Keine weiteren Einstellungen.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Eingeschaltete Funktionen und Erweiterungen mit eigenen Einstellungen erscheinen hier.')) ?></p></div>
<?php else: ?>
<?= \Core\Theme::capture(__DIR__ . '/_cards.php', ['groups' => $groups]) ?>
<?php endif; ?>
