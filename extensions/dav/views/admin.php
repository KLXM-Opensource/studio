<?php
/**
 * Erweiterung „dav“: App-Passwörter und Tabellen für CalDAV/CardDAV.
 * @var array $user  @var array $passwords  @var ?string $newPassword  @var array $tables  @var array $errors
 */
use Core\Data\Calendar;
use MyCms\Dav\Dav;

$base = absolute_url('/dav/');
$host = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
$email = strtolower((string) $user['email']);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Erweiterung')) ?> · CalDAV/CardDAV</p><h1><?= e(__('Kalender & Kontakte in Apps')) ?></h1>
    <p class="adm-muted"><?= e(__('Termine und Kontakte aus Datentabellen mit Apple Kalender/Kontakte, Thunderbird, DAVx⁵ (Android) und anderen Apps abgleichen. Angemeldet wird mit Ihrer E-Mail-Adresse und einem App-Passwort – nie mit Ihrem Login-Passwort.')) ?></p></div>
</header>

<?php if ($newPassword): ?>
<section class="adm-card adm-card--secret" role="alert">
  <h2><?= e(__('Neues App-Passwort – nur jetzt sichtbar')) ?></h2>
  <p><?= e(__('Tragen Sie es jetzt in der App ein. Gespeichert wird nur ein Hash; verloren gegangene Passwörter widerrufen Sie einfach und legen ein neues an.')) ?></p>
  <div class="adm-secret"><code id="dav-pw"><?= e($newPassword) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#dav-pw"><?= e(__('Kopieren')) ?></button></div>
</section>
<?php endif; ?>

<div class="adm-grid2 adm-grid2--wide">
  <section class="adm-card adm-card--flush">
    <table class="adm-table">
      <caption class="sr-only"><?= e(__('Ihre App-Passwörter')) ?></caption>
      <thead><tr><th scope="col"><?= e(__('Name')) ?></th><th scope="col"><?= e(__('Berechtigung')) ?></th><th scope="col"><?= e(__('Zuletzt genutzt')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($passwords as $p): ?>
        <tr>
          <td><strong><?= e($p['name']) ?></strong><br><span class="adm-muted"><code><?= e($p['prefix']) ?>…</code> · <?= e(__('angelegt')) ?> <?= e(\Core\Format::admin()->date((string) $p['created_at'])) ?></span></td>
          <td><span class="adm-badge<?= $p['scope'] === 'write' ? ' adm-badge--warn' : '' ?>"><?= e(__(Dav::SCOPES[$p['scope']] ?? $p['scope'])) ?></span></td>
          <td class="adm-muted"><?= $p['last_used_at'] ? e(\Core\Format::admin()->datetime((string) $p['last_used_at'])) : e(__('nie')) ?></td>
          <td class="adm-actions"><form method="post" action="<?= e(url('/admin/dav/passwords/' . (int) $p['id'] . '/delete')) ?>" data-confirm="<?= e(__('App-Passwort „{name}“ widerrufen? Die App kann sich danach nicht mehr anmelden.', ['name' => $p['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Widerrufen')) ?></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$passwords): ?><tr><td colspan="4" class="adm-muted"><?= e(__('Noch keine App-Passwörter.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="adm-card">
    <h2><?= e(__('Neues App-Passwort')) ?></h2>
    <form method="post" action="<?= e(url('/admin/dav/passwords')) ?>">
      <?= csrf_field() ?>
      <div class="f<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="dav-name"><?= e(__('Gerät / App')) ?></label><input id="dav-name" name="name" maxlength="100" placeholder="<?= e(__('z. B. iPhone oder Thunderbird Büro')) ?>" required<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="dav-name-e"' : '' ?>>
        <?php if (isset($errors['name'])): ?><p class="f-error" id="dav-name-e"><?= e($errors['name']) ?></p><?php endif; ?></div>
      <fieldset class="f--multi dav-scope"><legend><?= e(__('Berechtigung')) ?></legend>
        <?php foreach (Dav::SCOPES as $k => $l): ?><label class="f-check"><input type="radio" name="scope" value="<?= e($k) ?>"<?= $k === 'write' ? ' checked' : '' ?>> <span><?= e(__($l)) ?></span></label><?php endforeach; ?>
      </fieldset>
      <p><button class="adm-btn adm-btn--primary"><?= e(__('App-Passwort erzeugen')) ?></button></p>
    </form>
  </section>
</div>

<section class="adm-card">
  <h2><?= e(__('Verbinden')) ?></h2>
  <dl class="md-info">
    <dt><?= e(__('Server')) ?></dt><dd><code><?= e($base) ?></code></dd>
    <dt><?= e(__('Benutzername')) ?></dt><dd><code><?= e($email) ?></code></dd>
    <dt><?= e(__('Passwort')) ?></dt><dd><?= e(__('ein App-Passwort von dieser Seite')) ?></dd>
  </dl>
  <ul>
    <li><b>Apple (macOS/iOS)</b>: <?= e(__('Einstellungen → Kalender bzw. Kontakte → Accounts → Account hinzufügen → Andere → CalDAV- bzw. CardDAV-Account → „Manuell“; Server:')) ?> <code><?= e($host) ?></code></li>
    <li><b>Thunderbird</b>: <?= e(__('Neuer Kalender → Im Netzwerk → Adresse wie oben; Adressbuch: Neues CardDAV-Adressbuch → Adresse wie oben.')) ?></li>
    <li><b>DAVx⁵ (Android)</b>: <?= e(__('Konto hinzufügen → „Mit URL und Benutzername anmelden“ → Adresse wie oben.')) ?></li>
  </ul>
  <p class="adm-muted"><?= e(__('Sie sehen nur Tabellen, die Ihre Rolle bearbeiten darf. Mit „Nur lesen“ sind Kalender und Adressbücher schreibgeschützt.')) ?></p>
</section>

<?php if ($tables): ?>
<section class="adm-card" id="tabellen">
  <h2><?= e(__('Tabellen')) ?></h2>
  <p class="adm-muted"><?= e(__('Kalender: alle Tabellen mit „Als Kalender nutzen“. Adressbuch: beliebige Tabelle; nicht zugeordnete Felder werden als X-MYCMS-{FELD} übertragen.')) ?></p>
  <form method="post" action="<?= e(url('/admin/dav/tables')) ?>" class="dav-tables">
    <?= csrf_field() ?>
    <?php foreach ($tables as $t): $s = Dav::tableSettings($t); $isCal = Calendar::enabled($t); $n = 't[' . e($t['handle']) . ']'; $map = $s['map'] ?: Dav::guessMap($t); ?>
    <fieldset class="adm-inline-box">
      <legend><span aria-hidden="true"><?= e($t['icon']) ?></span> <?= e($t['name']) ?> <small class="adm-muted">(<?= e($t['handle']) ?>)</small></legend>
      <input type="hidden" name="<?= $n ?>[present]" value="1">
      <?php if ($isCal): ?>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[caldav]" value="1"<?= $s['caldav'] ? ' checked' : '' ?>> <span><?= e(__('Als Kalender (CalDAV) bereitstellen')) ?></span></label>
      <?php else: ?>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[addressbook]" value="1"<?= $s['addressbook'] ? ' checked' : '' ?>> <span><?= e(__('Als Adressbuch (CardDAV) bereitstellen')) ?></span></label>
      <?php endif; ?>
      <div class="adm-grid2">
        <div class="f"><label for="dav-st-<?= e($t['handle']) ?>"><?= e(__('Neue Einträge aus Apps')) ?></label><select id="dav-st-<?= e($t['handle']) ?>" name="<?= $n ?>[status]">
          <option value="published"<?= $s['status'] === 'published' ? ' selected' : '' ?>><?= e(__('sofort online')) ?></option>
          <option value="draft"<?= $s['status'] === 'draft' ? ' selected' : '' ?>><?= e(__('als Entwurf')) ?></option></select></div>
        <div class="f"><label for="dav-del-<?= e($t['handle']) ?>"><?= e(__('In der App gelöscht')) ?></label><select id="dav-del-<?= e($t['handle']) ?>" name="<?= $n ?>[on_delete]">
          <option value="draft"<?= $s['on_delete'] === 'draft' ? ' selected' : '' ?>><?= e(__('→ Entwurf (sicher, wiederherstellbar)')) ?></option>
          <option value="delete"<?= $s['on_delete'] === 'delete' ? ' selected' : '' ?>><?= e(__('→ endgültig löschen (Recht „Einträge löschen“)')) ?></option></select></div>
      </div>
      <?php if (!$isCal): ?>
      <details<?= $s['addressbook'] ? ' open' : '' ?>><summary><?= e(__('Zuordnung der vCard-Felder')) ?></summary>
        <div class="adm-grid2">
        <?php foreach ([
            'fn' => __('Name (ganzer Name, FN)'), 'n_family' => __('Nachname'), 'n_given' => __('Vorname'), 'org' => __('Firma (ORG)'), 'title' => __('Position (TITLE)'),
            'adr_street' => __('Straße'), 'adr_zip' => __('PLZ'), 'adr_city' => __('Ort'), 'adr_country' => __('Land'), 'url' => __('Website (URL)'),
            'note' => __('Notiz (NOTE)'), 'bday' => __('Geburtstag (BDAY)'), 'photo' => __('Foto (PHOTO)'), 'categories' => __('Gruppen (CATEGORIES)'),
        ] as $k => $label): $id = 'dav-m-' . $t['handle'] . '-' . $k; ?>
          <div class="f"><label for="<?= e($id) ?>"><?= e($label) ?></label><select id="<?= e($id) ?>" name="<?= $n ?>[map][<?= e($k) ?>]"><option value="">–</option>
            <?php foreach ($t['fields'] as $f): if (!in_array($f['type'], Dav::CARD_MAP[$k], true)) continue; ?>
            <option value="<?= e($f['name']) ?>"<?= ($map[$k] ?? '') === $f['name'] ? ' selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?></select></div>
        <?php endforeach; ?>
        </div>
        <p class="f-help"><?= e(__('E-Mail und Telefon: alle Felder dieser Typen (mobil/handy → Mobil, fax → Fax, privat → privat, sonst dienstlich).')) ?></p>
      </details>
      <?php endif; ?>
    </fieldset>
    <?php endforeach; ?>
    <button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
  </form>
</section>
<?php endif; ?>
