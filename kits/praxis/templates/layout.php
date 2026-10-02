<?php
/**
 * Grundlayout.
 * @var array $page  @var string $content  @var array $seo  @var ?array $editor  @var ?array $toolbar
 */
$theme = app()->theme;
$phone = praxis_phone();
?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="no-js<?= $editor ? ' is-editing' : '' ?><?= ($dc = design_classes()) !== '' ? ' ' . e($dc) : '' ?>"<?= $editor ? '' : ' data-l10n="' . json_attr(praxis_js_texts()) . '"' ?>>
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
<meta property="og:locale" content="<?= e(\Core\Lang::current() === 'de' ? 'de_DE' : (\Core\Lang::current() === 'en' ? 'en_GB' : \Core\Lang::current())) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<?php if ($seo['description']): ?><meta property="og:description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
<?php if ($seo['og_image']): ?><meta property="og:image" content="<?= e($seo['og_image']) ?>">
<?php endif; ?>
<?= \Core\AppIcons::headTags() ?><?= app()->theme->fontPreloads() ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<?php if (\Core\Features::on('search')): ?><link rel="stylesheet" href="<?= e(theme_asset('css/hsearch.css')) ?>">
<?php endif; ?>
<?php if (\Core\Lang::multi() || array_filter(\Core\Pages::menu(app()->auth->check()), fn($m) => $m['children'])): ?><link rel="stylesheet" href="<?= e(theme_asset('css/nav.css')) ?>">
<?php endif; ?>
<?php foreach ($extraCss ?? [] as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?= design_head() ?>
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
<body class="<?= $page['is_home'] ? 'is-home' : 'is-sub' ?>">
<?php if ($toolbar): ?><?= $theme->partial('toolbar', $toolbar) ?><?php endif; ?>
<a class="skip" href="#main"><?= e(lt('Zum Inhalt springen')) ?></a>

<?php if (notice_on()): /* Core\Notice: Schalter, Zeitraum, Darstellung */ ?>
<?= notice_open('topnote') ?><strong><?= e(lt('Aktuell.')) ?></strong> <?= inline((string) setting('aktueller_hinweis_text')) ?></div>
<?php endif; ?>

<?= $theme->partial('header', ['phone' => $phone]) ?>

<main id="main" tabindex="-1">
<?php if (!$editor && !str_contains($content, '<h1')): /* Seiten ohne Hero/eigene H1 (Rechtstexte, Übersichten, Detailseiten): Seitentitel als H1 für Screenreader und Gliederung */
    $h1 = app()->entry ? \Core\Data\Entries::title(app()->entry['table'], app()->entry['entry']) : (string) ($page['title'] ?? ''); ?>
<?php if (trim($h1) !== ''): ?><h1 class="sr-only"><?= e($h1) ?></h1>
<?php endif; endif; ?>
<?php if ($editor): ?>
<?= $theme->partial('editor', $editor) ?>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</main>

<?= $theme->partial('footer') ?>

<div class="bottombar" role="region" aria-label="<?= e(lt('Schnellkontakt')) ?>" data-cms-hide-editing>
  <a class="bottombar__call" href="<?= e(praxis_phone_href()) ?>">
    <?= praxis_icon('tel', 18) ?>
    <?= e(lt('Anrufen')) ?>
  </a>
  <?php $svc = praxis_services(); $termin = $svc['termin'] ?? null; ?>
  <a href="<?= e($termin ? $termin['href'] : link_href('#kontakt')) ?>"<?= $termin ? ' data-flip="termin"' . ext_attrs($termin['href']) : '' ?>>
    <?= praxis_icon('termin', 18) ?>
    <?= e(lt('Termin')) ?>
  </a>
</div>
<?php /* Mobilmenü-Stile lädt site.js beim ersten Öffnen (partials/header.php → data-css); ohne JavaScript hier */ ?>
<?php if ($editor): ?><link rel="stylesheet" href="<?= e(theme_asset('css/mnav.css')) ?>" media="(max-width:1079.98px)">
<?php else: ?><noscript><link rel="stylesheet" href="<?= e(theme_asset('css/mnav.css')) ?>" media="(max-width:1079.98px)"></noscript>
<?php endif; ?>
<?= cms_chat_launcher($page ?? null, (bool) ($editor ?? false)) /* Besucher-Chat (Core\AI\VisitorChat) – leer, solange aus */ ?>
<?= notice_late('topnote') /* Hinweis als Bubble bzw. mit Zeitraum */ ?>
</body>
</html>
