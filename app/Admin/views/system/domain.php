<?php
/**
 * System → Domain (DomainController, Core\Domains): Domains der Website, Hauptadresse, Weiterleitung, Umgebung.
 * Nur Einzel-Installation ohne Netzwerk.
 * @var list<string> $hosts  @var list<string> $landing  @var string $current  @var bool $redirect  @var string $env  @var string $siteUrl  @var array $checked
 */
$primary = $hosts[0] ?? null;
$post = fn(string $a) => e(url('/admin/system/domain/' . $a));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Domain')) ?></h1>
    <p class="adm-muted"><?= e(__('Unter welchen Adressen die Website erreichbar ist. Eine neue Domain richten Sie zuerst beim Hosting ein (DNS, in Plesk als Alias bzw. zusätzliche Domain, mit SSL-Zertifikat) und tragen sie dann hier ein.')) ?></p></div>
</header>

<section class="adm-card adm-card--flush" id="domains" aria-labelledby="dom-h">
  <h2 id="dom-h" class="adm-sr"><?= e(__('Domains')) ?></h2>
  <?php if (!$hosts): ?>
  <p class="adm-flash adm-flash--info" role="note"><?= e(__('Noch keine Domain eingetragen – die Website antwortet unter jeder Domain, die auf diesen Server zeigt. Mit der ersten eingetragenen Domain wird die aktuell aufgerufene Adresse ({host}) zur Hauptadresse.', ['host' => $current])) ?></p>
  <?php else: ?>
  <table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Domain')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($hosts as $i => $h): $ok = $checked[$h] ?? null; $okFresh = $ok && (int) ($ok['at'] ?? 0) >= time() - 600; ?>
      <tr>
        <td><strong><?= e($h) ?></strong><br><?= $i === 0 ? '<span class="adm-badge">' . e(__('Hauptadresse')) . '</span>' : '<span class="adm-badge adm-badge--muted">' . e(__('weitere Domain')) . '</span>' ?><?php if ($h === $current): ?> <span class="adm-badge adm-badge--muted"><?= e(__('aktuell aufgerufen')) ?></span><?php endif; ?>
          <?php if ($okFresh): ?><br><small class="adm-muted"><?= e(($ok['scheme'] ?? '') === 'https' ? __('geprüft: zeigt auf diese Website (HTTPS)') : __('geprüft: zeigt auf diese Website (nur HTTP)')) ?></small><?php endif; ?></td>
        <td><div class="adm-actions">
          <?php if ($i > 0): ?>
          <form method="post" action="<?= $post('check') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
            <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Erreichbarkeit prüfen')) ?></button></form>
          <form method="post" action="<?= $post('primary') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
            <button class="adm-btn adm-btn--small" type="submit" data-confirm="<?= e(__('{host} als Hauptadresse festlegen? Vorher wird geprüft, ob die Domain auf diese Website zeigt. Links in E-Mails, Sitemap und Canonical-Angaben nutzen danach die neue Adresse.', ['host' => $h])) ?>"><?= e(__('Als Hauptadresse festlegen')) ?></button></form>
          <?php if ($h !== $current): ?>
          <form method="post" action="<?= $post('remove') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
            <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit" data-confirm="<?= e(__('Domain {host} entfernen? Die Website ist darunter danach nicht mehr erreichbar (bzw. nur noch über den Rückfall der Installation).', ['host' => $h])) ?>"><?= e(__('Entfernen')) ?></button></form>
          <?php else: ?>
          <small class="adm-muted"><?= e(__('aktuell aufgerufen – nicht entfernbar')) ?></small>
          <?php endif; ?>
          <?php else: ?>
          <small class="adm-muted"><?= e(__('Hauptadresse – nicht entfernbar')) ?></small>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($landing as $h): ?>
      <tr><td><strong><?= e($h) ?></strong><br><span class="adm-badge adm-badge--muted"><?= e(__('Landing-Domain')) ?></span></td>
        <td><div class="adm-actions"><small class="adm-muted"><?= e(__('verwaltet unter Landingpages')) ?></small></div></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section class="adm-card" id="hinzufuegen" aria-labelledby="dom-add-h">
  <h2 id="dom-add-h"><?= e(__('Domain hinzufügen')) ?></h2>
  <form method="post" action="<?= $post('add') ?>" class="adm-fields">
    <?= csrf_field() ?>
    <div class="f f--half"><label for="dom-host"><?= e(__('Domain')) ?></label>
      <input id="dom-host" name="host" required autocomplete="off" spellcheck="false" maxlength="253" placeholder="www.beispiel.de" aria-describedby="dom-host-help">
      <p class="f-help" id="dom-host-help"><?= e(__('Ohne https:// und ohne Pfad. Neue Domains kommen als weitere Domain hinzu; zur Hauptadresse werden sie erst nach erfolgreicher Prüfung.')) ?></p></div>
    <div class="adm-row"><button class="adm-btn adm-btn--primary" type="submit"><?= icon('plus') ?> <?= e(__('Hinzufügen')) ?></button></div>
  </form>
  <?php if ($siteUrl !== ''): ?>
  <p class="f-help"><?= e(__('Kanonische Adresse (Grundeinstellungen): {url} – zeigt sie auf die bisherige Hauptadresse, wird sie beim Wechsel mit umgestellt.', ['url' => $siteUrl])) ?></p>
  <?php endif; ?>
</section>

<section class="adm-card" id="weiterleitung" aria-labelledby="dom-redir-h">
  <h2 id="dom-redir-h"><?= e(__('Weiterleitung')) ?></h2>
  <form method="post" action="<?= $post('redirect') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="on" value="0">
    <label class="f-check"><input type="checkbox" name="on" value="1"<?= $redirect ? ' checked' : '' ?>><span><?= e(__('Andere Adressen auf die Hauptadresse weiterleiten (301)')) ?></span></label>
    <p class="f-help"><?= e(__('Gilt für alle weiteren Domains dieser Liste (nicht für Landing-Domains), Pfad und Parameter bleiben erhalten. Besucher und Suchmaschinen landen so immer auf {host}.', ['host' => $primary ?? '–'])) ?> <?= e(__('Die Verwaltung ist danach nur noch unter der Hauptadresse erreichbar – auf weiteren Domains melden Sie sich dort neu an. Lokale Adressen (localhost, *.test) werden nie weitergeleitet.')) ?></p>
    <div class="adm-row"><button class="adm-btn" type="submit"><?= e(__('Speichern')) ?></button></div>
  </form>
</section>

<section class="adm-card" aria-labelledby="dom-env-h">
  <h2 id="dom-env-h"><?= e(__('Umgebung')) ?></h2>
  <p><?= e(__('Livebetrieb oder Testumgebung (staging) stellen Sie unter Grundeinstellungen → Umgebung ein.')) ?> <a class="adm-link" href="<?= e(url('/admin/system#umgebung')) ?>"><?= e(__('Zur Umgebung')) ?></a></p>
</section>
