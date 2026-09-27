<?php /** Offline-Seite der installierten App (vom Service Worker vorab gespeichert). */ ?>
<!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(lt('Offline')) ?> · <?= e(setting('praxis_name')) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/blocks.css')) ?>">
<link rel="icon" href="<?= e(base_path()) ?>/favicon.ico" sizes="48x48">
</head>
<body>
<main id="main" class="sec bg-gray offline">
  <div class="wrap">
    <p class="wordmark"><span class="wordmark__1"><?= e(setting('wortmarke_1')) ?><span class="dot">.</span></span><span class="wordmark__2"><?= e(setting('wortmarke_2')) ?></span></p>
    <h1 class="h2"><?= e(lt('Gerade keine Internetverbindung')) ?><span class="dot">.</span></h1>
    <p class="muted"><?= e(lt('Sobald Sie wieder online sind, lädt die Seite normal. Telefonisch erreichen Sie uns wie gewohnt.')) ?></p>
    <?php if (praxis_has_phone()): ?><p><a class="btn btn--primary" href="<?= e(praxis_phone_href()) ?>"><?= e(praxis_phone()) ?></a></p><?php endif; ?>
    <h2 class="h3"><?= e(lt('Sprechzeiten')) ?></h2>
    <?= app()->theme->partial('hours-table', ['class' => 'htable--card', 'noToday' => true]) ?>
  </div>
</main>
</body>
</html>
