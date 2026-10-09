<?php
/**
 * Nächste Termine: Vorkommen ab heute (Wiederholungen einzeln), optional nur in den nächsten N Tagen.
 * Kern-Block, vom Theme überschreibbar (kits/{name}/blocks/upcoming.php).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Tables;

$t = Tables::findContent((string) ($d['table'] ?? ''));
$wrap = app()->theme->def['container_class'] ?? 'wrap';
$btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
if (!$t || !Calendar::enabled($t)) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="cal-empty">' . e(__('Bitte in der Seitenleiste eine Tabelle wählen, die als Kalender eingerichtet ist.')) . '</p></div>';
    return;
}
$c = Calendar::config($t);
[$where] = Calendar::blockWhere($t, $d);
$occ = Calendar::upcoming($t, max(1, min(50, (int) ($d['limit'] ?? 5))), max(0, (int) ($d['days'] ?? 0)), ['where' => $where, 'source' => (string) ($d['source'] ?? '')]);
\Core\StructuredData::events($t, $occ);   // schema.org Event
$layout = ($d['layout'] ?? 'list') === 'compact' ? 'compact' : 'list';
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$today = (new DateTimeImmutable('today', Calendar::tz()))->format('Y-m-d');
?>
<div class="<?= e($wrap) ?> cal cal-up cal-up--<?= e($layout) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="cal-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m cal-heading"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted cal-intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <?php if (!$occ): ?>
  <p class="cal-empty"><?= e($d['empty_text'] ?: lt('Zurzeit sind keine Termine geplant.')) ?></p>
  <?php else: ?>
  <ol class="cal-uplist" role="list">
    <?php foreach ($occ as $o): $e = $o['entry']; $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null; $title = Entries::title($t, $e);
      $loc = !empty($d['show_location']) && $c['location'] !== '' ? Entries::html($t, $e, $c['location'], ['plain' => true]) : ''; ?>
    <li class="cal-upitem<?= $o['start']->format('Y-m-d') === $today ? ' is-today' : '' ?>"><?= $b->targetEdit('entry:' . $t['handle'] . ':' . (int) $e['id'], $title) ?>
      <?php if ($layout === 'list'): ?>
      <span class="cal-upcal" aria-hidden="true"><span class="cal-upday"><?= e($o['start']->format('j')) ?></span><span class="cal-upmon"><?= e(Calendar::fmt($o['start'], 'LLL')) ?></span></span>
      <?php endif; ?>
      <div class="cal-upbody">
        <<?= $hTag ?> class="cal-upname"><?= $url ? '<a class="cal-link" href="' . e($url) . '">' . e($title) . '</a>' : e($title) ?></<?= $hTag ?>>
        <p class="cal-upmeta"><time datetime="<?= e($o['all_day'] ? $o['start']->format('Y-m-d') : $o['start']->format('Y-m-d\TH:iP')) ?>"><?= e(Calendar::when($o)) ?></time><?php if ($loc !== ''): ?><span aria-hidden="true"> · </span><span class="cal-loc"><?= $loc ?></span><?php endif; ?></p>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if (!empty($d['subscribe']) && $c['feed']): $feed = Calendar::feedUrls($t, \Core\Lang::current()); ?>
  <p class="cal-sub"><a class="cal-sublink" href="<?= e($feed['webcal']) ?>"><?= e(lt('Kalender abonnieren')) ?></a>
    <span class="cal-subalt"><?= e(lt('oder als Datei')) ?>: <a href="<?= e($feed['https']) ?>" type="text/calendar" download>.ics</a></span></p>
  <?php endif; ?>
  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="cal-more"><a class="<?= e($btn) ?>" href="<?= e(link_href((string) $d['more_link'])) ?>"<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></a></p>
  <?php endif; ?>
</div>
