<?php
/**
 * Block-Designer: Felder, Vorlage, CSS, Beispieldaten, Einstellungen, Verlauf – mit Live-Vorschau im aktiven Theme.
 * Verhalten: resources/js/_blockbuilder.js. @var ?array $row  @var array $def  @var array $errors  @var array $warnings
 * @var array $types  @var array $versions  @var array $uses  @var bool $aiOn  @var bool $aiOpen  @var array $backgrounds  @var bool $hasDark  @var bool $library
 */
use Core\Blocks\Custom;
use Core\Blocks\Runtime;

$isNew = $row === null;
$action = url($isNew ? '/admin/blocks/new' : '/admin/blocks/' . $row['key']);
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" data-cb-err="' . e($k) . '">' . e($errors[$k]) . '</p>' : '<p class="f-error" data-cb-err="' . e($k) . '" hidden></p>';
$typeInfo = array_map(fn($t) => ['label' => $t[0], 'icon' => $t[1], 'ico' => \Core\Icons::resolve($t[1])], $types);
$status = $isNew ? null : match (true) {
    $row['status'] === 'published' && $row['changed'] => ['adm-badge adm-badge--adm-warn', __('Freigegeben · Änderungen noch nicht freigegeben')],
    $row['status'] === 'published' => ['adm-badge', __('Freigegeben (Version {n})', ['n' => $row['version']])],
    $row['status'] === 'withdrawn' => ['adm-badge adm-badge--muted', __('Zurückgezogen')],
    default => ['adm-badge adm-badge--draft', __('Entwurf – noch nicht freigegeben')],
};
$kinds = ['save' => __('Gespeichert'), 'publish' => __('Freigegeben'), 'restore' => __('Wiederhergestellt'), 'import' => __('Importiert'), 'ai' => __('KI-Vorschlag'), 'library' => __('Aus Bibliothek')];
$tabs = ['felder' => __('Felder'), 'vorlage' => __('Vorlage'), 'css' => __('CSS'), 'beispiel' => __('Beispieldaten'), 'einstellungen' => __('Einstellungen')];
if (!$isNew) $tabs['verlauf'] = __('Verlauf');
$tabErr = ['felder' => isset($errors['fields']), 'vorlage' => isset($errors['template']), 'css' => isset($errors['css'])];
$cfg = [
    'types' => $typeInfo, 'filters' => array_keys(Runtime::FILTERS), 'props' => Custom::PROPS, 'schemas' => Custom::SCHEMAS,
    'preview' => url('/admin/api/blocks/preview'), 'sample' => url('/admin/api/blocks/sample-form'), 'ai' => url('/admin/api/blocks/ai'),
    'key' => $isNew ? '' : $row['key'], 'isNew' => $isNew, 'published' => !$isNew && $row['published'] !== null,
];
?>
<header class="adm-head">
  <div>
    <p class="adm-eyebrow"><a href="<?= e(url('/admin/blocks')) ?>"><?= e(__('Blöcke')) ?></a><?php if ($status): ?> · <span class="<?= e($status[0]) ?>"><?= e($status[1]) ?></span><?php endif; ?></p>
    <h1><?php if (!$isNew): ?><span aria-hidden="true" class="dt-h1icon"><?= icon($def['icon'] ?: 'package') ?></span> <?= e($def['label']) ?><?php else: ?><?= e(__('Neuer Block')) ?><?php endif; ?></h1>
  </div>
  <?php if (!$isNew): ?>
  <div class="adm-row cb-headtools">
    <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/blocks/' . $row['key'] . '/export')) ?>"><?= icon('download-simple') ?> <?= e(__('JSON exportieren')) ?></a>
    <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/blocks/' . $row['key'] . '/theme-export')) ?>"><?= icon('code') ?> <?= e(__('Als Kit-Block exportieren')) ?></a>
    <?php if ($library): ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" form="cb-library"><?= icon('books') ?> <?= e(__('In Bibliothek kopieren')) ?></button><?php endif; ?>
  </div>
  <?php endif; ?>
</header>

<?php if ($aiOn): ?>
<details class="adm-card cb-ai" data-cb-aibox<?= $aiOpen ? ' open' : '' ?>>
  <summary><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Block mit {brand} vorschlagen lassen', ['brand' => \Core\AI\Assist::brand()])) ?></summary>
  <p class="adm-muted"><?= e(__('Beschreiben Sie, was der Block zeigen soll. Der Vorschlag (Felder, Vorlage, CSS) füllt dieses Formular – gespeichert oder freigegeben wird nichts automatisch. Beispieldaten sind als „Beispiel“ markiert.')) ?></p>
  <div class="f"><label for="cb-ai-desc"><?= e(__('Beschreibung')) ?></label>
    <textarea id="cb-ai-desc" rows="3" data-cb-aidesc data-kia-off placeholder="<?= e(__('z. B. Teamkarte mit Foto, Name, Funktion, kurzem Text und Symbol für das Fachgebiet')) ?>"></textarea></div>
  <p class="adm-row"><button type="button" class="adm-btn kia-btn" data-cb-aigo><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Vorschlag erstellen')) ?></button>
    <span class="adm-muted" data-cb-aistate aria-live="polite"></span></p>
</details>
<?php endif; ?>

<?php if ($warnings): ?>
<div class="adm-flash adm-flash--info cb-warn" data-cb-warnings role="status"><b><?= e(__('Hinweise:')) ?></b> <?= e(implode(' ', $warnings)) ?></div>
<?php else: ?><div class="adm-flash adm-flash--info cb-warn" data-cb-warnings role="status" hidden></div><?php endif; ?>

<div class="st-layout cb" data-cb data-cb-config="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
<form method="post" action="<?= e($action) ?>" class="adm-tabs-form cb-form" data-tabs data-cb-form novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="">
  <input type="hidden" name="_action" value="" data-cb-action>
  <input type="hidden" name="_ai" value="" data-cb-aiflag>
  <input type="hidden" name="fields_json" value="<?= e(json_encode($def['fields'], JSON_UNESCAPED_UNICODE)) ?>" data-cb-fieldsjson>
  <input type="hidden" name="jsonld_json" value="<?= e(json_encode($def['jsonld'], JSON_UNESCAPED_UNICODE)) ?>" data-cb-jsonldjson>

  <section class="adm-card cb-identity adm-fields">
    <div class="f f--half"><label for="cb-label"><?= e(__('Bezeichnung')) ?> <span aria-hidden="true">*</span></label>
      <input id="cb-label" name="label" value="<?= e($def['label']) ?>" maxlength="80" required data-cb-label placeholder="<?= e(__('z. B. Preistabelle')) ?>"><?= $err('label') ?></div>
    <div class="f f--half"><label for="cb-key"><?= e(__('Kurzname (Blocktyp)')) ?></label>
      <input id="cb-key" name="key" value="<?= e($def['key']) ?>" pattern="[a-z][a-z0-9_]{1,30}" maxlength="31" spellcheck="false" data-cb-key<?= $isNew ? '' : ' readonly aria-readonly="true"' ?> aria-describedby="cb-key-h">
      <p class="f-help" id="cb-key-h"><?= $isNew ? e(__('Wird aus der Bezeichnung gebildet; später nicht mehr änderbar. CSS-Klasse: .cblk-{kurzname}.')) : e(__('Blocktyp cblk_{key} · CSS-Klasse .{cls}', ['key' => $def['key'], 'cls' => Runtime::cls($def['key'])])) ?></p><?= $err('key') ?></div>
  </section>

  <div class="st-bar">
    <div class="adm-tabs" role="tablist" aria-label="<?= e(__('Bereiche des Blocks')) ?>">
      <?php $i = 0; foreach ($tabs as $id => $label): ?>
      <button type="button" role="tab" id="tab-<?= $id ?>" aria-controls="panel-<?= $id ?>" data-tab="<?= $id ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i++ === 0 ? '' : ' tabindex="-1"' ?>><?= e($label) ?><span class="cb-tabdot" data-cb-tabdot="<?= $id ?>"<?= !empty($tabErr[$id]) ? '' : ' hidden' ?>> <span aria-hidden="true">●</span><span class="adm-sr"><?= e(__('(Fehler)')) ?></span></span></button>
      <?php endforeach; ?>
    </div>
    <div class="st-tools">
      <span class="cb-check" data-cb-check aria-live="polite"></span>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-st-pvtoggle aria-pressed="false"><?= icon('eye') ?> <?= e(__('Vorschau')) ?></button>
    </div>
  </div>

  <!-- Felder -->
  <section class="adm-card adm-panel" role="tabpanel" id="panel-felder" aria-labelledby="tab-felder">
    <h2><?= e(__('Felder')) ?></h2>
    <p class="adm-muted"><?= e(__('Die Felder erscheinen beim Bearbeiten in der Seitenleiste. Mit dem Kurznamen greifen Sie in der Vorlage darauf zu: {{ kurzname }}.')) ?></p>
    <?= $err('fields') ?>
    <ol class="dt-fields cb-fields" data-cb-fields></ol>
    <div class="dt-addfield">
      <span class="adm-muted"><?= e(__('Feld hinzufügen:')) ?></span>
      <?php foreach ($types as $t => [$label, $ico]): ?>
      <button type="button" class="dt-typebtn" data-cb-add="<?= e($t) ?>"><?= icon($ico) ?> <?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
    <noscript><p class="f-error"><?= e(__('Der Feld-Editor braucht JavaScript.')) ?></p></noscript>
  </section>

  <!-- Vorlage -->
  <section class="adm-card adm-panel" role="tabpanel" id="panel-vorlage" aria-labelledby="tab-vorlage" hidden>
    <h2><?= e(__('Vorlage')) ?></h2>
    <p class="adm-muted"><?= e(__('HTML mit Platzhaltern – kein PHP, kein JavaScript. Ausgaben werden automatisch geschützt (escaped). Klick auf ein Feld fügt den passenden Platzhalter an der Cursorposition ein.')) ?></p>
    <div class="cb-palette" data-cb-palette role="group" aria-label="<?= e(__('Platzhalter einfügen')) ?>"></div>
    <div class="cb-editor" data-cb-editor>
      <pre class="cb-gutter" aria-hidden="true" data-cb-gutter></pre>
      <label class="adm-sr" for="cb-template"><?= e(__('Vorlage')) ?></label>
      <textarea id="cb-template" name="template" class="cb-code" rows="22" spellcheck="false" autocomplete="off" autocapitalize="off" wrap="off" data-cb-code="template" data-kia-off><?= e($def['template']) ?></textarea>
    </div>
    <?= $err('template') ?>
    <details class="cb-ref">
      <summary><?= e(__('Kurzreferenz')) ?></summary>
      <table class="doc-table cb-reftable">
        <tr><td><code>{{ feld }}</code></td><td><?= e(__('Ausgabe (geschützt). {{{ … }}} gibt es nicht.')) ?></td></tr>
        <tr><td><code>{{ text | rich }}</code> · <code>{{ text | inline }}</code></td><td><?= e(__('Formatierter Text (Whitelist des Sanitizers), am besten in einem <div>.')) ?></td></tr>
        <tr><td><code>{{ bild | image('(min-width: 800px) 50vw, 100vw', '4:3') }}</code></td><td><?= e(__('Responsives Bild (AVIF/WebP, Alt-Text aus der Mediathek). zoom(…) statt image(…) öffnet es in der Lightbox.')) ?></td></tr>
        <tr><td><code>&lt;a href="{{ link | link }}"&gt;</code></td><td><?= e(__('Links: Seiten, #anker, https:, mailto:, tel: – unsichere Adressen werden zu „#“, externe öffnen in neuem Tab.')) ?></td></tr>
        <tr><td><code>{{ symbol | icon }}</code> · <code>{{ datum | date('long') }}</code> · <code>{{ x | default('…') }}</code></td><td><?= e(__('Symbol, Datum (short, long, weekday, day_month), Ersatztext (übersetzbar).')) ?></td></tr>
        <tr><td><code>{{ 'Mehr erfahren' | lt }}</code></td><td><?= e(__('Fester Text in der Sprache der Seite.')) ?></td></tr>
        <tr><td><code>{% if a and not b %}…{% elseif x == 'y' %}…{% else %}…{% endif %}</code></td><td><?= e(__('Bedingungen.')) ?></td></tr>
        <tr><td><code>{% for e in liste %}{{ e.name }} {{ loop.index }}{% else %}…{% endfor %}</code></td><td><?= e(__('Listen; loop.index, loop.first, loop.last, loop.length. Zeilen eines Textfelds: {% for z in text | lines %}.')) ?></td></tr>
        <tr><td><code>id="{{ block.title_id }}"</code></td><td><?= e(__('Für die Überschrift, wenn es ein Feld „title“ gibt (Barrierefreiheit). Außerdem block.dom_id, block.dark, block.editing.')) ?></td></tr>
        <tr><td><?= e(__('Filter')) ?></td><td><code><?= e(implode(', ', array_keys(Runtime::FILTERS))) ?></code></td></tr>
      </table>
    </details>
  </section>

  <!-- CSS -->
  <section class="adm-card adm-panel" role="tabpanel" id="panel-css" aria-labelledby="tab-css" hidden>
    <h2><?= e(__('CSS')) ?></h2>
    <p class="adm-muted"><?= e(__('Selektoren ohne Präfix schreiben – sie gelten automatisch nur innerhalb des Blocks. :scope ist der Block selbst, :dark der Block auf dunklem Hintergrund. Farben über var(--cb-accent), var(--cb-surface), var(--cb-line), var(--cb-radius), var(--cb-gap) – so wirken Design-Einstellungen weiter.')) ?></p>
    <div class="cb-editor" data-cb-editor>
      <pre class="cb-gutter" aria-hidden="true" data-cb-gutter></pre>
      <label class="adm-sr" for="cb-css"><?= e(__('CSS')) ?></label>
      <textarea id="cb-css" name="css" class="cb-code" rows="18" spellcheck="false" autocomplete="off" autocapitalize="off" wrap="off" data-cb-code="css" data-kia-off><?= e($def['css']) ?></textarea>
    </div>
    <?= $err('css') ?>
    <p class="f-help"><?= e(__('Nicht erlaubt: @import, @font-face, url() zu fremden Adressen, position: fixed, html/body/:root. Das Ergebnis wird verkleinert und als eigene Datei nur auf Seiten mit dem Block geladen (höchstens 16 KB).')) ?></p>
  </section>

  <!-- Beispieldaten -->
  <section class="adm-card adm-panel" role="tabpanel" id="panel-beispiel" aria-labelledby="tab-beispiel" hidden>
    <h2><?= e(__('Beispieldaten')) ?></h2>
    <p class="adm-muted"><?= e(__('Nur für die Vorschau – so, wie die Redaktion den Block später ausfüllt. Beispieltexte sind als „Beispiel“ markiert.')) ?></p>
    <div class="adm-fields cb-sample" data-cb-sample></div>
    <p><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cb-sample-reset><?= e(__('Beispieldaten neu erzeugen')) ?></button></p>
  </section>

  <!-- Einstellungen -->
  <section class="adm-card adm-panel adm-fields" role="tabpanel" id="panel-einstellungen" aria-labelledby="tab-einstellungen" hidden>
    <h2><?= e(__('Einstellungen')) ?></h2>
    <div class="f f--half"><label for="cb-icon"><?= e(__('Symbol in der Block-Auswahl')) ?></label><?= \Core\Icons::picker('cb-icon', 'icon', $def['icon'] ?: 'package', ['suggest' => 'cb-label']) ?></div>
    <div class="f f--half"><label for="cb-group"><?= e(__('Gruppe')) ?></label><input id="cb-group" name="group" value="<?= e($def['group']) ?>" maxlength="60" placeholder="<?= e(__('Eigene Blöcke')) ?>"></div>
    <div class="f"><label for="cb-desc"><?= e(__('Beschreibung (für die Übersicht)')) ?></label><input id="cb-desc" name="description" value="<?= e($def['description']) ?>" maxlength="400"></div>
    <div class="f"><label for="cb-help"><?= e(__('Hilfetext für die Redaktion')) ?></label><textarea id="cb-help" name="settings[help]" rows="2" maxlength="300"><?= e($def['settings']['help'] ?? '') ?></textarea>
      <p class="f-help"><?= e(__('Erscheint in der Block-Auswahl und im Handbuch „Alle Blöcke“.')) ?></p></div>
    <div class="f f--half"><label for="cb-bg"><?= e(__('Standard-Hintergrund des Abschnitts')) ?></label>
      <select id="cb-bg" name="settings[background]"><option value=""><?= e(__('wie im Kit')) ?></option>
        <?php foreach ($backgrounds as $k => $l): ?><option value="<?= e($k) ?>"<?= ($def['settings']['background'] ?? '') === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
    <div class="f f--half"><label for="cb-width"><?= e(__('Breite')) ?></label>
      <select id="cb-width" name="settings[width]">
        <option value="wrap"<?= ($def['settings']['width'] ?? 'wrap') !== 'full' ? ' selected' : '' ?>><?= e(__('Inhaltsbreite des Kits')) ?></option>
        <option value="full"<?= ($def['settings']['width'] ?? '') === 'full' ? ' selected' : '' ?>><?= e(__('Volle Breite')) ?></option></select></div>
    <fieldset class="f cb-beh"><legend><?= e(__('Verhalten (ohne eigenes JavaScript)')) ?></legend>
      <?php foreach (['accordion' => __('Aufklappen: <details>/<summary> mit Fokus-Stil (name="…" = nur eines offen)'), 'reel' => __('Scroll-Leiste: Klasse cb-reel (Scroll-Snap, wischbar, per Tastatur scrollbar)'), 'lightbox' => __('Lightbox: Bilder mit {{ bild | zoom(…) }} in einem Element mit data-cms-lightbox')] as $k => $l): ?>
      <label class="f-check"><input type="checkbox" name="behaviours[]" value="<?= $k ?>"<?= in_array($k, $def['behaviours'], true) ? ' checked' : '' ?> data-cb-beh> <span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <fieldset class="f cb-jsonld" data-cb-jsonld><legend><?= e(__('Strukturierte Daten (schema.org, optional)')) ?></legend>
      <p class="f-help"><?= e(__('Für Suchmaschinen: Felder einem schema.org-Typ zuordnen. Einträge mit Platzhaltern in [eckigen Klammern] werden übersprungen.')) ?></p>
      <div data-cb-jsonld-ui></div>
    </fieldset>
    <?php if (!$isNew && $uses): ?>
    <div class="f"><p class="f-label"><?= e(__('Verwendet auf')) ?></p><ul class="cb-uses"><?php foreach ($uses as $u): ?><li><a href="<?= e(url('/admin/pages/' . (int) $u['id'])) ?>"><?= e($u['title']) ?></a> <span class="adm-muted">(<?= e($u['status'] === 'published' ? __('veröffentlicht') : __('Entwurf')) ?>)</span></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
  </section>

  <?php if (!$isNew): ?>
  <!-- Verlauf -->
  <section class="adm-card adm-panel" role="tabpanel" id="panel-verlauf" aria-labelledby="tab-verlauf" hidden>
    <h2><?= e(__('Verlauf')) ?></h2>
    <p class="adm-muted"><?= e(__('Jede Änderung wird gespeichert (die letzten {n}). Wiederherstellen legt die Fassung als neuen Entwurf an – veröffentlicht wird erst mit „Freigeben“.', ['n' => Custom::MAX_VERSIONS])) ?></p>
    <table class="adm-table cb-versions">
      <thead><tr><th><?= e(__('Zeitpunkt')) ?></th><th><?= e(__('Art')) ?></th><th><?= e(__('Von')) ?></th><th><span class="adm-sr"><?= e(__('Aktion')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($versions as $i => $v): ?>
        <tr><td><?= e(date_local((string) $v['created_at'], 'short')) ?> <?= e(substr((string) $v['created_at'], 11, 5)) ?></td>
          <td><?= e($kinds[$v['kind']] ?? $v['kind']) ?><?= $v['note'] ? ' <span class="adm-muted">– ' . e($v['note']) . '</span>' : '' ?></td>
          <td><?= e($v['user_name'] ?: ($v['user_email'] ?? '–')) ?></td>
          <td class="adm-actions"><?php if ($i > 0): ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" form="cb-restore-<?= (int) $v['id'] ?>"><?= e(__('Wiederherstellen')) ?></button><?php else: ?><span class="adm-muted"><?= e(__('aktuell')) ?></span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <div class="adm-savebar cb-savebar">
    <button class="adm-btn" type="submit" data-cb-save><?= e($isNew ? __('Als Entwurf anlegen') : __('Entwurf speichern')) ?></button>
    <?php if (!$isNew): ?><button class="adm-btn adm-btn--primary" type="submit" data-cb-publish><?= e(__('Speichern & für Redaktion freigeben')) ?></button><?php endif; ?>
    <span class="adm-muted" data-cb-state aria-live="polite"></span>
  </div>
</form>

<aside class="st-pv cb-pv" aria-label="<?= e(__('Vorschau')) ?>" hidden>
  <header class="st-pv__bar">
    <strong><?= e(__('Vorschau')) ?></strong>
    <div class="fx-seg" role="group" aria-label="<?= e(__('Gerät')) ?>">
      <button type="button" data-st-device="desktop" aria-pressed="true"><?= e(__('Desktop')) ?></button>
      <button type="button" data-st-device="mobile" aria-pressed="false"><?= e(__('Mobil')) ?></button>
    </div>
    <label class="adm-sr" for="cb-pv-bg"><?= e(__('Hintergrund')) ?></label>
    <select id="cb-pv-bg" class="ds-pvpage" data-cb-pvbg><option value=""><?= e(__('Hintergrund: Standard')) ?></option>
      <?php foreach ($backgrounds as $k => $l): ?><option value="<?= e($k) ?>"><?= e(__($l)) ?></option><?php endforeach; ?></select>
    <?php if ($hasDark): ?>
    <div class="fx-seg" role="group" aria-label="<?= e(__('Farbschema')) ?>">
      <button type="button" data-cb-scheme="light" aria-pressed="true"><?= e(__('Hell')) ?></button>
      <button type="button" data-cb-scheme="dark" aria-pressed="false"><?= e(__('Dunkel')) ?></button>
    </div>
    <?php endif; ?>
    <span class="st-pv__state" data-st-state aria-live="polite"></span>
  </header>
  <div class="st-pv__stage" data-st-stage><iframe title="<?= e(__('Vorschau des Blocks im aktiven Kit')) ?>" data-st-frame></iframe></div>
  <p class="st-pv__note adm-muted"><?= e(__('Zeigt den Block mit den Beispieldaten auf der Startseite des aktiven Kits – mit dem ungespeicherten Stand.')) ?></p>
</aside>
</div>

<?php if (!$isNew): ?>
<section class="adm-card cb-danger">
  <h2><?= e(__('Freigabe & Entfernen')) ?></h2>
  <div class="adm-row">
    <?php if ($row['status'] === 'published'): ?>
    <form method="post" action="<?= e(url('/admin/blocks/' . $row['key'] . '/withdraw')) ?>" data-confirm="<?= e(__('Block zurückziehen? Er lässt sich dann nicht mehr neu einfügen; bestehende Seiten zeigen ihn weiter.')) ?>"><?= csrf_field() ?>
      <button class="adm-btn adm-btn--ghost" type="submit"><?= e(__('Zurückziehen')) ?></button></form>
    <?php elseif ($row['status'] === 'withdrawn'): ?>
    <form method="post" action="<?= e(url('/admin/blocks/' . $row['key'] . '/publish')) ?>"><?= csrf_field() ?><button class="adm-btn" type="submit"><?= e(__('Wieder freigeben')) ?></button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/blocks/' . $row['key'] . '/delete')) ?>" data-confirm="<?= e(__('Block „{label}“ endgültig löschen?', ['label' => $row['label']])) ?>"><?= csrf_field() ?>
      <button class="adm-btn adm-btn--danger" type="submit"<?= $uses ? ' disabled aria-describedby="cb-del-h"' : '' ?>><?= e(__('Löschen')) ?></button></form>
    <?php if ($uses): ?><p class="f-help" id="cb-del-h"><?= e(__('Löschen ist erst möglich, wenn keine Seite den Block mehr verwendet.')) ?></p><?php endif; ?>
  </div>
</section>
<?php foreach ($versions as $v): ?>
<form method="post" id="cb-restore-<?= (int) $v['id'] ?>" action="<?= e(url('/admin/blocks/' . $row['key'] . '/restore/' . (int) $v['id'])) ?>" hidden><?= csrf_field() ?></form>
<?php endforeach; ?>
<?php if ($library): ?><form method="post" id="cb-library" action="<?= e(url('/admin/blocks/' . $row['key'] . '/library')) ?>" hidden><?= csrf_field() ?></form><?php endif; ?>
<?php endif; ?>

<template data-cb-field-tpl>
  <li class="dt-field cb-field" data-cb-field>
    <span class="dt-field__icon" aria-hidden="true" data-cb-ficon></span>
    <div class="dt-field__main">
      <div class="dt-field__row">
        <label class="dt-in dt-in--label"><span><?= e(__('Bezeichnung')) ?></span><input data-p="label" maxlength="80"></label>
        <label class="dt-in dt-in--name"><span><?= e(__('Kurzname')) ?></span><input data-p="name" pattern="[a-z][a-z0-9_]*" maxlength="40" spellcheck="false"></label>
        <label class="dt-in dt-in--type"><span><?= e(__('Typ')) ?></span><select data-p="type"></select></label>
      </div>
      <div class="dt-field__row dt-field__flags">
        <label class="f-check"><input type="checkbox" data-p="required"> <span><?= e(__('Pflichtfeld')) ?></span></label>
        <label class="f-check"><input type="checkbox" data-p="half"> <span><?= e(__('Halbe Breite')) ?></span></label>
      </div>
      <div class="dt-field__extra" data-show="select"><label class="dt-in"><span><?= e(__('Auswahlmöglichkeiten (eine pro Zeile, optional „kurzname=Text“)')) ?></span><textarea data-p="options" rows="3"></textarea></label></div>
      <div class="dt-field__extra" data-show="text textarea inline"><label class="dt-in dt-in--small"><span><?= e(__('Höchstlänge (Zeichen)')) ?></span><input type="number" data-p="max" min="0" max="5000"></label></div>
      <div class="dt-field__extra dt-group" data-show="repeater">
        <fieldset class="dt-group__subs"><legend><?= e(__('Unterfelder je Eintrag')) ?></legend>
          <ol class="dt-subs" data-cb-subs></ol>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cb-subadd>+ <?= e(__('Unterfeld')) ?></button>
        </fieldset>
        <div class="dt-group__opts">
          <label class="dt-in"><span><?= e(__('Bezeichnung eines Eintrags')) ?></span><input data-p="item_label" maxlength="40" placeholder="<?= e(__('z. B. Paket')) ?>"></label>
          <label class="dt-in"><span><?= e(__('Höchstens')) ?></span><input type="number" data-p="max_items" min="1" max="50"></label>
        </div>
      </div>
      <label class="dt-in dt-in--help"><span><?= e(__('Hilfetext (optional)')) ?></span><input data-p="help" maxlength="240"></label>
    </div>
    <div class="dt-field__tools">
      <button type="button" class="cms-iconbtn dt-iconbtn" data-cb-move="-1" aria-label="<?= e(__('Nach oben')) ?>">↑</button>
      <button type="button" class="cms-iconbtn dt-iconbtn" data-cb-move="1" aria-label="<?= e(__('Nach unten')) ?>">↓</button>
      <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-cb-remove aria-label="<?= e(__('Feld entfernen')) ?>">✕</button>
    </div>
  </li>
</template>
<template data-cb-sub-tpl>
  <li class="dt-sub" data-cb-sub><div class="dt-sub__row">
    <label class="dt-in dt-in--label"><span><?= e(__('Bezeichnung')) ?></span><input data-s="label" maxlength="80"></label>
    <label class="dt-in dt-in--name"><span><?= e(__('Kurzname')) ?></span><input data-s="name" pattern="[a-z][a-z0-9_]*" maxlength="40" spellcheck="false"></label>
    <label class="dt-in dt-in--type"><span><?= e(__('Typ')) ?></span><select data-s="type"></select></label>
    <span class="dt-sub__tools">
      <button type="button" class="cms-iconbtn dt-iconbtn" data-cb-submove="-1" aria-label="<?= e(__('Unterfeld nach oben')) ?>">↑</button>
      <button type="button" class="cms-iconbtn dt-iconbtn" data-cb-submove="1" aria-label="<?= e(__('Unterfeld nach unten')) ?>">↓</button>
      <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-cb-subremove aria-label="<?= e(__('Unterfeld entfernen')) ?>">✕</button></span></div>
    <div class="dt-sub__row dt-field__flags"><label class="f-check"><input type="checkbox" data-s="required"> <span><?= e(__('Pflichtfeld')) ?></span></label>
      <label class="f-check"><input type="checkbox" data-s="half"> <span><?= e(__('Halbe Breite')) ?></span></label></div>
    <label class="dt-in" data-sshow="select" hidden><span><?= e(__('Auswahlmöglichkeiten (eine pro Zeile)')) ?></span><textarea data-s="options" rows="3"></textarea></label>
  </li>
</template>
