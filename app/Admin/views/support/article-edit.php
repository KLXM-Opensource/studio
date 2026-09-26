<?php
/**
 * Wissensartikel anlegen/bearbeiten (Support-Team) – auch als Entwurf aus einer gelösten Meldung.
 * @var ?array $a  @var array $in  @var array $errors  @var ?array $issue  @var array $revisions  @var string $title
 */
use Core\Support\Support;
use Core\Support\Ui;

$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="ae-' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k, string $hint = '') => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="ae-' . $k . '-e' . ($hint ? ' ' . $hint : '') . '"' : ($hint ? ' aria-describedby="' . $hint . '"' : '');
$action = $a ? url('/admin/support/wissen/' . $a['id'] . '/bearbeiten') : url('/admin/support/wissen/neu');
$sites = Support::sites();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($a ? '/admin/support/wissen/' . $a['id'] : ($issue ? '/admin/support/meldung/' . $issue['id'] : '/admin/support/wissen'))) ?>">← <?= e($a ? $a['title'] : ($issue ? __('Meldung #{id}', ['id' => $issue['id']]) : __('Wissensdatenbank'))) ?></a></p>
    <h1><?= e($title) ?></h1>
    <?php if ($issue): ?><p class="adm-muted"><?= e(__('Entwurf aus der Meldung: technischer Kontext, Namen, E-Mail-Adressen, Telefonnummern und Website-Adressen wurden entfernt. Bitte vor dem Veröffentlichen lesen und allgemein formulieren.')) ?></p><?php endif; ?></div>
</header>
<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Felder.')) ?></div><?php endif; ?>

<form class="adm-card sp-form sp-article-form" method="post" action="<?= e($action) ?>" data-support-form>
  <?= csrf_field() ?>
  <?php if ($issue): ?><input type="hidden" name="source_issue_id" value="<?= (int) $issue['id'] ?>"><?php endif; ?>
  <div class="f<?= isset($errors['title']) ? ' f--error' : '' ?>">
    <label for="ae-title"><?= e(__('Titel')) ?></label>
    <input id="ae-title" name="title" maxlength="160" required value="<?= e($in['title']) ?>"<?= $inv('title') ?>>
    <?= $err('title') ?>
  </div>
  <div class="f<?= isset($errors['body']) ? ' f--error' : '' ?>">
    <label for="ae-body"><?= e(__('Text')) ?></label>
    <textarea id="ae-body" name="body" rows="18" required<?= $inv('body', 'ae-body-h') ?>><?= e($in['body']) ?></textarea>
    <?= $err('body') ?>
    <?= Ui::mdHint('ae-body-h') ?>
  </div>
  <div class="f">
    <label for="ae-tags"><?= e(__('Tags')) ?></label>
    <input id="ae-tags" name="tags" value="<?= e(is_array($in['tags']) ? implode(', ', $in['tags']) : $in['tags']) ?>" aria-describedby="ae-tags-h" list="ae-tag-list">
    <p class="f-hint" id="ae-tags-h"><?= e(__('Mit Komma trennen, z. B. „medien, bilder“ – höchstens 8.')) ?></p>
    <datalist id="ae-tag-list"><?php foreach (array_keys(\Core\Support\Knowledge::tagCloud()) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
  </div>
  <fieldset class="f sp-vis<?= isset($errors['sites']) ? ' f--error' : '' ?>" data-support-vis>
    <legend><?= e(__('Sichtbar für')) ?></legend>
    <?php foreach (['all', 'sites', 'staff'] as $v): ?>
    <label><input type="radio" name="visibility" value="<?= $v ?>"<?= $in['visibility'] === $v ? ' checked' : '' ?>> <?= e(Support::visibilityLabel($v)) ?></label>
    <?php endforeach; ?>
    <div class="sp-vis__sites" data-support-vis-sites>
      <?php foreach ($sites as $k => $l): ?><label><input type="checkbox" name="sites[]" value="<?= e($k) ?>"<?= in_array($k, (array) $in['sites'], true) ? ' checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?>
      <?= $err('sites') ?>
    </div>
  </fieldset>
  <fieldset class="f sp-prio">
    <legend><?= e(__('Status')) ?></legend>
    <label><input type="radio" name="status" value="published"<?= $in['status'] !== 'draft' ? ' checked' : '' ?>> <?= e(__('Veröffentlicht')) ?></label>
    <label><input type="radio" name="status" value="draft"<?= $in['status'] === 'draft' ? ' checked' : '' ?>> <?= e(__('Entwurf (nur Support-Team)')) ?></label>
  </fieldset>
  <?php if ($a): ?>
  <div class="f"><label for="ae-note"><?= e(__('Änderungsnotiz (optional)')) ?></label><input id="ae-note" name="note" maxlength="200" placeholder="<?= e(__('z. B. „Schritt 3 ergänzt“')) ?>"></div>
  <?php endif; ?>
  <div class="adm-form-actions sp-actions">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e($a ? __('Speichern') : ($issue ? __('Veröffentlichen') : __('Anlegen'))) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e(url($a ? '/admin/support/wissen/' . $a['id'] : '/admin/support/wissen')) ?>"><?= e(__('Abbrechen')) ?></a>
    <?php if ($a && count($revisions) > 1): ?><span class="adm-muted sp-small"><?= e(__('{n} Versionen – ansehen und wiederherstellen auf der Artikelseite.', ['n' => count($revisions)])) ?></span><?php endif; ?>
  </div>
</form>
