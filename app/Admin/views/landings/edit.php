<?php
/**
 * Landingpage anlegen/bearbeiten (Core\Landings).
 * @var ?\Core\Landing $landing  @var array $fields  @var array $values  @var array $errors  @var ?array $check  @var string $main
 */
use Core\Fields;
$isNew = $landing === null;
$root = $landing?->rootPage();
// Kontrast der Überschreibungen (WCAG 2.2 AA) – Werte der Website + Farben der Landingpage
$contrast = [];
if ($landing && $landing->design && \Core\Design::enabled()) {
    $merged = \Core\Design::values();
    foreach ($landing->design as $k => $v) $merged[$k] = $v;
    $contrast = array_values(array_filter(\Core\Design::checks($merged), fn($c) => !$c['ok']));
}
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/landingpages')) ?>"><?= e(__('Landingpages')) ?></a></p>
    <h1><?= e($isNew ? __('Neue Landingpage') : ($landing->label ?: $landing->host())) ?></h1>
    <?php if (!$isNew): ?><p class="adm-muted"><?= e($landing->origin() . url('/')) ?></p><?php endif; ?></div>
  <?php if (!$isNew): ?>
  <div class="adm-actions">
    <a class="adm-btn adm-btn--ghost" href="<?= e($landing->origin() . url('/')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('Auf der Landing-Domain ansehen')) ?> ↗</a>
    <?php if ($root): ?><a class="adm-btn adm-btn--ghost" href="<?= e(\Core\Pages::plainUrl($root)) ?>?edit=1"><?= e(__('Inhalte bearbeiten')) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <form class="adm-card" method="post" action="<?= e(url('/admin/landingpages/' . ($isNew ? 'new' : $landing->id))) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="adm-fields"><?= Fields::renderForm($fields, $values, $errors, 'f') ?></div>
    <?php if ($contrast): ?>
    <div class="adm-inline-box" role="note">
      <strong><?= e(__('Kontrast prüfen')) ?></strong>
      <ul><?php foreach ($contrast as $c): ?><li><?= e(__('{token} ({mode}): Kontrast {ratio}:1 – mindestens {min}:1 nötig (WCAG 2.2 AA).', ['token' => $c['token'], 'mode' => $c['mode'] === 'dark' ? __('dunkel') : __('hell'), 'ratio' => number_format($c['ratio'], 2, ',', ''), 'min' => number_format($c['min'], 1, ',', '')])) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e($isNew ? __('Landingpage anlegen') : __('Speichern')) ?></button></div>
  </form>

  <div>
    <?php if (!$isNew): ?>
    <section class="adm-card" id="status" aria-labelledby="lp-status-h">
      <h2 id="lp-status-h"><?= e(__('Status')) ?></h2>
      <?php if ($check): ?>
      <ul class="adm-list">
        <?php foreach ($check['hosts'] as $h => $c): ?>
        <li><code><?= e($h) ?></code>
          <?php if ($c['same']): ?><span class="adm-badge"><?= e(__('zeigt auf diese Installation')) ?></span>
          <?php elseif ($c['reachable']): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('erreichbar, aber nicht diese Landingpage')) ?></span>
          <?php else: ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('nicht erreichbar')) ?></span><?php endif; ?>
          <br><span class="adm-muted"><?= e(__('DNS: {ips}', ['ips' => $c['dns'] ? implode(', ', $c['dns']) : __('keine Adresse')])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="adm-muted"><?= e(__('Geprüft: {when}', ['when' => date_local((int) $check['at'], 'short')])) ?></p>
      <?php else: ?>
      <p class="adm-muted"><?= e(__('Prüft DNS und ob die Domain wirklich auf diese Installation zeigt (Anfrage an /health der Domain).')) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/landingpages/' . $landing->id . '/check')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Status prüfen')) ?></button></form>
    </section>
    <?php if ($landing->pages()): ?>
    <section class="adm-card" aria-labelledby="lp-pages-h">
      <h2 id="lp-pages-h"><?= e(__('Seiten auf der Landing-Domain')) ?></h2>
      <ul class="adm-list">
        <?php foreach ($landing->pages() as $p): ?>
        <li><a href="<?= e((string) $landing->absUrl($p)) ?>" target="_blank" rel="noopener noreferrer"><?= e($p['title']) ?></a>
          <span class="adm-muted"><?= e((string) $landing->relPath($p)) ?> · <?= e(__('Hauptdomain: {path}', ['path' => \Core\Pages::plainUrl($p)])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
    <?php endif; ?>
    <section class="adm-card" aria-labelledby="lp-check-h">
      <h2 id="lp-check-h"><?= e(__('Checkliste: Domain einrichten')) ?></h2>
      <ol>
        <li><?= e(__('DNS: A/AAAA-Eintrag (oder CNAME) der Domain auf den Server dieser Website.')) ?></li>
        <li><?= e(__('Plesk: Domain als Alias der Hauptdomain oder als zusätzliche Domain mit Dokumentstamm httpdocs/public; SSL-Zertifikat ausstellen.')) ?></li>
        <li><?= e(__('Domain in der Website-Konfiguration eintragen (Agentur): Netzwerk-Übersicht → Domains oder php bin/console site:hosts {site} add <domain> --landing.', ['site' => site()->key])) ?></li>
        <li><?= e(__('Landingpage hier anlegen und „Status prüfen“.')) ?></li>
        <li><?= e(__('Impressum und Datenschutz: Links der Landing-Domain führen automatisch auf die Seiten der Hauptdomain.')) ?></li>
      </ol>
      <p class="adm-muted"><?= e(__('Verwaltung und Anmeldung bleiben auf {url} – auf der Landing-Domain entstehen keine Cookies.', ['url' => $main])) ?></p>
    </section>
    <?php if (!$isNew): ?>
    <section class="adm-card adm-card--danger">
      <h2><?= e(__('Landingpage löschen')) ?></h2>
      <p class="adm-muted"><?= e(__('Die Seiten bleiben erhalten; die Domain zeigt danach die Startseite der Website.')) ?></p>
      <form method="post" action="<?= e(url('/admin/landingpages/' . $landing->id . '/delete')) ?>" data-confirm="<?= e(__('Landingpage „{name}“ löschen?', ['name' => $landing->label ?: $landing->host()])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--danger"><?= e(__('Löschen')) ?></button></form>
    </section>
    <?php endif; ?>
  </div>
</div>
