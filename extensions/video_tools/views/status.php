<?php
/**
 * Verwaltung → Video-Werkzeuge: Zustand von ffmpeg/ffprobe, Einrichtung (Plesk), Grenzen, Presets, Aufträge.
 * @var array $status  @var array $jobs  @var array $presets  @var bool $admin
 */
use Klxm\VideoTools\VideoTools;

$s = $status;
$c = $s['config'];
$st = ['queued' => __('wartet'), 'running' => __('läuft'), 'done' => __('fertig'), 'failed' => __('fehlgeschlagen'), 'canceled' => __('abgebrochen')];
?>
<div class="vt-status">
  <header>
    <h1><?= e(__('Video-Werkzeuge')) ?></h1>
    <p class="adm-muted"><?= e(__('Videos der Mediathek prüfen, fürs Web optimieren, schneiden und mit Poster versehen. Die Arbeit erledigt ffmpeg im Hintergrund – die Aktionen finden Sie in der Mediathek rechts bei jedem Video.')) ?>
      <a href="<?= e(url('/admin/media')) ?>"><?= e(__('Zur Mediathek')) ?></a> · <a href="<?= e(url('/admin/hilfe#video-tools')) ?>"><?= e(__('Handbuch')) ?></a></p>
  </header>

  <div class="vt-cards">
    <section class="vt-card" aria-labelledby="vt-s1">
      <h2 id="vt-s1"><?= VideoTools::icon('film-strip') ?> <?= e(__('Status')) ?></h2>
      <?php if ($s['available']): ?>
      <div class="vt-state is-ok" role="status"><?= icon('check-circle') ?><div><strong><?= e(__('Bereit.')) ?></strong> <?= e(__('ffmpeg und ffprobe wurden gefunden; Aufträge laufen im Hintergrund.')) ?></div></div>
      <?php else: ?>
      <div class="vt-state is-warn" role="status"><?= icon('warning') ?><div><strong><?= e(__('Eingeschränkt.')) ?></strong>
        <?php if (!$s['exec']): ?><?= e(__('PHP darf keine Programme starten (proc_open ist in disable_functions gesperrt).')) ?>
        <?php elseif ($s['ffmpeg_bad'] || $s['ffprobe_bad']): ?><?= e(__('Der konfigurierte Pfad zu ffmpeg bzw. ffprobe stimmt nicht.')) ?>
        <?php else: ?><?= e(__('ffmpeg/ffprobe wurden auf diesem Server nicht gefunden.')) ?><?php endif; ?>
        <?php if (!empty($s['hint'])): ?><?= e($s['hint']) ?><?php endif; ?>
        <?= e(__('Die Mediathek zeigt weiterhin die Analyse aus dem Dateikopf (Auflösung, Codec, Dauer, faststart), und Poster lassen sich aus dem Browser setzen. Optimieren und Schneiden sind erst nach der Einrichtung möglich.')) ?></div></div>
      <?php endif; ?>
      <dl class="vt-kv">
        <dt>ffmpeg</dt><dd><?= $s['ffmpeg'] !== '' ? '<code>' . e($s['ffmpeg']) . '</code>' : '<b>' . e(__('nicht gefunden')) . '</b>' ?><?= $s['ffmpeg_bad'] ? ' · ' . e(__('konfiguriert: {p}', ['p' => $s['ffmpeg_cfg']])) : '' ?></dd>
        <dt>ffprobe</dt><dd><?= $s['ffprobe'] !== '' ? '<code>' . e($s['ffprobe']) . '</code>' : '<b>' . e(__('nicht gefunden')) . '</b>' ?><?= $s['ffprobe_bad'] ? ' · ' . e(__('konfiguriert: {p}', ['p' => $s['ffprobe_cfg']])) : '' ?></dd>
        <?php if ($s['version'] !== ''): ?><dt><?= e(__('Version')) ?></dt><dd><?= e($s['version']) ?></dd><?php endif; ?>
        <dt>proc_open</dt><dd><?= e($s['exec'] ? __('erlaubt') : __('gesperrt')) ?></dd>
        <?php if (!empty($s['open_basedir'])): ?><dt>open_basedir</dt><dd><code><?= e($s['open_basedir']) ?></code> <small>(<?= e(__('Erkennung per Aufruf – /usr/bin muss nicht enthalten sein')) ?>)</small></dd><?php endif; ?>
        <?php if ($s['available']): ?>
        <dt>H.264 (libx264)</dt><dd><?= e($s['x264'] ? __('vorhanden') : __('fehlt – Presets mit Neukodierung schlagen fehl')) ?></dd>
        <dt>WebM (VP9/Opus)</dt><dd><?= e($s['webm'] ? __('vorhanden') : __('nicht verfügbar (optional)')) ?></dd>
        <?php endif; ?>
        <dt>nice</dt><dd><?= e($s['nice'] ? __('Priorität {n} (niedrig)', ['n' => $c['nice']]) : __('nicht vorhanden – normale Priorität')) ?></dd>
      </dl>
    </section>

    <section class="vt-card" aria-labelledby="vt-s2">
      <h2 id="vt-s2"><?= icon('gear-six') ?> <?= e(__('Grenzen & Betrieb')) ?></h2>
      <dl class="vt-kv">
        <dt><?= e(__('Gleichzeitig')) ?></dt><dd><?= e(__('{n} Auftrag/Aufträge (installationsweit)', ['n' => $c['max_jobs']])) ?></dd>
        <dt><?= e(__('Zeitlimit')) ?></dt><dd><?= e(__('{n} Minuten je Auftrag', ['n' => (int) round($c['timeout'] / 60)])) ?></dd>
        <dt><?= e(__('Eingang max.')) ?></dt><dd><?= (int) $c['max_input_mb'] ?> MB</dd>
        <dt><?= e(__('Ergebnis max.')) ?></dt><dd><?= e(__('{n} MB (Upload-Grenze der Mediathek, media.max_upload_mb)', ['n' => $s['max_upload_mb']])) ?></dd>
        <dt><?= e(__('Freier Speicher')) ?></dt><dd><?= $s['free_mb'] !== null ? e(number_format($s['free_mb'], 0, ',', '.') . ' MB') : '–' ?> <small>(<?= e(__('mindestens {n} MB frei halten', ['n' => $c['min_free_mb']])) ?>)</small></dd>
        <dt><?= e(__('Animierte Vorschau')) ?></dt><dd><?= e($c['preview'] ? __('an (bei Bedarf erzeugt)') : __('aus')) ?></dd>
      </dl>
      <?php if ($admin): ?>
      <p><?= e(__('Cron (empfohlen, jede Minute) – startet wartende Aufträge, falls der Start aus der Verwaltung nicht klappt:')) ?></p>
      <pre><code>* * * * * <?= e($s['php']) ?> <?= e(ROOT) ?>/bin/console video:work --site=<?= e(site()->key) ?> --all</code></pre>
      <?php endif; ?>
    </section>
  </div>

  <?php if (!$s['available'] && $admin): ?>
  <section class="vt-card" aria-labelledby="vt-s3">
    <h2 id="vt-s3"><?= icon('info') ?> <?= e(__('ffmpeg einrichten')) ?></h2>
    <ol>
      <li><b><?= e(__('Paketverwaltung (Root/Plesk-Administrator):')) ?></b> <?= e(__('Debian/Ubuntu:')) ?> <code>apt install ffmpeg</code> · <?= e(__('AlmaLinux/Rocky (EPEL + RPM Fusion):')) ?> <code>dnf install ffmpeg</code>. <?= e(__('Die Programme liegen dann unter /usr/bin und werden automatisch gefunden.')) ?></li>
      <li><b><?= e(__('Ohne Root (Plesk-Abonnement):')) ?></b> <?= e(__('statisches Build (z. B. johnvansickle.com/ffmpeg oder BtbN/FFmpeg-Builds, Linux x86_64/arm64) herunterladen und ffmpeg + ffprobe nach storage/video/bin/ legen, ausführbar machen (chmod 755). Der Ordner wird automatisch durchsucht und liegt außerhalb des Webroots.')) ?></li>
      <li><b><?= e(__('Eigener Pfad:')) ?></b> <?= e(__('in config/config.local.php bzw. config/sites/{key}.php:')) ?>
        <pre><code>'video_tools' =&gt; ['ffmpeg' =&gt; '/opt/ffmpeg/ffmpeg', 'ffprobe' =&gt; '/opt/ffmpeg/ffprobe'],</code></pre></li>
      <li><?= e(__('PHP muss Programme starten dürfen: in Plesk → PHP-Einstellungen → disable_functions darf proc_open nicht stehen; open_basedir muss den Programmpfad zulassen.')) ?></li>
      <li><?= e(__('Prüfen:')) ?> <code>php bin/console health --site=<?= e(site()->key) ?></code></li>
    </ol>
  </section>
  <?php endif; ?>

  <section class="vt-card" aria-labelledby="vt-s4">
    <h2 id="vt-s4"><?= icon('list-checks') ?> <?= e(__('Presets')) ?></h2>
    <div class="vt-tablewrap"><table class="vt-table">
      <thead><tr><th><?= e(__('Preset')) ?></th><th><?= e(__('Wirkung')) ?></th><th><?= e(__('Verfügbar')) ?></th></tr></thead>
      <tbody><?php foreach ($presets as $p): ?>
        <tr><td><b><?= e($p['label']) ?></b> <small><code><?= e($p['key']) ?></code></small></td><td><?= e($p['description']) ?></td>
          <td><?= e(!$s['available'] ? __('nein (ffmpeg fehlt)') : ($p['available'] ? __('ja') : __('nein'))) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>

  <section class="vt-card" aria-labelledby="vt-s5">
    <h2 id="vt-s5"><?= VideoTools::icon('queue') ?> <?= e(__('Aufträge')) ?></h2>
    <?php if (!$jobs): ?><p class="adm-muted"><?= e(__('Noch keine Aufträge.')) ?></p><?php else: ?>
    <div class="vt-tablewrap"><table class="vt-table">
      <thead><tr><th>#</th><th><?= e(__('Datei')) ?></th><th><?= e(__('Auftrag')) ?></th><th><?= e(__('Stand')) ?></th><th><?= e(__('Ergebnis')) ?></th></tr></thead>
      <tbody><?php foreach ($jobs as $j): ?>
        <tr><td><?= (int) $j['id'] ?></td>
          <td><a href="<?= e(url('/admin/media#m' . $j['media_id'])) ?>"><?= e($j['media']) ?></a><small><?= e($j['created_at']) ?></small></td>
          <td><?= e($j['label']) ?></td>
          <td><span class="vt-pill is-<?= e($j['status']) ?>"><?= e($st[$j['status']] ?? $j['status']) ?><?= $j['status'] === 'running' ? ' · ' . (int) $j['progress'] . ' %' : '' ?></span></td>
          <td><?= e($j['message']) ?><?php if ($j['result_id']): ?> <a href="<?= e(url('/admin/media#m' . $j['result_id'])) ?>"><?= e(__('Anzeigen')) ?></a><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <p class="fx-i-hint"><?= e(__('Abbrechen, Wiederholen und Protokolle: in der Mediathek über „Video-Aufträge“ in der Werkzeugleiste.')) ?></p>
    <?php endif; ?>
  </section>
</div>
