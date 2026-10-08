<?php
/**
 * Tabellen-Designer: Einstellungen einer Eingangs-Tabelle (verschlüsselte Anfragen, Core\Data\Inbox) – als Gruppen der
 * Einstellungsliste, aufgeteilt auf die Bereiche von „Felder & Einstellungen“:
 *   $part = 'form'      Formular (an/aus, Felder, Titel, Einleitung, Texte, Übersetzungen, Dateigröße) – Bereich „Formular & Eingang“
 *   $part = 'delivery'  Zustellung der Anfragen (System / System + E-Mail / nur E-Mail, Empfänger, S/MIME, Testmail)
 *   $part = 'privacy'   Datenschutz (Löschfrist)
 *   $part = 'notify'    Benachrichtigung ohne Inhalte – Bereich „Benachrichtigungen“
 * Feldnamen unverändert (settings[form][…], settings[inbox][…]) – gespeichert wird wie bisher über Tables::validate.
 * @var array $s  @var array $def  @var ?array $table  @var callable $err  @var string $part
 */
use Core\Data\DataForms;
use Core\Data\Delivery;
use Core\Data\Inbox;
use Core\FormCrypto;
use Core\Lang;

$part ??= 'form';
$fm = (array) ($s['form'] ?? []) + DataForms::DEFAULTS;
$ib = (array) ($s['inbox'] ?? []) + Inbox::DEFAULTS;
$fmAll = array_filter($def['fields'] ?? [], fn($f) => DataForms::eligible($f, false) || ($f['type'] ?? '') === 'file');
$dv = (array) ($ib['delivery'] ?? []) + Delivery::DEFAULTS;
$hasFiles = (bool) array_filter($def['fields'] ?? [], fn($f) => ($f['type'] ?? '') === 'file');
$selects = array_values(array_filter($def['fields'] ?? [], fn($f) => ($f['type'] ?? '') === 'select'));
$managed = $table && Delivery::managed($table);
$canDeliver = !$table || can('requests.manage', $table['handle']);
$themeForm = $ib['form'] !== '' ? (app()->theme->forms()[$ib['form']] ?? null) : null;
$langs = array_diff_key(Lang::all(), [Lang::default() => 1]);
?>
<?php if ($part === 'form'): ?>
    <section class="set-group dt-form" aria-labelledby="t-ib-h">
      <h3 class="set-group__title" id="t-ib-h"><?= e(__('Formular')) ?></h3>
      <input type="hidden" name="settings[inbox][form]" value="<?= e($ib['form']) ?>">
      <div class="set-list">
        <div class="f f--bool"><input type="hidden" name="settings[form][enabled]" value="0">
          <label class="f-check"><input type="checkbox" name="settings[form][enabled]" value="1"<?= $fm['enabled'] ? ' checked' : '' ?>> <span><?= e(__('Formular nimmt Anfragen an')) ?></span></label>
          <p class="f-help">
            <?php if ($themeForm): ?><?= e(__('Formular des Kits unter {url} (Anzeige und Link steuern die Einstellungen des Kits).', ['url' => '/anfrage/' . $ib['form']])) ?>
            <?php else: ?><?= e(__('Auf einer Seite mit dem Block „Formular (Datentabelle)“ einfügen.')) ?><?php endif; ?>
          </p></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Verschlüsselung')) ?></span>
          <span class="set-row__sub"><?= FormCrypto::ready() ? e(__('Verschlüsselung aktiv (Fingerabdruck {fp}).', ['fp' => FormCrypto::fingerprint()]))
              : '<b>' . e(__('Kein öffentlicher Schlüssel – das Formular zeigt „noch nicht eingerichtet“.')) . '</b>' ?></span></div>
          <div class="set-row__ctl"><?= FormCrypto::ready() ? '<span class="adm-badge">' . e(__('Aktiv')) . '</span>' : '<a href="' . e(url('/admin/system#keys')) . '">' . e(__('Schlüssel erzeugen')) . '</a>' ?></div></div>
        <fieldset class="f f--multi dt-form__fields"><legend><?= e(__('Felder im Formular')) ?></legend>
          <input type="hidden" name="settings[form][fields][]" value="">
          <?php if (!$fmAll): ?><p class="f-help"><?= e(__('Neue Felder erscheinen hier nach dem Speichern.')) ?></p><?php endif; ?>
          <div class="f-multi">
          <?php $selT = ['settings' => ['kind' => 'inbox', 'form' => $fm]];
            foreach ($fmAll as $f): $isFile = ($f['type'] ?? '') === 'file'; ?>
          <label class="f-check"><input type="checkbox" name="settings[form][fields][]" value="<?= e($f['name']) ?>"<?= DataForms::selected($selT, $f) ? ' checked' : '' ?><?= !empty($f['required']) ? ' disabled' : '' ?>><?php if (!empty($f['required'])): ?><input type="hidden" name="settings[form][fields][]" value="<?= e($f['name']) ?>"><?php endif; ?>
            <span><?= e($f['label']) ?><?= !empty($f['required']) ? ' <small class="adm-muted">(' . e(__('Pflichtfeld – immer dabei')) . ')</small>' : '' ?><?= $isFile ? ' <small class="adm-muted">(' . e(__('Datei – nur bei Zustellung per E-Mail')) . ')</small>' : '' ?></span></label>
          <?php endforeach; ?>
          </div>
          <p class="f-help"><?= e(__('Nichts angehakt = alle Felder. Neu angelegte Felder sind automatisch dabei. Die Checkbox „Datenschutzhinweise gelesen“ wird immer ergänzt.')) ?></p>
        </fieldset>
        <div class="f f--inline"><label for="t-ib-title"><?= e(__('Titel des Formulars')) ?></label>
          <input id="t-ib-title" name="settings[inbox][title]" value="<?= e($ib['title']) ?>" placeholder="<?= e($def['name'] ?? '') ?>" maxlength="120"></div>
        <div class="f"><label for="t-ib-intro"><?= e(__('Einleitung (optional)')) ?></label>
          <textarea id="t-ib-intro" name="settings[inbox][intro]" rows="2" maxlength="600"><?= e($ib['intro']) ?></textarea></div>
        <div class="f"><label for="t-form-success"><?= e(__('Text nach dem Absenden')) ?></label>
          <textarea id="t-form-success" name="settings[form][success]" rows="2" placeholder="<?= e($themeForm['success'] ?? 'Vielen Dank – Ihre Anfrage ist eingegangen.') ?>"><?= e($fm['success']) ?></textarea>
          <p class="f-help"><?= e(__('Leer = Text des Kits bzw. „Vielen Dank – Ihre Anfrage ist eingegangen.“')) ?></p></div>
        <div class="f f--inline"><label for="t-form-submit"><?= e(__('Beschriftung des Buttons')) ?></label>
          <input id="t-form-submit" name="settings[form][submit]" value="<?= e($fm['submit']) ?>" placeholder="<?= e(__('Absenden')) ?>" maxlength="60"></div>
        <?php if ($hasFiles): ?>
        <div class="f f--inline"><label for="t-form-mb"><?= e(__('Dateien: höchstens (MB je Datei)')) ?></label>
          <p class="f-help"><?= e(__('Dateien werden nur bei Zustellung per E-Mail angenommen – als Anhang bzw. versiegelt, nie in der Mediathek. Mehr ist nicht einzustellen: Dateifeld anlegen, unter „Felder im Formular“ anhaken, Zustellung „per E-Mail“ wählen. Welche Dateitypen erlaubt sind, legen Sie beim Dateifeld fest (Standard: PDF und Bilder).')) ?></p>
          <input type="number" id="t-form-mb" name="settings[form][upload_mb]" min="1" max="<?= DataForms::MAX_MB ?>" value="<?= (int) $fm['upload_mb'] ?>">
          <?php if (($dv['mode'] ?? 'system') === 'system'): ?><p class="f-error"><?= e(__('Die Zustellung steht auf „Im System“ – so nimmt das Formular keine Dateien an. Bitte unten „Im System und per E-Mail“ oder „Nur per E-Mail“ wählen.')) ?></p><?php endif; ?></div>
        <?php endif; ?>
      </div>
      <?= $err('settings.form') ?>
    </section>
    <?php foreach ($langs as $lc => $ll): $tr = (array) ($ib['i18n'][$lc] ?? []); ?>
    <details class="set-group dt-trans"<?= $tr ? ' open' : '' ?>><summary class="set-group__title"><?= e(__('Übersetzung')) ?>: <?= e($ll) ?></summary>
      <div class="set-list">
      <?php foreach (['title' => __('Titel des Formulars'), 'intro' => __('Einleitung (optional)'), 'success' => __('Text nach dem Absenden'), 'submit' => __('Beschriftung des Buttons')] as $k => $l): ?>
        <div class="f f--inline"><label for="t-ib-<?= e($lc . '-' . $k) ?>"><?= e($l) ?> (<?= e($ll) ?>)</label>
          <input id="t-ib-<?= e($lc . '-' . $k) ?>" name="settings[inbox][i18n][<?= e($lc) ?>][<?= $k ?>]" value="<?= e((string) ($tr[$k] ?? '')) ?>"></div>
      <?php endforeach; ?>
      </div>
    </details>
    <?php endforeach; ?>

<?php elseif ($part === 'notify'): ?>
    <section class="set-group" aria-labelledby="t-notify-h">
      <h3 class="set-group__title" id="t-notify-h"><?= e(__('Benachrichtigung an die Redaktion')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-form-notify"><?= e(__('Benachrichtigung an (E-Mail)')) ?></label>
          <p class="f-help"><?= e(Delivery::mode($table ?? ['settings' => $s]) === 'system' || !$table
              ? __('Die E-Mail enthält keine Inhalte – nur den Hinweis auf eine neue Anfrage und einen Link.')
              : __('Bei Zustellung per E-Mail geht der Inhalt an die Empfänger unter „Formular & Eingang → Zustellung“ – eine zusätzliche Benachrichtigung entfällt.')) ?></p>
          <input id="t-form-notify" name="settings[form][notify]" value="<?= e($fm['notify']) ?>" placeholder="<?= e(__('leer = Empfänger aus den Grundeinstellungen')) ?>" autocomplete="off"></div>
      </div>
    </section>

<?php elseif ($part === 'delivery'): if (!Delivery::available()) return;
  $certs = $dv['smime'] !== '' ? Delivery::certs($dv['smime']) : [];
  $transport = Delivery::transport();
  $problems = $table ? Delivery::problems($table) : [];
  $alerts = $table ? Delivery::alerts([$table['handle']]) : []; ?>
    <section class="set-group dt-delivery" id="zustellung" data-delivery aria-labelledby="t-dv-h">
      <h3 class="set-group__title" id="t-dv-h"><?= e(__('Zustellung der Anfragen')) ?></h3>
      <?php foreach ($alerts as $a): ?>
      <p class="adm-flash adm-flash--error"><?= e(__('Zustellung fehlgeschlagen')) ?> (<?= e(date('d.m.Y H:i', strtotime((string) $a['at']))) ?>, <?= e((string) $a['ref']) ?>): <?= e((string) $a['reason']) ?> –
        <?= e(!empty($a['stored']) ? __('Anfrage verschlüsselt gesichert (unter „Anfragen“).') : __('Anfrage NICHT gesichert.')) ?></p>
      <?php endforeach; ?>
      <div class="set-list">
      <?php if (!$canDeliver): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(Delivery::modeLabel($dv['mode'])) ?></span>
          <span class="set-row__sub"><?= e(__('Zustellung: {mode}. Ändern darf, wer Anfragen verwalten darf.', ['mode' => Delivery::modeLabel($dv['mode'])])) ?></span></div></div>
      <?php else: ?>
        <fieldset class="f dt-modes"><legend><?= e(__('Wohin gehen neue Anfragen?')) ?></legend>
          <?php foreach (Delivery::MODES as $m): if ($m === 'mail' && $managed) continue; ?>
          <label class="f-check"><input type="radio" name="settings[inbox][delivery][mode]" value="<?= $m ?>"<?= $dv['mode'] === $m ? ' checked' : '' ?>> <span><b><?= e(Delivery::modeLabel($m)) ?></b><br><small class="adm-muted"><?= e(match ($m) {
              'system' => __('Standard: verschlüsselt gespeichert, lesbar unter „Anfragen“ mit dem Schlüssel. Die Benachrichtigung enthält keine Inhalte.'),
              'both' => __('Verschlüsselt gespeichert und zusätzlich mit vollem Inhalt per E-Mail. Die Löschfrist unten gilt für die gespeicherte Fassung.'),
              'mail' => __('Nur die E-Mail mit vollem Inhalt – in der Datenbank bleibt nichts davon, nur ein Zustellprotokoll ohne Inhalte. Scheitert der Versand, wird die Anfrage verschlüsselt gesichert und die Administration gewarnt.'),
          }) ?></small></span></label>
          <?php endforeach; ?>
          <?php if ($managed): ?><p class="f-help"><?= e(__('„Nur per E-Mail“ ist hier nicht möglich: Eine Erweiterung (z. B. Buchungen) braucht die gespeicherten Einträge.')) ?></p><?php endif; ?>
        </fieldset>
      <?php endif; ?>
      </div>

      <?php if ($canDeliver): ?>
      <div class="dt-delivery__mail" data-delivery-when="both mail">
        <p class="dt-note" data-delivery-nosmime<?= $dv['smime'] !== '' ? ' hidden' : '' ?>><?= e(__('Inhalte werden per E-Mail übertragen. Für Gesundheitsdaten empfehlen wir S/MIME-Verschlüsselung mit dem Zertifikat der Empfänger – ohne sie ist die Nachricht nur auf dem Transportweg (TLS) geschützt und liegt unverschlüsselt im Postfach.')) ?></p>
        <p class="<?= $transport['level'] === 'ok' ? 'set-group__note' : ($transport['level'] === 'error' ? 'adm-flash adm-flash--error' : 'dt-note') ?>"><?= e(__('Versand:')) ?> <?= e($transport['text']) ?> <?php if ($transport['level'] !== 'ok' && can('system.manage')): ?><a href="<?= e(url('/admin/system')) ?>"><?= e(__('E-Mail-Versand einstellen')) ?></a><?php endif; ?></p>
        <?php foreach ($problems as $p): if ($p['text'] === $transport['text'] || str_starts_with($p['text'], __('Inhalte werden per E-Mail übertragen.'))) continue; ?>
        <p class="<?= $p['level'] === 'error' ? 'adm-flash adm-flash--error' : 'dt-note' ?>"><?= e($p['text']) ?></p>
        <?php endforeach; ?>

        <div class="set-list">
          <div class="f f--inline"><label for="t-dv-to"><?= e(__('Empfänger (E-Mail)')) ?></label>
            <p class="f-help"><?= e(__('Eine oder mehrere Adressen, durch Komma getrennt (höchstens 10). Ohne Empfänger werden Anfragen verschlüsselt im System gesichert.')) ?></p>
            <input id="t-dv-to" name="settings[inbox][delivery][to]" value="<?= e($dv['to']) ?>" placeholder="anfragen@example.de" autocomplete="off" spellcheck="false"><?= $err('settings.delivery') ?></div>

          <?php if ($selects): ?>
          <div class="f f--inline"><label for="t-dv-rf"><?= e(__('Weiterleitung nach Auswahlfeld (optional)')) ?></label>
            <p class="f-help"><?= e(__('Je Auswahl eine eigene Adresse, z. B. „Rezept“ → rezept@…; leer = Empfänger oben.')) ?></p>
            <select id="t-dv-rf" name="settings[inbox][delivery][route_field]" data-route-field>
              <option value=""><?= e(__('– keine: immer an die Empfänger oben –')) ?></option>
              <?php foreach ($selects as $f): ?><option value="<?= e($f['name']) ?>"<?= $dv['route_field'] === $f['name'] ? ' selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
            </select></div>
          <?php foreach ($selects as $f): ?>
          <div class="f dt-routes" data-route-for="<?= e($f['name']) ?>"<?= $dv['route_field'] === $f['name'] ? '' : ' hidden' ?>>
            <?php foreach ((array) ($f['options'] ?? []) as $k => $l): $rid = 't-dv-r-' . $f['name'] . '-' . preg_replace('~[^a-z0-9_-]~i', '_', (string) $k); ?>
            <div class="f f--inline"><label for="<?= e($rid) ?>"><?= e($f['label']) ?>: „<?= e((string) $l) ?>“ →</label>
              <input id="<?= e($rid) ?>" name="settings[inbox][delivery][routes][<?= e((string) $k) ?>]" value="<?= e($dv['route_field'] === $f['name'] ? (string) ($dv['routes'][(string) $k] ?? '') : '') ?>" autocomplete="off" spellcheck="false"<?= $dv['route_field'] === $f['name'] ? '' : ' disabled' ?>></div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <div class="f f--inline"><label for="t-dv-subj"><?= e(__('Betreff')) ?></label>
            <p class="f-help"><?= e(__('Platzhalter: {form} (Titel des Formulars), {table}, {ref} (Vorgangsnummer), {date}, {time} und {feldname} für ein Feld, z. B. „[Rezeptanfrage] {nachname} – {ref}“. Der Betreff wird auch mit S/MIME nicht verschlüsselt – deshalb stehen standardmäßig keine Inhalte darin.')) ?></p>
            <input id="t-dv-subj" name="settings[inbox][delivery][subject]" value="<?= e($dv['subject']) ?>" placeholder="<?= e(Delivery::SUBJECT) ?>" maxlength="200"></div>
          <div class="f f--bool"><input type="hidden" name="settings[inbox][delivery][reply_to]" value="0">
            <label class="f-check"><input type="checkbox" name="settings[inbox][delivery][reply_to]" value="1"<?= $dv['reply_to'] ? ' checked' : '' ?>> <span><?= e(__('„Antworten“ geht an die E-Mail-Adresse aus der Anfrage (Reply-To), falls das Formular ein E-Mail-Feld hat')) ?></span></label></div>
          <div class="f f--inline"><label for="t-dv-machine"><?= e(__('Maschinenlesbarer Anhang')) ?></label>
            <p class="f-help"><?= e(__('Für den Import in ein eigenes System (z. B. Fachsoftware, CRM oder Archiv).')) ?></p>
            <select id="t-dv-machine" name="settings[inbox][delivery][machine]">
              <option value=""<?= $dv['machine'] === '' ? ' selected' : '' ?>><?= e(__('keiner')) ?></option>
              <option value="json"<?= $dv['machine'] === 'json' ? ' selected' : '' ?>>JSON (anfrage-{ref}.json)</option>
              <option value="xml"<?= $dv['machine'] === 'xml' ? ' selected' : '' ?>>XML (anfrage-{ref}.xml)</option>
            </select></div>
          <div class="f f--inline"><label for="t-dv-mb"><?= e(__('Anhänge höchstens (MB je E-Mail)')) ?></label>
            <p class="f-help"><?= e(__('Größere Dateien: bei „System und E-Mail“ ein Hinweis statt des Anhangs (die Datei liegt verschlüsselt im System), bei „nur per E-Mail“ lehnt das Formular sie ab.')) ?></p>
            <input type="number" id="t-dv-mb" name="settings[inbox][delivery][attach_mb]" min="1" max="25" value="<?= (int) $dv['attach_mb'] ?>"></div>
        </div>

        <details class="set-group dt-smime"<?= $certs ? ' open' : '' ?>>
          <summary class="set-group__title"><?= e(__('Ende-zu-Ende-Verschlüsselung (S/MIME)')) ?> <span class="adm-muted"><?= e(__('empfohlen für Gesundheitsdaten')) ?></span></summary>
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
        </details>

        <?php if ($table): ?>
        <div class="set-actions"><button type="submit" class="adm-btn" formaction="<?= e(url('/admin/data/' . $table['handle'] . '/delivery-test')) ?>" formnovalidate><?= e(__('Testmail senden')) ?></button>
          <span class="f-help"><?= e(__('Schickt eine erfundene Anfrage mit den gespeicherten Einstellungen – vorher speichern.')) ?></span></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>

<?php elseif ($part === 'privacy'): ?>
    <section class="set-group" aria-labelledby="t-ib-priv">
      <h3 class="set-group__title" id="t-ib-priv"><?= e(__('Datenschutz')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="t-ib-ret"><?= e(__('Erledigte Anfragen automatisch löschen nach (Tagen)')) ?></label>
          <p class="f-help"><?= e(__('0 = nie. Gezählt ab dem Tag, an dem die Anfrage als erledigt markiert wurde.')) ?> <?= e(__('Bei „Nur per E-Mail“ wird nichts gespeichert – die Frist gilt dann nur für Anfragen, die nach einem Zustellfehler verschlüsselt gesichert wurden.')) ?></p>
          <input type="number" id="t-ib-ret" name="settings[inbox][retention_days]" min="0" max="3650" value="<?= (int) $ib['retention_days'] ?>"></div>
      </div>
      <p class="set-group__note"><?= e(__('Alle Angaben werden Ende-zu-Ende verschlüsselt gespeichert; lesbar nur unter „Anfragen“ mit dem geheimen Schlüssel. Keine Detailseiten, keine Ausgabe auf der Website, nicht in Suche, Sitemap, API-Inhalten oder Kalender-Abos. Jedes Entschlüsseln wird protokolliert.')) ?></p>
    </section>
<?php endif; ?>
