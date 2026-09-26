<?php
/**
 * Team: grid (Porträtkarten 4:5 – ohne Foto die Initialen auf einer Farbfläche) · list (kompakte Zeilen mit rundem Porträt).
 * Profil-Link: ohne Beschriftung ist die ganze Karte klickbar; E-Mail als eigener Link.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'list' ? 'list' : 'grid';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== '' || is_editing()));
$tag = modern_htag($d);
$tones = ['pop', 'tint', 'dark'];
?>
<div class="wrap">
  <?= modern_head($b) ?>
  <?php if ($items): ?>
  <ul class="<?= $v === 'grid' ? 'grid ' . e(modern_min($d)) . ' ' : '' ?>team team--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $name = trim((string) ($it['name'] ?? ''));
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $cover = $link !== '' && $label === '' && !is_editing();
        $email = trim((string) ($it['email'] ?? ''));
        $pic = !empty($it['image']) ? img((int) $it['image'], $v === 'grid' ? '(min-width: 1080px) 300px, 50vw' : '96px', ['ratio' => $v === 'grid' ? '4:5' : '1:1', 'alt' => '']) : '';
    ?>
    <li class="member member--<?= e($v) ?><?= $cover ? ' member--link' : '' ?><?= $v === 'grid' ? ' card' : '' ?>" data-reveal>
      <div class="member__pic<?= $pic === '' ? ' member__pic--initials member__pic--' . $tones[$i % 3] : '' ?>"<?= $pic === '' ? ' aria-hidden="true"' : '' ?>>
        <?= $pic !== '' ? $pic : '<span>' . e(modern_initials($name)) . '</span>' ?>
      </div>
      <div class="member__body">
        <<?= $tag ?> class="member__name"><?php if ($cover): ?><a class="cover-link" <?= modern_link_attrs($link) ?>><?= e($name) ?></a><?php else: ?><span<?= $b->edit("items.$i.name") ?>><?= e($name) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (trim((string) ($it['role'] ?? '')) !== ''): ?><p class="member__role"<?= $b->edit("items.$i.role") ?>><?= e($it['role']) ?></p><?php endif; ?>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="member__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
        <?php if ($email !== '' || ($link !== '' && $label !== '')): ?>
        <p class="member__links">
          <?php if ($email !== ''): ?><a class="member__mail" href="mailto:<?= e($email) ?>"><?= icon('envelope-simple') ?><span class="sr-only"><?= e(lt('E-Mail an {name}', ['name' => $name])) ?>: </span><span class="member__addr"><?= e($email) ?></span></a><?php endif; ?>
          <?php if ($link !== '' && $label !== ''): ?><a class="more" <?= modern_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span><?= icon('arrow-right', ['class' => 'more__ico']) ?><span class="sr-only">: <?= e($name) ?></span></a><?php endif; ?>
        </p>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Personen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
