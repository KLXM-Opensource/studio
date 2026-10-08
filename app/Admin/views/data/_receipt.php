<?php
/**
 * Formular-Einstellung „Eingangsbestätigung“ (Core\Data\DataForms::RECEIPT) – für Datentabellen und Eingänge.
 * Darstellung als Gruppe der Einstellungsliste (Bereich „Benachrichtigungen“ unter „Felder & Einstellungen“).
 * @var array $fm Formular-Einstellungen  @var array $fields Felder der Tabelle
 */
$rc = (array) ($fm['receipt'] ?? []) + \Core\Data\DataForms::RECEIPT;
$mailFields = array_values(array_filter($fields, fn($f) => ($f['type'] ?? '') === 'email'));
?>
<section class="set-group dt-receipt" aria-labelledby="t-rc-title">
  <h3 class="set-group__title" id="t-rc-title"><?= e(__('Eingangsbestätigung an die absendende Person')) ?></h3>
  <div class="set-list">
  <?php if (!$mailFields): ?>
    <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Nicht möglich')) ?></span>
      <span class="set-row__sub"><?= e(__('Braucht ein Feld vom Typ „E-Mail“ in dieser Tabelle.')) ?></span></div></div>
  <?php else: ?>
    <div class="f f--bool"><input type="hidden" name="settings[form][receipt][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[form][receipt][enabled]" value="1"<?= $rc['enabled'] ? ' checked' : '' ?>> <span><?= e(__('Nach dem Absenden eine Bestätigung per E-Mail senden')) ?></span></label></div>
    <div class="f f--inline"><label for="t-rc-field"><?= e(__('An die Adresse aus dem Feld')) ?></label>
      <select id="t-rc-field" name="settings[form][receipt][field]">
        <?php foreach ($mailFields as $f): ?><option value="<?= e($f['name']) ?>"<?= $rc['field'] === $f['name'] ? ' selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="f f--inline"><label for="t-rc-subject"><?= e(__('Betreff')) ?></label>
      <input id="t-rc-subject" name="settings[form][receipt][subject]" value="<?= e($rc['subject']) ?>" maxlength="150" placeholder="<?= e(__('Eingangsbestätigung: {name}', ['name' => '…'])) ?>"></div>
    <div class="f"><label for="t-rc-text"><?= e(__('Text der Bestätigung')) ?></label>
      <textarea id="t-rc-text" name="settings[form][receipt][text]" rows="3" maxlength="3000" placeholder="<?= e(__('wir bestätigen den Eingang Ihrer Angaben.')) ?>"><?= e($rc['text']) ?></textarea>
      <p class="f-help"><?= e(__('Anrede, Zeitpunkt des Eingangs und Grußformel ergänzt das System. Pflicht z. B. beim Widerruf (§ 356a BGB): Bestätigung unverzüglich per E-Mail.')) ?></p></div>
    <div class="f f--bool"><input type="hidden" name="settings[form][receipt][include]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[form][receipt][include]" value="1"<?= $rc['include'] ? ' checked' : '' ?>> <span><?= e(__('Die eingegebenen Angaben in die Bestätigung aufnehmen')) ?></span></label>
      <p class="f-help"><?= e(__('Nur, was die Person selbst eingegeben hat. Bei vertraulichen Angaben ausgeschaltet lassen – E-Mails sind nicht Ende-zu-Ende verschlüsselt.')) ?></p></div>
  <?php endif; ?>
  </div>
</section>
