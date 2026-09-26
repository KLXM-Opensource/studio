<?php
/**
 * Zentrale Einstellungen in einer weiteren Sprache: „Mit KI übersetzen“ für die übersetzbaren Textfelder (Core\AI).
 * Füllt nur das Formular; vorhandene Übersetzungen werden nur nach Bestätigung ersetzt. Oberfläche: resources/js/_ai.js
 * @var string $lang  @var array $groups
 */
use Core\AI\Assist;
use Core\Lang;

if ($lang === '' || !Assist::available('text')) return;
$fields = [];
foreach ($groups as $g) {
    foreach ($g['fields'] as $f) {
        if (!isset($f['name']) || !in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'inline'], true)) continue;
        $v = app()->settings->get($f['name']);
        if (!is_string($v) || trim(strip_tags($v)) === '') continue;
        $fields[] = ['name' => 'f[' . $f['name'] . ']', 'label' => $f['label'] ?? $f['name'], 'html' => in_array($f['type'], ['richtext', 'inline'], true), 'source' => $v];
    }
}
if (!$fields) return;
$cfg = ['from' => Lang::default(), 'fromLabel' => Lang::all()[Lang::default()] ?? '', 'to' => $lang, 'toLabel' => Lang::all()[$lang] ?? $lang, 'fields' => $fields];
?>
<p class="kia-inline"><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-translate-form="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>"><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Texte mit KI aus {from} übersetzen ({n})', ['from' => $cfg['fromLabel'], 'n' => count($fields)])) ?></button>
  <span class="adm-muted"><?= e(__('Vorschläge zum Prüfen – gespeichert wird erst mit „Speichern“.')) ?></span></p>
