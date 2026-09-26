<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
/**
 * Front-Controller. Einzige PHP-Datei im Webroot.
 * Alles Weitere (app/, config/, storage/, themes/, vendor/) liegt eine Ebene höher
 * und ist damit ohne .htaccess vor direktem Zugriff geschützt.
 */
declare(strict_types=1);

// PHP-Entwicklungsserver: statische Dateien direkt ausliefern
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->handle(Core\Http\Request::fromGlobals())->send();
