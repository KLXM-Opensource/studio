<?php
/**
 * Administration → Funktionen & Erweiterungen (FeaturesController). Schalter je Funktion (gruppiert) und je Erweiterung,
 * Sicherheitsbestätigung (resources/js/_features.js, ohne JavaScript: Bestätigung direkt in der Zeile), Protokoll.
 * @var array $rows  @var array $exts  @var bool $canManage  @var bool $networked  @var bool $delegated  @var bool $integrator  @var array $log
 */
use Core\FeatureInfo;

$groups = FeatureInfo::groups();
$effects = FeatureInfo::effectLabels();
$total = $onCount = 0;
foreach ($rows as $g) foreach ($g as $row) { $total++; $onCount += $row['on'] ? 1 : 0; }
$extOn = count(array_filter($exts, fn($x) => $x['enabled']));
$catalogLabels = [];
foreach ($rows as $g) foreach ($g as $row) $catalogLabels[$row['key']] = $row['label'];
$lockText = fn(array $l) => ($l['via'] ?? '') === 'preset'
    ? __('Preset „{preset}“ in {file}', ['preset' => $l['preset'] ?? '', 'file' => $l['file']]) : $l['file'];
$releaseHelp = fn(string $file) => __('Freigeben: Eintrag in {file} entfernen oder auf dem Server „php bin/console features:release --site={site}“ ausführen (Sicherung .bak wird angelegt). Danach steuert diese Seite den Schalter.', ['file' => $file, 'site' => site()->key]);
$sw = function (bool $on, bool $disabled, string $label) {
    return '<span class="ft-sw' . ($on ? ' is-on' : '') . ($disabled ? ' is-disabled' : '') . '" aria-hidden="true"><span></span></span>'
        . '<span class="adm-sr">' . e($label) . '</span>';
};
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Funktionen & Erweiterungen')) ?></h1>
    <p class="adm-muted"><?= e(__('{on} von {total} Funktionen und {ext} von {exts} Erweiterungen sind auf dieser Website eingeschaltet.', ['on' => $onCount, 'total' => $total, 'ext' => $extOn, 'exts' => count($exts)])) ?></p></div>
</header>

<div class="ft-hint" role="note">
  <?= icon('shield-check', ['class' => 'ft-hint__ico']) ?>
  <div>
    <p class="ft-hint__h"><?= e(__('Aktivieren Sie nur, was diese Website wirklich braucht.')) ?></p>
    <p><?= e(__('Jede Funktion ist zusätzliche Angriffsfläche, Pflege und ggf. Datenverarbeitung. Abgeschaltete Funktionen sperren ihre Rechte, Menüpunkte, API- und MCP-Werkzeuge automatisch.')) ?></p>
  </div>
</div>

<?php if (!$canManage): ?>
<p class="adm-flash ft-readonly" role="status"><?= icon('lock') ?> <?= e(__('Freischaltung durch die Agentur: Diese Übersicht zeigt, was auf Ihrer Website eingeschaltet ist. Änderungen nimmt die Netzwerk-Administration vor.')) ?></p>
<?php endif; ?>

<?php if ($networked && $integrator): // Netzwerk: Freigabe für die Website-Administration ?>
<section class="adm-card ft-delegate" id="freigabe" aria-labelledby="ft-del-h">
  <div>
    <h2 id="ft-del-h"><?= e(__('Freigabe für die Website-Administration')) ?></h2>
    <p class="adm-muted"><?= e(__('Nur für Netzwerk-Administration und Integratoren sichtbar. Mit Freigabe dürfen Rollen mit dem Recht „Funktionen & Erweiterungen“ (Administration hat es) auf dieser Website selbst schalten. Standard: aus.')) ?></p>
  </div>
  <form method="post" action="<?= e(url('/admin/funktionen/freigabe')) ?>">
    <?= csrf_field() ?><input type="hidden" name="on" value="<?= $delegated ? '0' : '1' ?>">
    <button class="ft-toggle" type="submit" role="switch" aria-checked="<?= $delegated ? 'true' : 'false' ?>"><?= $sw($delegated, false, __('Freigabe')) ?><span><?= e($delegated ? __('Freigegeben') : __('Nicht freigegeben')) ?></span></button>
  </form>
</section>
<?php endif; ?>

<nav class="ft-toc" aria-label="<?= e(__('Gruppen')) ?>">
  <?php foreach ($groups as $gk => [$gl, $gi]): if (empty($rows[$gk])) continue; ?>
  <a href="#g-<?= e($gk) ?>"><?= icon($gi) ?> <?= e($gl) ?> <small><?= count(array_filter($rows[$gk], fn($x) => $x['on'])) ?>/<?= count($rows[$gk]) ?></small></a>
  <?php endforeach; ?>
  <a href="#erweiterungen"><?= icon('puzzle-piece') ?> <?= e(__('Erweiterungen')) ?> <small><?= $extOn ?>/<?= count($exts) ?></small></a>
  <a href="#protokoll"><?= icon('clock') ?> <?= e(__('Protokoll')) ?></a>
</nav>

<?php foreach ($groups as $gk => [$gl, $gi]): if (empty($rows[$gk])) continue; ?>
<section class="adm-card ft-group" id="g-<?= e($gk) ?>" aria-labelledby="g-<?= e($gk) ?>-h">
  <h2 id="g-<?= e($gk) ?>-h"><?= icon($gi) ?> <?= e($gl) ?></h2>
  <?php foreach ($rows[$gk] as $row):
    $id = 'f-' . preg_replace('~[^a-z0-9]~', '-', $row['key']);
    $locked = $row['lock'] !== null;
    $risk = (string) ($row['risk'] ?? '');
    $disabled = !$canManage || $locked;
    $needDep = !$row['on'] && $row['dep'] && !(\Core\Features::all()[$row['dep']] ?? true);
  ?>
  <article class="ft-row<?= $row['on'] ? ' is-on' : '' ?><?= $row['dormant'] ? ' is-dormant' : '' ?>" id="<?= e($id) ?>">
    <div class="ft-row__main">
      <h3 class="ft-row__h"><?= e($row['label']) ?> <code><?= e($row['key']) ?></code>
        <?php if ($row['dormant']): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('ruht – braucht „{dep}“', ['dep' => $catalogLabels[$row['dep']] ?? $row['dep']])) ?></span>
        <?php elseif ($row['on']): ?><span class="adm-badge"><?= e(__('an')) ?></span>
        <?php else: ?><span class="adm-badge adm-badge--muted"><?= e(__('aus')) ?></span><?php endif; ?>
        <?php if ($locked): ?><span class="adm-badge adm-badge--adm-warn ft-lock" title="<?= e($releaseHelp($row['lock']['file'])) ?>"><?= icon('lock') ?> <?= e(__('per Konfiguration festgelegt ({file})', ['file' => $lockText($row['lock'])])) ?></span><?php endif; ?>
        <?php if ($risk !== ''): ?><span class="adm-badge adm-badge--draft" title="<?= e(__('Einschalten verlangt Bestätigung und Passwort.')) ?>"><?= icon('warning') ?> <?= e(__('sicherheitsrelevant')) ?></span><?php endif; ?>
        <?php if ($row['extension']): ?><span class="adm-badge adm-badge--muted"><?= icon('puzzle-piece') ?> <?= e($exts[$row['extension']]['label'] ?? $row['extension']) ?></span><?php endif; ?>
      </h3>
      <?php if ($row['desc'] !== ''): ?><p class="ft-row__desc"><?= e($row['desc']) ?></p><?php endif; ?>
      <?php if ($row['dep'] || $row['dependents']): ?>
      <p class="ft-row__deps"><?= icon('link') ?>
        <?php if ($row['dep']): ?><?= e(__('Braucht: {dep}', ['dep' => $catalogLabels[$row['dep']] ?? $row['dep']])) ?><?php endif; ?>
        <?php if ($row['dep'] && $row['dependents']): ?> · <?php endif; ?>
        <?php if ($row['dependents']): ?><?= e(__('Voraussetzung für: {list}', ['list' => implode(', ', array_map(fn($k) => $catalogLabels[$k] ?? $k, array_filter($row['dependents'], fn($k) => isset($catalogLabels[$k])))) ])) ?><?php endif; ?>
      </p>
      <?php endif; ?>
      <details class="ft-more">
        <summary><?= e(__('Was passiert beim Einschalten')) ?></summary>
        <dl class="ft-effects">
          <?php if ($row['perms']): ?><dt><?= e(__('Rechte')) ?></dt><dd><?= e(implode(' · ', $row['perms'])) ?></dd><?php endif; ?>
          <?php foreach ($effects as $ek => $el): if (empty($row['effects'][$ek])) continue; ?><dt><?= e($el) ?></dt><dd><?= e($row['effects'][$ek]) ?></dd><?php endforeach; ?>
          <?php if (!$row['perms'] && !$row['effects']): ?><dt><?= e(__('Wirkung')) ?></dt><dd><?= e(__('Keine eigenen Rechte oder Menüpunkte – schaltet nur die Funktion selbst.')) ?></dd><?php endif; ?>
          <?php if ($risk !== ''): ?><dt><?= e(__('Sicherheit')) ?></dt><dd><?= e($risk) ?></dd><?php endif; ?>
          <?php if (!empty($row['caution'])): ?><dt><?= e(__('Vorsicht')) ?></dt><dd><?= e($row['caution']) ?></dd><?php endif; ?>
          <?php if ($locked): ?><dt><?= e(__('Freigeben')) ?></dt><dd><?= e($releaseHelp($row['lock']['file'])) ?></dd><?php endif; ?>
        </dl>
      </details>
    </div>
    <div class="ft-row__act">
      <?php if ($disabled || $needDep): ?>
        <span class="ft-toggle is-static" title="<?= e($locked ? $releaseHelp($row['lock']['file']) : ($needDep ? __('Braucht: {dep}', ['dep' => $catalogLabels[$row['dep']] ?? $row['dep']]) : __('Freischaltung durch die Agentur'))) ?>"><?= $sw($row['on'], true, $row['on'] ? __('an') : __('aus')) ?></span>
      <?php else: ?>
      <form method="post" action="<?= e(url('/admin/funktionen/funktion')) ?>" class="ft-form"<?= !$row['on'] && $risk !== '' ? ' data-ft-risk="' . e($risk) . '" data-ft-title="' . e(__('„{label}“ einschalten?', ['label' => $row['label']])) . '"' : '' ?>
        <?= $row['on'] && !empty($row['caution']) ? ' data-confirm="' . e($row['caution']) . '" data-confirm-ok="' . e(__('Ausschalten')) . '"' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($row['key']) ?>"><input type="hidden" name="on" value="<?= $row['on'] ? '0' : '1' ?>">
        <?php if (!$row['on'] && $risk !== ''): // ohne JavaScript: Bestätigung direkt hier; mit JavaScript im Dialog (resources/js/_features.js) ?>
        <div class="ft-confirm" data-ft-inline>
          <p class="ft-confirm__risk"><?= icon('warning') ?> <?= e($risk) ?></p>
          <label class="f-check"><input type="checkbox" name="confirm" value="1" required> <span><?= e(__('Ich habe den Hinweis gelesen und brauche diese Funktion.')) ?></span></label>
          <label class="adm-sr" for="<?= e($id) ?>-pw"><?= e(__('Passwort zur Bestätigung')) ?></label>
          <input id="<?= e($id) ?>-pw" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>">
        </div>
        <?php endif; ?>
        <button class="ft-toggle" type="submit" role="switch" aria-checked="<?= $row['on'] ? 'true' : 'false' ?>" aria-label="<?= e($row['label'] . ': ' . ($row['on'] ? __('ausschalten') : __('einschalten'))) ?>"><?= $sw($row['on'], false, '') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>

<section class="adm-card ft-group" id="erweiterungen" aria-labelledby="ft-ext-h">
  <h2 id="ft-ext-h"><?= icon('puzzle-piece') ?> <?= e(__('Erweiterungen')) ?></h2>
  <p class="adm-muted"><?= e(__('Installiert im Ordner extensions/ oder per Composer. Abschalten ist nicht destruktiv: Daten und Einstellungen bleiben, die Erweiterung wird nur nicht mehr gestartet.')) ?></p>
  <?php if (!$exts): ?><p><?= e(__('Keine Erweiterungen installiert.')) ?></p><?php endif; ?>
  <?php foreach ($exts as $name => $x):
    $id = 'x-' . preg_replace('~[^a-z0-9]~', '-', $name);
    $locked = $x['lock'] !== null;
    $disabled = !$canManage || $locked || ($x['enabled'] && $x['required']) || (!$x['enabled'] && !$x['compatible']);
    $checks = $x['requirements'] + $x['health'];
  ?>
  <article class="ft-ext<?= $x['enabled'] ? ' is-on' : '' ?>" id="<?= e($id) ?>">
    <div class="ft-row__main">
      <h3 class="ft-row__h"><?= e($x['label']) ?> <code><?= e($name) ?></code>
        <?php if ($x['enabled'] && $x['active']): ?><span class="adm-badge"><?= e(__('aktiv')) ?></span>
        <?php elseif ($x['enabled']): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('eingeschaltet, startet nicht')) ?></span>
        <?php else: ?><span class="adm-badge adm-badge--muted"><?= e(__('aus')) ?></span><?php endif; ?>
        <?php if ($locked): ?><span class="adm-badge adm-badge--adm-warn ft-lock" title="<?= e($releaseHelp($x['lock']['file'])) ?>"><?= icon('lock') ?> <?= e(__('per Konfiguration festgelegt ({file})', ['file' => $x['lock']['file']])) ?></span><?php endif; ?>
        <?php if ($x['required']): ?><span class="adm-badge adm-badge--muted"><?= e(__('fester Bestandteil')) ?></span><?php endif; ?>
        <?php if ($x['risk'] !== ''): ?><span class="adm-badge adm-badge--draft"><?= icon('warning') ?> <?= e(__('sicherheitsrelevant')) ?></span><?php endif; ?>
      </h3>
      <p class="ft-row__desc"><?= e($x['description']) ?></p>
      <p class="ft-ext__meta">
        <?= e(implode(' · ', array_filter([$x['version'] !== '' ? 'v' . $x['version'] : '', $x['author'], $x['license'] !== '' ? __('Lizenz {l}', ['l' => $x['license']]) : '',
          $x['package'] !== '' ? $x['package'] : $x['dir'], $x['requires'] !== '' ? __('Core {r}', ['r' => $x['requires']]) : '', $x['php'] !== '' ? 'PHP ' . $x['php'] : '']))) ?>
        <?php if ($x['homepage'] !== ''): ?> · <a href="<?= e($x['homepage']) ?>" target="_blank" rel="noopener"><?= e(__('Website')) ?></a><?php endif; ?>
      </p>
      <?php if ($checks): ?>
      <ul class="adm-checks ft-checks" aria-label="<?= e(__('Voraussetzungen')) ?>">
        <?php foreach ($checks as $cl => $ok): ?><li class="<?= $ok === true ? 'is-ok' : ($ok === false ? 'is-bad' : 'is-warn') ?>"><span aria-hidden="true"><?= $ok === true ? '✓' : ($ok === false ? '✗' : '!') ?></span><span><?= e($cl) ?><span class="adm-sr"> – <?= e($ok === true ? __('in Ordnung') : ($ok === false ? __('Fehler') : __('Warnung'))) ?></span></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($x['usage']): ?><p class="ft-ext__usage"><?= icon('info') ?> <?= e(__('Wird auf dieser Website verwendet von: {list}', ['list' => implode(' · ', $x['usage'])])) ?></p><?php endif; ?>
      <details class="ft-more">
        <summary><?= e(__('Was passiert beim Einschalten')) ?></summary>
        <dl class="ft-effects">
          <?php if ($x['provides']): ?><dt><?= e(__('Bringt mit')) ?></dt><dd><?= e(implode(' · ', $x['provides'])) ?></dd><?php endif; ?>
          <?php $c = $x['contrib']; if ($c): ?>
            <?php if ($c['nav']): ?><dt><?= e(__('Menü & Seiten')) ?></dt><dd><?= e(implode(' · ', array_column($c['nav'], 1))) ?></dd><?php endif; ?>
            <?php if ($c['perms']): ?><dt><?= e(__('Rechte')) ?></dt><dd><?= e(implode(' · ', array_map('__', $c['perms']))) ?></dd><?php endif; ?>
            <?php if ($c['blocks']): ?><dt><?= e(__('Blöcke')) ?></dt><dd><?= e(implode(' · ', $c['blocks'])) ?></dd><?php endif; ?>
            <?php if ($c['commands']): ?><dt><?= e(__('Befehle (Cron)')) ?></dt><dd><code><?= e(implode(' ', array_keys($c['commands']))) ?></code></dd><?php endif; ?>
            <?php if ($c['csp'] || $c['frontend']): ?><dt><?= e(__('Auf der Website')) ?></dt><dd><?= e(__('Ergänzt Ausgabe bzw. Sicherheitsrichtlinie (CSP) der Website.')) ?></dd><?php endif; ?>
          <?php endif; ?>
          <dt><?= e(__('Abschalten')) ?></dt><dd><?= e(__('Daten bleiben erhalten. Routen, Menüpunkte, Rechte, CSP-Quellen und Befehle der Erweiterung entfallen ab dem nächsten Aufruf; Cron-Befehle beenden sich ohne Fehler.')) ?></dd>
          <?php if ($x['risk'] !== ''): ?><dt><?= e(__('Sicherheit')) ?></dt><dd><?= e($x['risk']) ?></dd><?php endif; ?>
          <?php if ($x['docs']): // Kapitel im Handbuch gibt es erst, wenn die Erweiterung läuft – vorher README im Ordner ?><dt><?= e(__('Dokumentation')) ?></dt><dd><?php foreach ($x['docs'] as $dl => $dh): ?><?= $dh !== '' && $x['active'] ? '<a href="' . e(url($dh)) . '">' . e($dl) . '</a> · ' : '' ?><?php endforeach; ?><code><?= e($x['dir'] . '/README.md') ?></code></dd><?php endif; ?>
          <?php if ($locked): ?><dt><?= e(__('Freigeben')) ?></dt><dd><?= e($releaseHelp($x['lock']['file'])) ?></dd><?php endif; ?>
        </dl>
      </details>
    </div>
    <div class="ft-row__act">
      <?php if ($disabled): ?>
        <span class="ft-toggle is-static"><?= $sw($x['enabled'], true, $x['enabled'] ? __('an') : __('aus')) ?></span>
      <?php else: ?>
      <form method="post" action="<?= e(url('/admin/funktionen/erweiterung')) ?>" class="ft-form"<?= !$x['enabled'] && $x['risk'] !== '' ? ' data-ft-risk="' . e($x['risk']) . '" data-ft-title="' . e(__('„{label}“ aktivieren?', ['label' => $x['label']])) . '"' : '' ?>
        <?= $x['enabled'] ? ' data-confirm="' . e(__('„{label}“ deaktivieren? Daten bleiben erhalten.', ['label' => $x['label']])) . '" data-confirm-ok="' . e(__('Deaktivieren')) . '"' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="name" value="<?= e($name) ?>"><input type="hidden" name="on" value="<?= $x['enabled'] ? '0' : '1' ?>">
        <?php if (!$x['enabled'] && $x['risk'] !== ''): ?>
        <div class="ft-confirm" data-ft-inline>
          <p class="ft-confirm__risk"><?= icon('warning') ?> <?= e($x['risk']) ?></p>
          <label class="f-check"><input type="checkbox" name="confirm" value="1" required> <span><?= e(__('Ich habe den Hinweis gelesen und brauche diese Erweiterung.')) ?></span></label>
          <label class="adm-sr" for="<?= e($id) ?>-pw"><?= e(__('Passwort zur Bestätigung')) ?></label>
          <input id="<?= e($id) ?>-pw" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>">
        </div>
        <?php endif; ?>
        <button class="adm-btn adm-btn--small<?= $x['enabled'] ? '' : ' adm-btn--primary' ?>" type="submit"><?= e($x['enabled'] ? __('Deaktivieren') : __('Aktivieren')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>
</section>

<section class="adm-card" id="protokoll" aria-labelledby="ft-log-h">
  <h2 id="ft-log-h"><?= icon('clock') ?> <?= e(__('Letzte Änderungen')) ?></h2>
  <?php if (!$log): ?><p class="adm-muted"><?= e(__('Noch keine Änderungen über diese Seite.')) ?></p><?php else: ?>
  <table class="adm-table ft-log">
    <thead><tr><th><?= e(__('Zeit')) ?></th><th><?= e(__('Wer')) ?></th><th><?= e(__('Was')) ?></th><th><?= e(__('Änderung')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): $kind = ['feature' => __('Funktion'), 'extension' => __('Erweiterung'), 'delegate' => __('Freigabe'), 'release' => __('Konfiguration freigegeben')][$l['kind']] ?? $l['kind']; ?>
      <tr><td><?= e(substr((string) $l['created_at'], 0, 16)) ?></td><td><?= e((string) ($l['user_email'] ?? '–')) ?></td>
        <td><?= e($kind) ?>: <code><?= e($l['target']) ?></code><?= $l['detail'] ? ' <small class="adm-muted">' . e($l['detail']) . '</small>' : '' ?></td>
        <td><?= e(($l['old_value'] ? __($l['old_value']) : '–') . ' → ' . ($l['new_value'] ? __($l['new_value']) : '–')) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<dialog class="adm-dialog adm-dialog--small ft-dialog" id="ft-dialog" aria-labelledby="ft-dialog-h">
  <form method="dialog" class="ft-dialog__form">
    <h2 id="ft-dialog-h" data-ft-dtitle></h2>
    <p class="ft-confirm__risk"><?= icon('warning') ?> <span data-ft-drisk></span></p>
    <p class="adm-muted"><?= e(__('Aktivieren Sie nur, was diese Website wirklich braucht. Zur Sicherheit bestätigen Sie bitte mit Ihrem Passwort.')) ?></p>
    <div class="f"><label for="ft-dialog-pw"><?= e(__('Passwort')) ?></label><input id="ft-dialog-pw" type="password" autocomplete="current-password" data-ft-dpw></div>
    <label class="f-check"><input type="checkbox" data-ft-dok> <span><?= e(__('Ich habe den Hinweis gelesen und brauche das wirklich.')) ?></span></label>
    <div class="ft-dialog__btns">
      <button class="adm-btn" value="cancel" type="submit" formnovalidate><?= e(__('Abbrechen')) ?></button>
      <button class="adm-btn adm-btn--primary" value="ok" type="submit" data-ft-dgo disabled><?= e(__('Einschalten')) ?></button>
    </div>
  </form>
</dialog>
