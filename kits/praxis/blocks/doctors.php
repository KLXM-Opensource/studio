<?php /** Ärztinnen und Ärzte – Karten-Grid. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="head-split">
    <?= praxis_heading($b) ?>
    <?php if ($d['intro']): ?><p class="muted" data-reveal="up" data-delay="80"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </div>
  <div class="doctors">
    <?php foreach ($d['items'] as $i => $p):
      // Ohne Namen (z. B. nur Redaktionsnotiz „[# … #]“) keine leere Karte für Besucher – im Bearbeiten-Modus sichtbar
      if (!is_editing() && trim((string) ($p['name'] ?? '')) === '') continue; ?>
    <article class="doctor" data-reveal="up">
      <div class="doctor__photo ph">
        <?= praxis_image($p['foto'] ?? null, '(min-width: 1280px) 400px, (min-width: 700px) 50vw, 100vw', lt('Porträt folgt · 1:1, min. 1200 × 1200 px'), '', ['ratio' => '1:1']) ?>
      </div>
      <div class="doctor__body">
        <p class="doctor__fach"<?= $b->edit("items.$i.fach") ?>><?= e($p['fach']) ?></p>
        <h3 class="doctor__name"><?= e(trim(($p['titel'] ?? '') . ' ' . $p['name'])) ?></h3>
        <?php if (!empty($p['zusatz'])): ?><p class="doctor__zusatz"<?= $b->edit("items.$i.zusatz") ?>><?= e($p['zusatz']) ?></p><?php endif; ?>
        <?php if (!empty($p['text'])): ?><p class="doctor__text"<?= $b->edit("items.$i.text") ?>><?= e($p['text']) ?></p><?php endif; ?>
        <div class="doctor__foot">
          <span class="doctor__hours"><?= !empty($p['sprechzeiten']) ? e(lt('Sprechzeiten: {hours}', ['hours' => $p['sprechzeiten']])) : '' ?></span>
          <?php if ($d['link_label']): ?><a <?= praxis_link_attrs($d['link']) ?>><?= e($d['link_label']) ?> →</a><?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</div>
