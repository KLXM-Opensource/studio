<?php
declare(strict_types=1);

namespace Core;

/**
 * Kit-System (Klassenname „Theme“ bleibt aus Kompatibilitätsgründen; Pfade liefert Core\Kit).
 *
 *   kits/{name}/theme.php          Definition (Blöcke, Einstellungs-Schema, Formulare, Hintergründe); kit.php geht ebenso
 *   kits/{name}/templates/         layout.php, page.php, form-page.php, error.php, partials/
 *   kits/{name}/fragments/         eigene Fassungen überschreibbarer Kern-Fragmente (Core\Fragments)
 *   kits/{name}/blocks/{type}.php  Renderer je Blocktyp
 *   kits/{name}/seed.php           Startinhalte (Seiten + Einstellungen)
 *   public/assets/kits/{name}/     öffentliche Assets (CSS, JS, Bilder) – Ablage: Core\PublicPaths
 *
 * Der Core kennt keine praxisspezifischen Inhalte – ein neues Projekt = neues Kit.
 */
final class Theme
{
    public readonly string $name;
    public readonly string $path;
    public readonly array $def;
    private array $blocks = [];

    /** Universelle Block-Tunes (Abschnitts-Optionen) */
    public const TUNES = [
        'background' => 'white', 'anchor' => '', 'visible' => true, 'showInNav' => false, 'navLabel' => '',
        'spaceTop' => 'normal', 'spaceBottom' => 'normal', 'divider' => false,
        'height' => 'auto', 'bgImage' => null, 'overlay' => 'none', 'align' => 'center', 'row' => '',
        // Glossar (Core\Glossary): in diesem Abschnitt keine Begriffe markieren
        'noGlossary' => false,
    ];

    /**
     * Veraltet – Blöcke nebeneinander jetzt mit dem Block „Layout“ (Core\Layout); nur noch Darstellung alter Inhalte bis zur Umstellung
     * (php bin/console layout:migrate-rows). Tune „row“: Breite des Blocks in Zwölfteln; auto = teilt sich den Rest mit dem ersten Block der Reihe.
     * Der erste Block einer Reihe hat keine eigene Breite (er bekommt den Rest).
     */
    public const ROW_WIDTHS = ['auto' => null, '1-2' => 6, '1-3' => 4, '2-3' => 8, '1-4' => 3, '3-4' => 9];

    /** Wurde beim Rendern eine Reihe ausgegeben? (→ css/rows.css) */
    private bool $rowCss = false;

    /** Wurde beim Rendern ein Abschnitt mit Vollbild-Höhe oder Hintergrundbild ausgegeben? (→ css/sections.css) */
    private bool $sectionCss = false;

    public function __construct(string $name)
    {
        $name = Kit::clean($name) ?: (string) array_key_first(self::available());
        $path = Kit::dir($name);
        $def = Kit::definitionFile($path);
        if ($def === null) {
            throw new \RuntimeException("Kit '$name' nicht gefunden");
        }
        $this->name = $name;
        $this->path = $path;
        $this->def = require $def;

        foreach ($this->def['blocks'] ?? [] as $type => $b) {
            $this->blocks[$type] = $b + ['type' => $type, 'fields' => [], 'label' => $type];
        }
        // Kern-Blöcke (Datenliste, Datensatz-Felder) – Theme kann sie abschalten oder die Ausgabe überschreiben
        if ($this->def['core_blocks'] ?? true) {
            foreach (require ROOT . '/app/Blocks/blocks.php' as $type => $b) {
                $this->blocks[$type] ??= $b + ['type' => $type, 'fields' => [], 'label' => $type, 'core' => true];
            }
        }
        // Layout (Spalten, Core\Layout): in jedem Kit, außer theme.php → 'layout' => false
        if (($this->def['layout'] ?? true) !== false) {
            $this->blocks[Layout::TYPE] ??= Layout::definition() + ['type' => Layout::TYPE];
        }
        // Eigene Blöcke der Website (Block-Designer, Core\Blocks\Custom): freigegebene Fassungen als Typ cblk_{schlüssel}
        foreach (Blocks\Custom::definitions() as $type => $b) {
            if (isset($b['background']) && !isset($this->backgrounds()[$b['background']])) unset($b['background']);
            $this->blocks[$type] ??= $b + ['type' => $type, 'label' => $type];
        }
        // Standardwerte der Theme-Einstellungen registrieren
        app()->settings->registerDefaults(Fields::defaults($this->settingsFields()));
    }

    /** Passt das Theme zur Core-Version? (theme.php → 'requires' => '>=1.0') */
    public function compatible(): bool
    {
        $req = trim((string) ($this->def['requires'] ?? ''));
        if ($req === '' || !preg_match('~^(>=|>|<=|<|=|==)?\s*([\d.]+)$~', $req, $m)) return true;
        return version_compare(CMS_VERSION, $m[2], ($m[1] ?? '') ?: '>=');
    }

    public static function available(): array
    {
        $out = [];
        // Label ohne Ausführen der Datei lesen (Kits können gleichnamige Helfer definieren); Ordner über Core\Kit
        foreach (Kit::all() as $name => $dir) {
            $f = (string) Kit::definitionFile($dir);
            $out[$name] = preg_match("~^    'label'\s*=>\s*'([^']+)'~m", (string) file_get_contents($f), $m) ? $m[1] : $name;
        }
        return $out;
    }

    public function label(): string
    {
        return $this->def['label'] ?? $this->name;
    }

    public function blocks(): array
    {
        return $this->blocks;
    }

    /** Blöcke einer Erweiterung hinzufügen (Theme-Blöcke gleichen Namens haben Vorrang) */
    public function addBlocks(array $defs, string $dir, string $extension): void
    {
        foreach ($defs as $type => $b) {
            $this->blocks[$type] ??= $b + ['type' => $type, 'fields' => [], 'label' => $type, 'dir' => $dir, 'extension' => $extension];
        }
    }

    public function block(string $type): ?array
    {
        return $this->blocks[$type] ?? null;
    }

    /**
     * Darf der Block in einer Spalte eines Layouts stehen? theme.php → blocks → {typ} → 'nestable' => true | false | ['variante', …]
     * (nur diese Varianten). Markiert das Kit keinen Block, gelten Layout::DEFAULT_NESTABLE. Layouts, Blöcke mit eigener Hülle
     * ('raw') und nicht einfügbare Typen nie. $variant = '' prüft nur den Typ.
     */
    public function nestable(string $type, string $variant = ''): bool
    {
        $v = $this->nestableVariants($type);
        return $v === true || (is_array($v) && ($variant === '' || in_array($variant, $v, true)));
    }

    /** true = alle Varianten, Liste = nur diese Varianten, false = nicht verschachtelbar */
    public function nestableVariants(string $type): bool|array
    {
        $def = $this->blocks[$type] ?? null;
        if (!$def || $type === Layout::TYPE || !empty($def['raw'])) return false;
        static $marked = [];
        $marked[$this->name] ??= (bool) array_filter($this->blocks, fn($b) => array_key_exists('nestable', $b) && empty($b['core']) && empty($b['custom']));
        $n = $def['nestable'] ?? (!$marked[$this->name] && empty($def['core']) && in_array($type, Layout::DEFAULT_NESTABLE, true));
        if (is_array($n)) {
            $n = array_values(array_filter(array_map('strval', $n), fn($v) => isset($def['variants'][$v])));
            return $n ?: false;
        }
        return (bool) $n;
    }

    /** Gruppen des zentralen Einstellungsformulars (Titel vom Theme) */
    public function settingsGroups(): array
    {
        // Hinweisbalken: Zeitraum und Darstellung ergänzt der Core (Core\Notice)
        return Notice::extendGroups($this->def['settings']['groups'] ?? []);
    }

    public function settingsTitle(): string
    {
        return __((string) ($this->def['settings']['title'] ?? 'Website'));
    }

    public function settingsFields(): array
    {
        $out = [];
        foreach ($this->settingsGroups() as $g) {
            foreach ($g['fields'] as $f) {
                $out[] = $f;
            }
        }
        return $out;
    }

    public function forms(): array
    {
        return $this->def['forms'] ?? [];
    }

    public function backgrounds(): array
    {
        return $this->def['backgrounds'] ?? ['white' => 'Weiß'];
    }

    public function sanitizeTunes(array $t, array $def = []): array
    {
        $out = self::TUNES;
        $bgs = $this->backgrounds();
        $out['background'] = isset($t['background'], $bgs[$t['background']]) ? $t['background'] : ($def['background'] ?? array_key_first($bgs));
        $anchor = trim((string) ($t['anchor'] ?? ''));
        $out['anchor'] = $anchor === '' ? '' : Pages::slugify($anchor);
        $out['visible'] = !isset($t['visible']) || filter_var($t['visible'], FILTER_VALIDATE_BOOL);
        $out['showInNav'] = filter_var($t['showInNav'] ?? false, FILTER_VALIDATE_BOOL);
        $out['navLabel'] = mb_substr(strip_tags((string) ($t['navLabel'] ?? '')), 0, 40);
        $out['spaceTop'] = in_array($t['spaceTop'] ?? '', ['normal', 'small', 'none'], true) ? $t['spaceTop'] : 'normal';
        $out['spaceBottom'] = in_array($t['spaceBottom'] ?? '', ['normal', 'small', 'none'], true) ? $t['spaceBottom'] : 'normal';
        $out['divider'] = filter_var($t['divider'] ?? false, FILTER_VALIDATE_BOOL);
        // Vollbild-Abschnitt, Hintergrundbild mit Abdunkelung/Aufhellung, vertikale Ausrichtung
        $out['height'] = ($t['height'] ?? '') === 'screen' ? 'screen' : 'auto';
        $out['bgImage'] = (int) ($t['bgImage'] ?? 0) > 0 ? (int) $t['bgImage'] : null;
        $out['overlay'] = in_array($t['overlay'] ?? '', ['light', 'dark'], true) ? $t['overlay'] : 'none';
        $out['align'] = in_array($t['align'] ?? '', ['top', 'bottom'], true) ? $t['align'] : 'center';
        // Neben den vorigen Block stellen: '' = eigener Abschnitt, sonst Breite (auto, 1-2, 1-3, 2-3, 1-4, 3-4); true = auto
        $row = ($t['row'] ?? '') === true ? 'auto' : (string) ($t['row'] ?? '');
        $out['row'] = array_key_exists($row, self::ROW_WIDTHS) ? $row : '';
        $out['noGlossary'] = filter_var($t['noGlossary'] ?? false, FILTER_VALIDATE_BOOL);
        return $out;
    }

    // ------------------------------------------------------------------ Rendering

    public function render(string $template, array $vars = []): string
    {
        $file = $this->path . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template '$template' fehlt im Kit '{$this->name}'");
        }
        $html = self::capture($file, $vars);
        // Ganze Seite (Layout): Stylesheet für angepasste Bilder nur bei Bedarf einbinden, im Bearbeiten-Modus immer (Core\ImageFx)
        // Bild im Rahmen (Core\ImageFit): Stylesheet + erzeugte Regeln (Farben, unscharfer Hintergrund) ebenso
        return str_contains($html, '</head>') ? ImageFit::inject(ImageFx::inject($html, app()->editing), app()->editing) : $html;
    }

    public static function capture(string $__file, array $__vars): string
    {
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Fragment rendern (Core\Fragments): Nur-Kern-Fragmente (video-embed, editor, toolbar) immer aus dem Kern,
     * überschreibbare nach der Suchreihenfolge project/overrides/kits/{kit}/fragments → kits/{kit}/fragments →
     * kits/{kit}/templates/partials → app/Views/fragments. Kit-eigene Partials (header, footer, section …) wie bisher.
     */
    public function partial(string $name, array $vars = []): string
    {
        $html = Fragments::render($name, $vars, $this->name);
        // Redaktions-Werkzeugleiste (nur angemeldet): in ein eigenes Shadow DOM, damit Kit-CSS nicht hineinwirkt
        return $name === 'toolbar' ? self::toolbarHost($html) : $html;
    }

    /**
     * Werkzeugleiste als Declarative Shadow DOM (<template shadowrootmode="open">, resources/js/_shadow.js rüstet
     * ältere Browser nach). Stylesheet per <link> (CSP). Alles nach <!--cms-bar-end--> (Hinweiszeile, Konfiguration)
     * bleibt im Dokument. data-cms-css: Stylesheets für weitere Schatten-Wurzeln (Seitenleiste, Dialoge, Block-Leisten).
     */
    public static function toolbarHost(string $html): string
    {
        if (trim($html) === '' || str_contains($html, 'cms-bar-host')) return $html;
        [$bar, $rest] = array_pad(explode('<!--cms-bar-end-->', $html, 2), 2, '');
        $user = app()->auth->user();
        $theme = in_array($user['appearance'] ?? '', ['light', 'dark'], true) ? $user['appearance'] : '';
        $css = ['admin' => asset('css/admin.shadow.css'), 'ui' => asset('css/editor.shadow.css')];
        return '<div class="cms-bar-host adm-ui"' . ($theme !== '' ? ' data-theme="' . e($theme) . '"' : '') . Accent::attrs($user)
            . ' data-cms-css="' . json_attr($css) . '" data-icons="' . e(Icons::sprite()) . '"'
            . (($icoTopics = Icons::enabledTopics()) ? ' data-icons-topics="' . e(implode(' ', $icoTopics)) . '"' : '') . '><template shadowrootmode="open">'
            . '<link rel="stylesheet" href="' . e($css['ui']) . '">' . trim($bar) . '</template></div>' . "\n" . ltrim($rest)
            . \Core\AI\Assist::clientScript()   // KI-Assistent (resources/js/_ai.js) – leer ohne Recht „ai.use“ bzw. KI
            . RichText::clientScript();   // Formatierungsleiste: Stile und Farbpalette des Kits (resources/js/_rte.js)
    }

    public function renderBlocks(array $blocks): string
    {
        $html = '';
        $prev = null;
        $row = [];   // laufende Reihe: erster Block = Kopf (Abschnitt), weitere mit Tune „row“
        foreach ($blocks as $i => $b) {
            $block = $this->makeBlock($b, $prev, $blocks[$i + 1] ?? null);
            if (!$block || (!$block->tune('visible') && !app()->editing)) {
                continue;
            }
            if ($block->inRow()) {
                if ($row && $this->rowable($row[0]) && $this->rowable($block)) {
                    $row[] = $block;
                    StructuredData::collect($block);
                    $prev = $block;
                    continue;
                }
                // Erster Block der Seite (bzw. nach einem Block mit eigener Hülle): Option wird ignoriert
                $block = $block->withTunes(['row' => '']);
            }
            $html .= $this->renderRow($row);
            $row = [$block];
            StructuredData::collect($block);   // schema.org-Daten des Blocks (theme.php → 'jsonld')
            $prev = $block;
        }
        return $html . $this->renderRow($row);
    }

    /** Unterstützt das Kit Reihen (theme.php → 'rows' => false schaltet sie ab) – und rendert der Block keine eigene Hülle? */
    public function rowable(Block $block): bool
    {
        return ($this->def['rows'] ?? true) !== false && empty($block->def['raw']);
    }

    /**
     * Reihe „Blöcke nebeneinander“ ausgeben: ein Abschnitt mit den Optionen des ersten Blocks (Hintergrund, Anker, Abstände,
     * Hintergrundbild …), darin <div class="wrap sec-row"> mit einer Zelle je Block (Inhalt ohne eigenen Abschnitt).
     * Weitere Blöcke: eigener Hintergrund ≠ dem der Reihe → Karte (bg-{name}, sec-row__cell--card); Sprungmarke an der Zelle;
     * Abstände, Trennlinie, Vollbild und Hintergrundbild gelten nur vom ersten Block. Ein einzelner Block → renderBlock().
     */
    public function renderRow(array $row): string
    {
        if (count($row) < 2) {
            return $row ? $this->renderBlock($row[0]) : '';
        }
        $lead = $row[0];
        $lead->rowLead = true;
        $this->rowCss = true;
        if ($lead->tunes['height'] === 'screen' || $lead->tunes['bgImage']) {
            $this->sectionCss = true;
        }
        $spans = self::rowSpans(array_map(fn(Block $b) => $b->tunes['row'], $row));
        $cells = '';
        foreach ($row as $i => $b) {
            $inner = $this->renderInner($b);
            if ($inner === null || (trim($inner) === '' && !app()->editing)) continue;
            $cls = ['sec-row__cell', 'sec-row__cell--w' . $spans[$i], 'sec--' . str_replace('_', '-', $b->type)];
            if ($b->variant()) $cls[] = 'v-' . $b->variant();
            $id = '';
            if ($i === 0) {
                $cls[] = 'sec-row__cell--lead';
            } else {
                // Karte: eigener Hintergrund; „sec“ dazu, weil viele Kits Fläche/Schriftfarbe nur an .sec.bg-* setzen (Variablen)
                if ($b->bg() !== $lead->bg()) array_push($cls, 'sec', 'sec-row__cell--card', 'bg-' . $b->bg());
                if (!$b->tunes['visible']) $cls[] = 'is-hidden-block';
                if ($b->tunes['anchor'] !== '') $id = ' id="' . e($b->domId()) . '"';
            }
            $vd = isset(self::$vdiff[$b->id]) ? ' data-vdiff="' . e(self::$vdiff[$b->id]) . '"' : '';
            $cells .= '<div' . $id . $vd . ' class="' . e(implode(' ', $cls)) . '">' . $inner . "</div>\n";
        }
        $wrap = (string) ($this->def['rows']['wrap'] ?? 'wrap');
        $html = $this->render('partials/section', ['b' => $lead, 'inner' => '<div class="' . e(trim($wrap . ' sec-row')) . '">' . "\n" . $cells . '</div>']);
        return EditorNotes::$show ? EditorNotes::decorate($html) : $html;
    }

    /**
     * Spaltenanteile einer Reihe (Zwölftel als flex-grow): feste Breiten wie gewählt, „auto“ und der erste Block teilen den Rest.
     * @param string[] $widths Tune „row“ je Block (erster wird als auto gewertet)
     * @return int[]
     */
    public static function rowSpans(array $widths): array
    {
        $fixed = 0; $autos = 0;
        foreach (array_values($widths) as $i => $w) {
            $n = $i === 0 ? null : (self::ROW_WIDTHS[$w] ?? null);
            $n === null ? $autos++ : $fixed += $n;
        }
        $share = $autos ? max(2, intdiv(max(0, 12 - $fixed), $autos)) : 0;
        $out = [];
        foreach (array_values($widths) as $i => $w) {
            $out[] = $i === 0 ? $share : (self::ROW_WIDTHS[$w] ?? $share);
        }
        return $out;
    }

    public function makeBlock(array $b, ?Block $prev = null, ?array $next = null): ?Block
    {
        $def = $this->block((string) ($b['type'] ?? ''));
        if (!$def) {
            return null;
        }
        $data = array_replace(Fields::defaults($def['fields']), is_array($b['data'] ?? null) ? $b['data'] : []);
        if (app()->entry) {
            $data = self::bindEntry($data, $def['fields']);
        }
        // Redaktionsnotizen [# … #] (Core\EditorNotes): für Besucher vor dem Rendern entfernen – kein Kit muss etwas tun
        if (!EditorNotes::$show) {
            $data = EditorNotes::stripData($data);
        }
        if (!empty($def['variants']) && empty($data['variant'])) {
            $data['variant'] = array_key_first($def['variants']);
        }
        $tunes = $this->sanitizeTunes($b['tunes']['section'] ?? [], $def);
        return new Block((string) ($b['id'] ?? uniqid()), $def['type'], $data, $tunes, $def, $prev);
    }

    /**
     * Detailseiten-Vorlagen: Platzhalter {{feld}} in Textfeldern und „@feld“ in Bild-/Dateifeldern
     * mit den Werten des aufgerufenen Eintrags füllen.
     */
    private static function bindEntry(array $data, array $fields): array
    {
        $types = array_column($fields, null, 'name');
        // Feldbindungen aus dem Editor: Blockfeld ← Datensatz-Feld (ohne Datensatz bleibt der eingetragene Wert)
        $ctx = app()->entry;
        foreach ((array) ($data['_bind'] ?? []) as $field => $source) {
            if (isset($types[$field]) && is_string($source) && $source !== '') {
                $data[$field] = \Core\Data\Entries::bindValue($ctx['table'], $ctx['entry'], $source, $types[$field]['type']);
            }
        }
        foreach ($data as $k => $v) {
            $f = $types[$k] ?? null;
            if (is_string($v)) {
                if ($f && in_array($f['type'], ['media', 'file'], true) && str_starts_with($v, '@')) {
                    $ctx = app()->entry;
                    $data[$k] = $ctx['entry'][substr($v, 1)] ?? null;
                } elseif (str_contains($v, '{{')) {
                    $data[$k] = \Core\Data\Entries::replaceTokens($v, in_array($f['type'] ?? '', ['richtext', 'inline'], true));
                }
            } elseif (is_array($v) && ($f['type'] ?? '') === 'repeater') {
                foreach ($v as $i => $item) {
                    if (is_array($item)) $data[$k][$i] = self::bindEntry($item, $f['fields'] ?? []);
                }
            }
        }
        return $data;
    }

    /** Versionen-Vorschau (Admin\VersionsController, ?mark=1): Block-ID → 'new' | 'changed' – als data-vdiff am äußersten Element */
    public static array $vdiff = [];

    public function renderBlock(Block $block): string
    {
        $inner = $this->renderInner($block);
        if ($inner === null) {
            return app()->editing ? '<p>Renderer für „' . e($block->type) . '“ fehlt.</p>' : '';
        }
        if (app()->editing && $block->parent === null && $block->inRow() && $this->rowable($block)) {
            // Editor (Vorschau je Block): Block „neben dem vorigen“ schon als Zelle einer Reihe – Hintergrund/Abstände der Reihe
            // und „Karte ja/nein“ setzt editor.js (layoutRows) nach dem ersten Block der Reihe
            $this->rowCss = true;
            $cls = ['sec-row__cell', 'sec-row__cell--w12', 'sec--' . str_replace('_', '-', $block->type), 'sec', 'sec-row__cell--card', 'bg-' . $block->bg()];
            if ($block->variant()) $cls[] = 'v-' . $block->variant();
            $html = '<section id="' . e($block->domId()) . '" class="sec sec--row-preview bg-' . e($block->bg()) . '" data-row-bg="' . e($block->bg()) . '">'
                . '<div class="' . e(trim((string) ($this->def['rows']['wrap'] ?? 'wrap') . ' sec-row')) . '"><div class="' . e(implode(' ', $cls)) . '">' . $inner . '</div></div></section>';
            return EditorNotes::$show ? EditorNotes::decorate($html) : $html;
        }
        $html = !empty($block->def['raw']) ? $inner   // Block rendert seinen Abschnitt selbst
            : $this->render('partials/section', ['b' => $block, 'inner' => $inner]);
        // Abschnitts-Option „Glossar-Begriffe hier nicht markieren“: Kennzeichen am äußersten Element (Core\Glossary\Annotator)
        if (!empty($block->tunes['noGlossary']) && !app()->editing) {
            $html = (string) preg_replace('~^(\s*<[a-zA-Z][a-zA-Z0-9-]*)(?=[\s>/])~', '$1 data-glossary="off"', $html, 1);
        }
        if (isset(self::$vdiff[$block->id])) {
            $html = (string) preg_replace('~^(\s*<[a-zA-Z][a-zA-Z0-9-]*)(?=[\s>/])~', '$1 data-vdiff="' . e(self::$vdiff[$block->id]) . '"', $html, 1);
        }
        // Redaktion (Bearbeiten-Modus, Entwurfsansicht): Notizen als Hinweis „Notiz: …“ (Core\EditorNotes)
        return EditorNotes::$show ? EditorNotes::decorate($html) : $html;
    }

    /** Inhalt eines Blocks ohne Abschnitts-Hülle (Block-Vorlage des Kits, Kern-Block, Erweiterung, eigener Block); null = kein Renderer */
    public function renderInner(Block $block): ?string
    {
        $file = $this->path . '/blocks/' . $block->type . '.php';
        if (!is_file($file) && !empty($block->def['core'])) {
            $file = ROOT . '/app/Blocks/' . $block->type . '.php';
        }
        if (!is_file($file) && !empty($block->def['dir'])) {
            $file = $block->def['dir'] . '/' . $block->type . '.php';   // Block einer Erweiterung
        }
        $custom = !empty($block->def['custom']);   // eigener Block: sichere Vorlagensprache statt PHP-Datei
        if (!$custom && !is_file($file)) {
            return null;
        }
        if ($block->tunes['height'] === 'screen' || $block->tunes['bgImage']) {
            $this->sectionCss = true;
        }
        // Bild anpassen je Einbindung (data._fx, Core\ImageFx): gilt für Bilder, die dieser Block selbst ausgibt
        ImageFx::enter($block->data);
        ImageFit::enter($block->data, $block->def['fields'] ?? []);   // Bild im Rahmen je Einbindung (data._fit je Feldpfad, Core\ImageFit)
        try {
            return $custom ? Blocks\Custom::render($block) : self::capture($file, ['b' => $block, 'd' => $block->data]);
        } finally {
            ImageFit::leave();
            ImageFx::leave();
        }
    }

    /** Öffentliche Adresse einer Theme-Schrift ohne Versions-Parameter (muss exakt der URL im CSS entsprechen) */
    public function fontUrl(string $path): string
    {
        return Kit::url($this->name, $path);
    }

    /** <link rel="preload"> für die wichtigsten Schnitte (theme.php → 'fonts' → 'preload') */
    public function fontPreloads(): string
    {
        $h = '';
        foreach ((array) ($this->def['fonts']['preload'] ?? []) as $f) {
            $h .= '<link rel="preload" href="' . e($this->fontUrl($f)) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
        return $h;
    }

    /** TTF für den App-Icon-Generator (theme.php → 'fonts' → 'icon', relativ zum Theme-Ordner) */
    public function iconFont(): ?string
    {
        $f = (string) ($this->def['fonts']['icon'] ?? '');
        $path = $f !== '' ? $this->path . '/' . ltrim($f, '/') : '';
        return $path !== '' && is_file($path) ? $path : null;
    }

    public function asset(string $path): string
    {
        $file = Kit::publicDir($this->name) . '/' . ltrim($path, '/');
        $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : CMS_VERSION;
        return Kit::url($this->name, $path) . '?v=' . $v;
    }

    /** Bringt das Kit die öffentliche Datei mit (public/assets/kits/{name}/…)? */
    public function hasAsset(string $path): bool
    {
        return is_file(Kit::publicDir($this->name) . '/' . ltrim($path, '/'));
    }

    /**
     * Stylesheets, die für die übergebenen Blocktypen zusätzlich nötig sind (null = alle).
     * Pseudo-Typ „@rich“ in conditional_css: Datei lädt, sobald die Seite Rich-Text-Stile ausgibt (Überschriften, Zitat,
     * t-lead/t-small/t-note, Textfarben c-*, mark, sup/sub – Core\Sanitizer::styled(); Inhalt vorher rendern).
     */
    public function conditionalCss(?array $types): array
    {
        if ($types !== null) $types = $this->withUses($types);
        if ($types !== null && Sanitizer::styled()) $types[] = '@rich';
        // Stelle: Bewerbung (job_apply) ist ein Formular wie „Formular (Datentabelle)“ – gleiche Stile (Kern und Kit)
        if ($types !== null && in_array('job_apply', $types, true)) $types[] = 'data_form';
        // Live-Galerie nutzt das Markup der Bildergalerie (cms-gallery) – gleiche Stile (Kern und Kit)
        if ($types !== null && in_array('live_gallery', $types, true)) $types[] = 'gallery';
        $out = [];
        // Kern-Blöcke: Theme-Stylesheet css/data.css bevorzugt, sonst das neutrale aus dem Kern
        if ($types === null || array_intersect(['data_list', 'data_fields'], $types)) {
            $out[] = $this->hasAsset('css/data.css') ? $this->asset('css/data.css') : asset('css/data.css');
        }
        // Medien-Blöcke (Galerie, Slider, Stapelkarten) und Abschnitte mit Vollbild/Hintergrundbild – ebenso überschreibbar
        if ($types === null || array_intersect(['gallery', 'slideshow', 'stack_cards'], $types)) {
            $out[] = $this->coreCss('media.css');
        }
        // Kalender-Blöcke (Monatsübersicht, Nächste Termine) – Theme kann css/calendar.css mitbringen
        if ($types === null || array_intersect(['calendar', 'upcoming'], $types)) {
            $out[] = $this->coreCss('calendar.css');
        }
        // Formular (Datentabelle) – Theme kann css/dataform.css mitbringen
        if ($types === null || in_array('data_form', $types, true)) {
            $out[] = $this->coreCss('dataform.css');
        }
        // Stellenangebote: Eckdaten + gesperrtes Feld „Stelle“ (Core\Data\Jobs) – Kit kann css/jobs.css mitbringen
        if ($types === null || array_intersect(['job_facts', 'job_apply'], $types)) {
            $out[] = $this->coreCss('jobs.css');
        }
        // Kennzahlen mit Skala – Variablen --dial-* aus dem Theme, oder eigene css/dials.css
        if (($types === null || in_array('dials', $types, true)) && !empty($this->blocks['dials']['core'])) {
            $out[] = $this->coreCss('dials.css');
        }
        // Partner & Logos – Variablen --partners-* aus dem Kit, oder eigene css/partners.css
        if (($types === null || in_array('partners', $types, true)) && !empty($this->blocks['partners']['core'])) {
            $out[] = $this->coreCss('partners.css');
        }
        // 404-Vorschläge (Seite „Nicht gefunden“, Core\NotFound) – auch mit eigenem Renderer des Kits (Kästen aus NotFound::boxes);
        // Variablen --nf-* oder eigene css/notfound.css
        if ($types === null || in_array('not_found', $types, true)) {
            $out[] = $this->coreCss('notfound.css');
        }
        if ($types === null || $this->sectionCss) {
            $out[] = $this->coreCss('sections.css');
        }
        // Blöcke nebeneinander (Tune „row“) – Variablen --row-gap, --row-stack, --row-align, --row-card-pad, --row-card-radius
        // oder eigene css/rows.css des Kits
        if (($types === null && ($this->def['rows'] ?? true) !== false) || $this->rowCss) {
            $out[] = $this->coreCss('rows.css');
        }
        // Layout (Spalten, Core\Layout) – Variablen --lay-gap*, --lay-stack, --lay-stack-tablet, --lay-item-gap, --lay-card-* oder css/layout.css des Kits
        if (($types === null && isset($this->blocks[Layout::TYPE])) || ($types !== null && in_array(Layout::TYPE, $types, true))) {
            $out[] = $this->coreCss('layout.css');
        }
        // Glossar (Block „Glossar“; Hinweise im Text bindet Core\Glossary\Glossary::page ein) – Kern + optional css/glossary.css des Kits
        if (($types === null || in_array('glossary', $types, true)) && Features::on('glossary', false)) {
            array_push($out, ...Glossary\Glossary::stylesheets(true));
        }
        foreach ($this->def['conditional_css'] ?? [] as $file => $needs) {
            if (str_ends_with($file, '.css') && ($types === null || array_intersect($needs, $types))) {
                $out[] = $this->asset($file);
            }
        }
        // Eigene Blöcke: statische CSS-Datei je Block (Medienordner), Lightbox braucht die Kern-Styles media.css
        $cb = Blocks\Custom::assets($types);
        if ($cb['media']) $out[] = $this->coreCss('media.css');
        return array_values(array_unique(array_merge($out, $cb['css'])));
    }

    /**
     * Blocktypen um das ergänzen, was eine Variante zusätzlich nutzt: theme.php → blocks → {typ} → 'uses' => ['variante' => ['dials', …]]
     * (z. B. Einstieg „Kennzahlen“ → Stylesheet der Kern-Kennzahlen). Erwartet Einträge „typ:variante“ in $types.
     */
    private function withUses(array $types): array
    {
        foreach ($types as $t) {
            if (!is_string($t) || !str_contains($t, ':')) continue;
            [$type, $variant] = explode(':', $t, 2);
            foreach ((array) ($this->blocks[$type]['uses'][$variant] ?? []) as $u) $types[] = (string) $u;
        }
        return array_values(array_unique($types));
    }

    /** Kern-Stylesheet – oder die gleichnamige Datei des Kits (public/assets/kits/{name}/css/…), falls vorhanden */
    private function coreCss(string $file): string
    {
        return $this->hasAsset('css/' . $file) ? $this->asset('css/' . $file) : asset('css/' . $file);
    }

    /** Skripte, die nur für bestimmte Blocktypen geladen werden */
    public function conditionalJs(array $types): array
    {
        $types = $this->withUses($types);
        $out = [];
        foreach ($this->def['conditional_css'] ?? [] as $file => $needs) {
            if (str_ends_with($file, '.js') && array_intersect($needs, $types)) {
                $out[] = $this->asset($file);
            }
        }
        return $out;
    }

    /**
     * Skripte, die auch im Seiten-Editor laufen dürfen (theme.php → 'editor_js' => ['js/b-loop.js', …]). Sonst lädt der Editor
     * bewusst keine Kit-Skripte (sie könnten Klicks und Eingaben abfangen). Gedacht für Videos, Animationen u. Ä., die Blöcke
     * auch nach dem Neuzeichnen einrichten (Ereignis cms:block-preview).
     */
    public function editorJs(): array
    {
        $out = [];
        foreach ((array) ($this->def['editor_js'] ?? []) as $file) {
            if (is_string($file) && str_ends_with($file, '.js') && !str_contains($file, '..')) $out[] = $this->asset($file);
        }
        return $out;
    }

    /** Navigation aus Blöcken der Startseite mit Tune „showInNav“ */
    public function navigation(): array
    {
        // Landing-Domain (Core\Landings): Abschnitte der Einstiegsseite statt der Startseite
        $home = Landings::current()?->root() ?? Pages::home();
        if (!$home) {
            return [];
        }
        $current = app()->currentPage;
        $onHome = $current && (int) $current['id'] === (int) $home['id'];
        $items = [];
        foreach (Pages::blocks($home, app()->editing || (app()->auth->check() && $onHome)) as $b) {
            $t = $b['tunes']['section'] ?? [];
            if (!empty($t['showInNav']) && !empty($t['anchor']) && ($t['visible'] ?? true)) {
                $items[] = [
                    'id' => $t['anchor'],
                    'href' => ($onHome ? '' : url('/')) . '#' . $t['anchor'],
                    'label' => $t['navLabel'] ?: strip_tags((string) ($b['data']['title_strong'] ?? $t['anchor'])),
                ];
            }
        }
        return $items;
    }

    /** Editor-Konfiguration (für Editor.js-Tools) */
    public function editorConfig(): array
    {
        $blocks = [];
        foreach ($this->blocks as $type => $b) {
            // Formular einer Datentabelle im Block (Knopf „Felder bearbeiten“, resources/js/_form_fields.js): 'formfields' => Feldname,
            // sonst das erste Tabellen-Feld, das Formular-Tabellen anbietet ('type' => 'datatable', 'inbox' => true – z. B. data_form, contact)
            $ff = $b['formfields'] ?? null;
            if ($ff === null) {
                foreach ($b['fields'] as $f) {
                    if (($f['type'] ?? '') === 'datatable' && !empty($f['inbox']) && !empty($f['name'])) { $ff = (string) $f['name']; break; }
                }
            }
            $blocks[$type] = [
                'label' => $b['label'], 'icon' => $b['icon'] ?? '▦', 'ico' => Icons::resolve((string) ($b['icon'] ?? '▦')), 'group' => $b['group'] ?? 'Inhalt',
                'fields' => $b['fields'], 'variants' => $b['variants'] ?? null,
                'central' => $b['central'] ?? null, 'background' => $b['background'] ?? 'white',
                'help' => $b['help'] ?? null,
                'insertable' => Features::allowsBlock($type) && ($b['insertable'] ?? true),   // zurückgezogene eigene Blöcke: nicht einfügbar
                'formfields' => $ff ?: null,
                'raw' => !empty($b['raw']),   // eigene Abschnitts-Hülle → nicht „neben den vorigen Block“
                'nestable' => $this->nestableVariants($type),   // in einer Spalte des Layouts erlaubt (true | Varianten | false)
            ];
        }
        // rows: nur noch Darstellung alter Reihen (Tune row) – die Option ist im Editor abgeschafft (Block „Layout“)
        return ['blocks' => $blocks, 'backgrounds' => $this->backgrounds(), 'rows' => ($this->def['rows'] ?? true) !== false,
            'layout' => isset($this->blocks[Layout::TYPE]) ? ['presets' => Layout::PRESETS] : null,
            'glossary' => Features::on('glossary', false)];   // Abschnitts-Option „Glossar-Begriffe hier nicht markieren“
    }
}
