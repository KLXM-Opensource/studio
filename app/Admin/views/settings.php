<?php
/** Schema-basiertes Einstellungsformular mit Reitern. @var array $groups  @var array $values  @var array $errors */
use Core\Fields;
use Core\Lang;
$lang = $lang ?? '';
$groupHasError = function (array $g) use ($errors) {
    foreach ($g['fields'] as $f) {
        foreach ($errors as $path => $_) {
            if (isset($f['name']) && ($path === $f['name'] || str_starts_with($path, $f['name'] . '.'))) return true;
        }
    }
    return false;
};
?>
<header class="adm-head">
  <div><p class="adm-eyebrow">Zentral gepflegt</p><h1><?= e($title) ?></h1>
    <p class="adm-muted"><?= e(__('Diese Angaben erscheinen automatisch überall auf der Website (Kopf, Kontakt, Fußzeile, Suchmaschinen-Daten).')) ?></p></div>
</header>
<?php $langName = Lang::all()[$lang !== '' ? $lang : Lang::default()] ?? ''; ?>
<?php if ($lang !== ''): ?>
<p class="st-trans" role="note"><strong><?= e(__('Sie bearbeiten: {lang}', ['lang' => $langName])) ?></strong> · <?= e(__('Übersetzung ({lang}): Nur Texte werden je Sprache gepflegt – Telefon, Adresse, Links usw. gelten für alle Sprachen. Leere Felder zeigen den Text der Standardsprache.', ['lang' => $langName])) ?></p>
<?= /* KI: Texte übersetzen (Core\AI) */ \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_settings.php', ['lang' => $lang, 'groups' => $groups]) ?>
<?php endif; ?>
<div class="st-layout" data-st-preview="<?= e(url('/admin/api/settings-preview')) ?>">
<form method="post" action="<?= e(url($action)) ?>" class="adm-tabs-form" data-tabs novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="">
  <?php if ($lang !== ''): ?><input type="hidden" name="_lang" value="<?= e($lang) ?>"><?php endif; ?>
  <div class="st-bar">
  <div class="adm-tabs" role="tablist" aria-label="Bereiche">
    <?php foreach ($groups as $i => $g): ?>
    <button type="button" role="tab" id="tab-<?= e($g['id']) ?>" aria-controls="panel-<?= e($g['id']) ?>" data-tab="<?= e($g['id']) ?>"
      aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?>><?= e($g['label']) ?><?= $groupHasError($g) ? ' <span class="adm-dot" aria-label="Fehler">●</span>' : '' ?></button>
    <?php endforeach; ?>
  </div>
  <div class="st-tools">
    <?php if (Lang::multi()): ?>
    <nav class="fx-seg dt-seg st-langsw" aria-label="<?= e(__('Sprache')) ?>"><?php foreach (Lang::all() as $code => $label): $isDef = $code === Lang::default(); ?>
      <a data-keep-hash href="<?= e(url('/admin/settings' . ($isDef ? '' : '?lang=' . $code))) ?>"<?= ($isDef ? $lang === '' : $lang === $code) ? ' aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?><span class="adm-sr"> <?= e($label) ?></span></a><?php endforeach; ?></nav>
    <?php endif; ?>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-st-pvtoggle aria-pressed="false"><?= e(__('Vorschau')) ?></button>
  </div>
  </div>
  <?php foreach ($groups as $i => $g): ?>
  <section class="adm-card adm-panel" role="tabpanel" id="panel-<?= e($g['id']) ?>" aria-labelledby="tab-<?= e($g['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
    <h2><?= e($g['label']) ?><?php if (Lang::multi()): ?> <span class="st-chip<?= $lang !== '' ? ' st-chip--trans' : '' ?>"><?= e($langName) ?></span><?php endif; ?></h2>
    <div class="adm-fields"><?= Fields::renderForm($g['fields'], $values, $errors, 'f') ?></div>
  </section>
  <?php endforeach; ?>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button><span class="adm-muted"><?= e(__('Änderungen sind sofort auf der Website sichtbar.')) ?></span></div>
</form>
<aside class="st-pv" aria-label="<?= e(__('Vorschau')) ?>" hidden>
  <header class="st-pv__bar">
    <strong><?= e(__('Vorschau')) ?></strong>
    <span class="st-pv__single" data-st-single hidden><?= e(__('Nur dieser Eintrag')) ?> · <button type="button" class="adm-link" data-st-all><?= e(__('Alle zeigen')) ?></button></span>
    <div class="fx-seg" role="group" aria-label="<?= e(__('Gerät')) ?>">
      <button type="button" data-st-device="desktop" aria-pressed="true"><?= e(__('Desktop')) ?></button>
      <button type="button" data-st-device="mobile" aria-pressed="false"><?= e(__('Mobil')) ?></button>
    </div>
    <span class="st-pv__state" data-st-state aria-live="polite"></span>
  </header>
  <div class="st-pv__stage" data-st-stage><iframe title="<?= e(__('Vorschau der Website mit ungespeicherten Änderungen')) ?>" data-st-frame></iframe></div>
  <p class="st-pv__note adm-muted"><?= e(__('Zeigt die Startseite mit Ihren ungespeicherten Änderungen. Gespeichert wird erst mit „Speichern“.')) ?></p>
</aside>
</div>
