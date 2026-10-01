<?php
/**
 * Bereich „KLXM AI“ → Übersetzen: fehlende Übersetzungen je Sprache (Seiten, Einträge, Alt-Texte, Website-Texte) mit
 * Prüfansicht (resources/js/_ai.js). Seiten werden als Entwurf angelegt und übersetzt; nichts wird veröffentlicht.
 */
use Core\AI\Assist;
use Core\AI\Center;
use Core\Data\Entries;
use Core\Lang;
use Core\Media;
use Core\Pages;

$brand = Assist::brand();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Übersetzen')) ?></h1>
    <p class="adm-muted"><?= e(__('Was in den weiteren Sprachen noch fehlt. Die KI übersetzt aus der Standardsprache; Sie prüfen jeden Text, bevor er übernommen wird. Neue Übersetzungen entstehen als Entwurf.')) ?></p></div>
</header>
<?php if (!Assist::available('text') || !Lang::multi()): ?>
<p class="adm-flash adm-flash--info"><?= e(!Lang::multi() ? __('Diese Website hat nur eine Sprache.') : __('Die Text-KI ist für diese Website nicht eingeschaltet.')) ?></p>
<?php return; endif;
$gaps = Center::translationGaps();
$def = Lang::default();
foreach ($gaps as $code => $g):
  $n = count($g['pages']) + count($g['entries']) + count($g['media']) + $g['settings']; ?>
<section class="adm-card kia-tc" aria-labelledby="kia-tc-<?= e($code) ?>">
  <h2 id="kia-tc-<?= e($code) ?>"><?= e($g['label']) ?> <span class="adm-badge<?= $n ? ' adm-badge--draft' : '' ?>"><?= $n ? e(__('{n} offen', ['n' => $n])) : e(__('vollständig')) ?></span></h2>
  <?php if (!$n): ?><p class="kia-chk kia-chk--ok"><?= e(__('Für {lang} fehlt nichts.', ['lang' => $g['label']])) ?></p><?php endif; ?>

  <?php if ($g['pages']): ?>
  <h3 class="kia-h3"><?= e(__('Seiten ohne Übersetzung')) ?> <small class="adm-muted">(<?= count($g['pages']) ?>)</small></h3>
  <ul class="kia-tc__list">
    <?php foreach ($g['pages'] as $p): ?>
    <li><span><strong><?= e($p['title']) ?></strong> <small class="adm-muted"><?= e(Pages::url($p)) ?></small></span>
      <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-tc-page="<?= (int) $p['id'] ?>" data-lang="<?= e($code) ?>"><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Anlegen & übersetzen')) ?></button></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($g['entries']): ?>
  <h3 class="kia-h3"><?= e(__('Einträge ohne Übersetzung')) ?> <small class="adm-muted">(<?= count($g['entries']) ?>)</small></h3>
  <p class="adm-muted kia-small"><?= e(__('„Übersetzung anlegen“ öffnet den neuen Entwurf – dort übersetzt „✦ Aus {lang} übersetzen“ die Felder.', ['lang' => Lang::all()[$def] ?? $def])) ?></p>
  <ul class="kia-tc__list">
    <?php foreach (array_slice($g['entries'], 0, 60) as $row): $t = $row['table']; $en = $row['entry']; ?>
    <li><span><strong><?= e(Entries::title($t, $en)) ?></strong> <small class="adm-muted"><?= e($t['name']) ?></small></span>
      <form method="post" action="<?= e(url('/admin/data/' . $t['handle'] . '/' . $en['id'] . '/translate')) ?>"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= e($code) ?>">
        <button class="adm-btn adm-btn--small"><?= e(__('Übersetzung anlegen')) ?></button></form></li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($g['entries']) > 60): ?><p class="adm-muted kia-small"><?= e(__('… und {n} weitere.', ['n' => count($g['entries']) - 60])) ?></p><?php endif; ?>
  <?php endif; ?>

  <?php if ($g['media']): $items = array_map(fn($m) => ['key' => (string) $m['id'], 'label' => $m['title'] ?: $m['original_name'], 'source' => (string) $m['alt'], 'current' => '', 'html' => false, 'thumb' => Media::url($m, 480)], $g['media']); ?>
  <h3 class="kia-h3"><?= e(__('Alt-Texte ohne Übersetzung')) ?> <small class="adm-muted">(<?= count($g['media']) ?>)</small></h3>
  <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-tc-media="<?= e(json_encode(['from' => $def, 'to' => $code, 'items' => $items], JSON_UNESCAPED_UNICODE)) ?>"><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Alt-Texte übersetzen ({n})', ['n' => count($items)])) ?></button>
  <?php endif; ?>

  <?php if ($g['settings']): ?>
  <h3 class="kia-h3"><?= e(__('Website-Texte ({title})', ['title' => app()->theme->settingsTitle()])) ?></h3>
  <p><?= e(__('{n} Texte ohne Übersetzung.', ['n' => $g['settings']])) ?> <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/settings?lang=' . $code)) ?>"><?= e(__('Öffnen und mit KI übersetzen')) ?></a></p>
  <?php endif; ?>
  <div data-kia-out aria-live="polite"></div>
</section>
<?php endforeach;
