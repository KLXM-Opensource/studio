<?php
/**
 * Seitenleiste „Felder bearbeiten“ im Seiten-Editor (geladen von resources/js/_form_fields.js, Endpunkt Admin\FormFieldsController).
 * Felder im Eingabeformat von Tables::validate (Tables::toInput bzw. die zuletzt gesendete Eingabe bei Fehlern), Klassen des
 * Tabellen-Designers (admin.shadow.css) mit Anpassungen für die schmale Leiste (entry-panel.css, .ff).
 * @var array $t  @var array $fields  @var array $form  @var array $errors  @var bool $askDrop  @var int $count  @var bool $saved
 */
use Core\Data\DataForms;
use Core\Data\Delivery;
use Core\Data\SchemaPanel;
use Core\Data\Tables;

$inbox = Tables::isInbox($t);
$kindLabels = ['pdf' => __('PDF'), 'image' => __('Bilder (JPG, PNG, WebP)'), 'docx' => __('Word (DOCX)'), 'odt' => __('OpenDocument-Text (ODT)')];
$adminUrl = url('/admin/data/' . $t['handle'] . '/schema');

/** Eine Feldzeile; $i = Position (bzw. „__i__“ in der Vorlage) */
$row = function (int|string $i, array $f, ?string $err) use ($t, $inbox, $kindLabels, $count): string {
    $n = 'fields[' . $i . ']';
    $type = (string) ($f['type'] ?? 'text');
    $id = (string) ($f['id'] ?? '');
    $uid = 'ff-' . $i;
    $opts = is_array($f['options'] ?? null) ? Tables::fieldInput($f)['options'] : (string) ($f['options'] ?? '');
    $acc = array_map('strval', (array) ($f['accept'] ?? DataForms::FILE_KINDS_DEFAULT));
    $label = (string) ($f['label'] ?? '');
    ob_start(); ?>
<li class="dt-field ff-field<?= $err ? ' is-error' : '' ?>" data-ff-field data-type="<?= e($type) ?>" data-orig-type="<?= e($id !== '' ? $type : '') ?>">
  <input type="hidden" name="<?= $n ?>[id]" value="<?= e($id) ?>">
  <div class="dt-field__main">
    <div class="dt-field__row ff-field__row">
      <label class="dt-in dt-in--label"><span><?= e(__('Bezeichnung')) ?></span><input name="<?= $n ?>[label]" value="<?= e($label) ?>" data-ff-label<?= $err ? ' aria-invalid="true" aria-describedby="' . $uid . '-err"' : '' ?>></label>
      <label class="dt-in dt-in--name"><span><?= e(__('Kurzname')) ?></span><input name="<?= $n ?>[name]" value="<?= e((string) ($f['name'] ?? '')) ?>" pattern="[a-z][a-z0-9_]*" spellcheck="false" autocapitalize="off" data-ff-name<?= $id !== '' ? ' data-locked="1"' : '' ?> aria-describedby="<?= $uid ?>-nh"></label>
      <label class="dt-in dt-in--type"><span><?= e(__('Typ')) ?></span><select name="<?= $n ?>[type]" data-ff-type>
        <?php foreach (SchemaPanel::types($t, $type) as $k): ?><option value="<?= e($k) ?>"<?= $k === $type ? ' selected' : '' ?>><?= e(__(Tables::TYPES[$k][0])) ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <p class="ff-field__namehelp" id="<?= $uid ?>-nh"><?= e($id !== '' ? __('Kurzname: für Platzhalter und Schnittstellen – nur ändern, wenn nötig.') : __('Kurzname: wird aus der Bezeichnung erzeugt.')) ?></p>
    <p class="ff-note" data-ff-typenote hidden><?= e($count > 0
        ? __('Typ geändert: Beim Speichern werden die vorhandenen Inhalte dieses Feldes in {n} Einträgen umgewandelt – was nicht zum neuen Typ passt, geht verloren.', ['n' => $count])
        : __('Typ geändert – wirksam nach dem Speichern.')) ?></p>
    <div class="dt-field__row dt-field__flags" data-hide-for="<?= e(implode(' ', Tables::LAYOUT)) ?>"<?= Tables::isLayout($f) ? ' hidden' : '' ?>>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[required]" value="1"<?= !empty($f['required']) ? ' checked' : '' ?>> <span><?= e(__('Pflichtfeld')) ?></span></label>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[width]" value="half"<?= ($f['width'] ?? '') === 'half' ? ' checked' : '' ?>> <span><?= e(__('Halbe Breite')) ?></span></label>
    </div>
    <div class="dt-field__extra" data-show-for="select multiselect"<?= in_array($type, ['select', 'multiselect'], true) ? '' : ' hidden' ?>>
      <label class="dt-in"><span><?= e(__('Auswahlmöglichkeiten (eine pro Zeile)')) ?></span><textarea name="<?= $n ?>[options]" rows="3"<?= in_array($type, ['select', 'multiselect'], true) ? '' : ' disabled' ?>><?= e($opts) ?></textarea></label>
    </div>
    <div class="dt-field__extra" data-show-for="file"<?= $type === 'file' ? '' : ' hidden' ?>>
      <fieldset class="dt-kinds"<?= $type === 'file' ? '' : ' disabled' ?>><legend><?= e(__('Erlaubte Dateitypen')) ?></legend>
        <input type="hidden" name="<?= $n ?>[accept][]" value="">
        <?php foreach ($kindLabels as $k => $l): if (!$inbox && in_array($k, DataForms::FILE_KINDS_INBOX, true)) continue; ?>
        <label class="f-check"><input type="checkbox" name="<?= $n ?>[accept][]" value="<?= $k ?>"<?= in_array($k, $acc, true) ? ' checked' : '' ?>> <span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <label class="dt-in dt-in--mb"><span><?= e(__('Höchstgröße (MB, optional)')) ?></span><input type="number" name="<?= $n ?>[max_mb]" min="1" max="<?= DataForms::MAX_MB ?>" value="<?= !empty($f['max_mb']) ? (int) $f['max_mb'] : '' ?>" placeholder="<?= e(__('wie Tabelle')) ?>"<?= $type === 'file' ? '' : ' disabled' ?>></label>
      <p class="f-help"><?= e($inbox ? __('Geprüft wird der Inhalt der Datei, nicht nur die Endung. Dateien nimmt das Formular nur bei Zustellung per E-Mail an.')
          : __('Geprüft wird der Inhalt der Datei, nicht nur die Endung. Word und OpenDocument gibt es nur in Eingangs-Tabellen.')) ?></p>
    </div>
    <div class="dt-field__extra" data-show-for="section"<?= $type === 'section' ? '' : ' hidden' ?>>
      <label class="dt-in"><span><?= e(__('Darstellung')) ?></span><select name="<?= $n ?>[style]"<?= $type === 'section' ? '' : ' disabled' ?>>
        <option value="heading"<?= ($f['style'] ?? 'heading') !== 'fieldset' ? ' selected' : '' ?>><?= e(__('Zwischenüberschrift')) ?></option>
        <option value="fieldset"<?= ($f['style'] ?? '') === 'fieldset' ? ' selected' : '' ?>><?= e(__('Gruppe mit Rahmen')) ?></option>
      </select></label>
      <p class="f-help"><?= e(__('Die folgenden Felder gehören zu diesem Abschnitt – bis zum nächsten Abschnitt. Die Bezeichnung ist die Überschrift im Formular, der Hilfetext steht darunter. Speichert keine Daten.')) ?></p>
    </div>
    <p class="f-help" data-show-for="content"<?= $type === 'content' ? '' : ' hidden' ?>><?= e(__('Den Text des Freitexts ändern Sie unter „Alle Einstellungen der Tabelle“.')) ?></p>
    <p class="f-help" data-show-for="group"<?= $type === 'group' ? '' : ' hidden' ?>><?= e(__('Unterfelder, Anzahl und Beschriftungen der Gruppe ändern Sie unter „Alle Einstellungen der Tabelle“.')) ?></p>
    <label class="dt-in dt-in--help" data-hide-for="content"<?= $type === 'content' ? ' hidden' : '' ?>><span><?= e(__('Hilfetext (optional)')) ?></span><input name="<?= $n ?>[help]" value="<?= e((string) ($f['help'] ?? '')) ?>" maxlength="<?= $type === 'section' ? 500 : 200 ?>"></label>
    <?php if ($err): ?><p class="f-error" id="<?= $uid ?>-err"><?= e($err) ?></p><?php endif; ?>
    <div class="ff-confirm" data-ff-confirm role="group" aria-label="<?= e(__('Feld entfernen?')) ?>" hidden>
      <p><?= $inbox || $id === '' ? e(__('Feld entfernen?')) : e(__('Feld entfernen? Beim Speichern werden seine Inhalte gelöscht.')) ?></p>
      <button type="button" class="adm-btn adm-btn--small adm-btn--danger" data-ff-remove-yes><?= e(__('Entfernen')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ff-remove-no><?= e(__('Abbrechen')) ?></button>
    </div>
  </div>
  <div class="dt-field__tools">
    <button type="button" class="cms-iconbtn dt-iconbtn" data-ff-move="-1" aria-label="<?= e(__('„{label}“ nach oben', ['label' => $label ?: __('Feld')])) ?>" title="<?= e(__('Nach oben')) ?>">↑</button>
    <button type="button" class="cms-iconbtn dt-iconbtn" data-ff-move="1" aria-label="<?= e(__('„{label}“ nach unten', ['label' => $label ?: __('Feld')])) ?>" title="<?= e(__('Nach unten')) ?>">↓</button>
    <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-ff-remove aria-label="<?= e(__('„{label}“ entfernen', ['label' => $label ?: __('Feld')])) ?>" title="<?= e(__('Entfernen')) ?>">✕</button>
  </div>
</li>
<?php return (string) ob_get_clean();
};

// „Felder im Formular“: gespeicherte, fürs Formular geeignete Felder (neue kommen beim Speichern automatisch dazu)
$selT = ['settings' => ['kind' => $inbox ? 'inbox' : 'content', 'form' => $form]];
$eligible = array_filter(Tables::dataFields($fields), fn($f) => (string) ($f['id'] ?? '') !== '' && isset($f['type'])
    && ($inbox ? DataForms::eligible($f, false) || $f['type'] === 'file' : DataForms::eligible($f, true)));
?>
<form class="cms-epanel__body ff" data-ff-form novalidate>
  <?php if ($saved): ?><p class="adm-flash adm-flash--success" data-ff-saved><?= e(__('Gespeichert – das Formular auf der Seite ist aktualisiert.')) ?></p><?php endif; ?>
  <?php if ($errors): ?>
  <div class="adm-flash adm-flash--error" role="alert" tabindex="-1" data-ff-errors>
    <p><b><?= e(__('Bitte prüfen Sie die markierten Angaben – es wurde nichts gespeichert.')) ?></b></p>
    <ul><?php foreach ($errors as $k => $msg): ?><li><?= e((string) $msg) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
  <p class="cms-epanel__hint"><?= e($inbox
      ? __('Eingang (verschlüsselt): Änderungen gelten für die Tabelle „{table}“ und jedes Formular, das sie nutzt. Keine Bilder, Verknüpfungen oder formatierten Texte; Dateifelder nur bei Zustellung per E-Mail.', ['table' => $t['name']])
      : __('Änderungen gelten für die Tabelle „{table}“ – überall, wo sie genutzt wird ({n} Einträge).', ['table' => $t['name'], 'n' => $count])) ?></p>

  <section class="ff-sec" aria-labelledby="ff-h-fields">
    <h3 id="ff-h-fields"><?= e(__('Felder')) ?></h3>
    <ol class="dt-fields ff-fields" data-ff-fields>
      <?php foreach (array_values($fields) as $i => $f) echo $row($i, $f, $errors["fields.$i"] ?? null); ?>
    </ol>
    <template data-ff-template><?= $row('__i__', ['type' => 'text', 'label' => '', 'name' => ''], null) ?></template>
    <div class="dt-addfield" role="group" aria-labelledby="ff-add-l">
      <span class="adm-muted" id="ff-add-l"><?= e(__('Feld hinzufügen:')) ?></span>
      <?php foreach (SchemaPanel::addTypes($t) as $type): [$tl, , $ti] = Tables::TYPES[$type]; ?>
      <button type="button" class="dt-typebtn" data-ff-add="<?= e($type) ?>" data-label="<?= e(__($tl)) ?>"><?= icon($ti) ?> <?= e(__($tl)) ?></button>
      <?php endforeach; ?>
    </div>
    <?php if ($inbox && !Delivery::mails($t)): ?><p class="f-help"><?= e(__('Dateifelder gibt es in Eingängen nur mit Zustellung per E-Mail – einstellbar unter „Alle Einstellungen der Tabelle“.')) ?></p><?php endif; ?>
    <?php if (isset($errors['fields'])): ?><p class="f-error"><?= e($errors['fields']) ?></p><?php endif; ?>
    <?php if ($askDrop): ?>
    <label class="f-check dt-confirmdrop"><input type="checkbox" name="confirm_drop" value="1" data-ff-confirmdrop> <span><b><?= e(__('Felder wirklich löschen')) ?></b> (<?= e(__('Inhalte gehen verloren')) ?>)</span></label>
    <?php endif; ?>
  </section>

  <section class="ff-sec" aria-labelledby="ff-h-form">
    <h3 id="ff-h-form"><?= e(__('Formular')) ?></h3>
    <label class="f-check"><input type="checkbox" name="form[enabled]" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>> <span><?= e($inbox ? __('Formular nimmt Anfragen an') : __('Besucher können Einträge anlegen')) ?></span></label>
    <fieldset class="f ff-formfields"><legend><?= e(__('Felder im Formular')) ?></legend>
      <input type="hidden" name="form[fields][]" value="">
      <?php foreach ($eligible as $f): $req = !empty($f['required']); $upl = in_array($f['type'], DataForms::UPLOAD_TYPES, true); ?>
      <label class="f-check"><input type="checkbox" name="form[fields][]" value="<?= e((string) $f['name']) ?>"<?= DataForms::selected($selT, ['name' => (string) $f['name'], 'required' => $req, 'type' => $f['type']]) ? ' checked' : '' ?><?= $req ? ' disabled' : '' ?>><?php if ($req): ?><input type="hidden" name="form[fields][]" value="<?= e((string) $f['name']) ?>"><?php endif; ?>
        <span><?= e((string) $f['label']) ?><?= $req ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?><?= $upl ? ' <small class="adm-muted">(' . e($inbox ? __('Datei – nur bei Zustellung per E-Mail') : __('nur mit Datei-Uploads')) . ')</small>' : '' ?></span></label>
      <?php endforeach; ?>
      <p class="f-help"><?= e(__('Nichts angehakt = alle passenden Felder. Neu hinzugefügte Felder kommen beim Speichern automatisch ins Formular.')) ?></p>
    </fieldset>
    <div class="f"><label for="ff-success"><?= e(__('Text nach dem Absenden')) ?></label>
      <textarea id="ff-success" name="form[success]" rows="2" maxlength="500" placeholder="<?= e($inbox ? 'Vielen Dank – Ihre Anfrage ist eingegangen.' : __('Vielen Dank – Ihre Angaben sind eingegangen.')) ?>"><?= e((string) $form['success']) ?></textarea></div>
    <div class="f"><label for="ff-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
      <input id="ff-submit" name="form[submit]" value="<?= e((string) $form['submit']) ?>" maxlength="60" placeholder="<?= e(__('Absenden')) ?>"></div>
    <p class="f-help"><?= e(__('Gilt für die Tabelle. Texte, die im Block selbst eingetragen sind (Seitenleiste „Bearbeiten“), haben Vorrang.')) ?></p>
    <?php if (!$inbox): ?>
    <label class="f-check"><input type="checkbox" name="form[uploads]" value="1"<?= !empty($form['uploads']) ? ' checked' : '' ?>> <span><?= e(__('Datei-Uploads erlauben (Bild- und Datei-Felder)')) ?></span></label>
    <div class="f"><label for="ff-mb"><?= e(__('Höchstgröße je Datei (MB)')) ?></label>
      <input type="number" id="ff-mb" name="form[upload_mb]" min="1" max="<?= DataForms::MAX_MB ?>" value="<?= (int) $form['upload_mb'] ?>"></div>
    <?php endif; ?>
    <?php if (isset($errors['settings.form'])): ?><p class="f-error"><?= e($errors['settings.form']) ?></p><?php endif; ?>
    <?php if ($inbox): ?><p class="f-help"><?= e(__('Zustellung, Verschlüsselung, Benachrichtigung und Übersetzungen stellen Sie unter „Alle Einstellungen der Tabelle“ ein.')) ?></p><?php endif; ?>
  </section>
</form>
<div class="cms-epanel__foot">
  <button type="button" class="adm-btn adm-btn--primary" data-ff-save><?= e(__('Speichern')) ?></button>
  <button type="button" class="adm-btn adm-btn--ghost" data-ff-close><?= e(__('Abbrechen')) ?></button>
  <a class="cms-epanel__admin" href="<?= e($adminUrl) ?>" target="_blank" rel="noopener"><?= e(__('Alle Einstellungen der Tabelle')) ?> ↗<span class="adm-sr"> <?= e(__('(öffnet in neuem Tab)')) ?></span></a>
</div>
