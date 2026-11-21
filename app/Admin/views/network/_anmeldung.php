<?php
/**
 * Netzwerk → Anmeldung & Domain (Teil von network/index.php): Anmelde-Richtlinie der Netzwerk-Konten (Core\Mfa, sys.net_auth),
 * Netzwerk-Domain (Core\NetworkHost) und – mit mehreren Websites – Adresse der Verwaltung (Core\AdminPath, gilt für alle Websites)
 * sowie der Agentur-Zugang per OpenID Connect der Netzwerk-Website (users/_oidc.php, Rolle network).
 */
$np = \Core\Mfa::netPolicy();
$multi = \Core\Network\Area::active();
?>
  <section class="adm-card" id="net-auth" aria-labelledby="net-auth-h">
    <h2 id="net-auth-h"><?= e(__('Anmelde-Richtlinie der Netzwerk-Konten')) ?></h2>
    <form method="post" action="<?= e(url('/admin/network/auth-policy')) ?>">
      <?= csrf_field() ?>
      <fieldset class="us-2fa__set"><legend><?= e(__('Erlaubte Verfahren für Netzwerk-Konten')) ?></legend>
        <label class="f-check"><input type="checkbox" name="totp" value="1"<?= $np['totp'] ? ' checked' : '' ?>> <span><?= e(__('Authenticator-App (6-stelliger Code)')) ?></span></label>
        <label class="f-check"><input type="checkbox" name="passkey" value="1"<?= $np['passkey'] ? ' checked' : '' ?>> <span><?= e(__('Passkeys')) ?></span></label>
        <label class="f-check"><input type="checkbox" name="passwordless" value="1"<?= $np['passwordless'] ? ' checked' : '' ?>> <span><?= e(__('Anmeldung ohne Passwort mit Passkey')) ?></span></label>
        <label class="f-check"><input type="checkbox" name="passkey_only" value="1"<?= $np['passkey_only'] ? ' checked' : '' ?>> <span><?= e(__('Nur Passkey als zweiter Faktor (phishing-resistent)')) ?></span></label>
      </fieldset>
      <p class="f-help"><?= e(__('Netzwerk-Konten melden sich immer mit zweitem Faktor an (App oder Passkey). Passkeys gelten je Domain – für die direkte Anmeldung auf einer anderen Website dort einen eigenen Passkey hinzufügen; „Öffnen“ aus dieser Übersicht braucht keinen.')) ?></p>
      <button class="adm-btn adm-btn--small adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button>
    </form>
  </section>
  <?php $nhSet = \Core\NetworkHost::get(); // Netzwerk-Domain (Core\NetworkHost): zentrale Adresse für Verwaltung und Anmeldung ?>
  <section class="adm-card" id="net-host" aria-labelledby="net-host-h">
    <h2 id="net-host-h"><?= e(__('Netzwerk-Domain')) ?><?= $nhSet !== null ? ': ' . e($nhSet) : '' ?></h2>
    <form id="netnetworkhost" method="post" action="<?= e(url('/admin/system/network-host')) ?>"><?= csrf_field() ?></form>
    <?php $nhForm = 'netnetworkhost'; $nhBack = 'network'; include dirname(__DIR__) . '/system/_network_host.php'; ?>
  </section>
  <?php if ($multi): // Adresse der Verwaltung gilt für alle Websites (Core\AdminPath) ?>
  <section class="adm-card" id="verwaltungsadresse" aria-labelledby="ap-h">
    <?php if (\Core\AdminPath::canManage()): // Sprungmarke #adminpath (alte Adresse) landet hier ?><form id="adminpath" method="post" action="<?= e(url('/admin/system/admin-path')) ?>"><?= csrf_field() ?></form><?php endif; ?>
    <?php ob_start(); include dirname(__DIR__) . '/system/_adminpath.php'; echo str_replace('<h2>', '<h2 id="ap-h">', (string) ob_get_clean()); ?>
  </section>
  <section class="adm-card" id="agentur-zugang" aria-labelledby="oidc-h">
    <p class="adm-muted"><?= e(__('Agentur-Zugang: Mit der Rolle „Netzwerk-Administration“ werden Konten dieses Anbieters Netzwerk-Konten und verwalten alle Websites. Jede Website kann außerdem einen eigenen Anbieter für ihre Redaktion einrichten (Benutzer & Rollen → Sicherheit).')) ?></p>
    <?php ob_start(); include dirname(__DIR__) . '/users/_oidc.php'; echo str_replace('<h2 class="set-group__title">', '<h2 class="set-group__title" id="oidc-h">', (string) ob_get_clean()); ?>
  </section>
  <?php endif; ?>
