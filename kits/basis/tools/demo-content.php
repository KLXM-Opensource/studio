<?php
/**
 * „Musterseiten“ des Kits basis: ein Seitenbaum, der jeden Block und jede Funktion mit frei erfundenen Inhalten zeigt –
 * inkl. Beispiel-Datentabelle (Projekte), Kalender (Veranstaltungen), zwei öffentlichen Formularen und erzeugten Bildern.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=demo php kits/basis/tools/demo.php [--force]
 * Alles ist als Demo gekennzeichnet (Seitenbaum „Musterseiten“, Tabellen demo_*, Medien-Schlagwort „musterseiten-demo“)
 * und lässt sich mit --remove wieder entfernen – auch der frühere Seitenbaum „Baukasten“ (/baukasten, Schlagwort
 * „baukasten-demo“, Sammlung „Baukasten (Demo)“) älterer Installationen.
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const BASIS_DEMO_TAG = 'musterseiten-demo';
/** Seitenbaum der Musterseiten (Pfad); BASIS_DEMO_OLD_*: frühere Namen, die --remove/--force ebenfalls entfernt */
const BASIS_DEMO_PATH = 'musterseiten';
const BASIS_DEMO_COLLECTION = 'Musterseiten (Demo)';
const BASIS_DEMO_OLD_PATH = 'baukasten';
const BASIS_DEMO_OLD_TAG = 'baukasten-demo';
const BASIS_DEMO_OLD_COLLECTION = 'Baukasten (Demo)';

/** Musterseiten anlegen. $force: vorhandene Musterseiten vorher entfernen. */
function basis_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path IN (?, ?) AND type = 'page' LIMIT 1", [BASIS_DEMO_PATH, BASIS_DEMO_OLD_PATH]);
    if ($existing && !$force) {
        $log('Die Musterseiten sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    if ($force) basis_demo_remove($log);

    // Fiktive Kontaktdaten nur ergänzen, wo nichts eingetragen ist (für Kopf-Infoleiste, Kontakt-Block, Karte)
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Musterstadt'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }

    $img = basis_demo_images($log);
    $pdf = basis_demo_pdf($log);
    $tables = basis_demo_tables($img, $log);
    basis_demo_pages($img, $pdf, $tables, $log);
    basis_demo_heroes($log);
    \Core\PageCache::clear();
    $log('Fertig: Seitenbaum „Musterseiten“ mit 6 Seiten, 4 Datentabellen und ' . count($img['all']) . ' Bildern.');
    return true;
}

/** Musterseiten, Demo-Tabellen und Demo-Medien entfernen (auch unter den früheren Namen „Baukasten“) */
function basis_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE path IN (?, ?) OR path LIKE ? OR path LIKE ? OR slug = '_vorlage-demo-projekte'",
        [BASIS_DEMO_PATH, BASIS_DEMO_OLD_PATH, BASIS_DEMO_PATH . '/%', BASIS_DEMO_OLD_PATH . '/%']), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) {
        if (str_starts_with($t['handle'], 'demo_')) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ? OR tags LIKE ?', ['%' . BASIS_DEMO_TAG . '%', '%' . BASIS_DEMO_OLD_TAG . '%']) as $m) Media::delete((int) $m['id']);
    $db->query('DELETE FROM media_collections WHERE name IN (?, ?)', [BASIS_DEMO_COLLECTION, BASIS_DEMO_OLD_COLLECTION]);
    $log('Vorhandene Musterseiten entfernt (' . count($ids) . ' Seiten).');
}

// ------------------------------------------------------------------ Bilder (abstrakt, mit GD erzeugt)

function basis_demo_images(callable $log): array
{
    $col = Media::createCollection(BASIS_DEMO_COLLECTION, 'Abstrakte Beispielbilder der Musterseiten des Kits – frei verwendbar, ohne Personen oder Marken.');
    $motifs = [
        ['Flächen und Kreise in Petrol und Sand', [15, 110, 104], [233, 223, 204], 'circles'],
        ['Diagonale Bänder in Blaugrau', [44, 62, 80], [180, 196, 210], 'bands'],
        ['Raster aus Quadraten in warmem Terrakotta', [166, 61, 21], [246, 238, 227], 'grid'],
        ['Weiche Wellen in Mint und Weiß', [4, 120, 87], [236, 253, 245], 'waves'],
        ['Bögen in Graphit und Hellgrau', [35, 39, 46], [226, 228, 232], 'arches'],
        ['Kreise in Bordeaux und Rosé', [142, 27, 58], [250, 236, 240], 'circles'],
        ['Streifen in Violett und Lavendel', [109, 40, 217], [237, 233, 254], 'bands'],
        ['Raster in Petrol und Hellgrau', [10, 87, 82], [236, 240, 240], 'grid'],
    ];
    $all = [];
    foreach ($motifs as $i => [$title, $dark, $light, $kind]) {
        $file = tempnam(sys_get_temp_dir(), 'bk') . '.jpg';
        basis_demo_draw($file, $dark, $light, $kind, $i);
        [$m, $err] = Media::import($file, sprintf('musterseiten-%02d.jpg', $i + 1), 'Abstrakte Grafik: ' . $title . ' (Beispielbild)',
            ['title' => $title, 'tags' => BASIS_DEMO_TAG, 'collection' => $col, 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($file);
        if ($m) $all[] = (int) $m['id'];
        elseif ($err) $log('Bild ' . ($i + 1) . ': ' . $err);
    }
    $log(count($all) . ' Beispielbilder erzeugt.');
    return ['all' => $all, 'collection' => $col];
}

/** Abstrakte Komposition 1600 × 1067 (3:2) */
function basis_demo_draw(string $file, array $dark, array $light, string $kind, int $seed): void
{
    $w = 1600; $h = 1067;
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    // Verlauf hell → etwas dunkler
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h * .35;
        $c = array_map(fn($a, $b) => (int) round($a * (1 - $t) + $b * $t), $light, $dark);
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, ...$c));
    }
    $col = fn(array $rgb, int $alpha) => imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    $mid = array_map(fn($a, $b) => (int) round(($a + $b) / 2), $light, $dark);
    mt_srand(4200 + $seed);
    switch ($kind) {
        case 'circles':
            imagefilledellipse($im, 1080, 480, 900, 900, $col($dark, 10));
            imagefilledellipse($im, 520, 760, 620, 620, $col($mid, 40));
            imagefilledellipse($im, 1320, 860, 360, 360, $col($light, 30));
            break;
        case 'bands':
            for ($k = -3; $k < 9; $k++) {
                $x = $k * 240;
                imagefilledpolygon($im, [$x, $h, $x + 140, $h, $x + 140 + 700, 0, $x + 700, 0], $col($k % 2 ? $dark : $mid, $k % 3 ? 30 : 8));
            }
            break;
        case 'grid':
            for ($gx = 0; $gx < 8; $gx++) {
                for ($gy = 0; $gy < 6; $gy++) {
                    if (mt_rand(0, 3) === 0) continue;
                    $x = 120 + $gx * 175; $y = 90 + $gy * 160;
                    imagefilledrectangle($im, $x, $y, $x + 140, $y + 125, $col(mt_rand(0, 2) ? $dark : $mid, mt_rand(0, 2) ? 12 : 70));
                }
            }
            break;
        case 'waves':
            for ($k = 0; $k < 7; $k++) {
                $poly = [];
                for ($x = 0; $x <= $w; $x += 40) { $poly[] = $x; $poly[] = (int) (300 + $k * 110 + sin($x / 230 + $k) * 70); }
                $poly[] = $w; $poly[] = $h; $poly[] = 0; $poly[] = $h;
                imagefilledpolygon($im, $poly, $col($k % 2 ? $dark : $mid, 96 - $k * 8));
            }
            break;
        case 'arches':
            for ($k = 5; $k >= 0; $k--) {
                $r = 360 + $k * 250;
                imagefilledellipse($im, 800, 1067, $r, $r, $col($k % 2 ? $dark : $light, 12 + $k * 10));
            }
            break;
    }
    imagejpeg($im, $file, 84);
    unset($im);
}

/** Kleine Beispiel-PDF (eine Seite) für den Download-Block */
function basis_demo_pdf(callable $log): ?int
{
    $text = 'Musterseiten - Beispiel-PDF (Demo). Frei erfundener Inhalt.';
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
    $file = tempnam(sys_get_temp_dir(), 'bk') . '.pdf';
    file_put_contents($file, $pdf);
    [$m, $err] = Media::import($file, 'musterseiten-leistungsuebersicht.pdf', '', ['title' => 'Leistungsübersicht (Beispiel)', 'tags' => BASIS_DEMO_TAG]);
    @unlink($file);
    if ($err) $log('PDF: ' . $err);
    return $m ? (int) $m['id'] : null;
}

// ------------------------------------------------------------------ Datentabellen

function basis_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

function basis_demo_tables(array $img, callable $log): array
{
    $out = [];
    // Projekte: Karten, Liste, Tabelle, Detailseite
    $t = basis_demo_table([
        'handle' => 'demo_projekte', 'name' => 'Projekte (Demo)', 'singular' => 'Projekt', 'icon' => '▦',
        'description' => 'Beispiel-Tabelle der Musterseiten – frei erfundene Projekte.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'kategorie', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "beratung=Beratung\nplanung=Planung\numsetzung=Umsetzung"],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'auftraggeber', 'label' => 'Auftraggeber', 'type' => 'text', 'in_list' => true],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'muster-projekte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'jahr', 'sort_dir' => 'desc', 'workflow' => 0],
    ], $log);
    if ($t) {
        $rows = [
            ['Neues Leitsystem für ein Bürgerhaus', 'planung', 2026, 'Verein Beispielstadt (fiktiv)', 'Orientierung auf drei Etagen: klare Wege, gut lesbare Schilder, barrierefreie Beschriftung.'],
            ['Digitale Terminbuchung', 'umsetzung', 2026, 'Muster & Partner (fiktiv)', 'Vom Papierkalender zur Online-Buchung – mit Schulung des Teams und sauberer Übergabe.'],
            ['Strategie-Workshop Nachfolge', 'beratung', 2025, 'Werkstatt Süd (fiktiv)', 'Zwei Tage, ein Plan: wie der Betrieb in fünf Jahren aussehen soll und wer was übernimmt.'],
            ['Umbau Empfangsbereich', 'umsetzung', 2025, 'Studio Nord (fiktiv)', 'Mehr Licht, kürzere Wege, ein Tresen auf zwei Höhen – umgesetzt bei laufendem Betrieb.'],
            ['Förderantrag Energiesanierung', 'beratung', 2024, 'Stiftung Muster (fiktiv)', 'Bestandsaufnahme, Maßnahmenliste und vollständiger Antrag in sechs Wochen.'],
            ['Neuer Internetauftritt', 'planung', 2024, 'Beispiel AG (fiktiv)', 'Inhalte neu geordnet, Texte verständlich gemacht, barrierefrei umgesetzt.'],
        ];
        foreach ($rows as $i => [$title, $cat, $year, $client, $text]) {
            Entries::save($t, null, ['titel' => $title, 'kategorie' => $cat, 'jahr' => (string) $year, 'auftraggeber' => $client, 'kurztext' => $text,
                'bild' => $img['all'][$i % count($img['all'])] ?? null, 'status' => 'published']);
        }
        $out['projekte'] = $t;
    }

    // Veranstaltungen: Kalender mit Wiederholung, ganztägigem Termin und Kategorien
    $t = basis_demo_table([
        'handle' => 'demo_termine', 'name' => 'Veranstaltungen (Demo)', 'singular' => 'Veranstaltung', 'icon' => '▦',
        'description' => 'Beispiel-Kalender der Musterseiten – Termine relativ zum Tag der Anlage.',
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
        $until = gmdate('Ymd\THis\Z', strtotime('+100 days'));
        $events = [
            ['Kurzberatung am Telefon', $d(0, '10:00'), $d(0, '11:30'), false, '', 'Telefon', 'Zehn Minuten für Ihre Frage – ohne Anmeldung.', 'sprechstunde'],
            ['Offene Sprechstunde', $d($thu, '16:00'), $d($thu, '18:00'), false, "FREQ=WEEKLY;BYDAY=TH;UNTIL=$until", 'Beispielraum 1', 'Jeden Donnerstag: Fragen mitbringen, Antworten mitnehmen.', 'sprechstunde'],
            ['Workshop: Projekte gut planen', $d(5, '09:30'), $d(5, '15:00'), false, '', 'Seminarraum (fiktiv)', 'Ein Tag mit Methoden, Beispielen und viel Zeit für Ihre Fragen.', 'workshop'],
            ['Vortrag: Förderprogramme im Überblick', $d(12, '18:30'), $d(12, '20:00'), false, '', 'Online', 'Welche Programme es gibt und wie ein Antrag gelingt.', 'vortrag'],
            ['Betriebsausflug – geschlossen', $d(19, '00:00'), '', true, '', '', 'An diesem Tag sind wir nicht erreichbar.', 'geschlossen'],
            ['Workshop: Texte fürs Web', $d(26, '10:00'), $d(26, '13:00'), false, '', 'Seminarraum (fiktiv)', 'Verständlich schreiben für Website und Newsletter.', 'workshop'],
        ];
        foreach ($events as [$title, $start, $end, $allDay, $rrule, $loc, $text, $cat]) {
            [, $err] = Entries::save($t, null, ['titel' => $title, 'beginn' => $start, 'ende' => $end, 'ganztaegig' => $allDay, 'wiederholung' => $rrule,
                'ort' => $loc, 'beschreibung' => $text, 'kategorie' => $cat, 'status' => 'published']);
            if ($err) $log('Termin „' . $title . '“: ' . implode(' ', $err));
        }
        $out['termine'] = $t;
    }

    // Formular 1: Rückrufwunsch (einfach)
    $t = basis_demo_table([
        'handle' => 'demo_rueckruf', 'name' => 'Rückrufwünsche (Demo)', 'singular' => 'Rückrufwunsch', 'icon' => '☏',
        'description' => 'Öffentliches Beispiel-Formular der Musterseiten.',
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
    $t = basis_demo_table([
        'handle' => 'demo_antrag', 'name' => 'Mitgliedsanträge (Demo)', 'singular' => 'Mitgliedsantrag', 'icon' => '✎',
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

    $log(count($out) . ' Datentabellen angelegt (demo_*).');
    return $out;
}

// ------------------------------------------------------------------ Seiten

function basis_demo_pages(array $img, ?int $pdf, array $tables, callable $log): void
{
    $I = fn(int $n) => $img['all'][$n % max(1, count($img['all']))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text], ['background' => 'muted']);
    $cta = $B('cta', ['variant' => 'band', 'title' => 'Genug gesehen? Dann legen Sie los.', 'text' => 'Alle Bausteine lassen sich frei kombinieren – Farben, Schriften und Navigation stellen Sie unter Verwaltung → Design ein.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/musterseiten'], ['background' => 'accent']);
    $proj = $tables['projekte']['handle'] ?? '';
    $term = $tables['termine']['handle'] ?? '';

    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $page = function (array $f, array $blocks) {
        return Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    };

    // ---------------------------------------------------------------- Übersicht (Musterseite für den Style-Editor)
    $root = $page(['slug' => BASIS_DEMO_PATH, 'title' => 'Musterseiten', 'sort' => $sort, 'og_image' => $I(0),
        'meta_description' => 'Alle Blöcke und Funktionen des Kits „Basis“ auf einen Blick – mit frei erfundenen Beispielinhalten.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Musterseiten (Demo)', 'title' => 'Alle Bausteine auf einen Blick.',
            'text' => 'Diese Seiten zeigen jeden Block des Kits mit Beispielinhalten. Unter Verwaltung → Design ändern Sie Farben, Schriften, Formen und Navigation – hier sehen Sie sofort, wie alles zusammenspielt.',
            'button_label' => 'Inhalte ansehen', 'button_link' => '/musterseiten/inhalte', 'button2_label' => 'Medien ansehen', 'button2_link' => '/musterseiten/medien',
            'image' => $I(0), 'ratio' => '4:3']),
        $B('features', ['eyebrow' => 'Überblick', 'title' => 'Fünf Bereiche, alle Blöcke', 'intro' => 'Jede Unterseite widmet sich einem Thema.', 'columns' => '3', 'style' => 'cards', 'items' => [
            ['icon' => 'layers', 'title' => 'Einstieg & Abschnitte', 'text' => 'Einstiege, Hintergründe, Vollbild mit Bild, Text und Bild.', 'link_label' => 'Ansehen', 'link' => '/musterseiten/abschnitte'],
            ['icon' => 'spark', 'title' => 'Inhalte', 'text' => 'Merkmale, Zitate, Ablauf, Reiter, Pakete, Logos, FAQ.', 'link_label' => 'Ansehen', 'link' => '/musterseiten/inhalte'],
            ['icon' => 'star', 'title' => 'Medien', 'text' => 'Galerie mit Lightbox, Slider, Stapelkarten, Video, Downloads, Karte.', 'link_label' => 'Ansehen', 'link' => '/musterseiten/medien'],
            ['icon' => 'calendar', 'title' => 'Daten & Termine', 'text' => 'Datenlisten in vier Darstellungen, Kalender und nächste Termine.', 'link_label' => 'Ansehen', 'link' => '/musterseiten/daten'],
            ['icon' => 'mail', 'title' => 'Formulare & Kontakt', 'text' => 'Öffentliche Formulare mit Bedingungen, IBAN und Gruppen.', 'link_label' => 'Ansehen', 'link' => '/musterseiten/formulare'],
            ['icon' => 'bulb', 'title' => 'Design', 'text' => 'Sieben Voreinstellungen, vier Navigationen, hell und dunkel.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('stats', ['eyebrow' => 'In Zahlen', 'title' => 'Beispielwerte', 'intro' => 'Frei erfundene Zahlen – nur zur Ansicht.', 'items' => [
            ['value' => '20+', 'label' => 'Blöcke', 'text' => 'vom Einstieg bis zum Formular'],
            ['value' => '7', 'label' => 'Voreinstellungen', 'text' => 'jeweils hell und dunkel geprüft'],
            ['value' => '4', 'label' => 'Navigationen', 'text' => 'mit eigenem Mobilmenü'],
            ['value' => '0', 'label' => 'externe Anfragen', 'text' => 'Schriften und Karten vom eigenen Server'],
        ]], ['background' => 'muted']),
        $B('pricing', ['eyebrow' => 'Leistungspakete', 'title' => 'Beispielpakete', 'intro' => 'So lassen sich Angebote vergleichen – Preise frei erfunden.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'items' => [
            ['name' => 'Start', 'badge' => '', 'price' => '490 €', 'period' => 'einmalig', 'text' => 'Für den ersten Überblick.', 'features' => "Erstgespräch (60 Minuten)\nKurze schriftliche Einschätzung\nE-Mail-Rückfragen eine Woche", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Begleitung', 'badge' => 'Beliebt', 'price' => '1.290 €', 'period' => 'pro Monat', 'text' => 'Für laufende Vorhaben.', 'features' => "Alles aus „Start“\nWöchentliche Abstimmung\nFeste Ansprechperson\nProtokolle und Aufgabenliste", 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Komplett', 'badge' => '', 'price' => 'auf Anfrage', 'period' => '', 'text' => 'Für große Projekte.', 'features' => "Alles aus „Begleitung“\nUmsetzung durch unser Team\nÜbergabe und Schulung", 'highlight' => false, 'button_label' => 'Gespräch vereinbaren', 'button_link' => '/kontakt'],
        ]]),
        $B('quote', ['items' => [['text' => 'Beispielzitat: Die Musterseiten haben uns gezeigt, wie viel mit wenigen, gut abgestimmten Bausteinen möglich ist.', 'name' => 'Alex Beispiel', 'role' => 'Beispiel GmbH (fiktiv)']]], ['divider' => true]),
        $B('steps', ['variant' => 'numbers', 'eyebrow' => 'Ablauf', 'title' => 'In vier Schritten zur Website', 'items' => [
            ['meta' => '', 'title' => 'Voreinstellung wählen', 'text' => 'Eine der sieben Vorlagen als Ausgangspunkt nehmen.'],
            ['meta' => '', 'title' => 'Anpassen', 'text' => 'Akzentfarbe, Schriften und Navigation fein einstellen.'],
            ['meta' => '', 'title' => 'Inhalte pflegen', 'text' => 'Blöcke kombinieren und Texte direkt auf der Seite ändern.'],
            ['meta' => '', 'title' => 'Veröffentlichen', 'text' => 'Prüfen, veröffentlichen – fertig.'],
        ]], ['background' => 'muted']),
        $B('faq', ['eyebrow' => 'FAQ', 'title' => 'Fragen zu den Musterseiten', 'intro' => 'Kurz erklärt.', 'items' => [
            ['q' => 'Sind die Inhalte echt?', 'a' => '<p>Nein. Alle Namen, Zahlen, Termine und Zitate sind frei erfunden und dienen nur als Beispiel.</p>'],
            ['q' => 'Kann ich die Musterseiten löschen?', 'a' => '<p>Ja – einfach den Seitenbaum „Musterseiten“ und die Tabellen „… (Demo)“ löschen, oder <code>php kits/basis/tools/demo.php --remove</code> ausführen.</p>'],
            ['q' => 'Wo stelle ich Farben und Schriften ein?', 'a' => '<p>Unter Verwaltung → Design. Die Vorschau zeigt diese Seite als Muster.</p>'],
        ]]),
        $cta,
    ]);

    // ---------------------------------------------------------------- Einstieg & Abschnitte
    $page(['slug' => 'abschnitte', 'title' => 'Einstieg & Abschnitte', 'parent_id' => $root, 'sort' => 1,
        'meta_description' => 'Einstiege, Hintergründe (Standard, getönt, Akzent, dunkel), Vollbild-Abschnitte mit Hintergrundbild.'], [
        $B('hero', ['variant' => 'centered', 'eyebrow' => 'Einstieg „Zentriert“', 'title' => 'Abschnitte und Hintergründe',
            'text' => 'Dieser Einstieg füllt den Bildschirm: Hintergrundbild mit Abdunkelung. Bei der Navigation „Modern“ mit Transparenz liegt die Leiste darüber.',
            'button_label' => 'Zum Vollbild-Abschnitt', 'button_link' => '#vollbild', 'button2_label' => 'Zur Übersicht', 'button2_link' => '/musterseiten', 'image' => null, 'ratio' => '16:9'],
            ['background' => 'dark', 'height' => 'screen', 'bgImage' => $I(1), 'overlay' => 'dark', 'align' => 'center']),
        $B('text_image', ['variant' => 'right', 'eyebrow' => 'Hintergrund „Standard“', 'title' => 'Text und Bild nebeneinander',
            'text' => '<p>Der Block „Text + Bild“ trägt Überschrift, formatierten Text, eine Liste mit Häkchen und einen Button. Das Bild steht rechts oder links.</p>',
            'list' => "Bildformat wählbar\nHäkchen-Liste ohne Aufwand\nButton optional", 'button_label' => 'Mehr erfahren', 'button_link' => '/musterseiten/inhalte', 'image' => $I(2), 'ratio' => '4:3']),
        $B('text_image', ['variant' => 'left', 'eyebrow' => 'Hintergrund „Getönt“', 'title' => 'Ruhige Fläche für Abwechslung',
            'text' => '<p>Getönte Abschnitte gliedern lange Seiten. Die Farbe kommt aus dem Design-Token „Getönte Fläche“.</p>', 'list' => '',
            'button_label' => '', 'button_link' => '', 'image' => $I(3), 'ratio' => '3:2'], ['background' => 'muted']),
        $B('features', ['eyebrow' => 'Hintergrund „Akzentfarbe“', 'title' => 'Alles bleibt lesbar', 'intro' => 'Schrift, Symbole und Buttons stellen sich automatisch auf den Hintergrund ein.', 'columns' => '3', 'style' => 'plain', 'items' => [
            ['icon' => 'check', 'title' => 'Kontrast geprüft', 'text' => 'Alle Voreinstellungen erfüllen WCAG 2.2 AA.', 'link_label' => '', 'link' => ''],
            ['icon' => 'shield', 'title' => 'Ohne Inline-Styles', 'text' => 'Farben kommen aus einer CSS-Datei mit Variablen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'bolt', 'title' => 'Schnell', 'text' => 'Nur die gewählte Schrift wird geladen.', 'link_label' => '', 'link' => ''],
        ]], ['background' => 'accent']),
        $B('stats', ['eyebrow' => 'Hintergrund „Dunkel“', 'title' => 'Kennzahlen auf dunkler Fläche', 'intro' => '', 'items' => [
            ['value' => '4', 'label' => 'Hintergründe', 'text' => 'plus Hintergrundbild'], ['value' => '2', 'label' => 'Abdunkelungen', 'text' => 'hell oder dunkel'],
            ['value' => '100 %', 'label' => 'Bildschirmhöhe', 'text' => 'für Vollbild-Abschnitte'],
        ]], ['background' => 'dark']),
        $B('cta', ['variant' => 'box', 'title' => 'Vollbild-Abschnitt mit Hintergrundbild', 'text' => 'Abschnitt-Optionen: Höhe „Bildschirmhöhe“, Hintergrundbild, Abdunkelung und Ausrichtung.',
            'button_label' => 'Zur Übersicht', 'button_link' => '/musterseiten', 'button2_label' => '', 'button2_link' => ''],
            ['background' => 'dark', 'anchor' => 'vollbild', 'height' => 'screen', 'bgImage' => $I(4), 'overlay' => 'dark', 'align' => 'center']),
        $B('text_image', ['variant' => 'right', 'eyebrow' => 'Bild mit Aufhellung', 'title' => 'Heller Schleier für ruhige Texte',
            'text' => '<p>Mit „Aufhellen“ liegt ein heller Schleier über dem Bild – dunkle Schrift bleibt gut lesbar.</p>', 'list' => '', 'button_label' => '', 'button_link' => '', 'ratio' => '4:3'],
            ['background' => 'white', 'bgImage' => $I(5), 'overlay' => 'light']),
        $B('richtext', ['eyebrow' => 'Fließtext', 'title' => 'Typografie im Fließtext', 'text' =>
            '<p>Der Block „Fließtext“ eignet sich für längere Texte wie Rechtstexte. Er kennt <strong>Hervorhebungen</strong>, <a href="/musterseiten">Links</a> und Zwischenüberschriften.</p>'
            . '<h2>Zwischenüberschrift</h2><p>Absätze sind auf eine angenehme Zeilenlänge begrenzt.</p>'
            . '<ul><li>Aufzählung mit Punkten</li><li>Zweiter Punkt</li></ul><ol><li>Nummerierte Liste</li><li>Zweiter Schritt</li></ol>'
            . '<blockquote><p>Ein eingerücktes Zitat im Fließtext.</p></blockquote>'
            . '<h3>Kleinere Überschrift</h3><p>Zum Schluss ein weiterer Absatz mit einem Beispiel-Link zur <a href="/musterseiten/inhalte">Inhaltsseite</a>.</p>']),
    ]);

    // ---------------------------------------------------------------- Inhalte
    $page(['slug' => 'inhalte', 'title' => 'Inhalte', 'parent_id' => $root, 'sort' => 2,
        'meta_description' => 'Merkmale, Zitate, Ablauf und Zeitleiste, Reiter, Leistungspakete, Logos und Fragen & Antworten.'], [
        $hero('Inhalte', 'Blöcke für Inhalte', 'Merkmale in drei Darstellungen, Stimmen, Ablauf, Reiter, Logos und Fragen – jeweils mit Beispieltexten.'),
        $B('features', ['eyebrow' => 'Merkmale „Raster mit Linien“', 'title' => 'Vier Spalten, klare Linien', 'intro' => '', 'columns' => '4', 'style' => 'grid', 'items' => [
            ['icon' => 'chat', 'title' => 'Beratung', 'text' => 'Zuhören, ordnen, Wege aufzeigen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'layers', 'title' => 'Planung', 'text' => 'Ziele, Schritte, Zuständigkeiten.', 'link_label' => '', 'link' => ''],
            ['icon' => 'tool', 'title' => 'Umsetzung', 'text' => 'Abgestimmt und termintreu.', 'link_label' => '', 'link' => ''],
            ['icon' => 'heart', 'title' => 'Betreuung', 'text' => 'Auch nach dem Abschluss.', 'link_label' => '', 'link' => ''],
        ]]),
        $B('features', ['eyebrow' => 'Merkmale „Karten“ mit Bild', 'title' => 'Karten mit Bild und Link', 'intro' => 'Der Kartenstil (Fläche, Rahmen, Schatten) folgt der Einstellung unter Design → Form.', 'columns' => '3', 'style' => 'cards', 'items' => [
            ['image' => $I(1), 'title' => 'Beispielprojekt Nord', 'text' => 'Kurze Beschreibung mit zwei Zeilen Text.', 'link_label' => 'Weiterlesen', 'link' => '/musterseiten/daten'],
            ['image' => $I(2), 'title' => 'Beispielprojekt Süd', 'text' => 'Die ganze Karte ist klickbar, wenn kein Linktext gesetzt ist.', 'link_label' => '', 'link' => '/musterseiten/daten'],
            ['image' => $I(3), 'title' => 'Beispielprojekt West', 'text' => 'Bilder erscheinen im Format 3:2.', 'link_label' => 'Weiterlesen', 'link' => '/musterseiten/daten'],
        ]], ['background' => 'muted']),
        $B('quote', ['eyebrow' => 'Stimmen', 'title' => 'Zitate als Karten', 'items' => [
            ['text' => 'Beispielzitat: Schnelle Rückmeldungen und ein klarer Plan – genau das hatten wir gesucht.', 'name' => 'Kim Muster', 'role' => 'Verein Beispielstadt (fiktiv)'],
            ['text' => 'Beispielzitat: Endlich verstehen alle im Team, wer was bis wann erledigt.', 'name' => 'Sam Beispiel', 'role' => 'Studio Nord (fiktiv)'],
            ['text' => 'Beispielzitat: Die Übergabe war so gut dokumentiert, dass wir allein weitermachen konnten.', 'name' => 'Jo Probe', 'role' => 'Werkstatt Süd (fiktiv)'],
        ]]),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Zeitleiste', 'title' => 'Ein Projekt im Zeitverlauf', 'intro' => 'Variante „Zeitleiste“ mit Phase oder Datum.', 'items' => [
            ['meta' => 'Woche 1', 'title' => 'Kennenlernen', 'text' => 'Ziele, Rahmen und Ansprechpartner klären.'],
            ['meta' => 'Woche 2–3', 'title' => 'Konzept', 'text' => 'Vorschlag mit Aufwand und Terminen.'],
            ['meta' => 'Woche 4–10', 'title' => 'Umsetzung', 'text' => 'Regelmäßige, kurze Abstimmungen.'],
            ['meta' => 'Woche 11', 'title' => 'Übergabe', 'text' => 'Dokumentation, Schulung, Abschluss.'],
        ]], ['background' => 'muted']),
        $B('tabs', ['variant' => 'top', 'eyebrow' => 'Reiter', 'title' => 'Inhalte zum Umschalten', 'intro' => 'Ohne JavaScript stehen alle Inhalte untereinander.', 'items' => [
            ['label' => 'Beratung', 'text' => '<p>Wir hören zu und ordnen Ihre Anforderungen. Sie erhalten eine ehrliche Einschätzung von Aufwand und Nutzen.</p><ul><li>Erstgespräch</li><li>Kurze Analyse</li></ul>', 'image' => $I(5)],
            ['label' => 'Planung', 'text' => '<p>Aus Zielen wird ein Plan mit Schritten, Zuständigkeiten und Terminen.</p>', 'image' => $I(6)],
            ['label' => 'Umsetzung', 'text' => '<p>Wir setzen um oder begleiten Ihr Team – mit kurzen, regelmäßigen Abstimmungen.</p>', 'image' => null],
        ]]),
        $B('tabs', ['variant' => 'side', 'eyebrow' => 'Reiter links', 'title' => 'Häufige Themen', 'intro' => '', 'items' => [
            ['label' => 'Zusammenarbeit', 'text' => '<p>Feste Ansprechperson, klare Absprachen, nachvollziehbare Angebote.</p>', 'image' => null],
            ['label' => 'Kosten', 'text' => '<p>Sie erhalten vorab ein schriftliches Angebot – ohne versteckte Kosten.</p>', 'image' => null],
            ['label' => 'Termine', 'text' => '<p>Realistische Zeitpläne und frühe Rückmeldung, falls sich etwas ändert.</p>', 'image' => null],
        ]], ['background' => 'muted']),
        $B('logos', ['eyebrow' => 'Logos', 'title' => 'Beispielhafte Auftraggeber', 'items' => [
            ['name' => 'Beispiel AG', 'link' => '', 'image' => null], ['name' => 'Muster & Partner', 'link' => '', 'image' => null], ['name' => 'Studio Nord', 'link' => '', 'image' => null],
            ['name' => 'Werkstatt Süd', 'link' => '', 'image' => null], ['name' => 'Stiftung Muster', 'link' => '', 'image' => null],
        ]]),
        $B('cta', ['variant' => 'box', 'title' => 'Handlungsaufruf als Box', 'text' => 'Die Variante „Box“ hebt sich auf jedem Hintergrund ab.', 'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email']),
    ]);

    // ---------------------------------------------------------------- Medien
    $page(['slug' => 'medien', 'title' => 'Medien', 'parent_id' => $root, 'sort' => 3, 'og_image' => $I(6),
        'meta_description' => 'Galerie mit Lightbox, Slider, Stapelkarten, Video mit Zwei-Klick-Lösung, Downloads und Karte.'], [
        $hero('Medien', 'Bilder, Video, Dateien, Karte', 'Alle Medien kommen vom eigenen Server – Videos von YouTube oder Vimeo erst nach einem Klick.'),
        $B('gallery', ['eyebrow' => 'Galerie „Raster“', 'title' => 'Bilder aus einer Sammlung', 'intro' => 'Ein Klick öffnet die Lightbox – mit Tastatur, Wischen und Bildunterschrift.',
            'source' => 'collection', 'collection' => $img['collection'], 'layout' => 'grid', 'columns' => '4', 'ratio' => '4:3', 'captions' => true, 'lightbox' => true]),
        $B('gallery', ['eyebrow' => 'Galerie „Zeilen“', 'title' => 'Bündige Zeilen', 'intro' => '', 'source' => 'manual',
            'images' => array_map(fn($id) => ['image' => $id, 'caption' => ''], array_slice($img['all'], 0, 6)), 'layout' => 'justified', 'columns' => '3', 'ratio' => '4:3', 'captions' => false, 'lightbox' => true], ['background' => 'muted']),
        $B('slideshow', ['eyebrow' => 'Slider', 'title' => 'Folien mit Text', 'intro' => '', 'source' => 'manual', 'height' => '21:9', 'transition' => 'slide', 'arrows' => true, 'dots' => true, 'loop' => true, 'autoplay' => false, 'interval' => '6', 'slides' => [
            ['image' => $I(0), 'eyebrow' => 'Folie 1', 'title' => 'Abgedunkelt mit heller Schrift', 'text' => 'Die Standard-Einstellung für Folien mit Text.', 'button_label' => 'Mehr', 'button_link' => '/musterseiten', 'position' => 'bottom-left', 'overlay' => 'dark'],
            ['image' => $I(3), 'eyebrow' => 'Folie 2', 'title' => 'Textkasten', 'text' => 'Ein Kasten in der Kartenfarbe.', 'button_label' => '', 'button_link' => '', 'position' => 'center-left', 'overlay' => 'box'],
            ['image' => $I(5), 'eyebrow' => 'Folie 3', 'title' => 'Aufgehellt', 'text' => 'Dunkle Schrift auf hellem Schleier.', 'button_label' => '', 'button_link' => '', 'position' => 'center', 'overlay' => 'light'],
        ]]),
        $B('stack_cards', ['eyebrow' => 'Stapelkarten', 'title' => 'Karten, die sich stapeln', 'intro' => 'Beim Scrollen schieben sich die Karten übereinander – bei „Bewegung reduzieren“ als Liste.', 'ratio' => '4:3', 'image_side' => 'alternate', 'cards' => [
            ['eyebrow' => 'Schritt 1', 'title' => 'Verstehen', 'text' => 'Wir klären Ziele und Rahmenbedingungen.', 'image' => $I(1), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 2', 'title' => 'Planen', 'text' => 'Ein belastbarer Plan mit Meilensteinen.', 'image' => $I(2), 'link_label' => '', 'link' => ''],
            ['eyebrow' => 'Schritt 3', 'title' => 'Umsetzen', 'text' => 'Abgestimmt, termintreu, dokumentiert.', 'image' => $I(7), 'link_label' => 'Kontakt', 'link' => '/kontakt'],
        ]], ['background' => 'muted']),
        $B('video', ['variant' => 'text', 'eyebrow' => 'Video', 'title' => 'Video mit Zwei-Klick-Lösung', 'intro' => '',
            'text' => '<p>Das Vorschaubild liegt auf dem eigenen Server. Erst nach dem Klick lädt der Anbieter den Player – vorher werden keine Daten übertragen.</p><p>Beispiel: „Big Buck Bunny“ © 2008 Blender Foundation / <a href="https://www.bigbuckbunny.org">www.bigbuckbunny.org</a>, Lizenz <a href="https://creativecommons.org/licenses/by/3.0/">CC BY 3.0</a>.</p>',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $I(4), 'ratio' => '16-9', 'caption' => 'Big Buck Bunny – © 2008 Blender Foundation / www.bigbuckbunny.org (CC BY 3.0)']),
        $B('downloads', ['eyebrow' => 'Downloads', 'title' => 'Dateien zum Herunterladen', 'intro' => 'PDFs lassen sich zusätzlich im Browser ansehen.', 'source' => 'manual', 'show_viewer' => true,
            'files' => $pdf ? [['label' => 'Leistungsübersicht (Beispiel)', 'file' => $pdf, 'note' => 'Eine Seite, frei erfundener Inhalt']] : []], ['background' => 'muted']),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort', 'location' => 'site', 'zoom' => '15', 'height' => 'm', 'route' => true]),
    ]);

    // ---------------------------------------------------------------- Daten & Termine
    $tpl = null;
    if ($proj !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-demo-projekte', 'title' => 'Projekte (Demo) – Detailseite', 'type' => 'template', 'template_for' => $proj, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.kategorie", "$proj.bild"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '21:9', 'back_label' => 'Alle Projekte', 'back_link' => '/musterseiten/daten'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none', 'spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.auftraggeber", "$proj.jahr", "$proj.kategorie"], 'layout' => 'dl', 'show_labels' => true, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', ['eyebrow' => '', 'title' => 'Weitere Projekte', 'intro' => '', 'table' => $proj, 'fields' => ["$proj._title", "$proj.bild", "$proj.kategorie"], 'layout' => 'cards', 'columns' => '3', 'ratio' => '16:10', 'limit' => 3, 'exclude_current' => true, 'link_detail' => true], ['background' => 'muted']),
        ]));
        $t = Tables::find($proj);
        $t['settings']['detail_page_id'] = $tpl;
        app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
        Tables::flush();
    }
    $dlBase = fn(string $layout) => ['eyebrow' => '', 'intro' => '', 'table' => $proj, 'layout' => $layout, 'columns' => '3', 'ratio' => '16:10', 'limit' => 0,
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $dl = fn(string $layout, array $extra = []) => $extra + $dlBase($layout);
    $page(['slug' => 'daten', 'title' => 'Daten & Termine', 'parent_id' => $root, 'sort' => 4,
        'meta_description' => 'Datenlisten als Karten, Liste, kompakt und Tabelle; Kalender als Monat und Terminliste; nächste Termine.'], [
        $hero('Daten & Termine', 'Datentabellen und Kalender', 'Einträge werden einmal in einer Tabelle gepflegt und hier in verschiedenen Darstellungen ausgegeben.'),
        $B('data_list', $dl('cards', ['eyebrow' => 'Datenliste „Karten“', 'title' => 'Projekte', 'fields' => ["$proj._title", "$proj.bild", "$proj.kategorie", "$proj.kurztext"], 'limit' => 3, 'more_label' => 'Alle als Tabelle', 'more_link' => '#tabelle'])),
        $B('data_list', $dl('list', ['eyebrow' => 'Datenliste „Liste mit Bild“', 'title' => 'Projekte als Liste', 'fields' => ["$proj._title", "$proj.bild", "$proj.auftraggeber", "$proj.kurztext"], 'limit' => 3])),
        $B('data_list', $dl('compact', ['eyebrow' => 'Datenliste „Kompakt“', 'title' => 'Projekte kompakt', 'fields' => ["$proj._title", "$proj.jahr", "$proj.kategorie"], 'limit' => 4])),
        $B('data_list', $dl('table', ['eyebrow' => 'Datenliste „Tabelle“', 'title' => 'Alle Projekte', 'fields' => ["$proj._title", "$proj.auftraggeber", "$proj.kategorie", "$proj.jahr"]]), ['anchor' => 'tabelle', 'background' => 'muted']),
        $B('calendar', ['eyebrow' => 'Kalender', 'title' => 'Monatsübersicht', 'intro' => 'Mit Wiederholungen, ganztägigen Terminen und Filter nach Kategorie.', 'table' => $term, 'view' => 'month',
            'filter_field' => '', 'filter_value' => '', 'visitor_filter' => true, 'subscribe' => true, 'link_detail' => false]),
        $B('upcoming', ['eyebrow' => 'Nächste Termine', 'title' => 'Demnächst', 'intro' => '', 'table' => $term, 'limit' => 4, 'days' => 0, 'layout' => 'list', 'show_location' => true, 'link_detail' => false, 'subscribe' => true, 'more_label' => 'Alle Termine', 'more_link' => '#termine'], ['background' => 'muted']),
        $B('calendar', ['eyebrow' => '', 'title' => 'Terminliste des Monats', 'intro' => '', 'table' => $term, 'view' => 'agenda', 'visitor_filter' => false, 'subscribe' => false, 'link_detail' => false], ['anchor' => 'termine']),
        $B('upcoming', ['eyebrow' => 'Kompakt auf dunkler Fläche', 'title' => 'Die nächsten drei', 'intro' => '', 'table' => $term, 'limit' => 3, 'days' => 0, 'layout' => 'compact', 'show_location' => true, 'link_detail' => false, 'subscribe' => false], ['background' => 'dark']),
    ]);

    // ---------------------------------------------------------------- Formulare & Kontakt
    $page(['slug' => 'formulare', 'title' => 'Formulare & Kontakt', 'parent_id' => $root, 'sort' => 5,
        'meta_description' => 'Öffentliche Formulare mit Fehler- und Erfolgsmeldung, Bedingungen, IBAN-Prüfung und Gruppen; Kontakt-Block.'], [
        $hero('Formulare & Kontakt', 'Formulare, die Besucher gern ausfüllen', 'Einträge landen in einer Datentabelle – mit Spamschutz ohne Cookies. Absenden ist gefahrlos: es handelt sich um Demo-Tabellen.'),
        $B('data_form', ['eyebrow' => 'Einfaches Formular', 'title' => 'Rückruf anfordern', 'intro' => 'Pflichtfelder sind markiert. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $tables['rueckruf']['handle'] ?? '', 'submit_label' => '', 'success_text' => '']),
        $B('data_form', ['eyebrow' => 'Mit Bedingungen, IBAN und Gruppe', 'title' => 'Mitgliedsantrag (Beispiel)', 'intro' => '„Firma oder Verein“ blendet ein weiteres Feld ein; bei „Privatperson“ lassen sich weitere Personen hinzufügen.', 'table' => $tables['antrag']['handle'] ?? '', 'submit_label' => '', 'success_text' => ''], ['background' => 'muted']),
        $B('contact', ['eyebrow' => 'Kontakt', 'title' => 'So erreichen Sie uns', 'intro' => 'Alle Angaben kommen aus „Website“.', 'show_hours' => true, 'show_map' => true, 'note' => 'Beispieladresse – frei erfunden.']),
    ]);
    $log('Seitenbaum „Musterseiten“ angelegt (/' . BASIS_DEMO_PATH . ').');
}

// ------------------------------------------------------------------ Hero-Varianten (Musterseite)

/**
 * Unterseite „Hero-Varianten“ der Musterseiten: jede Einstiegs-Variante mit Werkzeug untereinander (Suche, Formular, Standort)
 * plus die drei klassischen zum Vergleich. Nutzt die Demo-Bilder und -Tabellen der Musterseiten; ersetzt eine vorhandene Seite.
 * Einzeln (bestehende Websites): CMS_SITE=demo php kits/basis/tools/demo.php --heroes
 */
function basis_demo_heroes(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $root = $db->fetch("SELECT id, path FROM pages WHERE path IN (?, ?) AND type = 'page' ORDER BY path = ? DESC LIMIT 1", [BASIS_DEMO_PATH, BASIS_DEMO_OLD_PATH, BASIS_DEMO_PATH]);
    if (!$root) { $log('Keine Musterseiten vorhanden – zuerst tools/demo.php ausführen.'); return false; }
    $base = '/' . $root['path'];
    foreach ($db->fetchAll('SELECT id FROM pages WHERE path = ?', [$root['path'] . '/hero-varianten']) as $old) $db->query('DELETE FROM pages WHERE id = ?', [(int) $old['id']]);
    Pages::rebuildPaths();
    $img = array_map('intval', array_column($db->fetchAll('SELECT id FROM media WHERE tags LIKE ? OR tags LIKE ? ORDER BY id', ['%' . BASIS_DEMO_TAG . '%', '%' . BASIS_DEMO_OLD_TAG . '%']), 'id'));
    $img = array_values(array_filter($img, fn($id) => str_starts_with((string) (Media::find($id)['mime'] ?? ''), 'image/')));
    $I = fn(int $n) => $img ? $img[$n % count($img)] : null;
    $form = Tables::find('demo_rueckruf') ? 'demo_rueckruf' : '';
    $B = fn(string $variant, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => 'hero',
        'data' => ['variant' => $variant] + $data + ['eyebrow' => '', 'text' => ''], 'tunes' => ['section' => $tunes + ['divider' => true]]];
    $blocks = [
        $B('compact', ['eyebrow' => 'Musterseiten · Hero-Varianten', 'title' => 'Sechs Einstiege, ein Block.',
            'text' => 'Der Block „Einstieg (Hero)“ hat sechs Varianten. Die drei neuen bringen ein Werkzeug direkt nach oben: Suche, Formular und Standort. Jede Variante erscheint hier einmal – auf echten Seiten steht nur ein Einstieg ganz oben.'],
            ['background' => 'muted', 'divider' => false]),
        $B('search', ['eyebrow' => 'Variante „Such-Einstieg“', 'title' => 'Wie können wir Ihnen helfen?',
            'text' => 'Für Websites mit vielen Inhalten – Verwaltung, Verband, Verein. Vorschläge erscheinen schon beim Tippen.',
            'search_label' => 'Was suchen Sie?', 'search_placeholder' => 'z. B. Projekt, Termin, Formular …',
            'search_chips' => "Projekte\nTermine\nFormular\nKontakt | $base/formulare",
            'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '']),
        $B('form', ['eyebrow' => 'Variante „Mit Formular“', 'title' => 'Rückruf in zwei Minuten angefragt.',
            'text' => 'Wenn ein Ziel im Vordergrund steht: Das Formular ist sofort sichtbar, der Text daneben erklärt, was danach passiert.',
            'points' => "Rückruf am selben Werktag\nKeine Kosten, keine Verpflichtung\nDaten werden verschlüsselt übertragen",
            'button_label' => 'Leistungen ansehen', 'button_link' => $base . '/inhalte', 'button2_label' => '', 'button2_link' => '',
            'form_table' => $form, 'form_title' => 'Rückruf anfordern', 'form_submit' => '', 'form_note' => 'Demo-Formular – es ruft niemand an.'],
            ['background' => 'muted']),
        $B('map', ['eyebrow' => 'Variante „Standort“', 'title' => 'Besuchen Sie uns vor Ort.',
            'text' => 'Anschrift, Telefon, „jetzt geöffnet“ und die Öffnungszeiten kommen aus „Website“ – die Karte lädt über den eigenen Server, ohne Cookies.',
            'button_label' => 'Route planen', 'button_link' => $base . '/formulare', 'button2_label' => '', 'button2_link' => '',
            'map_zoom' => '15', 'map_hours' => true, 'map_note' => 'Beispieladresse – frei erfunden. Parkplätze im Hof.']),
        $B('split', ['eyebrow' => 'Variante „Text und Bild“', 'title' => 'Der Klassiker für die Startseite.',
            'text' => 'Text links, Bild rechts – das Bildformat ist wählbar.', 'image' => $I(1), 'ratio' => '4:3',
            'button_label' => 'Zu den Musterseiten', 'button_link' => $base, 'button2_label' => '', 'button2_link' => '']),
        $B('centered', ['eyebrow' => 'Variante „Zentriert“', 'title' => 'Eine Botschaft, ein breites Bild.',
            'text' => 'Kurz und zentriert, das Bild steht im Breitbild darunter.', 'image' => $I(3), 'ratio' => '16:9',
            'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => ''], ['background' => 'muted']),
    ];
    Pages::create(['slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'parent_id' => (int) $root['id'], 'sort' => 6, 'status' => 'published', 'menu' => 1,
        'meta_description' => 'Alle Varianten des Einstiegs (Hero) im Kit „Basis“: Such-Einstieg, mit Formular, Standort, Text und Bild, zentriert, Seitenkopf.'],
        Pages::sanitizeBlocks($blocks));
    \Core\PageCache::clear();
    $log('Musterseite „Hero-Varianten“ angelegt (' . $base . '/hero-varianten).');
    return true;
}
