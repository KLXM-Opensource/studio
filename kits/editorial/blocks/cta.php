<?php
/**
 * Handlungsaufruf: Band, Kasten oder Newsletter (öffentliches Formular einer Datentabelle direkt im Band – ohne Dienste Dritter,
 * Spamschutz ohne Cookies; Ausgabe über Core\Data\DataForms wie der Kern-Block „Formular“).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\DataForms;
use Core\Data\Tables;

$v = in_array($b->variant(), ['band', 'box', 'newsletter'], true) ? $b->variant() : 'band';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
$form = '';
if ($v === 'newsletter') {
    $t = Tables::find((string) ($d['form_table'] ?? ''));
    if ($t && DataForms::enabled($t)) {
        $state = DataForms::$state[$t['handle']] ?? [];
        $form = DataForms::render($t, [
            'uid' => $b->domId() . '-f', 'submit' => $d['submit_label'] ?? '', 'success' => $d['success_text'] ?? '',
            'values' => $state['values'] ?? [], 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
            'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null,
        ]);
    } elseif (is_editing()) {
        $form = '<p class="empty-hint">Bitte in der Seitenleiste eine Tabelle mit öffentlichem Formular wählen.</p>';
    }
}
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?>">
    <div class="cta__text">
      <?php if ($eyebrow !== ''): ?><p class="kicker"<?= $b->edit('eyebrow') ?>><?= e($eyebrow) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="cta__title"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
      <?php if ($text !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </div>
    <?php if ($v === 'newsletter'): ?>
    <div class="cta__form dff-wrap"><?= $form ?></div>
    <?php else: ?>
    <?= editorial_buttons($b, 'cta__btns') ?>
    <?php endif; ?>
  </div>
</div>
