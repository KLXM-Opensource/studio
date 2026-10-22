<?php
/**
 * Grundeinstellungen → Integrität (Core\Integrity, IntegrityController): letzte Prüfung von Dateien und Datenbank mit Funden,
 * Jetzt prüfen, Als bekannt übernehmen, E-Mail-Hinweis.
 * @var ?array $files  @var bool $canFiles  @var ?array $db  @var bool $mail
 */
$f = \Core\Format::admin();
$section = function (string $id, string $title, string $help, ?array $res, string $accept) use ($f): void {
    $err = $res ? count(array_filter($res['findings'], fn($x) => $x['level'] === 'error')) : 0;
    $warn = $res ? count($res['findings']) - $err : 0;
    ?>
<section class="adm-card" aria-labelledby="ig-<?= e($id) ?>">
  <h2 id="ig-<?= e($id) ?>"><?= icon($err ? 'warning' : 'shield-check') ?> <?= e($title) ?>
    <?php if ($res): ?><span class="adm-badge<?= $err ? ' adm-badge--warn' : ($warn ? ' adm-badge--muted' : '') ?>"><?= e($err ? __('{n} Fehler', ['n' => $err]) : ($warn ? __('{n} Hinweise', ['n' => $warn]) : __('nichts Unerwartetes'))) ?></span><?php endif; ?></h2>
  <p class="adm-muted"><?= e($help) ?></p>
  <?php if (!$res): ?>
  <p><?= e(__('Noch nicht geprüft.')) ?></p>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Letzte Prüfung: {time}', ['time' => $f->datetime((int) $res['time'])])) ?> · <?= e($res['baseline'] ? __('Bestätigter Stand vom {time}', ['time' => $f->datetime((int) $res['baseline'])]) : __('kein bestätigter Stand')) ?></p>
  <?php if ($res['findings']): ?>
  <ul class="ig-list" role="list">
    <?php foreach (array_slice($res['findings'], 0, 200) as $x): ?>
    <li class="ig-item ig-item--<?= e($x['level']) ?>"><span class="adm-badge<?= $x['level'] === 'error' ? ' adm-badge--warn' : ' adm-badge--muted' ?>"><?= e($x['level'] === 'error' ? __('Fehler') : __('Hinweis')) ?></span> <?= e($x['text']) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($res['findings']) > 200): ?><p class="adm-muted"><?= e(__('… und {n} weitere (Kommandozeile: integrity:check).', ['n' => count($res['findings']) - 200])) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/admin/system/integritaet/accept/' . $accept)) ?>" class="adm-row"
    data-confirm="<?= e(__('Den aktuellen Stand als bekannt übernehmen? Nur, wenn alle Funde gewollt sind (Update, neue Erweiterung, neues Konto).')) ?>"><?= csrf_field() ?>
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Als bekannt übernehmen')) ?></button>
  </form>
</section>
<?php
};
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Integrität')) ?></h1>
    <p class="adm-muted"><?= e(__('Überwacht, was Angreifer typischerweise verändern: Code, .htaccess und Konfiguration, ausführbare Dateien in Medien- und Datenordnern, Trigger in der Datenbank, neue Administrator-Konten und Skript-Code in Inhalten. Die Prüfung läuft stündlich (Geplante Aufgaben).')) ?></p></div>
  <form method="post" action="<?= e(url('/admin/system/integritaet/check')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--primary" type="submit"><?= icon('shield-check') ?> <?= e(__('Jetzt prüfen')) ?></button></form>
</header>

<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'integritaet'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">
<?php
if ($canFiles) $section('files', __('Dateien der Installation'), __('Prüfsummen von Code, Autoloader, Konfiguration und jeder .htaccess/.user.ini – dazu ausführbare Dateien in public/ und storage/ sowie PHP-Code in neuen Mediendateien. Nach einem Update oder Deploy den Stand als bekannt übernehmen.'), $files, 'files');
$section('db', __('Datenbank dieser Website'), __('Trigger und Views, neue Tabellen, neue Konten mit allen Rechten, neue API-Schlüssel und Skript-Code in Seiten oder Einstellungen.'), $db, 'db');
?>
<section class="adm-card" aria-labelledby="ig-mail">
  <h2 id="ig-mail"><?= icon('envelope-simple') ?> <?= e(__('Hinweis per E-Mail')) ?></h2>
  <form method="post" action="<?= e(url('/admin/system/integritaet/settings')) ?>" class="adm-row"><?= csrf_field() ?>
    <label class="f-check"><input type="checkbox" name="mail" value="1"<?= $mail ? ' checked' : '' ?>><span><?= e(__('Neue Fehler an alle Konten mit allen Rechten melden (je Fund einmal)')) ?></span></label>
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
  </form>
</section>
  </div>
</div>
