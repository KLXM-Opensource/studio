<?php
/** Verwaltung → Blöcke: eigene Blöcke (Block-Baukasten). @var array $blocks  @var ?array $library */
$status = fn(array $b) => match (true) {
    $b['status'] === 'published' && $b['changed'] => ['adm-badge adm-badge--adm-warn', __('Freigegeben · Änderungen offen')],
    $b['status'] === 'published' => ['adm-badge', __('Freigegeben')],
    $b['status'] === 'withdrawn' => ['adm-badge adm-badge--muted', __('Zurückgezogen')],
    default => ['adm-badge adm-badge--draft', __('Entwurf')],
};
?>
<header class="adm-head dt-head">
  <h1><?= e(__('Blöcke')) ?></h1>
  <div class="adm-row">
    <?php if (\Core\AI\Assist::available('text')): ?><a class="adm-btn kia-btn" href="<?= e(url('/admin/blocks/new?ai=1')) ?>"><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Block generieren')) ?></a><?php endif; ?>
    <a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/blocks/new')) ?>">+ <?= e(__('Neuer Block')) ?></a>
  </div>
</header>

<details class="adm-card dt-howto"<?= $blocks ? '' : ' open' ?>>
  <summary><?= e(__('So funktioniert der Block-Baukasten')) ?></summary>
  <ol class="dt-steps">
    <li><span class="dt-step">1</span><strong><?= e(__('Felder')) ?></strong><p><?= e(__('Welche Angaben pflegt die Redaktion? Text, Bild, Link, Auswahl, Listen …')) ?></p></li>
    <li><span class="dt-step">2</span><strong><?= e(__('Vorlage & CSS')) ?></strong><p><?= e(__('HTML mit Platzhaltern wie {{ title }} – ohne PHP und ohne JavaScript. Das CSS gilt nur innerhalb des Blocks.')) ?></p></li>
    <li><span class="dt-step">3</span><strong><?= e(__('Freigeben')) ?></strong><p><?= e(__('Nach dem Test in der Vorschau erscheint der Block beim Bearbeiten der Seiten neben den Blöcken des Kits.')) ?></p></li>
  </ol>
  <p class="adm-muted"><a href="<?= e(url('/admin/hilfe/technik#bloecke')) ?>"><?= e(__('Referenz der Vorlagensprache')) ?> →</a></p>
</details>

<?php if ($blocks): ?>
<div class="dt-grid">
  <?php foreach ($blocks as $b): [$cls, $lbl] = $status($b); ?>
  <article class="dt-card cb-card">
    <a class="dt-card__main" href="<?= e(url('/admin/blocks/' . $b['key'])) ?>">
      <span class="dt-icon" aria-hidden="true"><?= icon($b['icon'] ?: 'package') ?></span>
      <span><strong><?= e($b['label']) ?></strong><small><?= e(__('{n} Felder', ['n' => count($b['fields'])])) ?> · <?= e(__('auf {n} Seite(n)', ['n' => $b['uses']])) ?> · <code>cblk_<?= e($b['key']) ?></code></small></span>
    </a>
    <p class="dt-card__meta"><span class="<?= e($cls) ?>"><?= e($lbl) ?></span><?php if ($b['version']): ?> <span class="adm-muted"><?= e(__('Version {n}', ['n' => $b['version']])) ?></span><?php endif; ?></p>
    <?php if ($b['description'] !== ''): ?><p class="adm-muted cb-card__desc"><?= e($b['description']) ?></p><?php endif; ?>
    <div class="dt-card__actions">
      <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/blocks/' . $b['key'])) ?>"><?= e(__('Bearbeiten')) ?></a>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/blocks/' . $b['key'] . '/export')) ?>"><?= e(__('Exportieren')) ?></a>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?php else: ?>
<p class="adm-muted"><?= e(__('Noch keine eigenen Blöcke.')) ?></p>
<?php endif; ?>

<div class="adm-grid2">
  <section class="adm-card">
    <h2><?= e(__('Importieren')) ?></h2>
    <p class="adm-muted"><?= e(__('Block-Datei (JSON) aus einer anderen Website oder einem Export. Der Block wird als Entwurf angelegt.')) ?></p>
    <form method="post" action="<?= e(url('/admin/blocks/import')) ?>" enctype="multipart/form-data" class="adm-fields">
      <?= csrf_field() ?>
      <div class="f"><label for="cb-imp-file"><?= e(__('Datei')) ?></label><input id="cb-imp-file" type="file" name="file" accept=".json,application/json"></div>
      <details><summary><?= e(__('… oder JSON einfügen')) ?></summary>
        <div class="f"><label for="cb-imp-json"><?= e(__('JSON')) ?></label><textarea id="cb-imp-json" name="json" rows="5" spellcheck="false" class="cb-code" data-kia-off></textarea></div>
      </details>
      <button class="adm-btn" type="submit"><?= e(__('Importieren')) ?></button>
    </form>
  </section>
  <?php if ($library !== null): ?>
  <section class="adm-card">
    <h2><?= e(__('Netzwerk-Bibliothek')) ?></h2>
    <p class="adm-muted"><?= e(__('Blöcke, die Netzwerk-Administration oder Agentur für alle Websites bereitgestellt haben. Übernehmen legt eine Kopie als Entwurf an.')) ?></p>
    <?php if (!$library): ?><p class="adm-muted"><?= e(__('Die Bibliothek ist leer – im Baukasten „In Bibliothek kopieren“ wählen.')) ?></p><?php endif; ?>
    <ul class="cb-lib">
      <?php foreach ($library as $l): ?>
      <li><span class="dt-icon" aria-hidden="true"><?= icon($l['icon']) ?></span>
        <span><strong><?= e($l['label']) ?></strong><small class="adm-muted"><?= e($l['description']) ?><?= $l['site'] !== '' ? ' · ' . e(__('von {site}', ['site' => $l['site']])) : '' ?></small></span>
        <form method="post" action="<?= e(url('/admin/blocks/library')) ?>"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($l['file']) ?>">
          <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Übernehmen')) ?><span class="adm-sr"> <?= e($l['label']) ?></span></button></form></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
