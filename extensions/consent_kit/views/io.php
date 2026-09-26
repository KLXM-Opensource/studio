<?php
/** Eigene Vorlagen: Import (Datei), Export (Auswahl), vorhandene Dateien. Format kompatibel mit dem REDAXO-AddOn consent_kit. @var array $files  @var array $services  @var ?array $report */
?>
<div class="adm-grid2">
  <section class="adm-card">
    <h2><?= e(__('Importieren')) ?></h2>
    <p class="adm-muted"><?= e(__('Vorlagen-Datei (JSON, Format des AddOns consent_kit). Geprüft werden Schlüssel, Gruppe, Einträge und Consent-Mode-Signale; fehlerhafte Einträge werden einzeln übersprungen. Der Import legt keine Dienste an – danach „Dienst hinzufügen“.')) ?></p>
    <form method="post" action="<?= e(url('/admin/consent/io/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="f"><label for="ck-file"><?= e(__('Datei')) ?></label><input type="file" id="ck-file" name="file" accept="application/json,.json" required></div>
      <div class="f f--bool"><label class="f-check"><input type="checkbox" name="overwrite" value="1"> <span><?= e(__('Gleichnamige Datei überschreiben')) ?></span></label></div>
      <p><button class="adm-btn adm-btn--primary"><?= e(__('Importieren')) ?></button></p>
    </form>
    <?php if ($report && $report['skipped']): ?>
    <div class="adm-flash adm-flash--info"><p><?= e(__('Übersprungen')) ?>:</p><ul><?php foreach ($report['skipped'] as $x): ?><li><?= e((string) $x) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </section>
  <section class="adm-card">
    <h2><?= e(__('Exportieren')) ?></h2>
    <p class="adm-muted"><?= e(__('Ohne eingetragene Werte (Mess-IDs bleiben Platzhalter), Domains, Varianten, Status und Ereignis-Auslöser – gefahrlos weiterzugeben.')) ?></p>
    <form method="post" action="<?= e(url('/admin/consent/io/export')) ?>">
      <?= csrf_field() ?>
      <fieldset class="f f--multi"><legend><?= e(__('Dienste (keine Auswahl = alle)')) ?></legend><div class="f-multi">
        <?php foreach ($services as $s): ?><label class="f-check"><input type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>"> <span><?= e($s['name']) ?></span></label><?php endforeach; ?>
        <?php if (!$services): ?><p class="adm-muted"><?= e(__('Noch keine Dienste.')) ?></p><?php endif; ?>
      </div></fieldset>
      <p><button class="adm-btn"<?= $services ? '' : ' disabled' ?>><?= e(__('Als JSON herunterladen')) ?></button></p>
    </form>
  </section>
</div>
<section class="adm-card adm-card--flush">
  <table class="adm-table">
    <caption class="ck-caption"><?= e(__('Eigene Vorlagen dieser Website')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Datei')) ?></th><th scope="col"><?= e(__('Schlüssel')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr><td><code><?= e($f['file']) ?></code></td><td><?= e(implode(', ', $f['keys'])) ?></td>
        <td class="adm-actions"><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/consent/io/file/' . rawurlencode($f['file']))) ?>"><?= e(__('Herunterladen')) ?></a>
          <form method="post" action="<?= e(url('/admin/consent/io/file/' . rawurlencode($f['file']) . '/delete')) ?>" data-confirm="<?= e(__('Vorlagen-Datei löschen? Angelegte Dienste bleiben bestehen.')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$files): ?><tr><td colspan="3" class="adm-muted"><?= e(__('Keine eigenen Vorlagen.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
