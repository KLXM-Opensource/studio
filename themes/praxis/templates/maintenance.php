<!doctype html>
<html lang="<?= e(\Core\Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(setting('praxis_name')) ?></title>
<link rel="stylesheet" href="<?= e(theme_asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset('css/blocks.css')) ?>">
<?= \Core\AppIcons::headTags() ?>
</head>
<body>
<main id="main" class="sec bg-bordeaux maintenance">
  <div class="wrap">
    <p class="wordmark wordmark--footer"><span class="wordmark__1"><?= e(setting('wortmarke_1')) ?><span class="dot">.</span></span><span class="wordmark__2"><?= e(setting('wortmarke_2')) ?></span></p>
    <h1 class="h2"><?= e($text) ?></h1>
    <p><a class="btn btn--light" href="<?= e(praxis_phone_href()) ?>"><?= e(praxis_phone()) ?></a></p>
  </div>
</main>
</body>
</html>
