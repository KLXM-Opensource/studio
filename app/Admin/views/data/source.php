<?php
/**
 * Externe Quelle anlegen/bearbeiten (Core\Sources): Quelle, Abruf, Zieltabelle + Zuordnung, Vorschau & Test, Status, Upload, Protokoll.
 * Ohne JavaScript bedienbar: „Vorschau & Test“ schickt das Formular ab und zeigt es mit Ergebnis wieder an
 * (Tabellen-/Formatwechsel lösen das per data-autosubmit aus).
 * @var ?array $src  @var array $v  @var array $errors  @var ?array $preview  @var array $tables  @var array $logs  @var ?string $upload
 */
use Core\Sources\Mapper;
use Core\Sources\Sources;
use Core\Sources\Sync;

$isNew = $src === null;
$action = url('/admin/quellen/' . ($isNew ? 'new' : $src['id']));
$o = $v['options'];
$a = $v['auth'];
$t = $v['table'] ?? null;
$map = $v['mapping'];
$paths = $preview['paths'] ?? ($isNew ? [] : Sync::knownPaths((int) $src['id']));
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="err-' . e(str_replace('.', '-', $k)) . '">' . e((string) $errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e(str_replace('.', '-', $k)) . '"' : '';
$fmt = $v['format'];
$tx = Mapper::transforms();
$optHelp = __('Datum: Formate wie d.m.Y|Y-m-d H:i (leer = automatisch) · Zahl: de oder en (leer = automatisch) · Vorlage: {pfad} {pfad2}');
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/quellen')) ?>"><span aria-hidden="true"><?= icon('plugs-connected') ?></span> <?= e(__('Externe Quellen')) ?></a></p>
    <h1><?= e($isNew ? __('Neue Quelle') : $src['name']) ?><?php if (!$isNew && !empty($src['options']['example'])): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Beispiel')) ?></span><?php endif; ?></h1>
    <?php if (!$isNew && $src['table']): ?><p class="adm-muted"><?= e(__('Füllt die Tabelle')) ?> <a href="<?= e(url('/admin/data/' . $src['table']['handle'])) ?>"><?= e($src['table']['name']) ?></a> · <?= e(__('{n} Einträge aus dieser Quelle', ['n' => $src['items']])) ?></p><?php endif; ?></div>
</header>

<?php if (!empty($errors['_'])): ?><p class="adm-flash adm-flash--error" role="alert"><?= e((string) $errors['_']) ?></p><?php endif; ?>
<?php if ($errors && empty($errors['_'])): ?><p class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Angaben – es wurde nichts gespeichert.')) ?></p><?php endif; ?>

<div class="src-grid">
<form method="post" action="<?= e($action) ?>" class="src-form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="preview">
  <input type="hidden" name="mapping_table" value="<?= e((string) ($v['table_handle'] ?? '')) ?>">

  <section class="adm-card" aria-labelledby="src-h-quelle">
    <h2 id="src-h-quelle"><?= e(__('Quelle')) ?></h2>
    <div class="adm-fields">
      <div class="f"><label for="src-name"><?= e(__('Name')) ?> <span class="req" aria-hidden="true">*</span></label>
        <input id="src-name" name="name" value="<?= e($v['name']) ?>" required maxlength="120"<?= $inv('name') ?>><?= $err('name') ?></div>
      <div class="f f--half"><label for="src-format"><?= e(__('Format')) ?></label>
        <select id="src-format" name="format" data-autosubmit><?php foreach (Sources::FORMATS as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $fmt ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f f--half"><span class="f-label"><?= e(__('Status')) ?></span>
        <label class="f-check"><input type="checkbox" name="active" value="1"<?= $v['active'] ? ' checked' : '' ?>> <span><?= e(__('Aktiv (Zeitplan ruft ab)')) ?></span></label></div>
      <div class="f"><label for="src-url"><?= e(__('Adresse (URL)')) ?><?= $fmt !== 'openimmo' ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></label>
        <input id="src-url" name="url" type="url" inputmode="url" value="<?= e($v['url']) ?>" placeholder="https://…" spellcheck="false"<?= $inv('url') ?>><?= $err('url') ?>
        <p class="f-help"><?= e($fmt === 'openimmo' ? __('Optional: Adresse, unter der die Makler-Software die OpenImmo-Datei (XML oder ZIP) bereitstellt – sonst ZIP hochladen (rechts).') : __('Abgerufen wird nur vom Server. Adressen im eigenen Netz (localhost, private IP-Adressen) sind gesperrt.')) ?></p></div>
      <?php if ($fmt === 'json'): ?>
      <div class="f"><label for="src-ip"><?= e(__('Pfad zur Liste der Einträge')) ?></label>
        <input id="src-ip" name="options[items_path]" value="<?= e($o['items_path']) ?>" placeholder="data.items[*]" spellcheck="false">
        <p class="f-help"><?= e(__('Punkt-Schreibweise, z. B. data.items oder results[*]. Leer = erste Liste im Dokument.')) ?></p></div>
      <?php elseif ($fmt === 'xml'): ?>
      <div class="f"><label for="src-xp"><?= e(__('XPath der Einträge')) ?></label>
        <input id="src-xp" name="options[xpath]" value="<?= e($o['xpath']) ?>" placeholder="//produkt" spellcheck="false"<?= $inv('options.xpath') ?>><?= $err('options.xpath') ?>
        <p class="f-help"><?= e(__('Leer = alle Kindelemente des Wurzelelements. Ein Standard-Namensraum wird ignoriert.')) ?></p></div>
      <?php endif; ?>
    </div>
    <details class="src-auth"<?= $a['type'] !== 'none' || isset($errors['auth.header']) ? ' open' : '' ?>>
      <summary><?= icon('key') ?> <?= e(__('Anmeldung')) ?> <small class="adm-muted"><?= e(match ($a['type']) { 'bearer' => __('Bearer-Token'), 'header' => __('Kopfzeile'), 'basic' => __('Benutzer & Passwort'), default => __('keine') }) ?></small></summary>
      <div class="adm-fields">
        <div class="f f--half"><label for="src-at"><?= e(__('Art')) ?></label>
          <select id="src-at" name="auth[type]"><?php foreach (['none' => __('keine'), 'bearer' => __('Bearer-Token (Authorization)'), 'header' => __('API-Schlüssel in Kopfzeile'), 'basic' => __('Benutzer & Passwort (Basic)')] as $k => $l): ?><option value="<?= e($k) ?>"<?= $a['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="f f--half"><label for="src-ah"><?= e(__('Name der Kopfzeile')) ?></label>
          <input id="src-ah" name="auth[header]" value="<?= e($a['header']) ?>" placeholder="X-Api-Key" spellcheck="false"<?= $inv('auth.header') ?>><?= $err('auth.header') ?></div>
        <div class="f f--half"><label for="src-au"><?= e(__('Benutzer')) ?></label>
          <input id="src-au" name="auth[user]" value="<?= e($a['user']) ?>" autocomplete="off" spellcheck="false"></div>
        <div class="f f--half"><label for="src-as"><?= e(__('Token / Passwort')) ?></label>
          <input id="src-as" name="auth[secret]" type="password" value="" autocomplete="new-password" placeholder="<?= e($a['secret'] !== '' ? __('gespeichert – leer lassen = unverändert') : '') ?>">
          <?php if ($a['secret'] !== ''): ?><label class="f-check"><input type="checkbox" name="auth[clear_secret]" value="1"> <span><?= e(__('Gespeichertes Geheimnis löschen')) ?></span></label><?php endif; ?></div>
      </div>
      <p class="f-help"><?= e(__('Geheimnisse werden verschlüsselt gespeichert (wie das SMTP-Passwort) und nie wieder angezeigt; sie gehen nur an den Host der Quelle, nicht an Weiterleitungsziele.')) ?></p>
    </details>
  </section>

  <section class="adm-card" aria-labelledby="src-h-abruf">
    <h2 id="src-h-abruf"><?= e(__('Abruf')) ?></h2>
    <div class="adm-fields">
      <div class="f f--half"><label for="src-sch"><?= e(__('Zeitplan')) ?></label>
        <select id="src-sch" name="options[schedule]"><?php foreach (Sources::schedules() as $k => $l): ?><option value="<?= e($k) ?>"<?= $o['schedule'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f f--half"><label for="src-ttl"><?= e(__('Zwischenspeicher (Minuten)')) ?></label>
        <input id="src-ttl" name="options[ttl]" type="number" min="0" max="10080" value="<?= (int) $o['ttl'] ?>"><p class="f-help"><?= e(__('So lange wird die Antwort für Vorschau und Zeitplan wiederverwendet. „Jetzt abrufen“ lädt immer neu.')) ?></p></div>
      <div class="f f--half"><label for="src-to"><?= e(__('Zeitlimit (Sekunden)')) ?></label>
        <input id="src-to" name="options[timeout]" type="number" min="2" max="60" value="<?= (int) $o['timeout'] ?>"></div>
      <div class="f f--half"><label for="src-max"><?= e(__('Höchstens Einträge')) ?></label>
        <input id="src-max" name="options[max_items]" type="number" min="1" max="<?= \Core\Sources\Parser::MAX_ITEMS ?>" value="<?= (int) $o['max_items'] ?>"></div>
      <div class="f f--half"><label for="src-mb"><?= e(__('Größe der Antwort höchstens (MB)')) ?></label>
        <input id="src-mb" name="options[max_mb]" type="number" min="1" max="<?= (int) app()->config->get('sources_max_mb', 20) ?>" value="<?= (int) $o['max_mb'] ?>"></div>
      <div class="f f--half"><label for="src-miss"><?= e(__('Einträge, die in der Quelle fehlen')) ?></label>
        <select id="src-miss" name="options[missing]"><?php foreach (Sources::missingModes() as $k => $l): ?><option value="<?= e($k) ?>"<?= $o['missing'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f f--half"><label for="src-st"><?= e(__('Neue Einträge')) ?></label>
        <select id="src-st" name="options[status]"><option value="published"<?= $o['status'] === 'published' ? ' selected' : '' ?>><?= e(__('sofort online')) ?></option><option value="draft"<?= $o['status'] === 'draft' ? ' selected' : '' ?>><?= e(__('als Entwurf (erst prüfen)')) ?></option></select></div>
      <div class="f f--half"><span class="f-label"><?= e(__('Bilder')) ?></span>
        <input type="hidden" name="options[images]" value="0"><label class="f-check"><input type="checkbox" name="options[images]" value="1"<?= $o['images'] ? ' checked' : '' ?>> <span><?= e(__('in die Mediathek übernehmen (neu kodiert, ohne EXIF)')) ?></span></label></div>
      <div class="f"><label for="src-ua"><?= e(__('User-Agent')) ?></label>
        <input id="src-ua" name="options[ua]" value="<?= e($o['ua']) ?>" placeholder="<?= e(\Core\Sources\Fetcher::userAgent()) ?>" spellcheck="false"></div>
    </div>
  </section>

  <section class="adm-card" id="zuordnung" aria-labelledby="src-h-map">
    <h2 id="src-h-map"><?= e(__('Zieltabelle & Zuordnung')) ?></h2>
    <div class="adm-fields">
      <div class="f"><label for="src-table"><?= e(__('Datentabelle')) ?></label>
        <div class="adm-row">
          <select id="src-table" name="table_handle" data-autosubmit<?= $inv('table_handle') ?>>
            <option value=""><?= e(__('– Tabelle wählen –')) ?></option>
            <?php foreach ($tables as $tb): ?><option value="<?= e($tb['handle']) ?>"<?= ($v['table_handle'] ?? '') === $tb['handle'] ? ' selected' : '' ?>><?= e($tb['name']) ?></option><?php endforeach; ?>
          </select>
          <?php if (!$isNew && can('data.schema')): ?><button class="adm-btn adm-btn--small adm-btn--ghost" form="src-tbl"><?= icon('plus') ?> <?= e(match ($fmt) { 'openimmo' => __('Tabelle „Immobilien“ anlegen'), 'rss', 'atom' => __('Tabelle „Meldungen“ anlegen'), default => __('Tabelle anlegen') }) ?></button><?php endif; ?>
        </div><?= $err('table_handle') ?>
        <p class="f-help"><?= e($isNew ? __('Nach dem ersten Speichern können Sie hier auch eine passende Tabelle mit Detailseite anlegen lassen.') : __('Die Vorlage legt eine Tabelle mit passenden Feldern, Detailseite und Zuordnung an.')) ?></p></div>
    </div>
    <?php if ($t): ?>
    <datalist id="src-paths"><?php foreach ($paths as $p => $sample): ?><option value="<?= e((string) $p) ?>"><?= e(mb_strimwidth((string) $sample, 0, 60, '…')) ?></option><?php endforeach; ?></datalist>
    <div class="adm-fields">
      <div class="f f--half"><label for="src-id"><?= e(__('Eindeutige ID (Pfad)')) ?></label>
        <input id="src-id" name="mapping[id_path]" value="<?= e($map['id_path']) ?>" list="src-paths" placeholder="guid" spellcheck="false">
        <p class="f-help"><?= e(__('Erkennt Einträge beim nächsten Abruf wieder (keine Dubletten). Leer = Link bzw. Prüfsumme.')) ?></p></div>
      <div class="f f--half"><label for="src-slug"><?= e(__('Adresse der Detailseite (Pfad, optional)')) ?></label>
        <input id="src-slug" name="mapping[slug_path]" value="<?= e($map['slug_path']) ?>" list="src-paths" placeholder="<?= e(__('aus dem Titel')) ?>" spellcheck="false"></div>
    </div>
    <p class="f-help"><?= e(__('Pfade: Punkt-Schreibweise wie in der Vorschau, z. B. title, enclosure@url, link[@rel=alternate]@href, anhaenge.anhang[0].daten.pfad. Mehrere Pfade mit | – der erste nicht leere zählt.')) ?></p>
    <div class="src-map-wrap">
    <table class="adm-table src-map">
      <thead><tr><th scope="col"><?= e(__('Feld')) ?></th><th scope="col"><?= e(__('Quelle (Pfad)')) ?></th><th scope="col"><?= e(__('Umwandlung')) ?></th><th scope="col"><?= e(__('Option / Vorlage')) ?></th><th scope="col"><?= e(__('Standardwert')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($t['fields'] as $f): $row = (array) ($map['rows'][$f['name']] ?? []); $n = 'mapping[rows][' . $f['name'] . ']'; $fid = 'm-' . $f['name'];
        $unsup = in_array($f['type'], Mapper::UNSUPPORTED, true); $isMedia = in_array($f['type'], ['media', 'file'], true); ?>
        <tr>
          <th scope="row"><label for="<?= e($fid) ?>"><?= e($f['label']) ?></label><?= !empty($f['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '' ?><br><small class="adm-muted"><?= e(\Core\Data\Tables::TYPES[$f['type']][0] ?? $f['type']) ?></small></th>
          <?php if ($unsup): ?>
          <td colspan="4" class="adm-muted"><?= e(__('Dieser Feldtyp lässt sich nicht aus einer Quelle füllen.')) ?></td>
          <?php else: ?>
          <td><input id="<?= e($fid) ?>" name="<?= e($n) ?>[path]" value="<?= e((string) ($row['path'] ?? '')) ?>" list="src-paths" spellcheck="false" aria-describedby="src-h-map">
            <?php if ($isMedia): ?><label class="src-sub" for="<?= e($fid) ?>-alt"><?= e(__('Alt-Text aus')) ?></label><input id="<?= e($fid) ?>-alt" name="<?= e($n) ?>[alt]" value="<?= e((string) ($row['alt'] ?? '')) ?>" list="src-paths" spellcheck="false" placeholder="<?= e(__('Titel des Eintrags')) ?>"><?php endif; ?>
            <details class="src-lookup"<?= !empty($row['lookup']) ? ' open' : '' ?>><summary><?= e(__('Werte ersetzen')) ?></summary>
              <label class="sr-only" for="<?= e($fid) ?>-lk"><?= e(__('Werte ersetzen')) ?></label><textarea id="<?= e($fid) ?>-lk" name="<?= e($n) ?>[lookup]" rows="2" placeholder="VERBRAUCH=Verbrauchsausweis" spellcheck="false"><?= e((string) ($row['lookup'] ?? '')) ?></textarea></details></td>
          <td><?php if ($isMedia): ?><span class="adm-muted"><?= e(__('Bild/Datei laden')) ?></span><?php else: ?>
            <label class="sr-only" for="<?= e($fid) ?>-tx"><?= e(__('Umwandlung')) ?></label><select id="<?= e($fid) ?>-tx" name="<?= e($n) ?>[tx]"><?php foreach ($tx as $k => $l): ?><option value="<?= e($k) ?>"<?= ($row['tx'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?></td>
          <td><?php if (!$isMedia): ?><label class="sr-only" for="<?= e($fid) ?>-opt"><?= e(__('Option / Vorlage')) ?></label><input id="<?= e($fid) ?>-opt" name="<?= e($n) ?>[opt]" value="<?= e((string) ($row['opt'] ?? '')) ?>" spellcheck="false" title="<?= e($optHelp) ?>"><?php endif; ?></td>
          <td><label class="sr-only" for="<?= e($fid) ?>-def"><?= e(__('Standardwert')) ?></label><input id="<?= e($fid) ?>-def" name="<?= e($n) ?>[default]" value="<?= e((string) ($row['default'] ?? '')) ?>"></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="f-help"><?= e($optHelp) ?></p>
    <?php if ($miss = Mapper::missingRequired($t, $map)): ?><p class="adm-badge adm-badge--adm-warn"><?= icon('warning') ?> <?= e(__('Pflichtfelder ohne Zuordnung: {list}', ['list' => implode(', ', $miss)])) ?></p><?php endif; ?>
    <?php endif; ?>
  </section>

  <div class="adm-form-actions src-actions">
    <button class="adm-btn" name="do" value="preview"><?= icon('eye') ?> <?= e(__('Vorschau & Test')) ?></button>
    <label class="f-check src-fresh"><input type="checkbox" name="fresh" value="1"> <span><?= e(__('neu laden (Zwischenspeicher umgehen)')) ?></span></label>
    <button class="adm-btn adm-btn--primary" name="do" value="save"><?= e($isNew ? __('Quelle anlegen') : __('Speichern')) ?></button>
  </div>

  <?php if ($preview): ?>
  <section class="adm-card src-preview" id="vorschau" aria-labelledby="src-h-prev" tabindex="-1">
    <h2 id="src-h-prev"><?= e(__('Vorschau')) ?></h2>
    <?php if (!$preview['ok']): ?>
    <p class="adm-flash adm-flash--error" role="alert"><?= icon('warning') ?> <?= e((string) $preview['error']) ?></p>
    <?php else: ?>
    <p class="adm-flash adm-flash--success" role="status"><?= icon('check-circle') ?> <?= e(__('{n} Einträge gelesen ({kb} KB{cached}).', ['n' => $preview['total'], 'kb' => number_format($preview['bytes'] / 1024, 1, ',', '.'),
        'cached' => $preview['cached'] ? ', ' . __('aus dem Zwischenspeicher') : ($preview['ms'] ? ', ' . $preview['ms'] . ' ms' : '')])) ?>
      <?php if (!empty($preview['meta']['partial'])): ?> <?= e(__('OpenImmo-Teilabgleich: fehlende Objekte bleiben unverändert.')) ?><?php endif; ?></p>
    <?php if ($preview['suggested']): ?><p class="f-help"><?= e(__('Die Zuordnung oben wurde vorgeschlagen – bitte prüfen und speichern.')) ?></p><?php endif; ?>
    <?php if (!$t): ?><p class="adm-muted"><?= e(__('Wählen Sie eine Zieltabelle, um das Ergebnis der Zuordnung zu sehen.')) ?></p><?php endif; ?>
    <?php foreach ($preview['mapped'] as $i => $m): ?>
    <article class="src-item">
      <h3><?= e($m['title'] !== '' ? $m['title'] : __('Eintrag {n}', ['n' => $i + 1])) ?> <small class="adm-muted">ID <code><?= e(mb_strimwidth($m['ext_id'], 0, 70, '…')) ?></code></small></h3>
      <dl class="sh-fields">
        <?php foreach ($m['fields'] as $fd): ?><dt><?= e($fd['label']) ?></dt><dd><?= !empty($fd['media']) ? icon('image') . ' <code>' . e(mb_strimwidth($fd['value'], 0, 90, '…')) . '</code>' . ($fd['alt'] !== '' ? ' <small class="adm-muted">Alt: ' . e($fd['alt']) . '</small>' : '') : e(mb_strimwidth($fd['value'], 0, 300, '…')) ?></dd><?php endforeach; ?>
        <?php if (!$m['fields']): ?><dt><?= e(__('Ergebnis')) ?></dt><dd class="adm-muted"><?= e(__('Kein Feld zugeordnet.')) ?></dd><?php endif; ?>
      </dl>
      <?php if ($m['warnings']): ?><ul class="src-warn"><?php foreach ($m['warnings'] as $w): ?><li><?= icon('warning') ?> <?= e($w) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </article>
    <?php endforeach; ?>
    <details class="src-raw"><summary><?= e(__('Gefundene Pfade und Beispielwerte ({n})', ['n' => count($preview['paths'])])) ?></summary>
      <table class="adm-table src-paths"><thead><tr><th scope="col"><?= e(__('Pfad')) ?></th><th scope="col"><?= e(__('Beispiel (erster Eintrag)')) ?></th></tr></thead><tbody>
        <?php foreach ($preview['paths'] as $p => $sample): ?><tr><td><code><?= e((string) $p) ?></code></td><td><?= e((string) $sample) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </details>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</form>

<aside class="src-side">
  <?php if (!$isNew): ?>
  <section class="adm-card" aria-labelledby="src-h-st">
    <h2 id="src-h-st"><?= e(__('Stand')) ?></h2>
    <?php if ($src['last_error']): ?><p class="adm-flash adm-flash--error"><?= icon('warning') ?> <?= e((string) $src['last_error']) ?></p><?php endif; ?>
    <dl class="md-info">
      <dt><?= e(__('Letzter Abruf')) ?></dt><dd><?= e($src['last_run_at'] ? date('d.m.Y H:i', strtotime((string) $src['last_run_at'])) : __('noch nie')) ?></dd>
      <dt><?= e(__('Zuletzt erfolgreich')) ?></dt><dd><?= e($src['last_ok_at'] ? date('d.m.Y H:i', strtotime((string) $src['last_ok_at'])) : '–') ?></dd>
      <dt><?= e(__('Nächster Abruf')) ?></dt><dd><?= e(!$src['active'] ? __('pausiert') : ($src['next_due'] ? date('d.m.Y H:i', (int) $src['next_due']) : __('nur manuell'))) ?></dd>
      <dt><?= e(__('Einträge')) ?></dt><dd><?= (int) $src['items'] ?></dd>
    </dl>
    <?php if ($src['table']): ?>
    <form method="post" action="<?= e(url('/admin/quellen/' . $src['id'] . '/sync')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--primary adm-btn--block"><?= icon('arrows-clockwise') ?> <?= e(__('Jetzt abrufen')) ?></button></form>
    <?php endif; ?>
  </section>
  <?php if ($src['format'] === 'openimmo'): ?>
  <section class="adm-card" aria-labelledby="src-h-up">
    <h2 id="src-h-up"><?= icon('file-zip') ?> <?= e(__('OpenImmo-Datei hochladen')) ?></h2>
    <form method="post" action="<?= e(url('/admin/quellen/' . $src['id'] . '/upload')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="f"><label for="src-file"><?= e(__('ZIP (openimmo.xml + Bilder) oder XML')) ?></label><input id="src-file" type="file" name="datei" accept=".zip,.xml,application/zip,application/xml,text/xml" required></div>
      <button class="adm-btn adm-btn--block"<?= $src['table'] ? '' : ' disabled' ?>><?= icon('upload-simple') ?> <?= e(__('Hochladen und einlesen')) ?></button>
      <?php if (!$src['table']): ?><p class="f-help"><?= e(__('Zuerst eine Zieltabelle wählen.')) ?></p><?php endif; ?>
    </form>
    <?php if ($upload): ?><p class="f-help"><?= e(__('Zuletzt hochgeladen: {when} – „Jetzt abrufen“ liest diese Datei erneut ein, solange keine Adresse eingetragen ist.', ['when' => date('d.m.Y H:i', (int) filemtime($upload))])) ?></p><?php endif; ?>
    <p class="f-help"><?= e(__('Die Datei wird geprüft entpackt (nur XML und Bilder, keine Pfade, begrenzte Größe). Adressen ohne Freigabe (objektadresse_freigeben) werden ohne Straße und Hausnummer übernommen.')) ?></p>
  </section>
  <?php endif; ?>
  <section class="adm-card" id="protokoll" aria-labelledby="src-h-log">
    <h2 id="src-h-log"><?= e(__('Protokoll')) ?> <small class="adm-muted"><?= e(__('letzte {n} Abrufe', ['n' => Sources::LOG_KEEP])) ?></small></h2>
    <?php if (!$logs): ?><p class="adm-muted"><?= e(__('Noch keine Abrufe.')) ?></p><?php else: ?>
    <ol class="src-log">
      <?php foreach ($logs as $l): $det = json_decode((string) $l['details'], true) ?: []; ?>
      <li class="<?= $l['ok'] ? ($l['failed'] ? 'is-warn' : 'is-ok') : 'is-err' ?>">
        <span class="src-log__head"><?= icon($l['ok'] ? ($l['failed'] ? 'warning' : 'check-circle') : 'warning') ?> <b><?= e(date('d.m. H:i', strtotime((string) $l['started_at']))) ?></b>
          <small class="adm-muted"><?= e(match ($l['via']) { 'manual' => __('von Hand'), 'cron' => __('Cron'), 'auto' => __('Zeitplan'), 'upload' => __('Upload'), default => (string) $l['via'] }) ?> · <?= (int) $l['ms'] ?> ms</small></span>
        <span class="src-log__msg"><?= e((string) $l['message']) ?></span>
        <?php if ($det): ?><details><summary><?= e(__('Hinweise ({n})', ['n' => count($det)])) ?></summary><ul><?php foreach ($det as $d): ?><li><?= e((string) $d) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </section>
  <section class="adm-card adm-card--danger" aria-labelledby="src-h-del">
    <h2 id="src-h-del"><?= e(__('Quelle löschen')) ?></h2>
    <form method="post" action="<?= e(url('/admin/quellen/' . $src['id'] . '/delete')) ?>" data-confirm="<?= e(__('Quelle „{name}“ löschen?', ['name' => $src['name']])) ?>">
      <?= csrf_field() ?>
      <label class="f-check"><input type="checkbox" name="entries" value="1"> <span><?= e(__('Auch die {n} übernommenen Einträge löschen', ['n' => $src['items']])) ?></span></label>
      <p class="f-help"><?= e(__('Ohne Häkchen bleiben die Einträge als normale, bearbeitbare Einträge der Tabelle erhalten.')) ?></p>
      <button class="adm-btn adm-btn--danger"><?= e(__('Löschen')) ?></button>
    </form>
  </section>
  <?php else: ?>
  <section class="adm-card">
    <h2><?= e(__('Tipps')) ?></h2>
    <ul class="src-tips">
      <li><?= e(__('„Vorschau & Test“ ruft die Quelle ab und zeigt die ersten Einträge – vor dem Speichern.')) ?></li>
      <li><?= e(__('Zieltabelle wählen: die Zuordnung wird aus der Vorlage bzw. aus gleichnamigen Feldern vorgeschlagen.')) ?></li>
      <li><?= e(__('Übernommene Einträge sind in der Tabelle nur lesbar, lassen sich aber ausblenden.')) ?></li>
    </ul>
  </section>
  <?php endif; ?>
</aside>
</div>
<?php if (!$isNew): ?>
<form id="src-tbl" method="post" action="<?= e(url('/admin/quellen/' . $src['id'] . '/table')) ?>" hidden><?= csrf_field() ?></form>
<?php endif; ?>
