<?php
/**
 * Grundeinstellungen → Überwachung (Core\Monitoring\Status, MonitoringController): signierte Status-Abfrage an/aus, Schlüssel des
 * Überwachungsdienstes. @var array $mon
 */
$f = \Core\Format::admin();
$lines = implode("\n", array_map(fn($k) => trim($k['label'] . ' ' . $k['key']), $mon['keys']));
$dis = $mon['from_config'] ? ' disabled' : '';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Überwachung')) ?></h1>
    <p class="adm-muted"><?= e(__('Ein Überwachungsdienst (z. B. der Hub Ihrer Agentur) darf den technischen Zustand dieser Website abfragen: Versionen, Kit, Erweiterungen, Ampel der Prüfungen, letzte Sicherung und Integrität. Inhalte, Konten und Personendaten gibt die Abfrage nie heraus. Nur Anfragen, die mit einem hier eingetragenen Schlüssel signiert sind, erhalten eine Antwort.')) ?></p></div>
</header>
<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'ueberwachung'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">
<?php if ($mon['from_config']): ?><p class="adm-flash adm-flash--info"><?= e(__('Festgelegt in der Konfiguration der Website (monitoring) – hier nur zur Ansicht.')) ?></p><?php endif; ?>
<form class="adm-card" method="post" action="<?= e(url('/admin/system/ueberwachung')) ?>" aria-labelledby="mon-h">
  <?= csrf_field() ?>
  <h2 id="mon-h"><?= icon('heartbeat') ?> <?= e(__('Status-Abfrage')) ?> <span class="adm-badge<?= $mon['enabled'] ? '' : ' adm-badge--muted' ?>"><?= e($mon['enabled'] ? __('an') : __('aus')) ?></span></h2>
  <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="enabled" value="1"<?= $mon['enabled'] ? ' checked' : '' ?><?= $dis ?>> <span><?= e(__('Signierte Status-Abfrage erlauben')) ?></span></label></div>
  <div class="f"><label for="mon-keys"><?= e(__('Öffentliche Schlüssel des Überwachungsdienstes')) ?></label>
    <textarea id="mon-keys" name="keys" rows="3" spellcheck="false" aria-describedby="mon-keys-h"<?= $dis ?>><?= e($lines) ?></textarea>
    <p class="f-help" id="mon-keys-h"><?= e(__('Je Zeile ein Schlüssel „ed25519:…“, optional mit Bezeichnung davor (z. B. „Hub ed25519:…“). Zwei Schlüssel gleichzeitig erlauben einen Schlüsselwechsel ohne Lücke.')) ?></p></div>
  <dl class="acc-facts">
    <div><dt><?= e(__('Adresse für den Dienst')) ?></dt><dd><code><?= e(absolute_url('/api/monitoring/status')) ?></code></dd></div>
    <div><dt><?= e(__('Protokoll')) ?></dt><dd><code><?= e(\Core\Monitoring\Signature::PROTOCOL) ?></code></dd></div>
    <?php foreach ($mon['keys'] as $k): ?>
    <div><dt><?= e($k['label'] ?: __('Schlüssel')) ?></dt><dd><code><?= e(\Core\Monitoring\Signature::keyId((string) \Core\Monitoring\Signature::parseKey($k['key']))) ?></code><?= $k['added'] ? ' · ' . e(__('eingetragen {date}', ['date' => $f->datetime($k['added'])])) : '' ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php if (!$mon['from_config']): ?><div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div><?php endif; ?>
</form>
  </div>
</div>
