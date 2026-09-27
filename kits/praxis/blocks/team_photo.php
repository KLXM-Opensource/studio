<?php /** Teamfoto 2:1 + Text + optionale Ausbildungs-Box. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="teamphoto ph" data-reveal="scale">
    <?= praxis_image($d['image'], '(min-width: 1280px) 1200px, 100vw', lt('Gemeinsames Teamfoto folgt · 2:1, min. 2400 px breit'), '', ['ratio' => '2:1']) ?>
  </div>
  <div class="split teamphoto__text">
    <?= praxis_heading($b) ?>
    <div class="prose prose--stack" data-reveal="up"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
  </div>
  <?php if ($d['show_jobs_box']): ?>
  <aside class="jobsbox" data-reveal="up" aria-labelledby="<?= e($b->domId()) ?>-jobs">
    <div>
      <h3 id="<?= e($b->domId()) ?>-jobs" class="h3"><span<?= $b->edit('jobs_title_strong') ?>><?= e($d['jobs_title_strong']) ?></span><span class="dot">.</span> <span class="light--inline"<?= $b->edit('jobs_title_light') ?>><?= e($d['jobs_title_light']) ?></span></h3>
      <?php if ($d['jobs_text']): ?><p<?= $b->edit('jobs_text') ?>><?= e($d['jobs_text']) ?></p><?php endif; ?>
    </div>
    <?php if ($d['jobs_button_label']): ?><div><a class="btn btn--white" <?= praxis_link_attrs($d['jobs_link']) ?>><?= e($d['jobs_button_label']) ?> <span aria-hidden="true">→</span></a></div><?php endif; ?>
  </aside>
  <?php endif; ?>
</div>
