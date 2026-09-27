<?php
/**
 * Video mit Zwei-Klick-Lösung (DSGVO).
 * YouTube/Vimeo: lokales Vorschaubild (Server-Proxy), Player erst nach Einwilligung.
 * MP4 aus der Mediathek: direkt, ohne Einwilligung.
 *
 * @var string $url  YouTube-/Vimeo-Link  @var ?int $file  MP4 (Media-ID)  @var ?int $poster  eigenes Vorschaubild
 * @var string $ratio  16-9 | 4-3  @var string $label  Titel für Screenreader  @var string $note  eigener Hinweistext
 */
use Core\Embeds;
use Core\Media;

$ratio = in_array($ratio ?? '', ['16-9', '4-3'], true) ? $ratio : '16-9';
$v = Embeds::parse($url ?? '');
$mp4 = !$v && !empty($file) ? media((int) $file) : null;
$own = !empty($poster) ? media((int) $poster) : ($mp4 ? Media::posterFor($mp4) : null);   // ohne eigenes Poster: Vorschaubild einer Erweiterung (z. B. video_tools)
?>
<?php if ($mp4): ?>
<div class="vembed ratio-<?= e($ratio) ?>">
  <video controls preload="none" playsinline<?= $own ? ' poster="' . e(Media::url($own, 1200)) . '"' : '' ?>>
    <source src="<?= e(Media::url($mp4)) ?>" type="video/mp4">
    <?= \Core\MediaTracks::trackTags($mp4) /* Untertitel/Kapitel (nur veröffentlichte, Standard = Sprache der Seite) */ ?>
  </video>
</div>
<?= \Core\MediaTracks::transcriptHtml($mp4) /* „Transkript anzeigen“ (<details>), nur mit veröffentlichtem Transkript */ ?>
<?php elseif ($v):
    $meta = Embeds::meta($v);
    $prov = Embeds::PROVIDERS[$v['provider']];
    $title = $label ?: ($meta['title'] ?: lt('Video'));
?>
<div class="vembed ratio-<?= e($ratio) ?>" data-embed="<?= e($v['provider']) ?>" data-label="<?= e($prov['label']) ?>" data-src="<?= e(Embeds::playerUrl($v)) ?>" data-title="<?= e($title) ?>" data-t-revoke="<?= e(lt('Freigabe widerrufen')) ?>" data-t-direct="<?= e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $prov['label']])) ?>">
  <?php if ($own): ?>
    <?= img((int) $poster, '(min-width: 1080px) 1200px, 100vw', ['alt' => '', 'class' => 'vembed__poster', 'ratio' => $ratio]) ?>
  <?php elseif ($meta['poster']): ?>
    <img class="vembed__poster" src="<?= e($meta['poster']) ?>" srcset="<?= e($meta['poster_small']) ?> 640w, <?= e($meta['poster']) ?> 1280w"
         sizes="(min-width: 1080px) 1200px, 100vw" width="<?= $meta['width'] ?>" height="<?= $meta['height'] ?>" alt="" loading="lazy" decoding="async">
  <?php endif; ?>
  <div class="vembed__gate">
    <div class="vembed__info">
      <?php if ($meta['title'] || $label): ?><p class="vembed__title"><?= e($title) ?></p><?php endif; ?>
      <p class="vembed__text"><?= e(!empty($note) ? $note : lt('Das Video wird von {provider} ({company}) bereitgestellt. Erst beim Abspielen werden Daten wie Ihre IP-Adresse an {provider} übertragen und dort ggf. auch außerhalb der EU verarbeitet.', ['provider' => $prov['label'], 'company' => $prov['company']])) ?>
        <?= praxis_fill(lt('Mehr in der {link}.'), ['link' => '<a href="' . e(praxis_privacy_url()) . '">' . e(lt('Datenschutzerklärung')) . '</a>']) ?></p>
      <div class="vembed__row">
        <label class="vembed__remember"><input type="checkbox" data-embed-remember> <?= e(lt('{provider}-Videos künftig direkt laden', ['provider' => $prov['label']])) ?></label>
        <a class="vembed__ext" href="<?= e(Embeds::watchUrl($v)) ?>" target="_blank" rel="noopener"><?= e(lt('Auf {provider} ansehen', ['provider' => $prov['label']])) ?> ↗</a>
      </div>
    </div>
    <button type="button" class="vembed__play" data-embed-play>
      <span class="vembed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>
      <span><?= e(lt('{provider}-Video laden', ['provider' => $prov['label']])) ?><span class="sr-only">: <?= e($title) ?></span></span>
    </button>
  </div>
</div>
<?php else: ?>
<div class="vembed vembed--empty ratio-<?= e($ratio) ?>"><p><?= e(lt('[Video folgt]')) ?><?= is_editing() ? '<br><small>' . e(__('YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.')) . '</small>' : '' ?></p></div>
<?php endif; ?>
