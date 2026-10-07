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

<div class="set-page dom-page">
<section class="set-group" id="domains" aria-labelledby="dom-h">
  <h2 id="dom-h" class="set-group__title"><?= e(__('Domains')) ?></h2>
  <?php if (!$hosts): ?>
  <p class="adm-flash adm-flash--info" role="note"><?= e(__('Noch keine Domain eingetragen – die Website antwortet unter jeder Domain, die auf diesen Server zeigt. Mit der ersten eingetragenen Domain wird die aktuell aufgerufene Adresse ({host}) zur Hauptadresse.', ['host' => $current])) ?></p>
  <?php else: ?>
  <div class="set-list">
    <?php foreach ($hosts as $i => $h): $ok = $checked[$h] ?? null; $okFresh = $ok && (int) ($ok['at'] ?? 0) >= time() - 600; ?>
    <div class="set-row">
      <div class="set-row__main"><span class="set-row__label"><?= e($h) ?> <?= $i === 0 ? '<span class="adm-badge">' . e(__('Hauptadresse')) . '</span>' : '<span class="adm-badge adm-badge--muted">' . e(__('weitere Domain')) . '</span>' ?><?php if ($h === $current): ?> <span class="adm-badge adm-badge--muted"><?= e(__('aktuell aufgerufen')) ?></span><?php endif; ?></span>
        <?php if ($okFresh): ?><span class="set-row__sub"><?= e(($ok['scheme'] ?? '') === 'https' ? __('geprüft: zeigt auf diese Website (HTTPS)') : __('geprüft: zeigt auf diese Website (nur HTTP)')) ?></span><?php endif; ?></div>
      <div class="set-row__ctl">
        <?php if ($i > 0): ?>
        <form method="post" action="<?= $post('check') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
          <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Erreichbarkeit prüfen')) ?></button></form>
        <form method="post" action="<?= $post('primary') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
          <button class="adm-btn adm-btn--small" type="submit" data-confirm="<?= e(__('{host} als Hauptadresse festlegen? Vorher wird geprüft, ob die Domain auf diese Website zeigt. Links in E-Mails, Sitemap und Canonical-Angaben nutzen danach die neue Adresse.', ['host' => $h])) ?>"><?= e(__('Als Hauptadresse festlegen')) ?></button></form>
        <?php if ($h !== $current): ?>
        <form method="post" action="<?= $post('remove') ?>"><?= csrf_field() ?><input type="hidden" name="host" value="<?= e($h) ?>">
          <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" data-confirm="<?= e(__('Domain {host} entfernen? Die Website ist darunter danach nicht mehr erreichbar (bzw. nur noch über den Rückfall der Installation).', ['host' => $h])) ?>"><?= e(__('Entfernen')) ?></button></form>
        <?php else: ?>
        <small class="adm-muted"><?= e(__('aktuell aufgerufen – nicht entfernbar')) ?></small>
        <?php endif; ?>
        <?php else: ?>
        <small class="adm-muted"><?= e(__('Hauptadresse – nicht entfernbar')) ?></small>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php foreach ($landing as $h): ?>
    <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($h) ?> <span class="adm-badge adm-badge--muted"><?= e(__('Landing-Domain')) ?></span></span></div>
      <div class="set-row__ctl"><small class="adm-muted"><?= e(__('verwaltet unter Landingpages')) ?></small></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<form class="set-group" id="hinzufuegen" method="post" action="<?= $post('add') ?>" aria-labelledby="dom-add-h">
  <?= csrf_field() ?>
  <h2 id="dom-add-h" class="set-group__title"><?= e(__('Domain hinzufügen')) ?></h2>
  <div class="set-list">
    <div class="f f--inline"><label for="dom-host"><?= e(__('Domain')) ?></label>
      <input id="dom-host" name="host" required autocomplete="off" spellcheck="false" maxlength="253" placeholder="www.beispiel.de" aria-describedby="dom-host-help">
      <p class="f-help" id="dom-host-help"><?= e(__('Ohne https:// und ohne Pfad. Neue Domains kommen als weitere Domain hinzu; zur Hauptadresse werden sie erst nach erfolgreicher Prüfung.')) ?></p></div>
  </div>
  <?php if ($siteUrl !== ''): ?>
  <p class="set-group__note"><?= e(__('Kanonische Adresse (Grundeinstellungen): {url} – zeigt sie auf die bisherige Hauptadresse, wird sie beim Wechsel mit umgestellt.', ['url' => $siteUrl])) ?></p>
  <?php endif; ?>
  <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= icon('plus') ?> <?= e(__('Hinzufügen')) ?></button></div>
</form>

<form class="set-group" id="weiterleitung" method="post" action="<?= $post('redirect') ?>" aria-labelledby="dom-redir-h">
  <?= csrf_field() ?>
  <input type="hidden" name="on" value="0">
  <h2 id="dom-redir-h" class="set-group__title"><?= e(__('Weiterleitung')) ?></h2>
  <div class="set-list">
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="on" value="1" aria-describedby="dom-redir-help"<?= $redirect ? ' checked' : '' ?>><span><?= e(__('Andere Adressen auf die Hauptadresse weiterleiten (301)')) ?></span></label>
      <p class="f-help" id="dom-redir-help"><?= e(__('Gilt für alle weiteren Domains dieser Liste (nicht für Landing-Domains), Pfad und Parameter bleiben erhalten. Besucher und Suchmaschinen landen so immer auf {host}.', ['host' => $primary ?? '–'])) ?></p></div>
  </div>
  <p class="set-group__note"><?= e(__('Die Verwaltung ist danach nur noch unter der Hauptadresse erreichbar – auf weiteren Domains melden Sie sich dort neu an. Lokale Adressen (localhost, *.test) werden nie weitergeleitet.')) ?></p>
  <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div>
</form>

<section class="set-group" aria-labelledby="dom-env-h">
  <h2 id="dom-env-h" class="set-group__title"><?= e(__('Umgebung')) ?></h2>
  <div class="set-list">
    <a class="set-row set-row--link" href="<?= e(url('/admin/system#umgebung')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e(__('Zur Umgebung')) ?></span>
      <span class="set-row__sub"><?= e(__('Livebetrieb oder Testumgebung (staging) stellen Sie unter Grundeinstellungen → Umgebung ein.')) ?></span></span>
      <span class="set-row__ctl"><span class="adm-badge<?= environment() === 'production' ? '' : ' adm-badge--warn' ?>"><?= e(environment()) ?></span></span></a>
  </div>
</section>
</div>
