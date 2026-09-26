<?php
/**
 * Musterseiten „Labor“ des Kits „glas“: ein Seitenbaum, der jeden Block und jede Variante mit frei erfundenen Inhalten des
 * fiktiven Studios „Lumen Labs“ zeigt – inkl. Datentabellen (Projekte mit Detailseite, Termine mit Wiederholung,
 * öffentliches Formular mit Bedingung), erzeugten Bildern (GD: weiche Farbfelder mit Glasflächen, erfundene App-Oberflächen
 * und Telefon-Entwürfe – keine Fotos, keine Personen, keine Marken) und einer Beispiel-PDF.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php themes/glas/tools/demo.php [--force|--remove]
 * Alles ist als Demo gekennzeichnet (Seitenbaum „labor“, Tabellen labor_*, Medien-Schlagwort „glas-demo“).
 * Video: „Big Buck Bunny“ © 2008 Blender Foundation / www.bigbuckbunny.org, Lizenz CC BY 3.0 – per Zwei-Klick-Lösung.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const GLAS_DEMO_TAG = 'glas-demo';
const GLAS_DEMO_COLLECTION = 'Labor (Demo)';

/** Musterseiten anlegen. $force: vorhandene vorher entfernen. */
function glas_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path = 'labor' AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Die Musterseiten sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    glas_demo_remove($log);

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist (Kopf, Kontakt-Block, Karte).
    // Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse von Personen oder Firmen).
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterweg 7', 'zip' => '12345', 'city' => 'Musterstadt', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    @set_time_limit(120);
    $img = glas_demo_images($log);
    $pdf = glas_demo_pdf($log);
    $tables = glas_demo_tables($img, $log);
    glas_demo_pages($img, $pdf, $tables, $log);
    glas_demo_heroes($log);
    \Core\PageCache::clear();
    $log('Fertig: Musterseiten „Labor“, ' . count($tables) . ' Datentabellen, ' . count($img['all']) . ' Bilder.');
    return true;
}

/** Musterseiten, Demo-Tabellen und Demo-Medien entfernen */
function glas_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE path = 'labor' OR path LIKE 'labor/%' OR slug = '_vorlage-labor-projekte'"), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'labor_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . GLAS_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query('DELETE FROM media_collections WHERE name = ?', [GLAS_DEMO_COLLECTION]);
    $log('Vorhandene Musterseiten entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Bilder (GD)
// Farbfeld: kleines Bild Pixel für Pixel aus weichen Flecken (Gauß), dann groß gerechnet – so entstehen glatte Verläufe
// ohne Streifen. Glasflächen: je Fläche eine eigene Ebene (Tönung + Lichtkante), weicher Schatten aus einer winzigen,
// hochskalierten Form. Alles doppelt groß gezeichnet und verkleinert (glatte Kanten).

/** Paletten: Grund, drei Feldfarben, Glas, Tinte */
function glas_demo_palettes(): array
{
    return [
        'aurora' => ['base' => [243, 241, 251], 'f' => [[185, 166, 255], [143, 227, 240], [255, 179, 217], [109, 40, 217]], 'ink' => [18, 20, 43]],
        'lagune' => ['base' => [238, 247, 247], 'f' => [[142, 230, 214], [156, 201, 255], [200, 242, 166], [15, 118, 110]], 'ink' => [11, 31, 36]],
        'daemmerung' => ['base' => [251, 241, 238], 'f' => [[255, 194, 158], [255, 158, 179], [201, 168, 245], [180, 35, 79]], 'ink' => [42, 16, 32]],
        'nacht' => ['base' => [14, 16, 34], 'f' => [[91, 43, 181], [14, 106, 134], [140, 31, 102], [167, 139, 250]], 'ink' => [244, 243, 255]],
    ];
}

/** Farbfeld als GD-Bild der Größe $W × $H (Flecken: [x, y, Radius, Farbe, Stärke], Koordinaten 0–1) */
function glas_demo_field(int $W, int $H, array $base, array $blobs): \GdImage
{
    $sw = 200;
    $sh = max(2, (int) round($sw * $H / $W));
    $small = imagecreatetruecolor($sw, $sh);
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $c = $base;
            foreach ($blobs as [$bx, $by, $r, $rgb, $s]) {
                $dx = $x / $sw - $bx;
                $dy = ($y / $sh - $by) * $H / $W;
                $w = $s * exp(-($dx * $dx + $dy * $dy) / (2 * $r * $r));
                for ($i = 0; $i < 3; $i++) $c[$i] = $c[$i] * (1 - $w) + $rgb[$i] * $w;
            }
            imagesetpixel($small, $x, $y, imagecolorallocate($small, (int) $c[0], (int) $c[1], (int) $c[2]));
        }
    }
    $im = imagecreatetruecolor($W, $H);
    imagecopyresampled($im, $small, 0, 0, 0, 0, $W, $H, $sw, $sh);
   
    return $im;
}

/** Gefülltes Rechteck mit runden Ecken auf einer Ebene ohne Mischen (Überlappungen verdoppeln die Deckkraft nicht) */
function glas_demo_rrect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
}

/** Transparente Ebene */
function glas_demo_layer(int $w, int $h): \GdImage
{
    $l = imagecreatetruecolor(max(1, $w), max(1, $h));
    imagealphablending($l, false);
    imagesavealpha($l, true);
    imagefilledrectangle($l, 0, 0, $w, $h, imagecolorallocatealpha($l, 0, 0, 0, 127));
    return $l;
}

/** GD-Alpha (0 = deckend, 127 = durchsichtig) aus Deckkraft 0–1 */
function glas_demo_a(float $opacity): int
{
    return (int) round(127 * (1 - max(0, min(1, $opacity))));
}

/**
 * Glasfläche: weicher Schatten, Tönung, Lichtkante (heller Rand, oben kräftiger), optional als Kreis.
 * $tint: Farbe der Tönung, $op: Deckkraft (0–1)
 */
function glas_demo_glass(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, array $tint, float $op, bool $dark = false): void
{
    $w = $x2 - $x1;
    $h = $y2 - $y1;
    // Schatten: winzig zeichnen, groß rechnen = weich
    $pad = (int) ($r * 1.6 + 40);
    $sc = 12;
    $sw = (int) (($w + 2 * $pad) / $sc);
    $sh = (int) (($h + 2 * $pad) / $sc);
    $small = glas_demo_layer($sw, $sh);
    glas_demo_rrect($small, (int) ($pad / $sc), (int) (($pad + $h * .06) / $sc), (int) (($pad + $w) / $sc), (int) (($pad + $h * 1.04) / $sc), (int) ($r / $sc), imagecolorallocatealpha($small, 20, 12, 60, glas_demo_a($dark ? .5 : .22)));
    for ($k = 0; $k < 8; $k++) imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
    $shadow = imagescale($small, $w + 2 * $pad, $h + 2 * $pad, IMG_BILINEAR_FIXED);
    imagesavealpha($shadow, true);
    imagealphablending($im, true);
    imagecopy($im, $shadow, $x1 - $pad, $y1 - $pad + (int) ($h * .04), 0, 0, $w + 2 * $pad, $h + 2 * $pad);
   
   
    // Tönung + Kante auf einer eigenen Ebene
    $l = glas_demo_layer($w + 1, $h + 1);
    $edge = max(2, (int) round(min($w, $h) / 160));
    glas_demo_rrect($l, 0, 0, $w, $h, $r, imagecolorallocatealpha($l, 255, 255, 255, glas_demo_a($dark ? .22 : .85)));
    glas_demo_rrect($l, $edge, $edge * 2, $w - $edge, $h - $edge, max(0, $r - $edge), imagecolorallocatealpha($l, 255, 255, 255, glas_demo_a($dark ? .12 : .5)));
    glas_demo_rrect($l, $edge, $edge * 3, $w - $edge, $h - $edge, max(0, $r - $edge), imagecolorallocatealpha($l, $tint[0], $tint[1], $tint[2], glas_demo_a($op)));
    // Lichtschimmer oben: auf die Tönung gemischt (nicht ersetzt)
    imagealphablending($l, true);
    $sheen = intdiv($h, 3);
    for ($k = 0; $k < 10; $k++) {
        $yy = $edge * 3 + intdiv($sheen * $k, 10);
        imagefilledrectangle($l, $edge + intdiv($r, 2), $yy, $w - $edge - intdiv($r, 2), $yy + intdiv($sheen, 10) - 1, imagecolorallocatealpha($l, 255, 255, 255, glas_demo_a(($dark ? .025 : .05))));
    }
    imagealphablending($l, false);
    imagecopy($im, $l, $x1, $y1, 0, 0, $w + 1, $h + 1);
   
}

/** Kugel (Glasperle) mit Glanzpunkt */
function glas_demo_orb(\GdImage $im, int $cx, int $cy, int $d, array $c1, array $c2): void
{
    for ($k = 0; $k < 24; $k++) {
        $t = $k / 23;
        $col = [(int) ($c1[0] * (1 - $t) + $c2[0] * $t), (int) ($c1[1] * (1 - $t) + $c2[1] * $t), (int) ($c1[2] * (1 - $t) + $c2[2] * $t)];
        $dd = (int) ($d * (1 - $t * .92));
        imagefilledellipse($im, $cx + (int) ($d * .1 * $t), $cy + (int) ($d * .1 * $t), $dd, $dd, imagecolorallocate($im, ...$col));
    }
    for ($k = 0; $k < 8; $k++) imagefilledellipse($im, $cx - (int) ($d * .18), $cy - (int) ($d * .2), (int) ($d * (.34 - $k * .035)), (int) ($d * (.24 - $k * .025)), imagecolorallocatealpha($im, 255, 255, 255, glas_demo_a(.12 + $k * .06)));
}

/** Zeile „Text“ (Balken) in einer Oberfläche */
function glas_demo_bar(\GdImage $im, int $x, int $y, int $w, int $h, array $rgb, float $op): void
{
    $l = glas_demo_layer($w + 1, $h + 1);
    glas_demo_rrect($l, 0, 0, $w, $h, (int) ($h / 2), imagecolorallocatealpha($l, $rgb[0], $rgb[1], $rgb[2], glas_demo_a($op)));
    imagealphablending($im, true);
    imagecopy($im, $l, $x, $y, 0, 0, $w + 1, $h + 1);
   
}

/** Abstrakte Komposition: Farbfeld mit zwei, drei Glasflächen und einer Perle (Querformat 3:2) */
function glas_demo_comp(string $file, string $pal, int $seed, int $w = 1800, int $h = 1200): void
{
    $P = glas_demo_palettes()[$pal];
    $W = $w * 2; $H = $h * 2;
    mt_srand(9000 + $seed);
    $dark = $pal === 'nacht';
    $blobs = [];
    foreach ([[0.12, 0.15], [0.9, 0.2], [0.55, 0.95], [0.3, 0.6]] as $i => [$x, $y]) {
        $blobs[] = [$x + mt_rand(-10, 10) / 100, $y + mt_rand(-10, 10) / 100, .22 + mt_rand(0, 10) / 100, $P['f'][$i % 4], $i === 3 ? .55 : .95];
    }
    $im = glas_demo_field($W, $H, $P['base'], $blobs);
    $u = (int) ($H / 12);
    $tint = $dark ? [30, 34, 66] : [255, 255, 255];
    switch ($seed % 4) {
        case 0:   // große Karte + Pille + Perle
            glas_demo_orb($im, (int) ($W * .7), (int) ($H * .34), 5 * $u, $P['f'][1], $P['f'][3]);
            glas_demo_glass($im, (int) ($W * .16), (int) ($H * .22), (int) ($W * .64), (int) ($H * .78), (int) ($u * .9), $tint, $dark ? .5 : .42, $dark);
            glas_demo_glass($im, (int) ($W * .52), (int) ($H * .62), (int) ($W * .86), (int) ($H * .62) + (int) ($u * 1.4), (int) ($u * .7), $tint, $dark ? .5 : .4, $dark);
            break;
        case 1:   // gestaffelte Scheiben
            glas_demo_orb($im, (int) ($W * .32), (int) ($H * .62), 6 * $u, $P['f'][2], $P['f'][3]);
            for ($k = 0; $k < 3; $k++) glas_demo_glass($im, (int) ($W * (.28 + $k * .12)), (int) ($H * (.16 + $k * .1)), (int) ($W * (.62 + $k * .12)), (int) ($H * (.56 + $k * .1)), (int) ($u * .8), $tint, $dark ? .42 : .34, $dark);
            break;
        case 2:   // Kreise hinter einer breiten Leiste
            glas_demo_orb($im, (int) ($W * .3), (int) ($H * .4), 5 * $u, $P['f'][0], $P['f'][3]);
            glas_demo_orb($im, (int) ($W * .66), (int) ($H * .58), 4 * $u, $P['f'][1], $P['f'][3]);
            glas_demo_glass($im, (int) ($W * .1), (int) ($H * .44), (int) ($W * .9), (int) ($H * .72), (int) ($u * 1.2), $tint, $dark ? .45 : .38, $dark);
            break;
        default:  // Raster aus Kacheln
            for ($y = 0; $y < 2; $y++) for ($x = 0; $x < 3; $x++) {
                $x1 = (int) ($W * (.12 + $x * .27));
                $y1 = (int) ($H * (.18 + $y * .34));
                glas_demo_glass($im, $x1, $y1, $x1 + (int) ($W * .22), $y1 + (int) ($H * .26), (int) ($u * .6), $tint, $dark ? .45 : .36 + ($x + $y) * .06, $dark);
            }
            glas_demo_orb($im, (int) ($W * .5), (int) ($H * .5), (int) (2.4 * $u), $P['f'][2], $P['f'][3]);
    }
    glas_demo_save($im, $file, $w, $h, false);
}

/** Erfundene App-Oberfläche im Glasfenster (Querformat 4:3) – Kopfzeile, Seitenleiste, Diagramm, Karten */
function glas_demo_app(string $file, string $pal, int $seed): void
{
    $P = glas_demo_palettes()[$pal];
    $w = 1600; $h = 1200; $W = $w * 2; $H = $h * 2;
    mt_srand(7000 + $seed);
    $dark = $pal === 'nacht';
    $im = glas_demo_field($W, $H, $P['base'], [[.08, .1, .3, $P['f'][0], .95], [.95, .15, .28, $P['f'][1], .95], [.6, 1, .32, $P['f'][2], .95]]);
    $tint = $dark ? [30, 34, 66] : [255, 255, 255];
    $ink = $P['ink'];
    [$x1, $y1, $x2, $y2] = [(int) ($W * .08), (int) ($H * .1), (int) ($W * .92), (int) ($H * .9)];
    $r = (int) ($H * .04);
    glas_demo_glass($im, $x1, $y1, $x2, $y2, $r, $tint, $dark ? .55 : .5, $dark);
    // Fensterpunkte + Kopfzeile
    foreach ([0, 1, 2] as $k) imagefilledellipse($im, $x1 + 90 + $k * 60, $y1 + 90, 34, 34, imagecolorallocatealpha($im, $ink[0], $ink[1], $ink[2], glas_demo_a(.18)));
    glas_demo_bar($im, $x1 + 320, $y1 + 70, (int) (($x2 - $x1) * .3), 40, $ink, .12);
    // Seitenleiste
    for ($k = 0; $k < 5; $k++) {
        glas_demo_bar($im, $x1 + 80, $y1 + 220 + $k * 110, 360, 44, $k === 1 ? $P['f'][3] : $ink, $k === 1 ? .8 : .1);
    }
    // Diagramm auf einer inneren Glasfläche
    $cx1 = $x1 + 540; $cy1 = $y1 + 200; $cx2 = $x2 - 80; $cy2 = $y1 + (int) (($y2 - $y1) * .58);
    glas_demo_glass($im, $cx1, $cy1, $cx2, $cy2, (int) ($r * .7), $tint, $dark ? .35 : .55, $dark);
    imagesetthickness($im, 14);
    $pts = [];
    $n = 9;
    $v = .5;
    for ($k = 0; $k < $n; $k++) {
        $v = max(.12, min(.9, $v + mt_rand(-18, 22) / 100));
        $pts[] = [$cx1 + 70 + (int) (($cx2 - $cx1 - 140) * $k / ($n - 1)), $cy2 - 60 - (int) (($cy2 - $cy1 - 160) * $v)];
    }
    for ($k = 1; $k < $n; $k++) imageline($im, $pts[$k - 1][0], $pts[$k - 1][1], $pts[$k][0], $pts[$k][1], imagecolorallocate($im, ...$P['f'][3]));
    foreach ($pts as [$px, $py]) imagefilledellipse($im, $px, $py, 30, 30, imagecolorallocate($im, 255, 255, 255));
    imagesetthickness($im, 1);
    // Karten unten
    $cw = (int) (($cx2 - $cx1 - 80) / 3);
    for ($k = 0; $k < 3; $k++) {
        $kx = $cx1 + $k * ($cw + 40);
        glas_demo_glass($im, $kx, $cy2 + 60, $kx + $cw, $y2 - 80, (int) ($r * .6), $tint, $dark ? .35 : .6, $dark);
        glas_demo_orb($im, $kx + 110, $cy2 + 170, 100, $P['f'][$k], $P['f'][3]);
        glas_demo_bar($im, $kx + 60, $cy2 + 270, (int) ($cw * .6), 34, $ink, .22);
        glas_demo_bar($im, $kx + 60, $cy2 + 330, (int) ($cw * .4), 28, $ink, .12);
    }
    glas_demo_save($im, $file, $w, $h, false);
}

/** Telefon-Entwurf (Hochformat 4:5): Gerät mit Bildschirm aus Farbfeld und Glaskarten */
function glas_demo_phone(string $file, string $pal, int $seed): void
{
    $P = glas_demo_palettes()[$pal];
    $w = 1200; $h = 1500; $W = $w * 2; $H = $h * 2;
    mt_srand(5000 + $seed);
    $dark = $pal === 'nacht';
    $im = glas_demo_field($W, $H, $P['base'], [[.1, .2, .3, $P['f'][0], .95], [.9, .35, .28, $P['f'][1], .95], [.5, .95, .3, $P['f'][2], .95]]);
    $pw = (int) ($W * .5); $ph = (int) ($pw * 2.05);
    $px = (int) (($W - $pw) / 2); $py = (int) (($H - $ph) / 2);
    // Gehäuse
    glas_demo_glass($im, $px - 30, $py - 30, $px + $pw + 30, $py + $ph + 30, 150, [20, 22, 40], .92, true);
    $scr = glas_demo_field($pw, $ph, $dark ? [22, 24, 48] : [248, 246, 255], [[.2, .15, .35, $P['f'][0], .9], [.85, .4, .3, $P['f'][1], .85], [.4, .9, .35, $P['f'][2], .85]]);
    $mask = glas_demo_layer($pw, $ph);
    glas_demo_rrect($mask, 0, 0, $pw - 1, $ph - 1, 120, imagecolorallocatealpha($mask, 0, 0, 0, 0));
    // Bildschirm mit runden Ecken: Pixel außerhalb der Maske transparent übernehmen
    $screen = glas_demo_layer($pw, $ph);
    imagecopy($screen, $scr, 0, 0, 0, 0, $pw, $ph);
    $clear = imagecolorallocatealpha($screen, 0, 0, 0, 127);
    for ($y = 0; $y < 130; $y++) for ($x = 0; $x < 130; $x++) {
        foreach ([[$x, $y], [$pw - 1 - $x, $y], [$x, $ph - 1 - $y], [$pw - 1 - $x, $ph - 1 - $y]] as [$qx, $qy]) {
            if (((imagecolorat($mask, $qx, $qy) >> 24) & 0x7F) === 127) imagesetpixel($screen, $qx, $qy, $clear);
        }
    }
    imagealphablending($im, true);
    imagecopy($im, $screen, $px, $py, 0, 0, $pw, $ph);
   
    $tint = $dark ? [30, 34, 66] : [255, 255, 255];
    $ink = $P['ink'];
    // Kamera-Insel
    glas_demo_bar($im, $px + (int) ($pw * .36), $py + 40, (int) ($pw * .28), 64, [10, 10, 20], 1);
    // Inhalt: Begrüßung, große Karte, Liste
    glas_demo_bar($im, $px + 90, $py + 230, (int) ($pw * .5), 56, $ink, .7);
    glas_demo_bar($im, $px + 90, $py + 320, (int) ($pw * .34), 36, $ink, .35);
    glas_demo_glass($im, $px + 70, $py + 440, $px + $pw - 70, $py + 440 + (int) ($ph * .28), 70, $tint, $dark ? .45 : .5, $dark);
    glas_demo_orb($im, $px + 250, $py + 440 + (int) ($ph * .14), 230, $P['f'][1], $P['f'][3]);
    glas_demo_bar($im, $px + 440, $py + 440 + (int) ($ph * .1), (int) ($pw * .3), 44, $ink, .6);
    glas_demo_bar($im, $px + 440, $py + 440 + (int) ($ph * .16), (int) ($pw * .2), 32, $ink, .3);
    for ($k = 0; $k < 3; $k++) {
        $ky = $py + 560 + (int) ($ph * .28) + $k * 230;
        glas_demo_glass($im, $px + 70, $ky, $px + $pw - 70, $ky + 180, 56, $tint, $dark ? .45 : .55, $dark);
        imagefilledellipse($im, $px + 170, $ky + 90, 100, 100, imagecolorallocate($im, ...$P['f'][$k]));
        glas_demo_bar($im, $px + 250, $ky + 60, (int) ($pw * .42), 34, $ink, .55);
        glas_demo_bar($im, $px + 250, $ky + 110, (int) ($pw * .26), 26, $ink, .25);
    }
    glas_demo_bar($im, $px + (int) ($pw * .35), $py + $ph - 60, (int) ($pw * .3), 16, $ink, .55);
    glas_demo_save($im, $file, $w, $h, false);
}

/** Verkleinern (glatte Kanten) und speichern */
function glas_demo_save(\GdImage $im, string $file, int $w, int $h, bool $png): void
{
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
    $png ? imagepng($out, $file, 7) : imagejpeg($out, $file, 88);
   
   
}

/** @return array{all: list<int>, wide: list<int>, tall: list<int>, apps: list<int>, collection: int} */
function glas_demo_images(callable $log): array
{
    $col = Media::createCollection(GLAS_DEMO_COLLECTION, 'Beispielbilder des Kits „Glas“ – Farbfelder mit Glasflächen und erfundene App-Oberflächen, automatisch erzeugt, frei verwendbar, ohne Personen oder Marken.');
    $import = function (string $file, string $name, string $title, string $alt, bool $inCollection) use ($col, $log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => GLAS_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt']
            + ($inCollection ? ['collection' => $col] : []));
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    $all = $wide = $tall = $apps = [];
    $comps = [
        ['Aurora mit Glaskarte', 'aurora'], ['Gestaffelte Scheiben', 'lagune'], ['Perlen hinter Glas', 'daemmerung'], ['Glaskacheln', 'aurora'],
        ['Nachtglas', 'nacht'], ['Lagune mit Leiste', 'lagune'], ['Dämmerungsscheiben', 'daemmerung'], ['Nachtkacheln', 'nacht'],
    ];
    foreach ($comps as $i => [$title, $pal]) {
        $file = tempnam(sys_get_temp_dir(), 'gl') . '.jpg';
        glas_demo_comp($file, $pal, $i);
        if ($id = $import($file, sprintf('labor-feld-%02d.jpg', $i + 1), $title, 'Abstraktes Farbfeld mit Glasflächen: ' . $title . ' (Beispielbild)', true)) { $all[] = $id; $wide[] = $id; }
    }
    foreach ([['Telefon-Entwurf Aurora', 'aurora'], ['Telefon-Entwurf Lagune', 'lagune'], ['Telefon-Entwurf Nacht', 'nacht']] as $k => [$title, $pal]) {
        $file = tempnam(sys_get_temp_dir(), 'gl') . '.jpg';
        glas_demo_phone($file, $pal, $k);
        if ($id = $import($file, sprintf('labor-telefon-%02d.jpg', $k + 1), $title, 'Entwurf einer erfundenen App auf einem Telefon: Glaskarten über einem Farbfeld (Beispielbild)', true)) { $all[] = $id; $tall[] = $id; }
    }
    foreach ([['Lichtung', 'lagune'], ['Tidenhub', 'aurora'], ['Mondkarte', 'nacht'], ['Beetplan', 'lagune'], ['Laufband', 'daemmerung'], ['Hofgrün', 'aurora']] as $k => [$title, $pal]) {
        $file = tempnam(sys_get_temp_dir(), 'gl') . '.jpg';
        glas_demo_app($file, $pal, $k);
        if ($id = $import($file, 'labor-app-' . ($k + 1) . '.jpg', 'App „' . $title . '“ (Entwurf)', 'Oberfläche der erfundenen App „' . $title . '“: Glasfenster mit Diagramm und Karten (Beispielbild)', false)) { $all[] = $id; $apps[] = $id; }
    }
    $log(count($all) . ' Beispielbilder erzeugt.');
    return ['all' => $all, 'wide' => $wide, 'tall' => $tall, 'apps' => $apps, 'collection' => $col];
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function glas_demo_pdf(callable $log): ?int
{
    $text = 'Lumen Labs - Projektsteckbrief (Demo). Frei erfundener Inhalt.';
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
    $file = tempnam(sys_get_temp_dir(), 'gl') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'labor-steckbrief.pdf', '', ['title' => 'Projektsteckbrief (Beispiel)', 'tags' => GLAS_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function glas_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function glas_demo_tables(array $img, callable $log): array
{
    $out = [];
    $A = fn(int $n) => $img['apps'][$n % max(1, count($img['apps']))] ?? null;

    // Projekte: Karten, Liste, kompakt, Tabelle, Detailseite
    $t = glas_demo_table([
        'handle' => 'labor_projekte', 'name' => 'Projekte (Labor)', 'singular' => 'Projekt', 'icon' => 'device-mobile',
        'description' => 'Beispiel-Tabelle der Musterseiten – frei erfundene App-Projekte.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'kunde', 'label' => 'Auftrag (fiktiv)', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'plattform', 'label' => 'Plattform', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "ios=iOS\nandroid=Android\nweb=Web-App\nalle=iOS, Android & Web"],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'dauer', 'label' => 'Dauer', 'type' => 'text', 'width' => 'half'],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'projekte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'jahr', 'sort_dir' => 'desc', 'workflow' => 0, 'jsonld' => 'CreativeWork'],
    ], $log);
    if ($t) {
        $rows = [
            ['Lichtung', 'Waldschule (fiktiv)', 'alle', 2026, '10 Wochen', 'Eine Lern-App, die Pflanzen per Foto erklärt – offline nutzbar, ohne Konto.'],
            ['Tidenhub', 'Segelverein (fiktiv)', 'ios', 2025, '8 Wochen', 'Gezeiten, Wind und Liegeplätze auf einem Bildschirm, gut lesbar auch in der Sonne.'],
            ['Mondkarte', 'Sternwarte (fiktiv)', 'web', 2025, '6 Wochen', 'Eine Web-App für den Nachthimmel mit rotem Nachtmodus, der die Augen schont.'],
            ['Beetplan', 'Gemeinschaftsgarten (fiktiv)', 'android', 2024, '7 Wochen', 'Beete planen, Gießdienste tauschen, Ernte teilen – für Menschen ohne Technik-Lust.'],
            ['Laufband', 'Sportverein (fiktiv)', 'alle', 2024, '12 Wochen', 'Ein Lauf-Coach, der nicht drängelt: kurze Pläne, klare Erinnerungen, keine Rangliste.'],
            ['Hofgrün', 'Wohnprojekt (fiktiv)', 'web', 2023, '5 Wochen', 'Nachbarschaftsbrett mit Reservierung für Werkstatt, Lastenrad und Gästezimmer.'],
        ];
        foreach ($rows as $i => [$title, $client, $platform, $year, $dur, $text]) {
            Entries::save($t, null, ['titel' => $title, 'kunde' => $client, 'plattform' => $platform, 'jahr' => (string) $year, 'dauer' => $dur, 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Ein frei erfundenes Projekt, das zeigt, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h2>Aufgabe</h2><p>Die wichtigste Aufgabe in höchstens drei Schritten erledigen – auch mit großer Schrift und Screenreader.</p>'
                    . '<blockquote><p>Klar wie Glas: Man sieht, was man tun kann – und nichts, was ablenkt.</p></blockquote>'
                    . '<h2>Ergebnis</h2><ul><li>Klick-Prototyp nach zehn Tagen (Beispiel)</li><li>Tests mit fünf Menschen aus der Zielgruppe</li><li>Kontraste und Schriftgrößen nach WCAG 2.2 AA</li></ul>',
                'bild' => $A($i), 'status' => 'published']);
        }
        $out['projekte'] = $t;
    }

    // Termine: Kalender mit Wiederholung, ganztägigem Termin und Kategorien
    $t = glas_demo_table([
        'handle' => 'labor_termine', 'name' => 'Termine (Labor)', 'singular' => 'Termin', 'icon' => 'calendar-dots',
        'description' => 'Beispiel-Kalender der Musterseiten – Termine relativ zum Tag der Anlage.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'datetime', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ganztaegig', 'label' => 'Ganztägig', 'type' => 'bool'],
            ['name' => 'wiederholung', 'label' => 'Wiederholung', 'type' => 'recurrence'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'in_list' => true],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'textarea'],
            ['name' => 'kategorie', 'label' => 'Kategorie', 'type' => 'select', 'in_list' => true, 'options' => "sprechstunde=Offene Sprechstunde\ntalk=Vortrag\nworkshop=Workshop\ngeschlossen=Geschlossen"],
        ],
        'settings' => ['title_field' => 'titel', 'description_field' => 'ort', 'sort_field' => 'beginn', 'sort_dir' => 'asc', 'workflow' => 0,
            'calendar' => ['enabled' => 1, 'start' => 'beginn', 'end' => 'ende', 'all_day' => 'ganztaegig', 'recurrence' => 'wiederholung',
                'location' => 'ort', 'description' => 'beschreibung', 'category' => 'kategorie', 'duration' => 60, 'feed' => 1]],
    ], $log);
    if ($t) {
        $d = fn(int $days, string $time) => date('Y-m-d', strtotime("+$days days")) . ' ' . $time;
        $thu = (int) date('N') <= 4 ? 4 - (int) date('N') : 11 - (int) date('N');
        $until = gmdate('Ymd\THis\Z', strtotime('+120 days'));
        $events = [
            ['Offene UX-Sprechstunde', $d($thu, '16:00'), $d($thu, '17:30'), false, "FREQ=WEEKLY;BYDAY=TH;UNTIL=$until", 'Studio (fiktiv) und per Video', 'Jeden Donnerstag: Prototypen zeigen, Fragen stellen, gemeinsam testen.', 'sprechstunde'],
            ['Vortrag: Glas, aber lesbar', $d(6, '18:30'), $d(6, '20:00'), false, '', 'Online', 'Wie Transparenz schön bleibt und trotzdem 4,5:1 Kontrast hält.', 'talk'],
            ['Workshop: Barrierefreie Apps', $d(13, '10:00'), $d(13, '16:00'), false, '', 'Seminarraum (fiktiv)', 'Screenreader, Schriftgrößen, Tastatur – mit echten Geräten.', 'workshop'],
            ['Studio geschlossen', $d(20, '00:00'), '', true, '', '', 'An diesem Tag ist das Studio geschlossen.', 'geschlossen'],
            ['Workshop: Vom Einfall zum Prototyp', $d(27, '10:00'), $d(27, '13:00'), false, '', 'Studio (fiktiv)', 'In drei Stunden zum klickbaren Entwurf.', 'workshop'],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $text, $cat]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'status' => 'published']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // Öffentliches Formular: Anfrage mit Bedingung (Budget nur bei „Neues Projekt“)
    $t = glas_demo_table([
        'handle' => 'labor_anfragen', 'name' => 'Anfragen (Labor)', 'singular' => 'Anfrage', 'icon' => 'envelope-simple',
        'description' => 'Öffentliches Beispiel-Formular der Musterseiten.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'anliegen', 'label' => 'Anliegen', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => "frage=Kurze Frage\nprojekt=Neues Projekt\nworkshop=Workshop oder Termin"],
            ['name' => 'budget', 'label' => 'Rahmen (ungefähr)', 'type' => 'select', 'width' => 'half', 'options' => "klein=bis 10.000 €\nmittel=10.000–50.000 €\ngross=über 50.000 €",
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]]],
            ['name' => 'nachricht', 'label' => 'Nachricht', 'type' => 'textarea', 'required' => true, 'help' => 'Zwei, drei Sätze genügen.'],
            ['name' => 'rueckruf', 'label' => 'Bitte rufen Sie mich zurück', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke – Ihre Anfrage ist eingegangen (Demo: es meldet sich niemand).', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['anfragen'] = $t;

    $log(count($out) . ' Datentabellen angelegt (labor_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function glas_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $W = fn(int $n) => $img['wide'][$n % max(1, count($img['wide']))] ?? null;
    $T = fn(int $n) => $img['tall'][$n % max(1, count($img['tall']))] ?? null;
    $A = fn(int $n) => $img['apps'][$n % max(1, count($img['apps']))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text]);
    $cta = $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Alles gesehen? Dann *los*.', 'text' => 'Farbfeld, Glasdichte, Schriften, Kopf und Fuß stellen Sie unter Verwaltung → Design ein.',
        'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/labor'], ['background' => 'dark']);
    $proj = $tables['projekte']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';
    $form = $tables['anfragen']['handle'] ?? '';
    $ti = fn(string $v, string $eyebrow, string $title, string $text, ?int $image, string $ratio = '4:3', string $list = '', array $btn = []) => [
        'variant' => $v, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'list' => $list, 'image' => $image, 'ratio' => $ratio,
        'button_label' => $btn[0] ?? '', 'button_link' => $btn[1] ?? '', 'button2_label' => $btn[2] ?? '', 'button2_link' => $btn[3] ?? ''];

    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));

    // ---------------------------------------------------------------- Übersicht (Musterseite des Style-Editors)
    $root = $page(['slug' => 'labor', 'title' => 'Labor', 'sort' => $sort, 'og_image' => $W(0),
        'meta_description' => 'Alle Blöcke des Kits „Glas“ mit frei erfundenen Beispielinhalten des Studios Lumen Labs.'], [
        $B('hero', ['variant' => 'aurora', 'eyebrow' => 'Musterseiten', 'title' => 'Jede Fläche ist *lesbar*.',
            'text' => 'Diese Seiten zeigen alle Bausteine des Kits – mit erfundenen Apps, Zahlen und Terminen. Wählen Sie unter Verwaltung → Design eine Vorlage und sehen Sie, wie sich Farbfeld und Glas mitändern.',
            'button_label' => 'Projekte ansehen', 'button_link' => '/labor/projekte', 'button2_label' => 'Journal lesen', 'button2_link' => '/labor/journal',
            'points' => "Vorlagen: 4\nKontrast: ≥ 4,5:1", 'image' => $T(0), 'ratio' => '4:5']),
        $B('cards', ['variant' => 'glass', 'eyebrow' => 'Karten „Glaskarte“', 'title' => 'Drei Projekte, ein Maßstab', 'intro' => 'Erfundene Apps – gezeichnet, nicht fotografiert.', 'size' => 'm', 'ratio' => '4:3',
            'items' => [
                ['image' => $A(0), 'icon' => '', 'eyebrow' => 'iOS, Android & Web', 'title' => 'Lichtung', 'text' => 'Pflanzen erklären, offline.', 'meta' => '10 Wochen', 'link_label' => 'Details', 'link' => '/labor/projekte'],
                ['image' => $A(1), 'icon' => '', 'eyebrow' => 'iOS', 'title' => 'Tidenhub', 'text' => 'Gezeiten auf einen Blick.', 'meta' => '8 Wochen', 'link_label' => 'Details', 'link' => '/labor/projekte'],
                ['image' => $A(2), 'icon' => '', 'eyebrow' => 'Web-App', 'title' => 'Mondkarte', 'text' => 'Der Himmel mit Nachtmodus.', 'meta' => '6 Wochen', 'link_label' => 'Details', 'link' => '/labor/projekte'],
            ], 'more_label' => 'Alle Projekte', 'more_link' => '/labor/projekte']),
        $B('features', ['variant' => 'panel', 'eyebrow' => 'Merkmale „Ein Glaspaneel“', 'title' => 'Was das Kit mitbringt', 'intro' => '', 'size' => 's',
            'items' => [
                ['icon' => 'shield-check', 'image' => null, 'title' => 'WCAG 2.2 AA', 'text' => 'Jede Glasfläche hell und dunkel geprüft.', 'link_label' => '', 'link' => ''],
                ['icon' => 'lightning', 'image' => null, 'title' => 'Leicht', 'text' => 'Farbfeld aus Verläufen – keine Bilddateien.', 'link_label' => '', 'link' => ''],
                ['icon' => 'lock', 'image' => null, 'title' => 'Datensparsam', 'text' => 'Keine Cookies, Schriften vom eigenen Server.', 'link_label' => '', 'link' => ''],
                ['icon' => 'eye', 'image' => null, 'title' => 'Rücksichtsvoll', 'text' => 'Deckend bei „Transparenz reduzieren“.', 'link_label' => '', 'link' => ''],
            ]], ['background' => 'muted']),
        $B('text_image', $ti('auto', 'Text + Bild', 'Erst die Aufgabe, dann das Glas', '<p>Bevor eine Fläche durchscheinen darf, steht fest, was darauf gelesen werden muss. Die Deckkraft folgt der Lesbarkeit – nicht umgekehrt.</p>', $W(0), '4:3',
            "Inhalte ordnen\nKontrast prüfen\nTransparenz dosieren", ['Mehr im Journal', '/labor/journal'])),
        $B('text_image', $ti('auto', 'Abwechselnd', 'Tiefe ohne Schwere', '<p>Mehrere Text-Bild-Blöcke nacheinander wechseln automatisch die Seite – ohne Einstellung. Das Bild liegt in einem schmalen Glasrahmen.</p>', $W(4), '4:3')),
        $B('stats', ['variant' => 'row', 'eyebrow' => 'Kennzahlen „Große Zahlen“', 'title' => 'Mit Pegel', 'intro' => '',
            'items' => [
                ['value' => '4', 'label' => 'Vorlagen', 'level' => '', 'text' => 'hell und dunkel'],
                ['value' => '3', 'label' => 'Glasdichten', 'level' => '', 'text' => 'alle ≥ 4,5:1'],
                ['value' => '97', 'label' => 'Lighthouse (Beispiel)', 'level' => '97', 'text' => 'Leistung, fiktiv'],
            ]], ['background' => 'muted']),
        $B('dials', ['eyebrow' => 'Kern-Block „Kennzahlen mit Skala“', 'title' => 'Skalen auf Glas', 'intro' => 'Mit Einheit, Höchstwert und Hochzählen.', 'size' => 'm', 'style' => 'arc', 'animate' => true,
            'items' => [
                ['value' => '0,8', 'unit' => 's', 'label' => 'Startzeit der App', 'text' => 'Median, Beispiel', 'level' => '', 'number' => 0.8, 'max' => 3],
                ['value' => '92', 'unit' => '%', 'label' => 'Aufgabe gelöst', 'text' => 'Nutzertest, fiktiv', 'level' => '', 'number' => null, 'max' => null],
                ['value' => '4,8', 'unit' => '', 'label' => 'Store-Bewertung', 'text' => 'von 5', 'level' => '', 'number' => 4.8, 'max' => 5],
            ]]),
        $B('team', ['variant' => 'compact', 'eyebrow' => 'Team „Kompakt“', 'title' => 'Ansprechpersonen', 'intro' => 'Erfundene Personen – Initialen statt Fotos.', 'size' => 'm',
            'items' => [
                ['name' => 'Mara Lindqvist', 'role' => 'Produkt & Strategie', 'text' => '', 'image' => null, 'email' => 'mara@example.com'],
                ['name' => 'Jonas Adeyemi', 'role' => 'UX-Forschung', 'text' => '', 'image' => null, 'email' => 'jonas@example.com'],
                ['name' => 'Ilka Brandt', 'role' => 'UI-Gestaltung', 'text' => '', 'image' => null, 'email' => ''],
                ['name' => 'Tomás Ferreira', 'role' => 'iOS & Android', 'text' => '', 'image' => null, 'email' => ''],
                ['name' => 'Wen Hartmann', 'role' => 'Web & Barrierefreiheit', 'text' => '', 'image' => null, 'email' => ''],
                ['name' => 'Rafaela Nowak', 'role' => 'Projektbegleitung', 'text' => '', 'image' => null, 'email' => ''],
            ]], ['background' => 'muted']),
        $B('features', ['variant' => 'list', 'eyebrow' => 'Merkmale „Liste“', 'title' => 'Drei Regeln für Glas', 'intro' => '', 'size' => 'l',
            'items' => [
                ['icon' => 'sparkle', 'image' => null, 'title' => 'Hinter Text nur ruhige Flächen', 'text' => 'Das Farbfeld ist weich – Glas darüber bleibt gleichmäßig.', 'link_label' => '', 'link' => ''],
                ['icon' => 'sliders-horizontal', 'image' => null, 'title' => 'Deckkraft je Farbschema', 'text' => 'Im Dunkeln etwas dichter, weil helle Schrift mehr Ruhe braucht.', 'link_label' => '', 'link' => ''],
                ['icon' => 'device-mobile', 'image' => null, 'title' => 'Weniger Unschärfe auf Telefonen', 'text' => 'Dort ist Glas nur getönt – flüssiges Scrollen geht vor.', 'link_label' => '', 'link' => ''],
            ]]),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Stimmen (Beispiele)', 'title' => 'Was andere sagen', 'items' => [
            ['text' => 'Schön durchscheinend – und trotzdem kann ich alles lesen.', 'name' => 'Kim Muster', 'role' => 'Beispielkundin (fiktiv)', 'image' => null],
            ['text' => 'Auf dem alten Telefon läuft es genauso flüssig.', 'name' => 'Sam Beispiel', 'role' => 'Verein (fiktiv)', 'image' => null],
            ['text' => 'Mit „Transparenz reduzieren“ wird alles ruhig und deckend. Danke!', 'name' => 'Jo Probe', 'role' => 'Praxis (fiktiv)', 'image' => null],
        ]], ['background' => 'tint']),
        $cta,
    ]);

    // ---------------------------------------------------------------- Projekte (Datenlisten, Detailseite, Termine)
    if ($proj !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-labor-projekte', 'title' => 'Projekte (Labor) – Detailseite', 'type' => 'template', 'template_for' => $proj, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.kunde", "$proj.plattform"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => 'Alle Projekte', 'back_link' => '/labor/projekte'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.bild"], 'layout' => 'image', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small', 'background' => 'white']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext", "$proj.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kunde", "$proj.plattform", "$proj.dauer", "$proj.jahr"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => '', 'title' => 'Weitere Projekte', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.plattform"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '4:3', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
        ]));
        $t = Tables::find($proj);
        $t['settings']['detail_page_id'] = $tpl;
        app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $dl = fn(string $table, string $layout, array $extra) => $extra + ['eyebrow' => '', 'intro' => '', 'table' => $table, 'layout' => $layout, 'columns' => '3', 'ratio' => '4:3', 'limit' => 0,
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $page(['slug' => 'projekte', 'title' => 'Projekte & Termine', 'parent_id' => $root, 'sort' => 1,
        'meta_description' => 'Datenlisten als Karten, Liste, kompakt und Tabelle mit Detailseite; Kalender und nächste Termine.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Einstieg „Text und Bild“', 'title' => 'Einmal pflegen, überall zeigen.', 'text' => 'Projekte stehen in einer Datentabelle und erscheinen als Karten, Liste oder Tabelle – mit eigener Detailseite.',
            'button_label' => 'Zu den Terminen', 'button_link' => '#termine', 'button2_label' => '', 'button2_link' => '', 'points' => "Detailseite aus einer Vorlage\nFilter, Sortierung, Blättern", 'image' => $T(1), 'ratio' => '4:5']),
        $B('data_list', $dl($proj, 'cards', ['eyebrow' => 'Datenliste „Karten“', 'title' => 'Projekte', 'fields' => ["$proj._title", "$proj.bild", "$proj.plattform", "$proj.kurztext"], 'limit' => 6]), ['background' => 'muted']),
        $B('data_list', $dl($proj, 'list', ['eyebrow' => 'Datenliste „Liste mit Bild“', 'title' => 'Als Liste', 'fields' => ["$proj._title", "$proj.bild", "$proj.kunde", "$proj.kurztext"], 'limit' => 3])),
        $B('data_list', $dl($proj, 'table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Alle Projekte', 'fields' => ["$proj._title", "$proj.kunde", "$proj.plattform", "$proj.dauer", "$proj.jahr"]]), ['anchor' => 'tabelle', 'background' => 'muted']),
        $B('data_list', $dl($proj, 'compact', ['eyebrow' => 'Datenliste „Kompakt“', 'title' => 'Kurzübersicht', 'fields' => ["$proj._title", "$proj.plattform", "$proj.jahr"], 'limit' => 4])),
        $B('upcoming', ['eyebrow' => 'Nächste Termine', 'title' => 'Demnächst im Studio', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true, 'link_detail' => false, 'subscribe' => true, 'more_label' => '', 'more_link' => ''], ['anchor' => 'termine', 'background' => 'muted']),
        $B('calendar', ['eyebrow' => 'Kalender', 'title' => 'Monatsübersicht', 'intro' => 'Bei wenig Platz wird die Übersicht zur Liste der Tage mit Terminen.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => false]),
        $B('upcoming', ['eyebrow' => 'Kompakt in „Nacht“', 'title' => 'Die nächsten drei', 'intro' => '', 'table' => $term, 'limit' => 3, 'days' => 0, 'layout' => 'compact', 'show_location' => true, 'link_detail' => false, 'subscribe' => false], ['background' => 'dark']),
    ]);

    // ---------------------------------------------------------------- Formular & Kontakt
    $page(['slug' => 'formular', 'title' => 'Formular & Kontakt', 'parent_id' => $root, 'sort' => 2,
        'meta_description' => 'Öffentliches Formular mit Bedingung, Fehler- und Erfolgsmeldung; Kontakt mit Öffnungszeiten und Karte.'], [
        $hero('Formular', 'Anfragen, die man gern ausfüllt.', 'Einträge landen in einer Datentabelle – Spamschutz ohne Cookies. Absenden ist gefahrlos: es ist eine Demo-Tabelle.'),
        $B('data_form', ['eyebrow' => 'Öffentliches Formular', 'title' => 'Anfrage (Beispiel)', 'intro' => '„Neues Projekt“ blendet ein weiteres Pflichtfeld ein. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $form, 'submit_label' => '', 'success_text' => '']),
        $B('contact', ['eyebrow' => 'Kontakt', 'title' => 'So erreichen Sie uns', 'intro' => 'Angaben und Öffnungszeiten kommen aus „Website“; „Jetzt geöffnet“ rechnet im Browser.', 'show_hours' => true, 'show_map' => true,
            'form_table' => $form, 'form_title' => 'Kurze Nachricht', 'submit_label' => '', 'note' => 'Beispieladresse – frei erfunden.'], ['anchor' => 'kontakt', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Medien
    $page(['slug' => 'medien', 'title' => 'Medien', 'parent_id' => $root, 'sort' => 3,
        'meta_description' => 'Galerie mit Lightbox, Slider, Stapelkarten, Video mit Zwei-Klick-Lösung, Downloads und Karte.'], [
        $B('hero', ['variant' => 'statement', 'eyebrow' => 'Einstieg „Große Aussage“', 'title' => 'Bilder, Video, Dateien, Karte.', 'text' => 'Alles kommt vom eigenen Server – Videos von YouTube oder Vimeo erst nach einem Klick (oder nach Einwilligung im Cookie-Hinweis).',
            'button_label' => 'Zur Galerie', 'button_link' => '#galerie', 'button2_label' => '', 'button2_link' => '', 'points' => "Galerie: Raster, Mosaik, Zeilen\nSlider mit Tastatur\nPDF im Browser", 'image' => $W(2), 'ratio' => '16:9']),
        $B('gallery', ['eyebrow' => 'Galerie „Raster“', 'title' => 'Farbfelder und Entwürfe', 'intro' => 'Ein Klick öffnet die Lightbox.', 'source' => 'collection', 'collection' => $img['collection'],
            'layout' => 'grid', 'columns' => '3', 'ratio' => '3:2', 'captions' => true, 'lightbox' => true], ['anchor' => 'galerie']),
        $B('cards', ['variant' => 'overlay', 'eyebrow' => 'Karten „Bild mit Glasleiste“', 'title' => 'Text schwebt auf Glas', 'intro' => '', 'size' => 'm', 'ratio' => '3:2',
            'items' => [
                ['image' => $W(1), 'icon' => '', 'eyebrow' => 'Lagune', 'title' => 'Gestaffelte Scheiben', 'text' => 'Die Glasleiste ist dicht genug für jede Schrift.', 'meta' => '', 'link_label' => '', 'link' => ''],
                ['image' => $W(4), 'icon' => '', 'eyebrow' => 'Nacht', 'title' => 'Nachtglas', 'text' => 'Auch über dunklen Bildern lesbar.', 'meta' => '', 'link_label' => '', 'link' => ''],
                ['image' => $W(6), 'icon' => '', 'eyebrow' => 'Dämmerung', 'title' => 'Warme Scheiben', 'text' => 'Farbfeld, Glas, Lichtkante.', 'meta' => '', 'link_label' => '', 'link' => ''],
            ]], ['background' => 'muted']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Folien mit Text', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $W(4), 'eyebrow' => 'Folie 1', 'title' => 'Nachtglas', 'text' => 'Abgedunkelt mit heller Schrift.', 'button_label' => 'Mehr', 'button_link' => '/labor', 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $W(0), 'eyebrow' => 'Folie 2', 'title' => 'Textkasten', 'text' => 'Ein Kasten in der Hintergrundfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
            ['image' => $W(5), 'eyebrow' => 'Folie 3', 'title' => 'Aufgehellt', 'text' => 'Dunkle Schrift auf hellem Schleier.', 'button_label' => '', 'button_link' => '', 'position' => 'center', 'overlay' => 'light'],
        ]]),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Karten, die sich stapeln', 'intro' => 'Nur bei genug Platz und ohne „Bewegung reduzieren“ – sonst eine ruhige Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Verstehen', 'text' => 'Ziele, Menschen, Rahmen.', 'image' => $A(3), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Prototyp', 'text' => 'Klickbar nach zehn Tagen.', 'image' => $A(4), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Veröffentlichen', 'text' => 'Getestet, barrierearm, im Store.', 'image' => $A(5), 'link_label' => 'Kontakt', 'link' => '/kontakt'],
        ]], ['background' => 'muted']),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video „Mit Text“', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen. Mit dem Cookie-Hinweis (Erweiterung „Consent Kit“) gilt dessen Einwilligung.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W(3), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Dateien', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true, 'collection' => null,
            'files' => $pdf ? [['label' => 'Projektsteckbrief (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []], ['background' => 'muted']),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort', 'location' => 'site', 'zoom' => '13', 'height' => 'm', 'route' => true]),
    ]);

    // ---------------------------------------------------------------- Journal (Artikel, Glastext, FAQ)
    $page(['slug' => 'journal', 'title' => 'Journal', 'parent_id' => $root, 'sort' => 4, 'og_image' => $W(2),
        'meta_description' => 'Artikel mit Lesebreite, Inhaltsverzeichnis und Zitat; Text auf Glas; Fragen und Antworten.'], [
        $hero('Journal', 'Glas, aber lesbar', 'Ein Beispielartikel mit Inhaltsverzeichnis, Zwischenüberschriften und Zitat.'),
        $B('richtext', ['variant' => 'article', 'eyebrow' => 'Beitrag', 'title' => 'Transparenz mit Maß', 'intro' => '', 'meta' => 'Redaktion Lumen Labs · Beispieltext', 'toc' => true, 'text' =>
            '<p>Durchscheinende Flächen sind schön – bis man etwas darauf lesen muss. Dieser Text ist frei erfunden und zeigt die Gestaltung eines Artikels.</p>'
            . '<h2>Was hinter dem Glas liegt</h2><p>Ein unruhiger Hintergrund macht jede Schrift schwerer lesbar. Deshalb liegt hinter unseren Glasflächen nur ein weiches Farbfeld aus Verläufen – ohne Kanten, ohne Details.</p>'
            . '<blockquote><p>Die Deckkraft folgt der Lesbarkeit – nicht umgekehrt.</p></blockquote>'
            . '<h2>Deckkraft je Farbschema</h2><p>Im hellen Schema genügt weniger Tönung, im dunklen braucht helle Schrift etwas mehr Ruhe. Jede Stufe ist so gewählt, dass Text mindestens 4,5:1 Kontrast hält.</p><h3>Die drei Stellschrauben</h3><ul><li>Tönung statt Farbe</li><li>Unschärfe statt Muster</li><li>Lichtkante statt Rahmen</li></ul>'
            . '<h2>Rücksicht</h2><p>Wer im Gerät „Transparenz reduzieren“ oder „Mehr Kontrast“ einstellt, bekommt deckende Flächen. Ohne Unterstützung für Unschärfe ebenso.</p>'
            . '<h2>Was bleibt</h2><p>Eine Oberfläche, die leicht wirkt und sich trotzdem mühelos lesen lässt. <strong>Das ist das Ziel.</strong></p>']),
        $B('richtext', ['variant' => 'glass', 'eyebrow' => 'Fließtext „Auf einer Glasfläche“', 'title' => 'Kurz notiert', 'intro' => '', 'meta' => '', 'toc' => false, 'text' =>
            '<p class="t-lead">Hervorgehobener Einstieg: So wirkt ein Absatz, der mehr sagen soll als die übrigen.</p><p>Normaler Absatz mit <a href="/labor">einem Link</a>, <mark>markiertem Text</mark> und <span class="c-accent">Akzentfarbe</span>.</p><p class="t-note">Hinweis-Box: Fläche mit Linie links – auch auf Glas lesbar.</p><p class="t-small">Kleiner Text für Anmerkungen.</p>'], ['background' => 'tint']),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'Fragen „Untereinander“', 'title' => 'Fragen zum Kit', 'intro' => 'Suchmaschinen erhalten sie als strukturierte Daten.', 'items' => [
            ['q' => 'Ist Glas nicht schlecht lesbar?', 'a' => '<p>Nur ohne Maß. Hier hat jede Glasfläche eine geprüfte Mindestdeckkraft – hell wie dunkel.</p>'],
            ['q' => 'Brauche ich Bilder?', 'a' => '<p>Nein. Das Farbfeld und die Aurora bestehen aus Verläufen; ohne Bilder zeigt der Einstieg Glasplättchen oder ein Prisma.</p>'],
            ['q' => 'Läuft das auf alten Telefonen?', 'a' => '<p>Ja. Auf Touch-Geräten ist das Glas nur getönt, die Aurora bewegt sich nicht, und außer Sicht pausiert sie ganz.</p>'],
        ]], ['background' => 'muted']),
        $cta,
    ]);
    $log('Seitenbaum „Labor“ angelegt (/labor).');
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“

/**
 * Kurzes, stummes Loop-Video aus einem erzeugten Farbfeld (ffmpeg: sanftes Zoomen hin und zurück, nahtlose Schleife)
 * + das Bild als Standbild. Ohne ffmpeg: nur das Standbild (die Variante zeigt es dann ohne Video).
 * @return array{video: ?int, still: ?int}
 */
function glas_demo_hero_video(callable $log): array
{
    $db = app()->db;
    $find = fn(string $name) => ($r = $db->fetch('SELECT id FROM media WHERE tags LIKE ? AND original_name = ? ORDER BY id DESC LIMIT 1', ['%' . GLAS_DEMO_TAG . '%', $name])) ? (int) $r['id'] : null;
    $video = $find('labor-aurora-loop.mp4');
    $still = $find('labor-aurora-standbild.jpg');
    if ($video && $still) return ['video' => $video, 'still' => $still];
    $img = tempnam(sys_get_temp_dir(), 'glv') . '.jpg';
    glas_demo_comp($img, 'nacht', 5, 1920, 1080);
    $mp4 = tempnam(sys_get_temp_dir(), 'glv') . '.mp4';
    $ff = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($ff !== '' && !$video) {
        // 8 s bei 30 fps; Zoom 1,04 … 1,16 und leichte Drift als Sinus über genau eine Periode → Anfang = Ende (nahtlos)
        $vf = "scale=3840:-2,zoompan=z='1.10+0.06*sin(2*PI*on/240)':x='iw/2-(iw/zoom/2)+90*sin(2*PI*on/240)':y='ih/2-(ih/zoom/2)+50*cos(2*PI*on/240)':d=1:s=1280x720:fps=30";
        $cmd = escapeshellarg($ff) . ' -y -loglevel error -loop 1 -framerate 30 -i ' . escapeshellarg($img) . ' -vf ' . escapeshellarg($vf)
            . ' -t 8 -an -c:v libx264 -preset slow -pix_fmt yuv420p -movflags +faststart -crf 30 ' . escapeshellarg($mp4) . ' 2>&1';
        exec($cmd, $out, $rc);
        if ($rc === 0 && is_file($mp4) && filesize($mp4) > 0) {
            [$m, $err] = Media::import($mp4, 'labor-aurora-loop.mp4', '', ['title' => 'Aurora-Schleife (Beispielvideo)', 'tags' => GLAS_DEMO_TAG,
                'credit' => 'Beispielvideo, automatisch aus einem erzeugten Farbfeld erstellt', 'decorative' => true]);
            if ($err) $log('Video: ' . $err);
            $video = $m ? (int) $m['id'] : null;
        } else {
            $log('Video: ffmpeg fehlgeschlagen – die Variante zeigt nur das Standbild. ' . implode(' ', array_slice($out ?? [], 0, 2)));
        }
    } elseif ($ff === '') {
        $log('Video: ffmpeg nicht gefunden – die Variante zeigt nur das Standbild.');
    }
    if (!$still) {
        [$m, $err] = Media::import($img, 'labor-aurora-standbild.jpg', '', ['title' => 'Aurora-Standbild (Beispielbild)', 'tags' => GLAS_DEMO_TAG,
            'credit' => 'Beispielbild, automatisch erzeugt', 'decorative' => true]);
        if ($err) $log('Standbild: ' . $err);
        $still = $m ? (int) $m['id'] : null;
    }
    @unlink($img);
    @unlink($mp4);
    return ['video' => $video, 'still' => $still];
}

/**
 * Musterseite „Hero-Varianten“ (/labor/hero-varianten): die neuen Einstiege Befehlssuche, Video und Karten-Stapel untereinander,
 * dazu „Aurora“ zum Vergleich. Nutzt die Demo-Bilder und -Tabellen (labor_*) der Musterseiten; legt nur das Demo-Video neu an.
 * Aufruf: am Ende von glas_demo_install() und per CMS_SITE=… php themes/glas/tools/demo.php --heroes
 */
function glas_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $root = $db->fetch("SELECT id FROM pages WHERE path = 'labor' AND type = 'page' LIMIT 1");
    if (!$root) { $log('Keine Musterseiten vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE path = 'labor/hero-varianten'") as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();
    $tall = [];
    foreach ($db->fetchAll('SELECT id, original_name FROM media WHERE tags LIKE ? ORDER BY id', ['%' . GLAS_DEMO_TAG . '%']) as $m) {
        if (str_starts_with((string) $m['original_name'], 'labor-telefon-')) $tall[] = (int) $m['id'];
    }
    $vid = glas_demo_hero_video($log);
    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + ['eyebrow' => '', 'text' => '', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''],
        'tunes' => ['section' => $tunes]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Labor · Hero-Varianten', 'title' => 'Sieben Einstiege, *ein* Block.',
            'text' => 'Der Block „Einstieg (Hero)“ hat im Kit „Glas“ sieben Varianten. Die drei neuen stehen hier untereinander: Befehlssuche, Video im Hintergrund und Karten-Stapel – dazu die „Aurora“ zum Vergleich. Auf echten Seiten steht nur ein Einstieg ganz oben.']),
        $B('command', ['eyebrow' => 'Variante „Befehlssuche“', 'title' => 'Was möchten Sie *finden*?',
            'text' => 'Für Websites mit vielen Inhalten: ein großes gläsernes Suchfeld über der Aurora. Vorschläge erscheinen schon beim Tippen, beliebte Begriffe liegen als Chips bereit.',
            'search_label' => 'Labor durchsuchen', 'search_placeholder' => 'z. B. Projekt, Workshop, Sprechstunde …',
            'search_chips' => "Lichtung\nWorkshop\nSprechstunde\nAnfrage stellen | /labor/formular\nProjekte & Termine | /labor/projekte"]),
        $B('video', ['eyebrow' => 'Variante „Video im Hintergrund“', 'title' => 'Licht, das sich *bewegt*.',
            'text' => 'Ein kurzes, stummes Loop-Video füllt den Einstieg, der Text steht auf dichtem Glas. Die Schaltfläche rechts unten hält das Video an; bei „Bewegung reduzieren“ bleibt es ein Standbild. (Beispielvideo, aus einem erzeugten Farbfeld.)',
            'video' => $vid['video'], 'image' => $vid['still'], 'overlay' => 'strong',
            'button_label' => 'Projekte ansehen', 'button_link' => '/labor/projekte', 'button2_label' => 'Kontakt', 'button2_link' => '/labor/formular']),
        $B('stack', ['eyebrow' => 'Variante „Karten-Stapel“', 'title' => 'Drei Wege ins *Labor*.',
            'text' => 'Zwei bis drei Angebote gleich im Einstieg: Die Glaskarten liegen als Stapel übereinander und fächern auf, sobald der Zeiger oder der Tastaturfokus sie erreicht.',
            'button_label' => 'Alle Termine', 'button_link' => '/labor/projekte',
            'cards' => [
                ['icon' => 'pencil-ruler', 'title' => 'Entwurfs-Sprint', 'text' => 'In zehn Tagen vom Einfall zum klickbaren Prototyp – mit Tests (Beispielangebot).', 'link_label' => 'Projekte ansehen', 'link' => '/labor/projekte'],
                ['icon' => 'chalkboard-teacher', 'title' => 'Workshop', 'text' => 'Barrierefreie Apps mit echten Geräten – für Teams bis zwölf Personen.', 'link_label' => 'Termine', 'link' => '/labor/projekte'],
                ['icon' => 'compass', 'title' => 'UX-Sprechstunde', 'text' => 'Jeden Donnerstag: Fragen stellen, Prototypen zeigen, gemeinsam testen.', 'link_label' => 'Anfrage stellen', 'link' => '/labor/formular'],
            ]], ['background' => 'tint']),
        $B('aurora', ['eyebrow' => 'Zum Vergleich: „Aurora“', 'title' => 'Glas über dem *Farbfeld*.',
            'text' => 'Die bisherige Standard-Variante: Glaspaneel über der Aurora, daneben ein Bild im Glasrahmen und Kennwerte.',
            'points' => "Vorlagen: 4\nKontrast: ≥ 4,5:1", 'image' => $tall[1] ?? ($tall[0] ?? null), 'ratio' => '4:5',
            'button_label' => 'Zum Labor', 'button_link' => '/labor']),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 9, 'status' => 'published', 'menu' => 1,
        'meta_description' => 'Einstiegs-Varianten des Kits „Glas“: Befehlssuche über der Aurora, Video im Hintergrund, Karten-Stapel und Aurora – mit erfundenen Inhalten.'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/labor/hero-varianten).');
    return true;
}
