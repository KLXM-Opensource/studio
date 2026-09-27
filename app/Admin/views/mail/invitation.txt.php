<?php
/** E-Mail „Einladung“ (Text-Teil) – Core\Invites::mail(). Im Kit überschreibbar: kits/{kit}/templates/mail/invitation.txt.php. Reiner Text, kein Escaping nötig.
 * @var string $site  @var string $siteUrl  @var string $name  @var string $inviter  @var string $role  @var string $message  @var string $url
 * @var string $expires  @var bool $passkeys  @var bool $passwordless */
$blocks = [
    __('Sie wurden zu {site} eingeladen', ['site' => $site]),
    $name !== '' ? __('Guten Tag {name},', ['name' => $name]) : __('Guten Tag,'),
    __('{name} lädt Sie ein, als {role} an der Website {site} mitzuarbeiten.', ['name' => $inviter, 'role' => $role, 'site' => $site]),
];
if ($message !== '') $blocks[] = implode("\n", array_map(fn($l) => '> ' . $l, explode("\n", $message))) . "\n> – " . $inviter;
$blocks[] = __('Einladung annehmen') . ":\n" . $url;
$blocks[] = __('Die Einladung gilt bis {date}.', ['date' => $expires]);
$blocks[] = $passkeys && $passwordless
    ? __('Nach dem Klick geben Sie Ihren Namen ein und wählen, wie Sie sich künftig anmelden: am einfachsten mit einem Passkey – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN, ohne Passwort – oder mit einem eigenen Passwort (mindestens 12 Zeichen).')
    : ($passkeys ? __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen). Zusätzlich können Sie einen Passkey einrichten – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN.')
        : __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen).'));
$blocks[] = "—\n" . $site . ' · ' . $siteUrl . "\n" . __('Falls Sie diese E-Mail nicht erwartet haben, können Sie sie ignorieren.');
echo implode("\n\n", $blocks), "\n";
