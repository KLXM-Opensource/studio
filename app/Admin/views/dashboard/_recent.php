<?php
/** Übersicht · „Zuletzt bearbeitet“: eigene Seiten (Versionen) und Team (Seiten + Einträge). @var array $recent @var array $user */
use Core\Dashboard\Dashboard;

$item = function (array $r, bool $who) {
    $meta = trim(($r['kind'] ?? '') . ' · ' . Dashboard::ago($r['at']) . ($who && ($r['who'] ?? '') !== '' ? ' · ' . $r['who'] : ''), ' ·');
    return '<li><a href="' . e(url($r['href'])) . '"><span class="dash-list__ico">' . icon($r['icon']) . '</span><span class="dash-list__main"><span class="dash-list__title">'
        . e($r['title']) . '</span><small>' . e($meta) . '</small></span>'
        . (!empty($r['draft']) ? '<span class="adm-badge adm-badge--draft">' . e(__('Entwurf')) . '</span>' : '') . '</a></li>';
};
?>
<?php if (!$recent || (!$recent['mine'] && !$recent['team'])): ?>
  <p class="adm-muted"><?= e(__('Noch nichts bearbeitet. Öffnen Sie eine Seite und klicken Sie oben auf „Bearbeiten“.')) ?></p>
<?php else: ?>
  <?php if ($recent['mine']): ?>
  <h3 class="dash-h3"><?= e(__('Von Ihnen – weiter bearbeiten')) ?></h3>
  <ul class="dash-list" role="list"><?php foreach ($recent['mine'] as $r) echo $item($r, false); ?></ul>
  <?php endif; ?>
  <?php if ($recent['team']): ?>
  <h3 class="dash-h3"><?= e(__('Im Team')) ?></h3>
  <ul class="dash-list" role="list"><?php foreach ($recent['team'] as $r) echo $item($r, true); ?></ul>
  <?php endif; ?>
<?php endif; ?>
