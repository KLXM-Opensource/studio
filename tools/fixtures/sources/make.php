<?php
// SPDX-License-Identifier: MIT
/**
 * Testdaten für „Externe Quellen“ (Core\Sources) erzeugen – kleine Bilder (GD), JPEG mit EXIF-Block (Test: wird entfernt),
 * OpenImmo-ZIPs (vollständig, ohne Objekt „P-3“, mit Zip-Slip-Pfad). Aufruf: php tools/fixtures/sources/make.php
 * Keine echten Daten – alle Inhalte sind erfunden.
 */
$dir = __DIR__;
@mkdir("$dir/img", 0775, true);
$img = function (string $file, int $w, int $h, array $rgb, string $label) {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, (int) ($w * .1), (int) ($h * .55), (int) ($w * .9), (int) ($h * .9), imagecolorallocate($im, max(0, $rgb[0] - 40), max(0, $rgb[1] - 40), max(0, $rgb[2] - 40)));
    imagestring($im, 5, 12, 12, $label, $fg);
    str_ends_with($file, '.png') ? imagepng($im, $file) : imagejpeg($im, $file, 85);
};
// JPEG + EXIF-Block (APP1) mit Kennung – nach dem Import darf sie nicht mehr in der Datei stehen
$withExif = function (string $file) {
    $jpg = file_get_contents($file);
    $payload = "Exif\0\0" . 'II*' . "\0" . str_repeat("\0", 8) . 'GPS-GEHEIM-51.4510N-6.6260E';
    $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
    file_put_contents($file, substr($jpg, 0, 2) . $app1 . substr($jpg, 2));
};
$img("$dir/img/rss1.jpg", 640, 400, [49, 65, 100], 'Beispiel RSS 1');
$withExif("$dir/img/rss1.jpg");
$img("$dir/img/rss2.png", 480, 320, [47, 90, 80], 'Beispiel RSS 2');
$oi = "$dir/openimmo";
$img("$oi/wohnung-aussen.jpg", 800, 533, [120, 90, 60], 'Wohnhaus aussen');
$withExif("$oi/wohnung-aussen.jpg");
$img("$oi/wohnung-wohnzimmer.jpg", 800, 533, [70, 110, 140], 'Wohnzimmer');
$img("$oi/wohnung-grundriss.png", 600, 600, [200, 200, 200], 'Grundriss');
$img("$oi/haus-aussen.jpg", 800, 533, [60, 120, 70], 'Haus mit Garten');

$zip = function (string $out, string $xml, array $files, array $extra = []) {
    @unlink($out);
    $z = new ZipArchive();
    $z->open($out, ZipArchive::CREATE);
    $z->addFromString('openimmo.xml', $xml);
    foreach ($files as $f) $z->addFile($f, 'bilder/' . basename($f));
    foreach ($extra as $name => $content) $z->addFromString($name, $content);
    $z->close();
};
$xml = file_get_contents("$oi/openimmo.xml");
$pics = glob("$oi/*.{jpg,png}", GLOB_BRACE);
$zip("$dir/openimmo.zip", $xml, $pics);
// Variante 2: Objekt P-3 fehlt (VOLL) → wird ausgeblendet; Miete der Wohnung geändert
$doc = new DOMDocument();
$doc->loadXML($xml);
foreach (iterator_to_array($doc->getElementsByTagName('immobilie')) as $el) {
    if (str_contains($doc->saveXML($el), 'OBID-BSP-P-3')) $el->parentNode->removeChild($el);
}
$xml2 = str_replace('<kaltmiete>850.00</kaltmiete>', '<kaltmiete>875.00</kaltmiete>', $doc->saveXML());
$zip("$dir/openimmo-v2.zip", $xml2, $pics);
// Zip-Slip: Pfad mit ../ – muss abgelehnt werden
$zip("$dir/openimmo-slip.zip", $xml, [], ['../../evil.php' => '<?php echo "x";']);
echo "Testdaten erzeugt in $dir\n";
