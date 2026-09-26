<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Eine Landingpage mit eigener Domain (Core\Landings): zeigt eine Seite (optional mit Unterseiten) der Website unter
 * einer weiteren Domain. Für Themes über landing() erreichbar – z. B. landing()?->logo, landing()?->name.
 */
final class Landing
{
    /** Übersetzungen der Einstiegsseite je Sprache (Cache) */
    private array $roots = [];

    public function __construct(
        public readonly int $id,
        public readonly string $label,
        /** @var list<string> Domains (erste = Hauptdomain der Landingpage, optional mit :port für lokale Tests) */
        public readonly array $hosts,
        public readonly int $pageId,
        public readonly bool $includeSubpages,
        /** own = Canonical auf die Landing-Domain · mirror = Canonical auf die Hauptdomain */
        public readonly string $mode,
        /** own: Besucher der Hauptdomain-Adresse per 301 zur Landing-Domain schicken */
        public readonly bool $redirectMain,
        /** Andere Pfade auf der Landing-Domain zur Hauptdomain weiterleiten (sonst 404) */
        public readonly bool $redirectOther,
        /** Suche auf der Landing-Domain: subtree (nur eigene Seiten) | all (ganze Website) | off */
        public readonly string $search,
        /** full = normales Layout (Menü auf die Landingpage begrenzt) · landing = reduziertes Layout, falls das Theme es anbietet */
        public readonly string $layout,
        public readonly bool $noindex,
        public readonly bool $active,
        public readonly ?string $name = null,
        public readonly ?string $tagline = null,
        public readonly ?int $logo = null,
        public readonly ?int $favicon = null,
        public readonly ?int $ogImage = null,
        /** Design-Überschreibungen: Token-Name => Wert (Farben #RRGGBB, dunkle Werte als „name@dark“, Schriften als Schlüssel) */
        public readonly array $design = [],
    ) {}

    public static function fromRow(array $r): self
    {
        $o = json_decode((string) ($r['options_json'] ?? ''), true);
        $o = is_array($o) ? $o : [];
        $hosts = json_decode((string) ($r['hosts'] ?? '[]'), true);
        $str = fn(string $k) => ($v = trim((string) ($o[$k] ?? ''))) !== '' ? $v : null;
        $int = fn(string $k) => (int) ($o[$k] ?? 0) ?: null;
        return new self(
            (int) $r['id'], (string) ($r['label'] ?? ''), array_values(array_filter(array_map('strval', is_array($hosts) ? $hosts : []))),
            (int) $r['page_id'], (bool) $r['include_subpages'],
            in_array($r['mode'] ?? '', ['own', 'mirror'], true) ? (string) $r['mode'] : 'own',
            (bool) ($o['redirect_main'] ?? false), (bool) ($o['redirect_other'] ?? true),
            in_array($o['search'] ?? '', ['subtree', 'all', 'off'], true) ? (string) $o['search'] : 'subtree',
            ($o['layout'] ?? '') === 'landing' ? 'landing' : 'full',
            (bool) ($o['noindex'] ?? false), (bool) ($r['active'] ?? true),
            $str('name'), $str('tagline'), $int('logo'), $int('favicon'), $int('og_image'),
            is_array($o['design'] ?? null) ? $o['design'] : [],
        );
    }

    /** Hauptdomain der Landingpage (erste Domain) */
    public function host(): string
    {
        return $this->hosts[0] ?? '';
    }

    /** Ursprung ohne Pfad, z. B. https://reisemedizin-moers.de – Schema wie die aktuelle Anfrage (Kommandozeile: https) */
    public function origin(): string
    {
        $r = app()->request ?? null;
        $h = $this->host();
        // Kommandozeile: https, außer für lokale Test-Domains (mit Port, *.localhost) – wie Network::siteUrl
        $secure = $r ? $r->isSecure() : !(str_contains($h, ':') || $h === 'localhost' || str_ends_with($h, '.localhost'));
        return ($secure ? 'https://' : 'http://') . $this->host();
    }

    public function rootPage(): ?array
    {
        return $this->roots['#'] ??= Pages::find($this->pageId);
    }

    /** Einstiegsseite in einer Sprache (Übersetzung der gewählten Seite; null = keine Übersetzung) */
    public function root(?string $lang = null): ?array
    {
        $lang = Lang::norm($lang ?? Lang::current());
        if (!array_key_exists($lang, $this->roots)) {
            $base = $this->rootPage();
            $this->roots[$lang] = $base ? (Lang::multi() ? (Pages::translations($base)[$lang] ?? null) : (Lang::norm($base['lang'] ?? null) === $lang ? $base : null)) : null;
        }
        return $this->roots[$lang];
    }

    /** Pfad einer Seite auf der Landing-Domain („/“, „/impfungen“, „/en/“ …) oder null, wenn sie nicht dazugehört */
    public function relPath(array $page): ?string
    {
        if (empty($page['id']) || ($page['type'] ?? 'page') !== 'page') return null;
        $root = $this->root($page['lang'] ?? null);
        if (!$root) return null;
        $prefix = Lang::prefix($page['lang'] ?? null);
        if ((int) $page['id'] === (int) $root['id']) return $prefix . '/';
        if (!$this->includeSubpages) return null;
        $rp = (string) (($root['path'] ?? '') !== '' ? $root['path'] : $root['slug']);
        $pp = (string) ($page['path'] ?? '');
        return $rp !== '' && str_starts_with($pp, $rp . '/') ? $prefix . '/' . substr($pp, strlen($rp) + 1) : null;
    }

    public function contains(array $page): bool
    {
        return $this->relPath($page) !== null;
    }

    /** Link auf eine Seite, gerendert auf der Landing-Domain: eigene Seiten relativ, alle anderen absolut zur Hauptdomain */
    public function href(array $page): string
    {
        $rel = $this->relPath($page);
        return $rel !== null ? url($rel) : Landings::mainOrigin() . Pages::plainUrl($page);
    }

    /** Absolute Adresse einer Seite auf der Landing-Domain (null = gehört nicht dazu) */
    public function absUrl(array $page): ?string
    {
        $rel = $this->relPath($page);
        return $rel !== null ? $this->origin() . url($rel) : null;
    }

    /** Seite zu einem Pfad auf der Landing-Domain (ohne Sprachpräfix) */
    public function resolve(string $path, ?string $lang = null): ?array
    {
        $root = $this->root($lang);
        $path = trim($path, '/');
        if (!$root) return null;
        if ($path === '') return $root;
        if (!$this->includeSubpages) return null;
        $rp = (string) (($root['path'] ?? '') !== '' ? $root['path'] : $root['slug']);
        $p = Pages::byPath($rp . '/' . $path, $lang);
        return $p && $this->contains($p) ? $p : null;
    }

    /** Veröffentlichte Seiten der Landingpage (alle Sprachen) */
    public function pages(): array
    {
        return array_values(array_filter(Pages::published(), fn($p) => $this->contains($p)));
    }

    /** Seitenbaum (Pages::tree) auf die Unterseiten der Einstiegsseite begrenzen – für das Menü */
    public function subtree(array $tree, ?string $lang = null): array
    {
        $root = $this->root($lang);
        if (!$root || !$this->includeSubpages) return [];
        $find = function (array $nodes) use (&$find, $root): ?array {
            foreach ($nodes as $n) {
                if ((int) $n['page']['id'] === (int) $root['id']) return $n['children'];
                if (($c = $find($n['children'])) !== null) return $c;
            }
            return null;
        };
        return $find($tree) ?? [];
    }

    /**
     * Adresse (relativ zur Hauptdomain, wie im Suchindex) auf die Landing-Domain abbilden:
     * Seiten der Landingpage → relative Adresse, alles andere → absolut zur Hauptdomain.
     */
    public function mapHref(string $mainRel): string
    {
        if ($mainRel === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $mainRel)) return $mainRel;
        [$path, $rest] = array_pad(preg_split('~(?=[?#])~', $mainRel, 2) ?: [$mainRel], 2, '');
        foreach ($this->rootsAll() as $r) {
            $ru = Pages::plainUrl($r);
            $prefix = Lang::prefix($r['lang'] ?? null);
            if ($path === $ru) return url($prefix . '/') . $rest;
            if ($this->includeSubpages && str_starts_with($path, rtrim($ru, '/') . '/')) {
                return url($prefix . '/' . substr($path, strlen(rtrim($ru, '/')) + 1)) . $rest;
            }
        }
        return Landings::mainOrigin() . $mainRel;
    }

    /** Suche auf der Landing-Domain: gehört der Treffer (Zeile aus dem Suchindex) dazu? */
    public function searchAllows(array $doc): bool
    {
        if ($this->search === 'all') return true;
        if (($doc['type'] ?? '') !== 'page') return false;
        $u = preg_split('~[?#]~', (string) ($doc['url'] ?? ''))[0];
        foreach ($this->rootsAll() as $r) {
            $ru = Pages::plainUrl($r);
            if ($u === $ru || ($this->includeSubpages && str_starts_with($u, rtrim($ru, '/') . '/'))) return true;
        }
        return false;
    }

    /** Einstiegsseite in allen Sprachen */
    private function rootsAll(): array
    {
        $base = $this->rootPage();
        if (!$base) return [];
        return Lang::multi() ? array_values(Pages::translations($base)) : [$base];
    }

    // ------------------------------------------------------------------ Design

    /** CSS-Variablen der Überschreibungen (nur Farben und Schriften des Themes) */
    public function designCss(): string
    {
        $tokens = Design::tokens();
        $fonts = Design::fonts();   // inkl. installierter Schriften (Core\Fonts)
        $light = $dark = [];
        foreach ($this->design as $k => $v) {
            $isDark = str_ends_with((string) $k, '@dark');
            $n = $isDark ? substr((string) $k, 0, -5) : (string) $k;
            $t = $tokens[$n] ?? null;
            if (!$t || empty($t['var'])) continue;
            $type = $t['type'] ?? 'color';
            if ($type === 'color' && ($c = Design::color($v))) {
                if ($isDark) $dark[] = $t['var'] . ':' . $c; else $light[] = $t['var'] . ':' . $c;
            } elseif ($type === 'font' && !$isDark && isset($fonts[(string) $v]['stack'])) {
                $light[] = $t['var'] . ':' . $fonts[(string) $v]['stack'];
            }
        }
        if (!$light && !$dark) return '';
        $out = $light ? ':root{' . implode(';', $light) . '}' : '';
        $d = (array) (Design::def()['dark'] ?? []);
        if ($dark && !empty($d['scope'])) {
            $rule = $d['scope'] . '{' . implode(';', $dark) . '}';
            $out .= "\n" . (!empty($d['media']) ? '@media ' . $d['media'] . '{' . $rule . '}' : $rule);
        }
        return $out . "\n";
    }

    /** Öffentliche CSS-Datei der Überschreibungen (bei Bedarf neu erzeugt) → URL, null = keine Überschreibungen */
    public function designUrl(): ?string
    {
        $css = $this->designCss();
        if ($css === '') return null;
        $name = 'landing-' . $this->id . '-' . app()->theme->name . '-' . substr(sha1($css), 0, 10) . '.css';
        $dir = site()->mediaDir('design');
        if (!is_file("$dir/$name")) {
            @mkdir($dir, 0775, true);
            foreach (glob("$dir/landing-" . $this->id . '-*.css') ?: [] as $old) @unlink($old);
            @file_put_contents("$dir/$name", "/* Landingpage „" . str_replace('*/', '', $this->label ?: $this->host()) . "“ – Design-Überschreibungen (automatisch erzeugt) */\n" . $css, LOCK_EX);
        }
        return site()->mediaUrl('design/' . $name);
    }

    /** Schrift-Schlüssel, die die Landingpage für ein Token setzt (für die Schriftdateien in Design::head) */
    public function fontFor(string $token): ?string
    {
        $v = $this->design[$token] ?? null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    // ------------------------------------------------------------------ Ausgabe

    /** Für API, MCP und Verwaltung */
    public function toArray(): array
    {
        $root = $this->rootPage();
        return [
            'id' => $this->id, 'label' => $this->label, 'hosts' => $this->hosts, 'url' => $this->origin() . url('/'),
            'page' => $root ? ['id' => (int) $root['id'], 'title' => $root['title'], 'path' => Pages::plainUrl($root)] : null,
            'include_subpages' => $this->includeSubpages, 'mode' => $this->mode, 'redirect_main' => $this->redirectMain,
            'redirect_other' => $this->redirectOther, 'search' => $this->search, 'layout' => $this->layout, 'noindex' => $this->noindex,
            'active' => $this->active,
            'branding' => array_filter(['name' => $this->name, 'tagline' => $this->tagline, 'logo' => $this->logo, 'favicon' => $this->favicon,
                'og_image' => $this->ogImage, 'design' => $this->design ?: null], fn($v) => $v !== null),
            'pages' => array_map(fn($p) => ['id' => (int) $p['id'], 'title' => $p['title'], 'lang' => Lang::norm($p['lang'] ?? null),
                'url' => $this->absUrl($p)], $this->pages()),
        ];
    }
}
