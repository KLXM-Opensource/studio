<?php
// SPDX-License-Identifier: MIT
/**
 * Mini-Server für die Testdaten der externen Quellen (nur Entwicklung):
 *   php -S 127.0.0.1:8097 tools/fixtures/sources/server.php
 * In der Website-Konfiguration zusätzlich: 'environment' => 'development', 'sources_allow_private' => ['127.0.0.1:8097']
 *   /rss.xml[?v=2]  /atom.xml  /items.json  /products.xml  /openimmo.xml  /openimmo.zip[?v=2]  /img/*.jpg|png
 *   /xxe.xml  /laughs.xml  /big.xml (6 MB)  /wrongtype  /redirect-local (→ 127.0.0.1:8089)  /slow (20 s)
 */
$dir = __DIR__;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$v2 = ($_GET['v'] ?? '') === '2';
$send = function (string $type, string $body) { header('Content-Type: ' . $type); echo $body; };
switch ($path) {
    case '/rss.xml':
        $x = file_get_contents("$dir/rss.xml");
        if ($v2) {
            $x = preg_replace('~<item>\s*<title>Herbstmarkt.*?</item>~s', '', $x);
            $x = str_replace('Baustelle an der Hauptstraße &amp; Umleitung', 'Baustelle an der Hauptstraße verlängert', $x);
        }
        $send('application/rss+xml; charset=utf-8', $x);
        return true;
    case '/atom.xml': $send('application/atom+xml', file_get_contents("$dir/atom.xml")); return true;
    case '/items.json': $send('application/json', file_get_contents("$dir/items.json")); return true;
    case '/products.xml': $send('application/xml', file_get_contents("$dir/products.xml")); return true;
    case '/openimmo.xml': $send('application/xml', file_get_contents("$dir/openimmo/openimmo.xml")); return true;
    case '/openimmo.zip': $send('application/zip', file_get_contents($dir . ($v2 ? '/openimmo-v2.zip' : '/openimmo.zip'))); return true;
    case '/xxe.xml': $send('application/xml', file_get_contents("$dir/xxe.xml")); return true;
    case '/laughs.xml': $send('application/xml', file_get_contents("$dir/laughs.xml")); return true;
    case '/big.xml':
        header('Content-Type: application/rss+xml');
        echo '<?xml version="1.0"?><rss version="2.0"><channel>';
        $item = '<item><title>' . str_repeat('x', 1000) . '</title></item>';
        for ($i = 0; $i < 6200; $i++) echo $item;
        echo '</channel></rss>';
        return true;
    case '/wrongtype': $send('text/html', '<html><body>Keine Daten</body></html>'); return true;
    case '/redirect-local': header('Location: http://127.0.0.1:8089/', true, 302); return true;
    case '/slow': sleep(20); $send('application/xml', '<rss/>'); return true;
}
if (preg_match('~^/img/([\w.-]+\.(jpg|png))$~', $path, $m) && is_file("$dir/img/$m[1]")) {
    $send($m[2] === 'png' ? 'image/png' : 'image/jpeg', file_get_contents("$dir/img/$m[1]"));
    return true;
}
http_response_code(404);
echo 'not found';
return true;
