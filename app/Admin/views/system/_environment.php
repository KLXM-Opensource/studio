<?php
/**
 * Umgebung der Website (staging ↔ production) – Grundeinstellungen → Umgebung (DomainController::environment).
 * Erwartet $env (environment()). Formular steht außerhalb des Einstellungsformulars (form="environment").
 */
$envLabel = ['production' => __('Livebetrieb (production)'), 'staging' => __('Testumgebung (staging)'), 'development' => __('Entwicklung (development)')];
?>
    <h2><?= e(__('Umgebung')) ?></h2>
    <section class="set-group">
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Aktuell')) ?></span></div>
          <div class="set-row__ctl"><span class="adm-badge<?= $env === 'production' ? '' : ' adm-badge--warn' ?>"><?= e($envLabel[$env] ?? $env) ?></span></div></div>
        <div class="f f--inline"><label for="env-select"><?= e(__('Umgebung')) ?></label>
          <select id="env-select" name="environment" form="environment">
            <?php foreach (['production', 'staging'] as $k): ?><option value="<?= e($k) ?>"<?= $env === $k ? ' selected' : '' ?>><?= e($envLabel[$k]) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <p class="set-group__note"><?= e(__('Livebetrieb: Suchmaschinen dürfen die Website aufnehmen (sofern nicht in den Grundeinstellungen gesperrt), E-Mails gehen an die echten Empfänger.')) ?></p>
      <p class="set-group__note"><?= e(__('Testumgebung: Suchmaschinen werden immer ausgesperrt (noindex). E-Mails gehen nie an echte Empfänger – sie werden an die Adresse aus mail_redirect umgeleitet (Betreff mit [STAGING]) oder nur protokolliert und nicht versendet. Ist staging_auth eingerichtet, fragt die Website nach einem Passwort.')) ?></p>
      <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit" form="environment" data-confirm="<?= e(__('Umgebung wirklich umstellen? Das ändert, ob Suchmaschinen die Website sehen und ob E-Mails versendet werden.')) ?>"><?= e(__('Umstellen')) ?></button></div>
    </section>
