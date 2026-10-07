<?php
declare(strict_types=1);

use Core\Http\Controllers\Admin;
use Core\Http\Controllers\ApiController as Api;
use Core\Http\Controllers\McpController;
use Core\Http\Controllers\PdfController;
use Core\Http\Controllers\PwaController;
use Core\Http\Controllers\ProxyController;
use Core\Http\Controllers\FormController;
use Core\Http\Controllers\SiteController;
use Core\Http\Router;

return function (Router $r): void {
    // ---------------------------------------------------------------- Admin
    $r->get('/admin', [Admin\DashboardController::class, 'index']);
    // Übersicht: persönliche Anordnung der Karten, nachgeladene Statistiken (Core\Dashboard, resources/js/dashboard.js)
    $r->post('/admin/api/dashboard/prefs', [Admin\DashboardController::class, 'prefs']);
    $r->post('/admin/api/dashboard/placeholder-ok', [Admin\DashboardController::class, 'placeholderOk']);
    $r->get('/admin/api/dashboard/{card}', [Admin\DashboardController::class, 'card']);
    $r->get('/admin/login', [Admin\AuthController::class, 'loginForm']);
    $r->post('/admin/login', [Admin\AuthController::class, 'login']);
    $r->post('/admin/logout', [Admin\AuthController::class, 'logout']);
    // „Passwort vergessen“ (Core\PasswordReset): Anfrage mit immer gleicher Antwort; Link aus der E-Mail (GET = Formular, POST = setzen)
    $r->get('/admin/passwort-vergessen', [Admin\PasswordController::class, 'forgotForm']);
    $r->post('/admin/passwort-vergessen', [Admin\PasswordController::class, 'forgot']);
    $r->get('/admin/passwort/{token}', [Admin\PasswordController::class, 'resetForm']);
    $r->post('/admin/passwort/{token}', [Admin\PasswordController::class, 'reset']);
    // Erststart: Kit und Startinhalte wählen (Core\Onboarding)
    $r->get('/admin/willkommen', [Admin\WelcomeController::class, 'index']);
    $r->post('/admin/willkommen', [Admin\WelcomeController::class, 'save']);
    $r->get('/admin/setup', [Admin\AuthController::class, 'setupForm']);
    $r->post('/admin/setup', [Admin\AuthController::class, 'setup']);
    $r->get('/admin/account', [Admin\UserController::class, 'account']);
    $r->post('/admin/account', [Admin\UserController::class, 'saveAccount']);
    $r->post('/admin/account/accent', [Admin\UserController::class, 'saveAccent']);   // Akzentfarbe (Core\Accent)
    // Anmeldedaten (Core\EmailChange): Name, E-Mail-Adresse mit Bestätigung (Links aus den E-Mails öffentlich, Token allein genügt)
    $r->post('/admin/account/profile', [Admin\AccountController::class, 'saveProfile']);
    $r->post('/admin/account/email', [Admin\AccountController::class, 'requestEmail']);
    $r->post('/admin/account/email/resend', [Admin\AccountController::class, 'resendEmail']);
    $r->post('/admin/account/email/cancel', [Admin\AccountController::class, 'cancelEmail']);
    $r->get('/admin/konto/email/{token}', [Admin\AccountController::class, 'confirmPage']);
    $r->post('/admin/konto/email/{token}', [Admin\AccountController::class, 'confirm']);
    $r->get('/admin/konto/email-abbrechen/{token}', [Admin\AccountController::class, 'cancelPage']);
    $r->post('/admin/konto/email-abbrechen/{token}', [Admin\AccountController::class, 'cancel']);
    // Zwei-Faktor-Anmeldung (Core\Totp) und Netzwerk-Administration (Core\Network)
    $r->get('/admin/login/2fa', [Admin\TwoFactorController::class, 'challengeForm']);
    $r->post('/admin/login/2fa', [Admin\TwoFactorController::class, 'challenge']);
    $r->get('/admin/account/2fa', [Admin\TwoFactorController::class, 'setup']);
    $r->post('/admin/account/2fa/enable', [Admin\TwoFactorController::class, 'enable']);
    $r->post('/admin/account/2fa/disable', [Admin\TwoFactorController::class, 'disable']);
    $r->post('/admin/account/2fa/recovery', [Admin\TwoFactorController::class, 'recovery']);
    $r->post('/admin/users/2fa', [Admin\UserController::class, 'twoFactorRoles']);
    // Passkeys (Core\Passkeys, Richtlinie Core\Mfa): zweiter Faktor, Anmeldung ohne Passwort, Verwaltung im Konto
    $r->post('/admin/login/2fa/passkey/options', [Admin\TwoFactorController::class, 'passkeyOptions']);
    $r->post('/admin/login/2fa/passkey', [Admin\TwoFactorController::class, 'passkey']);
    $r->post('/admin/login/passkey/options', [Admin\PasskeyController::class, 'loginOptions']);
    $r->post('/admin/login/passkey', [Admin\PasskeyController::class, 'login']);
    $r->get('/admin/account/2fa/choose', [Admin\PasskeyController::class, 'choose']);
    $r->get('/admin/account/2fa/codes', [Admin\PasskeyController::class, 'codes']);
    $r->post('/admin/account/reauth', [Admin\PasskeyController::class, 'reauth']);
    $r->post('/admin/account/reauth/passkey/options', [Admin\PasskeyController::class, 'reauthOptions']);
    $r->post('/admin/account/reauth/passkey', [Admin\PasskeyController::class, 'reauthPasskey']);
    $r->post('/admin/account/passkeys/options', [Admin\PasskeyController::class, 'options']);
    $r->post('/admin/account/passkeys', [Admin\PasskeyController::class, 'store']);
    $r->post('/admin/account/passkeys/{id}/rename', [Admin\PasskeyController::class, 'rename']);
    $r->post('/admin/account/passkeys/{id}/delete', [Admin\PasskeyController::class, 'delete']);
    $r->post('/admin/network/auth-policy', [Admin\NetworkController::class, 'authPolicy']);
    $r->get('/admin/sso',[Admin\NetworkController::class, 'sso']);
    $r->get('/admin/network/media-usages', [Admin\NetworkController::class, 'mediaUsages']);   // signiert, zwischen Websites (MediaPools::usagesElsewhere)
    $r->get('/admin/network', [Admin\NetworkController::class, 'index']);
    $r->post('/admin/network/open', [Admin\NetworkController::class, 'open']);
    $r->post('/admin/network/sites', [Admin\NetworkController::class, 'createSite']);
    $r->post('/admin/network/accounts', [Admin\NetworkController::class, 'accountCreate']);
    // Weitere Netzwerk-Administratoren einladen (Core\Invites, Rolle network – nur Netzwerk-Website, nur Netzwerk-Konten)
    $r->post('/admin/network/invite', [Admin\NetworkController::class, 'inviteStore']);
    $r->post('/admin/network/invites/{id}/resend', [Admin\NetworkController::class, 'inviteResend']);
    $r->post('/admin/network/invites/{id}/revoke', [Admin\NetworkController::class, 'inviteRevoke']);
    $r->post('/admin/network/accounts/{id}/{action}', [Admin\NetworkController::class, 'accountAction']);
    $r->post('/admin/network/site/{key}/{action}', [Admin\NetworkController::class, 'action']);
    // Landingpages mit eigenen Domains (Core\Landings, Funktion „landings“)
    $r->get('/admin/landingpages', [Admin\LandingController::class, 'index']);
    $r->get('/admin/landingpages/new', [Admin\LandingController::class, 'edit']);
    $r->post('/admin/landingpages/new', [Admin\LandingController::class, 'save']);
    $r->get('/admin/landingpages/{id}', [Admin\LandingController::class, 'edit']);
    $r->post('/admin/landingpages/{id}', [Admin\LandingController::class, 'save']);
    $r->post('/admin/landingpages/{id}/delete', [Admin\LandingController::class, 'delete']);
    $r->post('/admin/landingpages/{id}/check', [Admin\LandingController::class, 'check']);
    // Weiterleitungen und 404-Protokoll (Core\Redirects, Funktion „redirects“, Recht redirects.manage)
    // Glossar (Funktion „glossary“, Core\Glossary): Begriffe, Prüfungen, Einstellungen, Import/Export, KI-Vorschlag
    $r->get('/admin/glossar', [Admin\GlossaryController::class, 'index']);
    $r->post('/admin/glossar/einrichten', [Admin\GlossaryController::class, 'install']);
    $r->post('/admin/glossar/einstellungen', [Admin\GlossaryController::class, 'settings']);
    $r->post('/admin/glossar/neu', [Admin\GlossaryController::class, 'quick']);
    $r->post('/admin/glossar/import', [Admin\GlossaryController::class, 'import']);
    $r->get('/admin/glossar/export', [Admin\GlossaryController::class, 'export']);
    // Geteiltes Glossar (Core\Glossary\Sharing): teilen, einladen, beitreten, verlassen; fremde Begriffe aus-/einblenden
    $r->get('/admin/glossar/teilen', [Admin\GlossaryController::class, 'sharing']);
    $r->post('/admin/glossar/teilen', [Admin\GlossaryController::class, 'sharingSave']);
    $r->post('/admin/glossar/ausblenden', [Admin\GlossaryController::class, 'hide']);
    // Quick-Glossar beim Bearbeiten auf der Website (Core\Glossary\QuickTool, resources/js/quick-glossary.mjs)
    $r->get('/admin/api/glossar/suche', [Admin\GlossaryController::class, 'apiSearch']);
    $r->post('/admin/api/glossar/begriff', [Admin\GlossaryController::class, 'apiCreate']);
    $r->post('/admin/api/glossar/seite', [Admin\GlossaryController::class, 'apiPage']);
    $r->get('/admin/weiterleitungen', [Admin\RedirectController::class, 'index']);
    $r->get('/admin/weiterleitungen/new', [Admin\RedirectController::class, 'edit']);
    $r->post('/admin/weiterleitungen/new', [Admin\RedirectController::class, 'save']);
    $r->get('/admin/weiterleitungen/404', [Admin\RedirectController::class, 'notFound']);
    $r->post('/admin/weiterleitungen/404', [Admin\RedirectController::class, 'notFoundAction']);
    $r->post('/admin/weiterleitungen/bulk', [Admin\RedirectController::class, 'bulk']);
    $r->post('/admin/weiterleitungen/import', [Admin\RedirectController::class, 'import']);
    $r->get('/admin/weiterleitungen/export', [Admin\RedirectController::class, 'export']);
    $r->post('/admin/weiterleitungen/einstellungen', [Admin\RedirectController::class, 'settings']);
    $r->get('/admin/weiterleitungen/{id}', [Admin\RedirectController::class, 'edit']);
    $r->post('/admin/weiterleitungen/{id}', [Admin\RedirectController::class, 'save']);
    $r->post('/admin/weiterleitungen/{id}/delete', [Admin\RedirectController::class, 'delete']);

    $r->get('/admin/pages', [Admin\PageController::class, 'index']);
    $r->get('/admin/pages/new', [Admin\PageController::class, 'create']);
    $r->post('/admin/pages/new', [Admin\PageController::class, 'store']);
    $r->post('/admin/pages/nicht-gefunden', [Admin\PageController::class, 'notFound']);   // Seite „Nicht gefunden (404)“ anlegen/öffnen
    $r->get('/admin/pages/{id}', [Admin\PageController::class, 'edit']);
    $r->post('/admin/pages/{id}', [Admin\PageController::class, 'update']);
    $r->post('/admin/pages/{id}/delete', [Admin\PageController::class, 'delete']);
    $r->post('/admin/pages/{id}/publish', [Admin\PageController::class, 'publish']);
    $r->post('/admin/pages/{id}/offline', [Admin\PageController::class, 'offline']);   // offline nehmen (Status Entwurf, veröffentlichte Fassung bleibt)
    $r->post('/admin/pages/{id}/discard', [Admin\PageController::class, 'discard']);
    $r->post('/admin/pages/{id}/restore/{rev}', [Admin\PageController::class, 'restore']);
    $r->post('/admin/pages/{id}/move', [Admin\PageController::class, 'move']);
    $r->post('/admin/pages/{id}/quick', [Admin\PageController::class, 'quick']);
    $r->post('/admin/pages/{id}/duplicate', [Admin\PageController::class, 'duplicate']);
    $r->post('/admin/pages/{id}/noindex', [Admin\PageController::class, 'noindex']);   // Nicht indexieren umschalten
    $r->get('/admin/pages/{id}/vorschau', [Admin\PageController::class, 'preview']);   // Seitenleiste „Vorschau“ im Seitenbaum
    // Seitenvorlagen für die Redaktion (Core\PageTemplates)
    $r->get('/admin/seitenvorlagen', [Admin\PageTemplateController::class, 'index']);
    $r->post('/admin/seitenvorlagen', [Admin\PageTemplateController::class, 'save']);
    $r->post('/admin/seitenvorlagen/neu', [Admin\PageTemplateController::class, 'create']);
    $r->post('/admin/pages/{id}/template', [Admin\PageTemplateController::class, 'fromPage']);
    $r->post('/admin/pages/{id}/translate', [Admin\PageController::class, 'translate']);
    // Entwürfe: offene Seiten- und Eintrags-Entwürfe prüfen, veröffentlichen, verwerfen, Notiz/Zuständigkeit (Core\Review\Drafts)
    $dr = Admin\DraftController::class;
    $r->get('/admin/entwuerfe', [$dr, 'index']);
    $r->get('/admin/entwuerfe/seite/{id}', [$dr, 'page']);
    $r->get('/admin/entwuerfe/eintrag/{table}/{id}', [$dr, 'entry']);
    $r->post('/admin/entwuerfe/notiz', [$dr, 'note']);
    $r->post('/admin/entwuerfe/wiederherstellen/{id}', [$dr, 'restore']);
    $r->post('/admin/entwuerfe/{op}', [$dr, 'action']);

    $r->get('/admin/settings', [Admin\SettingsController::class, 'edit']);
    $r->post('/admin/settings', [Admin\SettingsController::class, 'save']);
    $r->post('/admin/api/settings-preview', [Admin\SettingsController::class, 'preview']);
    $r->get('/admin/design', [Admin\DesignController::class, 'edit']);
    $r->post('/admin/design', [Admin\DesignController::class, 'save']);
    $r->post('/admin/api/design-preview', [Admin\DesignController::class, 'preview']);
    $r->post('/admin/api/design-import', [Admin\DesignController::class, 'import']);
    // Block-Designer (Core\Blocks\Custom): eigene Blöcke – feste Pfade vor /{key}
    $cb = Admin\BlockController::class;
    $r->get('/admin/blocks', [$cb, 'index']);
    $r->get('/admin/blocks/new', [$cb, 'create']);
    $r->post('/admin/blocks/new', [$cb, 'store']);
    $r->post('/admin/blocks/import', [$cb, 'import']);
    $r->post('/admin/blocks/library', [$cb, 'libraryImport']);
    $r->get('/admin/blocks/preview.css', [$cb, 'previewCss']);
    $r->post('/admin/blocks/demos', [$cb, 'demoInstall']);                       // Beispiele: Kopie als Entwurf (Core\Blocks\Demos)
    $r->get('/admin/blocks/demos/{name}/preview', [$cb, 'demoPreview']);
    $r->get('/admin/blocks/demos/{name}/preview.css', [$cb, 'demoCss']);
    $r->post('/admin/api/blocks/preview', [$cb, 'preview']);
    $r->post('/admin/api/blocks/sample-form', [$cb, 'sampleForm']);
    $r->post('/admin/api/blocks/ai', [$cb, 'ai']);
    $r->get('/admin/blocks/{key}', [$cb, 'edit']);
    $r->post('/admin/blocks/{key}', [$cb, 'update']);
    $r->post('/admin/blocks/{key}/publish', [$cb, 'publish']);
    $r->post('/admin/blocks/{key}/withdraw', [$cb, 'withdraw']);
    $r->post('/admin/blocks/{key}/delete', [$cb, 'delete']);
    $r->post('/admin/blocks/{key}/restore/{version}', [$cb, 'restore']);
    $r->post('/admin/blocks/{key}/library', [$cb, 'library']);
    $r->get('/admin/blocks/{key}/export', [$cb, 'export']);
    $r->get('/admin/blocks/{key}/theme-export', [$cb, 'themeExport']);

    // Daten (Tabellen-Designer + Einträge) – spezifische Routen vor {handle}/{id}
    $r->get('/admin/api/search', [Admin\SearchController::class, 'search']);
    $r->get('/admin/api/geocode', [Admin\SearchController::class, 'geocode']);
    $r->post('/admin/system/proxy-clear', [Admin\SystemController::class, 'clearProxy']);
    $r->post('/admin/system/admin-path', [Admin\SystemController::class, 'adminPath']);   // Adresse der Verwaltung (Core\AdminPath)
    $r->post('/admin/system/pools', [Admin\SystemController::class, 'poolCreate']);
    $r->post('/admin/system/pools/{key}', [Admin\SystemController::class, 'poolUpdate']);
    $r->post('/admin/system/pools/{key}/delete', [Admin\SystemController::class, 'poolDelete']);
    $r->post('/admin/system/shared', [Admin\SystemController::class, 'sharedCreate']);
    $r->post('/admin/system/shared/{key}', [Admin\SystemController::class, 'sharedUpdate']);
    $r->post('/admin/system/shared/{key}/unshare', [Admin\SystemController::class, 'sharedUnshare']);
    // Externe Quellen (Core\Sources): Feeds, JSON-APIs, XML, OpenImmo → Datentabellen (Funktion „sources“, Recht sources.manage)
    $r->get('/admin/quellen', [Admin\SourceController::class, 'index']);
    $r->post('/admin/quellen/schalter', [Admin\SourceController::class, 'toggle']);
    $r->get('/admin/quellen/new', [Admin\SourceController::class, 'edit']);
    $r->post('/admin/quellen/new', [Admin\SourceController::class, 'save']);
    $r->post('/admin/quellen/eintrag/{handle}/{id}', [Admin\SourceController::class, 'entryStatus']);
    $r->get('/admin/quellen/{id}', [Admin\SourceController::class, 'edit']);
    $r->post('/admin/quellen/{id}', [Admin\SourceController::class, 'save']);
    $r->post('/admin/quellen/{id}/sync', [Admin\SourceController::class, 'sync']);
    $r->post('/admin/quellen/{id}/upload', [Admin\SourceController::class, 'upload']);
    $r->post('/admin/quellen/{id}/table', [Admin\SourceController::class, 'createTable']);
    $r->post('/admin/quellen/{id}/delete', [Admin\SourceController::class, 'delete']);
    $r->get('/admin/data', [Admin\DataController::class, 'index']);
    $r->get('/admin/data/new', [Admin\DataController::class, 'create']);
    $r->post('/admin/data', [Admin\DataController::class, 'store']);
    $r->get('/admin/data/{handle}/schema', [Admin\DataController::class, 'schema']);
    $r->post('/admin/data/{handle}/schema', [Admin\DataController::class, 'update']);
    $r->post('/admin/data/{handle}/destroy', [Admin\DataController::class, 'destroy']);
    $r->post('/admin/data/{handle}/delivery-test', [Admin\DataController::class, 'deliveryTest']);
    $r->post('/admin/data/{handle}/template', [Admin\DataController::class, 'template']);
    $r->post('/admin/data/{handle}/bulk', [Admin\DataController::class, 'bulk']);
    $r->post('/admin/data/{handle}/quick', [Admin\DataController::class, 'quick']);
    // Geteilte Tabellen (Core\Data\Shared): fremde Einträge, Auswahl, Anzeige auf dieser Website – vor /{id}
    $r->get('/admin/data/{handle}/shared', [Admin\DataController::class, 'foreign']);
    $r->post('/admin/data/{handle}/pick', [Admin\DataController::class, 'pick']);
    $r->get('/admin/data/{handle}/display', [Admin\DataController::class, 'display']);
    $r->post('/admin/data/{handle}/display', [Admin\DataController::class, 'displaySave']);
    $r->get('/admin/data/{handle}/new', [Admin\DataController::class, 'entryNew']);
    $r->post('/admin/data/{handle}/new', [Admin\DataController::class, 'entrySave']);
    $r->post('/admin/data/{handle}/{id}/delete', [Admin\DataController::class, 'entryDelete']);
    $r->post('/admin/data/{handle}/{id}/translate', [Admin\DataController::class, 'translate']);
    $r->get('/admin/data/{handle}/{id}', [Admin\DataController::class, 'entryEdit']);
    $r->post('/admin/data/{handle}/{id}', [Admin\DataController::class, 'entrySave']);
    $r->get('/admin/data/{handle}', [Admin\DataController::class, 'entries']);

    // Funktionen & Erweiterungen der Website (Core\Features, Core\Extensions) – Recht system.features bzw. Netzwerk/Integratoren
    // Sammelseiten (Core\AdminPages): Einstellungen der Funktionen & Erweiterungen, Statistiken
    $r->get('/admin/einstellungen', [Admin\PrefsController::class, 'settings']);
    $r->get('/admin/statistiken', [Admin\PrefsController::class, 'stats']);
    $r->get('/admin/funktionen', [Admin\FeaturesController::class, 'index']);
    $r->post('/admin/funktionen/funktion', [Admin\FeaturesController::class, 'feature']);
    $r->post('/admin/funktionen/erweiterung', [Admin\FeaturesController::class, 'extension']);
    $r->post('/admin/funktionen/freigabe', [Admin\FeaturesController::class, 'delegate']);
    $r->get('/admin/system', [Admin\SystemController::class, 'edit']);
    $r->post('/admin/system', [Admin\SystemController::class, 'save']);
    $r->post('/admin/system/testmail', [Admin\SystemController::class, 'testMail']);
    $r->post('/admin/system/keys', [Admin\SystemController::class, 'generateKeys']);
    $r->post('/admin/system/cache', [Admin\SystemController::class, 'clearCache']);
    $r->post('/admin/system/icon-preview', [Admin\SystemController::class, 'iconPreview']);
    // Schriften: Google-Fonts-Katalog → selbst gehostet unter public/assets/fonts/installed (Core\Fonts)
    $r->get('/admin/system/fonts', [Admin\FontsController::class, 'index']);
    $r->post('/admin/system/fonts/install', [Admin\FontsController::class, 'install']);
    $r->get('/admin/system/fonts/preview/{id}', [Admin\FontsController::class, 'preview']);
    $r->post('/admin/system/fonts/{id}/remove', [Admin\FontsController::class, 'remove']);
    $r->post('/admin/system/fonts/{id}/preload', [Admin\FontsController::class, 'preload']);
    // Kits: installierte Kits, Kit-Paket (ZIP) hochladen, hochgeladene entfernen (Core\KitPackages)
    $r->get('/admin/system/kits', [Admin\KitsController::class, 'index']);
    $r->post('/admin/system/kits/install', [Admin\KitsController::class, 'install']);
    $r->post('/admin/system/kits/{name}/remove', [Admin\KitsController::class, 'remove']);
    // Domain: Domains, Hauptadresse, Weiterleitung, Umgebung – nur Einzel-Installation ohne Netzwerk (Core\Domains)
    $r->get('/admin/system/domain', [Admin\DomainController::class, 'index']);
    $r->post('/admin/system/domain/add', [Admin\DomainController::class, 'add']);
    $r->post('/admin/system/domain/remove', [Admin\DomainController::class, 'remove']);
    $r->post('/admin/system/domain/check', [Admin\DomainController::class, 'check']);
    $r->post('/admin/system/domain/primary', [Admin\DomainController::class, 'primary']);
    $r->post('/admin/system/domain/redirect', [Admin\DomainController::class, 'redirect']);
    $r->post('/admin/system/environment', [Admin\DomainController::class, 'environment']);   // Grundeinstellungen → Umgebung (jede Website, auch im Netzwerk)
    // Website-Suche und KI (Grundeinstellungen → Suche / KI)
    $r->post('/admin/system/search/rebuild', [Admin\AiSearchController::class, 'rebuild']);
    $r->post('/admin/system/search/misses-clear', [Admin\AiSearchController::class, 'missesClear']);
    $r->post('/admin/system/ai/test', [Admin\AiSearchController::class, 'test']);
    $r->post('/admin/system/ai/provider', [Admin\AiSearchController::class, 'provider']);
    // KI-Assistent der Redaktion (Core\AI\Assist, resources/js/_ai.js) und SEO-Übersicht – Vorschläge, gespeichert wird erst nach Prüfung
    $ai = Admin\AiController::class;
    $r->get('/admin/ai', [$ai, 'areaIndex']);                  // Bereich „KLXM AI“ (Marke: config 'ai_brand')
    $r->get('/admin/ai/texte', [$ai, 'areaWrite']);
    $r->get('/admin/ai/uebersetzen', [$ai, 'areaTranslate']);
    $r->get('/admin/ai/seo', [$ai, 'overview']);
    $r->get('/admin/ai/alt-texte', [$ai, 'areaAlt']);
    $r->get('/admin/ai/untertitel', [$ai, 'areaCaptions']);   // Videos ohne Untertitel + Transkriptions-Aufträge
    $r->get('/admin/ai/tabellen', [$ai, 'areaTables']);
    $r->get('/admin/ai/seiten', [$ai, 'areaPages']);
    $r->post('/admin/api/ai/table-propose', [$ai, 'tablePropose']);
    $r->post('/admin/api/ai/table-create', [$ai, 'tableCreate']);
    $r->post('/admin/api/ai/page-propose', [$ai, 'pagePropose']);
    $r->post('/admin/api/ai/page-create', [$ai, 'pageCreate']);
    $r->get('/admin/ai/verlauf', [$ai, 'areaHistory']);
    // Assistent der Redaktion (Funktion „chat.assistant“, Core\AI\Assistant): Frage (SSE/JSON), Aktion ausführen, Verlauf
    $as = Admin\AssistantController::class;
    $r->get('/admin/ai/assistent', [$as, 'page']);
    $r->post('/admin/api/assistant/ask', [$as, 'ask']);
    $r->post('/admin/api/assistant/execute', [$as, 'execute']);
    $r->get('/admin/api/assistant/history', [$as, 'history']);
    $r->get('/admin/api/assistant/history/{id}', [$as, 'show']);
    $r->post('/admin/api/assistant/save', [$as, 'save']);
    $r->post('/admin/api/assistant/history/{id}/delete', [$as, 'delete']);
    // Prüf-Ebene „Eingereicht“: Änderungen über API, MCP und KI prüfen und freigeben (Core\Review\Queue)
    $rv = Admin\ReviewController::class;
    $r->get('/admin/ai/eingereicht', [$rv, 'index']);
    $r->post('/admin/ai/eingereicht', [$rv, 'bulk']);
    $r->post('/admin/ai/eingereicht/einstellungen', [$rv, 'settings']);
    $r->get('/admin/ai/eingereicht/{id}', [$rv, 'show']);
    $r->get('/admin/ai/eingereicht/{id}/vorschau', [$rv, 'preview']);
    $r->post('/admin/ai/eingereicht/{id}/{action}', [$rv, 'action']);
    $r->get('/admin/ai/einstellungen', [$ai, 'areaSettings']);
    $r->get('/admin/seo', fn() => \Core\Http\Response::redirect(url('/admin/ai/seo'), 301));
    $r->post('/admin/api/ai/applied', [$ai, 'applied']);
    $r->get('/admin/api/ai/page-fields/{id}', [$ai, 'pageFields']);
    $r->post('/admin/api/ai/page-insert', [$ai, 'pageInsert']);
    $r->get('/admin/api/ai/status', [$ai, 'status']);
    $r->post('/admin/api/ai/text', [$ai, 'text']);
    $r->post('/admin/api/ai/translate', [$ai, 'translate']);
    $r->post('/admin/api/ai/seo', [$ai, 'seo']);
    $r->post('/admin/api/ai/seo-apply', [$ai, 'seoApply']);
    $r->get('/admin/api/ai/seo-check/{id}', [$ai, 'seoCheck']);
    $r->get('/admin/api/ai/page-translate/{id}', [$ai, 'pageTranslateItems']);
    $r->post('/admin/api/ai/page-translate/{id}', [$ai, 'pageTranslateApply']);
    $r->post('/admin/api/ai/alt', [$ai, 'alt']);
    $r->post('/admin/api/ai/alt-apply', [$ai, 'altApply']);
    $r->post('/admin/api/ai/summary', [$ai, 'summary']);
    $r->post('/admin/api/ai/support-reply', [$ai, 'supportReply']);
    $r->post('/admin/api/ai/kb-polish', [$ai, 'kbPolish']);
    $r->post('/admin/api/ai/schema', [$ai, 'schema']);

    $r->get('/admin/media', [Admin\MediaController::class, 'index']);
    $r->post('/admin/media/upload', [Admin\MediaController::class, 'upload']);
    $r->post('/admin/media/chunk', [Admin\MediaController::class, 'chunk']);
    $r->post('/admin/media/finalize', [Admin\MediaController::class, 'finalize']);
    $r->post('/admin/media/{id}/delete', [Admin\MediaController::class, 'delete']);
    $r->get('/admin/api/media', [Admin\MediaController::class, 'list']);
    $r->post('/admin/api/media-use', [Admin\MediaController::class, 'useShared']);
    $r->post('/admin/api/media-share', [Admin\MediaController::class, 'share']);
    $r->post('/admin/api/media-bulk', [Admin\MediaController::class, 'bulk']);
    $r->get('/admin/api/media/{id}', [Admin\MediaController::class, 'detail']);
    $r->get('/admin/api/media/{id}/thumb', [Admin\MediaController::class, 'thumb']);   // Vorschaubild eines Videos (lazy, Core\VideoThumbs)
    $r->post('/admin/api/media/{id}', [Admin\MediaController::class, 'save']);
    $r->post('/admin/api/media/{id}/delete', [Admin\MediaController::class, 'delete']);
    $r->post('/admin/api/media/{id}/crop', [Admin\MediaController::class, 'crop']);
    $r->post('/admin/api/media/{id}/adjust', [Admin\MediaController::class, 'adjust']);   // Bild anpassen (Core\ImageFx)
    $r->post('/admin/api/media/{id}/fit', [Admin\MediaController::class, 'fit']);   // Bild im Rahmen: Standard des Bildes (Core\ImageFit)
    $r->get('/admin/api/media/fit/options', [Admin\MediaController::class, 'fitOptions']);   // Bild im Rahmen: Farben des Kits
    $r->post('/admin/api/media/{id}/edit', [Admin\MediaController::class, 'edit']);   // Bild bearbeiten (Core\ImageEdit)
    // Untertitel, Kapitel, Transkripte für Video/Audio (Core\MediaTracks) + KI-Transkription/Übersetzung (Core\AI\MediaJobs)
    $mt = Admin\MediaTrackController::class;
    $r->get('/admin/api/media/{id}/tracks', [$mt, 'index']);
    $r->post('/admin/api/media/{id}/tracks', [$mt, 'store']);
    $r->get('/admin/api/media/{id}/tracks/{tid}', [$mt, 'show']);
    $r->post('/admin/api/media/{id}/tracks/{tid}', [$mt, 'update']);
    $r->post('/admin/api/media/{id}/tracks/{tid}/delete', [$mt, 'delete']);
    $r->get('/admin/api/media/{id}/tracks/{tid}/download', [$mt, 'download']);
    $r->post('/admin/api/media/{id}/tracks/{tid}/translate', [$mt, 'translate']);
    $r->post('/admin/api/media/{id}/transcript', [$mt, 'transcript']);
    $r->post('/admin/api/media/{id}/transcribe', [$mt, 'transcribe']);
    $r->get('/admin/api/media-jobs', [$mt, 'jobs']);
    $r->post('/admin/api/media-jobs/{jid}/cancel', [$mt, 'cancel']);
    $r->post('/admin/api/collections', [Admin\MediaController::class, 'collectionCreate']);
    $r->post('/admin/api/collections/{id}', [Admin\MediaController::class, 'collectionUpdate']);
    $r->post('/admin/api/collections/{id}/delete', [Admin\MediaController::class, 'collectionDelete']);

    // Anfragen = Eingangs-Tabellen (verschlüsselt, Core\Data\Inbox)
    $r->get('/admin/requests', [Admin\InboxController::class, 'index']);
    $r->post('/admin/requests', [Admin\InboxController::class, 'index']);
    $r->get('/admin/requests/log', [Admin\InboxController::class, 'log']);
    $r->post('/admin/requests/alerts/clear', [Admin\InboxController::class, 'clearAlerts']);
    $r->post('/admin/requests/{table}/{id}/status', [Admin\InboxController::class, 'status']);
    $r->post('/admin/requests/{table}/{id}/assign', [Admin\InboxController::class, 'assign']);
    $r->post('/admin/requests/{table}/{id}/delete', [Admin\InboxController::class, 'delete']);

    $r->get('/admin/users', [Admin\UserController::class, 'index']);
    $r->get('/admin/users/{section}', [Admin\UserController::class, 'section']);   // Personen, Einladen & Anlegen, Rollen, Anmeldung & Sicherheit
    $r->post('/admin/users', [Admin\UserController::class, 'store']);
    $r->post('/admin/users/{id}/delete', [Admin\UserController::class, 'delete']);
    $r->post('/admin/users/{id}/2fa-reset', [Admin\UserController::class, 'resetTwoFactor']);
    $r->post('/admin/users/{id}/email', [Admin\UserController::class, 'changeEmail']);   // ohne Bestätigung, Hinweis an beide Adressen
    // Einladungen (Core\Invites): Person einladen, erneut senden, zurückziehen; öffentlich annehmen (Passkey und/oder Passwort)
    $r->post('/admin/users/invite', [Admin\InviteController::class, 'store']);
    $r->post('/admin/users/invites/{id}/resend', [Admin\InviteController::class, 'resend']);
    $r->post('/admin/users/invites/{id}/revoke', [Admin\InviteController::class, 'revoke']);
    $r->get('/admin/einladung/{token}', [Admin\InviteController::class, 'show']);
    $r->post('/admin/einladung/{token}', [Admin\InviteController::class, 'accept']);
    $r->post('/admin/einladung/{token}/passkey/options', [Admin\InviteController::class, 'passkeyOptions']);
    $r->post('/admin/einladung/{token}/passkey', [Admin\InviteController::class, 'passkeyStore']);
    $r->post('/admin/account/locale', [Admin\UserController::class, 'saveLocale']);
    // Favoriten je Benutzer (Core\Favorites): Stern, Seitenleiste, Konto-Seite
    $r->post('/admin/api/favorites', [Admin\FavoriteController::class, 'add']);
    $r->post('/admin/api/favorites/remove', [Admin\FavoriteController::class, 'remove']);
    $r->post('/admin/api/favorites/rename', [Admin\FavoriteController::class, 'rename']);
    $r->post('/admin/api/favorites/reorder', [Admin\FavoriteController::class, 'reorder']);
    $r->post('/admin/users/{id}/role', [Admin\RoleController::class, 'assign']);
    $r->get('/admin/roles/new', [Admin\RoleController::class, 'edit']);
    $r->post('/admin/roles', [Admin\RoleController::class, 'save']);
    $r->get('/admin/roles/{key}', [Admin\RoleController::class, 'edit']);
    $r->post('/admin/roles/{key}', [Admin\RoleController::class, 'save']);
    $r->post('/admin/roles/{key}/delete', [Admin\RoleController::class, 'delete']);

    // Editor-API (JSON)
    // Versionen von Seiten und Einträgen (resources/js/versions.mjs)
    $r->get('/admin/api/pages/{id}/versions', [Admin\VersionsController::class, 'pageVersions']);
    $r->get('/admin/pages/{id}/versions/{rev}/vorschau', [Admin\VersionsController::class, 'pagePreview']);
    $r->get('/admin/api/data/{handle}/{id}/versions', [Admin\VersionsController::class, 'entryVersions']);
    $r->post('/admin/api/data/{handle}/{id}/versions/{rev}/restore', [Admin\VersionsController::class, 'entryRestore']);
    $r->get('/admin/api/pages/tree', [Admin\PageController::class, 'apiTree']);       // „Neue Seite“ auf der Website (Core\PageTool)
    $r->post('/admin/api/pages/create', [Admin\PageController::class, 'apiCreate']);
    $r->get('/admin/api/pages/{id}/settings', [Admin\PageController::class, 'apiSettings']);   // „Seiteneinstellungen“ auf der Website (Core\PageSettingsTool)
    $r->post('/admin/api/pages/{id}/settings', [Admin\PageController::class, 'apiSettingsSave']);
    $r->post('/admin/api/pages/{id}/save', [Admin\EditorController::class, 'save']);
    $r->post('/admin/api/pages/{id}/discard', [Admin\EditorController::class, 'discard']);
    $r->post('/admin/api/preview', [Admin\EditorController::class, 'preview']);
    $r->post('/admin/api/block-form', [Admin\EditorController::class, 'form']);
    $r->get('/admin/api/links', [Admin\EditorController::class, 'links']);
    // Einträge auf der Website bearbeiten (Seitenleiste + direkt im Text, Core\Data\EntryEdit)
    $r->get('/admin/api/entries/{handle}/{id}', [Admin\EntryEditController::class, 'form']);
    $r->post('/admin/api/entries/{handle}/{id}', [Admin\EntryEditController::class, 'save']);
    // Felder eines Formulars im Seiten-Editor bearbeiten (Seitenleiste „Felder bearbeiten“, Core\Data\SchemaPanel) – Recht data.schema
    $r->get('/admin/api/formfields/{handle}', [Admin\FormFieldsController::class, 'form']);
    $r->post('/admin/api/formfields/{handle}', [Admin\FormFieldsController::class, 'save']);

    $r->get('/admin/api-tokens', [Admin\ApiTokenController::class, 'index']);
    $r->post('/admin/api-tokens', [Admin\ApiTokenController::class, 'store']);
    $r->post('/admin/api-tokens/{id}/delete', [Admin\ApiTokenController::class, 'delete']);
    $r->post('/admin/api-tokens/{id}/mode', [Admin\ApiTokenController::class, 'mode']);
    $r->get('/admin/hilfe', [Admin\HelpController::class, 'manual']);
    $r->get('/admin/hilfe/technik', [Admin\HelpController::class, 'technical']);
    $r->get('/admin/hilfe/symbole', [Admin\HelpController::class, 'icons']);
    $r->get('/admin/hilfe/lizenzen', [Admin\HelpController::class, 'licenses']);   // Lizenzen & Danksagungen (THIRD-PARTY-NOTICES.md)
    // Tutorials als Text (app/Admin/tutorials.php, Core\Tutorials); Videos auf der Produkt-Website (config docs_url, Aufnahme tools/tutorials/record.mjs)
    $r->get('/admin/hilfe/tutorials', [Admin\HelpController::class, 'tutorials']);
    $r->get('/admin/hilfe/tutorials/{slug}', [Admin\HelpController::class, 'tutorial']);
    // Hinweise zu diesem Projekt (Core\Guide): Kit kits/{kit}/guide/*.md + Website {storage}/guide/*.md – Bearbeiten mit system.manage
    $gd = Admin\GuideController::class;
    $r->get('/admin/hilfe/projekt', [$gd, 'index']);
    $r->get('/admin/hilfe/projekt/neu', [$gd, 'edit']);
    $r->post('/admin/hilfe/projekt/neu', [$gd, 'save']);
    $r->get('/admin/hilfe/projekt/bild/{file}', [$gd, 'image']);
    $r->get('/admin/hilfe/projekt/{key}', [$gd, 'edit']);
    $r->post('/admin/hilfe/projekt/{key}', [$gd, 'save']);
    $r->post('/admin/hilfe/projekt/{key}/loeschen', [$gd, 'delete']);

    // Support & Wissensdatenbank (zentral für alle Websites der Installation – Core\Support)
    $sp = Admin\SupportController::class;
    $r->get('/admin/support', [$sp, 'mine']);
    $r->get('/admin/support/alle', [$sp, 'all']);
    $r->post('/admin/support/einstellungen', [$sp, 'prefs']);
    $r->get('/admin/support/neu', [$sp, 'create']);
    $r->post('/admin/support/neu', [$sp, 'store']);
    $r->get('/admin/support/meldung/{id}', [$sp, 'issue']);
    $r->post('/admin/support/meldung/{id}', [$sp, 'reply']);
    $r->post('/admin/support/meldung/{id}/status', [$sp, 'status']);
    $r->post('/admin/support/meldung/{id}/zuweisen', [$sp, 'assign']);
    $r->post('/admin/support/meldung/{id}/loeschen', [$sp, 'issueDelete']);
    $r->get('/admin/support/meldung/{id}/wissen', [$sp, 'publishForm']);
    $r->get('/admin/support/datei/{id}', [$sp, 'file']);
    $r->get('/admin/support/wissen', [$sp, 'kb']);
    $r->get('/admin/support/wissen/neu', [$sp, 'articleEdit']);
    $r->post('/admin/support/wissen/neu', [$sp, 'articleSave']);
    $r->get('/admin/support/wissen/{id}', [$sp, 'article']);
    $r->get('/admin/support/wissen/{id}/bearbeiten', [$sp, 'articleEdit']);
    $r->post('/admin/support/wissen/{id}/bearbeiten', [$sp, 'articleSave']);
    $r->post('/admin/support/wissen/{id}/hilfreich', [$sp, 'helpful']);
    $r->post('/admin/support/wissen/{id}/version/{rev}', [$sp, 'articleRestore']);
    $r->post('/admin/support/wissen/{id}/loeschen', [$sp, 'articleDelete']);
    $r->get('/admin/support/tags', [$sp, 'tags']);
    $r->get('/admin/support/fragen', [$sp, 'questions']);
    $r->get('/admin/support/fragen/neu', [$sp, 'ask']);
    $r->post('/admin/support/fragen/neu', [$sp, 'askSave']);
    $r->get('/admin/support/fragen/{id}', [$sp, 'question']);
    $r->post('/admin/support/fragen/{id}', [$sp, 'answerSave']);
    $r->get('/admin/support/fragen/{id}/bearbeiten', [$sp, 'ask']);
    $r->post('/admin/support/fragen/{id}/bearbeiten', [$sp, 'askSave']);
    $r->post('/admin/support/fragen/{id}/stimme', [$sp, 'voteQuestion']);
    $r->post('/admin/support/fragen/{id}/loeschen', [$sp, 'questionDelete']);
    $r->post('/admin/support/antwort/{id}', [$sp, 'answerUpdate']);
    $r->post('/admin/support/antwort/{id}/stimme', [$sp, 'voteAnswer']);
    $r->post('/admin/support/antwort/{id}/akzeptieren', [$sp, 'accept']);
    $r->post('/admin/support/antwort/{id}/loeschen', [$sp, 'answerDelete']);
    $r->get('/admin/api/support/similar', [$sp, 'similar']);

    // Chat zwischen Benutzern (Core\Chat, optional – Funktion „chat“): Seiten, Kanäle, Bilder, JSON + Server-Sent Events
    $uc = Admin\ChatController::class;
    $r->get('/admin/chat', [$uc, 'page']);
    $r->get('/admin/chat/einstellungen', [$uc, 'settings']);
    $r->post('/admin/chat/einstellungen', [$uc, 'saveSettings']);
    $r->post('/admin/chat/kanaele', [$uc, 'channelSave']);
    $r->post('/admin/chat/kanaele/{id}', [$uc, 'channelUpdate']);
    $r->post('/admin/chat/kanaele/{id}/archivieren', [$uc, 'channelArchive']);
    $r->get('/admin/chat/datei/{id}', [$uc, 'file']);
    $r->get('/admin/api/chat/state', [$uc, 'state']);
    $r->get('/admin/api/chat/unread', [$uc, 'unread']);
    $r->get('/admin/api/chat/people', [$uc, 'people']);
    $r->post('/admin/api/chat/prefs', [$uc, 'prefs']);
    $r->post('/admin/api/chat/dm', [$uc, 'dm']);
    $r->get('/admin/api/chat/stream', [$uc, 'stream']);
    $r->get('/admin/api/chat/poll', [$uc, 'poll']);
    $r->get('/admin/api/chat/rooms/{id}/messages', [$uc, 'messages']);
    $r->post('/admin/api/chat/rooms/{id}/messages', [$uc, 'send']);
    $r->post('/admin/api/chat/rooms/{id}/upload', [$uc, 'upload']);
    $r->post('/admin/api/chat/rooms/{id}/read', [$uc, 'read']);
    $r->post('/admin/api/chat/messages/{id}/edit', [$uc, 'edit']);
    $r->post('/admin/api/chat/messages/{id}/delete', [$uc, 'delete']);
    $r->post('/admin/api/chat/messages/{id}/react', [$uc, 'react']);

    // ---------------------------------------------------------------- REST-API v1
    $r->get('/api/v1', [Api::class, 'index']);
    $r->get('/api/v1/openapi.json', [Api::class, 'openapi']);
    $r->get('/api/v1/public', [Api::class, 'publicInfo']);
    $r->get('/api/v1/me', [Api::class, 'me']);
    $r->get('/api/v1/settings', [Api::class, 'settings']);
    $r->patch('/api/v1/settings', [Api::class, 'settingsUpdate']);
    $r->get('/api/v1/settings/schema', [Api::class, 'settingsSchema']);
    $r->get('/api/v1/design', [Api::class, 'design']);
    $r->patch('/api/v1/design', [Api::class, 'designUpdate']);
    $r->get('/api/v1/hours', [Api::class, 'hours']);
    $r->get('/api/v1/landings', [Api::class, 'landings']);   // Landingpages mit eigenen Domains (nur lesen)
    $r->get('/api/v1/redirects', [Api::class, 'redirects']);   // Weiterleitungen (Core\Redirects, nur lesen)
    $r->put('/api/v1/hours', [Api::class, 'hoursUpdate']);
    $r->put('/api/v1/notice', [Api::class, 'notice']);
    $r->get('/api/v1/block-types', [Api::class, 'blockTypes']);
    $r->get('/api/v1/pages', [Api::class, 'pages']);
    $r->post('/api/v1/pages', [Api::class, 'pageCreate']);
    $r->get('/api/v1/pages/{page}', [Api::class, 'page']);
    $r->patch('/api/v1/pages/{page}', [Api::class, 'pageUpdate']);
    $r->delete('/api/v1/pages/{page}', [Api::class, 'pageDelete']);
    $r->post('/api/v1/pages/{page}/publish', [Api::class, 'publish']);
    $r->post('/api/v1/pages/{page}/translate', [Api::class, 'pageTranslate']);
    $r->post('/api/v1/pages/{page}/discard', [Api::class, 'discard']);
    $r->get('/api/v1/pages/{page}/revisions', [Api::class, 'revisions']);
    $r->post('/api/v1/pages/{page}/revisions/{rev}/restore', [Api::class, 'restore']);
    $r->get('/api/v1/pages/{page}/blocks', [Api::class, 'blocks']);
    $r->put('/api/v1/pages/{page}/blocks', [Api::class, 'blocksReplace']);
    $r->post('/api/v1/pages/{page}/blocks', [Api::class, 'blockAdd']);
    $r->patch('/api/v1/pages/{page}/blocks/{block}', [Api::class, 'blockUpdate']);
    $r->delete('/api/v1/pages/{page}/blocks/{block}', [Api::class, 'blockDelete']);
    $r->post('/api/v1/pages/{page}/blocks/{block}/move', [Api::class, 'blockMove']);
    $r->get('/api/v1/media', [Api::class, 'media']);
    $r->get('/api/v1/media-meta', [Api::class, 'mediaMeta']);
    $r->post('/api/v1/media/{id}/crop', [Api::class, 'mediaCrop']);
    $r->post('/api/v1/media', [Api::class, 'mediaUpload']);
    $r->patch('/api/v1/media/{id}', [Api::class, 'mediaUpdate']);
    $r->delete('/api/v1/media/{id}', [Api::class, 'mediaDelete']);
    $r->get('/api/v1/data', [Api::class, 'dataTables']);
    $r->get('/api/v1/data/{table}', [Api::class, 'dataEntries']);
    $r->post('/api/v1/data/{table}', [Api::class, 'dataCreate']);
    $r->get('/api/v1/data/{table}/occurrences', [Api::class, 'dataOccurrences']);
    // Geteilte Tabellen: Vorschläge lesen, Auswahl dieser Website setzen (übernehmen, ablehnen, hervorheben, ausblenden)
    $r->get('/api/v1/data/{table}/suggestions', [Api::class, 'dataSuggestions']);
    $r->post('/api/v1/data/{table}/picks', [Api::class, 'dataPick']);
    $r->get('/api/v1/data/{table}/{id}', [Api::class, 'dataEntry']);
    $r->post('/api/v1/data/{table}/{id}/translate', [Api::class, 'dataTranslate']);
    $r->get('/api/v1/geocode', [Api::class, 'geocode']);
    $r->patch('/api/v1/data/{table}/{id}', [Api::class, 'dataUpdate']);
    $r->delete('/api/v1/data/{table}/{id}', [Api::class, 'dataDelete']);
    $r->get('/api/v1/kb', [\Core\Http\Controllers\SupportApiController::class, 'kb']);   // Wissensdatenbank (Core\Support)
    $r->get('/api/v1/search', [Api::class, 'search']);   // Website-Suche wie für Besucher (Core\Search)
    $r->get('/api/v1/requests', [Api::class, 'requests']);
    $r->patch('/api/v1/requests/{id}', [Api::class, 'requestUpdate']);
    // Prüf-Ebene (Core\Review): Stand eingereichter Änderungen dieses Tokens
    $r->get('/api/v1/changes', [Api::class, 'changes']);
    $r->get('/api/v1/changes/{id}', [Api::class, 'change']);

    // ---------------------------------------------------------------- MCP (Streamable HTTP)
    $r->post('/mcp', [McpController::class, 'handle']);
    $r->get('/mcp', [McpController::class, 'get']);
    $r->delete('/mcp', [McpController::class, 'get']);

    // ---------------------------------------------------------------- Öffentlich
    // Besucher-Chat (Funktion „chat.visitor“, Core\AI\VisitorChat): Texte + Antwort (Server-Sent Events bzw. JSON)
    $r->get('/api/chat/config', [\Core\Http\Controllers\VisitorChatController::class, 'config']);
    $r->post('/api/chat', [\Core\Http\Controllers\VisitorChatController::class, 'ask']);
    // Live-Aktualisierung (Core\Live): Versionen per Server-Sent Events, Block der veröffentlichten Seite neu rendern
    $r->get('/api/live', fn(\Core\Http\Request $req) => \Core\Live::stream($req));
    $r->get('/api/live/block', fn(\Core\Http\Request $req) => \Core\Live::block($req));
    $r->get('/pdf/{id}', [PdfController::class, 'show']);
    $r->get('/pdf/pool/{pool}/{id}', [PdfController::class, 'showPool']);
    $r->get('/favicon.ico', [PwaController::class, 'icon']);
    $r->get('/apple-touch-icon.png', [PwaController::class, 'icon']);
    $r->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
    $r->get('/sw.js', [PwaController::class, 'serviceWorker']);
    $r->get('/offline', [PwaController::class, 'offline']);
    $r->get('/proxy/{source}/{path*}', [ProxyController::class, 'handle']);
    // Geschützte Medien-Pools (Core\MediaPools::isProtected): nur mit Zugriff
    $r->get(\Core\MediaPools::PROTECTED_PATH . '/{pool}/{path*}', [\Core\Http\Controllers\ProtectedMediaController::class, 'serve']);
    $r->get('/sitemap.xml', [SiteController::class, 'sitemap']);
    // Rechtstexte im Dialog (Datenschutzhinweise an Formularen, Core\LegalDialog)
    $r->get('/_legal/{kind}', fn(\Core\Http\Request $req, string $kind) => \Core\LegalDialog::handle($req, $kind));
    // Glossar: Begriffe für Inhalte, die erst im Browser entstehen (resources/js/glossary-live.mjs) – nur veröffentlichte
    $r->get(\Core\Glossary\Glossary::JSON_PATH, fn(\Core\Http\Request $req) => \Core\Glossary\Glossary::jsonResponse($req));
    $r->get('/robots.txt', [SiteController::class, 'robots']);
    $r->get('/llms.txt', [SiteController::class, 'llms']);   // Core\Indexing (nur wenn eingeschaltet)
    // Theme-Formulare (z. B. /anfrage/rezept) – dahinter die Eingangs-Tabelle des Formulars
    $r->get('/api/form/{form}', [FormController::class, 'challenge']);
    $r->get('/anfrage/{form}', [FormController::class, 'show']);
    $r->post('/anfrage/{form}', [FormController::class, 'submit']);
    // Formulare für Datentabellen (Funktion „forms.data“; Block „data_form“)
    $r->get('/formular/{table}/challenge', [\Core\Http\Controllers\DataFormController::class, 'challenge']);
    $r->get('/formular/{table}', [\Core\Http\Controllers\DataFormController::class, 'show']);
    $r->post('/formular/{table}', [\Core\Http\Controllers\DataFormController::class, 'submit']);
    // Zustand für Deploy-Prüfungen (ohne Geheimnisse)
    $r->get('/health', function () {
        $ok = true;
        try { app()->db->fetchValue('SELECT 1'); } catch (\Throwable) { $ok = false; }
        $ok = $ok && is_writable(site()->storage()) && is_writable(site()->mediaDir());
        $data = ['ok' => $ok, 'version' => CMS_VERSION, 'environment' => environment()];
        // Selbsttest einer Landing-Domain (Verwaltung → Landingpages → Status prüfen): Kennung nur mit passendem Prüf-Token
        $lp = \Core\Landings::current();
        if ($lp && is_string($t = app()->request?->query['landing'] ?? null) && hash_equals(\Core\Landings::healthToken($lp), $t)) $data['landing'] = $lp->id;
        // Erreichbarkeit einer Domain (System → Domain, Core\Domains::check): Antwort nur zur gerade gestellten Frage
        if (is_string($q = app()->request?->query['domain_check'] ?? null) && $q !== '' && ($a = \Core\Domains::answer($q)) !== null) $data['domain_check'] = $a;
        return (new \Core\Http\Response(json_encode($data), $ok ? 200 : 503,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']));
    });
    // Kalender: iCal-Feed und einzelne Termine (Funktion „calendar“, nur Tabellen mit Kalender + Feed)
    $r->get('/kalender/{handle}.ics', [\Core\Http\Controllers\CalendarController::class, 'feed']);
    $r->get('/kalender/{handle}/{slug}.ics', [\Core\Http\Controllers\CalendarController::class, 'event']);
    // Visitenkarten (Core\VCard, vCard 3.0): Organisation der Website und Personen aus Tabellen mit Schema-Typ „Person“
    $r->get(\Core\VCard::ORG_PATH, [\Core\Http\Controllers\VCardController::class, 'org']);
    $r->get('/vcard/{handle}/{slug}.vcf', [\Core\Http\Controllers\VCardController::class, 'person']);
    // Website-Suche (Funktion „search“, Core\Search): /suche, /en/search …, Vorschläge als JSON – Seiten mit gleichem Pfad haben Vorrang
    $r->get(\Core\Search\Search::routePattern(true), [\Core\Http\Controllers\SearchPageController::class, 'suggest']);
    $r->get(\Core\Search\Search::routePattern(), [\Core\Http\Controllers\SearchPageController::class, 'show']);
    // Routen aktiver Erweiterungen (vor den Seiten-Routen)
    \Core\Extensions::routes($r);
    $r->get('/', [SiteController::class, 'home']);
    $r->get('/{path*}', [SiteController::class, 'page']);
};
