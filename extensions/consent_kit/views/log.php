<?php
/**
 * Protokoll: Kennzahlen (30 Tage, je ID nur die letzte Entscheidung), Filter, Tabelle, CSV-Export, Bereinigen.
 * @var array $filters  @var array $rows  @var int $total  @var int $pageNo  @var array $stats  @var array $domains  @var array $settings
 */
use MyCms\Consent\Log;

$qs = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$pages = max(1, (int) ceil($total / 50));
?>
<div class="adm-stats">
  <div class="adm-stat"><strong><?= (int) $stats['total'] ?></strong><span><?= e(__('Entscheidungen (30 Tage)')) ?></span></div>
  <?php foreach (['accept_all', 'reject_all', 'custom'] as $a): $n = (int) ($stats['actions'][$a] ?? 0); ?>
  <div class="adm-stat"><strong><?= $stats['total'] ? round($n * 100 / $stats['total']) : 0 ?> %</strong><span><?= e(__(Log::ACTIONS[$a])) ?> (<?= $n ?>)</span></div>
  <?php endforeach; ?>
</div>
<?php if ($stats['services']): ?>
<section class="adm-card">
  <h2><?= e(__('Zustimmung je Dienst (30 Tage)')) ?></h2>
  <ul class="ck-bars">
    <?php foreach ($stats['services'] as $k => $n): $pct = $stats['total'] ? round($n * 100 / $stats['total']) : 0; ?>
    <li><span><?= e((string) $k) ?></span><meter min="0" max="100" value="<?= $pct ?>" aria-label="<?= e((string) $k) ?>"><?= $pct ?> %</meter><span><?= $pct ?> % (<?= (int) $n ?>)</span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="adm-card">
  <form method="get" action="<?= e(url('/admin/consent/log')) ?>" class="ck-logfilter">
    <div class="f"><label for="lf-id"><?= e(__('Einwilligungs-ID')) ?></label><input id="lf-id" name="id" value="<?= e($filters['id']) ?>" spellcheck="false"></div>
    <div class="f"><label for="lf-a"><?= e(__('Entscheidung')) ?></label><select id="lf-a" name="action"><option value=""><?= e(__('alle')) ?></option>
      <?php foreach (Log::ACTIONS as $k => $l): ?><option value="<?= e($k) ?>"<?= $filters['action'] === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="lf-f"><?= e(__('von')) ?></label><input type="date" id="lf-f" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="f"><label for="lf-t"><?= e(__('bis')) ?></label><input type="date" id="lf-t" name="to" value="<?= e($filters['to']) ?>"></div>
    <?php if (count($domains) > 1): ?><div class="f"><label for="lf-d"><?= e(__('Domain')) ?></label><select id="lf-d" name="domain"><option value=""><?= e(__('alle')) ?></option>
      <?php foreach ($domains as $k => $l): ?><option value="<?= e($k) ?>"<?= $filters['domain'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <p class="ck-logfilter__btns"><button class="adm-btn adm-btn--small"><?= e(__('Filtern')) ?></button>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/consent/log.csv') . ($qs ? '?' . $qs : '')) ?>"><?= e(__('CSV-Export')) ?></a></p>
  </form>
</section>

<section class="adm-card adm-card--flush">
  <table class="adm-table">
    <caption class="sr-only"><?= e(__('Protokoll der Entscheidungen')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Zeitpunkt')) ?></th><th scope="col"><?= e(__('Einwilligungs-ID')) ?></th><th scope="col"><?= e(__('Entscheidung')) ?></th><th scope="col"><?= e(__('Akzeptiert')) ?></th><th scope="col"><?= e(__('Abgelehnt')) ?></th><th scope="col"><?= e(__('Stand')) ?></th><th scope="col">GPC</th><th scope="col"><?= e(__('Sprache')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(date('d.m.Y H:i', strtotime((string) $r['created_at']))) ?></td>
        <td><a href="<?= e(url('/admin/consent/log?id=' . rawurlencode((string) $r['consent_id']))) ?>"><code><?= e(substr((string) $r['consent_id'], 0, 8)) ?>…</code></a></td>
        <td><?= e(__(Log::ACTIONS[$r['action']] ?? $r['action'])) ?></td>
        <td><?= e(implode(', ', $r['accepted']) ?: '–') ?></td>
        <td class="adm-muted"><?= e(implode(', ', $r['rejected']) ?: '–') ?></td>
        <td><?php if ($r['revision_id']): ?><a href="<?= e(url('/admin/consent/revision/' . (int) $r['revision_id'])) ?>">#<?= (int) $r['revision_id'] ?></a><?php endif; ?></td>
        <td><?= $r['gpc'] ? e(__('ja')) : '–' ?></td>
        <td><?= e((string) $r['lang']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="adm-muted"><?= e(__('Keine Einträge.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php if ($pages > 1): ?>
  <nav class="ck-pager" aria-label="<?= e(__('Seiten')) ?>">
    <?php if ($pageNo > 1): ?><a href="<?= e(url('/admin/consent/log') . '?' . http_build_query(array_filter($filters) + ['page' => $pageNo - 1])) ?>">← <?= e(__('neuer')) ?></a><?php endif; ?>
    <span><?= e(__('Seite {n} von {m}', ['n' => $pageNo, 'm' => $pages])) ?></span>
    <?php if ($pageNo < $pages): ?><a href="<?= e(url('/admin/consent/log') . '?' . http_build_query(array_filter($filters) + ['page' => $pageNo + 1])) ?>"><?= e(__('älter')) ?> →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>

<section class="adm-card">
  <h2><?= e(__('Aufbewahrung')) ?></h2>
  <p class="adm-muted"><?= e(__('Gespeichert werden Einwilligungs-ID, Zeitpunkt, Domain, Stand, Entscheidung, Dienste, GPC-Signal und Sprache – keine IP-Adresse, kein User-Agent, keine aufgerufene Seite. Aufbewahrung: {days}. Bereinigt wird täglich nebenbei in der Verwaltung und per Cron: php bin/console consent:purge --site={site}.', ['days' => (int) $settings['retention'] ? __('{n} Tage', ['n' => (int) $settings['retention']]) : __('unbegrenzt'), 'site' => site()->key])) ?></p>
  <form method="post" action="<?= e(url('/admin/consent/log/purge')) ?>" data-confirm="<?= e(__('Einträge älter als die Aufbewahrungsfrist jetzt löschen?')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small"><?= e(__('Jetzt bereinigen')) ?></button></form>
</section>
