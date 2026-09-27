<?php
/**
 * Tabellen-Baukasten: Einstellungen einer Eingangs-Tabelle (verschlüsselte Anfragen, Core\Data\Inbox).
 * @var array $s  @var array $def  @var ?array $table  @var callable $err
 */
use Core\Data\DataForms;
use Core\Data\Delivery;
use Core\Data\Inbox;
use Core\FormCrypto;
use Core\Lang;

$fm = (array) ($s['form'] ?? []) + DataForms::DEFAULTS;
$ib = (array) ($s['inbox'] ?? []) + Inbox::DEFAULTS;
$fmAll = array_filter($def['fields'] ?? [], fn($f) => DataForms::eligible($f, false) || ($f['type'] ?? '') === 'file');
$dv = (array) ($ib['delivery'] ?? []) + Delivery::DEFAULTS;
$hasFiles = (bool) array_filter($def['fields'] ?? [], fn($f) => ($f['type'] ?? '') === 'file');
$selects = array_values(array_filter($def['fields'] ?? [], fn($f) => ($f['type'] ?? '') === 'select'));
$managed = $table && Delivery::managed($table);
$canDeliver = !$table || can('requests.manage', $table['handle']);
$certs = $dv['smime'] !== '' ? Delivery::certs($dv['smime']) : [];
$transport = Delivery::transport();
$problems = $table ? Delivery::problems($table) : [];
$alerts = $table ? Delivery::alerts([$table['handle']]) : [];
$themeForm = $ib['form'] !== '' ? (app()->theme->forms()[$ib['form']] ?? null) : null;
$langs = array_diff_key(Lang::all(), [Lang::default() => 1]);
?>
    <section class="adm-card dt-form">
      <h2><?= e(__('Formular')) ?></h2>
      <input type="hidden" name="settings[inbox][form]" value="<?= e($ib['form']) ?>">
      <input type="hidden" name="settings[form][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[form][enabled]" value="1"<?= $fm['enabled'] ? ' checked' : '' ?>> <span><?= e(__('Formular nimmt Anfragen an')) ?></span></label>
      <p class="f-help">
        <?php if ($themeForm): ?><?= e(__('Formular des Kits unter {url} (Anzeige und Link steuern die Einstellungen des Kits).', ['url' => '/anfrage/' . $ib['form']])) ?>
        <?php else: ?><?= e(__('Auf einer Seite mit dem Block „Formular (Datentabelle)“ einfügen.')) ?><?php endif; ?>
      </p>
      <p class="f-help"><?= FormCrypto::ready()
          ? e(__('Verschlüsselung aktiv (Fingerabdruck {fp}).', ['fp' => FormCrypto::fingerprint()]))
          : '<b>' . e(__('Kein öffentlicher Schlüssel – das Formular zeigt „noch nicht eingerichtet“.')) . '</b> <a href="' . e(url('/admin/system#keys')) . '">' . e(__('Schlüssel erzeugen')) . '</a>' ?></p>
      <fieldset class="f dt-form__fields"><legend><?= e(__('Felder im Formular')) ?></legend>
        <input type="hidden" name="settings[form][fields][]" value="">
        <?php if (!$fmAll): ?><p class="f-help"><?= e(__('Neue Felder erscheinen hier nach dem Speichern.')) ?></p><?php endif; ?>
        <?php foreach ($fmAll as $f): ?>
        <label class="f-check"><input type="checkbox" name="settings[form][fields][]" value="<?= e($f['name']) ?>"<?= !$fm['fields'] || in_array($f['name'], $fm['fields'], true) || !empty($f['required']) ? ' checked' : '' ?><?= !empty($f['required']) ? ' disabled' : '' ?>><?php if (!empty($f['required'])): ?><input type="hidden" name="settings[form][fields][]" value="<?= e($f['name']) ?>"><?php endif; ?>
          <span><?= e($f['label']) ?><?= !empty($f['required']) ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?></span></label>
        <?php endforeach; ?>
        <p class="f-help"><?= e(__('Nichts angehakt = alle Felder. Die Checkbox „Datenschutzhinweise gelesen“ wird immer ergänzt.')) ?></p>
      </fieldset>
      <div class="f"><label for="t-ib-title"><?= e(__('Titel des Formulars')) ?></label>
        <input id="t-ib-title" name="settings[inbox][title]" value="<?= e($ib['title']) ?>" placeholder="<?= e($def['name'] ?? '') ?>" maxlength="120"></div>
      <div class="f"><label for="t-ib-intro"><?= e(__('Einleitung (optional)')) ?></label>
        <textarea id="t-ib-intro" name="settings[inbox][intro]" rows="2" maxlength="600"><?= e($ib['intro']) ?></textarea></div>
      <div class="f"><label for="t-form-success"><?= e(__('Text nach dem Absenden')) ?></label>
        <textarea id="t-form-success" name="settings[form][success]" rows="2" placeholder="<?= e($themeForm['success'] ?? 'Vielen Dank – Ihre Anfrage ist eingegangen.') ?>"><?= e($fm['success']) ?></textarea>
        <p class="f-help"><?= e(__('Leer = Text des Kits bzw. „Vielen Dank – Ihre Anfrage ist eingegangen.“')) ?></p></div>
      <div class="f"><label for="t-form-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
        <input id="t-form-submit" name="settings[form][submit]" value="<?= e($fm['submit']) ?>" placeholder="<?= e(__('Absenden')) ?>" maxlength="60"></div>
      <?php foreach ($langs as $lc => $ll): $tr = (array) ($ib['i18n'][$lc] ?? []); ?>
      <details class="dt-trans"<?= $tr ? ' open' : '' ?>><summary><?= e(__('Übersetzung')) ?>: <?= e($ll) ?></summary>
        <?php foreach (['title' => __('Titel des Formulars'), 'intro' => __('Einleitung (optional)'), 'success' => __('Text nach dem Absenden'), 'submit' => __('Beschriftung des Buttons')] as $k => $l): ?>
        <div class="f"><label for="t-ib-<?= e($lc . '-' . $k) ?>"><?= e($l) ?> (<?= e($ll) ?>)</label>
          <input id="t-ib-<?= e($lc . '-' . $k) ?>" name="settings[inbox][i18n][<?= e($lc) ?>][<?= $k ?>]" value="<?= e((string) ($tr[$k] ?? '')) ?>"></div>
        <?php endforeach; ?>
      </details>
      <?php endforeach; ?>
      <div class="f"><label for="t-form-notify"><?= e(__('Benachrichtigung an (E-Mail)')) ?></label>
        <input id="t-form-notify" name="settings[form][notify]" value="<?= e($fm['notify']) ?>" placeholder="<?= e(__('leer = Empfänger aus den Grundeinstellungen')) ?>" autocomplete="off">
        <p class="f-help"><?= e(__('Die E-Mail enthält keine Inhalte – nur den Hinweis auf eine neue Anfrage und einen Link.')) ?></p></div>
      <?php if ($hasFiles): ?>
      <div class="f"><label for="t-form-mb"><?= e(__('Dateien: höchstens (MB je Datei)')) ?></label>
        <input type="number" id="t-form-mb" name="settings[form][upload_mb]" min="1" max="10" value="<?= (int) $fm['upload_mb'] ?>">
        <p class="f-help"><?= e(__('Dateifelder nehmen PDF, JPG, PNG und WebP an – nur bei Zustellung per E-Mail, nie in die Mediathek.')) ?></p></div>
      <?php endif; ?>
      <?= $err('settings.form') ?>
    </section>

    <?php if (Delivery::available()): ?>
    <section class="adm-card dt-delivery" id="zustellung" data-delivery>
      <h2><?= e(__('Zustellung der Anfragen')) ?></h2>
      <?php foreach ($alerts as $a): ?>
      <p class="adm-flash adm-flash--error"><?= e(__('Zustellung fehlgeschlagen')) ?> (<?= e(date('d.m.Y H:i', strtotime((string) $a['at']))) ?>, <?= e((string) $a['ref']) ?>): <?= e((string) $a['reason']) ?> –
        <?= e(!empty($a['stored']) ? __('Anfrage verschlüsselt gesichert (unter „Anfragen“).') : __('Anfrage NICHT gesichert.')) ?></p>
      <?php endforeach; ?>
      <?php if (!$canDeliver): ?>
      <p class="f-help"><?= e(__('Zustellung: {mode}. Ändern darf, wer Anfragen verwalten darf.', ['mode' => Delivery::modeLabel($dv['mode'])])) ?></p>
      <?php else: ?>
      <fieldset class="dt-modes"><legend><?= e(__('Wohin gehen neue Anfragen?')) ?></legend>
        <?php foreach (Delivery::MODES as $m): if ($m === 'mail' && $managed) continue; ?>
        <label class="f-check"><input type="radio" name="settings[inbox][delivery][mode]" value="<?= $m ?>"<?= $dv['mode'] === $m ? ' checked' : '' ?>> <span><b><?= e(Delivery::modeLabel($m)) ?></b><br><small class="adm-muted"><?= e(match ($m) {
            'system' => __('Standard: verschlüsselt gespeichert, lesbar unter „Anfragen“ mit dem Schlüssel. Die Benachrichtigung enthält keine Inhalte.'),
            'both' => __('Verschlüsselt gespeichert und zusätzlich mit vollem Inhalt per E-Mail. Die Löschfrist unten gilt für die gespeicherte Fassung.'),
            'mail' => __('Nur die E-Mail mit vollem Inhalt – in der Datenbank bleibt nichts davon, nur ein Zustellprotokoll ohne Inhalte. Scheitert der Versand, wird die Anfrage verschlüsselt gesichert und die Administration gewarnt.'),
        }) ?></small></span></label>
        <?php endforeach; ?>
        <?php if ($managed): ?><p class="f-help"><?= e(__('„Nur per E-Mail“ ist hier nicht möglich: Eine Erweiterung (z. B. Buchungen) braucht die gespeicherten Einträge.')) ?></p><?php endif; ?>
      </fieldset>

      <div data-delivery-when="both mail">
        <p class="dt-note" data-delivery-nosmime<?= $dv['smime'] !== '' ? ' hidden' : '' ?>><?= e(__('Inhalte werden per E-Mail übertragen. Für Gesundheitsdaten empfehlen wir S/MIME-Verschlüsselung mit dem Zertifikat der Empfänger – ohne sie ist die Nachricht nur auf dem Transportweg (TLS) geschützt und liegt unverschlüsselt im Postfach.')) ?></p>
        <p class="<?= $transport['level'] === 'ok' ? 'f-help' : ($transport['level'] === 'error' ? 'adm-flash adm-flash--error' : 'dt-note') ?>"><?= e(__('Versand:')) ?> <?= e($transport['text']) ?> <?php if ($transport['level'] !== 'ok' && can('system.manage')): ?><a href="<?= e(url('/admin/system')) ?>"><?= e(__('E-Mail-Versand einstellen')) ?></a><?php endif; ?></p>
        <?php foreach ($problems as $p): if ($p['text'] === $transport['text'] || str_starts_with($p['text'], __('Inhalte werden per E-Mail übertragen.'))) continue; ?>
        <p class="<?= $p['level'] === 'error' ? 'adm-flash adm-flash--error' : 'dt-note' ?>"><?= e($p['text']) ?></p>
        <?php endforeach; ?>

        <div class="f"><label for="t-dv-to"><?= e(__('Empfänger (E-Mail)')) ?></label>
          <input id="t-dv-to" name="settings[inbox][delivery][to]" value="<?= e($dv['to']) ?>" placeholder="praxis@example.de" autocomplete="off" spellcheck="false">
          <p class="f-help"><?= e(__('Eine oder mehrere Adressen, durch Komma getrennt (höchstens 10). Ohne Empfänger werden Anfragen verschlüsselt im System gesichert.')) ?></p><?= $err('settings.delivery') ?></div>

        <?php if ($selects): ?>
        <div class="f"><label for="t-dv-rf"><?= e(__('Weiterleitung nach Auswahlfeld (optional)')) ?></label>
          <select id="t-dv-rf" name="settings[inbox][delivery][route_field]" data-route-field>
            <option value=""><?= e(__('– keine: immer an die Empfänger oben –')) ?></option>
            <?php foreach ($selects as $f): ?><option value="<?= e($f['name']) ?>"<?= $dv['route_field'] === $f['name'] ? ' selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
          </select>
          <p class="f-help"><?= e(__('Je Auswahl eine eigene Adresse, z. B. „Rezept“ → rezept@…; leer = Empfänger oben.')) ?></p></div>
        <?php foreach ($selects as $f): ?>
        <div class="dt-routes" data-route-for="<?= e($f['name']) ?>"<?= $dv['route_field'] === $f['name'] ? '' : ' hidden' ?>>
          <?php foreach ((array) ($f['options'] ?? []) as $k => $l): $rid = 't-dv-r-' . $f['name'] . '-' . preg_replace('~[^a-z0-9_-]~i', '_', (string) $k); ?>
          <div class="f"><label for="<?= e($rid) ?>"><?= e($f['label']) ?>: „<?= e((string) $l) ?>“ →</label>
            <input id="<?= e($rid) ?>" name="settings[inbox][delivery][routes][<?= e((string) $k) ?>]" value="<?= e($dv['route_field'] === $f['name'] ? (string) ($dv['routes'][(string) $k] ?? '') : '') ?>" autocomplete="off" spellcheck="false"<?= $dv['route_field'] === $f['name'] ? '' : ' disabled' ?>></div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="f"><label for="t-dv-subj"><?= e(__('Betreff')) ?></label>
          <input id="t-dv-subj" name="settings[inbox][delivery][subject]" value="<?= e($dv['subject']) ?>" placeholder="<?= e(Delivery::SUBJECT) ?>" maxlength="200">
          <p class="f-help"><?= e(__('Platzhalter: {form} (Titel des Formulars), {table}, {ref} (Vorgangsnummer), {date}, {time} und {feldname} für ein Feld, z. B. „[Rezeptanfrage] {nachname} – {ref}“. Der Betreff wird auch mit S/MIME nicht verschlüsselt – deshalb stehen standardmäßig keine Inhalte darin.')) ?></p></div>
        <input type="hidden" name="settings[inbox][delivery][reply_to]" value="0">
        <label class="f-check"><input type="checkbox" name="settings[inbox][delivery][reply_to]" value="1"<?= $dv['reply_to'] ? ' checked' : '' ?>> <span><?= e(__('„Antworten“ geht an die E-Mail-Adresse aus der Anfrage (Reply-To), falls das Formular ein E-Mail-Feld hat')) ?></span></label>
        <div class="f"><label for="t-dv-machine"><?= e(__('Maschinenlesbarer Anhang')) ?></label>
          <select id="t-dv-machine" name="settings[inbox][delivery][machine]">
            <option value=""<?= $dv['machine'] === '' ? ' selected' : '' ?>><?= e(__('keiner')) ?></option>
            <option value="json"<?= $dv['machine'] === 'json' ? ' selected' : '' ?>>JSON (anfrage-{ref}.json)</option>
            <option value="xml"<?= $dv['machine'] === 'xml' ? ' selected' : '' ?>>XML (anfrage-{ref}.xml)</option>
          </select>
          <p class="f-help"><?= e(__('Für den Import in ein eigenes System (z. B. Praxissoftware oder Archiv).')) ?></p></div>
        <div class="f"><label for="t-dv-mb"><?= e(__('Anhänge höchstens (MB je E-Mail)')) ?></label>
          <input type="number" id="t-dv-mb" name="settings[inbox][delivery][attach_mb]" min="1" max="25" value="<?= (int) $dv['attach_mb'] ?>">
          <p class="f-help"><?= e(__('Größere Dateien: bei „System und E-Mail“ ein Hinweis statt des Anhangs (die Datei liegt verschlüsselt im System), bei „nur per E-Mail“ lehnt das Formular sie ab.')) ?></p></div>

        <fieldset class="f dt-smime"><legend><?= e(__('Ende-zu-Ende-Verschlüsselung (S/MIME)')) ?> <small class="adm-muted"><?= e(__('empfohlen für Gesundheitsdaten')) ?></small></legend>
          <?php if ($certs): ?>
          <ul class="dt-certs">
            <?php foreach ($certs as $c): if (!$c) continue; ?>
            <li><b><?= e($c['name']) ?></b><?= $c['emails'] ? ' &lt;' . e(implode(', ', $c['emails'])) . '&gt;' : '' ?> · <?= e(__('ausgestellt von {issuer}', ['issuer' => $c['issuer'] ?: '?'])) ?><?= $c['self_signed'] ? ' (' . e(__('selbst signiert')) . ')' : '' ?><br>
              <span class="<?= $c['expired'] || $c['days_left'] < 30 ? 'adm-badge adm-badge--adm-warn' : 'adm-muted' ?>"><?= e($c['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', $c['valid_to'])]) : __('gültig bis {date} (noch {n} Tage)', ['date' => date('d.m.Y', $c['valid_to']), 'n' => $c['days_left']])) ?></span>
              <br><small class="adm-muted adm-mono"><?= e(__('SHA-256')) ?> <?= e($c['fingerprint']) ?></small></li>
            <?php endforeach; ?>
          </ul>
          <label class="f-check"><input type="checkbox" name="settings[inbox][delivery][smime_remove]" value="1"> <span><?= e(__('Zertifikat entfernen (E-Mails dann ohne S/MIME)')) ?></span></label>
          <?php endif; ?>
          <div class="f"><label for="t-dv-smime"><?= e($certs ? __('Neues Zertifikat (ersetzt das bisherige)') : __('Zertifikat der Empfänger (PEM)')) ?></label>
            <textarea id="t-dv-smime" name="settings[inbox][delivery][smime]" rows="4" spellcheck="false" class="adm-mono" placeholder="-----BEGIN CERTIFICATE-----&#10;…&#10;-----END CERTIFICATE-----" data-pem-target></textarea>
            <p class="f-help"><label><?= e(__('oder Datei wählen (.pem, .crt, .cer):')) ?> <input type="file" accept=".pem,.crt,.cer,.der,application/x-x509-ca-cert,application/pkix-cert" data-pem-file></label></p>
            <p class="f-help"><?= e(__('Nur der öffentliche Teil (Zertifikat), nie den privaten Schlüssel. Mit Zertifikat wird jede E-Mail samt Anhängen mit S/MIME verschlüsselt (AES-256) – lesbar nur mit dem privaten Schlüssel im Mailprogramm der Praxis. Mehrere Zertifikate (z. B. je Empfänger) nacheinander einfügen; jede E-Mail ist dann für alle lesbar. Ist das Zertifikat abgelaufen, wird nichts im Klartext versendet.')) ?></p>
            <?= $err('settings.delivery.smime') ?></div>
          <p class="f-help"><a href="<?= e(url('/admin/hilfe#smime')) ?>"><?= e(__('Anleitung: S/MIME einrichten')) ?></a> · <?= e(__('PGP wird nicht unterstützt (keine MIT-kompatible Umsetzung ohne externes Programm).')) ?></p>
        </fieldset>

        <?php if ($table): ?>
        <p class="adm-row"><button type="submit" class="adm-btn adm-btn--ghost" formaction="<?= e(url('/admin/data/' . $table['handle'] . '/delivery-test')) ?>" formnovalidate><?= e(__('Testmail senden')) ?></button>
          <span class="f-help"><?= e(__('Schickt eine erfundene Anfrage mit den gespeicherten Einstellungen – vorher speichern.')) ?></span></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="adm-card">
      <h2><?= e(__('Datenschutz')) ?></h2>
      <div class="f"><label for="t-ib-ret"><?= e(__('Erledigte Anfragen automatisch löschen nach (Tagen)')) ?></label>
        <input type="number" id="t-ib-ret" name="settings[inbox][retention_days]" min="0" max="3650" value="<?= (int) $ib['retention_days'] ?>">
        <p class="f-help"><?= e(__('0 = nie. Gezählt ab dem Tag, an dem die Anfrage als erledigt markiert wurde.')) ?></p></div>
      <p class="f-help"><?= e(__('Bei „Nur per E-Mail“ wird nichts gespeichert – die Frist gilt dann nur für Anfragen, die nach einem Zustellfehler verschlüsselt gesichert wurden.')) ?></p>
      <p class="dt-note"><?= e(__('Alle Angaben werden Ende-zu-Ende verschlüsselt gespeichert; lesbar nur unter „Anfragen“ mit dem geheimen Schlüssel. Keine Detailseiten, keine Ausgabe auf der Website, nicht in Suche, Sitemap, API-Inhalten oder Kalender-Abos. Jedes Entschlüsseln wird protokolliert.')) ?></p>
    </section>
