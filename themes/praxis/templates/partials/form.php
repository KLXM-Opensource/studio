<?php
/**
 * Öffentliches Formular (Rezept / Überweisung) – Theme-Darstellung der verschlüsselten Eingangs-Tabelle.
 * Felder: Daten → Rezeptanfragen bzw. Überweisungen → Felder & Einstellungen (Core\Forms bildet sie ab).
 * Spamschutz: Honeypot, Token, Proof-of-Work. Absenden an /anfrage/{form} (verschlüsselt gespeichert).
 * @var string $form  @var bool $compact  @var array $values  @var array $errors  @var ?array $challenge
 */
use Core\Forms;
use Core\FormCrypto;
use Core\SpamGuard;

$values ??= [];
$errors ??= [];
$challenge ??= null;
$fields = Forms::fields($form);
$uid = 'f' . substr(md5($form . ($compact ? 'c' : 'p')), 0, 5);
$ready = FormCrypto::ready();
?>
<?php if (!$ready): ?>
<p class="form-off" role="note"><?= praxis_fill(lt('Das Online-Formular wird gerade eingerichtet. Bitte rufen Sie uns an: {phone}.'), ['phone' => '<a href="' . e(praxis_phone_href()) . '">' . e(praxis_phone()) . '</a>']) ?></p>
<?php else: ?>
<form class="pform" method="post" action="<?= e(url('/anfrage/' . $form)) ?>" data-form="<?= e($form) ?>" novalidate>
  <div class="pform__grid">
    <?php foreach ($fields as $f):
        if ($f['type'] === 'group') {   // Wiederholbare Gruppe (z. B. mehrere Medikamente) – Markup aus dem Kern, Klassen des Kits
            echo \Core\Data\DataForms::group($f, $uid, $values[$f['name']] ?? null, $errors,
                ['f' => 'pfield', 'half' => '', 'full' => 'pfield--full', 'req' => '', 'err' => 'perr', 'help' => 'pform__hint', 'check' => 'pcheck', 'opt' => 'opt']);
            continue;
        }
        $id = $uid . '-' . $f['name'];
        $err = $errors[$f['name']] ?? null;
        $val = (string) ($values[$f['name']] ?? '');
        $req = $f['required'] ? ' required aria-required="true"' : '';
        $aria = ' aria-describedby="' . $id . '-e"' . ($err ? ' aria-invalid="true"' : '');
        $full = $f['width'] === 'full' || $f['type'] === 'bool';
    ?>
    <div class="pfield<?= $full ? ' pfield--full' : '' ?><?= $f['type'] === 'bool' ? ' pfield--check' : '' ?>">
      <?php if ($f['type'] === 'bool'): ?>
        <label class="pcheck"><input type="checkbox" id="<?= $id ?>" name="<?= e($f['name']) ?>" value="1"<?= $val ? ' checked' : '' ?><?= $req . $aria ?>> <?= e($f['label']) ?></label>
      <?php else: ?>
        <label for="<?= $id ?>"><?= e($f['label']) ?><?= $f['required'] ? '' : ' <span class="opt">' . e(lt('(optional)')) . '</span>' ?></label>
        <?php if ($f['type'] === 'select'): ?>
        <select id="<?= $id ?>" name="<?= e($f['name']) ?>"<?= $req . $aria ?>>
          <?php if (!$f['required']): ?><option value=""></option><?php endif; ?>
          <?php foreach ($f['options'] as $k => $l): ?><option value="<?= e((string) $k) ?>"<?= (string) $k === $val ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <?php elseif ($f['type'] === 'textarea'): ?>
        <textarea id="<?= $id ?>" name="<?= e($f['name']) ?>" rows="4" maxlength="5000"<?= $req . $aria ?>><?= e($val) ?></textarea>
        <?php else: ?>
        <input id="<?= $id ?>" name="<?= e($f['name']) ?>" type="<?= e(['date' => 'date', 'tel' => 'tel', 'email' => 'email', 'number' => 'number', 'time' => 'time', 'datetime' => 'datetime-local'][$f['type']] ?? 'text') ?>" value="<?= e($val) ?>"<?= $req . $aria ?>
          autocomplete="<?= e(['vorname' => 'given-name', 'nachname' => 'family-name', 'telefon' => 'tel', 'geburtsdatum' => 'bday', 'email' => 'email'][$f['name']] ?? 'off') ?>" maxlength="300">
        <?php endif; ?>
      <?php endif; ?>
      <p class="perr" id="<?= $id ?>-e"<?= $err ? '' : ' hidden' ?>><?= e($err ?? '') ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <?php $pid = $uid . '-' . Forms::PRIVACY_FIELD; $perr = $errors[Forms::PRIVACY_FIELD] ?? null; ?>
  <div class="pfield pfield--check">
    <label class="pcheck"><input type="checkbox" id="<?= $pid ?>" name="<?= Forms::PRIVACY_FIELD ?>" value="1" required aria-required="true" aria-describedby="<?= $pid ?>-e"<?= $perr ? ' aria-invalid="true"' : '' ?><?= !empty($values[Forms::PRIVACY_FIELD]) ? ' checked' : '' ?>>
      <span><?= praxis_fill(lt('Ich habe die {link} gelesen. Bitte keine Beschwerden oder Diagnosen eingeben.'), ['link' => '<a href="' . e(praxis_privacy_url()) . '">' . e(lt('Datenschutzhinweise')) . '</a>']) ?></span></label>
    <p class="perr" id="<?= $pid ?>-e"<?= $perr ? '' : ' hidden' ?>><?= e($perr ?? '') ?></p>
  </div>

  <?php if (setting('sys.spam_honeypot', true)): ?>
  <div class="hp" aria-hidden="true"><label>Website<input type="text" name="<?= SpamGuard::HONEYPOT ?>" tabindex="-1" autocomplete="off"></label></div>
  <?php endif; ?>
  <input type="hidden" name="_token" value="<?= e($challenge['token'] ?? '') ?>">
  <input type="hidden" name="_pow" value="">
  <?php if ($challenge): ?><input type="hidden" name="_difficulty" value="<?= (int) $challenge['difficulty'] ?>"><?php endif; ?>

  <p class="pform__msg" role="alert" hidden></p>
  <button type="submit" class="btn-wide"><?= e(lt('Anfrage senden')) ?> <span aria-hidden="true">→</span></button>
  <p class="pform__hint"><?= e(setting('formular_hinweis') ?: lt('Übermittlung verschlüsselt. Ihre Angaben sind nur für die Praxis lesbar.')) ?></p>
  <noscript><p class="pform__hint"><?= e(lt('Hinweis: Für die Sicherheitsprüfung wird JavaScript benötigt. Alternativ erreichen Sie uns telefonisch.')) ?></p></noscript>
</form>
<div class="pform__done" role="status" hidden>
  <span aria-hidden="true" class="pform__check">✓</span>
  <p class="pform__done-title"><?= e(lt(Forms::def($form)['success'] ?? lt('Vielen Dank.'))) ?></p>
  <p class="pform__done-text"><?= e(lt('Bearbeitung: {time}. Bei Rückfragen melden wir uns telefonisch.', ['time' => setting('bearbeitungsfrist_text') ?: lt('[Frist – noch zu bestätigen]')])) ?></p>
  <?php if (!empty($compact)): ?><button type="button" class="btn-back" data-flip-back><?= e(lt('Zur Übersicht')) ?></button><?php endif; ?>
</div>
<?php endif; ?>
