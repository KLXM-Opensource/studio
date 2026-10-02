<?php
/**
 * „Showcase“ des Themes fluid: ein Seitenbaum, der jeden Block und jede Variante mit frei erfundenen Inhalten zeigt –
 * inkl. Datentabellen (Projekte mit Detailseite, Veranstaltungen mit Wiederholung, Team), zwei öffentlichen Formularen,
 * erzeugten abstrakten Bildern (GD, ohne Personen oder Marken) und einer Beispiel-PDF.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/fluid/tools/demo.php [--force|--remove]
 * Alles ist als Demo gekennzeichnet (Seitenbaum „Showcase“, Tabellen showcase_*, Medien-Schlagwort „showcase-demo“).
 * Video: „Big Buck Bunny“ © 2008 Blender Foundation / www.bigbuckbunny.org, Lizenz CC BY 3.0 – per Zwei-Klick-Lösung eingebunden.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const FLUID_DEMO_TAG = 'showcase-demo';

/** Showcase anlegen. $force: vorhandenen Showcase vorher entfernen. */
function fluid_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path = 'showcase' AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Der Showcase ist schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    fluid_demo_remove($log);   // auch Reste (Tabellen, Bilder) früherer Durchläufe, z. B. nach „reseed“

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist (Kopf, Kontakt-Block, Karte).
    // Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse von Personen oder Firmen).
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Musterstadt', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    $img = fluid_demo_images($log);
    $pdf = fluid_demo_pdf($log);
    $tables = fluid_demo_tables($img, $log);
    fluid_demo_pages($img, $pdf, $tables, $log);
    fluid_demo_heroes($log);   // Unterseite „Hero-Varianten“
    \Core\PageCache::clear();
    $log('Fertig: Seitenbaum „Showcase“ mit 8 Seiten, ' . count($tables) . ' Datentabellen und ' . count($img['all']) . ' Bildern.');
    return true;
}

/** Showcase, Demo-Tabellen und Demo-Medien entfernen */
function fluid_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE path = 'showcase' OR path LIKE 'showcase/%' OR slug = '_vorlage-showcase-projekte'"), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'showcase_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . FLUID_DEMO_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query("DELETE FROM media_collections WHERE name = 'Showcase (Demo)'");
    $log('Vorhandenen Showcase entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Bilder (abstrakt, mit GD erzeugt)

/** @return array{all: list<int>, wide: list<int>, tall: list<int>, avatars: list<int>, collection: int} */
function fluid_demo_images(callable $log): array
{
    $col = Media::createCollection('Showcase (Demo)', 'Abstrakte Beispielbilder des Kit-Showcase – automatisch erzeugt, frei verwendbar, ohne Personen oder Marken.');
    $motifs = [
        // Titel, dunkle Farbe, helle Farbe, Akzent, Motiv
        ['Weiche Farbfelder in Indigo und Sand', [49, 46, 129], [246, 239, 226], [253, 230, 138], 'blobs'],
        ['Bögen in Terrakotta', [163, 56, 15], [251, 243, 232], [246, 212, 107], 'arches'],
        ['Hügel in Salbeigrün', [44, 106, 92], [238, 246, 241], [213, 236, 221], 'hills'],
        ['Streifen in Violett und Limette', [74, 24, 179], [244, 243, 247], [217, 249, 91], 'bands'],
        ['Sonne über dem Horizont in Bordeaux', [138, 28, 50], [251, 246, 238], [235, 203, 152], 'sun'],
        ['Raster in Nachtblau', [20, 43, 77], [245, 244, 240], [237, 227, 204], 'grid'],
        ['Ringe in Blau und Gelb', [11, 92, 173], [242, 246, 251], [255, 216, 74], 'rings'],
        ['Wellen in Graphit und Rot', [17, 17, 17], [244, 244, 242], [200, 16, 46], 'waves'],
        ['Farbverlauf in Nachtblau und Violett', [10, 12, 19], [27, 21, 66], [139, 166, 255], 'glow'],
        ['Kreise in Salbei und Creme', [33, 83, 71], [251, 246, 238], [213, 236, 221], 'blobs'],
    ];
    $all = $wide = $tall = $avatars = [];
    $import = function (string $file, string $name, string $title, string $alt) use ($col, $log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => FLUID_DEMO_TAG, 'collection' => $col, 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    // Monogramme nicht in der Sammlung (die Galerie „aus Sammlung“ zeigt nur die Grafiken)
    $importPlain = function (string $file, string $name, string $title, string $alt) use ($log): ?int {
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => FLUID_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    };
    foreach ($motifs as $i => [$title, $dark, $light, $acc, $kind]) {
        $file = tempnam(sys_get_temp_dir(), 'fx') . '.jpg';
        fluid_demo_draw($file, 1800, 1200, $dark, $light, $acc, $kind, $i);
        if ($id = $import($file, sprintf('showcase-%02d.jpg', $i + 1), $title, 'Abstrakte Grafik: ' . $title . ' (Beispielbild)')) { $all[] = $id; $wide[] = $id; }
    }
    foreach ([0, 2, 4, 6] as $k => $i) {
        [$title, $dark, $light, $acc, $kind] = $motifs[$i];
        $file = tempnam(sys_get_temp_dir(), 'fx') . '.jpg';
        fluid_demo_draw($file, 1200, 1500, $dark, $light, $acc, $kind, 40 + $i);
        if ($id = $import($file, sprintf('showcase-hoch-%02d.jpg', $k + 1), $title . ' (Hochformat)', 'Abstrakte Grafik im Hochformat: ' . $title . ' (Beispielbild)')) { $all[] = $id; $tall[] = $id; }
    }
    // Monogramm-Avatare für das Demo-Team (keine Fotos von Personen)
    $font = dirname(__DIR__) . '/fonts/Inter_700Bold.ttf';
    foreach (['KM' => [49, 46, 129], 'SB' => [163, 56, 15], 'JP' => [44, 106, 92], 'AT' => [74, 24, 179], 'RL' => [138, 28, 50], 'NV' => [11, 92, 173]] as $initials => $rgb) {
        $file = tempnam(sys_get_temp_dir(), 'fx') . '.png';
        fluid_demo_avatar($file, $initials, $rgb, $font);
        if ($id = $importPlain($file, 'showcase-monogramm-' . strtolower($initials) . '.png', 'Monogramm ' . $initials, 'Monogramm ' . $initials . ' (Platzhalter statt Foto)')) $avatars[] = $id;
    }
    $log(count($all) + count($avatars) . ' Beispielbilder erzeugt.');
    return ['all' => $all, 'wide' => $wide, 'tall' => $tall, 'avatars' => $avatars, 'collection' => $col];
}

/** Abstrakte Komposition (weicher Verlauf + Motiv) */
function fluid_demo_draw(string $file, int $w, int $h, array $dark, array $light, array $acc, string $kind, int $seed): void
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imageantialias($im, true);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h * ($kind === 'glow' ? 1 : .28);
        $c = array_map(fn($a, $b) => (int) round($a * (1 - $t) + $b * $t), $kind === 'glow' ? $dark : $light, $kind === 'glow' ? $light : $dark);
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, ...$c));
    }
    $col = fn(array $rgb, int $alpha) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    $mid = array_map(fn($a, $b) => (int) round(($a + $b) / 2), $light, $dark);
    mt_srand(9100 + $seed);
    $s = $w / 1600;
    switch ($kind) {
        case 'blobs':
            for ($k = 0; $k < 14; $k++) {
                $r = (int) (mt_rand(160, 520) * $s);
                imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), $r, $r, $col([$dark, $acc, $mid][$k % 3], mt_rand(30, 90)));
            }
            break;
        case 'arches':
            for ($k = 6; $k >= 0; $k--) {
                $r = (int) ((300 + $k * 230) * $s);
                imagefilledellipse($im, (int) ($w * .62), $h, $r, (int) ($r * 1.15), $col($k % 2 ? $dark : ($k % 3 ? $acc : $light), 10 + $k * 8));
            }
            break;
        case 'hills':
            for ($k = 0; $k < 5; $k++) {
                $poly = [];
                for ($x = 0; $x <= $w; $x += 40) { $poly[] = $x; $poly[] = (int) ($h * (.35 + $k * .13) + sin($x / (260 * $s) + $k * 1.7) * 60 * $s); }
                array_push($poly, $w, $h, 0, $h);
                imagefilledpolygon($im, $poly, $col($k % 2 ? $dark : ($k === 0 ? $acc : $mid), 40 - $k * 6));
            }
            imagefilledellipse($im, (int) ($w * .78), (int) ($h * .22), (int) (220 * $s), (int) (220 * $s), $col($acc, 5));
            break;
        case 'bands':
            for ($k = -4; $k < 10; $k++) {
                $x = (int) ($k * 220 * $s);
                imagefilledpolygon($im, [$x, $h, (int) ($x + 130 * $s), $h, (int) ($x + 130 * $s + $h * .7), 0, (int) ($x + $h * .7), 0], $col($k % 3 === 0 ? $acc : ($k % 2 ? $dark : $mid), $k % 3 ? 25 : 5));
            }
            break;
        case 'sun':
            imagefilledellipse($im, (int) ($w * .5), (int) ($h * .58), (int) ($w * .42), (int) ($w * .42), $col($acc, 0));
            imagefilledellipse($im, (int) ($w * .5), (int) ($h * .58), (int) ($w * .3), (int) ($w * .3), $col($dark, 25));
            imagefilledrectangle($im, 0, (int) ($h * .62), $w, $h, $col($dark, 0));
            for ($k = 0; $k < 6; $k++) imagefilledrectangle($im, 0, (int) ($h * (.66 + $k * .055)), $w, (int) ($h * (.66 + $k * .055) + 6 * $s), $col($acc, 40 + $k * 12));
            break;
        case 'grid':
            for ($gx = 0; $gx < 9; $gx++) {
                for ($gy = 0; $gy < 7; $gy++) {
                    if (mt_rand(0, 3) === 0) continue;
                    $x = (int) ((90 + $gx * 170) * $s); $y = (int) ((70 + $gy * 160) * $s * ($h / $w * 1.33));
                    $r = mt_rand(0, 4);
                    $c = $r === 0 ? $acc : ($r < 3 ? $dark : $mid);
                    if ($r === 4) imagefilledellipse($im, (int) ($x + 65 * $s), (int) ($y + 60 * $s), (int) (125 * $s), (int) (125 * $s), $col($c, 10));
                    else imagefilledrectangle($im, $x, $y, (int) ($x + 130 * $s), (int) ($y + 120 * $s), $col($c, mt_rand(0, 2) ? 8 : 60));
                }
            }
            break;
        case 'rings':
            for ($k = 9; $k >= 1; $k--) {
                $r = (int) ($k * 170 * $s);
                imagefilledellipse($im, (int) ($w * .3), (int) ($h * .6), $r, $r, $col($k % 2 ? $dark : ($k % 4 === 0 ? $acc : $light), 12));
            }
            imagefilledellipse($im, (int) ($w * .82), (int) ($h * .25), (int) (260 * $s), (int) (260 * $s), $col($acc, 0));
            break;
        case 'waves':
            for ($k = 0; $k < 8; $k++) {
                $poly = [];
                for ($x = 0; $x <= $w; $x += 30) { $poly[] = $x; $poly[] = (int) ($h * (.2 + $k * .1) + sin($x / (200 * $s) + $k) * 50 * $s); }
                array_push($poly, $w, $h, 0, $h);
                imagefilledpolygon($im, $poly, $col($k === 3 ? $acc : ($k % 2 ? $dark : $mid), $k === 3 ? 0 : 96 - $k * 9));
            }
            break;
        case 'glow':
            for ($k = 0; $k < 40; $k++) {
                $r = (int) ((700 - $k * 16) * $s);
                imagefilledellipse($im, (int) ($w * .7), (int) ($h * .35), $r, $r, $col($acc, 124 - (int) ($k * 1.2)));
            }
            for ($x = 0; $x < $w; $x += (int) (60 * $s)) imageline($im, $x, 0, $x, $h, $col($acc, 118));
            for ($y = 0; $y < $h; $y += (int) (60 * $s)) imageline($im, 0, $y, $w, $y, $col($acc, 118));
            break;
    }
    imagejpeg($im, $file, 84);
    unset($im);
}

/** Monogramm (600 × 600): Kreis-Komposition mit Initialen – Platzhalter statt Porträtfoto */
function fluid_demo_avatar(string $file, string $initials, array $rgb, string $font): void
{
    $im = imagecreatetruecolor(600, 600);
    $light = array_map(fn($c) => (int) round($c + (255 - $c) * .86), $rgb);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$light));
    imagefilledellipse($im, 420, 470, 520, 520, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], 70));
    imagefilledellipse($im, 150, 120, 260, 260, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], 100));
    $ink = imagecolorallocate($im, ...$rgb);
    if (is_file($font)) {
        $box = imagettfbbox(190, 0, $font, $initials);
        $tw = $box[2] - $box[0];
        imagettftext($im, 190, 0, (int) ((600 - $tw) / 2) - $box[0], 385, $ink, $font, $initials);
    }
    imagepng($im, $file, 7);
    unset($im);
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function fluid_demo_pdf(callable $log): ?int
{
    $text = 'Showcase - Beispiel-PDF (Demo). Frei erfundener Inhalt.';
    $stream = "BT /F1 20 Tf 72 760 Td ($text) Tj ET";
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
    $file = tempnam(sys_get_temp_dir(), 'fx') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'showcase-leistungsuebersicht.pdf', '', ['title' => 'Leistungsübersicht (Beispiel)', 'tags' => FLUID_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function fluid_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function fluid_demo_tables(array $img, callable $log): array
{
    $out = [];
    $W = fn(int $n) => $img['wide'][$n % max(1, count($img['wide']))] ?? null;

    // Projekte: Karten, Liste, kompakt, Tabelle, Detailseite
    $t = fluid_demo_table([
        'handle' => 'showcase_projekte', 'name' => 'Projekte (Showcase)', 'singular' => 'Projekt', 'icon' => 'briefcase',
        'description' => 'Beispiel-Tabelle des Showcase – frei erfundene Projekte.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'kategorie', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "konzept=Konzept\ngestaltung=Gestaltung\numsetzung=Umsetzung"],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'auftraggeber', 'label' => 'Auftraggeber', 'type' => 'text', 'in_list' => true],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'showcase-projekte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'jahr', 'sort_dir' => 'desc', 'workflow' => 0],
    ], $log);
    if ($t) {
        $rows = [
            ['Leitsystem für ein Bürgerhaus', 'konzept', 2026, 'Verein Beispielstadt (fiktiv)', 'Orientierung auf drei Etagen: klare Wege, gut lesbare Schilder, barrierefreie Beschriftung.'],
            ['Online-Terminbuchung', 'umsetzung', 2026, 'Praxis am Park (fiktiv)', 'Vom Papierkalender zur Online-Buchung – mit Schulung des Teams.'],
            ['Neues Erscheinungsbild', 'gestaltung', 2025, 'Nordlicht Werkstatt (fiktiv)', 'Farben, Schrift und Formen als Design-Tokens – von der Visitenkarte bis zur Website.'],
            ['Speisekarte zum Mitnehmen', 'gestaltung', 2025, 'Café Morgenrot (fiktiv)', 'Saisonale Karte, die sich in zehn Minuten selbst aktualisieren lässt.'],
            ['Ausstellung digital', 'umsetzung', 2024, 'Museum Muster (fiktiv)', 'Ein Rundgang mit Texten in einfacher Sprache und Audiodeskription.'],
            ['Mandanten-Portal', 'konzept', 2024, 'Kanzlei Aster (fiktiv)', 'Sichere Formulare mit Ende-zu-Ende-Verschlüsselung statt E-Mail-Anhängen.'],
        ];
        foreach ($rows as $i => [$title, $cat, $year, $client, $text]) {
            Entries::save($t, null, ['titel' => $title, 'kategorie' => $cat, 'jahr' => (string) $year, 'auftraggeber' => $client, 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Dieser Text ist frei erfunden und zeigt, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h3>Ausgangslage</h3><p>Viele Informationen, wenig Zeit: Das Team wollte Inhalte einmal pflegen und überall aktuell ausgeben.</p>'
                    . '<h3>Ergebnis</h3><ul><li>Klare Struktur</li><li>Barrierearm umgesetzt</li><li>Selbst pflegbar</li></ul>',
                'bild' => $W($i + 1), 'status' => 'published']);
        }
        $out['projekte'] = $t;
    }

    // Veranstaltungen: Kalender mit Wiederholung, ganztägigem Termin und Kategorien
    $t = fluid_demo_table([
        'handle' => 'showcase_termine', 'name' => 'Veranstaltungen (Showcase)', 'singular' => 'Veranstaltung', 'icon' => 'calendar-dots',
        'description' => 'Beispiel-Kalender des Showcase – Termine relativ zum Tag der Anlage.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'beginn', 'label' => 'Beginn', 'type' => 'datetime', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'ende', 'label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['name' => 'ganztaegig', 'label' => 'Ganztägig', 'type' => 'bool'],
            ['name' => 'wiederholung', 'label' => 'Wiederholung', 'type' => 'recurrence'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'in_list' => true],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'textarea'],
            ['name' => 'kategorie', 'label' => 'Kategorie', 'type' => 'select', 'in_list' => true, 'options' => "workshop=Workshop\nsprechstunde=Offene Sprechstunde\nvortrag=Vortrag\ngeschlossen=Geschlossen"],
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
            ['Kurzberatung am Telefon', $d(0, '10:00'), $d(0, '11:30'), false, '', 'Telefon', 'Zehn Minuten für Ihre Frage – ohne Anmeldung.', 'sprechstunde'],
            ['Offene Sprechstunde', $d($thu, '16:00'), $d($thu, '18:00'), false, "FREQ=WEEKLY;BYDAY=TH;UNTIL=$until", 'Beispielraum 1', 'Jeden Donnerstag: Fragen mitbringen, Antworten mitnehmen.', 'sprechstunde'],
            ['Workshop: Websites ohne Breakpoints', $d(4, '09:30'), $d(4, '15:00'), false, '', 'Seminarraum (fiktiv)', 'Container-Queries, fließende Schrift, intrinsische Raster.', 'workshop'],
            ['Vortrag: Barrierefreiheit in der Praxis', $d(11, '18:30'), $d(11, '20:00'), false, '', 'Online', 'Was WCAG 2.2 für kleine Websites bedeutet.', 'vortrag'],
            ['Betriebsausflug – geschlossen', $d(18, '00:00'), '', true, '', '', 'An diesem Tag sind wir nicht erreichbar.', 'geschlossen'],
            ['Workshop: Texte fürs Web', $d(25, '10:00'), $d(25, '13:00'), false, '', 'Seminarraum (fiktiv)', 'Verständlich schreiben für Website und Newsletter.', 'workshop'],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $text, $cat]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'status' => 'published']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // Team: Monogramme statt Fotos (keine echten Personen)
    $t = fluid_demo_table([
        'handle' => 'showcase_team', 'name' => 'Team (Showcase)', 'singular' => 'Person', 'icon' => 'users',
        'description' => 'Beispiel-Team des Showcase – erfundene Namen, Monogramme statt Fotos.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'rolle', 'label' => 'Rolle', 'type' => 'text', 'in_list' => true],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
            ['name' => 'kurz', 'label' => 'Kurzvorstellung', 'type' => 'textarea'],
        ],
        'settings' => ['title_field' => 'name', 'image_field' => 'bild', 'description_field' => 'kurz', 'workflow' => 0],
    ], $log);
    if ($t) {
        $people = [
            ['Kim Muster', 'Konzept & Beratung', 'Hört zu, ordnet und schreibt die ersten Seiten.'],
            ['Sam Beispiel', 'Gestaltung', 'Farben, Schrift, Formen – und warum sie zusammenpassen.'],
            ['Jo Probe', 'Technik', 'Macht Websites schnell, sicher und barrierearm.'],
            ['Alex Test', 'Redaktion', 'Findet einfache Worte für komplizierte Dinge.'],
            ['Robin Leer', 'Projektleitung', 'Hält Termine, Budget und alle Fäden zusammen.'],
            ['Nika Vorlage', 'Schulung & Support', 'Erklärt das CMS so, dass es Spaß macht.'],
        ];
        foreach ($people as $i => [$name, $role, $text]) {
            Entries::save($t, null, ['name' => $name, 'rolle' => $role, 'kurz' => $text, 'bild' => $img['avatars'][$i] ?? null, 'status' => 'published']);
        }
        $out['team'] = $t;
    }

    // Formular 1: Rückrufwunsch
    $t = fluid_demo_table([
        'handle' => 'showcase_rueckruf', 'name' => 'Rückrufwünsche (Showcase)', 'singular' => 'Rückrufwunsch', 'icon' => 'phone',
        'description' => 'Öffentliches Beispiel-Formular des Showcase.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'telefon', 'label' => 'Telefon', 'type' => 'tel', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail (optional)', 'type' => 'email', 'width' => 'half'],
            ['name' => 'zeit', 'label' => 'Am besten erreichbar', 'type' => 'select', 'width' => 'half', 'options' => "vormittags=Vormittags\nnachmittags=Nachmittags\negal=Egal"],
            ['name' => 'thema', 'label' => 'Worum geht es?', 'type' => 'textarea', 'help' => 'Ein, zwei Sätze genügen.'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke! Wir rufen Sie zum gewünschten Zeitpunkt zurück (Demo – es wird niemand anrufen).', 'submit' => 'Rückruf anfordern']],
    ], $log);
    if ($t) $out['rueckruf'] = $t;

    // Formular 2: Bedingungen, IBAN, wiederholbare Gruppe
    $t = fluid_demo_table([
        'handle' => 'showcase_antrag', 'name' => 'Mitgliedsanträge (Showcase)', 'singular' => 'Mitgliedsantrag', 'icon' => 'clipboard-text',
        'description' => 'Beispiel für Bedingungen, IBAN-Prüfung und wiederholbare Gruppen.',
        'fields' => [
            ['name' => 'art', 'label' => 'Mitgliedschaft als', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => "person=Privatperson\nfirma=Firma oder Verein"],
            ['name' => 'firma', 'label' => 'Name der Firma / des Vereins', 'type' => 'text', 'width' => 'half',
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'firma']]],
                'required_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'firma']]]],
            ['name' => 'name', 'label' => 'Vor- und Nachname', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'width' => 'half'],
            ['name' => 'familie', 'label' => 'Weitere Personen (Familienmitgliedschaft)', 'type' => 'group', 'item_label' => 'Person', 'add_label' => 'Person hinzufügen', 'max' => 5,
                'fields' => [['name' => 'vorname', 'label' => 'Vorname', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'geburtsjahr', 'label' => 'Geburtsjahr', 'type' => 'number', 'width' => 'half']],
                'visible_if' => ['mode' => 'all', 'rules' => [['field' => 'art', 'op' => '=', 'value' => 'person']]]],
            ['name' => 'iban', 'label' => 'IBAN für den Beitrag', 'type' => 'iban', 'required' => true, 'help' => 'Beispiel zum Testen: DE02 1203 0000 0000 2020 51'],
            ['name' => 'newsletter', 'label' => 'Ich möchte den Newsletter erhalten', 'type' => 'bool'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Vielen Dank – Ihr Antrag ist eingegangen (Demo, es entsteht keine Mitgliedschaft).', 'submit' => 'Antrag absenden']],
    ], $log);
    if ($t) $out['antrag'] = $t;

    $log(count($out) . ' Datentabellen angelegt (showcase_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function fluid_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $W = fn(int $n) => $img['wide'][$n % max(1, count($img['wide']))] ?? null;
    $T = fn(int $n) => $img['tall'][$n % max(1, count($img['tall']))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text], ['background' => 'muted']);
    $cta = $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Genug gesehen? Dann legen Sie los.', 'text' => 'Alle Bausteine lassen sich frei kombinieren – Farben, Schriften, Kopf und Fuß stellen Sie unter Verwaltung → Design ein.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/showcase'], ['background' => 'accent']);
    $proj = $tables['projekte']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';
    $team = $tables['team']['handle'] ?? '';
    $mt = fn(string $v, string $eyebrow, string $title, string $text, ?int $image, string $ratio = '4:3', string $list = '', array $btn = []) => [
        'variant' => $v, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'list' => $list, 'image' => $image, 'ratio' => $ratio,
        'button_label' => $btn[0] ?? '', 'button_link' => $btn[1] ?? '', 'button2_label' => $btn[2] ?? '', 'button2_link' => $btn[3] ?? ''];

    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));

    // ---------------------------------------------------------------- Übersicht (Musterseite des Style-Editors)
    $root = $page(['slug' => 'showcase', 'title' => 'Showcase', 'sort' => $sort, 'og_image' => $W(0),
        'meta_description' => 'Alle Blöcke und Varianten des Kits „Fluid“ – breakpointlos, mit frei erfundenen Beispielinhalten.'], [
        $B('hero', ['variant' => 'cards', 'eyebrow' => 'Showcase (Demo)', 'title' => 'Gestaltung, die sich ihrem *Platz* anpasst.',
            'text' => 'Kein Layout in diesem Kit kennt Bildschirmbreiten. Raster füllen sich selbst, Karten stellen sich auf ihre eigene Breite ein, Schrift fließt stufenlos – vom Handy bis zum Kinobildschirm.',
            'button_label' => 'Blöcke ansehen', 'button_link' => '/showcase/inhalte', 'button2_label' => 'Artikel lesen', 'button2_link' => '/showcase/magazin',
            'points' => "Container-Queries\nFließende Typografie\nNeun Vorlagen", 'image' => $T(0), 'ratio' => '4:5',
            'cards' => [
                ['icon' => 'squares-four', 'title' => 'Intrinsische Raster', 'text' => 'auto-fit statt Spaltenzahlen'],
                ['icon' => 'text-t', 'title' => 'Fließende Stufen', 'text' => 'clamp() zwischen zwei Bildschirmbreiten'],
                ['icon' => 'sun', 'title' => 'Hell und dunkel', 'text' => 'alle Vorlagen AA-geprüft'],
            ]]),
        $B('logos', ['variant' => 'grid', 'eyebrow' => '', 'title' => 'Beispielhafte Auftraggeber (fiktiv)', 'items' => [
            ['name' => 'Nordlicht Werkstatt', 'link' => '', 'image' => null], ['name' => 'Kanzlei Aster', 'link' => '', 'image' => null],
            ['name' => 'Praxis am Park', 'link' => '', 'image' => null], ['name' => 'Verein Beispielstadt', 'link' => '', 'image' => null],
            ['name' => 'Café Morgenrot', 'link' => '', 'image' => null],
        ]], ['spaceTop' => 'small', 'spaceBottom' => 'small']),
        $B('bento', ['eyebrow' => 'Bento-Raster', 'title' => 'Sieben Bereiche, alle Varianten', 'intro' => 'Jede Kachel führt zu einer Unterseite. Die Kacheln rücken zusammen, sobald der Platz fehlt.', 'items' => [
            ['size' => 'big', 'tone' => 'image', 'image' => $W(1), 'icon' => '', 'eyebrow' => 'Abschnitte', 'title' => 'Vollflächig, geteilt, überlappend', 'text' => 'Einstiege, Hintergründe und Split-Screens.', 'link' => '/showcase/abschnitte'],
            ['size' => 's', 'tone' => 'highlight', 'icon' => 'squares-four', 'eyebrow' => '', 'title' => 'Inhalte', 'text' => 'Merkmale, Karten, Zitate, Tabs, Pakete, FAQ.', 'link' => '/showcase/inhalte'],
            ['size' => 's', 'tone' => 'card', 'icon' => 'images', 'eyebrow' => '', 'title' => 'Medien', 'text' => 'Galerie, Slider, Scrollytelling, Video.', 'link' => '/showcase/medien'],
            ['size' => 'tall', 'tone' => 'dark', 'icon' => 'database', 'eyebrow' => '6', 'title' => 'Datenlisten und Kalender', 'text' => 'Projekte, Team, Termine mit Wiederholung.', 'link' => '/showcase/daten'],
            ['size' => 'wide', 'tone' => 'accent', 'icon' => 'clipboard-text', 'eyebrow' => '', 'title' => 'Formulare & Kontakt', 'text' => 'Bedingungen, IBAN, Gruppen – Spamschutz ohne Cookies.', 'link' => '/showcase/formulare'],
            ['size' => 's', 'tone' => 'tint', 'icon' => 'article', 'eyebrow' => '', 'title' => 'Magazin', 'text' => 'Artikel mit Inhaltsverzeichnis.', 'link' => '/showcase/magazin'],
        ]], ['background' => 'muted']),
        $B('features', ['variant' => 'icons', 'eyebrow' => 'Merkmale „Große Symbole“', 'title' => 'Das Kit in vier Sätzen', 'intro' => '', 'size' => 's', 'items' => [
            ['icon' => 'arrows-clockwise', 'title' => 'Intrinsisch', 'text' => 'Layouts reagieren auf ihren Platz – auch in schmalen Spalten.', 'link_label' => '', 'link' => ''],
            ['icon' => 'palette', 'title' => 'Token-gesteuert', 'text' => 'Farben, Schrift, Formen, Kopf und Fuß im Style-Editor.', 'link_label' => '', 'link' => ''],
            ['icon' => 'shield-check', 'title' => 'Datensparsam', 'text' => 'Keine Cookies, keine externen Anfragen, keine Inline-Skripte.', 'link_label' => '', 'link' => ''],
            ['icon' => 'wheelchair', 'title' => 'Zugänglich', 'text' => 'WCAG 2.2 AA, Tastatur, Bewegung reduzieren.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('media_text', $mt('auto', 'Text + Bild „Abwechselnd“', 'Mehrere Blöcke wechseln von selbst die Seite', '<p>Stehen mehrere Blöcke „Text + Bild“ mit „Abwechselnd“ hintereinander, wechselt das Bild automatisch zwischen rechts und links. Bild und Text stehen nebeneinander, solange beide Hälften genug Platz haben – ganz ohne Breakpoint.</p>', $W(2), '4:3', "Ohne Einstellungen\nFunktioniert in jeder Spaltenbreite", ['Mehr Abschnitte', '/showcase/abschnitte'])),
        $B('media_text', $mt('auto', 'Zweiter Block', 'Und hier steht das Bild links', '<p>Die Reihenfolge im Quelltext bleibt gleich (erst Text, dann Bild) – nur die Darstellung wechselt. Screenreader lesen immer in derselben Reihenfolge.</p>', $W(3), '4:3')),
        $B('stats', ['variant' => 'split', 'eyebrow' => 'Kennzahlen „Nebeneinander“', 'title' => 'Beispielwerte, frei erfunden', 'intro' => 'Die Zahlen wachsen mit ihrer Zelle (Container-Einheiten).', 'items' => [
            ['value' => '20+', 'label' => 'Blöcke', 'text' => 'mit über 50 Varianten'],
            ['value' => '9', 'label' => 'Vorlagen', 'text' => 'für ganz unterschiedliche Projekte'],
            ['value' => '0', 'label' => 'Breakpoints', 'text' => 'nur Container und clamp()'],
        ]], ['background' => 'dark']),
        $B('cards', ['variant' => 'reel', 'eyebrow' => 'Karten „Band“', 'title' => 'Zum Wischen', 'intro' => 'Scroll-Snap ohne Bibliothek – die Pfeile erscheinen nur, wenn es etwas zu blättern gibt.', 'size' => 'm', 'ratio' => '3:2', 'more_label' => '', 'more_link' => '', 'items' => [
            ['image' => $W(4), 'eyebrow' => 'Abschnitte', 'title' => 'Split-Screen und Vollbild', 'text' => 'Hintergründe, Überlappungen, Bildschirmhöhe.', 'link_label' => 'Ansehen', 'link' => '/showcase/abschnitte'],
            ['image' => $W(5), 'eyebrow' => 'Inhalte', 'title' => 'Karten, Tabs, Pakete', 'text' => 'Alle Inhaltsblöcke mit Varianten.', 'link_label' => 'Ansehen', 'link' => '/showcase/inhalte'],
            ['image' => $W(6), 'eyebrow' => 'Medien', 'title' => 'Galerie und Scrollytelling', 'text' => 'Mosaik, Zeilen, Slider, Video.', 'link_label' => 'Ansehen', 'link' => '/showcase/medien'],
            ['image' => $W(7), 'eyebrow' => 'Daten', 'title' => 'Projekte, Team, Termine', 'text' => 'Datentabellen in allen Darstellungen.', 'link_label' => 'Ansehen', 'link' => '/showcase/daten'],
            ['image' => $W(8), 'eyebrow' => 'Formulare', 'title' => 'Kontakt mit Karte', 'text' => 'Formular, Öffnungszeiten, Karte.', 'link_label' => 'Ansehen', 'link' => '/showcase/formulare'],
        ]]),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Stimmen „Raster“', 'title' => 'Mauerwerk aus Zitaten', 'items' => [
            ['text' => 'Beispielzitat: Die Seite sieht auf dem alten Tablet am Empfang genauso ordentlich aus wie am großen Bildschirm.', 'name' => 'Kim Muster', 'role' => 'Praxis am Park (fiktiv)', 'image' => $img['avatars'][0] ?? null],
            ['text' => 'Beispielzitat: Wir haben die Vorlage „Handwerk“ genommen, Farbe angepasst – fertig.', 'name' => 'Sam Beispiel', 'role' => 'Nordlicht Werkstatt (fiktiv)', 'image' => $img['avatars'][1] ?? null],
            ['text' => 'Beispielzitat: Endlich ein Kalender, den wir selbst pflegen und den alle abonnieren können. Die Termine erscheinen automatisch auf der Startseite und im Vereinsheft.', 'name' => 'Jo Probe', 'role' => 'Verein Beispielstadt (fiktiv)', 'image' => $img['avatars'][2] ?? null],
            ['text' => 'Beispielzitat: Keine Cookie-Banner mehr. Das allein war den Umstieg wert.', 'name' => 'Alex Test', 'role' => 'Café Morgenrot (fiktiv)', 'image' => $img['avatars'][3] ?? null],
            ['text' => 'Beispielzitat: Die großen Überschriften in Serifen geben unserer Ausstellung genau die Ruhe, die sie braucht.', 'name' => 'Robin Leer', 'role' => 'Museum Muster (fiktiv)', 'image' => $img['avatars'][4] ?? null],
        ]], ['background' => 'tint']),
        $B('cta', ['variant' => 'big', 'eyebrow' => 'Handlungsaufruf „Großer Schriftzug“', 'title' => 'Bereit für *Ihr* Projekt?', 'text' => 'Die Überschrift füllt die Breite – in Container-Einheiten, damit sie nie überläuft.',
            'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => 'Blöcke ansehen', 'button2_link' => '/showcase/inhalte', 'image' => null], ['background' => 'accent']),
    ]);

    // ---------------------------------------------------------------- Abschnitte & Einstiege
    $page(['slug' => 'abschnitte', 'title' => 'Abschnitte', 'parent_id' => $root, 'sort' => 1, 'og_image' => $W(1),
        'meta_description' => 'Einstieg vollflächig, Split-Screen, überlappende Karte, Hintergründe und Vollbild-Abschnitte.'], [
        $B('hero', ['variant' => 'fullbleed', 'eyebrow' => 'Einstieg „Vollflächig“', 'title' => 'Bild über die ganze Fläche.',
            'text' => 'Mit Verlauf von unten für gute Lesbarkeit. Statt des Bildes kann ein stummes MP4-Video laufen – nie bei „Bewegung reduzieren“, immer mit Pause-Schaltfläche.',
            'button_label' => 'Zum Split-Screen', 'button_link' => '#split', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/showcase', 'points' => '',
            'image' => $W(1), 'ratio' => '16:9', 'video' => null, 'overlay' => 'gradient', 'cards' => []]),
        $B('media_text', $mt('split', 'Text + Bild „Geteilt“', 'Split-Screen, randlos', '<p>Bild und Text teilen sich den Abschnitt je zur Hälfte. Fehlt der Platz für zwei Hälften von mindestens 24 rem, steht das Bild über dem Text.</p>', $W(2), '4:3', "Randlos bis an den Fensterrand\nOptional in Bildschirmhöhe"), ['anchor' => 'split', 'height' => 'screen']),
        $B('media_text', $mt('split', 'Zweiter geteilter Abschnitt', 'Wechselt automatisch die Seite', '<p>Folgt ein weiterer geteilter Abschnitt, steht das Bild auf der anderen Seite.</p>', $W(3), '4:3'), ['background' => 'muted']),
        $B('media_text', $mt('overlap', 'Text + Bild „Überlappend“', 'Karte über großem Bild', '<p>Ab 52 rem Inhaltsbreite liegt die Karte über dem Bild; darunter rutscht sie leicht überlappend unter das Bild.</p>', $W(5), '16:9', '', ['Mehr erfahren', '/showcase/inhalte'])),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Hintergrund „Akzent hell“', 'title' => 'Alles bleibt lesbar', 'intro' => 'Schrift, Symbole und Buttons stellen sich automatisch auf jeden Hintergrund ein.', 'size' => 's', 'items' => [
            ['icon' => 'check-circle', 'title' => 'Kontrast geprüft', 'text' => 'Alle Vorlagen erfüllen WCAG 2.2 AA.', 'link_label' => '', 'link' => ''],
            ['icon' => 'shield-check', 'title' => 'Ohne Inline-Styles', 'text' => 'Farben kommen aus einer CSS-Datei mit Variablen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'lightning', 'title' => 'Schnell', 'text' => 'Nur die gewählten Schriften werden geladen.', 'link_label' => '', 'link' => ''],
        ]], ['background' => 'tint']),
        $B('cta', ['variant' => 'box', 'eyebrow' => 'Abschnitt mit Hintergrundbild', 'title' => 'Vollbild mit Abdunkelung', 'text' => 'Abschnitts-Optionen: Höhe „Bildschirmhöhe“, Hintergrundbild, Abdunkelung und Ausrichtung.',
            'button_label' => 'Zur Übersicht', 'button_link' => '/showcase', 'button2_label' => '', 'button2_link' => '', 'image' => null],
            ['background' => 'dark', 'height' => 'screen', 'bgImage' => $W(8), 'overlay' => 'dark', 'align' => 'center']),
        $B('media_text', $mt('left', 'Bild mit Aufhellung', 'Heller Schleier für ruhige Texte', '<p>Mit „Aufhellen“ liegt ein heller Schleier über dem Hintergrundbild – dunkle Schrift bleibt gut lesbar.</p>', $W(9), '1:1'), ['bgImage' => $W(6), 'overlay' => 'light']),
        $B('stats', ['variant' => 'cards', 'eyebrow' => 'Hintergrund „Akzentfarbe“', 'title' => 'Kennzahlen als Karten', 'intro' => '', 'items' => [
            ['value' => '5', 'label' => 'Hintergründe', 'text' => 'plus Bild mit Abdunkelung'], ['value' => '2', 'label' => 'Schleier', 'text' => 'hell oder dunkel'],
            ['value' => '100 %', 'label' => 'Bildschirmhöhe', 'text' => 'für Vollbild-Abschnitte'],
        ]], ['background' => 'accent']),
        $B('richtext', ['variant' => 'standard', 'eyebrow' => 'Fließtext', 'title' => 'Typografie im Fließtext', 'intro' => '', 'meta' => '', 'dropcap' => false, 'toc' => false, 'text' =>
            '<p>Der Block „Fließtext“ eignet sich für längere Texte. Er kennt <strong>Hervorhebungen</strong>, <a href="/showcase">Links</a> und Zwischenüberschriften – die Zeilenlänge bleibt angenehm (68 Zeichen).</p>'
            . '<h3>Zwischenüberschrift</h3><p>Überschriften und Absätze nutzen die fließenden Stufen des Kits.</p>'
            . '<ul><li>Aufzählung mit Punkten</li><li>Zweiter Punkt</li></ul><ol><li>Nummerierte Liste</li><li>Zweiter Schritt</li></ol>'
            . ''
            . '<h4>Kleinere Überschrift</h4><p>Zum Schluss ein Absatz mit einem Link zur <a href="/showcase/magazin">Magazin-Seite</a>.</p>'], ['background' => 'muted']),
        $cta,
    ]);

    // ---------------------------------------------------------------- Inhalte
    $page(['slug' => 'inhalte', 'title' => 'Inhalte', 'parent_id' => $root, 'sort' => 2,
        'meta_description' => 'Merkmale, Karten, Zitate, Ablauf, Reiter, Pakete, Vergleich, FAQ und Logos – jeweils in mehreren Varianten.'], [
        $hero('Einstieg „Seitenkopf“', 'Blöcke für Inhalte', 'Jeder Block zeigt hier seine Varianten. Ändern Sie die Fenstergröße: Nichts springt, alles fließt.'),
        $B('features', ['variant' => 'list', 'eyebrow' => 'Merkmale „Liste“', 'title' => 'Symbol links, Text rechts', 'intro' => 'Das Symbol rutscht über den Text, wenn der Eintrag zu schmal wird.', 'size' => 'l', 'items' => [
            ['icon' => 'chat-circle-text', 'title' => 'Beratung', 'text' => 'Zuhören, ordnen, Wege aufzeigen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'pencil-ruler', 'title' => 'Planung', 'text' => 'Ziele, Schritte, Zuständigkeiten.', 'link_label' => '', 'link' => ''],
            ['icon' => 'hammer', 'title' => 'Umsetzung', 'text' => 'Abgestimmt und termintreu.', 'link_label' => '', 'link' => ''],
            ['icon' => 'hand-heart', 'title' => 'Betreuung', 'text' => 'Auch nach dem Abschluss.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('features', ['variant' => 'numbered', 'eyebrow' => 'Merkmale „Nummeriert“', 'title' => 'Drei Gründe', 'intro' => '', 'size' => 'm', 'items' => [
            ['title' => 'Klarheit', 'text' => 'Eine Aussage pro Abschnitt – leicht zu überfliegen.', 'link_label' => '', 'link' => ''],
            ['title' => 'Tempo', 'text' => 'Nur das CSS, das die Seite braucht.', 'link_label' => '', 'link' => ''],
            ['title' => 'Haltbarkeit', 'text' => 'Standards statt Bibliotheken.', 'link_label' => '', 'link' => ''],
        ]], ['background' => 'muted']),
        $B('cards', ['variant' => 'image', 'eyebrow' => 'Karten „Bild oben“', 'title' => 'Teaser mit Bild', 'intro' => 'Ohne Linktext ist die ganze Karte klickbar.', 'size' => 'm', 'ratio' => '3:2', 'more_label' => 'Alle Projekte', 'more_link' => '/showcase/daten', 'items' => [
            ['image' => $W(1), 'eyebrow' => 'Konzept', 'title' => 'Leitsystem für ein Bürgerhaus', 'text' => 'Klare Wege auf drei Etagen.', 'link_label' => '', 'link' => '/showcase/daten'],
            ['image' => $W(2), 'eyebrow' => 'Gestaltung', 'title' => 'Neues Erscheinungsbild', 'text' => 'Farben, Schrift und Formen als Tokens.', 'link_label' => 'Weiterlesen', 'link' => '/showcase/daten'],
            ['image' => $W(3), 'eyebrow' => 'Umsetzung', 'title' => 'Online-Terminbuchung', 'text' => 'Vom Papierkalender ins Netz.', 'link_label' => 'Weiterlesen', 'link' => '/showcase/daten'],
        ]]),
        $B('cards', ['variant' => 'overlay', 'eyebrow' => 'Karten „Text auf dem Bild“', 'title' => 'Bildstark', 'intro' => '', 'size' => 'm', 'ratio' => '4:5', 'more_label' => '', 'more_link' => '', 'items' => [
            ['image' => $T(1), 'eyebrow' => 'Werkstatt', 'title' => 'Handwerk mit Haltung', 'text' => 'Beispieltext auf dem Bild.', 'link_label' => '', 'link' => '/showcase'],
            ['image' => $T(2), 'eyebrow' => 'Praxis', 'title' => 'Ruhe im Wartezimmer', 'text' => 'Beispieltext auf dem Bild.', 'link_label' => '', 'link' => '/showcase'],
            ['image' => $T(3), 'eyebrow' => 'Kultur', 'title' => 'Ausstellung im Wandel', 'text' => 'Beispieltext auf dem Bild.', 'link_label' => '', 'link' => '/showcase'],
        ]], ['background' => 'dark']),
        $B('cards', ['variant' => 'horizontal', 'eyebrow' => 'Karten „Quer“', 'title' => 'Bild neben dem Text – wenn die Karte breit genug ist', 'intro' => 'Jede Karte ist ein Container: in breiten Rastern quer, in schmalen Spalten hochkant.', 'size' => 'l', 'ratio' => '4:3', 'more_label' => '', 'more_link' => '', 'items' => [
            ['image' => $W(4), 'eyebrow' => 'Beitrag', 'title' => 'Warum Container-Queries Breakpoints ersetzen', 'text' => 'Komponenten wissen nichts vom Bildschirm – nur von ihrem Platz.', 'link_label' => 'Lesen', 'link' => '/showcase/magazin'],
            ['image' => $W(5), 'eyebrow' => 'Beitrag', 'title' => 'Fließende Typografie mit clamp()', 'text' => 'Zwei Grundgrößen, ein Verhältnis – der Rest rechnet sich.', 'link_label' => 'Lesen', 'link' => '/showcase/magazin'],
        ]], ['background' => 'muted']),
        $B('quote', ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
            ['text' => 'Beispielzitat: Eine Website ist dann fertig, wenn man nichts mehr weglassen kann.', 'name' => 'Studio Beispiel', 'role' => 'Leitgedanke (Demo)', 'image' => null],
        ]], ['background' => 'tint']),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Ablauf „Zeitleiste“', 'title' => 'Ein Projekt im Zeitverlauf', 'intro' => 'Datum links, sobald der Block breit genug ist.', 'items' => [
            ['meta' => 'Woche 1', 'title' => 'Kennenlernen', 'text' => 'Ziele, Rahmen und Ansprechpartner klären.', 'icon' => ''],
            ['meta' => 'Woche 2–3', 'title' => 'Konzept', 'text' => 'Vorschlag mit Aufwand und Terminen.', 'icon' => ''],
            ['meta' => 'Woche 4–10', 'title' => 'Umsetzung', 'text' => 'Regelmäßige, kurze Abstimmungen.', 'icon' => ''],
            ['meta' => 'Woche 11', 'title' => 'Übergabe', 'text' => 'Dokumentation, Schulung, Abschluss.', 'icon' => ''],
        ]]),
        $B('steps', ['variant' => 'numbers', 'eyebrow' => 'Ablauf „Nummeriert“', 'title' => 'In vier Schritten', 'intro' => '', 'items' => [
            ['meta' => '', 'title' => 'Vorlage wählen', 'text' => 'Eine der neun Vorlagen als Ausgangspunkt.', 'icon' => ''],
            ['meta' => '', 'title' => 'Anpassen', 'text' => 'Farbe, Schrift, Kopf und Fuß.', 'icon' => ''],
            ['meta' => '', 'title' => 'Inhalte pflegen', 'text' => 'Blöcke kombinieren, Texte direkt ändern.', 'icon' => ''],
            ['meta' => '', 'title' => 'Veröffentlichen', 'text' => 'Prüfen, veröffentlichen – fertig.', 'icon' => ''],
        ]], ['background' => 'muted']),
        $B('tabs', ['variant' => 'top', 'eyebrow' => 'Reiter „oben“', 'title' => 'Inhalte zum Umschalten', 'intro' => 'Ohne JavaScript stehen alle Inhalte untereinander.', 'items' => [
            ['label' => 'Beratung', 'text' => '<p>Wir hören zu und ordnen Ihre Anforderungen. Sie erhalten eine ehrliche Einschätzung von Aufwand und Nutzen.</p><ul><li>Erstgespräch</li><li>Kurze Analyse</li></ul>', 'image' => $W(6)],
            ['label' => 'Planung', 'text' => '<p>Aus Zielen wird ein Plan mit Schritten, Zuständigkeiten und Terminen.</p>', 'image' => $W(7)],
            ['label' => 'Umsetzung', 'text' => '<p>Wir setzen um oder begleiten Ihr Team – mit kurzen, regelmäßigen Abstimmungen.</p>', 'image' => null],
        ]]),
        $B('tabs', ['variant' => 'side', 'eyebrow' => 'Reiter „links“', 'title' => 'Häufige Themen', 'intro' => 'Die Leiste steht links, solange genug Platz bleibt – sonst oben.', 'items' => [
            ['label' => 'Zusammenarbeit', 'text' => '<p>Feste Ansprechperson, klare Absprachen, nachvollziehbare Angebote.</p>', 'image' => null],
            ['label' => 'Kosten', 'text' => '<p>Sie erhalten vorab ein schriftliches Angebot – ohne versteckte Kosten.</p>', 'image' => null],
            ['label' => 'Termine', 'text' => '<p>Realistische Zeitpläne und frühe Rückmeldung, falls sich etwas ändert.</p>', 'image' => null],
        ]], ['background' => 'muted']),
        $B('pricing', ['variant' => 'table', 'eyebrow' => 'Pakete „Vergleichstabelle“', 'title' => 'Pakete im Vergleich', 'intro' => 'Die erste Spalte bleibt beim waagerechten Scrollen stehen. Preise frei erfunden.', 'note' => 'Alle Preise sind Beispielwerte (Demo).',
            'items' => [
                ['name' => 'Start', 'badge' => '', 'price' => '1.900 €', 'period' => 'einmalig', 'text' => '', 'features' => '', 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
                ['name' => 'Wachstum', 'badge' => 'Beliebt', 'price' => '4.800 €', 'period' => 'einmalig', 'text' => '', 'features' => '', 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
                ['name' => 'Begleitung', 'badge' => '', 'price' => '190 €', 'period' => 'pro Monat', 'text' => '', 'features' => '', 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ],
            'rows' => [
                ['label' => 'Seiten', 'values' => "bis 5\nbis 20\n–"], ['label' => 'Eigene Farben und Schriften', 'values' => "nein\nja\nja"],
                ['label' => 'Termine und Formulare', 'values' => "nein\nja\nja"], ['label' => 'Schulung', 'values' => "1 Stunde\nhalber Tag\nnach Bedarf"],
                ['label' => 'Updates und Sicherung', 'values' => "–\n–\nja"],
            ]]),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'FAQ „Untereinander“', 'title' => 'Fragen zum Showcase', 'intro' => 'Suchmaschinen erhalten die Fragen als strukturierte Daten.', 'items' => [
            ['q' => 'Sind die Inhalte echt?', 'a' => '<p>Nein. Alle Namen, Zahlen, Termine und Zitate sind frei erfunden.</p>'],
            ['q' => 'Kann ich den Showcase löschen?', 'a' => '<p>Ja – den Seitenbaum „Showcase“ und die Tabellen „… (Showcase)“ löschen, oder „php kits/fluid/tools/demo.php --remove“ ausführen.</p>'],
            ['q' => 'Wo stelle ich Farben und Schriften ein?', 'a' => '<p>Unter Verwaltung → Design. Die Vorschau zeigt den Showcase als Musterseite.</p>'],
        ]], ['background' => 'muted']),
        $B('logos', ['variant' => 'marquee', 'eyebrow' => 'Logos „Laufband“', 'title' => 'Hält bei Maus und Fokus an', 'items' => [
            ['name' => 'Nordlicht Werkstatt', 'link' => '', 'image' => null], ['name' => 'Kanzlei Aster', 'link' => '', 'image' => null], ['name' => 'Praxis am Park', 'link' => '', 'image' => null],
            ['name' => 'Verein Beispielstadt', 'link' => '', 'image' => null], ['name' => 'Café Morgenrot', 'link' => '', 'image' => null], ['name' => 'Museum Muster', 'link' => '', 'image' => null],
        ]]),
        $B('cta', ['variant' => 'split', 'eyebrow' => 'Handlungsaufruf „Mit Bild“', 'title' => 'Box mit Bild daneben', 'text' => 'Bild und Text stehen nebeneinander, solange beide Platz haben.', 'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email', 'image' => $W(0)]),
    ]);

    // ---------------------------------------------------------------- Medien
    $page(['slug' => 'medien', 'title' => 'Medien', 'parent_id' => $root, 'sort' => 3, 'og_image' => $W(6),
        'meta_description' => 'Galerie (Mosaik, Raster, Zeilen) mit Lightbox, Slider, Stapelkarten, Scrollytelling, Video, Downloads und Karte.'], [
        $B('hero', ['variant' => 'centered', 'eyebrow' => 'Einstieg „Zentriert“', 'title' => 'Bilder, Video, Dateien, Karte.', 'text' => 'Alles kommt vom eigenen Server – Videos von YouTube oder Vimeo erst nach einem Klick.',
            'button_label' => 'Zur Galerie', 'button_link' => '#galerie', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => $W(6), 'ratio' => '16:9', 'video' => null, 'overlay' => 'strong', 'cards' => []]),
        $B('gallery', ['eyebrow' => 'Galerie „Mosaik“', 'title' => 'Mauerwerk mit CSS-Spalten', 'intro' => 'So viele Spalten, wie passen – ein Klick öffnet die Lightbox.', 'source' => 'collection', 'collection' => $img['collection'],
            'layout' => 'masonry', 'columns' => '3', 'ratio' => '4:3', 'captions' => false, 'lightbox' => true], ['anchor' => 'galerie']),
        $B('gallery', ['eyebrow' => 'Galerie „Zeilen“', 'title' => 'Bündige Zeilen', 'intro' => '', 'source' => 'manual',
            'images' => array_map(fn($id) => ['image' => $id, 'caption' => ''], array_slice($img['all'], 0, 8)), 'layout' => 'justified', 'columns' => '3', 'ratio' => '4:3', 'captions' => false, 'lightbox' => true], ['background' => 'muted']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Folien mit Text', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $W(0), 'eyebrow' => 'Folie 1', 'title' => 'Abgedunkelt mit heller Schrift', 'text' => 'Die Standard-Einstellung für Folien mit Text.', 'button_label' => 'Mehr', 'button_link' => '/showcase', 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $W(3), 'eyebrow' => 'Folie 2', 'title' => 'Textkasten', 'text' => 'Ein Kasten in der Hintergrundfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
            ['image' => $W(9), 'eyebrow' => 'Folie 3', 'title' => 'Aufgehellt', 'text' => 'Dunkle Schrift auf hellem Schleier.', 'button_label' => '', 'button_link' => '', 'position' => 'center', 'overlay' => 'light'],
        ]]),
        $B('scrolly', ['variant' => 'left', 'eyebrow' => 'Scrollytelling', 'title' => 'Das Bild bleibt, die Geschichte zieht vorbei', 'intro' => 'Auf breiten Flächen bleibt das Bild stehen und wechselt mit dem Abschnitt; auf schmalen steht jedes Bild bei seinem Text.', 'ratio' => '4:5', 'items' => [
            ['eyebrow' => 'Kapitel 1', 'title' => 'Am Anfang steht eine Frage', 'text' => 'Was sollen Besucherinnen und Besucher nach zehn Sekunden wissen? Die Antwort bestimmt alles Weitere.', 'image' => $T(0)],
            ['eyebrow' => 'Kapitel 2', 'title' => 'Dann kommt die Ordnung', 'text' => 'Inhalte werden sortiert, gekürzt und in eine klare Reihenfolge gebracht – bevor die erste Farbe gewählt wird.', 'image' => $T(1)],
            ['eyebrow' => 'Kapitel 3', 'title' => 'Zum Schluss die Form', 'text' => 'Farben, Schrift und Abstände geben der Ordnung ein Gesicht – als Tokens, damit alles zusammenpasst.', 'image' => $T(2)],
        ]], ['background' => 'muted']),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Karten, die sich stapeln', 'intro' => 'Nur bei genug Platz und ohne „Bewegung reduzieren“ – sonst eine ruhige Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Verstehen', 'text' => 'Wir klären Ziele und Rahmenbedingungen.', 'image' => $W(1), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Planen', 'text' => 'Ein belastbarer Plan mit Meilensteinen.', 'image' => $W(2), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Umsetzen', 'text' => 'Abgestimmt, termintreu, dokumentiert.', 'image' => $W(7), 'link_label' => 'Kontakt', 'link' => '/kontakt'],
        ]]),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video „Mit Text“', 'title' => 'Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W(4), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)'], ['background' => 'muted']),
        $B('video', ['variant' => 'cinema', 'eyebrow' => 'Video „Kino“', 'title' => 'Randlos auf dunkler Fläche', 'intro' => '', 'text' => '',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $W(8), 'ratio' => '21-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Dateien zum Herunterladen', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true, 'collection' => null,
            'files' => $pdf ? [['label' => 'Leistungsübersicht (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []]),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort', 'location' => 'site', 'zoom' => '13', 'height' => 'm', 'route' => true], ['background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Daten & Termine
    if ($proj !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-showcase-projekte', 'title' => 'Projekte (Showcase) – Detailseite', 'type' => 'template', 'template_for' => $proj, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.kategorie", "$proj.jahr", "$proj.bild"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '21:9', 'back_label' => 'Alle Projekte', 'back_link' => '/showcase/daten'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext", "$proj.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.auftraggeber", "$proj.jahr", "$proj.kategorie"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => '', 'title' => 'Weitere Projekte', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.kategorie"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '16:10', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
        ]));
        $t = Tables::find($proj);
        $t['settings']['detail_page_id'] = $tpl;
        app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $dl = fn(string $table, string $layout, array $extra) => $extra + ['eyebrow' => '', 'intro' => '', 'table' => $table, 'layout' => $layout, 'columns' => '3', 'ratio' => '16:10', 'limit' => 0,
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $page(['slug' => 'daten', 'title' => 'Daten & Termine', 'parent_id' => $root, 'sort' => 4,
        'meta_description' => 'Datenlisten als Karten, Liste, kompakt und Tabelle; Team mit Porträts; Kalender und nächste Termine.'], [
        $B('hero', ['variant' => 'type', 'eyebrow' => 'Einstieg „Großer Schriftzug“', 'title' => 'Einmal pflegen, *überall* zeigen.', 'text' => 'Einträge stehen in Datentabellen und erscheinen in verschiedenen Darstellungen – mit Detailseite, Filter und Kalender-Abo.',
            'button_label' => 'Zu den Terminen', 'button_link' => '#termine', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => null, 'ratio' => '16:9', 'video' => null, 'overlay' => 'strong', 'cards' => []]),
        $B('data_list', $dl($proj, 'cards', ['eyebrow' => 'Datenliste „Karten“', 'title' => 'Projekte', 'fields' => ["$proj._title", "$proj.bild", "$proj.kategorie", "$proj.kurztext"], 'limit' => 3, 'more_label' => 'Alle als Tabelle', 'more_link' => '#tabelle'])),
        $B('data_list', $dl($proj, 'list', ['eyebrow' => 'Datenliste „Liste mit Bild“', 'title' => 'Projekte als Liste', 'fields' => ["$proj._title", "$proj.bild", "$proj.auftraggeber", "$proj.kurztext"], 'limit' => 3]), ['background' => 'muted']),
        $B('data_list', $dl($proj, 'compact', ['eyebrow' => 'Datenliste „Kompakt“', 'title' => 'Projekte kompakt', 'fields' => ["$proj._title", "$proj.jahr", "$proj.kategorie"], 'limit' => 4])),
        $B('data_list', $dl($proj, 'table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Alle Projekte', 'fields' => ["$proj._title", "$proj.auftraggeber", "$proj.kategorie", "$proj.jahr"]]), ['anchor' => 'tabelle', 'background' => 'muted']),
        $B('data_list', $dl($team, 'cards', ['eyebrow' => 'Team „Karten“ (Bildformat 3:4)', 'title' => 'Das Team', 'intro' => 'Monogramme statt Fotos – alle Personen sind erfunden.', 'fields' => ["$team._title", "$team.bild", "$team.rolle", "$team.kurz"], 'columns' => '4', 'ratio' => '3:4', 'link_detail' => false])),
        $B('data_list', $dl($team, 'list', ['eyebrow' => 'Team „Liste“ (Bildformat 1:1 → Porträtkreis)', 'title' => 'Ansprechpersonen', 'fields' => ["$team._title", "$team.bild", "$team.rolle"], 'ratio' => '1:1', 'limit' => 4, 'link_detail' => false]), ['background' => 'muted']),
        $B('calendar', ['eyebrow' => 'Kalender', 'title' => 'Monatsübersicht', 'intro' => 'Unter 44 rem Blockbreite wird die Übersicht zur Liste der Tage mit Terminen.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => false], ['anchor' => 'termine']),
        $B('upcoming', ['eyebrow' => 'Nächste Termine', 'title' => 'Demnächst', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true, 'link_detail' => false, 'subscribe' => true, 'more_label' => 'Terminliste', 'more_link' => '#agenda'], ['background' => 'muted']),
        $B('calendar', ['eyebrow' => '', 'title' => 'Terminliste des Monats', 'intro' => '', 'table' => $term, 'view' => 'agenda', 'visitor_filter' => false, 'subscribe' => false, 'link_detail' => false], ['anchor' => 'agenda']),
        $B('upcoming', ['eyebrow' => 'Kompakt auf dunkler Fläche', 'title' => 'Die nächsten drei', 'intro' => '', 'table' => $term, 'limit' => 3, 'days' => 0, 'layout' => 'compact', 'show_location' => true, 'link_detail' => false, 'subscribe' => false], ['background' => 'dark']),
    ]);

    // ---------------------------------------------------------------- Formulare & Kontakt
    $page(['slug' => 'formulare', 'title' => 'Formulare & Kontakt', 'parent_id' => $root, 'sort' => 5,
        'meta_description' => 'Öffentliche Formulare mit Fehler- und Erfolgsmeldung, Bedingungen, IBAN-Prüfung und Gruppen; Kontakt mit Karte, Formular und Öffnungszeiten.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Einstieg „Nebeneinander“', 'title' => 'Formulare, die man gern ausfüllt.', 'text' => 'Einträge landen in einer Datentabelle – mit Spamschutz ohne Cookies. Absenden ist gefahrlos: es sind Demo-Tabellen.',
            'button_label' => 'Zum Kontakt', 'button_link' => '#kontakt', 'button2_label' => '', 'button2_link' => '', 'points' => "Fehler erscheinen am Feld und oben als Liste\nBedingungen und IBAN-Prüfung", 'image' => $T(3), 'ratio' => '4:5', 'video' => null, 'overlay' => 'strong', 'cards' => []]),
        $B('data_form', ['eyebrow' => 'Einfaches Formular', 'title' => 'Rückruf anfordern', 'intro' => 'Pflichtfelder sind markiert. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $tables['rueckruf']['handle'] ?? '', 'submit_label' => '', 'success_text' => ''], ['background' => 'muted']),
        $B('data_form', ['eyebrow' => 'Mit Bedingungen, IBAN und Gruppe', 'title' => 'Mitgliedsantrag (Beispiel)', 'intro' => '„Firma oder Verein“ blendet ein weiteres Feld ein; bei „Privatperson“ lassen sich weitere Personen hinzufügen.', 'table' => $tables['antrag']['handle'] ?? '', 'submit_label' => '', 'success_text' => '']),
        $B('contact', ['eyebrow' => 'Kontakt', 'title' => 'So erreichen Sie uns', 'intro' => 'Angaben, Öffnungszeiten und Karte kommen aus „Website“; daneben ein Formular.', 'show_hours' => true, 'show_map' => true,
            'form_table' => $tables['rueckruf']['handle'] ?? '', 'form_title' => 'Rückruf anfordern', 'submit_label' => '', 'note' => 'Beispieladresse – frei erfunden.'], ['anchor' => 'kontakt', 'background' => 'muted']),
    ]);

    // ---------------------------------------------------------------- Magazin (Artikel)
    $page(['slug' => 'magazin', 'title' => 'Magazin', 'parent_id' => $root, 'sort' => 6, 'og_image' => $W(4),
        'meta_description' => 'Artikel mit Lesebreite, Inhaltsverzeichnis und Initiale; Zeitungssatz in Spalten.'], [
        $hero('Magazin', 'Warum Container-Queries Breakpoints ersetzen', 'Ein Beispielartikel: Lesebreite, automatisches Inhaltsverzeichnis, Lesezeit – und ein zweiter Text im Zeitungssatz.'),
        $B('richtext', ['variant' => 'article', 'eyebrow' => 'Beitrag', 'title' => 'Komponenten kennen ihren Platz', 'intro' => '', 'meta' => 'Redaktion Studio Beispiel · Beispieltext', 'dropcap' => true, 'toc' => true, 'text' =>
            '<p>Lange galt: Eine Website hat drei Zustände – Handy, Tablet, Desktop. Doch Komponenten stehen heute überall: in schmalen Seitenleisten, in breiten Rastern, in Karten auf Karten. Die Bildschirmbreite sagt über ihren Platz wenig aus.</p>'
            . '<h3>Das Problem mit Breakpoints</h3><p>Ein Breakpoint fragt den Bildschirm. Eine Karte in einer Seitenleiste erfährt so nicht, dass sie schmal ist – sie sieht dieselbe Bildschirmbreite wie die Karte im Hauptbereich.</p><p>Die Folge sind Sonderregeln: „Karte in Seitenleiste“, „Karte im Footer“, „Karte im Modal“.</p>'
            . '<h3>Intrinsische Layouts</h3><p>Raster mit „auto-fit“ und „minmax()“ füllen sich selbst. Flexbox bricht um, wenn der Platz fehlt. Container-Queries fragen den Platz der Komponente selbst.</p><h4>Die wichtigsten Muster</h4><ul><li>Stapel (stack) und Reihe (cluster)</li><li>Seitenleiste (sidebar) und Umschalter (switcher)</li><li>Raster (grid) und Band (reel)</li></ul>'
            . '<h3>Fließende Typografie</h3><p>Statt Schriftgrößen je Breakpoint gibt es zwei Grundgrößen und ein Verhältnis. „clamp()“ rechnet den Rest – stufenlos, ohne Sprünge.</p><p><strong>Gute Gestaltung ist die, die man nicht bemerkt.</strong></p>'
            . '<h3>Was bleibt</h3><p>Media-Queries gibt es weiterhin – für Vorlieben der Menschen: dunkles Farbschema, reduzierte Bewegung, Druck. Nicht mehr für Breiten.</p>']),
        $B('richtext', ['variant' => 'columns', 'eyebrow' => 'Fließtext „Mehrspaltig“', 'title' => 'Zeitungssatz', 'intro' => '', 'meta' => '', 'dropcap' => false, 'toc' => false, 'text' =>
            '<p>Mehrspaltiger Text nutzt „column-width“: So viele Spalten, wie mit mindestens 22 rem Breite hineinpassen – auf dem Handy eine, auf breiten Bildschirmen drei.</p><p>Überschriften bleiben bei ihrem Absatz. Für lange Texte ist die einspaltige Variante meist besser lesbar; für kurze Meldungen, Chroniken oder Programmhefte passt der Zeitungssatz gut.</p>'
            . '<h4>Meldung</h4><p>Das Beispielfest findet in diesem Jahr auf dem Beispielplatz statt. Alle Angaben sind frei erfunden.</p><h4>Chronik</h4><p>Gegründet in einem fiktiven Jahr, gewachsen mit fiktiven Menschen, bekannt für fiktive Ideen.</p><p>Weitere Absätze zeigen, wie die Spalten sich füllen – gleichmäßig und ohne Umbruch mitten in Überschriften.</p>'], ['background' => 'muted']),
        $cta,
    ]);
    $log('Seitenbaum „Showcase“ angelegt (/showcase).');
}

// ------------------------------------------------------------------ Musterseite „Hero-Varianten“

/**
 * Unterseite /showcase/hero-varianten: die neuen Einstiege (Fließende Skala, Collage, Vorher/Nachher) untereinander,
 * dazu zwei vorhandene zum Vergleich. Bilder: die erzeugten Showcase-Grafiken + ein erzeugtes Vorher/Nachher-Paar.
 * Neu anlegen (bestehende Websites): CMS_SITE=fluid php kits/fluid/tools/demo.php --heroes
 */
function fluid_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $root = $db->fetch("SELECT id FROM pages WHERE path = 'showcase' AND type = 'page' LIMIT 1");
    if (!$root) { $log('Kein Showcase vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    foreach ($db->fetchAll("SELECT id FROM pages WHERE path = 'showcase/hero-varianten'") as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();

    $media = $db->fetchAll('SELECT id, title, width, height, mime FROM media WHERE tags LIKE ? ORDER BY id', ['%' . FLUID_DEMO_TAG . '%']);
    // Vorher/Nachher-Paar (erneut erzeugt, ältere Fassungen entfernen)
    foreach ($media as $m) if (str_starts_with((string) $m['title'], 'Vergleich ')) Media::delete((int) $m['id']);
    [$before, $after] = fluid_demo_compare_pair($log);
    $pics = array_values(array_filter($media, fn($m) => str_starts_with((string) $m['mime'], 'image/jpeg') && !str_starts_with((string) $m['title'], 'Vergleich ')));
    $wide = array_values(array_map(fn($m) => (int) $m['id'], array_filter($pics, fn($m) => (int) $m['width'] > (int) $m['height'])));
    $tall = array_values(array_map(fn($m) => (int) $m['id'], array_filter($pics, fn($m) => (int) $m['width'] < (int) $m['height'])));
    $W = fn(int $n) => $wide ? $wide[$n % count($wide)] : null;
    $T = fn(int $n) => $tall ? $tall[$n % count($tall)] : $W($n);

    $none = ['button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'points' => '', 'image' => null, 'ratio' => '4:5', 'video' => null, 'overlay' => 'strong', 'cards' => []];
    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + $none + ['eyebrow' => '', 'text' => ''], 'tunes' => ['section' => $tunes + ['divider' => true]]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Showcase · Hero-Varianten', 'title' => 'Neun Einstiege, ein Block.',
            'text' => 'Der Block „Einstieg (Hero)“ hat neun Varianten. Die drei neuen stehen hier untereinander – Fließende Skala, Collage und Vorher/Nachher –, darunter zwei bekannte zum Vergleich. Auf echten Seiten steht nur ein Einstieg ganz oben.'],
            ['background' => 'muted', 'divider' => false]),
        $B('scale', ['eyebrow' => 'Variante „Fließende Skala“', 'title' => 'Schrift, die ihren *Platz* misst.',
            'text' => 'Die Überschrift richtet sich nach ihrer Spalte, nicht nach dem Bildschirm. Ist genug Platz, rückt der Text in eine Seitenspalte; in schmalen Spalten steht alles untereinander.',
            'button_label' => 'Alle Blöcke ansehen', 'button_link' => '/showcase/inhalte', 'button2_label' => 'Wie das geht', 'button2_link' => '/showcase/magazin',
            'points' => "Ohne Breakpoints\nIn jeder Spaltenbreite",
            'scale_items' => "0: Breiten-Media-Queries im Kit\n7: fließende Schriftstufen\n9: Varianten dieses Einstiegs\nStufenlos"], ['divider' => false]),
        $B('collage', ['eyebrow' => 'Variante „Collage“', 'title' => 'Eine Bildwelt statt *eines* Fotos.',
            'text' => 'Bis zu fünf Bilder in einem asymmetrischen Raster, die Textkarte schwebt davor. Bei wenig Platz werden daraus Kacheln – die Karte schiebt sich über das erste Bild.',
            'button_label' => 'Zur Galerie', 'button_link' => '/showcase/medien', 'button2_label' => '', 'button2_link' => '',
            'points' => "Bis zu fünf Bilder\nAlt-Texte aus der Mediathek",
            'image' => $W(0), 'gallery' => [
                ['image' => $T(1), 'caption' => 'Beispielbild'], ['image' => $W(1), 'caption' => ''],
                ['image' => $W(6), 'caption' => ''], ['image' => $W(3), 'caption' => 'Frei erfunden'],
            ]], ['background' => 'muted', 'divider' => false]),
        $B('compare', ['eyebrow' => 'Variante „Vorher/Nachher“', 'title' => 'Der Unterschied liegt im *Ergebnis*.',
            'text' => 'Für Handwerk, Renovierung, Garten oder Bildbearbeitung: Regler ziehen – oder mit den Pfeiltasten bewegen. Ohne JavaScript stehen beide Bilder nebeneinander.',
            'button_label' => 'Angebot anfragen', 'button_link' => '/showcase/formulare', 'button2_label' => '', 'button2_link' => '',
            'points' => "Beispielbilder, automatisch erzeugt\nRegler mit Tastatur bedienbar",
            'ratio' => '3:2', 'image_before' => $before, 'image_after' => $after, 'before_label' => 'Vorher', 'after_label' => 'Nachher'], ['divider' => false]),
        $B('type', ['eyebrow' => 'Zum Vergleich: „Großer Schriftzug“', 'title' => 'Die Überschrift ist das *Bild*.',
            'text' => 'Die vorhandene typografische Variante: Schriftzug über die volle Breite, Text und Buttons darunter.',
            'button_label' => 'Zum Showcase', 'button_link' => '/showcase', 'ratio' => '16:9'], ['background' => 'muted', 'divider' => false]),
        $B('split', ['eyebrow' => 'Zum Vergleich: „Text und Bild“', 'title' => 'Der Klassiker für die Startseite.',
            'text' => 'Text und Bild stehen nebeneinander, solange beide Platz haben – sonst untereinander.',
            'button_label' => 'Zum Showcase', 'button_link' => '/showcase', 'image' => $T(2), 'ratio' => '4:5'], ['divider' => false]),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 7, 'status' => 'published', 'menu' => 1,
        'meta_description' => 'Die Einstiegs-Varianten des Kits „Fluid“: Fließende Skala, Collage, Vorher/Nachher – dazu großer Schriftzug und Text mit Bild.'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (/showcase/hero-varianten).');
    return true;
}

/**
 * Erzeugtes Vorher/Nachher-Paar (gleicher Ausschnitt, 1800 × 1200): „Nachher“ = Bögen in Terrakotta,
 * „Vorher“ = dieselbe Komposition grau, fleckig, mit Mauerwerksfugen. Keine Fotos, keine Personen.
 * @return array{0: ?int, 1: ?int}
 */
function fluid_demo_compare_pair(callable $log): array
{
    $ids = [null, null];
    $after = tempnam(sys_get_temp_dir(), 'fx') . '.jpg';
    fluid_demo_draw($after, 1800, 1200, [163, 56, 15], [251, 243, 232], [246, 212, 107], 'arches', 71);
    $before = tempnam(sys_get_temp_dir(), 'fx') . '.jpg';
    $im = imagecreatefromjpeg($after);
    if ($im) {
        imagefilter($im, IMG_FILTER_GRAYSCALE);
        imagefilter($im, IMG_FILTER_COLORIZE, 18, 12, 4);
        imagefilter($im, IMG_FILTER_CONTRAST, 28);
        imagefilter($im, IMG_FILTER_BRIGHTNESS, -24);
        imagealphablending($im, true);
        mt_srand(7310);
        $joint = imagecolorallocatealpha($im, 60, 56, 50, 70);
        for ($y = 0, $row = 0; $y < 1200; $y += 60, $row++) {   // Mauerwerk: Lagerfugen + versetzte Stoßfugen
            imagefilledrectangle($im, 0, $y, 1800, $y + 4, $joint);
            for ($x = ($row % 2) * 70; $x < 1800; $x += 140) imagefilledrectangle($im, $x, $y, $x + 4, $y + 60, $joint);
        }
        for ($k = 0; $k < 26; $k++) {   // Flecken
            $r = mt_rand(60, 260);
            imagefilledellipse($im, mt_rand(0, 1800), mt_rand(0, 1200), $r, (int) ($r * .7), imagecolorallocatealpha($im, mt_rand(70, 120), mt_rand(64, 100), 60, mt_rand(80, 112)));
        }
        imagesetthickness($im, 3);
        $crack = imagecolorallocatealpha($im, 35, 32, 30, 30);
        for ($c = 0; $c < 4; $c++) {   // Risse
            [$x, $y] = [mt_rand(100, 1700), mt_rand(0, 300)];
            for ($s = 0; $s < 22; $s++) { $nx = $x + mt_rand(-40, 40); $ny = $y + mt_rand(20, 50); imageline($im, $x, $y, $nx, $ny, $crack); [$x, $y] = [$nx, $ny]; }
        }
        imagejpeg($im, $before, 84);
        unset($im);
    }
    foreach ([[$before, 'showcase-vergleich-vorher.jpg', 'Vergleich (Beispiel): Wand vorher', 'Abstrakte Grafik: graue, fleckige Mauer mit Rissen – Zustand vorher (Beispielbild)'],
              [$after, 'showcase-vergleich-nachher.jpg', 'Vergleich (Beispiel): Wand nachher', 'Abstrakte Grafik: Bögen in Terrakotta und Sand – Zustand nachher (Beispielbild)']] as $k => [$file, $name, $title, $alt]) {
        if (!is_file($file) || !filesize($file)) continue;
        [$m, $err] = Media::import($file, $name, $alt, ['title' => $title, 'tags' => FLUID_DEMO_TAG, 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        $ids[$k] = $m ? (int) $m['id'] : null;
    }
    return $ids;
}
