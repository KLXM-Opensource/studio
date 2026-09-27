<?php /** Handlungsaufruf (B11) – Hintergrund dunkel oder bordeaux über Abschnitts-Optionen. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap cta">
  <?= praxis_heading($b, 'h2', 'h2 h2--cta', 'title_strong', 'title_light', false) ?>
  <?php if ($d['buttons']): ?>
  <div class="btn-row">
    <?php foreach ($d['buttons'] as $i => $btn):
        $label = $btn['link'] === 'telefon' && str_starts_with((string) $btn['label'], '[') ? (praxis_has_phone() ? praxis_phone() : lt('Anrufen')) : $btn['label']; ?>
    <a class="btn <?= $i === 0 ? 'btn--light' : 'btn--outline-light' ?>" <?= praxis_link_attrs($btn['link']) ?>><?= e($label) ?><?= $i === 0 ? ' <span aria-hidden="true">' . (is_external(praxis_link($btn['link'])) ? '↗' : '→') . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
