<?php
/**
 * Grundlayout – jede Seite, Detailseite, Suche und Fehlerseite läuft hier durch (Core\Theme::render('layout')).
 * Aufbau: Hinweisbalken → Kopf → <main> → Fuß → Besucher-Chat-Knopf.
 * CSP: keine Inline-Skripte/-Styles; JSON-LD ist als application/ld+json erlaubt.
 *
 * @var array  $page      aktuelle Seite (is_home, title, …)
 * @var string $content   gerenderte Blöcke
 * @var array  $seo       title, description, canonical, alternates, og_image, noindex, jsonld (Core\Seo)
 * @var ?array $editor    gesetzt im Bearbeiten-Modus (Editor.js übernimmt <main>)
 * @var ?array $toolbar   gesetzt, wenn angemeldet (Redaktions-Werkzeugleiste)
 * @var ?array $extraCss  Kern-/Block-Stylesheets dieser Seite (conditional_css, data.css, search.css …)
 * @var ?array $extraJs   Block-Skripte dieser Seite
 */
$theme = app()->theme;
$lang = \Core\Lang::current();
$notice = setting('notice_active') && trim(strip_tags((string) setting('notice_text'))) !== '';
?><!doctype html>
<html lang="<?= e($lang) ?>" class="<?= e(starter_html_class()) ?><?= $editor ? ' is-editing' : '' ?>">
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
<?php foreach ($seo['alternates'] ?? [] as $hl => $href): /* Mehrsprachigkeit: hreflang je Übersetzung */ ?><link rel="alternate" hreflang="<?= e($hl) ?>" href="<?= e($href) ?>">
<?php endforeach; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(starter_name()) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<?php if ($seo['og_image']): ?><meta property="og:image" content="<?= e($seo['og_image']) ?>">
<?php endif; ?>
<meta name="color-scheme" content="<?= design('dark') ? 'light dark' : 'light' ?>">
<?= \Core\AppIcons::headTags() /* Favicon, App-Icons, Manifest */ ?><?= $theme->fontPreloads() /* theme.php → fonts.preload */ ?>
<?= header_actions_head() /* Kopfbereich-Aktionen (Core): CSS-Teile nach Bedarf VOR dem Kit-CSS, JavaScript nur bei interaktiven Optionen */ ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<?php foreach (array_unique($extraCss ?? []) as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?= design_head() /* NACH dem Kit-CSS: gewählte Schriften + Design-Werte des Style-Editors (nur wenn geändert) */ ?>
<?php if ($toolbar): ?><link rel="stylesheet" href="<?= e(asset('css/editor.css')) ?>">
<?php endif; ?>
<?php if (!$editor): ?><script src="<?= e(theme_asset('js/site.js')) ?>" defer></script>
<?php endif; ?>
<?php if (isset(app()->request->query['pwa']) && setting('sys.pwa', true) && setting('sys.pwa_offline', true)): /* installierte App: Service Worker */ ?><script src="<?= e(asset('js/pwa.js')) ?>" data-sw="<?= e(base_path()) ?>/sw.js" data-scope="<?= e(base_path()) ?>/" defer></script>
<?php endif; ?>
<?php foreach ($extraJs ?? [] as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<?php if ($toolbar && !$editor): ?><script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($seo['jsonld']): /* schema.org @graph (Core\StructuredData + theme.php → jsonld) */ ?><script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body>
<?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) /* ohne eigenes Partial rendert der Core die Leiste */ ?><?php endif; ?>
<a class="skip" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>
<?php if ($notice): ?><div class="notice" role="note"><div class="wrap"><?= inline((string) setting('notice_text')) ?></div></div>
<?php endif; ?>
<?= $theme->partial('header') ?>
<main id="main" tabindex="-1">
<?= $editor ? $theme->partial('editor', $editor) : $content ?>
</main>
<?= $theme->partial('footer') ?>
<?= cms_chat_launcher($page ?? null, (bool) $editor) /* Besucher-Chat (Core\AI\VisitorChat) – leer, solange ausgeschaltet */ ?>
<?= header_actions_late() /* Kopfbereich-Aktionen: Paneel des Kontakt-Menüs – nicht renderblockierend */ ?>
</body>
</html>
