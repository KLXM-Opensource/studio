<?php
/**
 * Daten → Externe Quellen (Core\Sources): Übersicht, Vorlagen, Schalter der Funktion (Netzwerk/Integratoren).
 * @var array $sources  @var array $presets  @var bool $enabled  @var ?bool $configured  @var bool $integrator
 */
$fmt = \Core\Sources\Sources::FORMATS;
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/data')) ?>"><?= e(__('Daten')) ?></a></p>
    <h1><span aria-hidden="true" class="dt-h1icon"><?= icon('plugs-connected') ?></span> <?= e(__('Externe Quellen')) ?></h1>
    <p class="adm-muted"><?= e(__('Feeds, JSON-Schnittstellen, XML und OpenImmo regelmäßig abrufen und als Einträge einer Datentabelle speichern – Datenliste, Detailseiten, Suche und API funktionieren damit wie gewohnt. Besucher laden nie etwas von der Quelle.')) ?>
      <a href="<?= e(url('/admin/hilfe/technik#quellen')) ?>"><?= e(__('Technische Dokumentation →')) ?></a></p></div>
  <?php if ($enabled && \Core\Sources\Sources::canManage()): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/quellen/new?preset=rss')) ?>"><?= e(__('Neue Quelle')) ?></a><?php endif; ?>
</header>

<?php if ($integrator): ?>
<section class="adm-card src-switch" aria-labelledby="src-sw-h">
  <h2 id="src-sw-h"><?= e(__('Funktion für diese Website')) ?></h2>
  <?php if ($configured !== null): ?>
  <p class="adm-muted"><?= e($configured ? __('Eingeschaltet in der Konfiguration der Website (features → sources).') : __('Ausgeschaltet in der Konfiguration der Website (features → sources).')) ?></p>
  <?php else: ?>
  <form method="post" action="<?= e(url('/admin/quellen/schalter')) ?>" class="adm-row">
    <?= csrf_field() ?>
    <span><span class="dt-status dt-status--<?= $enabled ? 'published' : 'draft' ?>"><?= e($enabled ? __('eingeschaltet') : __('ausgeschaltet')) ?></span></span>
    <button class="adm-btn adm-btn--small<?= $enabled ? ' adm-btn--ghost' : ' adm-btn--primary' ?>" name="on" value="<?= $enabled ? '0' : '1' ?>"><?= e($enabled ? __('Ausschalten') : __('Für diese Website einschalten')) ?></button>
  </form>
  <p class="f-help"><?= e(__('Nur Netzwerk-Administration und Integratoren sehen diesen Schalter. Danach vergibt die Administration das Recht „Externe Quellen einrichten und abrufen“ an Rollen (Administration hat es immer).')) ?></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($enabled && \Core\Sources\Sources::canManage()): ?>
<?php if ($sources): ?>
<section class="adm-card adm-card--flush" aria-labelledby="src-list-h">
  <h2 id="src-list-h" class="sr-only"><?= e(__('Quellen')) ?></h2>
  <table class="adm-table src-table">
    <thead><tr><th scope="col"><?= e(__('Quelle')) ?></th><th scope="col"><?= e(__('Ziel')) ?></th><th scope="col"><?= e(__('Letzter Abruf')) ?></th><th scope="col"><?= e(__('Einträge')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($sources as $s): ?>
      <tr>
        <td><strong><a href="<?= e(url('/admin/quellen/' . $s['id'])) ?>"><?= e($s['name']) ?></a></strong>
          <?php if (!empty($s['options']['example'])): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Beispiel')) ?></span><?php endif; ?>
          <?php if (!$s['active']): ?> <span class="adm-badge adm-badge--muted"><?= e(__('pausiert')) ?></span><?php endif; ?>
          <br><span class="adm-muted"><?= e($fmt[$s['format']] ?? $s['format']) ?> · <?= e(\Core\Sources\Sources::schedules()[$s['options']['schedule']] ?? '') ?><?php if ($s['url'] !== ''): ?> · <?= e(mb_strimwidth((string) preg_replace('~^https?://~', '', $s['url']), 0, 48, '…')) ?><?php endif; ?></span></td>
        <td><?php if ($s['table']): ?><a href="<?= e(url('/admin/data/' . $s['table']['handle'])) ?>"><span aria-hidden="true"><?= icon($s['table']['icon']) ?></span> <?= e($s['table']['name']) ?></a><?php else: ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('keine Tabelle')) ?></span><?php endif; ?></td>
        <td><?php if ($s['last_error']): ?><span class="adm-badge adm-badge--adm-warn"><?= icon('warning') ?> <?= e(__('Fehler')) ?></span><br><span class="adm-muted src-err"><?= e(mb_strimwidth((string) $s['last_error'], 0, 90, '…')) ?></span>
          <?php elseif ($s['last_ok_at']): ?><span class="adm-badge"><?= icon('check-circle') ?> <?= e(__('OK')) ?></span> <span class="adm-muted"><?= e(date_local((string) $s['last_ok_at'], 'short')) ?>, <?= e(date('H:i', strtotime((string) $s['last_ok_at']))) ?></span>
          <?php else: ?><span class="adm-muted"><?= e(__('noch nie')) ?></span><?php endif; ?></td>
        <td><?= (int) $s['items'] ?></td>
        <td class="adm-actions">
          <?php if ($s['table']): ?><form method="post" action="<?= e(url('/admin/quellen/' . $s['id'] . '/sync')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small"><?= icon('arrows-clockwise') ?> <?= e(__('Jetzt abrufen')) ?><span class="sr-only"> <?= e($s['name']) ?></span></button></form><?php endif; ?>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/quellen/' . $s['id'])) ?>"><?= e(__('Bearbeiten')) ?><span class="sr-only"> <?= e($s['name']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<section class="adm-card dt-presets">
  <h2><?= e($sources ? __('Weitere Quelle anlegen') : __('Mit einer Vorlage starten')) ?></h2>
  <p class="adm-muted"><?= e(__('Vorlagen bringen passende Zuordnungen und – auf Wunsch – eine fertige Datentabelle mit Detailseite mit.')) ?></p>
  <div class="dt-preset-grid">
    <?php foreach ($presets as $key => $p): ?>
    <a class="dt-preset" href="<?= e(url('/admin/quellen/new?preset=' . $key)) ?>"><span class="dt-icon" aria-hidden="true"><?= icon($p['icon']) ?></span><strong><?= e($p['label']) ?></strong>
      <small><?= e($p['help'] ?? '') ?></small></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="adm-card" aria-labelledby="src-how-h">
  <h2 id="src-how-h"><?= e(__('So funktioniert’s')) ?></h2>
  <ol class="doc-steps src-steps">
    <li><?= e(__('Quelle anlegen: Adresse, Format und – falls nötig – Anmeldung (Token, Benutzer/Passwort; wird verschlüsselt gespeichert).')) ?></li>
    <li><?= e(__('Zieltabelle wählen oder aus der Vorlage anlegen, dann mit „Vorschau & Test“ die Felder zuordnen.')) ?></li>
    <li><?= e(__('Speichern und „Jetzt abrufen“ – danach holt der Zeitplan neue und geänderte Einträge automatisch. Übernommene Einträge sind in der Tabelle nur lesbar.')) ?></li>
  </ol>
  <p class="f-help"><?= e(__('Zuverlässig und pünktlich per Cronjob:')) ?> <code>*/15 * * * * php bin/console sources:sync --all</code></p>
</section>
<?php elseif (!$integrator): ?>
<p class="adm-muted"><?= e(__('Die Funktion „Externe Quellen“ ist auf dieser Website ausgeschaltet.')) ?></p>
<?php endif; ?>
