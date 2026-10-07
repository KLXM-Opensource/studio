<?php
/**
 * Begrüßungsfenster „Willkommen“ mit den nächsten Schritten (Core\Welcome). Ohne JavaScript ein offenes <dialog>,
 * js/welcome.js macht daraus ein modales Fenster (Fokus, Escape, Hintergrund). Animation nur ohne „Bewegung reduzieren“.
 * @var array $user  @var bool $auto (automatisch gezeigt)
 */
$steps = \Core\Welcome::steps();
$done = count(array_filter($steps, fn($s) => $s['ok']));
$total = count($steps);
$name = trim((string) ($user['name'] ?? '')) ?: (string) ($user['email'] ?? '');
$first = trim(strtok($name, ' ') ?: $name);
$pct = $total ? $done / $total : 0;
$r = 26; $circ = 2 * M_PI * $r;
$siteUrl = url(\Core\Lang::prefix(\Core\Lang::default()) . '/');
?>
<link rel="stylesheet" href="<?= e(asset('css/welcome-modal.css')) ?>">
<dialog class="wlc" id="wlc" open aria-labelledby="wlc-h" data-welcome<?= $auto ? ' data-welcome-auto' : '' ?>>
  <div class="wlc__glow" aria-hidden="true"><span></span><span></span><span></span></div>
  <form method="dialog" class="wlc__x"><button type="submit" class="wlc__close" aria-label="<?= e(__('Schließen')) ?>"><?= icon('x') ?></button></form>
  <header class="wlc__head">
    <span class="wlc__mark" aria-hidden="true"><?= cms_logo() ?></span>
    <div>
      <p class="wlc__eyebrow"><?= e(__('Willkommen bei KLXM Studio')) ?></p>
      <h2 id="wlc-h" class="wlc__title" tabindex="-1" autofocus><?= e(__('Schön, dass Sie da sind, {name}!', ['name' => $first])) ?></h2>
      <p class="wlc__lead"><?= e(__('Ihre Website ist eingerichtet. Mit diesen Schritten wird sie startklar – in beliebiger Reihenfolge, jederzeit wieder hier unter Hilfe & Support → Erste Schritte.')) ?></p>
    </div>
    <figure class="wlc__ring" style="--wlc-p:<?= round($pct, 3) ?>;--wlc-c:<?= round($circ, 2) ?>">
      <svg viewBox="0 0 64 64" aria-hidden="true"><circle class="wlc__ring-bg" cx="32" cy="32" r="<?= $r ?>"/><circle class="wlc__ring-fg" cx="32" cy="32" r="<?= $r ?>"/></svg>
      <figcaption><strong><?= $done ?>/<?= $total ?></strong><span><?= e(__('erledigt')) ?></span></figcaption>
    </figure>
  </header>
  <ol class="wlc__steps">
    <?php foreach ($steps as $i => $s): $ext = !str_starts_with($s['link'], '/admin'); ?>
    <li class="wlc__step<?= $s['ok'] ? ' is-done' : '' ?>" style="--i:<?= $i ?>">
      <span class="wlc__state" aria-hidden="true"><?= $s['ok'] ? icon('check') : '<span class="wlc__num">' . ($i + 1) . '</span>' ?></span>
      <span class="wlc__ico" aria-hidden="true"><?= icon($s['icon']) ?></span>
      <span class="wlc__txt">
        <strong><?= e($s['label']) ?><span class="sr-only"> – <?= e($s['ok'] ? __('erledigt') : __('offen')) ?></span></strong>
        <?php if ($s['hint'] !== ''): ?><span><?= e($s['hint']) ?></span><?php endif; ?>
      </span>
      <a class="wlc__go" href="<?= e($ext ? $s['link'] : url($s['link'])) ?>"><?= e($s['ok'] ? __('Ansehen') : __('Erledigen')) ?> <?= icon('arrow-right') ?></a>
    </li>
    <?php endforeach; ?>
  </ol>
  <footer class="wlc__foot">
    <nav class="wlc__links" aria-label="<?= e(__('Hilfe')) ?>">
      <a href="<?= e($siteUrl) ?>" target="_blank" rel="noopener"><?= icon('house') ?> <?= e(__('Website ansehen')) ?></a>
      <a href="<?= e(url('/admin/hilfe')) ?>"><?= icon('book-open-text') ?> <?= e(__('Handbuch')) ?></a>
      <a href="<?= e(\Core\I18n::locale() === 'en' ? 'https://studio.klxm.de/en/tutorials' : 'https://studio.klxm.de/tutorials') ?>" target="_blank" rel="noopener"><?= icon('play-circle') ?> <?= e(__('Video-Tutorials')) ?></a>
    </nav>
    <form method="dialog"><button type="submit" class="adm-btn adm-btn--primary"><?= e(__('Los geht’s')) ?></button></form>
  </footer>
</dialog>
<script src="<?= e(asset('js/welcome.js')) ?>" defer></script>
