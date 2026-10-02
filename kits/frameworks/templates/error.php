<?php
/** Fehlerseite (404, 500 …) – wird ins Grundlayout eingesetzt. @var int $code  @var string $message */
$search = $code === 404 ? \Core\Search\Search::form('page') : '';
?>
<section class="sec" aria-labelledby="err-title">
  <div class="wrap stack">
    <p class="eyebrow"><?= e(lt('Fehler {code}', ['code' => $code])) ?></p>
    <h1 id="err-title"><?= e($code === 404 ? lt('Diese Seite gibt es nicht (mehr).') : lt('Da ist etwas schiefgelaufen.')) ?></h1>
    <p class="lead"><?= e($code === 404 ? lt('Vielleicht hat sich die Adresse geändert. Über die Startseite, das Menü oder die Suche finden Sie weiter.') : lt('Bitte versuchen Sie es in einigen Minuten erneut.')) ?></p>
    <?= $search ?>
    <div class="btn-row"><a class="btn btn--primary" href="<?= e(url(\Core\Lang::prefix(\Core\Lang::current()) . '/')) ?>"><?= e(lt('Zur Startseite')) ?></a></div>
  </div>
</section>
