<?php
/**
 * Demo-Inhalte des Kits „foto“: die fiktive Fotografin „Mara Beispiel (Demo)“ mit Startseite, Seitenbaum „Arbeiten“
 * (drei Serien: Porträt, Reportage, Landschaft), „Über mich“ und „Kontakt“.
 *
 * Bilder: Platzhalter, die hier mit PHP GD erzeugt werden (Verläufe, Hügel, Dünen, Meer, Fassaden, Silhouetten mit Filmkorn) –
 * keine Fotos von Personen, keine fremden Bildrechte. In der Mediathek als „Platzhalter“ gekennzeichnet (Titel, Alt-Text,
 * Bildnachweis „Platzhalter, automatisch erzeugt“), Schlagwort „foto-demo“, Sammlungen je Serie. Ersetzen Sie sie durch
 * eigene Fotos (Mediathek → Datei ersetzen: Verwendungen bleiben erhalten).
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/foto/tools/demo.php [--force|--remove]
 */
declare(strict_types=1);

use Core\Media;
use Core\Pages;

const FOTO_DEMO_TAG = 'foto-demo';
const FOTO_DEMO_CREDIT = 'Platzhalter, automatisch erzeugt (Kit „foto“) – frei verwendbar';

/** Demo anlegen. $force: vorhandene Demo-Seiten vorher entfernen. */
function foto_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE slug = 'arbeiten' AND parent_id IS NULL AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Die Demo ist schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    foto_demo_remove($log);
    $img = foto_demo_images($log);
    foto_demo_pages($img, $log);
    \Core\PageCache::clear();
    $log('Fertig: Startseite, „Arbeiten“ mit drei Serien, „Über mich“ und „Kontakt“ mit ' . count($img['all']) . ' Platzhalterbildern.');
    return true;
}

/** Demo-Seiten (außer Impressum/Datenschutz) und Demo-Medien entfernen */
function foto_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $slugs = ['start', 'arbeiten', 'ueber-mich', 'kontakt'];
    $ids = [];
    foreach ($db->fetchAll("SELECT id FROM pages WHERE parent_id IS NULL AND type = 'page' AND slug IN ('" . implode("','", $slugs) . "')") as $r) {
        $ids[] = (int) $r['id'];
        foreach (Pages::descendantIds((int) $r['id']) as $c) $ids[] = (int) $c;
    }
    foreach (array_unique($ids) as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . FOTO_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query("DELETE FROM media_collections WHERE name LIKE '% (Demo)' AND description LIKE '%„foto“%'");
    if ($ids) $log('Vorhandene Demo entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Platzhalterbilder (GD)

/**
 * @return array{all: list<int>, land: list<int>, portrait: list<int>, report: list<int>, col: array<string, int>}
 */
function foto_demo_images(callable $log): array
{
    $out = ['all' => [], 'land' => [], 'portrait' => [], 'report' => [], 'col' => []];
    if (!function_exists('imagecreatetruecolor')) {
        $log('GD fehlt – Demo ohne Bilder.');
        return $out;
    }
    $desc = 'Platzhalterbilder des Kits „foto“ – automatisch erzeugt, frei verwendbar, ohne Personen. Durch eigene Fotos ersetzen.';
    $cols = [
        'land' => Media::createCollection('Landschaft (Demo)', $desc),
        'portrait' => Media::createCollection('Porträt (Demo)', $desc),
        'report' => Media::createCollection('Reportage (Demo)', $desc),
    ];
    $out['col'] = $cols;
    // [Gruppe, Motiv, Breite, Höhe, Titel, Alt-Text, Farbvariante]
    $plan = [
        ['land', 'hills', 2000, 1333, 'Nebel über Hügeln', 'Hügelketten im Morgennebel, die nach hinten heller werden', 0],
        ['land', 'dunes', 2000, 1333, 'Dünen am Abend', 'Sanfte Dünen in warmem Abendlicht mit langen Schatten', 1],
        ['land', 'sea', 2000, 1333, 'Horizont', 'Ruhiges Meer mit tief stehender Sonne und Spiegelung', 2],
        ['land', 'hills', 1400, 1750, 'Tal im Blau', 'Hochformat: Bergrücken im blauen Dunst', 3],
        ['land', 'sea', 2400, 1200, 'Weite', 'Panorama: Wasserfläche unter hellem, wolkenlosem Himmel', 4],
        ['land', 'hills', 2000, 1333, 'Erste Stunde', 'Hügel im ersten Licht, rosa Himmel', 5],
        ['portrait', 'portrait', 1400, 1750, 'Studie I', 'Schattenriss von Kopf und Schultern vor hellem Hintergrund (Platzhalter statt Porträt)', 0],
        ['portrait', 'portrait', 1400, 1750, 'Studie II', 'Schattenriss im Gegenlicht vor warmem Hintergrund (Platzhalter statt Porträt)', 1],
        ['portrait', 'portrait', 1400, 1750, 'Studie III', 'Schattenriss vor dunkelgrünem Hintergrund mit Streiflicht (Platzhalter statt Porträt)', 2],
        ['portrait', 'portrait', 1400, 1750, 'Studie IV', 'Schattenriss vor grauem Hintergrund, Licht von links (Platzhalter statt Porträt)', 3],
        ['portrait', 'portrait', 1400, 1750, 'Studie V', 'Schattenriss vor blassblauem Hintergrund (Platzhalter statt Porträt)', 4],
        ['portrait', 'portrait', 1400, 1750, 'Studie VI', 'Schattenriss vor sandfarbenem Hintergrund (Platzhalter statt Porträt)', 5],
        ['report', 'facade', 2000, 1333, 'Fassade, Mittag', 'Schwarzweiß: Hausfassade mit Fensterraster und harten Schatten', 0],
        ['report', 'stairs', 1400, 1750, 'Treppe', 'Schwarzweiß: Treppenstufen im Streiflicht', 1],
        ['report', 'crossing', 2000, 1333, 'Übergang', 'Schwarzweiß: Zebrastreifen von oben mit langen Schatten', 2],
        ['report', 'arches', 2000, 1333, 'Arkaden', 'Schwarzweiß: Bogengang mit Licht und Schatten', 3],
        ['report', 'facade', 1400, 1750, 'Hof', 'Schwarzweiß: Innenhof, Blick nach oben auf Fenster', 4],
        ['report', 'beam', 2000, 1333, 'Lichtschacht', 'Schwarzweiß: Lichtstrahl fällt in einen dunklen Raum', 5],
    ];
    foreach ($plan as $k => [$group, $motif, $w, $h, $title, $alt, $variant]) {
        $file = tempnam(sys_get_temp_dir(), 'fd') . '.jpg';
        foto_demo_draw($file, $motif, $w, $h, $variant, 7300 + $k);
        [$m, $err] = Media::import($file, sprintf('foto-demo-%s-%02d.jpg', $group, $k + 1), 'Platzhalter: ' . $alt,
            ['title' => $title . ' (Platzhalter)', 'tags' => FOTO_DEMO_TAG, 'collection' => $cols[$group], 'credit' => FOTO_DEMO_CREDIT]);
        @unlink($file);
        if ($err) { $log($title . ': ' . $err); continue; }
        $id = (int) $m['id'];
        $out['all'][] = $id;
        $out[$group][] = $id;
    }
    // Platzhalter-Videos (nur mit ffmpeg): langsame Kamerafahrt über ein erzeugtes Motiv, stumm, 6 Sekunden
    $out['video'] = [];
    if (\Core\Ffmpeg::available()) {
        foreach ([['land', 'hills', 0, 'Nebel, bewegt', 'Kamerafahrt über Hügelketten im Morgennebel'], ['report', 'beam', 5, 'Lichtschacht, bewegt', 'Schwarzweiß: Lichtstrahl in einem dunklen Raum, langsame Fahrt']] as $k => [$group, $motif, $variant, $title, $alt]) {
            $jpg = tempnam(sys_get_temp_dir(), 'fd') . '.jpg';
            $mp4 = tempnam(sys_get_temp_dir(), 'fd') . '.mp4';
            foto_demo_draw($jpg, $motif, 2400, 1350, $variant, 7900 + $k);
            $cmd = escapeshellarg(\Core\Ffmpeg::bin('ffmpeg')) . ' -y -loglevel error -loop 1 -i ' . escapeshellarg($jpg)
                . ' -vf ' . escapeshellarg("zoompan=z='min(zoom+0.0009,1.14)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=150:s=1280x720:fps=25,format=yuv420p")
                . ' -t 6 -c:v libx264 -crf 30 -preset veryfast -movflags +faststart -an ' . escapeshellarg($mp4) . ' 2>&1';
            exec($cmd, $o, $rc);
            @unlink($jpg);
            if ($rc !== 0 || !is_file($mp4) || filesize($mp4) < 1000) { $log('Video ' . $title . ': ffmpeg fehlgeschlagen.'); @unlink($mp4); continue; }
            [$m, $err] = Media::import($mp4, sprintf('foto-demo-video-%02d.mp4', $k + 1), 'Platzhalter: ' . $alt,
                ['title' => $title . ' (Platzhalter)', 'tags' => FOTO_DEMO_TAG, 'collection' => $cols[$group], 'credit' => FOTO_DEMO_CREDIT]);
            @unlink($mp4);
            if ($err || !$m) { $log($title . ': ' . $err); continue; }
            try { \Core\VideoThumbs::generate($m, true); } catch (\Throwable) {}   // Vorschaubild sofort (sonst nach der Antwort)
            $out['video'][$group] = (int) $m['id'];
            $out['all'][] = (int) $m['id'];
        }
    }
    $log(count($out['all']) . ' Platzhalterbilder und -videos erzeugt.');
    return $out;
}

/** Farbe zwischen a und b (t = 0 … 1) */
function foto_demo_mix(array $a, array $b, float $t): array
{
    $t = max(0.0, min(1.0, $t));
    return [(int) round($a[0] + ($b[0] - $a[0]) * $t), (int) round($a[1] + ($b[1] - $a[1]) * $t), (int) round($a[2] + ($b[2] - $a[2]) * $t)];
}

/**
 * Ein Platzhalterbild: Motiv in kleiner Auflösung zeichnen (weiche Kanten nach dem Hochskalieren, wie Unschärfe),
 * dann Vignette und Filmkorn in voller Größe.
 */
function foto_demo_draw(string $file, string $motif, int $W, int $H, int $variant, int $seed): void
{
    mt_srand($seed);
    $w = (int) round($W / 2.5);
    $h = (int) round($H / 2.5);
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    $col = fn(array $c, int $a = 0) => imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $a);
    $vgrad = function (array $top, array $bottom, int $y0 = 0, ?int $y1 = null) use ($im, $w, $h, $col): void {
        $y1 ??= $h;
        for ($y = $y0; $y < $y1; $y++) imageline($im, 0, $y, $w, $y, $col(foto_demo_mix($top, $bottom, ($y - $y0) / max(1, $y1 - $y0))));
    };
    $glow = function (float $cx, float $cy, float $r, array $c, int $steps = 28, int $minA = 70) use ($im, $col): void {
        for ($k = 0; $k < $steps; $k++) {
            $rr = (int) ($r * (1 - $k / $steps));
            imagefilledellipse($im, (int) $cx, (int) $cy, $rr * 2, $rr * 2, $col($c, max($minA, 126 - (int) ($k * 2.2))));
        }
    };
    $ridge = function (float $base, float $amp, float $freq, array $c, float $phase, int $a = 0) use ($im, $w, $h, $col): void {
        $poly = [];
        for ($x = 0; $x <= $w + 8; $x += 4) {
            $y = $base + sin($x / $w * $freq * 6.283 + $phase) * $amp + sin($x / $w * $freq * 15.7 + $phase * 2.3) * $amp * .35 + sin($x / $w * $freq * 41 + $phase) * $amp * .08;
            $poly[] = $x; $poly[] = (int) $y;
        }
        array_push($poly, $w + 8, $h, 0, $h);
        imagefilledpolygon($im, $poly, $col($c, $a));
    };
    $mono = false;
    switch ($motif) {
        case 'hills':
            $pal = [
                3 => [[178, 196, 214], [226, 233, 240], [38, 58, 84]],    // Blau
                5 => [[236, 196, 196], [250, 226, 210], [70, 62, 84]],    // Rosa
            ][$variant] ?? [[214, 222, 230], [247, 236, 228], [72, 86, 104]];   // Nebel blau-rosa
            [$sky, $horizon, $near] = $pal;
            $vgrad($sky, $horizon, 0, (int) ($h * .62));
            $vgrad($horizon, $horizon, (int) ($h * .62));
            $glow($w * .68, $h * .42, $w * .22, [255, 246, 232], 26, 60);
            for ($k = 0; $k < 6; $k++) {
                $t = $k / 5;
                $ridge($h * (.42 + $t * .4), $h * (.07 - $t * .02), 1.2 + $k * .35, foto_demo_mix(foto_demo_mix($horizon, $near, .25), $near, $t), mt_rand(0, 60) / 10);
                imagefilledrectangle($im, 0, 0, $w, $h, $col($horizon, 118 - $k * 2));   // Dunst zwischen den Ketten
            }
            break;
        case 'dunes':
            $vgrad([233, 196, 150], [250, 226, 190], 0, (int) ($h * .45));
            $glow($w * .2, $h * .3, $w * .18, [255, 238, 205], 24, 55);
            for ($k = 0; $k < 5; $k++) {
                $t = $k / 4;
                $base = $h * (.4 + $t * .45);
                $ridge($base, $h * .06, .7 + $k * .25, foto_demo_mix([214, 150, 96], [160, 92, 52], $t), mt_rand(0, 60) / 10);
                $ridge($base + $h * .05, $h * .05, .7 + $k * .25, foto_demo_mix([170, 104, 62], [96, 52, 30], $t), mt_rand(0, 60) / 10, 40);
            }
            break;
        case 'sea':
            $sky = $variant === 4 ? [[196, 214, 226], [236, 240, 240]] : [[120, 142, 168], [244, 214, 184]];
            $hz = (int) ($h * ($variant === 4 ? .48 : .56));
            $vgrad($sky[0], $sky[1], 0, $hz);
            $vgrad(foto_demo_mix($sky[1], [60, 80, 100], .45), [34, 48, 64], $hz, $h);
            if ($variant !== 4) {
                $glow($w * .5, $hz - $h * .04, $w * .1, [255, 240, 214], 22, 40);
                imagefilledellipse($im, (int) ($w * .5), (int) ($hz - $h * .04), (int) ($w * .055), (int) ($w * .055), $col([255, 244, 226]));
                for ($y = $hz + 2; $y < $h; $y += 3) {
                    $len = (int) ($w * .04 * (1 + ($y - $hz) / $h * 4) * (mt_rand(40, 100) / 100));
                    imageline($im, (int) ($w * .5 - $len), $y, (int) ($w * .5 + $len), $y, $col([255, 226, 190], 60 + (int) (60 * ($y - $hz) / ($h - $hz))));
                }
            }
            for ($y = $hz + 3; $y < $h; $y += mt_rand(3, 7)) imageline($im, 0, $y, $w, $y, $col([255, 255, 255], 120));
            imageline($im, 0, $hz, $w, $hz, $col([255, 255, 255], 90));
            break;
        case 'portrait':
            $bg = [[236, 232, 226], [226, 196, 164], [44, 70, 60], [176, 176, 172], [206, 222, 232], [222, 206, 178]][$variant] ?? [236, 232, 226];
            $dark = $variant === 2;
            $vgrad(foto_demo_mix($bg, [255, 255, 255], .15), foto_demo_mix($bg, [0, 0, 0], .18));
            $glow($w * ($variant === 3 ? .3 : .55), $h * .35, $w * .55, foto_demo_mix($bg, [255, 255, 255], .45), 30, 70);
            $fig = $dark ? [18, 26, 22] : foto_demo_mix($bg, [20, 20, 22], .78);
            $cx = $w * (.5 + ($variant % 2 ? .04 : -.03));
            // Schultern und Kopf als weiche Formen (mehrere Ellipsen mit Alpha für weiche Kanten)
            for ($k = 6; $k >= 0; $k--) {
                $a = $k === 0 ? 0 : 70 + $k * 7;
                $g = $k * $w * .006;
                imagefilledellipse($im, (int) $cx, (int) ($h * 1.06), (int) ($w * 1.02 + $g * 2), (int) ($h * .56 + $g * 2), $col($fig, $a));
                imagefilledrectangle($im, (int) ($cx - $w * .065 - $g), (int) ($h * .5), (int) ($cx + $w * .065 + $g), (int) ($h * .8), $col($fig, $a));
                imagefilledellipse($im, (int) $cx, (int) ($h * .4), (int) ($w * .3 + $g * 2), (int) ($h * .3 + $g * 2), $col($fig, $a));
            }
            // Streiflicht am Rand
            imagesetthickness($im, max(1, (int) ($w * .006)));
            imagearc($im, (int) $cx, (int) ($h * .4), (int) ($w * .3), (int) ($h * .3), $variant === 3 ? 140 : 250, $variant === 3 ? 230 : 330, $col(foto_demo_mix($bg, [255, 255, 255], .6), 50));
            break;
        case 'facade':
        case 'stairs':
        case 'crossing':
        case 'arches':
        case 'beam':
            $mono = true;
            $vgrad([206, 206, 206], [120, 120, 120]);
            if ($motif === 'facade') {
                imagefilledrectangle($im, 0, 0, $w, $h, $col([188, 186, 182]));
                $cw = $w / 9; $ch = $h / 7;
                for ($gx = 0; $gx < 9; $gx++) for ($gy = 0; $gy < 7; $gy++) {
                    $x = (int) ($gx * $cw + $cw * .22); $y = (int) ($gy * $ch + $ch * .2);
                    imagefilledrectangle($im, $x, $y, (int) ($x + $cw * .56), (int) ($y + $ch * .62), $col(mt_rand(0, 5) ? [36, 36, 38] : [230, 230, 228]));
                    imagefilledrectangle($im, $x - 2, (int) ($y + $ch * .62), (int) ($x + $cw * .56 + 2), (int) ($y + $ch * .66), $col([240, 240, 238]));
                }
                imagefilledpolygon($im, [0, (int) ($h * .15), (int) ($w * .7), $h, 0, $h], $col([0, 0, 0], 50));
            } elseif ($motif === 'stairs') {
                $n = 14;
                for ($k = 0; $k < $n; $k++) {
                    $y = (int) ($h * $k / $n);
                    imagefilledrectangle($im, 0, $y, $w, (int) ($y + $h / $n * .55), $col([226, 224, 220]));
                    imagefilledrectangle($im, 0, (int) ($y + $h / $n * .55), $w, (int) ($y + $h / $n), $col([70, 70, 72]));
                }
                imagefilledpolygon($im, [(int) ($w * .55), 0, $w, 0, $w, $h, (int) ($w * .2), $h], $col([0, 0, 0], 45));
            } elseif ($motif === 'crossing') {
                imagefilledrectangle($im, 0, 0, $w, $h, $col([64, 64, 66]));
                for ($k = 0; $k < 8; $k++) {
                    $x = (int) ($w * (.08 + $k * .11));
                    imagefilledrectangle($im, $x, (int) ($h * .25), (int) ($x + $w * .06), (int) ($h * .75), $col([236, 236, 234]));
                }
                $fx = $w * .62;   // kleiner Schatten einer Figur (ohne Person)
                imagefilledpolygon($im, [(int) $fx, (int) ($h * .62), (int) ($fx + $w * .012), (int) ($h * .62), (int) ($fx + $w * .26), (int) ($h * .98), (int) ($fx + $w * .22), (int) ($h * .98)], $col([10, 10, 10], 20));
                imagefilledellipse($im, (int) ($fx + $w * .006), (int) ($h * .6), (int) ($w * .018), (int) ($w * .018), $col([20, 20, 20]));
            } elseif ($motif === 'arches') {
                imagefilledrectangle($im, 0, 0, $w, $h, $col([214, 212, 206]));
                for ($k = 0; $k < 5; $k++) {
                    $x = (int) ($w * (.06 + $k * .2));
                    $aw = (int) ($w * .13);
                    imagefilledrectangle($im, $x, (int) ($h * .38), $x + $aw, $h, $col([28, 28, 30]));
                    imagefilledellipse($im, (int) ($x + $aw / 2), (int) ($h * .38), $aw, (int) ($aw * .9), $col([28, 28, 30]));
                    imagefilledpolygon($im, [$x + $aw, $h, (int) ($x + $aw * 1.9), $h, (int) ($x + $aw * 1.3), (int) ($h * .7)], $col([240, 240, 236], 30));
                }
            } else {   // beam
                imagefilledrectangle($im, 0, 0, $w, $h, $col([22, 22, 24]));
                for ($k = 0; $k < 18; $k++) imagefilledpolygon($im, [(int) ($w * (.56 + $k * .003)), 0, (int) ($w * (.66 - $k * .002)), 0, (int) ($w * (.44 - $k * .004)), $h, (int) ($w * (.22 + $k * .006)), $h], $col([255, 255, 250], 118 - $k * 3));
                imagefilledellipse($im, (int) ($w * .34), (int) ($h * .95), (int) ($w * .4), (int) ($h * .12), $col([255, 255, 250], 60));
            }
            break;
    }
    // Hochskalieren (weiche Kanten), Filmkorn
    $big = imagescale($im, $W, $H, IMG_BICUBIC);
    unset($im);
    imagealphablending($big, true);
    $tile = imagecreatetruecolor(256, 256);
    for ($y = 0; $y < 256; $y++) for ($x = 0; $x < 256; $x++) { $g = mt_rand(0, 255); imagesetpixel($tile, $x, $y, imagecolorallocate($tile, $g, $g, $g)); }
    for ($y = 0; $y < $H; $y += 256) for ($x = 0; $x < $W; $x += 256) imagecopymerge($big, $tile, $x, $y, 0, 0, 256, 256, $mono ? 9 : 6);
    if ($mono) { imagefilter($big, IMG_FILTER_GRAYSCALE); imagefilter($big, IMG_FILTER_CONTRAST, -12); }
    imagejpeg($big, $file, 86);
}

// ------------------------------------------------------------------ Seiten

function foto_demo_pages(array $img, callable $log): void
{
    $L = $img['land']; $P = $img['portrait']; $R = $img['report'];
    $at = fn(array $list, int $i) => $list[$i % max(1, count($list))] ?? null;
    $items = fn(array $ids, array $captions = []) => array_map(fn($id, $k) => ['image' => $id, 'caption' => $captions[$k] ?? ''], $ids, array_keys($ids));
    $page = function (array $f, array $blocks): int {
        $blocks = array_map(fn($b) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $b['type'], 'data' => $b['data'] ?? [], 'tunes' => ['section' => $b['tunes'] ?? []]], $blocks);
        return Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    };
    $cta = ['type' => 'cta', 'tunes' => ['background' => 'muted'], 'data' => [
        'variant' => 'box', 'eyebrow' => '', 'title' => 'Ein Auftrag, eine Idee, eine Frage?',
        'text' => 'Schreiben Sie mir – ich melde mich innerhalb von zwei Werktagen.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '',
    ]];

    // Startseite: kaum Text – ein Bildstrom aus großen Momenten, darunter ein Bento, das beim Scrollen nachlädt.
    // Hinter den Bildern stecken Galerien (Sammlungen) und Seiten (Serien); Videos laufen stumm, solange sie zu sehen sind.
    $V = $img['video'] ?? [];
    $C = $img['col'];
    $t = fn(?int $id, array $o = []) => $o + ['image' => $id, 'size' => 'auto', 'open' => 'zoom', 'collection' => null, 'link' => '', 'tone' => 'none', 'title' => '', 'text' => '', 'place' => 'auto'];
    $home = $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => 0,
        'meta_description' => 'Demo-Website der fiktiven Fotografin Mara Beispiel: Porträt, Reportage und Landschaft.'], [
        ['type' => 'moments', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'stream', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'manual',
            'motion' => 'reveal', 'row_height' => 'm', 'labels' => 'hover', 'more' => 'all', 'batch' => 12, 'spy' => false, 'width' => 'full', 'tiles' => [
            $t($at($L, 0), ['size' => 'full', 'open' => 'gallery', 'collection' => $C['land'] ?? null, 'title' => 'Weite.', 'text' => 'Landschaften, 2023–2025']),
            $t($at($P, 1), ['size' => 'm', 'open' => 'page', 'link' => '@portraet']),
            $t($at($P, 4), ['size' => 's', 'open' => 'zoom']),
            $t(null, ['size' => 's', 'open' => 'page', 'link' => '@portraet', 'title' => 'Stille Gesichter.', 'text' => 'Sechs Begegnungen, eine Stunde, ein Licht.']),
            $t($V['land'] ?? $at($L, 2), ['size' => 'wide', 'place' => 'end', 'open' => 'zoom']),
            $t($at($R, 0), ['size' => 'l', 'place' => 'center', 'tone' => 'secondary', 'open' => 'gallery', 'collection' => $C['report'] ?? null, 'title' => 'Stadt am Mittag.', 'text' => 'Reportage']),
            $t(null, ['size' => 's', 'title' => 'Echte Orte. Wenig Worte.', 'text' => 'Alles beginnt mit einem Moment, der nicht gestellt ist.', 'open' => 'none']),
            $t($at($R, 1), ['size' => 's', 'open' => 'zoom']),
            $t($V['report'] ?? $at($R, 5), ['size' => 'm', 'tone' => 'dark', 'open' => 'zoom']),
        ]]],
        ['type' => 'moments', 'tunes' => ['spaceTop' => 'small'], 'data' => ['variant' => 'bento', 'eyebrow' => '', 'title' => 'Kontaktbogen', 'intro' => '', 'source' => 'manual',
            'motion' => 'rise', 'row_height' => 'm', 'labels' => 'hover', 'more' => 'scroll', 'batch' => 10, 'spy' => true, 'width' => 'full', 'tiles' => [
            $t($at($L, 1), ['size' => 'l', 'open' => 'gallery', 'collection' => $C['land'] ?? null, 'title' => 'Dünen.']),
            $t($at($P, 0), ['size' => 'tall', 'open' => 'page', 'link' => '@portraet']),
            $t($at($R, 2), ['size' => 's']),
            $t(null, ['size' => 's', 'tone' => 'accent', 'title' => 'Porträt.', 'text' => 'Zur Serie', 'open' => 'page', 'link' => '@portraet']),
            $t($at($R, 3), ['size' => 'wide', 'open' => 'page', 'link' => '@reportage', 'title' => 'Arkaden.']),
            $t($at($L, 3), ['size' => 'm']),
            $t($V['report'] ?? $at($R, 5), ['size' => 'm']),
            $t($at($P, 2), ['size' => 's']),
            $t($at($L, 4), ['size' => 'full', 'open' => 'page', 'link' => '@landschaft', 'title' => 'Weite.', 'text' => 'Zur Serie']),
            $t($at($P, 3), ['size' => 'm']),
            $t($at($R, 4), ['size' => 'tall']),
            $t($at($L, 5), ['size' => 'wide']),
            $t(null, ['size' => 'm', 'tone' => 'dark', 'title' => 'Ein Auftrag?', 'text' => 'Schreiben Sie mir.', 'open' => 'page', 'link' => '/kontakt']),
            $t($at($P, 5), ['size' => 's']),
            $t($at($R, 5), ['size' => 'wide']),
            $t($at($L, 2), ['size' => 'm']),
            $t($V['land'] ?? $at($L, 0), ['size' => 'l']),
            $t($at($P, 4), ['size' => 'tall']),
            $t($at($R, 1), ['size' => 's']),
            $t($at($R, 0), ['size' => 's']),
        ]]],
    ]);

    // Arbeiten + drei Serien
    $root = $page(['slug' => 'arbeiten', 'title' => 'Arbeiten', 'sort' => 1, 'meta_description' => 'Serien und Projekte (Demo): Porträt, Reportage, Landschaft.'], [
        ['type' => 'hero', 'tunes' => ['spaceBottom' => 'small'], 'data' => ['variant' => 'compact', 'eyebrow' => '', 'title' => 'Arbeiten', 'text' => 'Serien aus den letzten Jahren – freie Projekte und Aufträge.']],
        ['type' => 'series_index', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'list', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'children', 'parent' => null,
            'limit' => 0, 'ratio' => '4:5', 'columns' => '3', 'width' => '', 'show_meta' => true, 'items' => []]],
    ]);
    $page(['slug' => 'portraet', 'title' => 'Porträt', 'parent_id' => $root, 'sort' => 0, 'meta_description' => 'Serie „Stille Gesichter“ (Demo mit Platzhaltern).'], [
        ['type' => 'series_head', 'data' => ['variant' => 'side', 'eyebrow' => 'Serie', 'title' => 'Stille Gesichter', 'year' => '2025', 'category' => 'Porträt', 'place' => 'Musterstadt', 'client' => '',
            'statement' => '<p>Sechs Begegnungen, jeweils eine Stunde, ein Licht. Keine Anweisungen – nur Warten, bis das Gesicht zur Ruhe kommt. [Platzhalterbilder: Schattenrisse statt Porträts.]</p>',
            'cover' => $at($P, 1), 'ratio' => '4:5', 'back_label' => 'Alle Arbeiten', 'back_link' => '']],
        ['type' => 'photo_grid', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'grid', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'manual', 'collection' => null,
            'images' => $items($P, ['Studie I', 'Studie II', 'Studie III', 'Studie IV', 'Studie V', 'Studie VI']), 'columns' => '3', 'ratio' => '4:5', 'row_height' => 'm', 'width' => '', 'captions' => true, 'lightbox' => true]],
        ['type' => 'photo_text', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'right', 'eyebrow' => '', 'title' => 'Über die Serie',
            'text' => '<p>Porträts entstehen im Gespräch. Das Licht kommt von einem Fenster, der Hintergrund ist eine graue Wand. Was bleibt, ist der Blick. [Eigenen Text einsetzen.]</p>',
            'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '',
            'image' => $at($P, 3), 'caption' => 'Studie IV · Fensterlicht', 'ratio' => '4:5', 'size' => 'half', 'align' => 'center', 'lightbox' => true]],
    ]);
    $page(['slug' => 'reportage', 'title' => 'Reportage', 'parent_id' => $root, 'sort' => 1, 'meta_description' => 'Serie „Stadt am Mittag“ (Demo mit Platzhaltern).'], [
        ['type' => 'series_head', 'data' => ['variant' => 'text', 'eyebrow' => 'Serie', 'title' => 'Stadt am *Mittag*', 'year' => '2024', 'category' => 'Reportage', 'place' => 'Musterstadt', 'client' => '',
            'statement' => '<p>Wenn die Sonne am höchsten steht, wird die Stadt zu Geometrie: Fassaden, Treppen, Übergänge. Eine Woche, jeden Tag zwischen zwölf und eins.</p>',
            'cover' => $at($R, 0), 'ratio' => '3:2', 'back_label' => 'Alle Arbeiten', 'back_link' => '']],
        ['type' => 'photo_grid', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'strip', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'manual', 'collection' => null,
            'images' => $items($R, ['Fassade, Mittag', 'Treppe', 'Übergang', 'Arkaden', 'Hof', 'Lichtschacht']), 'columns' => 'auto', 'ratio' => '', 'row_height' => 'm', 'width' => 'full', 'captions' => true, 'lightbox' => true]],
        ['type' => 'quote', 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
            ['text' => 'Der Schatten ist genauso Motiv wie das, was ihn wirft.', 'name' => 'Mara Beispiel', 'role' => 'Notizbuch, 2024 (Demo)', 'image' => null]]]],
        ['type' => 'photo_grid', 'data' => ['variant' => 'masonry', 'eyebrow' => 'Kontaktbogen', 'title' => 'Alle Bilder der Woche', 'intro' => '', 'source' => 'collection', 'collection' => $img['col']['report'] ?? null,
            'images' => [], 'columns' => '3', 'ratio' => '', 'row_height' => 'm', 'width' => '', 'captions' => true, 'lightbox' => true]],
    ]);
    $page(['slug' => 'landschaft', 'title' => 'Landschaft', 'parent_id' => $root, 'sort' => 2, 'meta_description' => 'Serie „Weite“ (Demo mit Platzhaltern).'], [
        ['type' => 'series_head', 'data' => ['variant' => 'cover', 'eyebrow' => 'Serie', 'title' => 'Weite', 'year' => '2023–2025', 'category' => 'Landschaft', 'place' => 'Küste und Mittelgebirge', 'client' => '',
            'statement' => '<p>Orte, an denen der Horizont das Wichtigste ist. Fotografiert im ersten und im letzten Licht, meist allein, meist zu Fuß.</p>',
            'cover' => $at($L, 4), 'ratio' => '', 'back_label' => 'Alle Arbeiten', 'back_link' => '']],
        ['type' => 'photo_grid', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'manual', 'collection' => null,
            'images' => $items(array_values(array_filter([$at($L, 0), $img['video']['land'] ?? null, $at($L, 3), $at($L, 2)])), isset($img['video']['land']) ? ['Nebel über Hügeln', 'Nebel, bewegt (Video)', 'Tal im Blau', 'Horizont'] : ['Nebel über Hügeln', 'Tal im Blau', 'Horizont']), 'columns' => 'auto', 'ratio' => '', 'row_height' => 'm', 'width' => 'contained', 'captions' => true, 'lightbox' => true]],
        ['type' => 'photo_grid', 'tunes' => ['background' => 'dark'], 'data' => ['variant' => 'justified', 'eyebrow' => '', 'title' => 'Weitere Bilder', 'intro' => '', 'source' => 'collection', 'collection' => $img['col']['land'] ?? null,
            'images' => [], 'columns' => 'auto', 'ratio' => '', 'row_height' => 'm', 'width' => 'wide', 'captions' => false, 'lightbox' => true]],
    ]);

    // Über mich
    $page(['slug' => 'ueber-mich', 'title' => 'Über mich', 'sort' => 2, 'meta_description' => 'Wer hinter den Bildern steht (Demo-Inhalt).'], [
        ['type' => 'photo_text', 'data' => ['variant' => 'left', 'eyebrow' => 'Über mich', 'title' => 'Mara Beispiel',
            'text' => '<p>Fotografin in Musterstadt (fiktive Person). Ich arbeite für Magazine, Kulturhäuser und Menschen, die ein Porträt brauchen, das nach ihnen aussieht – und an freien Serien über Licht und Orte.</p><p>[Eigene Geschichte, Ausbildung und Haltung hier ergänzen.]</p>',
            'button_label' => 'Arbeiten ansehen', 'button_link' => '/arbeiten', 'button2_label' => '', 'button2_link' => '',
            'image' => $at($P, 4), 'caption' => '', 'ratio' => '4:5', 'size' => 'half', 'align' => 'center', 'lightbox' => false]],
        ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'list', 'eyebrow' => '', 'title' => 'Was ich anbiete', 'intro' => '', 'size' => 'm', 'items' => [
            ['icon' => 'user-circle', 'image' => null, 'title' => 'Porträt', 'text' => 'Für Menschen, Teams und Bühnen – im Studio oder vor Ort.', 'link_label' => '', 'link' => ''],
            ['icon' => 'newspaper', 'image' => null, 'title' => 'Reportage', 'text' => 'Begleitung über Stunden oder Tage, ruhig und unaufdringlich.', 'link_label' => '', 'link' => ''],
            ['icon' => 'mountains', 'image' => null, 'title' => 'Landschaft & Architektur', 'text' => 'Orte, Räume und Gebäude im besten Licht.', 'link_label' => '', 'link' => ''],
            ['icon' => 'printer', 'image' => null, 'title' => 'Fine-Art-Drucke', 'text' => 'Ausgewählte Motive als signierte Abzüge.', 'link_label' => '', 'link' => ''],
        ]]],
        ['type' => 'logos', 'data' => ['variant' => 'grid', 'eyebrow' => '', 'title' => 'Veröffentlicht in (fiktiv)', 'items' => [
            ['name' => 'Magazin Beispiel', 'link' => '', 'image' => null], ['name' => 'Kulturhaus Muster', 'link' => '', 'image' => null],
            ['name' => 'Wochenblatt Probe', 'link' => '', 'image' => null], ['name' => 'Verlag Platzhalter', 'link' => '', 'image' => null],
        ]]],
        $cta,
    ]);

    // Kontakt
    $page(['slug' => 'kontakt', 'title' => 'Kontakt', 'sort' => 3, 'meta_description' => 'So erreichen Sie die fiktive Fotografin Mara Beispiel (Demo-Inhalt).'], [
        ['type' => 'hero', 'tunes' => ['spaceBottom' => 'small'], 'data' => ['variant' => 'compact', 'eyebrow' => '', 'title' => 'Kontakt', 'text' => 'Für Aufträge, Drucke und Ausstellungen. Ich antworte innerhalb von zwei Werktagen.']],
        ['type' => 'contact', 'tunes' => ['anchor' => 'kontakt', 'spaceTop' => 'none'], 'data' => ['eyebrow' => '', 'title' => 'So erreichen Sie mich', 'intro' => '', 'show_hours' => false, 'show_map' => false,
            'form_table' => '', 'form_title' => '', 'submit_label' => '', 'note' => 'Bitte nennen Sie Datum, Ort und Umfang Ihres Vorhabens.']],
        ['type' => 'pricing', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'cards', 'eyebrow' => '', 'title' => 'Pakete (Beispiel)', 'intro' => 'Preise frei erfunden – nur zur Ansicht.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'rows' => [], 'items' => [
            ['name' => 'Porträt', 'badge' => '', 'price' => 'ab 290 €', 'period' => 'pro Termin', 'text' => 'Eine Stunde, ein Ort.', 'features' => "Vorgespräch\n10 bearbeitete Bilder\nNutzung privat", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '#kontakt'],
            ['name' => 'Reportage', 'badge' => '', 'price' => 'ab 890 €', 'period' => 'pro Tag', 'text' => 'Begleitung vor Ort.', 'features' => "Bis 8 Stunden\nAuswahl und Bearbeitung\nNutzungsrechte nach Absprache", 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '#kontakt'],
            ['name' => 'Druck', 'badge' => '', 'price' => 'ab 120 €', 'period' => 'je Abzug', 'text' => 'Signierte Fine-Art-Drucke.', 'features' => "Hahnemühle-Papier (Beispiel)\nAuflage limitiert\nVersand in der Rolle", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '#kontakt'],
        ]]],
        ['type' => 'faq', 'data' => ['variant' => 'split', 'eyebrow' => '', 'title' => 'Häufige Fragen', 'intro' => '', 'items' => [
            ['q' => 'Wie lange dauert es bis zu den Bildern?', 'a' => '<p>In der Regel zwei Wochen nach dem Termin. [Bitte an Ihre Abläufe anpassen.]</p>'],
            ['q' => 'Sind die Bilder dieser Demo echt?', 'a' => '<p>Nein. Alle Bilder sind automatisch erzeugte Platzhalter, die Person ist erfunden.</p>'],
        ]]],
    ]);
    // Startseite: Unterseiten von „Arbeiten“ als Serien-Übersicht → Feld „parent“ setzen
    $home = app()->db->fetch("SELECT id, content_published FROM pages WHERE slug = 'start' AND is_home = 1 ORDER BY id DESC LIMIT 1");
    if ($home) {
        $json = json_decode((string) $home['content_published'], true);
        // Kacheln des Bildstroms: „@slug“ → stabiler Verweis page:ID (Serien unter „Arbeiten“, sonst Hauptebene)
        $ref = function (string $slug) use ($root): string {
            $id = app()->db->fetchValue("SELECT id FROM pages WHERE slug = ? AND type = 'page' AND (parent_id = ? OR parent_id IS NULL) ORDER BY parent_id IS NULL, id DESC LIMIT 1", [$slug, $root]);
            return $id ? 'page:' . (int) $id : '';
        };
        foreach ($json['blocks'] as &$bl) {
            if ($bl['type'] === 'series_index') $bl['data']['parent'] = $root;
            if ($bl['type'] !== 'moments') continue;
            foreach ($bl['data']['tiles'] as &$tile) {
                if (str_starts_with((string) $tile['link'], '@')) $tile['link'] = $ref(substr($tile['link'], 1));
                elseif (str_starts_with((string) $tile['link'], '/')) $tile['link'] = $ref(trim($tile['link'], '/'));
            }
            unset($tile);
        }
        unset($bl);
        $enc = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        app()->db->query('UPDATE pages SET content_published = ?, content_draft = ? WHERE id = ?', [$enc, $enc, (int) $home['id']]);
    }
    // Vorschaubild für soziale Netzwerke
    if ($L) app()->settings->set('og_default_image', $L[0]);
    $log('Seiten angelegt.');
}
