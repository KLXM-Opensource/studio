<?php
/** E-Mails zu den Anmeldedaten (Text-Teil) – Core\EmailChange::compose(). Im Kit überschreibbar: kits/{kit}/templates/mail/account.txt.php.
 * Reiner Text, kein Escaping nötig. @var string $site  @var string $siteUrl  @var string $title  @var string $greeting  @var list<string> $paras
 * @var array<string,string> $facts  @var ?array{label:string, url:string, note:string} $cta  @var list<string> $after  @var string $foot */
$blocks = [$title, $greeting, ...$paras];
if ($facts) {
    $w = max(array_map('mb_strlen', array_keys($facts))) + 2;
    $blocks[] = implode("\n", array_map(fn($k, $v) => $k . ':' . str_repeat(' ', $w - mb_strlen($k) - 1) . $v, array_keys($facts), $facts));
}
if ($cta) {
    $blocks[] = $cta['label'] . ":\n" . $cta['url'];
    if (($cta['note'] ?? '') !== '') $blocks[] = $cta['note'];
}
foreach ($after as $p) $blocks[] = $p;
$blocks[] = "—\n" . $site . ' · ' . $siteUrl . "\n" . $foot;
echo implode("\n\n", $blocks), "\n";
