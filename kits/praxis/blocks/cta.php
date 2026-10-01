<?php /** Handlungsaufruf (B11) – Band (Hintergrund dunkel oder bordeaux über Abschnitts-Optionen) oder Box/Karte. @var \Core\Block $b  @var array $d */
$box = $b->variant() === 'box';
$dark = $b->dark();
$btnLabel = fn(array $btn) => $btn['link'] === 'telefon' && str_starts_with((string) $btn['label'], '[') ? (praxis_has_phone() ? praxis_phone() : lt('Anrufen')) : $btn['label'];
$arrow = fn(array $btn) => ' <span aria-hidden="true">' . (is_external(praxis_link($btn['link'])) ? '↗' : '→') . '</span>';
?>
<?php if ($box):
    // Box: in einer Reihe (neben einem Text) eine Ebene tiefer (h3) – allein im Abschnitt h2
    $tag = $b->inRow() ? 'h3' : 'h2'; ?>
<div class="wrap">
  <div class="cta-box<?= $dark ? ' cta-box--dark' : '' ?>" data-reveal="up">
    <div>
      <?= praxis_heading($b, $tag, 'h3 cta-box__title', 'title_strong', 'title_light', false) ?>
      <?php if ($d['text'] || is_editing()): ?><p class="cta-box__text"<?= $b->edit('text') ?>><?= e($d['text']) ?></p><?php endif; ?>
    </div>
    <?php if ($d['buttons']): ?>
    <div class="btn-row">
      <?php foreach ($d['buttons'] as $i => $btn): ?>
      <a class="btn <?= $i === 0 ? ($dark ? 'btn--white' : 'btn--primary') : 'btn--outline-light' ?>" <?= praxis_link_attrs($btn['link']) ?>><?= e($btnLabel($btn)) ?><?= $i === 0 ? $arrow($btn) : '' ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="wrap cta">
  <?php if ($d['text']): ?>
  <div>
    <?= praxis_heading($b, 'h2', 'h2 h2--cta', 'title_strong', 'title_light', false) ?>
    <p class="cta__text"<?= $b->edit('text') ?>><?= e($d['text']) ?></p>
  </div>
  <?php else: ?>
  <?= praxis_heading($b, 'h2', 'h2 h2--cta', 'title_strong', 'title_light', false) ?>
  <?php endif; ?>
  <?php if ($d['buttons']): ?>
  <div class="btn-row">
    <?php foreach ($d['buttons'] as $i => $btn): ?>
    <a class="btn <?= $i === 0 ? 'btn--light' : 'btn--outline-light' ?>" <?= praxis_link_attrs($btn['link']) ?>><?= e($btnLabel($btn)) ?><?= $i === 0 ? $arrow($btn) : '' ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
