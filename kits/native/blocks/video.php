<?php
/**
 * Video im Kit „NativeCSS“:
 *   wide/text/cinema  eigene Videodatei im <ncss-video-player> von NativeCSS (Zeitleiste mit Kapiteln, Untertitel-Menü, Tasten,
 *                     Vollbild); YouTube/Vimeo bleiben bei der Zwei-Klick-Lösung des Kerns. Markup, Untertitel und Transkript
 *                     kommen aus dem Kern-Fragment „video-embed“ (Core\MediaTracks) – hier nur der Player darum.
 *   morph             „Video mit Morphing beim Scrollen“ (nur dieses Kit, theme.php → block_extend): eigene Datei stumm in
 *                     Schleife in .ncss-morph, Pause-Schalter und Laden wie beim Hintergrundvideo (js/video-bg.js: nie bei
 *                     „Bewegung reduzieren“/„Daten sparen“). Beim Bearbeiten und ohne eigene Datei wie „Mit Text daneben“.
 *   audio             <ncss-audio-player layout="card"> mit Cover („Vorschaubild“ poster), Kapiteln und Playlist (ab zwei
 *                     Dateien). Felder wie in fluid: Wiederholgruppe audio (file, title, artist). Kapitel und Transkript je Datei
 *                     aus der Mediathek (Core\MediaTracks): Kapitelspur der Seitensprache (sonst die erste), „Transkript anzeigen“
 *                     je Datei unter dem Player.
 * Beide Player erweitern nur ein natives <video controls>/<audio controls>: ohne JavaScript (und beim Bearbeiten) bleibt der
 * Player des Browsers. CSS/JS nur auf Seiten damit (Merkmale 'video:@player', 'video:@audio', '@morph', '@videobg').
 * @var \Core\Block $b  @var array $d
 */
use Core\Media;
use Core\MediaTracks;

if ($b->variant() === 'audio'):
    // Kapitel einer Audiodatei aus der Mediathek: veröffentlichte Spur in der Sprache der Seite, sonst die erste
    $chapters = static function (array $m): array {
        $cur = \Core\Lang::current();
        $found = null;
        foreach (MediaTracks::forMedia($m, true) as $t) {
            if ($t['kind'] !== 'chapters' || !($url = MediaTracks::url($m, $t))) continue;
            if ($found === null || ($t['lang'] === $cur && $found['lang'] !== $cur)) $found = ['url' => $url, 'lang' => (string) $t['lang']];
        }
        return $found ?? ['url' => '', 'lang' => ''];
    };
    $tracks = [];
    foreach ((array) ($d['audio'] ?? []) as $it) {
        $m = !empty($it['file']) ? media((int) $it['file']) : null;
        if (!$m || !str_starts_with((string) $m['mime'], 'audio/')) continue;
        $ch = MediaTracks::enabled() ? $chapters($m) : ['url' => '', 'lang' => ''];
        $tracks[] = ['m' => $m, 'url' => Media::url($m), 'title' => trim((string) ($it['title'] ?? '')) ?: Media::displayName($m),
            'artist' => trim((string) ($it['artist'] ?? '')), 'chapters' => $ch['url'], 'chapters_lang' => $ch['lang']];
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
      <audio controls preload="metadata" src="<?= e($first['url']) ?>"><?= $first['chapters'] !== '' ? '<track kind="chapters" src="' . e($first['chapters']) . '" srclang="' . e($first['chapters_lang']) . '">' : '' ?></audio>
      <?php if (count($tracks) > 1): ?>
      <ol class="ncss-audio-player-playlist">
        <?php foreach ($tracks as $t): ?>
        <li><a href="<?= e($t['url']) ?>" data-title="<?= e($t['title']) ?>" data-artist="<?= e($t['artist']) ?>"<?= $coverUrl !== '' ? ' data-cover="' . e($coverUrl) . '"' : '' ?><?= $t['chapters'] !== '' ? ' data-chapters="' . e($t['chapters']) . '"' : '' ?>><?= e($t['title']) ?></a></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </ncss-audio-player>
    <?php foreach ($tracks as $t): ?><?= MediaTracks::transcriptHtml($t['m']) ?><?php endforeach; /* Transkript je Datei (Mediathek) */ ?>
    <?php if (trim((string) $d['caption']) !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif; ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Audiodatei – in der Seitenleiste unter „Audiodateien“ hinzufügen.</p><?php endif; ?>
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
