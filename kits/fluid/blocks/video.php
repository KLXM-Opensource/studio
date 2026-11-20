<?php
/**
 * Video (YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 direkt mit Untertiteln und Transkript aus der Mediathek):
 * wide · text (Text daneben, Switcher) · cinema (randlos auf dunkler Fläche).
 * audio: Audiodateien der Mediathek mit dem Player des Browsers (<audio controls>), ab zwei Dateien als Liste (Playlist);
 * Kapitel, Untertitel und Transkript je Datei aus der Mediathek (Core\MediaTracks), Cover = „Vorschaubild“ (css/b-audio.css).
 * @var \Core\Block $b  @var array $d
 */
if ($b->variant() === 'audio'):
    $tracks = [];
    foreach ((array) ($d['audio'] ?? []) as $it) {
        $m = media((int) ($it['file'] ?? 0));
        if (!$m || !str_starts_with((string) $m['mime'], 'audio/')) continue;
        $tracks[] = ['m' => $m, 'title' => trim((string) ($it['title'] ?? '')) ?: \Core\Media::displayName($m), 'artist' => trim((string) ($it['artist'] ?? ''))];
    }
    $cover = !empty($d['poster']) ? img((int) $d['poster'], '160px', ['alt' => '', 'ratio' => '1:1']) : '';
?>
<div class="wrap audio-block">
  <?= fluid_head($b) ?>
  <?php if ($tracks): ?>
  <figure class="audio-card">
    <?php if ($cover !== ''): ?><div class="audio-card__cover"><?= $cover ?></div><?php endif; ?>
    <ol class="audio-card__list" role="list">
      <?php foreach ($tracks as $i => $t): ?>
      <li class="audio-card__item">
        <p class="audio-card__title"><?= e($t['title']) ?><?php if ($t['artist'] !== ''): ?> <span class="audio-card__artist">· <?= e($t['artist']) ?></span><?php endif; ?></p>
        <audio controls preload="<?= $i === 0 ? 'metadata' : 'none' ?>" aria-label="<?= e($t['title']) ?>"><source src="<?= e(\Core\Media::url($t['m'])) ?>" type="<?= e((string) $t['m']['mime']) ?>"><?= \Core\MediaTracks::trackTags($t['m']) ?></audio>
        <?= \Core\MediaTracks::transcriptHtml($t['m']) ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php if (trim((string) ($d['caption'] ?? '')) !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) ($d['caption'] ?? '')) ?></figcaption><?php endif; ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Audiodatei – in der Seitenleiste unter „Audiodateien“ hinzufügen.</p><?php endif; ?>
</div>
<?php return; endif;
// „morph“ (Kind-Kit, z. B. Morphing beim Scrollen) nach einem Kit-Wechsel: wie „Mit Text daneben“; sonst Unbekanntes wie „Breit“
$v = $b->variant() === 'morph' ? 'text' : (in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide');
$embed = app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
    'ratio' => $d['ratio'] ?: '16-9', 'label' => strip_emphasis(trim((string) $d['title']))]);
$fig = '<figure class="video">' . $embed
    . ((trim((string) $d['caption']) !== '' || is_editing()) ? '<figcaption class="video__cap"' . $b->edit('caption') . '>' . fluid_title((string) $d['caption']) . '</figcaption>' : '')
    . '</figure>';
?>
<?php if ($v === 'text'): ?>
<div class="wrap video-split">
  <div class="video-split__text">
    <?= fluid_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?= $fig ?>
</div>
<?php else: ?>
<div class="wrap<?= $v === 'cinema' ? ' video-cinema' : '' ?>">
  <?= fluid_head($b, $v === 'cinema' ? 'sec-head--center' : '') ?>
  <?= $fig ?>
</div>
<?php endif;
