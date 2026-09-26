<?php
/**
 * Block „KLXM Check“ (Erweiterung klxm_check) – Domain-, Mail- und TLS-Prüfung mit Generatoren, in jedem Kit.
 * Ist die Funktion „check“ aus, zeigt der Block in der Bearbeitung einen Hinweis und auf der Website nichts.
 * @var \Core\Block $b  @var array $d
 */
use Klxm\Check\Check;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
if (!Check::on()) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p>' . e(lt('KLXM Check ist auf dieser Website abgeschaltet (Administration → Funktionen & Erweiterungen).')) . '</p></div>';
    return;
}
?>
<div class="<?= e($wrap) ?>">
  <?= Check::render([
      'title' => trim((string) ($d['title'] ?? '')),
      'intro' => trim((string) ($d['intro'] ?? '')),
      'tab' => (string) ($d['tab'] ?? 'checker'),
      'tabs' => (string) ($d['tabs'] ?? 'all'),
      'guides' => (bool) ($d['guides'] ?? true),
      'level' => 2,
  ]) ?>
</div>
