<?php
/**
 * Seite „Bausteine“ des Kits „NativeCSS“: jede Funktion dieses Kits mit frei erfundenen Inhalten – Einblend-Effekte (alle vier),
 * Hintergrundvideo im Einstieg, Morphing (Text + Bild und Video), Kennzahlen hochzählen, Logos-Laufband, Scrollytelling,
 * Zeitleiste, Kundenstimmen, Preistabelle und Preisliste, Vorher/Nachher, Video-Player mit Untertiteln und Kapiteln, Audio-Playlist
 * mit Kapiteln und Transkript, Karten mit Hervorhebung und Filter, Datenliste mit sortierbaren Spalten (Tabelle native_demo_kurse).
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/native/tools/demo.php [--force|--remove]
 * Medien (kits/native/demo/media, Schlagwort „native-demo“): Kurzfilm, Schleifen und Vorschaubilder aus der Demo von NativeCSS
 * (für NativeCSS erzeugt, MIT, © Thomas Skerbis / KLXM Crossmedia), Foto Wohnzimmer von Curtis Adams (Pexels-Lizenz,
 * https://www.pexels.com/photo/empty-living-room-6835103/; „Vorher“ dasselbe Foto, flau bearbeitet). Videos ohne Ton.
 * Audio: zwei synthetische Klangproben (mit ffmpeg erzeugt, Akkord und Rauschen, ohne Sprache – frei, MIT wie das Kit).
 */
declare(strict_types=1);

use Core\Kit\Demo;
use Core\Media;
use Core\MediaTracks;

const NATIVE_DEMO_TAG = 'native-demo';
const NATIVE_DEMO_TABLE = 'native_demo_kurse';

/** Seite „Bausteine“ anlegen. $force: vorhandene Seite und Demo-Medien vorher entfernen. */
function native_demo_install(bool $force = false, ?callable $log = null): bool
{
    $log ??= static fn(string $m) => null;
    $existing = app()->db->fetch("SELECT id FROM pages WHERE path = 'bausteine' AND type = 'page' LIMIT 1");
    if ($existing && !$force) {
        $log('Die Seite „Bausteine“ ist schon vorhanden – mit --force neu anlegen.');
        return false;
    }
    native_demo_remove($log);
    $m = native_demo_media($log);
    native_demo_page($m, native_demo_table($log));
    \Core\PageCache::clear();
    $log('Fertig: Seite „Bausteine“ mit ' . count(array_filter($m)) . ' Medien.');
    return true;
}

/** Seite „Bausteine“ und Demo-Medien entfernen */
function native_demo_remove(?callable $log = null): void
{
    $log ??= static fn(string $m) => null;
    $ids = Demo::ids("SELECT id FROM pages WHERE path = 'bausteine' OR path LIKE 'bausteine/%'");
    Demo::deletePages($ids);
    Demo::deleteMedia(NATIVE_DEMO_TAG);
    Demo::deleteTables(NATIVE_DEMO_TABLE);
    $log('Vorhandene Seite „Bausteine“ entfernt (' . count($ids) . ' Seiten).');
}

/**
 * Demo-Medien übernehmen (Kopie – die Dateien im Kit bleiben). Kurzfilm mit Untertiteln (de, en) und Kapiteln als Textspuren
 * (Core\MediaTracks). @return array<string, ?int>
 */
function native_demo_media(callable $log): array
{
    $dir = dirname(__DIR__) . '/demo/media';
    $ncss = 'NativeCSS-Demo (MIT), © Thomas Skerbis / KLXM Crossmedia';
    $pexels = 'Foto: Curtis Adams, Pexels (Pexels-Lizenz), https://www.pexels.com/photo/empty-living-room-6835103/';
    $synth = 'Synthetische Klangprobe (ffmpeg), ohne Sprache – für das Kit NativeCSS erzeugt (MIT)';
    $files = [
        'film' => ['ncss-film.mp4', 'Kurzfilm: Vier Stimmungen (ohne Ton)', '', $ncss, false],
        'film_poster' => ['ncss-film-poster.jpg', 'Kurzfilm: Vorschaubild', 'Gezeichnete Hügellandschaft im Morgenlicht mit Wolken und Nadelbäumen', $ncss, false],
        'waves' => ['ncss-wellen.mp4', 'Schleife: Wellen', '', $ncss, true],
        'waves_poster' => ['ncss-wellen-poster.jpg', 'Schleife Wellen: Standbild', 'Weiche Wellen in Blau und Türkis', $ncss, false],
        'aurora' => ['ncss-polarlicht.mp4', 'Schleife: Polarlicht', '', $ncss, true],
        'aurora_poster' => ['ncss-polarlicht-poster.jpg', 'Schleife Polarlicht: Standbild', 'Grün und violett schimmerndes Polarlicht über dunklem Himmel', $ncss, false],
        'shapes' => ['ncss-formen-poster.jpg', 'Weiche Formen', 'Weiche, ineinanderfließende Farbflächen', $ncss, false],
        'before' => ['pexels-wohnzimmer-vorher.jpg', 'Wohnzimmer vorher (Foto: Curtis Adams, Pexels)', 'Leeres Wohnzimmer mit Deckenventilator und Holzboden, flau und gelbstichig', $pexels . ' – für das Beispiel flau bearbeitet', false],
        'after' => ['pexels-wohnzimmer-nachher.jpg', 'Wohnzimmer nachher (Foto: Curtis Adams, Pexels)', 'Helles, leeres Wohnzimmer mit Deckenventilator, Holzboden und großen Fenstern', $pexels, false],
        'chord' => ['ncss-klang-akkord.mp3', 'Klangprobe: Akkord', '', $synth, false],
        'surf' => ['ncss-klang-brandung.mp3', 'Klangprobe: Brandung', '', $synth, false],
    ];
    $out = [];
    foreach ($files as $key => [$file, $title, $alt, $credit, $decorative]) {
        $tmp = tempnam(sys_get_temp_dir(), 'ndemo');
        copy($dir . '/' . $file, $tmp);
        $out[$key] = Demo::import($tmp, $file, $alt, ['title' => $title, 'tags' => NATIVE_DEMO_TAG, 'credit' => $credit, 'decorative' => $decorative], $log);
    }
    // Textspuren des Kurzfilms: Untertitel deutsch/englisch, Kapitel deutsch/englisch
    $film = $out['film'] ? Media::find($out['film']) : null;
    if ($film && MediaTracks::supports($film)) {
        foreach ([['ncss-film.de.vtt', 'subtitles', 'de'], ['ncss-film.en.vtt', 'subtitles', 'en'], ['ncss-film-kapitel.de.vtt', 'chapters', 'de'], ['ncss-film-kapitel.en.vtt', 'chapters', 'en']] as [$f, $kind, $lang]) {
            try {
                MediaTracks::save($film, ['kind' => $kind, 'lang' => $lang, 'vtt' => (string) file_get_contents($dir . '/' . $f), 'status' => 'published', 'source' => 'upload']);
            } catch (\Throwable $e) {
                $log($f . ': ' . $e->getMessage());
            }
        }
    }
    // Audiodateien: Kapitel (deutsch, englisch) und Transkript je Datei – der Audio-Player liest sie aus der Mediathek
    $audio = [
        'chord' => ['ncss-klang-akkord', 'Ohne Sprache. Ein ruhiger Dreiklang schwillt an, schwingt leise und klingt nach etwa 24 Sekunden aus.'],
        'surf' => ['ncss-klang-brandung', 'Ohne Sprache. Weiches Rauschen, das wie Brandung an- und abschwillt – zwei Wellen, etwa 24 Sekunden.'],
    ];
    foreach ($audio as $key => [$base, $text]) {
        $a = $out[$key] ? Media::find($out[$key]) : null;
        if (!$a || !MediaTracks::supports($a)) continue;
        try {
            foreach (['de', 'en'] as $lang) {
                MediaTracks::save($a, ['kind' => 'chapters', 'lang' => $lang, 'vtt' => (string) file_get_contents($dir . '/' . $base . '-kapitel.' . $lang . '.vtt'), 'status' => 'published', 'source' => 'upload']);
            }
            MediaTracks::setTranscript($a, 'de', $text, 'published', 'upload');
        } catch (\Throwable $e) {
            $log($base . ': ' . $e->getMessage());
        }
    }
    return $out;
}

/**
 * Datentabelle „Kurse (Demo)“ für die Datenliste mit sortierbaren Spalten (Zahl, Datum, Text). Liefert den Handle oder ''.
 */
function native_demo_table(callable $log): string
{
    $t = Demo::table([
        'handle' => NATIVE_DEMO_TABLE, 'name' => 'Kurse (Demo)', 'singular' => 'Kurs', 'icon' => 'calendar',
        'description' => 'Beispiel-Tabelle der Seite „Bausteine“ (Kit NativeCSS) – frei erfundene Kurse für die sortierbare Tabelle.',
        'fields' => [
            ['name' => 'name', 'label' => 'Kurs', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['name' => 'start', 'label' => 'Beginn', 'type' => 'date', 'in_list' => true, 'width' => 'half'],
            ['name' => 'plaetze', 'label' => 'Freie Plätze', 'type' => 'number', 'in_list' => true, 'width' => 'half'],
            ['name' => 'preis', 'label' => 'Preis (€)', 'type' => 'number', 'in_list' => true, 'width' => 'half'],
            ['name' => 'ort', 'label' => 'Ort', 'type' => 'text', 'in_list' => true, 'width' => 'half'],
        ],
        'settings' => ['title_field' => 'name', 'sort_field' => 'start', 'sort_dir' => 'asc', 'workflow' => 0],
    ], $log);
    if (!$t) return '';
    $rows = [
        ['Grundlagen Fotografie', '2027-01-14', 6, 120, 'Raum 2'],
        ['Webseiten selbst pflegen', '2026-12-03', 2, 89, 'Raum 10'],
        ['Barrierefreie Texte', '2027-02-09', 11, 65, 'Online'],
        ['Videos fürs Handy', '2026-12-17', 0, 145, 'Studio'],
        ['Newsletter, die gelesen werden', '2027-03-02', 8, 75, 'Raum 2'],
    ];
    foreach ($rows as [$name, $start, $free, $price, $place]) {
        [, $err] = \Core\Data\Entries::save($t, null, ['name' => $name, 'start' => $start, 'plaetze' => $free, 'preis' => $price, 'ort' => $place, 'status' => 'published']);
        if ($err) $log('Kurs ' . $name . ': ' . (is_array($err) ? implode(' ', $err) : (string) $err));
    }
    return (string) $t['handle'];
}

/** Seite „Bausteine“ (oberste Ebene, im Menü nach dem Showcase) */
function native_demo_page(array $m, string $table = ''): int
{
    $B = Demo::block(...);
    $sort = (int) app()->db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 1 FROM pages WHERE parent_id IS NULL");
    $pexels = 'Foto: Curtis Adams, Pexels (Pexels-Lizenz). „Vorher“ ist dasselbe Foto, für das Beispiel flau bearbeitet.';
    $ncss = 'Kurzfilm und Schleifen: Demo von NativeCSS (MIT), ohne Ton.';
    return Demo::page(['slug' => 'bausteine', 'title' => 'Bausteine', 'sort' => $sort, 'og_image' => $m['film_poster'],
        'meta_description' => 'Alle Funktionen des Kits „NativeCSS“: Einblend-Effekte, Morphing, Hintergrundvideo, Video-Player, Vorher/Nachher und mehr.'], [
        // Einstieg vollflächig mit Hintergrundvideo (Pause-Schalter, lädt erst in der Nähe, nie bei „Bewegung reduzieren“)
        $B('hero', ['variant' => 'fullbleed', 'eyebrow' => 'Kit NativeCSS (Demo)', 'title' => 'Bausteine aus *nativem* CSS',
            'text' => 'Kopfleiste mit <dialog>-Menü, Einblend-Effekte, Morphing, Video-Player – gebaut mit NativeCSS: Cascade Layers, Container Queries, native Elemente. Jedes Stück lädt nur, wo es gebraucht wird.',
            'button_label' => 'Zu den Effekten', 'button_link' => '#effekte', 'button2_label' => 'Zum Video', 'button2_link' => '#video',
            'image' => $m['waves_poster'], 'video' => $m['waves'], 'overlay' => 'gradient', 'bleed_height' => 'medium', 'bleed_motion' => 'none', 'ratio' => '16:9']),
        // Einblend-Effekte: alle vier (Abschnitt → „Einblend-Effekt“)
        $B('richtext', ['variant' => 'standard', 'eyebrow' => 'Einblend-Effekte', 'title' => 'Vier Arten, einen Abschnitt zu zeigen', 'intro' => '',
            'text' => '<p>Unter „Abschnitt“ wählen Sie für jeden Block einen Einblend-Effekt: <strong>Sanft einblenden</strong>, <strong>Von unten</strong>, <strong>Kreis</strong> oder <strong>Vorhang</strong>. Er spielt einmal, wenn der Abschnitt hereinscrollt. Wer im System „Bewegung reduzieren“ eingestellt hat, sieht alles sofort; im Design schaltet „Dezente Animationen“ alle Effekte ab. Der erste Block einer Seite blendet nie ein.</p>',
            'measure' => 'normal', 'meta' => '', 'dropcap' => false, 'toc' => false, 'reveal' => 'off', 'scrollspy' => 'off'], ['anchor' => 'effekte']),
        $B('cards', ['variant' => 'image', 'eyebrow' => 'Sanft einblenden', 'title' => 'Karten mit Rahmen und Schatten', 'intro' => 'Die ganze Karte ist klickbar, weitere Links darin bleiben bedienbar. Eine Karte ist hervorgehoben.',
            'size' => 'm', 'ratio' => '16:9', 'filter' => false, 'more_label' => '', 'more_link' => '', 'items' => [
                ['image' => $m['aurora_poster'], 'eyebrow' => 'Kopf', 'title' => 'Menü als Dialog', 'text' => 'Schmal ein Off-Canvas-<dialog>, breit eine Leiste – dasselbe Markup.', 'link_label' => 'Mehr', 'link' => '#video', 'featured' => false, 'badge' => '', 'category' => ''],
                ['image' => $m['shapes'], 'eyebrow' => 'Ebenen', 'title' => 'Cascade Layers', 'text' => 'NativeCSS, Grund-CSS von fluid und Brücke in fester Reihenfolge.', 'link_label' => '', 'link' => '', 'featured' => true, 'badge' => 'Neu', 'category' => ''],
                ['image' => $m['film_poster'], 'eyebrow' => 'Medien', 'title' => 'Eigener Video-Player', 'text' => 'Kapitel, Untertitel, Tastatur – auf Basis von <video controls>.', 'link_label' => 'Ansehen', 'link' => '#video', 'featured' => false, 'badge' => '', 'category' => ''],
            ]], ['reveal' => 'fade']),
        $B('stats', ['variant' => 'row', 'eyebrow' => 'Von unten', 'title' => 'Zahlen, die hochzählen', 'intro' => 'Ganze Zahlen zählen beim Hereinscrollen hoch – Jahreszahlen und Werte wie „24/7“ nicht. Die echte Zahl steht immer im Text.', 'items' => [
            ['value' => '120+', 'label' => 'Beispielprojekte', 'text' => 'frei erfunden'],
            ['value' => '12.500', 'label' => 'Besuche im Monat', 'text' => 'Beispielwert'],
            ['value' => '98 %', 'label' => 'zufriedene Auftraggeber', 'text' => 'Beispielwert'],
            ['value' => '24/7', 'label' => 'erreichbar', 'text' => 'zählt nicht'],
        ]], ['reveal' => 'rise', 'background' => 'muted']),
        $B('compare', ['eyebrow' => 'Vorhang', 'title' => 'Vorher/Nachher mit Regler', 'intro' => 'Den Griff ziehen – oder mit Tab hinspringen und die Pfeiltasten nutzen. Der Abschnitt öffnet sich beim Hereinscrollen wie ein Vorhang.',
            'image_before' => $m['before'], 'image_after' => $m['after'], 'before_label' => 'Vorher', 'after_label' => 'Nachher', 'ratio' => '3:2', 'caption' => $pexels], ['reveal' => 'curtain']),
        $B('quote', ['variant' => 'grid', 'eyebrow' => 'Kreis', 'title' => 'Kundenstimmen', 'items' => [
            ['text' => 'Beispielzitat: Wir ändern Texte in Minuten – genau dort, wo Besucher sie lesen.', 'name' => 'Erika Musterfrau', 'role' => 'Online-Redaktion (fiktiv)', 'image' => null],
            ['text' => 'Beispielzitat: Das Menü fühlt sich auf dem Handy an wie eine App.', 'name' => 'Max Beispiel', 'role' => 'Agentur Muster (fiktiv)', 'image' => null],
            ['text' => 'Beispielzitat: Schnell, ruhig und gut lesbar – auch mit großer Schrift.', 'name' => 'Alex Test', 'role' => 'Verein Beispielstadt (fiktiv)', 'image' => null],
        ]], ['reveal' => 'circle', 'background' => 'tint']),
        // Morphing beim Scrollen: Text + Bild und Video
        $B('media_text', ['variant' => 'morph', 'eyebrow' => 'Text + Bild', 'title' => 'Morphing beim Scrollen',
            'text' => '<p>Das Bild startet groß und rückt beim Scrollen zur Seite – der Text erscheint daneben. Nur CSS (Scroll-Animationen), ohne Skript. Mit „Bewegung reduzieren“ stehen Bild und Text gleich nebeneinander.</p>',
            'list' => "Darstellung „Morphing beim Scrollen“\nNur in diesem Kit", 'image' => $m['after'], 'ratio' => '3:2', 'split' => 'even',
            'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '']),
        $B('video', ['variant' => 'morph', 'eyebrow' => 'Video', 'title' => 'Video mit Morphing', 'intro' => '',
            'video_url' => '', 'video_file' => $m['aurora'], 'poster' => $m['aurora_poster'], 'ratio' => '16-9', 'caption' => $ncss,
            'text' => '<p>Eine kurze, stumme Schleife wie ein Hintergrundvideo – mit Pause-Schalter, lädt erst in der Nähe und spielt nie bei „Bewegung reduzieren“ oder „Daten sparen“.</p>']),
        // Laufband, Scrollytelling, Zeitleiste
        $B('logos', ['variant' => 'marquee', 'eyebrow' => '', 'title' => 'Laufband mit Pause-Schaltfläche (fiktive Namen)', 'items' => [
            ['name' => 'Nordlicht Werkstatt', 'link' => '', 'image' => null], ['name' => 'Kanzlei Aster', 'link' => '', 'image' => null],
            ['name' => 'Praxis am Park', 'link' => '', 'image' => null], ['name' => 'Verein Beispielstadt', 'link' => '', 'image' => null],
            ['name' => 'Café Musterecke', 'link' => '', 'image' => null], ['name' => 'Museum Muster', 'link' => '', 'image' => null],
        ]], ['spaceTop' => 'none']),
        $B('scrolly', ['variant' => 'left', 'eyebrow' => 'Scrollytelling', 'title' => 'Ein Bild bleibt, die Geschichte läuft', 'intro' => 'Die Abschnitte ziehen vorbei, das Bild wechselt zum aktiven Abschnitt. Ohne JavaScript stehen alle untereinander.', 'ratio' => '16:9', 'items' => [
            ['eyebrow' => 'Morgen', 'title' => 'Hügel im ersten Licht', 'text' => 'Der Tag beginnt ruhig: gestaffelte Hügel, Wolken ziehen nach Osten.', 'image' => $m['film_poster']],
            ['eyebrow' => 'Nacht', 'title' => 'Polarlicht', 'text' => 'Grün und violett schimmert es über dem dunklen Himmel.', 'image' => $m['aurora_poster']],
            ['eyebrow' => 'Abend', 'title' => 'Wellen', 'text' => 'Weiche Wellen in Blau und Türkis – die Dünung kommt zur Ruhe.', 'image' => $m['waves_poster']],
            ['eyebrow' => 'Formen', 'title' => 'Farbflächen', 'text' => 'Zum Schluss nur noch Farbe: ineinanderfließende Formen.', 'image' => $m['shapes']],
        ]], ['background' => 'muted']),
        $B('steps', ['variant' => 'timeline', 'eyebrow' => 'Ablauf', 'title' => 'Zeitleiste', 'intro' => 'Die Linie wächst beim Scrollen mit (Option „Animation“).', 'marker' => 'filled', 'colors' => 'alternate', 'motion' => 'draw', 'items' => [
            ['meta' => 'Woche 1', 'title' => 'Kennenlernen', 'text' => 'Ziele, Rahmen und Ansprechpersonen klären.', 'icon' => ''],
            ['meta' => 'Woche 2–3', 'title' => 'Konzept', 'text' => 'Vorschlag mit Aufwand und Terminen.', 'icon' => ''],
            ['meta' => 'Woche 4–8', 'title' => 'Umsetzung', 'text' => 'Kurze, regelmäßige Abstimmungen.', 'icon' => ''],
            ['meta' => 'Danach', 'title' => 'Pflege', 'text' => 'Sie pflegen selbst – wir helfen bei Bedarf.', 'icon' => 'sparkle'],
        ]]),
        // Video-Player mit Untertiteln und Kapiteln
        $B('video', ['variant' => 'wide', 'eyebrow' => 'Video-Player', 'title' => 'Eigenes Video mit Kapiteln und Untertiteln', 'intro' => '',
            'video_url' => '', 'video_file' => $m['film'], 'poster' => $m['film_poster'], 'ratio' => '16-9', 'text' => '',
            'caption' => $ncss . ' Untertitel (deutsch, englisch) und Kapitel pflegen Sie in der Mediathek an der Datei.'], ['anchor' => 'video', 'background' => 'muted']),
        // Audio: Playlist mit Cover, Kapiteln und Transkript aus der Mediathek
        $B('video', ['variant' => 'audio', 'eyebrow' => 'Audio-Player', 'title' => 'Playlist mit Kapiteln', 'intro' => 'Zwei Klangproben ohne Sprache: Kapitel und Transkript kommen je Datei aus der Mediathek.',
            'video_url' => '', 'video_file' => null, 'poster' => $m['waves_poster'], 'ratio' => '16-9', 'text' => '', 'caption' => 'Synthetische Klangproben, für die Demo erzeugt.',
            'audio' => [
                ['file' => $m['chord'], 'title' => 'Akkord', 'artist' => 'Klangprobe (Demo)'],
                ['file' => $m['surf'], 'title' => 'Brandung', 'artist' => 'Klangprobe (Demo)'],
            ]], ['anchor' => 'audio']),
        // Karten: Hervorheben und Filter nach Kategorie
        $B('cards', ['variant' => 'image', 'eyebrow' => 'Filter', 'title' => 'Karten nach Kategorie filtern', 'intro' => 'Die Schaltflächen sind Optionsfelder: gefiltert wird per CSS, die Trefferzahl sagt ein kleines Skript an.',
            'size' => 's', 'ratio' => '3:2', 'filter' => true, 'more_label' => '', 'more_link' => '', 'items' => [
                ['image' => $m['film_poster'], 'eyebrow' => 'Medien', 'title' => 'Video-Player', 'text' => 'Kapitel und Untertitel aus der Mediathek.', 'link_label' => '', 'link' => '#video', 'featured' => true, 'badge' => '', 'category' => 'Medien'],
                ['image' => $m['waves_poster'], 'eyebrow' => 'Medien', 'title' => 'Audio-Player', 'text' => 'Playlist mit Cover und Kapiteln.', 'link_label' => '', 'link' => '#audio', 'featured' => false, 'badge' => '', 'category' => 'Medien'],
                ['image' => $m['aurora_poster'], 'eyebrow' => 'Bewegung', 'title' => 'Einblend-Effekte', 'text' => 'Vier Arten je Abschnitt.', 'link_label' => '', 'link' => '#effekte', 'featured' => false, 'badge' => '', 'category' => 'Bewegung'],
                ['image' => $m['shapes'], 'eyebrow' => 'Bewegung', 'title' => 'Morphing', 'text' => 'Bild und Video rücken beim Scrollen zur Seite.', 'link_label' => '', 'link' => '', 'featured' => false, 'badge' => '', 'category' => 'Bewegung'],
                ['image' => $m['after'], 'eyebrow' => 'Inhalt', 'title' => 'Preisliste', 'text' => 'Name, Führungspunkte, Preis.', 'link_label' => '', 'link' => '#preise', 'featured' => false, 'badge' => '', 'category' => 'Inhalt'],
                ['image' => $m['before'], 'eyebrow' => 'Inhalt', 'title' => 'Sortierbare Tabelle', 'text' => 'Spaltenkopf anklicken – Zahl, Datum oder Text.', 'link_label' => '', 'link' => '#tabelle', 'featured' => true, 'badge' => 'Neu', 'category' => 'Inhalt, Medien'],
            ]]),
        // Preistabelle
        $B('pricing', ['variant' => 'cards', 'eyebrow' => 'Pakete', 'title' => 'Preistabelle', 'intro' => 'Gleich hohe Karten, Zeilen bündig – eine Zeile mit „–“ gilt als nicht enthalten.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'rows' => [], 'items' => [
            ['name' => 'Start', 'badge' => '', 'price' => '490 €', 'period' => 'einmalig', 'text' => 'Für kleine Auftritte.', 'features' => "Bis 5 Seiten\nKit NativeCSS\n– Mehrsprachigkeit", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Plus', 'badge' => 'Empfohlen', 'price' => '1.290 €', 'period' => 'einmalig', 'text' => 'Für Betriebe und Vereine.', 'features' => "Bis 20 Seiten\nKit NativeCSS\nMehrsprachigkeit", 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Pflege', 'badge' => '', 'price' => '49 €', 'period' => 'pro Monat', 'text' => 'Wir halten alles aktuell.', 'features' => "Updates\nSicherung\nHilfe per E-Mail", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
        ]]),
        // Preisliste (Speisekarte)
        $B('pricing', ['variant' => 'list', 'eyebrow' => 'Preisliste', 'title' => 'Kurse und Preise', 'intro' => 'Name, Führungspunkte, Preis – und eine Zeile Beschreibung.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'rows' => [], 'items' => [], 'entries' => [
            ['name' => 'Schnupperstunde', 'price' => '15 €', 'text' => 'Einmal reinschauen, ohne Anmeldung.'],
            ['name' => 'Kurs, vier Abende', 'price' => '89 €', 'text' => 'Mit Unterlagen zum Mitnehmen.'],
            ['name' => 'Einzelberatung (60 Minuten)', 'price' => '60 €', 'text' => ''],
            ['name' => 'Gruppe bis acht Personen', 'price' => 'ab 240 €', 'text' => 'Termin nach Absprache.'],
        ]], ['anchor' => 'preise', 'background' => 'muted']),
        ...($table !== '' ? [$B('data_list', ['eyebrow' => 'Datenliste', 'title' => 'Kurse – Spalten sortierbar', 'intro' => 'Einen Spaltenkopf anklicken oder mit der Tastatur auslösen: sortiert nach Zahl, Datum oder Text. Das Skript kommt aus dem Kern.',
            'table' => $table, 'fields' => ["$table._title", "$table.start", "$table.plaetze", "$table.preis", "$table.ort"], 'layout' => 'table', 'table_sort' => true,
            'limit' => 20, 'sort_field' => "$table.start", 'sort_dir' => 'asc', 'link_detail' => false], ['anchor' => 'tabelle'])] : []),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'FAQ', 'title' => 'Fragen zum Kit', 'intro' => '', 'items' => [
            ['q' => 'Brauche ich JavaScript?', 'a' => '<p>Menü, Aufklappen, Morphing und Einblenden funktionieren mit nativem HTML und CSS. Kleine Skripte ergänzen nur – etwa das Hochzählen oder den Video-Player – und laden nur auf Seiten, die sie brauchen.</p>'],
            ['q' => 'Kann ich Farben und Schriften ändern?', 'a' => '<p>Ja – unter Verwaltung → Design, mit den Vorlagen „NativeCSS“ und „NativeCSS · Graphit“ und allen Vorlagen von Fluid.</p>'],
            ['q' => 'Sind die Inhalte echt?', 'a' => '<p>Nein. Namen, Zahlen und Zitate sind frei erfunden; die Medien stammen aus der Demo von NativeCSS bzw. von Pexels (siehe Bildunterschriften).</p>'],
        ]], ['background' => 'muted']),
        $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Gefällt Ihnen NativeCSS?', 'text' => 'Alle Bausteine lassen sich frei kombinieren – Farben und Schriften unter Verwaltung → Design.',
            'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => 'Zum Showcase', 'button2_link' => '/showcase']),
    ]);
}
