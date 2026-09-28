<?php
/**
 * Unterschiede (Format Core\Review\Diff) – feldweise nebeneinander, bei Seiten blockweise. Gemeinsam für die Prüf-Ebene
 * „Eingereicht“ (review/show.php) und die Entwürfe (drafts/show.php).
 * @var array $diff  @var string $newLabel  Beschriftung des neuen Stands  @var ?string $oldLabel  @var ?string $removedLabel
 */
use Core\Review\Diff;

$oldLabel ??= __('Vorher');
$removedLabel ??= __('Vorher (wird entfernt)');
$blockOps = ['added' => __('neu'), 'removed' => __('entfernt'), 'changed' => __('geändert'), 'moved' => __('verschoben')];

/** Feldwert vorher/nachher nebeneinander (wortweise markiert) */
$pair = function (mixed $before, mixed $after) use ($newLabel, $oldLabel, $removedLabel): string {
    [$a, $b] = Diff::words(Diff::text($before), Diff::text($after));
    $cell = fn(string $h, string $label, string $cls) => '<div class="rv-diff__col rv-diff__col--' . $cls . '"><span class="rv-diff__lbl">' . e($label) . '</span>'
        . ($h !== '' ? '<div class="rv-diff__val">' . $h . '</div>' : '<div class="rv-diff__val rv-diff__val--empty">' . e(__('(leer)')) . '</div>') . '</div>';
    // Nur eine Seite vorhanden (neu bzw. entfernt): eine Spalte statt „(leer)“ daneben
    if ($a === '' && $b !== '') return '<div class="rv-diff__pair rv-diff__pair--one">' . $cell($b, $newLabel, 'new') . '</div>';
    if ($b === '' && $a !== '') return '<div class="rv-diff__pair rv-diff__pair--one">' . $cell($a, $removedLabel, 'old') . '</div>';
    return '<div class="rv-diff__pair">' . $cell($a, $oldLabel, 'old') . $cell($b, $newLabel, 'new') . '</div>';
};
?>
<?php foreach ($diff as $d): ?>
  <?php if ($d['type'] === 'blocks'): ?>
  <div class="rv-diff rv-diff--blocks">
    <h3 class="rv-diff__field"><?= e($d['label']) ?> <small class="adm-muted"><?= e(count($d['items']) === 1 ? __('1 Block') : __('{n} Blöcke', ['n' => count($d['items'])])) ?></small></h3>
    <ol class="rv-blocks">
    <?php foreach ($d['items'] as $it): ?>
      <li class="rv-block rv-block--<?= e($it['op']) ?>">
        <p class="rv-block__head"><span class="rv-op rv-op--<?= e($it['op']) ?>"><?= e($blockOps[$it['op']] ?? $it['op']) ?></span>
          <strong><?= e($it['label']) ?></strong>
          <span class="adm-muted"><?= e(match ($it['op']) {
              'added' => __('an Position {n}', ['n' => ($it['to'] ?? 0) + 1]),
              'removed' => __('bisher Position {n}', ['n' => ($it['from'] ?? 0) + 1]),
              default => !empty($it['moved']) ? __('Position {a} → {b}', ['a' => ($it['from'] ?? 0) + 1, 'b' => ($it['to'] ?? 0) + 1]) : __('Position {n}', ['n' => ($it['to'] ?? 0) + 1]),
          }) ?></span></p>
        <?php if ($it['op'] !== 'moved' && $it['fields']): ?>
        <dl class="rv-block__fields">
          <?php foreach ($it['fields'] as $fd): ?>
          <dt><?= e($fd['label']) ?></dt><dd><?= $pair($fd['before'], $fd['after']) ?></dd>
          <?php endforeach; ?>
        </dl>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ol>
  </div>
  <?php else: ?>
  <div class="rv-diff">
    <h3 class="rv-diff__field"><?= e($d['label']) ?> <code class="rv-diff__key"><?= e($d['path']) ?></code></h3>
    <?= $pair($d['before'], $d['after']) ?>
  </div>
  <?php endif; ?>
<?php endforeach; ?>
