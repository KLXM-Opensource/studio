<?php
declare(strict_types=1);

namespace Core;

/** Spielt die Startinhalte des aktiven Themes ein (kits/{name}/seed.php). */
final class Seeder
{
    public function __construct(private App $app) {}

    public function run(): void
    {
        $file = $this->app->theme->path . '/seed.php';
        if (!is_file($file)) {
            return;
        }
        $seed = require $file;

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
