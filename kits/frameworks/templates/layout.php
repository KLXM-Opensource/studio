<?php
/**
 * Grundlayout – für beide Frameworks gleich. Unterschiede: Stylesheets/Skripte (frameworks_assets()), Kopf und Fuß
 * (views/{framework}/header.php, footer.php) und die Klasse fw-{framework} am <html>.
 *
 * Reihenfolge im <head> (wichtig für die Kaskade):
 *   1. Framework-CSS (Tailwind: alles in @layer theme/base/components/utilities · UIkit: ohne Layer)
 *   2. css/site.css (Design-Tokens, Variablen der Kern-Bausteine) – ohne Layer, gewinnt also gegen Tailwind-Utilities
 *   3. Kern-/Block-Stylesheets ($extraCss: Formular, Datenliste, Glossar …) – ohne Layer
 *   4. design_head() (Werte des Style-Editors) · 5. editor.css nur für die angemeldete Redaktion
 * Skripte: alle mit defer, keine Inline-Skripte (CSP). UIkit initialisiert sich über Attribute (uk-navbar, uk-offcanvas …)
 * selbst – CSP-sicher. Im Bearbeiten-Modus lädt das Framework-JS weiter (Kopf: Aufklappmenü, Offcanvas), die Blöcke geben
 * dann aber keine interaktiven Attribute aus (alle Einträge offen, siehe views/*).
 *
 * @var array $page  @var string $content  @var array $seo  @var ?array $editor  @var ?array $toolbar  @var ?array $extraCss  @var ?array $extraJs
 */
$theme = app()->theme;
$lang = \Core\Lang::current();
$notice = notice_on(); // Core\Notice: Schalter, Zeitraum, Darstellung
$fw = frameworks_assets();
?><!doctype html>
<html lang="<?= e($lang) ?>" class="<?= e(frameworks_html_class()) ?><?= $editor ? ' is-editing' : '' ?>">
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
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(frameworks_name()) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<?php if ($seo['og_image']): ?><meta property="og:image" content="<?= e($seo['og_image']) ?>">
<?php endif; ?>
<meta name="color-scheme" content="<?= design('dark') ? 'light dark' : 'light' ?>">
<?= \Core\AppIcons::headTags() ?>
<?php foreach ($fw['css'] as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php foreach (array_unique($extraCss ?? []) as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?= design_head() ?>
<?php if ($toolbar): ?><link rel="stylesheet" href="<?= e(asset('css/editor.css')) ?>">
<?php endif; ?>
<?php foreach ($fw['js'] as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php foreach ($extraJs ?? [] as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php if ($toolbar && !$editor): ?><script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($seo['jsonld']): ?><script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body>
<?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) ?><?php endif; ?>
<a class="fw-skip" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>
<?php if ($notice): ?><?= notice_open('fw-notice') ?><?= inline((string) setting('notice_text')) ?></div>
<?php endif; ?>
<?= $theme->partial('header') ?>
<main id="main" tabindex="-1">
<?= $editor ? $theme->partial('editor', $editor) : $content ?>
</main>
<?= $theme->partial('footer') ?>
<?= cms_chat_launcher($page ?? null, (bool) $editor) ?>
<?= notice_late('fw-notice') /* Hinweis als Bubble bzw. mit Zeitraum */ ?>
</body>
</html>
