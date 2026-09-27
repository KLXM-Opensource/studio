<?php /** Fehlerseite (im Grundlayout). @var int $code  @var string $message */
$notFound = $code === 404;
$email = editorial_email();
$menu = array_slice(\Core\Pages::menu(false), 0, 6);
?>
<link rel="stylesheet" href="<?= e(theme_asset('css/hero.css')) ?>">
<section class="sec err" aria-labelledby="err-title">
  <div class="wrap err__grid">
    <p class="err__code" aria-hidden="true"><?= e((string) $code) ?></p>
    <div class="err__text">
      <p class="kicker"><?= e(lt('Fehler {code}', ['code' => $code])) ?></p>
      <h1 id="err-title" class="h1"><?= e($notFound ? lt('Diese Seite ist vergriffen.') : lt('Da ist etwas schiefgelaufen.')) ?></h1>
      <p class="lead"><?= e($notFound
          ? lt('Vielleicht hat sich die Adresse geändert oder der Beitrag ist ins Archiv gewandert. Über die Startseite, die Suche oder eine Rubrik finden Sie weiter.')
          : lt('Bitte versuchen Sie es in einigen Minuten erneut.')) ?></p>
      <?php if ($notFound && ($form = \Core\Search\Search::form('page')) !== ''): ?><div class="err__search"><?= $form ?></div><?php endif; ?>
      <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url(\Core\Lang::prefix(\Core\Lang::current()) . '/')) ?>"><?= e(lt('Zur Startseite')) ?></a>
        <?php if ($email !== ''): ?><a class="btn btn--ghost" href="mailto:<?= e($email) ?>"><?= e(lt('E-Mail schreiben')) ?></a><?php endif; ?>
      </div>
      <?php if ($notFound && $menu): ?>
      <nav class="err__nav" aria-label="<?= e(lt('Rubriken')) ?>"><?= editorial_tab_links($menu, 'err__list') ?></nav>
      <?php endif; ?>
    </div>
  </div>
</section>
