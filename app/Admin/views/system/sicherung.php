<?php
/**
 * Grundeinstellungen → Sicherung außer Haus (Core\Backup\Offsite, OffsiteBackupController): Ziele, Stand, Jetzt sichern, Schlüssel.
 * @var array $targets  @var array $state  @var ?array $edit  @var string $editId  @var array $local  @var ?string $fingerprint  @var ?string $key  @var array $errors
 */
$f = \Core\Format::admin();
$kinds = ['webdav' => 'WebDAV', 'sftp' => 'SFTP', 'ftps' => 'FTPS', 'ftp' => __('FTP (unverschlüsselt)'), 'dir' => __('Ordner')];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Sicherung außer Haus')) ?></h1>
    <p class="adm-muted"><?= e(__('Kopiert jede Nacht die neuesten Sicherungen der Websites, Medien-Pools und geteilten Tabellen sowie die Installation (Konfiguration, Daten der Erweiterungen, eigene Kits und Erweiterungen – ohne den Kern) verschlüsselt auf einen anderen Server. Fällt dieser Server aus, lässt sich alles wiederherstellen.')) ?></p></div>
  <?php if ($targets): ?>
  <form method="post" action="<?= e(url('/admin/system/sicherung/run')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--primary" type="submit"><?= icon('cloud-arrow-up') ?> <?= e(__('Jetzt sichern')) ?></button></form>
  <?php endif; ?>
</header>

<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'sicherung'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">

<section class="adm-card" aria-labelledby="bk-key">
  <h2 id="bk-key"><?= icon('key') ?> <?= e(__('Schlüssel der Sicherungen')) ?></h2>
  <p class="adm-muted"><?= e(__('Alles, was den Server verlässt, ist mit diesem Schlüssel verschlüsselt. Er bleibt auf dem Server und ist NICHT Teil der Sicherung – bewahren Sie ihn an einem anderen Ort auf (Passwort-Manager, Tresor). Ohne ihn lässt sich keine Sicherung entschlüsseln.')) ?></p>
  <?php if ($key !== null): ?>
  <p><code class="bk-key" data-select-all><?= e($key) ?></code></p>
  <p class="adm-muted"><?= e(__('Fingerabdruck {fp} – damit prüfen Sie später, ob ein aufbewahrter Schlüssel passt.', ['fp' => (string) $fingerprint])) ?></p>
  <?php elseif ($fingerprint !== null): ?>
  <form method="post" action="<?= e(url('/admin/system/sicherung/key')) ?>" class="adm-row"><?= csrf_field() ?>
    <span class="adm-muted"><?= e(__('Fingerabdruck {fp}', ['fp' => $fingerprint])) ?></span>
    <button class="adm-btn adm-btn--small" type="submit"><?= icon('eye') ?> <?= e(__('Schlüssel anzeigen')) ?></button>
  </form>
  <?php else: ?>
  <p><?= e(__('Der Schlüssel entsteht, sobald Sie das erste Ziel speichern.')) ?></p>
  <?php endif; ?>
</section>

<section class="adm-card" aria-labelledby="bk-targets">
  <h2 id="bk-targets"><?= icon('cloud') ?> <?= e(__('Ziele')) ?></h2>
  <?php if (!$targets): ?>
  <p><?= e(__('Noch kein Ziel – die Sicherung außer Haus ist aus. Geeignet sind z. B. Nextcloud oder ownCloud (WebDAV), eine Storage-Box (WebDAV, SFTP, FTPS) oder ein NAS.')) ?></p>
  <?php else: ?>
  <ul class="bk-list" role="list">
    <?php foreach ($targets as $id => $t): $s = $state[$id] ?? []; $kind = \Core\Backup\Transport::kind((string) $t['url']); ?>
    <li class="bk-item">
      <div class="bk-item__main">
        <div class="bk-item__title"><b><?= e($t['label']) ?></b> <span class="adm-badge adm-badge--muted"><?= e($kinds[$kind] ?? $kind) ?></span>
        <?php if (empty($t['enabled'])): ?><span class="adm-badge adm-badge--muted"><?= e(__('aus')) ?></span><?php endif; ?></div>
        <div class="adm-muted bk-url"><?= e($t['url']) ?><?= ($t['user'] ?? '') !== '' ? ' · ' . e($t['user']) : '' ?> · <?= e(__('je {n} behalten', ['n' => (int) ($t['keep'] ?? 14)])) ?></div>
        <div class="<?= !empty($s['error']) ? 'f-error' : 'adm-muted' ?>">
          <?php if (!empty($s['error'])): ?><?= icon('warning') ?> <?= e(__('Letzter Versuch {time}: {msg}', ['time' => $f->datetime((int) $s['at']), 'msg' => (string) $s['error']])) ?>
          <?php elseif (!empty($s['ok'])): ?><?= icon('check-circle') ?> <?= e((int) ($s['remote'] ?? 0) === 1 ? __('Zuletzt gesichert {time} – 1 Datei am Ziel', ['time' => $f->datetime((int) $s['ok'])]) : __('Zuletzt gesichert {time} – {n} Dateien am Ziel', ['time' => $f->datetime((int) $s['ok']), 'n' => (int) ($s['remote'] ?? 0)])) ?>
          <?php else: ?><?= e(__('Noch nicht gesichert.')) ?><?php endif; ?>
        </div>
      </div>
      <div class="adm-row">
        <form method="post" action="<?= e(url('/admin/system/sicherung/test/' . $id)) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Verbindung prüfen')) ?></button></form>
        <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/system/sicherung?ziel=' . $id . '#bk-form')) ?>"><?= e(__('Bearbeiten')) ?></a>
        <form method="post" action="<?= e(url('/admin/system/sicherung/delete/' . $id)) ?>" data-confirm="<?= e(__('Ziel „{label}“ entfernen? Die Sicherungen dort bleiben erhalten.', ['label' => $t['label']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger" type="submit"><?= e(__('Entfernen')) ?></button></form>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($edit === null): ?><p><a class="adm-btn" href="<?= e(url('/admin/system/sicherung?ziel=neu#bk-form')) ?>"><?= icon('plus') ?> <?= e(__('Ziel hinzufügen')) ?></a></p><?php endif; ?>
</section>

<?php if ($edit !== null): $v = fn(string $k, $d = '') => $edit[$k] ?? $d; ?>
<section class="adm-card" aria-labelledby="bk-form" id="bk-form">
  <h2><?= e($editId === 'neu' ? __('Ziel hinzufügen') : __('Ziel bearbeiten')) ?></h2>
  <form method="post" action="<?= e(url('/admin/system/sicherung/save')) ?>" class="adm-fields"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($editId === 'neu' ? '' : $editId) ?>">
    <div class="f"><label for="bk-label"><?= e(__('Bezeichnung')) ?></label><input id="bk-label" name="label" value="<?= e((string) $v('label')) ?>" maxlength="80" placeholder="<?= e(__('z. B. Nextcloud Büro')) ?>"></div>
    <div class="f<?= isset($errors['url']) ? ' f--error' : '' ?>"><label for="bk-url"><?= e(__('Adresse des Zielordners')) ?></label>
      <input id="bk-url" name="url" value="<?= e((string) $v('url')) ?>" required inputmode="url" autocomplete="off" placeholder="https://cloud.example.org/remote.php/dav/files/name/Sicherungen">
      <p class="f-help"><?= e(__('Das Protokoll ergibt sich aus der Adresse: https:// = WebDAV (Nextcloud: …/remote.php/dav/files/BENUTZER/ORDNER), sftp://host:port/ordner, ftps://host/ordner, ftp:// (unverschlüsselte Übertragung) oder ein Ordner wie /mnt/nas/sicherungen. Fehlende Ordner werden angelegt.')) ?></p><?= $err('url') ?></div>
    <div class="adm-row">
      <div class="f"><label for="bk-user"><?= e(__('Benutzername')) ?></label><input id="bk-user" name="user" value="<?= e((string) $v('user')) ?>" autocomplete="off"></div>
      <div class="f"><label for="bk-pass"><?= e(__('Passwort')) ?></label><input id="bk-pass" name="pass" type="password" autocomplete="new-password" placeholder="<?= e($editId !== 'neu' && $v('pass') !== '' ? __('unverändert') : '') ?>">
        <p class="f-help"><?= e(__('Wird verschlüsselt gespeichert. Bei Nextcloud ein App-Passwort verwenden.')) ?></p></div>
    </div>
    <details<?= $v('key_file') !== '' || $v('fingerprint') !== '' || $v('insecure') ? ' open' : '' ?>><summary><?= e(__('Erweitert (SFTP-Schlüssel, Zertifikat)')) ?></summary>
      <div class="f"><label for="bk-keyfile"><?= e(__('SFTP: privater Schlüssel (Pfad auf dem Server)')) ?></label><input id="bk-keyfile" name="key_file" value="<?= e((string) $v('key_file')) ?>" placeholder="/home/…/.ssh/id_ed25519"></div>
      <div class="f"><label for="bk-fp"><?= e(__('SFTP: Fingerabdruck des Servers (SHA256, Base64)')) ?></label><input id="bk-fp" name="fingerprint" value="<?= e((string) $v('fingerprint')) ?>" autocomplete="off">
        <p class="f-help"><?= e(__('Empfohlen: Dann verbindet sich die Sicherung nur mit genau diesem Server (ssh-keyscan -t ed25519 host | ssh-keygen -lf -).')) ?></p></div>
      <label class="f-check"><input type="checkbox" name="insecure" value="1"<?= $v('insecure') ? ' checked' : '' ?>><span><?= e(__('TLS-Zertifikat nicht prüfen (nur für ein NAS mit eigenem Zertifikat)')) ?></span></label>
    </details>
    <div class="adm-row">
      <div class="f"><label for="bk-keep"><?= e(__('Je Website behalten')) ?></label><input id="bk-keep" name="keep" type="number" min="1" max="365" value="<?= (int) $v('keep', 14) ?>">
        <p class="f-help"><?= e(__('Ältere Sicherungen löscht die Sicherung am Ziel selbst – andere Dateien im Ordner nie.')) ?></p></div>
    </div>
    <label class="f-check"><input type="checkbox" name="code" value="1"<?= $v('code', true) ? ' checked' : '' ?>><span><?= e(__('Eigene Kits und Erweiterungen mitsichern (alles, was nicht zum Kern gehört)')) ?></span></label>
    <label class="f-check"><input type="checkbox" name="enabled" value="1"<?= $v('enabled', true) ? ' checked' : '' ?>><span><?= e(__('Eingeschaltet (jede Nacht um 03:30)')) ?></span></label>
    <div class="adm-row"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern und Verbindung prüfen')) ?></button>
      <a class="adm-btn" href="<?= e(url('/admin/system/sicherung')) ?>"><?= e(__('Abbrechen')) ?></a></div>
  </form>
</section>
<?php endif; ?>

<section class="adm-card" aria-labelledby="bk-local">
  <h2 id="bk-local"><?= icon('archive') ?> <?= e(__('Neueste Sicherungen auf diesem Server')) ?></h2>
  <?php if (!$local): ?><p><?= e(__('Noch keine – die geplanten Aufgaben legen sie jede Nacht ab 02:00 an.')) ?></p><?php else: ?>
  <ul class="bk-local" role="list">
    <?php foreach ($local as $prefix => $path): ?><li><code><?= e(basename($path)) ?></code> <span class="adm-muted"><?= e(\Core\Backup\Offsite::size((int) filesize($path))) ?> · <?= e($f->datetime((int) filemtime($path))) ?></span></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <p class="adm-muted"><?= e(__('Wiederherstellen: Entwicklerhandbuch → Betrieb → „Sicherung außer Haus“.')) ?></p>
</section>
  </div>
</div>
