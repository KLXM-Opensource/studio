<?php
/**
 * Anfragen – Postfächer in der Seitenleiste (Layout: $drill, resources/js/_drill.js; schmal in der Schublade), Aufbau wie Feedback:
 * Eingänge (je Eingangs-Tabelle mit Zahl neuer Anfragen), Status des gewählten Eingangs, Einrichtung.
 * @var array $tables  @var array $t  @var string $status  @var array $counts  @var array $newCounts  @var bool $canLog
 */
use Core\Data\Inbox;

$row = function (string $href, string $label, string $ico, bool $on, int $n = 0, string $extra = ''): string {
    return '<li><a class="fx-src rq-src' . ($on ? ' is-active' : '') . '" href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . $extra . '>'
        . icon($ico) . '<span>' . e($label) . '</span>' . ($n ? '<small>' . $n . '</small>' : '') . '</a></li>';
};
$base = url('/admin/requests');
$icoFor = ['neu' => 'tray', 'in_bearbeitung' => 'hourglass', 'erledigt' => 'check-circle', 'alle' => 'files'];
?>
<nav class="fx-side rq-side" data-drill-panel aria-label="<?= e(__('Postfächer')) ?>">
  <h3><?= e(__('Eingänge')) ?></h3>
  <ul>
    <?php foreach ($tables as $x): ?>
    <?= $row($base . '?table=' . rawurlencode($x['handle']), (string) $x['name'], (string) ($x['icon'] ?: 'tray'), $x['handle'] === $t['handle'], (int) ($newCounts[$x['handle']] ?? 0),
        ($newCounts[$x['handle']] ?? 0) ? ' data-warn' : '') ?>
    <?php endforeach; ?>
  </ul>
  <h3><?= e($t['name']) ?></h3>
  <ul>
    <?php foreach ([...array_keys(Inbox::statuses($t)), 'alle'] as $k): ?>
    <?= $row($base . '?' . http_build_query(['table' => $t['handle'], 'status' => $k]), $k === 'alle' ? __('Alle') : Inbox::statusLabel($k, $t),
        $icoFor[$k] ?? 'tag', $status === $k, (int) ($counts[$k] ?? 0)) ?>
    <?php endforeach; ?>
  </ul>
  <?php if ($canLog || can('data.schema') || can('system.manage')): ?>
  <h3><?= e(__('Einrichtung')) ?></h3>
  <ul>
    <?php if (can('data.schema')): ?><?= $row(url('/admin/data/' . $t['handle'] . '/schema#verschluesselung'), __('Felder & Einstellungen'), 'sliders-horizontal', false) ?><?php endif; ?>
    <?php if ($canLog): ?><?= $row(url('/admin/requests/log'), __('Protokoll'), 'list-checks', false) ?><?php endif; ?>
    <?php if (can('system.manage')): ?><?= $row(url('/admin/system#keys'), __('Schlüssel'), 'lock-key', false) ?><?php endif; ?>
  </ul>
  <?php endif; ?>
</nav>
