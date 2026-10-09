<?php
/**
 * Handlungsaufruf (Tailwind), Standard-Hintergrund „Akzentfarbe“ (bg-band). Optionaler Dialog: natives <dialog>,
 * geöffnet über Invoker-Befehle (commandfor/command="show-modal", ohne Skript in aktuellen Browsern) mit Rückfall in
 * js/tailwind.js. Esc, Fokusfalle und Rückgabe des Fokus übernimmt der Browser. Im Bearbeiten-Modus steht der Inhalt des
 * Dialogs als Kasten unter den Buttons (bearbeitbar).
 * @var \Core\Block $b  @var array $d
 */
$dlg = filled($d['modal_label'] ?? '') && filled($d['modal_title'] ?? '');
$id = $b->domId() . '-dlg';
?>
<div class="wrap text-center">
  <h2 id="<?= e($b->titleId()) ?>" class="mx-auto max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2>
  <?php if (filled($d['text']) || is_editing()): ?><p class="mx-auto mt-4 max-w-2xl text-lg"<?= $b->edit('text') ?>><?= nl2br(emphasis((string) $d['text']), false) ?></p><?php endif; ?>
  <div class="mt-8 flex flex-wrap justify-center gap-3">
    <?= frameworks_buttons($b, ['btn btn-primary', 'btn btn-secondary']) ?>
    <?php if ($dlg && !is_editing()): ?><button type="button" class="btn btn-secondary" commandfor="<?= e($id) ?>" command="show-modal" data-fw-dialog="<?= e($id) ?>"><?= e($d['modal_label']) ?></button><?php endif; ?>
  </div>
  <?php if ($dlg && is_editing()): ?>
  <div class="mx-auto mt-10 max-w-2xl rounded-kit border-2 border-dashed border-current p-6 text-left">
    <p class="text-sm font-semibold uppercase"><?= e(lt('Dialog')) ?>: <span<?= $b->edit('modal_label') ?>><?= e($d['modal_label']) ?></span></p>
    <h3 class="mt-2 text-xl font-bold"<?= $b->edit('modal_title') ?>><?= emphasis((string) $d['modal_title']) ?></h3>
    <div class="prose mt-3 max-w-none"<?= $b->edit('modal_text', 'rich') ?>><?= rich((string) ($d['modal_text'] ?? '')) ?></div>
  </div>
  <?php elseif ($dlg): ?>
  <dialog id="<?= e($id) ?>" closedby="any" aria-labelledby="<?= e($id) ?>-t" class="m-auto w-[min(36rem,calc(100%-2rem))] rounded-kit bg-white p-0 text-left text-slate-700 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-slate-300">
    <div class="p-6 sm:p-8">
      <h2 id="<?= e($id) ?>-t" class="text-2xl font-bold text-slate-900 dark:text-white"><?= emphasis((string) $d['modal_title']) ?></h2>
      <div class="prose prose-slate mt-4 max-w-none dark:prose-invert prose-a:text-accent"><?= rich((string) ($d['modal_text'] ?? '')) ?></div>
      <form method="dialog" class="mt-8 text-right"><button class="btn btn-primary"><?= e(lt('Schließen')) ?></button></form>
    </div>
  </dialog>
  <?php endif; ?>
</div>
