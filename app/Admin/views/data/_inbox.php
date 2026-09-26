<?php
/**
 * Tabellen-Baukasten: Einstellungen einer Eingangs-Tabelle (verschlüsselte Anfragen, Core\Data\Inbox).
 * @var array $s  @var array $def  @var ?array $table  @var callable $err
 */
use Core\Data\DataForms;
use Core\Data\Inbox;
use Core\FormCrypto;
use Core\Lang;

$fm = (array) ($s['form'] ?? []) + DataForms::DEFAULTS;
$ib = (array) ($s['inbox'] ?? []) + Inbox::DEFAULTS;
$fmAll = array_filter($def['fields'] ?? [], fn($f) => DataForms::eligible($f, false));
$themeForm = $ib['form'] !== '' ? (app()->theme->forms()[$ib['form']] ?? null) : null;
$langs = array_diff_key(Lang::all(), [Lang::default() => 1]);
?>
    <section class="adm-card dt-form">
      <h2><?= e(__('Formular')) ?></h2>
      <input type="hidden" name="settings[inbox][form]" value="<?= e($ib['form']) ?>">
      <input type="hidden" name="settings[form][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[form][enabled]" value="1"<?= $fm['enabled'] ? ' checked' : '' ?>> <span><?= e(__('Formular nimmt Anfragen an')) ?></span></label>
      <p class="f-help">
        <?php if ($themeForm): ?><?= e(__('Formular des Kits unter {url} (Anzeige und Link steuern die Einstellungen des Kits).', ['url' => '/anfrage/' . $ib['form']])) ?>
        <?php else: ?><?= e(__('Auf einer Seite mit dem Block „Formular (Datentabelle)“ einfügen.')) ?><?php endif; ?>
      </p>
      <p class="f-help"><?= FormCrypto::ready()
          ? e(__('Verschlüsselung aktiv (Fingerabdruck {fp}).', ['fp' => FormCrypto::fingerprint()]))
          : '<b>' . e(__('Kein öffentlicher Schlüssel – das Formular zeigt „noch nicht eingerichtet“.')) . '</b> <a href="' . e(url('/admin/system#keys')) . '">' . e(__('Schlüssel erzeugen')) . '</a>' ?></p>
      <fieldset class="f dt-form__fields"><legend><?= e(__('Felder im Formular')) ?></legend>
        <input type="hidden" name="settings[form][fields][]" value="">
        <?php if (!$fmAll): ?><p class="f-help"><?= e(__('Neue Felder erscheinen hier nach dem Speichern.')) ?></p><?php endif; ?>
        <?php foreach ($fmAll as $f): ?>
        <label class="f-check"><input type="checkbox" name="settings[form][fields][]" value="<?= e($f['name']) ?>"<?= !$fm['fields'] || in_array($f['name'], $fm['fields'], true) || !empty($f['required']) ? ' checked' : '' ?><?= !empty($f['required']) ? ' disabled' : '' ?>><?php if (!empty($f['required'])): ?><input type="hidden" name="settings[form][fields][]" value="<?= e($f['name']) ?>"><?php endif; ?>
          <span><?= e($f['label']) ?><?= !empty($f['required']) ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?></span></label>
        <?php endforeach; ?>
        <p class="f-help"><?= e(__('Nichts angehakt = alle Felder. Die Checkbox „Datenschutzhinweise gelesen“ wird immer ergänzt.')) ?></p>
      </fieldset>
      <div class="f"><label for="t-ib-title"><?= e(__('Titel des Formulars')) ?></label>
        <input id="t-ib-title" name="settings[inbox][title]" value="<?= e($ib['title']) ?>" placeholder="<?= e($def['name'] ?? '') ?>" maxlength="120"></div>
      <div class="f"><label for="t-ib-intro"><?= e(__('Einleitung (optional)')) ?></label>
        <textarea id="t-ib-intro" name="settings[inbox][intro]" rows="2" maxlength="600"><?= e($ib['intro']) ?></textarea></div>
      <div class="f"><label for="t-form-success"><?= e(__('Text nach dem Absenden')) ?></label>
        <textarea id="t-form-success" name="settings[form][success]" rows="2" placeholder="<?= e($themeForm['success'] ?? 'Vielen Dank – Ihre Anfrage ist eingegangen.') ?>"><?= e($fm['success']) ?></textarea>
        <p class="f-help"><?= e(__('Leer = Text des Kits bzw. „Vielen Dank – Ihre Anfrage ist eingegangen.“')) ?></p></div>
      <div class="f"><label for="t-form-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
        <input id="t-form-submit" name="settings[form][submit]" value="<?= e($fm['submit']) ?>" placeholder="<?= e(__('Absenden')) ?>" maxlength="60"></div>
      <?php foreach ($langs as $lc => $ll): $tr = (array) ($ib['i18n'][$lc] ?? []); ?>
      <details class="dt-trans"<?= $tr ? ' open' : '' ?>><summary><?= e(__('Übersetzung')) ?>: <?= e($ll) ?></summary>
        <?php foreach (['title' => __('Titel des Formulars'), 'intro' => __('Einleitung (optional)'), 'success' => __('Text nach dem Absenden'), 'submit' => __('Beschriftung des Buttons')] as $k => $l): ?>
        <div class="f"><label for="t-ib-<?= e($lc . '-' . $k) ?>"><?= e($l) ?> (<?= e($ll) ?>)</label>
          <input id="t-ib-<?= e($lc . '-' . $k) ?>" name="settings[inbox][i18n][<?= e($lc) ?>][<?= $k ?>]" value="<?= e((string) ($tr[$k] ?? '')) ?>"></div>
        <?php endforeach; ?>
      </details>
      <?php endforeach; ?>
      <div class="f"><label for="t-form-notify"><?= e(__('Benachrichtigung an (E-Mail)')) ?></label>
        <input id="t-form-notify" name="settings[form][notify]" value="<?= e($fm['notify']) ?>" placeholder="<?= e(__('leer = Empfänger aus den Grundeinstellungen')) ?>" autocomplete="off">
        <p class="f-help"><?= e(__('Die E-Mail enthält keine Inhalte – nur den Hinweis auf eine neue Anfrage und einen Link.')) ?></p></div>
      <?= $err('settings.form') ?>
    </section>

    <section class="adm-card">
      <h2><?= e(__('Datenschutz')) ?></h2>
      <div class="f"><label for="t-ib-ret"><?= e(__('Erledigte Anfragen automatisch löschen nach (Tagen)')) ?></label>
        <input type="number" id="t-ib-ret" name="settings[inbox][retention_days]" min="0" max="3650" value="<?= (int) $ib['retention_days'] ?>">
        <p class="f-help"><?= e(__('0 = nie. Gezählt ab dem Tag, an dem die Anfrage als erledigt markiert wurde.')) ?></p></div>
      <p class="dt-note"><?= e(__('Alle Angaben werden Ende-zu-Ende verschlüsselt gespeichert; lesbar nur unter „Anfragen“ mit dem geheimen Schlüssel. Keine Detailseiten, keine Ausgabe auf der Website, nicht in Suche, Sitemap, API-Inhalten oder Kalender-Abos. Jedes Entschlüsseln wird protokolliert.')) ?></p>
    </section>
