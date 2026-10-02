<?php
/** Verwaltung → Blöcke: eigene Blöcke (Block-Designer). @var array $blocks  @var ?array $library  @var array $demos  @var string $demoToken */
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
  <summary><?= e(__('So funktioniert der Block-Designer')) ?></summary>
  <ol class="dt-steps">
    <li><span class="dt-step">1</span><strong><?= e(__('Felder anlegen')) ?></strong><p><?= e(__('Welche Angaben pflegt die Redaktion? Text, Bild, Link, Auswahl, Listen …')) ?></p></li>
    <li><span class="dt-step">2</span><strong><?= e(__('Vorlage schreiben')) ?></strong><p><?= e(__('HTML mit Platzhaltern wie {{ title }} – ohne PHP und ohne JavaScript. Alle Ausgaben werden automatisch geschützt.')) ?></p></li>
    <li><span class="dt-step">3</span><strong><?= e(__('CSS ergänzen')) ?></strong><p><?= e(__('Das CSS gilt nur innerhalb des Blocks und nutzt die Farben des Kits. Danach in der Vorschau prüfen und für die Redaktion freigeben.')) ?></p></li>
  </ol>
  <p class="adm-muted cb-howto__links">
    <?php if ($demos): ?><a href="#beispiele"><?= e(__('Mit einem Beispiel anfangen')) ?> ↓</a> · <?php endif; ?>
    <a href="<?= e(url('/admin/hilfe#baukasten')) ?>"><?= e(__('Handbuch: Eigene Blöcke bauen')) ?> →</a> ·
    <a href="<?= e(url('/admin/hilfe/technik#bloecke')) ?>"><?= e(__('Referenz der Vorlagensprache')) ?> →</a></p>
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

<?php if ($demos): ?>
<details class="adm-card cb-demos" id="beispiele"<?= $blocks ? '' : ' open' ?> data-cb-demos>
  <summary><span class="cb-demos__title"><?= icon('lightbulb') ?> <?= e(__('Beispiele')) ?></span>
    <span class="adm-muted"><?= e(__('{n} Beispiel-Blöcke – vom einfachen Hinweis bis zur Aufklappliste', ['n' => count($demos)])) ?></span></summary>
  <p class="adm-muted cb-demos__lead"><?= e(__('Jedes Beispiel zeigt ein Konzept des Block-Designers. „Als Vorlage übernehmen“ legt eine Kopie als Entwurf an – bestehende Blöcke bleiben unberührt. Im Block-Designer erklären Hinweise, was das Beispiel zeigt. Die Vorschau nutzt das aktive Kit.')) ?></p>
  <ol class="cb-demos__grid">
    <?php $i = 0; foreach ($demos as $name => $d): $i++; $b = $d['block']; ?>
    <li class="cb-demo">
      <div class="cb-demo__shot" aria-hidden="true">
        <iframe src="<?= e(url('/admin/blocks/demos/' . $name . '/preview')) ?>" loading="lazy" tabindex="-1" title="<?= e(__('Vorschau: {label}', ['label' => $b['label']])) ?>"></iframe>
      </div>
      <div class="cb-demo__body">
        <p class="cb-demo__step"><?= e(__('Beispiel {n}', ['n' => $i])) ?><?= $d['level'] !== '' ? ' · ' . e($d['level']) : '' ?></p>
        <h3 class="cb-demo__name"><span class="dt-icon" aria-hidden="true"><?= icon((string) ($b['icon'] ?? 'package')) ?></span> <?= e((string) $b['label']) ?></h3>
        <p class="cb-demo__teaches"><?= e($d['teaches']) ?></p>
        <?php if ($d['concepts']): ?><ul class="cb-demo__tags" aria-label="<?= e(__('Zeigt')) ?>"><?php foreach ($d['concepts'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/blocks/demos')) ?>" class="cb-demo__act">
          <?= csrf_field() ?><input type="hidden" name="demo" value="<?= e($name) ?>"><input type="hidden" name="token" value="<?= e(substr(md5($demoToken . $name), 0, 16)) ?>">
          <button class="adm-btn adm-btn--small adm-btn--primary" type="submit"><?= e(__('Als Vorlage übernehmen')) ?><span class="adm-sr"> – <?= e((string) $b['label']) ?></span></button>
          <?php if ($d['copies']): ?><small class="adm-muted"><?= e(__('schon {n}× übernommen', ['n' => $d['copies']])) ?></small><?php endif; ?>
        </form>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
</details>
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
    <?php if (!$library): ?><p class="adm-muted"><?= e(__('Die Bibliothek ist leer – im Block-Designer „In Bibliothek kopieren“ wählen.')) ?></p><?php endif; ?>
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
