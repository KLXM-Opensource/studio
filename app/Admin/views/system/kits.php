<?php
/**
 * Grundeinstellungen → Kits (Core\KitPackages, KitsController): installierte Kits mit Herkunft, Version, nutzenden Websites;
 * Kit-Paket (ZIP) hochladen, hochgeladene Kits entfernen.
 * @var array $kits  @var bool $upload  @var int $maxMb
 */
$srcLabel = ['local' => __('mitgeliefert / lokal'), 'upload' => __('hochgeladen'), 'composer' => __('Composer')];
$siteLabel = fn(string $k) => $k === \Core\Site::DEFAULT ? __('Hauptwebsite') . ' (' . $k . ')' : $k;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Kits')) ?></h1>
    <p class="adm-muted"><?= e(__('Kits bringen Gestaltung, Blöcke und Startinhalte einer Website mit. Sie gelten für die ganze Installation: Jede Website wählt ihr Kit unter Grundeinstellungen → Aktives Kit. Zusätzliche Kits lassen sich als Paket installieren – per Composer oder als ZIP-Datei.')) ?></p></div>
</header>

<section class="adm-card adm-card--flush" id="installiert" aria-labelledby="kits-h">
  <h2 id="kits-h" class="adm-sr"><?= e(__('Installierte Kits')) ?></h2>
  <table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Kit')) ?></th><th scope="col"><?= e(__('Version')) ?></th><th scope="col"><?= e(__('Herkunft')) ?></th><th scope="col"><?= e(__('Verwendet von')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($kits as $name => $k): ?>
      <tr id="kit-<?= e($name) ?>">
        <td><strong><?= e($k['label']) ?></strong> <code><?= e($name) ?></code>
          <?php if (!$k['compatible']): ?><br><span class="adm-badge adm-badge--warn"><?= e(__('verlangt Core {req}', ['req' => $k['requires']])) ?></span><?php endif; ?>
          <?php if ($k['published'] === false): ?><br><span class="adm-badge adm-badge--warn"><?= e(__('Assets nicht veröffentlicht')) ?></span><?php endif; ?>
          <?php foreach ($k['shadowed'] as $sh): ?><br><small class="adm-muted"><?= e(__('Verdeckt: {dir} ({source}) – es gilt {active}', ['dir' => \Core\Kit::relative($sh['dir']), 'source' => $srcLabel[$sh['source']] ?? $sh['source'], 'active' => $k['dir']])) ?></small><?php endforeach; ?>
        </td>
        <td><?= e($k['version'] ?: '–') ?></td>
        <td><span class="adm-badge<?= $k['source'] === 'local' ? ' adm-badge--muted' : '' ?>"><?= e($srcLabel[$k['source']] ?? $k['source']) ?></span>
          <br><small class="adm-muted"><code><?= e($k['package'] !== '' ? $k['package'] : $k['dir']) ?></code></small>
          <?php if ($k['installed']): ?><br><small class="adm-muted"><?= e(__('am {date}', ['date' => \Core\Format::admin()->date((string) ($k['installed']['installed_at'] ?? ''))])) ?><?= ($k['installed']['by'] ?? '') !== '' ? ' · ' . e((string) $k['installed']['by']) : '' ?></small><?php endif; ?>
        </td>
        <td><?= $k['used'] ? e(implode(', ', array_map($siteLabel, $k['used']))) : '<span class="adm-muted">' . e(__('nicht verwendet')) . '</span>' ?></td>
        <td class="adm-actions">
          <?php if ($k['source'] === 'upload' && !$k['used']): ?>
          <form method="post" action="<?= e(url('/admin/system/kits/' . $name . '/remove')) ?>"><?= csrf_field() ?>
            <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit" data-confirm="<?= e(__('Kit „{name}“ entfernen? Dateien unter storage/kits/{name} und public/assets/kits/{name} werden gelöscht.', ['name' => $name])) ?>"><?= e(__('Entfernen')) ?></button></form>
          <?php elseif ($k['source'] === 'upload'): ?>
          <small class="adm-muted"><?= e(__('in Verwendung – nicht entfernbar')) ?></small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="adm-card" id="hochladen" aria-labelledby="kits-up-h">
  <h2 id="kits-up-h"><?= e(__('Kit-Paket hochladen')) ?></h2>
  <div class="adm-flash adm-flash--info" role="note"><p><strong><?= e(__('Ein Kit enthält PHP-Code.')) ?></strong> <?= e(__('Er läuft auf jeder Website dieser Installation, die das Kit nutzt, mit vollen Rechten. Installieren Sie Kits nur aus vertrauenswürdiger Quelle (z. B. von KLXM oder Ihrer Agentur).')) ?></p></div>
  <?php if (!$upload): ?>
  <p class="adm-muted"><?= e(__('Das Hochladen ist auf dieser Installation abgeschaltet (Konfiguration kit_upload bzw. PHP-Erweiterung zip fehlt). Kits lassen sich weiter per Kommandozeile installieren: php bin/console kit:install datei.zip')) ?></p>
  <?php else: ?>
  <form method="post" action="<?= e(url('/admin/system/kits/install')) ?>" enctype="multipart/form-data" class="adm-fields">
    <?= csrf_field() ?>
    <div class="f"><label for="kit-file"><?= e(__('ZIP-Datei')) ?></label><input id="kit-file" type="file" name="kit" accept=".zip,application/zip" required>
      <p class="f-help"><?= e(__('Höchstens {mb} MB. Inhalt: theme.php bzw. kit.php mit Templates und Blöcken, composer.json (Typ klxm-studio-kit) und die fertig gebauten Assets im Ordner public/.', ['mb' => $maxMb])) ?></p></div>
    <label class="f-check"><input type="checkbox" name="replace" value="1"><span><?= e(__('Ersetzen, falls bereits hochgeladen')) ?> <small class="adm-muted"><?= e(__('mitgelieferte und per Composer installierte Kits werden nie ersetzt')) ?></small></span></label>
    <label class="f-check"><input type="checkbox" name="trust" value="1" required><span><?= e(__('Das Kit stammt aus einer vertrauenswürdigen Quelle.')) ?></span></label>
    <div class="adm-row"><button class="adm-btn adm-btn--primary" type="submit"><?= icon('upload-simple') ?> <?= e(__('Prüfen und installieren')) ?></button></div>
  </form>
  <?php endif; ?>
  <p class="f-help"><?= e(__('Geprüft werden: Größe, Pfade (keine absoluten Pfade, kein „..“, keine symbolischen Links), Kit-Name, Angaben label/version, die verlangte Core-Version (requires) und die PHP-Syntax. Hochgeladene Kits liegen unter storage/kits/{name}, ihre Assets unter public/assets/kits/{name}. Per Composer: Paket vom Typ klxm-studio-kit, danach php bin/console kits:publish.')) ?></p>
</section>
