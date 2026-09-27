<section class="sec" aria-labelledby="err-title">
  <div class="wrap">
    <p class="eyebrow"><?= e(lt('Fehler {code}', ['code' => (int) $code])) ?></p>
    <h1 id="err-title" class="h2"><?= e($code === 404 ? lt('Seite nicht gefunden') : lt('Da ist etwas schiefgelaufen')) ?><span class="dot">.</span>
      <span class="light"><?= e($code === 404 ? lt('Die Adresse existiert nicht (mehr).') : lt('Bitte versuchen Sie es später erneut.')) ?></span></h1>
    <p class="lead"><?= praxis_fill(lt('Telefonisch sind wir wie gewohnt erreichbar: {phone}'), ['phone' => praxis_phone_link()]) ?></p>
    <p><a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= e(lt('Zur Startseite')) ?> <span aria-hidden="true">→</span></a></p>
  </div>
</section>
