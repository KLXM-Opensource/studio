<?php /** Mediathek – Oberfläche wird von resources/js/_media.js aufgebaut. @var int $maxMb */ ?>
<?php // Überschrift nur für Screenreader – Hinweise zum Hochladen stehen rechts in der Mediathek (mehr Platz für Dateien) ?>
<h1 class="adm-sr">Medien</h1>
<div data-media-library data-media-max="<?= (int) $maxMb ?>" data-media-formats="<?= function_exists('imageavif') ? 'WebP/AVIF' : 'WebP' ?>" data-media-base="<?= e(rtrim(url('/'), '/')) ?>">
  <noscript>
    <form class="adm-card" method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="f"><label for="up-file">Datei</label><input id="up-file" type="file" name="file" required></div>
      <div class="f"><label for="up-alt">Alt-Text (Pflicht bei Bildern)</label><input id="up-alt" name="alt" maxlength="250"></div>
      <button class="adm-btn adm-btn--primary" type="submit">Hochladen</button>
    </form>
  </noscript>
</div>
