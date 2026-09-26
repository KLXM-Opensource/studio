<?php
/**
 * Dienst anlegen/bearbeiten – Reiter: Allgemein · Cookies & Speicher · Scripts · Ereignisse · Varianten · Erweitert.
 * @var array $s  @var array $domains  @var array $errors  @var ?bool $complete
 */
use Core\Fields;
use MyCms\Consent\AdminController;
use MyCms\Consent\Compiler;
use MyCms\Consent\I18nLang;
use MyCms\Consent\Repository;

$id = (int) ($s['id'] ?? 0);
$lang = I18nLang::admin();
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="ck-e-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="ck-e-' . e($k) . '"' : '';
$tabErr = fn(array $keys) => array_filter(array_keys($errors), fn($k) => (bool) array_filter($keys, fn($p) => str_starts_with((string) $k, $p))) ? ' <span class="adm-dot" aria-label="' . e(__('Fehler')) . '"></span>' : '';
$tabs = [
    'general' => [__('Allgemein'), ['skey', 'name', 'privacy_url', 'param', 'params']],
    'items' => [__('Cookies & Speicher'), ['items']],
    'code' => [__('Scripts'), []],
    'events' => [__('Ereignisse'), ['events']],
    'variants' => [__('Varianten'), ['variants']],
    'advanced' => [__('Erweitert'), ['csp_']],
];
$p = $s['preset'] ? \MyCms\Consent\Presets::get($s['preset']) : null;
?>
<p><a class="adm-link" href="<?= e(url('/admin/consent')) ?>">← <?= e(__('Alle Dienste')) ?></a></p>
<?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte die markierten Angaben prüfen.')) ?><?php if (isset($errors['params'])): ?> <?= e($errors['params']) ?><?php endif; ?></div><?php endif; ?>
<?php if ($id && isset($complete) && !$complete): ?><div class="adm-flash adm-flash--info"><?= e(__('Unvollständig: Im Code steht noch ein offener Platzhalter (z. B. eine Mess-ID). Der Dienst wird erst ausgeliefert, wenn alles ausgefüllt ist.')) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/admin/consent/service/' . ($id ?: 'new'))) ?>" class="adm-tabs-form ck-form" data-tabs novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="">
  <?php if (!$id): ?><input type="hidden" name="s[preset]" value="<?= e((string) $s['preset']) ?>"><input type="hidden" name="s[param_defs]" value="<?= e((string) json_encode($s['param_defs'])) ?>"><?php endif; ?>
  <div class="adm-tabs" role="tablist" aria-label="<?= e(__('Bereiche')) ?>">
    <?php $first = true; foreach ($tabs as $k => [$label, $keys]): ?>
    <button type="button" role="tab" id="tab-<?= $k ?>" aria-controls="panel-<?= $k ?>" data-tab="<?= $k ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?>><?= e($label) ?><?= $tabErr($keys) ?></button>
    <?php $first = false; endforeach; ?>
  </div>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-general" aria-labelledby="tab-general">
    <div class="adm-fields">
      <div class="f f--half<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="ck-name"><?= e(__('Name')) ?> <span class="req" aria-hidden="true">*</span></label>
        <input id="ck-name" name="s[name]" value="<?= e((string) $s['name']) ?>" maxlength="190" required<?= $inv('name') ?>><?= $err('name') ?></div>
      <div class="f f--half<?= isset($errors['skey']) ? ' f--error' : '' ?>"><label for="ck-key"><?= e(__('Schlüssel')) ?></label>
        <input id="ck-key" name="s[skey]" value="<?= e((string) $s['skey']) ?>" pattern="[a-z0-9_]{1,60}" spellcheck="false" autocomplete="off"<?= $id ? ' readonly data-ck-lock' : '' ?><?= $inv('skey') ?> aria-describedby="ck-key-h">
        <p class="f-help" id="ck-key-h"><?= e(__('Für Templates: data-consent="…", consent_has(\'…\'). Nur a–z, 0–9, _.')) ?></p>
        <?php if ($id): ?><label class="f-check"><input type="checkbox" name="s[change_key]" value="1" data-ck-unlock="#ck-key"> <span><?= e(__('Schlüssel ändern (Templates anpassen; Besucher werden zu diesem Dienst erneut gefragt)')) ?></span></label><?php endif; ?>
        <?= $err('skey') ?></div>
      <div class="f f--half"><label for="ck-grp"><?= e(__('Gruppe')) ?></label>
        <select id="ck-grp" name="s[grp]"><?php foreach (Repository::GROUPS as $k => [$n]): ?><option value="<?= e($k) ?>"<?= $k === $s['grp'] ? ' selected' : '' ?>><?= e(__($n)) ?></option><?php endforeach; ?></select></div>
      <div class="f f--half f--bool"><input type="hidden" name="s[active]" value="0"><label class="f-check"><input type="checkbox" name="s[active]" value="1"<?= !empty($s['active']) ? ' checked' : '' ?>> <span><?= e(__('Aktiv (wird im Hinweis angeboten)')) ?></span></label></div>
    </div>
    <?php if ($s['param_defs']): ?>
    <h3 class="f-heading"><?= e(__('Angaben für diese Website')) ?></h3>
    <div class="adm-fields">
      <?php foreach ($s['param_defs'] as $d): $k = (string) $d['key']; $fid = 'ck-p-' . $k; ?>
      <div class="f f--half<?= isset($errors['param_' . $k]) ? ' f--error' : '' ?>"><label for="<?= e($fid) ?>"><?= e(Compiler::pick((array) ($d['label'] ?? []), $lang) ?: $k) ?> <code class="adm-muted">{{<?= e($k) ?>}}</code></label>
        <input id="<?= e($fid) ?>" name="s[params][<?= e($k) ?>]" value="<?= e((string) ($s['params'][$k] ?? '')) ?>" placeholder="<?= e((string) ($d['placeholder'] ?? '')) ?>"<?= !empty($d['pattern']) ? ' pattern="' . e((string) $d['pattern']) . '"' : '' ?> spellcheck="false" autocomplete="off" data-ck-param<?= $inv('param_' . $k) ?>>
        <?= $err('param_' . $k) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="adm-fields">
      <div class="f"><label for="ck-prov"><?= e(__('Anbieter (Name und Anschrift)')) ?></label><textarea id="ck-prov" name="s[provider]" rows="2"><?= e((string) $s['provider']) ?></textarea></div>
      <div class="f<?= isset($errors['privacy_url']) ? ' f--error' : '' ?>"><label for="ck-priv"><?= e(__('Datenschutzerklärung des Anbieters (https://…)')) ?></label><input id="ck-priv" type="url" name="s[privacy_url]" value="<?= e((string) $s['privacy_url']) ?>"<?= $inv('privacy_url') ?>><?= $err('privacy_url') ?></div>
      <div class="f f--half"><label for="ck-dde"><?= e(__('Beschreibung (Deutsch)')) ?></label><textarea id="ck-dde" name="s[description_de]" rows="3"><?= e((string) ($s['description']['de'] ?? '')) ?></textarea></div>
      <div class="f f--half"><label for="ck-den"><?= e(__('Beschreibung (Englisch)')) ?></label><textarea id="ck-den" name="s[description_en]" rows="3"><?= e((string) ($s['description']['en'] ?? '')) ?></textarea></div>
      <?php if (count($domains) > 1): ?>
      <fieldset class="f f--multi"><legend><?= e(__('Angeboten auf')) ?></legend><input type="hidden" name="s[hosts][]" value=""><div class="f-multi">
        <?php foreach ($domains as $did => $label): ?><label class="f-check"><input type="checkbox" name="s[hosts][]" value="<?= e($did) ?>"<?= !$s['hosts'] || in_array($did, $s['hosts'], true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label><?php endforeach; ?>
      </div></fieldset>
      <?php endif; ?>
    </div>
    <?php if ($p): ?>
    <details class="ck-origin"><summary><?= e(__('Aus Vorlage „{name}“', ['name' => (string) $p['name']])) ?><?= !empty($s['verified']) ? ' · ' . e(__('geprüft am {date}', ['date' => (string) $s['verified']])) : '' ?></summary>
      <?php if ($s['sources']): ?><p><?= e(__('Quellen')) ?>:</p><ul><?php foreach ($s['sources'] as $src): ?><li><a href="<?= e((string) $src) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $src) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($note = Compiler::pick((array) $s['note'], $lang)): ?><p class="adm-muted"><?= e(__('Nicht belegt')) ?>: <?= e($note) ?></p><?php endif; ?>
    </details>
    <?php endif; ?>
  </section>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-items" aria-labelledby="tab-items" hidden>
    <p class="adm-muted"><?= e(__('Was der Dienst im Browser ablegt. Erscheint in den Cookie-Einstellungen; beim Widerruf werden diese Einträge gelöscht, soweit der Browser das zulässt. Änderungen hier fragen Besucher zu diesem Dienst erneut.')) ?></p>
    <?= Fields::renderForm([['name' => 'items', 'label' => __('Cookies & Speicher'), 'type' => 'repeater', 'item_label' => __('Eintrag'), 'title_field' => 'name', 'fields' => AdminController::itemFields()]], ['items' => $s['items']], $errors, 's') ?>
  </section>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-code" aria-labelledby="tab-code" hidden>
    <p class="adm-muted"><?= e(__('Läuft erst nach Einwilligung. Inline-Code wird als Datei von dieser Domain ausgeliefert (kein Inline-Script, CSP „script-src \'self\'“); externe Hosts kommen erst nach Einwilligung in die CSP. Platzhalter: {{schlüssel}}, {{lang}}, {{domain}}.')) ?></p>
    <div class="adm-fields ck-code">
      <?php foreach (['html_head' => __('HTML im <head> (einmal nach Einwilligung, inkl. <script>-Tags)'), 'html_body' => __('HTML am Ende des <body>'), 'js_accept' => __('JavaScript bei Einwilligung (auf jeder Seite, solange sie besteht)'), 'js_revoke' => __('JavaScript bei Widerruf'), 'js_default' => __('JavaScript vor jeder Entscheidung (z. B. „consent default denied“ – darf nichts laden)')] as $k => $label): ?>
      <div class="f"><label for="ck-<?= $k ?>"><?= e($label) ?></label><textarea id="ck-<?= $k ?>" name="s[<?= $k ?>]" rows="<?= str_starts_with($k, 'html') ? 6 : 3 ?>" spellcheck="false" autocomplete="off"><?= e((string) $s[$k]) ?></textarea></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-events" aria-labelledby="tab-events" hidden>
    <p class="adm-muted"><?= e(__('Conversions ohne Code: „Wenn Klick auf … dann melde Anfrage“. Den Aufruf je Anbieter bringt die Vorlage mit (Matomo, Google Analytics/Ads, Meta, OpenAI); sonst „Eigener Code“. Läuft nur nach Einwilligung.')) ?></p>
    <?php if ($p && empty($p['events'])): ?><p class="adm-flash adm-flash--info"><?= e(__('Diese Vorlage kennt keine Ereignis-Aufrufe – nur „Eigener Code“ ist möglich.')) ?></p><?php endif; ?>
    <?= Fields::renderForm([['name' => 'events', 'label' => __('Ereignisse'), 'type' => 'repeater', 'item_label' => __('Ereignis'), 'title_field' => 'target', 'fields' => AdminController::eventFields()]], ['events' => $s['events']], $errors, 's') ?>
  </section>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-variants" aria-labelledby="tab-variants" hidden>
    <p class="adm-muted"><?= e(__('Gleicher Dienst, andere Angaben je Domain oder Sprache (z. B. andere Website-ID). Nur Abweichungen eintragen; die genaueste Variante gewinnt (Domain + Sprache vor Domain vor Sprache).')) ?></p>
    <?php $vals = array_map(fn($v) => ['domain' => $v['domain'], 'lang' => $v['lang'], 'params' => implode("\n", array_map(fn($k, $x) => "$k=$x", array_keys((array) $v['params']), (array) $v['params'])),
        'html_head' => $v['html_head'] ?? '', 'html_body' => $v['html_body'] ?? '', 'js_accept' => $v['js_accept'] ?? ''], $s['variants']); ?>
    <?= Fields::renderForm([['name' => 'variants', 'label' => __('Varianten'), 'type' => 'repeater', 'item_label' => __('Variante'), 'title_field' => 'domain', 'fields' => AdminController::variantFields($domains)]], ['variants' => $vals], $errors, 's') ?>
  </section>

  <section class="adm-card adm-panel" role="tabpanel" id="panel-advanced" aria-labelledby="tab-advanced" hidden>
    <div class="adm-fields">
      <fieldset class="f f--multi"><legend><?= e(__('Google Consent Mode v2: Signale dieses Dienstes')) ?></legend><input type="hidden" name="s[gcm][]" value=""><div class="f-multi">
        <?php foreach (Repository::GCM_SIGNALS as $sig): ?><label class="f-check"><input type="checkbox" name="s[gcm][]" value="<?= e($sig) ?>"<?= in_array($sig, $s['gcm'], true) ? ' checked' : '' ?>> <span><code><?= e($sig) ?></code></span></label><?php endforeach; ?>
      </div><p class="f-help"><?= e(__('Nur für Google-Tags. Sobald ein aktiver Dienst Signale nutzt, setzt der Hinweis „default: denied“ und nach der Entscheidung „update“ (Basic Mode).')) ?></p></fieldset>
      <div class="f"><label for="ck-embed"><?= e(__('Domains eingebetteter Inhalte (eine je Zeile, Subdomains zählen mit)')) ?></label>
        <textarea id="ck-embed" name="s[embed_hosts]" rows="2" spellcheck="false" placeholder="youtube.com&#10;youtube-nocookie.com"><?= e(implode("\n", $s['embed_hosts'])) ?></textarea>
        <p class="f-help"><?= e(__('Für 2-Klick-Platzhalter (Block „Externer Inhalt“, Video-Block bei YouTube/Vimeo). Diese Hosts stehen in der CSP (frame-src), solange der Dienst aktiv ist – geladen wird erst nach Klick bzw. Einwilligung.')) ?></p></div>
      <?php foreach (['script' => 'script-src', 'connect' => 'connect-src', 'img' => 'img-src', 'frame' => 'frame-src'] as $k => $dir): ?>
      <div class="f f--half<?= isset($errors['csp_' . $k]) ? ' f--error' : '' ?>"><label for="ck-csp-<?= $k ?>"><?= e(__('Zusätzliche Hosts für {dir} (nach Einwilligung)', ['dir' => $dir])) ?></label>
        <textarea id="ck-csp-<?= $k ?>" name="s[csp][<?= $k ?>]" rows="2" spellcheck="false" placeholder="https://*.example.com"<?= $inv('csp_' . $k) ?>><?= e(implode("\n", (array) ($s['csp'][$k] ?? []))) ?></textarea><?= $err('csp_' . $k) ?></div>
      <?php endforeach; ?>
      <p class="f-help"><?= e(__('Hosts aus dem Code (https://…) und aus URL-Angaben werden automatisch ergänzt. Nie „unsafe-inline“ – Anbieter, die Inline-Code nachladen, funktionieren unter dieser CSP nur eingeschränkt.')) ?></p>
    </div>
  </section>

  <div class="adm-savebar">
    <button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
    <?php if ($id): ?>
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/consent/service/' . $id . '/export')) ?>"><?= e(__('Als Vorlage exportieren')) ?></a>
    <?php endif; ?>
  </div>
</form>
<?php if ($id): ?>
<div class="ck-danger">
  <?php if ($s['preset']): ?>
  <form method="post" action="<?= e(url('/admin/consent/service/' . $id . '/reset')) ?>" data-confirm="<?= e(__('Auf die Vorlage zurücksetzen? Code, Cookies und Texte werden ersetzt; Schlüssel, Gruppe, Status, Domains, eingetragene Werte, Ereignisse und Varianten bleiben.')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Auf Vorlage zurücksetzen')) ?></button></form>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/admin/consent/service/' . $id . '/delete')) ?>" data-confirm="<?= e(__('Dienst „{name}“ löschen? Besucher werden dazu nicht mehr gefragt; das Protokoll bleibt.', ['name' => (string) $s['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Dienst löschen')) ?></button></form>
</div>
<?php endif; ?>
