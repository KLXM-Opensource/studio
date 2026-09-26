<?php
/** Übersicht · „Hilfe & Einstieg“: Assistent fragen, Tutorials (Top 3 der Rolle), Trailer, Hinweis des Kits, „Was ist neu“. @var array $help */
?>
<?php if ($help['ai']): ?>
<form class="dash-ask" method="get" action="<?= e(url('/admin/ai/assistent')) ?>" data-dash-ask role="search" aria-label="<?= e(__('Assistent fragen')) ?>">
  <label class="dash-ask__label" for="dash-ask-q"><?= e(__('Fragen Sie {brand}', ['brand' => $help['brand']])) ?></label>
  <div class="dash-ask__row">
    <input id="dash-ask-q" name="q" type="text" maxlength="500" autocomplete="off" placeholder="<?= e(__('z. B. Wie lege ich einen Termin an?')) ?>">
    <button type="submit" class="adm-btn adm-btn--primary adm-btn--small"><?= icon('paper-plane-tilt') ?><span class="sr-only"><?= e(__('Fragen')) ?></span></button>
  </div>
</form>
<?php endif; ?>
<h3 class="dash-h3"><?= e(__('Neu hier?')) ?> <span class="adm-muted"><?= e($help['track']) ?></span></h3>
<?php // Videos und Trailer liegen auf der Produkt-Website (config 'docs_url') – nur Links (neuer Tab), keine Anfrage nach außen
$__ext = fn(string $host) => '<span aria-hidden="true">↗</span><span class="sr-only"> (' . e(__('öffnet {host} in einem neuen Tab', ['host' => $host])) . ')</span>'; ?>
<ul class="dash-list dash-list--tight" role="list">
  <?php if ($help['trailer']): ?>
  <li><a href="<?= e($help['trailer']) ?>" target="_blank" rel="noopener"><span class="dash-list__ico"><?= icon('play-circle') ?></span><span class="dash-list__main"><span class="dash-list__title"><?= e(__('KLXM Studio im Überblick')) ?> <?= $__ext($help['docsHost']) ?></span><small><?= e(__('Trailer auf {host}', ['host' => $help['docsHost']])) ?></small></span></a></li>
  <?php endif; ?>
  <?php foreach ($help['tutorials'] as $t): ?>
  <li class="dash-list__row"><a href="<?= e(url('/admin/hilfe/tutorials/' . $t['slug'])) ?>"><span class="dash-list__ico"><?= icon($t['icon']) ?></span><span class="dash-list__main"><span class="dash-list__title"><?= e($t['title']) ?></span><small><?= e($t['level']) ?></small></span></a><?php if ($t['web']): ?><a class="dash-list__ext" href="<?= e($t['web']) ?>" target="_blank" rel="noopener"><?= e(__('Video')) ?> <span aria-hidden="true">↗</span><span class="sr-only">: <?= e($t['title']) ?> (<?= e(__('öffnet {host} in einem neuen Tab', ['host' => $help['docsHost']])) ?>)</span></a><?php endif; ?></li>
  <?php endforeach; ?>
</ul>
<p class="dash-links"><a href="<?= e(url('/admin/hilfe/tutorials')) ?>"><?= e(__('Alle Tutorials')) ?></a> · <a href="<?= e(url('/admin/hilfe')) ?>"><?= e(__('Handbuch')) ?></a></p>
<?php if (($hint = (string) project('dashboard_hint', '')) !== ''): ?>
<p class="dash-tip"><?= icon('lightbulb') ?><span><b><?= e(app()->theme->settingsTitle()) ?>:</b> <?= e(__($hint)) ?></span></p>
<?php endif; ?>
<?php if (!empty($help['news']['items'])): ?>
<h3 class="dash-h3"><?= e(__('Was ist neu')) ?> <span class="adm-muted"><?= e($help['news']['version']) ?></span></h3>
<ul class="dash-news" role="list">
  <?php foreach ($help['news']['items'] as $n): ?><li><?= e($n) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>
