<?php
/**
 * Kern-Fragment „video-embed“ (nur Kern – Kits können es nicht ersetzen, Core\Fragments::CORE_ONLY).
 *
 * Video mit Zwei-Klick-Lösung: YouTube/Vimeo zeigen ein lokales Vorschaubild (Core\Embeds, Server-Proxy); der Player
 * (youtube-nocookie / dnt=1) lädt erst nach Klick – resources/js/embed.js. „Künftig direkt laden“ speichert der Browser
 * (localStorage, kein Cookie) oder die Einwilligungs-Verwaltung (window.cmsConsent, Ereignis cms:consent).
 * MP4 aus der Mediathek: <video> mit Untertiteln (<track>) und Transkript (Core\MediaTracks), Vorschaubild: eigenes
 * Poster → sonst Media::posterFor() (Hook „mediaPoster“, z. B. Erweiterung video_tools).
 *
 * Pflichtteile, die kein Kit entfernen kann: Hinweistext mit Anbieter, Link zur Datenschutzerklärung, „künftig direkt
 * laden“, „Auf … ansehen“ – alle im Fluss VOR der Schaltfläche „{Anbieter}-Video laden“. Aussehen nur per CSS:
 *   .vembed (+ .r-16-9 / .r-4-3 / .r-21-9, .is-loaded, .vembed--empty) · .vembed__poster · .vembed__gate · .vembed__info
 *   .vembed__title · .vembed__text · .vembed__row · .vembed__remember · .vembed__ext · .vembed__play · .vembed__icon
 *   .vembed__frame (iframe nach dem Laden) · .vembed__revoke (Hinweis „direkt geladen“ + „Freigabe widerrufen“)
 * Damit der Hinweis nie abgeschnitten wird: Seitenverhältnis nur als Mindesthöhe, z. B.
 *   .vembed[data-embed]:not(.is-loaded){display:flex;flex-direction:column;overflow:clip}
 *   .vembed[data-embed]:not(.is-loaded) .vembed__gate{position:relative;flex:1 0 auto}
 *
 * Optionen des Kits (theme.php → 'fragments' → 'video-embed'):
 *   ratio_class  Präfix der Format-Klasse (Standard 'r-' → r-16-9; z. B. 'ratio-')
 *   notice       'short' (Standard) | 'detailed' (ausführlicher Hinweis inkl. Verarbeitung außerhalb der EU)
 *   title        true = Videotitel über dem Hinweis (.vembed__title)
 *   empty        leerer Block für Besucher: 'box' (Standard, leerer Kasten im Format) | 'placeholder' („[Video folgt]“) | 'none'
 *   play_class   zusätzliche Klassen der Schaltfläche (z. B. 'btn btn--primary')
 *   play_icon    Symbolname (icon()) statt des Standard-Dreiecks; icon_size = Größe des Dreiecks (Standard 22)
 *   poster_crop  true = eigenes Vorschaubild auf das Format zuschneiden
 *
 * @var string $url  @var ?int $file  @var ?int $poster  @var string $ratio 16-9|4-3|21-9  @var string $label  Titel für Screenreader
 * @var string $note  eigener Hinweistext der Redaktion (ersetzt nur den erklärenden Satz – Datenschutz-Link bleibt)
 * @var array $options
 */
use Core\Embeds;
use Core\Media;

$o = ($options ?? []) + ['ratio_class' => 'r-', 'notice' => 'short', 'title' => false, 'empty' => 'box',
    'play_class' => '', 'play_icon' => '', 'icon_size' => 22, 'poster_crop' => false];
$ratio = in_array($ratio ?? '', ['16-9', '4-3', '21-9'], true) ? $ratio : '16-9';
$rc = preg_replace('~[^a-z0-9_-]~i', '', (string) $o['ratio_class']);
$rcls = $rc !== '' ? ' ' . $rc . $ratio : '';
$v = Embeds::parse((string) ($url ?? ''));
$mp4 = !$v && !empty($file) ? media((int) $file) : null;
$own = !empty($poster) ? media((int) $poster) : ($mp4 ? Media::posterFor($mp4) : null);   // ohne eigenes Poster: Vorschaubild einer Erweiterung (z. B. video_tools)
$label = trim((string) ($label ?? ''));
$note = trim((string) ($note ?? ''));
?>
<?php if ($mp4): ?>
<div class="vembed<?= e($rcls) ?>">
  <video controls preload="none" playsinline<?= $own ? ' poster="' . e(Media::url($own, 1200)) . '"' : '' ?>><source src="<?= e(Media::url($mp4)) ?>" type="video/mp4"><?= \Core\MediaTracks::trackTags($mp4) ?></video>
</div>
<?= \Core\MediaTracks::transcriptHtml($mp4) /* Untertitel oben als <track>, Transkript als <details> (Core\MediaTracks) */ ?>
<?php elseif ($v):
    $meta = Embeds::meta($v);
    $prov = Embeds::PROVIDERS[$v['provider']];
    $title = $label !== '' ? $label : ($meta['title'] ?: lt('Video'));
    $privacy = '<a href="' . e(privacy_url()) . '">' . e(lt($o['notice'] === 'detailed' ? 'Datenschutzerklärung' : 'Datenschutz')) . '</a>';
    $text = $note !== '' ? e($note) : e($o['notice'] === 'detailed'
        ? lt('Das Video wird von {provider} ({company}) bereitgestellt. Erst beim Abspielen werden Daten wie Ihre IP-Adresse an {provider} übertragen und dort ggf. auch außerhalb der EU verarbeitet.', ['provider' => $prov['label'], 'company' => $prov['company']])
        : lt('Beim Abspielen lädt {provider} ({company}) das Video. Dabei werden Daten wie Ihre IP-Adresse an {provider} übertragen.', ['provider' => $prov['label'], 'company' => $prov['company']]));
    $more = $o['notice'] === 'detailed' ? strtr(e(lt('Mehr in der {link}.')), ['{link}' => $privacy]) : $privacy;
    $icon = (string) $o['play_icon'] !== '' ? icon((string) $o['play_icon'])
        : '<span class="vembed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="' . (int) $o['icon_size'] . '" height="' . (int) $o['icon_size'] . '"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>';
?>
<div class="vembed<?= e($rcls) ?>" data-embed="<?= e($v['provider']) ?>" data-label="<?= e($prov['label']) ?>" data-src="<?= e(Embeds::playerUrl($v)) ?>" data-title="<?= e($title) ?>"
     data-t-revoke="<?= e(lt('Freigabe widerrufen')) ?>" data-t-direct="<?= e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $prov['label']])) ?>">
  <?php if ($own): ?><?= img((int) $poster ?: null, '(min-width: 1080px) 1200px, 100vw', ['alt' => '', 'class' => 'vembed__poster'] + ($o['poster_crop'] ? ['ratio' => $ratio] : [])) ?>
  <?php elseif ($meta['poster']): ?><img class="vembed__poster" src="<?= e($meta['poster']) ?>" srcset="<?= e($meta['poster_small']) ?> 640w, <?= e($meta['poster']) ?> 1280w" sizes="(min-width: 1080px) 1200px, 100vw" width="<?= (int) $meta['width'] ?>" height="<?= (int) $meta['height'] ?>" alt="" loading="lazy" decoding="async">
  <?php endif; ?>
  <div class="vembed__gate">
    <div class="vembed__info">
      <?php if ($o['title'] && ($meta['title'] || $label !== '')): ?><p class="vembed__title"><?= e($title) ?></p><?php endif; ?>
      <p class="vembed__text"><?= $text ?> <?= $more ?></p>
      <p class="vembed__row">
        <label class="vembed__remember"><input type="checkbox" data-embed-remember> <?= e(lt('{provider}-Videos künftig direkt laden', ['provider' => $prov['label']])) ?></label>
        <a class="vembed__ext" href="<?= e(Embeds::watchUrl($v)) ?>" target="_blank" rel="noopener"><?= e(lt('Auf {provider} ansehen', ['provider' => $prov['label']])) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a>
      </p>
    </div>
    <button type="button" class="vembed__play<?= (string) $o['play_class'] !== '' ? ' ' . e((string) $o['play_class']) : '' ?>" data-embed-play>
      <?= $icon ?>
      <span><?= e(lt('{provider}-Video laden', ['provider' => $prov['label']])) ?><span class="sr-only">: <?= e($title) ?> (<?= e($prov['label']) ?>)</span></span>
    </button>
  </div>
</div>
<?= \Core\Embeds::script() /* resources/js/embed.js – einmal je Seite, nicht im Bearbeiten-Modus */ ?>
<?php elseif (is_editing() || $o['empty'] !== 'none'):
    $ph = $o['empty'] === 'placeholder';
    $hint = is_editing() ? e(__('YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.')) : '';
?>
<div class="vembed vembed--empty<?= e($rcls) ?>"><p><?= $ph ? e(lt('[Video folgt]')) . ($hint !== '' ? '<br><small>' . $hint . '</small>' : '') : $hint ?></p></div>
<?php endif; ?>
