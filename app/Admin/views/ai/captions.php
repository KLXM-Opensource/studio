<?php
/**
 * Bereich „KLXM AI“ → Untertitel: Videos ohne Untertitel, Audio ohne Transkript, Aufträge der KI-Transkription.
 * Knöpfe und Stand (Polling) über resources/js/_captions.js ([data-cap-queue]). Ergebnisse sind Entwürfe – geprüft wird in der Mediathek.
 */
use Core\AI\Assist;
use Core\AI\MediaJobs;
use Core\Lang;
use Core\Media;
use Core\MediaTracks;

$brand = Assist::brand();
$videos = Media::all(['nocaptions' => true]);
$audio = Media::all(['notranscript' => true]);
$drafts = [];
foreach (Media::all(['kind' => 'video']) as $m) {
    $s = MediaTracks::summary($m);
    if ($s['drafts']) $drafts[] = $m + ['_drafts' => $s['drafts']];
}
foreach (Media::all(['kind' => 'audio']) as $m) {
    $s = MediaTracks::summary($m);
    if ($s['drafts'] || $s['transcript_drafts']) $drafts[] = $m + ['_drafts' => $s['drafts'] + count($s['transcript_drafts'])];
}
$jobs = MediaJobs::list(null, 25);
$can = MediaJobs::canTranscribe();
$cfgLang = (string) (\Core\AI\Ai::config()['transcribe']['language'] ?? 'auto');
$status = ['queued' => [__('Wartet'), 'info'], 'running' => [__('Läuft'), 'info'], 'done' => [__('Fertig'), 'ok'], 'failed' => [__('Fehler'), 'error'], 'canceled' => [__('Abgebrochen'), 'info']];
$row = function (array $m, string $what) use ($can, $cfgLang): string {
    $lib = url('/admin/media') . '#c' . (int) $m['id'];
    $opts = '<option value="auto"' . ($cfgLang === 'auto' ? ' selected' : '') . '>' . e(__('Sprache erkennen')) . '</option>';
    foreach (Lang::all() as $c => $l) $opts .= '<option value="' . e($c) . '"' . ($cfgLang === $c ? ' selected' : '') . '>' . e($l) . '</option>';
    return '<li class="cap-q__item" data-cap-media="' . (int) $m['id'] . '"><span class="cap-q__kind" aria-hidden="true">' . e(Media::typeLabel((string) $m['mime'])) . '</span>'
        . '<span class="cap-q__name"><a href="' . e($lib) . '">' . e(Media::displayName($m)) . '</a><small>' . e($what) . '</small></span>'
        . ($can ? '<span class="cap-q__act"><label class="adm-sr" for="cap-l-' . (int) $m['id'] . '">' . e(__('Sprache')) . '</label><select id="cap-l-' . (int) $m['id'] . '" data-cap-lang>' . $opts . '</select>'
            . '<button type="button" class="adm-btn adm-btn--small kia-btn" data-cap-transcribe><span class="kia-spark" aria-hidden="true">✦</span> ' . e(__('Transkribieren')) . '</button></span>' : '')
        . '</li>';
};
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Untertitel')) ?></h1>
    <p class="adm-muted"><?= e(__('Videos brauchen Untertitel, Audio ein Transkript (WCAG 1.2.1–1.2.3). Die KI hört die Tonspur ab und erstellt einen Entwurf – er erscheint erst auf der Website, wenn jemand ihn in der Mediathek geprüft und veröffentlicht hat.')) ?></p></div>
</header>
<?php if (!$can): ?><p class="adm-flash adm-flash--info"><?= e(__('Die KI-Transkription ist für diese Website nicht eingerichtet (Grundeinstellungen → KI → Sprache → Text). Untertitel lassen sich in der Mediathek hochladen (.vtt/.srt) oder von Hand schreiben.')) ?></p><?php endif; ?>
<div data-cap-queue>
<section class="adm-card" aria-labelledby="cap-v-h">
  <h2 id="cap-v-h"><?= e(__('Videos ohne Untertitel')) ?> <span class="adm-badge adm-badge--muted"><?= count($videos) ?></span></h2>
  <?php if (!$videos): ?><p class="kia-chk kia-chk--ok"><?= e(__('Alle Videos haben veröffentlichte Untertitel.')) ?></p>
  <?php else: ?><ul class="cap-q"><?php foreach ($videos as $m) echo $row($m, __('keine veröffentlichten Untertitel')); ?></ul><?php endif; ?>
</section>
<?php if ($audio): ?>
<section class="adm-card" aria-labelledby="cap-a-h">
  <h2 id="cap-a-h"><?= e(__('Audio ohne Transkript')) ?> <span class="adm-badge adm-badge--muted"><?= count($audio) ?></span></h2>
  <ul class="cap-q"><?php foreach ($audio as $m) echo $row($m, __('kein veröffentlichtes Transkript')); ?></ul>
</section>
<?php endif; ?>
<?php if ($drafts): ?>
<section class="adm-card" aria-labelledby="cap-d-h">
  <h2 id="cap-d-h"><?= e(__('Entwürfe prüfen')) ?> <span class="adm-badge adm-badge--adm-warn"><?= count($drafts) ?></span></h2>
  <ul class="cap-q"><?php foreach ($drafts as $m): ?>
    <li class="cap-q__item"><span class="cap-q__kind" aria-hidden="true"><?= e(Media::typeLabel((string) $m['mime'])) ?></span>
      <span class="cap-q__name"><a href="<?= e(url('/admin/media') . '#c' . (int) $m['id']) ?>"><?= e(Media::displayName($m)) ?></a><small><?= e(__('{n} Entwurf/Entwürfe warten auf Prüfung', ['n' => $m['_drafts']])) ?></small></span>
      <span class="cap-q__act"><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/media') . '#c' . (int) $m['id']) ?>"><?= e(__('Prüfen')) ?></a></span></li>
  <?php endforeach; ?></ul>
</section>
<?php endif; ?>
<section class="adm-card" aria-labelledby="cap-j-h">
  <h2 id="cap-j-h"><?= e(__('Aufträge')) ?></h2>
  <p class="adm-muted kia-small"><?= e(__('Es läuft immer nur ein Auftrag gleichzeitig; weitere warten. Die Seite aktualisiert den Stand von selbst.')) ?></p>
  <div class="kia-tablewrap"><table class="adm-table cap-jobs">
    <thead><tr><th scope="col"><?= e(__('Datei')) ?></th><th scope="col"><?= e(__('Auftrag')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Ergebnis')) ?></th></tr></thead>
    <tbody data-cap-jobs>
    <?php if (!$jobs): ?><tr data-cap-nojobs><td colspan="4" class="adm-muted"><?= e(__('Noch keine Aufträge.')) ?></td></tr><?php endif; ?>
    <?php foreach ($jobs as $j): [$sl, $sc] = $status[$j['status']] ?? [$j['status'], 'info'];
        if ($j['pool']) Media::usePool($j['pool']);
        $jm = Media::find($j['media_id']);
        Media::usePool(null); ?>
      <tr data-cap-job="<?= (int) $j['id'] ?>" data-status="<?= e($j['status']) ?>">
        <td><?= $jm ? '<a href="' . e(url('/admin/media') . ($j['pool'] ? '' : '#c' . $j['media_id'])) . '">' . e(Media::displayName($jm)) . '</a>' : '–' ?><?= $j['pool'] ? ' <small class="adm-muted">⇄ ' . e($j['pool']) . '</small>' : '' ?></td>
        <td><?= e($j['type'] === 'translate' ? __('Übersetzen nach {lang}', ['lang' => MediaTracks::langLabel((string) $j['target'])]) : __('Transkription ({lang})', ['lang' => $j['lang'] === 'auto' ? __('automatisch') : MediaTracks::langLabel((string) $j['lang'])])) ?>
          <br><small class="adm-muted"><?= e(date('d.m.Y H:i', strtotime((string) $j['created_at']))) ?></small></td>
        <td><span class="kia-chk kia-chk--<?= e($sc) ?>" data-cap-status><?= e($sl) ?><?= in_array($j['status'], ['queued', 'running'], true) ? ' · ' . ($j['status'] === 'queued' ? e(__('Position {n}', ['n' => $j['position']])) : (int) $j['progress'] . ' %') : '' ?></span></td>
        <td data-cap-msg><?= e($j['message']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
</div>
