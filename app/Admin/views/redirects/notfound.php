<?php
/**
 * Administration → Weiterleitungen → Nicht gefunden (404): die letzten nicht gefundenen Adressen (Core\Redirects, ohne IP).
 * @var array $rows  @var string $q  @var array $suggest  @var int $count  @var bool $log
 */
use Core\Redirects\Redirects;

$base = '/admin/weiterleitungen';
$tab = '404';
$nRules = $count;
$n404 = Redirects::notFoundCount();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Weiterleitungen')) ?></p><h1><?= e(__('Nicht gefunden (404)')) ?></h1>
    <p class="adm-muted"><?= e(__('Adressen, die Besucher oder Suchmaschinen aufgerufen haben und die es nicht gibt – häufigste zuerst. Gespeichert werden nur Pfad, Anzahl und Zeitpunkte, keine IP-Adressen.')) ?></p></div>
  <?php // Seite, die Besucher bei 404 sehen (Core\NotFound): bearbeiten bzw. anlegen
  $nfPage = \Core\NotFound::exact(\Core\Lang::default()); ?>
  <?php if ($nfPage && can('pages.edit')): ?>
  <a class="adm-btn" href="<?= e(\Core\Pages::url($nfPage)) ?>?edit=1"><?= e(__('404-Seite bearbeiten')) ?></a>
  <?php elseif (!$nfPage && can('pages.manage')): ?>
  <form method="post" action="<?= e(url('/admin/pages/nicht-gefunden')) ?>"><?= csrf_field() ?><button class="adm-btn" title="<?= e(__('Eigene Seite für „Nicht gefunden“ mit Blöcken gestalten – statt der Standard-Fehlerseite des Kits')) ?>"><?= e(__('404-Seite anlegen')) ?></button></form>
  <?php endif; ?>
</header>

<?php include __DIR__ . '/_tabs.php'; ?>

<?php if (!$log): ?>
<div class="adm-flash adm-flash--info" role="status"><?= e(__('Das 404-Protokoll ist ausgeschaltet – neue Adressen kommen nicht hinzu.')) ?> <a href="<?= e(url($base)) ?>#einstellungen"><?= e(__('Einstellungen')) ?></a></div>
<?php endif; ?>

<form class="rv-toolbar" method="get" action="<?= e(url($base . '/404')) ?>" role="search" aria-label="<?= e(__('404-Protokoll durchsuchen')) ?>">
  <div class="f rv-toolbar__q"><label for="rd404-q"><?= e(__('Suche')) ?></label><input id="rd404-q" type="search" name="q" value="<?= e($q) ?>" placeholder="/agentur/"></div>
  <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
  <?php if ($q !== ''): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url($base . '/404')) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
</form>

<?php if (!$rows): ?>
<div class="adm-card rv-empty">
  <p><b><?= e($q !== '' ? __('Keine Adresse passt zur Suche.') : __('Keine nicht gefundenen Adressen.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Sobald jemand eine Adresse aufruft, die es nicht gibt, erscheint sie hier – mit Vorschlag für eine Weiterleitung.')) ?></p>
</div>
<?php else: ?>
<form method="post" action="<?= e(url($base . '/404')) ?>" data-rv-bulk>
  <?= csrf_field() ?>
  <div class="rv-bulk" role="group" aria-label="<?= e(__('Sammelaktion')) ?>">
    <label class="rv-check"><input type="checkbox" data-rv-all> <span><?= e(__('Alle auswählen')) ?></span></label>
    <span class="adm-muted" data-rv-count aria-live="polite"></span>
    <span class="rd-bulk__btns">
      <button class="adm-btn adm-btn--small" type="submit" name="op" value="ignore" data-rv-need><?= e(__('Aus dem Protokoll entfernen')) ?></button>
      <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" name="op" value="clear" formnovalidate data-confirm="<?= e(__('Das ganze 404-Protokoll leeren?')) ?>"><?= e(__('Protokoll leeren')) ?></button>
    </span>
  </div>
  <div class="adm-card adm-card--flush rd-tablewrap">
  <table class="adm-table rd-table">
    <caption class="sr-only"><?= e(__('Nicht gefundene Adressen')) ?></caption>
    <thead><tr>
      <th scope="col" class="rv-table__sel"><span class="sr-only"><?= e(__('Auswahl')) ?></span></th>
      <th scope="col"><?= e(__('Adresse')) ?></th><th scope="col" class="rd-num"><?= e(__('Aufrufe')) ?></th>
      <th scope="col"><?= e(__('Weiterleitung')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $rid = (int) $r['id']; $s = $suggest[$rid] ?? null; ?>
      <tr>
        <td class="rv-table__sel"><input type="checkbox" name="ids[]" value="<?= $rid ?>" aria-label="<?= e(__('{source} auswählen', ['source' => $r['path']])) ?>" data-rv-item></td>
        <td class="rd-src"><code><?= e($r['path']) ?></code>
          <span class="rd-meta"><?php if ((int) $r['internal']): ?><span class="adm-badge adm-badge--adm-warn" title="<?= e(__('Der Aufruf kam von einer Seite dieser Website – dort ist ein Link veraltet.')) ?>"><?= e(__('Interner Link')) ?></span><?php endif; ?>
          <span class="rd-last"><?= e(__('zuerst {first} · zuletzt {last}', ['first' => date_local((string) $r['first_seen'], 'short'), 'last' => date_local((string) $r['last_seen'], 'short')])) ?></span></span></td>
        <td class="rd-num"><?= (int) $r['hits'] ?></td>
        <td><div class="rd-actions">
          <?php if ($s): ?>
          <button class="adm-btn adm-btn--small adm-btn--primary" type="submit" form="rd404-c-<?= $rid ?>"><?= e(__('Zu „{title}“ weiterleiten', ['title' => $s['page']['title']])) ?></button>
          <?php endif; ?>
          <a class="adm-btn adm-btn--small<?= $s ? ' adm-btn--ghost' : '' ?>" href="<?= e(url($base . '/new') . '?from=404&source=' . rawurlencode((string) $r['path'])) ?>"><?= e($s ? __('Anderes Ziel …') : __('Weiterleitung anlegen')) ?><span class="sr-only"> <?= e($r['path']) ?></span></a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</form>
<?php // Ein-Klick-Vorschläge: eigene Formulare (verschachtelte Formulare sind nicht erlaubt), Knöpfe oben per form="…" ?>
<?php foreach ($rows as $r): $rid = (int) $r['id']; if (!isset($suggest[$rid])) continue; ?>
<form id="rd404-c-<?= $rid ?>" method="post" action="<?= e(url($base . '/404')) ?>" hidden><?= csrf_field() ?><input type="hidden" name="op" value="create"><input type="hidden" name="source" value="<?= e($r['path']) ?>"><input type="hidden" name="target" value="<?= e($suggest[$rid]['target']) ?>"></form>
<?php endforeach; ?>
<?php endif; ?>
