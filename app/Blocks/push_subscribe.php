<?php
/**
 * Benachrichtigungen abonnieren (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/push_subscribe.php) – Push-Abo neuer
 * Einträge einer Datentabelle für Besucher (Core\Push\Visitor). Aussehen: resources/css/push.css (Variablen --push-*), Verhalten:
 * resources/js/push.js. Im Kit auch direkt: <?= push_subscribe('aktuelles') ?>
 * @var \Core\Block $b  @var array $d
 */
$wrap = app()->theme->def['container_class'] ?? 'wrap';
$html = \Core\Push\Visitor::render((string) ($d['table'] ?? '') ?: null, [
    'title' => (string) ($d['title'] ?? ''), 'intro' => (string) ($d['intro'] ?? ''), 'button' => (string) ($d['button'] ?? ''),
    'layout' => (string) ($d['layout'] ?? 'box'), 'edit' => $b,
]);
if ($html === '') return;
?>
<div class="<?= e($wrap) ?>"><?= $html ?></div>
