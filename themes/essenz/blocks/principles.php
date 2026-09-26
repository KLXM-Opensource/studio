<?php
/**
 * Grundsätze / Thesen: list (große Nummer, Titel, Erläuterung – Zeilen mit Haarlinien) · grid (Paneele mit Nummer) ·
 * accordion (kompakt aufklappbar, <details> ohne JavaScript). Die Nummern sind eine geordnete Liste (<ol>).
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['list', 'grid', 'accordion'], true) ? $b->variant() : 'list';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = essenz_htag($d);
?>
<div class="wrap">
  <?= essenz_head($b) ?>
  <?php if ($items): ?>
  <ol class="pr pr--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it): $text = trim((string) ($it['text'] ?? '')); ?>
    <?php if ($v === 'accordion'): ?>
    <li class="pr__item"><details class="pr__det" name="<?= e($b->domId()) ?>-pr">
      <summary class="pr__sum"><span class="pr__n" aria-hidden="true"><?= essenz_num($i + 1) ?></span><span class="pr__title"<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span><span class="pr__pm" aria-hidden="true"></span></summary>
      <?php if ($text !== ''): ?><p class="pr__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </details></li>
    <?php else: ?>
    <li class="pr__item<?= $v === 'grid' ? ' card' : '' ?>" data-reveal>
      <span class="pr__n" aria-hidden="true"><?= essenz_num($i + 1) ?></span>
      <<?= $tag ?> class="pr__title"<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></<?= $tag ?>>
      <?php if ($text !== '' || is_editing()): ?><p class="pr__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </li>
    <?php endif; ?>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Grundsätze – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
