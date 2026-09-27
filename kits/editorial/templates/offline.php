<?php /** Offline-Seite der installierten App (vom Service Worker vorab gespeichert). */
$email = editorial_email();
?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(editorial_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(lt('Offline')) ?> · <?= e(editorial_name()) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/hero.css')) ?>">
<?= design_head() ?>
<link rel="icon" href="<?= e(base_path()) ?>/favicon.ico" sizes="48x48">
</head>
<body class="standalone">
<main id="main" class="standalone__main">
  <div class="wrap wrap--text">
    <?= app()->theme->partial('brand', ['href' => url('/'), 'class' => 'brand--mast']) ?>
    <hr class="rule">
    <p class="kicker"><?= e(lt('Offline')) ?></p>
    <h1 class="h1"><?= e(lt('Gerade keine Internetverbindung.')) ?></h1>
    <p class="lead"><?= e(lt('Sobald Sie wieder online sind, lädt die Seite wie gewohnt. Bereits gelesene Beiträge sind eventuell noch verfügbar.')) ?></p>
    <?php if ($email !== ''): ?><div class="btn-row"><a class="btn btn--ghost" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div><?php endif; ?>
  </div>
</main>
</body>
</html>
