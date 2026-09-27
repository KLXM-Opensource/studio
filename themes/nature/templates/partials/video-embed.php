<?php
/**
 * Video mit Zwei-Klick-Lösung: YouTube/Vimeo zeigen ein lokales Vorschaubild (Core\Embeds, Server-Proxy);
 * der Player lädt erst nach Klick (youtube-nocookie / dnt=1). MP4 aus der Mediathek läuft direkt.
 * @var string $url  @var ?int $file  @var ?int $poster  @var string $ratio 16-9|4-3  @var string $label
 */
use Core\Embeds;
use Core\Media;

$ratio = in_array($ratio ?? '', ['16-9', '4-3', '21-9'], true) ? $ratio : '16-9';
$v = Embeds::parse($url ?? '');
$mp4 = !$v && !empty($file) ? media((int) $file) : null;
$own = !empty($poster) ? media((int) $poster) : ($mp4 ? Media::posterFor($mp4) : null);   // ohne eigenes Poster: Vorschaubild einer Erweiterung (z. B. video_tools)
?>
<?php if ($mp4): ?>
<div class="vembed r-<?= e($ratio) ?>">
  <video controls preload="none" playsinline<?= $own ? ' poster="' . e(Media::url($own, 1200)) . '"' : '' ?>><source src="<?= e(Media::url($mp4)) ?>" type="video/mp4"><?= \Core\MediaTracks::trackTags($mp4) ?></video>
</div>
<?= \Core\MediaTracks::transcriptHtml($mp4) /* Untertitel oben als <track>, Transkript als <details> (Core\MediaTracks) */ ?>
<?php elseif ($v):
    $meta = Embeds::meta($v);
    $prov = Embeds::PROVIDERS[$v['provider']];
    $title = ($label ?? '') !== '' ? $label : ($meta['title'] ?: lt('Video'));
?>
<div class="vembed r-<?= e($ratio) ?>" data-embed="<?= e($v['provider']) ?>" data-src="<?= e(Embeds::playerUrl($v)) ?>" data-title="<?= e($title) ?>"
     data-t-revoke="<?= e(lt('Freigabe widerrufen')) ?>" data-t-direct="<?= e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $prov['label']])) ?>">
  <?php if ($own): ?><?= img((int) $poster, '(min-width: 1080px) 1200px, 100vw', ['alt' => '', 'class' => 'vembed__poster']) ?>
  <?php elseif ($meta['poster']): ?><img class="vembed__poster" src="<?= e($meta['poster']) ?>" srcset="<?= e($meta['poster_small']) ?> 640w, <?= e($meta['poster']) ?> 1280w" sizes="(min-width: 1080px) 1200px, 100vw" width="<?= (int) $meta['width'] ?>" height="<?= (int) $meta['height'] ?>" alt="" loading="lazy" decoding="async">
  <?php endif; ?>
  <div class="vembed__gate">
    <div class="vembed__info">
      <p><?= e(lt('Beim Abspielen lädt {provider} ({company}) das Video. Dabei werden Daten wie Ihre IP-Adresse an {provider} übertragen.', ['provider' => $prov['label'], 'company' => $prov['company']])) ?>
        <a href="<?= e(nature_privacy_url()) ?>"><?= e(lt('Datenschutz')) ?></a></p>
      <p class="vembed__row">
        <label class="vembed__remember"><input type="checkbox" data-embed-remember> <?= e(lt('{provider}-Videos künftig direkt laden', ['provider' => $prov['label']])) ?></label>
        <a class="vembed__ext" href="<?= e(Embeds::watchUrl($v)) ?>" target="_blank" rel="noopener"><?= e(lt('Auf {provider} ansehen', ['provider' => $prov['label']])) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a>
      </p>
    </div>
    <button type="button" class="vembed__play" data-embed-play>
      <span class="vembed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>
      <span><?= e(lt('{provider}-Video laden', ['provider' => $prov['label']])) ?><span class="sr-only">: <?= e($title) ?> (<?= e($prov['label']) ?>)</span></span>
    </button>
  </div>
</div>
<?php else: ?>
<div class="vembed vembed--empty r-<?= e($ratio) ?>"><p><?= is_editing() ? e('YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.') : '' ?></p></div>
<?php endif; ?>
