<?php
/**
 * Handbuch → Projekt-Hinweise verwalten (Core\Guide): Hinweise aus dem Kit (nur lesen, für die Website anpassen) und
 * eigene Hinweise der Website. @var list<array> $notes  @var ?string $kitDir
 */
use Core\Guide;

$base = '/admin/hilfe/projekt';
$where = fn(array $n) => match (true) {
    $n['source'] === 'kit' => __('Kit'),
    $n['overrides'] => __('Website (angepasst)'),
    default => __('Website'),
};
$helpTab = 'manual';
include ROOT . '/app/Admin/views/support/_helptabs.php';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/hilfe')) ?>"><?= e(__('Handbuch')) ?></a></p>
    <h1><?= e(__('Projekt-Hinweise')) ?></h1>
    <p class="adm-muted"><?= e(__('Hinweise zu diesem Projekt ergänzen das Handbuch für die Redaktion: eigene Blöcke, Bildformate, Abläufe. Das Kit „{kit}“ kann welche mitbringen; hier legen Sie eigene für diese Website an oder passen die des Kits an. Das Kapitel im Handbuch erscheint nur, wenn es Hinweise gibt.', ['kit' => app()->theme->label()])) ?>
      <a href="<?= e(url('/admin/hilfe/technik#projekt-hinweise')) ?>"><?= e(__('Format (Technische Dokumentation) →')) ?></a></p></div>
  <a class="adm-btn adm-btn--primary" href="<?= e(url($base . '/neu')) ?>"><?= e(__('Neuer Hinweis')) ?></a>
</header>

<?php if (!$notes): ?>
<div class="adm-card rv-empty">
  <p><b><?= e(__('Noch keine Projekt-Hinweise.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Weder das Kit noch diese Website bringen Hinweise mit – das Handbuch zeigt deshalb kein eigenes Kapitel.')) ?></p>
</div>
<?php else: ?>
<div class="adm-card adm-card--flush">
<table class="adm-table">
  <caption class="sr-only"><?= e(__('Projekt-Hinweise')) ?></caption>
  <thead><tr><th scope="col"><?= e(__('Titel')) ?></th><th scope="col"><?= e(__('Datei')) ?></th><th scope="col"><?= e(__('Herkunft')) ?></th><th scope="col"><?= e(__('Bezug')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
  <tbody>
  <?php foreach ($notes as $n): ?>
    <tr>
      <td><?php if (!$n['hidden']): ?><a href="<?= e(Guide::url($n)) ?>"><?= e($n['title']) ?></a><?php else: ?><?= e($n['title']) ?> <span class="adm-badge adm-badge--muted"><?= e(__('ausgeblendet')) ?></span><?php endif; ?></td>
      <td class="gd-file"><code><?= e($n['key']) ?>.md</code></td>
      <td><span class="adm-badge<?= $n['source'] === 'kit' ? ' adm-badge--muted' : '' ?>"><?= e($where($n)) ?></span></td>
      <td class="adm-muted"><?= e(implode(', ', [...array_map(fn($a) => __('Bereich') . ' ' . $a, $n['areas']), ...array_map(fn($b) => __('Block') . ' ' . $b, $n['blocks'])]) ?: '–') ?></td>
      <td><div class="adm-actions">
        <a class="adm-btn adm-btn--small" href="<?= e(url($base . '/' . $n['key'])) ?>"><?= e($n['source'] === 'kit' ? __('Für diese Website anpassen') : __('Bearbeiten')) ?><span class="sr-only"> <?= e($n['title']) ?></span></a>
        <?php if ($n['source'] === 'site'): ?>
        <form method="post" action="<?= e(url($base . '/' . $n['key'] . '/loeschen')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit"
          data-confirm="<?= e($n['overrides'] ? __('Anpassung entfernen? Danach gilt wieder der Hinweis aus dem Kit.') : __('Diesen Hinweis löschen?')) ?>"><?= e($n['overrides'] ? __('Anpassung entfernen') : __('Löschen')) ?><span class="sr-only"> <?= e($n['title']) ?></span></button></form>
        <?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<p class="adm-muted"><?= e(__('Dateien der Website: {dir}', ['dir' => str_replace(ROOT . '/', '', Guide::siteDir()) . '/*.md'])) ?><?php if ($kitDir): ?> · <?= e(__('Kit: {dir}', ['dir' => str_replace(ROOT . '/', '', $kitDir) . '/*.md'])) ?><?php endif; ?></p>
