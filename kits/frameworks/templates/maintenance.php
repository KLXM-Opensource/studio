<?php /** Wartungsmodus – eigenständige Seite (HTTP 503), ohne Kopf/Fuß. @var string $text */ ?><!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>" class="<?= e(frameworks_html_class()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(frameworks_name()) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<?= design_head() ?>
</head>
<body>
<main id="main" class="sec"><div class="wrap stack">
  <p class="eyebrow"><?= e(frameworks_name()) ?></p>
  <h1><?= e(trim((string) $text) !== '' ? (string) $text : lt('Wir sind gleich wieder da.')) ?></h1>
  <p class="lead"><?= e(lt('Die Website wird gerade aktualisiert. Bitte schauen Sie in Kürze wieder vorbei.')) ?></p>
</div></main>
</body>
</html>
