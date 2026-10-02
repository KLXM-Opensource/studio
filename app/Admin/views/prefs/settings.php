<?php
/**
 * Administration → Einstellungen → Einstellungen der Funktionen (Core\AdminPages, PrefsController): reine Einstellungsseiten von
 * Funktionen und Erweiterungen (kind „settings“) an einem Ort – statt je eines Menüpunkts. Die übrigen Punkte der Gruppe
 * „Einstellungen“ stehen in der Seitenleiste (keine doppelte Leiste mehr).
 * @var array $groups
 */
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1><?= e(__('Einstellungen der Funktionen')) ?></h1>
    <p class="adm-muted"><?= e(__('Einstellungen der Funktionen und Erweiterungen dieser Website. Seiten, die zu einer Datentabelle gehören, finden Sie zusätzlich direkt an der Tabelle.')) ?></p></div>
</header>
<?php // Keine eigene Leiste mit den übrigen Einstellungs-Punkten mehr – sie stehen schon in der Seitenleiste (Administration › Einstellungen) ?>
<?php if (!$groups): ?>
<div class="adm-card rv-empty"><p><b><?= e(__('Keine weiteren Einstellungen.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Eingeschaltete Funktionen und Erweiterungen mit eigenen Einstellungen erscheinen hier.')) ?></p></div>
<?php else: ?>
<?= \Core\Theme::capture(__DIR__ . '/_cards.php', ['groups' => $groups]) ?>
<?php endif; ?>
