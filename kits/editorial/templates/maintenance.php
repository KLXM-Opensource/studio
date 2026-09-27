<?php /** Wartungsmodus (eigenständige Seite, HTTP 503). @var string $text */
$email = editorial_email();
?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(editorial_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(editorial_name()) ?></title>
<?= \Core\AppIcons::headTags() ?><?= editorial_font_preloads() ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/hero.css')) ?>">
<?= design_head() ?>
</head>
<body class="standalone">
<main id="main" class="standalone__main">
  <div class="wrap wrap--text">
    <?= app()->theme->partial('brand', ['href' => url('/'), 'class' => 'brand--mast']) ?>
    <hr class="rule">
    <p class="kicker"><?= e(lt('Wartungsarbeiten')) ?></p>
    <h1 class="h1"><?= e(trim((string) $text) !== '' ? (string) $text : lt('Die nächste Ausgabe ist gleich da.')) ?></h1>
    <p class="lead"><?= e(lt('Die Website wird gerade aktualisiert. Bitte schauen Sie in Kürze wieder vorbei.')) ?></p>
    <?php if ($email !== ''): ?><div class="btn-row"><a class="btn btn--primary" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div><?php endif; ?>
  </div>
</main>
</body>
</html>
