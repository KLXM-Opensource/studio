<?php
/**
 * Problem melden: Formular mit Vorschlägen aus der Wissensdatenbank (resources/js/_support.js) und sichtbarem Kontext.
 * @var array $in  @var array $errors  @var string $from  @var array $context  @var array $labels
 */
use Core\Support\Support;
use Core\Support\Tickets;
use Core\Support\Ui;

$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="sp-' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k, string $hint = '') => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="sp-' . $k . '-e' . ($hint ? ' ' . $hint : '') . '"' : ($hint ? ' aria-describedby="' . $hint . '"' : '');
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/support')) ?>"><?= e(__('Support')) ?></a></p><h1><?= e(__('Problem melden')) ?></h1>
    <p class="adm-muted"><?= e(__('Beschreiben Sie, was nicht klappt oder was Sie brauchen. Das Support-Team antwortet hier und per E-Mail.')) ?></p></div>
</header>

<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Felder.')) ?></div><?php endif; ?>

<div class="sp-report">
  <form class="adm-card sp-form" method="post" action="<?= e(url('/admin/support/neu')) ?>" enctype="multipart/form-data" data-support-form novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="from" value="<?= e($from) ?>">
    <input type="hidden" name="viewport" value="" data-support-viewport>

    <fieldset class="f sp-choice">
      <legend><?= e(__('Worum geht es?')) ?></legend>
      <div class="sp-choice__opts">
        <?php foreach (['frage' => __('Ich habe eine Frage'), 'fehler' => __('Etwas funktioniert nicht'), 'wunsch' => __('Ich wünsche mir etwas')] as $k => $sub): ?>
        <label class="sp-choice__opt"><input type="radio" name="category" value="<?= e($k) ?>"<?= $in['category'] === $k ? ' checked' : '' ?>>
          <span><b><?= e(Support::categoryLabel($k)) ?></b><small><?= e($sub) ?></small></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="f<?= isset($errors['title']) ? ' f--error' : '' ?>">
      <label for="sp-title"><?= e(__('Kurzer Titel')) ?> <span class="sp-req" aria-hidden="true">*</span></label>
      <input id="sp-title" name="title" maxlength="160" required value="<?= e($in['title']) ?>" placeholder="<?= e(__('z. B. „Bild wird auf dem Handy abgeschnitten“')) ?>" data-support-similar<?= $inv('title') ?>>
      <?= $err('title') ?>
    </div>

    <div class="f<?= isset($errors['body']) ? ' f--error' : '' ?>">
      <label for="sp-body"><?= e(__('Beschreibung')) ?> <span class="sp-req" aria-hidden="true">*</span></label>
      <textarea id="sp-body" name="body" rows="8" required data-support-similar data-support-paste placeholder="<?= e(__('Was haben Sie getan, was ist passiert, was hätten Sie erwartet? Auf welcher Seite?')) ?>"<?= $inv('body', 'sp-body-h') ?>><?= e($in['body']) ?></textarea>
      <?= $err('body') ?>
      <?= Ui::mdHint('sp-body-h') ?>
    </div>

    <fieldset class="f sp-prio">
      <legend><?= e(__('Priorität')) ?></legend>
      <label><input type="radio" name="priority" value="normal"<?= $in['priority'] !== 'dringend' ? ' checked' : '' ?>> <?= e(__('Normal')) ?></label>
      <label><input type="radio" name="priority" value="dringend" aria-describedby="sp-prio-h"<?= $in['priority'] === 'dringend' ? ' checked' : '' ?>> <?= e(__('Dringend')) ?></label>
      <p class="f-hint" id="sp-prio-h"><?= e(__('Dringend: Die Website ist nicht erreichbar, Formulare kommen nicht an oder etwas Wichtiges ist falsch online.')) ?></p>
    </fieldset>

    <div class="f sp-shots" data-support-shots>
      <label for="sp-shots"><?= e(__('Bildschirmfotos (optional)')) ?></label>
      <input id="sp-shots" name="shots[]" type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple aria-describedby="sp-shots-h">
      <p class="f-hint" id="sp-shots-h"><?= e(__('Bis zu {n} Bilder (PNG, JPEG, WebP, GIF), je höchstens {mb} MB. Tipp: Bildschirmfoto kopieren und mit Strg/⌘+V in die Beschreibung einfügen. Bilder sieht nur das Support-Team – Metadaten wie Standort werden entfernt.', ['n' => Tickets::MAX_FILES, 'mb' => Tickets::MAX_BYTES / 1048576])) ?></p>
      <ul class="sp-thumbs" data-support-thumbs aria-live="polite"></ul>
    </div>

    <section class="sp-context" aria-labelledby="sp-ctx-h">
      <h2 id="sp-ctx-h"><?= e(__('Diese technischen Angaben werden mitgesendet')) ?></h2>
      <p class="adm-muted"><?= e(__('Sie helfen dem Support, das Problem nachzuvollziehen. Außerdem sehen Support-Team und die Administration Ihrer Website Ihren Namen und Ihre E-Mail-Adresse.')) ?></p>
      <dl class="sp-dl">
        <?php foreach ($context as $k => $v): ?><dt><?= e($labels[$k] ?? $k) ?></dt><dd<?= $k === 'viewport' ? ' data-support-viewport-out' : '' ?>><?= e($v) ?></dd><?php endforeach; ?>
        <?php if (!isset($context['viewport'])): ?><dt data-support-viewport-row hidden><?= e($labels['viewport']) ?></dt><dd data-support-viewport-out hidden></dd><?php endif; ?>
      </dl>
      <label class="sp-check"><input type="checkbox" name="send_context" value="1" checked> <?= e(__('Technische Angaben mitsenden')) ?></label>
    </section>

    <div class="adm-form-actions sp-actions">
      <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Meldung senden')) ?></button>
      <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/support')) ?>"><?= e(__('Abbrechen')) ?></a>
    </div>
  </form>

  <aside class="sp-suggest" aria-labelledby="sp-sugg-h" data-support-suggest data-endpoint="<?= e(url('/admin/api/support/similar')) ?>">
    <h2 id="sp-sugg-h"><?= e(__('Vielleicht hilft das schon')) ?></h2>
    <p class="adm-muted" data-support-suggest-hint><?= e(__('Während Sie schreiben, erscheinen hier passende Artikel aus der Wissensdatenbank und bereits beantwortete Fragen.')) ?></p>
    <ul class="sp-suggest__list" data-support-suggest-list aria-live="polite"></ul>
    <p class="sp-suggest__more"><a href="<?= e(url('/admin/support/wissen')) ?>"><?= e(__('Wissensdatenbank durchsuchen')) ?> →</a></p>
  </aside>
</div>
