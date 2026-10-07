<?php
/**
 * Hinweis für Besucher, solange der Erststart offen ist (Core\Onboarding – Kit und Startinhalte noch nicht gewählt).
 * Bewusst ohne Kit: Es gibt noch keins. Eigenes kleines Stylesheet (CSP: keine Inline-Stile), Sprache der Website.
 * @var string $lang
 */
?><!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(lt('Hier entsteht eine neue Website')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/setup-pending.css')) ?>">
</head>
<body>
<main class="sp">
  <span class="sp__mark" aria-hidden="true"></span>
  <h1><?= e(lt('Hier entsteht eine neue Website.')) ?></h1>
  <p><?= e(lt('Sie wird gerade eingerichtet – schauen Sie bald wieder vorbei.')) ?></p>
</main>
</body>
</html>
