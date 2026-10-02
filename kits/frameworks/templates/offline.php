<?php /** Offline-Seite der installierten Web-App (vom Service Worker vorab gespeichert). */ ?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(frameworks_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(lt('Offline')) ?> · <?= e(frameworks_name()) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<?= design_head() ?>
</head>
<body>
<main id="main" class="sec"><div class="wrap stack">
  <p class="eyebrow"><?= e(frameworks_name()) ?></p>
  <h1><?= e(lt('Gerade keine Internetverbindung.')) ?></h1>
  <p class="lead"><?= e(lt('Sobald Sie wieder online sind, lädt die Seite wie gewohnt.')) ?></p>
</div></main>
</body>
</html>
