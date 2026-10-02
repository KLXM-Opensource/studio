<?php
/**
 * Musterseiten „Werkstatt“ des Themes essenz: ein Seitenbaum, der jeden Block und jede Variante mit frei erfundenen
 * Inhalten zeigt – inkl. Datentabellen (Produkte mit Detailseite, Termine mit Wiederholung, öffentliches Formular mit
 * Bedingung), erzeugten geometrischen Bildern (GD: Gerät-Silhouetten als transparente PNG, abstrakte Kompositionen –
 * keine Fotos, keine Personen, keine Marken) und einer Beispiel-PDF.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/essenz/tools/demo.php [--force|--remove]
 * Alles ist als Demo gekennzeichnet (Seitenbaum „werkstatt“, Tabellen werkstatt_*, Medien-Schlagwort „essenz-demo“).
 * Video: „Big Buck Bunny“ © 2008 Blender Foundation / www.bigbuckbunny.org, Lizenz CC BY 3.0 – per Zwei-Klick-Lösung.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const ESSENZ_DEMO_TAG = 'essenz-demo';
const ESSENZ_DEMO_COLLECTION = 'Werkstatt (Demo)';

/** Musterseiten anlegen. $force: vorhandene vorher entfernen. */
function essenz_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path = 'werkstatt' AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Die Musterseiten sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    essenz_demo_remove($log);

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist (Kopf, Kontakt-Block, Karte).
    // Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse von Personen oder Firmen).
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterweg 7', 'zip' => '12345', 'city' => 'Musterstadt', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    $img = essenz_demo_images($log);
    $pdf = essenz_demo_pdf($log);
    $tables = essenz_demo_tables($img, $log);
    essenz_demo_pages($img, $pdf, $tables, $log);
    essenz_demo_heroes($log);
    \Core\PageCache::clear();
    $log('Fertig: Musterseiten „Werkstatt“, ' . count($tables) . ' Datentabellen, ' . count($img['all']) . ' Bilder.');
    return true;
}

/** Musterseiten, Demo-Tabellen und Demo-Medien entfernen */
function essenz_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE path = 'werkstatt' OR path LIKE 'werkstatt/%' OR slug = '_vorlage-werkstatt-produkte'"), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'werkstatt_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . ESSENZ_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query('DELETE FROM media_collections WHERE name = ?', [ESSENZ_DEMO_COLLECTION]);
    $log('Vorhandene Musterseiten entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Bilder (GD, doppelt gerendert und verkleinert = glatte Kanten)

/** @return array{all: list<int>, wide: list<int>, tall: list<int>, objects: list<int>, collection: int} */
function essenz_demo_images(callable $log): array
{
    $col = Media::createCollection(ESSENZ_DEMO_COLLECTION, 'Geometrische Beispielbilder des Kits „Essenz“ – automatisch erzeugt, frei verwendbar, ohne Personen oder Marken.');
    $import = function (string $file, string $name, string $title, string $alt, bool $inCollection) use ($col, $log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => ESSENZ_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt']
            + ($inCollection ? ['collection' => $col] : []));
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    $all = $wide = $tall = $objects = [];
    // Kompositionen (Querformat 3:2)
    $comps = [
        ['Punktraster mit Signalpunkt', 'dots'], ['Konzentrische Kreise', 'rings'], ['Senkrechte Lamellen', 'louvres'],
        ['Quadrate im Raster', 'squares'], ['Halbkreis über dem Horizont', 'horizon'], ['Schieberegler', 'sliders'],
        ['Bögen in Graphit', 'arcs'], ['Kreis im Quadrat', 'circle'],
    ];
    foreach ($comps as $i => [$title, $kind]) {
        $file = tempnam(sys_get_temp_dir(), 'ez') . '.jpg';
        essenz_demo_comp($file, 1800, 1200, $kind, $i);
        if ($id = $import($file, sprintf('werkstatt-%02d.jpg', $i + 1), $title, 'Geometrische Komposition: ' . $title . ' (Beispielbild)', true)) { $all[] = $id; $wide[] = $id; }
    }
    foreach (['rings', 'louvres', 'dots'] as $k => $kind) {
        $file = tempnam(sys_get_temp_dir(), 'ez') . '.jpg';
        essenz_demo_comp($file, 1200, 1500, $kind, 40 + $k);
        if ($id = $import($file, sprintf('werkstatt-hoch-%02d.jpg', $k + 1), $comps[array_search($kind, array_column($comps, 1), true)][0] . ' (Hochformat)', 'Geometrische Komposition im Hochformat (Beispielbild)', true)) { $all[] = $id; $tall[] = $id; }
    }
    // Gerät-Silhouetten (transparente PNG, 4:3) – erfundene Objekte, keine realen Produkte
    foreach ([['Empfänger R1', 'radio'], ['Wecker U2', 'clock'], ['Lautsprecher L3', 'speaker'], ['Rechner K4', 'calc'], ['Lüfter V5', 'fan'], ['Leuchte E6', 'lamp']] as $i => [$title, $kind]) {
        $file = tempnam(sys_get_temp_dir(), 'ez') . '.png';
        essenz_demo_object($file, $kind);
        if ($id = $import($file, 'werkstatt-objekt-' . $kind . '.png', $title . ' (Entwurf)', 'Zeichnung eines erfundenen Geräts: ' . $title . ' (Beispielbild)', false)) { $all[] = $id; $objects[] = $id; }
    }
    $log(count($all) . ' Beispielbilder erzeugt.');
    return ['all' => $all, 'wide' => $wide, 'tall' => $tall, 'objects' => $objects, 'collection' => $col];
}

/** Farbpalette: Papier, Hellgrau, Mittelgrau, Graphit, Signal */
function essenz_demo_palette(): array
{
    return ['paper' => [242, 241, 237], 'light' => [229, 227, 222], 'mid' => [196, 193, 186], 'grey' => [120, 118, 113],
        'graphite' => [36, 37, 40], 'white' => [251, 250, 248], 'signal' => [216, 91, 25]];
}

/** Abstrakte Komposition, doppelt gerendert */
function essenz_demo_comp(string $file, int $w, int $h, string $kind, int $seed): void
{
    $P = essenz_demo_palette();
    $W = $w * 2; $H = $h * 2;
    $im = imagecreatetruecolor($W, $H);
    $c = fn(array $rgb) => imagecolorallocate($im, ...$rgb);
    $bg = $seed % 3 === 2 ? $P['graphite'] : ($seed % 3 === 1 ? $P['light'] : $P['paper']);
    $dark = $bg === $P['graphite'];
    $ink = $dark ? $P['mid'] : $P['graphite'];
    $soft = $dark ? [58, 59, 63] : $P['mid'];
    imagefilledrectangle($im, 0, 0, $W, $H, $c($bg));
    mt_srand(4200 + $seed);
    $u = min($W, $H) / 24;
    switch ($kind) {
        case 'dots':
            $n = 13; $step = min($W, $H) / ($n + 2); $ox = ($W - $step * ($n - 1)) / 2; $oy = ($H - $step * (int) floor(($H / $step) - 2)) / 2;
            $sig = [mt_rand(2, $n - 3), mt_rand(2, 6)];
            for ($y = 0; $oy + $y * $step < $H - $step; $y++) for ($x = 0; $x < $n; $x++) {
                $is = $x === $sig[0] && $y === $sig[1];
                $r = (int) ($step * ($is ? .5 : .22));
                imagefilledellipse($im, (int) ($ox + $x * $step), (int) ($oy + $y * $step), $r * 2, $r * 2, $c($is ? $P['signal'] : $ink));
            }
            break;
        case 'rings':
            $cx = (int) ($W * .5); $cy = (int) ($H * .52);
            for ($k = 12; $k >= 1; $k--) imagefilledellipse($im, $cx, $cy, (int) ($k * $u * 3.2), (int) ($k * $u * 3.2), $c($k % 2 ? $soft : $bg));
            imagefilledellipse($im, $cx, $cy, (int) ($u * 3.2), (int) ($u * 3.2), $c($P['signal']));
            break;
        case 'louvres':
            $n = 22; $gw = $W / $n;
            for ($i = 0; $i < $n; $i++) {
                $x = (int) ($i * $gw);
                imagefilledrectangle($im, $x + (int) ($gw * .18), (int) ($H * .12), $x + (int) ($gw * .82), (int) ($H * .88), $c($i === 15 ? $P['signal'] : $soft));
            }
            break;
        case 'squares':
            $n = 6; $s = min($W, $H) / ($n + 1.4); $ox = ($W - $s * $n) / 2; $oy = ($H - $s * (int) floor($H / $s - 1)) / 2;
            $sig = mt_rand(0, 20);
            for ($y = 0; $oy + ($y + 1) * $s <= $H - $s * .3; $y++) for ($x = 0; $x < $n; $x++) {
                $k = $y * $n + $x;
                $col = $k === $sig ? $P['signal'] : ($k % 5 === 0 ? $ink : $soft);
                $pad = (int) ($s * .08);
                essenz_rrect($im, (int) ($ox + $x * $s) + $pad, (int) ($oy + $y * $s) + $pad, (int) ($ox + ($x + 1) * $s) - $pad, (int) ($oy + ($y + 1) * $s) - $pad, (int) ($s * .12), $c($col));
            }
            break;
        case 'horizon':
            imagefilledrectangle($im, 0, (int) ($H * .62), $W, $H, $c($dark ? [28, 29, 31] : $P['light']));
            imagefilledarc($im, (int) ($W * .5), (int) ($H * .62), (int) ($W * .44), (int) ($W * .44), 180, 360, $c($P['signal']), IMG_ARC_PIE);
            for ($k = 1; $k < 7; $k++) imagefilledrectangle($im, (int) ($W * .08), (int) ($H * .62 + $k * $u * 1.3), (int) ($W * .92), (int) ($H * .62 + $k * $u * 1.3 + $u * .18), $c($soft));
            break;
        case 'sliders':
            for ($k = 0; $k < 5; $k++) {
                $y = (int) ($H * (.2 + $k * .15));
                essenz_rrect($im, (int) ($W * .12), $y - (int) ($u * .25), (int) ($W * .88), $y + (int) ($u * .25), (int) ($u * .25), $c($soft));
                $x = (int) ($W * (.2 + mt_rand(0, 60) / 100));
                essenz_rrect($im, (int) ($W * .12), $y - (int) ($u * .25), $x, $y + (int) ($u * .25), (int) ($u * .25), $c($k === 2 ? $P['signal'] : $ink));
                imagefilledellipse($im, $x, $y, (int) ($u * 2), (int) ($u * 2), $c($dark ? $P['light'] : $P['white']));
                imageellipse($im, $x, $y, (int) ($u * 2), (int) ($u * 2), $c($ink));
            }
            break;
        case 'arcs':
            for ($k = 8; $k >= 1; $k--) imagefilledarc($im, (int) ($W * .3), $H, (int) ($k * $u * 5), (int) ($k * $u * 5), 180, 360, $c($k % 2 ? $ink : $bg), IMG_ARC_PIE);
            imagefilledellipse($im, (int) ($W * .78), (int) ($H * .3), (int) ($u * 5), (int) ($u * 5), $c($P['signal']));
            break;
        case 'circle':
            $s = (int) (min($W, $H) * .62);
            essenz_rrect($im, (int) (($W - $s) / 2), (int) (($H - $s) / 2), (int) (($W + $s) / 2), (int) (($H + $s) / 2), (int) ($s * .08), $c($dark ? [48, 49, 53] : $P['white']));
            imagefilledellipse($im, (int) ($W / 2), (int) ($H / 2), (int) ($s * .7), (int) ($s * .7), $c($ink));
            imagefilledellipse($im, (int) ($W / 2 + $s * .18), (int) ($H / 2 - $s * .18), (int) ($s * .12), (int) ($s * .12), $c($P['signal']));
            break;
    }
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $W, $H);
    imagejpeg($out, $file, 86);
    unset($im);
    unset($out);
}

/** Abgerundetes Rechteck (gefüllt) */
function essenz_rrect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
}

/** Erfundenes Gerät als Zeichnung auf transparentem Grund (1200 × 900, doppelt gerendert) */
function essenz_demo_object(string $file, string $kind): void
{
    $P = essenz_demo_palette();
    $W = 2400; $H = 1800;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, false);
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $c = fn(array $rgb, int $a = 0) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a);
    // weicher Schatten
    for ($k = 0; $k < 14; $k++) imagefilledellipse($im, 1200, 1560, 1300 - $k * 40, 110 - $k * 4, $c([0, 0, 0], 122 - $k));
    switch ($kind) {
        case 'radio':
            essenz_rrect($im, 520, 560, 1880, 1520, 90, $c($P['white']));
            essenz_rrect($im, 520, 1400, 1880, 1520, 60, $c($P['light']));
            for ($y = 0; $y < 7; $y++) for ($x = 0; $x < 9; $x++) imagefilledellipse($im, 640 + $x * 64, 700 + $y * 88, 26, 26, $c($P['graphite']));
            imagefilledellipse($im, 1520, 900, 440, 440, $c($P['light']));
            imagefilledellipse($im, 1520, 900, 300, 300, $c($P['white']));
            imagesetthickness($im, 18);
            imageline($im, 1520, 900, 1600, 800, $c($P['signal']));
            essenz_rrect($im, 1300, 1230, 1740, 1262, 16, $c($P['mid']));
            imagefilledellipse($im, 1620, 1246, 72, 72, $c($P['graphite']));
            imagefilledellipse($im, 1300, 1340, 30, 30, $c($P['signal']));
            break;
        case 'clock':
            essenz_rrect($im, 900, 1380, 1500, 1500, 50, $c($P['mid']));
            imagefilledellipse($im, 1200, 860, 1100, 1100, $c($P['graphite']));
            imagefilledellipse($im, 1200, 860, 980, 980, $c($P['white']));
            imagesetthickness($im, 14);
            for ($k = 0; $k < 60; $k++) {
                $a = deg2rad($k * 6); $l = $k % 5 ? 20 : 60;
                imageline($im, (int) (1200 + sin($a) * 440), (int) (860 - cos($a) * 440), (int) (1200 + sin($a) * (440 - $l)), (int) (860 - cos($a) * (440 - $l)), $c($k % 5 ? $P['mid'] : $P['graphite']));
            }
            imagesetthickness($im, 30); imageline($im, 1200, 860, 1200 + 170, 860 - 220, $c($P['graphite']));
            imagesetthickness($im, 20); imageline($im, 1200, 860, 1200 - 300, 860 + 90, $c($P['graphite']));
            imagesetthickness($im, 8); imageline($im, 1200, 860, 1200 + 90, 860 + 360, $c($P['signal']));
            imagefilledellipse($im, 1200, 860, 60, 60, $c($P['signal']));
            break;
        case 'speaker':
            essenz_rrect($im, 850, 300, 1550, 1520, 70, $c($P['graphite']));
            imagefilledellipse($im, 1200, 1060, 560, 560, $c([58, 59, 63]));
            for ($y = -5; $y <= 5; $y++) for ($x = -5; $x <= 5; $x++) if ($x * $x + $y * $y <= 26) imagefilledellipse($im, 1200 + $x * 46, 1060 + $y * 46, 18, 18, $c([24, 25, 27]));
            imagefilledellipse($im, 1200, 560, 220, 220, $c([58, 59, 63]));
            imagefilledellipse($im, 1200, 560, 90, 90, $c([24, 25, 27]));
            imagefilledellipse($im, 1200, 1440, 26, 26, $c($P['signal']));
            break;
        case 'calc':
            essenz_rrect($im, 820, 240, 1580, 1520, 60, $c($P['graphite']));
            essenz_rrect($im, 900, 330, 1500, 520, 20, $c([176, 184, 170]));
            for ($y = 0; $y < 5; $y++) for ($x = 0; $x < 4; $x++) {
                $sig = $x === 3 && $y === 4;
                imagefilledellipse($im, 960 + $x * 160, 680 + $y * 170, 118, 118, $c($sig ? $P['signal'] : ($x === 3 ? $P['mid'] : [72, 73, 77])));
            }
            break;
        case 'fan':
            essenz_rrect($im, 1110, 1260, 1290, 1470, 20, $c($P['mid']));
            essenz_rrect($im, 880, 1440, 1520, 1520, 40, $c($P['white']));
            imagefilledellipse($im, 1200, 760, 1000, 1000, $c($P['white']));
            imagefilledellipse($im, 1200, 760, 880, 880, $c($P['light']));
            imagesetthickness($im, 10);
            for ($r = 120; $r <= 440; $r += 64) imageellipse($im, 1200, 760, $r * 2, $r * 2, $c($P['mid']));
            for ($k = 0; $k < 12; $k++) { $a = deg2rad($k * 30); imageline($im, 1200, 760, (int) (1200 + sin($a) * 440), (int) (760 - cos($a) * 440), $c($P['mid'])); }
            imagefilledellipse($im, 1200, 760, 170, 170, $c($P['graphite']));
            imagefilledellipse($im, 1200, 760, 50, 50, $c($P['signal']));
            break;
        case 'lamp':
            essenz_rrect($im, 900, 1440, 1500, 1520, 40, $c($P['graphite']));
            essenz_rrect($im, 1184, 640, 1216, 1450, 16, $c($P['mid']));
            imagefilledarc($im, 1200, 700, 820, 820, 180, 360, $c($P['white']), IMG_ARC_PIE);
            essenz_rrect($im, 790, 690, 1610, 730, 20, $c($P['light']));
            imagefilledellipse($im, 1200, 760, 180, 60, $c([255, 214, 150], 40));
            imagefilledellipse($im, 1420, 1480, 34, 34, $c($P['signal']));
            break;
    }
    $out = imagecreatetruecolor(1200, 900);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefilledrectangle($out, 0, 0, 1200, 900, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, 1200, 900, $W, $H);
    imagepng($out, $file, 7);
    unset($im);
    unset($out);
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function essenz_demo_pdf(callable $log): ?int
{
    $text = 'Werkstatt Beispiel - Datenblatt (Demo). Frei erfundener Inhalt.';
    $stream = "BT /F1 18 Tf 72 760 Td ($text) Tj ET";
    $objs = [
        '<</Type/Catalog/Pages 2 0 R>>',
        '<</Type/Pages/Kids[3 0 R]/Count 1>>',
        '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
        '<</Length ' . strlen($stream) . ">>stream\n$stream\nendstream",
        '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
    ];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    $pdf .= 'trailer<</Size ' . (count($objs) + 1) . "/Root 1 0 R>>\nstartxref\n$xref\n%%EOF\n";
    $file = tempnam(sys_get_temp_dir(), 'ez') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'werkstatt-datenblatt.pdf', '', ['title' => 'Datenblatt (Beispiel)', 'tags' => ESSENZ_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function essenz_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function essenz_demo_tables(array $img, callable $log): array
{
    $out = [];
    $O = fn(int $n) => $img['objects'][$n % max(1, count($img['objects']))] ?? null;

    // Produkte: Karten, Liste, kompakt, Tabelle, Detailseite
    $t = essenz_demo_table([
        'handle' => 'werkstatt_produkte', 'name' => 'Produkte (Werkstatt)', 'singular' => 'Produkt', 'icon' => 'package',
        'description' => 'Beispiel-Tabelle der Musterseiten – frei erfundene Geräte-Entwürfe.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'modell', 'label' => 'Modell', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'kategorie', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "klang=Klang\nzeit=Zeit\nlicht=Licht\nluft=Luft\nrechnen=Rechnen"],
            ['name' => 'jahr', 'label' => 'Entwurfsjahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'material', 'label' => 'Material', 'type' => 'text', 'width' => 'half'],
            ['name' => 'masse', 'label' => 'Maße', 'type' => 'text', 'width' => 'half'],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'produkte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'modell', 'sort_dir' => 'asc', 'workflow' => 0],
    ], $log);
    if ($t) {
        $rows = [
            ['Empfänger', 'R1', 'klang', 2024, 'Aluminium, Esche', '24 × 14 × 9 cm', 'Ein Radio mit einem Regler für alles – Lautstärke, Sender, Aus.'],
            ['Wecker', 'U2', 'zeit', 2023, 'Stahl, Glas', 'Ø 11 cm', 'Ruhiges Zifferblatt, ein Zeiger in Signalfarbe, kein Ticken.'],
            ['Lautsprecher', 'L3', 'klang', 2025, 'Pulverbeschichtetes Blech', '18 × 32 × 20 cm', 'Ein Gehäuse, zwei Wege, keine Zierleisten.'],
            ['Rechner', 'K4', 'rechnen', 2022, 'Kunststoff (recycelt)', '8 × 14 × 1 cm', 'Zwanzig Tasten, eine davon hervorgehoben.'],
            ['Lüfter', 'V5', 'luft', 2025, 'Stahl, Kunststoff', 'Ø 30 cm', 'Drei Stufen, leise, ohne Fernbedienung.'],
            ['Leuchte', 'E6', 'licht', 2024, 'Stahl, Aluminium', 'H 42 cm', 'Blendfreies Licht, ein Schalter am Fuß.'],
        ];
        foreach ($rows as $i => [$title, $model, $cat, $year, $mat, $dim, $text]) {
            Entries::save($t, null, ['titel' => $title, 'modell' => $model, 'kategorie' => $cat, 'jahr' => (string) $year, 'material' => $mat, 'masse' => $dim, 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Ein frei erfundener Entwurf, der zeigt, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h2>Absicht</h2><p>So wenige Bedienelemente wie möglich, so viele wie nötig. Jede Funktion ist ohne Anleitung auffindbar.</p>'
                    . '<blockquote><p>Das Gerät tritt zurück, wenn es nicht gebraucht wird.</p></blockquote>'
                    . '<h2>Details</h2><ul><li>Reparierbar verschraubt statt verklebt</li><li>Ersatzteile zehn Jahre lieferbar (fiktiv)</li><li>Verpackung aus einem Material</li></ul>',
                'bild' => $O($i), 'status' => 'published']);
        }
        $out['produkte'] = $t;
    }

    // Termine: Kalender mit Wiederholung, ganztägigem Termin und Kategorien
    $t = essenz_demo_table([
        'handle' => 'werkstatt_termine', 'name' => 'Termine (Werkstatt)', 'singular' => 'Termin', 'icon' => 'calendar-dots',
        'description' => 'Beispiel-Kalender der Musterseiten – Termine relativ zum Tag der Anlage.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'datetime', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ganztaegig', 'label' => 'Ganztägig', 'type' => 'bool'],
            ['name' => 'wiederholung', 'label' => 'Wiederholung', 'type' => 'recurrence'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'in_list' => true],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'textarea'],
            ['name' => 'kategorie', 'label' => 'Kategorie', 'type' => 'select', 'in_list' => true, 'options' => "werkstatt=Offene Werkstatt\nvortrag=Vortrag\nkurs=Kurs\ngeschlossen=Geschlossen"],
        ],
        'settings' => ['title_field' => 'titel', 'description_field' => 'ort', 'sort_field' => 'beginn', 'sort_dir' => 'asc', 'workflow' => 0,
            'calendar' => ['enabled' => 1, 'start' => 'beginn', 'end' => 'ende', 'all_day' => 'ganztaegig', 'recurrence' => 'wiederholung',
                'location' => 'ort', 'description' => 'beschreibung', 'category' => 'kategorie', 'duration' => 60, 'feed' => 1]],
    ], $log);
    if ($t) {
        $d = fn(int $days, string $time) => date('Y-m-d', strtotime("+$days days")) . ' ' . $time;
        $wed = (int) date('N') <= 3 ? 3 - (int) date('N') : 10 - (int) date('N');
        $until = gmdate('Ymd\THis\Z', strtotime('+120 days'));
        $events = [
            ['Offene Werkstatt', $d($wed, '17:00'), $d($wed, '19:00'), false, "FREQ=WEEKLY;BYDAY=WE;UNTIL=$until", 'Werkraum (fiktiv)', 'Jeden Mittwoch: Entwürfe ansehen, Fragen stellen, Werkzeug ausprobieren.', 'werkstatt'],
            ['Vortrag: Weniger Bedienelemente', $d(5, '18:30'), $d(5, '20:00'), false, '', 'Online', 'Wie man Funktionen bündelt, ohne sie zu verstecken.', 'vortrag'],
            ['Kurs: Typografie mit System', $d(12, '10:00'), $d(12, '15:00'), false, '', 'Seminarraum (fiktiv)', 'Raster, Schriftstufen, Tabellenziffern.', 'kurs'],
            ['Inventur – geschlossen', $d(19, '00:00'), '', true, '', '', 'An diesem Tag ist die Werkstatt geschlossen.', 'geschlossen'],
            ['Kurs: Reparieren statt ersetzen', $d(26, '10:00'), $d(26, '13:00'), false, '', 'Werkraum (fiktiv)', 'Schrauben, löten, dokumentieren.', 'kurs'],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $text, $cat]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'status' => 'published']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // Öffentliches Formular: Anfrage mit Bedingung (Budget nur bei „Projekt“)
    $t = essenz_demo_table([
        'handle' => 'werkstatt_anfragen', 'name' => 'Anfragen (Werkstatt)', 'singular' => 'Anfrage', 'icon' => 'envelope-simple',
        'description' => 'Öffentliches Beispiel-Formular der Musterseiten.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'anliegen', 'label' => 'Anliegen', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => "frage=Kurze Frage\nprojekt=Neues Projekt\nkurs=Kurs oder Termin"],
            ['name' => 'budget', 'label' => 'Rahmen (ungefähr)', 'type' => 'select', 'width' => 'half', 'options' => "klein=bis 2.000 €\nmittel=2.000–8.000 €\ngross=über 8.000 €",
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]]],
            ['name' => 'nachricht', 'label' => 'Nachricht', 'type' => 'textarea', 'required' => true, 'help' => 'Zwei, drei Sätze genügen.'],
            ['name' => 'rueckruf', 'label' => 'Bitte rufen Sie mich zurück', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke – Ihre Anfrage ist eingegangen (Demo: es meldet sich niemand).', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['anfragen'] = $t;

    $log(count($out) . ' Datentabellen angelegt (werkstatt_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function essenz_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $W = fn(int $n) => $img['wide'][$n % max(1, count($img['wide']))] ?? null;
    $T = fn(int $n) => $img['tall'][$n % max(1, count($img['tall']))] ?? null;
    $O = fn(int $n) => $img['objects'][$n % max(1, count($img['objects']))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text]);
    $cta = $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Alles gesehen? Dann *legen Sie los*.', 'text' => 'Signalfarbe, Schriften, Kopf und Fuß stellen Sie unter Verwaltung → Design ein.',
        'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/werkstatt'], ['background' => 'dark']);
    $proj = $tables['produkte']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';
    $form = $tables['anfragen']['handle'] ?? '';
    $mt = fn(string $v, string $eyebrow, string $title, string $text, ?int $image, string $ratio = '4:3', string $list = '', array $btn = []) => [
        'variant' => $v, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'list' => $list, 'image' => $image, 'ratio' => $ratio,
        'button_label' => $btn[0] ?? '', 'button_link' => $btn[1] ?? '', 'button2_label' => $btn[2] ?? '', 'button2_link' => $btn[3] ?? ''];

    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));

    // ---------------------------------------------------------------- Übersicht (Musterseite des Style-Editors)
    $root = $page(['slug' => 'werkstatt', 'title' => 'Werkstatt', 'sort' => $sort, 'og_image' => $W(0),
        'meta_description' => 'Alle Blöcke des Kits „Essenz“ mit frei erfundenen Beispielinhalten der Werkstatt Beispiel.'], [
        $B('hero', ['variant' => 'panel', 'eyebrow' => 'Musterseiten', 'title' => 'Jedes Detail hat eine *Aufgabe*.',
            'text' => 'Diese Seiten zeigen alle Bausteine des Kits – mit erfundenen Geräten, Zahlen und Terminen. Wählen Sie unter Verwaltung → Design eine Vorlage und sehen Sie, wie sich alles mitändert.',
            'button_label' => 'Produkte ansehen', 'button_link' => '/werkstatt/produkte', 'button2_label' => 'Journal lesen', 'button2_link' => '/werkstatt/journal',
            'points' => "Vorlagen: 6\nSchriften: 5 (lokal)\nCSS Startseite: < 30 KB\nCookies: 0", 'panel_label' => 'Essenz · Modell 01', 'image' => $O(0), 'ratio' => '4:3']),
        $B('cards', ['variant' => 'product', 'eyebrow' => 'Produkte (Karten „Produkt“)', 'title' => 'Sechs Entwürfe, ein Maßstab', 'intro' => 'Erfundene Geräte – gezeichnet, nicht fotografiert.', 'size' => 'm', 'ratio' => '4:3',
            'items' => [
                ['image' => $O(0), 'eyebrow' => 'Modell R1', 'title' => 'Empfänger', 'text' => 'Ein Regler für alles.', 'meta' => '24 × 14 × 9 cm', 'link_label' => 'Details', 'link' => '/werkstatt/produkte'],
                ['image' => $O(1), 'eyebrow' => 'Modell U2', 'title' => 'Wecker', 'text' => 'Ruhig, ohne Ticken.', 'meta' => 'Ø 11 cm', 'link_label' => 'Details', 'link' => '/werkstatt/produkte'],
                ['image' => $O(3), 'eyebrow' => 'Modell K4', 'title' => 'Rechner', 'text' => 'Zwanzig Tasten, eine betont.', 'meta' => '8 × 14 × 1 cm', 'link_label' => 'Details', 'link' => '/werkstatt/produkte'],
            ], 'more_label' => 'Alle Produkte', 'more_link' => '/werkstatt/produkte'], ['background' => 'muted']),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Merkmale „Karten“', 'title' => 'Was das Kit mitbringt', 'intro' => '', 'size' => 's',
            'items' => [
                ['icon' => 'shield-check', 'title' => 'WCAG 2.2 AA', 'text' => 'Alle Vorlagen hell und dunkel geprüft.', 'link_label' => '', 'link' => ''],
                ['icon' => 'lightning', 'title' => 'Leicht', 'text' => 'Nur das CSS, das die Seite braucht.', 'link_label' => '', 'link' => ''],
                ['icon' => 'lock', 'title' => 'Datensparsam', 'text' => 'Keine Cookies, keine externen Schriften.', 'link_label' => '', 'link' => ''],
                ['icon' => 'sliders-horizontal', 'title' => 'Einstellbar', 'text' => 'Signal, Tiefe, Raster, Kopf und Fuß.', 'link_label' => '', 'link' => ''],
            ]]),
        $B('specs', ['variant' => 'image', 'eyebrow' => 'Datenblatt', 'title' => 'Technische Daten', 'intro' => 'Wie ein Typenschild: Bezeichnung links, Wert rechts, Tabellenziffern.', 'image' => $O(2), 'ratio' => '4:3', 'note' => 'Beispielwerte, Stand: heute.',
            'items' => [
                ['group' => 'Gestaltung', 'label' => 'Raster', 'value' => '8 px, kompakt/normal/weit'],
                ['group' => 'Gestaltung', 'label' => 'Signalfarben', 'value' => 'Orange, Ocker, Grün, Blau, Rot'],
                ['group' => 'Gestaltung', 'label' => 'Schriften', 'value' => 'Inter, Inter Tight, Manrope, Geist, Geist Mono'],
                ['group' => 'Technik', 'label' => 'CSS Grundlage', 'value' => '≈ 20 KB (minifiziert)'],
                ['group' => 'Technik', 'label' => 'JavaScript', 'value' => '≈ 4 KB'],
                ['group' => 'Technik', 'label' => 'Externe Anfragen', 'value' => '0'],
            ]]),
        $B('stats', ['variant' => 'row', 'eyebrow' => 'Kennzahlen „Reihe“', 'title' => 'Mit Pegel', 'intro' => '',
            'items' => [
                ['value' => '6', 'label' => 'Vorlagen', 'level' => '', 'text' => 'hell und dunkel'],
                ['value' => '18', 'label' => 'Blöcke', 'level' => '', 'text' => 'plus Kern-Blöcke'],
                ['value' => '96', 'label' => 'Lighthouse (Beispiel)', 'level' => '96', 'text' => 'Leistung, fiktiv'],
            ]], ['background' => 'muted']),
        $B('media_text', $mt('auto', 'Text + Bild', 'Ordnung zuerst, Form danach', '<p>Bevor eine Farbe gewählt wird, stehen Inhalte, Reihenfolge und Hierarchie fest. Die Form macht diese Ordnung dann sichtbar.</p>', $W(3), '4:3',
            "Inhalte sortieren\nHierarchie festlegen\nForm ableiten", ['Mehr zur Haltung', '/haltung'])),
        $B('media_text', $mt('auto', 'Abwechselnd', 'Wenige Elemente, sorgfältig gesetzt', '<p>Mehrere Text-Bild-Blöcke nacheinander wechseln automatisch die Seite – ohne Einstellung.</p>', $W(5), '4:3')),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Ablauf „Zeitleiste“', 'title' => 'Vom Auftrag zum Gerät', 'intro' => '',
            'items' => [
                ['meta' => 'Tag 1', 'title' => 'Zuhören', 'text' => 'Was soll das Ding können – und was nicht?'],
                ['meta' => 'Woche 1–2', 'title' => 'Skizzieren', 'text' => 'Viele Varianten, schnell verworfen.'],
                ['meta' => 'Woche 3–5', 'title' => 'Bauen', 'text' => 'Modelle, Tests, Korrekturen.'],
                ['meta' => 'danach', 'title' => 'Begleiten', 'text' => 'Ersatzteile, Pflege, Verbesserungen.'],
            ]], ['background' => 'muted']),
        $B('principles', ['variant' => 'accordion', 'eyebrow' => 'Grundsätze „Aufklappbar“', 'title' => 'Kurz und aufklappbar', 'intro' => '',
            'items' => [
                ['title' => 'Nützlich', 'text' => 'Es erfüllt seinen Zweck – zuerst und vollständig.'],
                ['title' => 'Verständlich', 'text' => 'Es erklärt sich durch Ordnung.'],
                ['title' => 'Langlebig', 'text' => 'Es altert gut – in Form und Technik.'],
            ]]),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Stimmen (Beispiele)', 'title' => 'Was andere sagen', 'items' => [
            ['text' => 'Endlich eine Website, die man nicht erklären muss.', 'name' => 'Kim Muster', 'role' => 'Beispielkundin (fiktiv)', 'image' => null],
            ['text' => 'Ruhig, schnell, und nach Jahren noch aktuell.', 'name' => 'Sam Beispiel', 'role' => 'Verein (fiktiv)', 'image' => null],
            ['text' => 'Die Details stimmen – bis zur Fehlermeldung.', 'name' => 'Jo Probe', 'role' => 'Praxis (fiktiv)', 'image' => null],
        ]], ['background' => 'muted']),
        $cta,
    ]);

    // ---------------------------------------------------------------- Produkte (Datenlisten, Detailseite, Termine)
    if ($proj !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-werkstatt-produkte', 'title' => 'Produkte (Werkstatt) – Detailseite', 'type' => 'template', 'template_for' => $proj, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.modell", "$proj.kategorie"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => 'Alle Produkte', 'back_link' => '/werkstatt/produkte'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.bild"], 'layout' => 'image', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small', 'background' => 'white']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext", "$proj.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.modell", "$proj.material", "$proj.masse", "$proj.jahr"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => '', 'title' => 'Weitere Entwürfe', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.modell"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '4:3', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
        ]));
        $t = Tables::find($proj);
        $t['settings']['detail_page_id'] = $tpl;
        app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $dl = fn(string $table, string $layout, array $extra) => $extra + ['eyebrow' => '', 'intro' => '', 'table' => $table, 'layout' => $layout, 'columns' => '3', 'ratio' => '4:3', 'limit' => 0,
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $page(['slug' => 'produkte', 'title' => 'Produkte & Termine', 'parent_id' => $root, 'sort' => 1,
        'meta_description' => 'Datenlisten als Karten, Liste, kompakt und Tabelle mit Detailseite; Kalender und nächste Termine.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Einstieg „Text + Bild“', 'title' => 'Einmal pflegen, überall zeigen.', 'text' => 'Produkte stehen in einer Datentabelle und erscheinen als Karten, Liste oder Tabelle – mit eigener Detailseite.',
            'button_label' => 'Zu den Terminen', 'button_link' => '#termine', 'button2_label' => '', 'button2_link' => '', 'points' => "Detailseite aus einer Vorlage\nFilter, Sortierung, Blättern", 'image' => $T(0), 'ratio' => '4:5']),
        $B('data_list', $dl($proj, 'cards', ['eyebrow' => 'Datenliste „Karten“', 'title' => 'Produkte', 'fields' => ["$proj._title", "$proj.bild", "$proj.modell", "$proj.kurztext"], 'limit' => 6]), ['background' => 'muted']),
        $B('data_list', $dl($proj, 'list', ['eyebrow' => 'Datenliste „Liste mit Bild“', 'title' => 'Als Liste', 'fields' => ["$proj._title", "$proj.bild", "$proj.material", "$proj.kurztext"], 'limit' => 3])),
        $B('data_list', $dl($proj, 'table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Alle Entwürfe', 'fields' => ["$proj._title", "$proj.modell", "$proj.kategorie", "$proj.masse", "$proj.jahr"]]), ['anchor' => 'tabelle', 'background' => 'muted']),
        $B('data_list', $dl($proj, 'compact', ['eyebrow' => 'Datenliste „Kompakt“', 'title' => 'Kurzübersicht', 'fields' => ["$proj._title", "$proj.modell", "$proj.jahr"], 'limit' => 4])),
        $B('upcoming', ['eyebrow' => 'Nächste Termine', 'title' => 'Demnächst in der Werkstatt', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true, 'link_detail' => false, 'subscribe' => true, 'more_label' => '', 'more_link' => ''], ['anchor' => 'termine', 'background' => 'muted']),
        $B('calendar', ['eyebrow' => 'Kalender', 'title' => 'Monatsübersicht', 'intro' => 'Bei wenig Platz wird die Übersicht zur Liste der Tage mit Terminen.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => false]),
        $B('upcoming', ['eyebrow' => 'Kompakt im Nachtpaneel', 'title' => 'Die nächsten drei', 'intro' => '', 'table' => $term, 'limit' => 3, 'days' => 0, 'layout' => 'compact', 'show_location' => true, 'link_detail' => false, 'subscribe' => false], ['background' => 'dark']),
    ]);

    // ---------------------------------------------------------------- Formular & Kontakt
    $page(['slug' => 'formular', 'title' => 'Formular & Kontakt', 'parent_id' => $root, 'sort' => 2,
        'meta_description' => 'Öffentliches Formular mit Bedingung, Fehler- und Erfolgsmeldung; Kontakt mit Öffnungszeiten-Paneel und Karte.'], [
        $hero('Formular', 'Anfragen, die man gern ausfüllt.', 'Einträge landen in einer Datentabelle – Spamschutz ohne Cookies. Absenden ist gefahrlos: es ist eine Demo-Tabelle.'),
        $B('data_form', ['eyebrow' => 'Öffentliches Formular', 'title' => 'Anfrage (Beispiel)', 'intro' => '„Neues Projekt“ blendet ein weiteres Pflichtfeld ein. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $form, 'submit_label' => '', 'success_text' => '']),
        $B('contact', ['eyebrow' => 'Kontakt', 'title' => 'So erreichen Sie uns', 'intro' => 'Angaben und Öffnungszeiten kommen aus „Website“; die Betriebsanzeige rechnet im Browser.', 'show_hours' => true, 'show_map' => true,
            'form_table' => $form, 'form_title' => 'Kurze Nachricht', 'submit_label' => '', 'note' => 'Beispieladresse – frei erfunden.'], ['anchor' => 'kontakt', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Medien
    $page(['slug' => 'medien', 'title' => 'Medien', 'parent_id' => $root, 'sort' => 3,
        'meta_description' => 'Galerie mit Lightbox, Slider, Stapelkarten, Video mit Zwei-Klick-Lösung, Downloads und Karte.'], [
        $B('hero', ['variant' => 'statement', 'eyebrow' => 'Einstieg „Große Aussage“', 'title' => 'Bilder, Video, Dateien, Karte.', 'text' => 'Alles kommt vom eigenen Server – Videos von YouTube oder Vimeo erst nach einem Klick.',
            'button_label' => 'Zur Galerie', 'button_link' => '#galerie', 'button2_label' => '', 'button2_link' => '', 'points' => "Galerie: Raster, Mosaik, Zeilen\nSlider mit Tastatur\nPDF im Browser", 'image' => $W(4), 'ratio' => '16:9']),
        $B('gallery', ['eyebrow' => 'Galerie „Raster“', 'title' => 'Geometrische Studien', 'intro' => 'Ein Klick öffnet die Lightbox.', 'source' => 'collection', 'collection' => $img['collection'],
            'layout' => 'grid', 'columns' => '3', 'ratio' => '3:2', 'captions' => true, 'lightbox' => true], ['anchor' => 'galerie']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Folien mit Text', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $W(1), 'eyebrow' => 'Folie 1', 'title' => 'Konzentrisch', 'text' => 'Abgedunkelt mit heller Schrift.', 'button_label' => 'Mehr', 'button_link' => '/werkstatt', 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $W(3), 'eyebrow' => 'Folie 2', 'title' => 'Textkasten', 'text' => 'Ein Kasten in der Hintergrundfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
            ['image' => $W(7), 'eyebrow' => 'Folie 3', 'title' => 'Aufgehellt', 'text' => 'Dunkle Schrift auf hellem Schleier.', 'button_label' => '', 'button_link' => '', 'position' => 'center', 'overlay' => 'light'],
        ]], ['background' => 'muted']),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Karten, die sich stapeln', 'intro' => 'Nur bei genug Platz und ohne „Bewegung reduzieren“ – sonst eine ruhige Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Verstehen', 'text' => 'Wir klären Ziele und Rahmen.', 'image' => $W(0), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Reduzieren', 'text' => 'Weglassen, was nicht trägt.', 'image' => $W(2), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Verfeinern', 'text' => 'Das Übrige so gut wie möglich machen.', 'image' => $W(6), 'link_label' => 'Kontakt', 'link' => '/kontakt'],
        ]]),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video „Mit Text“', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W(5), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)'], ['background' => 'muted']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Dateien', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true, 'collection' => null,
            'files' => $pdf ? [['label' => 'Datenblatt (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []]),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort', 'location' => 'site', 'zoom' => '13', 'height' => 'm', 'route' => true], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Journal (Artikel, FAQ)
    $page(['slug' => 'journal', 'title' => 'Journal', 'parent_id' => $root, 'sort' => 4, 'og_image' => $W(2),
        'meta_description' => 'Artikel mit Lesebreite, Inhaltsverzeichnis und Zitat; Fragen und Antworten.'], [
        $hero('Journal', 'Warum weniger oft mehr Arbeit ist', 'Ein Beispielartikel mit Inhaltsverzeichnis, Zwischenüberschriften und Zitat.'),
        $B('richtext', ['variant' => 'article', 'eyebrow' => 'Beitrag', 'title' => 'Das Weglassen gestalten', 'intro' => '', 'meta' => 'Redaktion Werkstatt Beispiel · Beispieltext', 'toc' => true, 'text' =>
            '<p>Reduktion klingt nach weniger Aufwand. In der Praxis ist es umgekehrt: Wer weglässt, muss vorher verstehen, was trägt. Dieser Text ist frei erfunden und zeigt die Gestaltung eines Artikels.</p>'
            . '<h2>Verstehen, was trägt</h2><p>Jedes Element auf einer Seite konkurriert um Aufmerksamkeit. Bevor wir etwas entfernen, fragen wir: Welche Aufgabe hat es – und erfüllt ein anderes Element diese Aufgabe schon?</p>'
            . '<blockquote><p>Gutes Design ist so wenig Design wie möglich – aber nicht weniger.</p></blockquote>'
            . '<h2>Ordnung sichtbar machen</h2><p>Ein Raster aus 8-Punkt-Schritten, wenige Schriftstufen, eine einzige Signalfarbe: So entsteht Ruhe, ohne dass es leer wirkt.</p><h3>Die drei Werkzeuge</h3><ul><li>Abstand statt Linien</li><li>Größe statt Farbe</li><li>Reihenfolge statt Hervorhebung</li></ul>'
            . '<h2>Details prüfen</h2><p>Fehlermeldungen, Fokusrahmen, Tastaturbedienung und Kontraste entscheiden, ob etwas für alle funktioniert. Sie sind nicht das Ende der Arbeit, sondern ihr Kern.</p>'
            . '<h2>Was bleibt</h2><p>Eine Seite, die man benutzt, ohne über sie nachzudenken. <strong>Das ist das Ziel.</strong></p>']),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'Fragen „Untereinander“', 'title' => 'Fragen zum Kit', 'intro' => 'Suchmaschinen erhalten sie als strukturierte Daten.', 'items' => [
            ['q' => 'Warum nur eine Signalfarbe?', 'a' => '<p>Eine Farbe, die nur dort erscheint, wo etwas zu tun ist, lenkt verlässlich. Mehrere Akzente konkurrieren.</p>'],
            ['q' => 'Brauche ich Bilder?', 'a' => '<p>Nein. Alle Blöcke funktionieren ohne Bilder; der Einstieg zeigt dann ein ruhiges Paneel mit Punktraster.</p>'],
            ['q' => 'Gibt es ein dunkles Schema?', 'a' => '<p>Ja, das „Nachtpaneel“ – automatisch, wenn Besucher es im Gerät eingestellt haben.</p>'],
        ]], ['background' => 'muted']),
        $cta,
    ]);
    $log('Seitenbaum „Werkstatt“ angelegt (/werkstatt).');
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“

/**
 * Musterseite „Hero-Varianten“ (/werkstatt/hero-varianten): die Geräte-Einstiege Bedienfeld, Kennzahlen und Monitor
 * untereinander, dazu „Geräte-Paneel“ und „Große Aussage“ zum Vergleich. Für den Monitor entsteht ein kurzes, stummes
 * Loop-Video aus einer erzeugten Komposition (ffmpeg; ohne ffmpeg zeigt der Monitor das Standbild).
 * Einzeln (bestehende Websites): CMS_SITE=… php kits/essenz/tools/demo.php --heroes
 */
function essenz_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $root = $db->fetch("SELECT id FROM pages WHERE path = 'werkstatt' AND type = 'page' LIMIT 1");
    if (!$root) { $log('Keine Musterseiten vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE path = 'werkstatt/hero-varianten'") as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();

    $media = $db->fetchAll('SELECT id, original_name, mime FROM media WHERE tags LIKE ? ORDER BY id', ['%' . ESSENZ_DEMO_TAG . '%']);
    $pick = fn(string $prefix) => array_values(array_map(fn($m) => (int) $m['id'], array_filter($media, fn($m) => str_starts_with((string) $m['original_name'], $prefix))));
    $objects = $pick('werkstatt-objekt-');
    $wide = $pick('werkstatt-0');
    $O = fn(int $n) => $objects ? $objects[$n % count($objects)] : null;
    $W = fn(int $n) => $wide ? $wide[$n % count($wide)] : null;
    [$video, $still] = essenz_demo_video($media, $log);

    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + ['eyebrow' => '', 'text' => '', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''],
        'tunes' => ['section' => $tunes]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Werkstatt · Hero-Varianten', 'title' => 'Sieben Einstiege, *ein* Block.',
            'text' => 'Der Block „Einstieg (Hero)“ hat sieben Varianten. Die drei neuen sehen aus wie Geräte: ein Bedienfeld mit Anzeige, ein Paneel mit Rundinstrumenten und ein Monitor mit stummem Video. Alle Werte hier sind frei erfunden – auf echten Seiten steht nur ein Einstieg ganz oben.'],
            ['background' => 'muted']),
        $B('console', ['eyebrow' => 'Variante „Bedienfeld“', 'title' => 'Alles im *Blick*, nichts zu drehen.',
            'text' => 'Eine große Anzeige und bis zu sechs Kanäle aus „Bezeichnung: Wert“. Prozentwerte und Brüche zeigen einen Pegel, alles andere einen Drehregler – reine Anzeige, die Werte stehen als Text daneben.',
            'button_label' => 'Anfrage stellen', 'button_link' => '/werkstatt/formular', 'button2_label' => 'Termine', 'button2_link' => '/werkstatt',
            'panel_label' => 'Werkstatt · Pult 02', 'display_value' => '24 h', 'display_label' => 'Antwort innerhalb (Beispiel)',
            'points' => "Auslastung: 72 %\nZufriedenheit: 4,6 / 5\nReparaturquote: 91 %\nErsatzteile: 10 Jahre\nKurse im Monat: 6\nWerkbänke: 4"]),
        $B('dials', ['eyebrow' => 'Variante „Kennzahlen“', 'title' => 'Zahlen, die man *ablesen* kann.',
            'text' => 'Die Aussage steht oben, darunter drei bis vier Kennzahlen als Rundinstrumente in einem Paneel. Nur belegbare Zahlen verwenden – diese hier sind Beispiele.',
            'button_label' => 'Produkte ansehen', 'button_link' => '/werkstatt/produkte',
            'panel_label' => 'Messwerte (fiktiv)', 'figures_style' => 'arc', 'items' => [
                ['value' => '91 %', 'unit' => '', 'label' => 'Reparaturquote', 'text' => 'Beispielwert', 'level' => '', 'max' => ''],
                ['value' => '24', 'unit' => 'h', 'label' => 'Antwortzeit', 'text' => 'werktags, von 48 h', 'level' => '', 'max' => '48'],
                ['value' => '4,6', 'unit' => '', 'label' => 'Bewertung', 'text' => 'von 5 (Beispiel)', 'level' => '', 'max' => '5'],
                ['value' => '10', 'unit' => 'J.', 'label' => 'Ersatzteile', 'text' => 'lieferbar (fiktiv)', 'level' => '', 'max' => ''],
            ]], ['background' => 'muted']),
        $B('monitor', ['eyebrow' => 'Variante „Monitor“', 'title' => 'Bewegung, *ohne* Anbieter.',
            'text' => 'Ein kurzes, stummes Loop-Video aus der Mediathek im Geräterahmen. Es läuft nur, solange es sichtbar ist, hält über die Taste unten rechts und steht bei „Bewegung reduzieren“ als Standbild still.',
            'button_label' => 'Medien ansehen', 'button_link' => '/werkstatt/medien', 'button2_label' => 'Kontakt', 'button2_link' => '/kontakt',
            'panel_label' => 'Monitor M1 · Beispiel', 'video' => $video, 'image' => $still ?? $W(1), 'overlay' => 'strong']),
        $B('panel', ['eyebrow' => 'Zum Vergleich: „Geräte-Paneel“', 'title' => 'Jedes Detail hat eine *Aufgabe*.',
            'text' => 'Die bisherige Standard-Variante: Text links, rechts ein Paneel mit Bild und nummerierten Kennwerten.',
            'points' => "Vorlagen: 6\nSchriften: 5 (lokal)\nCookies: 0", 'panel_label' => 'Essenz · Modell 01', 'image' => $O(0), 'ratio' => '4:3'],
            ['background' => 'muted']),
        $B('statement', ['eyebrow' => 'Zum Vergleich: „Große Aussage“', 'title' => 'Weniger, aber *besser*.',
            'text' => 'Typografisch groß, Kennwerte als Index daneben, optional ein Breitbild.',
            'points' => "Raster: 8 px\nSignalfarben: 1\nSchriftstufen: 6", 'image' => $W(4)]),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 9, 'status' => 'published', 'menu' => 1,
        'meta_description' => 'Alle Einstiege (Hero) des Kits „Essenz“: Bedienfeld, Kennzahlen, Monitor, Geräte-Paneel und große Aussage – mit frei erfundenen Werten.'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/werkstatt/hero-varianten).');
    return true;
}

/**
 * Demo-Video für den Monitor: Komposition „Konzentrische Kreise“ (Hellgrau) → 8 s stummes MP4 mit langsamem Zoom
 * hin und zurück (nahtlose Schleife). Vorhandenes wiederverwenden. @return array{0: ?int, 1: ?int} [Video-ID, Standbild-ID]
 */
function essenz_demo_video(array $media, callable $log): array
{
    $find = fn(string $name) => ($m = array_values(array_filter($media, fn($m) => $m['original_name'] === $name))) ? (int) $m[0]['id'] : null;
    $vid = $find('werkstatt-monitor.mp4');
    $still = $find('werkstatt-monitor.jpg');
    if ($vid && $still) return [$vid, $still];

    $jpg = tempnam(sys_get_temp_dir(), 'ez') . '.jpg';
    essenz_demo_comp($jpg, 1920, 1080, 'rings', 4);
    if (!$still) {
        $copy = tempnam(sys_get_temp_dir(), 'ez') . '.jpg';
        copy($jpg, $copy);
        [$m, $err] = Media::import($copy, 'werkstatt-monitor.jpg', 'Konzentrische Kreise in Hellgrau mit Signalpunkt (Beispielbild)',
            ['title' => 'Monitor-Standbild (Beispiel)', 'tags' => ESSENZ_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($copy);
        if ($err) $log('Standbild: ' . $err);
        $still = $m ? (int) $m['id'] : null;
    }
    if (!$vid) {
        $ff = '';
        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $p) if (is_executable($p)) { $ff = $p; break; }
        if ($ff === '' && function_exists('shell_exec')) $ff = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ff === '' || !function_exists('exec')) {
            $log('ffmpeg nicht gefunden – der Monitor zeigt nur das Standbild.');
        } else {
            $mp4 = tempnam(sys_get_temp_dir(), 'ez') . '.mp4';
            // 240 Bilder bei 30 fps: Zoom 1 → 1,12 → 1 (Kosinus), Ausschnitt mittig – Anfang = Ende, nahtlos
            $vf = "scale=3840:-2,zoompan=z='1+0.06*(1-cos(2*PI*on/240))':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=1280x720:fps=30";
            exec(escapeshellarg($ff) . ' -y -loglevel error -loop 1 -framerate 30 -i ' . escapeshellarg($jpg) . ' -vf ' . escapeshellarg($vf)
                . ' -frames:v 240 -an -c:v libx264 -pix_fmt yuv420p -movflags +faststart -crf 28 ' . escapeshellarg($mp4) . ' 2>&1', $out, $code);
            if ($code === 0 && is_file($mp4) && filesize($mp4) > 0) {
                [$m, $err] = Media::import($mp4, 'werkstatt-monitor.mp4', '', ['title' => 'Monitor-Schleife (Beispiel, ohne Ton)', 'tags' => ESSENZ_DEMO_TAG, 'credit' => 'Beispielvideo, automatisch erzeugt']);
                if ($err) $log('Video: ' . $err);
                $vid = $m ? (int) $m['id'] : null;
                if ($vid) $log('Demo-Video erzeugt (' . round(filesize($mp4) / 1024) . ' KB).');
            } else {
                $log('ffmpeg: ' . implode(' ', array_slice($out, -2)));
            }
            @unlink($mp4);
        }
    }
    @unlink($jpg);
    return [$vid, $still];
}
