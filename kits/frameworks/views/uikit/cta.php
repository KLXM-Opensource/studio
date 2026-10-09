<?php
/**
 * Handlungsaufruf (UIkit) – Standard-Hintergrund „Akzentfarbe“ (uk-section-primary uk-light). Dialog: uk-modal, geöffnet
 * mit uk-toggle (UIkit setzt role="dialog", aria-modal, Fokusfalle, Esc). Der Dialog wird beim Öffnen an <body> gehängt
 * (UIkit „container“) – er liegt damit außerhalb von uk-light und braucht eigene Farben (css/uikit.css).
 * Im Bearbeiten-Modus steht der Inhalt des Dialogs als Kasten unter den Buttons.
 * @var \Core\Block $b  @var array $d
 */
$dlg = filled($d['modal_label'] ?? '') && filled($d['modal_title'] ?? '');
$id = $b->domId() . '-dlg';
$btn = frameworks_buttons($b, ['uk-button uk-button-default uk-button-large fw-btn-invert', 'uk-button uk-button-text uk-button-large']);
?>
<div class="uk-container uk-text-center">
  <h2 id="<?= e($b->titleId()) ?>" class="uk-h1 uk-width-2xlarge uk-margin-auto"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2>
  <?php if (filled($d['text']) || is_editing()): ?><p class="uk-text-large uk-width-xlarge uk-margin-auto"<?= $b->edit('text') ?>><?= nl2br(emphasis((string) $d['text']), false) ?></p><?php endif; ?>
  <div class="uk-margin-medium-top fw-btns uk-flex-center">
    <?= $btn ?>
    <?php if ($dlg && !is_editing()): ?><button class="uk-button uk-button-default uk-button-large" type="button" uk-toggle="target: #<?= e($id) ?>"><?= e($d['modal_label']) ?></button><?php endif; ?>
  </div>
  <?php if ($dlg && is_editing()): ?>
  <div class="uk-card uk-card-body uk-margin-large-top uk-width-xlarge uk-margin-auto uk-text-left fw-dlg-preview">
    <p class="uk-text-small uk-text-bold uk-text-uppercase"><?= e(lt('Dialog')) ?>: <span<?= $b->edit('modal_label') ?>><?= e($d['modal_label']) ?></span></p>
    <h3 class="uk-h3"<?= $b->edit('modal_title') ?>><?= emphasis((string) $d['modal_title']) ?></h3>
    <div class="fw-prose"<?= $b->edit('modal_text', 'rich') ?>><?= rich((string) ($d['modal_text'] ?? '')) ?></div>
  </div>
  <?php elseif ($dlg): ?>
  <div id="<?= e($id) ?>" uk-modal aria-labelledby="<?= e($id) ?>-t">
    <div class="uk-modal-dialog uk-modal-body uk-text-left">
      <button class="uk-modal-close-default" type="button" uk-close aria-label="<?= e(lt('Schließen')) ?>"></button>
      <h2 id="<?= e($id) ?>-t" class="uk-modal-title"><?= emphasis((string) $d['modal_title']) ?></h2>
      <div class="fw-prose"><?= rich((string) ($d['modal_text'] ?? '')) ?></div>
      <p class="uk-text-right uk-margin-medium-top"><button class="uk-button uk-button-primary uk-modal-close" type="button"><?= e(lt('Schließen')) ?></button></p>
    </div>
  </div>
  <?php endif; ?>
</div>
