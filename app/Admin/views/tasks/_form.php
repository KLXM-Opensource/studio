<?php
/**
 * Felder einer Aufgabe (Core\Tasks) – im Dialog und auf der eigenen Seite. Ohne Recht tasks.manage ist man selbst zuständig.
 * Ziel über die Linkauswahl des Cores (Struktur-Browser: Seite, Anker/Block, Eintrag, Datei – stabile Verweise page:ID …).
 * @var array $form  (values, errors, manager, people, roles)  @var string $idp  Präfix der IDs (eindeutig je Seite)
 */
use Core\Fields;
use Core\Tasks\Tasks;

$v = $form['values'];
$er = $form['errors'];
$fe = fn(string $k) => isset($er[$k]) ? '<p class="f-error" id="' . e($idp . '-' . $k . '-e') . '">' . e($er[$k]) . '</p>' : '';
$inv = fn(string $k, bool $help = false) => (isset($er[$k]) ? ' aria-invalid="true"' : '')
    . ($help || isset($er[$k]) ? ' aria-describedby="' . e(trim(($help ? $idp . '-' . $k . '-h ' : '') . (isset($er[$k]) ? $idp . '-' . $k . '-e' : ''))) . '"' : '');
$cur = (string) $v['assignee'];
?>
<div class="tk-form">
  <div class="f<?= isset($er['title']) ? ' f--error' : '' ?>"><label for="<?= e($idp) ?>-title"><?= e(__('Titel')) ?></label>
    <input id="<?= e($idp) ?>-title" name="title" required maxlength="<?= Tasks::TITLE_MAX ?>" value="<?= e((string) $v['title']) ?>" placeholder="<?= e(__('z. B. Öffnungszeiten im Sommer prüfen')) ?>"<?= $inv('title') ?>><?= $fe('title') ?></div>
  <div class="f<?= isset($er['body']) ? ' f--error' : '' ?>"><label for="<?= e($idp) ?>-body"><?= e(__('Beschreibung (optional)')) ?></label>
    <textarea id="<?= e($idp) ?>-body" name="body" rows="4" maxlength="<?= Tasks::BODY_MAX ?>"<?= $inv('body', true) ?>><?= e((string) $v['body']) ?></textarea>
    <p class="f-help" id="<?= e($idp) ?>-body-h"><?= e(__('Was ist zu tun, was ist offen? Möglich: **fett**, Listen mit „- “ am Zeilenanfang, Links [Text](https://…).')) ?></p><?= $fe('body') ?></div>
  <div class="f<?= isset($er['link']) ? ' f--error' : '' ?>"><label for="<?= e($idp) ?>-link"><?= e(__('Bezug (optional)')) ?></label>
    <?= Fields::renderLink(e($idp) . '-link', 'link', (string) $v['link'], ' aria-describedby="' . e($idp) . '-link-h" placeholder="' . e(__('Seite, Block, Eintrag oder Datei')) . '"') ?>
    <p class="f-help" id="<?= e($idp) ?>-link-h"><?= e(__('„Auswählen …“ öffnet den Struktur-Browser: Seite, Abschnitt einer Seite (Anker), Eintrag oder Datei.')) ?></p><?= $fe('link') ?></div>
  <div class="tk-form__row">
    <?php if ($form['manager']): ?>
    <div class="f<?= isset($er['assignee']) ? ' f--error' : '' ?>"><label for="<?= e($idp) ?>-assignee"><?= e(__('Zuständig')) ?></label>
      <select id="<?= e($idp) ?>-assignee" name="assignee"<?= $inv('assignee') ?>>
        <option value=""<?= $cur === '' ? ' selected' : '' ?>><?= e(__('Alle in der Verwaltung')) ?></option>
        <optgroup label="<?= e(__('Rolle')) ?>">
          <?php foreach ($form['roles'] as $rk => $rn): ?><option value="role:<?= e((string) $rk) ?>"<?= $cur === 'role:' . $rk ? ' selected' : '' ?>><?= e($rn) ?></option><?php endforeach; ?>
        </optgroup>
        <optgroup label="<?= e(__('Person')) ?>">
          <?php foreach ($form['people'] as $pid => $pn): ?><option value="user:<?= (int) $pid ?>"<?= $cur === 'user:' . $pid ? ' selected' : '' ?>><?= e($pn) ?></option><?php endforeach; ?>
        </optgroup>
      </select><?= $fe('assignee') ?></div>
    <?php else: ?>
    <div class="f"><span class="f-label"><?= e(__('Zuständig')) ?></span><p class="tk-form__self"><?= icon('user') ?> <?= e(__('Sie selbst')) ?></p>
      <input type="hidden" name="assignee" value="<?= e($cur) ?>"><?= $fe('assignee') ?></div>
    <?php endif; ?>
    <div class="f<?= isset($er['due']) ? ' f--error' : '' ?>"><label for="<?= e($idp) ?>-due"><?= e(__('Fällig am (optional)')) ?></label>
      <input type="date" id="<?= e($idp) ?>-due" name="due" value="<?= e((string) $v['due']) ?>"<?= $inv('due') ?>><?= $fe('due') ?></div>
    <div class="f"><label for="<?= e($idp) ?>-prio"><?= e(__('Priorität')) ?></label>
      <select id="<?= e($idp) ?>-prio" name="priority">
        <?php foreach (Tasks::PRIORITIES as $p): ?><option value="<?= e($p) ?>"<?= $v['priority'] === $p ? ' selected' : '' ?>><?= e(Tasks::priorityLabel($p)) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <?php if ($form['manager']): ?><p class="f-help"><?= e(__('Zuständige Personen bekommen eine E-Mail (abschaltbar unter Konto → Mitteilungen). Die Aufgabe bleibt in „Was ist zu tun?“, bis jemand sie abhakt.')) ?></p><?php endif; ?>
</div>
