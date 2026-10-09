<?php
/**
 * Tabellen-Designer „Felder & Einstellungen“ im Stil der macOS-Systemeinstellungen: Bereiche in einer Seitenleiste (schmal ein
 * Auswahlfeld, admin.js [data-tabs]), darin Gruppen mit Einstellungszeilen (.set-group/.set-list). Welche Bereiche zuerst
 * erscheinen, bestimmt der Zweck der Tabelle (Core\Data\Purpose::sections) – die übrigen stehen unter „Weitere Bereiche“.
 * Alle Feldnamen wie bisher (Tables::validate, SchemaPanel, Packen großer Formulare über _pack.js); ein Formular, ein „Speichern“.
 * Bereich „Einsetzen“: Wie kommt die Tabelle auf die Website (Block, Seiten, die sie verwenden) – Aktionen unter /einsetzen.
 * @var ?array $table  @var array $def  @var array $errors  @var bool $askDrop
 */
use Core\Data\Placement;
use Core\Data\Purpose;
use Core\Data\Tables;
use Core\Pages;

$isNew = $table === null;
$s = $def['settings'] ?? [];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
$allTables = array_column(Tables::content(), 'name', 'handle');
// Eingang (verschlüsselte Anfragen): eingeschränkte Feldtypen, keine Website-/Kalender-/Verwaltungs-Einstellungen
$inbox = ($s['kind'] ?? 'content') === 'inbox';
$types = $inbox ? [...\Core\Data\DataForms::TYPES, 'file', ...Tables::LAYOUT] : array_keys(Tables::TYPES);   // Eingang: Datei nur bei Zustellung per E-Mail (Tables::validate)
// Designer: alle Elemente (Datenfelder, Abschnitte, Freitext); Auswahllisten (Titel-Feld, Kalender, Bedingungen …): nur Datenfelder
$defAll = Tables::allFields($def);
$defData = Tables::dataFields($defAll);
$empty = $isNew || !(int) Tables::db($table)->fetchValue("SELECT COUNT(*) FROM {$table['table']}");
// Geteilte Tabelle: gilt für alle beteiligten Websites; Verknüpfungen nur zu geteilten Tabellen derselben Website
$sharedT = !$isNew && Tables::isShared($table);
if ($sharedT) $allTables = array_column(array_filter(Tables::content(), fn($x) => ($x['shared']['owner'] ?? null) === $table['shared']['owner']), 'name', 'handle');
$tplPage = !$isNew && !empty($s['detail_page_id']) ? Pages::find((int) $s['detail_page_id']) : null;
$fieldOpts = fn(array $types = []) => array_filter($defData, fn($f) => !$types || in_array($f['type'], $types, true));
// Felder für die Auswahl in „Bedingungen“ (das Skript hält die Liste beim Bearbeiten aktuell)
$ruleFields = array_map(fn($f) => ['name' => (string) ($f['name'] ?? ''), 'label' => (string) ($f['label'] ?? ''), 'type' => (string) ($f['type'] ?? 'text'),
    'options' => is_array($f['options'] ?? null) ? $f['options'] : []], $defData);

// Zweck und Bereiche
$purpose = Purpose::valid($s['purpose'] ?? null) ? (string) $s['purpose'] : Purpose::derive($s);
[$main, $more] = Purpose::sections($purpose, $inbox);
if ($isNew) { $main = array_values(array_diff($main, ['einsetzen'])); $more = array_values(array_diff($more, ['einsetzen'])); }
if (!$inbox && !\Core\Features::on('calendar')) { $main = array_values(array_diff($main, ['kalender'])); $more = array_values(array_diff($more, ['kalender'])); }
$secLabel = ['allgemein' => __('Allgemein'), 'felder' => __('Felder'), 'website' => __('Auf der Website'), 'formular' => $inbox ? __('Formular & Eingang') : __('Formular'),
    'verschluesselung' => __('Verschlüsselung'), 'benachrichtigungen' => __('Benachrichtigungen'), 'suche' => __('Suche'), 'kalender' => __('Kalender'), 'einsetzen' => __('Einsetzen'), 'erweitert' => __('Erweitert')];
$secIcon = ['allgemein' => 'gear-six', 'felder' => 'list-checks', 'website' => 'browser', 'formular' => $inbox ? 'tray' : 'clipboard-text',
    'verschluesselung' => 'lock-key', 'benachrichtigungen' => 'bell-ringing', 'suche' => 'magnifying-glass', 'kalender' => 'calendar-dots', 'einsetzen' => 'puzzle-piece', 'erweitert' => 'sliders-horizontal'];
// Fehler → Bereich (Punkt in der Seitenleiste; admin.js öffnet den ersten Bereich mit Fehler)
$errSec = [];
foreach (array_keys($errors) as $k) {
    $k = (string) $k;
    $sec = match (true) {
        str_starts_with($k, 'fields') || $k === '_drop' => ['felder'],
        $k === 'settings.route' => ['website'],
        $k === 'settings.calendar' => ['kalender'],
        $k === 'settings.kind' || $k === 'settings.delivery.smime' => ['verschluesselung'],
        str_starts_with($k, 'settings.delivery') => ['formular'],
        $k === 'settings.form' => ['formular', 'benachrichtigungen'],
        default => ['allgemein'],
    };
    foreach ($sec as $x) $errSec[$x] = true;
}
// Verschlüsselt, aber noch kein zentraler Schlüssel: Warnpunkt am Bereich „Verschlüsselung“
$noKey = $inbox && !\Core\FormCrypto::ready();
$all = [...$main, ...$more];
$first = $all[0];
$posted = (string) (app()->request?->post['_tab'] ?? '');   // nach einem Fehler: zuletzt offener Bereich
if ($posted !== '' && in_array($posted, $all, true) && !$errSec) $first = $posted;
elseif ($errSec) foreach ($all as $x) if (isset($errSec[$x])) { $first = $x; break; }
$tab = fn(string $id) => '<button type="button" role="tab" id="tab-' . e($id) . '" aria-controls="panel-' . e($id) . '" data-tab="' . e($id) . '" aria-selected="' . ($id === $first ? 'true' : 'false') . '"' . ($id === $first ? '' : ' tabindex="-1"') . '>'
    . '<span class="adm-tabs__ico" aria-hidden="true">' . icon($secIcon[$id]) . '</span><span class="adm-tabs__label">' . e($secLabel[$id]) . '</span>'
    . (isset($errSec[$id]) ? '<span class="adm-dot" aria-hidden="true"> ●</span><span class="adm-sr"> – ' . e(__('mit Fehler')) . '</span>'
        : ($id === 'verschluesselung' && $noKey ? '<span class="adm-dot adm-dot--warn" aria-hidden="true"> ●</span><span class="adm-sr"> – ' . e(__('Schlüssel fehlt')) . '</span>' : '')) . '</button>';
$panel = fn(string $id, string $extra = '') => '<section class="adm-card adm-panel adm-panel--groups dt-panel" role="tabpanel" id="panel-' . e($id) . '" aria-labelledby="tab-' . e($id) . '"'
    . ($id === $first ? '' : ' hidden') . $extra . '><h2>' . e($secLabel[$id]) . '</h2>';
$sw = fn(string $name, bool $on, string $label, string $help = '', string $attrs = '') => '<div class="f f--bool"><input type="hidden" name="' . e($name) . '" value="0">'
    . '<label class="f-check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . $attrs . '> <span>' . e($label) . '</span></label>'
    . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '') . '</div>';
$fm = (array) ($s['form'] ?? []) + \Core\Data\DataForms::DEFAULTS;
?>
<?php if ($inbox): // Eingang: ohne Daten-Navigation, Kopf mit Rückweg zu den Anfragen ?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/data')) ?>">Daten</a><?= $isNew ? '' : ' · <a href="' . e(url('/admin/requests?table=' . $table['handle'])) . '">' . e($table['name']) . '</a>' ?> · <?= e(__('Eingang (verschlüsselt)')) ?></p>
    <h1><?= $isNew ? e(__('Neuer Eingang')) : 'Felder &amp; Einstellungen' ?></h1></div>
  <?php if (!$isNew): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/requests?table=' . $table['handle'])) ?>"><?= e(__('Zu den Anfragen')) ?></a><?php endif; ?>
</header>
<?php else: // Tabelle und „Zu den Einträgen“ stehen in der Daten-Navigation (data/_nav.php) ?>
<header class="adm-head dt-head">
  <h1><?php if ($isNew): ?>Neue Tabelle<?php else: ?><span aria-hidden="true" class="dt-h1icon"><?= icon($table['icon']) ?></span> <?= e($table['name']) ?> <span class="dt-h1sub">· Felder &amp; Einstellungen</span><?php endif; ?></h1>
  <?php if ($isNew): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data/new')) ?>"><?= e(__('Zum Assistenten')) ?></a><?php endif; ?>
</header>
<?php endif; ?>
<?php if (!empty($errors)): ?><div class="adm-flash adm-flash--error" role="alert">Bitte prüfen: <?= e(implode(' ', array_values($errors))) ?></div><?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/admin/data' : '/admin/data/' . $table['handle'] . '/schema')) ?>" class="dt-settings adm-tabs-form adm-tabs-form--side" data-schema data-tabs novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="<?= e($first) ?>">
  <div class="adm-tabs" role="tablist" aria-label="<?= e(__('Bereiche')) ?>">
    <?php foreach ($main as $id): ?><?= $tab($id) ?><?php endforeach; ?>
    <?php if ($more): ?><span class="adm-tabs__sep" role="separator"></span><span class="adm-tabs__cap" aria-hidden="true"><?= e(__('Weitere Bereiche')) ?></span>
    <?php foreach ($more as $id): ?><?= $tab($id) ?><?php endforeach; endif; ?>
  </div>

  <?php /* ================================================================ Allgemein */ ?>
  <?= $panel('allgemein') ?>
    <section class="set-group" aria-labelledby="t-g-table">
      <h3 class="set-group__title" id="t-g-table"><?= e($inbox ? __('Eingang') : __('Tabelle')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-name">Name (Mehrzahl) <span class="req">*</span></label>
          <input id="t-name" name="name" value="<?= e($def['name'] ?? '') ?>" placeholder="z. B. Aktuelles, Produkte" required><?= $err('name') ?></div>
        <div class="f f--inline"><label for="t-sing">Einzahl</label>
          <input id="t-sing" name="singular" value="<?= e($def['singular'] ?? '') ?>" placeholder="z. B. Beitrag"></div>
        <div class="f"><label for="t-icon"><?= e(__('Symbol')) ?></label><?= \Core\Icons::picker('t-icon', 'icon', \Core\Icons::clean($def['icon'] ?? '', null), ['suggest' => 't-name']) ?><?= $err('icon') ?></div>
        <div class="f f--inline"><label for="t-handle">Kurzname</label>
          <p class="f-help">Für API und Datenbank (<code>data_…</code>), später nicht mehr änderbar.</p>
          <?php if ($isNew): ?><input id="t-handle" name="handle" value="<?= e($def['handle'] ?? '') ?>" placeholder="wird aus dem Namen erzeugt" pattern="[a-z][a-z0-9_]+"><?php else: ?><input id="t-handle" value="<?= e($table['handle']) ?>" readonly><?php endif; ?>
          <?= $err('handle') ?></div>
        <div class="f"><label for="t-desc">Beschreibung (intern)</label><textarea id="t-desc" name="description" rows="2"><?= e($def['description'] ?? '') ?></textarea></div>
      </div>
      <?php if ($sharedT): ?>
      <p class="adm-flash adm-flash--info"><?= e(__('Geteilte Tabelle: Änderungen an Feldern und Einstellungen gelten für alle {n} beteiligten Websites. Die Detailseiten-Vorlage gestaltet jede Website selbst.', ['n' => count($table['shared']['members']) + 1])) ?></p>
      <?php endif; ?>
    </section>

    <section class="set-group" aria-labelledby="t-g-purpose">
      <h3 class="set-group__title" id="t-g-purpose"><?= e(__('Wofür ist diese Tabelle?')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-purpose"><?= e(__('Zweck')) ?></label>
          <p class="f-help"><?= e(__('Bestimmt, welche Bereiche hier zuerst erscheinen und was „Einsetzen“ vorschlägt – am Speichern der Einträge ändert er nichts.')) ?><?php if (!empty($table['purpose_derived'])): ?> <?= e(__('Aus den Einstellungen abgeleitet – wird beim nächsten Speichern übernommen.')) ?><?php endif; ?></p>
          <select id="t-purpose" name="settings[purpose]">
            <?php foreach (Purpose::all() as $pk => $px):
              if (!Purpose::fits($pk, $s) && !($inbox && in_array($pk, ['mail', 'inbox'], true) && $pk === $purpose)) continue; ?>
            <option value="<?= e($pk) ?>"<?= $pk === $purpose ? ' selected' : '' ?>><?= e($px['label']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <?php if ($inbox): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__sub"><?= e(__('„Nur per E-Mail“ und „Anfragen sammeln“ folgen der Wahl im Bereich „Verschlüsselung“.')) ?></span></div></div>
        <?php endif; ?>
        <input type="hidden" name="settings[kind]" value="<?= $inbox ? 'inbox' : 'content' ?>">
      </div>
    </section>

    <?php if (!$isNew && !$inbox && \Core\Features::on('sources') && ($srcs = array_filter(\Core\Sources\Sources::all(), fn($x) => ($x['table_handle'] ?? '') === $table['handle']))): ?>
    <section class="set-group" aria-labelledby="t-g-src">
      <h3 class="set-group__title" id="t-g-src"><?= e(__('Externe Quellen')) ?></h3>
      <div class="set-list">
        <?php foreach ($srcs as $src): ?>
        <a class="set-row set-row--link" href="<?= e(url('/admin/quellen/' . $src['id'])) ?>"><span class="set-row__main"><span class="set-row__label"><?= e((string) $src['name']) ?></span>
          <span class="set-row__sub"><?= e(__('{n} Einträge aus dieser Quelle', ['n' => (int) $src['items']])) ?></span></span></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </section>

  <?php /* ================================================================ Felder */ ?>
  <?= $panel('felder') ?>
    <?php if ($inbox): ?>
    <p class="set-page__lead"><?= e(__('Die Felder bilden das Formular. Eingänge speichern alle Angaben gemeinsam Ende-zu-Ende verschlüsselt (keine Spalte je Feld) – Felder lassen sich daher jederzeit ändern, ohne dass Anfragen verloren gehen. Keine Bilder, Verknüpfungen oder Karten; Dateifelder nur bei Zustellung per E-Mail.')) ?></p>
    <?php else: ?>
    <p class="set-page__lead">Jedes Feld wird eine Spalte der Tabelle. Reihenfolge mit ↑ ↓ ändern. Den <b>Kurznamen</b> brauchen Sie für Platzhalter wie <code>{{titel}}</code>.</p>
    <?php endif; ?>
    <div class="dt-fieldsbox">
      <ol class="dt-fields" data-fields>
        <?php foreach ($defAll as $i => $f): ?>
        <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_field.php', ['i' => $i, 'f' => $f, 'tables' => $allTables, 'err' => $errors["fields.$i"] ?? null, 'all' => $ruleFields, 'types' => $types, 'inbox' => $inbox]) ?>
        <?php endforeach; ?>
      </ol>
      <template data-field-template><?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_field.php', ['i' => '__i__', 'f' => ['type' => 'text', 'label' => '', 'name' => ''], 'tables' => $allTables, 'err' => null, 'all' => $ruleFields, 'types' => $types, 'inbox' => $inbox]) ?></template>
      <template data-rule-template="cond"><?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_rule.php', ['p' => '__P__', 'r' => [], 'kind' => 'cond', 'all' => [], 'self' => '']) ?></template>
      <template data-rule-template="cmp"><?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_rule.php', ['p' => '__P__', 'r' => [], 'kind' => 'cmp', 'all' => [], 'self' => '']) ?></template>
      <div class="dt-addfield">
        <span class="adm-muted">Feld hinzufügen:</span>
        <?php foreach (Tables::TYPES as $type => [$label, , $icon]): if (!in_array($type, $types, true)) continue; ?>
        <button type="button" class="dt-typebtn" data-add-field="<?= e($type) ?>" title="<?= e($label) ?>"><?= icon($icon) ?> <?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <?= $err('fields') ?>
      <?php if (!empty($askDrop)): ?>
      <label class="f-check dt-confirmdrop"><input type="checkbox" name="confirm_drop" value="1"> <span><b>Felder wirklich löschen</b> (Inhalte gehen verloren)</span></label>
      <?php endif; ?>
    </div>
  </section>

<?php if (!$inbox): /* ================================================================ Auf der Website */ ?>
  <?= $panel('website') ?>
    <section class="set-group" aria-labelledby="t-g-detail">
      <h3 class="set-group__title" id="t-g-detail"><?= e(__('Detailseiten')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-route">Adresse der Detailseiten</label>
          <p class="f-help">Einträge erscheinen unter <code>/<?= e(($s['route'] ?? '') ?: 'adresse') ?>/titel-des-eintrags</code>. Leer = keine Detailseiten.</p>
          <div class="adm-prefix"><span>/</span><input id="t-route" name="settings[route]" value="<?= e($s['route'] ?? '') ?>" placeholder="z. B. aktuelles"></div><?= $err('settings.route') ?></div>
        <div class="f f--bool"><label class="f-check"><input type="checkbox" name="settings[noindex]" value="1"<?= !empty($s['noindex']) ? ' checked' : '' ?>> <span><?= e(__('Detailseiten nicht indexieren (nicht in Suchmaschinen, Sitemap und llms.txt)')) ?></span></label></div>
        <?php if ($isNew): ?>
        <div class="f f--bool"><label class="f-check"><input type="checkbox" name="with_template" value="1" checked> <span>Detailseiten-Vorlage gleich mit anlegen</span></label></div>
        <?php elseif ($s['route'] ?? ''): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Detailseiten-Vorlage')) ?></span>
          <span class="set-row__sub"><?= $tplPage ? 'Die Vorlage gilt für alle Einträge. Gestaltet wird sie wie jede Seite – mit echten Inhalten eines Eintrags.' : 'Noch keine Vorlage vorhanden.' ?></span></div>
          <div class="set-row__ctl"><button class="adm-btn adm-btn--small" form="tplform"><?= $tplPage ? 'Detailseite gestalten ↗' : 'Vorlage anlegen' ?></button></div></div>
        <?php endif; ?>
      </div>
    </section>
    <?php $sel = function (string $name, string $label, array $opts, string $help = '') use ($s) {
        $h = '<div class="f f--inline"><label for="t-' . $name . '">' . e($label) . '</label>' . ($help ? '<p class="f-help">' . e($help) . '</p>' : '')
            . '<select id="t-' . $name . '" name="settings[' . $name . ']"><option value="">– automatisch –</option>';
        foreach ($opts as $f) $h .= '<option value="' . e($f['name']) . '"' . (($s[$name] ?? '') === $f['name'] ? ' selected' : '') . '>' . e($f['label']) . '</option>';
        return $h . '</select></div>';
    }; ?>
    <section class="set-group" aria-labelledby="t-g-show">
      <h3 class="set-group__title" id="t-g-show"><?= e(__('Darstellung & Suchmaschinen')) ?></h3>
      <div class="set-list">
        <?= $sel('title_field', 'Titel-Feld', $fieldOpts(['text', 'textarea']), 'Überschrift und Name in Listen und Adresse.') ?>
        <?= $sel('image_field', 'Bild-Feld', $fieldOpts(['media']), 'Vorschaubild in Karten und beim Teilen.') ?>
        <div class="f f--inline"><label for="t-list_image"><?= e(__('Bilder in der Eintragsliste')) ?></label>
          <p class="f-help"><?= e(__('Gilt für das Bild-Feld neben dem Titel und für Bildfelder, die als Spalte „In der Liste zeigen“ aktiv haben.')) ?></p>
          <select id="t-list_image" name="settings[list_image]">
            <?php foreach (['small' => __('Klein neben dem Titel'), 'large' => __('Groß neben dem Titel'), 'none' => __('Keine Bilder')] as $lk => $ll): ?>
            <option value="<?= $lk ?>"<?= ($s['list_image'] ?? 'small') === $lk ? ' selected' : '' ?>><?= e($ll) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="f f--inline"><label for="t-schema_type"><?= e(__('Strukturierte Daten (schema.org)')) ?></label>
          <p class="f-help"><?= e(__('Für Suchmaschinen: So wird die Detailseite eines Eintrags beschrieben. Titel, Bild, Beschreibung, Datum, Preis, Funktion usw. werden automatisch aus den passenden Feldern übernommen.')) ?></p>
          <select id="t-schema_type" name="settings[schema_type]">
            <?php foreach (\Core\StructuredData::TYPES as $sk => $sl): ?><option value="<?= e($sk) ?>"<?= ($s['schema_type'] ?? '') === $sk ? ' selected' : '' ?>><?= e(__($sl)) ?></option><?php endforeach; ?>
          </select></div>
        <?= $sel('description_field', 'Beschreibung für Suchmaschinen', $fieldOpts(['text', 'textarea', 'richtext'])) ?>
      </div>
    </section>

    <?php if (($s['schema_type'] ?? '') === \Core\Data\Jobs::TYPE): $jc = (array) ($s['jobs'] ?? []) + \Core\Data\Jobs::DEFAULTS; $inboxes = \Core\Data\Inbox::available() ? \Core\Data\Inbox::tables() : []; ?>
    <section class="set-group" id="stellen" aria-labelledby="t-g-jobs">
      <h3 class="set-group__title" id="t-g-jobs"><?= e(__('Stellenangebote & Bewerbung')) ?></h3>
      <p class="set-group__note"><?= e(__('Diese Tabelle beschreibt Stellen für Google for Jobs (JobPosting). Abgelaufene Stellen („Gültig bis“ vor heute) verschwinden automatisch aus Listen, Sitemap und Google; ihre Seite zeigt „nicht mehr ausgeschrieben“.')) ?></p>
      <div class="set-list">
        <div class="f f--inline"><label for="t-jobs-form"><?= e(__('Bewerbungsformular')) ?></label>
          <p class="f-help"><?= e(__('Eingang, dessen Formular auf jeder Stellenseite erscheint (Block „Stelle: Bewerbung“). Das Feld „Stelle“ ist dort schon ausgefüllt und gesperrt; der Betreff der E-Mail nennt die Stelle.')) ?></p>
          <select id="t-jobs-form" name="settings[jobs][form]">
            <option value=""><?= e(__('– kein Formular –')) ?></option>
            <?php if ($isNew || $jc['form'] === '_new'): ?><option value="_new"<?= $jc['form'] === '_new' ? ' selected' : '' ?>><?= e(Core\Data\Tables::find('bewerbungen') ? __('Eingang „Bewerbungen“ nutzen (Feld „Stelle“ wird ergänzt)') : __('Neuen Eingang „Bewerbungen“ anlegen')) ?></option><?php endif; ?>
            <?php foreach ($inboxes as $ib): ?><option value="<?= e($ib['handle']) ?>"<?= $jc['form'] === $ib['handle'] ? ' selected' : '' ?>><?= e($ib['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <input type="hidden" name="settings[jobs][field]" value="<?= e((string) $jc['field']) ?>">
      <?php foreach ((array) $jc['map'] as $role => $fname): ?><input type="hidden" name="settings[jobs][map][<?= e((string) $role) ?>]" value="<?= e((string) $fname) ?>"><?php endforeach; ?>
    </section>
    <?php endif; ?>
  </section>
<?php endif; ?>

  <?php /* ================================================================ Formular (& Eingang) */ ?>
  <?= $panel('formular') ?>
<?php if ($inbox): ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_inbox.php', ['s' => $s, 'def' => $def, 'table' => $table, 'err' => $err, 'part' => 'form']) ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_inbox.php', ['s' => $s, 'def' => $def, 'table' => $table, 'err' => $err, 'part' => 'delivery']) ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_inbox.php', ['s' => $s, 'def' => $def, 'table' => $table, 'err' => $err, 'part' => 'privacy']) ?>
<?php elseif (\Core\Data\DataForms::available()): $fmAll = array_filter($defData, fn($f) => \Core\Data\DataForms::eligible($f, true)); ?>
    <section class="set-group dt-form" data-form-settings aria-labelledby="t-g-form">
      <h3 class="set-group__title" id="t-g-form"><?= e(__('Öffentliches Formular')) ?></h3>
      <div class="set-list">
        <?= $sw('settings[form][enabled]', (bool) $fm['enabled'], __('Besucher können Einträge anlegen'), __('Auf einer Seite mit dem Block „Formular (Datentabelle)“ einfügen. Mehrstufiger Spamschutz, ohne Cookies.'), ' data-form-toggle') ?>
      </div>
      <div class="dt-form__opts" data-form-opts<?= $fm['enabled'] ? '' : ' hidden' ?>>
        <div class="set-list">
          <fieldset class="f f--multi dt-form__fields"><legend><?= e(__('Felder im Formular')) ?></legend>
            <input type="hidden" name="settings[form][fields][]" value="">
            <?php if (!$fmAll): ?><p class="f-help"><?= e(__('Neue Felder erscheinen hier nach dem Speichern.')) ?></p><?php endif; ?>
            <div class="f-multi">
            <?php foreach ($fmAll as $f): $upl = in_array($f['type'], \Core\Data\DataForms::UPLOAD_TYPES, true); ?>
            <label class="f-check"<?= $upl ? ' data-form-upload' : '' ?>><input type="checkbox" name="settings[form][fields][]" value="<?= e($f['name']) ?>"<?= !$fm['fields'] || in_array($f['name'], $fm['fields'], true) || !empty($f['required']) ? ' checked' : '' ?><?= !empty($f['required']) ? ' disabled' : '' ?>><?php if (!empty($f['required'])): ?><input type="hidden" name="settings[form][fields][]" value="<?= e($f['name']) ?>"><?php endif; ?>
              <span><?= e($f['label']) ?><?= !empty($f['required']) ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?><?= $upl ? ' <small class="adm-muted">(' . e(__('nur mit Datei-Uploads')) . ')</small>' : '' ?></span></label>
            <?php endforeach; ?>
            </div>
            <p class="f-help"><?= e(__('Nichts angehakt = alle passenden Felder. Verknüpfungen, formatierter Text, Karte, Links und Wiederholungen füllt nur die Redaktion aus.')) ?></p>
          </fieldset>
          <div class="f f--inline"><label for="t-form-status"><?= e(__('Neue Einträge')) ?></label>
            <select id="t-form-status" name="settings[form][status]">
              <option value="draft"<?= $fm['status'] !== 'published' ? ' selected' : '' ?>><?= e(__('als Entwurf – Redaktion prüft und veröffentlicht')) ?></option>
              <option value="published"<?= $fm['status'] === 'published' ? ' selected' : '' ?>><?= e(__('sofort veröffentlichen')) ?></option>
            </select></div>
          <div class="f f--inline"><label for="t-form-max"><?= e(__('Obergrenze (Einträge)')) ?></label>
            <p class="f-help"><?= e(__('Z. B. Plätze einer Anmeldung: Sind so viele Einträge da (auch Entwürfe), zeigt das Formular „ausgebucht“. 0 = keine Grenze.')) ?><?php if (!$isNew && (int) $fm['max'] > 0): ?> <?= e(__('Belegt: {n} von {max}.', ['n' => \Core\Data\Entries::count($table, ['status' => 'all', 'lang' => 'all', 'source' => 'own']), 'max' => (int) $fm['max']])) ?><?php endif; ?></p>
            <input type="number" id="t-form-max" name="settings[form][max]" min="0" max="<?= \Core\Data\DataForms::MAX_ENTRIES ?>" value="<?= (int) $fm['max'] ?>"></div>
          <div class="f"><label for="t-form-success"><?= e(__('Text nach dem Absenden')) ?></label>
            <textarea id="t-form-success" name="settings[form][success]" rows="2" placeholder="<?= e(__('Vielen Dank – Ihre Angaben sind eingegangen.')) ?>"><?= e($fm['success']) ?></textarea></div>
          <div class="f f--inline"><label for="t-form-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
            <input id="t-form-submit" name="settings[form][submit]" value="<?= e($fm['submit']) ?>" placeholder="<?= e(__('Absenden')) ?>"></div>
          <?= $sw('settings[form][uploads]', (bool) $fm['uploads'], __('Datei-Uploads erlauben (Bild- und Datei-Felder)')) ?>
          <div class="f f--inline"><label for="t-form-mb"><?= e(__('Höchstgröße je Datei (MB)')) ?></label>
            <p class="f-help"><?= e(__('Bildfelder: JPG, PNG, WebP. Dateifelder: PDF und/oder Bilder – einstellbar beim Feld. Hochgeladene Dateien landen in der Mediathek (Tag „formular“) und sind über ihre Adresse erreichbar.')) ?></p>
            <input type="number" id="t-form-mb" name="settings[form][upload_mb]" min="1" max="<?= \Core\Data\DataForms::MAX_MB ?>" value="<?= (int) $fm['upload_mb'] ?>"></div>
        </div>
        <p class="dt-note"><?= e(__('Einträge werden nicht verschlüsselt gespeichert. Für vertrauliche Angaben (z. B. Gesundheitsdaten) einen verschlüsselten Eingang (Vorlage „Anfragen“) nutzen.')) ?></p>
      </div>
      <?= $err('settings.form') ?>
    </section>
<?php else: ?>
    <p class="set-page__lead"><?= e(__('Öffentliche Formulare für Datentabellen sind auf dieser Website aus (Funktionen & Erweiterungen → „Formulare für Datentabellen“).')) ?></p>
<?php endif; ?>
  </section>

  <?php /* ================================================================ Verschlüsselung */ ?>
  <?= $panel('verschluesselung') ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_crypto.php', ['s' => $s, 'table' => $table, 'err' => $err, 'empty' => $empty, 'sharedT' => $sharedT]) ?>
  </section>

  <?php /* ================================================================ Benachrichtigungen */ ?>
  <?= $panel('benachrichtigungen') ?>
<?php if ($inbox): ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_inbox.php', ['s' => $s, 'def' => $def, 'table' => $table, 'err' => $err, 'part' => 'notify']) ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_receipt.php', ['fm' => $fm, 'fields' => $defData]) ?>
<?php else: ?>
    <?php if (\Core\Data\DataForms::available()): ?>
    <section class="set-group" aria-labelledby="t-g-notify">
      <h3 class="set-group__title" id="t-g-notify"><?= e(__('Neue Einträge über das Formular')) ?></h3>
      <?php if (!$fm['enabled']): ?><p class="set-group__note"><?= e(__('Gilt, sobald das öffentliche Formular eingeschaltet ist (Bereich „Formular“).')) ?></p><?php endif; ?>
      <div class="set-list">
        <div class="f f--inline"><label for="t-form-notify"><?= e(__('Benachrichtigung an (E-Mail)')) ?></label>
          <p class="f-help"><?= e(__('Mehrere Adressen mit Komma trennen. Die E-Mail enthält keine Inhalte – nur einen Link zum neuen Eintrag.')) ?></p>
          <input id="t-form-notify" name="settings[form][notify]" value="<?= e($fm['notify']) ?>" placeholder="<?= e(__('leer = Empfänger aus den Grundeinstellungen')) ?>" autocomplete="off"></div>
      </div>
    </section>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_receipt.php', ['fm' => $fm, 'fields' => $defData]) ?>
    <?php endif; ?>
    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_push_settings.php', ['table' => $table, 'def' => $def]) /* Push: Besucher abonnieren neue Einträge (Core\Push\Topics) */ ?>
<?php endif; ?>
  </section>

<?php if (!$inbox): ?>
  <?php /* ================================================================ Suche */ ?>
  <?= $panel('suche') ?>
    <?php if (\Core\Features::on('search')): ?>
    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_search_settings.php', ['table' => $table, 'def' => $def]) /* Website-Suche je Tabelle */ ?>
    <?php else: ?>
    <p class="set-page__lead"><?= e(__('Die Website-Suche ist auf dieser Website aus (Funktionen & Erweiterungen).')) ?></p>
    <?php endif; ?>
  </section>

  <?php if (\Core\Features::on('calendar')): $c = (array) ($s['calendar'] ?? []) + \Core\Data\Calendar::DEFAULTS;
    $calSel = function (string $key, string $label, bool $optional = true, string $help = '') use ($c, $fieldOpts) {
        $h = '<div class="f f--inline"><label for="t-cal-' . $key . '">' . e($label) . '</label>' . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '')
            . '<select id="t-cal-' . $key . '" name="settings[calendar][' . $key . ']">'
            . '<option value="">' . e($optional ? __('– keins –') : __('– wählen –')) . '</option>';
        foreach ($fieldOpts(\Core\Data\Calendar::MAP[$key]) as $f) $h .= '<option value="' . e($f['name']) . '"' . ($c[$key] === $f['name'] ? ' selected' : '') . '>' . e($f['label']) . '</option>';
        return $h . '</select></div>';
    }; ?>
  <?php /* ================================================================ Kalender */ ?>
  <?= $panel('kalender') ?>
    <section class="set-group dt-cal" data-cal-settings aria-labelledby="t-g-cal">
      <h3 class="set-group__title" id="t-g-cal"><?= e(__('Kalender')) ?></h3>
      <div class="set-list">
        <div class="f f--bool"><input type="hidden" name="settings[calendar][enabled]" value="0">
          <label class="f-check"><input type="checkbox" name="settings[calendar][enabled]" value="1"<?= $c['enabled'] ? ' checked' : '' ?> data-cal-toggle> <span><?= e(__('Als Kalender nutzen')) ?></span></label>
          <p class="f-help"><?= e(__('Einträge werden zu Terminen: Kalender-Block, „Nächste Termine“, iCal-Abo und Wiederholungen.')) ?></p></div>
      </div>
      <div class="dt-cal__map" data-cal-map<?= $c['enabled'] ? '' : ' hidden' ?>>
        <div class="set-list">
          <?= $calSel('start', __('Beginn'), false, __('Feld vom Typ „Datum & Uhrzeit“ oder „Datum“ (= ganztägig).')) ?>
          <?= $calSel('end', __('Ende (optional)')) ?>
          <div class="f f--inline"><label for="t-cal-duration"><?= e(__('Dauer ohne Ende (Minuten)')) ?></label>
            <p class="f-help"><?= e(__('Gilt, wenn bei einem Termin kein Ende eingetragen ist.')) ?></p>
            <input type="number" id="t-cal-duration" name="settings[calendar][duration]" min="0" max="10080" step="5" value="<?= (int) $c['duration'] ?>"></div>
          <?= $calSel('all_day', __('Ganztägig (optional)')) ?>
          <?= $calSel('recurrence', __('Wiederholung (optional)'), true, __('Feld vom Typ „Wiederholung“.')) ?>
          <?= $calSel('location', __('Ort (optional)')) ?>
          <?= $calSel('description', __('Beschreibung (optional)'), true, __('Für iCal und Kalender-Apps (als reiner Text).')) ?>
          <?= $calSel('category', __('Kategorie (optional)'), true, __('Auswahlfeld – zum Filtern und als Kategorie in Kalender-Apps.')) ?>
          <div class="f f--bool"><input type="hidden" name="settings[calendar][feed]" value="0">
            <label class="f-check"><input type="checkbox" name="settings[calendar][feed]" value="1"<?= $c['feed'] ? ' checked' : '' ?>> <span><?= e(__('Öffentlicher iCal-Feed (Abonnieren)')) ?></span></label>
            <?php if (!$isNew && $c['feed']): ?><p class="f-help"><code><?= e(\Core\Data\Calendar::feedUrls($table)['https']) ?></code></p><?php endif; ?></div>
        </div>
      </div>
      <?= $err('settings.calendar') ?>
    </section>
  </section>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$isNew): /* ================================================================ Einsetzen */
  $ptype = Placement::blockType($table);
  $uses = Placement::usages($table); ?>
  <?= $panel('einsetzen', ' data-nosave') ?>
    <section class="set-group dt-place" aria-labelledby="t-g-place">
      <h3 class="set-group__title" id="t-g-place"><?= e(__('So kommt „{name}“ auf die Website', ['name' => $table['name']])) ?></h3>
      <div class="set-list">
        <div class="set-row set-row--stack"><div class="set-row__body">
          <?php if ($ptype === 'data_form'): ?>
          <p><?= e(__('Block „Formular (Datentabelle)“ auf einer Seite einfügen und darin die Tabelle „{name}“ wählen. Felder, Zustellung und Bestätigung kommen aus diesen Einstellungen.', ['name' => $table['name']])) ?></p>
          <?php elseif ($ptype === 'data_list'): ?>
          <p><?= e(__('Block „Datenliste“ auf einer Seite einfügen und darin die Tabelle „{name}“ wählen.', ['name' => $table['name']])) ?><?= ($s['route'] ?? '') !== '' ? ' ' . e(__('Detailseiten entstehen automatisch unter /{route}/…', ['route' => $s['route']])) : '' ?></p>
          <?php else: ?>
          <p><?= e(__('Interne Liste – erscheint nicht auf der Website. Unter „Allgemein → Zweck“ lässt sich das ändern.')) ?></p>
          <?php endif; ?>
        </div></div>
        <?php if ($ptype): ?>
        <a class="set-row set-row--link" href="<?= e(url('/admin/data/' . $table['handle'] . '/einsetzen')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e($ptype === 'data_form' ? __('Seite mit Formular anlegen oder in eine Seite einfügen') : __('Seite mit Liste anlegen oder in eine Seite einfügen')) ?></span>
          <span class="set-row__sub"><?= e(__('Mit Auswahl im Seitenbaum, Menü und Anleitung')) ?></span></span></a>
        <?php endif; ?>
      </div>
    </section>
    <section class="set-group" aria-labelledby="t-g-uses">
      <h3 class="set-group__title" id="t-g-uses"><?= e(__('Verwendet auf')) ?></h3>
      <div class="set-list">
        <?php if (!$uses): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Noch auf keiner Seite')) ?></span></div></div>
        <?php else: foreach ($uses as $u): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($u['title']) ?><?= $u['status'] !== 'published' ? ' <span class="adm-badge adm-badge--muted">' . e(__('Entwurf')) . '</span>' : '' ?></span>
          <span class="set-row__sub"><?= e(implode(' · ', $u['blocks'])) ?></span></div>
          <div class="set-row__ctl"><?php if ($u['edit']): ?><a href="<?= e($u['edit']) ?>"><?= e($u['template'] ? __('Vorlage gestalten') : __('Bearbeiten')) ?></a><?php endif; ?></div></div>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </section>
<?php endif; ?>

  <?php /* ================================================================ Erweitert */ ?>
  <?= $panel('erweitert') ?>
<?php if (!$inbox): ?>
    <section class="set-group" aria-labelledby="t-g-admin">
      <h3 class="set-group__title" id="t-g-admin"><?= e(__('Verwaltung & Freigabe')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-sort">Sortierung</label><select id="t-sort" name="settings[sort_field]">
          <?php foreach (['sort' => 'Manuell (ziehen)', 'published_at' => 'Veröffentlicht am', 'created_at' => 'Angelegt am'] + array_column($fieldOpts(['text', 'date', 'datetime', 'time', 'number', 'select']), 'label', 'name') as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= ($s['sort_field'] ?? 'sort') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="f f--inline"><label for="t-dir">Richtung</label><select id="t-dir" name="settings[sort_dir]">
          <option value="asc"<?= ($s['sort_dir'] ?? 'asc') === 'asc' ? ' selected' : '' ?>>aufsteigend</option>
          <option value="desc"<?= ($s['sort_dir'] ?? '') === 'desc' ? ' selected' : '' ?>>absteigend</option></select></div>
        <?= $sw('settings[workflow]', (bool) ($s['workflow'] ?? true), 'Entwurf / Online je Eintrag', __('Aus: Einträge sind sofort online, ohne Freigabe.')) ?>
        <input type="hidden" name="settings[per_page]" value="<?= (int) ($s['per_page'] ?? 50) ?>">
      </div>
    </section>
<?php endif; ?>
<?php if (!$isNew && !$sharedT): ?>
    <section class="set-group dt-danger" aria-labelledby="t-g-del">
      <h3 class="set-group__title" id="t-g-del"><?= e($inbox ? __('Eingang löschen') : __('Tabelle löschen')) ?></h3>
      <div class="set-list">
        <div class="set-row set-row--stack"><div class="set-row__body">
          <p><?= $inbox ? e(__('Löscht den Eingang mit allen (verschlüsselten) Anfragen. Formulare auf Seiten zeigen dann nichts mehr an. Zum Bestätigen den Kurznamen eintippen:')) : 'Löscht die Tabelle mit <b>allen Einträgen</b> und die Detailseiten-Vorlage. Datenlisten auf Seiten bleiben leer. Zum Bestätigen den Kurznamen eintippen:' ?></p>
          <div class="adm-row"><input form="dt-delete" name="confirm" placeholder="<?= e($table['handle']) ?>" aria-label="Kurzname zur Bestätigung" autocomplete="off"><button class="adm-btn adm-btn--danger" form="dt-delete" type="submit">Endgültig löschen</button></div>
        </div></div>
      </div>
    </section>
<?php elseif ($isNew && $inbox): ?>
    <p class="set-page__lead"><?= e(__('Weitere Einstellungen gibt es nach dem Anlegen.')) ?></p>
<?php endif; ?>
  </section>

  <div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit"><?= $isNew ? 'Tabelle anlegen' : 'Speichern' ?></button></div>
</form>

<?php if (!$isNew): ?>
<form id="tplform" method="post" action="<?= e(url('/admin/data/' . $table['handle'] . '/template')) ?>"><?= csrf_field() ?></form>
<?php endif; ?>
<?php if (!$isNew && !$sharedT): ?>
<form id="dt-delete" method="post" action="<?= e(url('/admin/data/' . $table['handle'] . '/destroy')) ?>"><?= csrf_field() ?></form>
<?php endif; ?>
