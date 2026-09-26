<?php
/**
 * Frage stellen / bearbeiten – mit Vorschlägen aus Wissensdatenbank und beantworteten Fragen.
 * @var ?array $q  @var array $in  @var array $errors  @var string $title
 */
use Core\Support\Ui;

$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="ask-' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k, string $hint = '') => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="ask-' . $k . '-e' . ($hint ? ' ' . $hint : '') . '"' : ($hint ? ' aria-describedby="' . $hint . '"' : '');
$helpTab = 'questions';
include __DIR__ . '/_helptabs.php';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($q ? '/admin/support/fragen/' . $q['id'] : '/admin/support/fragen')) ?>">← <?= e($q ? $q['title'] : __('Fragen & Antworten')) ?></a></p><h1><?= e($title) ?></h1>
    <p class="adm-muted"><?= e(__('Andere Redaktionen und das Support-Team antworten. Für Fehler oder dringende Probleme besser „Problem melden“ nutzen.')) ?></p></div>
</header>
<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Felder.')) ?></div><?php endif; ?>

<div class="sp-report">
  <form class="adm-card sp-form" method="post" action="<?= e(url($q ? '/admin/support/fragen/' . $q['id'] . '/bearbeiten' : '/admin/support/fragen/neu')) ?>" data-support-form>
    <?= csrf_field() ?>
    <div class="f<?= isset($errors['title']) ? ' f--error' : '' ?>">
      <label for="ask-title"><?= e(__('Ihre Frage')) ?></label>
      <input id="ask-title" name="title" maxlength="160" required value="<?= e($in['title']) ?>" placeholder="<?= e(__('z. B. „Wie verlinke ich eine PDF-Datei im Text?“')) ?>" data-support-similar<?= $inv('title') ?>>
      <?= $err('title') ?>
    </div>
    <div class="f<?= isset($errors['body']) ? ' f--error' : '' ?>">
      <label for="ask-body"><?= e(__('Details')) ?></label>
      <textarea id="ask-body" name="body" rows="8" required data-support-similar<?= $inv('body', 'ask-body-h') ?>><?= e($in['body']) ?></textarea>
      <?= $err('body') ?>
      <?= Ui::mdHint('ask-body-h') ?>
    </div>
    <div class="f">
      <label for="ask-tags"><?= e(__('Tags')) ?></label>
      <input id="ask-tags" name="tags" value="<?= e($in['tags']) ?>" aria-describedby="ask-tags-h" list="ask-tag-list">
      <p class="f-hint" id="ask-tags-h"><?= e(__('Mit Komma trennen, z. B. „medien, bilder“ – höchstens 8.')) ?></p>
      <datalist id="ask-tag-list"><?php foreach (array_keys(\Core\Support\Knowledge::tagCloud()) as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    </div>
    <fieldset class="f sp-prio">
      <legend><?= e(__('Sichtbar für')) ?></legend>
      <label><input type="radio" name="visibility" value="all"<?= $in['visibility'] !== 'site' ? ' checked' : '' ?> aria-describedby="ask-vis-h"> <?= e(__('Alle Websites')) ?></label>
      <label><input type="radio" name="visibility" value="site"<?= $in['visibility'] === 'site' ? ' checked' : '' ?>> <?= e(__('Nur meine Website (und Support-Team)')) ?></label>
      <p class="f-hint" id="ask-vis-h"><?= e(__('Redaktionen anderer Websites sehen Ihren Namen nicht. Bitte keine Kundendaten oder Passwörter in Fragen schreiben.')) ?></p>
    </fieldset>
    <div class="adm-form-actions sp-actions">
      <button class="adm-btn adm-btn--primary" type="submit"><?= e($q ? __('Speichern') : __('Frage veröffentlichen')) ?></button>
      <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/support/fragen')) ?>"><?= e(__('Abbrechen')) ?></a>
    </div>
  </form>
  <aside class="sp-suggest" aria-labelledby="sp-sugg-h" data-support-suggest data-endpoint="<?= e(url('/admin/api/support/similar')) ?>">
    <h2 id="sp-sugg-h"><?= e(__('Gibt es die Antwort schon?')) ?></h2>
    <p class="adm-muted" data-support-suggest-hint><?= e(__('Während Sie schreiben, erscheinen hier passende Artikel aus der Wissensdatenbank und bereits beantwortete Fragen.')) ?></p>
    <ul class="sp-suggest__list" data-support-suggest-list aria-live="polite"></ul>
  </aside>
</div>
