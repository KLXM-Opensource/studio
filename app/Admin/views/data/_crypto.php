<?php
/**
 * Tabellen-Designer, Bereich „Verschlüsselung“: eine Wahl, wie Einsendungen geschützt werden – keine Verschlüsselung (normale
 * Tabelle), zentraler Schlüssel (Eingang, Core\Data\Inbox + FormCrypto), zentraler Schlüssel + Kopie per E-Mail, nur per E-Mail
 * (Core\Data\Delivery). Dazu Stand des Schlüssels und S/MIME für die E-Mail. Gespeichert als settings[protection] – Tables::validate
 * übersetzt das in Art der Tabelle (kind) und Zustellung (inbox.delivery.mode); die Art wechselt nur, solange die Tabelle leer ist.
 * @var array $s  @var ?array $table  @var callable $err  @var bool $empty  @var bool $sharedT
 */
use Core\Data\Delivery;
use Core\Data\Inbox;
use Core\FormCrypto;

$inbox = ($s['kind'] ?? 'content') === 'inbox';
$dv = (array) ($s['inbox']['delivery'] ?? []) + Delivery::DEFAULTS;
$cur = $inbox ? (string) $dv['mode'] : 'none';
$switch = $empty && !$sharedT;                                    // Art (normal ⇄ Eingang) nur, solange leer
$managed = $table && $inbox && Delivery::managed($table);
$canDeliver = !$table || !$inbox || can('requests.manage', $table['handle']);
$certs = $dv['smime'] !== '' ? Delivery::certs($dv['smime']) : [];
$opts = [
    'none' => [__('Keine Verschlüsselung'), 'database', __('Einträge liegen normal in der Datenbank – die Redaktion sieht und bearbeitet sie direkt. Richtig für Inhalte der Website, Anmeldungen und Listen ohne vertrauliche Angaben.'),
        __('Mitlesen können: alle mit Zugang zu dieser Tabelle, die Server-Administration.')],
    'system' => [__('Zentraler Schlüssel'), 'lock-key', __('Jede Einsendung wird schon beim Absenden verschlüsselt gespeichert. Lesbar nur unter „Anfragen“ mit dem geheimen Schlüssel der Website – auch wer an die Datenbank kommt, sieht nur Zeichensalat. Empfohlen für Gesundheitsdaten und andere vertrauliche Anfragen.'),
        __('Mitlesen können: nur Personen mit dem geheimen Schlüssel und dem Recht „Anfragen lesen“. Jedes Öffnen wird protokolliert.')],
    'both' => [__('Zentraler Schlüssel + Kopie per E-Mail'), 'envelope-simple', __('Wie „Zentraler Schlüssel“, zusätzlich geht der volle Inhalt per E-Mail an die Empfänger (unter „Formular & Eingang → Zustellung“).'),
        __('Die E-Mail ist ohne S/MIME nur auf dem Weg verschlüsselt und liegt lesbar im Postfach.')],
    'mail' => [__('Nur per E-Mail, nichts speichern'), 'paper-plane-tilt', __('Die Einsendung geht nur per E-Mail hinaus; auf der Website bleibt nichts außer einem Zustellprotokoll ohne Inhalte. Scheitert der Versand, wird sie verschlüsselt gesichert.'),
        __('Ohne S/MIME liegt die E-Mail lesbar im Postfach – mit S/MIME (unten) nur für die Empfänger lesbar.')],
];
// Warum eine Wahl gerade nicht geht (null = wählbar)
$why = function (string $o) use ($inbox, $switch, $managed, $sharedT): ?string {
    $wantInbox = $o !== 'none';
    if ($wantInbox !== $inbox && !$switch) return $sharedT ? __('Bei geteilten Tabellen nicht möglich.') : __('Nur wählbar, solange die Tabelle keine Einträge hat – sonst eine neue Tabelle anlegen.');
    if ($wantInbox && !Inbox::available()) return __('Dafür die Funktion „Anfragen“ einschalten (Funktionen & Erweiterungen).');
    if (in_array($o, ['both', 'mail'], true) && !Delivery::available()) return __('Dafür die Funktion „Anfragen per E-Mail zustellen“ einschalten (Funktionen & Erweiterungen).');
    if ($o === 'mail' && $managed) return __('Diesen Eingang verwaltet eine Erweiterung (z. B. Buchungen), die gespeicherte Einträge braucht.');
    return null;
};
?>
    <p class="set-page__lead"><?= e(__('Wie sollen Einsendungen über das Formular geschützt werden? Die Wahl gilt für die ganze Tabelle.')) ?></p>
    <section class="set-group dt-crypto" data-delivery data-delivery-mode="<?= e($cur) ?>" aria-labelledby="t-cr-h">
      <h3 class="set-group__title" id="t-cr-h"><?= e(__('Schutz der Einsendungen')) ?></h3>
      <?php if (!FormCrypto::ready()): ?>
      <div class="adm-flash adm-flash--error dt-crypto__nokey" data-delivery-when="system both mail" role="alert">
        <b><?= e(__('Noch kein zentraler Schlüssel hinterlegt.')) ?></b>
        <?= e(__('Bis er erzeugt ist, nimmt das Formular keine Einsendungen an und zeigt „noch nicht eingerichtet“. Bei „Nur per E-Mail“ fehlt sonst die Sicherung, falls der Versand scheitert.')) ?>
        <?php if (can('system.manage')): ?><a class="adm-btn adm-btn--sm" href="<?= e(url('/admin/system#keys')) ?>"><?= e(__('Schlüssel erzeugen')) ?></a>
        <?php else: ?><?= e(__('Bitte die Administration bitten, den Schlüssel zu erzeugen.')) ?><?php endif; ?></div>
      <?php endif; ?>
      <?php if (!$canDeliver): ?>
      <div class="set-list"><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($opts[$cur][0]) ?></span>
        <span class="set-row__sub"><?= e(__('Ändern darf, wer Anfragen verwalten darf.')) ?></span></div></div></div>
      <input type="hidden" name="settings[protection]" value="<?= e($cur) ?>">
      <?php else: ?>
      <fieldset class="dt-protect"><legend class="adm-sr"><?= e(__('Schutz der Einsendungen')) ?></legend>
        <?php foreach ($opts as $o => [$label, $ico, $text, $who]): $no = $o === $cur ? null : $why($o); ?>
        <label class="dt-protect__opt<?= $no ? ' is-off' : '' ?>"><input type="radio" name="settings[protection]" value="<?= $o ?>"<?= $o === $cur ? ' checked' : '' ?><?= $no ? ' disabled' : '' ?>>
          <span class="dt-protect__ico" aria-hidden="true"><?= icon($ico) ?></span>
          <span class="dt-protect__body"><b><?= e($label) ?></b><span><?= e($text) ?></span><small><?= e($no ?? $who) ?></small></span></label>
        <?php endforeach; ?>
      </fieldset>
      <?php if ($switch && !$inbox): ?><p class="set-group__note"><?= e(__('Beim Wechsel zu einer verschlüsselten Art erscheinen nach dem Speichern die passenden Einstellungen (Formular & Eingang). Bilder, Verknüpfungen und Karten gibt es dort nicht.')) ?></p><?php endif; ?>
      <?php endif; ?>
      <?= $err('settings.kind') ?>
    </section>

    <section class="set-group" aria-labelledby="t-cr-key">
      <h3 class="set-group__title" id="t-cr-key"><?= e(__('Zentraler Schlüssel der Website')) ?></h3>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= FormCrypto::ready() ? e(__('Eingerichtet')) : e(__('Noch nicht eingerichtet')) ?></span>
          <span class="set-row__sub"><?= FormCrypto::ready() ? e(__('Fingerabdruck {fp}. Ein Schlüssel für alle verschlüsselten Tabellen dieser Website.', ['fp' => FormCrypto::fingerprint()]))
              : e(__('Ohne Schlüssel zeigen verschlüsselte Formulare „noch nicht eingerichtet“. Der geheime Teil wird beim Erzeugen nur einmal angezeigt – sicher aufbewahren.')) ?></span></div>
          <div class="set-row__ctl"><?php if (FormCrypto::ready()): ?><span class="adm-badge"><?= e(__('Aktiv')) ?></span><?php elseif (can('system.manage')): ?><a class="adm-btn adm-btn--sm" href="<?= e(url('/admin/system#keys')) ?>"><?= e(__('Schlüssel erzeugen')) ?></a><?php endif; ?></div></div>
      </div>
    </section>

    <?php if (Delivery::available() && $canDeliver): ?>
    <section class="set-group dt-smime" data-delivery data-delivery-mode="<?= e($cur) ?>" aria-labelledby="t-cr-smime">
      <h3 class="set-group__title" id="t-cr-smime"><?= e(__('E-Mail Ende-zu-Ende verschlüsseln (S/MIME)')) ?></h3>
      <p class="set-group__note" data-delivery-when="none system"><?= e(__('Nur nötig, wenn Einsendungen per E-Mail verschickt werden.')) ?></p>
      <div data-delivery-when="both mail">
        <div class="set-list">
          <?php if ($certs): ?>
          <div class="f"><ul class="dt-certs">
            <?php foreach ($certs as $c): if (!$c) continue; ?>
            <li><b><?= e($c['name']) ?></b><?= $c['emails'] ? ' &lt;' . e(implode(', ', $c['emails'])) . '&gt;' : '' ?> · <?= e(__('ausgestellt von {issuer}', ['issuer' => $c['issuer'] ?: '?'])) ?><?= $c['self_signed'] ? ' (' . e(__('selbst signiert')) . ')' : '' ?><br>
              <span class="<?= $c['expired'] || $c['days_left'] < 30 ? 'adm-badge adm-badge--adm-warn' : 'adm-muted' ?>"><?= e($c['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', $c['valid_to'])]) : __('gültig bis {date} (noch {n} Tage)', ['date' => date('d.m.Y', $c['valid_to']), 'n' => $c['days_left']])) ?></span>
              <br><small class="adm-muted adm-mono"><?= e(__('SHA-256')) ?> <?= e($c['fingerprint']) ?></small></li>
            <?php endforeach; ?>
          </ul></div>
          <div class="f f--bool"><label class="f-check"><input type="checkbox" name="settings[inbox][delivery][smime_remove]" value="1"> <span><?= e(__('Zertifikat entfernen (E-Mails dann ohne S/MIME)')) ?></span></label></div>
          <?php endif; ?>
          <div class="f"><label for="t-dv-smime"><?= e($certs ? __('Neues Zertifikat (ersetzt das bisherige)') : __('Zertifikat der Empfänger (PEM)')) ?></label>
            <textarea id="t-dv-smime" name="settings[inbox][delivery][smime]" rows="4" spellcheck="false" class="adm-mono" placeholder="-----BEGIN CERTIFICATE-----&#10;…&#10;-----END CERTIFICATE-----" data-pem-target></textarea>
            <p class="f-help"><label><?= e(__('oder Datei wählen (.pem, .crt, .cer):')) ?> <input type="file" accept=".pem,.crt,.cer,.der,application/x-x509-ca-cert,application/pkix-cert" data-pem-file></label></p>
            <p class="f-help"><?= e(__('Nur der öffentliche Teil (Zertifikat), nie den privaten Schlüssel. Mit Zertifikat wird jede E-Mail samt Anhängen mit S/MIME verschlüsselt (AES-256) – lesbar nur mit dem privaten Schlüssel im Mailprogramm der Empfänger. Mehrere Zertifikate (z. B. je Empfänger) nacheinander einfügen; jede E-Mail ist dann für alle lesbar. Ist das Zertifikat abgelaufen, wird nichts im Klartext versendet.')) ?></p>
            <?= $err('settings.delivery.smime') ?></div>
        </div>
        <p class="set-group__note"><a href="<?= e(url('/admin/hilfe#smime')) ?>"><?= e(__('Anleitung: S/MIME einrichten')) ?></a> · <?= e(__('PGP wird nicht unterstützt (keine MIT-kompatible Umsetzung ohne externes Programm).')) ?></p>
      </div>
    </section>
    <?php endif; ?>
