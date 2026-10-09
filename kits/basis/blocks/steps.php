<?php /** Ablauf (nummerierte Schritte) oder Zeitleiste. @var \Core\Block $b  @var array $d */
$items = array_values(array_filter((array) $d['items'], fn($i) => trim((string) ($i['title'] ?? '')) !== ''));
$v = $b->variant() === 'timeline' ? 'timeline' : 'numbers';
?>
<div class="wrap">
  <?= basis_head($b) ?>
  <?php if ($items): ?>
  <ol class="steps steps--<?= $v ?> cols-<?= max(2, min(4, count($items))) ?>" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="step">
      <span class="step__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <div class="step__body">
        <?php if (trim((string) ($it['meta'] ?? '')) !== ''): ?><p class="step__meta"<?= $b->edit("items.$i.meta") ?>><?= emphasis((string) $it['meta']) ?></p><?php endif; ?>
        <h3 class="step__title"><span class="sr-only"><?= e(lt('Schritt {nr}:', ['nr' => $i + 1])) ?> </span><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span></h3>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="step__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(emphasis((string) $it['text']), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
