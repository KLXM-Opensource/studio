<?php
/**
 * Video mit Zwei-Klick-Lösung (Kern-Helfer, kaum Kit-Code):
 *   YouTube/Vimeo: Core\Embeds::parse() erkennt den Link, Embeds::meta() holt Titel + Vorschaubild über den eigenen Server
 *   (Proxy, gecacht). Der Player (youtube-nocookie / dnt=1) lädt erst nach Klick – js/video.js; „künftig direkt laden“
 *   speichert der Browser (localStorage, kein Cookie) oder die Erweiterung consent_kit (window.cmsConsent).
 *   MP4 aus der Mediathek: <video> mit Untertiteln (<track>) und Transkript (Core\MediaTracks).
 *   Vorschaubild: eigenes Poster → sonst Media::posterFor() (Hook „mediaPoster“, z. B. Erweiterung video_tools).
 * @var string $url  @var ?int $file  @var ?int $poster  @var string $label
 */
use Core\Embeds;
use Core\Media;

$v = Embeds::parse($url ?? '');
$mp4 = !$v && !empty($file) ? media((int) $file) : null;
$own = !empty($poster) ? media((int) $poster) : ($mp4 ? Media::posterFor($mp4) : null);
?>
<?php if ($mp4): ?>
<div class="vembed">
  <video controls preload="none" playsinline<?= $own ? ' poster="' . e(Media::url($own, 1200)) . '"' : '' ?>><source src="<?= e(Media::url($mp4)) ?>" type="video/mp4"><?= \Core\MediaTracks::trackTags($mp4) ?></video>
</div>
<?= \Core\MediaTracks::transcriptHtml($mp4) ?>
<?php elseif ($v):
    $meta = Embeds::meta($v);
    $prov = Embeds::PROVIDERS[$v['provider']];
    $title = ($label ?? '') !== '' ? $label : ($meta['title'] ?: lt('Video'));
?>
<div class="vembed" data-embed="<?= e($v['provider']) ?>" data-src="<?= e(Embeds::playerUrl($v)) ?>" data-title="<?= e($title) ?>"
     data-t-revoke="<?= e(lt('Freigabe widerrufen')) ?>" data-t-direct="<?= e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $prov['label']])) ?>">
  <?php if ($own): ?><?= img((int) $poster, '(min-width: 1200px) 1100px, 100vw', ['alt' => '', 'class' => 'vembed__poster']) ?>
  <?php elseif ($meta['poster']): ?><img class="vembed__poster" src="<?= e($meta['poster']) ?>" width="<?= (int) $meta['width'] ?>" height="<?= (int) $meta['height'] ?>" alt="" loading="lazy" decoding="async">
  <?php endif; ?>
  <div class="vembed__gate">
    <p><?= e(lt('Beim Abspielen lädt {provider} ({company}) das Video. Dabei werden Daten wie Ihre IP-Adresse an {provider} übertragen.', ['provider' => $prov['label'], 'company' => $prov['company']])) ?>
      <a href="<?= e(starter_privacy_url()) ?>"><?= e(lt('Datenschutz')) ?></a></p>
    <p class="vembed__row">
      <label><input type="checkbox" data-embed-remember> <?= e(lt('{provider}-Videos künftig direkt laden', ['provider' => $prov['label']])) ?></label>
      <a href="<?= e(Embeds::watchUrl($v)) ?>" target="_blank" rel="noopener"><?= e(lt('Auf {provider} ansehen', ['provider' => $prov['label']])) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a>
    </p>
    <button type="button" class="btn btn--primary" data-embed-play><?= icon('play-circle') ?><span><?= e(lt('{provider}-Video laden', ['provider' => $prov['label']])) ?><span class="sr-only">: <?= e($title) ?> (<?= e($prov['label']) ?>)</span></span></button>
  </div>
</div>
<?php elseif (is_editing()): ?>
<div class="vembed vembed--empty"><p>YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.</p></div>
<?php endif; ?>
