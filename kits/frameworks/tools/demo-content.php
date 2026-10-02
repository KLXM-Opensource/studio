<?php
// SPDX-License-Identifier: MIT
/**
 * Demo-Inhalte des Kits „frameworks“ für die fiktive Firma „Beispielwerk“ (keine echten Marken, Namen oder Adressen):
 * Seiten mit allen Blöcken, ein Seitenbaum (Aufklappmenü), Datentabelle „Projekte“ mit Detailseite, Formular „Anfragen“,
 * Glossar mit Begriffen, die in den Texten vorkommen, abstrakte Bilder (mit GD erzeugt, ohne Personen oder Marken).
 *
 * Neue Websites: seed.php → 'after'. Bestehende Website neu befüllen (ersetzt NUR die Demo-Inhalte dieses Kits):
 *   CMS_SITE=<website> php kits/frameworks/tools/demo.php [--force]
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

/** Schlagwort der Demo-Bilder (zum Entfernen) */
function frameworks_demo_tag(): string
{
    return 'frameworks-demo';
}

/** Alles anlegen. $force: vorhandene Demo-Inhalte vorher entfernen. */
function frameworks_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    if (Tables::findContent('projekte') && !$force) {
        $log('Demo-Inhalte sind schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    frameworks_demo_remove($log);
    // Fiktive Kontaktdaten; Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese – keine Adresse von Personen/Firmen)
    foreach (['phone' => '0123 456789-0', 'street' => 'Musterstraße 1', 'zip' => '12345', 'city' => 'Musterstadt', 'geo' => '51.1634,10.4477'] as $k => $v) {
        if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
    }
    $img = frameworks_demo_images($log);
    $tables = frameworks_demo_tables($img, $log);
    frameworks_demo_glossary($log);
    frameworks_demo_pages($img, $tables, $log);
    \Core\PageCache::clear();
    $log('Fertig: Demo „Beispielwerk“ mit ' . count($img) . ' Bildern, Datentabelle „Projekte“, Formular „Anfragen“ und Glossar.');
    return true;
}

/**
 * Demo-Inhalte dieses Kits entfernen: Seiten der Demo (nach Adresse), Tabellen projekte/anfragen, Bilder mit dem Schlagwort
 * frameworks-demo und die Glossar-Begriffe der Demo. Andere Seiten, Tabellen und Begriffe bleiben.
 */
function frameworks_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $paths = ['start', 'leistungen', 'projekte', 'kontakt', 'impressum', 'datenschutz'];
    $ids = array_map('intval', array_column($db->fetchAll(
        "SELECT id FROM pages WHERE path IN ('" . implode("','", $paths) . "') OR path LIKE 'leistungen/%' OR slug = '_vorlage-projekte' OR (is_home = 1 AND slug = 'start')"), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (['projekte', 'anfragen'] as $h) {
        if ($t = Tables::find($h)) Tables::delete($t);
    }
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . frameworks_demo_tag() . '%']) as $m) Media::delete((int) $m['id']);
    if ($g = Tables::findContent(\Core\Glossary\Glossary::HANDLE)) {
        foreach (Entries::query($g, ['status' => 'all', 'limit' => 3000]) as $e) {
            if (in_array($e['begriff'] ?? '', array_column(frameworks_demo_terms(), 0), true)) Entries::delete($g, (int) $e['id']);
        }
    }
    Tables::flush();
    if ($ids) $log('Vorhandene Demo-Seiten entfernt (' . count($ids) . ').');
}

// ------------------------------------------------------------------ Bilder (abstrakt, GD)

/** @return list<int> Medien-IDs */
function frameworks_demo_images(callable $log): array
{
    $motifs = [
        ['Kreise in Indigo', [39, 71, 196], [236, 240, 252], [157, 180, 255]],
        ['Wellen in Petrol', [15, 107, 79], [233, 246, 241], [111, 211, 174]],
        ['Raster in Graphit', [31, 35, 40], [244, 245, 247], [255, 216, 74]],
        ['Bögen in Terrakotta', [163, 56, 15], [251, 243, 232], [246, 212, 107]],
        ['Streifen in Violett', [74, 24, 179], [244, 243, 247], [196, 181, 253]],
        ['Verlauf in Nachtblau', [16, 24, 48], [39, 71, 196], [157, 180, 255]],
    ];
    $ids = [];
    foreach ($motifs as $i => [$title, $dark, $light, $acc]) {
        $file = tempnam(sys_get_temp_dir(), 'fw') . '.jpg';
        frameworks_demo_draw($file, 1600, 1200, $dark, $light, $acc, $i);
        [$m, $err] = Media::import($file, sprintf('beispielwerk-%02d.jpg', $i + 1), 'Abstrakte Grafik: ' . $title . ' (Beispielbild)',
            ['title' => $title, 'tags' => frameworks_demo_tag(), 'credit' => 'Beispielbild, automatisch erzeugt']);
        @unlink($file);
        if ($err) $log("Bild {$title}: {$err}");
        if ($m) $ids[] = (int) $m['id'];
    }
    $log(count($ids) . ' Beispielbilder erzeugt.');
    return $ids;
}

function frameworks_demo_draw(string $file, int $w, int $h, array $dark, array $light, array $acc, int $seed): void
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = array_map(fn($a, $b) => (int) round($a * (1 - $t) + $b * $t), $light, $dark);
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, ...$c));
    }
    mt_srand(1000 + $seed);
    for ($i = 0; $i < 9; $i++) {
        $rgb = $i % 2 ? $acc : $dark;
        $r = mt_rand((int) ($h * .15), (int) ($h * .6));
        imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), $r, $r, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], mt_rand(70, 105)));
    }
    imagejpeg($im, $file, 82);
    imagedestroy($im);
}

// ------------------------------------------------------------------ Datentabellen

function frameworks_demo_table(array $def, callable $log): ?array
{
    [$clean, $errors] = Tables::validate($def);
    if ($errors) {
        $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
        return null;
    }
    return Tables::find(Tables::create($clean));
}

/** @return array{projekte: ?array, anfragen: ?array} */
function frameworks_demo_tables(array $img, callable $log): array
{
    $out = ['projekte' => null, 'anfragen' => null];
    $t = frameworks_demo_table([
        'handle' => 'projekte', 'name' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'briefcase',
        'description' => 'Fiktive Referenzprojekte der Demo – erscheinen als Datenliste und mit Detailseite.',
        'fields' => [
            ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'bereich', 'label' => 'Bereich', 'type' => 'select', 'in_list' => true, 'width' => 'half', 'options' => "web=Website\napp=Anwendung\nsystem=Designsystem"],
            ['name' => 'jahr', 'label' => 'Jahr', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
            ['name' => 'kunde', 'label' => 'Auftraggeber (fiktiv)', 'type' => 'text', 'in_list' => true],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'bild', 'label' => 'Bild', 'type' => 'media'],
        ],
        'settings' => ['route' => 'projekte', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'kurztext',
            'sort_field' => 'jahr', 'sort_dir' => 'desc', 'workflow' => 0],
    ], $log);
    if ($t) {
        $rows = [
            ['Buchungsportal für einen Sportverein', 'web', 2026, 'Sportverein Musterstadt', 'Kurse online buchen – mit Tailwind-Komponenten, die das Team selbst erweitert.'],
            ['Designsystem für eine Stadtbibliothek', 'system', 2026, 'Bibliothek Beispielhausen', 'Ein gemeinsames Designsystem für Website, Aushänge und Newsletter.'],
            ['Intranet für ein Handwerksnetzwerk', 'app', 2025, 'Netzwerk Werkbank', 'Schnelle Formulare und klare Listen – umgesetzt mit UIkit.'],
            ['Kampagnenseite für ein Kulturfest', 'web', 2025, 'Kulturverein Am Fluss', 'Programm, Karte und Anmeldung auf einer Seite.'],
        ];
        foreach ($rows as $i => [$title, $area, $year, $client, $text]) {
            Entries::save($t, null, ['titel' => $title, 'bereich' => $area, 'jahr' => (string) $year, 'kunde' => $client . ' (fiktiv)', 'kurztext' => $text,
                'beschreibung' => '<p>' . e($text) . ' Alle Angaben sind frei erfunden und zeigen, wie eine Detailseite aus einer Datentabelle entsteht.</p>'
                    . '<h3>Ausgangslage</h3><p>Das Team wollte Inhalte einmal pflegen und überall aktuell ausgeben – ohne das CSS-Framework aufzugeben, das es schon kannte.</p>'
                    . '<h3>Ergebnis</h3><ul><li>Bekannte Klassen von Tailwind CSS bzw. UIkit</li><li>Bearbeiten direkt auf der Website</li><li>Barrierearm und ohne Tracking</li></ul>',
                'bild' => $img[$i % max(1, count($img))] ?? null, 'status' => 'published']);
        }
        $out['projekte'] = Tables::find('projekte');
    }
    $t = frameworks_demo_table([
        'handle' => 'anfragen', 'name' => 'Anfragen', 'singular' => 'Anfrage', 'icon' => 'envelope-simple',
        'description' => 'Öffentliches Formular der Demo (Kern-Block „Formular“). Einträge kommen als Entwurf an.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true, 'in_list' => true, 'width' => 'half'],
            ['name' => 'thema', 'label' => 'Thema', 'type' => 'select', 'width' => 'half', 'options' => "tailwind=Tailwind CSS\nuikit=UIkit\nanderes=Etwas anderes"],
            ['name' => 'nachricht', 'label' => 'Nachricht', 'type' => 'textarea', 'required' => true, 'help' => 'Ein, zwei Sätze genügen.'],
        ],
        'settings' => ['title_field' => 'name', 'workflow' => 1,
            'form' => ['enabled' => 1, 'status' => 'draft', 'success' => 'Danke! Das war nur eine Demo – niemand liest diese Nachricht.', 'submit' => 'Anfrage senden']],
    ], $log);
    if ($t) $out['anfragen'] = $t;
    return $out;
}

// ------------------------------------------------------------------ Glossar

/** Begriffe der Demo: [Begriff, Varianten, Kurz-Erklärung, Link] – kommen in den Texten der Demo-Seiten vor */
function frameworks_demo_terms(): array
{
    return [
        ['Tailwind CSS', 'Tailwind', 'CSS-Framework mit kleinen Hilfsklassen (Utilities) direkt im HTML; das CSS entsteht beim Bauen nur aus den verwendeten Klassen.', 'https://tailwindcss.com/'],
        ['UIkit', '', 'Modulares CSS- und JavaScript-Framework mit fertigen Komponenten (Navigation, Offcanvas, Dialog, Reiter); MIT-Lizenz.', 'https://getuikit.com/'],
        ['Preflight', '', 'Die Grundstile von Tailwind CSS: setzt Abstände, Überschriften und Listen des Browsers zurück.', ''],
        ['Shadow DOM', '', 'Abgeschirmter Teilbaum einer Seite: CSS der Seite wirkt nicht hinein. Die Bedienoberfläche des CMS liegt darin.', 'https://developer.mozilla.org/de/docs/Web/API/Web_components/Using_shadow_DOM'],
        ['Kaskaden-Layer', "Cascade Layers", 'Ebenen im CSS (@layer): Regeln in einer späteren Ebene gewinnen; CSS ohne Ebene gewinnt gegen jede Ebene.', 'https://developer.mozilla.org/de/docs/Web/CSS/@layer'],
        ['Content-Security-Policy', 'CSP', 'Regeln des Servers, woher eine Seite Skripte, Stile und Bilder laden darf – schützt vor eingeschleustem Code.', ''],
    ];
}

function frameworks_demo_glossary(callable $log): void
{
    \Core\Features::setUi(\Core\Glossary\Glossary::FEATURE, true);
    [$msgs] = \Core\Glossary\Glossary::install(['publish' => true]);
    $t = Tables::findContent(\Core\Glossary\Glossary::HANDLE);
    if (!$t) { $log('Glossar: Tabelle fehlt.'); return; }
    $terms = frameworks_demo_terms();
    foreach ($terms as [$term, $variants, $short, $link]) {
        Entries::save($t, null, ['begriff' => $term, 'varianten' => $variants, 'kurz' => $short, 'link' => $link, 'status' => 'published']);
    }
    \Core\Glossary\Glossary::flush();
    $log('Glossar eingerichtet: ' . count($terms) . ' Begriffe (' . implode(' ', $msgs) . ')');
}

// ------------------------------------------------------------------ Seiten

function frameworks_demo_pages(array $img, array $tables, callable $log): void
{
    $I = fn(int $n) => $img[$n % max(1, count($img))] ?? null;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $hero = fn(string $eyebrow, string $title, string $text) => $B('hero', ['variant' => 'center', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text,
        'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'image' => null], ['background' => 'tint']);
    $proj = $tables['projekte']['handle'] ?? '';
    $dl = fn(array $x) => $x + ['eyebrow' => '', 'title' => '', 'intro' => '', 'table' => $proj, 'layout' => 'cards', 'columns' => '3', 'ratio' => '16:10', 'limit' => 0,
        'fields' => ["$proj._title", "$proj.bild", "$proj.bereich", "$proj.kurztext"], 'filter_field' => '', 'filter_op' => '=', 'filter_value' => '',
        'sort_field' => '', 'sort_dir' => '', 'paginate' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => ''];
    $firstEntry = $proj !== '' ? (int) (Entries::query($tables['projekte'], ['status' => 'published', 'limit' => 1])[0]['id'] ?? 0) : 0;

    // ---------------------------------------------------------------- Startseite
    $page(['slug' => 'start', 'title' => 'Start', 'is_home' => 1, 'menu' => 0, 'sort' => 0,
        'meta_description' => 'Demo-Website der fiktiven Firma Beispielwerk – dieselben Inhalte mit Tailwind CSS oder UIkit.'], [
        $B('hero', ['variant' => 'split', 'eyebrow' => 'Beispielwerk (fiktiv)',
            'title' => 'Ein Kit, zwei Frameworks.',
            'text' => 'Diese Demo zeigt dieselben Inhalte wahlweise mit Tailwind CSS oder UIkit – und dass Bearbeiten auf der Website, Glossar, Datenlisten und Formulare mit beiden funktionieren.',
            'button_label' => 'Projekte ansehen', 'button_link' => '/projekte', 'button2_label' => 'Kontakt', 'button2_link' => '/kontakt', 'image' => $I(0)]),
        $B('cards', ['eyebrow' => 'Was das Kit zeigt', 'title' => 'Bekannte Klassen, volle Redaktion', 'intro' => 'Jede Karte führt zu einer Seite oder einem Eintrag – angemeldet erscheint dort „Bearbeiten“ für das Ziel.', 'items' => [
            ['icon' => 'palette', 'image' => null, 'title' => 'Leistungen', 'text' => 'Seitenbaum mit Unterseiten – im Kopf als Aufklappmenü.', 'link' => '/leistungen'],
            ['icon' => 'squares-four', 'image' => null, 'title' => 'Projekte', 'text' => 'Eine Datentabelle als Karten, Liste und Tabelle, mit Detailseite.', 'link' => '/projekte'],
            ['icon' => 'sparkle', 'image' => null, 'title' => 'Ein Eintrag direkt', 'text' => 'Karte mit Ziel „Eintrag“ – öffnet in der Redaktion die Seitenleiste.', 'link' => $firstEntry ? "entry:$proj:$firstEntry" : '/projekte'],
        ]], ['background' => 'tint', 'anchor' => 'kit', 'showInNav' => false]),
        $B('text_image', ['variant' => 'right', 'eyebrow' => 'Warum es zusammenpasst', 'title' => 'Das Framework gestaltet die Seite, nicht das CMS',
            'text' => '<p>Mit Tailwind CSS liegt alles in Kaskaden-Layer; der Preflight setzt nur die Seite zurück. Die Oberfläche des CMS lebt im Shadow DOM und bleibt davon unberührt.</p><p>UIkit bringt fertige Komponenten mit – Navigation, Offcanvas, Dialog, Reiter. Es initialisiert sich über Attribute und kommt so ohne Inline-Skripte aus, passend zur Content-Security-Policy.</p><p class="t-note">Tipp: Hängen Sie <code>?fw=uikit</code> oder <code>?fw=tailwind</code> an die Adresse, um zu vergleichen.</p>',
            'button_label' => 'Mehr über die Technik', 'button_link' => '/leistungen/technik', 'image' => $I(1), 'ratio' => '4:3']),
        $B('data_list', $dl(['eyebrow' => 'Datenliste', 'title' => 'Neueste Projekte', 'limit' => 3, 'more_label' => 'Alle Projekte', 'more_link' => '/projekte']), ['background' => 'tint']),
        $B('tabs', ['eyebrow' => 'Reiter', 'title' => 'So wird gebaut', 'intro' => '', 'items' => [
            ['label' => 'Tailwind CSS', 'text' => '<p>Das CSS entsteht vorab mit der Tailwind-CLI aus den Vorlagen des Kits – ohne Play-CDN, ohne Node auf dem Server.</p><ul><li>Quellen: Vorlagen und Blöcke des Kits</li><li>Preflight in <code>@layer base</code></li><li>Dunkelmodus über die Variante <code>dark:</code></li></ul>'],
            ['label' => 'UIkit', 'text' => '<p>UIkit liegt fertig gebaut im Kit (CSS, JavaScript, Symbole). Eine kleine Brücke stellt Farben auf Variablen um – für Kontrast und Dunkelmodus.</p>'],
            ['label' => 'Gemeinsam', 'text' => '<p>Beide nutzen dieselben Blöcke, dieselben Inhalte und dieselben Kern-Bausteine: Glossar, Formular, Karte, Video.</p>'],
        ]]),
        $B('accordion', ['eyebrow' => 'FAQ', 'title' => 'Häufige Fragen', 'intro' => '', 'items' => [
            ['q' => 'Stört der Preflight die Bearbeitung?', 'a' => '<p>Nein. Preflight liegt in einem Kaskaden-Layer und verliert gegen das CSS des CMS; Werkzeugleiste und Dialoge liegen im Shadow DOM.</p>'],
            ['q' => 'Funktionieren UIkit-Komponenten beim Bearbeiten?', 'a' => '<p>Ja – Aufklappmenü und Offcanvas im Kopf bleiben bedienbar. Aufklapplisten und Reiter stehen im Editor offen untereinander, damit alles bearbeitbar ist.</p>'],
            ['q' => 'Ist das eine echte Firma?', 'a' => '<p>Nein. Beispielwerk, alle Projekte und Adressen sind frei erfunden.</p>'],
        ]], ['background' => 'tint']),
        $B('cta', ['title' => 'Lust, das eigene Framework mitzubringen?', 'text' => 'Kopieren Sie das Kit, behalten Sie einen der beiden Ordner unter views/ und bauen Sie das CSS.',
            'button_label' => 'Anfrage senden', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '',
            'modal_label' => 'Wie geht das?', 'modal_title' => 'In drei Schritten zum eigenen Framework-Kit',
            'modal_text' => '<ol><li><code>php bin/console kit:create meinkit --from=frameworks</code></li><li>Einen Ordner unter <code>views/</code> behalten (tailwind oder uikit)</li><li><code>cd tools &amp;&amp; pnpm run build</code></li></ol><p>Ausführlich: Handbuch → Technik → Frameworks.</p>'], ['background' => 'band']),
    ]);

    // ---------------------------------------------------------------- Leistungen (mit Unterseiten → Aufklappmenü)
    $l = $page(['slug' => 'leistungen', 'title' => 'Leistungen', 'sort' => 1, 'meta_description' => 'Leistungen der fiktiven Firma Beispielwerk.'], [
        $hero('Leistungen', 'Was Beispielwerk anbietet', 'Drei Bereiche – als Karten und als Unterseiten im Aufklappmenü.'),
        $B('cards', ['eyebrow' => '', 'title' => 'Unsere Bereiche', 'intro' => '', 'items' => [
            ['icon' => 'compass', 'image' => $I(2), 'title' => 'Beratung', 'text' => 'Welches Framework passt zum Team? Wir wägen gemeinsam ab.', 'link' => '/leistungen/beratung'],
            ['icon' => 'code', 'image' => $I(3), 'title' => 'Technik', 'text' => 'Build, Layer, Dunkelmodus, CSP – sauber aufgesetzt.', 'link' => '/leistungen/technik'],
            ['icon' => 'lightning', 'image' => $I(4), 'title' => 'Schulung', 'text' => 'Die Redaktion pflegt selbst – direkt auf der Website.', 'link' => '/kontakt'],
        ]]),
        $B('text', ['eyebrow' => '', 'title' => 'Arbeitsweise', 'text' => '<p class="t-lead">Kurze Wege, klare Absprachen, nachvollziehbare Ergebnisse.</p><p>Wir setzen auf Tailwind CSS, wenn ein Team gern mit Hilfsklassen arbeitet, und auf UIkit, wenn fertige Komponenten gefragt sind. <mark>Beides ist Geschmackssache</mark> – das CMS kommt mit beiden zurecht.</p><h3>Was immer dazugehört</h3><ul><li>Barrierefreiheit nach WCAG 2.2 AA</li><li>Hell und dunkel</li><li>Keine externen Anfragen, keine Cookies</li></ul><p><span class="c-success">Geprüft</span> mit axe, Tastatur und Bildschirmleser.</p>']),
    ]);
    $page(['slug' => 'beratung', 'title' => 'Beratung', 'parent_id' => $l, 'sort' => 0, 'meta_description' => 'Beratung (Demo).'], [
        $hero('Leistungen', 'Beratung', 'Unterseite – erscheint im Kopf im Aufklappmenü unter „Leistungen“.'),
        $B('text', ['eyebrow' => '', 'title' => 'Tailwind CSS oder UIkit?', 'text' => '<p>Tailwind CSS eignet sich für Teams, die gestalterische Freiheit wollen; UIkit für Projekte, die schnell fertige Bausteine brauchen.</p>']),
    ]);
    $page(['slug' => 'technik', 'title' => 'Technik', 'parent_id' => $l, 'sort' => 1, 'meta_description' => 'Technik hinter der Demo.'], [
        $hero('Leistungen', 'Technik', 'Wie die beiden Frameworks neben dem CMS arbeiten.'),
        $B('text', ['eyebrow' => '', 'title' => 'Ebenen und Schatten', 'text' => '<p>Tailwind CSS v4 legt Preflight, Komponenten und Hilfsklassen in Kaskaden-Layer. CSS ohne Layer – das des CMS – gewinnt immer. Die Werkzeugleiste und alle Dialoge der Redaktion liegen im Shadow DOM.</p><h3>UIkit</h3><p>UIkit bringt keine Layer mit; seine Grundstile treffen nur Elemente der Seite. Die Content-Security-Policy erlaubt keine Inline-Stile für Besucher – deshalb nutzt das Kit Symbole aus dem Symbolsatz statt der animierten SVG-Symbole mit eingebettetem Stil.</p>']),
        $B('video', ['eyebrow' => 'Video', 'title' => 'Zwei-Klick-Video', 'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'video_file' => null, 'poster' => $I(5)], ['background' => 'tint']),
    ]);

    // ---------------------------------------------------------------- Projekte (Datenliste) + Detailseite
    $page(['slug' => 'projekte', 'title' => 'Projekte', 'sort' => 2, 'meta_description' => 'Fiktive Projekte als Datenliste.'], [
        $hero('Datenliste', 'Projekte', 'Einträge der Tabelle „Projekte“ – als Karten (Framework-Klassen), als Liste und als Tabelle (Kern).'),
        $B('data_list', $dl(['title' => 'Alle Projekte', 'columns' => '2'])),
        $B('data_list', $dl(['eyebrow' => 'Layout „Liste“', 'title' => 'Als Liste', 'layout' => 'list', 'limit' => 2, 'fields' => ["$proj._title", "$proj.bild", "$proj.jahr", "$proj.kurztext"]]), ['background' => 'tint']),
        $B('data_list', $dl(['eyebrow' => 'Layout „Tabelle“ (Kern)', 'title' => 'Als Tabelle', 'layout' => 'table', 'fields' => ["$proj._title", "$proj.kunde", "$proj.bereich", "$proj.jahr"]])),
    ]);
    if ($proj !== '') {
        $tpl = Pages::create(['slug' => '_vorlage-projekte', 'title' => 'Projekte – Detailseite', 'type' => 'template', 'template_for' => $proj, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj._title", "$proj.bereich", "$proj.jahr", "$proj.bild"], 'layout' => 'head', 'show_labels' => false, 'ratio' => '21:9', 'back_label' => 'Alle Projekte', 'back_link' => '/projekte'], ['spaceBottom' => 'small']),
            $B('data_fields', ['table' => $proj, 'fields' => ["$proj.kurztext", "$proj.beschreibung"], 'layout' => 'prose', 'show_labels' => false, 'ratio' => '16:9', 'back_label' => '', 'back_link' => ''], ['spaceTop' => 'none']),
            $B('data_list', $dl(['title' => 'Weitere Projekte', 'limit' => 3, 'exclude_current' => true, 'fields' => ["$proj._title", "$proj.bild", "$proj.bereich"]]), ['background' => 'tint']),
        ]));
        Tables::setDetailPage(Tables::find($proj), $tpl);
        Tables::flush();
    }

    // ---------------------------------------------------------------- Kontakt: zentrale Angaben, Formular, Karte
    $page(['slug' => 'kontakt', 'title' => 'Kontakt', 'sort' => 3, 'meta_description' => 'Kontakt zur fiktiven Firma Beispielwerk.'], [
        $hero('Kontakt', 'Schreiben Sie uns', 'Das Formular ist ein Kern-Block – gestaltet über Variablen und die Button-Klassen des Frameworks.'),
        $B('contact', ['eyebrow' => 'Zentral gepflegt', 'title' => 'So erreichen Sie uns', 'intro' => 'Adresse, Telefon und E-Mail kommen aus „Website“ (fiktiv).']),
        $B('data_form', ['eyebrow' => 'Formular', 'title' => 'Anfrage', 'intro' => 'Pflichtfelder sind markiert. Ohne Angaben absenden zeigt die Fehlermeldungen.', 'table' => $tables['anfragen']['handle'] ?? '', 'submit_label' => '', 'success_text' => ''], ['background' => 'tint']),
        $B('map', ['eyebrow' => 'Karte', 'title' => 'Standort (Beispiel)', 'location' => 'site', 'zoom' => '12', 'height' => 'm', 'route' => true]),
    ]);

    // ---------------------------------------------------------------- Rechtliches
    $imp = $page(['slug' => 'impressum', 'title' => 'Impressum', 'menu' => 0, 'sort' => 8, 'noindex' => 1], [
        $B('text', ['eyebrow' => '', 'title' => 'Impressum', 'text' => '<p>Beispielwerk (fiktiv), Musterstraße 1, 12345 Musterstadt. Diese Website ist eine Demo von KLXM Studio; alle Angaben sind frei erfunden.</p>']),
    ]);
    $dse = $page(['slug' => 'datenschutz', 'title' => 'Datenschutz', 'menu' => 0, 'sort' => 9, 'noindex' => 1], [
        $B('text', ['eyebrow' => '', 'title' => 'Datenschutz', 'text' => '<p>Demo-Inhalt. Die Website setzt keine Cookies für Besucher und lädt keine Inhalte Dritter – Videos erst nach Klick (Zwei-Klick-Lösung).</p>']),
    ]);
    app()->settings->set('imprint_page', $imp);
    app()->settings->set('privacy_page', $dse);
    $log('Seiten angelegt: Start, Leistungen (+ Beratung, Technik), Projekte (+ Detailseite), Kontakt, Impressum, Datenschutz, Glossar.');
}
