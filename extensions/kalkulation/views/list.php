<?php
/**
 * Kalkulationen: Suche, Status-Filter, Tabelle mit Summen (netto), Aktionen.
 * @var list<array> $rows  @var string $q  @var string $status  @var array<string,int> $counts
 */
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Num;

$statuses = Kalkulation::statuses();
$total = array_sum($counts);
$base = url('/admin/kalkulation');
?>
<form class="kx-toolbar" method="get" action="<?= e($base) ?>" role="search" aria-label="<?= e(__('Kalkulationen filtern')) ?>">
  <div class="f kx-toolbar__q"><label for="kx-q"><?= e(__('Suche')) ?></label>
    <input id="kx-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Nummer, Titel, Kunde, Projekt …')) ?>"></div>
  <div class="f"><label for="kx-st"><?= e(__('Status')) ?></label>
    <select id="kx-st" name="status" data-kx-autosubmit>
      <option value=""<?= $status === '' ? ' selected' : '' ?>><?= e(__('Alle außer Archiv')) ?></option>
      <?php foreach ($statuses as $k => [$label]): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?> (<?= (int) $counts[$k] ?>)</option><?php endforeach; ?>
      <option value="all"<?= $status === 'all' ? ' selected' : '' ?>><?= e(__('Alle')) ?> (<?= (int) $total ?>)</option>
    </select></div>
  <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
  <?php if ($q !== '' || $status !== ''): ?><a class="adm-btn adm-btn--ghost" href="<?= e($base) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
</form>

<?php if (!$rows): ?>
<section class="adm-card kx-empty">
  <?php if ($total === 0): ?>
  <h2><?= e(__('Noch keine Kalkulation')) ?></h2>
  <p class="adm-muted"><?= e(__('Tipp: Tragen Sie zuerst unter „Einstellungen“ die Stundensätze ein und prüfen Sie den Leistungskatalog – die Pakete dort sind Beispiele ohne Beträge.')) ?></p>
  <p class="adm-row"><a class="adm-btn" href="<?= e(url('/admin/kalkulation/einstellungen')) ?>"><?= e(__('Einstellungen')) ?></a>
    <a class="adm-btn" href="<?= e(url('/admin/kalkulation/katalog')) ?>"><?= e(__('Leistungskatalog')) ?></a></p>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Keine Kalkulation passt zu diesem Filter.')) ?></p>
  <?php endif; ?>
</section>
<?php else: ?>
<section class="adm-card adm-card--flush">
  <table class="adm-table kx-list">
    <caption class="sr-only"><?= e(__('Kalkulationen')) ?></caption>
    <thead><tr>
      <th scope="col"><?= e(__('Nummer')) ?></th>
      <th scope="col"><?= e(__('Titel / Kunde')) ?></th>
      <th scope="col"><?= e(__('Status')) ?></th>
      <th scope="col" class="kx-num"><?= e(__('Einmalig netto')) ?></th>
      <th scope="col" class="kx-num"><?= e(__('Monatlich netto')) ?></th>
      <th scope="col"><?= e(__('Geändert')) ?></th>
      <th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $t = $c['_totals']; $cur = (string) $c['params']['currency']; [$sl, $sc] = $statuses[$c['status']] ?? [$c['status'], '']; ?>
      <tr>
        <td class="kx-nowrap"><a href="<?= e($base . '/' . $c['id']) ?>"><strong><?= e($c['number']) ?></strong></a><br><small class="adm-muted"><?= e(Kalkulation::date($c['calc_date'])) ?></small></td>
        <td>
          <?php if ($c['locked']): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('Nicht lesbar (anderer Schlüssel)')) ?></span>
          <?php else: ?>
          <a href="<?= e($base . '/' . $c['id']) ?>"><?= e($c['title'] !== '' ? $c['title'] : __('(ohne Titel)')) ?></a>
          <?php if ($c['customer'] !== '' || $c['project'] !== ''): ?><br><small class="adm-muted"><?= e(implode(' · ', array_filter([$c['customer'], $c['project']]))) ?></small><?php endif; ?>
          <?php endif; ?>
        </td>
        <td><span class="adm-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="kx-num"><?= $t['once']['net'] != 0 ? e(Num::money($t['once']['net'], $cur)) : '<span class="adm-muted">–</span>' ?></td>
        <td class="kx-num"><?= $t['monthly']['net'] != 0 ? e(Num::money($t['monthly']['net'], $cur)) : '<span class="adm-muted">–</span>' ?></td>
        <td class="kx-nowrap"><small><?= e(Kalkulation::dateTime($c['updated_at'])) ?><br><span class="adm-muted"><?= e(Kalkulation::userName($c['updated_by'])) ?></span></small></td>
        <td class="adm-actions">
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($base . '/' . $c['id']) ?>"><?= e(__('Öffnen')) ?></a>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($base . '/' . $c['id'] . '/angebot') ?>" target="_blank" rel="noopener"><?= e(__('Angebot')) ?></a>
          <form method="post" action="<?= e($base . '/' . $c['id'] . '/duplizieren') ?>"><?= csrf_field() ?>
            <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" aria-label="<?= e(__('{number} duplizieren', ['number' => $c['number']])) ?>"><?= e(__('Duplizieren')) ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<p class="adm-muted kx-foot"><?= e(__('Summen ohne optionale und alternative Positionen, nach Nachlass, ohne Umsatzsteuer.')) ?></p>
<?php endif; ?>
