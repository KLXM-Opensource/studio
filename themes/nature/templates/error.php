<?php /** Fehlerseite (im Grundlayout). @var int $code  @var string $message */
$notFound = $code === 404;
$email = nature_email();
$search = \Core\Search\Search::form('page');
?>
<link rel="stylesheet" href="<?= e(theme_asset('css/pages.css')) ?>">
<section class="sec hero-err" aria-labelledby="err-title">
  <div class="wrap err">
    <p class="err__code" aria-hidden="true"><?= e((string) $code) ?></p>
    <div class="err__text stack">
      <p class="eyebrow"><?= e(lt('Fehler {code}', ['code' => $code])) ?></p>
      <h1 id="err-title" class="h1"><?= e($notFound ? lt('Diese Seite gibt es nicht (mehr).') : lt('Da ist etwas schiefgelaufen.')) ?></h1>
      <p class="lead"><?= e($notFound
          ? lt('Vielleicht hat sich die Adresse geändert. Über die Startseite, das Menü oder die Suche finden Sie weiter.')
          : lt('Bitte versuchen Sie es in einigen Minuten erneut.')) ?></p>
      <?php if ($notFound && $search !== ''): ?><div class="err__search"><?= $search ?></div><?php endif; ?>
      <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url(\Core\Lang::prefix(\Core\Lang::current()) . '/')) ?>"><?= e(lt('Zur Startseite')) ?></a>
        <?php if ($email !== ''): ?><a class="btn btn--secondary" href="mailto:<?= e($email) ?>"><?= e(lt('E-Mail schreiben')) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
