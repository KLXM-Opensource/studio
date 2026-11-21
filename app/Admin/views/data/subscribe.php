<?php
/**
 * Daten → Tabelle → Abonnieren (Core\Data\Subscriptions): öffentliche Adresse (webcal bzw. .vcf), Abo-Tokens, QR-Code, Anleitung je App.
 * Ein neuer Token steht nur in dieser Antwort ($new) – im Dialog mit Adresse, QR-Code und Anleitung.
 * @var array $t  @var string $kind calendar|contacts  @var array $tokens  @var ?array $new {id, token, name, scope}  @var array $errors  @var array $old
 */
use Core\Data\Subscriptions;
use Core\Totp;

$cal = $kind === 'calendar';
$enabled = Subscriptions::enabled();
$fmt = \Core\Format::admin();
$base = '/admin/data/' . $t['handle'];
$apps = fn(?string $token, string $scope) => array_values(array_filter((array) (\Core\Extensions::filter('subscriptions.apps', [],
    ['table' => $t, 'scope' => $scope, 'token' => $token]) ?: []), fn($a) => is_array($a) && !empty($a['label'])));
$fe = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="abo-' . e($k) . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="abo-' . e($k) . '-e"' : '';

/** Adresse mit Kopieren-Knopf */
$addr = function (string $id, string $label, string $value): string {
    return '<div class="abo-addr"><span class="abo-addr__label">' . e($label) . '</span><div class="adm-secret"><code id="' . e($id) . '">' . e($value) . '</code>'
        . '<button type="button" class="adm-btn adm-btn--small" data-copy="#' . e($id) . '">' . e(__('Kopieren')) . '</button></div></div>';
};

/** QR-Code (lokal erzeugt, chillerlan/php-qrcode über Core\Totp) */
$qr = fn(string $value, string $alt) => '<figure class="abo-qr"><img src="' . e(Totp::qrDataUri($value)) . '" width="180" height="180" alt="' . e($alt) . '">'
    . '<figcaption>' . e(__('Mit der Kamera des Telefons scannen')) . '</figcaption></figure>';

/** Anleitung je App ($u = Adressen, $dav = CardDAV-Zugang oder null) */
$guide = function (array $u, ?array $dav) use ($cal): string {
    $items = $cal ? [
        ['iPhone / iPad', __('Den QR-Code mit der Kamera scannen oder die webcal-Adresse antippen → „Abonnieren“. Von Hand: Einstellungen → Apps → Kalender → Accounts → Account hinzufügen → Andere → „Kalenderabo hinzufügen“ → Adresse einfügen.')],
        ['macOS Kalender', __('Ablage → „Neues Kalenderabonnement …“ → Adresse einfügen → „Abonnieren“; unter „Automatisch aktualisieren“ z. B. „Jede Stunde“ wählen. Ort „iCloud“ gleicht das Abo mit allen Apple-Geräten ab.')],
        ['Android', __('Mit ICSx⁵ (F-Droid oder Play Store): „+“ → Adresse einfügen → Kalender erscheint in jeder Kalender-App. Google Kalender im Browser: „Weitere Kalender“ → „+“ → „Per URL“ – Google ruft die Adresse dann von seinen Servern ab (bei Abos mit internen Einträgen besser ICSx⁵).')],
        ['Outlook', __('Outlook im Web bzw. neues Outlook: Kalender → „Kalender hinzufügen“ → „Aus dem Internet abonnieren“ → https-Adresse. Klassisches Outlook: Kalender → „Kalender hinzufügen“ → „Aus dem Internet …“.')],
        ['Thunderbird', __('Kalender → „Neuer Kalender …“ → „Im Netzwerk“ → https-Adresse einfügen → „Kalender finden“ → abonnieren.')],
    ] : [
        ['iPhone / iPad', $dav ? __('Einstellungen → Apps → Kontakte → Accounts → Account hinzufügen → Andere → „CardDAV-Account hinzufügen“ → Server, Benutzername und Passwort wie oben.')
            : __('Die .vcf-Adresse öffnen (QR-Code scannen) → „Alle Kontakte hinzufügen“. Das ist ein einmaliger Stand – für Änderungen die Datei erneut öffnen.')],
        ['macOS Kontakte', $dav ? __('Kontakte → Einstellungen → Accounts → „+“ → „Anderer Kontakte-Account …“ → CardDAV → „Manuell“ → Server, Benutzername und Passwort wie oben.')
            : __('Die .vcf-Datei laden und mit Kontakte öffnen (Ablage → Importieren). Einmaliger Stand.')],
        ['Android', $dav ? __('DAVx⁵: Konto hinzufügen → „Mit URL und Benutzername anmelden“ → Server, Benutzername und Passwort wie oben → Adressbuch auswählen.')
            : __('Die .vcf-Datei laden und in der Kontakte-App importieren. Einmaliger Stand.')],
        ['Outlook', __('Outlook kennt kein Adressbuch-Abo: die .vcf-Datei laden und öffnen bzw. importieren – ein einmaliger Stand.')],
        ['Thunderbird', $dav ? __('Adressbuch → „Neues Adressbuch“ → „CardDAV-Adressbuch hinzufügen“ → Benutzername und Server-Adresse wie oben, danach das Passwort.')
            : __('Adressbuch → Extras → „Importieren …“ → vCard-Datei. Einmaliger Stand.')],
    ];
    $out = '<div class="abo-guide">';
    foreach ($items as $i => [$app, $text]) {
        $out .= '<details class="abo-guide__app"' . ($i === 0 ? ' open' : '') . '><summary>' . e($app) . '</summary><p>' . e($text) . '</p></details>';
    }
    if ($cal && str_starts_with($u['https'], 'http')) $out .= '<p class="f-help">' . e(__('Adresse für Apps ohne webcal:')) . ' <code>' . e($u['https']) . '</code></p>';
    return $out . '</div>';
};
?>
<header class="adm-head dt-head">
  <h1><span aria-hidden="true" class="dt-h1icon"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?> <span class="dt-h1sub">· <?= e(__('Abonnieren')) ?></span></h1>
</header>
<p class="adm-muted abo-lead"><?= e($cal
    ? __('Termine dieser Tabelle in Kalender-Apps abonnieren – Änderungen erscheinen dort von selbst. Öffentlich mit allen freigegebenen Terminen oder mit einem Abo-Token, wahlweise auch mit internen Terminen und Angaben.')
    : __('Kontakte dieser Tabelle in Adressbuch-Apps übernehmen. Öffentlich nur freigegebene Kontakte und Angaben; mit einem Abo-Token wahlweise auch interne.')) ?></p>

<?php if ($new):
    $nu = Subscriptions::urls($t, $new['token']);
    $nApps = $apps($new['token'], (string) $new['scope']);
    $dav = $nApps[0] ?? null; ?>
<dialog class="abo-dlg" id="abo-dlg" open aria-labelledby="abo-dlg-h" data-abo-dialog>
  <h2 id="abo-dlg-h"><?= e(__('Abo „{name}“ angelegt', ['name' => $new['name']])) ?></h2>
  <p class="abo-dlg__warn" role="alert"><?= icon('warning') ?> <?= e(__('Die Adresse ist nur jetzt sichtbar. Gespeichert wird nur ein Hash – geht sie verloren, widerrufen Sie das Abo und legen ein neues an.')) ?>
    <?= $new['scope'] === 'internal' ? e(__('Dieses Abo enthält interne Einträge und Angaben – nur an Personen weitergeben, die sie sehen dürfen.')) : '' ?></p>
  <div class="abo-dlg__grid">
    <div>
      <?= $cal ? $addr('abo-new-webcal', __('Abo-Adresse (webcal)'), $nu['webcal']) . $addr('abo-new-https', __('Abo-Adresse (https)'), $nu['https'])
          : $addr('abo-new-vcf', __('Kontakte-Datei (.vcf)'), $nu['https']) ?>
      <?php foreach ($nApps as $i => $a): ?>
      <div class="abo-app">
        <h3><?= e((string) $a['label']) ?></h3>
        <?= $addr('abo-app-' . $i . '-url', __('Server'), (string) ($a['url'] ?? '')) ?>
        <?= $addr('abo-app-' . $i . '-user', __('Benutzername'), (string) ($a['user'] ?? '')) ?>
        <?php if (!empty($a['password'])): ?><?= $addr('abo-app-' . $i . '-pw', __('Passwort'), (string) $a['password']) ?><?php endif; ?>
        <?php if (!empty($a['help'])): ?><p class="f-help"><?= e((string) $a['help']) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?= $qr($cal ? $nu['webcal'] : $nu['https'], __('QR-Code mit der Abo-Adresse')) ?>
  </div>
  <h3><?= e(__('So geht es in Ihrer App')) ?></h3>
  <?= $guide($nu, $dav) ?>
  <form method="dialog" class="abo-dlg__foot"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Fertig – Adresse übernommen')) ?></button></form>
</dialog>
<?php endif; ?>

<section class="adm-card abo-card" aria-labelledby="abo-pub-h">
  <h2 id="abo-pub-h"><?= e(__('Öffentlich abonnieren')) ?></h2>
  <?php if ($cal && Subscriptions::publicCalendar($t)): $pu = Subscriptions::urls($t); ?>
  <p class="adm-muted"><?= e(__('Alle veröffentlichten Termine mit den für „Kalender (ICS)“ freigegebenen Angaben – dieselbe Adresse wie „Kalender abonnieren“ auf der Website.')) ?></p>
  <div class="abo-dlg__grid">
    <div><?= $addr('abo-pub-webcal', __('Abo-Adresse (webcal)'), $pu['webcal']) ?><?= $addr('abo-pub-https', __('Abo-Adresse (https)'), $pu['https']) ?></div>
    <?= $qr($pu['webcal'], __('QR-Code mit der öffentlichen Abo-Adresse')) ?>
  </div>
  <?php elseif ($cal): ?>
  <p class="adm-muted"><?= e(__('Der öffentliche iCal-Feed ist für diese Tabelle ausgeschaltet.')) ?>
    <?php if (can('data.schema')): ?><a href="<?= e(url($base . '/schema')) ?>"><?= e(__('Unter „Felder & Einstellungen → Kalender“ einschalten')) ?></a><?php endif; ?></p>
  <?php elseif (Subscriptions::publicContacts($t)): $pu = Subscriptions::urls($t); ?>
  <p class="adm-muted"><?= e(__('Alle Kontakte mit „Auf der Website zeigen“ und nur deren freigegebene Angaben (Ziel „Visitenkarte“) als eine .vcf-Datei.')) ?></p>
  <div class="abo-dlg__grid">
    <div><?= $addr('abo-pub-vcf', __('Kontakte-Datei (.vcf)'), $pu['https']) ?></div>
    <?= $qr($pu['https'], __('QR-Code mit der Adresse der Kontakte-Datei')) ?>
  </div>
  <?php else: ?>
  <p class="adm-muted"><?= e(!$enabled ? __('Die öffentliche Kontakte-Datei gehört zur Funktion „Kalender und Kontakte per App abonnieren“, die für diese Website ausgeschaltet ist.')
      : __('Diese Tabelle ist nicht öffentlich (interne Liste oder geschützter Bereich) – Kontakte gibt es nur mit einem Abo-Token.')) ?></p>
  <?php endif; ?>
</section>

<section class="adm-card abo-card" aria-labelledby="abo-tok-h">
  <h2 id="abo-tok-h"><?= e(__('Abo-Tokens')) ?></h2>
  <?php if (!$enabled): ?>
  <p class="adm-muted"><?= e(__('Abos mit Token (auch mit internen Einträgen) sind für diese Website ausgeschaltet.')) ?>
    <?php if (\Core\Features::canManage()): ?><a href="<?= e(url('/admin/funktionen')) ?>"><?= e(__('Unter „Funktionen & Erweiterungen“ einschalten')) ?></a><?php endif; ?></p>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Jedes Abo bekommt eine eigene, geheime Adresse – benannt nach Gerät oder Person, jederzeit widerrufbar. Nur lesen: Änderungen aus Apps kommen nie zurück.')) ?></p>
  <div class="adm-card--flush abo-list">
    <table class="adm-table">
      <caption class="sr-only"><?= e(__('Abo-Tokens dieser Tabelle')) ?></caption>
      <thead><tr><th scope="col"><?= e(__('Name')) ?></th><th scope="col"><?= e(__('Umfang')) ?></th><th scope="col"><?= e(__('Läuft ab')) ?></th><th scope="col"><?= e(__('Zuletzt genutzt')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($tokens as $k): $exp = Subscriptions::expired($k); ?>
        <tr>
          <td><strong><?= e((string) $k['name']) ?></strong><br><span class="adm-muted"><code>abo-<?= e((string) $k['prefix']) ?>…</code> · <?= e(__('angelegt')) ?> <?= e($fmt->date((string) $k['created_at'])) ?><?= !empty($k['user_name']) || !empty($k['user_email']) ? ' · ' . e((string) ($k['user_name'] ?: $k['user_email'])) : '' ?></span></td>
          <td><span class="adm-badge<?= $k['scope'] === 'internal' ? ' adm-badge--warn' : '' ?>"><?= e($k['scope'] === 'internal' ? __('inkl. intern') : __('nur öffentlich')) ?></span></td>
          <td><?= $k['expires_at'] ? ($exp ? '<span class="adm-badge adm-badge--muted">' . e(__('abgelaufen')) . '</span> ' : '') . e($fmt->date((string) $k['expires_at'])) : '<span class="adm-muted">' . e(__('nie')) . '</span>' ?></td>
          <td class="adm-muted"><?= $k['last_used_at'] ? e($fmt->datetime((string) $k['last_used_at'])) : e(__('nie')) ?></td>
          <td class="adm-actions"><form method="post" action="<?= e(url($base . '/abonnieren/' . (int) $k['id'] . '/widerrufen')) ?>" data-confirm="<?= e(__('Abo „{name}“ widerrufen? Apps mit dieser Adresse erhalten danach nichts mehr.', ['name' => $k['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Widerrufen')) ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tokens): ?><tr><td colspan="5" class="adm-muted"><?= e(__('Noch keine Abo-Tokens.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <form method="post" action="<?= e(url($base . '/abonnieren')) ?>" class="abo-form">
    <?= csrf_field() ?>
    <h3><?= e(__('Neues Abo')) ?></h3>
    <div class="f<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="abo-name"><?= e(__('Name')) ?></label>
      <input id="abo-name" name="name" maxlength="<?= Subscriptions::NAME_MAX ?>" required value="<?= e((string) ($old['name'] ?? '')) ?>" placeholder="<?= e(__('z. B. iPhone Vorstand oder Outlook Geschäftsstelle')) ?>"<?= $inv('name') ?>><?= $fe('name') ?></div>
    <fieldset class="f--multi abo-scope"><legend><?= e(__('Umfang')) ?></legend>
      <label class="f-check"><input type="radio" name="scope" value="public"<?= ($old['scope'] ?? 'public') !== 'internal' ? ' checked' : '' ?>> <span><b><?= e(__('Nur öffentlich')) ?></b> – <?= e($cal ? __('veröffentlichte Termine, nur freigegebene Angaben (wie der öffentliche Feed)') : __('Kontakte mit „Auf der Website zeigen“, nur freigegebene Angaben')) ?></span></label>
      <label class="f-check"><input type="radio" name="scope" value="internal"<?= ($old['scope'] ?? '') === 'internal' ? ' checked' : '' ?>> <span><b><?= e(__('Inklusive interner Einträge und Angaben')) ?></b> – <?= e($cal ? __('auch Entwürfe und nicht freigegebene Angaben (Felder „Nur Website“ nie)') : __('auch interne Kontakte, private Nummern und nicht freigegebene Angaben (Felder „Nur Website“ nie)')) ?></span></label>
    </fieldset>
    <div class="f<?= isset($errors['expires']) ? ' f--error' : '' ?>"><label for="abo-exp"><?= e(__('Läuft ab am (optional)')) ?></label>
      <input id="abo-exp" type="date" name="expires" min="<?= e(date('Y-m-d')) ?>" value="<?= e((string) ($old['expires'] ?? '')) ?>"<?= $inv('expires') ?>><?= $fe('expires') ?>
      <p class="f-help"><?= e(__('Leer = gilt bis zum Widerruf. Das Abo endet auch, wenn Ihr Konto das Recht auf diese Tabelle verliert.')) ?></p></div>
    <p><button class="adm-btn adm-btn--primary"><?= e(__('Abo anlegen')) ?></button></p>
  </form>
  <?php endif; ?>
</section>

<section class="adm-card abo-card" aria-labelledby="abo-help-h">
  <h2 id="abo-help-h"><?= e(__('Anleitung je App')) ?></h2>
  <?php $gu = ($cal && Subscriptions::publicCalendar($t)) || (!$cal && Subscriptions::publicContacts($t)) ? Subscriptions::urls($t) : ['https' => ''];
  $gApps = $enabled ? $apps(null, 'public') : []; ?>
  <?php if (!$cal && $gApps): ?>
  <p class="adm-muted"><?= e(__('Mit einem Abo-Token gleichen Adressbuch-Apps die Kontakte laufend ab (CardDAV, nur lesen): Server {url}, Benutzername „abo“, Passwort = Abo-Token.', ['url' => (string) ($gApps[0]['url'] ?? '')])) ?></p>
  <?php endif; ?>
  <?= $guide($gu, $cal ? null : ($gApps[0] ?? null)) ?>
  <p class="f-help"><?= e(__('Ausführlich im Handbuch:')) ?> <a href="<?= e(url('/admin/hilfe#abonnieren')) ?>"><?= e(__('Kalender und Kontakte abonnieren')) ?></a></p>
</section>
