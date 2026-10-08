<?php
/**
 * Demo-Set „Netzwerk & Bildung“ des Kits fluid (passend zur Design-Vorlage „Netzwerk & Bildung · Farbenfroh mit Seitenleiste“):
 * Mitglieder-Verzeichnis aus Kern-Bausteinen – Datentabelle „Mitglieder“ (Logo, Name, Kategorien, Region, Ansprechperson,
 * Adresse, Telefon, E-Mail, Website, Kurztext, Beschreibung, Ort), Seite „Mitglieder“ (Datenliste „Verzeichnis“ mit
 * Filter-Schaltflächen, Suche und A–Z-Liste) und Detailseiten-Vorlage (Profil, Beschreibung + Kontaktkarte, „Weitere
 * Mitglieder“). Dazu ein kleiner Seitenbaum „Netzwerk“ mit drei Ebenen und ein erzeugtes Logo.
 *
 * Alle Mitglieder, Namen, Adressen und Logos sind frei erfunden (Logos: Monogramme, mit GD erzeugt; Adressen in
 * „Musterstadt“, E-Mail/Web unter example.org). Nicht Teil der Standard-Startinhalte – nur auf Wunsch:
 *   CMS_SITE=… php kits/fluid/tools/demo.php --network [--preset=netzwerk]      anlegen (ersetzt ein vorhandenes Demo-Set)
 *   CMS_SITE=… php kits/fluid/tools/demo.php --network-remove                   entfernen
 */
declare(strict_types=1);

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

const FLUID_NET_TAG = 'netzwerk-demo';
const FLUID_NET_TABLE = 'netzwerk_mitglieder';

/** Demo-Set anlegen (vorhandenes vorher entfernen). Name und Logo der Website nur, solange dort noch die Fluid-Demo steht. */
function fluid_network_install(?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    fluid_network_remove($log);
    $font = dirname(__DIR__) . '/fonts/Inter_700Bold.ttf';
    $palette = [[194, 65, 12], [21, 128, 61], [124, 58, 237], [219, 39, 119], [37, 99, 235]];

    // Logo des Netzwerks (quadratisch-hoch: zeigt das große Logo in der Seitenleiste) – nur, wenn noch keins gesetzt ist
    if (!(int) setting('logo')) {
        $file = tempnam(sys_get_temp_dir(), 'nw') . '.png';
        fluid_network_brand($file, $palette, $font);
        [$m, $err] = Media::import($file, 'netzwerk-lernfreude-logo.png', 'Logo Netzwerk Lernfreude', ['title' => 'Logo Netzwerk Lernfreude (Demo)', 'tags' => FLUID_NET_TAG, 'credit' => 'Beispiel-Logo, automatisch erzeugt']);
        @unlink($file);
        if ($m) app()->settings->set('logo', (int) $m['id']);
        elseif ($err) $log('Logo: ' . $err);
        // Helle Fassung für das dunkle Farbschema
        $file = tempnam(sys_get_temp_dir(), 'nw') . '.png';
        fluid_network_brand($file, [[253, 186, 116], [134, 239, 172], [196, 181, 253], [249, 168, 212], [147, 197, 253]], $font, [251, 244, 238]);
        [$m, $err] = Media::import($file, 'netzwerk-lernfreude-logo-hell.png', 'Logo Netzwerk Lernfreude', ['title' => 'Logo Netzwerk Lernfreude, helle Fassung (Demo)', 'tags' => FLUID_NET_TAG, 'credit' => 'Beispiel-Logo, automatisch erzeugt']);
        @unlink($file);
        if ($m && !(int) setting('logo_dark')) app()->settings->set('logo_dark', (int) $m['id']);
    }
    if (in_array(trim((string) setting('org_name')), ['', 'Studio Beispiel (Demo)'], true)) {
        app()->settings->set('org_name', 'Netzwerk Lernfreude (Demo)');
        app()->settings->set('short_name', 'Lernfreude');
        app()->settings->set('tagline', 'Bildungsanbieter, die voneinander lernen.');
    }

    // ---------------------------------------------------------------- Tabelle „Mitglieder“
    [$clean, $errors] = Tables::validate([
        'handle' => FLUID_NET_TABLE, 'name' => 'Mitglieder (Demo)', 'singular' => 'Mitglied', 'icon' => 'users-three',
        'description' => 'Demo-Set „Netzwerk & Bildung“ – frei erfundene Mitglieder.',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'media'],
            ['name' => 'kategorie', 'label' => 'Schwerpunkt', 'type' => 'multiselect', 'in_list' => true,
                'options' => "seminare=Seminare\nreisen=Bildungsreisen\nverlag=Verlag\nmedien=Medien\nberatung=Beratung"],
            ['name' => 'region', 'label' => 'Region', 'type' => 'select', 'in_list' => true, 'width' => 'half',
                'options' => "nord=Nord\nwest=West\nmitte=Mitte\nsued=Süd\nost=Ost"],
            ['name' => 'ansprechperson', 'label' => 'Ansprechperson', 'type' => 'text', 'width' => 'half'],
            ['name' => 'strasse', 'label' => 'Straße', 'type' => 'text'],
            ['name' => 'plz', 'label' => 'PLZ', 'type' => 'text', 'width' => 'half'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'width' => 'half', 'in_list' => true],
            ['name' => 'telefon', 'label' => 'Telefon', 'type' => 'tel', 'width' => 'half'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'width' => 'half'],
            ['name' => 'website', 'label' => 'Website', 'type' => 'url'],
            ['name' => 'kurztext', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'beschreibung', 'label' => 'Beschreibung', 'type' => 'richtext'],
            ['name' => 'standort', 'label' => 'Standort (Karte)', 'type' => 'geo'],
        ],
        'settings' => ['route' => 'mitglieder', 'title_field' => 'name', 'image_field' => 'logo', 'description_field' => 'kurztext',
            'sort_field' => 'name', 'sort_dir' => 'asc', 'workflow' => 0],
    ]);
    if ($errors) { $log('Tabelle Mitglieder: ' . implode(' ', $errors)); return false; }
    $t = Tables::find(Tables::create($clean));

    $members = [
        ['Akademie Lichtblick', ['seminare', 'beratung'], 'mitte', 'Dr. Erika Musterfrau', 'Musterweg 3', '12345', 'Musterstadt', 'Seminare zu Kommunikation, Achtsamkeit und Führung – in kleinen Gruppen und mit viel Zeit für Austausch.'],
        ['Reisewerk Horizonte', ['reisen'], 'sued', 'Max Mustermann', 'Am Beispielplatz 7', '23456', 'Beispielhausen', 'Bildungsreisen mit Studienleitung: Kultur, Natur und Geschichte – langsam und gut vorbereitet.'],
        ['Verlag Blattgrün', ['verlag', 'medien'], 'west', 'Jana Beispiel', 'Papiergasse 12', '34567', 'Exempelburg', 'Sachbücher und Arbeitshefte für Pädagogik, Beratung und Erwachsenenbildung.'],
        ['Seminarhaus am Weiher', ['seminare'], 'nord', 'Tom Probe', 'Weiherweg 1', '45678', 'Musterhafen', 'Tagungshaus im Grünen mit sechs Seminarräumen, Garten und vegetarischer Küche.'],
        ['Medienwerkstatt Funkenflug', ['medien'], 'ost', 'Lea Exempel', 'Studiostraße 4', '56789', 'Bad Musterbach', 'Lernvideos, Podcasts und digitale Kurse – von der Idee bis zur fertigen Lernplattform.'],
        ['Institut Gelassenheit', ['seminare', 'beratung'], 'sued', 'Paul Muster', 'Ruheweg 9', '23457', 'Beispielhausen', 'Fortbildungen zu Stressbewältigung und Resilienz für Teams in sozialen Berufen.'],
        ['Atelier Wandelbar', ['seminare'], 'west', 'Mia Beispielkind', 'Farbenhof 2', '34568', 'Exempelburg', 'Kreative Seminare: Malen, Schreiben und Theater als Wege des Lernens.'],
        ['Zeitschrift Neugier', ['verlag'], 'mitte', 'Ole Testmann', 'Redaktionsplatz 5', '12346', 'Musterstadt', 'Magazin für lebendiges Lernen – viermal im Jahr, gedruckt und digital.'],
        ['Wegbegleiter Beratung', ['beratung'], 'nord', 'Sara Vorlage', 'Hafenkante 8', '45679', 'Musterhafen', 'Supervision, Coaching und Organisationsberatung für Bildungseinrichtungen.'],
        ['Klangraum Seminare', ['seminare', 'reisen'], 'ost', 'Jonas Muster', 'Lindenallee 14', '56780', 'Bad Musterbach', 'Musik, Stimme und Bewegung – Seminare und Seminarreisen für alle Altersgruppen.'],
        ['Fernweh & Fragen', ['reisen', 'seminare'], 'sued', 'Nina Probe', 'Bahnhofstraße 21', '23458', 'Beispielberg', 'Studienreisen mit Seminarprogramm: lernen, wo die Dinge geschehen.'],
        ['Lernlabor Digital', ['medien', 'beratung'], 'mitte', 'Ben Beispiel', 'Werkstattweg 6', '12347', 'Musterstadt', 'Beratung und Werkzeuge für digitales Lernen: Konzepte, Schulungen und Begleitung.'],
    ];
    $text = fn(string $name, string $short) => '<p>' . e($name) . ' ist ein frei erfundenes Beispielmitglied. Die Beschreibung zeigt, wie eine Detailseite aus der Datentabelle entsteht: Profil mit Logo, ausführlicher Text, Kontaktkarte und weitere Mitglieder mit ähnlichem Schwerpunkt.</p>'
        . '<h3>Angebote</h3><ul><li>Offene Seminare und Inhouse-Veranstaltungen</li><li>Austausch im Netzwerk und gemeinsame Projekte</li><li>Ansprechbar für Kooperationen</li></ul>';
    foreach ($members as $i => [$name, $cats, $region, $person, $street, $zip, $city, $short]) {
        $file = tempnam(sys_get_temp_dir(), 'nw') . '.png';
        fluid_network_logo($file, $name, $palette[$i % 5], $i, $font);
        [$m, $err] = Media::import($file, 'mitglied-' . Pages::slugify($name) . '.png', 'Logo ' . $name . ' (Beispiel)', ['title' => 'Logo ' . $name . ' (Demo)', 'tags' => FLUID_NET_TAG, 'credit' => 'Beispiel-Logo, automatisch erzeugt']);
        @unlink($file);
        if ($err) $log($name . ': ' . $err);
        $slug = Pages::slugify($name);
        Entries::save($t, null, ['name' => $name, 'logo' => $m ? (int) $m['id'] : null, 'kategorie' => $cats, 'region' => $region, 'ansprechperson' => $person,
            'strasse' => $street, 'plz' => $zip, 'ort' => $city, 'telefon' => sprintf('0123 4567-%02d', 10 + $i), 'email' => 'kontakt@' . $slug . '.example.org',
            'website' => 'https://' . $slug . '.example.org', 'kurztext' => $short, 'beschreibung' => $text($name, $short),
            // Kartenpunkt nur beim ersten Mitglied: geografischer Mittelpunkt Deutschlands (Wiese, keine Adresse)
            'standort' => $i === 0 ? '51.1634,10.4477' : '', 'status' => 'published']);
    }
    $h = FLUID_NET_TABLE;
    $B = fn(string $type, array $data, array $tunes = []) => ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    $dl = fn(array $extra) => $extra + ['eyebrow' => '', 'title' => '', 'intro' => '', 'table' => $h, 'columns' => '3', 'ratio' => '16:10', 'limit' => 0, 'image_fit' => '',
        'filter_field' => '', 'filter_op' => '=', 'filter_value' => '', 'exclude_current' => false, 'sort_field' => '', 'sort_dir' => '', 'paginate' => false,
        'visitor_filter' => '', 'visitor_filter2' => '', 'visitor_search' => false, 'az_index' => false, 'link_detail' => true, 'more_label' => '', 'more_link' => '', 'empty_text' => '', 'hide_empty' => false];
    $df = fn(array $fields, string $layout, array $extra = []) => $extra + ['table' => $h, 'fields' => array_map(fn($f) => "$h.$f", $fields), 'layout' => $layout, 'show_labels' => false, 'ratio' => '1:1', 'back_label' => '', 'back_link' => ''];
    $page = fn(array $f, array $blocks) => Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    $sort = (int) app()->db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL');

    // ---------------------------------------------------------------- Seite „Mitglieder“ + Detailseiten-Vorlage
    $page(['slug' => 'mitglieder', 'title' => 'Mitglieder', 'sort' => $sort, 'meta_description' => 'Die Mitglieder des Netzwerks (Demo): Seminaranbieter, Bildungsreisen, Verlage, Medien und Beratung – mit Filter und Suche.'], [
        $B('hero', ['variant' => 'compact', 'eyebrow' => 'Netzwerk (Demo)', 'title' => 'Unsere *Mitglieder*', 'text' => 'Zwölf frei erfundene Bildungsanbieter zeigen das Verzeichnis: nach Schwerpunkt und Region filtern, suchen oder alphabetisch stöbern.'], ['background' => 'tint']),
        $B('data_list', $dl(['eyebrow' => 'Verzeichnis', 'title' => 'Alle Mitglieder', 'layout' => 'directory', 'fields' => ["$h._title", "$h.logo", "$h.ort", "$h.kurztext", "$h.kategorie"],
            'visitor_filter' => "$h.kategorie", 'visitor_filter2' => "$h.region", 'visitor_search' => true, 'text_lines' => '3'])),
        $B('data_list', $dl(['eyebrow' => 'Von A bis Z', 'title' => 'Mitglieder alphabetisch', 'layout' => 'compact', 'fields' => ["$h._title", "$h.ort", "$h.region"], 'az_index' => true]), ['background' => 'muted']),
    ]);
    $tpl = Pages::create(['slug' => '_vorlage-netzwerk-mitglieder', 'title' => 'Mitglieder (Demo) – Detailseite', 'type' => 'template', 'template_for' => $h, 'status' => 'published', 'menu' => 0], Pages::sanitizeBlocks([
        $B('data_fields', $df(['_title', 'logo', 'kategorie', 'kurztext'], 'profile', ['back_label' => 'Alle Mitglieder', 'back_link' => '/mitglieder']), ['background' => 'tint']),
        $B('layout', ['preset' => '2-1', 'valign' => 'top', 'gap' => 'large', 'stack' => 'tablet', 'reverse' => false, 'columns' => [
            ['blocks' => [$B('data_fields', $df(['beschreibung'], 'prose'))]],
            ['blocks' => [$B('data_fields', $df(['ansprechperson', 'strasse', 'plz', 'ort', 'telefon', 'email', 'website', 'standort'], 'contact'))]],
        ]]),
        $B('data_list', $dl(['title' => 'Weitere Mitglieder', 'layout' => 'directory', 'fields' => ["$h._title", "$h.logo", "$h.ort", "$h.kategorie"], 'limit' => 3,
            'filter_field' => "$h.kategorie", 'filter_op' => 'same', 'exclude_current' => true, 'more_label' => 'Alle Mitglieder', 'more_link' => '/mitglieder']), ['background' => 'muted']),
    ]));
    $t['settings']['detail_page_id'] = $tpl;
    app()->db->update('data_tables', ['settings_json' => json_encode($t['settings'], JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $t['id']]);
    Tables::flush();

    // ---------------------------------------------------------------- Seitenbaum „Netzwerk“ (drei Ebenen – Menübaum der Seitenleiste)
    $rich = fn(string $title, string $html) => $B('richtext', ['variant' => 'standard', 'eyebrow' => '', 'title' => $title, 'intro' => '', 'text' => $html, 'meta' => '', 'dropcap' => false, 'toc' => false]);
    $root = $page(['slug' => 'netzwerk', 'title' => 'Das Netzwerk', 'sort' => $sort + 1, 'meta_description' => 'Wie das Netzwerk arbeitet (Demo).'], [
        $B('hero', ['variant' => 'compact', 'eyebrow' => 'Über uns (Demo)', 'title' => 'Voneinander *lernen*', 'text' => 'Bildungsanbieter aus verschiedenen Feldern treffen sich, tauschen Erfahrungen und entwickeln gemeinsam Neues.'], ['background' => 'tint']),
        $B('features', ['variant' => 'cards', 'eyebrow' => 'Was uns verbindet', 'title' => 'Vier Grundsätze', 'intro' => '', 'size' => 'm', 'items' => [
            ['icon' => 'handshake', 'title' => 'Persönlich', 'text' => 'Wir kennen uns – und treffen uns mindestens einmal im Jahr.', 'link_label' => '', 'link' => ''],
            ['icon' => 'door-open', 'title' => 'Offen', 'text' => 'Neue Mitglieder sind willkommen, wenn sie die Grundsätze teilen.', 'link_label' => '', 'link' => ''],
            ['icon' => 'chats-circle', 'title' => 'Im Austausch', 'text' => 'Erfahrungen, Kontakte und Räume teilen wir großzügig.', 'link_label' => '', 'link' => ''],
            ['icon' => 'puzzle-piece', 'title' => 'Gemeinsam', 'text' => 'Aus Gesprächen entstehen Kooperationen und neue Angebote.', 'link_label' => '', 'link' => ''],
        ]]),
    ]);
    $meet = $page(['slug' => 'treffen', 'title' => 'Netzwerktreffen', 'parent_id' => $root, 'sort' => 1, 'meta_description' => 'Das jährliche Netzwerktreffen (Demo).'], [
        $B('hero', ['variant' => 'compact', 'eyebrow' => 'Jedes Jahr im Januar', 'title' => 'Das Netzwerktreffen', 'text' => 'Zwei Tage, ein neuer Ort, viele Gespräche – ausgerichtet von einem Mitglied.'], ['background' => 'tint']),
        $rich('Ablauf (Beispiel)', '<p>Am ersten Tag stellen sich neue Mitglieder vor, am zweiten arbeiten wir in kleinen Runden an gemeinsamen Themen. Alle Angaben sind frei erfunden.</p><ul><li>Ankommen und Kennenlernen</li><li>Werkstattrunden</li><li>Abschluss und Ausblick</li></ul>'),
    ]);
    $page(['slug' => 'anmeldung', 'title' => 'Anmeldung', 'parent_id' => $meet, 'sort' => 1, 'meta_description' => 'Anmeldung zum Netzwerktreffen (Demo).'], [
        $B('hero', ['variant' => 'compact', 'eyebrow' => 'Netzwerktreffen', 'title' => 'Anmeldung', 'text' => 'Beispielseite der dritten Ebene im Menübaum.'], ['background' => 'tint']),
        $rich('So melden Sie sich an', '<p>Für ein echtes Formular eignet sich der Block „Formular (Datentabelle)“. Diese Seite zeigt nur die Tiefe der Navigation.</p>'),
    ]);
    $page(['slug' => 'geschichte', 'title' => 'Geschichte', 'parent_id' => $root, 'sort' => 2, 'meta_description' => 'Die Geschichte des Netzwerks (Demo).'], [
        $B('hero', ['variant' => 'compact', 'eyebrow' => 'Seit vielen Jahren', 'title' => 'Wie alles begann', 'text' => 'Eine erfundene Chronik als Beispiel.'], ['background' => 'tint']),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => '', 'title' => 'Chronik (Beispiel)', 'intro' => '', 'items' => [
            ['meta' => 'Anfang', 'title' => 'Erstes Treffen', 'text' => 'Fünf Anbieter sitzen an einem Tisch.', 'icon' => ''],
            ['meta' => 'Danach', 'title' => 'Wachstum', 'text' => 'Verlage, Reiseveranstalter und Medien kommen dazu.', 'icon' => ''],
            ['meta' => 'Heute', 'title' => 'Ein lebendiges Netzwerk', 'text' => 'Jedes Jahr ein Treffen an einem neuen Ort.', 'icon' => ''],
        ]]),
    ]);
    \Core\PageCache::clear();
    $log('Demo-Set „Netzwerk & Bildung“ angelegt: /mitglieder (' . count($members) . ' Mitglieder mit Detailseiten) und /netzwerk.');
    return true;
}

/** Demo-Set entfernen (Seiten, Vorlage, Tabelle, Logos) */
function fluid_network_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $db = app()->db;
    $ids = array_map('intval', array_column($db->fetchAll("SELECT id FROM pages WHERE path IN ('mitglieder', 'netzwerk') OR path LIKE 'netzwerk/%' OR slug = '_vorlage-netzwerk-mitglieder'"), 'id'));
    foreach ($ids as $id) $db->query('DELETE FROM pages WHERE id = ?', [$id]);
    if ($ids) Pages::rebuildPaths();
    foreach (Tables::all() as $t) if ($t['handle'] === FLUID_NET_TABLE) Tables::delete($t);
    $logo = (int) setting('logo');
    foreach ($db->fetchAll('SELECT id FROM media WHERE tags LIKE ?', ['%' . FLUID_NET_TAG . '%']) as $m) {
        if ((int) $m['id'] === $logo) app()->settings->set('logo', null);
        if ((int) $m['id'] === (int) setting('logo_dark')) app()->settings->set('logo_dark', null);
        Media::delete((int) $m['id']);
    }
    if ($ids) $log('Vorhandenes Demo-Set „Netzwerk & Bildung“ entfernt.');
}

/** Logo des Netzwerks (900 × 900, transparent): fünf Farbkreise im Bogen, darunter die Wortmarke */
function fluid_network_brand(string $file, array $palette, string $font, array $text = [28, 25, 23]): void
{
    $im = imagecreatetruecolor(900, 900);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    foreach ($palette as $i => $rgb) {
        $a = deg2rad(200 + $i * 35);
        imagefilledellipse($im, (int) (450 + cos($a) * 250), (int) (430 + sin($a) * 250), 190, 190, imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], 12));
    }
    $ink = imagecolorallocate($im, ...$text);
    if (is_file($font)) {
        foreach ([['Netzwerk', 92, 640], ['Lernfreude', 118, 790]] as [$w, $size, $y]) {
            $box = imagettfbbox($size, 0, $font, $w);
            imagettftext($im, $size, 0, (int) ((900 - ($box[2] - $box[0])) / 2) - $box[0], $y, $ink, $font, $w);
        }
    }
    imagepng($im, $file, 7);
    unset($im);
}

/** Monogramm-Logo eines Beispielmitglieds (900 × 560, transparent): Form in der Farbe + Initialen, daneben der Name */
function fluid_network_logo(string $file, string $name, array $rgb, int $seed, string $font): void
{
    $im = imagecreatetruecolor(900, 560);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $col = imagecolorallocate($im, ...$rgb);
    $soft = imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], 85);
    $white = imagecolorallocate($im, 255, 255, 255);
    $ink = imagecolorallocate($im, 30, 27, 25);
    $cx = 190; $cy = 280;
    switch ($seed % 4) {
        case 0: imagefilledellipse($im, $cx, $cy, 270, 270, $col); break;
        case 1: imagefilledrectangle($im, $cx - 130, $cy - 130, $cx + 130, $cy + 130, $col); break;
        case 2: imagefilledpolygon($im, [$cx, $cy - 150, $cx + 150, $cy + 115, $cx - 150, $cy + 115], $col); break;
        default: imagefilledellipse($im, $cx - 35, $cy, 230, 230, $soft); imagefilledellipse($im, $cx + 35, $cy, 230, 230, $col);
    }
    if (is_file($font)) {
        $words = preg_split('~[\s&-]+~u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $ini = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1] ?? '', 0, 1));
        $box = imagettfbbox(96, 0, $font, $ini);
        imagettftext($im, 96, 0, (int) ($cx - ($box[2] - $box[0]) / 2) - $box[0], $cy + ($seed % 4 === 2 ? 70 : 48), $white, $font, $ini);
        // Name in bis zu zwei Zeilen rechts daneben
        $ws = preg_split('~\s+~u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $cut = (int) ceil(count($ws) / 2);
        $lines = count($ws) > 1 ? [implode(' ', array_slice($ws, 0, $cut)), implode(' ', array_slice($ws, $cut))] : [$name];
        // Schriftgröße so, dass die längste Zeile in die Fläche rechts passt (max. 500 px)
        $size = 50;
        foreach ($lines as $l) { $bb = imagettfbbox(50, 0, $font, $l); $size = min($size, (int) floor(50 * 500 / max(1, $bb[2] - $bb[0]))); }
        foreach ($lines as $k => $l) imagettftext($im, $size, 0, 370, 262 + $k * (int) ($size * 1.45) - (count($lines) - 1) * 10, $k ? $col : $ink, $font, $l);
    }
    imagepng($im, $file, 7);
    unset($im);
}
