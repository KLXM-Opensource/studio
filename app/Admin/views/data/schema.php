<?php
/**
 * Tabellen-Designer: Felder und Einstellungen.
 * @var ?array $table  @var array $def  @var array $errors  @var bool $askDrop
 */
use Core\Data\Tables;
use Core\Pages;

$isNew = $table === null;
$s = $def['settings'] ?? [];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
$allTables = array_column(Tables::content(), 'name', 'handle');
// Eingang (verschlüsselte Anfragen): eingeschränkte Feldtypen, keine Website-/Kalender-/Verwaltungs-Einstellungen
$inbox = ($s['kind'] ?? 'content') === 'inbox';
$types = $inbox ? [...\Core\Data\DataForms::TYPES, 'file'] : array_keys(Tables::TYPES);   // Eingang: Datei nur bei Zustellung per E-Mail (Tables::validate)
$empty = $isNew || !(int) Tables::db($table)->fetchValue("SELECT COUNT(*) FROM {$table['table']}");
// Geteilte Tabelle: gilt für alle beteiligten Websites; Verknüpfungen nur zu geteilten Tabellen derselben Website
$sharedT = !$isNew && Tables::isShared($table);
if ($sharedT) $allTables = array_column(array_filter(Tables::content(), fn($x) => ($x['shared']['owner'] ?? null) === $table['shared']['owner']), 'name', 'handle');
$tplPage = !$isNew && !empty($s['detail_page_id']) ? Pages::find((int) $s['detail_page_id']) : null;
$fieldOpts = fn(array $types = []) => array_filter($def['fields'] ?? [], fn($f) => !$types || in_array($f['type'], $types, true));
// Felder für die Auswahl in „Bedingungen“ (das Skript hält die Liste beim Bearbeiten aktuell)
$ruleFields = array_map(fn($f) => ['name' => (string) ($f['name'] ?? ''), 'label' => (string) ($f['label'] ?? ''), 'type' => (string) ($f['type'] ?? 'text'),
    'options' => is_array($f['options'] ?? null) ? $f['options'] : []], array_values($def['fields'] ?? []));
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
</header>
<?php endif; ?>
<?php if (!empty($errors)): ?><div class="adm-flash adm-flash--error" role="alert">Bitte prüfen: <?= e(implode(' ', array_values($errors))) ?></div><?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/admin/data' : '/admin/data/' . $table['handle'] . '/schema')) ?>" class="dt-schema" data-schema novalidate>
  <?= csrf_field() ?>
  <div class="dt-schema__main">
    <section class="adm-card">
      <h2>Felder</h2>
      <?php if ($inbox): ?>
      <p class="adm-muted"><?= e(__('Die Felder bilden das Formular. Eingänge speichern alle Angaben gemeinsam Ende-zu-Ende verschlüsselt (keine Spalte je Feld) – Felder lassen sich daher jederzeit ändern, ohne dass Anfragen verloren gehen. Keine Bilder, Verknüpfungen oder Karten; Dateifelder nur bei Zustellung per E-Mail.')) ?></p>
      <?php else: ?>
      <p class="adm-muted">Jedes Feld wird eine Spalte der Tabelle. Reihenfolge mit ↑ ↓ ändern. Den <b>Kurznamen</b> brauchen Sie für Platzhalter wie <code>{{titel}}</code>.</p>
      <?php endif; ?>
      <ol class="dt-fields" data-fields>
        <?php foreach (array_values($def['fields'] ?? []) as $i => $f): ?>
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
    </section>
  </div>

  <aside class="dt-schema__side">
    <section class="adm-card">
      <h2>Tabelle</h2>
      <div class="f"><label for="t-name">Name (Mehrzahl) <span class="req">*</span></label><input id="t-name" name="name" value="<?= e($def['name'] ?? '') ?>" placeholder="z. B. Aktuelles, Produkte" required><?= $err('name') ?></div>
      <div class="adm-grid2">
        <div class="f"><label for="t-sing">Einzahl</label><input id="t-sing" name="singular" value="<?= e($def['singular'] ?? '') ?>" placeholder="z. B. Beitrag"></div>
        <div class="f"><label for="t-icon"><?= e(__('Symbol')) ?></label><?= \Core\Icons::picker('t-icon', 'icon', \Core\Icons::clean($def['icon'] ?? '', null), ['suggest' => 't-name']) ?><?= $err('icon') ?></div>
      </div>
      <div class="f"><label for="t-handle">Kurzname</label>
        <?php if ($isNew): ?><input id="t-handle" name="handle" value="<?= e($def['handle'] ?? '') ?>" placeholder="wird aus dem Namen erzeugt" pattern="[a-z][a-z0-9_]+"><?php else: ?><input id="t-handle" value="<?= e($table['handle']) ?>" readonly><?php endif; ?>
        <p class="f-help">Für API und Datenbank (<code>data_…</code>), später nicht mehr änderbar.</p><?= $err('handle') ?></div>
      <div class="f"><label for="t-desc">Beschreibung (intern)</label><textarea id="t-desc" name="description" rows="2"><?= e($def['description'] ?? '') ?></textarea></div>
      <?php if ($sharedT): ?>
      <p class="adm-flash adm-flash--info"><?= e(__('Geteilte Tabelle: Änderungen an Feldern und Einstellungen gelten für alle {n} beteiligten Websites. Die Detailseiten-Vorlage gestaltet jede Website selbst.', ['n' => count($table['shared']['members']) + 1])) ?></p>
      <?php endif; ?>
      <?php if ($empty && !$sharedT && \Core\Data\Inbox::available()): ?>
      <div class="f"><label for="t-kind"><?= e(__('Art der Tabelle')) ?></label>
        <select id="t-kind" name="settings[kind]">
          <option value="content"<?= !$inbox ? ' selected' : '' ?>><?= e(__('Inhalte (erscheinen auf der Website)')) ?></option>
          <option value="inbox"<?= $inbox ? ' selected' : '' ?>><?= e(__('Eingang (verschlüsselte Anfragen)')) ?></option>
        </select>
        <p class="f-help"><?= e(__('Eingang: Einträge entstehen nur über das Formular und werden Ende-zu-Ende verschlüsselt (z. B. Gesundheitsdaten). Nur änderbar, solange die Tabelle leer ist; die passenden Einstellungen erscheinen nach dem Speichern.')) ?></p><?= $err('settings.kind') ?></div>
      <?php else: ?>
      <input type="hidden" name="settings[kind]" value="<?= $inbox ? 'inbox' : 'content' ?>">
      <?php endif; ?>
    </section>
<?php if ($inbox): ?>
    <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_inbox.php', ['s' => $s, 'def' => $def, 'table' => $table, 'err' => $err]) ?>
<?php else: ?>

    <section class="adm-card">
      <h2>Website</h2>
      <div class="f"><label for="t-route">Adresse der Detailseiten</label>
        <div class="adm-prefix"><span>/</span><input id="t-route" name="settings[route]" value="<?= e($s['route'] ?? '') ?>" placeholder="z. B. aktuelles"></div>
        <p class="f-help">Einträge erscheinen unter <code>/<?= e(($s['route'] ?? '') ?: 'adresse') ?>/titel-des-eintrags</code>. Leer = keine Detailseiten.</p><?= $err('settings.route') ?></div>
      <label class="f-check"><input type="checkbox" name="settings[noindex]" value="1"<?= !empty($s['noindex']) ? ' checked' : '' ?>> <span><?= e(__('Detailseiten nicht indexieren (nicht in Suchmaschinen, Sitemap und llms.txt)')) ?></span></label>
      <?php if ($isNew): ?>
      <label class="f-check"><input type="checkbox" name="with_template" value="1" checked> <span>Detailseiten-Vorlage gleich mit anlegen</span></label>
      <?php elseif ($s['route'] ?? ''): ?>
      <div class="adm-inline-box">
        <strong>Detailseite</strong>
        <p class="adm-muted"><?= $tplPage ? 'Die Vorlage gilt für alle Einträge. Gestaltet wird sie wie jede Seite – mit echten Inhalten eines Eintrags.' : 'Noch keine Vorlage vorhanden.' ?></p>
        <button class="adm-btn adm-btn--small" form="tplform"><?= $tplPage ? 'Detailseite gestalten ↗' : 'Vorlage anlegen' ?></button>
      </div>
      <?php endif; ?>
      <?php $sel = function (string $name, string $label, array $opts, string $help = '') use ($s) {
          $h = '<div class="f"><label for="t-' . $name . '">' . e($label) . '</label><select id="t-' . $name . '" name="settings[' . $name . ']"><option value="">– automatisch –</option>';
          foreach ($opts as $f) $h .= '<option value="' . e($f['name']) . '"' . (($s[$name] ?? '') === $f['name'] ? ' selected' : '') . '>' . e($f['label']) . '</option>';
          return $h . '</select>' . ($help ? '<p class="f-help">' . e($help) . '</p>' : '') . '</div>';
      }; ?>
      <?= $sel('title_field', 'Titel-Feld', $fieldOpts(['text', 'textarea']), 'Überschrift und Name in Listen und Adresse.') ?>
      <?= $sel('image_field', 'Bild-Feld', $fieldOpts(['media']), 'Vorschaubild in Karten und beim Teilen.') ?>
      <div class="f"><label for="t-list_image"><?= e(__('Bilder in der Eintragsliste')) ?></label>
        <select id="t-list_image" name="settings[list_image]">
          <?php foreach (['small' => __('Klein neben dem Titel'), 'large' => __('Groß neben dem Titel'), 'none' => __('Keine Bilder')] as $lk => $ll): ?>
          <option value="<?= $lk ?>"<?= ($s['list_image'] ?? 'small') === $lk ? ' selected' : '' ?>><?= e($ll) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="f-help"><?= e(__('Gilt für das Bild-Feld neben dem Titel und für Bildfelder, die als Spalte „In der Liste zeigen“ aktiv haben.')) ?></p></div>
      <div class="f"><label for="t-schema_type"><?= e(__('Strukturierte Daten (schema.org)')) ?></label>
        <select id="t-schema_type" name="settings[schema_type]">
          <?php foreach (\Core\StructuredData::TYPES as $sk => $sl): ?><option value="<?= e($sk) ?>"<?= ($s['schema_type'] ?? '') === $sk ? ' selected' : '' ?>><?= e(__($sl)) ?></option><?php endforeach; ?>
        </select>
        <p class="f-help"><?= e(__('Für Suchmaschinen: So wird die Detailseite eines Eintrags beschrieben. Titel, Bild, Beschreibung, Datum, Preis, Funktion usw. werden automatisch aus den passenden Feldern übernommen.')) ?></p></div>
      <?= $sel('description_field', 'Beschreibung für Suchmaschinen', $fieldOpts(['text', 'textarea', 'richtext'])) ?>
    </section>

    <?php if (($s['schema_type'] ?? '') === \Core\Data\Jobs::TYPE): $jc = (array) ($s['jobs'] ?? []) + \Core\Data\Jobs::DEFAULTS; $inboxes = \Core\Data\Inbox::available() ? \Core\Data\Inbox::tables() : []; ?>
    <section class="adm-card" id="stellen">
      <h2><?= e(__('Stellenangebote & Bewerbung')) ?></h2>
      <p class="f-help"><?= e(__('Diese Tabelle beschreibt Stellen für Google for Jobs (JobPosting). Abgelaufene Stellen („Gültig bis“ vor heute) verschwinden automatisch aus Listen, Sitemap und Google; ihre Seite zeigt „nicht mehr ausgeschrieben“.')) ?></p>
      <div class="f"><label for="t-jobs-form"><?= e(__('Bewerbungsformular')) ?></label>
        <select id="t-jobs-form" name="settings[jobs][form]">
          <option value=""><?= e(__('– kein Formular –')) ?></option>
          <?php if ($isNew || $jc['form'] === '_new'): ?><option value="_new"<?= $jc['form'] === '_new' ? ' selected' : '' ?>><?= e(Core\Data\Tables::find('bewerbungen') ? __('Eingang „Bewerbungen“ nutzen (Feld „Stelle“ wird ergänzt)') : __('Neuen Eingang „Bewerbungen“ anlegen')) ?></option><?php endif; ?>
          <?php foreach ($inboxes as $ib): ?><option value="<?= e($ib['handle']) ?>"<?= $jc['form'] === $ib['handle'] ? ' selected' : '' ?>><?= e($ib['name']) ?></option><?php endforeach; ?>
        </select>
        <p class="f-help"><?= e(__('Eingang, dessen Formular auf jeder Stellenseite erscheint (Block „Stelle: Bewerbung“). Das Feld „Stelle“ ist dort schon ausgefüllt und gesperrt; der Betreff der E-Mail nennt die Stelle.')) ?></p></div>
      <input type="hidden" name="settings[jobs][field]" value="<?= e((string) $jc['field']) ?>">
      <?php foreach ((array) $jc['map'] as $role => $fname): ?><input type="hidden" name="settings[jobs][map][<?= e((string) $role) ?>]" value="<?= e((string) $fname) ?>"><?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if (\Core\Features::on('calendar')): $c = (array) ($s['calendar'] ?? []) + \Core\Data\Calendar::DEFAULTS;
      $calSel = function (string $key, string $label, bool $optional = true, string $help = '') use ($c, $fieldOpts) {
          $h = '<div class="f"><label for="t-cal-' . $key . '">' . e($label) . '</label><select id="t-cal-' . $key . '" name="settings[calendar][' . $key . ']">'
              . '<option value="">' . e($optional ? __('– keins –') : __('– wählen –')) . '</option>';
          foreach ($fieldOpts(\Core\Data\Calendar::MAP[$key]) as $f) $h .= '<option value="' . e($f['name']) . '"' . ($c[$key] === $f['name'] ? ' selected' : '') . '>' . e($f['label']) . '</option>';
          return $h . '</select>' . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '') . '</div>';
      }; ?>
    <section class="adm-card dt-cal" data-cal-settings>
      <h2><?= e(__('Kalender')) ?></h2>
      <input type="hidden" name="settings[calendar][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[calendar][enabled]" value="1"<?= $c['enabled'] ? ' checked' : '' ?> data-cal-toggle> <span><?= e(__('Als Kalender nutzen')) ?></span></label>
      <p class="f-help"><?= e(__('Einträge werden zu Terminen: Kalender-Block, „Nächste Termine“, iCal-Abo und Wiederholungen.')) ?></p>
      <div class="dt-cal__map" data-cal-map<?= $c['enabled'] ? '' : ' hidden' ?>>
        <?= $calSel('start', __('Beginn'), false, __('Feld vom Typ „Datum & Uhrzeit“ oder „Datum“ (= ganztägig).')) ?>
        <?= $calSel('end', __('Ende (optional)')) ?>
        <div class="f"><label for="t-cal-duration"><?= e(__('Dauer ohne Ende (Minuten)')) ?></label><input type="number" id="t-cal-duration" name="settings[calendar][duration]" min="0" max="10080" step="5" value="<?= (int) $c['duration'] ?>">
          <p class="f-help"><?= e(__('Gilt, wenn bei einem Termin kein Ende eingetragen ist.')) ?></p></div>
        <?= $calSel('all_day', __('Ganztägig (optional)')) ?>
        <?= $calSel('recurrence', __('Wiederholung (optional)'), true, __('Feld vom Typ „Wiederholung“.')) ?>
        <?= $calSel('location', __('Ort (optional)')) ?>
        <?= $calSel('description', __('Beschreibung (optional)'), true, __('Für iCal und Kalender-Apps (als reiner Text).')) ?>
        <?= $calSel('category', __('Kategorie (optional)'), true, __('Auswahlfeld – zum Filtern und als Kategorie in Kalender-Apps.')) ?>
        <input type="hidden" name="settings[calendar][feed]" value="0">
        <label class="f-check"><input type="checkbox" name="settings[calendar][feed]" value="1"<?= $c['feed'] ? ' checked' : '' ?>> <span><?= e(__('Öffentlicher iCal-Feed (Abonnieren)')) ?></span></label>
        <?php if (!$isNew && $c['feed']): ?><p class="f-help"><code><?= e(\Core\Data\Calendar::feedUrls($table)['https']) ?></code></p><?php endif; ?>
      </div>
      <?= $err('settings.calendar') ?>
    </section>
    <?php endif; ?>

    <?php if (\Core\Data\DataForms::available()): $fm = (array) ($s['form'] ?? []) + \Core\Data\DataForms::DEFAULTS;
      $fmAll = array_filter($def['fields'] ?? [], fn($f) => \Core\Data\DataForms::eligible($f, true)); ?>
    <section class="adm-card dt-form" data-form-settings>
      <h2><?= e(__('Öffentliches Formular')) ?></h2>
      <input type="hidden" name="settings[form][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[form][enabled]" value="1"<?= $fm['enabled'] ? ' checked' : '' ?> data-form-toggle> <span><?= e(__('Besucher können Einträge anlegen')) ?></span></label>
      <p class="f-help"><?= e(__('Auf einer Seite mit dem Block „Formular (Datentabelle)“ einfügen. Mehrstufiger Spamschutz, ohne Cookies.')) ?></p>
      <div class="dt-form__opts" data-form-opts<?= $fm['enabled'] ? '' : ' hidden' ?>>
        <fieldset class="f dt-form__fields"><legend><?= e(__('Felder im Formular')) ?></legend>
          <input type="hidden" name="settings[form][fields][]" value="">
          <?php if (!$fmAll): ?><p class="f-help"><?= e(__('Neue Felder erscheinen hier nach dem Speichern.')) ?></p><?php endif; ?>
          <?php foreach ($fmAll as $f): $upl = in_array($f['type'], \Core\Data\DataForms::UPLOAD_TYPES, true); ?>
          <label class="f-check"<?= $upl ? ' data-form-upload' : '' ?>><input type="checkbox" name="settings[form][fields][]" value="<?= e($f['name']) ?>"<?= !$fm['fields'] || in_array($f['name'], $fm['fields'], true) || !empty($f['required']) ? ' checked' : '' ?><?= !empty($f['required']) ? ' disabled' : '' ?>><?php if (!empty($f['required'])): ?><input type="hidden" name="settings[form][fields][]" value="<?= e($f['name']) ?>"><?php endif; ?>
            <span><?= e($f['label']) ?><?= !empty($f['required']) ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?><?= $upl ? ' <small class="adm-muted">(' . e(__('nur mit Datei-Uploads')) . ')</small>' : '' ?></span></label>
          <?php endforeach; ?>
          <p class="f-help"><?= e(__('Nichts angehakt = alle passenden Felder. Verknüpfungen, formatierter Text, Karte, Links und Wiederholungen füllt nur die Redaktion aus.')) ?></p>
        </fieldset>
        <div class="f"><label for="t-form-status"><?= e(__('Neue Einträge')) ?></label>
          <select id="t-form-status" name="settings[form][status]">
            <option value="draft"<?= $fm['status'] !== 'published' ? ' selected' : '' ?>><?= e(__('als Entwurf – Redaktion prüft und veröffentlicht')) ?></option>
            <option value="published"<?= $fm['status'] === 'published' ? ' selected' : '' ?>><?= e(__('sofort veröffentlichen')) ?></option>
          </select></div>
        <div class="f"><label for="t-form-notify"><?= e(__('Benachrichtigung an (E-Mail)')) ?></label>
          <input id="t-form-notify" name="settings[form][notify]" value="<?= e($fm['notify']) ?>" placeholder="<?= e(__('leer = Empfänger aus den Grundeinstellungen')) ?>" autocomplete="off">
          <p class="f-help"><?= e(__('Mehrere Adressen mit Komma trennen. Die E-Mail enthält keine Inhalte – nur einen Link zum neuen Eintrag.')) ?></p></div>
        <?= Core\Theme::capture(ROOT . '/app/Admin/views/data/_receipt.php', ['fm' => $fm, 'fields' => (array) ($def['fields'] ?? [])]) ?>
        <div class="f"><label for="t-form-success"><?= e(__('Text nach dem Absenden')) ?></label>
          <textarea id="t-form-success" name="settings[form][success]" rows="2" placeholder="<?= e(__('Vielen Dank – Ihre Angaben sind eingegangen.')) ?>"><?= e($fm['success']) ?></textarea></div>
        <div class="f"><label for="t-form-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
          <input id="t-form-submit" name="settings[form][submit]" value="<?= e($fm['submit']) ?>" placeholder="<?= e(__('Absenden')) ?>"></div>
        <input type="hidden" name="settings[form][uploads]" value="0">
        <label class="f-check"><input type="checkbox" name="settings[form][uploads]" value="1"<?= $fm['uploads'] ? ' checked' : '' ?>> <span><?= e(__('Datei-Uploads erlauben (Bild- und Datei-Felder)')) ?></span></label>
        <div class="f"><label for="t-form-mb"><?= e(__('Höchstgröße je Datei (MB)')) ?></label>
          <input type="number" id="t-form-mb" name="settings[form][upload_mb]" min="1" max="<?= \Core\Data\DataForms::MAX_MB ?>" value="<?= (int) $fm['upload_mb'] ?>">
          <p class="f-help"><?= e(__('Bildfelder: JPG, PNG, WebP. Dateifelder: PDF und/oder Bilder – einstellbar beim Feld. Hochgeladene Dateien landen in der Mediathek (Tag „formular“) und sind über ihre Adresse erreichbar.')) ?></p></div>
        <p class="dt-note"><?= e(__('Einträge werden nicht verschlüsselt gespeichert. Für vertrauliche Angaben (z. B. Gesundheitsdaten) einen verschlüsselten Eingang (Vorlage „Anfragen“) nutzen.')) ?></p>
      </div>
      <?= $err('settings.form') ?>
    </section>
    <?php endif; ?>

    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_search_settings.php', ['table' => $table, 'def' => $def]) /* Website-Suche je Tabelle */ ?>
    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_push_settings.php', ['table' => $table, 'def' => $def]) /* Push: Besucher abonnieren neue Einträge (Core\Push\Topics) */ ?>

    <section class="adm-card">
      <h2>Verwaltung</h2>
      <div class="adm-grid2">
        <div class="f"><label for="t-sort">Sortierung</label><select id="t-sort" name="settings[sort_field]">
          <?php foreach (['sort' => 'Manuell (ziehen)', 'published_at' => 'Veröffentlicht am', 'created_at' => 'Angelegt am'] + array_column($fieldOpts(['text', 'date', 'datetime', 'time', 'number', 'select']), 'label', 'name') as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= ($s['sort_field'] ?? 'sort') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="f"><label for="t-dir">Richtung</label><select id="t-dir" name="settings[sort_dir]">
          <option value="asc"<?= ($s['sort_dir'] ?? 'asc') === 'asc' ? ' selected' : '' ?>>aufsteigend</option>
          <option value="desc"<?= ($s['sort_dir'] ?? '') === 'desc' ? ' selected' : '' ?>>absteigend</option></select></div>
      </div>
      <input type="hidden" name="settings[workflow]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[workflow]" value="1"<?= ($s['workflow'] ?? true) ? ' checked' : '' ?>> <span>Entwurf / Online je Eintrag</span></label>
      <input type="hidden" name="settings[per_page]" value="<?= (int) ($s['per_page'] ?? 50) ?>">
    </section>
<?php endif; ?>

    <div class="dt-save"><button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= $isNew ? 'Tabelle anlegen' : 'Speichern' ?></button></div>
  </aside>
</form>

<?php if (!$isNew): ?>
<form id="tplform" method="post" action="<?= e(url('/admin/data/' . $table['handle'] . '/template')) ?>"><?= csrf_field() ?></form>
<?php endif; ?>
<?php if (!$isNew && !$sharedT): ?>
<details class="adm-card dt-danger">
  <summary>Tabelle löschen …</summary>
  <form method="post" action="<?= e(url('/admin/data/' . $table['handle'] . '/destroy')) ?>">
    <?= csrf_field() ?>
    <p><?= $inbox ? e(__('Löscht den Eingang mit allen (verschlüsselten) Anfragen. Formulare auf Seiten zeigen dann nichts mehr an. Zum Bestätigen den Kurznamen eintippen:')) : 'Löscht die Tabelle mit <b>allen Einträgen</b> und die Detailseiten-Vorlage. Datenlisten auf Seiten bleiben leer. Zum Bestätigen den Kurznamen eintippen:' ?></p>
    <div class="adm-row"><input name="confirm" placeholder="<?= e($table['handle']) ?>" aria-label="Kurzname zur Bestätigung" autocomplete="off"><button class="adm-btn adm-btn--danger">Endgültig löschen</button></div>
  </form>
</details>
<?php endif; ?>
