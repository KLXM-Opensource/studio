<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

/*
 * Bootstrap – wird von public/index.php und bin/console eingebunden.
 * Alles außer /public liegt außerhalb des Webroots (kein .htaccess nötig).
 */

define('ROOT', dirname(__DIR__));
define('CMS_NAME', 'KLXM Studio');
define('CMS_SLUG', 'klxm-studio');   // MCP-Serverkennung, Bearer-Realms
define('CMS_VERSION', '1.0.0');

require ROOT . '/vendor/autoload.php';

use Core\App;
use Core\Config;
use Core\Site;
use Core\Sites;

// Multi-Site: Website über die Domain bestimmen (Kommandozeile: --site=key oder CMS_SITE)
if (PHP_SAPI === 'cli') {
    $siteKey = Sites::fromCli($argv);
    $_SERVER['argv'] = $argv;
} else {
    $base = require ROOT . '/config/config.php';
    $local = is_file(ROOT . '/config/config.local.php') ? require ROOT . '/config/config.local.php' : [];
    $local = is_array($local) ? $local : [];
    $fallback = array_key_exists('fallback_site', $local) ? $local['fallback_site'] : ($base['fallback_site'] ?? Site::DEFAULT);
    // Vorgeschalteter Proxy (z. B. nginx im Abonnement des Kunden): Domain, https und Besucher-IP übernehmen (Core\Http\Forwarded)
    Core\Http\Forwarded::apply($_SERVER, (array) ($local['trusted_proxies'] ?? $base['trusted_proxies'] ?? []),
        fn(string $h) => Sites::resolve($h, null) !== null, (bool) ($local['forwarded_host'] ?? $base['forwarded_host'] ?? true));
    $siteKey = Sites::resolve((string) ($_SERVER['HTTP_HOST'] ?? ''), $fallback);
    if ($siteKey === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Diese Domain ist auf diesem Server nicht eingerichtet.\n");
    }
}
if (!isset(Sites::all()[$siteKey])) {
    fwrite(STDERR, "Unbekannte Website „{$siteKey}“. Vorhanden: " . implode(', ', array_keys(Sites::all())) . "\n");
    exit(1);
}

$config = Config::load(ROOT . '/config', $siteKey);

date_default_timezone_set($config->get('timezone', 'Europe/Berlin'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', $config->get('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

return App::boot($config, new Site($siteKey, Sites::all()[$siteKey]));
