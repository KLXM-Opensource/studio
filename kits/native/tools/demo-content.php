<?php
/**
 * Seite „Bausteine“ des Kits „NativeCSS“: jede Funktion dieses Kits mit frei erfundenen Inhalten – Einblend-Effekte (alle vier),
 * Hintergrundvideo im Einstieg, Morphing (Text + Bild und Video), Kennzahlen hochzählen, Logos-Laufband, Scrollytelling,
 * Zeitleiste, Kundenstimmen, Preistabelle, Vorher/Nachher, Video-Player mit Untertiteln und Kapiteln.
 *
 * Neue Websites: seed.php → 'after'. Bestehende Websites: CMS_SITE=… php kits/native/tools/demo.php [--force|--remove]
 * Medien (kits/native/demo/media, Schlagwort „native-demo“): Kurzfilm, Schleifen und Vorschaubilder aus der Demo von NativeCSS
 * (für NativeCSS erzeugt, MIT, © Thomas Skerbis / KLXM Crossmedia), Foto Wohnzimmer von Curtis Adams (Pexels-Lizenz,
 * https://www.pexels.com/photo/empty-living-room-6835103/; „Vorher“ dasselbe Foto, flau bearbeitet). Videos ohne Ton.
 */
declare(strict_types=1);

use Core\Kit\Demo;
use Core\Media;
use Core\MediaTracks;

const NATIVE_DEMO_TAG = 'native-demo';

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
    native_demo_page($m);
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
    return $out;
}

/** Seite „Bausteine“ (oberste Ebene, im Menü nach dem Showcase) */
function native_demo_page(array $m): int
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
            'before' => $m['before'], 'after' => $m['after'], 'label_before' => 'Vorher', 'label_after' => 'Nachher', 'ratio' => '3:2', 'caption' => $pexels], ['reveal' => 'curtain']),
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
        // Preistabelle
        $B('pricing', ['variant' => 'cards', 'eyebrow' => 'Pakete', 'title' => 'Preistabelle', 'intro' => 'Gleich hohe Karten, Zeilen bündig – eine Zeile mit „–“ gilt als nicht enthalten.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'rows' => [], 'items' => [
            ['name' => 'Start', 'badge' => '', 'price' => '490 €', 'period' => 'einmalig', 'text' => 'Für kleine Auftritte.', 'features' => "Bis 5 Seiten\nKit NativeCSS\n– Mehrsprachigkeit", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Plus', 'badge' => 'Empfohlen', 'price' => '1.290 €', 'period' => 'einmalig', 'text' => 'Für Betriebe und Vereine.', 'features' => "Bis 20 Seiten\nKit NativeCSS\nMehrsprachigkeit", 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
            ['name' => 'Pflege', 'badge' => '', 'price' => '49 €', 'period' => 'pro Monat', 'text' => 'Wir halten alles aktuell.', 'features' => "Updates\nSicherung\nHilfe per E-Mail", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
        ]]),
        $B('faq', ['variant' => 'stacked', 'eyebrow' => 'FAQ', 'title' => 'Fragen zum Kit', 'intro' => '', 'items' => [
            ['q' => 'Brauche ich JavaScript?', 'a' => '<p>Menü, Aufklappen, Morphing und Einblenden funktionieren mit nativem HTML und CSS. Kleine Skripte ergänzen nur – etwa das Hochzählen oder den Video-Player – und laden nur auf Seiten, die sie brauchen.</p>'],
            ['q' => 'Kann ich Farben und Schriften ändern?', 'a' => '<p>Ja – unter Verwaltung → Design, mit den Vorlagen „NativeCSS“ und „NativeCSS · Graphit“ und allen Vorlagen von Fluid.</p>'],
            ['q' => 'Sind die Inhalte echt?', 'a' => '<p>Nein. Namen, Zahlen und Zitate sind frei erfunden; die Medien stammen aus der Demo von NativeCSS bzw. von Pexels (siehe Bildunterschriften).</p>'],
        ]], ['background' => 'muted']),
        $B('cta', ['variant' => 'band', 'eyebrow' => 'Nächster Schritt', 'title' => 'Gefällt Ihnen NativeCSS?', 'text' => 'Alle Bausteine lassen sich frei kombinieren – Farben und Schriften unter Verwaltung → Design.',
            'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt', 'button2_label' => 'Zum Showcase', 'button2_link' => '/showcase']),
    ]);
}
