<?php /** Offline-Seite der installierten App (vom Service Worker vorab gespeichert). */
$email = glas_email();
$phone = glas_phone();
$hours = glas_hours();
?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(glas_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(lt('Offline')) ?> · <?= e(glas_name()) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/pages.css')) ?>">
<?= design_head() ?>
<link rel="icon" href="<?= e(base_path()) ?>/favicon.ico" sizes="48x48">
</head>
<body class="standalone">
<main id="main" class="standalone__main">
  <div class="wrap standalone__box stack">
    <?= app()->theme->partial('brand', ['href' => url('/')]) ?>
    <h1 class="h1"><?= e(lt('Gerade keine Internetverbindung.')) ?></h1>
    <p class="lead"><?= e(lt('Sobald Sie wieder online sind, lädt die Seite wie gewohnt.')) ?></p>
    <?php if ($email !== '' || filled($phone)): ?>
    <div class="btn-row">
      <?php if (filled($phone) && ($tel = glas_phone_href())): ?><a class="btn btn--primary" href="<?= e($tel) ?>"><?= e($phone) ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a class="btn btn--secondary" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($hours): ?>
    <h2 class="h3"><?= e(lt('Öffnungszeiten')) ?></h2>
    <?= app()->theme->partial('hours', ['hours' => $hours]) ?>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
