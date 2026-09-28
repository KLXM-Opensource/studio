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

// Alte Asset-Adressen ganz oben (/kits/…, /themes/…, /extensions/…, /fonts/… – vor der Ablage unter /assets/): dauerhaft
// auf /assets/… umleiten, sobald die Datei dort liegt – ohne die App zu starten (zwischengespeicherte Seiten und Stylesheets,
// Lesezeichen, fremde Verweise). Gibt es dort keine Datei, geht es normal weiter (eine Seite /kits bleibt erreichbar).
// Liegen die Ordner noch am alten Ort, liefert der Webserver die Datei vorher selbst aus. Ablage: Core\PublicPaths.
$__path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$__base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && preg_match('~^/(kits|themes|extensions|fonts)/~i', $__rel = substr($__path, strlen($__base)))) {
    require_once dirname(__DIR__) . '/app/PublicPaths.php';
    if (str_starts_with($__path, $__base . '/') && ($__to = Core\PublicPaths::legacyRedirect($__rel, __DIR__)) !== null) {
        $__q = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        header('Location: ' . $__base . $__to . ($__q !== '' ? '?' . $__q : ''), true, 301);
        header('Cache-Control: public, max-age=604800');
        exit;
    }
}
unset($__path, $__base, $__rel, $__to, $__q);

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->handle(Core\Http\Request::fromGlobals())->send();
