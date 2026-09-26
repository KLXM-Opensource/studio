<?php
/**
 * Leistungskatalog: Leistungen mit ihren Paketen (Übersicht, Reihenfolge, neu anlegen).
 * @var list<array> $services  @var array $rates
 */
use Klxm\Kalkulation\Num;

$base = url('/admin/kalkulation/katalog');
$bill = ['both' => __('Einrichtung + monatlich'), 'once' => __('einmalig'), 'monthly' => __('monatlich')];
$basis = fn(string $b) => $b === 'fixed' ? __('Festpreis') : ($b === '' ? __('Standard') : (string) ($rates[$b]['label'] ?? $b));
$part = function (array $it, string $k) use ($basis): string {
    $a = $it[$k . '_amount'];
    $c = $it[$k . '_cost'];
    if ($a === null && $c === null) return '<span class="adm-muted">–</span>';
    $out = [];
    if ($a !== null) $out[] = ($it[$k . '_basis'] === 'fixed' ? Num::fmt($a) : Num::input($a) . ' ' . __('Std.')) . ' <small class="adm-muted">' . e($basis((string) $it[$k . '_basis'])) . '</small>';
    if ($c !== null) $out[] = __('Fremdkosten') . ' ' . Num::fmt($c);
    return implode('<br>', $out);
};
$examples = 0;
foreach ($services as $s) foreach ($s['items'] as $it) if (!empty($it['example'])) $examples++;
?>
<?php if ($examples): ?>
<p class="adm-flash adm-flash--info kx-note"><?= e(__('{n} Pakete sind noch als „Beispiel“ markiert: Struktur aus der bisherigen Arbeitsmappe, alle Beträge leer. Beim Bearbeiten Stunden und Kosten eintragen und die Markierung entfernen.', ['n' => $examples])) ?></p>
<?php endif; ?>

<form class="adm-row kx-toolbar" method="post" action="<?= e($base . '/neu') ?>"><?= csrf_field() ?>
  <div class="f kx-toolbar__q"><label for="kx-sname"><?= e(__('Neue Leistung')) ?></label><input id="kx-sname" name="name" maxlength="120" placeholder="<?= e(__('z. B. Managed Mail')) ?>"></div>
  <button class="adm-btn adm-btn--primary" type="submit"><?= icon('plus') ?><?= e(__('Anlegen')) ?></button>
</form>

<div class="kx-services">
<?php foreach ($services as $i => $s): ?>
  <section class="adm-card kx-service" id="s-<?= e($s['id']) ?>" aria-labelledby="h-<?= e($s['id']) ?>">
    <div class="adm-row">
      <h2 id="h-<?= e($s['id']) ?>"><?= e($s['name']) ?> <small class="adm-muted"><?= e(__('{n} Pakete', ['n' => count($s['items'])])) ?></small></h2>
      <span class="kx-bar__sp"></span>
      <form method="post" action="<?= e($base . '/' . $s['id'] . '/verschieben') ?>" class="adm-row"><?= csrf_field() ?>
        <button class="icon-btn" name="dir" value="up" aria-label="<?= e(__('{name} nach oben', ['name' => $s['name']])) ?>"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
        <button class="icon-btn" name="dir" value="down" aria-label="<?= e(__('{name} nach unten', ['name' => $s['name']])) ?>"<?= $i === count($services) - 1 ? ' disabled' : '' ?>>↓</button>
      </form>
      <a class="adm-btn adm-btn--small" href="<?= e($base . '/' . $s['id']) ?>"><?= e(__('Bearbeiten')) ?></a>
    </div>
    <?php if ($s['desc'] !== ''): ?><p class="adm-muted kx-service__desc"><?= e($s['desc']) ?></p><?php endif; ?>
    <?php if ($s['items']): ?>
    <div class="kx-scroll">
    <table class="adm-table">
      <caption class="sr-only"><?= e(__('Pakete von {name}', ['name' => $s['name']])) ?></caption>
      <thead><tr><th scope="col"><?= e(__('Paket')) ?></th><th scope="col"><?= e(__('Abrechnung')) ?></th><th scope="col"><?= e(__('Einheit')) ?></th>
        <th scope="col" class="kx-num"><?= e(__('Einmalig')) ?></th><th scope="col" class="kx-num"><?= e(__('Monatlich')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($s['items'] as $it): ?>
        <tr><td><strong><?= e($it['name']) ?></strong><?= !empty($it['example']) ? ' <span class="adm-badge adm-badge--draft">' . e(__('Beispiel')) . '</span>' : '' ?>
            <?= $it['desc'] !== '' ? '<br><small class="adm-muted">' . e($it['desc']) . '</small>' : '' ?></td>
          <td><?= e($bill[$it['bill']] ?? $it['bill']) ?><?= !empty($it['buffer']) ? ' <span class="adm-badge adm-badge--muted">' . e(__('Puffer')) . '</span>' : '' ?></td>
          <td><?= e($it['unit']) ?></td>
          <td class="kx-num"><?= $it['bill'] !== 'monthly' ? $part($it, 'once') : '' ?></td>
          <td class="kx-num"><?= $it['bill'] !== 'once' ? $part($it, 'monthly') : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p class="adm-muted"><?= e(__('Noch keine Pakete.')) ?></p><?php endif; ?>
    <?php if (trim((string) $s['notes']) !== ''): ?><details class="kx-service__notes"><summary><?= e(__('Kostentreiber & Hinweise')) ?></summary><p class="adm-muted"><?= e($s['notes']) ?></p></details><?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
