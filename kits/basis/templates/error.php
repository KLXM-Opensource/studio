<?php /** Fehlerseite (im Grundlayout). @var int $code  @var string $message */
$notFound = $code === 404;
$email = basis_email();
?>
<section class="sec hero hero--compact" aria-labelledby="err-title">
  <div class="wrap">
    <div class="hero__text">
      <span class="err__code" aria-hidden="true"><?= e((string) $code) ?></span>
      <p class="eyebrow"><?= e(lt('Fehler {code}', ['code' => $code])) ?></p>
      <h1 id="err-title" class="h1"><?= e($notFound ? lt('Diese Seite gibt es nicht (mehr).') : lt('Da ist etwas schiefgelaufen.')) ?></h1>
      <p class="hero__lead"><?= e($notFound
          ? lt('Vielleicht hat sich die Adresse geändert. Über die Startseite oder das Menü finden Sie weiter.')
          : lt('Bitte versuchen Sie es in einigen Minuten erneut.')) ?></p>
      <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url(\Core\Lang::prefix(\Core\Lang::current()) . '/')) ?>"><?= e(lt('Zur Startseite')) ?></a>
        <?php if ($email !== ''): ?><a class="btn btn--secondary" href="mailto:<?= e($email) ?>"><?= e(lt('E-Mail schreiben')) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
