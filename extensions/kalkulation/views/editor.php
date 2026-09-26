<?php
/**
 * Editor einer Kalkulation. Kopf-, Grundlage- und Textfelder stehen im HTML (data-f = Feld), Positionen, Sätze der
 * Grundlage, Summen und Kundenansicht baut assets/js/kalkulation.js aus #kx-boot. Speichern per JSON (Stand-Prüfung).
 * @var array $c  @var array $boot  @var list<array> $versions  @var list<array> $log
 */
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Num;

$base = url('/admin/kalkulation/' . $c['id']);
$st = Kalkulation::statuses();
$p = $c['params'];
$texts = array_values(array_filter(array_map(fn($s) => ['name' => (string) $s['name'], 'text' => (string) ($s['text'] ?? '')], $boot['catalog']), fn($t) => trim($t['text']) !== ''));
$logLabels = ['create' => __('angelegt'), 'save' => __('gespeichert'), 'status' => __('Status geändert'), 'version' => __('Stand gesichert'), 'restore' => __('Stand wiederhergestellt'),
    'duplicate' => __('als Kopie angelegt'), 'export' => __('exportiert')];
?>
<header class="adm-head kx-head kx-head--editor">
  <div>
    <p class="adm-eyebrow"><a href="<?= e(url('/admin/kalkulation')) ?>">← <?= e(__('Kalkulationen')) ?></a> · <span data-kx-number><?= e($c['number']) ?></span></p>
    <h1 data-kx-title><?= e($c['title'] !== '' ? $c['title'] : __('(ohne Titel)')) ?></h1>
  </div>
  <div class="adm-row kx-head__actions">
    <a class="adm-btn" href="<?= e($base . '/angebot') ?>" target="_blank" rel="noopener" data-kx-offer><?= icon('printer') ?><?= e(__('Angebot / PDF')) ?></a>
    <details class="kx-menu">
      <summary class="adm-btn"><?= icon('download-simple') ?><?= e(__('CSV')) ?></summary>
      <div class="kx-menu__pop">
        <a href="<?= e($base . '/positionen.csv') ?>" data-kx-dl><?= e(__('Positionen mit internen Spalten')) ?></a>
        <a href="<?= e($base . '/positionen.csv?ansicht=kunde') ?>" data-kx-dl><?= e(__('Positionen für den Kunden')) ?></a>
      </div>
    </details>
    <form method="post" action="<?= e($base . '/duplizieren') ?>"><?= csrf_field() ?><button class="adm-btn" type="submit"><?= icon('copy') ?><?= e(__('Duplizieren')) ?></button></form>
  </div>
</header>

<?php if ($c['locked']): ?>
<section class="adm-card adm-card--danger"><p><?= e(__('Diese Kalkulation wurde mit einem anderen Schlüssel (app_key) verschlüsselt und kann hier nicht gelesen werden.')) ?></p></section>
<?php return; endif; ?>

<noscript><p class="adm-flash adm-flash--error"><?= e(__('Der Editor braucht JavaScript. Angebot und CSV funktionieren auch ohne.')) ?></p></noscript>

<div id="kx-app" class="kx-app" data-view="internal">
<script type="application/json" id="kx-boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<section class="adm-card kx-meta" aria-labelledby="kx-meta-h">
  <h2 id="kx-meta-h" class="sr-only"><?= e(__('Angaben')) ?></h2>
  <div class="kx-meta__grid">
    <div class="f kx-span2"><label for="kx-title"><?= e(__('Titel')) ?></label><input id="kx-title" data-f="title" value="<?= e($c['title']) ?>" maxlength="200" placeholder="<?= e(__('z. B. Managed Nextcloud Team + Office')) ?>"></div>
    <div class="f"><label for="kx-status"><?= e(__('Status')) ?></label>
      <select id="kx-status" data-f="status"><?php foreach ($st as $k => [$l]): ?><option value="<?= e($k) ?>"<?= $c['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="kx-number"><?= e(__('Nummer')) ?></label><input id="kx-number" data-f="number" value="<?= e($c['number']) ?>" maxlength="60"></div>
    <div class="f kx-span2"><label for="kx-customer"><?= e(__('Kunde')) ?></label><input id="kx-customer" data-f="customer" value="<?= e($c['customer']) ?>" maxlength="200" autocomplete="off"></div>
    <div class="f"><label for="kx-date"><?= e(__('Datum')) ?></label><input id="kx-date" type="date" data-f="calc_date" value="<?= e($c['calc_date']) ?>"></div>
    <div class="f"><label for="kx-valid"><?= e(__('Gültig bis')) ?></label><input id="kx-valid" type="date" data-f="valid_until" value="<?= e($c['valid_until']) ?>"></div>
    <div class="f kx-span2"><label for="kx-address"><?= e(__('Anschrift (für das Angebot)')) ?></label><textarea id="kx-address" data-f="address" data-kia-off rows="3" maxlength="600"><?= e($c['address']) ?></textarea></div>
    <div class="f kx-span2">
      <label for="kx-project"><?= e(__('Projekt / Website')) ?></label><input id="kx-project" data-f="project" value="<?= e($c['project']) ?>" maxlength="200" placeholder="<?= e(__('z. B. cloud.beispiel.de')) ?>">
      <label for="kx-contact" class="kx-mt"><?= e(__('Ansprechpartner (Kunde)')) ?></label><input id="kx-contact" data-f="contact" value="<?= e($c['contact']) ?>" maxlength="200">
    </div>
  </div>
</section>

<section class="adm-card adm-card--flush kx-positions" aria-labelledby="kx-pos-h">
  <div class="kx-bar">
    <h2 id="kx-pos-h"><?= e(__('Positionen')) ?></h2>
    <div class="kx-seg" role="group" aria-label="<?= e(__('Ansicht')) ?>">
      <button type="button" aria-pressed="true" data-kx-view="internal"><?= e(__('Intern')) ?></button>
      <button type="button" aria-pressed="false" data-kx-view="customer"><?= e(__('Kundenansicht')) ?></button>
    </div>
    <span class="kx-bar__sp"></span>
    <button type="button" class="adm-btn adm-btn--small" data-kx-add><?= icon('plus') ?><?= e(__('Position')) ?></button>
    <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-kx-catalog><?= icon('package') ?><?= e(__('Aus Katalog …')) ?></button>
  </div>
  <div class="kx-scroll" data-kx-table></div>
  <div class="kx-preview" data-kx-preview hidden></div>
  <p class="kx-hint adm-muted"><?= e(__('Tastatur: Tab springt durch die Felder, Enter in der letzten Zeile legt eine neue Position an (sonst nächste Zeile), Strg+Enter neue Position, Alt+↑/↓ verschiebt, Strg+S speichert.')) ?></p>
  <p class="kx-phone-note adm-muted"><?= e(__('Auf dem Telefon nur Ansicht – bearbeiten ab Tablet-Breite.')) ?></p>
</section>

<div class="kx-sums">
  <section class="adm-card kx-totals" aria-labelledby="kx-tot-h">
    <h2 id="kx-tot-h"><?= e(__('Summen')) ?></h2>
    <div data-kx-totals></div>
    <div class="f kx-disc">
      <div><label for="kx-d1"><?= e(__('Nachlass einmalig %')) ?></label><input id="kx-d1" data-f="discount_once" data-num inputmode="decimal" value="<?= e(Num::input($c['discount_once'])) ?>"></div>
      <div><label for="kx-d2"><?= e(__('Nachlass monatlich %')) ?></label><input id="kx-d2" data-f="discount_monthly" data-num inputmode="decimal" value="<?= e(Num::input($c['discount_monthly'])) ?>"></div>
    </div>
  </section>
  <section class="adm-card kx-internal" aria-labelledby="kx-int-h">
    <h2 id="kx-int-h"><?= e(__('Intern: Kosten & Marge')) ?></h2>
    <div data-kx-internal></div>
  </section>
</div>
<div class="sr-only" aria-live="polite" data-kx-live></div>

<details class="adm-card kx-fold" id="kx-basis">
  <summary><h2><?= e(__('Grundlage dieser Kalkulation')) ?></h2><span class="adm-muted" data-kx-basis-sum></span></summary>
  <p class="adm-muted"><?= e(__('Sätze und Aufschläge werden beim Anlegen aus den Einstellungen übernommen und bleiben hier fest – spätere Änderungen der Einstellungen verändern alte Kalkulationen nicht.')) ?></p>
  <div class="kx-basis">
    <div data-kx-rates></div>
    <div class="kx-basis__grid">
      <div class="f"><label for="kx-markup"><?= e(__('Aufschlag Fremdkosten %')) ?></label><input id="kx-markup" data-f="params.markup" data-num inputmode="decimal" value="<?= e(Num::input($p['markup'])) ?>"></div>
      <div class="f"><label for="kx-buffer"><?= e(__('Projektpuffer %')) ?></label><input id="kx-buffer" data-f="params.buffer" data-num inputmode="decimal" value="<?= e(Num::input($p['buffer'])) ?>"></div>
      <div class="f"><label for="kx-vat"><?= e(__('Umsatzsteuer %')) ?></label><input id="kx-vat" data-f="params.vat" data-num inputmode="decimal" value="<?= e(Num::input($p['vat'])) ?>"></div>
      <div class="f"><label for="kx-costrate"><?= e(__('Selbstkosten je Stunde (intern)')) ?></label><input id="kx-costrate" data-f="params.cost_rate" data-num inputmode="decimal" value="<?= e(Num::input($p['cost_rate'])) ?>"></div>
      <div class="f"><label for="kx-cur"><?= e(__('Währung')) ?></label>
        <select id="kx-cur" data-f="params.currency"><?php foreach (Kalkulation::currencies() as $k => $l): ?><option value="<?= e($k) ?>"<?= $p['currency'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="kx-round"><?= e(__('Einzelpreise runden')) ?></label>
        <select id="kx-round" data-f="params.round_step"><?php foreach (['0' => __('nicht (auf Cent)'), '0.1' => __('auf 10 Cent'), '1' => __('auf 1'), '5' => __('auf 5'), '10' => __('auf 10'), '50' => __('auf 50')] as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= (string) (float) $p['round_step'] === (string) (float) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="f"><label for="kx-rmode"><?= e(__('Rundungsart')) ?></label>
        <select id="kx-rmode" data-f="params.round_mode"><option value="nearest"<?= $p['round_mode'] !== 'up' ? ' selected' : '' ?>><?= e(__('kaufmännisch')) ?></option><option value="up"<?= $p['round_mode'] === 'up' ? ' selected' : '' ?>><?= e(__('immer aufrunden')) ?></option></select></div>
    </div>
    <p><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-kx-reload-basis><?= e(__('Grundlage aus Einstellungen laden')) ?></button></p>
  </div>
</details>

<details class="adm-card kx-fold" id="kx-texts">
  <summary><h2><?= e(__('Texte für das Angebot')) ?></h2><span class="adm-muted"><?= e(__('Einleitung, Zahlung, Gültigkeit, Laufzeit')) ?></span></summary>
  <div class="kx-texts">
    <div class="f"><label for="kx-intro"><?= e(__('Einleitung')) ?></label><textarea id="kx-intro" data-f="intro" rows="4"><?= e($c['intro']) ?></textarea>
      <?php if ($texts): ?>
      <div class="kx-snip"><label for="kx-snip" class="f-help"><?= e(__('Textbaustein anhängen')) ?></label>
        <select id="kx-snip" data-kx-snippet><option value=""><?= e(__('– auswählen –')) ?></option>
          <?php foreach ($texts as $i => $t): ?><option value="<?= $i ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
        <script type="application/json" id="kx-snippets"><?= json_encode(array_column($texts, 'text'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      </div><?php endif; ?>
    </div>
    <div class="f"><label for="kx-pay"><?= e(__('Zahlungsbedingungen')) ?></label><textarea id="kx-pay" data-f="payment" rows="3"><?= e($c['payment']) ?></textarea></div>
    <div class="f"><label for="kx-validity"><?= e(__('Gültigkeit')) ?></label><textarea id="kx-validity" data-f="validity" rows="2"><?= e($c['validity']) ?></textarea>
      <p class="f-help"><?= e(__('{datum} wird durch „Gültig bis“ ersetzt.')) ?></p></div>
    <div class="f"><label for="kx-term"><?= e(__('Laufzeit und Kündigung (monatliche Leistungen)')) ?></label><textarea id="kx-term" data-f="term" rows="2"><?= e($c['term']) ?></textarea></div>
    <div class="f"><label for="kx-closing"><?= e(__('Schluss')) ?></label><textarea id="kx-closing" data-f="closing" rows="2"><?= e($c['closing']) ?></textarea></div>
    <div class="f"><label for="kx-note"><?= e(__('Interne Notiz (erscheint nicht im Angebot)')) ?></label><textarea id="kx-note" data-f="note" data-kia-off rows="3"><?= e($c['note']) ?></textarea></div>
  </div>
</details>

<details class="adm-card kx-fold" id="kx-versions">
  <summary><h2><?= e(__('Stände & Protokoll')) ?></h2><span class="adm-muted"><?= e(__('{n} Stände', ['n' => count($versions)])) ?> · <?= e(__('zuletzt {time} von {user}', ['time' => Kalkulation::dateTime($c['updated_at']), 'user' => Kalkulation::userName($c['updated_by'])])) ?></span></summary>
  <p class="adm-muted"><?= e(__('Beim Speichern wird höchstens alle 15 Minuten und bei jedem Statuswechsel automatisch ein Stand gesichert. Wiederherstellen sichert vorher den aktuellen Stand.')) ?></p>
  <form class="adm-row kx-snapform" method="post" action="<?= e($base . '/sichern') ?>"><?= csrf_field() ?>
    <label class="sr-only" for="kx-snapnote"><?= e(__('Notiz zum Stand')) ?></label>
    <input id="kx-snapnote" name="note" maxlength="191" placeholder="<?= e(__('Notiz, z. B. „an Kunden geschickt“')) ?>">
    <button class="adm-btn adm-btn--small" type="submit" data-kx-needsave><?= e(__('Aktuellen Stand sichern')) ?></button>
  </form>
  <?php if ($versions): ?>
  <table class="adm-table kx-vtable">
    <caption class="sr-only"><?= e(__('Gesicherte Stände')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Zeit')) ?></th><th scope="col"><?= e(__('Von')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Notiz')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $v): ?>
      <tr><td class="kx-nowrap"><?= e(Kalkulation::dateTime($v['created_at'])) ?></td><td><?= e(Kalkulation::userName($v['user_id'] !== null ? (int) $v['user_id'] : null)) ?></td>
        <td><?= e($st[$v['status']][0] ?? (string) $v['status']) ?></td><td><?= e((string) $v['note']) ?></td>
        <td class="adm-actions"><form method="post" action="<?= e($base . '/stand/' . (int) $v['id']) ?>" data-kx-confirm="<?= e(__('Diesen Stand wiederherstellen? Der aktuelle Stand wird vorher gesichert.')) ?>"><?= csrf_field() ?>
          <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Wiederherstellen')) ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php if ($log): ?>
  <h3 class="kx-h3"><?= e(__('Protokoll')) ?></h3>
  <ul class="adm-list kx-log">
    <?php foreach ($log as $l): ?><li><span class="kx-nowrap"><?= e(Kalkulation::dateTime($l['at'])) ?></span> · <?= e(Kalkulation::userName($l['user_id'] !== null ? (int) $l['user_id'] : null)) ?> · <?= e($logLabels[$l['action']] ?? $l['action']) ?><?= $l['info'] !== null && $l['info'] !== '' ? ' <span class="adm-muted">(' . e((string) $l['info']) . ')</span>' : '' ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</details>

<details class="adm-card adm-card--danger kx-fold">
  <summary><h2><?= e(__('Löschen')) ?></h2></summary>
  <form method="post" action="<?= e($base . '/loeschen') ?>" class="kx-delete"><?= csrf_field() ?>
    <p><?= e(__('Löscht die Kalkulation mit allen Ständen endgültig. Zum Aufheben lieber den Status „Archiv“ wählen.')) ?></p>
    <div class="f"><label for="kx-del"><?= e(__('Zur Bestätigung die Nummer {number} eintippen', ['number' => $c['number']])) ?></label><input id="kx-del" name="confirm" autocomplete="off"></div>
    <button class="adm-btn adm-btn--danger" type="submit"><?= e(__('Endgültig löschen')) ?></button>
  </form>
</details>

<div class="adm-savebar kx-savebar">
  <span class="kx-state" data-kx-state role="status"><?= e(__('Gespeichert')) ?></span>
  <span class="kx-savebar__sums" data-kx-mini></span>
  <span class="kx-bar__sp"></span>
  <button type="button" class="adm-btn adm-btn--primary" data-kx-save><?= e(__('Speichern')) ?> <kbd>⌘S</kbd></button>
</div>

<datalist id="kx-units"><?php foreach ($boot['units'] as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist>

<dialog class="adm-dialog kx-dialog" id="kx-catalog" aria-labelledby="kx-cat-h">
  <div class="adm-dialog__head"><h2 id="kx-cat-h"><?= e(__('Aus dem Leistungskatalog')) ?></h2>
    <button type="button" class="icon-btn" data-kx-close aria-label="<?= e(__('Schließen')) ?>"><?= icon('x') ?></button></div>
  <div class="f"><label for="kx-cat-q"><?= e(__('Suchen')) ?></label><input id="kx-cat-q" type="search" data-kx-cat-q placeholder="<?= e(__('Leistung oder Paket …')) ?>" autocomplete="off"></div>
  <div class="kx-cat" data-kx-cat-list></div>
  <p class="adm-muted kx-cat__foot"><?= e(__('Pakete mit Einrichtung und Betrieb ergeben zwei Positionen (einmalig und monatlich). Enter oder Klick fügt hinzu; das Fenster bleibt offen.')) ?>
    <a href="<?= e(url('/admin/kalkulation/katalog')) ?>"><?= e(__('Katalog bearbeiten')) ?></a></p>
</dialog>
</div>
