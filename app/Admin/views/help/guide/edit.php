<?php
/**
 * Projekt-Hinweis anlegen/bearbeiten (Core\Guide). Gespeichert wird immer die Fassung der Website ({storage}/guide/{key}.md);
 * ein Hinweis aus dem Kit bleibt unverändert und gilt wieder, sobald die Anpassung entfernt wird.
 * @var ?array $note  @var string $key  @var string $src  @var array $errors  @var string $newKey
 */
$base = '/admin/hilfe/projekt';
$isNew = $note === null;
$action = url($base . '/' . ($isNew ? 'neu' : $key));   // vor _helptabs.php (setzt $key)
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="gd-' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k, string $help) => ' aria-describedby="' . $help . (isset($errors[$k]) ? ' gd-' . $k . '-e' : '') . '"' . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
$helpTab = 'manual';
include ROOT . '/app/Admin/views/support/_helptabs.php';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base)) ?>"><?= e(__('Projekt-Hinweise')) ?></a></p>
    <h1><?= e($isNew ? __('Neuer Projekt-Hinweis') : $note['title']) ?></h1>
    <?php if (!$isNew && $note['source'] === 'kit'): ?><p class="adm-muted"><?= e(__('Dieser Hinweis kommt aus dem Kit. Beim Speichern entsteht eine eigene Fassung für diese Website – das Kit bleibt unverändert.')) ?></p><?php endif; ?></div>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <form class="adm-card" method="post" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isNew): ?>
    <div class="f<?= isset($errors['key']) ? ' f--error' : '' ?>">
      <label for="gd-key"><?= e(__('Dateiname')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="gd-key" name="key" value="<?= e($newKey) ?>" required spellcheck="false" autocomplete="off" maxlength="64" pattern="[a-z0-9][a-z0-9_\-]*" placeholder="50-bildformate"<?= $inv('key', 'gd-key-h') ?>>
      <p class="f-help" id="gd-key-h"><?= e(__('Kleinbuchstaben, Ziffern, - und _. Die Zahl vorn bestimmt die Reihenfolge im Handbuch (10-…, 20-…). Gleicher Name wie ein Hinweis aus dem Kit ersetzt diesen.')) ?></p>
      <?= $err('key') ?>
    </div>
    <?php endif; ?>
    <div class="f<?= isset($errors['src']) ? ' f--error' : '' ?>">
      <label for="gd-src"><?= e(__('Text (Markdown)')) ?> <span class="req" aria-hidden="true">*</span></label>
      <textarea id="gd-src" name="src" rows="22" required spellcheck="true" class="gd-src"<?= $inv('src', 'gd-src-h') ?>><?= e($src) ?></textarea>
      <p class="f-help" id="gd-src-h"><?= e(__('Erste Zeile „# Titel“. Dann Absätze, Listen mit „-“, Zwischentitel mit „##“, **fett**, *kursiv*, `Code` und Links [Text](https://… oder /admin/…). HTML ist nicht erlaubt.')) ?></p>
      <?= $err('src') ?>
    </div>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e($isNew ? __('Hinweis anlegen') : __('Speichern')) ?></button>
      <a class="adm-btn adm-btn--ghost" href="<?= e(url($base)) ?>"><?= e(__('Abbrechen')) ?></a></div>
  </form>

  <div>
    <section class="adm-card" aria-labelledby="gd-fm-h">
      <h2 id="gd-fm-h"><?= e(__('Kopfzeilen (optional)')) ?></h2>
      <p class="adm-muted"><?= e(__('Zwischen zwei Zeilen „---“ ganz oben:')) ?></p>
      <ul class="adm-muted">
        <li><code>bereich: medien</code> – <?= e(__('Link „Hinweis zum Projekt“ in diesem Bereich der Verwaltung: seiten, einstellungen, medien, daten, daten/{tabelle}, anfragen, design.')) ?></li>
        <li><code>block: hero, stage</code> – <?= e(__('Link im Formular dieser Blocktypen.')) ?></li>
        <li><code>titel: …</code> – <?= e(__('statt der ersten Überschrift.')) ?></li>
        <li><code>ausblenden: ja</code> – <?= e(__('Hinweis (z. B. aus dem Kit) für diese Website ausblenden.')) ?></li>
      </ul>
    </section>
  </div>
</div>
