<?php
/** Hero mit rotierenden Themen + Flip-Kontaktkarte. @var \Core\Block $b  @var array $d */
$slides = praxis_slides(!empty($d['use_override']) && !empty($d['slides']) ? $d['slides'] : null);
$labels = ['greeting' => lt('Begrüßung'), 'topic' => lt('Aktuelles Thema'), 'main' => lt('Hauptthema')];
?>
<section id="<?= e($b->tune('anchor') ?: 'top') ?>" class="hero<?= $b->tune('visible') ? '' : ' is-hidden-block' ?>" aria-labelledby="hero-title">
  <div class="hero__stage">
    <?php
    // Hintergrund: Variante des Blocks; ein Hintergrundbild aus den Abschnitts-Optionen gilt wie „Hintergrundbild“
    $variant = (string) ($d['variant'] ?? 'silk');
    $bgImg = (int) ($d['bg_image'] ?? 0) ?: (int) $b->tune('bgImage');
    if ($variant === 'silk' && $b->tune('bgImage')) $variant = 'image';
    $overlay = in_array($d['bg_overlay'] ?? '', ['strong', 'medium', 'light'], true) ? $d['bg_overlay'] : 'strong';
    $focusBg = preview_focus('hero_slides');
    $activeAt = 0;
    foreach ($slides as $i => $s) {
        if ($focusBg !== null ? ($s['_idx'] ?? -1) === $focusBg : !empty($s['is_main'])) { $activeAt = $i; break; }
    }
    ?>
    <?php if ($variant === 'image' && ($bgImg || array_filter(array_column($slides, 'bild')))): ?>
    <div class="hero__bg hero__bg--<?= e($overlay) ?>" aria-hidden="true">
      <?php foreach ($slides as $i => $s): $img = (int) ($s['bild'] ?? 0) ?: $bgImg; if (!$img) continue; ?>
      <div class="hero__bgimg<?= $i === $activeAt ? ' is-active' : '' ?>" data-bg="<?= $i ?>"><?= img($img, '100vw', ['eager' => $i === $activeAt]) ?></div>
      <?php endforeach; ?>
    </div>
    <?php elseif ($variant === 'video' && !empty($d['bg_video']) && ($vid = media((int) $d['bg_video'])) && str_starts_with((string) $vid['mime'], 'video/')): ?>
    <div class="hero__bg hero__bg--<?= e($overlay) ?>" aria-hidden="true">
      <?php if ($bgImg): ?><div class="hero__bgimg is-active hero__poster"><?= img($bgImg, '100vw', ['eager' => true]) ?></div><?php endif; ?>
      <video class="hero__video" data-hero-video muted loop playsinline preload="metadata"<?= ($pm = $bgImg ? media($bgImg) : null) ? ' poster="' . e(\Core\Media::url($pm, 1600)) . '"' : '' ?>>
        <source src="<?= e(\Core\Media::url($vid)) ?>" type="<?= e((string) $vid['mime']) ?>">
      </video>
    </div>
    <?php elseif ($variant === 'color'): ?>
    <div class="hero__bg hero__bg--color" aria-hidden="true"></div>
    <?php else: ?>
    <div class="silk" aria-hidden="true"><span class="silk__a"></span><span class="silk__b"></span><span class="silk__c"></span><span class="silk__sheen"></span></div>
    <?php endif; ?>
    <?php if (in_array($variant, ['silk', 'color'], true) && array_filter(array_column($slides, 'bild'))): ?>
    <?php // Eigenes Bild je Thema auch bei Verlauf/Farbfläche: liegt darüber, nur solange ein Thema mit Bild aktiv ist ?>
    <div class="hero__bg hero__bg--slides hero__bg--<?= e($overlay) ?>" aria-hidden="true">
      <?php foreach ($slides as $i => $s): if (!($img = (int) ($s['bild'] ?? 0))) continue; ?>
      <div class="hero__bgimg<?= $i === $activeAt ? ' is-active' : '' ?>" data-bg="<?= $i ?>"><?= img($img, '100vw', ['eager' => $i === $activeAt]) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="hero__grid<?= $d['show_card'] ? '' : ' hero__grid--solo' ?>">
      <div class="hero__topics" data-hero data-reveal="up">
        <div class="hero__slides">
          <?php $focus = preview_focus('hero_slides'); $startAt = $focus !== null && in_array($focus, array_column($slides, '_idx'), true) ? $focus : null; ?>
          <?php foreach ($slides as $i => $s): $main = !empty($s['is_main']); $greet = ($s['typ'] ?? '') === 'greeting';
            $active = $startAt !== null ? ($s['_idx'] ?? -1) === $startAt : $main; ?>
          <div class="slide<?= $active ? ' is-active' : '' ?>" data-slide="<?= $i ?>"<?= $main ? ' data-main' : '' ?><?= $active || $main ? '' : ' aria-hidden="true"' /* H1 (Hauptthema) nie verstecken */ ?>
               data-label="<?= e($labels[$s['typ'] ?? 'topic'] ?? lt('Thema')) ?>">
            <?php if (!empty($s['eyebrow'])): ?><p class="eyebrow eyebrow--line"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <?php $title = $greet && trim((string) ($s['titel'] ?? '')) === '' ? '<span data-greeting="' . e(lt('Guten Morgen') . '|' . lt('Guten Tag') . '|' . lt('Guten Abend')) . '">' . e(praxis_greeting()) . '</span>' : e($s['titel'] ?? ''); ?>
            <?php if ($main): ?>
            <h1 id="hero-title" class="hero__title"><?= $title ?><span class="dot">.</span></h1>
            <?php else: ?>
            <p class="hero__title"><?= $title ?><span class="dot">.</span></p>
            <?php endif; ?>
            <?php if (!empty($s['text'])): ?><p class="hero__sub"><?= e($s['text']) ?></p><?php endif; ?>
            <?php if (!empty($s['button_label']) || !empty($s['button2_label'])): ?>
            <div class="btn-row">
              <?php if (!empty($s['button_label'])): ?><a class="btn btn--light" <?= praxis_link_attrs($s['button_link'] ?? '') ?>><?= e($s['button_label']) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
              <?php if (!empty($s['button2_label'])): ?><a class="btn btn--outline-light" <?= praxis_link_attrs($s['button2_link'] ?? '') ?>><?= e($s['button2_label']) ?></a><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1): ?>
        <div class="hero__controls">
          <div class="dots" role="group" aria-label="<?= e(lt('Themen')) ?>">
            <?php foreach ($slides as $i => $s): ?>
            <button type="button" class="dots__btn" data-go="<?= $i ?>" aria-label="<?= e(lt('Thema {n}: {title}', ['n' => $i + 1, 'title' => ($s['typ'] ?? '') === 'greeting' ? lt('Begrüßung') : ($s['titel'] ?: $s['eyebrow'] ?? '')])) ?>"<?= ($startAt !== null ? ($s['_idx'] ?? -1) === $startAt : !empty($s['is_main'])) ? ' aria-current="true"' : '' ?>><span><span></span></span></button>
            <?php endforeach; ?>
          </div>
          <button type="button" class="hero__pause" data-pause aria-label="<?= e(lt('Themenwechsel anhalten')) ?>">❚❚ <?= e(lt('Pause')) ?></button>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($d['show_card']): ?>
      <div class="hero__card" data-reveal="up" data-delay="120">
        <?= app()->theme->partial('contact-card') ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
