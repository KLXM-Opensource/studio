<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
/**
 * Front-Controller. Einzige PHP-Datei im Webroot.
 * Alles Weitere (app/, config/, storage/, kits/, vendor/) liegt eine Ebene höher
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

// Alte Asset-Adressen /themes/{kit}/… (vor der Umbenennung in kits/): dauerhaft auf /kits/{kit}/… umleiten,
// sobald die Datei dort liegt – ohne die App zu starten (zwischengespeicherte Seiten, Lesezeichen, fremde Verweise).
// Liegt ein Kit noch unter public/themes/, liefert der Webserver die Datei vorher selbst aus.
$__path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$__base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if (str_starts_with($__path, $__base . '/themes/') && preg_match('~^/themes/([a-z0-9_\-]+/[^?#]+)$~i', substr($__path, strlen($__base)), $__m)
    && !str_contains($__m[1], '..') && is_file(__DIR__ . '/kits/' . $__m[1])) {
    $__q = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    header('Location: ' . $__base . '/kits/' . $__m[1] . ($__q !== '' ? '?' . $__q : ''), true, 301);
    header('Cache-Control: public, max-age=604800');
    exit;
}
unset($__path, $__base, $__m, $__q);

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->handle(Core\Http\Request::fromGlobals())->send();
