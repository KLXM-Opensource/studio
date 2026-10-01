<?php
/** Bereich „KLXM AI“ → Alt-Texte: Bilder ohne Alt-Text, Sammel-Vorschläge mit Prüfliste (resources/js/_ai.js → bulkAlt). */
use Core\AI\Assist;
use Core\AI\Center;
use Core\Media;

$brand = Assist::brand();
$list = Center::missingAlt();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Alt-Texte')) ?></h1>
    <p class="adm-muted"><?= e(__('Bilder ohne Alt-Text und fehlende Übersetzungen. Die KI beschreibt Bilder bzw. übersetzt vorhandene Alt-Texte – gespeichert werden nur die Vorschläge, die Sie prüfen und anhaken.')) ?></p></div>
  <?php if ($list && Assist::available('vision')): ?><button type="button" class="adm-btn adm-btn--primary kia-btn" data-kia-bulk-alt><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Alt-Texte vorschlagen ({n})', ['n' => count($list)])) ?></button><?php endif; ?>
</header>
<?php if (!Assist::available('vision')): ?><p class="adm-flash adm-flash--info"><?= e(__('Die Bild-KI ist für diese Website nicht eingeschaltet – Alt-Texte bitte in der Mediathek von Hand ergänzen.')) ?></p><?php endif; ?>
<section class="adm-card" aria-labelledby="kia-alt-h">
  <h2 id="kia-alt-h"><?= e(__('Ohne Alt-Text')) ?> <span class="adm-badge adm-badge--muted"><?= count($list) ?></span></h2>
  <?php if (!$list): ?><p class="kia-chk kia-chk--ok"><?= e(__('Alle Bilder haben einen Alt-Text oder sind als dekorativ markiert.')) ?></p>
  <?php else: ?>
  <ul class="kia-altgrid">
    <?php foreach ($list as $m): ?><li><img src="<?= e(Media::url($m, 480)) ?>" alt="" loading="lazy"><span><?= e($m['title'] ?: $m['original_name']) ?></span></li><?php endforeach; ?>
  </ul>
  <p class="adm-muted kia-small"><?= e(__('Rein schmückende Bilder (Muster, Flächen) in der Mediathek als „dekorativ“ markieren statt sie zu beschreiben.')) ?> <a href="<?= e(url('/admin/media')) ?>"><?= e(__('Zur Mediathek')) ?></a></p>
  <?php endif; ?>
</section>
<?php foreach (\Core\Lang::all() as $code => $label): if ($code === \Core\Lang::default()) continue; $miss = Center::missingAltLang($code); ?>
<section class="adm-card" id="<?= e($code) ?>" aria-labelledby="kia-alt-<?= e($code) ?>">
  <div class="adm-row adm-row--between">
    <h2 id="kia-alt-<?= e($code) ?>"><?= e(__('Alt-Text fehlt in {lang}', ['lang' => $label])) ?> <span class="adm-badge adm-badge--muted"><?= count($miss) ?></span></h2>
    <?php if ($miss && Assist::available('text')): ?><button type="button" class="adm-btn adm-btn--primary kia-btn" data-kia-bulk-alt-lang="<?= e($code) ?>"><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Übersetzen ({n})', ['n' => count($miss)])) ?></button><?php endif; ?>
  </div>
  <?php if (!$miss): ?><p class="kia-chk kia-chk--ok"><?= e(__('Alle Alt-Texte sind übersetzt.')) ?></p>
  <?php else: ?>
  <p class="adm-muted kia-small"><?= e(__('Übersetzt wird der vorhandene Alt-Text der Standardsprache – so bleiben beide Fassungen inhaltlich gleich.')) ?></p>
  <ul class="kia-altgrid">
    <?php foreach ($miss as $m): ?><li><img src="<?= e(Media::url($m, 480)) ?>" alt="" loading="lazy"><span><?= e($m['alt']) ?></span></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endforeach; ?>
