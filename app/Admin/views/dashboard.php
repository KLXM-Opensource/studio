<?php
/**
 * Übersicht der Verwaltung (DashboardController::index). Karten: Core\Dashboard\Dashboard, Zahlen: Core\Dashboard\Metrics,
 * Nachladen/Anpassen: resources/js/dashboard.js, Aussehen: resources/css/dashboard.css.
 * @var array $user  @var array $cards  @var array $figures  @var array $todos  @var ?array $recent  @var array $help  @var array $actions
 * @var bool $eager  @var bool $customize
 */
use Core\Dashboard\Dashboard;

$hour = (int) date('G');
$greet = $hour < 5 ? __('Guten Abend') : ($hour < 11 ? __('Guten Morgen') : ($hour < 18 ? __('Guten Tag') : __('Guten Abend')));
$first = trim(explode(' ', trim((string) ($user['name'] ?? '')))[0] ?? '');
$loc = \Core\I18n::locale();
$today = class_exists(\IntlDateFormatter::class)
    ? (string) (new \IntlDateFormatter($loc, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, date_default_timezone_get(), null, str_starts_with($loc, 'de') ? 'EEEE, d. MMMM y' : 'EEEE, d MMMM y'))->format(time())
    : date('d.m.Y');
$visible = array_filter($cards, fn($c) => !$c['hidden']);
$hiddenCards = array_filter($cards, fn($c) => $c['hidden']);
$keys = array_keys($cards);
$fmtTrend = function (?array $t): string {
    if (!$t) return '';
    $d = (int) $t['now'] - (int) $t['prev'];
    $cls = $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : 'is-flat');
    $arrow = $d > 0 ? '↑' : ($d < 0 ? '↓' : '→');
    return '<span class="dash-tile__trend ' . $cls . '">' . e(__('{n} {label} in 30 Tagen', ['n' => (int) $t['now'], 'label' => $t['label'] ?? ''])) . ' '
        . '<b><span aria-hidden="true">' . $arrow . ' </span>' . e(($d > 0 ? '+' : '') . $d) . '<span class="sr-only"> ' . e(__('im Vergleich zu den 30 Tagen davor ({n})', ['n' => (int) $t['prev']])) . '</span></b></span>';
};
?>
<header class="dash-hero">
  <div class="dash-hero__text">
    <p class="adm-eyebrow"><?= e(site_name()) ?></p>
    <h1><?= e($greet) ?><?= $first !== '' ? ', ' . e($first) : '' ?></h1>
    <p class="adm-muted"><?= e($today) ?> · <?= e($todos ? __('{n} Punkte auf Ihrer Liste', ['n' => count($todos)]) : __('Alles erledigt – schön!')) ?></p>
  </div>
  <nav class="dash-actions" aria-label="<?= e(__('Schnellaktionen')) ?>">
    <?php foreach ($actions as $a): ?>
    <a class="adm-btn<?= !empty($a['primary']) ? ' adm-btn--primary' : ' adm-btn--ghost' ?>" href="<?= e(url($a['href'])) ?>"<?= !empty($a['external']) ? ' target="_blank" rel="noopener"' : '' ?>><?= icon($a['icon']) ?><span><?= e($a['label']) ?></span><?php if (!empty($a['external'])): ?><span class="sr-only"> <?= e(__('(öffnet in neuem Tab)')) ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
</header>

<div class="dash-bar">
  <a class="dash-bar__btn" href="<?= e(url('/admin' . ($customize ? '' : '?anpassen=1'))) ?>" data-dash-customize aria-pressed="<?= $customize ? 'true' : 'false' ?>"><?= icon('sliders-horizontal') ?><span data-dash-customize-label><?= e($customize ? __('Fertig') : __('Übersicht anpassen')) ?></span></a>
  <form method="post" action="<?= e(url('/admin/api/dashboard/prefs')) ?>" class="dash-bar__reset" data-dash-form<?= $customize ? '' : ' hidden' ?>><?= csrf_field() ?><input type="hidden" name="do" value="reset"><button type="submit" class="dash-bar__btn"><?= icon('arrow-counter-clockwise') ?><span><?= e(__('Standard wiederherstellen')) ?></span></button></form>
  <p class="dash-bar__hint adm-muted" data-dash-hint<?= $customize ? '' : ' hidden' ?>><?= e(__('Karten mit den Pfeilen oder per Ziehen am Griff verschieben, mit dem Auge aus- und einblenden.')) ?></p>
</div>

<div class="dash-grid<?= $customize ? ' is-customizing' : '' ?>" id="dash" data-dash data-endpoint="<?= e(url('/admin/api/dashboard')) ?>">
<?php foreach ($cards as $key => $c):
  $hid = $c['hidden'];
  $closed = $c['closed'];
  $i = array_search($key, $keys, true);
  $bodyId = 'dash-' . $key . '-b';
  $lazy = $c['lazy'] && !$eager;
?>
  <section class="dash-card dash-card--<?= e($c['size']) ?><?= $hid ? ' is-hidden' : '' ?><?= $closed ? ' is-closed' : '' ?>" id="dash-<?= e($key) ?>" data-card="<?= e($key) ?>" aria-labelledby="dash-<?= e($key) ?>-h"<?= $hid && !$customize ? ' hidden' : '' ?>>
    <form method="post" action="<?= e(url('/admin/api/dashboard/prefs')) ?>" id="dash-f-<?= e($key) ?>" data-dash-form hidden><?= csrf_field() ?><input type="hidden" name="card" value="<?= e($key) ?>"></form>
    <header class="dash-card__head">
      <span class="dash-card__grip" data-dash-grip aria-hidden="true" title="<?= e(__('Ziehen zum Verschieben')) ?>"><?= icon('dots-six-vertical') ?></span>
      <h2 class="dash-card__h" id="dash-<?= e($key) ?>-h">
        <button type="submit" form="dash-f-<?= e($key) ?>" name="do" value="<?= $closed ? 'open' : 'close' ?>" class="dash-card__toggle" aria-expanded="<?= $closed ? 'false' : 'true' ?>" aria-controls="<?= e($bodyId) ?>" data-dash-toggle>
          <span class="dash-card__ico"><?= icon($c['icon']) ?></span><span><?= e($c['title']) ?></span><span class="dash-card__chev" aria-hidden="true"></span>
        </button>
      </h2>
      <div class="dash-card__tools" role="group" aria-label="<?= e(__('„{name}“ anordnen', ['name' => $c['title']])) ?>">
        <button type="submit" form="dash-f-<?= e($key) ?>" name="do" value="up" class="dash-tool" data-dash-move="up"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('caret-down', ['class' => 'dash-up']) ?><span class="sr-only"><?= e(__('Nach oben: {name}', ['name' => $c['title']])) ?></span></button>
        <button type="submit" form="dash-f-<?= e($key) ?>" name="do" value="down" class="dash-tool" data-dash-move="down"<?= $i === count($keys) - 1 ? ' disabled' : '' ?>><?= icon('caret-down') ?><span class="sr-only"><?= e(__('Nach unten: {name}', ['name' => $c['title']])) ?></span></button>
        <button type="submit" form="dash-f-<?= e($key) ?>" name="do" value="<?= $hid ? 'show' : 'hide' ?>" class="dash-tool" data-dash-hide aria-pressed="<?= $hid ? 'true' : 'false' ?>"><span class="dash-tool__on"><?= icon('eye') ?></span><span class="dash-tool__off"><?= icon('eye-slash') ?></span><span class="sr-only" data-dash-hide-label><?= e(($hid ? __('Einblenden: {name}', ['name' => $c['title']]) : __('Ausblenden: {name}', ['name' => $c['title']]))) ?></span></button>
      </div>
    </header>
    <div class="dash-card__body dash-body--<?= e($key) ?>" id="<?= e($bodyId) ?>"<?= $closed ? ' hidden' : '' ?><?= $lazy ? ' data-lazy="' . e($key) . '" aria-busy="true"' : '' ?>>
      <?php if ($lazy): ?>
        <div class="dash-skel" aria-hidden="true"><span></span><span></span><span></span></div>
        <noscript><p><a href="<?= e(url('/admin?alle=1')) ?>"><?= e(__('Statistiken anzeigen')) ?></a></p></noscript>
      <?php elseif ($key === 'figures'): ?>
        <?php if ($figures): ?>
        <ul class="dash-tiles" role="list">
          <?php foreach ($figures as $f): $lvl = isset($f['level']) && is_numeric($f['level']) ? (float) $f['level'] : null; $tag = !empty($f['href']) ? 'a' : 'div'; ?>
          <li class="dash-tile<?= !empty($f['hot']) ? ' is-hot' : '' ?><?= $lvl === null ? ' is-neutral' : '' ?>">
            <<?= $tag ?> class="dash-tile__in"<?= $tag === 'a' ? ' href="' . e(url((string) $f['href'])) . '"' : '' ?>>
              <span class="dash-tile__gauge"><?= Dashboard::gauge($lvl) ?><span class="dash-tile__value<?= mb_strlen((string) $f['value']) > 4 ? ' is-long' : '' ?>"><?= e((string) $f['value']) ?></span></span>
              <span class="dash-tile__text">
                <span class="dash-tile__label"><?= e((string) $f['label']) ?></span>
                <?php if (!empty($f['text'])): ?><span class="dash-tile__sub"><?= e((string) $f['text']) ?></span><?php endif; ?>
                <?= $fmtTrend(is_array($f['trend'] ?? null) ? $f['trend'] : null) ?>
              </span>
            </<?= $tag ?>>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="dash-note adm-muted"><?= e(__('Nur Zahlen aus dieser Website – ohne Besucher-Tracking. Pfeil: Vergleich mit den 30 Tagen davor.')) ?></p>
        <?php else: ?>
        <p class="adm-muted"><?= e(__('Für Ihre Rolle gibt es hier keine Kennzahlen.')) ?></p>
        <?php endif; ?>
      <?php elseif ($key === 'todo'): ?>
        <?php if ($todos): ?>
        <ol class="dash-todo" role="list">
          <?php foreach ($todos as $n => $t): if ($n === 6): ?>
        </ol>
        <details class="dash-more"><summary><?= e(__('{n} weitere anzeigen', ['n' => count($todos) - 6])) ?></summary><ol class="dash-todo" role="list" start="7">
          <?php endif; ?>
          <li class="dash-todo__item is-<?= e($t['tone']) ?>">
            <span class="dash-todo__ico"><?= icon($t['icon']) ?></span>
            <div class="dash-todo__main">
              <p class="dash-todo__title"><span class="dash-todo__count"><?= (int) $t['count'] ?></span> <?= e($t['title']) ?><?php if ($t['tone'] === 'err'): ?> <span class="dash-todo__tag"><?= e(__('dringend')) ?></span><?php endif; ?></p>
              <p class="dash-todo__text"><?= e($t['text']) ?></p>
            </div>
            <a class="adm-btn adm-btn--small<?= $n === 0 ? ' adm-btn--primary' : '' ?>" href="<?= e(url($t['href'])) ?>"><?= e($t['action']) ?><span class="sr-only">: <?= e($t['title']) ?></span></a>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php if (count($todos) > 6): ?></details><?php endif; ?>
        <?php else: ?>
        <div class="dash-done">
          <span class="dash-done__ico"><?= icon('confetti') ?></span>
          <p class="dash-done__title"><?= e(__('Alles erledigt')) ?></p>
          <p class="adm-muted"><?= e(__('Keine offenen Anfragen, Entwürfe oder Prüfhinweise. Zeit für neue Inhalte – oder eine Tasse Kaffee.')) ?></p>
        </div>
        <?php endif; ?>
      <?php elseif ($key === 'recent'): ?>
        <?= \Core\Theme::capture(ROOT . '/app/Admin/views/dashboard/_recent.php', ['recent' => $recent, 'user' => $user]) ?>
      <?php elseif ($key === 'help'): ?>
        <?= \Core\Theme::capture(ROOT . '/app/Admin/views/dashboard/_help.php', ['help' => $help, 'user' => $user]) ?>
      <?php else: ?>
        <?= Dashboard::render($key, $user, $c) ?>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>
<div class="sr-only" role="status" aria-live="polite" data-dash-live></div>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
