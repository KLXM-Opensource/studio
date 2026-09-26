<?php
/** Grundeinstellungen → Landingpages (Core\Landings). @var list<\Core\Landing> $landings  @var string $main  @var list<string> $free */
$modes = ['own' => __('Eigene Domain'), 'mirror' => __('Spiegel')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Grundeinstellungen')) ?></p><h1><?= e(__('Landingpages')) ?></h1>
    <p class="adm-muted"><?= e(__('Weitere Domains zeigen eine Seite dieser Website – auf Wunsch mit ihren Unterseiten, eigenem Logo, Farben und Favicon. Inhalte, Formulare und Anfragen bleiben in diesem Projekt.')) ?>
      <a href="<?= e(url('/admin/hilfe/technik#landingpages')) ?>"><?= e(__('Technische Dokumentation →')) ?></a></p></div>
  <a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/landingpages/new')) ?>"><?= e(__('Neue Landingpage')) ?></a>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <section class="adm-card adm-card--flush" aria-labelledby="lp-list-h">
    <h2 id="lp-list-h" class="sr-only"><?= e(__('Landingpages')) ?></h2>
    <table class="adm-table">
      <thead><tr><th scope="col"><?= e(__('Domain')) ?></th><th scope="col"><?= e(__('Seite')) ?></th><th scope="col"><?= e(__('Canonical')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($landings as $l): $root = $l->rootPage(); ?>
        <tr>
          <td><strong><a href="<?= e(url('/admin/landingpages/' . $l->id)) ?>"><?= e($l->host()) ?></a></strong>
            <?php if (count($l->hosts) > 1): ?><br><span class="adm-muted"><?= e(implode(', ', array_slice($l->hosts, 1))) ?></span><?php endif; ?>
            <?php if ($l->label !== '' && $l->label !== $l->host()): ?><br><span class="adm-muted"><?= e($l->label) ?></span><?php endif; ?>
            <?php if (!$l->active): ?> <span class="adm-badge adm-badge--muted"><?= e(__('inaktiv')) ?></span><?php endif; ?></td>
          <td><?= $root ? e($root['title']) . '<br><span class="adm-muted">' . e(\Core\Pages::plainUrl($root)) . ($l->includeSubpages ? ' ' . e(__('+ Unterseiten')) : '') . '</span>' : '<span class="adm-badge adm-badge--adm-warn">' . e(__('Seite fehlt')) . '</span>' ?></td>
          <td><span class="adm-badge"><?= e($modes[$l->mode] ?? $l->mode) ?></span><?php if ($l->noindex): ?> <span class="adm-badge adm-badge--muted">noindex</span><?php endif; ?></td>
          <td class="adm-actions">
            <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($l->origin() . url('/')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('Ansehen')) ?> ↗<span class="sr-only"> <?= e($l->host()) ?></span></a>
            <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/landingpages/' . $l->id)) ?>"><?= e(__('Bearbeiten')) ?><span class="sr-only"> <?= e($l->host()) ?></span></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$landings): ?><tr><td colspan="4" class="adm-muted"><?= e(__('Noch keine Landingpages.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="adm-card" aria-labelledby="lp-hosts-h">
    <h2 id="lp-hosts-h"><?= e(__('Domains dieser Website')) ?></h2>
    <p class="adm-muted"><?= e(__('Hauptadresse (Verwaltung, Links von Landing-Domains): {url}', ['url' => $main])) ?></p>
    <?php if ($free): ?>
    <p><?= e(__('Noch frei für Landingpages:')) ?></p>
    <ul><?php foreach ($free as $h): ?><li><code><?= e($h) ?></code></li><?php endforeach; ?></ul>
    <?php else: ?>
    <p class="adm-muted"><?= e(__('Keine freie Domain. Neue Domains richtet die Agentur ein (siehe unten).')) ?></p>
    <?php endif; ?>
    <h3><?= e(__('Neue Domain einrichten')) ?></h3>
    <ol>
      <li><?= e(__('Domain beim Anbieter registrieren und per DNS (A/AAAA bzw. CNAME) auf den Server dieser Website zeigen lassen.')) ?></li>
      <li><?= e(__('Plesk: Domain als Alias der Hauptdomain oder als zusätzliche Domain mit Dokumentstamm httpdocs/public anlegen; SSL-Zertifikat (Let’s Encrypt) ausstellen.')) ?></li>
      <li><?= e(__('Domain der Website hinzufügen – Netzwerk-Übersicht → Domains oder:')) ?><br><code>php bin/console site:hosts <?= e(site()->key) ?> add www.beispiel.de --landing</code></li>
      <li><?= e(__('Hier eine Landingpage anlegen, Seite wählen und „Status prüfen“.')) ?></li>
    </ol>
  </section>
</div>
