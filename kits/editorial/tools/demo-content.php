<?php
/**
 * Demo-Ausgabe des Themes „editorial“: „Kulturnetz Musterland (Demo)“ – ein frei erfundener Verband von Kulturvereinen.
 * Legt an: Datentabellen (Nachrichten mit Rubriken und Detailseite, Termine mit Wiederholungen, Personen, zwei öffentliche Formulare),
 * Rubrikseiten (Aktuelles, Termine, Magazin mit Langform-Geschichte, Menschen, Verband, Mitmachen), Detailvorlagen und
 * abstrakte, typografische Platzhalterbilder (GD + Playfair Display, OFL) – keine Fotos, keine echten Personen, Firmen oder Adressen.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/editorial/tools/demo.php [--force|--remove]
 * Alles ist als Demo gekennzeichnet (Tabellen ed_*, Medien-Schlagwort „editorial-demo“) und lässt sich wieder entfernen.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const EDITORIAL_DEMO_TAG = 'editorial-demo';
const EDITORIAL_DEMO_PAGES = ['aktuelles', 'termine', 'magazin', 'menschen', 'verband', 'mitmachen'];

/** Demo-Ausgabe anlegen. $force: vorhandene Demo vorher entfernen. */
function editorial_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    if (Tables::find('ed_news') && !$force) {
        $log('Die Demo-Ausgabe ist schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    if ($force) editorial_demo_remove($log);

    foreach (['phone' => '0123 4567-0', 'street' => 'Musterweg 12', 'zip' => '12345', 'city' => 'Musterstadt'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }
    if (!setting('social')) {
        app()->settings->set('social', [['label' => 'Mastodon', 'url' => 'https://example.org/@kulturnetz'], ['label' => 'Newsletter-Archiv', 'url' => 'https://example.org/newsletter']]);
    }
    $img = editorial_demo_images($log);
    $pdf = editorial_demo_pdf($log);
    $tables = editorial_demo_tables($img, $log);
    editorial_demo_pages($img, $pdf, $tables, $log);
    editorial_demo_heroes($log);
    \Core\PageCache::clear();
    $log('Fertig: Ausgabe mit ' . count(EDITORIAL_DEMO_PAGES) . ' Rubriken, ' . count($tables) . ' Datentabellen und ' . count($img['all']) . ' Bildern.');
    return true;
}

/** Demo-Seiten, Demo-Tabellen (ed_*) und Demo-Medien entfernen; die Startseite wird auf einen schlichten Aufmacher zurückgesetzt */
function editorial_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = [];
    foreach (EDITORIAL_DEMO_PAGES as $slug) {
        foreach ($db->fetchAll("SELECT id FROM pages WHERE path = ? OR path LIKE ?", [$slug, $slug . '/%']) as $r) $ids[] = (int) $r['id'];
    }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE slug LIKE '_vorlage-ed-%' OR path = 'hero-varianten'") as $r) $ids[] = (int) $r['id'];
    foreach (array_unique($ids) as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (['ed_news', 'ed_termine', 'ed_newsletter', 'ed_mitglied', 'ed_personen'] as $h) {
        if ($t = Tables::find($h)) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . EDITORIAL_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query("DELETE FROM media_collections WHERE name = 'Editorial (Demo)'");
    if ($home = Pages::home()) {
        editorial_demo_set_blocks((int) $home['id'], [editorial_demo_block('hero', ['variant' => 'centered', 'eyebrow' => editorial_name(), 'title' => '[Schlagzeile der Startseite]',
            'text' => '[Kurzer Vorspann: Wer sind Sie, worüber berichten Sie?]', 'ratio' => '21:9'])]);
    }
    $log('Demo-Ausgabe entfernt (' . count($ids) . ' Seiten).');
}

function editorial_demo_block(string $type, array $data, array $tunes = []): array
{
    return ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
}

/** Blöcke einer vorhandenen Seite ersetzen (Entwurf + veröffentlicht) */
function editorial_demo_set_blocks(int $id, array $blocks, array $fields = []): void
{
    $json = json_encode(['time' => (int) (microtime(true) * 1000), 'blocks' => Pages::sanitizeBlocks($blocks), 'version' => '2.31'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    app()->db->update('pages', $fields + ['content_draft' => $json, 'content_published' => $json, 'status' => 'published', 'updated_at' => now()], 'id = :id', ['id' => $id]);
}

// ------------------------------------------------------------------ Bilder (abstrakt, typografisch, mit GD erzeugt)

function editorial_demo_images(callable $log): array
{
    $col = Media::createCollection('Editorial (Demo)', 'Abstrakte, typografische Platzhalterbilder der Demo-Ausgabe – automatisch erzeugt, ohne Personen, Orte oder Marken.');
    $P = ['ink' => [21, 19, 15], 'paper' => [242, 239, 232], 'red' => [180, 35, 24], 'ochre' => [214, 160, 58], 'blue' => [43, 63, 214],
        'green' => [45, 106, 78], 'pink' => [174, 20, 89], 'sand' => [226, 214, 190], 'teal' => [14, 92, 107], 'cream' => [251, 250, 247]];
    // [Titel (Alt-Text), Motiv, Hintergrund, Vordergrund, Akzent, Text/Ziffer, Breite, Höhe]
    $motifs = [
        ['Großes A in Druckschwarz mit rotem Kreis auf Papierton', 'letter', 'paper', 'ink', 'red', 'A'],
        ['Punktraster in Ocker, zur Mitte hin größer werdend', 'halftone', 'ink', 'ochre', 'paper', ''],
        ['Diagonale Bänder in Blau und Papierton', 'bands', 'paper', 'blue', 'ink', ''],
        ['Die Zahl 100 in schwarzer Serifenschrift auf Sand', 'number', 'sand', 'ink', 'red', '100'],
        ['Raster aus Quadraten in Petrol', 'grid', 'paper', 'teal', 'ink', ''],
        ['Konzentrische Bögen in Grün und Papierton', 'arches', 'paper', 'green', 'ink', ''],
        ['Zwei große Anführungszeichen in Magenta', 'quote', 'cream', 'pink', 'ink', '„“'],
        ['Collage aus Rechtecken und Kreisen in Rot, Ocker und Schwarz', 'collage', 'paper', 'red', 'ochre', ''],
        ['Großes K in Blau, angeschnitten', 'letter', 'cream', 'blue', 'ochre', 'K'],
        ['Wellenlinien in Magenta auf dunklem Grund', 'waves', 'ink', 'pink', 'paper', ''],
        ['Kaufmanns-Und in Ocker auf Schwarz', 'letter', 'ink', 'ochre', 'red', '&'],
        ['Die Zahl 240 in Rot auf Papierton', 'number', 'paper', 'red', 'ink', '240'],
        ['Titelbild: Bühnenlicht als Kreise in Ocker auf Schwarz', 'spot', 'ink', 'ochre', 'red', '', 2400, 1350],
        ['Breitbild: Streifen in Petrol, Sand und Schwarz', 'bands', 'sand', 'teal', 'ink', '', 2400, 1030],
    ];
    $all = [];
    foreach ($motifs as $i => $m) {
        [$title, $kind, $bg, $fg, $acc, $txt] = $m;
        $file = tempnam(sys_get_temp_dir(), 'ed') . '.jpg';
        editorial_demo_draw($file, $kind, $P[$bg], $P[$fg], $P[$acc], $txt, $i, (int) ($m[6] ?? 1800), (int) ($m[7] ?? 1200));
        [$media, $err] = Media::import($file, sprintf('editorial-%02d.jpg', $i + 1), 'Abstrakte Grafik: ' . $title . ' (Platzhalterbild)',
            ['title' => $title, 'tags' => EDITORIAL_DEMO_TAG, 'collection' => $col, 'credit' => 'Platzhalter, automatisch erzeugt']);
        @unlink($file);
        if ($media) $all[] = (int) $media['id'];
        elseif ($err) $log('Bild ' . ($i + 1) . ': ' . $err);
    }
    // Porträts: Monogramme (keine Gesichter) im Hochformat 4:5
    $people = [['KB', 'red'], ['AM', 'blue'], ['RP', 'green'], ['SE', 'ochre'], ['JP', 'teal'], ['MV', 'pink'], ['TE', 'ink'], ['CS', 'red']];
    $portraits = [];
    foreach ($people as $i => [$ini, $c]) {
        $file = tempnam(sys_get_temp_dir(), 'ed') . '.jpg';
        editorial_demo_draw($file, 'monogram', $P[$i % 2 ? 'sand' : 'paper'], $P[$c], $P['ink'], $ini, 50 + $i, 1200, 1500);
        [$media] = Media::import($file, sprintf('editorial-portrait-%02d.jpg', $i + 1), 'Monogramm ' . $ini . ' (Platzhalter statt Porträtfoto)',
            ['title' => 'Monogramm ' . $ini, 'tags' => EDITORIAL_DEMO_TAG, 'credit' => 'Platzhalter, automatisch erzeugt']);
        @unlink($file);
        if ($media) $portraits[] = (int) $media['id'];
    }
    $log(count($all) . ' Bilder und ' . count($portraits) . ' Monogramme erzeugt.');
    return ['all' => $all, 'people' => $portraits, 'collection' => $col];
}

/** Abstrakte, typografische Komposition (JPEG) */
function editorial_demo_draw(string $file, string $kind, array $bg, array $fg, array $acc, string $txt, int $seed, int $w = 1800, int $h = 1200): void
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imageantialias($im, true);
    $c = fn(array $rgb, int $alpha = 0) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    imagefilledrectangle($im, 0, 0, $w, $h, $c($bg));
    $font = dirname(__DIR__) . '/fonts/PlayfairDisplay_900Black.ttf';
    $italic = dirname(__DIR__) . '/fonts/PlayfairDisplay_400Regular_Italic.ttf';
    $hasFont = is_file($font) && function_exists('imagettftext');
    $text = function (string $s, float $size, int $x, int $y, array $col, int $alpha = 0, ?string $f = null) use ($im, $c, $font, $hasFont) {
        if (!$hasFont) { imagestring($im, 5, $x, $y - 20, $s, $c($col, $alpha)); return; }
        imagettftext($im, $size, 0, $x, $y, $c($col, $alpha), $f ?? $font, $s);
    };
    $box = fn(string $s, float $size, ?string $f = null) => $hasFont ? imagettfbbox($size, 0, $f ?? $font, $s) : [0, 0, strlen($s) * 9, 0, 0, 0, 0, -15];
    mt_srand(9100 + $seed);
    // feines Papierkorn
    for ($n = 0; $n < $w * $h / 90; $n++) imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), $c(array_map(fn($v) => max(0, min(255, $v + mt_rand(-14, 14))), $bg), 60));
    switch ($kind) {
        case 'letter':
            imagefilledellipse($im, (int) ($w * .68), (int) ($h * .42), (int) ($h * .78), (int) ($h * .78), $c($acc, 0));
            $size = $h * 1.02;
            $b = $box($txt, $size);
            $text($txt, $size, (int) ($w * .52 - ($b[2] - $b[0]) / 2), (int) ($h * 1.02), $fg);
            imagesetthickness($im, 6);
            imageline($im, (int) ($w * .06), (int) ($h * .12), (int) ($w * .3), (int) ($h * .12), $c($fg));
            break;
        case 'number':
            $size = $h * .62;
            $b = $box($txt, $size);
            $text($txt, $size, (int) (($w - ($b[2] - $b[0])) / 2), (int) ($h * .78), $fg);
            imagesetthickness($im, 10);
            imageline($im, (int) ($w * .08), (int) ($h * .88), (int) ($w * .92), (int) ($h * .88), $c($acc));
            imagesetthickness($im, 3);
            imageline($im, (int) ($w * .08), (int) ($h * .91), (int) ($w * .92), (int) ($h * .91), $c($acc));
            break;
        case 'quote':
            $size = $h * .9;
            $text('„', $size, (int) ($w * .08), (int) ($h * .7), $fg);
            $text('“', $size, (int) ($w * .5), (int) ($h * 1.2), $acc, 90);
            break;
        case 'monogram':
            imagefilledellipse($im, (int) ($w * .5), (int) ($h * .44), (int) ($w * .82), (int) ($w * .82), $c($fg, 0));
            $size = $w * .3;
            $b = $box($txt, $size, is_file($italic) ? $italic : null);
            $text($txt, $size, (int) (($w - ($b[2] - $b[0])) / 2), (int) ($h * .44 + $size / 2.6), $bg, 0, is_file($italic) ? $italic : null);
            imagesetthickness($im, 5);
            imageline($im, (int) ($w * .2), (int) ($h * .9), (int) ($w * .8), (int) ($h * .9), $c($acc));
            break;
        case 'halftone':
            for ($x = 30; $x < $w; $x += 44) {
                for ($y = 30; $y < $h; $y += 44) {
                    $d = hypot(($x - $w * .62) / $w, ($y - $h * .5) / $h);
                    $r = (int) max(3, 40 * (1 - $d * 1.7));
                    imagefilledellipse($im, $x, $y, $r, $r, $c($fg));
                }
            }
            break;
        case 'bands':
            for ($k = -4; $k < 10; $k++) {
                $x = $k * (int) ($w / 7);
                imagefilledpolygon($im, [$x, $h, $x + (int) ($w / 12), $h, $x + (int) ($w / 12) + (int) ($h * .7), 0, $x + (int) ($h * .7), 0], $c($k % 2 ? $fg : $acc, $k % 3 ? 0 : 60));
            }
            break;
        case 'grid':
            $s = (int) ($h / 7);
            for ($gx = 0; $gx * $s < $w; $gx++) {
                for ($gy = 0; $gy < 7; $gy++) {
                    $r = mt_rand(0, 5);
                    if ($r === 0) continue;
                    $x = $gx * $s + 6; $y = $gy * $s + 6;
                    if ($r === 1) imagefilledellipse($im, $x + (int) ($s / 2) - 6, $y + (int) ($s / 2) - 6, $s - 12, $s - 12, $c($acc));
                    else imagefilledrectangle($im, $x, $y, $x + $s - 12, $y + $s - 12, $c($fg, $r > 3 ? 70 : 0));
                }
            }
            break;
        case 'arches':
            for ($k = 7; $k >= 0; $k--) {
                $r = (int) ($h * .25 + $k * $h * .22);
                imagefilledellipse($im, (int) ($w * .5), $h, $r, $r, $c($k % 2 ? $bg : $fg, $k % 2 ? 0 : 10 + $k * 6));
            }
            imagefilledellipse($im, (int) ($w * .5), $h, (int) ($h * .2), (int) ($h * .2), $c($acc));
            break;
        case 'collage':
            imagefilledrectangle($im, (int) ($w * .08), (int) ($h * .14), (int) ($w * .46), (int) ($h * .86), $c($fg));
            imagefilledellipse($im, (int) ($w * .62), (int) ($h * .46), (int) ($h * .62), (int) ($h * .62), $c($acc));
            imagefilledrectangle($im, (int) ($w * .52), (int) ($h * .62), (int) ($w * .92), (int) ($h * .78), $c([21, 19, 15]));
            imagesetthickness($im, 4);
            imagerectangle($im, (int) ($w * .3), (int) ($h * .3), (int) ($w * .7), (int) ($h * .7), $c([21, 19, 15]));
            break;
        case 'waves':
            imagesetthickness($im, 7);
            for ($k = 0; $k < 11; $k++) {
                $py = null;
                for ($x = 0; $x <= $w; $x += 12) {
                    $y = (int) ($h * .16 + $k * $h * .07 + sin($x / 170 + $k * .6) * 36);
                    if ($py !== null) imageline($im, $x - 12, $py, $x, $y, $c($k % 4 ? $fg : $acc));
                    $py = $y;
                }
            }
            break;
        case 'spot':
            foreach ([[.3, .45, .9, 70], [.62, .4, .7, 40], [.78, .6, .5, 20]] as [$fx, $fy, $fr, $a]) {
                imagefilledellipse($im, (int) ($w * $fx), (int) ($h * $fy), (int) ($h * $fr), (int) ($h * $fr), $c($fg, $a));
            }
            imagefilledellipse($im, (int) ($w * .62), (int) ($h * .4), (int) ($h * .16), (int) ($h * .16), $c($acc));
            imagesetthickness($im, 4);
            imageline($im, 0, (int) ($h * .86), $w, (int) ($h * .86), $c($fg, 50));
            break;
    }
    imagejpeg($im, $file, 84);
    unset($im);
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function editorial_demo_pdf(callable $log): ?int
{
    $text = 'Jahresbericht 2025 - Beispiel-PDF (Demo). Frei erfundener Inhalt.';
    $stream = "BT /F1 18 Tf 72 760 Td ($text) Tj ET";
    $objs = ['<</Type/Catalog/Pages 2 0 R>>', '<</Type/Pages/Kids[3 0 R]/Count 1>>',
        '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
        '<</Length ' . strlen($stream) . ">>stream\n$stream\nendstream", '<</Type/Font/Subtype/Type1/BaseFont/Times-Roman>>'];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    $pdf .= 'trailer<</Size ' . (count($objs) + 1) . "/Root 1 0 R>>\nstartxref\n$xref\n%%EOF\n";
    $file = tempnam(sys_get_temp_dir(), 'ed') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'jahresbericht-2025-beispiel.pdf', '', ['title' => 'Jahresbericht 2025 (Beispiel)', 'tags' => EDITORIAL_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function editorial_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

/** Veröffentlichungsdatum setzen (Beispiel: „vor n Tagen“) */
function editorial_demo_published(array $t, int $id, int $daysAgo, string $time = '09:30'): void
{
    $at = date('Y-m-d', strtotime("-$daysAgo days")) . ' ' . $time . ':00';
    Tables::db($t)->update($t['table'], ['published_at' => $at, 'created_at' => $at, 'updated_at' => $at], 'id = :id', ['id' => $id]);
}

function editorial_demo_tables(array $img, callable $log): array
{
    $out = [];
    $I = fn(int $n) => $img['all'][$n % max(1, count($img['all']))] ?? null;

    // ---------------------------------------------------------------- Personen (Redaktion, Vorstand, Geschäftsstelle)
    $t = editorial_demo_table([
        'handle' => 'ed_personen', 'name' => 'Menschen (Demo)', 'singular' => 'Person', 'icon' => 'users',
        'description' => 'Beispiel-Tabelle der Demo-Ausgabe – frei erfundene Personen mit Monogrammen statt Fotos.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'funktion', 'label' => 'Funktion', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'bereich', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half',
                'options' => "vorstand=Vorstand\nredaktion=Redaktion\ngeschaeftsstelle=Geschäftsstelle\nreferate=Referate"],
            ['name' => 'kurzbio', 'label' => 'Kurzbiografie', 'type' => 'textarea'],
            ['name' => 'text', 'label' => 'Porträt', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email'],
        ],
        'settings' => ['route' => 'menschen', 'title_field' => 'name', 'image_field' => 'bild', 'description_field' => 'kurzbio', 'schema_type' => 'Person',
            'sort_field' => 'sort', 'sort_dir' => 'asc', 'workflow' => 0],
    ], $log);
    $people = [];
    if ($t) {
        $rows = [
            ['Kim Beispiel', 'Chefredaktion', 'redaktion', 'Leitet die Redaktion des Kulturnetzes und schreibt über Förderung, Ehrenamt und alles, was Vereine bewegt.'],
            ['Alex Muster', 'Vorsitz', 'vorstand', 'Seit sechs Jahren im Vorstand, davor Kassenwart eines Chors. Setzt sich für einfache Förderanträge ein.'],
            ['Robin Probe', 'Referat Bildung', 'referate', 'Organisiert Workshops, die Sommerakademie und die Fortbildungsreihe für Ehrenamtliche.'],
            ['Sam Exempel', 'Geschäftsführung', 'geschaeftsstelle', 'Verantwortet Finanzen, Mitgliederbetreuung und die Zusammenarbeit mit Kommunen.'],
            ['Jo Platzhalter', 'Redaktion', 'redaktion', 'Schreibt Reportagen aus den Vereinen und betreut den Veranstaltungskalender.'],
            ['Mika Vorlage', 'Stellvertretender Vorsitz', 'vorstand', 'Kümmert sich um Jugendarbeit und die Vernetzung junger Kulturinitiativen.'],
            ['Toni Entwurf', 'Referat Vereine', 'referate', 'Erste Anlaufstelle für Fragen zu Satzung, Versicherung und Vereinsrecht.'],
            ['Charlie Skizze', 'Assistenz der Geschäftsstelle', 'geschaeftsstelle', 'Plant Termine, Räume und die Mitgliederversammlung – und weiß, wo alles steht.'],
        ];
        foreach ($rows as $i => [$name, $fn, $area, $bio]) {
            [$id] = Entries::save($t, null, ['name' => $name, 'funktion' => $fn, 'bereich' => $area, 'kurzbio' => $bio, 'bild' => $img['people'][$i] ?? null,
                'email' => 'person' . ($i + 1) . '@example.org', 'status' => 'published',
                'text' => '<p>' . e($bio) . ' Dieses Porträt ist frei erfunden und zeigt, wie Personen im Kit „Editorial“ erscheinen.</p><h3>Im Kulturnetz seit</h3><p>' . (2014 + $i) . ' – zuerst ehrenamtlich, heute im Team.</p>']);
            $people[] = $id;
        }
        $out['personen'] = $t;
    }

    // ---------------------------------------------------------------- Nachrichten (Rubriken, Autor = Person, Detailseite)
    $t = editorial_demo_table([
        'handle' => 'ed_news', 'name' => 'Nachrichten (Demo)', 'singular' => 'Nachricht', 'icon' => 'newspaper',
        'description' => 'Beispiel-Tabelle der Demo-Ausgabe – frei erfundene Meldungen mit Rubriken.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'rubrik', 'label' => 'Rubrik', 'type' => 'select', 'in_list' => true, 'width' => 'half',
                'options' => "verband=Verband\nkultur=Kultur\nbildung=Bildung\nvereine=Aus den Vereinen\nservice=Service"],
            ['name' => 'autor', 'label' => 'Autor', 'type' => 'relation', 'target' => 'ed_personen', 'width' => 'half'],
            ['name' => 'teaser', 'label' => 'Vorspann', 'type' => 'textarea', 'help' => 'Ein bis zwei Sätze – erscheint in Listen und über dem Artikel.'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
            ['name' => 'bildunterschrift', 'label' => 'Bildunterschrift', 'type' => 'text', 'width' => 'half'],
        ],
        'settings' => ['route' => 'aktuelles', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'teaser', 'schema_type' => 'NewsArticle',
            'sort_field' => 'published_at', 'sort_dir' => 'desc', 'workflow' => 1],
    ], $log);
    if ($t) {
        $long = '<p>Kleine Kulturvereine tragen einen großen Teil des kulturellen Lebens im Land – oft mit wenig Geld und viel Ehrenamt. Die neue Förderrunde soll genau dort ansetzen: bei Proberäumen, Instrumenten, Honoraren und bei der Frage, wie Vereine neue Mitglieder gewinnen.</p>'
            . '<p>Der Vorstand hat das Programm in den vergangenen Monaten gemeinsam mit Vereinen aus allen Regionen entwickelt. Herausgekommen ist ein Verfahren, das mit einem zweiseitigen Antrag auskommt.</p>'
            . '<h3>Wer kann sich bewerben?</h3><p>Antragsberechtigt sind alle Mitgliedsvereine des Kulturnetzes mit weniger als 250 Mitgliedern. Gefördert werden Vorhaben zwischen 500 und 8.000 Euro; ein Eigenanteil ist nicht nötig.</p>'
            . '<ul><li>Anschaffungen wie Instrumente, Technik oder Bühnenelemente</li><li>Honorare für Workshops und Gastkünstlerinnen und -künstler</li><li>Projekte, die neue Zielgruppen erreichen</li></ul>'
            . '<p><strong>„Wir wollten ein Programm, das man an einem Abend beantragen kann – nach der Probe, am Küchentisch“</strong>, sagt die Vorsitzende (fiktiv).</p>'
            . '<h3>Was wird gefördert?</h3><p>Im Mittelpunkt stehen Vorhaben, die ohne Förderung nicht zustande kämen. Besonders willkommen sind Projekte mehrerer Vereine und Angebote für Kinder und Jugendliche.</p>'
            . '<h3>So läuft das Verfahren</h3><p>Anträge können bis zum Ende des Quartals eingereicht werden. Eine Jury aus Ehrenamtlichen entscheidet innerhalb von sechs Wochen; die Mittel werden kurz danach ausgezahlt.</p>'
            . '<p>Fragen beantwortet das Referat Vereine telefonisch oder per E-Mail. Alle Zahlen und Namen in diesem Beitrag sind frei erfunden (Demo).</p>';
        $short = fn(string $a, string $b) => '<p>' . $a . '</p><p>' . $b . '</p><p>Dieser Beitrag ist frei erfunden und zeigt, wie Nachrichten im Kit „Editorial“ gesetzt werden.</p>';
        $news = [
            ['Neue Förderrunde: 180.000 Euro für kleine Kulturvereine', 'verband', 0, 'Zweiseitiger Antrag, Entscheidung in sechs Wochen: Das Kulturnetz startet ein Programm für Vereine mit weniger als 250 Mitgliedern.', $long, 0, 'Das neue Förderprogramm setzt bei Proberäumen, Instrumenten und Honoraren an.', 1],
            ['Die Nacht der offenen Proberäume kehrt zurück', 'kultur', 4, 'Chöre, Bands und Theatergruppen öffnen an einem Abend ihre Türen – Besucherinnen und Besucher dürfen mitsingen, mitspielen, mitmachen.', $short('Nach zwei Jahren Pause lädt das Kulturnetz wieder zur Nacht der offenen Proberäume ein. Rund dreißig Vereine haben ihre Teilnahme bereits zugesagt.', 'Ein Shuttlebus verbindet die Proberäume in der Innenstadt; der Eintritt ist frei.'), 1, '', 4],
            ['Workshopreihe: Pressearbeit für Ehrenamtliche', 'bildung', 6, 'Wie schreibe ich eine Pressemitteilung, die gelesen wird? Drei Abende mit Übungen, Beispielen und Zeit für eigene Texte.', $short('Die Reihe richtet sich an alle, die in ihrem Verein für Öffentlichkeitsarbeit zuständig sind – oder es werden wollen.', 'Die Teilnahme ist für Mitgliedsvereine kostenlos; die Plätze sind begrenzt.'), 2, '', 2],
            ['Musikverein Beispielfeld feiert 100 Jahre – mit einem Konzert für alle', 'vereine', 9, 'Vom Blasorchester zur Big Band: Der fiktive Musikverein Beispielfeld blickt auf ein Jahrhundert zurück und lädt zum Jubiläumskonzert.', $short('Gegründet als Blasorchester, heute mit Big Band, Jugendensemble und Musikschule – der Verein hat sich immer wieder neu erfunden.', 'Das Jubiläumskonzert findet unter freiem Himmel statt; bei Regen in der Stadthalle (fiktiv).'), 3, 'Hundert Jahre Vereinsgeschichte – gesetzt in Serifen.', 4],
            ['Datenschutz im Verein: die fünf häufigsten Fragen', 'service', 12, 'Mitgliederlisten, Fotos von Veranstaltungen, Newsletter: Was Vereine beachten müssen – kurz und verständlich erklärt.', $short('Die meisten Fragen, die das Referat Vereine erreichen, drehen sich um Fotos und Mitgliederdaten.', 'Eine Checkliste zum Herunterladen fasst die wichtigsten Punkte zusammen.'), 4, '', 6],
            ['Mitgliederversammlung beschließt neue Satzung', 'verband', 16, 'Mit großer Mehrheit hat die Mitgliederversammlung eine überarbeitete Satzung verabschiedet. Neu: digitale Abstimmungen und ein Jugendbeirat.', $short('Die neue Satzung tritt nach der Eintragung ins Vereinsregister in Kraft.', 'Der Jugendbeirat wird im Frühjahr zum ersten Mal gewählt.'), 5, '', 1],
            ['Theatergruppe Nordufer gewinnt Landespreis', 'vereine', 21, 'Die fiktive Theatergruppe Nordufer überzeugt die Jury mit einem Stück über Nachbarschaft – gespielt in einem leeren Ladenlokal.', $short('Die Jury lobte vor allem den Mut, an einem ungewöhnlichen Ort zu spielen.', 'Das Preisgeld fließt in eine neue Lichtanlage.'), 6, 'Große Anführungszeichen – das Stück lebt von Dialogen.', 4],
            ['Leitfaden: Barrierearme Veranstaltungen planen', 'service', 27, 'Von der Einladung bis zur Toilette: Ein neuer Leitfaden zeigt, wie Vereine ihre Veranstaltungen für mehr Menschen zugänglich machen.', $short('Der Leitfaden ist in einfacher Sprache verfasst und enthält Checklisten für kleine und große Veranstaltungen.', 'Er steht als PDF zum Herunterladen bereit.'), 7, '', 6],
            ['Jugendkunstschule öffnet Werkstatt am Wochenende', 'bildung', 34, 'Drucken, Nähen, Löten: Ab sofort ist die Werkstatt der fiktiven Jugendkunstschule auch samstags geöffnet.', $short('Das Angebot richtet sich an Jugendliche zwischen 12 und 18 Jahren.', 'Eine Anmeldung ist nicht nötig.'), 8, '', 2],
            ['Chorfestival 2026: Anmeldung gestartet', 'kultur', 41, 'Drei Tage, zwölf Bühnen, ein Abschlusskonzert: Chöre aller Größen können sich ab sofort für das Chorfestival anmelden.', $short('Das Festival ist offen für alle Chöre – vom Kinderchor bis zum Seniorenensemble.', 'Anmeldeschluss ist in sechs Wochen.'), 9, '', 4],
            ['Geschäftsstelle zieht um', 'verband', 55, 'Neue Räume, mehr Platz für Beratung: Die Geschäftsstelle des Kulturnetzes ist umgezogen. Telefon und E-Mail bleiben gleich.', $short('In den neuen Räumen gibt es einen Seminarraum, den Mitgliedsvereine kostenlos nutzen können.', 'Die Adresse finden Sie wie immer im Fußbereich dieser Seite.'), 10, '', 3],
            ['Rückblick: Sommerakademie mit 240 Teilnehmenden', 'bildung', 70, 'Eine Woche lang Kurse, Konzerte und Gespräche – die Sommerakademie war so gut besucht wie nie.', $short('Besonders gefragt waren die Kurse zu Bühnentechnik und Stimmbildung.', 'Die nächste Sommerakademie ist bereits in Planung.'), 11, 'Zweihundertvierzig – eine Zahl als Bild.', 2],
        ];
        foreach ($news as [$title, $cat, $days, $teaser, $text, $imgIdx, $cap, $author]) {
            [$id, $err] = Entries::save($t, null, ['titel' => $title, 'rubrik' => $cat, 'teaser' => $teaser, 'text' => $text, 'bild' => $I($imgIdx),
                'bildunterschrift' => $cap, 'autor' => $people[$author] ?? null, 'status' => 'published']);
            if ($err) { $log('Nachricht „' . $title . '“: ' . implode(' ', $err)); continue; }
            editorial_demo_published($t, (int) $id, $days, sprintf('%02d:%02d', 7 + $days % 11, ($days * 7) % 60));
        }
        $out['news'] = $t;
    }

    // ---------------------------------------------------------------- Termine (Kalender mit Wiederholung, mehrtägig, Kategorien)
    $t = editorial_demo_table([
        'handle' => 'ed_termine', 'name' => 'Termine (Demo)', 'singular' => 'Termin', 'icon' => 'calendar-dots',
        'description' => 'Beispiel-Kalender der Demo-Ausgabe – Termine relativ zum Tag der Anlage.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'datetime', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ganztaegig', 'label' => 'Ganztägig', 'type' => 'bool'],
            ['name' => 'wiederholung', 'label' => 'Wiederholung', 'type' => 'recurrence'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'in_list' => true],
            ['name' => 'kategorie', 'label' => 'Art', 'type' => 'select', 'in_list' => true,
                'options' => "konzert=Konzert\nlesung=Lesung\nworkshop=Workshop\nversammlung=Versammlung\nfest=Fest & Festival"],
            ['name' => 'beschreibung', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'termine', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'beschreibung',
            'sort_field' => 'beginn', 'sort_dir' => 'asc', 'workflow' => 0,
            'calendar' => ['enabled' => 1, 'start' => 'beginn', 'end' => 'ende', 'all_day' => 'ganztaegig', 'recurrence' => 'wiederholung',
                'location' => 'ort', 'description' => 'beschreibung', 'category' => 'kategorie', 'duration' => 90, 'feed' => 1]],
    ], $log);
    if ($t) {
        $d = fn(int $days, string $time) => date('Y-m-d', strtotime("+$days days")) . ' ' . $time;
        $tue = (9 - (int) date('N')) % 7 ?: 7;   // nächster Dienstag
        $until = gmdate('Ymd\THis\Z', strtotime('+120 days'));
        $events = [
            ['Offene Probe: Chor der Geschäftsstelle', $d($tue, '18:30'), $d($tue, '20:00'), false, "FREQ=WEEKLY;BYDAY=TU;UNTIL=$until", 'Probensaal, Musterweg 12 (fiktiv)', 'konzert', 'Jeden Dienstag: mitsingen ohne Vorkenntnisse, Noten liegen bereit.', 1],
            ['Lesung: Stimmen aus dem Archiv', $d(3, '19:30'), $d(3, '21:00'), false, '', 'Stadtbibliothek Musterstadt (fiktiv)', 'lesung', 'Briefe und Protokolle aus hundert Jahren Vereinsgeschichte, gelesen von Mitgliedern.', 6],
            ['Workshop: Pressearbeit für Ehrenamtliche', $d(6, '10:00'), $d(6, '15:00'), false, '', 'Seminarraum der Geschäftsstelle', 'workshop', 'Erster Abend der Reihe: Die gute Pressemitteilung.', 2],
            ['Jubiläumskonzert Musikverein Beispielfeld', $d(10, '18:00'), $d(10, '21:30'), false, '', 'Marktplatz Beispielfeld (fiktiv)', 'konzert', 'Hundert Jahre, ein Abend: Blasorchester, Big Band und Jugendensemble.', 3],
            ['Nacht der offenen Proberäume', $d(17, '18:00'), $d(18, '01:00'), false, '', 'Innenstadt, 30 Proberäume', 'fest', 'Eine Nacht, dreißig Proberäume – Eintritt frei, Shuttlebus inklusive.', 1],
            ['Mitgliederversammlung', $d(24, '17:00'), $d(24, '20:00'), false, '', 'Bürgerhaus Musterstadt (fiktiv)', 'versammlung', 'Mit Wahl des ersten Jugendbeirats.', 5],
            ['Chorfestival Musterland', $d(38, '00:00'), $d(40, '00:00'), true, '', 'Zwölf Bühnen in Musterstadt', 'fest', 'Drei Tage, zwölf Bühnen, ein großes Abschlusskonzert.', 9],
            ['Workshop: Barrierearm veranstalten', $d(45, '14:00'), $d(45, '17:30'), false, '', 'Online', 'workshop', 'Der neue Leitfaden in der Praxis – mit Beispielen aus den Vereinen.', 7],
            ['Lesecafé am Sonntag', $d(((7 - (int) date('N')) % 7) + 7, '15:00'), $d(((7 - (int) date('N')) % 7) + 7, '17:00'), false, 'FREQ=MONTHLY;BYDAY=1SU;COUNT=6', 'Café im Kulturhaus (fiktiv)', 'lesung', 'Einmal im Monat: Mitglieder lesen aus Lieblingsbüchern.', 10],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $cat, $text, $imgIdx]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'bild' => $I($imgIdx), 'status' => 'published',
                'text' => '<p>' . e($text) . '</p><p>Dieser Termin ist frei erfunden (Demo). Auf der Detailseite erscheinen Termin, Ort und ein Link zum Übernehmen in den eigenen Kalender.</p>']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // ---------------------------------------------------------------- Formular 1: Newsletter
    $t = editorial_demo_table([
        'handle' => 'ed_newsletter', 'name' => 'Newsletter-Anmeldungen (Demo)', 'singular' => 'Anmeldung', 'icon' => 'envelope-simple',
        'description' => 'Öffentliches Beispiel-Formular der Demo-Ausgabe (Band „Newsletter“).',
        'fields' => [
            ['name' => 'email', 'label' => 'E-Mail-Adresse', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'vorname', 'label' => 'Vorname (optional)', 'type' => 'text', 'width' => 'half'],
            ['name' => 'turnus', 'label' => 'Wie oft?', 'type' => 'select', 'options' => "woche=Jede Woche\nmonat=Einmal im Monat"],
        ],
        'settings' => ['title_field' => 'email', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke! Sie erhalten gleich eine E-Mail zur Bestätigung (Demo – es wird nichts verschickt).', 'submit' => 'Anmelden']],
    ], $log);
    if ($t) $out['newsletter'] = $t;

    // ---------------------------------------------------------------- Formular 2: Mitgliedschaft (Bedingungen, IBAN, wiederholbare Gruppe)
    $t = editorial_demo_table([
        'handle' => 'ed_mitglied', 'name' => 'Mitgliedsanträge (Demo)', 'singular' => 'Mitgliedsantrag', 'icon' => 'clipboard-text',
        'description' => 'Beispiel für Bedingungen, IBAN-Prüfung und wiederholbare Gruppen.',
        'fields' => [
            ['name' => 'art', 'label' => 'Mitglied werden als', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => "verein=Verein (Mitgliedsverein)\nperson=Privatperson (Fördermitglied)"],
            ['name' => 'verein', 'label' => 'Name des Vereins', 'type' => 'text', 'width' => 'half',
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'verein']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'verein']]]],
            ['name' => 'name', 'label' => 'Vor- und Nachname', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'width' => 'half'],
            ['name' => 'mitglieder', 'label' => 'Anzahl Mitglieder', 'type' => 'number', 'width' => 'half',
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'verein']]]],
            ['name' => 'kontakte', 'label' => 'Weitere Ansprechpersonen', 'type' => 'group', 'item_label' => 'Person', 'add_label' => 'Person hinzufügen', 'max' => 4,
                'fields' => [['name' => 'vorname', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'funktion', 'label' => 'Funktion', 'type' => 'text', 'width' => 'half']],
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'verein']]]],
            ['name' => 'iban', 'label' => 'IBAN für den Beitrag', 'type' => 'iban', 'required' => true, 'help' => 'Beispiel zum Testen: DE02 1203 0000 0000 2020 51'],
            ['name' => 'newsletter', 'label' => 'Den Newsletter möchte ich auch erhalten', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Vielen Dank – Ihr Antrag ist eingegangen (Demo, es entsteht keine Mitgliedschaft).', 'submit' => 'Antrag absenden']],
    ], $log);
    if ($t) $out['mitglied'] = $t;

    $log(count($out) . ' Datentabellen angelegt (ed_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function editorial_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $I = fn(int $n) => $img['all'][$n % max(1, count($img['all']))] ?? null;
    $B = 'editorial_demo_block';
    $news = $tables['news']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';
    $pers = $tables['personen']['handle'] ?? '';
    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $head = fn(string $kicker, string $title, string $text, bool $subnav = true) => $B('hero', ['variant' => 'compact', 'eyebrow' => $kicker, 'title' => $title, 'text' => $text, 'subnav' => $subnav]);
    $dl = fn(array $d) => $d + ['eyebrow' => '', 'title' => '', 'intro' => '', 'columns' => '3', 'ratio' => '3:2', 'limit' => 6, 'filter_field' => '', 'filter_op' => '=', 'filter_value' => '',
        'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => '', 'show_origin' => true, 'skip' => 0, 'filter_nav' => '', 'exclude_current' => false];
    $newsFields = ["$news._title", "$news.bild", "$news.rubrik", "$news.teaser", "$news.published_at"];
    $newsletter = $B('cta', ['variant' => 'newsletter', 'eyebrow' => 'Newsletter', 'title' => 'Die Ausgabe am Freitag – direkt ins Postfach.',
        'text' => 'Einmal pro Woche: die wichtigsten Nachrichten, Termine und Förderfristen aus dem Kulturnetz. Ohne Werbung, jederzeit abbestellbar.',
        'form_table' => $tables['newsletter']['handle'] ?? '', 'submit_label' => 'Anmelden', 'success_text' => ''], ['background' => 'dark', 'anchor' => 'newsletter']);

    // ---------------------------------------------------------------- Startseite (Musterseite für den Style-Editor)
    $home = Pages::home();
    $homeBlocks = [
        $B('data_list', $dl(['table' => $news, 'fields' => $newsFields, 'layout' => 'lead', 'limit' => 4, 'featured_first' => true])),
        $B('data_list', $dl(['eyebrow' => 'Weitere Meldungen', 'title' => 'Kurz notiert', 'table' => $news, 'fields' => ["$news._title", "$news.rubrik", "$news.published_at"],
            'layout' => 'brief', 'limit' => 6, 'skip' => 4, 'more_label' => 'Alle Nachrichten', 'more_link' => '/aktuelles']), ['spaceTop' => 'small']),
        $B('upcoming', ['eyebrow' => 'Kalender', 'title' => 'Demnächst', 'intro' => '', 'table' => $term, 'limit' => 5, 'days' => 0, 'layout' => 'list',
            'show_location' => true, 'link_detail' => true, 'subscribe' => true, 'more_label' => 'Alle Termine', 'more_link' => '/termine'], ['background' => 'muted']),
        $B('teasers', ['variant' => 'lead', 'eyebrow' => 'Magazin', 'title' => 'Geschichten aus den Vereinen', 'intro' => '', 'columns' => '2', 'ratio' => '16:9', 'items' => [
            ['kicker' => 'Langform', 'meta' => '12 Min. Lesezeit', 'title' => 'Die Stadt als Bühne', 'text' => 'Wie drei Vereine leere Läden, Hinterhöfe und eine stillgelegte Tankstelle in Spielorte verwandeln – eine Reportage über Kultur, die dorthin geht, wo die Menschen sind.', 'image' => $I(12), 'link' => '/magazin/die-stadt-als-buehne'],
            ['kicker' => 'Werkstattbesuch', 'meta' => '5 Min. Lesezeit', 'title' => 'Wo die Kulissen entstehen', 'text' => 'Ein Nachmittag in der Werkstatt der Theatergruppe Nordufer.', 'image' => $I(7), 'link' => '/magazin/werkstattbesuch'],
            ['kicker' => 'Porträt', 'meta' => '4 Min. Lesezeit', 'title' => 'Die Frau am Mischpult', 'text' => 'Seit zwanzig Jahren sorgt sie dafür, dass man alle hört.', 'image' => $I(9), 'link' => '/magazin'],
        ], 'more_label' => 'Zum Magazin', 'more_link' => '/magazin']),
        $B('quote', ['items' => [['text' => 'Kultur entsteht nicht in Förderbescheiden, sondern in Proberäumen. Unsere Aufgabe ist es, die Türen offen zu halten.', 'name' => 'Alex Muster', 'role' => 'Vorsitz, Kulturnetz Musterland (fiktiv)']]], ['divider' => true]),
        $B('stats', ['eyebrow' => 'Das Netzwerk', 'title' => 'In Zahlen', 'intro' => 'Beispielwerte der Demo – bitte durch eigene, belegbare Zahlen ersetzen.', 'items' => [
            ['value' => '42', 'label' => 'Mitgliedsvereine', 'text' => 'von Chor bis Jugendkunstschule'],
            ['value' => '3.800', 'label' => 'Mitglieder', 'text' => 'davon ein Drittel unter 27'],
            ['value' => '610', 'label' => 'Veranstaltungen', 'text' => 'im vergangenen Jahr'],
            ['value' => '1926', 'label' => 'gegründet', 'text' => 'als Sängerbund (fiktiv)'],
        ]], ['background' => 'muted']),
        $B('data_list', $dl(['eyebrow' => 'Menschen', 'title' => 'Die Redaktion und der Vorstand', 'table' => $pers, 'fields' => ["$pers._title", "$pers.bild", "$pers.bereich", "$pers.funktion"],
            'layout' => 'people', 'limit' => 4, 'ratio' => '4:5', 'more_label' => 'Alle Menschen', 'more_link' => '/menschen'])),
        $newsletter,
    ];
    if ($home) {
        editorial_demo_set_blocks((int) $home['id'], $homeBlocks, ['og_image' => $I(0), 'slug' => 'start']);
    } else {
        $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => 0], $homeBlocks);
    }

    // ---------------------------------------------------------------- Aktuelles (Ressort) + Archiv
    $aktuell = $page(['slug' => 'aktuelles', 'title' => 'Aktuelles', 'sort' => $sort, 'og_image' => $I(1),
        'meta_description' => 'Nachrichten aus dem Kulturnetz und seinen Vereinen – Förderung, Bildung, Kultur und Service (Demo).'], [
        $head('Ressort', 'Aktuelles', 'Nachrichten aus dem Verband und aus 42 Vereinen – nach Rubrik filtern oder im Archiv stöbern.'),
        $B('data_list', $dl(['table' => $news, 'fields' => $newsFields, 'layout' => 'river', 'limit' => 8, 'paginate' => true, 'filter_nav' => "$news.rubrik"]), ['spaceTop' => 'small']),
        $newsletter,
    ]);
    $page(['slug' => 'archiv', 'title' => 'Archiv', 'parent_id' => $aktuell, 'sort' => 1, 'meta_description' => 'Alle Nachrichten nach Monaten (Demo).'], [
        $head('Aktuelles', 'Archiv', 'Alle Meldungen, nach Monaten geordnet.'),
        $B('data_list', $dl(['table' => $news, 'fields' => ["$news._title", "$news.rubrik", "$news.published_at"], 'layout' => 'archive', 'limit' => 0]), ['spaceTop' => 'small']),
    ]);

    // ---------------------------------------------------------------- Termine
    $page(['slug' => 'termine', 'title' => 'Termine', 'sort' => $sort + 1, 'og_image' => $I(9),
        'meta_description' => 'Konzerte, Lesungen, Workshops und Feste im Kulturnetz – als Monatsübersicht, Liste oder zum Abonnieren (Demo).'], [
        $head('Kalender', 'Termine', 'Was, wann, wo: alle Veranstaltungen der Vereine und des Verbands. Termine lassen sich in den eigenen Kalender übernehmen.', false),
        $B('upcoming', ['eyebrow' => 'Die nächsten Termine', 'title' => 'Demnächst', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list',
            'show_location' => true, 'link_detail' => true, 'subscribe' => false], ['spaceTop' => 'small']),
        $B('calendar', ['eyebrow' => 'Monatsübersicht', 'title' => 'Kalender', 'intro' => 'Nach Art filtern, vor- und zurückblättern – auch ohne JavaScript.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => true], ['background' => 'muted', 'anchor' => 'kalender']),
        $B('calendar', ['eyebrow' => 'Liste', 'title' => 'Alle Termine des Monats', 'intro' => '', 'table' => $term, 'view' => 'agenda', 'visitor_filter' => false, 'subscribe' => false, 'link_detail' => true], ['anchor' => 'liste']),
    ]);

    // ---------------------------------------------------------------- Magazin (Übersicht) + Langform-Geschichte + Werkstattbesuch
    $mag = $page(['slug' => 'magazin', 'title' => 'Magazin', 'sort' => $sort + 2, 'og_image' => $I(12),
        'meta_description' => 'Reportagen, Porträts und Hintergründe aus der Vereinskultur (Demo).'], [
        $head('Magazin', 'Geschichten, die bleiben', 'Reportagen, Porträts und Hintergründe – in Ruhe erzählt.'),
        $B('teasers', ['variant' => 'lead', 'eyebrow' => '', 'title' => '', 'intro' => '', 'columns' => '3', 'ratio' => '3:2', 'items' => [
            ['kicker' => 'Langform', 'meta' => 'Text: Jo Platzhalter · 12 Min.', 'title' => 'Die Stadt als Bühne', 'text' => 'Wie drei Vereine leere Läden, Hinterhöfe und eine stillgelegte Tankstelle in Spielorte verwandeln.', 'image' => $I(12), 'link' => '/magazin/die-stadt-als-buehne'],
            ['kicker' => 'Werkstattbesuch', 'meta' => '5 Min.', 'title' => 'Wo die Kulissen entstehen', 'text' => 'Ein Nachmittag in der Werkstatt der Theatergruppe Nordufer.', 'image' => $I(7), 'link' => '/magazin/werkstattbesuch'],
            ['kicker' => 'Porträt', 'meta' => '4 Min.', 'title' => 'Die Frau am Mischpult', 'text' => 'Seit zwanzig Jahren sorgt sie dafür, dass man alle hört.', 'image' => $I(9), 'link' => '/magazin'],
            ['kicker' => 'Hintergrund', 'meta' => '7 Min.', 'title' => 'Was kostet ein Konzert?', 'text' => 'Eine Rechnung von der Saalmiete bis zum letzten Notenblatt.', 'image' => $I(3), 'link' => '/magazin'],
        ]]),
        $B('teasers', ['variant' => 'list', 'eyebrow' => 'Meistgelesen', 'title' => 'Diese Woche', 'intro' => '', 'columns' => '2', 'ratio' => '3:2', 'items' => [
            ['kicker' => 'Service', 'meta' => '', 'title' => 'Datenschutz im Verein: die fünf häufigsten Fragen', 'text' => '', 'image' => null, 'link' => '/aktuelles'],
            ['kicker' => 'Verband', 'meta' => '', 'title' => 'Neue Förderrunde: 180.000 Euro für kleine Kulturvereine', 'text' => '', 'image' => null, 'link' => '/aktuelles'],
            ['kicker' => 'Magazin', 'meta' => '', 'title' => 'Die Stadt als Bühne', 'text' => '', 'image' => null, 'link' => '/magazin/die-stadt-als-buehne'],
            ['kicker' => 'Kultur', 'meta' => '', 'title' => 'Die Nacht der offenen Proberäume kehrt zurück', 'text' => '', 'image' => null, 'link' => '/aktuelles'],
        ]], ['background' => 'muted']),
    ]);
    $story = '<p>Der Laden an der Ecke stand zwei Jahre leer. Jetzt stehen dort vierzig Stühle, ein Klavier und eine Kasse aus Sperrholz. Donnerstags probt hier die Theatergruppe, freitags singt ein Chor, und samstags kommen Leute, die vorher nie in einem Theater waren.</p>'
        . '<p>Solche Orte gibt es inzwischen in vielen Städten des Landes. Sie entstehen selten aus einem Plan, sondern aus einer Gelegenheit: ein Schlüssel, eine Idee, ein paar Menschen mit Zeit. Diese Reportage folgt drei Vereinen, die aus Leerstand Bühnen gemacht haben.</p>'
        . '<h3>Der Laden</h3><p>Die Theatergruppe Nordufer hatte keinen festen Probenraum mehr, als ihr ein Vermieter den leeren Laden anbot – für die Nebenkosten. Die Gruppe strich die Wände, baute eine Bühne aus Europaletten und hängte Vorhänge aus alten Bettlaken auf.</p>'
        . '<p>Seitdem kommen Nachbarinnen zu den Proben, Kinder drücken die Nasen an die Scheibe, und manche bleiben. Drei neue Mitglieder hat die Gruppe so gewonnen.</p>'
        . '<h3>Der Hinterhof</h3><p>Im Sommer spielt der Musikverein Beispielfeld in einem Hinterhof, der sonst Fahrradständer und Mülltonnen beherbergt. Die Akustik ist erstaunlich gut: Die Hauswände werfen den Klang zurück, die Zuhörer sitzen auf Bierbänken und Balkonen.</p>'
        . '<p>Genehmigungen, Nachbarn, Versicherung – der Aufwand war größer als gedacht. Das Referat Vereine hat geholfen, eine Checkliste zu erstellen, die inzwischen auch andere Vereine nutzen.</p>'
        . '<h3>Die Tankstelle</h3><p>Die ungewöhnlichste Bühne ist eine stillgelegte Tankstelle am Stadtrand. Unter dem Vordach finden Konzerte und Lesungen statt, das ehemalige Kassenhäuschen ist Bar und Garderobe zugleich.</p>'
        . '<p>Alle Orte, Vereine und Personen in dieser Reportage sind frei erfunden – sie zeigen, wie eine Langform-Geschichte im Kit „Editorial“ aussieht: mit Initiale, Inhaltsverzeichnis, Randnotiz, großem Zitat und randlosen Bildern.</p>';
    $page(['slug' => 'die-stadt-als-buehne', 'title' => 'Die Stadt als Bühne', 'parent_id' => $mag, 'sort' => 1, 'og_image' => $I(12),
        'meta_description' => 'Reportage: Wie drei Vereine Leerstand in Spielorte verwandeln (frei erfundene Demo-Geschichte).'], [
        $B('hero', ['variant' => 'cover', 'eyebrow' => 'Magazin · Langform', 'title' => 'Die Stadt als Bühne',
            'text' => 'Ein leerer Laden, ein Hinterhof, eine stillgelegte Tankstelle: Wie drei Vereine Orte für Kultur schaffen, wo niemand sie erwartet.',
            'byline' => 'Text: Jo Platzhalter · Grafiken: Platzhalter · 12 Min. Lesezeit', 'image' => $I(12), 'ratio' => '16:9', 'caption' => 'Bühnenlicht als Kreise – Platzhalter statt Foto.'], ['background' => 'dark']),
        $B('richtext', ['eyebrow' => '', 'title' => '', 'text' => $story, 'dropcap' => true, 'toc' => true, 'aside_title' => 'Zur Reportage',
            'aside' => 'Drei Vereine, drei Orte, ein halbes Jahr Recherche. Alle Namen sind frei erfunden (Demo).']),
        $B('figure', ['variant' => 'bleed', 'image' => $I(13), 'ratio' => '21:9', 'caption' => 'Randlos über die ganze Breite: Streifen in Petrol und Sand (Platzhalter).', 'credit' => '']),
        $B('quote', ['items' => [['text' => 'Wir haben nicht nach einem Theater gesucht. Wir haben nach Menschen gesucht – und sie waren schon da.', 'name' => 'Theatergruppe Nordufer', 'role' => 'fiktiv']]]),
        $B('stats', ['eyebrow' => 'Leerstand als Chance', 'title' => 'Die Orte in Zahlen', 'intro' => '', 'items' => [
            ['value' => '3', 'label' => 'Orte', 'text' => 'Laden, Hinterhof, Tankstelle'], ['value' => '47', 'label' => 'Veranstaltungen', 'text' => 'im ersten Jahr'],
            ['value' => '0 €', 'label' => 'Miete', 'text' => 'nur Nebenkosten'], ['value' => '11', 'label' => 'neue Mitglieder', 'text' => 'in drei Vereinen']]], ['background' => 'muted']),
        $B('gallery', ['eyebrow' => 'Bildstrecke', 'title' => 'Formen der Bühne', 'intro' => 'Ein Klick öffnet die Lightbox – mit Tastatur, Wischen und Bildunterschrift.', 'source' => 'manual',
            'images' => array_map(fn($n) => ['image' => $I($n), 'caption' => 'Platzhalterbild ' . ($n + 1)], [0, 3, 6, 8, 10, 11]), 'layout' => 'grid', 'columns' => '3', 'ratio' => '3:2', 'captions' => true, 'lightbox' => true]),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen. Eigene MP4-Videos laufen direkt, mit Untertiteln und Transkript aus der Mediathek.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $I(1), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)']),
        $B('teasers', ['variant' => 'grid', 'eyebrow' => 'Weiterlesen', 'title' => 'Mehr aus dem Magazin', 'intro' => '', 'columns' => '3', 'ratio' => '3:2', 'items' => [
            ['kicker' => 'Werkstattbesuch', 'meta' => '5 Min.', 'title' => 'Wo die Kulissen entstehen', 'text' => '', 'image' => $I(7), 'link' => '/magazin/werkstattbesuch'],
            ['kicker' => 'Porträt', 'meta' => '4 Min.', 'title' => 'Die Frau am Mischpult', 'text' => '', 'image' => $I(9), 'link' => '/magazin'],
            ['kicker' => 'Hintergrund', 'meta' => '7 Min.', 'title' => 'Was kostet ein Konzert?', 'text' => '', 'image' => $I(3), 'link' => '/magazin'],
        ]], ['divider' => true]),
    ]);
    $page(['slug' => 'werkstattbesuch', 'title' => 'Wo die Kulissen entstehen', 'parent_id' => $mag, 'sort' => 2, 'nav_title' => 'Werkstattbesuch', 'og_image' => $I(7),
        'meta_description' => 'Ein Nachmittag in der Werkstatt einer Theatergruppe (frei erfundene Demo-Geschichte).'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Magazin · Werkstattbesuch', 'title' => 'Wo die Kulissen entstehen',
            'text' => 'Sägen, Kleistern, Streichen: Ein Nachmittag in der Werkstatt der Theatergruppe Nordufer, wo aus Sperrholz Paläste werden.',
            'byline' => 'Text: Kim Beispiel · 5 Min. Lesezeit', 'image' => $I(7), 'ratio' => '4:3', 'caption' => 'Collage aus Rechtecken und Kreisen (Platzhalter).']),
        $B('richtext', ['text' => '<p>Es riecht nach Leim und frischer Farbe. Auf zwei Böcken liegt eine Tür, die in drei Wochen ein Schlosstor sein soll. Die Werkstatt ist ein ehemaliger Kohlenkeller, sechzig Quadratmeter, eine Glühbirne pro Werkbank.</p><h3>Alles aus zweiter Hand</h3><p>Fast alles hier ist gebraucht: Bretter vom Sperrmüll, Stoffe aus Haushaltsauflösungen, Farben, die bei Renovierungen übrig blieben. Was nicht gebraucht wird, landet im Regal „vielleicht später“.</p><h3>Das Regal „vielleicht später“</h3><p>Es ist das größte Regal der Werkstatt.</p><p>Alle Angaben sind frei erfunden (Demo).</p>',
            'dropcap' => true, 'toc' => false, 'aside' => '', 'aside_title' => '']),
        $B('text_image', ['variant' => 'left', 'eyebrow' => 'Zur Gruppe', 'title' => 'Theatergruppe Nordufer', 'text' => '<p>Gegründet von Nachbarinnen und Nachbarn, heute 34 Mitglieder zwischen 9 und 81 Jahren (fiktiv).</p>',
            'list' => "Proben donnerstags ab 18 Uhr\nNeue Mitglieder willkommen\nKeine Vorkenntnisse nötig", 'button_label' => 'Termine ansehen', 'button_link' => '/termine', 'image' => $I(6), 'ratio' => '4:5', 'caption' => ''], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Menschen
    $page(['slug' => 'menschen', 'title' => 'Menschen', 'sort' => $sort + 3, 'og_image' => $img['people'][0] ?? null,
        'meta_description' => 'Vorstand, Redaktion, Geschäftsstelle und Referate des Kulturnetzes (frei erfundene Demo-Personen).'], [
        $head('Menschen', 'Wer hier arbeitet', 'Vorstand, Redaktion, Geschäftsstelle und Referate – mit Monogrammen statt Fotos (Demo).', false),
        $B('data_list', $dl(['table' => $pers, 'fields' => ["$pers._title", "$pers.bild", "$pers.bereich", "$pers.funktion", "$pers.email"], 'layout' => 'people', 'limit' => 0, 'ratio' => '4:5',
            'filter_nav' => "$pers.bereich"]), ['spaceTop' => 'small']),
        $B('contact', ['eyebrow' => 'Geschäftsstelle', 'title' => 'So erreichen Sie uns', 'intro' => '', 'show_hours' => true, 'show_map' => true, 'note' => 'Beispieladresse – frei erfunden.'], ['background' => 'muted', 'anchor' => 'kontakt']),
    ]);

    // ---------------------------------------------------------------- Verband + Geschichte
    $verband = $page(['slug' => 'verband', 'title' => 'Verband', 'sort' => $sort + 4, 'og_image' => $I(5),
        'meta_description' => 'Das Kulturnetz Musterland: Aufgaben, Mitgliedsvereine, Satzung, Förderer (frei erfundene Demo-Angaben).'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Über uns', 'title' => 'Ein Netz aus 42 Vereinen.',
            'text' => 'Das Kulturnetz Musterland berät, fördert und verbindet Chöre, Orchester, Theatergruppen, Kunstschulen und Initiativen – seit 1926 (fiktiv).',
            'button_label' => 'Mitglied werden', 'button_link' => '/mitmachen', 'button2_label' => 'Kontakt', 'button2_link' => '/menschen#kontakt',
            'image' => $I(5), 'ratio' => '4:3', 'caption' => 'Konzentrische Bögen (Platzhalter).']),
        $B('features', ['eyebrow' => 'Was wir tun', 'title' => 'Vier Aufgaben', 'intro' => '', 'columns' => '4', 'style' => 'index', 'items' => [
            ['title' => 'Fördern', 'text' => 'Wir vergeben Mittel für Anschaffungen, Honorare und neue Projekte – mit einfachen Anträgen.', 'icon' => '', 'image' => null, 'link_label' => '', 'link' => '/aktuelles'],
            ['title' => 'Beraten', 'text' => 'Satzung, Versicherung, Datenschutz: Das Referat Vereine beantwortet Fragen am Telefon.', 'icon' => '', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Bilden', 'text' => 'Workshops, Sommerakademie und Fortbildungen für Ehrenamtliche.', 'icon' => '', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Verbinden', 'text' => 'Festivals, Nächte der offenen Proberäume und ein Netzwerk, das trägt.', 'icon' => '', 'image' => null, 'link_label' => '', 'link' => ''],
        ]]),
        $B('features', ['eyebrow' => 'Sparten', 'title' => 'Wer bei uns Mitglied ist', 'intro' => '', 'columns' => '3', 'style' => 'grid', 'items' => [
            ['title' => 'Chöre', 'text' => '14 Chöre vom Kinderchor bis zum Kammerchor.', 'icon' => 'microphone-stage', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Orchester & Bands', 'text' => '9 Ensembles, darunter zwei Big Bands.', 'icon' => 'music-notes', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Theater', 'text' => '7 Amateurtheater und ein Figurentheater.', 'icon' => 'ticket', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Bildende Kunst', 'text' => '5 Kunstvereine und Ateliergemeinschaften.', 'icon' => 'palette', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Literatur', 'text' => '4 Lesekreise und ein Literaturhaus.', 'icon' => 'book-open', 'image' => null, 'link_label' => '', 'link' => ''],
            ['title' => 'Jugendkunst', 'text' => '3 Jugendkunstschulen mit offenen Werkstätten.', 'icon' => 'paint-brush', 'image' => null, 'link_label' => '', 'link' => ''],
        ]], ['background' => 'muted']),
        $B('faq', ['eyebrow' => 'FAQ', 'title' => 'Häufige Fragen', 'intro' => 'Kurze Antworten – Ihre Frage fehlt? Schreiben Sie uns.', 'items' => [
            ['q' => 'Wer kann Mitglied werden?', 'a' => '<p>Gemeinnützige Kulturvereine und -initiativen aus dem Land sowie Privatpersonen als Fördermitglieder.</p>'],
            ['q' => 'Was kostet die Mitgliedschaft?', 'a' => '<p>Vereine zahlen nach Größe zwischen 40 und 240 Euro im Jahr, Fördermitglieder ab 30 Euro (Beispielwerte).</p>'],
            ['q' => 'Wie beantrage ich Fördermittel?', 'a' => '<p>Mit dem zweiseitigen Antrag aus dem Downloadbereich – die Entscheidung fällt innerhalb von sechs Wochen.</p>'],
            ['q' => 'Sind die Inhalte dieser Website echt?', 'a' => '<p>Nein. Alle Namen, Vereine, Orte, Zahlen und Zitate sind frei erfunden und dienen nur als Beispiel für das Kit „Editorial“.</p>'],
        ]]),
        $B('downloads', ['eyebrow' => 'Dokumente', 'title' => 'Satzung, Berichte, Anträge', 'intro' => 'PDFs lassen sich im Browser ansehen oder herunterladen.', 'source' => 'manual', 'show_viewer' => true,
            'files' => $pdf ? [['label' => 'Jahresbericht 2025 (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []], ['background' => 'muted']),
        $B('logos', ['eyebrow' => 'Partner & Förderer', 'title' => 'Mit Unterstützung von', 'items' => [
            ['name' => 'Stiftung Beispielkultur', 'link' => '', 'image' => null], ['name' => 'Musterland Kulturfonds', 'link' => '', 'image' => null],
            ['name' => 'Verein der Freunde', 'link' => '', 'image' => null], ['name' => 'Stadtwerke Musterstadt', 'link' => '', 'image' => null],
            ['name' => 'Beispiel Bank', 'link' => '', 'image' => null], ['name' => 'Radio Muster', 'link' => '', 'image' => null],
        ]]),
    ]);
    $page(['slug' => 'geschichte', 'title' => 'Geschichte', 'parent_id' => $verband, 'sort' => 1, 'meta_description' => 'Hundert Jahre Kulturnetz (frei erfundene Chronik).'], [
        $head('Verband', 'Geschichte', 'Vom Sängerbund zum Kulturnetz – eine frei erfundene Chronik.'),
        $B('richtext', ['text' => '<h3>1926: Der Sängerbund</h3><p>Zwölf Chöre gründen einen Sängerbund, um gemeinsam Noten zu kaufen und ein Sängerfest auszurichten.</p><h3>1968: Neue Sparten</h3><p>Theatergruppen und Orchester kommen hinzu; der Bund benennt sich um.</p><h3>2004: Die erste Geschäftsstelle</h3><p>Mit zwei Teilzeitstellen beginnt die professionelle Beratung der Vereine.</p><h3>2026: Das Kulturnetz</h3><p>42 Vereine, ein Magazin, ein Förderprogramm – und die zweite Nacht der offenen Proberäume.</p>',
            'dropcap' => false, 'toc' => true, 'aside' => 'Alle Jahreszahlen und Ereignisse sind frei erfunden (Demo).', 'aside_title' => 'Hinweis']),
        $B('figure', ['variant' => 'margin', 'image' => $I(3), 'ratio' => '3:2', 'caption' => 'Hundert Jahre – als Zahl gesetzt. Die Bildunterschrift steht in der Randspalte.', 'credit' => '']),
    ]);

    // ---------------------------------------------------------------- Mitmachen (Newsletter, Mitgliedsantrag)
    $page(['slug' => 'mitmachen', 'title' => 'Mitmachen', 'sort' => $sort + 5, 'og_image' => $I(10),
        'meta_description' => 'Newsletter abonnieren oder Mitglied werden – Formulare ohne Cookies und ohne Dienste Dritter (Demo).'], [
        $head('Mitmachen', 'Werden Sie Teil des Netzes', 'Newsletter abonnieren, Mitglied werden oder eine Veranstaltung melden. Absenden ist gefahrlos: Es handelt sich um Demo-Tabellen.', false),
        $newsletter,
        $B('data_form', ['eyebrow' => 'Mitgliedschaft', 'title' => 'Mitgliedsantrag (Beispiel)', 'intro' => '„Verein“ blendet weitere Felder ein – mit wiederholbarer Gruppe für Ansprechpersonen. Die IBAN wird geprüft.',
            'table' => $tables['mitglied']['handle'] ?? '', 'submit_label' => '', 'success_text' => '']),
        $B('cta', ['variant' => 'box', 'eyebrow' => 'Fragen?', 'title' => 'Das Referat Vereine hilft weiter.', 'text' => 'Montag bis Freitag am Telefon – oder jederzeit per E-Mail.',
            'button_label' => 'E-Mail schreiben', 'button_link' => 'email', 'button2_label' => 'Menschen', 'button2_link' => '/menschen', 'form_table' => '', 'submit_label' => '', 'success_text' => ''], ['background' => 'white']),
    ]);

    // ---------------------------------------------------------------- Detailvorlagen (Nachricht, Termin, Person)
    $tpl = function (array $t, string $slug, string $title, array $blocks) use ($log) {
        $id = Pages::create(['slug' => $slug, 'title' => $title, 'type' => 'template', 'template_for' => $t['handle'], 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks($blocks));
        Tables::setDetailPage(Tables::find($t['handle']), $id);
        return $id;
    };
    if ($news !== '') {
        $tpl($tables['news'], '_vorlage-ed-news', 'Nachrichten (Demo) – Detailseite', [
            $B('article', ['table' => $news, 'kicker_field' => '', 'dek_field' => '', 'body_field' => '', 'author_field' => '', 'image_ratio' => '3:2', 'image_width' => 'wide',
                'show_toc' => true, 'show_share' => true, 'show_progress' => true, 'show_facts' => true, 'back_label' => 'Alle Nachrichten', 'back_link' => '/aktuelles'], ['spaceTop' => 'small']),
            $B('data_list', $dl(['eyebrow' => 'Mehr aus der Rubrik', 'title' => 'Weiterlesen', 'table' => $news, 'fields' => $newsFields, 'layout' => 'cards', 'limit' => 3,
                'filter_field' => "$news.rubrik", 'filter_op' => 'same', 'exclude_current' => true]), ['background' => 'muted']),
            $newsletter,
        ]);
    }
    if ($term !== '') {
        $tpl($tables['termine'], '_vorlage-ed-termine', 'Termine (Demo) – Detailseite', [
            $B('article', ['table' => $term, 'image_ratio' => '16:9', 'image_width' => 'wide', 'show_toc' => false, 'show_share' => true, 'show_progress' => false, 'show_facts' => true,
                'back_label' => 'Alle Termine', 'back_link' => '/termine'], ['spaceTop' => 'small']),
            $B('upcoming', ['eyebrow' => 'Kalender', 'title' => 'Weitere Termine', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true,
                'link_detail' => true, 'subscribe' => true, 'more_label' => 'Alle Termine', 'more_link' => '/termine'], ['background' => 'muted']),
        ]);
    }
    if ($pers !== '') {
        $tpl($tables['personen'], '_vorlage-ed-personen', 'Menschen (Demo) – Detailseite', [
            $B('article', ['table' => $pers, 'kicker_field' => "$pers.bereich", 'dek_field' => "$pers.funktion", 'image_ratio' => '4:5', 'image_width' => 'text',
                'show_toc' => false, 'show_share' => false, 'show_progress' => false, 'show_facts' => false, 'back_label' => 'Alle Menschen', 'back_link' => '/menschen'], ['spaceTop' => 'small']),
            $B('data_list', $dl(['eyebrow' => 'Beiträge', 'title' => 'Zuletzt geschrieben', 'table' => $news, 'fields' => ["$news._title", "$news.rubrik", "$news.published_at", "$news.teaser"],
                'layout' => 'river', 'limit' => 4, 'filter_field' => "$news.autor", 'filter_op' => 'current', 'empty_text' => 'Noch keine Beiträge.']), ['background' => 'muted']),
        ]);
    }
    $log('Rubriken, Magazin, Detailvorlagen und Startseite angelegt.');
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“ (Unterseite der Startseite)

/** Musterseite mit den Aufmacher-Varianten Titelgeschichte, Aktuell, Stimme (+ Titelbild und Aufmacher zum Vergleich) – ersetzt eine vorhandene */
function editorial_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $home = Pages::home();
    if (!$home) { $log('Keine Startseite vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE slug = 'hero-varianten' AND parent_id = ?", [(int) $home['id']]) as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();
    $img = array_map('intval', array_column($db->fetchAll("SELECT id FROM media WHERE tags LIKE ? AND mime LIKE 'image/%' AND title NOT LIKE 'Monogramm%' ORDER BY id", ['%' . EDITORIAL_DEMO_TAG . '%']), 'id'));
    $I = fn(int $n) => $img ? $img[$n % count($img)] : null;
    $news = Tables::find('ed_news') ? 'ed_news' : '';
    $term = Tables::find('ed_termine') ? 'ed_termine' : '';
    $B = fn(string $variant, array $data, array $tunes = []) => editorial_demo_block('hero', ['variant' => $variant] + $data
        + ['eyebrow' => '', 'byline' => '', 'text' => '', 'caption' => '', 'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''], $tunes + ['divider' => true]);
    $blocks = [
        $B('compact', ['eyebrow' => 'Musterseite', 'title' => 'Hero-Varianten', 'subnav' => false,
            'text' => 'Der Block „Aufmacher / Seitenkopf“ hat sieben Varianten. Neu sind Titelgeschichte, Aktuell und Stimme. Jede erscheint hier einmal – auf echten Seiten steht nur ein Aufmacher ganz oben.'],
            ['divider' => false, 'spaceBottom' => 'small']),
        $B('issue', ['issue' => 'Ausgabe 12 · Herbst 2026', 'eyebrow' => 'Titelgeschichte', 'title' => 'Hundert Proberäume, eine Stadt',
            'text' => 'Wo geübt wird, entsteht Kultur. Eine Reise durch Keller, Hinterhöfe und Vereinsheime – und zu den Menschen, die dort jede Woche die Türen aufschließen.',
            'byline' => 'Text: Kim Beispiel · 12 Min. Lesezeit', 'image' => $I(8), 'caption' => 'Titelmotiv der Beispiel-Ausgabe (Platzhaltergrafik).',
            'button_label' => 'Titelgeschichte lesen', 'button_link' => '/magazin/die-stadt-als-buehne',
            'issue_more_title' => '', 'issue_more' => [
                ['kicker' => 'Werkstattbesuch', 'title' => 'Wo die Kulissen entstehen', 'meta' => '5 Min. Lesezeit', 'link' => '/magazin/werkstattbesuch'],
                ['kicker' => 'Chronik', 'title' => 'Hundert Jahre Kulturnetz in zwölf Bildern', 'meta' => '8 Min. Lesezeit', 'link' => '/verband/geschichte'],
                ['kicker' => 'Service', 'title' => 'Förderfristen im Herbst auf einen Blick', 'meta' => 'Übersicht', 'link' => '/aktuelles'],
            ]]),
        $B('agenda', ['eyebrow' => 'Kalender', 'title' => 'Was diese Woche im Netz passiert',
            'text' => 'Konzerte, Lesungen und Workshops der Vereine – die nächsten Termine kommen direkt aus dem Kalender.',
            'dates_table' => $term, 'dates_limit' => '3', 'dates_title' => 'Die nächsten Termine', 'dates_more_label' => 'Alle Termine', 'dates_more_link' => '/termine'],
            ['background' => 'muted']),
        $B('agenda', ['eyebrow' => 'Aktuell', 'title' => 'Neues aus den Vereinen',
            'text' => 'Mit einer Nachrichten-Tabelle zeigt dieselbe Variante die neuesten Meldungen – hier vier Stück im Raster.',
            'dates_table' => $news, 'dates_limit' => '4', 'dates_title' => 'Zuletzt gemeldet', 'dates_more_label' => 'Alle Nachrichten', 'dates_more_link' => '/aktuelles']),
        $B('voice', ['eyebrow' => 'Mitmachen', 'title' => 'Warum wir jeden Dienstag proben',
            'text' => 'Stimmen aus den Vereinen erzählen, was Ehrenamt bedeutet – ein Zitat als Einstieg in die Seite.',
            'button_label' => 'Mitglied werden', 'button_link' => '/mitmachen', 'button2_label' => 'Alle Stimmen', 'button2_link' => '/menschen',
            'quote' => 'Nach einem langen Arbeitstag in den Probenraum zu kommen, ist wie nach Hause zu kommen. Hier zählt nicht, was man kann – sondern dass man da ist.',
            'quote_name' => 'Rike Beispiel', 'quote_role' => 'Beispielstimme · Chor Nordufer (fiktiv)', 'rating' => '4.5', 'rating_note' => 'Beispielbewertung, 36 erfundene Stimmen'],
            ['background' => 'dark']),
        $B('cover', ['eyebrow' => 'Zum Vergleich: Titelbild', 'title' => 'Die Stadt als Bühne',
            'text' => 'Bild vollflächig, Text auf dunkler Verlaufsfläche – die bisherige Variante für die große Geschichte.', 'image' => $I(12), 'byline' => 'Text: Jo Platzhalter · 12 Min.']),
        $B('split', ['eyebrow' => 'Zum Vergleich: Aufmacher', 'title' => 'Text und Bild im Seitenraster',
            'text' => 'Der Klassiker – das Raster kommt aus Design → Raster.', 'image' => $I(3), 'ratio' => '3:2', 'caption' => 'Platzhaltergrafik.']),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $home['id'], 'sort' => 90, 'status' => 'published', 'menu' => 0,
        'meta_description' => 'Alle Aufmacher-Varianten des Kits „Editorial“: Titelgeschichte, Aktuell, Stimme, Titelbild und Aufmacher (Demo).'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/hero-varianten).');
    return true;
}
