<?php
/**
 * Grundlayout „editorial“.
 * @var array $page  @var string $content  @var array $seo  @var ?array $editor  @var ?array $toolbar  @var ?array $extraCss  @var ?array $extraJs
 */
$theme = app()->theme;
$lang = \Core\Lang::current();
$ogLocale = ['de' => 'de_DE', 'en' => 'en_GB', 'fr' => 'fr_FR', 'it' => 'it_IT', 'es' => 'es_ES', 'nl' => 'nl_NL'][$lang] ?? str_replace('-', '_', $lang);
$notice = notice_on(); // Core\Notice: Schalter, Zeitraum, Darstellung
$head = editorial_header();
$isEntry = app()->entry !== null;
?><!doctype html>
<html lang="<?= e($lang) ?>" class="<?= e(editorial_html_class()) ?><?= $editor ? ' is-editing' : '' ?>">
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
<meta property="og:type" content="<?= $isEntry ? 'article' : 'website' ?>">
<meta property="og:locale" content="<?= e($ogLocale) ?>">
<meta property="og:site_name" content="<?= e(editorial_name()) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<?php if ($seo['description']): ?><meta property="og:description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
<?php if ($seo['og_image']): ?><meta property="og:image" content="<?= e($seo['og_image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="color-scheme" content="<?= design('dark') ? 'light dark' : 'light' ?>">
<?= \Core\AppIcons::headTags() ?><?= editorial_font_preloads() ?>
<?= header_actions_head() /* Kopfbereich-Aktionen: CSS-Teile nach Bedarf (vor dem Kit-CSS), JavaScript nur bei interaktiven Optionen */ ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/head-' . $head . '.css')) ?>">
<?php foreach (array_unique($extraCss ?? []) as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php $designHead = design_head(); ?><?= $designHead ?>
<?php if (str_contains($designHead, 'data-design-preview')): /* Vorschau im Style-Editor: Nachtausgabe auch über html.is-dark */ ?><link rel="stylesheet" href="<?= e(theme_asset('css/preview.css')) ?>">
<?php endif; ?>
<?php if ($toolbar): ?><link rel="stylesheet" href="<?= e(asset('css/editor.css')) ?>">
<?php endif; ?>
<?php if (!$editor): ?><script src="<?= e(theme_asset('js/site.js')) ?>" defer></script>
<?php endif; ?>
<?php if (isset(app()->request->query['pwa']) && setting('sys.pwa', true) && setting('sys.pwa_offline', true)): ?><script src="<?= e(asset('js/pwa.js')) ?>" data-sw="<?= e(base_path()) ?>/sw.js" data-scope="<?= e(base_path()) ?>/" defer></script>
<?php endif; ?>
<?php foreach (array_unique($extraJs ?? []) as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php if ($toolbar && !$editor): ?><script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($seo['jsonld']): ?><script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="<?= !empty($page['is_home']) ? 'is-home' : 'is-sub' ?><?= $isEntry ? ' is-entry' : '' ?>">
<a class="skip" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>   <?php /* Skiplink zuerst – auch vor der Werkzeugleiste der Redaktion (Tab-Reihenfolge) */ ?>
<?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) ?><?php endif; ?>
<?php if ($notice): ?>
<?= notice_open('topnote') ?><div class="wrap"><span class="topnote__label"><?= e(lt('Hinweis')) ?></span> <?= inline((string) setting('notice_text')) ?></div></div>
<?php endif; ?>

<?= $theme->partial('header') ?>

<main id="main" tabindex="-1">
<?php if ($editor): ?>
<?= $theme->partial('editor', $editor) ?>
<?php else: ?>
<?php if (!str_contains($content, '<h1')): /* Seiten ohne Aufmacher (z. B. Startseite mit Datenliste): Überschrift für Screenreader */ ?><h1 class="sr-only"><?= e(!empty($page['is_home']) ? editorial_name() : (string) ($page['title'] ?? editorial_name())) ?></h1>
<?php endif; ?>
<?= $content ?>
<?php endif; ?>
</main>

<?= $theme->partial('footer') ?>
<?= cms_chat_launcher($page ?? null, (bool) ($editor ?? false)) /* Besucher-Chat (Core\AI\VisitorChat) – leer, solange aus */ ?>
<?= header_actions_late() /* Kopfbereich-Aktionen: Paneel des Kontakt-Menüs – nicht renderblockierend */ ?>
<?= notice_late('topnote') /* Hinweis als Bubble bzw. mit Zeitraum */ ?>
</body>
</html>
