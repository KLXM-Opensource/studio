<?php
/**
 * Demo-Inhalte des Kits „galerie“: fiktive Galerie mit fünf erfundenen Künstlerinnen und Künstlern, acht Ausstellungen
 * (Daten relativ zum Tag der Anlage – Status „Jetzt“, „Demnächst“ und „Archiv“ stimmen dadurch immer), sechzehn Werken,
 * Detailseiten, Anfrageformular und allen Seiten (Start, Ausstellungen, Künstler, Werke, Viewing Room, Die Galerie,
 * Kabinett, Besuch).
 *
 * Bilder: ausschließlich PLATZHALTER, mit GD erzeugt – abstrakte „Werke“ (Farbfelder, Quadrate, Raster, Gesten, Kreise,
 * Horizonte, Streifen), daraus montierte Ausstellungsansichten (weiße Wand, Boden, gehängte Platzhalter-Werke) und
 * Monogramme statt Porträtfotos. Keine Personen, keine Marken, keine fremden Werke. Medien-Schlagwort „galerie-demo“.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/galerie/tools/demo.php [--force|--remove]
 * Alle Namen, Biografien, Preise und Maße sind frei erfunden.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

/** Medien-Schlagwort und Tabellen der Demo */
function galerie_demo_tag(): string
{
    return 'galerie-demo';
}

function galerie_demo_handles(): array
{
    return ['anfragen', 'ausstellungen', 'werke', 'kuenstler'];   // Reihenfolge zum Löschen (Verknüpfungen zuerst)
}

/** Demo anlegen. $force: vorhandene Demo-Tabellen vorher entfernen. $pages: auch die Seiten anlegen. */
function galerie_demo_install(bool $force = false, ?callable $log = null, bool $pages = true): bool
{
    $log ??= static fn(string $m) => null;
    if (Tables::find('kuenstler') && !$force) {
        $log('Die Demo-Tabellen sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    galerie_demo_remove($log, $pages);
    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Musterstadt', 'email' => 'galerie@example.com'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }
    $img = galerie_demo_images($log);
    $pdf = galerie_demo_pdf($log);
    $t = galerie_demo_tables($img, $pdf, $log);
    if ($pages) galerie_demo_pages($img, $t, $log);
    galerie_demo_settings();
    \Core\PageCache::clear();
    $log('Fertig: ' . count($t) . ' Datentabellen, ' . count($img['all']) . ' Platzhalter-Bilder' . ($pages ? ', Seiten und Detailseiten' : '') . '.');
    return true;
}

/** Demo-Tabellen, Detailseiten, Demo-Bilder und (mit $pages) die Demo-Seiten entfernen */
function galerie_demo_remove(?callable $log = null, bool $pages = false): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $slugs = ['_vorlage-kuenstler', '_vorlage-ausstellungen', '_vorlage-werke'];
    if ($pages) $slugs = array_merge($slugs, ['start', 'ausstellungen', 'kuenstler', 'werke', 'viewing-room', 'die-galerie', 'kabinett', 'besuch']);
    $n = 0;
    foreach ($slugs as $s) {
        foreach ($db->fetchAll('SELECT id FROM pages WHERE slug = ? AND parent_id IS NULL', [$s]) as $p) { $db->query('DELETE FROM pages WHERE id = ?', [(int) $p['id']]); $n++; }
    }
    if ($n) Pages::rebuildPaths();
    foreach (galerie_demo_handles() as $h) if ($t = Tables::find($h)) Tables::delete($t);
    Tables::flush();
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . galerie_demo_tag() . '%']) as $m) Media::delete((int) $m['id']);
    $db->query("DELETE FROM media_collections WHERE name = 'Galerie (Demo)'");
    $log('Vorhandene Demo entfernt (' . $n . ' Seiten).');
}

// ------------------------------------------------------------------ Platzhalter-Bilder (GD)

/** Palette je Künstler: [Grund, Farbe 1, Farbe 2, Farbe 3] */
function galerie_demo_palettes(): array
{
    return [
        'ocker' => [[236, 226, 208], [196, 120, 52], [150, 48, 36], [58, 40, 34]],
        'blau' => [[226, 230, 234], [32, 64, 128], [92, 140, 190], [210, 170, 70]],
        'gruen' => [[232, 233, 222], [62, 96, 70], [150, 170, 120], [214, 120, 80]],
        'grau' => [[238, 236, 232], [40, 40, 40], [140, 140, 136], [196, 60, 44]],
        'rosa' => [[240, 228, 226], [210, 120, 130], [96, 60, 110], [236, 200, 120]],
        'nacht' => [[24, 28, 40], [70, 90, 140], [220, 150, 90], [240, 230, 210]],
    ];
}

/** Abstraktes Platzhalter-„Werk“ (Malerei, Zeichnung, Fotografie-ähnlich) – keine Nachahmung bestimmter Werke */
function galerie_demo_art(string $file, int $w, int $h, string $style, array $p, int $seed): void
{
    mt_srand(4200 + $seed);
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imageantialias($im, true);
    $c = fn(array $rgb, int $a = 0) => imagecolorallocatealpha($im, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])), $a);
    $mix = fn(array $a, array $b, float $t) => array_map(fn($x, $y) => (int) round($x * (1 - $t) + $y * $t), $a, $b);
    imagefill($im, 0, 0, $c($p[0]));
    $s = min($w, $h) / 1000;
    switch ($style) {
        case 'field':   // gestapelte, weiche Farbfelder
            imagefill($im, 0, 0, $c($mix($p[0], $p[2], .35)));
            $bands = [[.06, .40, $p[1]], [.46, .58, $p[3]], [.64, .94, $p[2]]];
            foreach ($bands as $k => [$y0, $y1, $col]) {
                for ($i = 0; $i < 26; $i++) {
                    $pad = (int) ((26 - $i) * 2.2 * $s);
                    imagefilledrectangle($im, (int) ($w * .08) + $pad, (int) ($h * $y0) + $pad, (int) ($w * .92) - $pad, (int) ($h * $y1) - $pad, $c($mix($col, $p[0], .08 * ($k % 2)), 112 - $i * 4));
                }
            }
            break;
        case 'squares':   // ineinanderliegende Quadrate
            $cols = [$p[1], $p[2], $p[3], $mix($p[1], $p[0], .5)];
            for ($k = 0; $k < 4; $k++) {
                $m = $k * .11;
                imagefilledrectangle($im, (int) ($w * $m), (int) ($h * ($m * 1.35)), (int) ($w * (1 - $m)), (int) ($h * (1 - $m * .65)), $c($cols[$k]));
            }
            break;
        case 'grid':   // Rasterzeichnung mit einzelnen Feldern
            $n = 9;
            $cw = $w / ($n + 1); $ch = $h / ($n + 1);
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    if (mt_rand(0, 9) < 2) imagefilledrectangle($im, (int) ($cw * (.5 + $i)), (int) ($ch * (.5 + $j)), (int) ($cw * (1.5 + $i)), (int) ($ch * (1.5 + $j)), $c(mt_rand(0, 1) ? $p[1] : $p[3], 30));
                }
            }
            imagesetthickness($im, max(1, (int) (2 * $s)));
            for ($i = 0; $i <= $n; $i++) {
                $jx = mt_rand(-3, 3); $jy = mt_rand(-3, 3);
                imageline($im, (int) ($cw * (.5 + $i)) + $jx, (int) ($ch * .5), (int) ($cw * (.5 + $i)) - $jx, (int) ($ch * ($n + .5)), $c($p[2], 20));
                imageline($im, (int) ($cw * .5), (int) ($ch * (.5 + $i)) + $jy, (int) ($cw * ($n + .5)), (int) ($ch * (.5 + $i)) - $jy, $c($p[2], 20));
            }
            break;
        case 'gesture':   // breite, gestische Pinselbahnen
            imagefill($im, 0, 0, $c($mix($p[0], [255, 255, 255], .3)));
            foreach ([[$p[1], 9], [$p[2], 7], [$p[3], 5]] as [$col, $count]) {
                for ($k = 0; $k < $count; $k++) {
                    $x = mt_rand(0, $w); $y = mt_rand(0, $h); $a = mt_rand(0, 628) / 100; $r = (int) (mt_rand(26, 60) * $s);
                    for ($st = 0; $st < 90; $st++) {
                        $a += (mt_rand(-20, 20) / 100);
                        $x += (int) (cos($a) * 9 * $s); $y += (int) (sin($a) * 9 * $s);
                        imagefilledellipse($im, $x, $y, $r, (int) ($r * .7), $c($col, 96));
                    }
                }
            }
            break;
        case 'circles':   // überlagerte Kreise
            for ($k = 0; $k < 7; $k++) {
                $r = (int) (mt_rand(220, 560) * $s);
                imagefilledellipse($im, mt_rand((int) ($w * .2), (int) ($w * .8)), mt_rand((int) ($h * .2), (int) ($h * .8)), $r, $r, $c([$p[1], $p[2], $p[3]][$k % 3], 58));
            }
            break;
        case 'horizon':   // fotografie-ähnlicher Horizont mit Verlauf
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                $col = $t < .62 ? $mix($p[1], $p[3], $t / .62) : $mix($p[2], $p[0], ($t - .62) / .38 * .6);
                imageline($im, 0, $y, $w, $y, $c($col));
            }
            imagefilledellipse($im, (int) ($w * .68), (int) ($h * .5), (int) (150 * $s), (int) (150 * $s), $c($p[3], 30));
            break;
        case 'stripes':   // senkrechte Bahnen
            $x = 0;
            while ($x < $w) {
                $bw = (int) (mt_rand(30, 140) * $s);
                imagefilledrectangle($im, $x, 0, $x + $bw, $h, $c([$p[1], $p[2], $p[3], $p[0]][mt_rand(0, 3)], mt_rand(0, 40)));
                $x += $bw + (int) (mt_rand(4, 18) * $s);
            }
            break;
    }
    // Leinwand-/Papierstruktur: feines Rauschen
    $n = (int) ($w * $h / 40);
    for ($i = 0; $i < $n; $i++) {
        $v = mt_rand(0, 1) ? 255 : 0;
        imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocatealpha($im, $v, $v, $v, 118));
    }
    imagejpeg($im, $file, 86);
    unset($im);
}

/**
 * Ausstellungsansicht (Platzhalter): weiße Wand mit Lichtverlauf, Boden, Sockelleiste, darauf gehängte Platzhalter-Werke
 * mit Schatten. $works: Dateipfade erzeugter Werke.
 */
function galerie_demo_room(string $file, array $works, int $seed, bool $dark = false): void
{
    mt_srand(7300 + $seed);
    [$w, $h] = [1800, 1125];
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    $floorY = (int) ($h * .74);
    $wall = $dark ? [40, 40, 42] : [244, 243, 240];
    for ($y = 0; $y < $floorY; $y++) {
        $t = $y / $floorY;
        $k = (int) round(10 * sin($t * M_PI));
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, $wall[0] - 8 + $k, $wall[1] - 8 + $k, $wall[2] - 9 + $k));
    }
    $floor = $dark ? [62, 58, 54] : [178, 170, 160];
    for ($y = $floorY; $y < $h; $y++) {
        $t = ($y - $floorY) / ($h - $floorY);
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($floor[0] - 30 * $t), (int) ($floor[1] - 30 * $t), (int) ($floor[2] - 30 * $t)));
    }
    imagefilledrectangle($im, 0, $floorY - 10, $w, $floorY, imagecolorallocate($im, $wall[0] - 22, $wall[1] - 22, $wall[2] - 22));
    // Lichtkegel der Deckenstrahler
    $count = max(1, min(3, count($works)));
    $slots = match ($count) { 1 => [.5], 2 => [.3, .7], default => [.2, .5, .8] };
    foreach ($slots as $k => $cx) {
        for ($r = 9; $r >= 1; $r--) imagefilledellipse($im, (int) ($w * $cx), (int) ($h * .38), $r * 120, $r * 95, imagecolorallocatealpha($im, 255, 255, 250, 121));
        $src = @imagecreatefromjpeg($works[$k]);
        if (!$src) continue;
        $sw = imagesx($src); $sh = imagesy($src);
        $maxH = $h * ($count === 1 ? .46 : .38); $maxW = $w * ($count === 1 ? .36 : .2);
        $f = min($maxH / $sh, $maxW / $sw);
        $dw = (int) ($sw * $f); $dh = (int) ($sh * $f);
        $dx = (int) ($w * $cx - $dw / 2); $dy = (int) ($h * .40 - $dh / 2);
        for ($i = 14; $i >= 1; $i--) imagefilledrectangle($im, $dx + $i, $dy + $i * 2, $dx + $dw + $i, $dy + $dh + $i * 2, imagecolorallocatealpha($im, 0, 0, 0, 122));
        imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        unset($src);
    }
    // Sitzbank als einfache Form
    if ($seed % 2 === 0) {
        $bx = (int) ($w * .38); $by = (int) ($h * .84);
        imagefilledrectangle($im, $bx, $by, $bx + (int) ($w * .24), $by + 26, imagecolorallocate($im, 70, 62, 54));
        imagefilledrectangle($im, $bx + 20, $by + 26, $bx + 34, $by + 90, imagecolorallocate($im, 50, 44, 40));
        imagefilledrectangle($im, $bx + (int) ($w * .24) - 34, $by + 26, $bx + (int) ($w * .24) - 20, $by + 90, imagecolorallocate($im, 50, 44, 40));
    }
    imagejpeg($im, $file, 84);
    unset($im);
}

/** Monogramm (600 × 600) – Platzhalter statt Porträtfoto */
function galerie_demo_avatar(string $file, string $initials, array $bg, array $ink, string $font): void
{
    $im = imagecreatetruecolor(600, 750);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$bg));
    imagefilledellipse($im, 300, 760, 560, 560, imagecolorallocatealpha($im, $ink[0], $ink[1], $ink[2], 96));
    $col = imagecolorallocate($im, ...$ink);
    if (is_file($font)) {
        $box = imagettfbbox(170, 0, $font, $initials);
        imagettftext($im, 170, 0, (int) ((600 - ($box[2] - $box[0])) / 2) - $box[0], 420, $col, $font, $initials);
    }
    imagejpeg($im, $file, 88);
    unset($im);
}

/**
 * Alle Platzhalter-Bilder. @return array{all: list<int>, works: array<string,int>, details: array<string,int>, rooms: list<int>, portraits: array<string,int>, collection: int}
 */
function galerie_demo_images(callable $log): array
{
    $col = Media::createCollection('Galerie (Demo)', 'Platzhalter-Bilder der Galerie-Demo – automatisch erzeugt (GD), keine echten Kunstwerke, frei verwendbar.');
    $tmp = sys_get_temp_dir() . '/galerie-demo-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0775, true);
    $credit = 'Platzhalter, automatisch erzeugt (GD) – kein echtes Werk';
    $all = [];
    $import = function (string $file, string $name, string $title, string $alt, bool $inCol = true) use ($col, $log, $credit, &$all): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => galerie_demo_tag(), 'credit' => $credit] + ($inCol ? ['collection' => $col] : []));
        if ($err) $log($name . ': ' . $err);
        if ($m) $all[] = (int) $m['id'];
        return $m ? (int) $m['id'] : null;
    };
    $P = galerie_demo_palettes();
    // Werke: Schlüssel => [Stil, Palette, Breite, Höhe, Beschreibung für den Alt-Text]
    $defs = galerie_demo_work_defs();
    $files = $works = $details = [];
    foreach ($defs as $i => $wd) {
        [$key, $style, $pal, $ww, $hh, $desc] = [$wd['key'], $wd['style'], $wd['palette'], $wd['w'], $wd['h'], $wd['alt']];
        $f = "$tmp/$key.jpg";
        galerie_demo_art($f, $ww, $hh, $style, $P[$pal], $i);
        $files[$key] = $f;
        $works[$key] = $import($f, "platzhalter-werk-$key.jpg", $wd['title'] . ' (Platzhalter)', 'Platzhalter-Abbildung: ' . $desc . ' (automatisch erzeugt, kein echtes Werk)');
        if (!empty($wd['detail'])) {
            // Detailansicht: Ausschnitt aus der Mitte, vergrößert
            $src = imagecreatefromjpeg($f);
            $cw = (int) (imagesx($src) * .4); $ch = (int) (imagesy($src) * .4);
            $dst = imagecreatetruecolor(1200, (int) (1200 * $ch / $cw));
            imagecopyresampled($dst, $src, 0, 0, (int) (imagesx($src) * .3), (int) (imagesy($src) * .3), imagesx($dst), imagesy($dst), $cw, $ch);
            imagejpeg($dst, "$tmp/$key-detail.jpg", 86);
            unset($src, $dst);
            $details[$key] = $import("$tmp/$key-detail.jpg", "platzhalter-werk-$key-detail.jpg", $wd['title'] . ' – Detail (Platzhalter)', 'Platzhalter-Abbildung: Ausschnitt aus ' . $desc . ' (automatisch erzeugt)', false);
        }
    }
    // Ausstellungsansichten: aus den Platzhalter-Werken montiert
    $rooms = [];
    $roomDefs = [
        ['a1', 'a2', 'a3'], ['a4'], ['c1', 'c2'], ['c3'], ['b1', 'b2', 'b3'], ['b2'], ['d1', 'd2', 'd3'], ['e1', 'e2'], ['e3', 'a2', 'c1'], ['d2'], ['a3', 'c2', 'e1'],
    ];
    foreach ($roomDefs as $k => $keys) {
        $f = "$tmp/raum-$k.jpg";
        galerie_demo_room($f, array_values(array_filter(array_map(fn($x) => $files[$x] ?? null, $keys))), $k, $k === 5);
        $rooms[] = $import($f, sprintf('platzhalter-ausstellungsansicht-%02d.jpg', $k + 1), 'Ausstellungsansicht ' . ($k + 1) . ' (Platzhalter)',
            'Platzhalter: montierte Ausstellungsansicht – weiße Wand mit ' . count($keys) . ' gehängten Platzhalter-Werken (automatisch erzeugt)');
    }
    // Platzhalter-Video (stumm, 8 s, abstrakter Farbverlauf) – nur wenn ffmpeg auf dem Server läuft
    $video = null;
    if (class_exists(\Core\Ffmpeg::class) && \Core\Ffmpeg::available() && function_exists('exec')) {
        $f = "$tmp/platzhalter-video.mp4";
        $bin = \Core\Ffmpeg::bin('ffmpeg');
        @exec(escapeshellarg($bin) . ' -hide_banner -loglevel error -y -f lavfi -i ' . escapeshellarg('gradients=s=1280x720:d=8:speed=0.015:c0=0xc47834:c1=0x96302c:c2=0xece2d0:c3=0x3a2822:n=4')
            . ' -an -c:v libx264 -pix_fmt yuv420p -preset veryfast -crf 30 -movflags +faststart ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
        if ($rc === 0 && is_file($f) && filesize($f) > 1000) {
            $video = $import($f, 'platzhalter-video-atelier.mp4', 'Atelier, Farbschichten (Platzhalter-Video)', '', false);
        } else $log('Platzhalter-Video: ffmpeg fehlgeschlagen – Demo ohne Video.');
    } else $log('Platzhalter-Video übersprungen (ffmpeg nicht verfügbar).');
    // Monogramme statt Porträtfotos
    $font = dirname(__DIR__) . '/fonts/Inter_700Bold.ttf';
    $portraits = [];
    foreach (galerie_demo_artist_defs() as $a) {
        $f = "$tmp/monogramm-{$a['key']}.jpg";
        galerie_demo_avatar($f, $a['initials'], $P[$a['palette']][0], $P[$a['palette']][1], $font);
        $portraits[$a['key']] = $import($f, "platzhalter-monogramm-{$a['key']}.jpg", 'Monogramm ' . $a['initials'] . ' (Platzhalter)',
            'Monogramm ' . $a['initials'] . ' – Platzhalter statt Porträtfoto', false);
    }
    foreach (glob("$tmp/*") ?: [] as $f) @unlink($f);
    @rmdir($tmp);
    $log(count($all) . ' Platzhalter-Bilder erzeugt.');
    return ['all' => $all, 'works' => $works, 'details' => $details, 'rooms' => $rooms, 'portraits' => $portraits, 'video' => $video, 'collection' => $col];
}

/** Kleiner Pressetext als PDF (eine Seite) */
function galerie_demo_pdf(callable $log): ?int
{
    $text = 'Pressetext (Demo) - Farbe als Ort - frei erfundener Inhalt.';
    $stream = "BT /F1 18 Tf 72 760 Td ($text) Tj ET";
    $objs = ['<</Type/Catalog/Pages 2 0 R>>', '<</Type/Pages/Kids[3 0 R]/Count 1>>',
        '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
        '<</Length ' . strlen($stream) . ">>stream\n$stream\nendstream", '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>'];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    $pdf .= 'trailer<</Size ' . (count($objs) + 1) . "/Root 1 0 R>>\nstartxref\n$xref\n%%EOF\n";
    $file = tempnam(sys_get_temp_dir(), 'gx') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'pressetext-farbe-als-ort-demo.pdf', '', ['title' => 'Pressetext „Farbe als Ort“ (Demo)', 'tags' => galerie_demo_tag()]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Inhalte (alles frei erfunden)

function galerie_demo_artist_defs(): array
{
    return [
        ['key' => 'a', 'name' => 'Lene Beispiel', 'sortname' => 'Beispiel', 'initials' => 'LB', 'palette' => 'ocker', 'featured' => true,
            'geboren' => '* 1984 in Musterstadt', 'lebt' => 'lebt und arbeitet in Beispielstadt',
            'kurz' => 'Malerei als Raum: großformatige Farbfelder, die sich mit dem Licht des Tages verändern.',
            'bio' => '<p>Lene Beispiel (fiktiv) malt in dünnen, übereinanderliegenden Schichten. Ihre Bilder entstehen über Monate; jede Lasur verschiebt den Ton der vorigen.</p><h3>Ausbildung</h3><p>Studium der Malerei an einer erfundenen Kunsthochschule in Musterstadt.</p><h3>Auswahl</h3><ul><li>Einzelausstellungen in der Galerie Beispiel (Demo)</li><li>Teilnahme an der Kunstmesse Beispielstadt</li></ul>'],
        ['key' => 'b', 'name' => 'Jonas Probe', 'sortname' => 'Probe', 'initials' => 'JP', 'palette' => 'blau', 'featured' => true,
            'geboren' => '* 1979 in Musterhausen', 'lebt' => 'lebt in Musterstadt',
            'kurz' => 'Fotografische Arbeiten über Horizonte, Dämmerung und die Zeit dazwischen.',
            'bio' => '<p>Jonas Probe (fiktiv) fotografiert nur in der blauen Stunde. Seine Abzüge sind Unikate, die Farben entstehen in der Dunkelkammer.</p>'],
        ['key' => 'c', 'name' => 'Lene Vorlage', 'sortname' => 'Vorlage', 'initials' => 'LV', 'palette' => 'gruen', 'featured' => true,
            'geboren' => '* 1991 in Beispielstadt', 'lebt' => 'lebt und arbeitet in Musterstadt',
            'kurz' => 'Zeichnungen aus Rastern, Abweichungen und langsam gezogenen Linien.',
            'bio' => '<p>Lene Vorlage (fiktiv) zeichnet Raster von Hand – die kleinen Fehler sind das Thema. Für das Kabinett entsteht eine neue, raumbezogene Arbeit.</p>'],
        ['key' => 'd', 'name' => 'Theo Muster', 'sortname' => 'Muster', 'initials' => 'TM', 'palette' => 'grau', 'featured' => false,
            'geboren' => '* 1968 in Musterstadt', 'lebt' => 'lebt in Beispieldorf',
            'kurz' => 'Objekte und Reliefs aus gefundenen Materialien, reduziert auf Kreis, Linie und Fläche.',
            'bio' => '<p>Theo Muster (fiktiv) arbeitet seit den Neunzigern mit Fundstücken aus Werkstätten. Seine Reliefs werden hier als Platzhalter-Abbildungen gezeigt.</p>'],
        ['key' => 'e', 'name' => 'Ida Platzhalter', 'sortname' => 'Platzhalter', 'initials' => 'IP', 'palette' => 'rosa', 'featured' => true,
            'geboren' => '* 1988 in Musterhausen', 'lebt' => 'lebt und arbeitet in Beispielstadt',
            'kurz' => 'Textile Malerei: Gesten, Bahnen, weiche Systeme.',
            'bio' => '<p>Ida Platzhalter (fiktiv) verbindet Malerei und Textil. Ihre Arbeiten hängen frei im Raum oder liegen wie Teppiche am Boden.</p>'],
    ];
}

function galerie_demo_work_defs(): array
{
    $w = fn(string $key, string $artist, string $title, string $year, string $tech, string $size, string $style, string $pal, int $iw, int $ih, string $alt,
        string $avail, string $show, ?float $price, string $edition = '', bool $detail = false) =>
        compact('key', 'artist', 'title', 'year', 'tech', 'size', 'style', 'avail', 'show', 'price', 'edition', 'detail', 'alt') + ['palette' => $pal, 'w' => $iw, 'h' => $ih];
    return [
        $w('a1', 'a', 'Ort I (Ocker)', '2025', 'Öl auf Leinwand', '180 × 140 cm', 'field', 'ocker', 1100, 1400, 'Farbfeldbild mit drei weichen Bahnen in Ocker, Rot und Braun', 'verfuegbar', 'anfrage', 24000, '', true),
        $w('a2', 'a', 'Ort II (Rot)', '2025', 'Öl auf Leinwand', '160 × 120 cm', 'field', 'rosa', 1050, 1400, 'Farbfeldbild in Rosa- und Violetttönen', 'verkauft', 'anfrage', 21000),
        $w('a3', 'a', 'Nachmittag', '2024', 'Öl und Wachs auf Leinwand', '90 × 70 cm', 'squares', 'ocker', 1090, 1400, 'Ineinanderliegende Rechtecke in warmen Erdtönen', 'reserviert', 'zeigen', 9800),
        $w('a4', 'a', 'Studie zu „Ort“', '2023', 'Öl auf Papier', '42 × 30 cm', 'field', 'nacht', 990, 1400, 'Kleine Farbfeldstudie in Nachtblau mit hellem Band', 'verfuegbar', 'zeigen', 2400),
        $w('b1', 'b', 'Blaue Stunde, Ufer', '2025', 'Pigmentdruck auf Baryt', '100 × 150 cm', 'horizon', 'blau', 1400, 933, 'Fotografie-ähnlicher Verlauf von Blau zu Gelb über einem Horizont', 'verfuegbar', 'zeigen', 6800, 'Auflage 5 + 2 AP'),
        $w('b2', 'b', 'Blaue Stunde, Feld', '2024', 'Pigmentdruck auf Baryt', '60 × 90 cm', 'horizon', 'nacht', 1400, 933, 'Dunkler Verlauf mit warmem Licht am Horizont', 'verkauft', 'zeigen', 3900, 'Auflage 7'),
        $w('b3', 'b', 'Zwischenzeit', '2025', 'Pigmentdruck', '40 × 60 cm', 'horizon', 'rosa', 1400, 933, 'Verlauf in Rosa und Violett mit hellem Kreis', 'verfuegbar', 'zeigen', 1800, 'Edition 3/10'),
        $w('c1', 'c', 'Raster, langsam (1)', '2026', 'Bleistift und Gouache auf Papier', '70 × 70 cm', 'grid', 'gruen', 1300, 1300, 'Handgezeichnetes Raster mit einzelnen grünen und orangen Feldern', 'verfuegbar', 'anfrage', 4200, '', true),
        $w('c2', 'c', 'Raster, langsam (2)', '2026', 'Bleistift und Gouache auf Papier', '70 × 70 cm', 'grid', 'grau', 1300, 1300, 'Handgezeichnetes Raster in Grau mit roten Feldern', 'verfuegbar', 'anfrage', 4200),
        $w('c3', 'c', 'Abweichung', '2025', 'Tusche auf Papier', '50 × 40 cm', 'stripes', 'gruen', 1120, 1400, 'Senkrechte, unregelmäßige Bahnen in Grüntönen', 'verkauft', 'aus', null),
        $w('d1', 'd', 'Kreis und Fläche', '2022', 'Relief, Holz und Lack', '120 × 120 cm', 'circles', 'grau', 1300, 1300, 'Überlagerte Kreise in Grau, Schwarz und Rot', 'verfuegbar', 'anfrage', 15000),
        $w('d2', 'd', 'Werkstattstück', '2021', 'Fundstücke, Stahl', '80 × 60 cm', 'squares', 'grau', 1050, 1400, 'Ineinanderliegende Rechtecke in Grau mit rotem Kern', 'verfuegbar', 'aus', null),
        $w('d3', 'd', 'Linie', '2020', 'Relief, Aluminium', '30 × 200 cm', 'stripes', 'grau', 1400, 560, 'Querformat mit schmalen senkrechten Bahnen in Grau und Rot', 'verkauft', 'anfrage', null),
        $w('e1', 'e', 'Weiches System I', '2025', 'Acryl auf Baumwolle, genäht', '210 × 160 cm', 'gesture', 'rosa', 1070, 1400, 'Gestische Bahnen in Rosa, Violett und Gelb', 'verfuegbar', 'zeigen', 12500),
        $w('e2', 'e', 'Weiches System II', '2025', 'Acryl auf Baumwolle, genäht', '210 × 160 cm', 'gesture', 'ocker', 1070, 1400, 'Gestische Bahnen in Ocker und Rot', 'reserviert', 'zeigen', 12500),
        $w('e3', 'e', 'Teppichbild', '2024', 'Wolle, getuftet', '120 × 180 cm', 'stripes', 'rosa', 1400, 933, 'Querformat mit farbigen Bahnen in Rosa und Violett', 'verfuegbar', 'anfrage', 8600),
    ];
}

function galerie_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

/** Tabellen anlegen und füllen. @return array<string, array> Tabellen nach Kurzname */
function galerie_demo_tables(array $img, ?int $pdf, callable $log): array
{
    $out = [];
    // ---------------------------------------------------------------- Künstler
    $t = galerie_demo_table([
        'handle' => 'kuenstler', 'name' => 'Künstler', 'singular' => 'Künstlerin / Künstler', 'icon' => 'users-three',
        'description' => 'Das Programm der Galerie – Namen, Porträt, Biografie. Detailseite unter /kuenstler/…',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'searchable' => true],
            ['name' => 'sortname', 'label' => 'Sortiername (optional)', 'type' => 'text', 'width' => 'half', 'help' => 'Für die alphabetische Liste, meist der Nachname. Leer = letztes Wort des Namens.'],
            ['name' => 'featured', 'label' => 'Im Programm hervorheben', 'type' => 'bool', 'in_list' => true, 'width' => 'half', 'help' => 'Für Listen mit „Nur hervorgehobene“ – z. B. Programm vs. Gäste.'],
            ['name' => 'portraet', 'label' => 'Porträt', 'type' => 'media', 'help' => 'Hochformat 4:5. Alt-Text in der Mediathek pflegen.'],
            ['name' => 'geboren', 'label' => 'Geboren', 'type' => 'text', 'width' => 'half', 'help' => 'Freitext, z. B. „* 1984 in Musterstadt“.'],
            ['name' => 'lebt', 'label' => 'Lebt / arbeitet', 'type' => 'text', 'width' => 'half', 'help' => 'z. B. „lebt und arbeitet in Beispielstadt“.'],
            ['name' => 'kurzbio', 'label' => 'Kurzbiografie', 'type' => 'textarea', 'help' => 'Ein, zwei Sätze für Listen und Suchmaschinen.'],
            ['name' => 'bio', 'label' => 'Biografie', 'type' => 'richtext'],
            ['name' => 'website', 'label' => 'Website (optional)', 'type' => 'url'],
            ['name' => 'medien_1', 'label' => 'Bild oder Video 1', 'type' => 'file', 'width' => 'half',
                'help' => 'Atelier, Arbeitsprozess, Videoarbeit (MP4). Einfacher: auf der Detailseite Dateien in „Bilder und Videos“ ziehen.'],
            ['name' => 'medien_2', 'label' => 'Bild oder Video 2', 'type' => 'file', 'width' => 'half'],
            ['name' => 'medien_3', 'label' => 'Bild oder Video 3', 'type' => 'file', 'width' => 'half'],
            ['name' => 'medien_4', 'label' => 'Bild oder Video 4', 'type' => 'file', 'width' => 'half'],
            ['name' => 'video_url', 'label' => 'Video (YouTube- oder Vimeo-Link, optional)', 'type' => 'url', 'help' => 'Wird erst nach einem Klick geladen (Zwei-Klick-Lösung).'],
        ],
        'settings' => ['route' => 'kuenstler', 'title_field' => 'name', 'image_field' => 'portraet', 'description_field' => 'kurzbio', 'sort_field' => 'sort', 'sort_dir' => 'asc',
            'workflow' => 1, 'schema_type' => 'none', 'search' => ['enabled' => '1']],
    ], $log);
    $artistIds = [];
    if ($t) {
        foreach (galerie_demo_artist_defs() as $a) {
            [$id, $err] = Entries::save($t, null, ['name' => $a['name'], 'sortname' => $a['sortname'], 'featured' => $a['featured'], 'portraet' => $img['portraits'][$a['key']] ?? null,
                'geboren' => $a['geboren'], 'lebt' => $a['lebt'], 'kurzbio' => $a['kurz'], 'bio' => $a['bio'], 'website' => '',
                'medien_1' => $a['key'] === 'e' ? ($img['video'] ?? null) : ($a['key'] === 'a' ? ($img['rooms'][1] ?? null) : null), 'status' => 'published']);
            if ($err) $log('Künstler „' . $a['name'] . '“: ' . implode(' ', $err));
            $artistIds[$a['key']] = $id;
        }
        $out['kuenstler'] = $t;
    }
    // ---------------------------------------------------------------- Werke
    $t = galerie_demo_table([
        'handle' => 'werke', 'name' => 'Werke', 'singular' => 'Werk', 'icon' => 'paint-brush',
        'description' => 'Werke mit Angaben, Verfügbarkeit und Preis. Detailseite unter /werke/…',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true, 'searchable' => true],
            ['name' => 'kuenstler', 'label' => 'Künstlerin / Künstler', 'type' => 'relation', 'target' => 'kuenstler', 'in_list' => true, 'width' => 'half'],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'technik', 'label' => 'Technik / Material', 'type' => 'text', 'width' => 'half', 'help' => 'z. B. „Öl auf Leinwand“.'],
            ['name' => 'masse', 'label' => 'Maße', 'type' => 'text', 'width' => 'half', 'help' => 'z. B. „180 × 140 cm“ (Höhe × Breite).'],
            ['name' => 'auflage', 'label' => 'Auflage / Edition (optional)', 'type' => 'text', 'width' => 'half', 'help' => 'z. B. „Edition 3/10“ oder „Auflage 5 + 2 AP“.'],
            ['name' => 'verfuegbarkeit', 'label' => 'Verfügbarkeit', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "verfuegbar=Verfügbar\nreserviert=Reserviert\nverkauft=Verkauft"],
            ['name' => 'bild', 'label' => 'Abbildung', 'type' => 'media', 'help' => 'Ganzes Werk, ohne Rahmen; lange Seite mind. 1600 px. Werke werden nie beschnitten. Einfacher: auf der Detailseite Dateien in „Bilder und Videos“ ziehen.'],
            ['name' => 'ansicht_1', 'label' => 'Weitere Ansicht oder Video 1', 'type' => 'file', 'width' => 'half', 'help' => 'Detail, Seitenansicht, Rückseite – oder ein Video (MP4).'],
            ['name' => 'ansicht_2', 'label' => 'Weitere Ansicht oder Video 2', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_3', 'label' => 'Weitere Ansicht oder Video 3', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_4', 'label' => 'Weitere Ansicht oder Video 4', 'type' => 'file', 'width' => 'half'],
            ['name' => 'video_url', 'label' => 'Video (YouTube- oder Vimeo-Link, optional)', 'type' => 'url', 'help' => 'z. B. für Installationen und Videokunst. Lädt erst nach einem Klick.'],
            ['name' => 'preis', 'label' => 'Preis in Euro (optional)', 'type' => 'number', 'width' => 'half'],
            ['name' => 'preis_anzeige', 'label' => 'Preis auf der Website', 'type' => 'select', 'width' => 'half', 'options' => "anfrage=Preis auf Anfrage\nzeigen=Preis anzeigen\naus=Nicht anzeigen",
                'help' => 'Design → Galerie → „Preise“ kann das für alle Werke übersteuern. Verkaufte Werke zeigen nie einen Preis.'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung (optional)', 'type' => 'richtext'],
        ],
        'settings' => ['route' => 'werke', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'technik', 'sort_field' => 'sort', 'sort_dir' => 'asc',
            'workflow' => 1, 'schema_type' => 'none', 'search' => ['enabled' => '1']],
    ], $log);
    $workIds = [];
    if ($t) {
        foreach (galerie_demo_work_defs() as $wd) {
            [$id, $err] = Entries::save($t, null, ['titel' => $wd['title'], 'kuenstler' => $artistIds[$wd['artist']] ?? null, 'jahr' => $wd['year'], 'technik' => $wd['tech'],
                'masse' => $wd['size'], 'auflage' => $wd['edition'], 'verfuegbarkeit' => $wd['avail'], 'bild' => $img['works'][$wd['key']] ?? null,
                'ansicht_1' => $img['details'][$wd['key']] ?? ($wd['key'] === 'e1' ? ($img['video'] ?? null) : null), 'preis' => $wd['price'], 'preis_anzeige' => $wd['show'],
                'beschreibung' => '<p>Beschreibung (Demo): ' . e($wd['alt']) . '. Die Abbildung ist ein automatisch erzeugter Platzhalter, Maße und Preise sind frei erfunden.</p>',
                'status' => 'published']);
            if ($err) $log('Werk „' . $wd['title'] . '“: ' . implode(' ', $err));
            $workIds[$wd['key']] = $id;
        }
        $out['werke'] = $t;
    }
    // ---------------------------------------------------------------- Ausstellungen (Daten relativ zu heute)
    $t = galerie_demo_table([
        'handle' => 'ausstellungen', 'name' => 'Ausstellungen', 'singular' => 'Ausstellung', 'icon' => 'calendar-dots',
        'description' => 'Ausstellungen, Messen und Präsentationen. Der Status (jetzt, demnächst, Archiv) ergibt sich aus Beginn und Ende. Detailseite unter /ausstellungen/…',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true, 'searchable' => true],
            ['name' => 'untertitel', 'label' => 'Untertitel / Zusatz (optional)', 'type' => 'text', 'help' => 'z. B. „Neue Arbeiten“ oder ein Satz zur Ausstellung.'],
            ['name' => 'kuenstler', 'label' => 'Künstlerinnen und Künstler', 'type' => 'relations', 'target' => 'kuenstler', 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'date', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'date', 'in_list' => true, 'width' => 'half', 'help' => 'Leer = bis auf Weiteres.'],
            ['name' => 'eroeffnung', 'label' => 'Eröffnung (Vernissage, optional)', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'select', 'in_list' => true, 'width' => 'half',
                'options' => "galerie=Galerie\nprojektraum=Kabinett\nshowroom=Showroom\nmesse=Kunstmesse\nextern=Extern"],
            ['name' => 'ort_text', 'label' => 'Ortsangabe (optional)', 'type' => 'text', 'help' => 'z. B. „Halle 2, Stand B 14“ bei Messen oder die Adresse eines externen Orts.'],
            ['name' => 'kurztext', 'label' => 'Kurztext', 'type' => 'textarea', 'help' => 'Zwei, drei Sätze für Listen, Startseite und Suchmaschinen.'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Hauptbild (Ausstellungsansicht oder Werk)', 'type' => 'media'],
            ['name' => 'ansicht_1', 'label' => 'Ansicht oder Video 1', 'type' => 'file', 'width' => 'half',
                'help' => 'Ausstellungsansichten und Videos (MP4). Einfacher: auf der Detailseite Dateien in „Bilder und Videos“ ziehen.'],
            ['name' => 'ansicht_2', 'label' => 'Ansicht oder Video 2', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_3', 'label' => 'Ansicht oder Video 3', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_4', 'label' => 'Ansicht oder Video 4', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_5', 'label' => 'Ansicht oder Video 5', 'type' => 'file', 'width' => 'half'],
            ['name' => 'ansicht_6', 'label' => 'Ansicht oder Video 6', 'type' => 'file', 'width' => 'half'],
            ['name' => 'video_url', 'label' => 'Video (YouTube- oder Vimeo-Link, optional)', 'type' => 'url', 'help' => 'Rundgang, Interview. Lädt erst nach einem Klick (Zwei-Klick-Lösung).'],
            ['name' => 'werke', 'label' => 'Gezeigte Werke', 'type' => 'relations', 'target' => 'werke'],
            ['name' => 'pressetext', 'label' => 'Pressetext (PDF, optional)', 'type' => 'file', 'accept' => ['pdf']],
        ],
        'settings' => ['route' => 'ausstellungen', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext', 'sort_field' => 'beginn', 'sort_dir' => 'desc',
            'workflow' => 1, 'schema_type' => 'none', 'search' => ['enabled' => '1']],
    ], $log);
    if ($t) {
        $d = fn(int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
        $R = fn(int $i) => $img['rooms'][$i] ?? null;
        $A = fn(string ...$k) => array_values(array_filter(array_map(fn($x) => $artistIds[$x] ?? null, $k)));
        $W = fn(string ...$k) => array_values(array_filter(array_map(fn($x) => $workIds[$x] ?? null, $k)));
        $txt = fn(string $lead) => '<p>Dieser Text ist frei erfunden und zeigt, wie eine Ausstellung auf der Website erscheint: mit Zeitraum, Eröffnung, Ort, Künstlerinnen und Künstlern, Ausstellungsansichten und den gezeigten Werken.</p><h3>Zur Ausstellung</h3><p>[Hier steht der Ausstellungstext der Galerie.]</p>';
        $rows = [
            ['Farbe als Ort', 'Neue Malerei', $A('a'), $d(-24), $d(38), $d(-24) . ' 19:00', 'galerie', '', 'Lene Beispiel zeigt sieben neue Bilder, die erst im Lauf des Tages ihre Farbe preisgeben.', $R(0), array_values(array_filter([$R(1), $R(10), $img['video'] ?? null])), $W('a1', 'a2', 'a3', 'a4'), $pdf],
            ['Linie, langsam', 'Eine Arbeit für das Kabinett', $A('c'), $d(-10), $d(25), $d(-10) . ' 18:30', 'projektraum', '4,20 Meter Wand', 'Lene Vorlage zeichnet ein Raster direkt auf die Wand des Kabinetts – zwei Wochen lang, vor Publikum.', $R(2), [$R(3)], $W('c1', 'c2'), null],
            ['Kunstmesse Beispielstadt', 'Kabinettstücke', $A('a', 'd', 'e'), $d(20), $d(23), '', 'messe', 'Halle 2, Stand B 14 · Messegelände Beispielstadt', 'Die Galerie zeigt neue Arbeiten von Lene Beispiel, Theo Muster und Ida Platzhalter.', $R(8), [], $W('a4', 'd1', 'e3'), null],
            ['Licht über Musterhausen', 'Fotografien', $A('b'), $d(45), $d(110), $d(45) . ' 19:00', 'galerie', '', 'Jonas Probe zeigt Fotografien aus der blauen Stunde – Abzüge als Unikate.', $R(4), [$R(5)], $W('b1', 'b2', 'b3'), null],
            ['Sammeln und Zeigen', 'Gruppenausstellung', $A('a', 'b', 'c', 'd', 'e'), $d(-200), $d(-140), $d(-200) . ' 19:00', 'galerie', '', 'Alle Künstlerinnen und Künstler der Galerie in einer Ausstellung über das Sammeln.', $R(10), [$R(0)], $W('a3', 'b3', 'c3', 'd2', 'e2'), null],
            ['Art Fair Musterstadt', '', $A('b', 'c'), $d(-300), $d(-297), '', 'messe', 'Stand 7 · Alte Halle Musterstadt', 'Fotografie und Zeichnung im Dialog.', $R(9), [], $W('b2', 'c3'), null],
            ['Objekte im Raum', 'Reliefs und Fundstücke', $A('d'), $d(-330), $d(-270), $d(-330) . ' 19:00', 'galerie', '', 'Theo Muster zeigt Reliefs aus drei Jahrzehnten.', $R(6), [$R(9)], $W('d1', 'd2', 'd3'), null],
            ['Weiche Systeme', 'Textile Malerei', $A('e'), $d(-430), $d(-375), $d(-430) . ' 19:00', 'galerie', '', 'Ida Platzhalter hängt genähte Bilder frei in den Raum.', $R(7), [], $W('e1', 'e2', 'e3'), null],
        ];
        foreach ($rows as [$title, $sub, $artists, $start, $end, $open, $ort, $ortText, $lead, $main, $views, $works, $press]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'untertitel' => $sub, 'kuenstler' => $artists, 'beginn' => $start, 'ende' => $end, 'eroeffnung' => $open,
                'ort' => $ort, 'ort_text' => $ortText, 'kurztext' => $lead, 'text' => $txt($lead), 'bild' => $main,
                'ansicht_1' => $views[0] ?? null, 'ansicht_2' => $views[1] ?? null, 'ansicht_3' => $views[2] ?? null, 'werke' => $works, 'pressetext' => $press, 'status' => 'published']);
            if ($err) $log('Ausstellung „' . $title . '“: ' . implode(' ', $err));
        }
        $out['ausstellungen'] = $t;
    }
    // ---------------------------------------------------------------- Anfragen (öffentliches Formular, Feld „werk“ wird vorausgefüllt)
    $t = galerie_demo_table([
        'handle' => 'anfragen', 'name' => 'Anfragen', 'singular' => 'Anfrage', 'icon' => 'envelope-simple',
        'description' => 'Anfragen zu Werken und an die Galerie (öffentliches Formular auf der Seite „Besuch“).',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'telefon', 'label' => 'Telefon (optional)', 'type' => 'tel', 'width' => 'half'],
            ['name' => 'werk', 'label' => 'Werk (optional)', 'type' => 'text', 'in_list' => true, 'width' => 'half', 'help' => 'Wird ausgefüllt, wenn Sie über „Anfrage zu diesem Werk“ kommen.'],
            ['name' => 'nachricht', 'label' => 'Ihre Nachricht', 'type' => 'textarea', 'required' => true],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Vielen Dank – wir melden uns in den nächsten Tagen (Demo: es meldet sich niemand).', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['anfragen'] = $t;
    $log(count($out) . ' Datentabellen angelegt (kuenstler, werke, ausstellungen, anfragen).');
    return $out;
}

/** Website-Einstellungen der Demo (Öffnungszeiten, Ausnahmen relativ zu heute, weiterer Ort, Anfragen) */
function galerie_demo_settings(): void
{
    $s = app()->settings;
    $day = fn(string $tag, string $von, string $bis) => ['tag' => $tag, 'von' => $von, 'bis' => $bis, 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];
    if (!setting('hours')) $s->set('hours', [$day('3', '12:00', '18:00'), $day('4', '12:00', '18:00'), $day('5', '12:00', '18:00'), $day('6', '11:00', '16:00')]);
    if (trim((string) setting('hours_note')) === '') $s->set('hours_note', 'und nach Vereinbarung');
    $d = fn(int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
    $s->set('hours_exceptions', [
        ['von' => $d(39), 'bis' => $d(44), 'text' => 'Aufbau der nächsten Ausstellung', 'zeiten' => '', 'geschlossen' => true],
        ['von' => $d(20), 'bis' => $d(23), 'text' => 'Kunstmesse – Galerie geöffnet', 'zeiten' => 'Do–So 14–18 Uhr', 'geschlossen' => false],
    ]);
    $s->set('venues', [['name' => 'Showroom (Lager)', 'karte' => '', 'adresse' => "Hinterhof 3\n12345 Musterstadt", 'zeiten' => "Nur nach Vereinbarung", 'hinweis' => 'Werke aus dem Lager zeigen wir gern persönlich.']]);
    $s->set('venue_name', 'Galerie am Musterplatz');
    $s->set('admission', 'Eintritt frei');
    $s->set('appointment_note', 'Besuche außerhalb der Öffnungszeiten und Atelierbesuche nach Vereinbarung.');
    $s->set('enquiry_mode', 'page');
    if ($p = Pages::bySlug('besuch')) $s->set('enquiry_page', (int) $p['id']);
}

// ------------------------------------------------------------------ Seiten

function galerie_demo_pages(array $img, array $tables, callable $log): void
{
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $title, string $text, string $eyebrow = '') => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text], ['spaceBottom' => 'small']);
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $R = fn(int $i) => $img['rooms'][$i] ?? null;
    $db = app()->db;
    $sort = (int) $db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL');

    // ---------------------------------------------------------------- Start
    $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => $sort,
        'meta_description' => 'Galerie Beispiel (Demo): zeitgenössische Kunst, aktuelle Ausstellungen, Künstlerinnen und Künstler, Werke und Besuch.'], [
        $B('exhibition_feature', ['variant' => 'bleed', 'exhibition' => '', 'venue' => 'galerie', 'eyebrow' => '', 'h1' => true, 'show_text' => true, 'button_label' => '', 'ratio' => '4:5', 'overlay' => 'gradient', 'height' => 'screen']),
        $B('exhibitions_list', ['variant' => 'cards', 'eyebrow' => '', 'title' => 'Auch jetzt und demnächst', 'intro' => '', 'show' => 'current_upcoming', 'venue' => '', 'limit' => 0,
            'auto_artist' => true, 'exclude_current' => true, 'show_image' => true, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => '', 'more_label' => 'Alle Ausstellungen', 'more_link' => '/ausstellungen']),
        $B('artworks', ['variant' => 'salon', 'eyebrow' => '', 'title' => 'Ausgewählte Werke', 'intro' => '', 'source' => 'all', 'ref' => '', 'only_available' => true, 'limit' => 7,
            'filters' => false, 'show_artist' => true, 'enquiry' => false, 'link_detail' => true, 'size' => 'm', 'more_label' => 'Zum Viewing Room', 'more_link' => '/viewing-room'], ['background' => 'muted']),
        $B('artists_index', ['variant' => 'list', 'eyebrow' => '', 'title' => 'Künstlerinnen und Künstler', 'intro' => '', 'selection' => 'all', 'order' => 'alpha', 'show_meta' => true, 'size' => 'm', 'ratio' => '3:4']),
        $B('visit', ['variant' => 'split', 'eyebrow' => '', 'title' => 'Besuch', 'intro' => '', 'show_today' => true, 'show_exceptions' => true, 'show_venues' => false, 'show_map' => true,
            'note' => '', 'button_label' => 'Besuch planen', 'button_link' => '/besuch', 'button2_label' => '', 'button2_link' => ''], ['background' => 'muted']),
    ]);
    // ---------------------------------------------------------------- Ausstellungen
    $page(['slug' => 'ausstellungen', 'title' => 'Ausstellungen', 'sort' => $sort + 1,
        'meta_description' => 'Aktuelle, kommende und vergangene Ausstellungen der Galerie Beispiel (Demo) – mit Archiv nach Jahren.'], [
        $hero('Ausstellungen', 'Was jetzt läuft, was kommt und was war – das Archiv nach Jahren.'),
        $B('exhibitions_list', ['variant' => 'list', 'eyebrow' => '', 'title' => '', 'intro' => '', 'show' => 'tabs', 'venue' => 'no_fairs', 'limit' => 0, 'auto_artist' => true, 'exclude_current' => true,
            'show_image' => true, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => '', 'more_label' => '', 'more_link' => ''], ['spaceTop' => 'small']),
        $B('exhibitions_list', ['variant' => 'fairs', 'eyebrow' => '', 'title' => 'Messen & Termine', 'intro' => 'Wo Sie die Galerie außerhalb ihrer Räume treffen.', 'show' => 'sections', 'venue' => 'messe', 'limit' => 0,
            'auto_artist' => true, 'exclude_current' => true, 'show_image' => false, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => '', 'more_label' => '', 'more_link' => ''], ['background' => 'muted', 'anchor' => 'messen']),
    ]);
    // ---------------------------------------------------------------- Künstler
    $page(['slug' => 'kuenstler', 'title' => 'Künstler', 'sort' => $sort + 2,
        'meta_description' => 'Die Künstlerinnen und Künstler der Galerie Beispiel (Demo) – alle Namen sind erfunden.'], [
        $hero('Künstlerinnen und Künstler', 'Fünf Positionen, langfristig begleitet. Alle Namen dieser Demo sind erfunden.'),
        $B('artists_index', ['variant' => 'list', 'eyebrow' => '', 'title' => '', 'intro' => '', 'selection' => 'all', 'order' => 'alpha', 'show_meta' => true, 'size' => 'm', 'ratio' => '3:4'], ['spaceTop' => 'small']),
        $B('artists_index', ['variant' => 'works', 'eyebrow' => '', 'title' => 'Im Programm', 'intro' => 'Je ein Werk – ein Klick führt zur Künstlerseite.', 'selection' => 'featured', 'order' => 'manual', 'show_meta' => false, 'size' => 's', 'ratio' => '4:5'], ['background' => 'muted']),
    ]);
    // ---------------------------------------------------------------- Werke
    $page(['slug' => 'werke', 'title' => 'Werke', 'sort' => $sort + 3,
        'meta_description' => 'Verfügbare und verkaufte Werke der Galerie Beispiel (Demo) mit Angaben und Anfrage.'], [
        $hero('Werke', 'Verfügbare Arbeiten – mit Anfrage direkt am Werk. Preise auf Anfrage oder wie angegeben (Demo-Werte).'),
        $B('artworks', ['variant' => 'grid', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'all', 'ref' => '', 'only_available' => false, 'limit' => 0,
            'filters' => true, 'show_artist' => true, 'enquiry' => true, 'link_detail' => true, 'size' => 'm', 'more_label' => '', 'more_link' => ''], ['spaceTop' => 'small']),
    ]);
    // ---------------------------------------------------------------- Viewing Room
    $page(['slug' => 'viewing-room', 'title' => 'Viewing Room', 'sort' => $sort + 4,
        'meta_description' => 'Online-Ausstellung der Galerie Beispiel (Demo): ein Werk je Bildschirm.'], [
        $hero('Viewing Room', 'Ein Werk je Bildschirm – nehmen Sie sich Zeit. Alle Abbildungen sind Platzhalter.', 'Online'),
        $B('artworks', ['variant' => 'room', 'eyebrow' => '', 'title' => '', 'intro' => '', 'source' => 'all', 'ref' => '', 'only_available' => true, 'limit' => 8,
            'filters' => false, 'show_artist' => true, 'enquiry' => true, 'link_detail' => true, 'size' => 'm', 'more_label' => '', 'more_link' => ''], ['spaceTop' => 'none']),
    ]);
    // ---------------------------------------------------------------- Die Galerie
    $page(['slug' => 'die-galerie', 'title' => 'Die Galerie', 'sort' => $sort + 5,
        'meta_description' => 'Über die Galerie Beispiel (Demo): Haltung, Räume, Leistungen und Team.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Die Galerie', 'title' => 'Ein Raum für *langsames* Sehen.', 'text' => 'Seit [Jahr] zeigt die Galerie Beispiel (Demo) zeitgenössische Malerei, Zeichnung, Fotografie und Objekte – mit wenigen Positionen, die wir lange begleiten.',
            'button_label' => 'Besuch planen', 'button_link' => '/besuch', 'button2_label' => 'Künstler', 'button2_link' => '/kuenstler', 'points' => '', 'image' => $R(10), 'ratio' => '4:5']),
        $B('media_text', ['variant' => 'auto', 'eyebrow' => 'Räume', 'title' => 'Zwei Räume, ein Kabinett', 'text' => '<p>Der große Raum misst [Maße] und hat Oberlicht. Daneben liegt das Kabinett – 4,20 Meter Wand für eine einzige Arbeit. [Eigene Beschreibung ergänzen.]</p>',
            'list' => "Oberlicht und Tageslicht\nBarrierefreier Zugang über den Hof\nShowroom nach Vereinbarung", 'button_label' => 'Zum Kabinett', 'button_link' => '/kabinett', 'button2_label' => '', 'button2_link' => '', 'image' => $R(1), 'ratio' => '4:3'], ['background' => 'muted']),
        $B('features', ['variant' => 'list', 'eyebrow' => 'Leistungen', 'title' => 'Was wir außerdem tun', 'intro' => '', 'size' => 'l', 'items' => [
            ['icon' => 'chat-circle-text', 'image' => null, 'title' => 'Beratung beim Sammeln', 'text' => 'Wir nehmen uns Zeit – auch für die erste Arbeit.', 'link_label' => '', 'link' => ''],
            ['icon' => 'image', 'image' => null, 'title' => 'Rahmung und Hängung', 'text' => 'Mit Werkstätten aus der Region, auf Wunsch bei Ihnen vor Ort.', 'link_label' => '', 'link' => ''],
            ['icon' => 'truck', 'image' => null, 'title' => 'Leihgaben', 'text' => 'Für Museen, Kunstvereine und Unternehmen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'files', 'image' => null, 'title' => 'Editionen', 'text' => 'Kleine Auflagen unserer Künstlerinnen und Künstler.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Geschichte', 'title' => 'Stationen (erfunden)', 'intro' => '', 'items' => [
            ['meta' => '[Jahr]', 'title' => 'Gründung', 'text' => 'Erste Ausstellung in einem Ladenlokal am Musterplatz.', 'icon' => ''],
            ['meta' => '[Jahr]', 'title' => 'Das Kabinett', 'text' => 'Ein zweiter, kleiner Raum für Einzelarbeiten.', 'icon' => ''],
            ['meta' => '[Jahr]', 'title' => 'Erste Messe', 'text' => 'Teilnahme an der Kunstmesse Beispielstadt.', 'icon' => ''],
        ]], ['background' => 'muted']),
        $B('quote', ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
            ['text' => 'Beispielzitat: Eine Galerie ist ein Ort, an dem man Kunst begegnet, bevor man sie versteht.', 'name' => 'Galerie Beispiel', 'role' => 'Leitgedanke (Demo)', 'image' => null],
        ]]),
    ]);
    // ---------------------------------------------------------------- Kabinett (besonderer Raum)
    $page(['slug' => 'kabinett', 'title' => 'Kabinett', 'sort' => $sort + 6,
        'meta_description' => 'Das Kabinett der Galerie Beispiel (Demo): 4,20 Meter Wand für eine einzige Arbeit.'], [
        $B('hero', ['variant' => 'type', 'eyebrow' => 'Kabinett', 'title' => '4,20 Meter für *eine* Arbeit.', 'text' => 'Das Kabinett ist der kleinste Raum der Galerie: eine Wand, ein Werk, wechselnd alle sechs Wochen. Oft entstehen die Arbeiten direkt vor Ort.',
            'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => $R(3), 'ratio' => '21:9']),
        $B('exhibition_feature', ['variant' => 'split', 'exhibition' => '', 'venue' => 'projektraum', 'eyebrow' => '', 'h1' => false, 'show_text' => true, 'button_label' => '', 'ratio' => '4:3', 'overlay' => 'gradient', 'height' => 'screen'], ['background' => 'muted']),
        $B('exhibitions_list', ['variant' => 'timeline', 'eyebrow' => '', 'title' => 'Bisher im Kabinett', 'intro' => '', 'show' => 'past', 'venue' => 'projektraum', 'limit' => 0, 'auto_artist' => true, 'exclude_current' => true,
            'show_image' => true, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => 'Das Kabinett ist neu – hier erscheint künftig sein Archiv.', 'more_label' => '', 'more_link' => '']),
    ]);
    // ---------------------------------------------------------------- Besuch (Anfrageformular mit Anker „anfrage“)
    $page(['slug' => 'besuch', 'title' => 'Besuch', 'sort' => $sort + 7,
        'meta_description' => 'Öffnungszeiten, Adresse, abweichende Zeiten und Anfrage – Galerie Beispiel (Demo).'], [
        $hero('Besuch', 'Wir freuen uns auf Sie. Der Eintritt ist frei.'),
        $B('visit', ['variant' => 'columns', 'eyebrow' => '', 'title' => '', 'intro' => '', 'show_today' => true, 'show_exceptions' => true, 'show_venues' => true, 'show_map' => true,
            'note' => 'Barrierefreier Zugang über den Hof (Beispielangabe).', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''], ['spaceTop' => 'small']),
        $B('contact', ['eyebrow' => 'Anfrage', 'title' => 'Schreiben Sie uns', 'intro' => 'Zu einem Werk, einer Ausstellung oder einem Termin.', 'show_hours' => false, 'show_map' => false,
            'form_table' => 'anfragen', 'form_title' => '', 'submit_label' => '', 'note' => 'Demo: Anfragen landen in der Tabelle „Anfragen“.'], ['anchor' => 'anfrage', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Detailseiten (Vorlagen je Tabelle)
    $tpl = fn(string $handle, string $title, array $blocks) => Pages::create(['slug' => '_vorlage-' . $handle, 'title' => $title, 'type' => 'template', 'template_for' => $handle, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks($blocks));
    $details = [
        'kuenstler' => $tpl('kuenstler', 'Künstler – Detailseite', [
            $B('artist_profile', ['variant' => 'split', 'back_label' => 'Alle Künstler', 'back_link' => '/kuenstler', 'show_portrait' => true, 'show_enquiry' => true, 'enquiry_label' => '']),
            $B('artworks', ['variant' => 'masonry', 'eyebrow' => '', 'title' => 'Werke', 'intro' => '', 'source' => 'auto', 'ref' => '', 'only_available' => false, 'limit' => 0,
                'filters' => false, 'show_artist' => true, 'enquiry' => true, 'link_detail' => true, 'size' => 'm', 'more_label' => '', 'more_link' => ''], ['background' => 'muted']),
            $B('exhibitions_list', ['variant' => 'list', 'eyebrow' => '', 'title' => 'Ausstellungen', 'intro' => '', 'show' => 'sections', 'venue' => '', 'limit' => 0, 'auto_artist' => true, 'exclude_current' => true,
                'show_image' => true, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => '', 'more_label' => '', 'more_link' => '']),
        ]),
        'ausstellungen' => $tpl('ausstellungen', 'Ausstellungen – Detailseite', [
            $B('exhibition_detail', ['variant' => 'split', 'back_label' => 'Alle Ausstellungen', 'back_link' => '/ausstellungen', 'show_views' => true, 'show_works' => true, 'works_title' => '', 'press_label' => '']),
            $B('exhibitions_list', ['variant' => 'cards', 'eyebrow' => '', 'title' => 'Weitere Ausstellungen', 'intro' => '', 'show' => 'current_upcoming', 'venue' => 'no_fairs', 'limit' => 3, 'auto_artist' => false, 'exclude_current' => true,
                'show_image' => true, 'size' => 'm', 'ratio' => '4:3', 'empty_text' => '', 'more_label' => 'Archiv', 'more_link' => '/ausstellungen'], ['background' => 'muted']),
        ]),
        'werke' => $tpl('werke', 'Werke – Detailseite', [
            $B('artwork_detail', ['back_label' => 'Alle Werke', 'back_link' => '/werke', 'show_more' => true, 'more_limit' => 4]),
        ]),
    ];
    foreach ($details as $handle => $pid) if ($t = Tables::find($handle)) Tables::setDetailPage($t, $pid);
    $log('Seiten angelegt: Start, Ausstellungen, Künstler, Werke, Viewing Room, Die Galerie, Kabinett, Besuch + 3 Detailseiten.');
}
