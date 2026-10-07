<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
/**
 * Front-Controller. Einzige PHP-Datei im Webroot.
 * Alles Weitere (app/, config/, storage/, kits/, vendor/) liegt eine Ebene höher
 * und ist damit ohne .htaccess vor direktem Zugriff geschützt.
 */
declare(strict_types=1);

// Mehr Formularfelder als max_input_vars (bzw. Teile als max_multipart_body_parts)? PHP meldet das nur als Warnung beim Start der Anfrage – hier festhalten, bevor eine
// spätere (unterdrückte) Meldung error_get_last() überschreibt (Core\Http\Request::overflow)
define('CMS_INPUT_OVERFLOW', (bool) preg_match('~Input variables exceeded|body parts limit exceeded~', (string) (error_get_last()['message'] ?? '')));

// PHP-Entwicklungsserver: statische Dateien direkt ausliefern
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->handle(Core\Http\Request::fromGlobals())->send();
