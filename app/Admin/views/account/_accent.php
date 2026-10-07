<?php
/**
 * Konto → Akzentfarbe (Core\Accent): kuratierte Vorlagen mit Live-Vorschau (resources/js/_accent.js).
 * @var array $user
 */
use Core\Accent;

$accAllowed = Accent::allowed();
if (count($accAllowed) < 2) return;   // Website erlaubt nur den Standard (Grundeinstellungen → Website)
$accPrefs = Accent::current($user);
$accLabels = Accent::labels();
?>
<form class="set-group acc-group" id="akzent" method="post" action="<?= e(url('/admin/account/accent')) ?>" data-accent-form aria-labelledby="acc-h">
  <?= csrf_field() ?>
  <h2 class="set-group__title" id="acc-h"><?= e(__('Akzentfarbe')) ?></h2>
  <div class="set-list acc-box">
  <p class="adm-muted"><?= e(__('Färbt Schaltflächen, Links, Markierungen und Fokusrahmen der Verwaltung und der Werkzeugleiste auf der Website – nur für Sie. Alle Farben sind in Hell und Dunkel auf ausreichenden Kontrast geprüft.')) ?></p>
  <fieldset class="acc-grid">
    <legend><?= e(__('Farbe wählen')) ?></legend>
    <?php foreach ($accAllowed as $k): ?>
    <label class="acc-opt"><input type="radio" name="accent" value="<?= e($k) ?>"<?= $accPrefs['accent'] === $k ? ' checked' : '' ?>><span class="acc-sw" data-sw="<?= e($k) ?>" aria-hidden="true"><i></i><i></i><i></i></span><span><?= e($accLabels[$k]) ?><?= $k === Accent::DEFAULT ? ' <small class="adm-muted">' . e(__('(Standard)')) . '</small>' : '' ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  <label class="f-check acc-side"><input type="checkbox" role="switch" name="side" value="1" data-accent-side<?= Accent::prefs($user)['side'] ? ' checked' : '' ?><?= $accPrefs['accent'] === Accent::DEFAULT ? ' disabled' : '' ?>><span><?= e(__('Seitenleiste mitfärben')) ?> <small class="adm-muted"><?= e(__('dunkler Ton der gewählten Farbe statt Navy')) ?></small></span></label>
  <div class="acc-pv" aria-hidden="true">
    <div class="acc-pv__side"><span><?= e(__('Übersicht')) ?></span><span class="is-on"><?= e(__('Seiten')) ?></span><span><?= e(__('Medien')) ?></span></div>
    <div class="acc-pv__main">
      <p class="adm-eyebrow"><?= e(__('Vorschau')) ?></p>
      <p><?= e(__('So sehen Links aus:')) ?> <a href="#akzent" tabindex="-1"><?= e(__('Beispiel-Link')) ?></a></p>
      <div class="acc-pv__row"><span class="adm-btn adm-btn--primary adm-btn--small"><?= e(__('Speichern')) ?></span><span class="acc-pv__chip"><?= e(__('Ausgewählt')) ?></span><span class="acc-pv__focus"><?= e(__('Fokus')) ?></span></div>
    </div>
  </div>
  <div class="adm-row">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Akzentfarbe speichern')) ?></button>
    <button class="adm-btn adm-btn--ghost" type="reset"><?= e(__('Zurücksetzen')) ?></button>
  </div>
  <?php if (Accent::locked() === false && count($accAllowed) < count(Accent::PRESETS)): ?><p class="f-help"><?= e(__('Weitere Farben kann die Administration in den Grundeinstellungen freigeben.')) ?></p><?php endif; ?>
  </div>
</form>
