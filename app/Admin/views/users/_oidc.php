<?php
/**
 * Anmeldung über einen Identitätsanbieter (OpenID Connect, Core\Oidc, OidcController::save) – Formular #oidc.
 * Eingebunden in Benutzer → Sicherheit bzw. auf der Netzwerk-Website mehrerer Websites unter Netzwerk → Anmeldung & Domain.
 */
?>
<?php // Anmeldung über einen Identitätsanbieter (OpenID Connect, Core\Oidc) – Standard aus
$oc = \Core\Oidc\Providers::all()['main'] ?? (\Core\Oidc\Providers::DEFAULTS + ['id' => 'main', 'from_config' => false, 'role' => 'auto']);
$ocRaw = (array) (((array) app()->settings->get(\Core\Oidc\Providers::KEY, []))['main'] ?? []);
$ocLocked = !empty($oc['from_config']);
$ocNet = \Core\Network\Network::isNetworkSite() && \Core\Sites::multi();
$ocRole = (string) ($ocRaw['role'] ?? 'auto');
$ocDis = $ocLocked ? ' disabled' : ''; ?>
<form class="set-group us-oidc" id="oidc" method="post" action="<?= e(url('/admin/users/oidc')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <h2 class="set-group__title"><?= e(__('Anmeldung über einen Identitätsanbieter (OpenID Connect)')) ?></h2>
  <p class="set-group__note"><?= e(__('Personen melden sich zentral bei einem Anbieter an (z. B. dem Hub Ihrer Agentur) statt mit einem eigenen Passwort auf dieser Website. Lokale Konten mit Passwort und Passkey – auch Ihr Notfall-Zugang – bleiben immer nutzbar.')) ?></p>
  <?php if ($ocLocked): ?><p class="adm-flash adm-flash--info"><?= e(__('Festgelegt in der Konfiguration der Website (oidc) – hier nur zur Ansicht.')) ?></p><?php endif; ?>
  <div class="set-list">
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="oidc[enabled]" value="1"<?= !empty($oc['enabled']) ? ' checked' : '' ?><?= $ocDis ?>> <span><?= e(__('Anmeldung über den Anbieter anbieten')) ?></span></label></div>
    <div class="f"><label for="oc-name"><?= e(__('Name des Anbieters')) ?></label><input id="oc-name" name="oidc[name]" maxlength="60" value="<?= e((string) $oc['name']) ?>" placeholder="Agentur-Login"<?= $ocDis ?>></div>
    <div class="f"><label for="oc-label"><?= e(__('Beschriftung des Knopfs')) ?></label><input id="oc-label" name="oidc[label]" maxlength="60" value="<?= e((string) $oc['label']) ?>" placeholder="<?= e(__('Mit {name} anmelden', ['name' => $oc['name'] ?: 'Agentur-Login'])) ?>" aria-describedby="oc-label-h"<?= $ocDis ?>>
      <p class="f-help" id="oc-label-h"><?= e(__('Leer = „Mit {name} anmelden“.', ['name' => __('Name des Anbieters')])) ?></p></div>
    <?php $ocLogo = \Core\Oidc\Logo::status((string) $oc['issuer']); $ocMid = (int) ($oc['logo_media'] ?? 0); // Logo neben dem Anmelde-Link ?>
    <div class="f" role="group" aria-labelledby="oc-logo-l" aria-describedby="oc-logo-h"><span class="f-label" id="oc-logo-l"><?= e(__('Logo des Anbieters (optional)')) ?></span>
      <div class="media-field" data-accept="image"><input type="hidden" name="oidc[logo_media]" value="<?= $ocMid > 0 ? $ocMid : '' ?>"<?= $ocDis ?>>
        <div class="media-field-preview"><?= \Core\Fields::mediaPreview($ocMid > 0 ? $ocMid : null) ?></div>
        <?php if (!$ocLocked): ?><button type="button" class="btn btn--small" data-media-pick><?= e(__('Auswählen …')) ?></button> <button type="button" class="btn btn--small btn--ghost" data-media-clear><?= e(__('Entfernen')) ?></button><?php endif; ?></div>
      <p class="f-help" id="oc-logo-h"><?= e(__('Erscheint klein neben dem Anmelde-Link (SVG mit currentColor passt sich hell und dunkel an). Leer = Logo aus dem Discovery-Dokument (logo_uri), sonst ein neutrales Symbol.')) ?>
        <?php if ($ocLogo && empty($ocLogo['error'])): ?> <?= e(__('Logo des Anbieters ist lokal gespeichert.')) ?><?php elseif ($ocLogo): ?> <?= e(__('Logo des Anbieters nicht übernommen: {msg}', ['msg' => mb_strimwidth((string) $ocLogo['error'], 0, 120, '…')])) ?><?php endif; ?></p></div>
    <div class="f"><label for="oc-iss"><?= e(__('Issuer-Adresse')) ?></label><input id="oc-iss" name="oidc[issuer]" type="url" value="<?= e((string) $oc['issuer']) ?>" placeholder="https://anbieter.example.org/oidc" aria-describedby="oc-iss-h"<?= $ocDis ?>>
      <p class="f-help" id="oc-iss-h"><?= e(__('Die Einstellungen holt die Website von {path} (Discovery).', ['path' => '/.well-known/openid-configuration'])) ?></p></div>
    <div class="f"><label for="oc-cid"><?= e(__('Client-ID')) ?></label><input id="oc-cid" name="oidc[client_id]" maxlength="191" value="<?= e((string) $oc['client_id']) ?>"<?= $ocDis ?>></div>
    <div class="f"><label for="oc-sec"><?= e(__('Client-Geheimnis')) ?></label><input id="oc-sec" name="oidc[client_secret]" type="password" autocomplete="new-password" placeholder="<?= e(($oc['client_secret'] ?? '') !== '' ? __('gespeichert – leer lassen, um es zu behalten') : '') ?>" aria-describedby="oc-sec-h"<?= $ocDis ?>>
      <p class="f-help" id="oc-sec-h"><?= e(__('Wird verschlüsselt gespeichert und nie wieder angezeigt. „-“ löscht es.')) ?></p></div>
    <div class="f f--inline"><label for="oc-auth"><?= e(__('Übermittlung des Geheimnisses')) ?></label>
      <select id="oc-auth" name="oidc[auth]"<?= $ocDis ?>><option value="client_secret_basic"<?= $oc['auth'] !== 'client_secret_post' ? ' selected' : '' ?>>client_secret_basic</option><option value="client_secret_post"<?= $oc['auth'] === 'client_secret_post' ? ' selected' : '' ?>>client_secret_post</option></select></div>
    <div class="f f--inline"><label for="oc-role"><?= e(__('Rolle der Konten')) ?></label>
      <select id="oc-role" name="oidc[role]" aria-describedby="oc-role-h"<?= $ocDis ?>>
        <option value="auto"<?= $ocRole === 'auto' ? ' selected' : '' ?>><?= e($ocNet ? __('automatisch: Netzwerk-Administration') : __('automatisch: Administration')) ?></option>
        <?php if ($ocNet): ?><option value="network"<?= $ocRole === 'network' ? ' selected' : '' ?>><?= e(__('Netzwerk-Administration (alle Websites)')) ?></option><?php endif; ?>
        <?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= $ocRole === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?>
      </select>
      <p class="f-help" id="oc-role-h"><?= e($ocNet ? __('Netzwerk-Administration: Die Anmeldung hier gilt per Einmal-Anmeldung für alle Websites des Netzwerks; die anderen Websites zeigen den Knopf ebenfalls.') : __('Gilt für neu angelegte Konten; danach können Sie die Rolle hier ändern – außer der Anbieter liefert den Rollen-Claim (dann folgt die Rolle ihm).')) ?></p></div>
    <div class="f"><label for="oc-rc"><?= e(__('Rolle aus dem Claim')) ?></label><input id="oc-rc" name="oidc[role_claim]" maxlength="60" value="<?= e((string) ($oc['role_claim'] ?? 'roles')) ?>" placeholder="roles" aria-describedby="oc-rc-h"<?= $ocDis ?>>
      <p class="f-help" id="oc-rc-h"><?= e(__('Steht der Claim im Token, entscheidet er bei jeder Anmeldung: „admin“ → Rolle oben, „redaktion“ → Redaktionsrolle, andere Werte werden abgewiesen. Leer = nicht auswerten.')) ?></p></div>
    <div class="f f--inline"><label for="oc-er"><?= e(__('Redaktionsrolle')) ?></label>
      <select id="oc-er" name="oidc[editor_role]"<?= $ocDis ?>>
        <?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= ($oc['editor_role'] ?? 'editor') === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="f"><label for="oc-acc"><?= e(__('Kennzeichnung der Konten')) ?></label><input id="oc-acc" name="oidc[account_label]" maxlength="40" value="<?= e((string) $oc['account_label']) ?>" placeholder="<?= e(__('Agentur-Konto')) ?>"<?= $ocDis ?>></div>
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="oidc[jit]" value="1" aria-describedby="oc-jit-h"<?= !empty($oc['jit']) ? ' checked' : '' ?><?= $ocDis ?>> <span><?= e(__('Konto bei der ersten Anmeldung anlegen')) ?></span></label>
      <p class="f-help" id="oc-jit-h"><?= e(__('Aus: Nur vorab freigegebene Konten (php bin/console oidc:allow <email>) können sich anmelden. Bestehende Konten mit eigenem Passwort werden nie übernommen.')) ?></p></div>
    <div class="f f--inline"><label for="oc-mfa"><?= e(__('Zweiter Faktor')) ?></label>
      <select id="oc-mfa" name="oidc[mfa]"<?= $ocDis ?>><option value="amr"<?= $oc['mfa'] !== 'trust' ? ' selected' : '' ?>><?= e(__('muss im Token belegt sein (amr)')) ?></option><option value="trust"<?= $oc['mfa'] === 'trust' ? ' selected' : '' ?>><?= e(__('der Anbieter erzwingt ihn selbst')) ?></option></select></div>
    <div class="f"><label for="oc-claim"><?= e(__('Nur mit Claim (optional)')) ?></label><input id="oc-claim" name="oidc[claim]" maxlength="60" value="<?= e((string) $oc['claim']) ?>" placeholder="roles"<?= $ocDis ?>></div>
    <div class="f"><label for="oc-cv"><?= e(__('Erlaubte Werte des Claims')) ?></label><input id="oc-cv" name="oidc[claim_values]" value="<?= e(implode(', ', (array) $oc['claim_values'])) ?>" placeholder="admin, redaktion" aria-describedby="oc-cv-h"<?= $ocDis ?>>
      <p class="f-help" id="oc-cv-h"><?= e(__('Durch Komma getrennt. Leer = der Claim muss nur vorhanden sein.')) ?></p></div>
    <div class="f"><label for="oc-dom"><?= e(__('Erlaubte E-Mail-Domains (optional)')) ?></label><input id="oc-dom" name="oidc[domains]" value="<?= e(implode(', ', (array) $oc['domains'])) ?>" placeholder="example.org"<?= $ocDis ?>></div>
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="oidc[logout]" value="1"<?= !empty($oc['logout']) ? ' checked' : '' ?><?= $ocDis ?>> <span><?= e(__('Beim Abmelden auch beim Anbieter abmelden')) ?></span></label></div>
  </div>
  <h3 class="set-group__title set-group__title--sub"><?= e(__('Beim Anbieter eintragen')) ?></h3>
  <dl class="acc-facts">
    <div><dt><?= e(__('Redirect-URI')) ?></dt><dd><code><?= e(\Core\Oidc\Providers::redirectUri()) ?></code></dd></div>
    <div><dt><?= e(__('Einstieg für die Anmeldung (initiate_login_uri)')) ?></dt><dd><code><?= e(\Core\Oidc\Providers::initiateUri()) ?></code></dd></div>
    <div><dt><?= e(__('Nach dem Abmelden')) ?></dt><dd><code><?= e(admin_url_abs('/admin/login')) ?></code></dd></div>
  </dl>
  <?php if (!$ocLocked): ?><div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div><?php endif; ?>
</form>
