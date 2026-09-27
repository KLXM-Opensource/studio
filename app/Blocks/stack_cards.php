<?php
/**
 * Stapelkarten (Kern-Block, vom Theme überschreibbar: kits/{name}/blocks/stack_cards.php).
 * Reines CSS: position: sticky mit wachsendem Abstand oben, leichte Verkleinerung per Scroll-Timeline (wo unterstützt).
 * Kleine Bildschirme und „Bewegung reduzieren“: einfache Liste.
 * @var \Core\Block $b  @var array $d
 */
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$ratio = array_key_exists((string) ($d['ratio'] ?? ''), MediaBlocks::RATIOS) ? (string) $d['ratio'] : '4:3';
$side = in_array($d['image_side'] ?? '', ['start', 'end', 'alternate'], true) ? $d['image_side'] : 'end';
$cards = array_values(array_filter((array) ($d['cards'] ?? []), fn($c) => is_array($c) && (trim((string) ($c['title'] ?? '')) !== '' || is_editing())));
$hTag = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
?>
<div class="<?= e($wrap) ?>">
  <?= MediaBlocks::head($b) ?>
  <?php if ($cards): ?>
  <ol class="cms-stack n-<?= count($cards) ?>" role="list">
    <?php foreach ($cards as $i => $c):
        $pic = !empty($c['image']) ? img((int) $c['image'], '(min-width: 860px) 560px, 100vw', ['ratio' => $ratio]) : '';
        $start = $side === 'start' || ($side === 'alternate' && $i % 2 === 1);
        $label = trim((string) ($c['link_label'] ?? ''));
        $link = trim((string) ($c['link'] ?? ''));
    ?>
    <li class="cms-stack__card">
      <article class="cms-stack__inner<?= $pic !== '' ? ' has-img' : '' ?><?= $start ? ' img-start' : '' ?>">
        <div class="cms-stack__body">
          <span class="cms-stack__num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
          <?php if (!empty($c['eyebrow'])): ?><p class="cms-stack__eyebrow"<?= $b->edit("cards.$i.eyebrow") ?>><?= e($c['eyebrow']) ?></p><?php endif; ?>
          <<?= $hTag ?> class="cms-stack__title"<?= $b->edit("cards.$i.title") ?>><?= e((string) ($c['title'] ?? '')) ?></<?= $hTag ?>>
          <?php if (!empty($c['text'])): ?><p class="cms-stack__text"<?= $b->edit("cards.$i.text") ?>><?= nl2br(e($c['text']), false) ?></p><?php endif; ?>
          <?php if ($label !== '' && $link !== ''): ?><p class="cms-stack__more"><a class="cms-stack__link" href="<?= e(link_href($link)) ?>"<?= $b->edit("cards.$i.link_label") ?>><?= e($label) ?></a></p><?php endif; ?>
        </div>
        <?php if ($pic !== ''): ?><div class="cms-stack__media r-<?= e(str_replace(':', '-', $ratio)) ?>"><?= $pic ?></div><?php endif; ?>
      </article>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?>
  <p class="cms-empty-hint">Noch keine Karten – in der Seitenleiste Karten anlegen.</p>
  <?php endif; ?>
</div>
