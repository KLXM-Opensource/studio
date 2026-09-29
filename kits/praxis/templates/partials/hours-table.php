<?php
/**
 * Sprechzeiten als Tabelle: Tag · Vormittag · Nachmittag.
 * @var string $class  Zusatzklasse (htable--pop | htable--card)
 */
$hours = praxis_hours();
$today = !empty($noToday) ? -1 : (int) date('w');   // Offline-Seite: kein „heute“ (wird zwischengespeichert)
?>
<table class="htable <?= e($class ?? '') ?>">
  <caption class="sr-only"><?= e(lt('Sprechzeiten')) ?></caption>
  <thead><tr><th scope="col"><span class="sr-only"><?= e(lt('Wochentag')) ?></span></th><th scope="col"><?= e(lt('Vormittag')) ?></th><th scope="col"><?= e(lt('Nachmittag')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($hours as $h): $isToday = $h['dow'] === $today; ?>
    <tr<?= $isToday ? ' class="is-today"' : '' ?>>
      <th scope="row"><?= e($h['day']) ?><?= $isToday ? '<span class="sr-only"> ' . e(lt('(heute)')) . '</span>' : '' ?></th>
      <?php /* Zeiten bündig (praxis_range_html): Beginn rechtsbündig in fester Breite – die Striche stehen untereinander */ ?>
      <td<?= $h['am'] ? '' : ' class="is-closed"' ?>><?= $h['am'] ? praxis_ranges_html($h['am_seg']) : '–' ?></td>
      <td<?= $h['pm'] ? '' : ' class="is-closed"' ?>><?= $h['pm'] ? praxis_ranges_html($h['pm_seg']) : e($h['am'] ? lt('geschlossen') : '–') ?></td>
    </tr>
    <?php if ($h['extra'] !== '' && ($h['am'] || $h['pm'])): ?>
    <tr class="htable__note"><td></td><td colspan="2"><?= e($h['extra']) ?></td></tr>
    <?php elseif (!$h['am'] && !$h['pm'] && $h['note'] !== ''): ?>
    <tr class="htable__note"><td></td><td colspan="2"><?= e($h['note']) ?></td></tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($hours && praxis_weekend_closed()): ?>
    <tr class="htable__weekend<?= in_array($today, [0, 6], true) ? ' is-today' : '' ?>"><th scope="row"><?= e(lt('Sa – So')) ?></th><td colspan="2" class="is-closed"><?= e(lt('geschlossen')) ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
<p class="htable__unit"><?= e(lt('Alle Zeiten in Uhr.')) ?></p>
