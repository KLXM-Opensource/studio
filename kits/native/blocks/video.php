<?php
/**
 * Video im Kit „NativeCSS“:
 *   wide/text/cinema  eigene Videodatei im <ncss-video-player> von NativeCSS (Zeitleiste mit Kapiteln, Untertitel-Menü, Tasten,
 *                     Vollbild); YouTube/Vimeo bleiben bei der Zwei-Klick-Lösung des Kerns. Markup, Untertitel und Transkript
 *                     kommen aus dem Kern-Fragment „video-embed“ (Core\MediaTracks) – hier nur der Player darum.
 *   morph             „Video mit Morphing beim Scrollen“ (nur dieses Kit, theme.php → block_extend): eigene Datei stumm in
 *                     Schleife in .ncss-morph, Pause-Schalter und Laden wie beim Hintergrundvideo (js/video-bg.js: nie bei
 *                     „Bewegung reduzieren“/„Daten sparen“). Beim Bearbeiten und ohne eigene Datei wie „Mit Text daneben“.
 *   audio             <ncss-audio-player layout="card"> mit Cover, Kapiteln und Playlist (ab zwei Dateien) – sobald der Block
 *                     die Variante hat (Felder audio: file, title, artist, chapters; audio_transcript).
 * Beide Player erweitern nur ein natives <video controls>/<audio controls>: ohne JavaScript (und beim Bearbeiten) bleibt der
 * Player des Browsers. CSS/JS nur auf Seiten damit (Merkmale 'video:@player', 'video:@audio', '@morph', '@videobg').
 * @var \Core\Block $b  @var array $d
 */
use Core\Media;

if ($b->variant() === 'audio'):
    $tracks = [];
    foreach ((array) ($d['audio'] ?? []) as $it) {
        $m = !empty($it['file']) ? media((int) $it['file']) : null;
        if (!$m || !str_starts_with((string) $m['mime'], 'audio/')) continue;
        $ch = !empty($it['chapters']) ? media((int) $it['chapters']) : null;
        $tracks[] = ['url' => Media::url($m), 'title' => trim((string) ($it['title'] ?? '')) ?: (string) ($m['title'] ?? ''),
            'artist' => trim((string) ($it['artist'] ?? '')), 'chapters' => $ch ? Media::url($ch) : ''];
    }
    $cover = !empty($d['poster']) ? media((int) $d['poster']) : null;
    $coverUrl = $cover ? Media::url($cover, 600) : '';
    $first = $tracks[0] ?? null;
    if ($first && !is_editing()) native_mark('video:@audio');
?>
<div class="wrap nv-audio">
  <?= fluid_head($b) ?>
  <?php if ($first): ?>
  <figure class="nv-audio__fig">
    <ncss-audio-player layout="card"<?= trim((string) $d['title']) !== '' ? ' aria-label="' . e(strip_emphasis(trim((string) $d['title']))) . '"' : '' ?>>
      <?php if ($coverUrl !== ''): ?><img class="ncss-audio-player-cover" src="<?= e($coverUrl) ?>" alt=""><?php endif; ?>
      <p class="ncss-audio-player-title"><?= e($first['title']) ?></p>
      <?php if ($first['artist'] !== '' || count($tracks) > 1): ?><p class="ncss-audio-player-artist"><?= e($first['artist']) ?></p><?php endif; ?>
      <audio controls preload="metadata" src="<?= e($first['url']) ?>"><?= $first['chapters'] !== '' ? '<track kind="chapters" src="' . e($first['chapters']) . '" srclang="' . e(\Core\Lang::current()) . '">' : '' ?></audio>
      <?php if (count($tracks) > 1): ?>
      <ol class="ncss-audio-player-playlist">
        <?php foreach ($tracks as $t): ?>
        <li><a href="<?= e($t['url']) ?>" data-title="<?= e($t['title']) ?>" data-artist="<?= e($t['artist']) ?>"<?= $coverUrl !== '' ? ' data-cover="' . e($coverUrl) . '"' : '' ?><?= $t['chapters'] !== '' ? ' data-chapters="' . e($t['chapters']) . '"' : '' ?>><?= e($t['title']) ?></a></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </ncss-audio-player>
    <?php if (trim(strip_tags((string) ($d['audio_transcript'] ?? ''))) !== ''): ?>
    <details class="media-transcript"><summary><?= e(lt('Text zum Audio')) ?></summary><div class="prose"><?= rich((string) $d['audio_transcript']) ?></div></details>
    <?php endif; ?>
    <?php if (trim((string) $d['caption']) !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif; ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Audiodatei – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
<?php return; endif;

// Morphing: eigene Datei stumm in Schleife, nur für Besucher
if ($b->variant() === 'morph' && !is_editing()) {
    $mv = !empty($d['video_file']) ? media((int) $d['video_file']) : null;
    if ($mv && str_starts_with((string) $mv['mime'], 'video/')) {
        native_mark('@morph', 'video:@morph', '@videobg');
        $poster = !empty($d['poster']) ? media((int) $d['poster']) : Media::posterFor($mv);
        echo '<div class="wrap nv-morph"><div class="ncss-morph ncss-morph--card"><div class="ncss-morph-stage">'
            . '<div class="ncss-morph-media" data-ncss-video-bg><video muted loop playsinline preload="none" aria-hidden="true"' . ($poster ? ' poster="' . e(Media::url($poster, 1200)) . '"' : '') . '>'
            . '<source data-src="' . e(Media::url($mv)) . '" type="' . e((string) $mv['mime']) . '"></video></div>'
            . '<div class="ncss-morph-content">' . fluid_head($b)
            . (trim(strip_tags((string) $d['text'])) !== '' ? '<div class="prose">' . rich((string) $d['text']) . '</div>' : '')
            . (trim((string) $d['caption']) !== '' ? '<p class="video__cap">' . fluid_title((string) $d['caption']) . '</p>' : '')
            . '</div></div></div></div>';
        return;
    }
}

// Video: Ausgabe des Kerns; eine eigene Videodatei bekommt den Player von NativeCSS (ohne die Hülle .vembed – deren CSS legt das
// Video absolut in einen Rahmen, der Player bringt sein eigenes Format mit)
$embed = app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
    'ratio' => $d['ratio'] ?: '16-9', 'label' => strip_emphasis(trim((string) $d['title']))]);
if (!is_editing()) {
    $embed = (string) preg_replace('~<div class="vembed[^"]*">\s*(<video\b.*?</video>)\s*</div>~s', '<div class="nv-vplayer"><ncss-video-player>$1</ncss-video-player></div>', $embed, 1);
    if (str_contains($embed, '<ncss-video-player>')) native_mark('video:@player');
}
$v = $b->variant() === 'morph' ? 'text' : (in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide');
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
