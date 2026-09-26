<?php
/**
 * Weiterleitung anlegen/bearbeiten (Core\Redirects).
 * @var ?array $row  @var array $values  @var array $errors  @var bool $from404
 */
use Core\Fields;
use Core\Redirects\Redirects;

$isNew = $row === null;
$base = '/admin/weiterleitungen';
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="rd-' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k, string $help = '') => ($help !== '' || isset($errors[$k]) ? ' aria-describedby="' . trim($help . (isset($errors[$k]) ? ' rd-' . $k . '-e' : '')) . '"' : '') . (isset($errors[$k]) ? ' aria-invalid="true"' : '');
$code = (int) ($values['code'] ?? 301);
$codes = [301 => __('301 – dauerhaft verschoben (Standard, für Suchmaschinen)'), 302 => __('302 – vorübergehend (z. B. Aktion, Umbau)'), 410 => __('410 – dauerhaft entfernt (ohne Ziel)')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($from404 ? $base . '/404' : $base)) ?>"><?= e($from404 ? __('Nicht gefunden (404)') : __('Weiterleitungen')) ?></a></p>
    <h1><?= e($isNew ? __('Neue Weiterleitung') : __('Weiterleitung bearbeiten')) ?></h1>
    <?php if (!$isNew): ?><p class="adm-muted"><?= e(__('{hits} Treffer · zuletzt {when} · angelegt {created}', ['hits' => (int) $row['hits'], 'when' => $row['last_hit'] ? date_local($row['last_hit'], 'short') : '–', 'created' => date_local((string) $row['created_at'], 'short')])) ?></p><?php endif; ?></div>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <form class="adm-card" method="post" action="<?= e(url($base . '/' . ($isNew ? 'new' : (int) $row['id']))) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($from404): ?><input type="hidden" name="back" value="404"><?php endif; ?>
    <div class="f<?= isset($errors['source']) ? ' f--error' : '' ?>">
      <label for="rd-source"><?= e(__('Alte Adresse')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="rd-source" name="f[source]" value="<?= e((string) ($values['source'] ?? '')) ?>" required spellcheck="false" autocomplete="off" placeholder="/alte-seite/"<?= $inv('source', 'rd-source-h') ?>>
      <p class="f-help" id="rd-source-h"><?= e(__('Pfad ohne Domain, z. B. /agentur/team/ – Groß/Kleinschreibung und Schrägstrich am Ende sind egal. Mit * am Ende: alle Adressen, die so beginnen (/blog/*).')) ?></p>
      <?= $err('source') ?>
    </div>
    <fieldset class="rd-codes">
      <legend class="f-label"><?= e(__('Art')) ?></legend>
      <?php foreach ($codes as $c => $label): ?>
      <label class="rv-check"><input type="radio" name="f[code]" value="<?= $c ?>"<?= $code === $c ? ' checked' : '' ?> data-rd-code> <span><?= e($label) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="f<?= isset($errors['target']) ? ' f--error' : '' ?>" data-rd-target<?= $code === 410 ? ' hidden' : '' ?>>
      <label for="rd-target"><?= e(__('Ziel')) ?> <span class="req" aria-hidden="true">*</span></label>
      <?= Fields::renderLink('rd-target', 'f[target]', (string) ($values['target'] ?? ''), $inv('target', 'rd-target-h') . ' placeholder="/neue-seite/"') ?>
      <p class="f-help" id="rd-target-h"><?= e(__('Am besten eine Seite wählen – sie bleibt verknüpft, auch wenn sie später umbenannt wird. Sonst ein Pfad (/neue-seite/) oder eine vollständige Adresse (https://…). Mit * am Ende der alten Adresse übernimmt ein * im Ziel den Rest (/news/*).')) ?></p>
      <?php if (!empty($values['suggested'])): ?><p class="f-help"><?= e(__('Vorschlag: Seite mit gleichem Adressteil – bitte prüfen.')) ?></p><?php endif; ?>
      <?= $err('target') ?>
    </div>
    <div class="f">
      <label for="rd-note"><?= e(__('Notiz')) ?></label>
      <input id="rd-note" name="f[note]" value="<?= e((string) ($values['note'] ?? '')) ?>" maxlength="190" placeholder="<?= e(__('z. B. Umzug 2026, alte Referenzen')) ?>">
    </div>
    <div class="adm-checks rd-active"><label class="rv-check"><input type="checkbox" name="f[active]" value="1"<?= !empty($values['active']) ? ' checked' : '' ?>> <span><?= e(__('Aktiv')) ?></span></label></div>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e($isNew ? __('Weiterleitung anlegen') : __('Speichern')) ?></button></div>
  </form>

  <div>
    <?php if (!$isNew): ?>
    <section class="adm-card" aria-labelledby="rd-now-h">
      <h2 id="rd-now-h"><?= e(__('Aktuell')) ?></h2>
      <?php $x = Redirects::explain(str_replace('*', 'beispiel', (string) $row['source'])); ?>
      <p><code><?= e($x['path']) ?></code></p>
      <p><?= e($x['text']) ?></p>
      <p><a href="<?= e(url($base) . '?test=' . rawurlencode(str_replace('*', 'beispiel', (string) $row['source']))) ?>#test"><?= e(__('Andere Adresse testen →')) ?></a></p>
    </section>
    <section class="adm-card adm-card--danger" aria-labelledby="rd-del-h">
      <h2 id="rd-del-h"><?= e(__('Löschen')) ?></h2>
      <p class="adm-muted"><?= e(__('Die alte Adresse endet danach mit „Seite nicht gefunden“ (404).')) ?></p>
      <form method="post" action="<?= e(url($base . '/' . (int) $row['id'] . '/delete')) ?>" data-confirm="<?= e(__('Weiterleitung von „{source}“ löschen?', ['source' => $row['source']])) ?>">
        <?= csrf_field() ?><button class="adm-btn adm-btn--danger" type="submit"><?= e(__('Weiterleitung löschen')) ?></button></form>
    </section>
    <?php else: ?>
    <section class="adm-card" aria-labelledby="rd-tip-h">
      <h2 id="rd-tip-h"><?= e(__('Gut zu wissen')) ?></h2>
      <ul>
        <li><?= e(__('Weitergeleitet wird nur, wenn es unter der alten Adresse keine Seite gibt.')) ?></li>
        <li><?= e(__('Parameter der Anfrage (?utm_source=…) werden an das Ziel angehängt.')) ?></li>
        <li><?= e(__('Weiterleitungen werden nicht verkettet: Tragen Sie immer das endgültige Ziel ein.')) ?></li>
        <li><?= e(__('Für Sprachen die Adresse mit Präfix angeben, z. B. /en/old-page/.')) ?></li>
      </ul>
    </section>
    <?php endif; ?>
  </div>
</div>
