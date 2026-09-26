<?php
/**
 * Grundeinstellungen → Reiter „KI“ (Symfony AI, Core\AI\Ai): Status je Fähigkeit mit „Verbindung testen“,
 * Anbieter der Installation (nur Agentur/Netzwerk), Datenschutz-Hinweis, Nutzung der letzten 30 Tage.
 * $part = 'panel' | 'forms'
 * @var string $part
 */
use Core\AI\Ai;
use Core\Features;

if (!Features::on('ai')) return;
$integrator = Features::integrator();
if ($part === 'forms'): ?>
<form id="ai-test" method="post" action="<?= e(url('/admin/system/ai/test')) ?>"><?= csrf_field() ?></form>
<?php if ($integrator): ?><form id="ai-provider" method="post" action="<?= e(url('/admin/system/ai/provider')) ?>"><?= csrf_field() ?></form><?php endif; ?>
<?php return; endif;

$cfg = Ai::config();
$stored = Ai::stored();
$locked = Ai::fileConfig();
$site = Ai::siteSettings();
$external = false;
foreach (array_keys(Ai::CAPS) as $c) $external = $external || Ai::capability($c, $cfg)['external'];
$lock = fn(string $k) => array_key_exists($k, $locked) ? ' disabled title="' . e(__('in config/config.local.php festgelegt')) . '"' : '';
?>
<div class="adm-inline-box" id="ai-status">
  <strong><?= e(__('Fähigkeiten')) ?></strong>
  <?php if (!$cfg['configured']): ?>
    <p class="adm-flash adm-flash--info"><?= e($integrator ? __('Noch kein KI-Anbieter eingerichtet – unten unter „Anbieter der Installation“ festlegen.') : __('Noch kein KI-Anbieter eingerichtet. Das übernimmt die Agentur bzw. Netzwerk-Administration.')) ?></p>
  <?php endif; ?>
  <table class="adm-table px-table">
    <thead><tr><th><?= e(__('Fähigkeit')) ?></th><th><?= e(__('Anbieter')) ?></th><th><?= e(__('Modell')) ?></th><th><?= e(__('Daten')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach (Ai::CAPS as $cap => $label): $c = Ai::capability($cap, $cfg); $on = Ai::enabled($cap); ?>
      <tr>
        <td><strong><?= e(__($label)) ?></strong><br><code><?= e($cap) ?></code></td>
        <td><?= e($c['provider'] !== '' ? __(Ai::PROVIDERS[$c['provider']] ?? $c['provider']) : '–') ?></td>
        <td><?= $c['model'] !== '' ? '<code>' . e($c['model']) . '</code>' : '–' ?></td>
        <td><?= !$c['configured'] ? '–' : ($c['external'] ? '<span class="adm-badge adm-badge--draft">' . e(__('extern')) . '</span>' : '<span class="adm-badge">' . e(__('bleibt auf dem Server')) . '</span>') ?></td>
        <td><?= $on ? '<span class="adm-badge">' . e(__('aktiv')) . '</span>' : '<span class="adm-muted">' . e(!$c['configured'] ? __('nicht eingerichtet') : (!$site['enabled'] ? __('Website: aus') : __('aus'))) . '</span>' ?></td>
        <td><?php if ($c['configured']): ?><button class="adm-btn adm-btn--small" type="submit" form="ai-test" name="cap" value="<?= e($cap) ?>"><?= e(__('Verbindung testen')) ?></button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="adm-muted"><?= e(__('Wer KI nutzen darf, steuert das Recht „KI-Funktionen nutzen“ unter Benutzer & Rollen (Standard: Redaktion und Administration).')) ?></p>
  <?php $q = \Core\AI\Assist::quota(); ?>
  <p><?= e($q['cap'] > 0 ? __('KI-Assistent heute: {used} von {cap} Aufrufen genutzt, {left} übrig.', $q) : __('KI-Assistent heute: {used} Aufrufe (ohne Tageslimit).', $q)) ?>
    · <a href="<?= e(url('/admin/ai/seo')) ?>"><?= e(__('SEO-Übersicht')) ?></a></p>
</div>

<div class="adm-flash <?= $external ? 'adm-flash--info' : 'adm-flash--success' ?>">
  <p><strong><?= e(__('Datenschutz')) ?>:</strong>
  <?= e($external
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

<div class="adm-inline-box" id="ai-provider-box">
  <strong><?= e(__('Anbieter der Installation')) ?></strong>
  <?php if (!$integrator): ?>
    <p class="adm-muted"><?= e(__('Anbieter, Modelle und Schlüssel legt die Agentur bzw. Netzwerk-Administration für alle Websites fest.')) ?></p>
    <?php if ($cfg['configured']): ?><p><?= e(__(Ai::PROVIDERS[$cfg['provider']] ?? $cfg['provider'])) ?> · <code><?= e(Ai::endpoint($cfg)) ?></code></p><?php endif; ?>
  <?php else: ?>
    <p class="adm-muted"><?= e(__('Gilt für alle Websites dieser Installation (storage/ai/config.json, nicht im Webroot). Werte aus config/config.local.php bzw. config/sites/*.php haben Vorrang und sind hier gesperrt.')) ?></p>
    <div class="adm-fields">
      <div class="f f--half"><label for="ai-p"><?= e(__('Anbieter')) ?></label>
        <select id="ai-p" form="ai-provider" name="ai[provider]"<?= $lock('provider') ?>><option value=""><?= e(__('– keiner (KI aus) –')) ?></option>
        <?php foreach (Ai::PROVIDERS as $k => $l): ?><option value="<?= e($k) ?>"<?= $cfg['provider'] === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
      <div class="f f--half"><label for="ai-u"><?= e(__('Adresse (base_url)')) ?></label>
        <input id="ai-u" form="ai-provider" name="ai[base_url]" value="<?= e((string) ($stored['base_url'] ?? $cfg['base_url'])) ?>" placeholder="http://127.0.0.1:11434" aria-describedby="ai-u-h"<?= $lock('base_url') ?>>
        <p class="f-help" id="ai-u-h"><?= e(__('Ollama: http://127.0.0.1:11434 · OpenAI-kompatibel: Basis-URL des Dienstes · Mistral/OpenAI: leer lassen')) ?></p></div>
      <div class="f f--half"><label for="ai-k"><?= e(__('API-Schlüssel')) ?></label>
        <input id="ai-k" type="password" form="ai-provider" name="ai[api_key]" value="" autocomplete="off" placeholder="<?= e($cfg['api_key'] !== '' ? __('gesetzt – leer lassen = unverändert') : __('nicht nötig für Ollama')) ?>" aria-describedby="ai-k-h"<?= $lock('api_key') ?>>
        <p class="f-help" id="ai-k-h"><?= e(__('„-“ eingeben, um den Schlüssel zu löschen.')) ?></p></div>
      <div class="f f--half"><label for="ai-r"><?= e(__('Region (nur OpenAI)')) ?></label>
        <select id="ai-r" form="ai-provider" name="ai[region]"<?= $lock('region') ?>><option value=""><?= e(__('Standard')) ?></option><option value="EU"<?= $cfg['region'] === 'EU' ? ' selected' : '' ?>><?= e(__('EU-Datenresidenz')) ?></option></select></div>
      <?php foreach (Ai::CAPS as $cap => $label): if ($cap === 'transcribe') continue; ?>
      <div class="f f--half"><label for="ai-m-<?= e($cap) ?>"><?= e(__('Modell: {cap}', ['cap' => __($label)])) ?></label>
        <input id="ai-m-<?= e($cap) ?>" form="ai-provider" name="ai[models][<?= e($cap) ?>]" value="<?= e((string) ($stored['models'][$cap] ?? '')) ?>" placeholder="<?= e($cfg['models'][$cap] ?: ['text' => 'gemma3', 'embed' => 'nomic-embed-text', 'vision' => 'gemma3'][$cap]) ?>"<?= isset($locked['models'][$cap]) ? ' disabled' : '' ?>></div>
      <?php endforeach; ?>
      <div class="f f--half"><label for="ai-t"><?= e(__('Zeitlimit Besucher-Suche (Sekunden)')) ?></label>
        <input id="ai-t" type="number" step="0.5" min="0.5" max="30" form="ai-provider" name="ai[timeout]" value="<?= e((string) $cfg['timeout']) ?>"<?= $lock('timeout') ?>></div>
      <div class="f f--half"><label for="ai-tt"><?= e(__('Zeitlimit Redaktion (Sekunden)')) ?></label>
        <input id="ai-tt" type="number" min="5" max="300" form="ai-provider" name="ai[text_timeout]" value="<?= e((string) (int) $cfg['text_timeout']) ?>"<?= $lock('text_timeout') ?>></div>
    </div>
    <p class="adm-muted"><?= e(__('Empfehlung: eigener Ollama-Server (z. B. Modelle gemma3 für Text und Bilder, bge-m3 oder nomic-embed-text für die Suche) oder ein EU-Anbieter wie Mistral AI.')) ?></p>
    <?php // Sprache → Text (Untertitel): Ollama kann das nicht – whisper.cpp auf diesem Server oder ein OpenAI-kompatibler Dienst (Core\AI\Transcriber)
    $tc = $cfg['transcribe']; $tp = \Core\AI\Transcriber::paths($tc); $tl = isset($locked['transcribe']) ? ' disabled title="' . e(__('in config/config.local.php festgelegt')) . '"' : ''; ?>
    <fieldset class="ai-tr" id="ai-transcribe">
      <legend><?= e(__('Sprache → Text (Untertitel für Videos und Audio)')) ?></legend>
      <p class="adm-muted"><?= e(__('Ollama hat kein Spracherkennungs-Modell. Lokal und ohne Datenweitergabe: whisper.cpp auf diesem Server (Programm whisper-cli, ein Modell wie ggml-large-v3-turbo-q5_0.bin in storage/ai/models/, ffmpeg). Alternativ ein OpenAI-kompatibler Dienst (OpenAI whisper-1, Mistral Voxtral, eigener faster-whisper-Server).')) ?></p>
      <div class="adm-fields">
        <div class="f f--half"><label for="ai-tr-b"><?= e(__('Verfahren')) ?></label>
          <select id="ai-tr-b" form="ai-provider" name="ai[transcribe][backend]"<?= $tl ?>><option value=""><?= e(__('– aus –')) ?></option>
          <?php foreach (\Core\AI\Transcriber::BACKENDS as $k => $l): ?><option value="<?= e($k) ?>"<?= $tc['backend'] === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></div>
        <div class="f f--half"><label for="ai-tr-l"><?= e(__('Sprache (Standard)')) ?></label>
          <input id="ai-tr-l" form="ai-provider" name="ai[transcribe][language]" value="<?= e($tc['language']) ?>" placeholder="auto" aria-describedby="ai-tr-l-h"<?= $tl ?>>
          <p class="f-help" id="ai-tr-l-h"><?= e(__('„auto“ erkennt die Sprache; beim Start lässt sie sich je Datei wählen.')) ?></p></div>
        <div class="f f--half"><label for="ai-tr-w"><?= e(__('whisper-cli (Pfad)')) ?></label>
          <input id="ai-tr-w" form="ai-provider" name="ai[transcribe][whisper_bin]" value="<?= e($tc['whisper_bin']) ?>" placeholder="<?= e($tp['whisper_bin'] ?: '/usr/local/bin/whisper-cli') ?>"<?= $tl ?>></div>
        <div class="f f--half"><label for="ai-tr-m"><?= e(__('Whisper-Modell (Pfad)')) ?></label>
          <input id="ai-tr-m" form="ai-provider" name="ai[transcribe][model_path]" value="<?= e($tc['model_path']) ?>" placeholder="<?= e($tp['model_path'] !== '' ? str_replace(ROOT . '/', '', $tp['model_path']) : 'storage/ai/models/ggml-large-v3-turbo-q5_0.bin') ?>"<?= $tl ?>></div>
        <div class="f f--half"><label for="ai-tr-f"><?= e(__('ffmpeg (Pfad)')) ?></label>
          <input id="ai-tr-f" form="ai-provider" name="ai[transcribe][ffmpeg]" value="<?= e($tc['ffmpeg']) ?>" placeholder="<?= e($tp['ffmpeg'] ?: '/usr/bin/ffmpeg') ?>"<?= $tl ?>></div>
        <div class="f f--half"><label for="ai-tr-t"><?= e(__('Threads / Zeitlimit (Sekunden)')) ?></label>
          <span class="adm-row"><input id="ai-tr-t" type="number" min="1" max="64" form="ai-provider" name="ai[transcribe][threads]" value="<?= (int) $tc['threads'] ?>" aria-label="<?= e(__('Threads')) ?>"<?= $tl ?>>
          <input type="number" min="60" max="21600" step="60" form="ai-provider" name="ai[transcribe][timeout]" value="<?= (int) $tc['timeout'] ?>" aria-label="<?= e(__('Zeitlimit (Sekunden)')) ?>"<?= $tl ?>></span></div>
        <div class="f f--half"><label for="ai-tr-u"><?= e(__('OpenAI-kompatibel: Adresse (base_url)')) ?></label>
          <input id="ai-tr-u" form="ai-provider" name="ai[transcribe][base_url]" value="<?= e($tc['base_url']) ?>" placeholder="https://api.openai.com"<?= $tl ?>></div>
        <div class="f f--half"><label for="ai-tr-mo"><?= e(__('OpenAI-kompatibel: Modell / Schlüssel')) ?></label>
          <span class="adm-row"><input id="ai-tr-mo" form="ai-provider" name="ai[transcribe][model]" value="<?= e($tc['model']) ?>" placeholder="whisper-1 · voxtral-mini-latest"<?= $tl ?>>
          <input type="password" form="ai-provider" name="ai[transcribe][api_key]" value="" autocomplete="off" aria-label="<?= e(__('API-Schlüssel')) ?>" placeholder="<?= e($tc['api_key'] !== '' ? __('gesetzt – leer lassen = unverändert') : __('API-Schlüssel')) ?>"<?= $tl ?>></span></div>
      </div>
      <p class="adm-muted"><?= e(__('Leere Pfade werden automatisch gesucht (storage/ai/bin, /opt/homebrew/bin, /usr/local/bin, /usr/bin; Modelle in storage/ai/models). Eine Stunde Video braucht mit large-v3-turbo auf 4 CPU-Kernen etwa 20–60 Minuten; es läuft immer nur ein Auftrag gleichzeitig.')) ?></p>
    </fieldset>
    <button class="adm-btn adm-btn--primary" type="submit" form="ai-provider"><?= e(__('Anbieter speichern')) ?></button>
  <?php endif; ?>
</div>

<?php $usage = $cfg['configured'] ? Ai::usage(30) : []; if ($usage): ?>
<div class="adm-inline-box" id="ai-usage">
  <strong><?= e(__('Nutzung (letzte 30 Tage, diese Website)')) ?></strong>
  <table class="adm-table px-table">
    <thead><tr><th><?= e(__('Fähigkeit')) ?></th><th><?= e(__('Modell')) ?></th><th class="num"><?= e(__('Aufrufe')) ?></th><th class="num"><?= e(__('Fehler')) ?></th><th class="num"><?= e(__('Tokens ein/aus')) ?></th><th class="num"><?= e(__('Ø Dauer')) ?></th><th class="num"><?= e(__('Kosten')) ?></th></tr></thead>
    <tbody><?php foreach ($usage as $u): ?>
      <tr><td><code><?= e($u['cap']) ?></code></td><td><?= e($u['provider'] . ' · ' . $u['model']) ?></td><td class="num"><?= (int) $u['calls'] ?></td><td class="num"><?= (int) $u['errors'] ?></td>
        <td class="num"><?= e($u['cap'] === 'transcribe' ? __('{min} min Audio', ['min' => number_format((int) ($u['seconds'] ?? 0) / 60, 1, ',', '.')]) : number_format((int) $u['tokens_in'], 0, ',', '.') . ' / ' . number_format((int) $u['tokens_out'], 0, ',', '.')) ?></td>
        <td class="num"><?= $u['calls'] ? e(number_format($u['ms'] / $u['calls'], 0, ',', '.') . ' ms') : '–' ?></td>
        <td class="num"><?= $u['cost'] !== null ? e('$ ' . number_format((float) $u['cost'], 4, ',', '.')) : '–' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <p class="adm-muted"><?= e(__('Gezählt werden nur Aufrufe, Tokens und Dauer – nie Inhalte. Kosten nur mit Preisangaben in der Konfiguration (\'prices\').')) ?></p>
</div>
<?php endif;
