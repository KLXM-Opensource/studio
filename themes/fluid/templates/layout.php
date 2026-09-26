<?php
/**
 * Grundlayout „fluid“.
 * Aufbau: .page (bei „Seitenleiste“ ein Flex-Umbruch: Kopf als Leiste links, sobald daneben genug Platz ist) →
 * Kopfbereich, .page__main (Inhalt + Fußbereich). Keine Breakpoints – siehe README.
 * @var array $page  @var string $content  @var array $seo  @var ?array $editor  @var ?array $toolbar  @var ?array $extraCss  @var ?array $extraJs
 */
$theme = app()->theme;
$lang = \Core\Lang::current();
$ogLocale = ['de' => 'de_DE', 'en' => 'en_GB', 'fr' => 'fr_FR', 'it' => 'it_IT', 'es' => 'es_ES', 'nl' => 'nl_NL'][$lang] ?? str_replace('-', '_', $lang);
$notice = setting('notice_active') && trim(strip_tags((string) setting('notice_text'))) !== '';
$extraCss = array_values(array_unique($extraCss ?? []));
// Design-Optionen mit eigenem Stylesheet (Kopf-/Fußvariante, Seitenhintergrund, Buttons, Dachzeilen, Glas-Karten) – nur das Gewählte
$optCss = [];
foreach (['header', 'footer', 'pagebg', 'buttons', 'eyebrow', 'cards'] as $opt) {
    $file = 'css/opt-' . $opt . '-' . preg_replace('~[^a-z]~', '', (string) design($opt)) . '.css';
    if (is_file(ROOT . '/public/themes/' . $theme->name . '/' . $file)) $optCss[] = theme_asset($file);
}
if ($editor || is_editing()) $optCss[] = theme_asset('css/editing.css');
?><!doctype html>
<html lang="<?= e($lang) ?>" class="<?= e(fluid_html_class()) ?><?= $editor ? ' is-editing' : '' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($seo['title']) ?></title>
<?php if ($seo['description']): ?><meta name="description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
<?php if ($seo['noindex']): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($seo['canonical']): ?><link rel="canonical" href="<?= e($seo['canonical']) ?>">
<meta property="og:url" content="<?= e($seo['canonical']) ?>">
<?php endif; ?>
<?php foreach ($seo['alternates'] ?? [] as $hl => $href): ?><link rel="alternate" hreflang="<?= e($hl) ?>" href="<?= e($href) ?>">
<?php endforeach; ?>
<?php if (!empty($seo['alternates'][\Core\Lang::default()])): ?><link rel="alternate" hreflang="x-default" href="<?= e($seo['alternates'][\Core\Lang::default()]) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= e($ogLocale) ?>">
<meta property="og:site_name" content="<?= e(fluid_name()) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<?php if ($seo['description']): ?><meta property="og:description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
<?php if ($seo['og_image']): ?><meta property="og:image" content="<?= e($seo['og_image']) ?>">
<?php endif; ?>
<meta name="color-scheme" content="<?= design('dark') ? 'light dark' : 'light' ?>">
<?= \Core\AppIcons::headTags() ?><?= fluid_font_preloads() ?>
<?= header_actions_head() /* Kopfbereich-Aktionen: CSS-Teile nach Bedarf (vor dem Kit-CSS), JavaScript nur bei interaktiven Optionen */ ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<?php foreach ($optCss as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php foreach ($extraCss as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php $designHead = design_head(); ?><?= $designHead ?>
<?php if (str_contains($designHead, 'data-design-preview')): /* Vorschau im Style-Editor: Dunkel-Regeln auch über html.is-dark */ ?><link rel="stylesheet" href="<?= e(theme_asset('css/preview.css')) ?>">
<?php endif; ?>
<?php if ($toolbar): ?><link rel="stylesheet" href="<?= e(asset('css/editor.css')) ?>">
<?php endif; ?>
<?php if (!$editor): ?><script src="<?= e(theme_asset('js/site.js')) ?>" defer></script>
<?php endif; ?>
<?php if (isset(app()->request->query['pwa']) && setting('sys.pwa', true) && setting('sys.pwa_offline', true)): ?><script src="<?= e(asset('js/pwa.js')) ?>" data-sw="<?= e(base_path()) ?>/sw.js" data-scope="<?= e(base_path()) ?>/" defer></script>
<?php endif; ?>
<?php foreach ($extraJs ?? [] as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php if ($toolbar && !$editor): ?><script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($seo['jsonld']): ?><script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="<?= !empty($page['is_home']) ? 'is-home' : 'is-sub' ?>">
<?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) ?><?php endif; ?>
<a class="skip" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>
<?php if ($notice): ?>
<div class="topnote" role="note"><div class="wrap"><?= inline((string) setting('notice_text')) ?></div></div>
<?php endif; ?>
<div class="page">
<?= $theme->partial('header') ?>
<div class="page__main">
<main id="main" tabindex="-1">
<?php if ($editor): ?>
<?= $theme->partial('editor', $editor) ?>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</main>
<?= $theme->partial('footer') ?>
</div>
</div>
<?= $theme->partial('sheet') ?>
<?php /* Aufklappmenüs, Such-Popover, Seitenblatt: bis zur Bedienung verborgen → nicht renderblockierend am Ende */ ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/overlay.css')) ?>">
<?= cms_chat_launcher($page ?? null, (bool) ($editor ?? false)) /* Besucher-Chat (Core\AI\VisitorChat) – leer, solange aus */ ?>
<?= header_actions_late() /* Kopfbereich-Aktionen: Paneel des Kontakt-Menüs – nicht renderblockierend */ ?>
</body>
</html>
