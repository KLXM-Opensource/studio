<?php
/**
 * Kalender: Monatsübersicht (Tabelle mit Wochentagen als Spaltenköpfen) oder Terminliste des Monats (Tage als Überschriften).
 * Blättern über ?monat=JJJJ-MM – funktioniert ohne JavaScript. Kern-Block, vom Theme überschreibbar (themes/{name}/blocks/calendar.php).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Tables;

$t = Tables::findContent((string) ($d['table'] ?? ''));
$wrap = app()->theme->def['container_class'] ?? 'wrap';
if (!$t || !Calendar::enabled($t)) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="cal-empty">' . e(__('Bitte in der Seitenleiste eine Tabelle wählen, die als Kalender eingerichtet ist.')) . '</p></div>';
    return;
}
$c = Calendar::config($t);
$tz = Calendar::tz();
$query = (array) (app()->request?->query ?? []);
$today = new DateTimeImmutable('today', $tz);
$month = is_string($query['monat'] ?? null) && preg_match('~^(\d{4})-(\d{2})$~', $query['monat'], $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12
    && abs((int) $m[1] - (int) $today->format('Y')) <= 10 ? $query['monat'] : $today->format('Y-m');
$first = new DateTimeImmutable($month . '-01 00:00', $tz);
$next = $first->modify('+1 month');
$prev = $first->modify('-1 month');
$view = ($d['view'] ?? 'month') === 'agenda' ? 'agenda' : 'month';
// Raster: Montag vor dem Monatsersten bis Sonntag nach dem Monatsletzten
$gridStart = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
$gridEnd = $next->modify('+' . ((8 - (int) $next->format('N')) % 7) . ' days');
[$where, $cat] = Calendar::blockWhere($t, $d, $query);
$occ = Calendar::occurrences($t, $view === 'month' ? $gridStart : $first, $view === 'month' ? $gridEnd : $next, ['where' => $where, 'source' => (string) ($d['source'] ?? '')]);
\Core\StructuredData::events($t, array_values(array_filter($occ, fn($o) => $o['start'] >= new DateTimeImmutable('today', Calendar::tz()))));   // schema.org Event (anstehende)

// Termine je Tag (mehrtägige an jedem Tag)
$byDay = [];
$inMonth = 0;
foreach ($occ as $o) {
    $lastDay = $o['end'] > $o['start'] ? $o['end']->modify('-1 second') : $o['start'];
    for ($day = $o['start']->setTime(0, 0), $n = 0; $day <= $lastDay && $n < 62; $day = $day->modify('+1 day'), $n++) {
        $byDay[$day->format('Y-m-d')][] = $o;
        if ($day >= $first && $day < $next) $inMonth++;
    }
}
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$dTag = $hTag === 'h3' ? 'h4' : 'h3';
$qs = fn(string $mon, ?string $k = null) => '?' . http_build_query(array_filter(['monat' => $mon, 'kategorie' => $k ?? $cat], fn($v) => $v !== null && $v !== '')) . '#' . $b->domId();
$link = fn(array $o) => !empty($d['link_detail']) ? Entries::href($t, $o['entry']) : null;
$monthId = $b->domId() . '-monat';
$catField = $c['category'] !== '' ? Tables::field($t, $c['category']) : null;
$event = function (array $o, bool $withLoc) use ($t, $c, $link) {
    $url = $link($o);
    $title = e(Entries::title($t, $o['entry']));
    $time = $o['all_day'] ? '' : '<span class="cal-time">' . e($o['start']->format('H:i')) . '</span> ';
    $h = $time . ($url ? '<a class="cal-link" href="' . e($url) . '">' . $title . '</a>' : '<span class="cal-title">' . $title . '</span>');
    if ($withLoc && $c['location'] !== '' && ($loc = Entries::html($t, $o['entry'], $c['location'], ['plain' => true])) !== '') {
        $h .= '<span class="cal-loc">' . $loc . '</span>';
    }
    return $h;
};
?>
<div class="<?= e($wrap) ?> cal cal--<?= e($view) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="cal-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m cal-heading"><span<?= $b->edit('title') ?>><?= e($d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted cal-intro"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <div class="cal-bar">
    <<?= $hTag ?> class="cal-month" id="<?= e($monthId) ?>"><?= e(Calendar::fmt($first, 'LLLL y')) ?></<?= $hTag ?>>
    <nav class="cal-nav" aria-label="<?= e(lt('Monat wechseln')) ?>">
      <a class="cal-navlink" href="<?= e($qs($prev->format('Y-m'))) ?>" rel="nofollow"><span aria-hidden="true">←</span> <span class="cal-navtext"><?= e(lt('Vorheriger Monat')) ?>:</span> <?= e(Calendar::fmt($prev, 'LLLL')) ?></a>
      <?php if ($month !== $today->format('Y-m')): ?><a class="cal-navlink cal-navlink--today" href="<?= e($qs($today->format('Y-m'))) ?>" rel="nofollow"><?= e(lt('Heute')) ?></a><?php endif; ?>
      <a class="cal-navlink" href="<?= e($qs($next->format('Y-m'))) ?>" rel="nofollow"><span class="cal-navtext"><?= e(lt('Nächster Monat')) ?>:</span> <?= e(Calendar::fmt($next, 'LLLL')) ?> <span aria-hidden="true">→</span></a>
    </nav>
  </div>

  <?php if (!empty($d['visitor_filter']) && $catField): ?>
  <nav class="cal-filter" aria-label="<?= e(lt('Nach Kategorie filtern')) ?>">
    <a class="cal-chip" href="<?= e('?' . http_build_query(['monat' => $month]) . '#' . $b->domId()) ?>" rel="nofollow"<?= $cat === null ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a>
    <?php foreach ((array) ($catField['options'] ?? []) as $k => $l): ?>
    <a class="cal-chip" href="<?= e($qs($month, (string) $k)) ?>" rel="nofollow"<?= $cat === (string) $k ? ' aria-current="true"' : '' ?>><?= e(Tables::optionLabel($catField, (string) $k)) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if ($view === 'month'): ?>
  <div class="cal-gridwrap">
  <table class="cal-grid" aria-labelledby="<?= e($monthId) ?>">
    <thead><tr>
      <?php for ($i = 0, $wd = $gridStart; $i < 7; $i++, $wd = $wd->modify('+1 day')): ?>
      <th scope="col" abbr="<?= e(Calendar::fmt($wd, 'EEEE')) ?>"><span aria-hidden="true"><?= e(Calendar::fmt($wd, 'EEEEEE')) ?></span><span class="cal-sr"><?= e(Calendar::fmt($wd, 'EEEE')) ?></span></th>
      <?php endfor; ?>
    </tr></thead>
    <tbody>
    <?php for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')):
      $key = $day->format('Y-m-d'); $events = $byDay[$key] ?? []; $isToday = $key === $today->format('Y-m-d'); $other = $day < $first || $day >= $next;
      if ($day->format('N') === '1') echo '<tr>'; ?>
      <td class="cal-day<?= $other ? ' is-other' : '' ?><?= $isToday ? ' is-today' : '' ?><?= $events ? ' has-events' : '' ?>"<?= $isToday ? ' aria-current="date"' : '' ?>>
        <span class="cal-num" aria-hidden="true"><?= (int) $day->format('j') ?></span>
        <span class="cal-full"><?= e(Calendar::dayLabel($day, 'long')) ?><?= $isToday ? ' (' . e(lt('heute')) . ')' : '' ?></span>
        <?php if ($events): ?><ul class="cal-events" role="list">
          <?php foreach ($events as $o): ?><li class="cal-ev<?= $o['all_day'] ? ' cal-ev--allday' : '' ?>"><?= $event($o, false) ?></li><?php endforeach; ?>
        </ul><?php endif; ?>
      </td>
      <?php if ($day->format('N') === '7') echo '</tr>'; endfor; ?>
    </tbody>
  </table>
  </div>
  <?php if (!$inMonth): ?><p class="cal-empty"><?= e($d['empty_text'] ?: lt('In diesem Monat sind keine Termine eingetragen.')) ?></p><?php endif; ?>

  <?php elseif (!$byDay): ?>
  <p class="cal-empty"><?= e($d['empty_text'] ?: lt('In diesem Monat sind keine Termine eingetragen.')) ?></p>
  <?php else: ?>
  <ol class="cal-agenda" role="list">
    <?php foreach ($byDay as $key => $events): $day = new DateTimeImmutable($key, $tz); if ($day < $first || $day >= $next) continue; $isToday = $key === $today->format('Y-m-d'); ?>
    <li class="cal-aday<?= $isToday ? ' is-today' : '' ?>">
      <<?= $dTag ?> class="cal-adate"><time datetime="<?= e($key) ?>"<?= $isToday ? ' aria-current="date"' : '' ?>><?= e(Calendar::dayLabel($day, 'long')) ?></time><?= $isToday ? ' <span class="cal-badge">' . e(lt('heute')) . '</span>' : '' ?></<?= $dTag ?>>
      <ul class="cal-alist" role="list">
        <?php foreach ($events as $o): ?>
        <li class="cal-aev"><span class="cal-atime"><?= e(Calendar::timeLabel($o)) ?></span><span class="cal-abody"><?= $event($o, true) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if (!empty($d['subscribe']) && $c['feed']): $feed = Calendar::feedUrls($t, \Core\Lang::current()); ?>
  <p class="cal-sub"><a class="cal-sublink" href="<?= e($feed['webcal']) ?>"><?= e(lt('Kalender abonnieren')) ?></a>
    <span class="cal-subalt"><?= e(lt('oder als Datei')) ?>: <a href="<?= e($feed['https']) ?>" type="text/calendar" download>.ics</a></span></p>
  <?php endif; ?>
</div>
