<?php
declare(strict_types=1);

namespace Core;

/**
 * Karten mit MapLibre GL und OpenFreeMap – vollständig über den eigenen Proxy.
 *
 *  - Stil, Kacheln, Schriften und Symbole kommen von /proxy/ofm/… (siehe Core\Proxy).
 *    Besucher haben keinen Kontakt zu Dritten → keine Einwilligung nötig, keine Cookies.
 *  - MapLibre (~800 KB) wird erst geladen, wenn die Karte in Sichtweite kommt.
 *  - Kartengebiet: Detailkacheln (ab Zoom 9) liefert der Proxy nur rund um Orte, die auf
 *    der Website tatsächlich als Karte ausgegeben werden – kein freier Kachelserver für Dritte.
 *  - Geokodierung (Adresse → Koordinaten) nur in der Verwaltung, serverseitig über Nominatim.
 */
final class Maps
{
    public const STYLES = [
        'liberty' => 'Liberty (farbig, detailreich)',
        'bright' => 'Bright (hell, klassisch)',
        'positron' => 'Positron (dezent, grau)',
        'dark' => 'Dark (dunkel)',
        'fiord' => 'Fiord (dunkelblau)',
    ];
    public const HEIGHTS = ['s' => 'Niedrig', 'm' => 'Mittel', 'l' => 'Hoch'];

    private const FREE_ZOOM = 8;
    private static bool $assets = false;

    public static function registerProxy(): void
    {
        Proxy::register('ofm', [
            'label' => 'Karten (OpenFreeMap)',
            'upstream' => 'https://tiles.openfreemap.org',
            'allow' => '^(styles/[a-z]+|planet(/[\w.-]+/\d+/\d+/\d+\.pbf)?|sprites/[\w./@-]+\.(json|png)|fonts/[^/]+/\d+-\d+\.pbf|natural_earth/ne2sr/\d+/\d+/\d+\.png)$',
            'rewrite' => true,
            'ttl' => fn(string $p) => preg_match('~^(styles/|planet$)~', $p) ? 86400 : 30 * 86400,
            'guard' => [self::class, 'allowTile'],
            'max' => 4 * 1024 * 1024,
        ]);
        Proxy::register('nominatim', [
            'label' => 'Adresssuche (OpenStreetMap Nominatim, nur Verwaltung)',
            'upstream' => 'https://nominatim.openstreetmap.org',
            'public' => false,
            'types' => ['application/json'],
            'max' => 512 * 1024,
        ]);
    }

    // ------------------------------------------------------------------ Koordinaten

    /** "51.16, 10.45" | ['lat'=>…, 'lng'=>…] → [lat, lng] oder null */
    public static function parse(mixed $v): ?array
    {
        if (is_array($v)) {
            $lat = $v['lat'] ?? $v[0] ?? null;
            $lng = $v['lng'] ?? $v['lon'] ?? $v[1] ?? null;
        } elseif (is_string($v) && preg_match('~^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$~', $v, $m)) {
            [$lat, $lng] = [$m[1], $m[2]];
        } else {
            return null;
        }
        if (!is_numeric($lat) || !is_numeric($lng)) return null;
        $lat = (float) $lat;
        $lng = (float) $lng;
        if (abs($lat) > 85 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) return null;
        return [round($lat, 6), round($lng, 6)];
    }

    public static function format(?array $p): string
    {
        return $p ? rtrim(rtrim(number_format($p[0], 6, '.', ''), '0'), '.') . ', ' . rtrim(rtrim(number_format($p[1], 6, '.', ''), '0'), '.') : '';
    }

    /** Standort der Website (Theme: 'project' → 'map' → 'location' = Name der Einstellung) */
    public static function siteLocation(): ?array
    {
        $key = (string) project('map.location', '');
        return $key !== '' ? self::parse(setting($key)) : null;
    }

    public static function siteLabel(): string
    {
        $key = (string) project('map.label', '');
        return $key !== '' ? (string) setting($key, '') : site_name();
    }

    /** Postanschrift aus den Einstellungen (für Adresssuche und Bildunterschrift) */
    public static function siteAddress(): string
    {
        $parts = [];
        foreach ((array) project('map.address', []) as $k) {
            $v = trim((string) setting($k, ''));
            if (filled($v)) $parts[] = $v;
        }
        return implode(', ', $parts);
    }

    // ------------------------------------------------------------------ Kartengebiet (Schutz des Proxys)

    /** Ort merken, damit der Proxy Detailkacheln dafür ausliefert */
    public static function remember(array $p): void
    {
        $areas = (array) app()->settings->get('sys.map_areas', []);
        foreach ($areas as $a) {
            if (self::distanceKm($p, $a) < 2) return;
        }
        $areas[] = [$p[0], $p[1]];
        app()->settings->set('sys.map_areas', array_slice($areas, -200));
    }

    public static function radiusKm(): float
    {
        return max(2.0, min(200.0, (float) setting('sys.map_radius', 25)));
    }

    /** Guard für /proxy/ofm: Kachel liegt im erlaubten Gebiet? */
    public static function allowTile(string $path): bool
    {
        if (!preg_match('~^planet/[\w.-]+/(\d+)/(\d+)/(\d+)\.pbf$~', $path, $m)) {
            return true;
        }
        [$z, $x, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($z <= self::FREE_ZOOM) return true;
        if ($z > 16) return false;
        $n = 2 ** $z;
        $west = $x / $n * 360 - 180;
        $east = ($x + 1) / $n * 360 - 180;
        $north = rad2deg(atan(sinh(M_PI * (1 - 2 * $y / $n))));
        $south = rad2deg(atan(sinh(M_PI * (1 - 2 * ($y + 1) / $n))));
        $r = self::radiusKm() + 5;
        foreach ((array) app()->settings->get('sys.map_areas', []) as $a) {
            // nächster Punkt der Kachel zum Ort
            $lat = max($south, min($north, (float) $a[0]));
            $lng = max($west, min($east, (float) $a[1]));
            if (self::distanceKm([$lat, $lng], $a) <= $r) return true;
        }
        return false;
    }

    private static function distanceKm(array $a, array $b): float
    {
        $dLat = deg2rad((float) $b[0] - (float) $a[0]);
        $dLng = deg2rad((float) $b[1] - (float) $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad((float) $a[0])) * cos(deg2rad((float) $b[0])) * sin($dLng / 2) ** 2;
        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    /** [west, süd, ost, nord] um einen Ort */
    private static function bounds(array $p): array
    {
        $r = self::radiusKm();
        $dLat = $r / 111.32;
        $dLng = $r / (111.32 * max(0.1, cos(deg2rad($p[0]))));
        return [round($p[1] - $dLng, 5), round($p[0] - $dLat, 5), round($p[1] + $dLng, 5), round($p[0] + $dLat, 5)];
    }

    // ------------------------------------------------------------------ Ausgabe

    public static function styleUrl(?string $style = null): string
    {
        $style = $style && isset(self::STYLES[$style]) ? $style : (string) setting('sys.map_style', 'liberty');
        return Proxy::url('ofm', 'styles/' . (isset(self::STYLES[$style]) ? $style : 'liberty'));
    }

    /**
     * Karte ausgeben.
     * @param array{lat?: float, lng?: float, point?: mixed, zoom?: int, label?: string, address?: string,
     *              height?: string, style?: string, route?: bool|string, class?: string} $o
     */
    public static function render(array $o = []): string
    {
        $p = self::parse($o['point'] ?? (isset($o['lat'], $o['lng']) ? [$o['lat'], $o['lng']] : null)) ?? self::siteLocation();
        if (!$p) {
            return is_editing()
                ? '<div class="cms-map cms-map--empty"><p>' . e(__('Für die Karte fehlt der Standort – bitte Koordinaten eintragen (Adresse suchen oder in der Karte klicken).')) . '</p></div>'
                : '';
        }
        self::remember($p);
        $label = trim((string) ($o['label'] ?? self::siteLabel()));
        $address = trim((string) ($o['address'] ?? ''));
        $zoom = max(3, min(18, (int) ($o['zoom'] ?? 15)));
        $height = isset(self::HEIGHTS[$o['height'] ?? '']) ? $o['height'] : 'm';
        $route = $o['route'] ?? true;
        $routeUrl = is_string($route) && $route !== '' ? $route
            : 'https://www.openstreetmap.org/directions?to=' . rawurlencode($p[0] . ',' . $p[1]) . '#map=' . $zoom . '/' . $p[0] . '/' . $p[1];
        $t = self::texts();

        $cfg = [
            'style' => self::styleUrl($o['style'] ?? null),
            // Dunkler Kartenstil – nur genutzt, wenn das Theme Dunkelmodus erklärt (<meta name="color-scheme" content="light dark">)
            'styleDark' => self::styleUrl((string) setting('sys.map_style_dark', 'dark')),
            'center' => [$p[1], $p[0]],
            'zoom' => $zoom,
            'bounds' => self::bounds($p),
            'label' => $label,
            'vendor' => base_path() . '/assets/vendor/maplibre/',
            'css' => asset('vendor/maplibre/maplibre-gl.css'),
            'i18n' => $t['maplibre'],
        ];
        $h = '<figure class="cms-map cms-map--' . $height . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '" data-cms-map="'
            . e(json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">'
            . '<div class="cms-map__canvas" role="region" aria-label="' . e(str_replace('{label}', $label ?: self::format($p), $t['region'])) . '">'
            . '<span class="cms-map__pin" aria-hidden="true"></span>'
            . '<button type="button" class="cms-map__load" data-cms-map-load hidden>' . e($t['load']) . '</button></div>'
            . '<figcaption class="cms-map__cap"><span>' . ($label !== '' ? '<strong>' . e($label) . '</strong>' : '')
            . ($address !== '' ? ($label !== '' ? ' · ' : '') . e($address) : '') . '</span>'
            . ($route ? '<a class="cms-map__route" href="' . e($routeUrl) . '" target="_blank" rel="noopener">' . e($t['route']) . '<span class="sr-only"> ' . e($t['newtab']) . '</span></a>' : '')
            . '</figcaption></figure>';
        if (!self::$assets) {
            self::$assets = true;
            $h .= '<link rel="stylesheet" href="' . e(asset('css/map.css')) . '"><script type="module" src="' . e(asset('js/map.mjs')) . '"></script>';
        }
        return $h;
    }

    private static function texts(): array
    {
        return [
            'region' => lt('Karte: {label}'), 'load' => lt('Karte anzeigen'), 'route' => lt('Route planen'), 'newtab' => lt('(öffnet in neuem Tab)'),
            'maplibre' => [
                'NavigationControl.ZoomIn' => lt('Vergrößern'), 'NavigationControl.ZoomOut' => lt('Verkleinern'),
                'NavigationControl.ResetBearing' => lt('Nach Norden ausrichten'), 'FullscreenControl.Enter' => lt('Vollbild'),
                'FullscreenControl.Exit' => lt('Vollbild beenden'), 'AttributionControl.ToggleAttribution' => lt('Quellenangaben'),
                'CooperativeGesturesHandler.WindowsHelpText' => lt('Strg + Mausrad zum Zoomen'),
                'CooperativeGesturesHandler.MacHelpText' => lt('⌘ + Mausrad zum Zoomen'),
                'CooperativeGesturesHandler.MobileHelpText' => lt('Mit zwei Fingern verschieben'),
                'Map.Title' => lt('Karte'), 'Marker.Title' => lt('Standort'),
            ],
        ];
    }

    // ------------------------------------------------------------------ Block „Karte“

    /** Felder für Karten-Blöcke (Kern-Block und Themes: [...eigene Überschrift, ...Maps::blockFields()]) */
    public static function blockFields(): array
    {
        return [
            ['name' => 'location', 'label' => 'Ort', 'type' => 'select', 'required' => true, 'default' => 'site',
                'options' => ['site' => 'Standort der Website (zentrale Angaben)', 'custom' => 'Eigener Ort']],
            ['name' => 'point', 'label' => 'Eigener Ort', 'type' => 'geo',
                'help' => 'Adresse suchen oder in die Karte klicken. Auf Detailseiten per ⛓ an ein Ortsfeld des Eintrags koppeln.'],
            ['name' => 'label', 'label' => 'Beschriftung', 'type' => 'text', 'width' => 'half', 'placeholder' => 'Standard: Name der Website'],
            ['name' => 'address', 'label' => 'Adresse unter der Karte', 'type' => 'text', 'width' => 'half', 'placeholder' => 'Standard: Adresse aus den zentralen Angaben'],
            ['name' => 'zoom', 'label' => 'Zoomstufe', 'type' => 'select', 'required' => true, 'default' => '15', 'width' => 'half',
                'options' => ['11' => '11 – Stadt', '13' => '13 – Stadtteil', '15' => '15 – Straße', '16' => '16', '17' => '17 – Gebäude']],
            ['name' => 'height', 'label' => 'Höhe', 'type' => 'select', 'required' => true, 'default' => 'm', 'width' => 'half', 'options' => self::HEIGHTS],
            ['name' => 'style', 'label' => 'Kartenstil', 'type' => 'select', 'default' => '', 'width' => 'half',
                'options' => ['' => 'Standard (Grundeinstellungen)'] + self::STYLES],
            ['name' => 'route', 'label' => 'Link „Route planen“ anzeigen', 'type' => 'bool', 'default' => true],
        ];
    }

    /** Karten-Block ausgeben (Werte aus blockFields) */
    public static function renderBlock(array $d, array $extra = []): string
    {
        $site = ($d['location'] ?? 'site') !== 'custom';
        $route = !empty($d['route']);
        if ($route && $site && ($k = (string) project('map.route', '')) !== '' && filled((string) setting($k))) {
            $route = (string) setting($k);
        }
        return self::render([
            'point' => $site ? self::siteLocation() : ($d['point'] ?? null),
            'label' => trim((string) ($d['label'] ?? '')) ?: ($site ? self::siteLabel() : ''),
            'address' => trim((string) ($d['address'] ?? '')) ?: ($site ? self::siteAddress() : ''),
            'zoom' => (int) ($d['zoom'] ?? 15),
            'height' => (string) ($d['height'] ?? 'm'),
            'style' => (string) ($d['style'] ?? ''),
            'route' => $route,
        ] + $extra);
    }

    // ------------------------------------------------------------------ Geokodierung (Verwaltung)

    /** @return list<array{label: string, lat: float, lng: float}> */
    public static function geocode(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) return [];
        $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&addressdetails=0&q=' . rawurlencode($q)
            . '&accept-language=' . rawurlencode(Lang::default());
        $res = Proxy::http($url);
        $rows = $res && $res['status'] === 200 ? json_decode($res['body'], true) : null;
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            if (isset($r['lat'], $r['lon'])) {
                $out[] = ['label' => (string) ($r['display_name'] ?? ''), 'lat' => round((float) $r['lat'], 6), 'lng' => round((float) $r['lon'], 6)];
            }
        }
        return $out;
    }
}
