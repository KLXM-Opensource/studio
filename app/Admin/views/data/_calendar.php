<?php
/**
 * Kalenderansicht der Einträge (Tabellen mit „Als Kalender nutzen“): Monatsraster mit allen Vorkommen.
 * Blättern per ?monat=JJJJ-MM (ohne JavaScript), Klick auf einen Termin → Eintrag (bei Serien: der Serien-Eintrag),
 * „+“ an einem Tag → neuer Termin mit vorbelegtem Beginn. Auf schmalen Bildschirmen: Terminliste.
 * @var array $t  @var array $cal  @var string $base  @var string $lang  @var bool $multi  @var string $status  @var string $q
 */
use Core\Data\Calendar;
use Core\Data\Entries;

$first = $cal['first'];
$today = $cal['today']->format('Y-m-d');
$link = fn(array $o) => url($base) . '?' . http_build_query(array_filter($o + ['lang' => $multi && $lang !== \Core\Lang::default() ? $lang : '',
    'status' => $status === 'all' ? '' : $status, 'q' => $q], fn($v) => $v !== '' && $v !== null));
$newUrl = fn(string $day) => url($base . '/new') . '?' . http_build_query(array_filter(['start' => $day, 'lang' => $multi ? $lang : ''], fn($v) => $v !== ''));
$byDay = [];
foreach ($cal['occ'] as $o) {
    $last = $o['end'] > $o['start'] ? $o['end']->modify('-1 second') : $o['start'];
    for ($d = $o['start']->setTime(0, 0), $n = 0; $d <= $last && $n < 62; $d = $d->modify('+1 day'), $n++) $byDay[$d->format('Y-m-d')][] = $o;
}
$weekdays = [__('Montag'), __('Dienstag'), __('Mittwoch'), __('Donnerstag'), __('Freitag'), __('Samstag'), __('Sonntag')];
$fmt = fn(DateTimeInterface $d, string $p) => (new IntlDateFormatter(\Core\I18n::locale(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, $d->getTimezone(), null, $p))->format($d);
$canEdit = can('data.edit', $t['handle']);
$de = str_starts_with(\Core\I18n::locale(), 'de');
?>
<section class="dt-cal" aria-labelledby="dt-cal-month">
  <div class="dt-cal__bar">
    <h2 id="dt-cal-month" class="dt-cal__month"><?= e($fmt($first, 'LLLL y')) ?></h2>
    <nav class="dt-cal__nav" aria-label="<?= e(__('Monat wechseln')) ?>">
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($link(['monat' => $first->modify('-1 month')->format('Y-m')])) ?>" rel="nofollow"><span aria-hidden="true">←</span> <?= e($fmt($first->modify('-1 month'), 'LLLL')) ?></a>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($link(['monat' => $cal['today']->format('Y-m')])) ?>"<?= $cal['month'] === $cal['today']->format('Y-m') ? ' aria-current="date"' : '' ?>><?= e(__('Heute')) ?></a>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($link(['monat' => $cal['next']->format('Y-m')])) ?>" rel="nofollow"><?= e($fmt($cal['next'], 'LLLL')) ?> <span aria-hidden="true">→</span></a>
    </nav>
  </div>
  <table class="dt-cal__grid">
    <caption class="sr-only"><?= e(__('Termine im {month}', ['month' => $fmt($first, 'LLLL y')])) ?></caption>
    <thead><tr><?php foreach ($weekdays as $wd): ?><th scope="col" abbr="<?= e($wd) ?>"><span aria-hidden="true"><?= e(mb_substr($wd, 0, 2)) ?></span><span class="sr-only"><?= e($wd) ?></span></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php for ($d = $cal['gridStart']; $d < $cal['gridEnd']; $d = $d->modify('+1 day')):
      $key = $d->format('Y-m-d'); $events = $byDay[$key] ?? []; $other = $d < $first || $d >= $cal['next'];
      if ($d->format('N') === '1') echo '<tr>'; ?>
      <td class="dt-cal__day<?= $other ? ' is-other' : '' ?><?= $key === $today ? ' is-today' : '' ?><?= $events ? ' has-events' : '' ?>"<?= $key === $today ? ' aria-current="date"' : '' ?> data-cal-day>
        <div class="dt-cal__head">
          <span class="dt-cal__num" aria-hidden="true"><?= (int) $d->format('j') ?></span>
          <span class="dt-cal__full"><?= e($fmt($d, $de ? 'EEEE, d. MMMM' : 'EEEE, d MMMM')) ?></span>
          <?php if ($canEdit): ?><a class="dt-cal__add" href="<?= e($newUrl($key)) ?>" data-cal-add><span aria-hidden="true">+</span><span class="sr-only"><?= e(__('Neuer Termin am {date}', ['date' => $fmt($d, $de ? 'd. MMMM y' : 'd MMMM y')])) ?></span></a><?php endif; ?>
        </div>
        <?php if ($events): ?><ul class="dt-cal__events" role="list">
          <?php foreach ($events as $o): $e = $o['entry']; $draft = $e['status'] === 'draft'; ?>
          <li class="dt-cal__ev<?= $o['all_day'] ? ' is-allday' : '' ?><?= $draft ? ' is-draft' : '' ?>">
            <a href="<?= e(url($base . '/' . $e['id'])) ?>" title="<?= e(Calendar::when($o) . ($o['recurring'] ? ' · ' . __('Serie') : '')) ?>">
              <?php if (!$o['all_day']): ?><span class="dt-cal__time"><?= e($o['start']->format('H:i')) ?></span><?php endif; ?>
              <span class="dt-cal__title"><?= e(Entries::title($t, $e)) ?></span>
              <?php if ($o['recurring']): ?><span class="dt-cal__rec" aria-hidden="true"><?= icon('repeat') ?></span><span class="sr-only">(<?= e(__('Serie')) ?>)</span><?php endif; ?>
              <?php if ($draft): ?><span class="dt-cal__badge"><?= e(__('Entwurf')) ?></span><?php endif; ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul><?php endif; ?>
      </td>
      <?php if ($d->format('N') === '7') echo '</tr>'; endfor; ?>
    </tbody>
  </table>
  <p class="dt-foot"><span><?= e(__('{n} Termine in diesem Ansichtsbereich', ['n' => count($cal['occ'])])) ?> · <?= e(__('Klick auf einen Tag legt einen Termin an; Serien öffnen den Serien-Eintrag.')) ?></span></p>
</section>
