<?php
/** Benutzer und Rollen. @var array $users  @var array $errors  @var array $old  @var array $user */
use Core\Permissions;

$roles = Permissions::roles();
$catalog = Permissions::catalog();
$labels = array_merge(...array_values($catalog));
$count = array_count_values(array_column($users, 'role'));
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
// Einladungen (Core\Invites): offene Einladungen, Rollen zur Auswahl, Formularwerte nach Fehlern
$invites ??= [];
$inviteRoles ??= [];
$inv = !empty($old['_invite']) ? $old : [];
$createOld = $inv ? [] : $old;
$locales = \Core\I18n::available();
$defLocale = (string) (setting('sys.admin_locale') ?: \Core\I18n::SOURCE);
$section ??= '';
$mfaMissing ??= [];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1><?= e(__('Benutzer & Rollen')) ?></h1>
    <p class="adm-muted"><?= e(__('Jede Person bekommt ein eigenes Konto. Was sie darf, bestimmt ihre Rolle – Rollen lassen sich frei zusammenstellen.')) ?></p></div>
</header>
<?php
// Anfangsbuchstaben für den runden Platzhalter (wie Kontaktbilder in den macOS-Einstellungen)
$initials = function (array $u): string {
    $n = trim((string) ($u['name'] ?? ''));
    if ($n === '') return mb_strtoupper(mb_substr((string) $u['email'], 0, 1));
    $parts = preg_split('~\s+~u', $n) ?: [$n];
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
};
$lastLogin = fn(array $u) => $u['last_login'] ? __('Letzte Anmeldung: {date}', ['date' => date('d.m.Y H:i', strtotime($u['last_login']))]) : __('Noch nie angemeldet');
?>
<?php
// Unterseiten wie die Bereiche der Grundeinstellungen – hier je Bereich eine eigene Adresse (UserController::section)
$secs = ['' => [__('Übersicht'), 'squares-four'], 'personen' => [__('Personen'), 'user'], 'einladen' => [__('Einladen & Anlegen'), 'paper-plane-tilt'],
    'rollen' => [__('Rollen'), 'key'], 'sicherheit' => [__('Anmeldung & Sicherheit'), 'shield-check']];
$secUrl = fn(string $k) => url('/admin/users' . ($k === '' ? '' : '/' . $k));
// Alte Sprungmarken (/admin/users#rollen …) auf die Unterseite umleiten
$oldHash = ['rollen' => 'rollen', 'zwei-faktor' => 'sicherheit', 'einladungen' => 'einladen', 'einladen' => 'einladen', 'einladung-link' => 'einladen', 'benutzer' => 'personen'];
?>
<div class="adm-tabs-form--side us-shell" data-secnav<?= $section === '' ? ' data-old-hash="' . e(json_encode(array_map($secUrl, $oldHash), JSON_UNESCAPED_SLASHES)) . '"' : '' ?>>
  <nav class="adm-tabs adm-secnav" aria-label="<?= e(__('Bereiche')) ?>">
    <?php foreach ($secs as $k => [$label, $ico]): ?>
    <a href="<?= e($secUrl($k)) ?>"<?= $section === $k ? ' aria-current="page"' : '' ?>><span class="adm-tabs__ico" aria-hidden="true"><?= icon($ico) ?></span><span class="adm-tabs__label"><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="us-main">
    <h2 class="us-main__title"><?= e($secs[$section][0] ?? '') ?></h2>
<?php if ($section === 'einladen'): ?>
<?php if (!empty($inviteLink)): // E-Mail nicht zugestellt: Link einmal anzeigen (nur jetzt sichtbar, gespeichert ist nur ein Hash) ?>
<section class="adm-card adm-card--secret inv-link" id="einladung-link" role="alert">
  <h2><?= e(__('Einladung angelegt – E-Mail nicht zugestellt')) ?></h2>
  <p><?= e($inviteLink['error'] ? __('Die E-Mail an {email} konnte nicht gesendet werden: {error}', ['email' => $inviteLink['email'], 'error' => $inviteLink['error']])
    : __('Die E-Mail an {email} wurde nicht zugestellt (Testumgebung oder Versand deaktiviert – nur protokolliert bzw. umgeleitet).', ['email' => $inviteLink['email']])) ?></p>
  <p><?= e(__('Geben Sie den Link selbst weiter, z. B. per Messenger. Er ist nur jetzt sichtbar, gilt einmal und bis zum Ablauf der Einladung.')) ?>
    <?php if (can('system.manage')): ?><a href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('E-Mail-Versand einrichten')) ?> →</a><?php endif; ?></p>
  <div class="adm-secret"><code id="inv-url"><?= e($inviteLink['url']) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#inv-url"><?= e(__('Kopieren')) ?></button></div>
</section>
<?php endif; ?>
<?php endif; ?>
    <div class="set-page us-page us-page--<?= e($section === '' ? 'uebersicht' : $section) ?>">
    <?php include __DIR__ . '/users/_' . ($section === '' ? 'uebersicht' : $section) . '.php'; ?>
    </div>
  </div>
</div>
