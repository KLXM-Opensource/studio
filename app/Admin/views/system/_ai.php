<?php
/**
 * Grundeinstellungen → Reiter „KI“ (Symfony AI, Core\AI\Ai, Core\AI\Profiles):
 *   part 'top'    Übersicht je Zweck (Status, „Verbindung testen“), Verbindungen (Profile mit Statuspunkt, Prüfen, Modelle,
 *                 Bearbeiten, Löschen, „Verbindung hinzufügen“), Verwendung (Zweck → Verbindung → Modell, Ersatz, Zeitlimits)
 *   part 'panel'  Datenschutz, Besucher-Chat (Absatz für die Datenschutzerklärung), Nutzung der letzten 30 Tage
 *   part 'forms'  Formulare außerhalb des Hauptformulars + Dialoge „Verbindung“ und „Modelle“ (resources/js/_aiset.js)
 * Verbindungen und Verwendung ändert nur die Agentur/Netzwerk-Administration (Features::integrator()); Schlüssel werden nie
 * ausgegeben. Werte aus Konfigurationsdateien sind gesperrt (Profiles::locks()).
 * @var string $part
 */
use Core\AI\Ai;
use Core\AI\Profiles;
use Core\Features;

if (!Features::on('ai')) return;
$integrator = Features::integrator();
$cfg = Ai::config();
$profiles = $cfg['profiles'];
$assign = $cfg['assign'];
$locks = Profiles::locks(Ai::fileLayer());
$checks = Profiles::lastChecks();
$endpoint = url('/admin/system/ai/provider');
$lockTitle = e(__('in einer Konfigurationsdatei festgelegt'));
$typeLabel = fn(string $t) => __(Profiles::TYPES[$t] ?? $t);
$usedBy = function (string $id) use ($assign): array {
    $out = [];
    foreach ($assign as $p => $a) if ($a['profile'] === $id || $a['fallback'] === $id) $out[] = __(Profiles::PURPOSES[$p]) . ($a['fallback'] === $id ? ' (' . __('Ersatz') . ')' : '');
    return $out;
};

if ($part === 'forms'):
    // Daten für das Skript – ohne Schlüssel (nur „gesetzt“)
    $js = ['endpoint' => $endpoint, 'integrator' => $integrator, 'types' => array_map('__', Profiles::TYPES), 'supports' => Profiles::SUPPORTS,
        'purposes' => array_map('__', Profiles::PURPOSES), 'profiles' => []];
    foreach ($profiles as $id => $p) {
        $js['profiles'][$id] = array_diff_key($p, ['api_key' => 1]) + ['has_key' => $p['api_key'] !== '', 'locked' => array_keys($locks['profiles'][$id] ?? [])];
    }
    ?>
<form id="ai-test" method="post" action="<?= e(url('/admin/system/ai/test')) ?>"><?= csrf_field() ?></form>
<?php if ($integrator): ?>
<form id="ai-provider" method="post" action="<?= e($endpoint) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="assign"></form>
<?php foreach ($profiles as $id => $p): if (isset($locks['profiles'][$id])) continue; ?>
<form id="ai-del-<?= e($id) ?>" method="post" action="<?= e($endpoint) ?>" data-confirm="<?= e(__('Verbindung „{label}“ löschen? Zuordnungen, die sie nutzen, werden geleert.', ['label' => $p['label']])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"></form>
<?php endforeach; ?>
<dialog class="adm-dialog aip-dlg" id="aip-profile" aria-labelledby="aip-profile-t">
  <form method="post" action="<?= e($endpoint) ?>" class="aip-dlg__form" data-aip-pform>
    <?= csrf_field() ?><input type="hidden" name="op" value="profile"><input type="hidden" name="id" value="" data-aip-pid>
    <div class="adm-dialog__head"><h2 id="aip-profile-t" data-aip-ptitle><?= e(__('Verbindung hinzufügen')) ?></h2>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-aip-close><?= e(__('Abbrechen')) ?></button></div>
    <div class="adm-fields">
      <div class="f f--half"><label for="aip-label"><?= e(__('Bezeichnung')) ?></label>
        <input id="aip-label" name="p[label]" maxlength="60" required placeholder="<?= e(__('z. B. Ollama Büro')) ?>" data-aip-f="label"></div>
      <div class="f f--half"><label for="aip-type"><?= e(__('Anbieter')) ?></label>
        <select id="aip-type" name="p[type]" data-aip-f="type"><?php foreach (Profiles::TYPES as $k => $l): if ($k === 'fake' && environment() === 'production') continue; ?><option value="<?= e($k) ?>"><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
      <div class="f" data-aip-for="ollama mistral anthropic generic"><label for="aip-url"><?= e(__('Adresse (base_url)')) ?></label>
        <input id="aip-url" name="p[base_url]" inputmode="url" autocomplete="off" spellcheck="false" placeholder="http://localhost:11434" aria-describedby="aip-url-h" data-aip-f="base_url">
        <p class="f-help" id="aip-url-h" data-aip-urlhelp><?= e(__('Ollama: http://localhost:11434 oder die Adresse Ihres Servers. Mistral/Anthropic: leer lassen. OpenAI-kompatibel: Basis-Adresse des Dienstes.')) ?></p></div>
      <div class="f f--half" data-aip-for="ollama mistral openai anthropic generic"><label for="aip-key"><?= e(__('API-Schlüssel bzw. Token')) ?></label>
        <input id="aip-key" type="password" name="p[api_key]" autocomplete="new-password" spellcheck="false" aria-describedby="aip-key-h" data-aip-f="api_key">
        <p class="f-help" id="aip-key-h" data-aip-keyhelp><?= e(__('Optional für Ollama (z. B. hinter einem Proxy mit Token). Wird nie wieder angezeigt; „-“ löscht ihn.')) ?></p></div>
      <div class="f f--half" data-aip-for="openai"><label for="aip-region"><?= e(__('Region')) ?></label>
        <select id="aip-region" name="p[region]" data-aip-f="region"><option value=""><?= e(__('Standard')) ?></option><option value="EU"><?= e(__('EU-Datenresidenz')) ?></option></select></div>
      <div class="f f--half"><label for="aip-to"><?= e(__('Zeitlimit (Sekunden)')) ?></label>
        <input id="aip-to" type="number" min="1" max="600" step="1" name="p[timeout]" placeholder="<?= e(__('Standard')) ?>" aria-describedby="aip-to-h" data-aip-f="timeout">
        <p class="f-help" id="aip-to-h"><?= e(__('Für Aufgaben der Redaktion bzw. eine Transkription. Leer = allgemeines Zeitlimit.')) ?></p></div>
      <div class="f f--half" data-aip-for="whisper_cpp"><label for="aip-wb"><?= e(__('whisper-cli (Pfad)')) ?></label>
        <input id="aip-wb" name="p[whisper_bin]" spellcheck="false" placeholder="<?= e(__('leer = automatisch suchen')) ?>" data-aip-f="whisper_bin"></div>
      <div class="f f--half" data-aip-for="whisper_cpp"><label for="aip-wm"><?= e(__('Whisper-Modell (Pfad)')) ?></label>
        <input id="aip-wm" name="p[model_path]" spellcheck="false" placeholder="storage/ai/models/ggml-large-v3-turbo-q5_0.bin" data-aip-f="model_path"></div>
      <div class="f f--half" data-aip-for="whisper_cpp"><label for="aip-wf"><?= e(__('ffmpeg (Pfad)')) ?></label>
        <input id="aip-wf" name="p[ffmpeg]" spellcheck="false" placeholder="<?= e(__('leer = automatisch suchen')) ?>" data-aip-f="ffmpeg"></div>
      <div class="f f--half" data-aip-for="whisper_cpp"><label for="aip-wt"><?= e(__('Threads')) ?></label>
        <input id="aip-wt" type="number" min="1" max="64" name="p[threads]" placeholder="4" data-aip-f="threads"></div>
    </div>
    <p class="aip-result" data-aip-presult role="status" aria-live="polite" hidden></p>
    <div class="adm-row aip-dlg__actions">
      <button type="submit" class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
      <button type="button" class="adm-btn" data-aip-pcheck><?= e(__('Verbindung prüfen')) ?></button>
      <button type="button" class="adm-btn adm-btn--ghost" data-aip-pmodels><?= e(__('Modelle anzeigen')) ?></button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<dialog class="adm-dialog aip-dlg aip-models" id="aip-models" aria-labelledby="aip-models-t">
  <div class="adm-dialog__head"><h2 id="aip-models-t"><?= e(__('Modelle')) ?> <span class="adm-muted" data-aip-mprof></span></h2>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-aip-close><?= e(__('Schließen')) ?></button></div>
  <div class="aip-models__bar">
    <input type="search" class="lp__q" data-aip-mq aria-label="<?= e(__('Modelle filtern')) ?>" placeholder="<?= e(__('Filtern …')) ?>" autocomplete="off" spellcheck="false" aria-controls="aip-mlist">
    <?php if ($integrator): ?><label class="aip-models__for"><span><?= e(__('Übernehmen für')) ?></span>
      <select data-aip-mfor aria-label="<?= e(__('Übernehmen für')) ?>"><?php foreach (Profiles::PURPOSES as $k => $l): ?><option value="<?= e($k) ?>"><?= e(__($l)) ?></option><?php endforeach; ?></select></label><?php endif; ?>
  </div>
  <p class="aip-result" data-aip-mstatus role="status" aria-live="polite"></p>
  <div class="aip-models__wrap"><table class="adm-table aip-mtable" id="aip-mlist">
    <thead><tr><th scope="col"><?= e(__('Modell')) ?></th><th scope="col"><?= e(__('Größe')) ?></th><th scope="col"><?= e(__('Kontext')) ?></th><th scope="col"><?= e(__('Eignung')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktion')) ?></span></th></tr></thead>
    <tbody data-aip-mbody></tbody></table></div>
  <p class="f-help"><?= e(__('Die Eignung erkennt KLXM Studio an den Angaben des Dienstes oder am Namen – im Zweifel mit „Verbindung testen“ prüfen.')) ?></p>
</dialog>
<script type="application/json" id="aip-data"><?= json_encode($js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php return; endif;

if ($part === 'top'):
$site = Ai::siteSettings();
$dot = function (?array $c): string {
    $st = $c['status'] ?? '';
    [$cls, $txt] = match ($st) {
        'ok' => ['ok', __('erreichbar')], 'auth' => ['err', __('Schlüssel falsch')], 'unreachable' => ['err', __('nicht erreichbar')],
        'wrong_type' => ['warn', __('falsche Art')], 'blocked' => ['err', __('Adresse gesperrt')], '' => ['none', __('noch nicht geprüft')], default => ['err', __('Fehler')],
    };
    return '<span class="aip-dot aip-dot--' . $cls . '" data-aip-dot><span class="aip-dot__txt">' . e($txt) . '</span></span>';
};
?>
<div class="aip" data-aip>
<details class="f-sec" id="ki-status" open>
  <summary class="f-sec__sum"><span class="f-sec__title"><?= e(__('Übersicht')) ?></span><span class="f-sec__hint"><?= e($cfg['configured'] ? ($site['enabled'] ? __('KI eingeschaltet') : __('KI für diese Website aus')) : __('nicht eingerichtet')) ?></span></summary>
  <div class="f-sec__body f-sec__body--block">
  <?php if (!$cfg['configured']): ?>
    <p class="adm-flash adm-flash--info"><?= e($integrator ? __('Noch keine KI eingerichtet: zuerst unter „Verbindungen“ eine Verbindung anlegen und prüfen, dann unter „Verwendung“ Modelle zuordnen.') : __('Noch keine KI eingerichtet. Das übernimmt die Agentur bzw. Netzwerk-Administration.')) ?></p>
  <?php endif; ?>
  <div class="aip-scroll"><table class="adm-table px-table aip-overview">
    <thead><tr><th scope="col"><?= e(__('Zweck')) ?></th><th scope="col"><?= e(__('Verbindung · Modell')) ?></th><th scope="col"><?= e(__('Daten')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktion')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach (Profiles::PURPOSES as $cap => $label): $c = Ai::capability($cap, $cfg); $on = $cap === 'chat' ? \Core\AI\VisitorChat::available() : Ai::enabled($cap);
        $conn = $cap === 'transcribe' ? ($profiles[$assign['transcribe']['profile']]['label'] ?? '') : $c['label']; ?>
      <tr>
        <td><strong><?= e(__($label)) ?></strong></td>
        <td><?= $c['configured'] || $c['provider'] !== '' ? e($conn !== '' ? $conn : __(Ai::PROVIDERS[$c['provider']] ?? $c['provider'])) . ' · <code>' . e($c['model']) . '</code>' . (!empty($c['fallback']) ? '<br><small class="adm-muted">' . e(__('Ersatz: {label}', ['label' => ($c['fallback']['label'] ?? '') . ' · ' . $c['fallback']['model']])) . '</small>' : '') : '–' ?></td>
        <td><?= !$c['configured'] ? '–' : ($c['external'] ? '<span class="adm-badge adm-badge--draft">' . e(__('extern')) . '</span>' : '<span class="adm-badge">' . e(__('bleibt auf dem Server')) . '</span>') ?></td>
        <td><?= $on ? '<span class="adm-badge">' . e(__('aktiv')) . '</span>' : '<span class="adm-muted">' . e(!$c['configured'] ? __('nicht eingerichtet') : (!$site['enabled'] ? __('Website: aus') : __('aus'))) . '</span>' ?></td>
        <td><?php if ($c['configured'] && $cap !== 'chat'): ?><button class="adm-btn adm-btn--small" type="submit" form="ai-test" name="cap" value="<?= e($cap) ?>"><?= e(__('Verbindung testen')) ?></button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="adm-muted"><?= e(__('„Verbindung testen“ schickt eine kurze Probe an das Modell. Wer KI nutzen darf, steuert das Recht „KI-Funktionen nutzen“ unter Benutzer & Rollen (Standard: Redaktion und Administration).')) ?></p>
  <?php $q = \Core\AI\Assist::quota(); ?>
  <p><?= e($q['cap'] > 0 ? __('KI-Assistent heute: {used} von {cap} Aufrufen genutzt, {left} übrig.', $q) : __('KI-Assistent heute: {used} Aufrufe (ohne Tageslimit).', $q)) ?>
    · <a href="<?= e(url('/admin/ai/seo')) ?>"><?= e(__('SEO-Übersicht')) ?></a></p>
  </div>
</details>

<details class="f-sec" id="ki-conn"<?= $integrator || $profiles ? ' open' : '' ?>>
  <summary class="f-sec__sum"><span class="f-sec__title"><?= e(__('Verbindungen')) ?></span><span class="f-sec__hint"><?= e(__('{n} für die ganze Installation', ['n' => count($profiles)])) ?></span></summary>
  <div class="f-sec__body f-sec__body--block">
  <p class="adm-muted"><?= e($integrator
      ? __('Beliebig viele Verbindungen, z. B. ein eigener Ollama-Server und ein EU-Anbieter. Gilt für alle Websites dieser Installation (storage/ai/config.json, nicht im Webroot); Werte aus config/config.local.php bzw. config/sites/*.php haben Vorrang und sind gesperrt.')
      : __('Verbindungen, Modelle und Schlüssel legt die Agentur bzw. Netzwerk-Administration für alle Websites fest. Prüfen können Sie sie hier.')) ?></p>
  <?php if (!$profiles): ?><p class="adm-muted"><?= e(__('Noch keine Verbindung.')) ?></p><?php endif; ?>
  <ul class="aip-plist">
  <?php foreach ($profiles as $id => $p): $lk = $locks['profiles'][$id] ?? []; $ub = $usedBy($id); ?>
    <li class="aip-prof" id="ki-p-<?= e($id) ?>" data-aip-prow="<?= e($id) ?>">
      <div class="aip-prof__main">
        <?= $dot($checks[$id] ?? null) ?>
        <div class="aip-prof__txt">
          <strong class="aip-prof__name"><?= e($p['label']) ?></strong><?php if ($lk): ?> <span class="adm-badge" title="<?= $lockTitle ?>"><?= icon('lock') ?> <?= e(__('Datei')) ?></span><?php endif; ?>
          <span class="aip-prof__meta"><?= e($typeLabel($p['type'] ?: '?')) ?><?php if ($p['type'] !== 'whisper_cpp' && $p['type'] !== ''): ?> · <code><?= e(\Core\AI\Probe::endpoint($p)) ?></code><?php endif; ?>
            <?php if ($p['type'] !== 'whisper_cpp'): ?> · <?= e($p['api_key'] !== '' ? __('Schlüssel gesetzt') : __('ohne Schlüssel')) ?><?php endif; ?></span>
          <span class="aip-prof__meta"><?= e($ub ? __('Genutzt für: {list}', ['list' => implode(', ', $ub)]) : __('Noch keinem Zweck zugeordnet')) ?></span>
        </div>
      </div>
      <div class="aip-prof__acts">
        <button type="button" class="adm-btn adm-btn--small" data-aip-check="<?= e($id) ?>"><?= e(__('Verbindung prüfen')) ?></button>
        <button type="button" class="adm-btn adm-btn--small" data-aip-models="<?= e($id) ?>"><?= e(__('Modelle anzeigen')) ?></button>
        <?php if ($integrator): ?>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-aip-edit="<?= e($id) ?>"><?= e(__('Bearbeiten')) ?></button>
          <?php if (!$lk): ?><button type="submit" class="adm-btn adm-btn--small adm-btn--ghost aip-del" form="ai-del-<?= e($id) ?>"><?= e(__('Löschen')) ?></button><?php endif; ?>
        <?php endif; ?>
      </div>
      <p class="aip-result" data-aip-result role="status" aria-live="polite"<?= isset($checks[$id]) ? '' : ' hidden' ?>><?= isset($checks[$id]) ? e($checks[$id]['message'] . ' (' . __('geprüft {when}', ['when' => fmt()->relative((int) $checks[$id]["at"])]) . ')') : '' ?></p>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php if ($integrator): ?><p><button type="button" class="adm-btn" data-aip-add><?= icon('plus') ?> <?= e(__('Verbindung hinzufügen')) ?></button></p><?php endif; ?>
  </div>
</details>

<details class="f-sec" id="ki-use"<?= $profiles ? ' open' : '' ?>>
  <summary class="f-sec__sum"><span class="f-sec__title"><?= e(__('Verwendung')) ?></span><span class="f-sec__hint"><?= e(__('Zweck → Verbindung → Modell')) ?></span></summary>
  <div class="f-sec__body f-sec__body--block">
  <p class="adm-muted"><?= e(__('Je Zweck eine Verbindung und ein Modell. „Modelle …“ zeigt die Modelle der Verbindung zum Übernehmen per Klick; der Name lässt sich auch von Hand eintragen. Eine Ersatz-Verbindung springt ein, wenn die erste nicht antwortet (nicht bei Embeddings).')) ?></p>
  <div class="aip-scroll"><table class="adm-table aip-use">
    <thead><tr><th scope="col"><?= e(__('Zweck')) ?></th><th scope="col"><?= e(__('Verbindung')) ?></th><th scope="col"><?= e(__('Modell')) ?></th><th scope="col"><?= e(__('Ersatz')) ?></th></tr></thead>
    <tbody>
    <?php foreach (Profiles::PURPOSES as $pp => $label): $a = $assign[$pp]; $lk = $locks['assign'][$pp] ?? []; $dis = fn(string $k) => !$integrator || isset($lk[$k]) ? ' disabled title="' . $lockTitle . '"' : '';
        $opts = array_filter($profiles, fn($p) => in_array($pp, Profiles::SUPPORTS[$p['type']] ?? [], true));
        $ph = $pp === 'chat' ? __('wie Texte') : ($pp === 'transcribe' ? __('whisper-1 · voxtral-mini-latest') : __('Vorgabe der Verbindung')); ?>
      <tr data-aip-use="<?= e($pp) ?>">
        <th scope="row"><span class="aip-use__label"><?= e(__($label)) ?></span><small class="adm-muted"><?= e(__(Profiles::PURPOSE_HELP[$pp])) ?></small></th>
        <td><select form="ai-provider" name="ai[assign][<?= e($pp) ?>][profile]" aria-label="<?= e(__('Verbindung für „{p}“', ['p' => __($label)])) ?>" data-aip-uprof<?= $dis('profile') ?>>
          <option value=""><?= e($pp === 'chat' ? __('– wie Texte –') : __('– aus –')) ?></option>
          <?php foreach ($opts as $id => $p): ?><option value="<?= e($id) ?>"<?= $a['profile'] === $id ? ' selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?></select></td>
        <td><div class="aip-model">
          <input form="ai-provider" name="ai[assign][<?= e($pp) ?>][model]" value="<?= e($a['model']) ?>" maxlength="120" spellcheck="false" autocomplete="off"
            placeholder="<?= e($pp === 'transcribe' && ($profiles[$a['profile']]['type'] ?? '') === 'whisper_cpp' ? __('Modell der Verbindung') : $ph) ?>" aria-label="<?= e(__('Modell für „{p}“', ['p' => __($label)])) ?>" data-aip-umodel<?= $dis('model') ?>>
          <?php if ($integrator && !isset($lk['model'])): ?><button type="button" class="adm-btn adm-btn--small" data-aip-pick="<?= e($pp) ?>" aria-haspopup="dialog"><?= e(__('Modelle …')) ?></button><?php endif; ?>
        </div>
        <?php if ($pp === 'transcribe'): ?><label class="aip-lang"><span><?= e(__('Sprache')) ?></span> <input form="ai-provider" name="ai[assign][transcribe][language]" value="<?= e($a['language']) ?>" maxlength="5" placeholder="auto" aria-describedby="aip-lang-h"<?= $dis('language') ?>></label>
          <small class="adm-muted" id="aip-lang-h"><?= e(__('„auto“ erkennt die Sprache; beim Start lässt sie sich je Datei wählen.')) ?></small><?php endif; ?>
        <?php if ($pp === 'embed'): ?><p class="aip-warn" data-aip-embedwarn hidden><?= e(__('Neues Embedding-Modell: Nach dem Speichern berechnet der Suchindex alle Vektoren neu – bis dahin findet die semantische Suche weniger.')) ?></p><?php endif; ?></td>
        <td><?php if ($pp !== 'embed' && $pp !== 'transcribe'): ?>
          <select form="ai-provider" name="ai[assign][<?= e($pp) ?>][fallback]" aria-label="<?= e(__('Ersatz-Verbindung für „{p}“', ['p' => __($label)])) ?>"<?= $dis('fallback') ?>>
            <option value=""><?= e(__('– keine –')) ?></option>
            <?php foreach ($opts as $id => $p): ?><option value="<?= e($id) ?>"<?= $a['fallback'] === $id ? ' selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?></select>
          <input form="ai-provider" name="ai[assign][<?= e($pp) ?>][fallback_model]" value="<?= e($a['fallback_model']) ?>" maxlength="120" spellcheck="false" autocomplete="off" placeholder="<?= e(__('Modell (leer = gleiches)')) ?>" aria-label="<?= e(__('Ersatz-Modell für „{p}“', ['p' => __($label)])) ?>"<?= $dis('fallback_model') ?>>
        <?php else: ?><span class="adm-muted">–</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <details class="aip-more">
    <summary><?= e(__('Zeitlimits')) ?></summary>
    <div class="adm-fields">
      <?php foreach (['timeout' => [__('Besucher-Suche (Sekunden)'), '0.5', '0.5', '30'], 'text_timeout' => [__('Redaktion (Sekunden)'), '1', '5', '300'], 'index_timeout' => [__('Indexierung (Sekunden)'), '1', '5', '300']] as $k => [$l, $step, $min, $max]): ?>
      <div class="f f--half"><label for="aip-g-<?= e($k) ?>"><?= e($l) ?></label>
        <input id="aip-g-<?= e($k) ?>" type="number" step="<?= $step ?>" min="<?= $min ?>" max="<?= $max ?>" form="ai-provider" name="ai[<?= e($k) ?>]" value="<?= e((string) $cfg[$k]) ?>"<?= !$integrator || isset($locks['globals'][$k]) ? ' disabled title="' . $lockTitle . '"' : '' ?>></div>
      <?php endforeach; ?>
    </div>
  </details>
  <?php if ($integrator): ?><p class="aip-save"><button class="adm-btn adm-btn--primary" type="submit" form="ai-provider"><?= e(__('Verwendung speichern')) ?></button>
    <span class="adm-muted"><?= e(__('Empfehlung: eigener Ollama-Server (z. B. gemma3 für Texte und Bilder, bge-m3 oder nomic-embed-text für die Suche) oder ein EU-Anbieter wie Mistral AI.')) ?></span></p><?php endif; ?>
  </div>
</details>
</div>
<?php return; endif;

// ---------------------------------------------------------------- part 'panel': Datenschutz, Besucher-Chat, Nutzung
$external = false;
foreach (array_keys(Ai::CAPS) as $c) $external = $external || Ai::capability($c, $cfg)['external'];
$external = $external || Ai::capability('chat', $cfg)['external'];
?>
<details class="f-sec" id="ki-privacy"<?= $external ? ' open' : '' ?>>
  <summary class="f-sec__sum"><span class="f-sec__title"><?= e(__('Datenschutz')) ?></span><span class="f-sec__hint"><?= e($external ? __('externer Anbieter im Einsatz') : __('bleibt auf dem eigenen Server')) ?></span></summary>
  <div class="f-sec__body f-sec__body--block">
<div class="adm-flash <?= $external ? 'adm-flash--info' : 'adm-flash--success' ?>">
  <p><?= e($external
      ? __('Mindestens eine Fähigkeit nutzt einen externen Anbieter. Texte, Bilder oder Suchbegriffe, die eine KI-Funktion verarbeitet, gehen dorthin – nur wenn jemand die Funktion auslöst (bzw. bei der semantischen Suche: bei jeder Suche). Mit dem Anbieter ist ein Vertrag zur Auftragsverarbeitung nötig; bevorzugen Sie Anbieter mit Sitz/Rechenzentrum in der EU oder einen eigenen Ollama-Server. Keine Patientendaten oder andere besondere Kategorien personenbezogener Daten eingeben.')
      : __('Alle eingerichteten Fähigkeiten laufen auf einem eigenen Server (z. B. Ollama) – Inhalte verlassen die Infrastruktur nicht.')) ?></p>
  <?php if (Ai::capability('transcribe', $cfg)['external'] && Ai::capability('transcribe', $cfg)['configured']): ?><p><?= e(__('Sprache → Text: Die Tonspur von Videos/Audio, die jemand transkribieren lässt, geht an den externen Dienst. Bitte nur Aufnahmen ohne Gesundheitsdaten verwenden – oder whisper.cpp auf dem eigenen Server.')) ?></p><?php endif; ?>
  <?php if ($external): ?><blockquote class="adm-muted" id="ai-privacy"><?= e(\Core\Search\AdminSettings::privacyText()) ?></blockquote>
  <button type="button" class="adm-btn adm-btn--small" data-copy="#ai-privacy"><?= e(__('Text für die Datenschutzerklärung kopieren')) ?></button><?php endif; ?>
</div>
<?php if (Features::on('chat.visitor')): /* Besucher-Chat (Core\AI\VisitorChat): Stand, Tageslimit, Absatz für die Datenschutzerklärung */ $vc = \Core\AI\VisitorChat::quota(); ?>
<div class="adm-inline-box" id="ai-chat-box">
  <strong><?= e(__('Besucher-Chat')) ?></strong>
  <p><?= \Core\AI\VisitorChat::available() ? '<span class="adm-badge">' . e(__('aktiv')) . '</span> ' : '<span class="adm-muted">' . e(__('nicht aktiv')) . '</span> ' ?>
    <?= e($vc['cap'] > 0 ? __('Heute {used} von {cap} Antworten genutzt.', $vc) : __('Heute {used} Antworten (ohne Tageslimit).', $vc)) ?>
    <?php if (!\Core\Search\Search::enabled()): ?><br><span class="adm-muted"><?= e(__('Der Chat braucht die Website-Suche (Funktion „search“).')) ?></span><?php endif; ?></p>
  <p class="adm-muted"><?= e(__('Absatz für die Datenschutzerklärung (vor dem Einschalten übernehmen):')) ?></p>
  <blockquote class="adm-muted" id="ai-chat-privacy"><?= e(\Core\AI\VisitorChat::privacyPolicyText()) ?></blockquote>
  <button type="button" class="adm-btn adm-btn--small" data-copy="#ai-chat-privacy"><?= e(__('Text für die Datenschutzerklärung kopieren')) ?></button>
</div>
<?php endif; ?>
  </div>
</details>

<?php $usage = $cfg['configured'] ? Ai::usage(30) : []; if ($usage): ?>
<details class="f-sec" id="ai-usage">
  <summary class="f-sec__sum"><span class="f-sec__title"><?= e(__('Nutzung (letzte 30 Tage, diese Website)')) ?></span><span class="f-sec__hint"><?= e(__('{n} Aufrufe', ['n' => array_sum(array_column($usage, 'calls'))])) ?></span></summary>
  <div class="f-sec__body f-sec__body--block">
  <div class="aip-scroll"><table class="adm-table px-table">
    <thead><tr><th><?= e(__('Fähigkeit')) ?></th><th><?= e(__('Modell')) ?></th><th class="num"><?= e(__('Aufrufe')) ?></th><th class="num"><?= e(__('Fehler')) ?></th><th class="num"><?= e(__('Tokens ein/aus')) ?></th><th class="num"><?= e(__('Ø Dauer')) ?></th><th class="num"><?= e(__('Kosten')) ?></th></tr></thead>
    <tbody><?php foreach ($usage as $u): ?>
      <tr><td><code><?= e($u['cap']) ?></code></td><td><?= e($u['provider'] . ' · ' . $u['model']) ?></td><td class="num"><?= (int) $u['calls'] ?></td><td class="num"><?= (int) $u['errors'] ?></td>
        <td class="num"><?= e($u['cap'] === 'transcribe' ? __('{min} min Audio', ['min' => number_format((int) ($u['seconds'] ?? 0) / 60, 1, ',', '.')]) : number_format((int) $u['tokens_in'], 0, ',', '.') . ' / ' . number_format((int) $u['tokens_out'], 0, ',', '.')) ?></td>
        <td class="num"><?= $u['calls'] ? e(number_format($u['ms'] / $u['calls'], 0, ',', '.') . ' ms') : '–' ?></td>
        <td class="num"><?= $u['cost'] !== null ? e('$ ' . number_format((float) $u['cost'], 4, ',', '.')) : '–' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="adm-muted"><?= e(__('Gezählt werden nur Aufrufe, Tokens und Dauer – nie Inhalte. Kosten nur mit Preisangaben in der Konfiguration (\'prices\').')) ?></p>
  </div>
</details>
<?php endif;
