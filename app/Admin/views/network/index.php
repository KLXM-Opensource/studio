<?php
/**
 * Netzwerk: Bereichsleiste (Websites · Administratoren · E-Mail · Geteilt · Protokoll) – je Bereich eine eigene Adresse
 * (/admin/network/{bereich}, NetworkController::SECTIONS), Inhalt in network/_{bereich}.php. „Neue Website“ als Knopf oben.
 * Websites: „Braucht Aufmerksamkeit“ (alle Hinweise als Liste) und ruhige Karten (Core\Network\Stats, SiteIcon); Stile in
 * resources/css/network.css, Filter/Suche/Ansicht in resources/js/_network.js.
 * @var string $section  ''|administratoren|email|neu|geteilt|protokoll
 * @var array $stats  @var array $warn  @var array $accounts  @var array $log  @var array $shared  @var array $themes  @var array $me  @var ?array $newSite
 * @var array $invites offene Einladungen als Netzwerk-Administration (Core\Invites::open(true))  @var ?array $inviteLink  @var ?array $inviteOld
 */
$invites ??= [];
$inviteOld ??= null;
$invErr = (array) ($inviteOld['errors'] ?? []);
$title = __('Netzwerk');
$size = fn(?int $b): string => $b === null ? '–' : \Core\Format::admin()->bytes($b);
$ts = fn($t): int => is_int($t) ? $t : (int) strtotime((string) $t);
$when = fn($t) => $t ? \Core\Format::admin()->datetime($ts($t)) : '–';
// Relative Zeitangabe („vor 3 Std.“) mit genauem Datum als <time> + title
$ago = function ($t) use ($ts, $when): string {
    if (!$t) return '–';
    $d = time() - $ts($t);
    $txt = match (true) {
        $d < 90 => __('gerade eben'),
        $d < 3600 => __('vor {n} Min.', ['n' => (int) round($d / 60)]),
        $d < 86400 => __('vor {n} Std.', ['n' => (int) round($d / 3600)]),
        $d < 2 * 86400 => __('gestern'),
        $d < 45 * 86400 => __('vor {n} Tagen', ['n' => (int) floor($d / 86400)]),
        default => \Core\Format::admin()->date($ts($t)),
    };
    return '<time datetime="' . e(date('c', $ts($t))) . '" title="' . e($when($t)) . '">' . e($txt) . '</time>';
};
$envLabel = ['production' => __('Live'), 'staging' => __('Staging'), 'development' => __('Entwicklung')];
$nWarn = count(array_filter($warn));
$nMaint = count(array_filter($stats, fn($s) => !empty($s['maintenance'])));
$nOff = count(array_filter($stats, fn($s) => ($s['environment'] ?? 'production') !== 'production' || !empty($s['noindex'])));
$inbox = array_sum(array_map(fn($s) => (int) ($s['inbox_new'] ?? 0), $stats));
$review = array_sum(array_map(fn($s) => (int) ($s['review_pending'] ?? 0), $stats));
$pools = array_column($shared['pools'], null, 'key');
$poolBytes = array_sum(array_map(fn($p) => (int) ($p['size'] ?? 0), $pools));
$bytes = array_sum(array_map(fn($s) => (int) ($s['db_size'] ?? 0) + (int) ($s['media_size'] ?? 0), $stats)) + $poolBytes;
$oldest = min(array_map(fn($s) => (int) ($s['at'] ?? time()), $stats) ?: [time()]);
$actionUrl = fn(string $k, string $a) => url('/admin/network/site/' . $k . '/' . $a);
// Passkeys des Netzwerk-Kontos je Domain (Core\HostHandover) – Hinweis an Domains ohne Passkey
$me = app()->auth->user() ?? [];
$pkHosts = \Core\HostHandover::passkeyHosts($me);

$section ??= '';
$nm = \Core\Mail\Accounts::network();
$secs = ['' => [__('Websites'), 'globe', (string) count($stats)], 'administratoren' => [__('Administratoren'), 'users-three', (string) count($accounts)],
    'email' => [__('E-Mail'), 'envelope-simple', $nm && \Core\Mail\Accounts::ready($nm) ? '' : '!'], 'geteilt' => [__('Geteilt'), 'share-network', ''],
    'speicher' => [__('Speicher'), 'hard-drives', ''], 'protokoll' => [__('Protokoll'), 'clock-counter-clockwise', '']];
$secUrl = fn(string $k) => url('/admin/network' . ($k === '' ? '' : '/' . $k));
// Alte Sprungmarken (/admin/network#konten …) auf die Bereiche umleiten (resources/js/_network.js)
$oldHash = ['konten' => 'administratoren', 'net-invite' => 'administratoren', 'net-auth' => 'administratoren', 'email' => 'email', 'neu' => 'neu', 'geteilt' => 'geteilt', 'speicher' => 'speicher', 'protokoll' => 'protokoll'];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Netzwerk-Administration')) ?></p><h1><?= e(match ($section) { '' => __('Alle Websites'), 'neu' => __('Neue Website'), 'email' => __('E-Mail-Konto des Netzwerks'), default => $secs[$section][0] }) ?></h1>
    <?php if ($section === ''): ?><p class="adm-muted"><?= e(__('„Öffnen“ meldet Sie mit Ihrem Netzwerk-Konto in der Verwaltung der jeweiligen Website an.')) ?></p><?php endif; ?></div>
  <div class="adm-head__actions">
    <?php if ($section === ''): ?><a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url('/admin/network?refresh=1')) ?>" title="<?= e(__('Stand: {time}', ['time' => date('H:i:s', $oldest)])) ?>"><?= icon('arrows-clockwise') ?> <?= e(__('Aktualisieren')) ?></a><?php endif; ?>
    <?php if ($section !== 'neu'): ?><a class="adm-btn adm-btn--primary adm-btn--small" href="<?= e($secUrl('neu')) ?>"><?= icon('plus') ?> <?= e(__('Neue Website')) ?></a><?php endif; ?>
  </div>
</header>
<?php
// Bereichsliste links wie Grundeinstellungen (Core\AdminUi) – eine Adresse je Bereich
$items = [];
foreach ($secs as $k => [$label, $ico, $badge]) {
    $items[$k] = ['href' => $secUrl($k), 'label' => $label, 'icon' => $ico, 'badge' => $badge !== '!' ? $badge : null, 'warn' => $badge === '!' ? __('Noch nicht eingerichtet') : ''];
}
?>
<?php if ($section === ''): // alte Sprungmarken (/admin/network#email …) leiten um – resources/js/_network.js ?><span hidden data-net-oldhash="<?= e(json_encode(array_map($secUrl, $oldHash), JSON_UNESCAPED_SLASHES)) ?>"></span><?php endif; ?>
<?= \Core\AdminUi::open($items, $section === 'neu' ? '' : $section, __('Bereiche des Netzwerks'), false) ?>
<div class="net-page net-page--<?= e($section === '' ? 'websites' : $section) ?>">
<?php include __DIR__ . '/_' . ($section === '' ? 'websites' : ($section === 'administratoren' ? 'admins' : $section)) . '.php'; ?>
</div>
<?= \Core\AdminUi::close() ?>
