<?php
/** Administration → Statistiken (Core\AdminPages, PrefsController): Berichte und Statistiken von Funktionen und Erweiterungen (kind „stats“). @var array $groups */
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Werkzeuge')) ?></p><h1><?= e(__('Statistiken')) ?></h1>
    <p class="adm-muted"><?= e(__('Berichte und Auswertungen der Funktionen und Erweiterungen dieser Website.')) ?></p></div>
</header>
<?= \Core\Theme::capture(__DIR__ . '/_cards.php', ['groups' => $groups]) ?>
