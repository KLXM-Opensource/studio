<?php
/** Admin-Layout. @var string $content  @var ?array $user  @var array $flash  @var string $view */
$dataOk = $user && (can('data.schema') || array_filter(\Core\Data\Tables::content(), fn($t) => can('data.edit', $t['handle'])));
// Anfragen = Eingangs-Tabellen, die der Benutzer lesen darf (Recht requests.read, optional je Tabelle)
$inboxes = $user && can('requests.read') ? \Core\Data\Inbox::readable() : [];
// Netzwerk-Administration (Core\Network): „Netzwerk“ auf der Netzwerk-Website, „Website wechseln“ auf allen anderen
$netUser = $user && \Core\Network\Network::isNetworkUser($user);
$nav = array_values(array_filter([
    ['/admin/network', __('Netzwerk'), 'network', $netUser && \Core\Network\Network::isNetworkSite()],
    ['/admin', __('Übersicht'), 'dashboard', true],
    ['/admin/pages', __('Seiten'), 'pages', $user && can('pages.edit')],
    // Entwürfe (Core\Review\Drafts): offene Seiten- und Eintrags-Entwürfe prüfen, veröffentlichen, verwerfen
    ['/admin/entwuerfe', __('Entwürfe'), 'drafts', $user && \Core\Review\Drafts::canView()],
    ['/admin/settings', app()->theme->settingsTitle(), 'settings', $user && can('settings.edit')],
    ['/admin/media', __('Medien'), 'media', $user && can('media.upload')],
    ['/admin/data', __('Daten'), 'data', (bool) $dataOk],
    // Glossar (Core\Glossary) ist eine Einstellungsseite (Core\AdminPages, kind settings): Sammelseite „Einstellungen“ und an der Tabelle „glossar“
    ['/admin/requests', __('Anfragen'), 'requests', (bool) $inboxes || ($user && can('requests.read') && can('data.schema') && \Core\Data\Inbox::available())],
    // Support & Wissensdatenbank: im Abschnitt „Hilfe & Support“ unten in der Seitenleiste
    // Chat zwischen Benutzern (Core\Chat, optional) – öffnet mit JavaScript die Schublade (resources/js/userchat.js)
    ['/admin/chat', __('Chat'), 'chat', $user && \Core\Chat\Chat::canUse()],
    // KI-Bereich (Core\AI, Marke config 'ai_brand' – Standard „KLXM AI“)
    ['/admin/ai', \Core\AI\Assist::brand(), 'ai', $user && \Core\AI\Assist::navVisible()],
    // Prüf-Ebene „Eingereicht“ (Core\Review) – eigener Punkt nur, wenn der KI-Bereich nicht sichtbar ist
    ['/admin/ai/eingereicht', __('Eingereicht'), 'review', $user && \Core\Review\Queue::canReview() && !\Core\AI\Assist::navVisible()],
    // Seiten von Funktionen und Erweiterungen (Core\AdminPages): hier nur Inhalte und Werkzeuge – Einstellungen und Statistiken
    // stehen gesammelt unter „Einstellungen“ bzw. „Statistiken“ (Administration)
    ...($user ? \Core\AdminPages::nav('main') : []),
], fn($n) => $n[3]));
// Abschnitt „Administration“: nur aufklappbare Gruppen „Einstellungen“ und „Werkzeuge“ (Core\AdminPages::groups) – leere entfallen,
// eine Gruppe mit nur einem Punkt erscheint als einfacher Link (z. B. Redaktion, die nur das Glossar einstellen darf)
$adminGroups = $user ? \Core\AdminPages::groups() : [];
$adminNav = array_merge([], ...array_column($adminGroups, 'items'));
$section = explode('/', $view)[0];
// Seite einer Sammelseite (Einstellungen/Statistiken, Core\AdminPages)? Dann ist deren Menüpunkt der aktuelle
$reqPath = $user && app()->request ? (string) app()->request->path : '';
$hubKind = $reqPath !== '' ? (\Core\AdminPages::match($reqPath)['kind'] ?? '') : '';
$hubKind = in_array($hubKind, ['settings', 'stats'], true) ? $hubKind : '';
// Unterseite einer Sammelseite (z. B. Einstellungen von KLXM Check): im Menü eingerückt unter der Sammelseite, über dem Inhalt ein Rückweg –
// sonst wäre die Seite eine Sackgasse
$hubPage = $hubKind !== '' ? \Core\AdminPages::match($reqPath) : null;
if ($hubPage && in_array(rtrim($hubPage['href'], '/'), [\Core\AdminPages::HUB, \Core\AdminPages::STATS], true)) $hubPage = null;
// Aktueller Menüpunkt: Sammelseite > Adresse (mit $href, Administration) > Bereich der Ansicht
$isCur = function (string $key, string $href = '') use ($hubKind, $reqPath, $section): bool {
    if ($hubKind !== '') return ($key === 'prefs' && $hubKind === 'settings') || ($key === 'stats' && $hubKind === 'stats');
    if ($href !== '' && ($reqPath === $href || str_starts_with($reqPath, $href . '/'))) return true;
    if (in_array($key, ['prefs', 'stats'], true)) return false;   // beide Sammelseiten liegen in views/prefs – nur über die Adresse
    return $section === $key || ($key === 'users' && $section === 'role');
};
$newReq = 0;
foreach ($inboxes as $ib) $newReq += \Core\Data\Inbox::count($ib, 'neu');
[$chatN, $chatAt] = $user ? \Core\Chat\Chat::navCount() : [0, 0];   // Chat: ungelesen + Erwähnungen (live: resources/js/userchat.js)
$supportN = $user ? \Core\Support\Support::navCount() : 0;   // ungelesene Antworten bzw. (Team) neue Meldungen
$reviewN = $user && \Core\Review\Queue::canReview() ? \Core\Review\Queue::pendingCount() : 0;   // offene Einreichungen (API, MCP, KI)
$draftsN = $user && in_array('drafts', array_column($nav, 2), true) ? \Core\Review\Drafts::count() : 0;   // offene Entwürfe (Seiten, Einträge)
// Favoriten (Core\Favorites): Liste + Vorschlag für den Stern dieser Seite (Adresse, Titel, Symbol) – resources/js/_favorites.js
$favs = $user ? \Core\Favorites::all((int) $user['id']) : [];
$favHere = $favTitle = $favIcon = '';
if ($user && ($req = app()->request)) {
    $favQ = $req->query;
    $favT = isset($t) && is_array($t) ? $t : (isset($table) && is_array($table) ? $table : null);
    // Kalender-Tabellen merken die Ansicht in der Sitzung – im Favoriten steht sie ausdrücklich
    if ($view === 'data/entries' && $favT && \Core\Data\Calendar::enabled($favT)) $favQ['view'] = empty($cal) ? 'list' : 'calendar';
    $favHere = (string) \Core\Favorites::normalize($req->path . ($favQ ? '?' . http_build_query($favQ) : ''));
    $favTitle = match (true) {
        $view === 'dashboard' => __('Übersicht'),
        $view === 'help/technical' => __('Technische Dokumentation'),
        $section === 'help' => __('Handbuch'),
        $favT !== null && $view === 'data/entries' => $favT['name'] . (empty($cal) ? '' : ' · ' . __('Kalender')),
        $favT !== null && $view === 'data/entry' => $favT['name'] . ' · ' . ($title ?? ''),
        $favT !== null && $view === 'data/schema' => $favT['name'] . ' · ' . __('Felder & Einstellungen'),
        default => (string) ($title ?? ''),
    };
    // Symbol des Favoriten: Symbol der Tabelle (Symbolname, Core\Icons) bzw. Menü-Schlüssel des Bereichs
    $favIcon = $favT !== null && trim((string) ($favT['icon'] ?? '')) !== '' ? (string) $favT['icon']
        : (['role' => 'users', 'account' => 'account', 'help' => 'help'][$section] ?? $section);
}
?><!doctype html>
<html lang="<?= e(\Core\I18n::locale()) ?>" class="adm-ui" data-icons="<?= e(\Core\Icons::sprite()) ?>"<?= ($icoTopics = \Core\Icons::enabledTopics()) ? ' data-icons-topics="' . e(implode(' ', $icoTopics)) . '"' /* Symbolbereiche (Grundeinstellungen) */ : '' ?><?= in_array($user['appearance'] ?? '', ['light', 'dark'], true) ? ' data-theme="' . e($user['appearance']) . '"' : '' ?><?= !$user && \Core\AuthScreen::forceDark() ? ' data-theme="dark"' /* Anmeldung nachts immer dunkel */ : '' ?><?= $user ? \Core\Accent::attrs($user) /* persönliche Akzentfarbe (Konto) */ : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? __('Verwaltung')) ?> · <?= e(site_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php foreach ($css ?? [] as $c): ?><link rel="stylesheet" href="<?= e(asset($c)) ?>">
<?php endforeach; ?>
<?= $user ? '' : \Core\AuthScreen::head() /* Bildschirme vor der Anmeldung: Farbhimmel nach Tageszeit + Markenfarbe (resources/css/auth.css) */ ?>
<link rel="icon" href="<?= e(base_path()) ?>/favicon.ico?v=<?= e(\Core\AppIcons::version()) ?>" sizes="48x48">
<script type="application/json" id="cms-i18n"><?= json_encode(\Core\I18n::dictionary(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= $user ? \Core\AI\Assist::clientScript() /* KI-Assistent (resources/js/_ai.js) – leer ohne Recht/KI */ : '' ?>
<?= $user ? \Core\AI\Assistant::clientScript() /* Assistent-Chat der Redaktion (resources/js/_assistant.js → assistant.mjs) – leer ohne Recht/KI */ : '' ?>
<?= $user ? \Core\Chat\Chat::head() /* Chat zwischen Benutzern (resources/js/userchat.js, css/userchat.css) – leer ohne Recht */ : '' ?>
<?= $user ? \Core\RichText::clientScript() /* Formatierungsleiste: Stile und Farben des Kits (resources/js/_rte.js) */ : '' ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?= $user ? \Core\Extensions::adminHead((string) ($view ?? '')) /* CSS/JS aktiver Erweiterungen für diese Ansicht (Extension::adminAssets) – nach admin.js */ : '' ?>
</head>
<?php $drill = $user && !empty($drill) ? $drill : ''; ?>
<body class="adm<?= $user ? '' : ' adm--bare ' . \Core\AuthScreen::bodyClass() ?><?= $drill ? ' is-drill' : '' ?>">
<?= $user ? '' : \Core\AuthScreen::sky() ?>
<?php if (environment() !== 'production'): ?><div class="adm-env adm-env--<?= e(environment()) ?>" role="note"><?= e(strtoupper(environment())) ?> · <?= e(__('Testumgebung – Änderungen hier gehen nicht auf die Live-Website. E-Mails werden umgeleitet, Suchmaschinen ausgesperrt.')) ?></div><?php endif; ?>
<?php if ($user):
  // Schmale Bildschirme (≤ 900 px): schlanke Kopfleiste, Seitenleiste als Schublade (resources/js/_drawer.js; ohne JavaScript per #adm-side)
  $areaTitle = (string) ($drillTitle ?? '');
  if ($areaTitle === '') foreach ($adminNav as [$h, $l, $k]) if ($isCur($k, $h)) { $areaTitle = (string) $l; break; }
  if ($areaTitle === '') foreach ($nav as [, $l, $k]) if ($isCur($k)) { $areaTitle = (string) $l; break; }
  if ($areaTitle === '') $areaTitle = match ($section) { 'help' => __('Handbuch & Hilfe'), 'account' => __('Konto'), 'role' => __('Benutzer & Rollen'), default => (string) ($title ?? __('Verwaltung')) };
?>
<header class="adm-top">
  <a class="adm-top__menu" href="#adm-side" aria-label="<?= e(__('Menü öffnen')) ?>" data-drawer-open><?= icon('list') ?></a>
  <a class="adm-top__title" href="<?= e(url('/admin')) ?>"><small><?= e(admin_brand()[0]) ?></small><span><?= e($areaTitle) ?></span></a>
  <button type="button" class="adm-top__search" data-spotlight aria-label="<?= e(__('Suchen')) ?>"><?= icon('magnifying-glass') ?></button>
</header>
<aside class="adm-side" id="adm-side" aria-label="<?= e(__('Menü')) ?>">
  <a class="adm-side__close" href="#main" aria-label="<?= e(__('Menü schließen')) ?>" data-drawer-close><?= icon('x') ?></a>
  <div class="adm-brand-row">
  <?php if ($netUser): // Netzwerk-Konten: der Website-Name steht im Umschalter darunter – hier kein doppelter Titel ?>
  <a class="adm-brand adm-brand--net" href="<?= e(url('/admin')) ?>" data-search-endpoint="<?= e(url('/admin/api/search')) ?>"><small><?= e(__('Verwaltung')) ?></small></a>
  <?php else: ?>
  <a class="adm-brand" href="<?= e(url('/admin')) ?>" data-search-endpoint="<?= e(url('/admin/api/search')) ?>"><?php [$b1, $b2] = admin_brand(); ?><span><?= e($b1) ?><i>.</i></span><small><?= e(trim($b2 . ' · ' . __('Verwaltung'), ' ·')) ?></small></a>
  <?php endif; ?>
    <div class="adm-brand-tools">
      <button type="button" class="adm-site-open adm-site-open--search" data-spotlight aria-keyshortcuts="Meta+K Control+K" title="<?= e(__('Suchen')) ?> (⌘K)" aria-label="<?= e(__('Suchen')) ?>"><?= icon('magnifying-glass') ?></button>
      <?php if (\Core\AI\Assistant::available()): // Assistent (KLXM AI) – nur wenn KI aktiv; öffnet das Chat-Fenster (resources/js/_assistant.js) ?>
      <a class="adm-site-open adm-site-open--ai" href="<?= e(url('/admin/ai/assistent')) ?>" data-assistant aria-keyshortcuts="Alt+Shift+K" title="<?= e(__('Assistent fragen')) ?> (⌥⇧K)" aria-label="<?= e(__('Assistent fragen')) ?>"><?= icon('chat-teardrop-dots') ?></a>
      <?php endif; ?>
      <a class="adm-site-open" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="<?= e(__('Website ansehen')) ?>" aria-label="<?= e(__('Website ansehen')) ?> <?= e(__('(öffnet in neuem Tab)')) ?>"><?= icon('arrow-square-out') ?></a>
    </div>
  </div>
  <?php if ($netUser): // Website wechseln (immer für Netzwerk-Konten, auch auf der Netzwerk-Website): Einmal-Anmeldung per Netzwerk-Token (ohne JavaScript) ?>
  <details class="adm-netswitch">
    <summary><?php // Für Netzwerk-Konten ersetzt der Umschalter den Titel der Marke ?><span class="adm-netswitch__eyebrow"><?= e(__('Netzwerk')) ?></span><span class="adm-netswitch__cur"><?= e(site()->label()) ?></span><span class="adm-netswitch__chev" aria-hidden="true"></span><span class="adm-sr"> – <?= e(__('Website wechseln')) ?></span></summary>
    <form method="post" action="<?= e(url('/admin/network/open')) ?>" class="adm-netswitch__menu">
      <?= csrf_field() ?>
      <p class="adm-netswitch__h"><?= e(__('Website wechseln')) ?></p>
      <?php if (\Core\Network\Network::isNetworkSite()): ?>
      <a href="<?= e(url('/admin/network')) ?>" class="is-home"><?= e(__('Netzwerk-Übersicht')) ?></a>
      <?php else: ?>
      <button type="submit" name="site" value="<?= e(\Core\Network\Network::siteKey()) ?>" class="is-home"><?= e(__('Netzwerk-Übersicht')) ?></button>
      <?php endif; ?>
      <?php foreach (\Core\Sites::all() as $sk => $sc): if ($sk === site()->key) continue; $st = new \Core\Site($sk, $sc); ?>
      <?php $sIco = \Core\Network\SiteIcon::for($sk, \Core\Network\Stats::cached($sk)); // App-Icon wie in der Netzwerk-Übersicht ?>
      <button type="submit" name="site" value="<?= e($sk) ?>"><span class="adm-netswitch__site"><img class="adm-netswitch__ico" src="<?= e($sIco['src']) ?>" width="16" height="16" alt="" loading="lazy" decoding="async"><?= e($sk === 'default' && empty($sc['label']) ? __('Hauptwebsite') : $st->label()) ?></span><small><?= e($sk) ?></small></button>
      <?php endforeach; ?>
    </form>
  </details>
  <?php endif; ?>
  <?php // Favoriten: immer sichtbar (auch im Drill-down), aufklappbar, bearbeitbar – resources/js/_favorites.js ?>
  <section class="adm-fav<?= $favs ? '' : ' is-empty' ?>" id="adm-fav" aria-labelledby="adm-fav-h" data-fav-endpoint="<?= e(url('/admin/api/favorites')) ?>"
    data-fav-here="<?= e($favHere) ?>" data-fav-title="<?= e($favTitle) ?>" data-fav-icon="<?= e($favIcon) ?>" data-fav-ico="<?= e((string) \Core\Icons::resolve($favIcon)) ?>" data-fav-user="<?= (int) $user['id'] ?>" data-fav-max="<?= \Core\Favorites::MAX ?>">
    <div class="adm-fav__head">
      <h2 class="adm-fav__h" id="adm-fav-h"><button type="button" class="adm-fav__toggle" aria-expanded="true" aria-controls="adm-fav-body"><span class="adm-fav__chev" aria-hidden="true"></span><?= e(__('Favoriten')) ?></button></h2>
      <a class="adm-fav__edit" href="<?= e(url('/admin/account#favoriten')) ?>" data-fav-edit><?= e(__('Bearbeiten')) ?></a>
    </div>
    <div class="adm-fav__body" id="adm-fav-body">
      <nav aria-labelledby="adm-fav-h">
        <ul class="adm-fav__list" data-fav-list>
          <?php foreach ($favs as $f): $favSvg = \Core\Icons::render($f['icon'] ?: 'fav', ['class' => 'adm-fav__ico']); ?>
          <li data-url="<?= e($f['url']) ?>"><a href="<?= e(url($f['url'])) ?>"<?= $f['url'] === $favHere ? ' aria-current="page"' : '' ?>><?= $favSvg ?><span><?= e($f['title']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <p class="adm-fav__empty" data-fav-empty<?= $favs ? ' hidden' : '' ?>><?= e(__('Noch keine Favoriten. Mit dem Stern ☆ oben auf jeder Seite merken Sie sich Seiten, Tabellen, Einträge oder Einstellungen – sie erscheinen dann hier.')) ?></p>
    </div>
    <script type="application/json" id="adm-fav-data"><?= json_encode(array_map(fn($f) => $f + ['href' => url($f['url']), 'ico' => \Core\Icons::resolve($f['icon'] ?: 'fav')], $favs), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </section>
  <?php if ($drill): // Bereichsnavigation (z. B. Daten) ersetzt die Hauptnavigation – resources/js/_drill.js; ohne JavaScript führt „Hauptmenü“ zur Übersicht ?>
  <div class="adm-drill" data-drill-area="<?= e(['review' => 'ai', 'assistant' => 'ai'][$section] ?? $section) ?>" data-drill-back="<?= e(__('Zurück zu {name}', ['name' => $drillTitle ?? ''])) ?>" data-drill-title="<?= e($drillTitle ?? '') ?>">
    <a class="adm-drill__back" href="<?= e(url('/admin')) ?>"><span aria-hidden="true">‹</span> <?= e(__('Hauptmenü')) ?></a>
    <p class="adm-drill__title" aria-hidden="true"><?= e($drillTitle ?? '') ?></p>
    <?= $drill ?>
  </div>
  <?php endif; ?>
  <nav id="adm-mainnav" aria-label="<?= e(__('Verwaltung')) ?>">
    <ul>
      <?php foreach ($nav as [$href, $label, $key]): $navSvg = \Core\Icons::nav($key, 'adm-nav__ico'); // Symbol aus dem Sprite; ohne (KLXM AI) → CSS-Maske über data-ico ?>
      <li><a href="<?= e(url($href)) ?>"<?= $navSvg ? ' data-nav="' . e($key) . '"' : ' data-ico="' . e($key) . '"' ?><?= $isCur($key) ? ' aria-current="page"' : '' ?>><?= $navSvg ?><span><?= e($label) ?></span><?php if ($key === 'requests' && $newReq): ?> <span class="adm-count"><?= $newReq ?></span><?php endif; ?><?php if ($key === 'chat' && $href === '/admin/chat'): ?> <span class="adm-count uc-count<?= $chatAt ? ' uc-count--at' : '' ?>" data-chat-badge<?= $chatN ? '' : ' hidden' ?>><?= $chatAt ? '@ ' : '' ?><?= $chatN ?><span class="sr-only"> <?= e(__('ungelesen')) ?></span></span><?php endif; ?><?php if ($key === 'support' && $supportN): ?> <span class="adm-count"><?= $supportN ?><span class="sr-only"> <?= e(__('ungelesen')) ?></span></span><?php endif; ?><?php if ($key === 'drafts'): ?> <span class="adm-count" title="<?= e(__('Offene Entwürfe')) ?>" data-drafts-badge<?= $draftsN ? '' : ' hidden' ?>><span data-n><?= $draftsN ?></span><span class="sr-only"> <?= e(__('offene Entwürfe')) ?></span></span><?php endif; ?><?php if (in_array($key, ['ai', 'review'], true) && $reviewN): ?> <span class="adm-count" title="<?= e(__('Eingereicht: zur Freigabe')) ?>"><?= $reviewN ?><span class="sr-only"> <?= e(__('zur Freigabe eingereicht')) ?></span></span><?php endif; ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($adminGroups): // Administration: aufklappbare Gruppen – Zustand je Browser (resources/js/_navgroups.js), offen auf ihren Seiten ?>
    <p class="adm-side__label" id="adm-admin-h"><?= e(__('Administration')) ?></p>
    <ul class="adm-navgrps" aria-labelledby="adm-admin-h">
      <?php foreach ($adminGroups as $g): $gOpen = (bool) array_filter($g['items'], fn($n) => $isCur($n[2], $n[0])); ?>
      <?php if (count($g['items']) === 1): [$href, $label, $key] = $g['items'][0]; $navSvg = \Core\Icons::nav($key, 'adm-nav__ico'); ?>
      <li><a href="<?= e(url($href)) ?>"<?= $navSvg ? ' data-nav="' . e($key) . '"' : ' data-ico="' . e($key) . '"' ?><?= $gOpen ? ' aria-current="page"' : '' ?>><?= $navSvg ?><span><?= e($label) ?></span></a></li>
      <?php else: $gId = 'adm-grp-' . $g['key']; ?>
      <li class="adm-navgrp<?= $gOpen ? ' is-open is-current' : '' ?>" data-navgroup="<?= e($g['key']) ?>">
        <button type="button" class="adm-navgrp__btn" aria-expanded="<?= $gOpen ? 'true' : 'false' ?>" aria-controls="<?= e($gId) ?>"><?= \Core\Icons::nav($g['icon'], 'adm-nav__ico') ?><span><?= e($g['label']) ?></span><span class="adm-navgrp__chev" aria-hidden="true"></span></button>
        <ul class="adm-navgrp__list" id="<?= e($gId) ?>"<?= $gOpen ? '' : ' hidden' ?>>
          <?php foreach ($g['items'] as [$href, $label, $key]): ?>
          <?php $sub = $hubPage && (($key === 'prefs' && $hubKind === 'settings') || ($key === 'stats' && $hubKind === 'stats')); ?>
          <li><a href="<?= e(url($href)) ?>" data-nav="<?= e($key) ?>"<?= $sub ? ' class="is-parent"' : ($isCur($key, $href) ? ' aria-current="page"' : '') ?>><span><?= e($label) ?></span></a>
            <?php if ($sub): ?><ul class="adm-navgrp__sub"><li><a href="<?= e(url($hubPage['href'])) ?>" aria-current="page"><span><?= e($hubPage['label']) ?></span></a></li></ul><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      </li>
      <?php endif; ?>
      <?php endforeach; ?>
    </ul>
    <noscript><style>.adm-side .adm-navgrp__list[hidden]{display:grid}.adm-navgrp__chev{display:none}</style></noscript>
    <?php endif; ?>
  </nav>
  <div class="adm-side__foot">
    <?php // Hilfe: Handbuch, Assistent, Problem melden – zusammengeklappt; offen auf Hilfeseiten, Zustand merkt sich der Browser (admin.js) ?>
    <details class="adm-helpbox" data-helpbox<?= in_array($section, ['help', 'support'], true) ? ' open' : '' ?>>
      <summary class="adm-helpbox__sum"><?= icon('question', ['class' => 'adm-help-link__ico']) ?><span><?= e(__('Hilfe & Support')) ?></span><?php if ($supportN): ?><span class="adm-count"><?= $supportN ?><span class="sr-only"> <?= e(__('ungelesen')) ?></span></span><?php endif; ?><span class="adm-helpbox__chev" aria-hidden="true"></span></summary>
      <div class="adm-helpbox__body">
      <?php if (\Core\Support\Support::canRead()): // Support & Wissensdatenbank (Core\Support) – zentral für alle Websites ?>
      <a class="adm-help-link<?= $section === 'support' && $view !== 'support/new' ? ' is-current' : '' ?>" href="<?= e(url('/admin/support')) ?>"><?= icon('lifebuoy', ['class' => 'adm-help-link__ico']) ?> <?= e(__('Support')) ?><?php if ($supportN): ?> <span class="adm-count"><?= $supportN ?><span class="sr-only"> <?= e(__('ungelesen')) ?></span></span><?php endif; ?></a>
      <?php endif; ?>
      <a class="adm-help-link<?= $section === 'help' ? ' is-current' : '' ?>" href="<?= e(url('/admin/hilfe')) ?>"><?= icon('question', ['class' => 'adm-help-link__ico']) ?> <?= e(__('Handbuch & Hilfe')) ?></a>
      <?php if (\Core\Guide::exists()): // Hinweise zu diesem Projekt (Core\Guide) – nur wenn Kit oder Website welche mitbringen ?>
      <a class="adm-help-link" href="<?= e(url('/admin/hilfe')) ?>#projekt"><?= icon('lightbulb', ['class' => 'adm-help-link__ico']) ?> <?= e(__('Projekt-Hinweise')) ?></a>
      <?php endif; ?>
      <?php if (\Core\AI\Assistant::available()): // Assistent-Chat (Core\AI\Assistant) – öffnet das Chat-Fenster, ohne JavaScript die Seite im KI-Bereich ?>
      <a class="adm-help-link adm-help-link--assistant" href="<?= e(url('/admin/ai/assistent')) ?>" data-assistant aria-keyshortcuts="Alt+Shift+K"><?= icon('chat-teardrop-dots', ['class' => 'adm-help-link__ico']) ?> <?= e(__('Assistent fragen')) ?></a>
      <?php endif; ?>
      <?php if (\Core\Support\Support::canReport()): // Problem melden (Core\Support) – überall erreichbar, nimmt die aktuelle Seite als Kontext mit ?>
      <a class="adm-help-link adm-help-link--report<?= $view === 'support/new' ? ' is-current' : '' ?>" href="<?= e(url('/admin/support/neu')) ?>" data-support-report><?= icon('warning', ['class' => 'adm-help-link__ico']) ?> <?= e(__('Problem melden')) ?></a>
      <?php endif; ?>
      </div>
    </details>
    <a class="adm-me" href="<?= e(url('/admin/account')) ?>"><?= e($user['name'] ?: $user['email']) ?><small><?= e(app()->auth->role()["name"] ?? $user["role"]) ?> · <?= e(__("Konto")) ?></small></a>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="adm-link" type="submit"><?= e(__('Abmelden')) ?></button></form>
  </div>
</aside>
<?php endif; ?>
<main class="adm-main" id="main">
  <?php if ($hubPage): // Rückweg von einer Einstellungs-/Statistikseite zur Sammelseite (und zur Datentabelle, falls sie dazugehört) ?>
  <nav class="adm-backbar" aria-label="<?= e(__('Zurück')) ?>">
    <a href="<?= e(url($hubKind === 'stats' ? \Core\AdminPages::STATS : \Core\AdminPages::HUB)) ?>"><span aria-hidden="true">←</span> <?= e($hubKind === 'stats' ? __('Statistiken') : __('Einstellungen der Funktionen')) ?></a>
    <?php if (!empty($hubPage['table']) && ($tbl = \Core\Data\Tables::find((string) $hubPage['table']))): ?><a href="<?= e(url('/admin/data/' . $tbl['handle'])) ?>"><?= e(__('Zur Tabelle „{name}“', ['name' => (string) $tbl['name']])) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php foreach ($flash as [$type, $msg]): ?>
  <div class="adm-flash adm-flash--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?php // Hinweise zu diesem Projekt für diesen Bereich (Core\Guide, Front Matter „bereich:“) – kleiner Link über dem Inhalt
  $guideT = isset($t) && is_array($t) ? $t : (isset($table) && is_array($table) ? $table : null);
  if ($user && $section !== 'help' && !\Core\Guide::$shownInline && ($guideHere = \Core\Guide::forArea($section, $guideT['handle'] ?? null))): ?>
  <p class="adm-guidehint"><?= icon('lightbulb') ?><span><?= e(count($guideHere) > 1 ? __('Hinweise zum Projekt:') : __('Hinweis zum Projekt:')) ?>
    <?php foreach ($guideHere as $gi => $gn): ?><?= $gi ? ' · ' : '' ?><a href="<?= e(\Core\Guide::url($gn)) ?>"><?= e($gn['title']) ?></a><?php endforeach; ?></span></p>
  <?php endif; ?>
  <?= $content ?>
</main>
<?php if ($user): ?>
<input type="hidden" id="adm-csrf" value="<?= e(\Core\Csrf::token()) ?>">
<div class="adm-sr" id="adm-live" role="status" aria-live="polite"></div>
<datalist id="cms-links" data-endpoint="<?= e(url('/admin/api/links')) ?>"></datalist>
<?php endif; ?>
</body>
</html>
