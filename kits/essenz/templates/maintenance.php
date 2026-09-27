<?php /** Wartungsmodus (eigenständige Seite, HTTP 503). @var string $text */
$email = essenz_email();
$phone = essenz_phone();
?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(essenz_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(essenz_name()) ?></title>
<?= \Core\AppIcons::headTags() ?><?= essenz_font_preloads() ?>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/pages.css')) ?>">
<?= design_head() ?>
</head>
<body class="standalone">
<main id="main" class="standalone__main">
  <div class="wrap standalone__box stack">
    <?= app()->theme->partial('brand', ['href' => url('/')]) ?>
    <p class="eyebrow"><?= e(lt('Wartungsarbeiten')) ?></p>
    <h1 class="h1"><?= e(trim((string) $text) !== '' ? (string) $text : lt('Wir sind gleich wieder da.')) ?></h1>
    <p class="lead"><?= e(lt('Die Website wird gerade aktualisiert. Bitte schauen Sie in Kürze wieder vorbei.')) ?></p>
    <?php if ($email !== '' || filled($phone)): ?>
    <div class="btn-row">
      <?php if (filled($phone) && ($tel = essenz_phone_href())): ?><a class="btn btn--primary" href="<?= e($tel) ?>"><?= e($phone) ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a class="btn btn--secondary" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
