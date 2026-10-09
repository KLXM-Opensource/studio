<?php
/**
 * Team / Menschen: grid (Karten mit Foto oder Monogramm in einer Kieselform) · list (Zeilen, Bild links).
 * Ohne Foto: Initialen – keine Platzhalter-Fotos. E-Mail optional als Link.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'list' ? 'list' : 'grid';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== '' || is_editing()));
$tag = nature_htag($d);
?>
<div class="wrap">
  <?= nature_head($b) ?>
  <?php if ($items): ?>
  <ul class="team team--<?= e($v) ?> <?= e(nature_min($d)) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $name = trim((string) ($it['name'] ?? ''));
        $role = trim((string) ($it['role'] ?? ''));
        $text = trim((string) ($it['text'] ?? ''));
        $mail = trim((string) ($it['email'] ?? ''));
        $pic = !empty($it['image']) ? img((int) $it['image'], '(min-width: 800px) 240px, 40vw', ['ratio' => '1:1', 'alt' => '', 'class' => 'team__img']) : '';
    ?>
    <li class="team__item<?= $v === 'grid' ? ' card' : '' ?>" data-reveal>
      <div class="team__pic team__pic--<?= $i % 3 ?>" aria-hidden="true"><?= $pic !== '' ? $pic : '<span class="team__mono">' . e(nature_initials(strip_emphasis($name))) . '</span>' ?></div>
      <div class="team__body">
        <<?= $tag ?> class="team__name"<?= $b->edit("items.$i.name") ?>><?= nature_title($name) ?></<?= $tag ?>>
        <?php if ($role !== '' || is_editing()): ?><p class="team__role"<?= $b->edit("items.$i.role") ?>><?= nature_title($role) ?></p><?php endif; ?>
        <?php if ($text !== '' || is_editing()): ?><p class="team__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(nature_title($text), false) ?></p><?php endif; ?>
        <?php if ($mail !== ''): ?><a class="team__mail" href="mailto:<?= e($mail) ?>"><?= icon('envelope-simple') ?><span><?= e($mail) ?></span></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Personen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
