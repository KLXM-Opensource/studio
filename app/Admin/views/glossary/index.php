<?php
/**
 * Verwaltung → Glossar (Core\Glossary): einrichten, Begriffe mit Vorkommen und Hinweisen, schnell hinzufügen, Import/Export, Einstellungen.
 * @var ?array $t  @var array $terms  @var array $checks  @var array $occ  @var array $settings  @var ?array $import  @var bool $ai  @var ?string $overview
 */
use Core\Glossary\Glossary;

$base = '/admin/glossar';
$schema = can('data.schema');
$pub = count(array_filter($terms, fn($x) => !$x['draft']));
$byId = [];
foreach ($checks as $c) $byId[$c['id']][] = $c;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Inhalte')) ?></p><h1><?= e(__('Glossar')) ?></h1>
    <p class="adm-muted"><?= e(__('Fachbegriffe kurz erklärt: Auf der Website bekommt das erste Vorkommen eines Begriffs eine gepunktete Unterstreichung – ein Klick oder Tippen zeigt die Kurz-Erklärung mit Link ins Glossar. Markiert werden nur veröffentlichte Begriffe; Entwürfe sieht nur die angemeldete Redaktion.')) ?>
      <a href="<?= e(url('/admin/hilfe#glossar')) ?>"><?= e(__('Handbuch →')) ?></a></p></div>
  <?php if ($t): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/data/' . $t['handle'] . '/new')) ?>"><?= e(__('Neuer Begriff')) ?></a><?php endif; ?>
</header>

<?php if (!$t): ?>
<section class="adm-card gls-setup" aria-labelledby="gls-setup-h">
  <h2 id="gls-setup-h"><?= e(__('Glossar einrichten')) ?></h2>
  <p><?= e(__('Legt die Datentabelle „Glossar“ an (Begriff, Varianten, Kurz-Erklärung, ausführliche Erklärung, Kategorie, Link), dazu Detailseiten unter /glossar/… und eine Übersichtsseite /glossar mit dem Block „Glossar“ (A–Z, Suchfeld).')) ?></p>
  <?php if ($schema): ?>
  <form method="post" action="<?= e(url($base . '/einrichten')) ?>">
    <?= csrf_field() ?>
    <label class="rv-check"><input type="checkbox" name="publish" value="1"> <span><?= e(__('Übersichtsseite gleich veröffentlichen (sonst als Entwurf)')) ?></span></label>
    <p><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Glossar einrichten')) ?></button></p>
  </form>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Einrichten kann die Administration (Recht „Tabellen und Felder anlegen“).')) ?></p>
  <?php endif; ?>
</section>
<?php return; endif; ?>

<div class="adm-grid2 adm-grid2--wide rd-grid">
  <div class="rd-main">
    <p class="gls-stats">
      <span><b><?= count($terms) ?></b> <?= e(__('Begriffe')) ?></span> · <span><b><?= $pub ?></b> <?= e(__('veröffentlicht')) ?></span>
      · <a href="<?= e(url('/admin/data/' . $t['handle'])) ?>"><?= e(__('In der Datentabelle bearbeiten')) ?></a>
      <?php if ($overview): ?> · <a href="<?= e($overview) ?>" target="_blank" rel="noopener"><?= e(__('Übersicht auf der Website')) ?> ↗</a><?php endif; ?>
    </p>

    <?php $warn = array_values(array_filter($checks, fn($c) => $c['level'] === 'warn')); $info = array_values(array_filter($checks, fn($c) => $c['level'] !== 'warn')); ?>
    <?php if ($checks): ?>
    <section class="adm-card gls-checks" aria-labelledby="gls-checks-h">
      <h2 id="gls-checks-h"><?= e(__('Hinweise')) ?> <small class="adm-muted">(<?= count($warn) ?>)</small></h2>
      <?php if (!$warn): ?><p class="adm-muted"><?= e(__('Keine Probleme gefunden.')) ?></p><?php endif; ?>
      <ul>
        <?php foreach (array_slice($warn, 0, 40) as $c): ?>
        <li class="gls-check"><span class="adm-badge adm-badge--adm-warn"><?= e(__('Prüfen')) ?></span>
          <a href="<?= e(url('/admin/data/' . $t['handle'] . '/' . (int) $c['id'])) ?>"><?= e($c['text']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($info): ?>
      <details class="gls-info"><summary><?= e(__('Überschneidungen: {n} – dort gilt jeweils der längere Begriff', ['n' => count($info)])) ?></summary>
        <ul><?php foreach (array_slice($info, 0, 60) as $c): ?><li><a href="<?= e(url('/admin/data/' . $t['handle'] . '/' . (int) $c['id'])) ?>"><?= e($c['text']) ?></a></li><?php endforeach; ?></ul>
      </details>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!$terms): ?>
    <div class="adm-card rv-empty"><p><b><?= e(__('Noch keine Begriffe.')) ?></b></p>
      <p class="adm-muted"><?= e(__('Legen Sie Begriffe an, fügen Sie sie rechts schnell hinzu oder importieren Sie eine CSV-Datei.')) ?></p></div>
    <?php else: ?>
    <div class="adm-card adm-card--flush rd-tablewrap">
      <table class="adm-table gls-table">
        <caption class="sr-only"><?= e(__('{n} Begriffe', ['n' => count($terms)])) ?></caption>
        <thead><tr><th scope="col"><?= e(__('Begriff')) ?></th><th scope="col"><?= e(__('Kurz-Erklärung')) ?></th>
          <th scope="col" class="rd-num"><?= e(__('Vorkommen')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($terms as $x): $where = $occ[$x['id']] ?? []; $alt = array_values(array_diff($x['variants'], [$x['term']])); ?>
          <tr>
            <td class="gls-term"><a href="<?= e(url('/admin/data/' . $t['handle'] . '/' . $x['id'])) ?>"><b><?= e($x['term']) ?></b></a>
              <?php if (\Core\Lang::multi() && ($x['lang'] ?? '') !== \Core\Lang::default()): ?> <span class="adm-badge" title="<?= e(__('Sprache')) ?>"><?= e(strtoupper((string) $x['lang'])) ?></span><?php endif; ?>
              <?php if ($x['draft']): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Entwurf')) ?></span><?php endif; ?>
              <?php if (!empty($byId[$x['id']])): ?> <span class="adm-badge adm-badge--adm-warn" title="<?= e(implode(' ', array_column($byId[$x['id']], 'text'))) ?>"><?= e(__('Hinweis')) ?></span><?php endif; ?>
              <?php if ($alt): ?><span class="gls-alt"><?= e(implode(', ', $alt)) ?></span><?php endif; ?>
              <?php if ($x['category'] !== ''): ?><span class="gls-alt"><?= e(__('Kategorie')) ?>: <?= e($x['category']) ?></span><?php endif; ?></td>
            <td class="gls-short"><?= e($x['short']) ?> <small class="adm-muted gls-len<?= mb_strlen($x['short']) > Glossary::SHORT_MAX ? ' is-long' : '' ?>">(<?= mb_strlen($x['short']) ?>/<?= Glossary::SHORT_MAX ?>)</small></td>
            <td class="rd-num gls-occ">
              <?php if (!$where): ?><span class="adm-muted">0</span>
              <?php else: ?>
              <details><summary><?= count($where) ?> <span class="sr-only"><?= e(__('Seiten mit „{t}“ anzeigen', ['t' => $x['term']])) ?></span></summary>
                <ul><?php foreach (array_slice($where, 0, 25) as $w): ?><li><a href="<?= e($w['url']) ?>" target="_blank" rel="noopener"><?= e($w['title'] ?: $w['url']) ?></a></li><?php endforeach; ?></ul>
              </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="adm-muted gls-note"><?= e(__('„Vorkommen“: Seiten und Einträge im Text der Website-Suche, in denen der Begriff oder eine Variante steht (alle Stellen, auch Überschriften). Stand: höchstens 10 Minuten alt.')) ?>
      <a href="<?= e(url($base) . '?neu=1') ?>"><?= e(__('Jetzt neu zählen')) ?></a></p>
    <?php endif; ?>
  </div>

  <div class="rd-side">
    <section class="adm-card" id="neu" aria-labelledby="gls-new-h">
      <h2 id="gls-new-h"><?= e(__('Begriff schnell hinzufügen')) ?></h2>
      <form method="post" action="<?= e(url($base . '/neu')) ?>">
        <?= csrf_field() ?>
        <div class="f"><label for="gls-b"><?= e(__('Begriff')) ?></label><input id="gls-b" name="begriff" required maxlength="120" autocomplete="off"></div>
        <div class="f"><label for="gls-v"><?= e(__('Varianten (optional)')) ?></label><input id="gls-v" name="varianten" maxlength="400" autocomplete="off" aria-describedby="gls-v-h">
          <p class="f-help" id="gls-v-h"><?= e(__('Mit Komma getrennt, z. B. „Sender Policy Framework, SPF-Eintrag“.')) ?></p></div>
        <div class="f"><label for="gls-k"><?= e(__('Kurz-Erklärung')) ?></label><textarea id="gls-k" name="kurz" rows="3" maxlength="400" aria-describedby="gls-k-h"></textarea>
          <p class="f-help" id="gls-k-h"><?= e($ai ? __('Höchstens 240 Zeichen. Leer lassen und „Von der KI vorschlagen“ wählen: Die KI schreibt einen Entwurf, den Sie danach prüfen.') : __('Höchstens 240 Zeichen, Klartext.')) ?></p></div>
        <p class="adm-actions">
          <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Als Entwurf anlegen')) ?></button>
          <?php if ($ai): ?><button class="adm-btn adm-btn--small" type="submit" name="ai" value="1"><?= e(__('Von der KI vorschlagen')) ?></button><?php endif; ?>
        </p>
      </form>
    </section>

    <section class="adm-card" id="import" aria-labelledby="gls-imp-h">
      <h2 id="gls-imp-h"><?= e(__('Import & Export')) ?></h2>
      <p class="adm-muted"><?= e(__('CSV mit Semikolon und Spaltennamen in der ersten Zeile: begriff;varianten;kurz;erklaerung;kategorie;link;status. Neue Begriffe werden Entwürfe, außer status = published.')) ?></p>
      <?php if ($import): ?>
      <div class="adm-inline-box" role="status">
        <strong><?= e($import['dry'] ? __('Probelauf (nichts gespeichert)') : __('Letzter Import')) ?></strong>
        <p><?= e(__('{t} Zeilen: {c} neu, {u} geändert, {s} übersprungen.', ['t' => $import['total'], 'c' => $import['created'], 'u' => $import['updated'], 's' => $import['skipped']])) ?></p>
        <?php if ($import['errors']): ?><ul class="rd-errors"><?php foreach ($import['errors'] as $line => $msg): ?><li><?= e(__('Zeile {n}: {msg}', ['n' => $line, 'msg' => $msg])) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
      <?php endif; ?>
      <form method="post" action="<?= e(url($base . '/import')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="f"><label for="gls-file"><?= e(__('Datei (CSV)')) ?></label><input id="gls-file" type="file" name="file" accept=".csv,.txt,text/csv"></div>
        <div class="f"><label for="gls-text"><?= e(__('… oder Zeilen einfügen')) ?></label><textarea id="gls-text" name="text" rows="3" spellcheck="false" data-kia-off placeholder="begriff;varianten;kurz&#10;SPF;Sender Policy Framework;…"></textarea></div>
        <div class="adm-checks">
          <label class="rv-check"><input type="checkbox" name="overwrite" value="1"> <span><?= e(__('Vorhandene Begriffe überschreiben')) ?></span></label>
          <label class="rv-check"><input type="checkbox" name="dry" value="1"> <span><?= e(__('Nur prüfen (Probelauf)')) ?></span></label>
        </div>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Importieren')) ?></button>
      </form>
      <p class="rd-export"><?= e(__('Exportieren:')) ?> <a href="<?= e(url($base . '/export')) ?>">CSV</a></p>
    </section>

    <?php if ($schema): ?>
    <form class="adm-card" id="einstellungen" method="post" action="<?= e(url($base . '/einstellungen')) ?>">
      <?= csrf_field() ?>
      <h2><?= e(__('Einstellungen')) ?></h2>
      <div class="f"><label for="gls-mode"><?= e(__('Begriffe im Text markieren')) ?></label>
        <select id="gls-mode" name="s[mode]"><?php foreach (Glossary::MODES as $k => $l): ?><option value="<?= e($k) ?>"<?= $settings['mode'] === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="gls-h"><?= e(__('Überschriften nicht markieren')) ?></label>
        <select id="gls-h" name="s[headings]"><?php foreach ([0 => __('Alle Überschriften markieren'), 1 => 'h1', 2 => 'h1–h2', 3 => 'h1–h3', 4 => 'h1–h4', 6 => __('Keine Überschrift (h1–h6)')] as $k => $l): ?><option value="<?= $k ?>"<?= (int) $settings['headings'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="gls-ex"><?= e(__('Seiten ausnehmen')) ?></label><textarea id="gls-ex" name="s[exclude]" rows="3" spellcheck="false" data-kia-off aria-describedby="gls-ex-h" placeholder="/impressum&#10;/blog/*"><?= e($settings['exclude']) ?></textarea>
        <p class="f-help" id="gls-ex-h"><?= e(__('Ein Pfad je Zeile; „/pfad/*“ nimmt alle Seiten darunter aus. Einzelne Abschnitte: Abschnitts-Option „Glossar-Begriffe hier nicht markieren“ im Editor.')) ?></p></div>
      <div class="f"><label for="gls-live"><?= e(__('Dynamische Bereiche (CSS-Selektoren)')) ?></label><textarea id="gls-live" name="s[live]" rows="2" spellcheck="false" data-kia-off aria-describedby="gls-live-h" placeholder=".ergebnisse"><?= e($settings['live']) ?></textarea>
        <p class="f-help" id="gls-live-h"><?= e(__('Für Inhalte, die erst im Browser entstehen (z. B. Prüfergebnisse eines Werkzeugs): einfache Selektoren wie .klasse oder #id, einer je Zeile. Dort wird je Bereich das erste Vorkommen markiert. Elemente mit data-glossary="live" gelten immer.')) ?></p></div>
      <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
