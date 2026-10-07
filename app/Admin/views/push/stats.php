<?php
/**
 * Mitteilungen → Statistik (nur Zähler, keine personenbezogenen Daten): Abos je Kanal, Zu- und Abgänge je Tag/Woche (maßstäbliche
 * Grafik), Versand und Zustellquote je Woche, Geräte der Redaktion je Ereignis, Protokoll der Zu-/Abgänge je Tag und Kanal.
 * @var string $topic  @var int $days  @var array $channels
 */
use Core\Push\Channels;
use Core\Push\Stats;
use Core\Dashboard\Dashboard;

$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$current = Stats::current();
$visitors = (int) app()->db->fetchValue("SELECT COUNT(*) FROM push_subscriptions WHERE topics IS NOT NULL AND topics != '' AND key_fp = ?", [\Core\Push\Keys::fingerprint()]);
$staffDevices = (int) app()->db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE user_id IS NOT NULL AND key_fp = ?', [\Core\Push\Keys::fingerprint()]);
$rows = $days === 90 ? Stats::weekly($topic === '' ? null : $topic, 13) : Stats::daily($topic === '' ? null : $topic, $days);
$labelled = [];
foreach ($rows as $k => $v) $labelled[($days === 90 ? __('Woche ab {date}', ['date' => date('d.m.', strtotime($k))]) : date('d.m.', strtotime($k)))] = $v;
$sc = Stats::scale($rows);
$sumIn = array_sum(array_column($rows, 'sub'));
$sumOut = array_sum(array_column($rows, 'lost'));
$per30 = Stats::daily(null, 30);
$deliv = Stats::deliveries(12);
$msgs30 = (int) app()->db->fetchValue("SELECT COUNT(*) FROM push_messages WHERE created_at >= ? AND kind != 'test' AND COALESCE(status, 'sent') NOT IN ('scheduled', 'canceled')", [date('Y-m-d H:i:s', time() - 30 * 86400)]);
$d30 = array_reduce(array_slice($deliv, -5), fn($c, $w) => ['sent' => $c['sent'] + $w['sent'], 'lost' => $c['lost'] + $w['lost']], ['sent' => 0, 'lost' => 0]);
$rate = $d30['sent'] + $d30['lost'] > 0 ? (int) round($d30['sent'] / ($d30['sent'] + $d30['lost']) * 100) : null;
$label = fn(string $t) => Channels::label($t);
$link = fn(array $p) => url($b . '/statistik') . '?' . http_build_query(array_filter(['kanal' => $topic ?: null, 'tage' => $days !== 30 ? $days : null] + $p, fn($v) => $v !== null && $v !== ''));
$log = Stats::log(60);
?>
<h1 class="adm-sr"><?= e(__('Statistik')) ?></h1>
<div class="pm pm--page">
  <div class="pm-bar"><div class="pm-bar__title"><h2><?= e(__('Statistik')) ?></h2><small><?= e(__('Nur Zähler – keine Geräte, Adressen oder Namen. Zugestellt heißt: vom Push-Dienst angenommen.')) ?></small></div></div>
  <div class="pm-scroll pm-statpage">
    <div class="pm-tiles">
      <div class="pm-tile"><span><?= e(__('Abos von Besuchern')) ?></span><strong><?= e(fmt()->number($visitors)) ?></strong><small><?= e(__('+{in} / −{out} in 30 Tagen', ['in' => array_sum(array_column($per30, 'sub')), 'out' => array_sum(array_column($per30, 'lost'))])) ?></small></div>
      <div class="pm-tile"><span><?= e(__('Geräte der Redaktion')) ?></span><strong><?= e(fmt()->number($staffDevices)) ?></strong><small><?= e(__('Konto → Benachrichtigungen')) ?></small></div>
      <div class="pm-tile"><span><?= e(__('Mitteilungen (30 Tage)')) ?></span><strong><?= e(fmt()->number($msgs30)) ?></strong><small><?= e(__('von Hand und automatisch')) ?></small></div>
      <div class="pm-tile"><span><?= e(__('Zustellquote (5 Wochen)')) ?></span><strong><?= $rate === null ? '–' : $rate . ' %' ?></strong><small><?= e(__('{sent} zugestellt, {lost} nicht', ['sent' => $d30['sent'], 'lost' => $d30['lost']])) ?></small></div>
    </div>

    <section class="set-group">
      <div class="set-group__head"><h3 class="set-group__title"><?= e(__('Zu- und Abgänge')) ?></h3>
        <form class="pm-filter" method="get" action="<?= e(url($b . '/statistik')) ?>">
          <label class="adm-sr" for="ps-kanal"><?= e(__('Kanal')) ?></label>
          <select id="ps-kanal" name="kanal" data-autosubmit><option value=""><?= e(__('Alle Kanäle')) ?></option>
            <?php foreach ($channels as $t => $c): ?><option value="<?= e($t) ?>"<?= $topic === $t ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            <option value="<?= e(Stats::STAFF) ?>"<?= $topic === Stats::STAFF ? ' selected' : '' ?>><?= e(__('Geräte der Redaktion')) ?></option></select>
          <label class="adm-sr" for="ps-tage"><?= e(__('Zeitraum')) ?></label>
          <select id="ps-tage" name="tage" data-autosubmit><?php foreach ([7 => __('7 Tage'), 30 => __('30 Tage'), 90 => __('13 Wochen')] as $k => $l): ?><option value="<?= $k ?>"<?= $days === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <noscript><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Anzeigen')) ?></button></noscript>
        </form></div>
      <div class="set-list pm-chartbox">
        <p class="pm-chart__sum"><span class="pm-key pm-key--up"></span> <?= e(__('{n} neue Abos', ['n' => $sumIn])) ?> <span class="pm-key pm-key--down"></span> <?= e(__('{n} abbestellt oder abgelaufen', ['n' => $sumOut])) ?>
          <span class="adm-muted"> · <?= e($topic === '' ? __('alle Kanäle') : $label($topic)) ?></span></p>
        <figure class="pm-chart">
          <div class="pm-chart__axis" aria-hidden="true"><span style="top:0"><?= $sc['up'] ?></span><span style="top:<?= Stats::zero($rows) ?>%">0</span><?php if ($sc['down']): ?><span style="top:100%">−<?= $sc['down'] ?></span><?php endif; ?></div>
          <?= Stats::chart($labelled, __('Zu- und Abgänge: {in} neu, {out} weg', ['in' => $sumIn, 'out' => $sumOut])) ?>
          <figcaption class="dash-axis" aria-hidden="true"><span><?= e((string) array_key_first($labelled)) ?></span><span><?= e((string) array_key_last($labelled)) ?></span></figcaption>
        </figure>
        <details class="dash-table"><summary><?= e(__('Als Tabelle anzeigen')) ?></summary>
          <table class="adm-table"><thead><tr><th scope="col"><?= e($days === 90 ? __('Woche') : __('Tag')) ?></th><th scope="col"><?= e(__('Neu')) ?></th><th scope="col"><?= e(__('Weg')) ?></th></tr></thead><tbody>
          <?php foreach (array_reverse($labelled, true) as $l => $v): ?><tr><th scope="row"><?= e($l) ?></th><td><?= (int) $v['sub'] ?></td><td><?= (int) $v['lost'] ?></td></tr><?php endforeach; ?>
          </tbody></table></details>
      </div>
    </section>

    <div class="pm-two">
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Abos je Kanal')) ?></h3>
        <div class="set-list">
          <?php if (!$channels): ?><div class="set-row"><span class="set-row__sub"><?= e(__('Noch keine Kanäle.')) ?></span></div><?php endif; ?>
          <?php foreach ($channels as $t => $c): $d = Stats::daily($t, 30); ?>
          <a class="set-row set-row--link" href="<?= e($link(['kanal' => $t])) ?>"><div class="set-row__main"><span class="set-row__label"><?= e($c['name']) ?><?= $c['archived'] ? ' <span class="adm-badge adm-badge--muted">' . e(__('archiviert')) . '</span>' : '' ?></span>
            <span class="set-row__sub"><?= e(__('+{in} / −{out} in 30 Tagen', ['in' => array_sum(array_column($d, 'sub')), 'out' => array_sum(array_column($d, 'lost'))])) ?></span></div>
            <div class="set-row__ctl"><strong><?= e(fmt()->number($current[$t] ?? 0)) ?></strong></div></a>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Redaktion: Geräte je Anlass')) ?></h3>
        <div class="set-list">
          <?php foreach (Stats::staff() as $k => $s): ?>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($s['label']) ?></span><span class="set-row__sub"><code><?= e($k) ?></code></span></div>
            <div class="set-row__ctl"><?= e(__('{d} Gerät(e) · {p} Person(en)', ['d' => $s['devices'], 'p' => $s['people']])) ?></div></div>
          <?php endforeach; ?>
        </div>
        <p class="set-group__note"><?= e(__('Geräte, deren Person den Anlass unter Konto → Benachrichtigungen eingeschaltet hat und ihn sehen darf.')) ?></p>
      </section>
    </div>

    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Versand je Woche')) ?></h3>
      <div class="set-list pm-chartbox">
        <?php $dRows = array_map(fn($k, $w) => [__('Woche ab {date}', ['date' => date('d.m.', strtotime($k))]) . ' · ' . __('{n} Mitteilungen', ['n' => $w['messages']]), $w['sent']], array_keys($deliv), $deliv); ?>
        <figure class="dash-chart dash-chart--small"><?= Dashboard::bars($dRows, __('Zugestellte Mitteilungen je Woche, letzte 12 Wochen')) ?>
          <figcaption class="dash-axis" aria-hidden="true"><span><?= e(date('d.m.', strtotime((string) array_key_first($deliv)))) ?></span><span><?= e(__('diese Woche')) ?></span></figcaption></figure>
        <table class="adm-table pm-table"><thead><tr><th scope="col"><?= e(__('Woche')) ?></th><th scope="col"><?= e(__('Mitteilungen')) ?></th><th scope="col"><?= e(__('zugestellt')) ?></th><th scope="col"><?= e(__('nicht zugestellt')) ?></th><th scope="col"><?= e(__('Quote')) ?></th></tr></thead><tbody>
        <?php foreach (array_reverse($deliv, true) as $k => $w): if (!$w['messages']) continue; ?><tr><th scope="row"><?= e(date('d.m.Y', strtotime($k))) ?></th><td><?= $w['messages'] ?></td><td><?= $w['sent'] ?></td><td><?= $w['lost'] ?></td><td><?= $w['rate'] === null ? '–' : $w['rate'] . ' %' ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </section>

    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Protokoll (60 Tage)')) ?></h3>
      <div class="set-list">
        <?php if (!$log): ?><div class="set-row"><span class="set-row__sub"><?= e(__('Noch keine Zu- oder Abgänge.')) ?></span></div><?php else: ?>
        <div class="set-scroll"><table class="adm-table pm-table"><thead><tr><th scope="col"><?= e(__('Tag')) ?></th><th scope="col"><?= e(__('Kanal')) ?></th><th scope="col"><?= e(__('abonniert')) ?></th><th scope="col"><?= e(__('abbestellt')) ?></th><th scope="col"><?= e(__('abgelaufen')) ?></th></tr></thead><tbody>
        <?php foreach ($log as $l): ?><tr><td><?= e(date('d.m.Y', strtotime($l['day']))) ?></td><td><?= e($label($l['topic'])) ?></td><td><?= $l['sub'] ? '+' . $l['sub'] : '–' ?></td><td><?= $l['unsub'] ? '−' . $l['unsub'] : '–' ?></td><td><?= $l['gone'] ? '−' . $l['gone'] : '–' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
      </div>
      <p class="set-group__note"><?= e(__('Gespeichert werden nur Tageszähler je Kanal (2 Jahre). „Abgelaufen“: Der Push-Dienst meldet das Abo als ungültig, es scheiterte mehrmals oder gehörte zu alten Schlüsseln.')) ?></p>
    </section>
  </div>
</div>
