<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;
use Core\Data\Calendar;
use Core\Data\DataForms;
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Maps;
use Core\Media;
use Core\Search\Search;

/**
 * Bausteine für die Einstiegs-Varianten der Kits (Block „hero“): Suche, Hintergrundvideo, Kennzahlen, Termine,
 * Formular, Karte, Vorher/Nachher, Laufzeile. Markup und Aussehen bestimmt jedes Kit selbst – hier liegen nur die Teile,
 * die in allen Kits gleich funktionieren müssen (Daten, Kern-Komponenten, Barrierefreiheit, Feld-Voreinstellungen).
 *
 *   'fields' => [..., ...Hero::fields('search', ['search']), ...Hero::fields('video', ['video'])]
 *       Feld-Voreinstellung, nur bei den genannten Varianten in der Seitenleiste sichtbar (Core\Fields 'variants')
 *   'uses' => ['figures' => ['dials'], 'dates' => ['upcoming'], 'form' => ['data_form']]
 *       lädt die Kern-Stylesheets der eingebetteten Teile nur auf Seiten mit dieser Variante (Core\Theme::withUses)
 *
 *   Hero::search($d)               Suchfeld (Core\Search, Vorschläge beim Tippen); '' ohne Website-Suche
 *   Hero::chips($d)                Vorschläge unter dem Suchfeld: [['label', 'href']]
 *   Hero::video($d)                ['media' => Standbild + <video>, 'toggle' => Pause-Schaltfläche] oder null
 *   Hero::compare($d, $sizes)      Vorher/Nachher mit Schieberegler (input range) oder ''
 *   Hero::marquee($text, $class)   Laufzeile mit Pause-Schaltfläche (läuft nur mit Skript, nie bei „Bewegung reduzieren“)
 *   Hero::dials($b, $d)            Kern-Kennzahlen (app/Blocks/dials.php) ohne eigenen Container
 *   Hero::dates($d)                nächste Termine (Kalender-Tabelle) bzw. neueste Einträge (andere Tabelle)
 *   Hero::form($b, $d)             öffentliches Formular einer Datentabelle (Core\Data\DataForms)
 *   Hero::map($d)                  Karte mit dem Standort aus „Website“ (Core\Maps, ohne Cookies)
 *   Hero::lines($s), Hero::pairs($s)  Zeilen bzw. „Bezeichnung: Wert“-Paare aus einem Textfeld
 *
 * Skript: public/assets/js/hero.mjs (≈ 1 KB, nur mit Video, Vorher/Nachher oder Laufzeile). Keine Inline-Styles/-Skripte.
 */
final class Hero
{
    private static bool $script = false;

    // ------------------------------------------------------------------ Feld-Voreinstellungen

    /**
     * Feld-Voreinstellungen je Baustein; $variants = Varianten, bei denen die Felder erscheinen.
     * Bausteine: search, video, figures, dates, form, map, compare, gallery, quote, cards, marquee
     */
    public static function fields(string $part, array $variants): array
    {
        $list = match ($part) {
            'search' => [
                ['name' => 'search_label', 'label' => 'Beschriftung über dem Suchfeld (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half',
                    'placeholder' => 'z. B. Was suchen Sie?', 'help' => 'Leer = nur für Screenreader „Website durchsuchen“.'],
                ['name' => 'search_placeholder', 'label' => 'Platzhalter im Suchfeld (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half',
                    'placeholder' => 'z. B. Leistung, Formular, Thema …'],
                ['name' => 'search_chips', 'label' => 'Vorschläge unter dem Suchfeld (einer pro Zeile)', 'type' => 'textarea', 'rows' => 3,
                    'help' => 'Beliebte Suchbegriffe führen zur Ergebnisseite. „Beschriftung | Link“ verlinkt direkt, z. B. „Öffnungszeiten | /kontakt“. Die Suche muss eingeschaltet sein (Grundeinstellungen → Funktionen).'],
            ],
            'video' => [
                ['name' => 'video', 'label' => 'Hintergrundvideo (MP4, ohne Ton)', 'type' => 'file', 'width' => 'half',
                    'help' => 'Eigene Datei aus der Mediathek (keine Einwilligung nötig), kurz und klein (< 8 MB). Läuft stumm in Schleife, nur ohne „Bewegung reduzieren“ und mit Pause-Schaltfläche. Das Bild dient als Standbild.'],
                ['name' => 'overlay', 'label' => 'Abdunkelung über Bild und Video', 'type' => 'select', 'required' => true, 'default' => 'strong', 'width' => 'half',
                    'options' => ['strong' => 'Stark (empfohlen)', 'medium' => 'Mittel', 'gradient' => 'Verlauf von unten'],
                    'help' => 'Hält die Schrift lesbar (Kontrast), auch wenn das Video helle Stellen hat.'],
            ],
            'figures' => [
                ['name' => 'items', 'label' => 'Kennzahlen (3–4 empfohlen)', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 4,
                    'help' => 'Nur belegbare Zahlen. Füllstand leer = aus Prozentwert berechnet, sonst neutrale Skala.', 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 92 % oder 24'],
                    ['name' => 'unit', 'label' => 'Einheit (optional)', 'type' => 'text', 'max' => 8, 'width' => 'half', 'placeholder' => 'z. B. h, km'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Zusatz (optional)', 'type' => 'text', 'max' => 90, 'width' => 'half', 'placeholder' => 'z. B. Quelle, Zeitraum'],
                    ['name' => 'level', 'label' => 'Füllstand in % (optional)', 'type' => 'number', 'min' => 0, 'max' => 100, 'width' => 'half'],
                    ['name' => 'max', 'label' => 'Höchstwert der Skala (optional)', 'type' => 'number', 'width' => 'half'],
                ]],
                ['name' => 'figures_style', 'label' => 'Skala der Kennzahlen', 'type' => 'select', 'required' => true, 'default' => 'arc', 'width' => 'half',
                    'options' => ['arc' => 'Bogen mit Skalenstrichen', 'ticks' => 'Nur Skalenstriche', 'minimal' => 'Schlichter Ring']],
            ],
            'dates' => [
                ['name' => 'dates_table', 'label' => 'Termine oder Neuigkeiten aus Tabelle', 'type' => 'datatable', 'width' => 'half',
                    'help' => 'Kalender-Tabelle → die nächsten Termine ab heute (Wiederholungen einzeln). Andere Tabelle → die neuesten veröffentlichten Einträge.'],
                ['name' => 'dates_limit', 'label' => 'Anzahl', 'type' => 'select', 'required' => true, 'default' => '3', 'width' => 'half',
                    'options' => ['2' => '2', '3' => '3', '4' => '4']],
                ['name' => 'dates_title', 'label' => 'Überschrift der Liste (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Als Nächstes'],
                ['name' => 'dates_more_label', 'label' => 'Link unter der Liste – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'z. B. Alle Termine'],
                ['name' => 'dates_more_link', 'label' => 'Link unter der Liste', 'type' => 'link'],
            ],
            'form' => [
                ['name' => 'form_table', 'label' => 'Formular (Datentabelle)', 'type' => 'datatable', 'inbox' => true, 'width' => 'half',
                    'help' => 'Tabelle mit eingeschaltetem „Öffentliches Formular“, z. B. Rückrufwunsch – kurz halten (2–4 Felder).'],
                ['name' => 'form_title', 'label' => 'Überschrift über dem Formular', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. Rückruf anfordern'],
                ['name' => 'form_submit', 'label' => 'Beschriftung des Buttons (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'form_note', 'label' => 'Hinweis unter dem Formular (optional)', 'type' => 'text', 'max' => 140, 'width' => 'half', 'placeholder' => 'z. B. Wir melden uns werktags innerhalb von 24 Stunden.'],
            ],
            'map' => [
                ['name' => 'map_zoom', 'label' => 'Kartenausschnitt', 'type' => 'select', 'required' => true, 'default' => '15', 'width' => 'half',
                    'options' => ['13' => 'Stadtteil', '15' => 'Straße (Standard)', '17' => 'Gebäude']],
                ['name' => 'map_hours', 'label' => 'Öffnungszeiten auf der Standortkarte zeigen (falls eingetragen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'map_note', 'label' => 'Hinweis zur Anfahrt (optional)', 'type' => 'text', 'max' => 140, 'placeholder' => 'z. B. Parkplätze im Hof, barrierefreier Eingang links'],
            ],
            'compare' => [
                ['name' => 'image_before', 'label' => 'Bild „Vorher“', 'type' => 'media', 'width' => 'half',
                    'help' => 'Gleicher Ausschnitt und gleiches Format wie „Nachher“ (Alt-Text in der Mediathek).'],
                ['name' => 'image_after', 'label' => 'Bild „Nachher“', 'type' => 'media', 'width' => 'half'],
                ['name' => 'before_label', 'label' => 'Beschriftung „Vorher“', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'Vorher'],
                ['name' => 'after_label', 'label' => 'Beschriftung „Nachher“', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'Nachher'],
            ],
            'gallery' => [
                ['name' => 'gallery', 'label' => 'Weitere Bilder (2–4)', 'type' => 'repeater', 'item_label' => 'Bild', 'title_field' => 'caption', 'max_items' => 4,
                    'help' => 'Zusammen mit „Bild“ entsteht eine Collage. Alt-Texte in der Mediathek pflegen.', 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'required' => true, 'width' => 'half'],
                    ['name' => 'caption', 'label' => 'Kurze Beschriftung (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                ]],
            ],
            'quote' => [
                ['name' => 'quote', 'label' => 'Zitat', 'type' => 'textarea', 'rows' => 3, 'max' => 320,
                    'help' => 'Nur echte, freigegebene Stimmen. Statt Porträt erscheinen die Initialen.'],
                ['name' => 'quote_name', 'label' => 'Name', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ['name' => 'quote_role', 'label' => 'Zusatz (z. B. Ort, Funktion)', 'type' => 'text', 'max' => 80, 'width' => 'half'],
                ['name' => 'rating', 'label' => 'Bewertung (optional)', 'type' => 'select', 'default' => '', 'width' => 'half',
                    'options' => ['' => '– keine –', '5' => '5 von 5', '4.5' => '4,5 von 5', '4' => '4 von 5']],
                ['name' => 'rating_note', 'label' => 'Quelle der Bewertung', 'type' => 'text', 'max' => 90, 'width' => 'half', 'placeholder' => 'z. B. 48 Bewertungen, Stand 2026'],
            ],
            'cards' => [
                ['name' => 'cards', 'label' => 'Karten (2–3)', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 3, 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'max' => 120],
                    ['name' => 'link_label', 'label' => 'Link – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'width' => 'half'],
                ]],
            ],
            'marquee' => [
                ['name' => 'marquee', 'label' => 'Laufzeile (optional)', 'type' => 'text', 'max' => 160,
                    'help' => 'Begriffe mit „·“ trennen, z. B. „Beratung · Planung · Umsetzung“. Läuft langsam, hält per Schaltfläche, Maus oder Tastatur und steht bei „Bewegung reduzieren“ still.'],
            ],
            default => [],
        };
        return array_map(fn(array $f) => $f + ['variants' => $variants], $list);
    }

    // ------------------------------------------------------------------ Helfer

    /** Nicht leere Zeilen eines Textfelds */
    public static function lines(mixed $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $s) ?: []), fn($l) => $l !== ''));
    }

    /** „Bezeichnung: Wert“ je Zeile → [['label', 'value']] (ohne Doppelpunkt: nur value) */
    public static function pairs(mixed $s): array
    {
        return array_map(function (string $l): array {
            [$a, $b] = str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : ['', $l];
            return ['label' => $a, 'value' => $b];
        }, self::lines($s));
    }

    /** Einmal je Seite: <script type="module"> für Video, Vorher/Nachher, Laufzeile (nicht im Bearbeitungsmodus) */
    public static function script(): string
    {
        if (self::$script || is_editing()) return '';
        self::$script = true;
        return '<script type="module" src="' . e(asset('js/hero.mjs')) . '"></script>';
    }

    /** SVG „Pause“ und „Abspielen“ für Schaltflächen (CSS zeigt je nach .is-paused eines davon) */
    private static function pauseIcons(): string
    {
        return '<svg class="hx-ico hx-ico--pause" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>'
            . '<svg class="hx-ico hx-ico--play" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M8 5.5v13l11-6.5z"/></svg>';
    }

    // ------------------------------------------------------------------ Suche

    /** Suchfeld (Search::form Variante „hero“) – '' wenn die Website-Suche ausgeschaltet ist */
    public static function search(array $d): string
    {
        return Search::form('hero', '', ['label' => (string) ($d['search_label'] ?? ''), 'placeholder' => (string) ($d['search_placeholder'] ?? '')]);
    }

    /** Vorschläge: „Begriff“ → Ergebnisseite, „Beschriftung | Link“ → Link. Ohne Suche nur die direkten Links. */
    public static function chips(array $d): array
    {
        $out = [];
        foreach (self::lines($d['search_chips'] ?? '') as $l) {
            if (str_contains($l, '|')) {
                [$label, $link] = array_map('trim', explode('|', $l, 2));
                if ($label !== '' && $link !== '') $out[] = ['label' => $label, 'href' => link_href($link)];
            } elseif (Search::enabled()) {
                $out[] = ['label' => $l, 'href' => Search::url($l)];
            }
        }
        return array_slice($out, 0, 8);
    }

    // ------------------------------------------------------------------ Video

    /**
     * Hintergrundvideo (eigene Datei): Standbild (Feld „image“, sonst Poster aus der Mediathek) + <video muted loop playsinline>.
     * ['media' => …, 'toggle' => …] – das Kit setzt beides in einen Rahmen mit data-hero-video-box. null ohne Video.
     * $o: sizes (für das Standbild), class (Präfix der Klassen, Standard „hx-video“)
     */
    public static function video(array $d, array $o = []): ?array
    {
        $vid = !empty($d['video']) ? Media::find((int) $d['video']) : null;
        if (!$vid || !str_starts_with((string) $vid['mime'], 'video/')) return null;
        $c = (string) ($o['class'] ?? 'hx-video');
        // Standbild: Feld „image“ > gewähltes Poster (Erweiterung) > automatisches Vorschaubild (Core\VideoThumbs, ohne ID)
        $poster = !empty($d['image']) ? Media::find((int) $d['image']) : Media::posterFor($vid);
        $media = ($poster ? Media::pictureOf($poster, (string) ($o['sizes'] ?? '100vw'), ['eager' => true, 'alt' => '', 'class' => $c . '__still']) : '')
            . '<video class="' . e($c) . '__el" data-hero-video muted loop playsinline preload="none" disablepictureinpicture aria-hidden="true" tabindex="-1"'
            . ($poster ? ' poster="' . e(Media::url($poster, 1600)) . '"' : '') . '>'
            . '<source src="' . e(Media::url($vid)) . '" type="' . e((string) $vid['mime']) . '"></video>';
        $toggle = '<button type="button" class="' . e($c) . '__toggle hx-toggle" data-hero-video-toggle hidden>' . self::pauseIcons()
            . '<span data-l-pause="' . e(lt('Hintergrundvideo anhalten')) . '" data-l-play="' . e(lt('Hintergrundvideo abspielen')) . '">'
            . e(lt('Hintergrundvideo anhalten')) . '</span></button>' . self::script();
        return ['media' => $media, 'toggle' => $toggle];
    }

    // ------------------------------------------------------------------ Vorher/Nachher

    /**
     * Vergleich zweier Bilder: ohne Skript nebeneinander (beschriftet), mit Skript übereinander mit Schieberegler.
     * Der Regler ist ein natives <input type="range"> (Pfeiltasten, Pos1/Ende, Bildlauf-Gesten) mit sichtbarem Fokus im Kit.
     */
    public static function compare(array $d, string $sizes = '100vw', string $ratio = '', string $class = 'hx-compare'): string
    {
        $a = (int) ($d['image_before'] ?? 0);
        $bId = (int) ($d['image_after'] ?? 0) ?: (int) ($d['image'] ?? 0);
        if (!$a || !$bId) {
            return is_editing() ? '<p class="' . e($class) . '__empty">' . e(__('Bitte „Vorher“ und „Nachher“ wählen.')) . '</p>' : '';
        }
        $la = trim((string) ($d['before_label'] ?? '')) ?: lt('Vorher');
        $lb = trim((string) ($d['after_label'] ?? '')) ?: lt('Nachher');
        $pic = fn(int $id) => $ratio !== '' ? Media::picture($id, $sizes, ['ratio' => $ratio, 'eager' => true]) : img($id, $sizes, ['eager' => true]);
        $id = 'hxc-' . substr(md5($a . '-' . $bId), 0, 6);
        return '<div class="' . e($class) . '" data-hero-compare>'
            . '<figure class="' . e($class) . '__pane ' . e($class) . '__pane--before">' . $pic($a) . '<figcaption class="' . e($class) . '__tag">' . e($la) . '</figcaption></figure>'
            . '<figure class="' . e($class) . '__pane ' . e($class) . '__pane--after">' . $pic($bId) . '<figcaption class="' . e($class) . '__tag">' . e($lb) . '</figcaption></figure>'
            . '<input class="' . e($class) . '__range" id="' . $id . '" type="range" min="0" max="100" step="1" value="50" hidden'
            . ' aria-label="' . e(lt('Vergleich: Anteil „{a}“ in Prozent', ['a' => $la])) . '">'
            . '</div>' . self::script();
    }

    // ------------------------------------------------------------------ Laufzeile

    /** Laufzeile: Begriffe mit „·“ getrennt; zweimal gesetzt für die Endlosschleife (Kopie aria-hidden) */
    public static function marquee(string $text, string $class = 'hx-marquee'): string
    {
        $items = array_values(array_filter(array_map('trim', preg_split('~\s*[·•|]\s*~u', $text) ?: []), fn($x) => $x !== ''));
        if (!$items) return '';
        $row = fn(bool $copy) => '<ul class="' . e($class) . '__row" role="list"' . ($copy ? ' aria-hidden="true"' : '') . '>'
            . implode('', array_map(fn($x) => '<li>' . emphasis($x) . '</li>', $items)) . '</ul>';
        return '<div class="' . e($class) . '" data-hero-marquee><div class="' . e($class) . '__track">' . $row(false) . $row(true) . '</div>'
            . '<button type="button" class="' . e($class) . '__toggle hx-toggle" data-hero-marquee-toggle hidden>' . self::pauseIcons()
            . '<span data-l-pause="' . e(lt('Laufzeile anhalten')) . '" data-l-play="' . e(lt('Laufzeile abspielen')) . '">' . e(lt('Laufzeile anhalten')) . '</span></button>'
            . '</div>' . self::script();
    }

    // ------------------------------------------------------------------ Kennzahlen (Kern-Block „dials“)

    /** Kennzahlen des Einstiegs (Feld „items“) als Kern-Rundinstrumente – Stylesheet über 'uses' => [… => ['dials']] */
    public static function dials(Block $b, array $d, string $size = 'm'): string
    {
        $items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => is_array($i) && trim((string) ($i['value'] ?? '')) !== ''));
        if (!$items && !is_editing()) return '';
        $theme = app()->theme;
        $def = $theme->block('dials');
        if (!$def) return '';
        // gleiche Block-ID und Feldname „items“: Inline-Bearbeitung der Werte schreibt in den Einstieg
        $data = ['items' => $items, 'size' => $size, 'style' => (string) ($d['figures_style'] ?? 'arc'), 'animate' => true, 'title' => '', '_bare' => true];
        $blk = new Block($b->id, 'dials', $data, $b->tunes, $def);
        return \Core\Theme::capture(ROOT . '/app/Blocks/dials.php', ['b' => $blk, 'd' => $data]);
    }

    // ------------------------------------------------------------------ Termine / Neuigkeiten

    /**
     * Einträge für die Liste im Einstieg: Kalender-Tabelle → nächste Vorkommen, sonst neueste veröffentlichte Einträge.
     * @return array{items: list<array{title: string, href: ?string, date: ?\DateTimeInterface, day: string, month: string, when: string, datetime: string, place: string}>, calendar: bool, table: ?array}
     */
    public static function dates(array $d): array
    {
        $t = Tables::findContent((string) ($d['dates_table'] ?? ''));
        $limit = max(1, min(4, (int) ($d['dates_limit'] ?? 3)));
        if (!$t) return ['items' => [], 'calendar' => false, 'table' => null];
        $items = [];
        if (Calendar::enabled($t)) {
            $c = Calendar::config($t);
            foreach (Calendar::upcoming($t, $limit) as $o) {
                $e = $o['entry'];
                $items[] = ['title' => Entries::title($t, $e), 'href' => Entries::href($t, $e), 'date' => $o['start'],
                    'day' => $o['start']->format('j'), 'month' => Calendar::fmt($o['start'], 'LLL'), 'when' => Calendar::when($o),
                    'datetime' => $o['all_day'] ? $o['start']->format('Y-m-d') : $o['start']->format('Y-m-d\TH:iP'),
                    'place' => $c['location'] !== '' ? Entries::html($t, $e, $c['location'], ['plain' => true]) : ''];
            }
            if ($items) \Core\StructuredData::events($t, Calendar::upcoming($t, $limit));
            return ['items' => $items, 'calendar' => true, 'table' => $t];
        }
        $dateField = '';
        foreach ($t['fields'] as $f) if (in_array($f['type'] ?? '', ['date', 'datetime'], true)) { $dateField = $f['name']; break; }
        foreach (Entries::query($t, ['status' => 'published', 'limit' => $limit] + ($dateField !== '' ? ['sort' => $dateField, 'dir' => 'desc'] : [])) as $e) {
            $raw = $dateField !== '' ? (string) ($e[$dateField] ?? '') : (string) ($e['created_at'] ?? '');
            $dt = $raw !== '' ? (\DateTimeImmutable::createFromFormat('!Y-m-d', substr($raw, 0, 10)) ?: null) : null;
            $items[] = ['title' => Entries::title($t, $e), 'href' => Entries::href($t, $e), 'date' => $dt,
                'day' => $dt ? $dt->format('j') : '', 'month' => $dt ? Calendar::fmt($dt, 'LLL') : '',
                'when' => $dt ? Calendar::fmt($dt, 'd. MMMM y') : '', 'datetime' => $dt ? $dt->format('Y-m-d') : '', 'place' => ''];
        }
        return ['items' => $items, 'calendar' => false, 'table' => $t];
    }

    // ------------------------------------------------------------------ Formular

    /** Öffentliches Formular (Feld „form_table“) – wie Block „data_form“, ohne Container; Stylesheet über 'uses' → data_form */
    public static function form(Block $b, array $d): string
    {
        $t = Tables::find((string) ($d['form_table'] ?? ''));
        if (!$t || !DataForms::enabled($t)) {
            return is_editing() ? '<p class="dl-empty">' . e(__('Bitte in der Seitenleiste eine Tabelle mit öffentlichem Formular wählen.')) . '</p>' : '';
        }
        $state = DataForms::$state[$t['handle']] ?? [];
        return DataForms::render($t, [
            'uid' => $b->domId() . '-hf', 'submit' => (string) ($d['form_submit'] ?? ''), 'success' => '',
            'values' => $state['values'] ?? [], 'errors' => $state['errors'] ?? [], 'message' => $state['message'] ?? null,
            'sent' => $state['sent'] ?? false, 'challenge' => $state['challenge'] ?? null,
        ]);
    }

    // ------------------------------------------------------------------ Karte

    /** Karte mit dem Standort aus „Website“ (Core\Maps: Kacheln über den eigenen Server, lädt erst in Sichtweite) */
    public static function map(array $d, string $height = 'm', string $class = ''): string
    {
        return Maps::renderBlock(['location' => 'site', 'zoom' => (int) ($d['map_zoom'] ?? 15), 'height' => $height, 'route' => true], ['class' => $class]);
    }
}
