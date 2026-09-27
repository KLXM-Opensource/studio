<?php
/**
 * Demo-Inhalte des Kits „nature“: das fiktive „Hofgut Wiesengrund“ – Hofladen (Datentabelle mit Detailseite),
 * Termine (Führungen, Markttage, Kurse – als Liste und Kalender), Gruppen-Formular mit Bedingung, die Musterseite
 * „Erleben“ mit allen Blöcken und Varianten (Unterseiten Medien und Journal) und erzeugte Illustrationen.
 *
 * Illustrationen: GD, doppelt gerendert und verkleinert (glatte Kanten) – flache Landschaften in der Palette des Kits
 * (Acker, Streuobstwiese, Blumenwiese, Teich, Waldrand, Hof, Abendhügel, Bienenstand) und freigestellte Hofladen-
 * Produkte (PNG). Keine Fotos, keine Personen, keine Marken.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/nature/tools/demo.php [--force|--remove]
 * Alles ist als Demo gekennzeichnet (Tabellen hof_*, Seiten hofladen/termine/erleben, Medien-Schlagwort „nature-demo“).
 * Video: „Big Buck Bunny“ © 2008 Blender Foundation / www.bigbuckbunny.org, Lizenz CC BY 3.0 – per Zwei-Klick-Lösung.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const NATURE_DEMO_TAG = 'nature-demo';
const NATURE_DEMO_COLLECTION = 'Hofgut Wiesengrund (Demo)';
const NATURE_DEMO_PAGES = ['hofladen', 'termine', 'erleben'];

/** Demo anlegen. $force: vorhandene Demo-Inhalte vorher entfernen. */
function nature_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path = 'erleben' AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Die Demo-Inhalte sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    nature_demo_remove($log);

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist (Kopf, Kontakt-Block, Karte).
    // Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse von Personen oder Firmen).
    foreach (['phone' => '0123 456789-0', 'street' => 'Am Wiesengrund 1', 'zip' => '12345', 'city' => 'Musterfeld', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    $img = nature_demo_images($log);
    $pdf = nature_demo_pdf($log);
    $tables = nature_demo_tables($img, $log);
    nature_demo_pages($img, $pdf, $tables, $log);
    nature_demo_heroes($log);
    \Core\PageCache::clear();
    $log('Fertig: Hofladen, Termine, Erleben, ' . count($tables) . ' Datentabellen, ' . count($img['all']) . ' Illustrationen.');
    return true;
}

/** Demo-Seiten, Demo-Tabellen und Demo-Medien entfernen (Seiten aus seed.php bleiben) */
function nature_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = [];
    foreach (NATURE_DEMO_PAGES as $p) {
        $ids = array_merge($ids, array_map('intval', array_column($db->fetchAll('SELECT id FROM pages WHERE path = ? OR path LIKE ?', [$p, $p . '/%']), 'id')));
    }
    $ids = array_merge($ids, array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE slug = '_vorlage-hofladen'"), 'id')));
    foreach (array_unique($ids) as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'hof_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . NATURE_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query('DELETE FROM media_collections WHERE name = ?', [NATURE_DEMO_COLLECTION]);
    $log('Vorhandene Demo-Inhalte entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Illustrationen (GD)

/** Palette der Illustrationen (Vorlage „Moos“) */
function nature_demo_palette(): array
{
    return [
        'paper' => [246, 242, 232], 'sand' => [236, 229, 211], 'cream' => [251, 246, 234], 'moss' => [82, 112, 58], 'fern' => [124, 154, 91],
        'sage' => [169, 185, 143], 'meadow' => [150, 172, 102], 'forest' => [46, 70, 48], 'night' => [31, 43, 34], 'clay' => [168, 90, 50],
        'bark' => [91, 70, 51], 'wheat' => [222, 176, 88], 'sun' => [231, 179, 80], 'sky' => [207, 224, 227], 'skydeep' => [157, 191, 198],
        'blossom' => [217, 139, 160], 'apple' => [184, 69, 47], 'water' => [120, 163, 172], 'dusk' => [232, 170, 128],
    ];
}

/** @return array{all: list<int>, wide: array<string,int>, tall: list<int>, objects: array<string,int>, collection: int} */
function nature_demo_images(callable $log): array
{
    $col = Media::createCollection(NATURE_DEMO_COLLECTION, 'Illustrationen des Kits „nature“ – automatisch erzeugt, frei verwendbar, ohne Personen oder Marken.');
    $import = function (string $file, string $name, string $title, string $alt, bool $inCollection) use ($col, $log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => NATURE_DEMO_TAG, 'credit' => 'Illustration, automatisch erzeugt']
            + ($inCollection ? ['collection' => $col] : []));
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    $all = $wide = $tall = $objects = [];
    $scenes = [
        'acker' => ['Acker im Frühsommer', 'Illustration: Ackerreihen, die zu sanften Hügeln führen, darüber Himmel und Sonne'],
        'obstwiese' => ['Streuobstwiese', 'Illustration: Apfelbäume mit roten Früchten auf einer Wiese'],
        'blumenwiese' => ['Blumenwiese', 'Illustration: Wiese mit bunten Blüten vor einem Waldrand'],
        'teich' => ['Teich mit Schilf', 'Illustration: kleiner Teich mit Schilf und Seerosen zwischen Hügeln'],
        'waldrand' => ['Waldrand', 'Illustration: Reihe von Nadelbäumen am Rand einer Weide'],
        'hof' => ['Hof mit Scheune', 'Illustration: Scheune mit Satteldach und Laubbaum auf einem Hügel'],
        'abend' => ['Hügel am Abend', 'Illustration: gestaffelte Hügelketten im Abendlicht'],
        'bienen' => ['Bienenstand', 'Illustration: drei Bienenkästen auf einer Wiese am Waldrand'],
    ];
    $i = 0;
    foreach ($scenes as $kind => [$title, $alt]) {
        $file = tempnam(sys_get_temp_dir(), 'nt') . '.jpg';
        nature_demo_scene($file, 1800, 1200, $kind, $i++);
        if ($id = $import($file, 'wiesengrund-' . $kind . '.jpg', $title, $alt . ' (Beispielbild)', true)) { $all[] = $id; $wide[$kind] = $id; }
    }
    foreach (['obstwiese', 'hof', 'waldrand'] as $k => $kind) {
        $file = tempnam(sys_get_temp_dir(), 'nt') . '.jpg';
        nature_demo_scene($file, 1200, 1500, $kind, 40 + $k);
        if ($id = $import($file, 'wiesengrund-' . $kind . '-hoch.jpg', $scenes[$kind][0] . ' (Hochformat)', $scenes[$kind][1] . ' (Beispielbild, Hochformat)', true)) { $all[] = $id; $tall[] = $id; }
    }
    foreach (['honig' => 'Glas Blütenhonig', 'eier' => 'Korb mit Eiern', 'aepfel' => 'Äpfel', 'moehren' => 'Bund Möhren', 'brot' => 'Bauernbrot', 'kuerbis' => 'Hokkaido-Kürbis'] as $kind => $title) {
        $file = tempnam(sys_get_temp_dir(), 'nt') . '.png';
        nature_demo_object($file, $kind);
        if ($id = $import($file, 'hofladen-' . $kind . '.png', $title . ' (Illustration)', 'Zeichnung: ' . $title . ' (Beispielbild)', false)) { $all[] = $id; $objects[$kind] = $id; }
    }
    $log(count($all) . ' Illustrationen erzeugt.');
    return ['all' => $all, 'wide' => $wide, 'tall' => $tall, 'objects' => $objects, 'collection' => $col];
}

/** Hügelkette als gefülltes Polygon: Grundlinie + Summe von Sinuswellen */
function nature_demo_hill(\GdImage $im, int $W, int $H, float $base, float $amp, int $seed, int $col): void
{
    mt_srand($seed);
    $waves = [];
    for ($k = 0; $k < 3; $k++) $waves[] = [mt_rand(8, 26) / 10000 * ($k + 1), mt_rand(0, 628) / 100, $amp / ($k + 1.2)];
    $pts = [0, $H];
    for ($x = 0; $x <= $W; $x += 12) {
        $y = $base;
        foreach ($waves as [$f, $ph, $a]) $y += sin($x * $f + $ph) * $a;
        array_push($pts, $x, (int) $y);
    }
    array_push($pts, $W, $H);
    imagefilledpolygon($im, $pts, $col);
}

/** Landschaft, doppelt gerendert */
function nature_demo_scene(string $file, int $w, int $h, string $kind, int $seed): void
{
    $P = nature_demo_palette();
    $W = $w * 2; $H = $h * 2;
    $im = imagecreatetruecolor($W, $H);
    $c = fn(array $rgb) => imagecolorallocate($im, ...$rgb);
    $mix = fn(array $a, array $b, float $t) => [(int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t)];
    $evening = $kind === 'abend';
    [$top, $bottom] = $evening ? [[214, 164, 140], [243, 214, 170]] : [$P['skydeep'], $P['sky']];
    for ($y = 0; $y < $H; $y += 2) imagefilledrectangle($im, 0, $y, $W, $y + 1, $c($mix($top, $bottom, min(1, $y / ($H * .6)))));
    $u = min($W, $H) / 24;
    // Sonne
    [$sx, $sy] = [$W * (.62 + ($seed % 3) * .1), $H * ($evening ? .5 : .22)];
    imagefilledellipse($im, (int) $sx, (int) $sy, (int) ($u * 4.2), (int) ($u * 4.2), $c($mix($P['sun'], $bottom, .45)));
    imagefilledellipse($im, (int) $sx, (int) $sy, (int) ($u * 3.2), (int) ($u * 3.2), $c($evening ? $P['dusk'] : $P['sun']));
    // Hügel (hinten → vorn)
    $far = $evening ? [178, 132, 128] : $mix($P['sage'], $P['sky'], .45);
    $mid = $evening ? [128, 98, 104] : $P['sage'];
    $near = $evening ? [74, 70, 72] : $P['meadow'];
    nature_demo_hill($im, $W, $H, $H * .5, $H * .05, 100 + $seed, $c($far));
    nature_demo_hill($im, $W, $H, $H * .6, $H * .045, 200 + $seed, $c($mid));
    mt_srand(300 + $seed);
    $tree = function (float $x, float $y, float $s, array $crown) use ($im, $c, $P): void {
        imagefilledrectangle($im, (int) ($x - $s * .09), (int) $y, (int) ($x + $s * .09), (int) ($y + $s * .9), $c($P['bark']));
        imagefilledellipse($im, (int) $x, (int) ($y - $s * .2), (int) ($s * 1.5), (int) ($s * 1.35), $c($crown));
    };
    $fir = function (float $x, float $y, float $s, array $col) use ($im, $c, $P): void {
        imagefilledrectangle($im, (int) ($x - $s * .06), (int) ($y - $s * .1), (int) ($x + $s * .06), (int) ($y + $s * .25), $c($P['bark']));
        imagefilledpolygon($im, [(int) $x, (int) ($y - $s * 1.6), (int) ($x + $s * .5), (int) $y, (int) ($x - $s * .5), (int) $y], $c($col));
    };
    switch ($kind) {
        case 'acker':
            nature_demo_hill($im, $W, $H, $H * .66, $H * .02, 400 + $seed, $c($P['fern']));
            $vy = $H * .62; $vx = $W * .55;
            for ($k = -14; $k <= 14; $k++) {
                $x0 = $vx + $k * $W * .09;
                imagesetthickness($im, (int) max(2, $u * .35));
                imageline($im, (int) ($vx + $k * $W * .012), (int) ($H * .7), (int) $x0, $H, $c($k % 2 ? $P['moss'] : $mix($P['bark'], $P['fern'], .5)));
            }
            imagesetthickness($im, 1);
            foreach ([.12, .22, .82] as $tx) $tree($W * $tx, $H * .58, $u * 2.2, $P['moss']);
            break;
        case 'obstwiese':
            nature_demo_hill($im, $W, $H, $H * .7, $H * .03, 410 + $seed, $c($near));
            foreach ([[.18, .62, 3.4], [.42, .66, 4.2], [.7, .6, 3.2], [.88, .7, 3.8], [.3, .78, 4.6], [.6, .8, 5]] as $j => [$tx, $ty, $ts]) {
                $x = $W * $tx; $y = $H * $ty; $s = $u * $ts;
                $tree($x, $y, $s, $j % 2 ? $P['moss'] : $P['fern']);
                for ($a = 0; $a < 7; $a++) imagefilledellipse($im, (int) ($x + mt_rand(-60, 60) / 100 * $s * .6), (int) ($y - $s * .2 + mt_rand(-50, 50) / 100 * $s * .5), (int) ($s * .16), (int) ($s * .16), $c($P['apple']));
            }
            break;
        case 'blumenwiese':
            foreach (range(0, 16) as $k) $fir($W * ($k / 16) + mt_rand(-20, 20), $H * .58, $u * (2.2 + mt_rand(0, 12) / 10), $k % 3 ? $P['forest'] : $P['moss']);
            nature_demo_hill($im, $W, $H, $H * .68, $H * .02, 420 + $seed, $c($near));
            foreach ([$P['blossom'], $P['cream'], $P['sun'], $P['clay'], [150, 120, 190]] as $ci => $col) {
                for ($k = 0; $k < 170; $k++) {
                    $y = $H * (.7 + mt_rand(0, 300) / 1000);
                    $r = (int) ($u * (.12 + ($y / $H - .7) * .9));
                    imagefilledellipse($im, mt_rand(0, $W), (int) $y, max(3, $r * 2), max(3, $r * 2), $c($col));
                }
            }
            break;
        case 'teich':
            nature_demo_hill($im, $W, $H, $H * .66, $H * .025, 430 + $seed, $c($near));
            imagefilledellipse($im, (int) ($W * .5), (int) ($H * .8), (int) ($W * .62), (int) ($H * .2), $c($mix($P['water'], $P['sky'], .2)));
            imagefilledellipse($im, (int) ($W * .5), (int) ($H * .8), (int) ($W * .52), (int) ($H * .14), $c($P['water']));
            imagesetthickness($im, (int) max(2, $u * .18));
            for ($k = 0; $k < 36; $k++) {
                $x = $W * (.18 + mt_rand(0, 140) / 1000 + ($k % 2 ? .52 : 0));
                imageline($im, (int) $x, (int) ($H * .83), (int) ($x + mt_rand(-20, 20)), (int) ($H * (.66 + mt_rand(0, 60) / 1000)), $c($k % 3 ? $P['moss'] : $P['bark']));
            }
            imagesetthickness($im, 1);
            foreach ([[.42, .8], [.56, .78], [.5, .84]] as [$lx, $ly]) {
                imagefilledellipse($im, (int) ($W * $lx), (int) ($H * $ly), (int) ($u * 2.2), (int) ($u * .9), $c($P['fern']));
                imagefilledellipse($im, (int) ($W * $lx), (int) ($H * $ly - $u * .3), (int) ($u * .7), (int) ($u * .5), $c($P['blossom']));
            }
            break;
        case 'waldrand':
            nature_demo_hill($im, $W, $H, $H * .64, $H * .02, 440 + $seed, $c($near));
            foreach (range(0, 22) as $k) $fir($W * ($k / 22) + mt_rand(-30, 30), $H * (.64 + mt_rand(-10, 10) / 1000), $u * (3 + mt_rand(0, 20) / 10), $k % 4 ? $P['forest'] : $P['moss']);
            nature_demo_hill($im, $W, $H, $H * .8, $H * .015, 441 + $seed, $c($P['fern']));
            imagesetthickness($im, (int) max(2, $u * .14));
            for ($x = (int) ($W * .05); $x < $W; $x += (int) ($W * .08)) {
                imageline($im, $x, (int) ($H * .8), $x, (int) ($H * .88), $c($P['bark']));
            }
            imageline($im, 0, (int) ($H * .82), $W, (int) ($H * .82), $c($P['bark']));
            imagesetthickness($im, 1);
            break;
        case 'hof':
            nature_demo_hill($im, $W, $H, $H * .68, $H * .02, 450 + $seed, $c($near));
            $bx = $W * .38; $by = $H * .68; $bw = $W * .3; $bh = $H * .2;
            imagefilledrectangle($im, (int) $bx, (int) ($by - $bh), (int) ($bx + $bw), (int) ($by + $u), $c($P['clay']));
            imagefilledpolygon($im, [(int) ($bx - $u), (int) ($by - $bh), (int) ($bx + $bw / 2), (int) ($by - $bh - $H * .14), (int) ($bx + $bw + $u), (int) ($by - $bh)], $c($P['bark']));
            imagefilledrectangle($im, (int) ($bx + $bw * .4), (int) ($by - $bh * .6), (int) ($bx + $bw * .6), (int) ($by + $u), $c($mix($P['bark'], $P['clay'], .3)));
            imagesetthickness($im, (int) max(2, $u * .12));
            imageline($im, (int) ($bx + $bw * .4), (int) ($by - $bh * .6), (int) ($bx + $bw * .6), (int) ($by + $u), $c($P['cream']));
            imageline($im, (int) ($bx + $bw * .6), (int) ($by - $bh * .6), (int) ($bx + $bw * .4), (int) ($by + $u), $c($P['cream']));
            imagesetthickness($im, 1);
            imagefilledellipse($im, (int) ($bx + $bw * .5), (int) ($by - $bh - $H * .06), (int) ($u * 1.4), (int) ($u * 1.4), $c($P['cream']));
            $tree($W * .8, $H * .58, $u * 5, $P['moss']);
            $tree($W * .16, $H * .64, $u * 3, $P['fern']);
            break;
        case 'abend':
            nature_demo_hill($im, $W, $H, $H * .7, $H * .04, 460 + $seed, $c([98, 84, 90]));
            nature_demo_hill($im, $W, $H, $H * .82, $H * .03, 461 + $seed, $c($near));
            foreach (range(0, 6) as $k) $fir($W * (.06 + $k * .035), $H * .83, $u * 2, [52, 50, 54]);
            break;
        case 'bienen':
            foreach (range(0, 14) as $k) $fir($W * ($k / 14) + mt_rand(-20, 20), $H * .6, $u * (2.4 + mt_rand(0, 10) / 10), $P['forest']);
            nature_demo_hill($im, $W, $H, $H * .68, $H * .02, 470 + $seed, $c($near));
            foreach ([.3, .48, .66] as $j => $hx) {
                $x = $W * $hx; $y = $H * .8; $s = $u * 3.2;
                imagefilledrectangle($im, (int) ($x - $s * .6), (int) ($y + $s * .5), (int) ($x - $s * .5), (int) ($y + $s * 1), $c($P['bark']));
                imagefilledrectangle($im, (int) ($x + $s * .5), (int) ($y + $s * .5), (int) ($x + $s * .6), (int) ($y + $s * 1), $c($P['bark']));
                imagefilledrectangle($im, (int) ($x - $s * .7), (int) ($y - $s * .6), (int) ($x + $s * .7), (int) ($y + $s * .5), $c([$P['cream'], $P['wheat'], $P['sage']][$j]));
                imagefilledrectangle($im, (int) ($x - $s * .8), (int) ($y - $s * .8), (int) ($x + $s * .8), (int) ($y - $s * .6), $c($P['clay']));
                imagefilledrectangle($im, (int) ($x - $s * .25), (int) ($y + $s * .3), (int) ($x + $s * .25), (int) ($y + $s * .38), $c($P['bark']));
                imageline($im, (int) ($x - $s * .7), (int) ($y - $s * .05), (int) ($x + $s * .7), (int) ($y - $s * .05), $c($mix($P['bark'], $P['cream'], .6)));
            }
            for ($k = 0; $k < 120; $k++) imagefilledellipse($im, mt_rand(0, $W), (int) ($H * (.72 + mt_rand(0, 280) / 1000)), (int) ($u * .3), (int) ($u * .3), $c($k % 2 ? $P['cream'] : $P['sun']));
            break;
    }
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $W, $H);
    imagejpeg($out, $file, 86);
}

/** Hofladen-Produkt als Zeichnung auf transparentem Grund (1200 × 900, doppelt gerendert) */
function nature_demo_object(string $file, string $kind): void
{
    $P = nature_demo_palette();
    $W = 2400; $H = 1800;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, false);
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $c = fn(array $rgb, int $a = 0) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a);
    // weicher Schatten
    for ($k = 0; $k < 14; $k++) imagefilledellipse($im, 1200, 1560, 1300 - $k * 40, 110 - $k * 4, $c([40, 30, 10], 122 - $k));
    switch ($kind) {
        case 'honig':
            nature_rrect($im, 820, 700, 1580, 1540, 110, $c([214, 150, 48]));
            nature_rrect($im, 860, 760, 1000, 1480, 60, $c([236, 190, 96], 40));
            nature_rrect($im, 880, 520, 1520, 720, 50, $c($P['bark']));
            for ($k = 0; $k < 7; $k++) imagefilledrectangle($im, 900 + $k * 90, 540, 930 + $k * 90, 700, $c([72, 55, 40]));
            nature_rrect($im, 900, 980, 1500, 1320, 30, $c($P['cream']));
            imagefilledpolygon($im, [1200, 1030, 1270, 1150, 1200, 1270, 1130, 1150], $c($P['moss']));
            imagesetthickness($im, 10);
            imageline($im, 1200, 1060, 1200, 1250, $c($P['cream']));
            break;
        case 'eier':
            foreach ([[980, 900, [238, 222, 196]], [1200, 860, [214, 170, 120]], [1420, 910, [246, 238, 222]], [1090, 980, [226, 188, 140]], [1320, 990, [240, 228, 206]]] as [$x, $y, $col]) {
                imagefilledellipse($im, $x, $y, 250, 330, $c($col));
            }
            imagefilledarc($im, 1200, 1040, 1100, 900, 0, 180, $c($P['bark']), IMG_ARC_PIE);
            imagefilledrectangle($im, 650, 1010, 1750, 1060, $c([120, 92, 66]));
            imagesetthickness($im, 14);
            for ($k = 0; $k < 9; $k++) imageline($im, 720 + $k * 120, 1070, 760 + $k * 100, 1450, $c([118, 90, 64]));
            for ($k = 0; $k < 3; $k++) imagearc($im, 1200, 1040, 1000 - $k * 20, 700 + $k * 140, 20, 160, $c([118, 90, 64]));
            imagesetthickness($im, 30);
            imagearc($im, 1200, 1040, 900, 1100, 185, 355, $c($P['bark']));
            break;
        case 'aepfel':
            foreach ([[900, 1180, 460, $P['apple']], [1480, 1200, 430, [141, 168, 90]], [1190, 1000, 500, [196, 92, 54]]] as [$x, $y, $r, $col]) {
                imagefilledellipse($im, $x, $y, $r, (int) ($r * .92), $c($col));
                imagefilledellipse($im, $x - (int) ($r * .18), $y - (int) ($r * .16), (int) ($r * .22), (int) ($r * .16), $c([255, 255, 255], 90));
                imagesetthickness($im, 18);
                imageline($im, $x, $y - (int) ($r * .42), $x + 20, $y - (int) ($r * .6), $c($P['bark']));
                imagefilledellipse($im, $x + 90, $y - (int) ($r * .56), 150, 70, $c($P['moss']));
            }
            break;
        case 'moehren':
            foreach ([[-160, 0], [0, -40], [160, 10], [-60, 60], [90, 70]] as $j => [$dx, $dy]) {
                $x = 1200 + $dx; $y = 820 + $dy;
                imagefilledpolygon($im, [$x - 90, $y, $x + 90, $y, $x + 16, $y + 640, $x - 16, $y + 640], $c([224, 122, 52]));
                imagesetthickness($im, 8);
                for ($k = 1; $k < 5; $k++) imageline($im, $x - 60 + $k * 6, $y + $k * 120, $x - 20, $y + $k * 120 + 10, $c([196, 98, 36]));
                imagesetthickness($im, 22);
                foreach ([-120, -40, 40, 120] as $fx) imageline($im, $x, $y, $x + $fx, $y - 380 + abs($fx), $c($j % 2 ? $P['moss'] : $P['fern']));
            }
            imagesetthickness($im, 1);
            nature_rrect($im, 1050, 780, 1350, 850, 30, $c($P['clay']));
            break;
        case 'brot':
            imagefilledellipse($im, 1200, 1180, 1300, 760, $c([176, 118, 64]));
            imagefilledellipse($im, 1200, 1120, 1180, 620, $c([198, 142, 82]));
            imagesetthickness($im, 26);
            foreach ([-300, -100, 100, 300] as $dx) imagearc($im, 1200 + $dx, 1120, 260, 520, 250, 290, $c([150, 96, 50]));
            for ($k = 0; $k < 40; $k++) imagefilledellipse($im, mt_rand(760, 1640), mt_rand(930, 1300), 16, 10, $c($P['cream'], 30));
            break;
        case 'kuerbis':
            foreach ([[-300, 700, 760], [300, 700, 760], [-150, 800, 820], [150, 800, 820], [0, 820, 860]] as $j => [$dx, $w2, $h2]) {
                imagefilledellipse($im, 1200 + $dx, 1150, $w2, $h2, $c($j < 2 ? [196, 98, 36] : ($j < 4 ? [214, 112, 42] : [228, 128, 52])));
            }
            nature_rrect($im, 1160, 640, 1250, 800, 30, $c($P['bark']));
            imagefilledellipse($im, 1330, 720, 260, 120, $c($P['moss']));
            break;
    }
    $out = imagecreatetruecolor(1200, 900);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, 1200, 900, $W, $H);
    imagepng($out, $file, 9);
}

/** Abgerundetes Rechteck (gefüllt) */
function nature_rrect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
}

/** Beispiel-PDF (eine Seite, reines PDF ohne Bibliothek) */
function nature_demo_pdf(callable $log): ?int
{
    $lines = ['Hofgut Wiesengrund - Saisonkalender (Beispiel)', 'Fruehling: Radieschen, Spinat, Baerlauch', 'Sommer: Erdbeeren, Tomaten, Zucchini', 'Herbst: Aepfel, Kuerbis, Kartoffeln', 'Winter: Lagergemuese, Honig, Brot', 'Frei erfundener Inhalt (Demo).'];
    $stream = 'BT /F1 20 Tf 72 760 Td (' . $lines[0] . ') Tj /F1 13 Tf';
    foreach (array_slice($lines, 1) as $l) $stream .= ' 0 -28 Td (' . $l . ') Tj';
    $stream .= ' ET';
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
    $file = tempnam(sys_get_temp_dir(), 'nt') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'wiesengrund-saisonkalender.pdf', '', ['title' => 'Saisonkalender (Beispiel)', 'tags' => NATURE_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function nature_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function nature_demo_tables(array $img, callable $log): array
{
    $out = [];
    $O = fn(string $k) => $img['objects'][$k] ?? null;

    // Hofladen: Karten, Liste, Tabelle (Preisliste), Detailseite
    $t = nature_demo_table([
        'handle' => 'hof_produkte', 'name' => 'Hofladen (Demo)', 'singular' => 'Produkt', 'icon' => 'storefront',
        'description' => 'Beispiel-Sortiment des fiktiven Hofguts Wiesengrund – Preise frei erfunden.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Produkt', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'kategorie', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "gemuese=Gemüse\nobst=Obst\nbackwaren=Backwaren\nvorrat=Vorrat\neier=Eier"],
            ['name' => 'preis', 'label' => 'Preis', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'saison', 'label' => 'Saison', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'herkunft', 'label' => 'Herkunft', 'type' => 'text', 'width' => 'half'],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'sortiment', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'titel', 'sort_dir' => 'asc', 'workflow' => 0, 'jsonld' => 'Product'],
    ], $log);
    if ($t) {
        $rows = [
            ['Blütenhonig', 'vorrat', '7,50 € / 500 g', 'ganzjährig', 'eigene Bienen am Waldrand', 'Mild und cremig gerührt, aus Linde, Klee und Obstblüte.', 'honig'],
            ['Eier vom Mobilstall', 'eier', '0,55 € / Stück', 'ganzjährig', 'eigene Hühner', 'Von Hühnern, die jede Woche auf frisches Gras umziehen.', 'eier'],
            ['Äpfel aus der Streuobstwiese', 'obst', '3,20 € / kg', 'September bis März', 'eigene Obstwiese', 'Alte Sorten wie Boskoop und Goldparmäne – ungespritzt.', 'aepfel'],
            ['Möhren im Bund', 'gemuese', '2,40 € / Bund', 'Juni bis November', 'eigener Acker', 'Süß und knackig, mit Grün für Suppe oder Pesto.', 'moehren'],
            ['Bauernbrot aus dem Holzofen', 'backwaren', '5,80 € / 1 kg', 'freitags und samstags', 'Hofbäckerei', 'Roggen und Dinkel aus eigenem Anbau, Sauerteig, lange Führung.', 'brot'],
            ['Hokkaido-Kürbis', 'gemuese', '2,90 € / kg', 'September bis Dezember', 'eigener Acker', 'Mit Schale essbar, nussig im Geschmack – für Suppe und Ofen.', 'kuerbis'],
        ];
        foreach ($rows as [$title, $cat, $price, $season, $origin, $text, $img]) {
            Entries::save($t, null, ['titel' => $title, 'kategorie' => $cat, 'preis' => $price, 'saison' => $season, 'herkunft' => $origin, 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Ein frei erfundenes Produkt, das zeigt, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h2>Vom Hof</h2><p>Geerntet, gebacken oder abgefüllt wird so nah wie möglich am Verkaufstag. Was übrig bleibt, geht an die Tafel im Ort (fiktiv).</p>'
                    . '<blockquote><p>Man schmeckt, wenn etwas Zeit zum Wachsen hatte.</p></blockquote>'
                    . '<h2>Gut zu wissen</h2><ul><li>Verpackung: Pfandglas, Papier oder eigener Korb</li><li>Vorbestellung für größere Mengen möglich</li><li>Preise frei erfunden</li></ul>',
                'bild' => $O($img), 'status' => 'published']);
        }
        $out['produkte'] = $t;
    }

    // Termine: Führungen, Markttage, Kurse – Wiederholung, ganztägig, Kategorien, Abonnieren
    $t = nature_demo_table([
        'handle' => 'hof_termine', 'name' => 'Termine (Demo)', 'singular' => 'Termin', 'icon' => 'calendar-dots',
        'description' => 'Beispiel-Kalender des fiktiven Hofguts – Termine relativ zum Tag der Anlage.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'datetime', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ganztaegig', 'label' => 'Ganztägig', 'type' => 'bool'],
            ['name' => 'wiederholung', 'label' => 'Wiederholung', 'type' => 'recurrence'],
            ['name' => 'ort', 'label' => 'Treffpunkt', 'type' => 'text', 'in_list' => true],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'textarea'],
            ['name' => 'kategorie', 'label' => 'Art', 'type' => 'select', 'in_list' => true, 'options' => "fuehrung=Hofführung\nmarkt=Markttag\nkurs=Kurs\nfest=Hoffest\ngeschlossen=Geschlossen"],
            ['name' => 'preis', 'label' => 'Beitrag', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
        ],
        'settings' => ['title_field' => 'titel', 'description_field' => 'ort', 'sort_field' => 'beginn', 'sort_dir' => 'asc', 'workflow' => 0,
            'calendar' => ['enabled' => 1, 'start' => 'beginn', 'end' => 'ende', 'all_day' => 'ganztaegig', 'recurrence' => 'wiederholung',
                'location' => 'ort', 'description' => 'beschreibung', 'category' => 'kategorie', 'duration' => 90, 'feed' => 1]],
    ], $log);
    if ($t) {
        $d = fn(int $days, string $time) => date('Y-m-d', strtotime("+$days days")) . ' ' . $time;
        $sat = (6 - (int) date('N') + 7) % 7 ?: 7;          // nächster Samstag
        $fri = (5 - (int) date('N') + 7) % 7 ?: 7;          // nächster Freitag
        $until = gmdate('Ymd\THis\Z', strtotime('+120 days'));
        $events = [
            ['Hofführung: Vom Acker in den Laden', $d($sat, '11:00'), $d($sat, '12:30'), false, "FREQ=WEEKLY;BYDAY=SA;UNTIL=$until", 'Hofladen', 'Rundgang über Acker, Weide und Obstwiese. Festes Schuhwerk empfohlen.', 'fuehrung', 'frei, Spende willkommen'],
            ['Markttag auf dem Hof', $d($fri, '14:00'), $d($fri, '18:00'), false, "FREQ=WEEKLY;INTERVAL=2;BYDAY=FR;UNTIL=$until", 'Hofplatz', 'Nachbarhöfe, Käse, Brot aus dem Holzofen und Musik im Hof.', 'markt', ''],
            ['Kräuterwanderung am Waldrand', $d(4, '16:00'), $d(4, '18:00'), false, '', 'Parkplatz am Waldweg', 'Wildkräuter erkennen, sammeln und zu Kräutersalz verarbeiten.', 'kurs', '12 €'],
            ['Obstbaumschnitt für Einsteiger', $d(11, '10:00'), $d(11, '15:00'), false, '', 'Streuobstwiese', 'Theorie am Morgen, Schnitt am Nachmittag. Scheren werden gestellt.', 'kurs', '45 € inkl. Mittagessen'],
            ['Vom Korn zum Brot – Kindernachmittag', $d(15, '14:30'), $d(15, '17:00'), false, '', 'Backhaus', 'Mahlen, kneten, backen: für Kinder von 6 bis 11 Jahren.', 'kurs', '8 € pro Kind'],
            ['Erntedank-Hoffest', $d(22, '00:00'), '', true, '', 'ganzer Hof', 'Kürbisschnitzen, Traktor-Rundfahrten, Hofcafé und Musik.', 'fest', 'Eintritt frei'],
            ['Inventur – Hofladen geschlossen', $d(29, '00:00'), '', true, '', '', 'An diesem Tag bleibt der Hofladen geschlossen.', 'geschlossen', ''],
            ['Imkerabend: Ein Jahr im Bienenstock', $d(33, '19:00'), $d(33, '21:00'), false, '', 'Hofcafé', 'Erzählung mit Schaukasten und Honigverkostung.', 'kurs', '6 €'],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $text, $cat, $price]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'preis' => $price, 'status' => 'published']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // Öffentliches Formular: Führung für Gruppen anfragen (Klassenstufe nur bei „Schulklasse“)
    $t = nature_demo_table([
        'handle' => 'hof_gruppen', 'name' => 'Gruppenanfragen (Demo)', 'singular' => 'Anfrage', 'icon' => 'users-three',
        'description' => 'Öffentliches Beispiel-Formular: Führungen für Schulklassen, Kitas, Vereine und Firmen.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'gruppe', 'label' => 'Art der Gruppe', 'type' => 'select', 'required' => true, 'width' => 'half', 'in_list' => true,
                'options' => "schule=Schulklasse\nkita=Kita\nverein=Verein\nfirma=Firma / Team\nprivat=Familie / Freunde"],
            ['name' => 'klasse', 'label' => 'Klassenstufe', 'type' => 'select', 'width' => 'half', 'options' => "1-2=1.–2. Klasse\n3-4=3.–4. Klasse\n5-7=5.–7. Klasse\n8+=ab 8. Klasse",
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'gruppe', 'op' => '=', 'value' => 'schule']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'gruppe', 'op' => '=', 'value' => 'schule']]]],
            ['name' => 'personen', 'label' => 'Anzahl Personen', 'type' => 'number', 'required' => true, 'width' => 'half', 'help' => 'Höchstens 30 je Führung.'],
            ['name' => 'wunschtermin', 'label' => 'Wunschtermin', 'type' => 'date', 'width' => 'half'],
            ['name' => 'nachricht', 'label' => 'Nachricht', 'type' => 'textarea', 'help' => 'Schwerpunkt, Barrierefreiheit, Allergien …'],
            ['name' => 'rueckruf', 'label' => 'Bitte rufen Sie mich zurück', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke – Ihre Anfrage ist eingegangen (Demo: es meldet sich niemand).', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['gruppen'] = $t;

    $log(count($out) . ' Datentabellen angelegt (hof_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function nature_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $W = fn(string $k) => $img['wide'][$k] ?? null;
    $T = fn(int $n) => $img['tall'][$n % max(1, count($img['tall']))] ?? null;
    $O = fn(string $k) => $img['objects'][$k] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text]);
    $cta = $B('cta', ['variant' => 'band', 'eyebrow' => 'Besuch planen', 'title' => 'Wir freuen uns auf *Ihren Besuch*.', 'text' => 'Farben, Jahreszeit, Schriften, Kopf und Fuß stellen Sie unter Verwaltung → Design ein.',
        'button_label' => 'Termine', 'button_link' => '/termine', 'button2_label' => 'Anfahrt', 'button2_link' => '/kontakt'], ['background' => 'dark']);
    $prod = $tables['produkte']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';
    $form = $tables['gruppen']['handle'] ?? '';
    $mt = fn(string $v, string $eyebrow, string $title, string $text, ?int $image, string $ratio = '4:3', string $list = '', array $btn = []) => [
        'variant' => $v, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'list' => $list, 'image' => $image, 'ratio' => $ratio,
        'button_label' => $btn[0] ?? '', 'button_link' => $btn[1] ?? '', 'button2_label' => $btn[2] ?? '', 'button2_link' => $btn[3] ?? ''];
    $dl = fn(string $table, string $layout, array $extra) => $extra + ['eyebrow' => '', 'intro' => '', 'table' => $table, 'layout' => $layout, 'columns' => '3', 'ratio' => '4:3', 'limit' => 0,
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $db = app()->db;

    // Reihenfolge im Menü: Der Hof · Hofladen · Termine · Erleben · Kontakt
    $sortOf = fn(string $slug) => (int) ($db->fetchValue('SELECT sort FROM pages WHERE parent_id IS NULL AND slug = ?', [$slug]) ?? 0);
    $base = $sortOf('hof');
    $db->query('UPDATE pages SET sort = ? WHERE parent_id IS NULL AND slug = ?', [$base + 10, 'kontakt']);

    // ---------------------------------------------------------------- Startseite ergänzen: Text + Bild, nächste Termine
    if ($home = Pages::home()) {
        $blocks = Pages::blocks($home);
        $add = [
            $B('media_text', $mt('auto', 'Aus eigener Ernte', 'Was heute im Hofladen liegt', '<p>Morgens geerntet, mittags im Regal: Gemüse vom Acker, Äpfel von der Streuobstwiese, Brot aus dem Holzofen und Honig vom Waldrand.</p>', $W('obstwiese'), '4:3',
                "Dienstag bis Samstag geöffnet\nPfandgläser und eigene Körbe willkommen\nVorbestellung für größere Mengen", ['Zum Sortiment', '/hofladen'])),
        ];
        if ($term !== '') {
            $add[] = $B('upcoming', ['eyebrow' => 'Demnächst', 'title' => 'Führungen, Markttage, Kurse', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true,
                'link_detail' => false, 'subscribe' => true, 'more_label' => 'Alle Termine', 'more_link' => '/termine'], ['background' => 'tint']);
        }
        $new = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'stats') array_push($new, ...Pages::sanitizeBlocks($add));
            $new[] = $b;
        }
        $json = json_encode(['time' => (int) (microtime(true) * 1000), 'blocks' => $new, 'version' => '2.31'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $db->update('pages', ['content_draft' => $json, 'content_published' => $json, 'og_image' => $W('acker')], 'id = :id', ['id' => $home['id']]);
    }

    // ---------------------------------------------------------------- Hofladen (Datenlisten, Detailseite)
    if ($prod !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-hofladen', 'title' => 'Hofladen – Detailseite', 'type' => 'template', 'template_for' => $prod, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $prod, 'fields' => ["$prod._title", "$prod.kategorie", "$prod.preis"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => 'Zum Sortiment', 'back_link' => '/hofladen'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $prod, 'fields' => ["$prod.bild"], 'layout' => 'image', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small', 'background' => 'white']),
            $B('data_fields', ['table' => $prod, 'fields' => ["$prod.kurztext", "$prod.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $prod, 'fields' => ["$prod.preis", "$prod.saison", "$prod.herkunft", "$prod.kategorie"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => '', 'title' => 'Auch im Hofladen', 'intro' => '', 'table' => $prod, 'fields' => ["$prod._title", "$prod.bild", "$prod.preis"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '4:3', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
        ]));
        $t = Tables::find($prod);
        $t['settings']['detail_page_id'] = $tpl;
        $db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $page(['slug' => 'hofladen', 'title' => 'Hofladen', 'sort' => $base + 1, 'og_image' => $W('obstwiese'),
        'meta_description' => 'Sortiment des fiktiven Hofladens Wiesengrund: Gemüse, Obst, Brot, Eier und Honig – als Karten, Liste und Preisliste.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Hofladen', 'title' => 'Frisch vom Feld, *ehrlich* im Preis.', 'text' => 'Rund 60 Produkte, die Hälfte aus eigener Ernte, der Rest von Nachbarhöfen im Umkreis von 30 Kilometern (fiktiv).',
            'button_label' => 'Sortiment', 'button_link' => '#sortiment', 'button2_label' => 'Öffnungszeiten', 'button2_link' => '/kontakt', 'points' => "Dienstag bis Samstag\nPfandgläser und eigene Körbe willkommen", 'image' => $T(0), 'ratio' => '4:5']),
        $B('cards', ['variant' => 'product', 'eyebrow' => 'Diese Woche', 'title' => 'Gerade besonders gut', 'intro' => 'Gezeichnet, nicht fotografiert – Preise frei erfunden.', 'size' => 'm', 'ratio' => '4:3',
            'items' => [
                ['image' => $O('aepfel'), 'eyebrow' => 'Streuobstwiese', 'title' => 'Äpfel, alte Sorten', 'text' => 'Boskoop, Goldparmäne, Rheinischer Winterrambur.', 'meta' => '3,20 € / kg', 'link_label' => 'Details', 'link' => '/hofladen#sortiment'],
                ['image' => $O('kuerbis'), 'eyebrow' => 'Acker', 'title' => 'Hokkaido-Kürbis', 'text' => 'Mit Schale essbar, nussig.', 'meta' => '2,90 € / kg', 'link_label' => 'Details', 'link' => '/hofladen#sortiment'],
                ['image' => $O('honig'), 'eyebrow' => 'Waldrand', 'title' => 'Blütenhonig', 'text' => 'Cremig gerührt, im Pfandglas.', 'meta' => '7,50 € / 500 g', 'link_label' => 'Details', 'link' => '/hofladen#sortiment'],
            ]], ['background' => 'muted']),
        $B('data_list', $dl($prod, 'cards', ['eyebrow' => 'Datenliste „Karten“', 'title' => 'Sortiment', 'fields' => ["$prod._title", "$prod.bild", "$prod.preis", "$prod.kurztext"], 'limit' => 6]), ['anchor' => 'sortiment']),
        $B('data_list', $dl($prod, 'table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Preisliste', 'fields' => ["$prod._title", "$prod.kategorie", "$prod.preis", "$prod.saison"]]), ['anchor' => 'preise', 'background' => 'muted']),
        $B('data_list', $dl($prod, 'list', ['eyebrow' => 'Datenliste „Liste mit Bild“', 'title' => 'Woher es kommt', 'fields' => ["$prod._title", "$prod.bild", "$prod.herkunft", "$prod.kurztext"], 'limit' => 3])),
        $B('specs', ['variant' => 'table', 'eyebrow' => 'Steckbrief', 'title' => 'Hofladen auf einen Blick', 'intro' => '', 'image' => null, 'ratio' => '4:3', 'note' => 'Beispielwerte.',
            'items' => [
                ['group' => 'Laden', 'label' => 'Sortiment', 'value' => 'rund 60 Produkte'],
                ['group' => 'Laden', 'label' => 'Eigene Ernte', 'value' => 'etwa die Hälfte'],
                ['group' => 'Laden', 'label' => 'Bezahlen', 'value' => 'bar oder mit Karte'],
                ['group' => 'Umwelt', 'label' => 'Verpackung', 'value' => 'Pfandglas, Papier, eigener Korb'],
                ['group' => 'Umwelt', 'label' => 'Lieferwege', 'value' => 'höchstens 30 km'],
                ['group' => 'Umwelt', 'label' => 'Strom', 'value' => 'Photovoltaik auf der Scheune'],
            ]], ['background' => 'tint']),
    ]);

    // ---------------------------------------------------------------- Termine (Liste, Kalender) + Gruppen-Formular
    $termine = $page(['slug' => 'termine', 'title' => 'Termine', 'sort' => $base + 2, 'og_image' => $W('blumenwiese'),
        'meta_description' => 'Hofführungen, Markttage, Kurse und Hoffest des fiktiven Hofguts Wiesengrund – als Liste und im Kalender.'], [
        $hero('Termine', 'Führungen, Markttage und Kurse', 'Samstags führen wir über den Hof, jeden zweiten Freitag ist Markttag. Alle Termine lassen sich abonnieren.'),
        $B('upcoming', ['eyebrow' => 'Nächste Termine', 'title' => 'Demnächst auf dem Hof', 'intro' => '', 'table' => $term, 'limit' => 6, 'days' => 0, 'layout' => 'list', 'show_location' => true, 'link_detail' => false, 'subscribe' => true, 'more_label' => '', 'more_link' => ''], ['anchor' => 'liste']),
        $B('calendar', ['eyebrow' => 'Kalender', 'title' => 'Monatsübersicht', 'intro' => 'Bei wenig Platz wird die Übersicht zur Liste der Tage mit Terminen. Filter nach Art.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => false], ['anchor' => 'kalender', 'background' => 'muted']),
        $B('data_list', $dl($term, 'table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Alle Termine mit Beitrag', 'fields' => ["$term._title", "$term.beginn", "$term.ort", "$term.kategorie", "$term.preis"], 'link_detail' => false])),
        $B('upcoming', ['eyebrow' => 'Kompakt in Waldnacht', 'title' => 'Die nächsten drei', 'intro' => '', 'table' => $term, 'limit' => 3, 'days' => 0, 'layout' => 'compact', 'show_location' => true, 'link_detail' => false, 'subscribe' => false], ['background' => 'dark']),
    ]);
    $page(['slug' => 'gruppen', 'title' => 'Gruppen & Schulklassen', 'parent_id' => $termine, 'sort' => 1,
        'meta_description' => 'Führungen für Schulklassen, Kitas und Vereine anfragen – öffentliches Formular mit Bedingung (Demo).'], [
        $hero('Gruppen', 'Ein Vormittag auf dem Hof', 'Für Schulklassen, Kitas, Vereine und Teams: Führung mit Mitmach-Station, auf Wunsch mit Brotbacken. Absenden ist gefahrlos – es ist eine Demo-Tabelle.'),
        $B('steps', ['variant' => 'steps', 'eyebrow' => 'Ablauf', 'title' => 'So läuft ein Gruppenbesuch', 'intro' => '',
            'items' => [
                ['meta' => '9:00', 'title' => 'Ankommen', 'text' => 'Begrüßung im Hofladen, kurze Sicherheitsregeln.'],
                ['meta' => '9:30', 'title' => 'Hofrunde', 'text' => 'Acker, Weide, Hühnermobil und Obstwiese.'],
                ['meta' => '10:30', 'title' => 'Mitmachen', 'text' => 'Säen, Äpfel pressen oder Brot backen – je nach Jahreszeit.'],
                ['meta' => '11:30', 'title' => 'Picknick', 'text' => 'Unter dem Nussbaum, bei Regen in der Scheune.'],
            ]]),
        $B('data_form', ['eyebrow' => 'Öffentliches Formular', 'title' => 'Führung anfragen', 'intro' => '„Schulklasse“ blendet die Klassenstufe als Pflichtfeld ein. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $form, 'submit_label' => '', 'success_text' => ''], ['background' => 'muted', 'anchor' => 'anfrage']),
        $B('faq', ['variant' => 'split', 'eyebrow' => 'Fragen', 'title' => 'Gut zu wissen', 'intro' => '', 'items' => [
            ['q' => 'Was kostet ein Gruppenbesuch?', 'a' => '<p>Schulklassen und Kitas 4 € pro Kind, Erwachsenengruppen 9 € pro Person (Beispielpreise).</p>'],
            ['q' => 'Ist der Hof barrierefrei?', 'a' => '<p>Hofladen, Hofplatz und Scheune sind stufenlos; die Wege zu Acker und Wiese sind geschottert.</p>'],
            ['q' => 'Was passiert bei Regen?', 'a' => '<p>Wir gehen trotzdem hinaus – mit kürzerer Runde und längerer Mitmach-Station in der Scheune.</p>'],
        ]]),
    ]);

    // ---------------------------------------------------------------- Erleben (Musterseite des Style-Editors: alle Blöcke)
    $root = $page(['slug' => 'erleben', 'title' => 'Erleben', 'sort' => $base + 3, 'og_image' => $W('teich'),
        'meta_description' => 'Alle Blöcke des Kits „nature“ mit frei erfundenen Inhalten des Hofguts Wiesengrund.'], [
        $B('hero', ['variant' => 'panel', 'eyebrow' => 'Naturerlebnis', 'title' => 'Draußen lernen, *mit allen Sinnen*.', 'text' => 'Diese Seite zeigt alle Bausteine des Kits mit erfundenen Inhalten. Wählen Sie unter Verwaltung → Design eine Jahreszeit und sehen Sie, wie sich alles mitändert.',
            'button_label' => 'Medien', 'button_link' => '/erleben/medien', 'button2_label' => 'Journal', 'button2_link' => '/erleben/journal',
            'points' => "Jahreszeiten: 4 Vorlagen\nSchriften: lokal\nCookies: 0", 'panel_label' => 'Teich am Wiesengrund', 'image' => $W('teich'), 'ratio' => '4:3']),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Merkmale „Karten“', 'title' => 'Stationen am Naturpfad', 'intro' => 'Ein Rundweg mit sechs Stationen, frei zugänglich (fiktiv).', 'size' => 's',
            'items' => [
                ['icon' => 'bird', 'title' => 'Hecke', 'text' => 'Nistplätze für Goldammer und Neuntöter.', 'link_label' => '', 'link' => ''],
                ['icon' => 'drop', 'title' => 'Teich', 'text' => 'Libellen, Molche und Schilf.', 'link_label' => '', 'link' => ''],
                ['icon' => 'tree', 'title' => 'Obstwiese', 'text' => '31 alte Apfelsorten.', 'link_label' => '', 'link' => ''],
                ['icon' => 'sun', 'title' => 'Blühstreifen', 'text' => 'Nahrung für Wildbienen bis in den Herbst.', 'link_label' => '', 'link' => ''],
            ]]),
        $B('features', ['variant' => 'list', 'eyebrow' => 'Merkmale „Liste“', 'title' => 'Was Sie mitbringen sollten', 'intro' => '', 'size' => 'l',
            'items' => [
                ['icon' => 'footprints', 'title' => 'Feste Schuhe', 'text' => 'Die Wege sind teils matschig.', 'link_label' => '', 'link' => ''],
                ['icon' => 'drop', 'title' => 'Wasserflasche', 'text' => 'Auffüllen am Hofladen.', 'link_label' => '', 'link' => ''],
                ['icon' => 'basket', 'title' => 'Einen Korb', 'text' => 'Für den Einkauf danach.', 'link_label' => '', 'link' => ''],
            ]], ['background' => 'muted']),
        $B('media_text', $mt('auto', 'Text + Bild', 'Die Hecke als Lebensraum', '<p>Zwei Kilometer Hecke gliedern die Felder, bremsen den Wind und bieten Vögeln und Insekten Nahrung und Schutz.</p>', $W('waldrand'), '4:3',
            "Schlehe, Weißdorn, Hundsrose\nalle 10 Jahre abschnittsweise geschnitten\nSaum aus Wildblumen", ['Naturpfad', '#pfad'])),
        $B('media_text', $mt('auto', 'Abwechselnd', 'Bienen am Waldrand', '<p>Zwölf Völker stehen zwischen Obstwiese und Wald. Mehrere Text-Bild-Blöcke nacheinander wechseln automatisch die Seite.</p>', $W('bienen'), '4:3')),
        $B('dials', ['eyebrow' => 'Kennzahlen mit Skala (Kern-Block)', 'title' => 'Ein Jahr am Teich', 'intro' => 'Beispielwerte.', 'size' => 'm', 'animate' => true,
            'items' => [
                ['value' => '14', 'unit' => '', 'label' => 'Libellenarten', 'text' => 'gezählt im Sommer', 'level' => '70'],
                ['value' => '3', 'unit' => '', 'label' => 'Amphibienarten', 'text' => 'Molche, Frösche, Kröten', 'level' => '30'],
                ['value' => '85', 'unit' => '%', 'label' => 'Uferbewuchs', 'text' => 'Schilf und Seggen', 'level' => '85'],
            ]], ['background' => 'tint']),
        $B('stats', ['variant' => 'row', 'eyebrow' => 'Kennzahlen „Reihe“', 'title' => 'Der Naturpfad', 'intro' => '',
            'items' => [
                ['value' => '3,2 km', 'label' => 'Rundweg', 'level' => '', 'text' => 'etwa 1 Stunde'],
                ['value' => '6', 'label' => 'Stationen', 'level' => '', 'text' => 'mit Tafeln'],
                ['value' => '80 %', 'label' => 'kinderwagentauglich', 'level' => '80', 'text' => 'Beispielwert'],
            ]], ['anchor' => 'pfad']),
        $B('steps', ['variant' => 'steps', 'eyebrow' => 'Ablauf „Pfad“', 'title' => 'Das Gartenjahr', 'intro' => '',
            'items' => [
                ['meta' => 'März', 'title' => 'Säen', 'text' => 'Vorziehen im Folientunnel.'],
                ['meta' => 'Mai', 'title' => 'Pflanzen', 'text' => 'Nach den Eisheiligen ins Beet.'],
                ['meta' => 'August', 'title' => 'Ernten', 'text' => 'Täglich, früh am Morgen.'],
                ['meta' => 'November', 'title' => 'Ruhen', 'text' => 'Gründüngung schützt den Boden.'],
            ]], ['background' => 'muted']),
        $B('principles', ['variant' => 'accordion', 'eyebrow' => 'Grundsätze „Aufklappbar“', 'title' => 'Regeln am Naturpfad', 'intro' => '',
            'items' => [
                ['title' => 'Auf den Wegen bleiben', 'text' => 'Bodenbrüter nisten mitten in der Wiese.'],
                ['title' => 'Hunde an die Leine', 'text' => 'Von März bis Juli ist Brut- und Setzzeit.'],
                ['title' => 'Nichts mitnehmen, nichts dalassen', 'text' => 'Außer Fotos und Erinnerungen.'],
            ]]),
        $B('cards', ['variant' => 'service', 'eyebrow' => 'Angebote „Symbol“', 'title' => 'Mitmachen im Jahreslauf', 'intro' => '', 'size' => 'm', 'ratio' => '4:3',
            'items' => [
                ['icon' => 'plant', 'eyebrow' => 'Frühling', 'title' => 'Pflanztag', 'text' => 'Jungpflanzen setzen, Beete mulchen.', 'meta' => 'frei', 'link_label' => 'Termine', 'link' => '/termine'],
                ['icon' => 'basket', 'eyebrow' => 'Herbst', 'title' => 'Apfelernte', 'text' => 'Sammeln, sortieren, Saft pressen.', 'meta' => '5 € / Familie', 'link_label' => 'Termine', 'link' => '/termine'],
                ['icon' => 'tree-evergreen', 'eyebrow' => 'Winter', 'title' => 'Heckenpflege', 'text' => 'Schneiden, Totholz aufschichten.', 'meta' => 'frei', 'link_label' => 'Termine', 'link' => '/termine'],
            ]], ['background' => 'muted']),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Stimmen (Beispiele)', 'title' => 'Was Gäste sagen', 'items' => [
            ['text' => 'Die Kinder reden seit Wochen nur noch vom Brotbacken.', 'name' => 'Kim Muster', 'role' => 'Lehrerin (fiktiv)', 'image' => null],
            ['text' => 'Endlich wissen wir, wo unsere Äpfel herkommen.', 'name' => 'Sam Beispiel', 'role' => 'Stammkunde (fiktiv)', 'image' => null],
            ['text' => 'Ruhig, ehrlich, ohne Show – so muss ein Hofbesuch sein.', 'name' => 'Jo Probe', 'role' => 'Wanderverein (fiktiv)', 'image' => null],
        ]]),
        $B('cta', ['variant' => 'minimal', 'eyebrow' => 'Handlungsaufruf „Zurückhaltend“', 'title' => 'Lust auf einen Rundgang?', 'text' => 'Samstags um 11 Uhr am Hofladen.', 'button_label' => 'Termine', 'button_link' => '/termine', 'button2_label' => '', 'button2_link' => ''], ['background' => 'tint']),
        $cta,
    ]);

    // ---------------------------------------------------------------- Medien
    $page(['slug' => 'medien', 'title' => 'Medien', 'parent_id' => $root, 'sort' => 1,
        'meta_description' => 'Galerie mit Lightbox, Slider, Stapelkarten, Video mit Zwei-Klick-Lösung, Downloads und Karte.'], [
        $B('hero', ['variant' => 'statement', 'eyebrow' => 'Einstieg „Große Aussage“', 'title' => 'Bilder, Video, Dateien, Karte.', 'text' => 'Alles kommt vom eigenen Server – Videos von YouTube oder Vimeo erst nach einem Klick.',
            'button_label' => 'Zur Galerie', 'button_link' => '#galerie', 'button2_label' => '', 'button2_link' => '', 'points' => "Galerie: Raster, Mosaik, Zeilen\nSlider mit Tastatur\nPDF im Browser", 'image' => $W('abend'), 'ratio' => '16:9']),
        $B('gallery', ['eyebrow' => 'Galerie „Raster“', 'title' => 'Rund um den Wiesengrund', 'intro' => 'Ein Klick öffnet die Lightbox.', 'source' => 'collection', 'collection' => $img['collection'],
            'layout' => 'grid', 'columns' => '3', 'ratio' => '3:2', 'captions' => true, 'lightbox' => true], ['anchor' => 'galerie']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Ein Jahr in Bildern', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $W('acker'), 'eyebrow' => 'Frühsommer', 'title' => 'Der Acker', 'text' => 'Abgedunkelt mit heller Schrift.', 'button_label' => 'Hofladen', 'button_link' => '/hofladen', 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $W('blumenwiese'), 'eyebrow' => 'Sommer', 'title' => 'Die Wiese', 'text' => 'Ein Kasten in der Hintergrundfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
            ['image' => $W('hof'), 'eyebrow' => 'Herbst', 'title' => 'Die Scheune', 'text' => 'Dunkle Schrift auf hellem Schleier.', 'button_label' => '', 'button_link' => '', 'position' => 'center', 'overlay' => 'light'],
        ]], ['background' => 'muted']),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Vom Korn zum Brot', 'intro' => 'Nur bei genug Platz und ohne „Bewegung reduzieren“ – sonst eine ruhige Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Säen', 'text' => 'Roggen im Herbst, Dinkel im Frühjahr.', 'image' => $W('acker'), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Ernten', 'text' => 'Im Juli, wenn die Körner hart sind.', 'image' => $W('abend'), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Backen', 'text' => 'Im Holzofen, freitags und samstags.', 'image' => $W('hof'), 'link_label' => 'Hofladen', 'link' => '/hofladen'],
        ]]),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video „Mit Text“', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen. Mit der Erweiterung Consent Kit gilt die dort erteilte Einwilligung.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W('blumenwiese'), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)'], ['background' => 'muted']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Zum Mitnehmen', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true, 'collection' => null,
            'files' => $pdf ? [['label' => 'Saisonkalender (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []]),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'So finden Sie uns', 'location' => 'site', 'zoom' => '13', 'height' => 'm', 'route' => true], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Journal (Artikel, FAQ)
    $page(['slug' => 'journal', 'title' => 'Journal', 'parent_id' => $root, 'sort' => 2, 'og_image' => $W('acker'),
        'meta_description' => 'Artikel mit Lesebreite, Inhaltsverzeichnis und Zitat; Fragen und Antworten.'], [
        $hero('Journal', 'Warum wir Hecken pflanzen', 'Ein Beispielartikel mit Inhaltsverzeichnis, Zwischenüberschriften und Zitat.'),
        $B('richtext', ['variant' => 'article', 'eyebrow' => 'Beitrag', 'title' => 'Zwei Kilometer Hecke', 'intro' => '', 'meta' => 'Hofgut Wiesengrund · Beispieltext', 'toc' => true, 'text' =>
            '<p>Eine Hecke zu pflanzen, heißt in Jahrzehnten zu denken. Dieser Text ist frei erfunden und zeigt die Gestaltung eines Artikels im Kit „nature“.</p>'
            . '<h2>Was eine Hecke leistet</h2><p>Sie bremst den Wind, hält Wasser im Boden, gibt Vögeln Nistplätze und Insekten Nahrung – vom ersten Schlehenblühen im März bis zu den Hagebutten im Winter.</p>'
            . '<blockquote><p>Wer eine Hecke pflanzt, pflanzt für seine Enkel.</p></blockquote>'
            . '<h2>Wie wir gepflanzt haben</h2><p>Dreireihig, mit heimischen Sträuchern aus der Region, und mit einem Saum aus Wildblumen auf beiden Seiten.</p><h3>Die Sträucher</h3><ul><li>Schlehe und Weißdorn für Vögel</li><li>Hundsrose und Holunder für Insekten</li><li>Hasel für Haselmaus und Eichhörnchen</li></ul>'
            . '<h2>Pflege</h2><p>Alle zehn Jahre wird abschnittsweise auf den Stock gesetzt – nie die ganze Hecke auf einmal. So bleibt immer ein Teil dicht und blühend.</p>'
            . '<h2>Was bleibt</h2><p>Ein Feld, das lebendiger ist als vorher. <strong>Und ein Weg, den man gern entlanggeht.</strong></p>']),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'Fragen „Untereinander“', 'title' => 'Fragen zum Kit', 'intro' => 'Suchmaschinen erhalten sie als strukturierte Daten.', 'items' => [
            ['q' => 'Gibt es Vorlagen für die Jahreszeiten?', 'a' => '<p>Ja: Frühling, Sommer, Herbst und Winter, dazu „Moos“ und „Waldnacht“. Die Landschaft im Einstieg wechselt auf Wunsch automatisch mit dem Datum.</p>'],
            ['q' => 'Brauche ich Fotos?', 'a' => '<p>Nein. Alle Blöcke funktionieren ohne Bilder; der Einstieg zeigt dann eine gezeichnete Landschaft.</p>'],
            ['q' => 'Gibt es ein dunkles Schema?', 'a' => '<p>Ja, „Waldnacht“ – automatisch, wenn Besucher es im Gerät eingestellt haben.</p>'],
        ]], ['background' => 'muted']),
        $cta,
    ]);
    $log('Seiten „Hofladen“, „Termine“ (mit „Gruppen“) und „Erleben“ (mit „Medien“, „Journal“) angelegt.');
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“

/**
 * Unterseite „Erleben → Hero-Varianten“: die Einstiege „Jahreszeiten-Bühne“ (automatisch und fest „Winter“), „Termine am
 * Ast“ (Demo-Tabelle hof_termine) und „Papierkarte“ (öffentliches Formular hof_gruppen), dazu „Mit Bildtafel“ zum Vergleich.
 * Einzeln (bestehende Websites): CMS_SITE=… php kits/nature/tools/demo.php --heroes
 */
function nature_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $root = $db->fetch("SELECT id FROM pages WHERE path = 'erleben' AND type = 'page' LIMIT 1");
    if (!$root) { $log('Keine Musterseite „Erleben“ vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE path = 'erleben/hero-varianten'") as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();
    $term = Tables::find('hof_termine') ? 'hof_termine' : '';
    $form = Tables::find('hof_gruppen') ? 'hof_gruppen' : '';
    $teich = $db->fetch("SELECT id FROM media WHERE tags LIKE ? AND original_name LIKE ? ORDER BY id LIMIT 1", ['%' . NATURE_DEMO_TAG . '%', '%teich%']);
    $none = ['button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''];
    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + ['eyebrow' => '', 'text' => ''] + $none, 'tunes' => ['section' => $tunes + ['divider' => true]]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Erleben · Hero-Varianten', 'title' => 'Sieben Einstiege, *ein Block*.',
            'text' => 'Der Block „Einstieg (Hero)“ hat sieben Varianten. Die drei neuen bringen das Wichtigste nach oben: ob der Hofladen geöffnet hat, die nächsten Markttage und ein Anfrageformular. Jede erscheint hier einmal – auf echten Seiten steht nur ein Einstieg ganz oben.'],
            ['background' => 'muted', 'divider' => false]),
        $B('season', ['eyebrow' => 'Variante „Jahreszeiten-Bühne“', 'title' => 'Frisch vom Feld, *direkt ab Hof*.',
            'text' => 'Das Panorama zeigt die aktuelle Jahreszeit („Automatisch nach Monat“) – ganz ohne Foto. Das Hofschild rechnet im Browser aus, ob gerade geöffnet ist; die Zeiten kommen aus „Website“.',
            'button_label' => 'Zum Hofladen', 'button_link' => '/hofladen', 'button2_label' => 'Anfahrt', 'button2_link' => '/kontakt',
            'season_scene' => 'auto', 'sign_label' => 'Hofladen', 'sign_hours' => true], ['divider' => false]),
        $B('dates', ['eyebrow' => 'Variante „Termine am Ast“', 'title' => 'Markttage, Führungen, *Kurse*.',
            'text' => 'Die nächsten drei Termine kommen aus der Kalender-Tabelle „Termine (Demo)“ – Wiederholungen erscheinen einzeln, vergangene verschwinden von selbst.',
            'button_label' => 'Alle Termine', 'button_link' => '/termine',
            'dates_table' => $term, 'dates_limit' => '3', 'dates_title' => 'Als Nächstes am Hof', 'dates_more_label' => 'Kalender öffnen', 'dates_more_link' => '/termine'],
            ['background' => 'tint']),
        $B('form', ['eyebrow' => 'Variante „Papierkarte“', 'title' => 'Ein Vormittag auf dem Hof – *für Ihre Gruppe*.',
            'text' => 'Schulklassen, Kitas, Vereine und Teams fragen gleich hier an. Das Formular ist die Demo-Tabelle „Gruppenanfragen“ – Absenden ist gefahrlos.',
            'points' => "Führung mit Mitmach-Station\nPicknick unter dem Nussbaum\nAntwort innerhalb von zwei Werktagen (Beispiel)",
            'form_table' => $form, 'form_title' => 'Führung anfragen', 'form_submit' => '', 'form_note' => 'Demo-Formular – es meldet sich niemand.']),
        $B('season', ['eyebrow' => 'Jahreszeiten-Bühne, fest „Winter“', 'title' => 'Winterruhe – *der Laden bleibt offen*.',
            'text' => 'Dieselbe Variante mit fester Jahreszeit auf dunklem Abschnitt: Schnee, kahle Bäume, Rauch aus dem Schornstein.',
            'season_scene' => 'winter', 'sign_label' => 'Hofladen', 'sign_hours' => true], ['background' => 'dark']),
        $B('panel', ['eyebrow' => 'Zum Vergleich: „Mit Bildtafel“', 'title' => 'Der Teich am *Wiesengrund*.',
            'text' => 'Die bisherige Standard-Variante: Text neben einer Bildtafel mit Eckdaten.',
            'points' => "Fläche: 1.200 m²\nTiefe: bis 2,4 m\nLibellenarten: 14", 'panel_label' => 'Beispielbild', 'image' => $teich ? (int) $teich['id'] : null, 'ratio' => '4:3']),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 3, 'status' => 'published', 'menu' => 1,
        'meta_description' => 'Alle neuen Einstiege (Hero) des Kits „nature“: Jahreszeiten-Bühne mit Öffnungszeiten, Termine am Ast, Papierkarte mit Formular.'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/erleben/hero-varianten).');
    return true;
}
