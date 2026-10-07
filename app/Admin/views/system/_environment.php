<?php
/**
 * Umgebung der Website (staging ↔ production) – Grundeinstellungen → Umgebung (DomainController::environment).
 * Erwartet $env (environment()). Formular steht außerhalb des Einstellungsformulars (form="environment").
 */
$envLabel = ['production' => __('Livebetrieb (production)'), 'staging' => __('Testumgebung (staging)'), 'development' => __('Entwicklung (development)')];
?>
    <h2><?= e(__('Umgebung')) ?></h2>
    <dl class="adm-dl"><dt><?= e(__('Aktuell')) ?></dt><dd><span class="adm-badge<?= $env === 'production' ? '' : ' adm-badge--warn' ?>"><?= e($envLabel[$env] ?? $env) ?></span></dd></dl>
    <ul class="f-help">
      <li><?= e(__('Livebetrieb: Suchmaschinen dürfen die Website aufnehmen (sofern nicht in den Grundeinstellungen gesperrt), E-Mails gehen an die echten Empfänger.')) ?></li>
      <li><?= e(__('Testumgebung: Suchmaschinen werden immer ausgesperrt (noindex). E-Mails gehen nie an echte Empfänger – sie werden an die Adresse aus mail_redirect umgeleitet (Betreff mit [STAGING]) oder nur protokolliert und nicht versendet. Ist staging_auth eingerichtet, fragt die Website nach einem Passwort.')) ?></li>
    </ul>
    <div class="adm-fields">
      <div class="f f--half"><label for="env-select"><?= e(__('Umgebung')) ?></label>
        <select id="env-select" name="environment" form="environment">
          <?php foreach (['production', 'staging'] as $k): ?><option value="<?= e($k) ?>"<?= $env === $k ? ' selected' : '' ?>><?= e($envLabel[$k]) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit" form="environment" data-confirm="<?= e(__('Umgebung wirklich umstellen? Das ändert, ob Suchmaschinen die Website sehen und ob E-Mails versendet werden.')) ?>"><?= e(__('Umstellen')) ?></button></div>
