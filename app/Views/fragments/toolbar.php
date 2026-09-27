<?php
/**
 * Kern-Fragment „toolbar“ (nur Kern – Kits können es nicht ersetzen, Core\Fragments::CORE_ONLY):
 * Redaktions-Werkzeugleiste der Website für alle Modi (Seite, Eintrag, Vorlage) – Core\Toolbar, app/Views/toolbar.php.
 * Aufruf im Layout des Kits: <?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) ?><?php endif; ?>
 * Theme::partial() hängt sie in ein eigenes Shadow DOM (Theme::toolbarHost), damit Kit-CSS nicht hineinwirkt.
 * @var array $page  @var bool $editing  @var bool $dirty  @var bool $live
 */
echo \Core\Toolbar::render(array_diff_key(get_defined_vars(), ['options' => 1, 'fragment' => 1, '__file' => 1, '__vars' => 1]));
