<?php
/**
 * Grundeinstellungen → Push-Benachrichtigungen (Core\Push): Funktion, VAPID-Schlüssel, Abos, Warteschlange, letzter Versand,
 * Cron, Umgebung, Testnachricht, Vorschlag für die Datenschutzerklärung. Eigene Formulare (#push-keys, #push-test) unten in system.php.
 */
use Core\Push\Push;
use Core\Push\Topics;

$ps = Push::status();
$psLast = (array) ($ps['last_run'] ?? []);
$psStats = (array) ($psLast['stats'] ?? []);
$psTables = [];
foreach (\Core\Data\Tables::content() as $t) $psTables[\Core\Push\Topics::topic($t)] = $t;
$psCan = \Core\Features::canView();
?>
    <h2><?= e(__('Push-Benachrichtigungen')) ?></h2>
    <p class="set-page__lead"><?= e(__('Mitteilungen auf Telefon und Computer – auch bei geschlossenem Browser-Tab. Für die Redaktion (Konto → Benachrichtigungen) und als Abo neuer Einträge für Besucher (je Datentabelle, Block „Benachrichtigungen abonnieren“). Zugestellt wird verschlüsselt über die Push-Dienste der Browser-Hersteller (Web Push, VAPID).')) ?></p>
    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Status')) ?></h3>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Funktion „Push-Benachrichtigungen“')) ?></span>
          <span class="set-row__sub"><?= e(__('Ein- und ausschalten unter Administration → Funktionen & Erweiterungen (Kommunikation).')) ?></span></div>
          <div class="set-row__ctl"><?= $ps['feature'] ? '<span class="adm-badge">' . e(__('an')) . '</span>' : '<span class="adm-badge adm-badge--muted">' . e(__('aus')) . '</span>' ?>
            <?php if ($psCan): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/funktionen#f-push')) ?>"><?= e(__('Funktionen')) ?></a><?php endif; ?></div></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('VAPID-Schlüssel')) ?></span>
          <span class="set-row__sub"><?= e(__('Ein Schlüsselpaar für alle Websites dieser Installation (config/config.local.php). Neu erzeugen nur auf der Kommandozeile – alle Abos werden dabei ungültig.')) ?></span></div>
          <div class="set-row__ctl"><?php if ($ps['keys']): ?><span class="adm-badge"><?= e(__('vorhanden')) ?></span> <code><?= e($ps['fingerprint']) ?></code><?php else: ?>
            <span class="adm-badge adm-badge--warn"><?= e(__('fehlen')) ?></span><?php if (can('system.manage')): ?> <button class="adm-btn adm-btn--small adm-btn--primary" type="submit" form="push-keys"><?= e(__('Schlüssel anlegen')) ?></button><?php endif; ?><?php endif; ?></div></div>
        <?php if (!empty($ps['created'])): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Erzeugt')) ?></span></div><div class="set-row__ctl"><?= e(fmt()->datetime((string) $ps['created'])) ?></div></div><?php endif; ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Umgebung')) ?></span>
          <span class="set-row__sub"><?= e($ps['visitors_allowed'] ? __('Nachrichten an Besucher werden gesendet.') : __('Testumgebung: keine Nachrichten an Besucher (config push_staging_visitors). Die Redaktion erhält ihre Mitteilungen.')) ?></span></div>
          <div class="set-row__ctl"><code><?= e((string) $ps['environment']) ?></code></div></div>
      </div>
    </section>

    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Abos')) ?></h3>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Geräte der Redaktion')) ?></span></div>
          <div class="set-row__ctl"><?= e(__('{n} Gerät(e) von {p} Person(en)', ['n' => fmt()->number($ps['devices']), 'p' => fmt()->number($ps['people'])])) ?></div></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Besucher')) ?></span>
          <span class="set-row__sub"><?= e(__('Abos neuer Einträge – ohne Konto, ohne IP-Adresse.')) ?></span></div>
          <div class="set-row__ctl"><?= e(fmt()->number($ps['visitors'])) ?></div></div>
        <?php foreach ($ps['topics'] as $tp => $n): $tt = $psTables[$tp] ?? null; ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($tt ? __('Thema „{name}“', ['name' => $tt['name']]) : $tp) ?></span>
          <span class="set-row__sub"><code><?= e($tp) ?></code><?= $tt && !Topics::enabled($tt) ? ' · ' . e(__('Abo derzeit abgeschaltet')) : '' ?></span></div>
          <div class="set-row__ctl"><?= e(fmt()->number($n)) ?></div></div>
        <?php endforeach; ?>
        <?php if ($ps['stale']): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Veraltet (anderer Schlüssel)')) ?></span>
          <span class="set-row__sub"><?= e(__('Werden beim nächsten Versand gelöscht; Browser abonnieren beim nächsten Besuch neu.')) ?></span></div><div class="set-row__ctl"><?= e(fmt()->number($ps['stale'])) ?></div></div><?php endif; ?>
      </div>
      <?php $psSubT = Topics::tables(); ?>
      <p class="set-group__note"><?= $psSubT ? e(__('Abonnierbar: {list}.', ['list' => implode(', ', array_column($psSubT, 'name'))])) : e(__('Noch keine Tabelle bietet ein Abo an – einschalten je Tabelle unter Daten → Tabelle → Felder & Einstellungen → „Benachrichtigungen (Push)“.')) ?></p>
    </section>

    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Versand')) ?></h3>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Warteschlange')) ?></span></div>
          <div class="set-row__ctl"><?= $ps['queued'] ? '<span class="adm-badge adm-badge--warn">' . e(__('{n} wartend', ['n' => fmt()->number($ps['queued'])])) . '</span>' : e(__('leer')) ?></div></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Letzte 7 Tage')) ?></span>
          <?php if (!empty($ps['last_error'])): ?><span class="set-row__sub"><?= e(__('Letzter Fehler: {error}', ['error' => (string) $ps['last_error']])) ?></span><?php endif; ?></div>
          <div class="set-row__ctl"><?= e(__('{sent} zugestellt · {failed} fehlgeschlagen · {gone} Abos abgelaufen', ['sent' => fmt()->number($ps['sent7']), 'failed' => fmt()->number($ps['failed7']), 'gone' => fmt()->number($ps['gone7'])])) ?></div></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Letzter Versandlauf')) ?></span>
          <?php if ($psStats): ?><span class="set-row__sub"><?= e(__('{sent} gesendet, {retry} später erneut, {failed} fehlgeschlagen, {gone} abgelaufen', ['sent' => (int) ($psStats['sent'] ?? 0), 'retry' => (int) ($psStats['retry'] ?? 0), 'failed' => (int) ($psStats['failed'] ?? 0), 'gone' => (int) ($psStats['gone'] ?? 0)])) ?></span><?php endif; ?></div>
          <div class="set-row__ctl"><?= !empty($psLast['at']) ? e(fmt()->relative((string) $psLast['at'])) : e(__('noch nie')) ?></div></div>
        <div class="set-row set-row--stack"><div class="set-row__main"><span class="set-row__label"><?= e(__('Cronjob (empfohlen)')) ?></span>
          <span class="set-row__sub"><?= e(__('Ohne Cron sendet die Website kleine Mengen nebenbei nach Aufrufen. Mit Cron kommen Mitteilungen pünktlich, auch für viele Abos:')) ?></span></div>
          <?php $cronCmd = 'cd ' . ROOT . ' && ' . \Core\Network\Stats::phpBinary() . ' bin/console push:send --all'; ?>
          <div class="set-row__body">
            <div class="adm-secret"><code id="push-cron"><?= e($cronCmd) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#push-cron"><?= e(__('Kopieren')) ?></button></div>
            <ol class="f-help push-cron-steps">
              <li><?= e(__('Plesk: Websites & Domains → diese Domain → „Geplante Aufgaben“ (Cron-Jobs) → „Aufgabe hinzufügen“.')) ?></li>
              <li><?= e(__('Aufgabentyp „Befehl ausführen“, den Befehl oben einfügen, Ausführung „Cron-Stil“ mit * * * * * (jede Minute; alle 5 Minuten reicht auch: */5 * * * *).')) ?></li>
              <li><?= e(__('Benachrichtigungen per E-Mail: „Nicht senden“ bzw. nur bei Fehlern. Speichern.')) ?></li>
              <li><?= e(__('Ohne Plesk: denselben Befehl mit crontab -e für den Benutzer der Website eintragen, davor * * * * *.')) ?></li>
              <li><?= e(__('Prüfen: Nach ein, zwei Minuten steht oben bei „Letzter Versandlauf“ eine aktuelle Zeit. Eine Aufgabe genügt für alle Websites dieser Installation (--all).')) ?></li>
            </ol>
          </div></div>
      </div>
      <?php if ($ps['feature'] && $ps['keys']): ?>
      <div class="set-actions">
        <button class="adm-btn" type="submit" form="push-test"><?= icon('paper-plane-tilt') ?> <?= e(__('Testnachricht an mich')) ?></button>
        <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/account#benachrichtigungen')) ?>"><?= e(__('Konto → Benachrichtigungen')) ?></a>
      </div>
      <p class="set-group__note"><?= e(__('Die Testnachricht geht an alle Geräte, die Sie unter Konto → Benachrichtigungen angemeldet haben.')) ?></p>
      <?php endif; ?>
    </section>

    <details class="set-group set-group--collapse" id="push-privacy">
      <summary class="set-group__title"><?= e(__('Datenschutzerklärung ergänzen')) ?> <span class="adm-muted">· <?= e(__('Vorschlag, sobald Besucher abonnieren können')) ?></span></summary>
      <div class="set-list"><div class="set-row set-row--stack"><div class="set-row__body"><blockquote class="adm-muted" id="push-privacy-text"><?= e(Push::privacyText()) ?></blockquote>
        <button type="button" class="adm-btn adm-btn--small" data-copy="#push-privacy-text"><?= e(__('Kopieren')) ?></button></div></div></div>
    </details>
