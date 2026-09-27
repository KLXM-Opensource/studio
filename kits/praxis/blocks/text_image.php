<?php /** Text + Bild (B01 rechts / B02 links). @var \Core\Block $b  @var array $d */
$left = $b->variant() === 'left';
$ratio = $d['ratio'] ?: '4-3';
// Aufzählung: eine Zeile pro Punkt; Einrückung oder „- “ = Unterpunkt
$list = [];
foreach (preg_split('~\R~', (string) $d['list']) as $line) {
    if (trim($line) === '') continue;
    $sub = (bool) preg_match('~^(\s{2,}|\t|-\s)~', $line);
    $text = trim(preg_replace('~^\s*-\s~', '', $line));
    if ($sub && $list) { $list[count($list) - 1]['sub'][] = $text; } else { $list[] = ['text' => $text, 'sub' => []]; }
}
$listTag = ($d['list_style'] ?? 'dash') === 'number' ? 'ol' : 'ul';
$listClass = ($d['list_style'] ?? 'dash') === 'check' ? ' class="check"' : '';
$ph = lt('Bild · {ratio} · min. {px} px', ['ratio' => str_replace('-', ':', $ratio), 'px' => 1600]);
?>
<div class="wrap media-split<?= $left ? ' media-split--left' : '' ?>">
  <div class="media-split__text" data-reveal="up">
    <?php if ($d['title_style'] === 'sentence'): ?>
      <?php if ($d['eyebrow']): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h2 h2--s"<?= $b->edit('title_strong') ?>><?= e($d['title_strong']) ?></h2>
    <?php else: ?>
      <?php if ($d['eyebrow']): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <?= praxis_heading($b, 'h2', 'h2 h2--m') ?>
    <?php endif; ?>
    <?php if ($d['text'] || is_editing()): ?><div class="prose muted media-split__body"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div><?php endif; ?>
    <?php if ($list): ?>
    <div class="prose media-split__list">
      <<?= $listTag . $listClass ?>>
        <?php foreach ($list as $li): ?>
        <li><?= e($li['text']) ?><?php if ($li['sub']): ?><ul><?php foreach ($li['sub'] as $sub): ?><li><?= e($sub) ?></li><?php endforeach; ?></ul><?php endif; ?></li>
        <?php endforeach; ?>
      </<?= $listTag ?>>
    </div>
    <?php endif; ?>
    <?php if ($d['button_label']): ?><a class="btn btn--primary media-split__btn" <?= praxis_link_attrs($d['button_link']) ?>><?= e($d['button_label']) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
  </div>
  <div class="media-split__media ph ratio-<?= e($ratio) ?>" data-reveal="up" data-delay="120">
    <?= praxis_image($d['image'], '(min-width: 1080px) 600px, 100vw', $ph, '', ['ratio' => $ratio]) ?>
  </div>
</div>
