<?php
declare(strict_types=1);

namespace Core;

/**
 * Spielt die Startinhalte des aktiven Kits ein (kits/{name}/seed.php) – beim Erststart (Core\Onboarding entscheidet, wann).
 *   full   alles: Einstellungen, Seiten, Seitenreferenzen und 'after' (weitere Inhalte, z. B. Demo mit Bildern)
 *   empty  ohne Startinhalte: Einstellungen des Kits ohne Beispiel-Angaben (Demo-Name, Platzhalter, example.com, Links auf
 *          Musterseiten), eine leere Startseite und nur die Seiten, auf die Einstellungen verweisen (page_refs – Impressum,
 *          Datenschutz …) als Vorlagen; kein 'after'
 */
final class Seeder
{
    public function __construct(private App $app) {}

    public function run(string $mode = 'full'): void
    {
        $empty = $mode === 'empty';
        $file = $this->app->theme->path . '/seed.php';
        if (!is_file($file)) {
            return;
        }
        $seed = require $file;
        if ($empty) {
            // Nur Seiten, auf die Einstellungen verweisen (Rechtstexte), dazu eine leere Startseite
            $keep = array_flip(array_map('strval', array_values((array) ($seed['page_refs'] ?? []))));
            $home = null;
            foreach ((array) ($seed['pages'] ?? []) as $p) if (!empty($p['is_home'])) { $home = $p; break; }
            $pages = [['slug' => (string) ($home['slug'] ?? 'start'), 'title' => (string) ($home['title'] ?? __('Startseite')), 'is_home' => true, 'blocks' => []]];
            foreach ((array) ($seed['pages'] ?? []) as $p) if (empty($p['is_home']) && isset($keep[(string) ($p['slug'] ?? '')])) $pages[] = $p;
            $seed['pages'] = $pages;
            unset($seed['after']);
            // Beispiel-Angaben leeren (Name „… (Demo)“, Platzhalter in [Klammern], example.com, Muster-/Beispielwerte) und Links
            // auf Musterseiten, die es ohne Startinhalte nicht gibt (z. B. Kopf-Button „/kontakt“) samt Beschriftung
            foreach ((array) ($seed['settings'] ?? []) as $k => $v) {
                if (!is_string($v)) continue;
                if (preg_match('~\(Demo\)|\bBeispiel|\bMuster|example\.(com|org|net)|^\[.*\]$~u', $v)) $seed['settings'][$k] = '';
                if (str_ends_with((string) $k, '_link') && str_starts_with($v, '/')) {
                    $seed['settings'][$k] = '';
                    $label = substr((string) $k, 0, -5) . '_label';
                    if (array_key_exists($label, $seed['settings'])) $seed['settings'][$label] = '';
                }
            }
        }

        $this->app->db->transaction(function () use ($seed) {
            foreach ($seed['settings'] ?? [] as $k => $v) {
                $this->app->settings->set($k, $v);
            }
            foreach (SystemSchema::defaults() as $k => $v) {
                if (!$this->app->settings->has($k)) {
                    $this->app->settings->set($k, $v);
                }
            }
            foreach ($seed['pages'] ?? [] as $i => $p) {
                $blocks = array_map(fn($b) => [
                    'id' => substr(bin2hex(random_bytes(6)), 0, 10),
                    'type' => $b['type'],
                    'data' => $b['data'] ?? [],
                    'tunes' => ['section' => $b['tunes'] ?? []],
                ], $p['blocks'] ?? []);
                $blocks = Pages::sanitizeBlocks($blocks);
                Pages::create([
                    'slug' => $p['slug'], 'title' => $p['title'], 'meta_description' => $p['meta_description'] ?? '',
                    'is_home' => !empty($p['is_home']) ? 1 : 0, 'sort' => $i, 'status' => $p['status'] ?? 'published',
                    'noindex' => !empty($p['noindex']) ? 1 : 0,
                ] + (array_key_exists('menu', $p) ? ['menu' => $p['menu'] ? 1 : 0] : []) + (isset($p['nav_title']) ? ['nav_title' => (string) $p['nav_title']] : []), $blocks);
            }
        });
        // Seitenreferenzen (Impressum usw.) auf IDs auflösen
        foreach ($seed['page_refs'] ?? [] as $key => $slug) {
            $page = Pages::bySlug($slug);
            if ($page) {
                $this->app->settings->set($key, (int) $page['id']);
            }
        }
        // Optional: weitere Startinhalte des Themes (z. B. Seitenbaum, Datentabellen, Bilder) – seed.php → 'after' => callable
        if (is_callable($seed['after'] ?? null)) {
            ($seed['after'])($this->app);
        }
    }
}
