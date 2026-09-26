<?php
/**
 * Administration → Weiterleitungen (Core\Redirects): Liste mit Suche und Sammelaktion, Adresse testen, Import/Export, Einstellungen.
 * @var array $f  @var array $list  @var string $test  @var ?array $result  @var int $count404  @var ?array $import  @var bool $auto  @var bool $log
 */
use Core\Redirects\Redirects;

$base = '/admin/weiterleitungen';
$qs = fn(array $p) => url($base) . '?' . http_build_query(array_filter($p + ['q' => $f['q'], 'code' => $f['code'], 'origin' => $f['origin'], 'sort' => $f['sort']],
    fn($v) => $v !== null && $v !== '' && $v !== 1));
$filtered = $f['q'] !== '' || $f['code'] !== '' || $f['origin'] !== '';
$origins = ['manual' => __('Von Hand'), 'auto' => __('Automatisch'), 'import' => __('Import')];
$when = fn(?string $t) => $t ? date_local($t, 'short') : '–';
$tab = 'list';
$nRules = Redirects::count();
$n404 = $count404;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Weiterleitungen')) ?></h1>
    <p class="adm-muted"><?= e(__('Alte Adressen führen zu Seiten, Pfaden oder anderen Websites. Weitergeleitet wird nur, wenn es unter der Adresse keine Seite gibt – bestehende Seiten gehen immer vor. Beim Umbenennen oder Verschieben veröffentlichter Seiten entstehen Weiterleitungen automatisch.')) ?>
      <a href="<?= e(url('/admin/hilfe/technik#weiterleitungen')) ?>"><?= e(__('Technische Dokumentation →')) ?></a></p></div>
  <a class="adm-btn adm-btn--primary" href="<?= e(url($base . '/new')) ?>"><?= e(__('Neue Weiterleitung')) ?></a>
</header>

<?php include __DIR__ . '/_tabs.php'; ?>

<div class="adm-grid2 adm-grid2--wide rd-grid">
  <div class="rd-main">
    <form class="rv-toolbar" method="get" action="<?= e(url($base)) ?>" role="search" aria-label="<?= e(__('Weiterleitungen filtern')) ?>">
      <div class="f rv-toolbar__q"><label for="rd-q"><?= e(__('Suche')) ?></label><input id="rd-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Alte Adresse, Ziel, Notiz …')) ?>"></div>
      <div class="f"><label for="rd-code"><?= e(__('Art')) ?></label><select id="rd-code" name="code"><option value=""><?= e(__('Alle')) ?></option>
        <?php foreach (Redirects::CODES as $c): ?><option value="<?= $c ?>"<?= $f['code'] === (string) $c ? ' selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="rd-origin"><?= e(__('Herkunft')) ?></label><select id="rd-origin" name="origin"><option value=""><?= e(__('Alle')) ?></option>
        <?php foreach ($origins as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['origin'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="rd-sort"><?= e(__('Sortierung')) ?></label><select id="rd-sort" name="sort">
        <?php foreach (['' => __('Neueste zuerst'), 'hits' => __('Meiste Treffer'), 'last' => __('Zuletzt benutzt'), 'source' => __('Alte Adresse (A–Z)')] as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
      <?php if ($filtered): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url($base)) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
    </form>

    <?php if (!$list['rows']): ?>
    <div class="adm-card rv-empty">
      <p><b><?= e($filtered ? __('Keine Weiterleitung passt zur Suche.') : __('Noch keine Weiterleitungen.')) ?></b></p>
      <p class="adm-muted"><?= e(__('Legen Sie eine an, importieren Sie eine Liste (z. B. aus einem Website-Umzug) oder übernehmen Sie Adressen aus dem 404-Protokoll.')) ?></p>
    </div>
    <?php else: ?>
    <form method="post" action="<?= e(url($base . '/bulk')) ?>" data-rv-bulk>
      <?= csrf_field() ?>
      <div class="rv-bulk" role="group" aria-label="<?= e(__('Sammelaktion')) ?>">
        <label class="rv-check"><input type="checkbox" data-rv-all> <span><?= e(__('Alle auswählen')) ?></span></label>
        <span class="adm-muted" data-rv-count aria-live="polite"></span>
        <span class="rd-bulk__btns">
          <button class="adm-btn adm-btn--small" type="submit" name="op" value="on" data-rv-need><?= e(__('Aktivieren')) ?></button>
          <button class="adm-btn adm-btn--small" type="submit" name="op" value="off" data-rv-need><?= e(__('Deaktivieren')) ?></button>
          <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" name="op" value="delete" data-rv-need data-confirm="<?= e(__('Ausgewählte Weiterleitungen löschen? Die alten Adressen enden danach mit 404.')) ?>"><?= e(__('Löschen')) ?></button>
        </span>
      </div>
      <div class="adm-card adm-card--flush rd-tablewrap">
      <table class="adm-table rd-table">
        <caption class="sr-only"><?= e(__('{n} Weiterleitungen', ['n' => $list['total']])) ?></caption>
        <thead><tr>
          <th scope="col" class="rv-table__sel"><span class="sr-only"><?= e(__('Auswahl')) ?></span></th>
          <th scope="col"><?= e(__('Alte Adresse')) ?></th><th scope="col"><?= e(__('Ziel')) ?></th>
          <th scope="col" class="rd-num"><?= e(__('Treffer')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($list['rows'] as $r): $rid = (int) $r['id']; $d = Redirects::describeTarget($r); $shadow = !(int) $r['wildcard'] && Redirects::pageAt((string) $r['source']); ?>
          <tr class="<?= (int) $r['active'] ? '' : 'rd-row--off' ?>">
            <td class="rv-table__sel"><input type="checkbox" name="ids[]" value="<?= $rid ?>" aria-label="<?= e(__('{source} auswählen', ['source' => $r['source']])) ?>" data-rv-item></td>
            <td class="rd-src"><a href="<?= e(url($base . '/' . $rid)) ?>"><code><?= e($r['source']) ?></code></a>
              <span class="rd-meta">
                <span class="adm-badge adm-badge--muted"><?= e($origins[$r['origin']] ?? $r['origin']) ?></span>
                <?php if (!(int) $r['active']): ?><span class="adm-badge adm-badge--muted"><?= e(__('inaktiv')) ?></span><?php endif; ?>
                <?php if ($shadow): ?><span class="adm-badge adm-badge--adm-warn" title="<?= e(__('Unter dieser Adresse gibt es eine Seite – sie geht vor.')) ?>"><?= e(__('Seite vorhanden')) ?></span><?php endif; ?>
              </span>
              <?php if ((string) $r['note'] !== ''): ?><span class="rd-note"><?= e($r['note']) ?></span><?php endif; ?></td>
            <td class="rd-target"><span class="rd-code rd-code--<?= (int) $r['code'] ?>"><?= (int) $r['code'] ?></span>
              <?php if ((int) $r['code'] === 410): ?><span class="adm-muted"><?= e(__('Entfernt')) ?></span>
              <?php else: ?><span class="rd-target__label"><?= e($d['label']) ?></span><?php if ($d['href'] !== '' && $d['href'] !== $d['label']): ?> <span class="rd-target__href"><?= e($d['href']) ?></span><?php endif; ?>
                <?php if ($d['missing']): ?> <span class="adm-badge adm-badge--adm-warn"><?= e(__('Ziel fehlt')) ?></span><?php endif; ?><?php endif; ?></td>
            <td class="rd-num"><?= (int) $r['hits'] ?><span class="rd-last"><?= e($when($r['last_hit'])) ?></span></td>
            <td><div class="adm-actions"><a class="adm-btn adm-btn--small" href="<?= e(url($base . '/' . $rid)) ?>"><?= e(__('Bearbeiten')) ?><span class="sr-only"> <?= e($r['source']) ?></span></a></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </form>
    <?php if ($list['pages'] > 1): ?>
    <nav class="dt-pager" aria-label="<?= e(__('Seiten')) ?>">
      <?php if ($list['page'] > 1): ?><a href="<?= e($qs(['page' => $list['page'] - 1])) ?>">← <?= e(__('Zurück')) ?></a><?php endif; ?>
      <?= e(__('Seite {page} / {pages}', ['page' => $list['page'], 'pages' => $list['pages']])) ?>
      <?php if ($list['page'] < $list['pages']): ?><a href="<?= e($qs(['page' => $list['page'] + 1])) ?>"><?= e(__('Weiter')) ?> →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="rd-side">
    <section class="adm-card" id="test" aria-labelledby="rd-test-h">
      <h2 id="rd-test-h"><?= e(__('Adresse testen')) ?></h2>
      <form method="get" action="<?= e(url($base)) ?>#test">
        <div class="f"><label for="rd-test"><?= e(__('Wohin führt …')) ?></label>
          <input id="rd-test" name="test" value="<?= e($test) ?>" placeholder="/alte-seite/" spellcheck="false" autocomplete="off" aria-describedby="rd-test-help">
          <p class="f-help" id="rd-test-help"><?= e(__('Pfad oder vollständige Adresse, gern mit ?parameter.')) ?></p></div>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Testen')) ?></button>
      </form>
      <?php if ($result): ?>
      <div class="rd-result rd-result--<?= e($result['status']) ?>" role="status">
        <p class="rd-result__path"><code><?= e($result['path']) ?></code></p>
        <p><strong><?= e(match ($result['status']) { 'page' => __('Seite'), 'redirect' => __('Weiterleitung'), 'gone' => __('Entfernt'), 'broken' => __('Ziel fehlt'), default => __('Nicht gefunden') }) ?>:</strong> <?= e($result['text']) ?></p>
        <?php if ($result['rule']): ?><p><a href="<?= e(url($base . '/' . (int) $result['rule']['id'])) ?>"><?= e(__('Regel #{id} bearbeiten', ['id' => $result['rule']['id']])) ?></a></p><?php endif; ?>
        <?php if ($result['status'] === 'none' && $result['path'] !== '' && $result['path'] !== '/'): ?><p><a class="adm-btn adm-btn--small" href="<?= e(url($base . '/new') . '?source=' . rawurlencode($result['path'])) ?>"><?= e(__('Weiterleitung anlegen')) ?></a></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="adm-card" id="import" aria-labelledby="rd-imp-h">
      <h2 id="rd-imp-h"><?= e(__('Import & Export')) ?></h2>
      <p class="adm-muted"><?= e(__('CSV mit Semikolon (alte Adresse;Ziel;Code;Notiz) oder JSON wie aus einem Umzugs-Mapping:')) ?></p>
      <pre class="rd-pre"><code>[{"from": "/alt/", "to": "/neu/"}]</code></pre>
      <?php if ($import): ?>
      <div class="adm-inline-box" role="status">
        <strong><?= e($import['dry'] ? __('Probelauf (nichts gespeichert)') : __('Letzter Import')) ?></strong>
        <p><?= e(__('{t} Zeilen: {c} neu, {u} geändert, {s} übersprungen, {l} Ziele mit Seiten verknüpft.', ['t' => $import['total'], 'c' => $import['created'], 'u' => $import['updated'], 's' => $import['skipped'], 'l' => $import['linked']])) ?></p>
        <?php if ($import['errors']): ?><ul class="rd-errors"><?php foreach ($import['errors'] as $line => $msg): ?><li><?= e(__('Zeile {n}: {msg}', ['n' => $line, 'msg' => $msg])) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
      <?php endif; ?>
      <form method="post" action="<?= e(url($base . '/import')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="f"><label for="rd-file"><?= e(__('Datei (CSV oder JSON)')) ?></label><input id="rd-file" type="file" name="file" accept=".csv,.json,.txt,text/csv,application/json"></div>
        <div class="f"><label for="rd-text"><?= e(__('… oder Zeilen einfügen')) ?></label><textarea id="rd-text" name="text" rows="4" spellcheck="false" data-kia-off placeholder="/alte-seite/;/neue-seite/;301;<?= e(__('Notiz')) ?>"></textarea></div>
        <div class="adm-checks">
          <label class="rv-check"><input type="checkbox" name="link" value="1" checked> <span><?= e(__('Ziele mit Seiten verknüpfen (folgen späteren Umbenennungen)')) ?></span></label>
          <label class="rv-check"><input type="checkbox" name="overwrite" value="1"> <span><?= e(__('Vorhandene alte Adressen überschreiben')) ?></span></label>
          <label class="rv-check"><input type="checkbox" name="dry" value="1"> <span><?= e(__('Nur prüfen (Probelauf)')) ?></span></label>
        </div>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Importieren')) ?></button>
      </form>
      <p class="rd-export"><?= e(__('Exportieren:')) ?> <a href="<?= e(url($base . '/export') . '?format=csv') ?>">CSV</a> · <a href="<?= e(url($base . '/export') . '?format=json') ?>">JSON</a></p>
    </section>

    <form class="adm-card" id="einstellungen" method="post" action="<?= e(url($base . '/einstellungen')) ?>">
      <?= csrf_field() ?>
      <h2><?= e(__('Einstellungen')) ?></h2>
      <div class="adm-checks">
        <label class="rv-check"><input type="checkbox" name="auto" value="1"<?= $auto ? ' checked' : '' ?> aria-describedby="rd-auto-help"> <span><?= e(__('Beim Umbenennen und Verschieben automatisch weiterleiten')) ?></span></label>
        <p class="f-help" id="rd-auto-help"><?= e(__('Ändert sich die Adresse einer veröffentlichten Seite, führt die alte Adresse dauerhaft (301) zur Seite – auch nach weiteren Änderungen.')) ?></p>
        <label class="rv-check"><input type="checkbox" name="log" value="1"<?= $log ? ' checked' : '' ?> aria-describedby="rd-log-help"> <span><?= e(__('404-Protokoll führen')) ?></span></label>
        <p class="f-help" id="rd-log-help"><?= e(__('Merkt sich die letzten {n} nicht gefundenen Adressen mit Anzahl – ohne IP-Adressen oder andere Angaben zu Besuchern.', ['n' => Redirects::MAX_404])) ?></p>
      </div>
      <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
    </form>
  </div>
</div>
