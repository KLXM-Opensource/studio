<?php
/**
 * Seitenleiste „Eintrag bearbeiten“ auf der Website (geladen von resources/js/_entry_edit.js).
 * Gleiche Feld-Widgets wie das Formular der Verwaltung (Core\Fields::renderForm).
 * @var array $t  @var ?array $e  @var array $values  @var array $errors  @var ?string $reason  @var bool $foreign
 */
use Core\Data\Entries;
use Core\Data\EntryEdit;
use Core\Fields;
use Core\Lang;

$isNew = $e === null;
$workflow = (bool) $t['settings']['workflow'];
$publish = can('data.publish', $t['handle']) && $workflow;
$status = $e['status'] ?? 'draft';
$readonly = $reason !== null;
?>
<?php if ($foreign && $e): $origin = EntryEdit::originEdit($t, $e); $info = \Core\Data\Shared::siteInfo((string) $e['origin_site'], $t['shared']['key']); ?>
<div class="cms-epanel__body">
  <p class="adm-flash adm-flash--info"><?= e(__('Dieser Eintrag stammt von „{site}“ und ist hier nur lesbar. Ändern kann ihn nur diese Website.', ['site' => $info['name']])) ?></p>
  <dl class="cms-epanel__ro">
    <?php foreach ($t['fields'] as $f): $h = Entries::html($t, $e, $f['name'], ['link' => false, 'plain' => true]); if ($h === '') continue; ?>
    <dt><?= e($f['label']) ?></dt><dd><?= $h ?></dd>
    <?php endforeach; ?>
  </dl>
</div>
<div class="cms-epanel__foot">
  <?php if ($origin && $origin['sso']): ?>
  <form method="post" action="<?= e($origin['url']) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($origin['site']) ?>"><input type="hidden" name="path" value="<?= e($origin['path']) ?>">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Auf Ursprungs-Website bearbeiten')) ?> ↗</button></form>
  <?php elseif ($origin): ?><a class="adm-btn adm-btn--primary" href="<?= e($origin['url']) ?>" target="_blank" rel="noopener"><?= e(__('Auf Ursprungs-Website bearbeiten')) ?> ↗</a><?php endif; ?>
  <button type="button" class="adm-btn adm-btn--ghost" data-panel-close><?= e(__('Schließen')) ?></button>
</div>
<?php return; endif; ?>
<form class="cms-epanel__body" data-entry-form novalidate>
  <?php if ($readonly): ?><p class="adm-flash adm-flash--info"><?= e($reason) ?></p><?php endif; ?>
  <?php if ($errors): ?><p class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.')) ?><?= isset($errors['_']) ? ' ' . e($errors['_']) : '' ?></p><?php endif; ?>
  <?php if (!$workflow || !can('data.publish', $t['handle'])): ?>
  <p class="cms-epanel__hint"><?= !$workflow ? e(__('Einträge dieser Tabelle sind nach dem Speichern sofort online.'))
      : '<span class="dt-status dt-status--draft">' . e(__('Entwurf')) . '</span> ' . e(__('Ihre Rolle speichert Einträge als Entwurf – veröffentlicht wird von der Redaktion.')) ?></p>
  <?php elseif (!$isNew && $status === 'published'): ?>
  <p class="cms-epanel__hint"><span class="dt-status dt-status--published"><?= e(__('Online')) ?></span> <?= e(__('Änderungen sind nach dem Speichern sofort sichtbar.')) ?></p>
  <?php endif; ?>
  <fieldset class="adm-fields cms-epanel__fields"<?= $readonly ? ' disabled' : '' ?>><legend class="adm-sr"><?= e(__('Felder')) ?></legend><?= Fields::renderForm(Entries::formSchema($t), $values, $errors, 'f') ?></fieldset>
  <details class="cms-epanel__more"<?= isset($errors['slug']) ? ' open' : '' ?>>
    <summary><?= e(__('Adresse & Sprache')) ?></summary>
    <div class="f"><label for="cms-ep-slug"><?= e(__('Adresse')) ?></label>
      <div class="adm-prefix"><span>/<?= e($t['settings']['route'] ?: '…') ?>/</span><input id="cms-ep-slug" name="slug" value="<?= e((string) ($values['slug'] ?? '')) ?>" placeholder="<?= e(__('aus dem Titel')) ?>"<?= $readonly ? ' disabled' : '' ?>></div>
      <p class="f-help"><?= e(__('Wird die Adresse geändert, öffnet sich die Seite nach dem Speichern unter der neuen Adresse.')) ?></p></div>
    <?php if (Lang::multi()): $elang = $isNew ? Lang::current() : Lang::norm($e['lang'] ?? null); ?>
    <?php if ($isNew): ?><input type="hidden" name="lang" value="<?= e($elang) ?>"><?php endif; ?>
    <p class="cms-epanel__lang"><strong><?= e(__('Sprache')) ?>: <?= e(Lang::all()[$elang] ?? $elang) ?></strong>
      <?php if (!$isNew): $tr = Entries::translations($t, $e); foreach (Lang::all() as $code => $label): if ($code === $elang) continue; ?>
      <?php if (isset($tr[$code]) && ($u = Entries::url($t, $tr[$code]))): ?> · <a href="<?= e($u) ?>"><?= e($label) ?></a>
      <?php else: ?> · <a href="<?= e(EntryEdit::adminUrl($t, $e)) ?>"><?= e(__('{lang} anlegen (Verwaltung)', ['lang' => $label])) ?></a><?php endif; ?>
      <?php endforeach; endif; ?></p>
    <?php endif; ?>
  </details>
</form>
<div class="cms-epanel__foot">
  <?php if (!$readonly): ?>
    <?php if ($isNew && $publish): ?>
    <button type="button" class="adm-btn adm-btn--primary" data-panel-save="published"><?= e(__('Veröffentlichen')) ?></button>
    <button type="button" class="adm-btn" data-panel-save="draft"><?= e(__('Als Entwurf speichern')) ?></button>
    <?php elseif ($publish && $status === 'draft'): ?>
    <button type="button" class="adm-btn adm-btn--primary" data-panel-save="published"><?= e(__('Veröffentlichen')) ?></button>
    <button type="button" class="adm-btn" data-panel-save=""><?= e(__('Entwurf speichern')) ?></button>
    <?php elseif ($publish): ?>
    <button type="button" class="adm-btn adm-btn--primary" data-panel-save=""><?= e(__('Speichern')) ?></button>
    <button type="button" class="adm-btn" data-panel-save="draft" title="<?= e(__('Speichert und nimmt den Eintrag offline')) ?>"><?= e(__('Als Entwurf')) ?></button>
    <?php else: ?>
    <button type="button" class="adm-btn adm-btn--primary" data-panel-save=""><?= e($workflow ? __('Entwurf speichern') : __('Speichern')) ?></button>
    <?php endif; ?>
    <button type="button" class="adm-btn adm-btn--ghost" data-panel-discard><?= e(__('Abbrechen')) ?></button>
  <?php else: ?>
    <button type="button" class="adm-btn adm-btn--ghost" data-panel-close><?= e(__('Schließen')) ?></button>
  <?php endif; ?>
  <a class="cms-epanel__admin" href="<?= e(EntryEdit::adminUrl($t, $e)) ?>"><?= e(__('In der Verwaltung öffnen')) ?> ↗</a>
</div>
