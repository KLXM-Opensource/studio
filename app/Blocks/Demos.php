<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;

/**
 * Beispiel-Blöcke des Block-Designers (Verwaltung → Blöcke → Beispiele, php bin/console blocks:demos).
 *
 * Mitgeliefert unter app/Blocks/demos/{name}.json im Exportformat („klxm-studio-block“) plus Angaben zum Lernen:
 *   "demo": {"order": 1, "level": "Einstieg", "teaches": "…", "concepts": ["…"], "hints": {"felder"|"vorlage"|"css"|"beispiel"|"einstellungen": "…"}}
 * Übernehmen legt immer eine Kopie als Entwurf an (Custom::import, Verlauf „Aus Beispiel“) – nie wird ein Block überschrieben.
 * Die Herkunft steht in settings.demo; der Block-Designer zeigt dazu die Hinweise „Was zeigt dieses Beispiel?“.
 * Leere Bild- bzw. Datumsfelder der Beispieldaten werden beim Übernehmen mit dem neuesten Bild der Mediathek bzw. einem Datum
 * in drei Wochen gefüllt, damit die Vorschau sofort etwas zeigt.
 */
final class Demos
{
    public const DIR = __DIR__ . '/demos';
    public const HINT_TABS = ['felder', 'vorlage', 'css', 'beispiel', 'einstellungen'];

    private static ?array $all = null;

    /** @return array<string, array{name: string, order: int, level: string, teaches: string, concepts: list<string>, hints: array<string, string>, block: array}> */
    public static function all(): array
    {
        if (self::$all !== null) return self::$all;
        $out = [];
        foreach (glob(self::DIR . '/*.json') ?: [] as $f) {
            $name = basename($f, '.json');
            if (!preg_match('~^[a-z][a-z0-9_]{1,30}$~', $name)) continue;
            $d = json_decode((string) file_get_contents($f), true);
            if (!is_array($d) || !in_array($d['format'] ?? '', Custom::FORMATS, true) || !is_array($d['block'] ?? null)) continue;
            $m = (array) ($d['demo'] ?? []);
            $out[$name] = [
                'name' => $name, 'order' => (int) ($m['order'] ?? 99), 'level' => (string) ($m['level'] ?? ''),
                'teaches' => (string) ($m['teaches'] ?? ''), 'concepts' => array_values(array_map('strval', (array) ($m['concepts'] ?? []))),
                'hints' => array_intersect_key(array_map('strval', (array) ($m['hints'] ?? [])), array_flip(self::HINT_TABS)),
                'block' => $d['block'],
            ];
        }
        uasort($out, fn($a, $b) => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);
        return self::$all = $out;
    }

    public static function find(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }

    /** Normalisierte Definition (wie nach dem Import) mit Herkunft und ergänzten Beispieldaten */
    public static function definition(string $name): ?array
    {
        $d = self::find($name);
        if (!$d) return null;
        $b = $d['block'];
        $b['settings'] = (array) ($b['settings'] ?? []) + ['demo' => $name];
        $b['settings']['demo'] = $name;
        [$def] = Custom::normalize($b, (string) ($b['key'] ?? $name));
        $def['sample'] = self::fillSample($def['fields'], $def['sample']);
        return $def;
    }

    /** Leere Bild-/Datumsfelder der Beispieldaten füllen (auch in Listen) */
    private static function fillSample(array $fields, array $sample): array
    {
        static $img = null;
        foreach ($fields as $f) {
            $n = $f['name'];
            $v = $sample[$n] ?? null;
            if ($f['type'] === 'media' && !$v) {
                $img ??= (int) app()->db->fetchValue("SELECT id FROM media WHERE mime LIKE 'image/%' AND mime <> 'image/svg+xml' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
                $sample[$n] = $img ?: null;
            } elseif ($f['type'] === 'date' && !$v) {
                $sample[$n] = date('Y-m-d', strtotime('+21 days'));
            } elseif ($f['type'] === 'repeater' && is_array($v)) {
                $sample[$n] = array_map(fn($row) => is_array($row) ? self::fillSample((array) $f['fields'], $row) : $row, $v);
            }
        }
        return $sample;
    }

    /** Eigene Blöcke, die aus diesem Beispiel übernommen wurden (Schlüssel) */
    public static function installed(string $name): array
    {
        return array_values(array_map(fn($b) => $b['key'], array_filter(Custom::all(), fn($b) => ($b['settings']['demo'] ?? '') === $name)));
    }

    /**
     * Beispiel als neuen Entwurf übernehmen. Kurzname und Bezeichnung werden eindeutig gemacht (hinweisbox_2, „Hinweisbox 2“).
     * $once: nichts tun, wenn es schon eine Kopie gibt (Kommandozeile, wiederholbar).
     * @return array{key: ?string, errors: array, existing: bool}
     */
    public static function install(string $name, int $userId, bool $once = false): array
    {
        $def = self::definition($name);
        if (!$def) return ['key' => null, 'errors' => ['demo' => __('Beispiel „{name}“ nicht gefunden.', ['name' => $name])], 'existing' => false];
        if ($once && ($have = self::installed($name))) return ['key' => $have[0], 'errors' => [], 'existing' => true];
        $labels = array_column(Custom::all(), 'label');
        $label = $def['label'];
        for ($i = 2; in_array($label, $labels, true); $i++) $label = $def['label'] . ' ' . $i;
        $def['label'] = $label;
        $json = (string) json_encode(['format' => Custom::FORMAT, 'format_version' => 1, 'block' => $def], JSON_UNESCAPED_UNICODE);
        return Custom::import($json, $userId, 'demo') + ['existing' => false];
    }

    // ------------------------------------------------------------------ Vorschau in der Übersicht

    /** Block mit Beispieldaten, gerendert wie auf einer Seite (ohne Hülle des Kits) – für Selbsttest und Vorschau */
    public static function block(string $name): ?Block
    {
        $def = self::definition($name);
        if (!$def) return null;
        $type = Custom::type($def['key']);
        $tdef = Custom::themeDef($def + ['css_file' => ''], 'published') + ['type' => $type];
        if (isset($tdef['background']) && !isset(app()->theme->backgrounds()[$tdef['background']])) unset($tdef['background']);
        $data = array_replace(\Core\Fields::defaults($def['fields']), $def['sample']);
        return new Block('demo', $type, $data, app()->theme->sanitizeTunes([], $tdef), $tdef);
    }

    /** Ganze Seite des aktiven Kits mit dem Beispiel-Block (iframe der Übersicht); CSS über css() */
    public static function page(string $name, string $cssUrl): ?string
    {
        $b = self::block($name);
        $home = \Core\Pages::home();
        if (!$b || !$home) return null;
        $theme = app()->theme;
        app()->currentPage = $home;
        \Core\StructuredData::reset();
        $section = $theme->renderBlock($b);
        $css = $theme->conditionalCss(in_array('lightbox', (array) $b->def['cblk']['behaviours'], true) ? [$b->type, 'gallery'] : [$b->type]);
        $css[] = $cssUrl;
        return $theme->render('layout', [
            'page' => $home, 'content' => $section, 'seo' => ['noindex' => true] + \Core\Seo::forPage($home), 'editor' => null,
            'extraCss' => $css, 'extraJs' => [], 'toolbar' => null,
        ]);
    }

    /**
     * CSS des Beispiels für die Vorschau: Block-CSS plus Ausblenden von Kopf, Fuß und übrigen Inhalten der Seite
     * (alles, was weder den Block enthält noch in ihm liegt).
     */
    public static function css(string $name): string
    {
        $def = self::definition($name);
        if (!$def) return '';
        $r = Css::compile($def['css'], $def['key'], $def['behaviours']);
        $id = '#b-demo';
        return $r['css'] . "\nbody *:not(:has($id)):not($id):not($id *){display:none!important}"
            . "body{min-height:0!important;padding:0!important}main{padding:0!important;margin:0!important}$id{margin:0!important}html{scroll-behavior:auto}";
    }
}
