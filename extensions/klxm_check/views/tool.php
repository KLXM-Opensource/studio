<?php
/**
 * KLXM Check – Markup des Werkzeugs (Block „klxm_check“ und eigenständige Seite). Ohne Inline-Skript und ohne Inline-Stil
 * (CSP der Website): Verhalten aus assets/js/check.mjs, Texte für das Skript als JSON (<script type="application/json">).
 * @var array $o  @var string $uid  @var bool $first  @var string $api  @var string $css  @var string $js  @var array $i18n
 */
use Core\Lang;

$L = max(1, min(4, (int) $o['level']));
$title = trim((string) $o['title']);
$hp = $title !== '' ? $L + 1 : $L;          // Überschriften der Reiter
$hs = min(6, $hp + 1);                      // Abschnitte der Ergebnisse
$tabsAll = [
    'checker' => lt('Domain-Analyse'),
    'ssl-checker' => lt('SSL/TLS-Checker'),
    'spf-generator' => lt('SPF-Generator'),
    'dmarc-generator' => lt('DMARC-Generator'),
];
$tabs = match ((string) $o['tabs']) {
    'checker' => ['checker'],
    'ssl' => ['ssl-checker'],
    'generators' => ['spf-generator', 'dmarc-generator'],
    default => array_keys($tabsAll),
};
$start = in_array($o['tab'], $tabs, true) ? $o['tab'] : $tabs[0];
$lang = Lang::current() !== Lang::default() ? Lang::current() : '';
$h = fn(int $n) => 'h' . min(6, $n);
$id = fn(string $s) => $uid . '-' . $s;
$providers = [
    '_spf.google.com' => 'Google Workspace', 'spf.protection.outlook.com' => 'Microsoft 365', '_spf-eu.ionos.com' => 'IONOS',
    'spf.kasserver.com' => 'ALL-INKL', 'spf.your-server.de' => 'Hetzner', 'zoho.eu' => 'Zoho (EU)', 'spf.brevo.com' => 'Brevo',
    'spf.mandrillapp.com' => 'Mailchimp Transactional', 'sendgrid.net' => 'SendGrid', 'mailgun.org' => 'Mailgun', 'amazonses.com' => 'Amazon SES', 'spf.mailjet.com' => 'Mailjet',
];
$ico = fn(string $name) => '<svg class="kc-i" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . match ($name) {
    'search' => '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    'link' => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/>',
    'wand' => '<path d="m4 20 11-11M14 4v3M18.5 5.5l-2 2M20 10h-3M9 4.5l1 1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    default => '',
} . '</svg>';
?>
<?php if ($first): ?><link rel="stylesheet" href="<?= e($css) ?>"><?php endif; ?>
<div class="kc" id="<?= e($uid) ?>" data-kc data-api="<?= e($api) ?>" data-lang="<?= e($lang) ?>" data-hl="<?= $hs ?>" data-start="<?= e($start) ?>"<?= $first ? ' data-url' : '' ?>>
  <?php if ($title !== '' || trim((string) $o['intro']) !== ''): ?>
  <header class="kc-head">
    <?php if ($title !== ''): ?><<?= $h($L) ?> class="kc-title"><?= e($title) ?></<?= $h($L) ?>><?php endif; ?>
    <?php if (trim((string) $o['intro']) !== ''): ?><p class="kc-intro"><?= e((string) $o['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <noscript><p class="kc-note"><?= e(lt('Dieses Werkzeug braucht JavaScript.')) ?></p></noscript>

  <?php if (count($tabs) > 1): ?>
  <div class="kc-tabs" role="tablist" aria-label="<?= e(lt('Werkzeuge')) ?>">
    <?php foreach ($tabs as $t): ?>
    <button type="button" role="tab" class="kc-tab" id="<?= e($id('tab-' . $t)) ?>" aria-controls="<?= e($id('p-' . $t)) ?>" aria-selected="<?= $t === $start ? 'true' : 'false' ?>"<?= $t === $start ? '' : ' tabindex="-1"' ?> data-tab="<?= e($t) ?>"><?= e($tabsAll[$t]) ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (in_array('checker', $tabs, true)): ?>
  <section class="kc-panel" id="<?= e($id('p-checker')) ?>" data-panel="checker"<?= count($tabs) > 1 ? ' role="tabpanel" aria-labelledby="' . e($id('tab-checker')) . '" tabindex="0"' : '' ?><?= $start === 'checker' ? '' : ' hidden' ?>>
    <<?= $h($hp) ?> class="kc-ptitle"><?= e(lt('Domain-Analyse')) ?></<?= $h($hp) ?>>
    <form class="kc-form" data-form="analyse" method="get" novalidate>
      <label class="kc-label" for="<?= e($id('domain')) ?>"><?= e(lt('Domain')) ?></label>
      <div class="kc-row">
        <input class="kc-input" id="<?= e($id('domain')) ?>" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="300" required
               placeholder="<?= e(lt('beispiel.de')) ?>" aria-describedby="<?= e($id('domain-hint')) ?> <?= e($id('domain-err')) ?>" data-shortcut>
        <button type="submit" class="btn btn--primary kc-btn"><?= $ico('search') ?><span><?= e(lt('Prüfen')) ?></span></button>
      </div>
      <p class="kc-err" id="<?= e($id('domain-err')) ?>" hidden></p>
      <p class="kc-hint" id="<?= e($id('domain-hint')) ?>"><?= e(lt('Prüft SPF, DMARC, DKIM, Mailserver, Hosting und Website. Die Adresse lässt sich teilen (?domain=…); Tastenkürzel:')) ?> <kbd>⌘</kbd>/<kbd><?= e(lt('Strg')) ?></kbd> + <kbd>K</kbd></p>
      <details class="kc-opts">
        <summary><?= e(lt('Optionen')) ?></summary>
        <div class="kc-opts__body">
          <div class="kc-field">
            <label class="kc-label" for="<?= e($id('selector')) ?>"><?= e(lt('DKIM-Selektor (optional)')) ?></label>
            <input class="kc-input" id="<?= e($id('selector')) ?>" name="selector" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="100" aria-describedby="<?= e($id('selector-hint')) ?>">
            <p class="kc-hint" id="<?= e($id('selector-hint')) ?>"><?= e(lt('Steht im Kopf einer versendeten Mail: „DKIM-Signature: … s=selektor“. Gängige Selektoren werden immer geprüft.')) ?></p>
          </div>
          <label class="kc-check"><input type="checkbox" name="smtp" value="1" checked> <span><?= e(lt('Verschlüsselung des Mailservers prüfen (STARTTLS auf Port 25)')) ?></span></label>
        </div>
      </details>
    </form>
    <p class="kc-status" role="status" aria-live="polite" data-status></p>
    <div class="kc-results" data-results hidden>
      <section class="kc-summary" aria-labelledby="<?= e($id('sum')) ?>" data-summary>
        <<?= $h($hs) ?> class="kc-stitle" id="<?= e($id('sum')) ?>"><?= e(lt('Zusammenfassung')) ?> <span class="kc-sdomain" data-sdomain></span></<?= $h($hs) ?>>
        <div class="kc-score" data-score></div>
        <nav class="kc-jump" aria-label="<?= e(lt('Direkt zu den Abschnitten')) ?>"><ul data-jump></ul></nav>
        <div class="kc-actions">
          <button type="button" class="btn btn--secondary kc-btn kc-sm" data-copylink><?= $ico('link') ?><span><?= e(lt('Link kopieren')) ?></span></button>
          <span class="kc-meta" data-meta></span>
        </div>
      </section>
      <div class="kc-sections" data-sections></div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (in_array('ssl-checker', $tabs, true)): ?>
  <section class="kc-panel" id="<?= e($id('p-ssl-checker')) ?>" data-panel="ssl-checker"<?= count($tabs) > 1 ? ' role="tabpanel" aria-labelledby="' . e($id('tab-ssl-checker')) . '" tabindex="0"' : '' ?><?= $start === 'ssl-checker' ? '' : ' hidden' ?>>
    <<?= $h($hp) ?> class="kc-ptitle"><?= e(lt('SSL/TLS-Checker')) ?></<?= $h($hp) ?>>
    <p class="kc-lead"><?= e(lt('Zertifikat, Kette, Protokoll und Verschlüsselung eines Servers prüfen – für Websites und Mailserver.')) ?></p>
    <form class="kc-form" data-form="tls" method="get" novalidate>
      <div class="kc-grid">
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('host')) ?>"><?= e(lt('Hostname')) ?></label>
          <input class="kc-input" id="<?= e($id('host')) ?>" name="host" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="300" required placeholder="<?= e(lt('www.beispiel.de')) ?>" aria-describedby="<?= e($id('host-err')) ?>">
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('service')) ?>"><?= e(lt('Dienst')) ?></label>
          <select class="kc-input" id="<?= e($id('service')) ?>" name="service">
            <option value="https">HTTPS (443)</option>
            <option value="smtps">SMTPS (465)</option>
            <option value="submission">SMTP STARTTLS (587)</option>
            <option value="smtp">SMTP STARTTLS (25)</option>
            <option value="imaps">IMAPS (993)</option>
            <option value="pop3s">POP3S (995)</option>
          </select>
        </div>
      </div>
      <p class="kc-err" id="<?= e($id('host-err')) ?>" hidden></p>
      <div class="kc-row kc-row--end"><button type="submit" class="btn btn--primary kc-btn"><?= $ico('lock') ?><span><?= e(lt('Zertifikat prüfen')) ?></span></button></div>
    </form>
    <p class="kc-status kc-sr" role="status" aria-live="polite" data-status></p>
    <div class="kc-sections" data-out></div>
  </section>
  <?php endif; ?>

  <?php if (in_array('spf-generator', $tabs, true)): ?>
  <section class="kc-panel" id="<?= e($id('p-spf-generator')) ?>" data-panel="spf-generator"<?= count($tabs) > 1 ? ' role="tabpanel" aria-labelledby="' . e($id('tab-spf-generator')) . '" tabindex="0"' : '' ?><?= $start === 'spf-generator' ? '' : ' hidden' ?>>
    <<?= $h($hp) ?> class="kc-ptitle"><?= e(lt('SPF-Generator')) ?></<?= $h($hp) ?>>
    <p class="kc-lead"><?= e(lt('SPF legt fest, welche Server Mails für deine Domain senden dürfen. Trage ein, worüber du Mails verschickst – der Eintrag entsteht direkt hier im Browser.')) ?></p>
    <form class="kc-form kc-gen" data-form="spf-gen" novalidate>
      <div class="kc-field">
        <label class="kc-label" for="<?= e($id('sg-domain')) ?>"><?= e(lt('Domain')) ?></label>
        <input class="kc-input" id="<?= e($id('sg-domain')) ?>" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="253" required placeholder="<?= e(lt('beispiel.de')) ?>">
      </div>
      <fieldset class="kc-fs">
        <legend><?= e(lt('Eigene Server')) ?></legend>
        <label class="kc-check"><input type="checkbox" name="a" checked> <span><?= e(lt('Webserver der Domain darf senden (a)')) ?></span></label>
        <label class="kc-check"><input type="checkbox" name="mx" checked> <span><?= e(lt('Mailserver der Domain dürfen senden (mx)')) ?></span></label>
      </fieldset>
      <fieldset class="kc-fs">
        <legend><?= e(lt('Häufige Dienste (include)')) ?></legend>
        <div class="kc-chips">
          <?php foreach ($providers as $inc => $name): ?>
          <label class="kc-check kc-chip"><input type="checkbox" name="provider" value="<?= e($inc) ?>"> <span><?= e($name) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="kc-grid">
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-ip4')) ?>"><?= e(lt('IPv4-Adressen oder -Netze (eine pro Zeile)')) ?></label>
          <textarea class="kc-input" id="<?= e($id('sg-ip4')) ?>" name="ip4" rows="3" spellcheck="false" placeholder="192.0.2.10&#10;198.51.100.0/24"></textarea>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-ip6')) ?>"><?= e(lt('IPv6-Adressen oder -Netze (eine pro Zeile)')) ?></label>
          <textarea class="kc-input" id="<?= e($id('sg-ip6')) ?>" name="ip6" rows="3" spellcheck="false" placeholder="2001:db8::10"></textarea>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-inc')) ?>"><?= e(lt('Weitere Dienste per include (eine Domain pro Zeile)')) ?></label>
          <textarea class="kc-input" id="<?= e($id('sg-inc')) ?>" name="include" rows="3" spellcheck="false" placeholder="spf.newsletter-anbieter.example"></textarea>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-ahost')) ?>"><?= e(lt('Weitere Server per Name (a:, eine pro Zeile)')) ?></label>
          <textarea class="kc-input" id="<?= e($id('sg-ahost')) ?>" name="ahost" rows="3" spellcheck="false" placeholder="mail.beispiel.de"></textarea>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-mxhost')) ?>"><?= e(lt('Mailserver anderer Domains (mx:, eine pro Zeile)')) ?></label>
          <textarea class="kc-input" id="<?= e($id('sg-mxhost')) ?>" name="mxhost" rows="3" spellcheck="false"></textarea>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('sg-all')) ?>"><?= e(lt('Alle anderen Server')) ?></label>
          <select class="kc-input" id="<?= e($id('sg-all')) ?>" name="all" aria-describedby="<?= e($id('sg-all-hint')) ?>">
            <option value="~all"><?= e(lt('~all – als verdächtig markieren (empfohlen)')) ?></option>
            <option value="-all"><?= e(lt('-all – ablehnen (streng)')) ?></option>
            <option value="?all"><?= e(lt('?all – keine Aussage (schützt kaum)')) ?></option>
          </select>
          <p class="kc-hint" id="<?= e($id('sg-all-hint')) ?>"><?= e(lt('Mit DMARC ist „~all“ sicher; „-all“ kann weitergeleitete Mails abweisen.')) ?></p>
        </div>
      </div>
      <p class="kc-err" data-generr hidden></p>
      <div class="kc-row kc-row--end"><button type="submit" class="btn btn--primary kc-btn"><?= $ico('wand') ?><span><?= e(lt('SPF-Eintrag erstellen')) ?></span></button></div>
    </form>
    <p class="kc-status kc-sr" role="status" aria-live="polite" data-status></p>
    <div class="kc-out" data-genout hidden>
      <<?= $h($hs) ?> class="kc-stitle"><?= e(lt('Dein SPF-Eintrag')) ?></<?= $h($hs) ?>>
      <div class="kc-code"><code data-record></code><button type="button" class="btn btn--secondary kc-btn kc-sm" data-copy><?= e(lt('Kopieren')) ?></button></div>
      <ul class="kc-findings" data-notes></ul>
      <ol class="kc-steps">
        <li><b><?= e(lt('DNS-Verwaltung öffnen')) ?></b> – <?= e(lt('beim Anbieter deiner Domain (Hoster bzw. Registrar).')) ?></li>
        <li><b><?= e(lt('TXT-Eintrag anlegen')) ?></b> – <?= e(lt('Typ: TXT · Name: @ (bzw. leer oder die Domain selbst) · Wert: der Eintrag oben. Gibt es schon einen „v=spf1“-Eintrag, diesen ersetzen – nie zwei anlegen.')) ?></li>
        <li><b><?= e(lt('Speichern und prüfen')) ?></b> – <?= e(lt('Änderungen wirken nach Minuten bis 24 Stunden. Danach mit der Domain-Analyse kontrollieren.')) ?></li>
      </ol>
      <button type="button" class="btn btn--secondary kc-btn kc-sm" data-toanalyse><?= e(lt('Diesen Eintrag analysieren')) ?></button>
    </div>

    <form class="kc-form kc-ana" data-form="spf-analyse" novalidate>
      <<?= $h($hs) ?> class="kc-stitle"><?= e(lt('Bestehenden SPF-Eintrag analysieren und optimieren')) ?></<?= $h($hs) ?>>
      <div class="kc-field">
        <label class="kc-label" for="<?= e($id('sa-record')) ?>"><?= e(lt('SPF-Eintrag')) ?></label>
        <textarea class="kc-input kc-mono" id="<?= e($id('sa-record')) ?>" name="record" rows="3" spellcheck="false" maxlength="2048" required placeholder="v=spf1 include:_spf.google.com ~all"></textarea>
      </div>
      <div class="kc-field">
        <label class="kc-label" for="<?= e($id('sa-domain')) ?>"><?= e(lt('Domain (optional, für genauere Vorschläge)')) ?></label>
        <input class="kc-input" id="<?= e($id('sa-domain')) ?>" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="253" placeholder="<?= e(lt('beispiel.de')) ?>">
      </div>
      <div class="kc-examples" role="group" aria-label="<?= e(lt('Beispiele laden')) ?>">
        <span class="kc-label"><?= e(lt('Beispiele:')) ?></span>
        <button type="button" class="kc-link" data-example="v=spf1 a a:example.com mx include:mailgun.org include:sendgrid.net ip4:192.0.2.1 ip4:192.0.2.1 ip4:192.0.2.0/24 ptr ?all" data-exdomain="example.com"><?= e(lt('Mit Problemen')) ?></button>
        <button type="button" class="kc-link" data-example="v=spf1 a mx ip4:192.0.2.1 ~all"><?= e(lt('Einfach')) ?></button>
        <button type="button" class="kc-link" data-example="v=spf1 include:_spf.google.com ~all"><?= e(lt('Google Workspace')) ?></button>
        <button type="button" class="kc-link" data-example="v=spf1 include:spf.protection.outlook.com -all"><?= e(lt('Microsoft 365')) ?></button>
      </div>
      <p class="kc-err" data-generr hidden></p>
      <div class="kc-row kc-row--end"><button type="submit" class="btn btn--primary kc-btn"><?= $ico('search') ?><span><?= e(lt('Analysieren')) ?></span></button></div>
    </form>
    <p class="kc-status kc-sr" role="status" aria-live="polite" data-status2></p>
    <div class="kc-sections" data-out></div>
  </section>
  <?php endif; ?>

  <?php if (in_array('dmarc-generator', $tabs, true)): ?>
  <section class="kc-panel" id="<?= e($id('p-dmarc-generator')) ?>" data-panel="dmarc-generator"<?= count($tabs) > 1 ? ' role="tabpanel" aria-labelledby="' . e($id('tab-dmarc-generator')) . '" tabindex="0"' : '' ?><?= $start === 'dmarc-generator' ? '' : ' hidden' ?>>
    <<?= $h($hp) ?> class="kc-ptitle"><?= e(lt('DMARC-Generator')) ?></<?= $h($hp) ?>>
    <p class="kc-lead"><?= e(lt('DMARC sagt Empfängern, was mit Mails passieren soll, die SPF und DKIM nicht bestehen – und schickt dir Berichte. Starte mit „none“ und steigere dich.')) ?></p>
    <form class="kc-form kc-gen" data-form="dmarc-gen" novalidate>
      <div class="kc-grid">
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-p')) ?>"><?= e(lt('Richtlinie (p)')) ?></label>
          <select class="kc-input" id="<?= e($id('dg-p')) ?>" name="p" aria-describedby="<?= e($id('dg-p-hint')) ?>">
            <option value="none"><?= e(lt('none – nur beobachten (zum Start)')) ?></option>
            <option value="quarantine"><?= e(lt('quarantine – verdächtige Mails in den Spam')) ?></option>
            <option value="reject"><?= e(lt('reject – verdächtige Mails ablehnen')) ?></option>
          </select>
          <p class="kc-hint" id="<?= e($id('dg-p-hint')) ?>"><?= e(lt('Die ersten 2–4 Wochen „none“ und die Berichte auswerten.')) ?></p>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-sp')) ?>"><?= e(lt('Richtlinie für Subdomains (sp)')) ?></label>
          <select class="kc-input" id="<?= e($id('dg-sp')) ?>" name="sp">
            <option value=""><?= e(lt('wie die Domain')) ?></option>
            <option value="none">none</option>
            <option value="quarantine">quarantine</option>
            <option value="reject">reject</option>
          </select>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-pct')) ?>"><?= e(lt('Anteil der geprüften Mails (pct)')) ?> <output class="kc-out-val" for="<?= e($id('dg-pct')) ?>" data-pctout>100 %</output></label>
          <input class="kc-range" id="<?= e($id('dg-pct')) ?>" name="pct" type="range" min="0" max="100" step="5" value="100">
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-rua')) ?>"><?= e(lt('Adresse für Sammelberichte (rua)')) ?></label>
          <input class="kc-input" id="<?= e($id('dg-rua')) ?>" name="rua" type="email" autocomplete="off" placeholder="<?= e(lt('dmarc@beispiel.de')) ?>" aria-describedby="<?= e($id('dg-rua-hint')) ?>">
          <p class="kc-hint" id="<?= e($id('dg-rua-hint')) ?>"><?= e(lt('Täglich ein XML-Bericht je Empfänger – ein eigenes Postfach oder ein Auswertungsdienst lohnt sich.')) ?></p>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-ruf')) ?>"><?= e(lt('Adresse für Fehlerberichte (ruf, optional)')) ?></label>
          <input class="kc-input" id="<?= e($id('dg-ruf')) ?>" name="ruf" type="email" autocomplete="off" aria-describedby="<?= e($id('dg-ruf-hint')) ?>">
          <p class="kc-hint" id="<?= e($id('dg-ruf-hint')) ?>"><?= e(lt('Nur wenige Anbieter schicken sie; sie können personenbezogene Daten enthalten.')) ?></p>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-domain')) ?>"><?= e(lt('Deine Domain (optional)')) ?></label>
          <input class="kc-input" id="<?= e($id('dg-domain')) ?>" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="253" placeholder="<?= e(lt('beispiel.de')) ?>" aria-describedby="<?= e($id('dg-domain-hint')) ?>">
          <p class="kc-hint" id="<?= e($id('dg-domain-hint')) ?>"><?= e(lt('Für den Hinweis, ob Berichte an eine fremde Domain freigegeben werden müssen.')) ?></p>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-aspf')) ?>"><?= e(lt('SPF-Abgleich (aspf)')) ?></label>
          <select class="kc-input" id="<?= e($id('dg-aspf')) ?>" name="aspf">
            <option value="r"><?= e(lt('relaxed – Subdomains zählen mit (empfohlen)')) ?></option>
            <option value="s"><?= e(lt('strict – exakt gleiche Domain')) ?></option>
          </select>
        </div>
        <div class="kc-field">
          <label class="kc-label" for="<?= e($id('dg-adkim')) ?>"><?= e(lt('DKIM-Abgleich (adkim)')) ?></label>
          <select class="kc-input" id="<?= e($id('dg-adkim')) ?>" name="adkim">
            <option value="r"><?= e(lt('relaxed – Subdomains zählen mit (empfohlen)')) ?></option>
            <option value="s"><?= e(lt('strict – exakt gleiche Domain')) ?></option>
          </select>
        </div>
      </div>
      <p class="kc-err" data-generr hidden></p>
      <div class="kc-row kc-row--end"><button type="submit" class="btn btn--primary kc-btn"><?= $ico('wand') ?><span><?= e(lt('DMARC-Eintrag erstellen')) ?></span></button></div>
    </form>
    <p class="kc-status kc-sr" role="status" aria-live="polite" data-status></p>
    <div class="kc-out" data-genout hidden>
      <<?= $h($hs) ?> class="kc-stitle"><?= e(lt('Dein DMARC-Eintrag')) ?></<?= $h($hs) ?>>
      <div class="kc-code"><code data-record></code><button type="button" class="btn btn--secondary kc-btn kc-sm" data-copy><?= e(lt('Kopieren')) ?></button></div>
      <ul class="kc-findings" data-notes></ul>
      <ol class="kc-steps">
        <li><b><?= e(lt('DNS-Verwaltung öffnen')) ?></b> – <?= e(lt('beim Anbieter deiner Domain (Hoster bzw. Registrar).')) ?></li>
        <li><b><?= e(lt('TXT-Eintrag anlegen')) ?></b> – <?= e(lt('Typ: TXT · Name: _dmarc · Wert: der Eintrag oben. Es darf nur einen DMARC-Eintrag geben.')) ?></li>
        <li><b><?= e(lt('Berichte auswerten und steigern')) ?></b> – <?= e(lt('Nach 2–4 Wochen ohne Überraschungen auf „quarantine“, später auf „reject“ umstellen.')) ?></li>
      </ol>
      <button type="button" class="btn btn--secondary kc-btn kc-sm" data-toanalyse><?= e(lt('Diesen Eintrag analysieren')) ?></button>
    </div>

    <form class="kc-form kc-ana" data-form="dmarc-analyse" novalidate>
      <<?= $h($hs) ?> class="kc-stitle"><?= e(lt('Bestehenden DMARC-Eintrag analysieren')) ?></<?= $h($hs) ?>>
      <div class="kc-field">
        <label class="kc-label" for="<?= e($id('da-record')) ?>"><?= e(lt('DMARC-Eintrag')) ?></label>
        <textarea class="kc-input kc-mono" id="<?= e($id('da-record')) ?>" name="record" rows="3" spellcheck="false" maxlength="2048" required placeholder="v=DMARC1; p=none; rua=mailto:dmarc@beispiel.de"></textarea>
      </div>
      <div class="kc-field">
        <label class="kc-label" for="<?= e($id('da-domain')) ?>"><?= e(lt('Domain (optional, prüft externe Berichtsadressen)')) ?></label>
        <input class="kc-input" id="<?= e($id('da-domain')) ?>" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="253" placeholder="<?= e(lt('beispiel.de')) ?>">
      </div>
      <div class="kc-examples" role="group" aria-label="<?= e(lt('Beispiele laden')) ?>">
        <span class="kc-label"><?= e(lt('Beispiele:')) ?></span>
        <button type="button" class="kc-link" data-example="v=DMARC1; p=none; rua=mailto:dmarc-reports@example.com"><?= e(lt('Einstieg')) ?></button>
        <button type="button" class="kc-link" data-example="v=DMARC1; p=quarantine; pct=25; rua=mailto:dmarc-reports@example.com"><?= e(lt('Schrittweise')) ?></button>
        <button type="button" class="kc-link" data-example="v=DMARC1; p=reject; rua=mailto:dmarc-reports@example.com; adkim=s; aspf=s"><?= e(lt('Streng')) ?></button>
      </div>
      <p class="kc-err" data-generr hidden></p>
      <div class="kc-row kc-row--end"><button type="submit" class="btn btn--primary kc-btn"><?= $ico('search') ?><span><?= e(lt('Analysieren')) ?></span></button></div>
    </form>
    <p class="kc-status kc-sr" role="status" aria-live="polite" data-status2></p>
    <div class="kc-sections" data-out></div>
  </section>
  <?php endif; ?>

  <?php if (!empty($o['guides'])): ?>
  <section class="kc-guides" aria-labelledby="<?= e($id('guides')) ?>">
    <<?= $h($hp) ?> class="kc-ptitle" id="<?= e($id('guides')) ?>"><?= e(lt('Einsteiger-Guides')) ?></<?= $h($hp) ?>>
    <details class="kc-guide">
      <summary><?= e(lt('SPF: Wer darf für meine Domain senden?')) ?></summary>
      <div class="kc-guide__body">
        <p><?= e(lt('SPF (Sender Policy Framework) ist ein TXT-Eintrag im DNS. Er listet die Server, die Mails für deine Domain verschicken dürfen. Empfänger prüfen ihn und sortieren Mails von anderen Servern aus – das schützt vor gefälschten Absendern und verbessert die Zustellbarkeit. DMARC setzt SPF voraus.')) ?></p>
        <ul>
          <li><code>v=spf1</code> – <?= e(lt('Version, steht immer am Anfang')) ?></li>
          <li><code>a</code> / <code>mx</code> – <?= e(lt('Webserver bzw. Mailserver der Domain dürfen senden')) ?></li>
          <li><code>ip4:192.0.2.10</code> – <?= e(lt('eine feste Adresse oder ein Netz')) ?></li>
          <li><code>include:_spf.google.com</code> – <?= e(lt('übernimmt die Server eines Dienstes')) ?></li>
          <li><code>~all</code> / <code>-all</code> – <?= e(lt('was mit allen anderen passiert (markieren bzw. ablehnen)')) ?></li>
        </ul>
        <p><b><?= e(lt('Wichtige Grenzen:')) ?></b> <?= e(lt('höchstens 10 DNS-Lookups (a, mx, include, exists, redirect – auch in eingebundenen Einträgen), genau ein SPF-Eintrag je Domain, möglichst kurz halten.')) ?></p>
      </div>
    </details>
    <details class="kc-guide">
      <summary><?= e(lt('DMARC: Was passiert mit gefälschten Mails?')) ?></summary>
      <div class="kc-guide__body">
        <p><?= e(lt('DMARC baut auf SPF und DKIM auf. Es sagt Empfängern, was sie mit Mails tun sollen, die im Namen deiner Domain kommen, aber die Prüfungen nicht bestehen – und schickt dir Berichte darüber, wer in deinem Namen sendet. Google, Yahoo und Microsoft verlangen DMARC für Massenversender.')) ?></p>
        <ol>
          <li><code>p=none</code> – <?= e(lt('beobachten und Berichte sammeln (Start, 2–4 Wochen)')) ?></li>
          <li><code>p=quarantine</code> – <?= e(lt('verdächtige Mails landen im Spam')) ?></li>
          <li><code>p=reject</code> – <?= e(lt('verdächtige Mails werden abgewiesen (Ziel)')) ?></li>
        </ol>
        <p><b><?= e(lt('Voraussetzungen:')) ?></b> <?= e(lt('SPF ist eingerichtet, DKIM signiert deine Mails (empfohlen), ein Postfach für die Berichte (rua) ist vorhanden.')) ?></p>
        <p class="kc-callout"><?= e(lt('Nie direkt mit „reject“ starten: Vergessene Versender (Newsletter, Shop, Kontaktformular) würden sonst abgewiesen.')) ?></p>
      </div>
    </details>
    <details class="kc-guide">
      <summary><?= e(lt('Mail-Check: So gehst du vor')) ?></summary>
      <div class="kc-guide__body">
        <ol class="kc-steps">
          <li><b><?= e(lt('Ist-Zustand prüfen')) ?></b> – <?= e(lt('Domain-Analyse starten: SPF, DMARC, DKIM, Mailserver, Hosting und Website auf einen Blick.')) ?></li>
          <li><b><?= e(lt('SPF aufräumen')) ?></b> – <?= e(lt('mit dem SPF-Generator einen sauberen Eintrag bauen; die Analyse zählt die Lookups und schlägt Vereinfachungen vor.')) ?></li>
          <li><b><?= e(lt('DKIM aktivieren')) ?></b> – <?= e(lt('beim Mail-Anbieter einschalten und den angezeigten Schlüssel ins DNS eintragen.')) ?></li>
          <li><b><?= e(lt('DMARC einführen')) ?></b> – <?= e(lt('mit p=none starten, Berichte lesen, dann schrittweise verschärfen.')) ?></li>
          <li><b><?= e(lt('Dranbleiben')) ?></b> – <?= e(lt('nach jeder Änderung am Versand (neuer Dienst, Umzug) erneut prüfen.')) ?></li>
        </ol>
        <p><?= e(lt('DNS-Änderungen brauchen Minuten bis 24 Stunden, bis sie überall ankommen. Notiere dir vorher die alten Einträge, dann kannst du jederzeit zurück.')) ?></p>
      </div>
    </details>
  </section>
  <?php endif; ?>

  <p class="kc-foot"><?= e(lt('Alle Angaben ohne Gewähr – prüfe erzeugte Einträge vor dem Eintragen. Abgefragte Domains werden nicht gespeichert; Ergebnisse werden nur wenige Minuten zwischengespeichert.')) ?></p>
  <script type="application/json" class="kc-i18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
<?php if ($first): ?><script type="module" src="<?= e($js) ?>"></script><?php endif; ?>
