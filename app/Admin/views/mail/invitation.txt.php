<?php
/** E-Mail „Einladung“ (Text-Teil) – Core\Invites::mail(). Im Kit überschreibbar: kits/{kit}/templates/mail/invitation.txt.php. Reiner Text, kein Escaping nötig.
 * @var string $site  @var string $siteUrl  @var string $name  @var string $inviter  @var string $role  @var string $message  @var string $url
 * @var string $expires  @var bool $passkeys  @var bool $passwordless  @var bool $network  @var int $sites */
$network ??= false;
$sites ??= 0;
$blocks = [
    $network ? __('Einladung zur Netzwerk-Administration') : __('Sie wurden zu {site} eingeladen', ['site' => $site]),
    $name !== '' ? __('Guten Tag {name},', ['name' => $name]) : __('Guten Tag,'),
    $network ? __('{name} lädt Sie ein, als {role} alle Websites dieser Installation zu betreuen.', ['name' => $inviter, 'role' => $role])
        : __('{name} lädt Sie ein, als {role} an der Website {site} mitzuarbeiten.', ['name' => $inviter, 'role' => $role, 'site' => $site]),
];
if ($network) {
    $blocks[] = '!! ' . __('Netzwerk-Konto: Zugriff auf ALLE Websites') . "\n"
        . ($sites > 1 ? __('Mit diesem Konto verwalten Sie alle {n} Websites dieser Installation mit allen Inhalten, Daten und Konten.', ['n' => $sites])
            : __('Mit diesem Konto verwalten Sie alle Websites dieser Installation mit allen Inhalten, Daten und Konten.')) . "\n"
        . __('Die Zwei-Faktor-Anmeldung ist Pflicht: Sie richten sie direkt beim Annehmen ein.');
}
if ($message !== '') $blocks[] = implode("\n", array_map(fn($l) => '> ' . $l, explode("\n", $message))) . "\n> – " . $inviter;
$blocks[] = __('Einladung annehmen') . ":\n" . $url;
$blocks[] = __('Die Einladung gilt bis {date}.', ['date' => $expires]);
$blocks[] = $network
    ? ($passkeys ? __('Nach dem Klick geben Sie Ihren Namen ein und legen einen Passkey und/oder ein Passwort fest (mindestens 12 Zeichen). Direkt danach richten Sie die Zwei-Faktor-Anmeldung ein – ein Passkey erfüllt sie bereits, sonst die Authenticator-App. Danach öffnet sich die Netzwerk-Übersicht.')
        : __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen). Direkt danach richten Sie die Zwei-Faktor-Anmeldung mit einer Authenticator-App ein. Danach öffnet sich die Netzwerk-Übersicht.'))
    : ($passkeys && $passwordless
        ? __('Nach dem Klick geben Sie Ihren Namen ein und wählen, wie Sie sich künftig anmelden: am einfachsten mit einem Passkey – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN, ohne Passwort – oder mit einem eigenen Passwort (mindestens 12 Zeichen).')
        : ($passkeys ? __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen). Zusätzlich können Sie einen Passkey einrichten – bestätigt per Fingerabdruck, Gesicht oder Geräte-PIN.')
            : __('Nach dem Klick geben Sie Ihren Namen ein und legen ein Passwort fest (mindestens 12 Zeichen).')));
$blocks[] = "—\n" . $site . ' · ' . $siteUrl . "\n" . __('Falls Sie diese E-Mail nicht erwartet haben, können Sie sie ignorieren.');
echo implode("\n\n", $blocks), "\n";
