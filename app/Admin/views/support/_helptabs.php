<?php
/**
 * Reiter „Handbuch & Hilfe“: Handbuch · Wissensdatenbank · Fragen & Antworten · Technik · Lizenzen.
 * Eingebunden in help/manual.php, help/technical.php und die Wissens-Seiten des Supports.
 * @var string $helpTab  manual|tutorials|kb|questions|icons|technical|licenses
 */
$helpTab ??= '';
$tabs = [['/admin/hilfe', __('Handbuch'), 'manual']];
$tabs[] = ['/admin/hilfe/tutorials', __('Tutorials'), 'tutorials'];   // Videos je Zielgruppe (app/Admin/tutorials.php)
if (\Core\Support\Support::canRead()) {
    $tabs[] = ['/admin/support/wissen', __('Wissensdatenbank'), 'kb'];
    $tabs[] = ['/admin/support/fragen', __('Fragen & Antworten'), 'questions'];
}
$tabs[] = ['/admin/hilfe/symbole', __('Symbole'), 'icons'];   // Übersicht aller Symbole (Core\Icons)
$tabs[] = ['/admin/hilfe/technik', __('Technische Dokumentation'), 'technical'];
$tabs[] = ['/admin/hilfe/lizenzen', __('Lizenzen'), 'licenses'];   // Lizenzen & Danksagungen (THIRD-PARTY-NOTICES.md)
?>
<nav class="sp-helptabs" aria-label="<?= e(__('Handbuch & Hilfe')) ?>">
  <?php foreach ($tabs as [$href, $label, $key]): ?>
  <a href="<?= e(url($href)) ?>"<?= $helpTab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if (\Core\Support\Support::canReport()): ?>
  <a class="sp-helptabs__report" href="<?= e(url('/admin/support/neu')) ?>" data-support-report><?= e(__('Problem melden')) ?></a>
  <?php endif; ?>
</nav>
