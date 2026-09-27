<?php
/**
 * Demo-Inhalte des Kits „modern“: „Studio Nordlicht – Produktdesign & Beratung (fiktiv)“.
 * Legt einen vollständigen Seitenbaum an, der jeden Block und jede Variante mit frei erfundenen Inhalten zeigt – inkl.
 * Datentabellen (Projekte mit Detailseite, öffentliches Anfrage-Formular mit Bedingung), einer Beispiel-PDF und
 * erzeugten Bildern (GD, prozedural: Polarlicht-Verläufe, gezeichnete Produkt-Entwürfe, Formkompositionen –
 * keine Fotos, keine Personen, keine Marken).
 *
 * Neue Websites: seed.php → 'after' (dann ist die Startseite die Studio-Startseite).
 * Bestehende Websites: CMS_SITE=… php kits/modern/tools/demo.php [--force|--remove] – dann entsteht alles unterhalb
 * von /nordlicht, die vorhandene Startseite bleibt unberührt.
 * Alles ist als Demo gekennzeichnet (Einstellung modern.demo_pages, Tabellen nordlicht_*, Medien-Schlagwort „modern-demo“).
 * Video: „Big Buck Bunny“ © 2008 Blender Foundation / www.bigbuckbunny.org, Lizenz CC BY 3.0 – per Zwei-Klick-Lösung.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const MODERN_DEMO_TAG = 'modern-demo';
const MODERN_DEMO_COLLECTION = 'Studio Nordlicht (Demo)';

/** Demo anlegen. $force: vorhandene vorher entfernen. */
function modern_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    if (json_decode((string) setting('modern.demo_pages', '[]'), true) && !$force) {
        $log('Die Demo-Inhalte sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    modern_demo_remove($log);

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist.
    // Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse von Personen oder Firmen).
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterweg 7', 'zip' => '12345', 'city' => 'Musterstadt', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    $img = modern_demo_images($log);
    $pdf = modern_demo_pdf($log);
    $tables = modern_demo_tables($img, $log);
    modern_demo_pages($img, $pdf, $tables, $log);
    modern_demo_heroes($log);   // Musterseite „Hero-Varianten“ unter dem Baukasten
    if (!(int) setting('og_default_image') && $img['wide']) app()->settings->set('og_default_image', $img['wide'][0]);
    \Core\PageCache::clear();
    $log('Fertig: Studio Nordlicht – ' . count($tables) . ' Datentabellen, ' . count($img['all']) . ' Bilder.');
    return true;
}

/** Demo-Seiten, Demo-Tabellen und Demo-Medien entfernen */
function modern_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', (array) json_decode((string) setting('modern.demo_pages', '[]'), true));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    app()->settings->set('modern.demo_pages', '[]');
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'nordlicht_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . MODERN_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query('DELETE FROM media_collections WHERE name = ?', [MODERN_DEMO_COLLECTION]);
    if ($ids) $log('Vorhandene Demo-Seiten entfernt (' . count($ids) . ').');
}

// ------------------------------------------------------------------ Bilder (GD, prozedural)

/** Farbpalette „Nordlicht“ */
function modern_demo_palette(): array
{
    return ['night' => [11, 19, 32], 'deep' => [18, 32, 52], 'ink' => [10, 13, 18], 'green' => [8, 107, 75], 'aurora' => [79, 214, 156],
        'lime' => [201, 242, 94], 'teal' => [45, 180, 190], 'violet' => [124, 92, 230], 'white' => [250, 251, 252], 'fog' => [236, 239, 243],
        'sand' => [240, 234, 222], 'blush' => [247, 220, 212], 'mint' => [214, 240, 226], 'sky' => [218, 230, 250], 'coral' => [232, 106, 72]];
}

/** @return array{all: list<int>, wide: list<int>, tall: list<int>, objects: list<int>, square: list<int>, collection: int} */
function modern_demo_images(callable $log): array
{
    $col = Media::createCollection(MODERN_DEMO_COLLECTION, 'Erzeugte Beispielbilder des Kits „modern“ – Polarlicht-Verläufe, gezeichnete Entwürfe, Formkompositionen. Frei verwendbar, ohne Personen oder Marken.');
    $import = function (string $file, string $name, string $title, string $alt) use ($col, $log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => MODERN_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt', 'collection' => $col]);
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    $tmp = fn(string $ext) => tempnam(sys_get_temp_dir(), 'mo') . '.' . $ext;
    $all = $wide = $tall = $objects = $square = [];
    // Polarlicht-Verläufe (3:2 und 4:5)
    foreach ([['Polarlicht über dem Fjord', 0], ['Grüner Schleier', 1], ['Nachtblau mit Band', 2]] as $i => [$title, $seed]) {
        $f = $tmp('jpg'); modern_demo_aurora($f, 1800, 1200, $seed);
        if ($id = $import($f, sprintf('nordlicht-aurora-%02d.jpg', $i + 1), $title, 'Abstrakter Farbverlauf in Grün und Blau, erinnert an Polarlicht (Beispielbild)')) { $all[] = $id; $wide[] = $id; }
    }
    foreach ([['Polarlicht, hoch', 3], ['Grünes Band, hoch', 4]] as $i => [$title, $seed]) {
        $f = $tmp('jpg'); modern_demo_aurora($f, 1200, 1500, $seed);
        if ($id = $import($f, sprintf('nordlicht-aurora-hoch-%02d.jpg', $i + 1), $title, 'Abstrakter Farbverlauf im Hochformat (Beispielbild)')) { $all[] = $id; $tall[] = $id; }
    }
    // Gezeichnete Produkt-Entwürfe (4:3 und 4:5) – erfundene Objekte
    $objs = [['Leuchte „Polar“', 'lamp', 'sand'], ['Lautsprecher „Echo“', 'speaker', 'sky'], ['Wasserkocher „Kelvin“', 'kettle', 'mint'],
        ['Stuhl „Fjell“', 'chair', 'blush'], ['Kopfhörer „Stille“', 'headphones', 'fog'], ['Thermoflasche „Tide“', 'bottle', 'lime']];
    foreach ($objs as $i => [$title, $kind, $bg]) {
        $f = $tmp('jpg'); modern_demo_object($f, 1600, 1200, $kind, $bg, $i);
        if ($id = $import($f, 'nordlicht-entwurf-' . $kind . '.jpg', $title . ' (Entwurf)', 'Zeichnung eines erfundenen Produkts: ' . $title . ' (Beispielbild)')) { $all[] = $id; $objects[] = $id; }
    }
    foreach ([['lamp', 'lime', 'Leuchte „Polar“, Detail'], ['chair', 'sky', 'Stuhl „Fjell“, Detail']] as $i => [$kind, $bg, $title]) {
        $f = $tmp('jpg'); modern_demo_object($f, 1200, 1500, $kind, $bg, 10 + $i);
        if ($id = $import($f, 'nordlicht-entwurf-hoch-' . $kind . '.jpg', $title, 'Zeichnung eines erfundenen Produkts im Hochformat (Beispielbild)')) { $all[] = $id; $tall[] = $id; }
    }
    // Formkompositionen (1:1 und 3:2)
    foreach (range(0, 3) as $i) {
        $f = $tmp('jpg'); modern_demo_shapes($f, 1400, $i % 2 ? 1400 : 934, $i);
        if ($id = $import($f, sprintf('nordlicht-form-%02d.jpg', $i + 1), 'Formstudie ' . ($i + 1), 'Grafische Komposition aus Kreisen und Flächen (Beispielbild)')) { $all[] = $id; ($i % 2 ? $square[] = $id : $wide[] = $id); }
    }
    $log(count($all) . ' Beispielbilder erzeugt.');
    return ['all' => $all, 'wide' => $wide, 'tall' => $tall, 'objects' => $objects, 'square' => $square, 'collection' => $col];
}

/** Polarlicht: Farbfeld in kleiner Auflösung rechnen (weich), hochskalieren, Sterne und Horizont scharf darüber */
function modern_demo_aurora(string $file, int $w, int $h, int $seed): void
{
    $P = modern_demo_palette();
    mt_srand(7100 + $seed);
    $lw = (int) ($w / 6); $lh = (int) ($h / 6);
    $lo = imagecreatetruecolor($lw, $lh);
    $bands = [];
    foreach ([$P['aurora'], $P['lime'], $P['teal'], $P['violet']] as $k => $col) {
        if ($k === 3 && $seed % 2) continue;
        $bands[] = ['col' => $col, 'y' => .28 + mt_rand(0, 30) / 100, 'amp' => .06 + mt_rand(0, 10) / 100, 'f' => (1.2 + mt_rand(0, 20) / 10) * M_PI / $lw,
            'ph' => mt_rand(0, 628) / 100, 'wid' => .05 + mt_rand(0, 6) / 100, 'str' => .55 + mt_rand(0, 35) / 100, 'g' => (8 + mt_rand(0, 14)) * M_PI / $lw];
    }
    for ($y = 0; $y < $lh; $y++) {
        $t = $y / $lh;
        $base = [$P['night'][0] + ($P['deep'][0] - $P['night'][0]) * $t, $P['night'][1] + ($P['deep'][1] - $P['night'][1]) * $t, $P['night'][2] + ($P['deep'][2] - $P['night'][2]) * $t];
        for ($x = 0; $x < $lw; $x++) {
            $c = $base;
            foreach ($bands as $b) {
                $cy = $b['y'] + $b['amp'] * sin($x * $b['f'] + $b['ph']);
                $d = ($t - $cy) / $b['wid'];
                // Vorhang: oben ausfransend (lange Fahne), unten scharf
                $v = $d < 0 ? exp($d * .9) : exp(-$d * $d * 2.2);
                $v *= $b['str'] * (.65 + .35 * sin($x * $b['g'] + $b['ph'] * 2));
                for ($k = 0; $k < 3; $k++) $c[$k] += $b['col'][$k] * $v * .85;
            }
            imagesetpixel($lo, $x, $y, imagecolorallocate($lo, (int) min(255, $c[0]), (int) min(255, $c[1]), (int) min(255, $c[2])));
        }
    }
    $im = imagecreatetruecolor($w, $h);
    imagecopyresampled($im, $lo, 0, 0, 0, 0, $w, $h, $lw, $lh);
    // Sterne
    for ($i = 0; $i < 140; $i++) {
        $sx = mt_rand(0, $w); $sy = mt_rand(0, (int) ($h * .55)); $a = mt_rand(40, 100);
        $col = imagecolorallocatealpha($im, 255, 255, 255, 127 - (int) ($a * 1.2));
        $r = mt_rand(0, 9) > 7 ? 3 : 2;
        imagefilledellipse($im, $sx, $sy, $r, $r, $col);
    }
    // Horizont: sanfte Hügel in Nachtblau
    $hill = imagecolorallocate($im, 6, 11, 20);
    $pts = [0, $h];
    for ($x = 0; $x <= $w; $x += 20) $pts = array_merge($pts, [$x, (int) ($h * (.84 + .05 * sin($x / $w * 5 + $seed) + .02 * sin($x / $w * 17 + $seed * 3)))]);
    $pts = array_merge($pts, [$w, $h]);
    imagefilledpolygon($im, $pts, $hill);
    imagejpeg($im, $file, 84);
}

/** Abgerundetes Rechteck (gefüllt) */
function modern_rrect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
}

/** Gezeichnetes Produkt auf Studiofläche (doppelt gerendert und verkleinert = glatte Kanten) */
function modern_demo_object(string $file, int $w, int $h, string $kind, string $bgKey, int $seed): void
{
    $P = modern_demo_palette();
    $W = $w * 2; $H = $h * 2;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    $c = fn(array $rgb, int $a = 0) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a);
    $bg = $P[$bgKey];
    $floor = array_map(fn($v) => (int) max(0, $v - 14), $bg);
    // Hohlkehle: Wand oben, Boden unten mit weichem Übergang
    for ($y = 0; $y < $H; $y++) {
        $t = max(0, min(1, ($y - $H * .58) / ($H * .14)));
        $t = $t * $t * (3 - 2 * $t);
        imageline($im, 0, $y, $W, $y, $c([(int) ($bg[0] + ($floor[0] - $bg[0]) * $t), (int) ($bg[1] + ($floor[1] - $bg[1]) * $t), (int) ($bg[2] + ($floor[2] - $bg[2]) * $t)]));
    }
    $u = min($W, $H) / 100;
    $cx = (int) ($W / 2); $base = (int) ($H * .8);
    // weicher Schatten
    for ($k = 0; $k < 18; $k++) imagefilledellipse($im, $cx, $base, (int) ($u * (70 - $k * 2.6)), (int) ($u * (9 - $k * .4)), $c([0, 0, 0], 124 - $k));
    $ink = $P['ink']; $white = $P['white']; $green = $P['green']; $lime = $P['lime']; $fog = $P['fog'];
    $mid = [196, 202, 210]; $dark2 = [34, 40, 50];
    switch ($kind) {
        case 'lamp':
            modern_rrect($im, (int) ($cx - $u * 16), (int) ($base - $u * 4), (int) ($cx + $u * 16), $base, (int) ($u * 2), $c($ink));
            modern_rrect($im, (int) ($cx - $u * 1.2), (int) ($base - $u * 46), (int) ($cx + $u * 1.2), (int) ($base - $u * 3), (int) $u, $c($dark2));
            imagefilledarc($im, $cx, (int) ($base - $u * 44), (int) ($u * 40), (int) ($u * 40), 180, 360, $c($green), IMG_ARC_PIE);
            modern_rrect($im, (int) ($cx - $u * 20), (int) ($base - $u * 45), (int) ($cx + $u * 20), (int) ($base - $u * 43), (int) $u, $c($dark2));
            imagefilledellipse($im, $cx, (int) ($base - $u * 42), (int) ($u * 12), (int) ($u * 4), $c([255, 240, 190], 20));
            for ($k = 0; $k < 10; $k++) imagefilledellipse($im, $cx, (int) ($base - $u * 30), (int) ($u * (30 - $k * 2)), (int) ($u * (24 - $k * 2)), $c([255, 244, 200], 122 - $k));
            imagefilledellipse($im, (int) ($cx + $u * 11), (int) ($base - $u * 2), (int) ($u * 2), (int) ($u * 2), $c($lime));
            break;
        case 'speaker':
            modern_rrect($im, (int) ($cx - $u * 17), (int) ($base - $u * 56), (int) ($cx + $u * 17), $base, (int) ($u * 6), $c($ink));
            imagefilledellipse($im, $cx, (int) ($base - $u * 20), (int) ($u * 24), (int) ($u * 24), $c($dark2));
            imagefilledellipse($im, $cx, (int) ($base - $u * 20), (int) ($u * 10), (int) ($u * 10), $c([22, 26, 33]));
            imagefilledellipse($im, $cx, (int) ($base - $u * 43), (int) ($u * 11), (int) ($u * 11), $c($dark2));
            imagefilledellipse($im, $cx, (int) ($base - $u * 43), (int) ($u * 4), (int) ($u * 4), $c([22, 26, 33]));
            modern_rrect($im, (int) ($cx - $u * 8), (int) ($base - $u * 5), (int) ($cx + $u * 8), (int) ($base - $u * 4), (int) ($u * .5), $c($lime));
            break;
        case 'kettle':
            imagefilledellipse($im, $cx, (int) ($base - $u * 20), (int) ($u * 40), (int) ($u * 40), $c($white));
            modern_rrect($im, (int) ($cx - $u * 20), (int) ($base - $u * 20), (int) ($cx + $u * 20), $base, (int) ($u * 3), $c($white));
            modern_rrect($im, (int) ($cx - $u * 21), (int) ($base - $u * 4), (int) ($cx + $u * 21), $base, (int) ($u * 2), $c($ink));
            imagesetthickness($im, (int) ($u * 3));
            imagearc($im, (int) ($cx + $u * 20), (int) ($base - $u * 22), (int) ($u * 18), (int) ($u * 22), 270, 90, $c($ink));
            imagesetthickness($im, 1);
            $pts = [(int) ($cx - $u * 18), (int) ($base - $u * 26), (int) ($cx - $u * 30), (int) ($base - $u * 34), (int) ($cx - $u * 28), (int) ($base - $u * 30), (int) ($cx - $u * 17), (int) ($base - $u * 18)];
            imagefilledpolygon($im, $pts, $c($white));
            modern_rrect($im, (int) ($cx - $u * 4), (int) ($base - $u * 42), (int) ($cx + $u * 4), (int) ($base - $u * 39), (int) $u, $c($ink));
            imagefilledellipse($im, (int) ($cx + $u * 12), (int) ($base - $u * 8), (int) ($u * 2.2), (int) ($u * 2.2), $c($green));
            break;
        case 'chair':
            $leg = $c($dark2);
            imagesetthickness($im, (int) ($u * 1.6));
            imageline($im, (int) ($cx - $u * 14), (int) ($base - $u * 22), (int) ($cx - $u * 18), $base, $leg);
            imageline($im, (int) ($cx + $u * 14), (int) ($base - $u * 22), (int) ($cx + $u * 18), $base, $leg);
            imageline($im, (int) ($cx - $u * 8), (int) ($base - $u * 22), (int) ($cx - $u * 10), (int) ($base - $u * 3), $leg);
            imageline($im, (int) ($cx + $u * 8), (int) ($base - $u * 22), (int) ($cx + $u * 10), (int) ($base - $u * 3), $leg);
            imagesetthickness($im, 1);
            modern_rrect($im, (int) ($cx - $u * 19), (int) ($base - $u * 27), (int) ($cx + $u * 19), (int) ($base - $u * 21), (int) ($u * 3), $c($green));
            modern_rrect($im, (int) ($cx - $u * 17), (int) ($base - $u * 56), (int) ($cx + $u * 17), (int) ($base - $u * 34), (int) ($u * 10), $c($green));
            imagesetthickness($im, (int) ($u * 1.6));
            imageline($im, (int) ($cx - $u * 12), (int) ($base - $u * 36), (int) ($cx - $u * 14), (int) ($base - $u * 25), $leg);
            imageline($im, (int) ($cx + $u * 12), (int) ($base - $u * 36), (int) ($cx + $u * 14), (int) ($base - $u * 25), $leg);
            imagesetthickness($im, 1);
            break;
        case 'headphones':
            imagesetthickness($im, (int) ($u * 4));
            imagearc($im, $cx, (int) ($base - $u * 24), (int) ($u * 44), (int) ($u * 50), 180, 360, $c($ink));
            imagesetthickness($im, 1);
            modern_rrect($im, (int) ($cx - $u * 27), (int) ($base - $u * 30), (int) ($cx - $u * 15), (int) ($base - $u * 8), (int) ($u * 5), $c($ink));
            modern_rrect($im, (int) ($cx + $u * 15), (int) ($base - $u * 30), (int) ($cx + $u * 27), (int) ($base - $u * 8), (int) ($u * 5), $c($ink));
            modern_rrect($im, (int) ($cx - $u * 17), (int) ($base - $u * 28), (int) ($cx - $u * 13), (int) ($base - $u * 10), (int) ($u * 2), $c($mid));
            modern_rrect($im, (int) ($cx + $u * 13), (int) ($base - $u * 28), (int) ($cx + $u * 17), (int) ($base - $u * 10), (int) ($u * 2), $c($mid));
            imagefilledellipse($im, (int) ($cx - $u * 21), (int) ($base - $u * 19), (int) ($u * 3), (int) ($u * 3), $c($lime));
            break;
        case 'bottle':
            modern_rrect($im, (int) ($cx - $u * 10), (int) ($base - $u * 52), (int) ($cx + $u * 10), $base, (int) ($u * 5), $c($ink));
            modern_rrect($im, (int) ($cx - $u * 7), (int) ($base - $u * 62), (int) ($cx + $u * 7), (int) ($base - $u * 50), (int) ($u * 2), $c($green));
            modern_rrect($im, (int) ($cx - $u * 10), (int) ($base - $u * 30), (int) ($cx + $u * 10), (int) ($base - $u * 22), 0, $c($fog));
            imagefilledellipse($im, (int) ($cx - $u * 5), (int) ($base - $u * 40), (int) ($u * 2), (int) ($u * 14), $c($white, 90));
            break;
    }
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $W, $H);
    imagejpeg($out, $file, 86);
}

/** Formkomposition: große Kreise, Bögen und Flächen in den Farben des Studios */
function modern_demo_shapes(string $file, int $w, int $h, int $seed): void
{
    $P = modern_demo_palette();
    $W = $w * 2; $H = $h * 2;
    $im = imagecreatetruecolor($W, $H);
    $c = fn(array $rgb, int $a = 0) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a);
    $sets = [
        [$P['night'], $P['lime'], $P['aurora'], $P['fog']],
        [$P['lime'], $P['night'], $P['green'], $P['white']],
        [$P['fog'], $P['green'], $P['night'], $P['lime']],
        [$P['green'], $P['lime'], $P['night'], $P['mint']],
    ];
    [$bg, $a, $b, $d] = $sets[$seed % 4];
    imagefilledrectangle($im, 0, 0, $W, $H, $c($bg));
    $u = min($W, $H) / 10;
    switch ($seed % 4) {
        case 0:
            imagefilledellipse($im, (int) ($W * .32), (int) ($H * .55), (int) ($u * 7), (int) ($u * 7), $c($a));
            imagefilledarc($im, (int) ($W * .7), (int) ($H * .95), (int) ($u * 8), (int) ($u * 8), 180, 360, $c($b), IMG_ARC_PIE);
            modern_rrect($im, (int) ($W * .62), (int) ($H * .12), (int) ($W * .9), (int) ($H * .4), (int) ($u * .4), $c($d));
            break;
        case 1:
            for ($k = 5; $k >= 1; $k--) imagefilledellipse($im, (int) ($W * .5), (int) ($H * .5), (int) ($k * $u * 1.8), (int) ($k * $u * 1.8), $c($k % 2 ? $a : $bg));
            imagefilledellipse($im, (int) ($W * .5), (int) ($H * .5), (int) ($u * 1.2), (int) ($u * 1.2), $c($b));
            break;
        case 2:
            modern_rrect($im, (int) ($W * .08), (int) ($H * .12), (int) ($W * .46), (int) ($H * .88), (int) ($u * .6), $c($a));
            imagefilledellipse($im, (int) ($W * .7), (int) ($H * .38), (int) ($u * 5), (int) ($u * 5), $c($b));
            modern_rrect($im, (int) ($W * .54), (int) ($H * .7), (int) ($W * .92), (int) ($H * .88), (int) ($u * .3), $c($d));
            break;
        case 3:
            for ($k = 0; $k < 4; $k++) imagefilledarc($im, (int) ($W * (.2 + $k * .2)), $H, (int) ($u * 3.4), (int) ($u * 3.4), 180, 360, $c($k % 2 ? $a : $b), IMG_ARC_PIE);
            imagefilledellipse($im, (int) ($W * .5), (int) ($H * .32), (int) ($u * 3.4), (int) ($u * 3.4), $c($d));
            break;
    }
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $W, $H);
    imagejpeg($out, $file, 88);
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function modern_demo_pdf(callable $log): ?int
{
    $lines = ['Studio Nordlicht - Leistungsuebersicht (Demo)', 'Frei erfundener Inhalt eines Beispiel-Studios.', 'Strategie - Produktdesign - Prototypen - Begleitung'];
    $stream = 'BT /F1 20 Tf 72 760 Td (' . $lines[0] . ') Tj /F1 12 Tf 0 -30 Td (' . $lines[1] . ') Tj 0 -18 Td (' . $lines[2] . ') Tj ET';
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
    $file = tempnam(sys_get_temp_dir(), 'mo') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'nordlicht-leistungen.pdf', '', ['title' => 'Leistungsübersicht (Beispiel)', 'tags' => MODERN_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function modern_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function modern_demo_tables(array $img, callable $log): array
{
    $out = [];
    $O = fn(int $n) => $img['objects'][$n % max(1, count($img['objects']))] ?? null;

    // Projekte: Karten, Liste, Tabelle, Detailseite
    $t = modern_demo_table([
        'handle' => 'nordlicht_projekte', 'name' => 'Projekte (Nordlicht)', 'singular' => 'Projekt', 'icon' => 'cube',
        'description' => 'Beispiel-Tabelle des Kits „modern“ – frei erfundene Produkt-Entwürfe für erfundene Auftraggeber.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'kunde', 'label' => 'Auftraggeber (fiktiv)', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'kategorie', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "licht=Licht\nklang=Klang\nkueche=Küche\nmoebel=Möbel\nunterwegs=Unterwegs"],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'leistungen', 'label' => 'Leistungen', 'type' => 'text', 'width' => 'half'],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'projekte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'jahr', 'sort_dir' => 'desc', 'workflow' => 0, 'structured' => 'CreativeWork'],
    ], $log);
    if ($t) {
        $rows = [
            ['Leuchte „Polar“', 'Lichtwerk Beispiel GmbH', 'licht', 2026, 'Strategie, Produktdesign, Prototypen', 'Eine Tischleuchte, die ohne Schalter auskommt: Antippen des Schirms dimmt stufenlos.'],
            ['Lautsprecher „Echo“', 'Klangkontor Muster', 'klang', 2025, 'Produktdesign, Materialwahl', 'Ein Regallautsprecher aus recyceltem Aluminium mit nur einem Bedienelement.'],
            ['Wasserkocher „Kelvin“', 'Haushalt Beispiel AG', 'kueche', 2025, 'Nutzerforschung, Produktdesign', 'Temperatur in fünf Stufen, sichtbar an einem einzigen Lichtpunkt.'],
            ['Stuhl „Fjell“', 'Möbelmanufaktur Muster', 'moebel', 2024, 'Produktdesign, Serienbegleitung', 'Ein stapelbarer Stuhl aus zwei Formteilen – ohne Kleber, vollständig trennbar.'],
            ['Kopfhörer „Stille“', 'Klangkontor Muster', 'klang', 2024, 'Strategie, Produktdesign', 'Geschlossene Kopfhörer mit austauschbaren Polstern und Akku.'],
            ['Thermoflasche „Tide“', 'Draußen Beispiel e. K.', 'unterwegs', 2023, 'Produktdesign, Verpackung', 'Eine Flasche, ein Deckel, drei Farben – und ein Ersatzteilprogramm.'],
        ];
        foreach ($rows as $i => [$title, $client, $cat, $year, $services, $text]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'kunde' => $client . ' (fiktiv)', 'kategorie' => $cat, 'jahr' => (string) $year, 'leistungen' => $services, 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Ein frei erfundenes Projekt, das zeigt, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h2>Ausgangslage</h2><p>Der Auftraggeber wünschte sich ein Produkt, das sich ohne Anleitung bedienen lässt und sich reparieren statt ersetzen lässt.</p>'
                    . '<blockquote><p>Weniger Teile, klarere Funktion, längeres Leben.</p></blockquote>'
                    . '<h2>Vorgehen</h2><ul><li>Interviews und Beobachtung im Alltag</li><li>Drei Richtungen als Modelle im Maßstab 1:1</li><li>Serienreife mit dem Hersteller</li></ul>'
                    . '<h2>Ergebnis</h2><p>Ein Entwurf mit wenigen, gut erreichbaren Bedienelementen und Ersatzteilen für zehn Jahre (fiktiv).</p>',
                'bild' => $O($i), 'status' => 'published']);
            if ($err) $log('Projekt „' . $title . '“: ' . implode(' ', $err));
        }
        $out['projekte'] = $t;
    }

    // Öffentliches Formular: Anfrage mit Bedingung (Budget nur bei „Projekt“)
    $t = modern_demo_table([
        'handle' => 'nordlicht_anfragen', 'name' => 'Anfragen (Nordlicht)', 'singular' => 'Anfrage', 'icon' => 'envelope-simple',
        'description' => 'Öffentliches Beispiel-Formular des Kits „modern“.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'anliegen', 'label' => 'Worum geht es?', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => "frage=Kurze Frage\nprojekt=Neues Projekt\nworkshop=Workshop"],
            ['name' => 'budget', 'label' => 'Rahmen (ungefähr)', 'type' => 'select', 'width' => 'half', 'options' => "klein=bis 10.000 €\nmittel=10.000–40.000 €\ngross=über 40.000 €",
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'anliegen', 'op' => '=', 'value' => 'projekt']]]],
            ['name' => 'nachricht', 'label' => 'Nachricht', 'type' => 'textarea', 'required' => true, 'help' => 'Zwei, drei Sätze genügen.'],
            ['name' => 'rueckruf', 'label' => 'Bitte rufen Sie mich zurück', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke – Ihre Anfrage ist eingegangen (Demo: es meldet sich niemand).', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['anfragen'] = $t;

    $log(count($out) . ' Datentabellen angelegt (nordlicht_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function modern_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $W = fn(int $n) => $img['wide'][$n % max(1, count($img['wide']))] ?? null;
    $T = fn(int $n) => $img['tall'][$n % max(1, count($img['tall']))] ?? null;
    $O = fn(int $n) => $img['objects'][$n % max(1, count($img['objects']))] ?? null;
    $S = fn(int $n) => $img['square'][$n % max(1, count($img['square']))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $compact = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text]);
    $proj = $tables['projekte']['handle'] ?? '';
    $form = $tables['anfragen']['handle'] ?? '';

    // Neue Website: Studio als Startseite. Bestehende Website: alles unter /nordlicht.
    $asSite = Pages::home() === null;
    $created = [];
    $root = null;
    $sort = (int) app()->db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL');
    $page = function (array $f, array $blocks) use (&$created, &$root, &$sort): int {
        $f += ['status' => 'published', 'menu' => 1];
        if ($root !== null && !array_key_exists('parent_id', $f)) $f['parent_id'] = $root;
        if (!isset($f['sort'])) $f['sort'] = $sort++;
        $id = Pages::create($f, Pages::sanitizeBlocks($blocks));
        $created[] = $id;
        return $id;
    };
    $link = fn(string $path) => ($asSite ? '' : '/nordlicht') . $path;
    $cta = $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Haben Sie eine Idee? *Lassen Sie uns reden.*',
        'text' => 'Ein erstes Gespräch ist kostenlos und dauert selten länger als 30 Minuten.',
        'button_label' => 'Projekt anfragen', 'button_link' => $link('/kontakt'), 'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email'], ['background' => 'accent']);

    // ---------------------------------------------------------------- Startseite
    $homeBlocks = [
        $B('hero', ['variant' => 'mosaic', 'eyebrow' => 'Produktdesign & Beratung · fiktives Studio', 'title' => 'Dinge, die man *gern* benutzt.',
            'text' => 'Studio Nordlicht entwirft Produkte mit wenigen Bedienelementen, ehrlichen Materialien und langer Lebensdauer – von der ersten Frage bis zur Serie.',
            'button_label' => 'Projekte ansehen', 'button_link' => $link('/projekte'), 'button2_label' => 'Leistungen', 'button2_link' => $link('/leistungen'),
            'points' => "Erstgespräch kostenlos\nAntwort innerhalb von 48 Stunden", 'stat_value' => '140+', 'stat_label' => 'Entwürfe seit 2016 (fiktiv)',
            'image' => $T(0), 'image2' => $O(0), 'image3' => $W(0), 'ratio' => '4:5']),
        $B('logos', ['variant' => 'marquee', 'eyebrow' => 'Auftraggeber (erfundene Beispiele)', 'title' => '', 'items' => array_map(fn($n) => ['name' => $n, 'link' => '', 'image' => null],
            ['Lichtwerk Beispiel', 'Klangkontor Muster', 'Haushalt Beispiel', 'Möbelmanufaktur Muster', 'Draußen Beispiel', 'Fjordlabor', 'Kiel & Kante'])], ['spaceTop' => 'small', 'spaceBottom' => 'small']),
        $B('bento', ['eyebrow' => 'Was wir tun', 'title' => 'Vom ersten Gedanken bis zur *Serie*', 'intro' => 'Vier Disziplinen, ein Team. Sie haben eine Ansprechperson – von der Strategie bis zum Serienanlauf.', 'items' => [
            ['size' => 'big', 'tone' => 'image', 'icon' => '', 'image' => $W(1), 'eyebrow' => 'Strategie', 'title' => 'Wir klären, was ein Produkt wirklich können muss.', 'text' => 'Interviews, Beobachtung, Wettbewerb – verdichtet auf eine Seite.', 'link' => $link('/leistungen')],
            ['size' => 's', 'tone' => 'pop', 'icon' => '', 'image' => null, 'eyebrow' => '98 %', 'title' => 'Projekte im Zeitplan', 'text' => 'Beispielwert eines fiktiven Studios.', 'link' => ''],
            ['size' => 'tall', 'tone' => 'card', 'icon' => 'pencil-ruler', 'image' => $O(3), 'eyebrow' => '', 'title' => 'Produktdesign', 'text' => 'Form, Farbe, Material – als nachvollziehbares System.', 'link' => $link('/leistungen')],
            ['size' => 's', 'tone' => 'dark', 'icon' => 'cube', 'image' => null, 'eyebrow' => '', 'title' => 'Prototypen im Maßstab 1:1', 'text' => 'Gedruckt, gefräst, getestet.', 'link' => ''],
            ['size' => 's', 'tone' => 'tint', 'icon' => 'recycle', 'image' => null, 'eyebrow' => '', 'title' => 'Reparierbar geplant', 'text' => 'Schrauben statt Kleber, Ersatzteile statt Neukauf.', 'link' => ''],
            ['size' => 'full', 'tone' => 'accent', 'icon' => 'handshake', 'image' => null, 'eyebrow' => 'Begleitung', 'title' => 'Wir bleiben bis zur Serie – und danach.', 'text' => 'Abstimmung mit Herstellern, Qualitätsprüfung, Pflege der Produktfamilie.', 'link' => $link('/studio')],
        ]]),
        // Karten statt Datenliste: hält die Startseite schlank (Budget < 30 KB CSS); Links führen auf die Detailseiten der Datentabelle
        $B('cards', ['variant' => 'image', 'eyebrow' => 'Ausgewählte Projekte', 'title' => 'Neu aus dem Studio', 'intro' => 'Alle Projekte und Auftraggeber sind frei erfunden.', 'size' => 'm', 'ratio' => '4:3', 'items' => [
            ['image' => $O(0), 'eyebrow' => 'Licht · 2026', 'title' => 'Leuchte „Polar“', 'text' => 'Antippen des Schirms dimmt stufenlos – ganz ohne Schalter.', 'link_label' => '', 'link' => '/projekte/' . Pages::slugify('Leuchte „Polar“')],
            ['image' => $O(2), 'eyebrow' => 'Küche · 2025', 'title' => 'Wasserkocher „Kelvin“', 'text' => 'Fünf Temperaturstufen, ein einziger Lichtpunkt.', 'link_label' => '', 'link' => '/projekte/' . Pages::slugify('Wasserkocher „Kelvin“')],
            ['image' => $O(1), 'eyebrow' => 'Klang · 2025', 'title' => 'Lautsprecher „Echo“', 'text' => 'Recyceltes Aluminium, nur ein Bedienelement.', 'link_label' => '', 'link' => '/projekte/' . Pages::slugify('Lautsprecher „Echo“')],
        ], 'more_label' => 'Alle Projekte', 'more_link' => $link('/projekte')], ['background' => 'muted']),
        $B('stats', ['variant' => 'row', 'eyebrow' => 'In Zahlen', 'title' => 'Klein im Team, groß in der Wirkung', 'intro' => 'Beispielwerte eines fiktiven Studios.', 'items' => [
            ['value' => '10', 'label' => 'Jahre Erfahrung', 'text' => 'seit 2016 (fiktiv)'],
            ['value' => '140+', 'label' => 'Entwürfe', 'text' => 'vom Löffel bis zur Leuchte'],
            ['value' => '7', 'label' => 'Personen im Team', 'text' => 'Design, Technik, Forschung'],
            ['value' => '0', 'label' => 'Cookies auf dieser Website', 'text' => 'Schriften und Bilder lokal'],
        ]]),
        $B('quote', ['variant' => 'single', 'eyebrow' => 'Stimme (Beispiel)', 'title' => '', 'items' => [
            ['text' => 'Nordlicht hat unser Produkt nicht schöner gemacht, sondern verständlicher. Die Rückfragen im Service haben sich halbiert.', 'name' => 'Kim Beispiel', 'role' => 'Produktleitung, Lichtwerk Beispiel (fiktiv)', 'image' => null],
        ]], ['background' => 'pop']),
        $cta,
    ];

    if ($asSite) {
        $homeId = $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => 0, 'og_image' => $W(0),
            'meta_description' => 'Studio Nordlicht (fiktiv): Produktdesign und Beratung – Dinge mit wenigen Bedienelementen, ehrlichen Materialien und langer Lebensdauer.'], $homeBlocks);
    } else {
        $root = $page(['slug' => 'nordlicht', 'title' => 'Studio Nordlicht (Demo)', 'og_image' => $W(0),
            'meta_description' => 'Demo des Kits „modern“: das fiktive Studio Nordlicht mit allen Blöcken.'], $homeBlocks);
    }

    // ---------------------------------------------------------------- Leistungen
    $page(['slug' => 'leistungen', 'title' => 'Leistungen', 'meta_description' => 'Strategie, Produktdesign, Prototypen und Begleitung – die Leistungen des fiktiven Studio Nordlicht.'], [
        $compact('Leistungen', 'Vier Disziplinen. *Ein* Team.', 'Wir arbeiten in klaren Schritten und zeigen Ihnen jederzeit, woran wir gerade sind.'),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Angebot', 'title' => 'Was Sie bei uns bekommen', 'intro' => '', 'size' => 'l', 'items' => [
            ['icon' => 'compass', 'image' => null, 'title' => 'Strategie', 'text' => 'Wir klären Zielgruppe, Nutzung und Rahmen – bevor gestaltet wird.', 'link_label' => '', 'link' => $link('/kontakt')],
            ['icon' => 'pencil-ruler', 'image' => null, 'title' => 'Produktdesign', 'text' => 'Form, Material und Bedienung als stimmiges System.', 'link_label' => '', 'link' => $link('/projekte')],
            ['icon' => 'cube', 'image' => null, 'title' => 'Prototypen', 'text' => 'Modelle im Maßstab 1:1 – zum Anfassen, Testen und Verwerfen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'handshake', 'image' => null, 'title' => 'Serienbegleitung', 'text' => 'Abstimmung mit Herstellern bis zum ersten Serienteil.', 'link_label' => '', 'link' => ''],
            ['icon' => 'recycle', 'image' => null, 'title' => 'Kreislauf', 'text' => 'Reparierbarkeit und Materialwahl von Anfang an mitgedacht.', 'link_label' => '', 'link' => ''],
            ['icon' => 'users-three', 'image' => null, 'title' => 'Workshops', 'text' => 'Ein Tag mit Ihrem Team: Ideen sortieren, Richtung festlegen.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('steps', ['variant' => 'process', 'eyebrow' => 'Ablauf', 'title' => 'So arbeiten wir zusammen', 'intro' => '', 'items' => [
            ['meta' => 'Woche 1', 'title' => 'Verstehen', 'text' => 'Gespräche, Beobachtung, gemeinsames Ziel.', 'icon' => ''],
            ['meta' => 'Woche 2–4', 'title' => 'Entwerfen', 'text' => 'Drei Richtungen, eine begründete Empfehlung.', 'icon' => ''],
            ['meta' => 'Woche 5–8', 'title' => 'Bauen', 'text' => 'Prototypen, Tests, Korrekturen.', 'icon' => ''],
            ['meta' => 'danach', 'title' => 'Begleiten', 'text' => 'Serienanlauf, Qualität, Weiterentwicklung.', 'icon' => ''],
        ]], ['background' => 'muted']),
        $B('pricing', ['variant' => 'cards', 'eyebrow' => 'Angebote', 'title' => 'Drei Wege, mit uns zu starten', 'intro' => 'Beispielpreise eines fiktiven Studios – zzgl. MwSt.', 'items' => [
            ['name' => 'Sprint', 'badge' => '', 'price' => '4.800 €', 'period' => 'pauschal, 1 Woche', 'text' => 'Ein fokussierter Workshop mit Ergebnisdokument.', 'features' => "Ein Workshop-Tag vor Ort\nZusammenfassung der Erkenntnisse\nEmpfehlung für die nächsten Schritte", 'highlight' => false, 'button_label' => 'Sprint anfragen', 'button_link' => $link('/kontakt')],
            ['name' => 'Entwurf', 'badge' => 'Beliebt', 'price' => 'ab 24.000 €', 'period' => '6–8 Wochen', 'text' => 'Vom Ziel bis zum getesteten Prototyp.', 'features' => "Strategie und Nutzerforschung\nDrei Entwurfsrichtungen\nPrototyp im Maßstab 1:1\nÜbergabe an die Konstruktion", 'highlight' => true, 'button_label' => 'Entwurf anfragen', 'button_link' => $link('/kontakt')],
            ['name' => 'Partnerschaft', 'badge' => '', 'price' => 'nach Aufwand', 'period' => 'laufend', 'text' => 'Wir begleiten Ihre Produktfamilie dauerhaft.', 'features' => "Feste Ansprechperson\nMonatliche Abstimmung\nPflege von Gestaltungsregeln", 'highlight' => false, 'button_label' => 'Gespräch vereinbaren', 'button_link' => $link('/kontakt')],
        ], 'rows' => [], 'note' => 'Alle Preise sind erfundene Beispiele.']),
        $B('faq', ['variant' => 'split', 'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen', 'intro' => 'Kurz beantwortet.', 'items' => [
            ['q' => 'Arbeiten Sie auch für kleine Unternehmen?', 'a' => '<p>Ja. Der „Sprint“ ist genau dafür gedacht – ein überschaubarer Einstieg mit klarem Ergebnis.</p>'],
            ['q' => 'Wem gehören die Entwürfe?', 'a' => '<p>Die Nutzungsrechte gehen mit der Schlusszahlung vollständig auf Sie über.</p>'],
            ['q' => 'Übernehmen Sie auch die Konstruktion?', 'a' => '<p>Wir arbeiten eng mit Konstruktionsbüros zusammen und begleiten die Übergabe – die Konstruktion selbst übernehmen Partner.</p>'],
            ['q' => 'Wie schnell können Sie starten?', 'a' => '<p>In der Regel innerhalb von drei bis vier Wochen nach dem Erstgespräch.</p>'],
        ]]),
        $B('cta', ['variant' => 'box', 'eyebrow' => 'Unverbindlich', 'title' => 'Welcher Weg passt zu Ihnen?', 'text' => 'Erzählen Sie uns kurz von Ihrem Vorhaben – wir melden uns mit einer Empfehlung.',
            'button_label' => 'Anfrage senden', 'button_link' => $link('/kontakt'), 'button2_label' => 'Anrufen', 'button2_link' => 'phone', 'image' => null], ['background' => 'white']),
    ]);

    // ---------------------------------------------------------------- Projekte (Datenliste mit Detailseite, Galerie)
    if ($proj !== '') {
        $tpl = $page(['slug' => '_vorlage-nordlicht-projekte', 'title' => 'Projekte (Nordlicht) – Detailseite', 'type' => 'template', 'template_for' => $proj, 'menu' => 0, 'parent_id' => null], [
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.kunde", "$proj.jahr"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => 'Alle Projekte', 'back_link' => $link('/projekte')], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.bild"], 'layout' => 'image', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kategorie", "$proj.leistungen", "$proj.jahr", "$proj.kunde"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext", "$proj.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => 'Weiter entdecken', 'title' => 'Weitere Projekte', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.kunde"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '4:3', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
            $cta,
        ]);
        $t = Tables::find($proj);
        $t['settings']['detail_page_id'] = $tpl;
        app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $page(['slug' => 'projekte', 'title' => 'Projekte', 'og_image' => $O(0), 'meta_description' => 'Ausgewählte (erfundene) Projekte des Studio Nordlicht – als Karten, Tabelle und Galerie.'], [
        $B('hero', ['variant' => 'statement', 'eyebrow' => 'Projekte', 'title' => 'Weniger Knöpfe. Mehr *Klarheit*.', 'text' => 'Sechs Entwürfe für erfundene Auftraggeber – jeder mit eigener Detailseite aus einer Datentabelle.',
            'button_label' => 'Zur Übersicht', 'button_link' => '#tabelle', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => $W(2), 'ratio' => '21:9']),
        $B('data_list', ['eyebrow' => 'Alle Projekte', 'title' => 'Karten', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.kunde", "$proj.kurztext"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '4:3', 'limit' => 6,
            'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''], ['background' => 'muted']),
        $B('data_list', ['eyebrow' => 'Übersicht', 'title' => 'Als Tabelle', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.kunde", "$proj.kategorie", "$proj.jahr"], 'layout' => 'table', 'columns' => '3', 'ratio' => '4:3', 'limit' => 0,
            'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''], ['anchor' => 'tabelle']),
        $B('gallery', ['eyebrow' => 'Werkstatt', 'title' => 'Skizzen, Modelle, Stimmungen', 'intro' => 'Ein Klick öffnet die Lightbox. Alle Bilder sind automatisch erzeugt.', 'source' => 'collection', 'collection' => $img['collection'],
            'layout' => 'grid', 'columns' => '4', 'ratio' => '1:1', 'captions' => true, 'lightbox' => true], ['background' => 'muted']),
        $cta,
    ]);

    // ---------------------------------------------------------------- Studio (Team, Geschichte, Kennzahlen)
    $page(['slug' => 'studio', 'title' => 'Studio', 'meta_description' => 'Team, Geschichte und Haltung des fiktiven Studio Nordlicht.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Studio', 'title' => 'Sieben Menschen, *eine* Haltung.', 'text' => 'Wir sind Gestalterinnen, Ingenieure und Forschende – und wir glauben, dass gute Produkte leise sind.',
            'button_label' => 'Team kennenlernen', 'button_link' => '#team', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'stat_value' => '2016', 'stat_label' => 'gegründet (fiktiv)', 'image' => $T(3), 'ratio' => '4:5']),
        $B('team', ['variant' => 'grid', 'eyebrow' => 'Team (erfundene Personen)', 'title' => 'Die Menschen hinter den Entwürfen', 'intro' => 'Ohne Fotos: Porträts erscheinen als Initialen auf einer Farbfläche.', 'size' => 's', 'items' => [
            ['name' => 'Mara Beispiel', 'role' => 'Gründerin, Produktdesign', 'text' => 'Entwirft seit zwanzig Jahren Dinge für den Alltag.', 'image' => null, 'email' => 'mara@example.com', 'link_label' => '', 'link' => ''],
            ['name' => 'Jonas Muster', 'role' => 'Industriedesign', 'text' => 'Denkt in Schrauben, Toleranzen und Ersatzteilen.', 'image' => null, 'email' => '', 'link_label' => '', 'link' => ''],
            ['name' => 'Lea Probe', 'role' => 'Nutzerforschung', 'text' => 'Beobachtet, fragt nach, fasst zusammen.', 'image' => null, 'email' => '', 'link_label' => '', 'link' => ''],
            ['name' => 'Tarek Beispiel', 'role' => 'Prototypen & Werkstatt', 'text' => 'Baut Modelle schneller, als andere skizzieren.', 'image' => null, 'email' => '', 'link_label' => '', 'link' => ''],
        ]], ['anchor' => 'team']),
        $B('dials', ['eyebrow' => 'Kennzahlen mit Skala', 'title' => 'Wie wir arbeiten – gemessen', 'intro' => 'Beispielwerte eines fiktiven Studios.', 'items' => [
            ['value' => '92 %', 'unit' => '', 'label' => 'Weiterempfehlung', 'text' => 'Beispielumfrage', 'level' => '', 'number' => '', 'max' => ''],
            ['value' => '48', 'unit' => 'h', 'label' => 'Antwortzeit', 'text' => 'höchstens', 'level' => '', 'number' => '48', 'max' => '72'],
            ['value' => '10', 'unit' => 'J.', 'label' => 'Ersatzteile', 'text' => 'Zusage je Produkt (fiktiv)', 'level' => '', 'number' => '10', 'max' => '12'],
        ], 'size' => 'm', 'style' => 'arc', 'animate' => true], ['background' => 'dark']),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Geschichte', 'title' => 'Zehn Jahre in vier Schritten', 'intro' => '', 'items' => [
            ['meta' => '2016', 'title' => 'Gründung', 'text' => 'Zwei Personen, eine Werkbank, der erste Auftrag: eine Leselampe.', 'icon' => ''],
            ['meta' => '2019', 'title' => 'Eigene Werkstatt', 'text' => 'Prototypen entstehen seitdem im Haus – schneller und günstiger.', 'icon' => ''],
            ['meta' => '2022', 'title' => 'Forschung im Team', 'text' => 'Nutzerforschung wird fester Bestandteil jedes Projekts.', 'icon' => ''],
            ['meta' => '2026', 'title' => 'Kreislauf als Standard', 'text' => 'Jedes neue Produkt ist reparierbar geplant.', 'icon' => ''],
        ]]),
        $B('media_text', ['variant' => 'auto', 'eyebrow' => 'Haltung', 'title' => 'Gute Produkte sind *leise*', 'text' => '<p>Sie erklären sich selbst, halten lange und lassen sich reparieren. Das klingt selbstverständlich – ist es aber selten.</p><p>Deshalb beginnen wir jedes Projekt mit derselben Frage: Was kann weg?</p>',
            'list' => "Wenige, gut erreichbare Bedienelemente\nMaterialien, die altern dürfen\nErsatzteile statt Neukauf", 'button_label' => 'Leistungen', 'button_link' => $link('/leistungen'), 'button2_label' => '', 'button2_link' => '', 'image' => $O(1), 'ratio' => '4:3'], ['background' => 'muted']),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Stimmen (Beispiele)', 'title' => 'Was Auftraggeber sagen', 'items' => [
            ['text' => 'Klare Abläufe, ehrliche Einschätzungen und Entwürfe, die man sofort versteht.', 'name' => 'Sam Muster', 'role' => 'Geschäftsführung (fiktiv)', 'image' => null],
            ['text' => 'Die Prototypen haben uns Monate an Diskussion erspart.', 'name' => 'Jo Beispiel', 'role' => 'Entwicklung (fiktiv)', 'image' => null],
            ['text' => 'Endlich ein Studio, das Reparierbarkeit von sich aus anspricht.', 'name' => 'Alex Probe', 'role' => 'Einkauf (fiktiv)', 'image' => null],
        ]]),
        $cta,
    ]);

    // ---------------------------------------------------------------- Journal (Artikel, Video, Downloads)
    $page(['slug' => 'journal', 'title' => 'Journal', 'og_image' => $W(1), 'meta_description' => 'Ein Beispielartikel des fiktiven Studio Nordlicht mit Inhaltsverzeichnis, Video und Download.'], [
        $compact('Journal', 'Was kann *weg*?', 'Ein Beispielartikel über das Weglassen – mit Inhaltsverzeichnis, Zitat, Video und Download.'),
        $B('richtext', ['variant' => 'article', 'eyebrow' => 'Beitrag', 'title' => 'Die Kunst, Knöpfe zu streichen', 'intro' => '', 'meta' => 'Mara Beispiel · Beispieltext', 'toc' => true, 'dropcap' => false, 'text' =>
            '<p class="t-lead">Jedes Bedienelement ist ein Versprechen – und eine Frage, die sich Nutzerinnen stellen müssen. Dieser Text ist frei erfunden und zeigt die Gestaltung eines Artikels.</p>'
            . '<h2>Zählen, bevor man zeichnet</h2><p>Am Anfang jedes Projekts zählen wir: Wie viele Funktionen hat das Produkt, wie viele werden tatsächlich genutzt? Die Differenz ist oft überraschend groß.</p>'
            . '<blockquote><p>Ein Knopf, den niemand drückt, ist kein Merkmal, sondern ein Kostenfaktor.</p></blockquote>'
            . '<h2>Bündeln statt verstecken</h2><p>Weglassen heißt nicht, Funktionen in Untermenüs zu verbannen. Gute Produkte bündeln: ein Regler für Lautstärke und Ein/Aus, ein Lichtpunkt für den Zustand.</p><h3>Drei Fragen für jede Funktion</h3><ul><li>Wer braucht sie – und wie oft?</li><li>Lässt sie sich mit einer anderen zusammenlegen?</li><li>Merkt jemand, wenn sie fehlt?</li></ul>'
            . '<h2>Was bleibt</h2><p>Ein Produkt, das man benutzt, ohne darüber nachzudenken. <mark>Das ist das Ziel.</mark></p>']),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video „Mit Text daneben“', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – mit der Einwilligungs-Verwaltung abgestimmt.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W(0), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)'], ['background' => 'muted']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Zum Mitnehmen', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true, 'collection' => null,
            'files' => $pdf ? [['label' => 'Leistungsübersicht (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []]),
        $cta,
    ]);

    // ---------------------------------------------------------------- Kontakt
    $page(['slug' => 'kontakt', 'title' => 'Kontakt', 'meta_description' => 'Kontakt zum fiktiven Studio Nordlicht: Formular, Telefon, E-Mail, Öffnungszeiten und Karte.'], [
        $compact('Kontakt', 'Erzählen Sie uns von Ihrer *Idee*.', 'Wir antworten innerhalb von 48 Stunden – versprochen (fiktiv).'),
        $B('contact', ['eyebrow' => 'So erreichen Sie uns', 'title' => 'Kontakt', 'intro' => 'Angaben und Öffnungszeiten kommen zentral aus „Website“; „Jetzt geöffnet“ rechnet der Browser.', 'show_hours' => true, 'show_map' => true,
            'form_table' => $form, 'form_title' => 'Anfrage (Beispiel)', 'submit_label' => '', 'note' => 'Beispieladresse – frei erfunden. Absenden ist gefahrlos: Es ist eine Demo-Tabelle.'], ['anchor' => 'kontakt']),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'Vorab', 'title' => 'Gut zu wissen', 'intro' => '', 'items' => [
            ['q' => 'Kann ich einfach vorbeikommen?', 'a' => '<p>Gern – am besten mit kurzer Anmeldung, damit jemand Zeit hat.</p>'],
            ['q' => 'Was passiert mit meinen Angaben?', 'a' => '<p>Sie landen in einer Datentabelle dieser Website. Es werden keine Cookies gesetzt.</p>'],
        ]], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Baukasten: alle übrigen Varianten (Musterseite des Style-Editors)
    $page(['slug' => 'baukasten', 'title' => 'Baukasten', 'menu' => 0, 'noindex' => 1, 'meta_description' => 'Alle Blöcke und Varianten des Kits „modern“ auf einer Seite.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Baukasten', 'title' => 'Alle Blöcke auf *einer* Seite.', 'text' => 'Diese Seite ist die Musterseite des Style-Editors: Wählen Sie unter Verwaltung → Design eine Vorlage und sehen Sie, wie sich alles mitändert.',
            'button_label' => 'Zur Startseite', 'button_link' => $link('/') ?: '/', 'button2_label' => 'Kontakt', 'button2_link' => $link('/kontakt'), 'points' => "4 Farbvorlagen\n4 Navigationen\nHell und dunkel", 'stat_value' => '< 30 KB', 'stat_label' => 'CSS der Startseite', 'image' => $O(2), 'ratio' => '4:5']),
        $B('features', ['variant' => 'numbered', 'eyebrow' => 'Merkmale „Nummeriert“', 'title' => 'Was das Kit mitbringt', 'intro' => '', 'size' => 's', 'items' => [
            ['icon' => '', 'image' => null, 'title' => 'WCAG 2.2 AA', 'text' => 'Alle Vorlagen hell und dunkel geprüft.', 'link_label' => '', 'link' => ''],
            ['icon' => '', 'image' => null, 'title' => 'Leicht', 'text' => 'Nur das CSS, das die Seite braucht.', 'link_label' => '', 'link' => ''],
            ['icon' => '', 'image' => null, 'title' => 'Datensparsam', 'text' => 'Keine Cookies, keine externen Schriften.', 'link_label' => '', 'link' => ''],
            ['icon' => '', 'image' => null, 'title' => 'Einstellbar', 'text' => 'Farben, Schriften, Navigation, Fußbereich.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('features', ['variant' => 'icons', 'eyebrow' => 'Merkmale „Große Symbole“', 'title' => 'Ohne Rahmen', 'intro' => '', 'size' => 'm', 'items' => [
            ['icon' => 'lightning', 'image' => null, 'title' => 'Schnell', 'text' => 'Kein Framework, kein Tracking.', 'link_label' => '', 'link' => ''],
            ['icon' => 'shield-check', 'image' => null, 'title' => 'Sicher', 'text' => 'Content-Security-Policy ohne Inline-Skripte.', 'link_label' => '', 'link' => ''],
            ['icon' => 'leaf', 'image' => null, 'title' => 'Sparsam', 'text' => 'Bilder in AVIF und WebP, passende Größen.', 'link_label' => '', 'link' => ''],
        ]], ['background' => 'tint']),
        $B('media_text', ['variant' => 'split', 'eyebrow' => 'Text + Bild „Geteilt“', 'title' => 'Randlos, halb und halb', 'text' => '<p>Bild und Text teilen sich die volle Breite. Auf dem Handy steht das Bild über dem Text.</p>', 'list' => '', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'image' => $W(1), 'ratio' => '4:3']),
        $B('media_text', ['variant' => 'overlap', 'eyebrow' => 'Text + Bild „Überlappend“', 'title' => 'Karte über großem Bild', 'text' => '<p>Die Textkarte liegt über dem Bild – auf dem Handy darunter.</p>', 'list' => "Mit Häkchen-Liste\nUnd Buttons", 'button_label' => 'Mehr', 'button_link' => $link('/studio'), 'button2_label' => '', 'button2_link' => '', 'image' => $W(2), 'ratio' => '16:9']),
        $B('cards', ['variant' => 'overlay', 'eyebrow' => 'Karten „Text auf dem Bild“', 'title' => 'Stimmungen', 'intro' => '', 'size' => 'm', 'ratio' => '4:5', 'items' => [
            ['image' => $T(0), 'eyebrow' => 'Licht', 'title' => 'Polarlicht', 'text' => 'Verlauf in Grün und Blau.', 'link_label' => '', 'link' => $link('/projekte')],
            ['image' => $T(3), 'eyebrow' => 'Form', 'title' => 'Detail „Polar“', 'text' => 'Leuchte im Hochformat.', 'link_label' => '', 'link' => $link('/projekte')],
            ['image' => $T(1), 'eyebrow' => 'Nacht', 'title' => 'Grünes Band', 'text' => 'Ruhig und dunkel.', 'link_label' => '', 'link' => $link('/projekte')],
        ], 'more_label' => '', 'more_link' => ''], ['background' => 'muted']),
        $B('cards', ['variant' => 'horizontal', 'eyebrow' => 'Karten „Quer“', 'title' => 'Bild links, Text rechts', 'intro' => '', 'size' => 'l', 'ratio' => '4:3', 'items' => [
            ['image' => $O(4), 'eyebrow' => 'Klang', 'title' => 'Kopfhörer „Stille“', 'text' => 'Austauschbare Polster und Akku.', 'link_label' => 'Details', 'link' => $link('/projekte')],
            ['image' => $O(5), 'eyebrow' => 'Unterwegs', 'title' => 'Thermoflasche „Tide“', 'text' => 'Ein Deckel, drei Farben.', 'link_label' => 'Details', 'link' => $link('/projekte')],
        ], 'more_label' => 'Alle Projekte', 'more_link' => $link('/projekte')]),
        $B('stats', ['variant' => 'split', 'eyebrow' => 'Kennzahlen „Neben der Überschrift“', 'title' => 'Zahlen mit Kontext', 'intro' => 'Beispielwerte.', 'items' => [
            ['value' => '4', 'label' => 'Farbvorlagen', 'text' => 'hell und dunkel geprüft'],
            ['value' => '4', 'label' => 'Navigationen', 'text' => 'modern, klassisch, minimal, ausführlich'],
            ['value' => '19', 'label' => 'Blöcke', 'text' => 'plus Kern-Blöcke'],
        ]], ['background' => 'muted']),
        $B('tabs', ['variant' => 'top', 'eyebrow' => 'Reiter', 'title' => 'Umschalten statt scrollen', 'intro' => '', 'items' => [
            ['label' => 'Strategie', 'text' => '<p>Wir klären, was ein Produkt können muss – und was nicht.</p>', 'image' => $S(0)],
            ['label' => 'Design', 'text' => '<p>Form, Material und Bedienung als System.</p>', 'image' => $S(1)],
            ['label' => 'Prototypen', 'text' => '<p>Modelle im Maßstab 1:1 – zum Anfassen.</p>', 'image' => null],
        ]]),
        $B('pricing', ['variant' => 'table', 'eyebrow' => 'Angebote „Vergleichstabelle“', 'title' => 'Im Vergleich', 'intro' => '', 'items' => [
            ['name' => 'Sprint', 'badge' => '', 'price' => '4.800 €', 'period' => 'pauschal', 'text' => '', 'features' => '', 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => $link('/kontakt')],
            ['name' => 'Entwurf', 'badge' => 'Beliebt', 'price' => 'ab 24.000 €', 'period' => '6–8 Wochen', 'text' => '', 'features' => '', 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => $link('/kontakt')],
            ['name' => 'Partnerschaft', 'badge' => '', 'price' => 'nach Aufwand', 'period' => 'laufend', 'text' => '', 'features' => '', 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => $link('/kontakt')],
        ], 'rows' => [
            ['label' => 'Workshop vor Ort', 'values' => "ja\nja\nja"],
            ['label' => 'Nutzerforschung', 'values' => "nein\nja\nja"],
            ['label' => 'Prototypen', 'values' => "nein\n1–2\nnach Bedarf"],
            ['label' => 'Feste Ansprechperson', 'values' => "nein\nja\nja"],
        ], 'note' => 'Erfundene Beispielpreise, zzgl. MwSt.'], ['background' => 'muted']),
        $B('team', ['variant' => 'list', 'eyebrow' => 'Team „Liste“', 'title' => 'Kompakt', 'intro' => '', 'size' => 'm', 'items' => [
            ['name' => 'Mara Beispiel', 'role' => 'Gründerin', 'text' => '', 'image' => null, 'email' => 'mara@example.com', 'link_label' => '', 'link' => ''],
            ['name' => 'Jonas Muster', 'role' => 'Industriedesign', 'text' => '', 'image' => null, 'email' => 'jonas@example.com', 'link_label' => '', 'link' => ''],
            ['name' => 'Lea Probe', 'role' => 'Nutzerforschung', 'text' => '', 'image' => null, 'email' => 'lea@example.com', 'link_label' => '', 'link' => ''],
            ['name' => 'Tarek Beispiel', 'role' => 'Werkstatt', 'text' => '', 'image' => null, 'email' => 'tarek@example.com', 'link_label' => '', 'link' => ''],
        ]]),
        $B('logos', ['variant' => 'grid', 'eyebrow' => 'Logos „Raster“ (erfundene Namen)', 'title' => '', 'items' => array_map(fn($n) => ['name' => $n, 'link' => '', 'image' => null],
            ['Lichtwerk Beispiel', 'Klangkontor Muster', 'Fjordlabor', 'Kiel & Kante'])], ['background' => 'muted']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Folien mit Text', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $W(0), 'eyebrow' => 'Folie 1', 'title' => 'Polarlicht', 'text' => 'Abgedunkelt mit heller Schrift.', 'button_label' => 'Projekte', 'button_link' => $link('/projekte'), 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $W(3), 'eyebrow' => 'Folie 2', 'title' => 'Formstudie', 'text' => 'Ein Kasten in der Hintergrundfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
        ]]),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Karten, die sich stapeln', 'intro' => 'Nur bei genug Platz und ohne „Bewegung reduzieren“ – sonst eine ruhige Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Verstehen', 'text' => 'Wir klären Ziele und Rahmen.', 'image' => $O(0), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Entwerfen', 'text' => 'Drei Richtungen, eine Empfehlung.', 'image' => $O(2), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Bauen', 'text' => 'Prototypen, Tests, Serie.', 'image' => $O(3), 'link_label' => 'Kontakt', 'link' => $link('/kontakt')],
        ]], ['background' => 'muted']),
        $B('steps', ['variant' => 'numbers', 'eyebrow' => 'Ablauf „Nummeriert“', 'title' => 'In drei Schritten', 'intro' => '', 'items' => [
            ['meta' => '', 'title' => 'Anfragen', 'text' => 'Formular oder Anruf.', 'icon' => ''],
            ['meta' => '', 'title' => 'Kennenlernen', 'text' => '30 Minuten, kostenlos.', 'icon' => ''],
            ['meta' => '', 'title' => 'Starten', 'text' => 'Angebot, Termin, los.', 'icon' => ''],
        ]]),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort', 'location' => 'site', 'zoom' => '12', 'height' => 'm', 'route' => true], ['background' => 'muted']),
        $B('cta', ['variant' => 'big', 'eyebrow' => 'Handlungsaufruf „Großer Schriftzug“', 'title' => 'Bereit für *weniger*?', 'text' => '', 'button_label' => 'Kontakt', 'button_link' => $link('/kontakt'), 'button2_label' => '', 'button2_link' => '', 'image' => null], ['background' => 'pop']),
    ]);

    app()->settings->set('modern.demo_pages', json_encode($created));
    $log('Seiten angelegt: ' . count($created) . ($asSite ? ' (Studio als Startseite).' : ' (unter /nordlicht).'));
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“

/**
 * Musterseite „Hero-Varianten“ unter dem Baukasten (Musterseite des Style-Editors, design.php → 'sample'):
 * die neuen Einstiege Produkt, Typo und Kennzahlen untereinander, dazu Mosaik und Große Aussage zum Vergleich.
 * Nutzt nur die erzeugten Demo-Bilder (Schlagwort „modern-demo“). Vorhandene Musterseite wird ersetzt.
 * CLI: CMS_SITE=… php kits/modern/tools/demo.php --heroes
 */
function modern_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', (array) json_decode((string) setting('modern.demo_pages', '[]'), true));
    $root = $ids ? $db->fetch('SELECT id, path FROM pages WHERE slug = ? AND id IN (' . implode(',', $ids) . ') LIMIT 1', ['baukasten']) : null;
    if (!$root) { $log('Kein Baukasten vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll('SELECT id FROM pages WHERE parent_id = ? AND slug = ?', [(int) $root['id'], 'hero-varianten']) as $old) {
        $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
        $ids = array_values(array_diff($ids, [(int) $old['id']]));
    }
    Pages::rebuildPaths();
    // Demo-Bilder über den Dateinamen (modern_demo_images); Rückfall: irgendein Demo-Bild
    $any = array_map('intval', array_column($db->fetchAll("SELECT id FROM media WHERE tags LIKE ? AND mime LIKE 'image/%' ORDER BY id", ['%' . MODERN_DEMO_TAG . '%']), 'id'));
    $I = function (string $name, int $n = 0) use ($db, $any): ?int {
        $id = $db->fetchValue('SELECT id FROM media WHERE tags LIKE ? AND original_name = ? ORDER BY id DESC LIMIT 1', ['%' . MODERN_DEMO_TAG . '%', $name]);
        return $id ? (int) $id : ($any ? $any[$n % count($any)] : null);
    };
    $link = fn(string $p) => rtrim('/' . trim(dirname('/' . $root['path']), '/.'), '/') . $p;
    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + ['eyebrow' => '', 'text' => '', 'points' => '', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''],
        'tunes' => ['section' => $tunes + ['divider' => true]]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Baukasten · Hero-Varianten', 'title' => 'Sieben Einstiege, *ein* Block.',
            'text' => 'Der Block „Einstieg (Hero)“ hat sieben Varianten. Neu sind Produkt, Typo und Kennzahlen – darunter zum Vergleich Mosaik und Große Aussage. Auf echten Seiten steht nur ein Einstieg ganz oben.'],
            ['background' => 'muted', 'divider' => false]),
        $B('product', ['eyebrow' => 'Variante „Produkt“ · Beispiel', 'title' => 'Leuchte „Polar“. Tippen statt schalten.',
            'text' => 'Eine Tischleuchte ohne Schalter: Antippen des Schirms dimmt stufenlos. Frei erfundenes Produkt – Preis und Angaben sind Beispielwerte.',
            'button_label' => 'Vorbestellen', 'button_link' => $link('/kontakt'), 'button2_label' => 'Projekt ansehen', 'button2_link' => $link('/projekte'),
            'points' => "Ersatzteile für 10 Jahre\nVersand klimaneutral (Beispiel)",
            'image' => $I('nordlicht-entwurf-lamp.jpg', 5), 'image2' => $I('nordlicht-entwurf-hoch-lamp.jpg', 11), 'image3' => $I('nordlicht-form-02.jpg', 14),
            'badge' => 'Neu', 'price' => '249 €', 'price_note' => 'Beispielpreis', 'spec_title' => 'Leuchte „Polar“ – Tischleuchte',
            'specs' => "Material: Aluminium, recycelt\nLichtfarbe: 2.200–3.000 K\nLeistung: 8 W\nDimmbar: stufenlos, per Berührung\nHöhe: 46 cm\nGewicht: 1,2 kg\nErsatzteile: 10 Jahre\nFarben: Tannengrün, Sand, Graphit"]),
        $B('marquee', ['eyebrow' => 'Variante „Typo“', 'title' => 'Weniger Teile. *Mehr* Haltung.',
            'text' => 'Ohne Bild, dafür mit Riesenschrift und einer ruhigen Laufzeile. Sie hält per Schaltfläche, Maus oder Tastatur – und steht bei „Bewegung reduzieren“ still.',
            'button_label' => 'Studio kennenlernen', 'button_link' => $link('/studio'), 'button2_label' => 'Kontakt', 'button2_link' => $link('/kontakt'),
            'marquee' => 'Strategie · Produktdesign · Prototypen · Nutzerforschung · Serienbegleitung · Reparierbarkeit']),
        $B('figures', ['eyebrow' => 'Variante „Kennzahlen“ · Beispielwerte', 'title' => 'Zahlen, die *tragen*.',
            'text' => 'Die Aussage links, die Belege rechts: drei bis vier Kennzahlen als farbige Kacheln mit Rundinstrument. Alle Werte hier sind frei erfunden.',
            'button_label' => 'Alle Projekte', 'button_link' => $link('/projekte'), 'button2_label' => '', 'button2_link' => '',
            'figures_style' => 'arc', 'items' => [
                ['value' => '92 %', 'unit' => '', 'label' => 'Produkte reparierbar', 'text' => 'Beispielwert, Projekte 2020–2026', 'level' => '', 'max' => ''],
                ['value' => '140', 'unit' => '', 'label' => 'Entwürfe seit 2016', 'text' => 'fiktiv', 'level' => '70', 'max' => ''],
                ['value' => '4,8', 'unit' => '', 'label' => 'Zufriedenheit', 'text' => 'von 5 – Beispielumfrage', 'level' => '', 'max' => '5'],
                ['value' => '12', 'unit' => 'J.', 'label' => 'Ersatzteil-Zusage', 'text' => 'Durchschnitt, Beispiel', 'level' => '', 'max' => '15'],
            ]], ['background' => 'muted']),
        $B('mosaic', ['eyebrow' => 'Zum Vergleich: „Mosaik“', 'title' => 'Drei Bilder, eine Zahl.',
            'text' => 'Text neben einem Bildraster mit Kennzahl-Kachel in Blockfarbe.',
            'stat_value' => '140+', 'stat_label' => 'Entwürfe seit 2016 (fiktiv)',
            'image' => $I('nordlicht-aurora-hoch-01.jpg', 3), 'image2' => $I('nordlicht-entwurf-speaker.jpg', 6), 'image3' => $I('nordlicht-aurora-01.jpg', 0)]),
        $B('statement', ['eyebrow' => 'Zum Vergleich: „Große Aussage“', 'title' => 'Dinge, die man *gern* benutzt.',
            'text' => 'Schriftzug über die volle Breite, darunter Text und Buttons, optional ein Bild im Breitbild.',
            'button_label' => 'Zum Baukasten', 'button_link' => '/' . $root['path'], 'image' => $I('nordlicht-aurora-02.jpg', 1), 'ratio' => '21:9'], ['background' => 'muted']),
    ];
    $id = Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 1, 'status' => 'published', 'menu' => 0, 'noindex' => 1,
        'meta_description' => 'Alle Varianten des Einstiegs (Hero) im Kit „modern“: Produkt, Typo, Kennzahlen – dazu Mosaik und Große Aussage zum Vergleich.'],
        Pages::sanitizeBlocks($blocks));
    app()->settings->set('modern.demo_pages', json_encode(array_values(array_merge($ids, [$id]))));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/' . $root['path'] . '/hero-varianten).');
    return true;
}
