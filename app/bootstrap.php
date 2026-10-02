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
    $fallback = (require ROOT . '/config/config.php')['fallback_site'] ?? Site::DEFAULT;
    if (is_file(ROOT . '/config/config.local.php')) {
        $local = require ROOT . '/config/config.local.php';
        $fallback = is_array($local) && array_key_exists('fallback_site', $local) ? $local['fallback_site'] : $fallback;
    }
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
