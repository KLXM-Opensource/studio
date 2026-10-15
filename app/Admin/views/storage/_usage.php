<?php
/**
 * Speicher einer Website aufgeschlüsselt (Core\StorageUsage) – Balken wie in den macOS-Speicher-Einstellungen und Liste darunter.
 * Eingebunden in der Übersicht (Karte „Speicher“) und im Netzwerk (Bereich „Speicher“).
 * @var array $u  Ergebnis von StorageUsage::site()  @var bool $compact  Karte: nur die größten Teile, Rest aufklappbar
 */
use Core\StorageUsage;

$compact ??= false;
$defs = StorageUsage::parts();
$total = max(1, (int) $u['total']);
$rows = array_filter($u['parts'], fn($p) => (int) $p['bytes'] > 0);
uasort($rows, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
$extNames = [];
foreach (\Core\Extensions::available() as $n => $x) $extNames[$n] = (string) ($x['label'] ?? $n);
$row = function (string $k, array $p) use ($defs, $total, $u, $extNames): string {
    [$label, $ico, $tone] = $defs[$k];
    $sub = match ($k) {
        'images', 'video', 'audio', 'docs', 'other' => (int) $p['files'] === 1 ? __('1 Datei') : __('{n} Dateien', ['n' => fmt()->number((int) $p['files'])]),
        'trash' => __('{n} Dateien – werden nach Ablauf der Frist endgültig gelöscht', ['n' => fmt()->number((int) $p['files'])]),
        'variants' => __('Automatisch erzeugt, wird bei Bedarf neu angelegt'),
        'backups' => (int) $p['files'] === 1 ? __('1 Sicherung') : __('{n} Sicherungen', ['n' => (int) $p['files']]),
        'cache' => __('Wird bei Bedarf neu aufgebaut'),
        'extra' => implode(', ', array_map(fn($n, $b) => ($extNames[$n] ?? $n) . ' ' . fmt()->bytes($b), array_keys($u['extra'] ?? []), $u['extra'] ?? [])),
        default => '',
    };
    $pct = $p['bytes'] / $total * 100;
    return '<li class="su-row"><span class="su-dot su-t' . (int) $tone . '" aria-hidden="true"></span><span class="su-row__ico" aria-hidden="true">' . icon($ico) . '</span>'
        . '<span class="su-row__main"><span class="su-row__label">' . e($label) . '</span>' . ($sub !== '' ? '<span class="su-row__sub">' . e($sub) . '</span>' : '') . '</span>'
        . '<span class="su-row__val">' . e(fmt()->bytes((int) $p['bytes'])) . '<small>' . e($pct >= 1 ? fmt()->number(round($pct)) . ' %' : '< 1 %') . '</small></span></li>';
};
?>
<div class="su">
  <p class="su-total"><strong><?= e(fmt()->bytes((int) $u['total'])) ?></strong> <span class="adm-muted"><?= e(__('belegt')) ?></span></p>
  <div class="su-bar" role="img" aria-label="<?= e(implode(', ', array_map(fn($k, $p) => $defs[$k][0] . ' ' . fmt()->bytes((int) $p['bytes']), array_keys($rows), $rows))) ?>">
    <?php foreach ($rows as $k => $p): $w = $p['bytes'] / $total * 100; if ($w < 0.4) continue; ?><span class="su-seg su-t<?= (int) $defs[$k][2] ?>" style="width:<?= number_format($w, 2, '.', '') ?>%" title="<?= e($defs[$k][0] . ' · ' . fmt()->bytes((int) $p['bytes'])) ?>"></span><?php endforeach; ?>
  </div>
  <?php $list = array_keys($rows); $head = $compact ? array_slice($list, 0, 5) : $list; $rest = array_diff($list, $head); ?>
  <ul class="su-list" role="list"><?php foreach ($head as $k) echo $row($k, $rows[$k]); ?></ul>
  <?php if ($rest): ?>
  <details class="su-more"><summary><?= e(__('{n} weitere', ['n' => count($rest)])) ?></summary><ul class="su-list" role="list"><?php foreach ($rest as $k) echo $row($k, $rows[$k]); ?></ul></details>
  <?php endif; ?>
  <?php if (!empty($u['pools'])): ?>
  <p class="su-note"><?= icon('share-network') ?> <?= e(__('Zusätzlich genutzt, aber nicht mitgezählt:')) ?> <?= e(implode(' · ', array_map(fn($p) => __('Pool „{pool}“', ['pool' => $p['label']]) . ' ' . fmt()->bytes((int) $p['bytes']), $u['pools']))) ?></p>
  <?php endif; ?>
  <p class="su-note adm-muted"><?= e(__('Stand: {time}', ['time' => fmt()->relative((int) $u['at'])])) ?></p>
</div>
