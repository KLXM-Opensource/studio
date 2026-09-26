<?php
/** Einstellungen: Darstellung, Rechtstexte (Linkauswahl), Einwilligung/Protokoll, GPC und Consent Mode. @var array $values  @var array $errors */
use Core\Fields;
use MyCms\Consent\AdminController;
?>
<form method="post" action="<?= e(url('/admin/consent/settings')) ?>" novalidate>
  <?= csrf_field() ?>
  <section class="adm-card">
    <div class="adm-fields"><?= Fields::renderForm(AdminController::settingsFields(), $values, $errors, 'f') ?></div>
  </section>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button></div>
</form>

<section class="adm-card">
  <h2><?= e(__('Alle Besucher erneut fragen')) ?></h2>
  <p class="adm-muted"><?= e(__('Verwirft alle bisherigen Entscheidungen (Zustimmungen und Ablehnungen). Nur nötig, wenn sich etwas grundlegend geändert hat – neue oder geänderte Dienste werden ohnehin einzeln neu abgefragt.')) ?></p>
  <form method="post" action="<?= e(url('/admin/consent/reask')) ?>" data-confirm="<?= e(__('Wirklich alle Besucher erneut fragen?')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small"><?= e(__('Alle Besucher erneut fragen')) ?></button></form>
</section>

<section class="adm-card">
  <h2><?= e(__('Einbindung im Kit')) ?></h2>
  <ul class="ck-list">
    <li><?= e(__('Automatisch: Konfiguration und ein Skript im <head>, nur auf Seiten der Website mit aktivem einwilligungspflichtigem Dienst.')) ?></li>
    <li><?= e(__('„Cookie-Einstellungen“ im Fußbereich: automatisch in den mitgelieferten Kits (footer_links()); in eigenen Kits')) ?> <code>&lt;?= consent_settings_link() ?&gt;</code> <?= e(__('oder ein Link auf')) ?> <code>#cookie-einstellungen</code>.</li>
    <li><?= e(__('Eigene Skripte sperren')) ?>: <code>&lt;script type="text/plain" data-consent="matomo" data-src="/pfad/datei.js"&gt;&lt;/script&gt;</code></li>
    <li><?= e(__('Fremde Inhalte')) ?>: <code>&lt;?= consent_embed('google_maps', '&lt;iframe src=…&gt;&lt;/iframe&gt;', ['title' =&gt; 'Anfahrt']) ?&gt;</code> <?= e(__('oder der Block „Externer Inhalt (mit Einwilligung)“.')) ?></li>
  </ul>
</section>
