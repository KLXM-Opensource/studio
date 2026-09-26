<?php
/**
 * Design-Editor: Farben hell/dunkel und Maße als --ck-*-Variablen, Standard = abgeleitet aus dem Design der Website.
 * Live-Vorschau mit den echten Diensten (ohne Cookie/Protokoll), Kontrastprüfung nach WCAG 2.2 AA.
 * @var array $values  @var array $derived  @var array $overrides  @var array $checks  @var array $preview
 */
use MyCms\Consent\Consent;
use MyCms\Consent\Design;

$hasEmbed = false;
foreach ($preview['groups'] as $g) foreach ($g['services'] as $sv) if (!empty($sv['embed']) && !$g['required']) { $hasEmbed = $sv['key']; break 2; }
?>
<div class="ck-design">
  <form method="post" action="<?= e(url('/admin/consent/design')) ?>" class="adm-card ck-design__form" data-ck-design novalidate>
    <?= csrf_field() ?>
    <h2><?= e(__('Farben')) ?></h2>
    <p class="adm-muted"><?= e(__('Leer bzw. unverändert = aus dem Design der Website abgeleitet (Verwaltung → Design). Gespeichert werden nur Abweichungen.')) ?></p>
    <table class="adm-table ck-colors">
      <thead><tr><th scope="col"><?= e(__('Variable')) ?></th><th scope="col"><?= e(__('Hell')) ?></th><th scope="col"><?= e(__('Dunkel')) ?></th></tr></thead>
      <tbody>
      <?php foreach (Design::COLORS as $k => [$label]): $css = str_replace('_', '-', $k); ?>
        <tr><th scope="row"><?= e(__($label)) ?><br><code class="adm-muted">--ck-<?= e($css) ?></code></th>
          <?php foreach (['' => 'ck-' . $css, '@dark' => 'ck-dark-' . $css] as $m => $var): $v = (string) $values[$k . $m]; $id = 'ck-c-' . $k . ($m ? '-d' : ''); ?>
          <td><span class="f-color"><input type="color" value="<?= e($v) ?>" aria-hidden="true" tabindex="-1" data-color-for="<?= e($id) ?>">
            <input type="text" id="<?= e($id) ?>" name="v[<?= e($k . $m) ?>]" value="<?= e($v) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" spellcheck="false" data-var="--<?= e($var) ?>" data-key="<?= e($k . $m) ?>"
              aria-label="<?= e(__($label)) ?> (<?= e($m ? __('Dunkel') : __('Hell')) ?>)"<?= isset($overrides[$k . $m]) ? ' class="is-set"' : '' ?>></span></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <h2><?= e(__('Form')) ?></h2>
    <div class="adm-fields">
      <?php foreach (Design::SIZES as $k => [$label, $unit, $min, $max, $step]): $css = str_replace('_', '-', $k); ?>
      <div class="f f--half"><label for="ck-s-<?= e($k) ?>"><?= e(__($label)) ?> (<?= e($unit) ?>)</label>
        <input type="number" id="ck-s-<?= e($k) ?>" name="v[<?= e($k) ?>]" value="<?= e((string) $values[$k]) ?>" min="<?= e((string) $min) ?>" max="<?= e((string) $max) ?>" step="<?= e((string) $step) ?>" data-var="--ck-<?= e($css) ?>" data-unit="<?= e($unit) ?>"></div>
      <?php endforeach; ?>
    </div>
    <h2 id="ck-contrast-h"><?= e(__('Kontrast (WCAG 2.2 AA)')) ?></h2>
    <table class="adm-table ck-contrast" aria-labelledby="ck-contrast-h" data-ck-contrast>
      <thead><tr><th scope="col"><?= e(__('Kombination')) ?></th><th scope="col"><?= e(__('Modus')) ?></th><th scope="col"><?= e(__('Verhältnis')) ?></th><th scope="col"><?= e(__('Ergebnis')) ?></th></tr></thead>
      <tbody>
      <?php foreach (['light' => '', 'dark' => '@dark'] as $mode => $m): foreach (Design::CHECKS as [$fg, $bg, $min, $label]): $c = array_values(array_filter($checks, fn($x) => $x['label'] === $label && $x['mode'] === $mode))[0]; ?>
        <tr data-fg="<?= e($fg . $m) ?>" data-bg="<?= e($bg . $m) ?>" data-min="<?= e((string) $min) ?>"><td><?= e(__($label)) ?></td><td><?= e($mode === 'light' ? __('Hell') : __('Dunkel')) ?></td>
          <td data-ratio><?= e(number_format($c['ratio'], 2, ',', '')) ?> : 1</td>
          <?php $okT = __('erfüllt'); $failT = __('zu schwach (mind. {min} : 1)', ['min' => number_format($min, 1, ',', '')]); ?>
          <td data-result><span class="adm-badge<?= $c['ok'] ? '' : ' adm-badge--adm-warn' ?>" data-ok="<?= e($okT) ?>" data-fail="<?= e($failT) ?>"><?= e($c['ok'] ? $okT : $failT) ?></span></td></tr>
      <?php endforeach; endforeach; ?>
      </tbody>
    </table>
    <div class="adm-savebar">
      <button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
      <button class="adm-btn adm-btn--ghost" name="reset" value="1" data-confirm="<?= e(__('Alle Abweichungen verwerfen und das Design der Website übernehmen?')) ?>"><?= e(__('Aus dem Website-Design übernehmen')) ?></button>
    </div>
    <details class="ck-css"><summary><?= e(__('Als CSS für das eigene Stylesheet')) ?></summary>
      <pre data-ck-css><code>consent-kit, consent-embed {
<?php foreach (Design::COLORS as $k => $_): ?>  --ck-<?= e(str_replace('_', '-', $k)) ?>: <?= e($values[$k]) ?>;
  --ck-dark-<?= e(str_replace('_', '-', $k)) ?>: <?= e($values[$k . '@dark']) ?>;
<?php endforeach; ?>}</code></pre></details>
  </form>

  <section class="adm-card ck-preview" aria-labelledby="ck-pv-h">
    <h2 id="ck-pv-h"><?= e(__('Vorschau')) ?></h2>
    <div class="ck-preview__tools" role="group" aria-label="<?= e(__('Vorschau einstellen')) ?>">
      <?php foreach (['box' => __('Box'), 'bar' => __('Leiste'), 'modal' => __('Dialog'), 'offcanvas' => __('Off-Canvas')] as $k => $l): ?><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-layout="<?= e($k) ?>" aria-pressed="<?= $k === $preview['layout'] ? 'true' : 'false' ?>"><?= e($l) ?></button><?php endforeach; ?>
      <span class="ck-sep" aria-hidden="true"></span>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-theme="light" aria-pressed="true"><?= e(__('Hell')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-theme="dark" aria-pressed="false"><?= e(__('Dunkel')) ?></button>
      <span class="ck-sep" aria-hidden="true"></span>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-view="banner" aria-pressed="true"><?= e(__('Hinweis')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-view="settings" aria-pressed="false"><?= e(__('Einstellungen')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ck-mobile aria-pressed="false"><?= e(__('Mobil (390 px)')) ?></button>
    </div>
    <p class="adm-muted"><?= e(__('Die Form wird unter Einstellungen gespeichert; hier wirkt der Umschalter nur in der Vorschau. Klicks lösen nichts aus.')) ?></p>
    <div class="ck-stage" data-ck-stage>
      <consent-kit preview layout="<?= e($preview['layout']) ?>" position="bottom-left" theme="light"></consent-kit>
      <?php if ($hasEmbed): ?><consent-embed service="<?= e($hasEmbed) ?>" label="<?= e(__('Beispiel')) ?>" theme="light" ratio="16/9"><template><p><?= e(__('(Hier erschiene der eingebettete Inhalt.)')) ?></p></template></consent-embed><?php endif; ?>
    </div>
  </section>
</div>
<script type="application/json" id="cms-consent-config"><?= json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(Consent::asset('js/consent.js')) ?>" defer></script>
