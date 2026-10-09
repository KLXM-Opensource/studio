<?php
/**
 * Team / Personen: cards (Glaskarten mit Porträt oder Initialen in einer Glasperle) · compact (kompakte Liste).
 * Ohne Foto: Initialen aus dem Namen, Farbe der Perle wechselt reihum durch die drei Feldfarben (reine Gestaltung).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'compact' ? 'compact' : 'cards';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== '' || is_editing()));
$tag = glas_htag($d);
$initials = function (string $name): string {
    $parts = array_values(array_filter(preg_split('~[\s\-]+~u', trim(strip_emphasis($name))) ?: [], fn($p) => $p !== '' && mb_substr($p, 0, 1) !== '('));
    if (!$parts) return '·';
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
};
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($items): ?>
  <ul class="team team--<?= e($v) ?><?= $v === 'cards' ? ' grid ' . e(glas_min($d)) : '' ?>" role="list">
    <?php foreach ($items as $i => $it):
        $name = trim((string) ($it['name'] ?? ''));
        $role = trim((string) ($it['role'] ?? ''));
        $text = trim((string) ($it['text'] ?? ''));
        $email = trim((string) ($it['email'] ?? ''));
        $pic = !empty($it['image']) ? img((int) $it['image'], '160px', ['ratio' => '1:1', 'alt' => '', 'class' => 'person__img']) : '';
    ?>
    <li class="person<?= $v === 'cards' ? ' glass' : '' ?>" data-reveal>
      <span class="person__avatar person__avatar--<?= $i % 3 + 1 ?>" aria-hidden="true"><?= $pic !== '' ? $pic : '<span class="person__initials">' . e($initials($name)) . '</span>' ?></span>
      <div class="person__body">
        <<?= $tag ?> class="person__name"<?= $b->edit("items.$i.name") ?>><?= glas_title($name) ?></<?= $tag ?>>
        <?php if ($role !== '' || is_editing()): ?><p class="person__role"<?= $b->edit("items.$i.role") ?>><?= glas_title($role) ?></p><?php endif; ?>
        <?php if ($text !== '' && $v === 'cards'): ?><p class="person__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(glas_title($text), false) ?></p><?php endif; ?>
        <?php if ($email !== '' && filled($email)): ?><a class="person__mail" href="mailto:<?= e($email) ?>"><?= icon('envelope-simple') ?><span><?= e($email) ?></span></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Personen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
