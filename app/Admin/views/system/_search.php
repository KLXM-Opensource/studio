<?php
/**
 * Grundeinstellungen → Reiter „Suche“: Status des Index, Neuaufbau, KI-Hinweis, Suchbegriffe ohne Treffer.
 * $part = 'panel' (im Reiter, unter den Feldern; Knöpfe mit form="…") oder 'forms' (eigene Formulare hinter dem Hauptformular).
 * @var string $part
 */
use Core\AI\Ai;
use Core\Search\Search;
use Core\Search\Stats;

if (!Search::enabled()) return;
if ($part === 'forms'): ?>
<form id="search-rebuild" method="post" action="<?= e(url('/admin/system/search/rebuild')) ?>"><?= csrf_field() ?></form>
<form id="search-misses" method="post" action="<?= e(url('/admin/system/search/misses-clear')) ?>"><?= csrf_field() ?></form>
<?php return; endif;

try {
    $st = Search::status();
} catch (\Throwable $e) {
    $st = null;
}
$set = Search::settings();
$embed = Ai::capability('embed');
$fmt = fn(?string $iso) => $iso ? date('d.m.Y H:i', strtotime($iso)) : __('noch nie');
?>
<div class="adm-inline-box" id="search-status">
  <strong><?= e(__('Suchindex')) ?></strong>
  <?php if (!$st): ?>
    <p class="adm-flash adm-flash--error"><?= e(__('Der Suchindex ist nicht lesbar – bitte „Index neu aufbauen“.')) ?></p>
  <?php else: ?>
  <table class="adm-table px-table">
    <thead><tr><th><?= e(__('Sprache')) ?></th><th class="num"><?= e(__('Dokumente')) ?></th><?php if ($st['semantic']): ?><th class="num"><?= e(__('mit Vektoren')) ?></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($st['languages'] as $l => $x): ?>
      <tr><td><?= e(\Core\Lang::all()[$l] ?? $l) ?> <code><?= e($l) ?></code></td><td class="num"><?= (int) $x['docs'] ?></td><?php if ($st['semantic']): ?><td class="num"><?= (int) $x['vectors'] ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="adm-muted">
    <?= e(__('Zuletzt abgeglichen: {when}', ['when' => $fmt($st['synced_at'])])) ?>
    · <?= e(__('Größe: {size}', ['size' => \Core\Media::humanSize((int) $st['bytes'])])) ?>
    · <?= e(__('Verfahren: {engine}', ['engine' => $st['semantic'] ? __('Stichwort (Loupe) + semantisch (Embeddings)') : __('Stichwort (Loupe)')])) ?>
    <?php if ($st['dirty']): ?> · <span class="adm-badge adm-badge--draft"><?= e(__('Änderungen vorgemerkt')) ?></span><?php endif; ?>
    <?php if ($st['pending']): ?> · <span class="adm-badge adm-badge--draft"><?= e(__('{n} Dokumente ohne Vektoren', ['n' => $st['pending']])) ?></span><?php endif; ?>
  </p>
  <?php if ($st['error']): ?><p class="adm-flash adm-flash--error"><?= e(__('Letzter Fehler: {error}', ['error' => $st['error']])) ?></p><?php endif; ?>
  <?php if ($st['semantic'] && $st['vector_error']): ?><p class="adm-flash adm-flash--error"><?= e(__('KI-Anbieter: {error} – die Stichwortsuche funktioniert weiter.', ['error' => $st['vector_error']])) ?></p><?php endif; ?>
  <?php endif; ?>
  <div class="adm-row">
    <button class="adm-btn" type="submit" form="search-rebuild"><?= e(__('Index neu aufbauen')) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e(Search::url()) ?>" target="_blank" rel="noopener"><?= e(__('Suche ansehen')) ?> ↗</a>
  </div>
  <p class="adm-muted"><?= e(__('Für große Websites und geteilte Daten zusätzlich per Cron: {cmd}', ['cmd' => 'php bin/console search:index --all'])) ?></p>
</div>

<?php if ($set['semantic'] && !Ai::enabled('embed')): ?>
<p class="adm-flash adm-flash--info"><?= e(__('Die semantische Suche ist eingeschaltet, aber KI mit Embeddings ist für diese Website nicht aktiv (Reiter „KI“). Bis dahin sucht die Website nur nach Stichworten.')) ?></p>
<?php elseif ($set['semantic'] && $embed['external']): ?>
<div class="adm-flash adm-flash--info">
  <p><strong><?= e(__('Datenschutz: Suchtexte gehen an einen externen Anbieter ({endpoint}).', ['endpoint' => parse_url($embed['endpoint'], PHP_URL_HOST)])) ?></strong>
  <?= e(__('Ergänzen Sie die Datenschutzerklärung, z. B.:')) ?></p>
  <blockquote class="adm-muted" id="search-privacy"><?= e(\Core\Search\AdminSettings::privacyText()) ?></blockquote>
  <button type="button" class="adm-btn adm-btn--small" data-copy="#search-privacy"><?= e(__('Text kopieren')) ?></button>
</div>
<?php endif; ?>

<?php $misses = $set['misses'] && $st ? Stats::top(20) : []; if ($set['misses']): ?>
<div class="adm-inline-box" id="search-misses-box">
  <strong><?= e(__('Suchbegriffe ohne Treffer')) ?></strong>
  <p class="adm-muted"><?= e(__('Anonym gezählt. Tipp: Fehlende Begriffe als Synonym ergänzen oder Inhalte dazu anlegen.')) ?></p>
  <?php if ($misses): ?>
  <table class="adm-table px-table">
    <thead><tr><th><?= e(__('Begriff')) ?></th><th><?= e(__('Sprache')) ?></th><th class="num"><?= e(__('Anzahl')) ?></th><th><?= e(__('Zuletzt')) ?></th></tr></thead>
    <tbody><?php foreach ($misses as $m): ?><tr><td><?= e($m['term']) ?></td><td><code><?= e($m['lang']) ?></code></td><td class="num"><?= (int) $m['n'] ?></td><td><?= e(date('d.m.Y', strtotime((string) $m['last']))) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" form="search-misses" data-confirm="<?= e(__('Liste der Suchbegriffe ohne Treffer leeren?')) ?>"><?= e(__('Liste leeren')) ?></button>
  <?php else: ?><p><?= e(__('Bisher keine.')) ?></p><?php endif; ?>
</div>
<?php endif;
