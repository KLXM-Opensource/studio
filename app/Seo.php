<?php
declare(strict_types=1);

namespace Core;

/** Meta-Daten, Canonical, Open Graph und JSON-LD (JSON-LD liefert das Theme). */
final class Seo
{
    public static function forPage(array $page): array
    {
        $s = app()->settings;
        $theme = app()->theme->def;
        $suffix = (string) setting($theme['seo']['title_suffix'] ?? 'site_title_suffix', '');
        if ($suffix === '' && !empty($theme['seo']['title_suffix_fallback'])) {
            $suffix = (string) setting($theme['seo']['title_suffix_fallback'], '');
        }
        $homeTitle = (string) setting($theme['seo']['home_title'] ?? 'site_title', '');
        // Landing-Domain (Core\Landings): eigener Name im Titel, eigenes Vorschaubild
        $lp = Landings::current();
        if ($lp && $lp->name !== null) $suffix = $lp->name;

        $title = $page['is_home'] && $homeTitle !== '' ? $homeTitle : $page['title'];
        // Eigener Titel für Suchmaschinen (Seiteneinstellungen → „Titel für Suchmaschinen“) hat Vorrang
        if (trim((string) ($page['meta_title'] ?? '')) !== '') $title = trim((string) $page['meta_title']);
        if ($suffix !== '' && !str_contains($title, $suffix)) {
            $title .= ' | ' . $suffix;
        }
        $desc = trim((string) ($page['meta_description'] ?: setting($theme['seo']['description'] ?? 'default_meta_description', '')));
        $ogId = $page['og_image'] ?: ($lp?->ogImage ?: setting($theme['seo']['og_image'] ?? 'og_default_image'));
        $og = $ogId ? Media::find((int) $ogId) : null;


        // hreflang: alle veröffentlichten Übersetzungen dieser Seite. Canonical/hreflang folgen den Landingpages:
        // „Eigene Domain“ → Adresse der Landing-Domain (auch auf der Hauptdomain), „Spiegel“ → Adressen der Hauptdomain
        $links = function () use ($page): array {
            $alternates = [];
            if (Lang::multi() && !empty($page['id'])) {
                foreach (Pages::translations($page) as $l => $tp) {
                    if ($tp['status'] === 'published' && Lang::valid($l)) $alternates[$l] = Landings::canonical($tp) ?? abs_url(Pages::url($tp));
                }
                if (count($alternates) < 2) $alternates = [];
            }
            return [$alternates, Landings::canonical($page) ?? abs_url(Pages::url($page))];
        };
        $mirror = $lp && $lp->mode === 'mirror';
        [$alternates, $canonical] = $mirror ? Landings::suspend($links) : $links();
        $out = [
            'alternates' => $alternates,
            'lang' => Lang::current(),
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            // SVG zeigen soziale Netzwerke nicht als Vorschaubild
            'og_image' => $og && $og['mime'] !== Svg::MIME && !MediaPools::mediaProtected($og) ? site_url() . Media::url($og, 1200) : null,
            'noindex' => !empty($page['noindex']) || $page['status'] !== 'published' || noindex_site() || ($lp?->noindex ?? false),
        ];
        // schema.org-Graph: Organisation (Theme), WebSite, WebPage, Brotkrumen + Daten der Blöcke (Spiegel: wie auf der Hauptdomain)
        $out['jsonld'] = $mirror ? Landings::suspend(fn() => StructuredData::graph($page, null, null, $out)) : StructuredData::graph($page, null, null, $out);
        return $out;
    }

    /** Detailseite eines Datensatzes: Titel, Beschreibung, Bild und Canonical aus dem Eintrag */
    public static function forEntry(array $template, array $table, array $entry): array
    {
        $seo = self::forPage($template + ['is_home' => 0]);
        $suffix = explode(' | ', $seo['title'], 2)[1] ?? '';
        $title = Data\Entries::title($table, $entry);
        $seo['title'] = $title . ($suffix !== '' ? ' | ' . $suffix : '');
        if ($table['settings']['description_field'] !== '') {
            $d = Data\Entries::text($table, $entry, $table['settings']['description_field']);
            if ($d !== '') $seo['description'] = mb_strimwidth(preg_replace('~\s+~', ' ', $d), 0, 160, '…');
        }
        $imgField = \Core\Data\Tables::imageField($table);
        $img = $imgField !== '' ? ($entry[$imgField] ?? null) : null;
        if ($img && ($m = Media::find((int) $img)) && $m['mime'] !== Svg::MIME && !MediaPools::mediaProtected($m)) {
            $seo['og_image'] = site_url() . Media::url($m, 1200);
        }
        // Fremde Einträge geteilter Tabellen: Canonical auf die Ursprungs-Website (falls sie Detailseiten hat)
        $seo['canonical'] = Data\Entries::absUrl($table, $entry);
        // Abgelaufene Stellenangebote (Core\Data\Jobs): nicht mehr in Suchmaschinen
        $seo['noindex'] = $entry['status'] !== 'published' || noindex_site() || !empty($table['settings']['noindex'])   // Tabelle „Nicht indexieren“ (Core\Indexing)
            || (Data\Jobs::is($table) && Data\Jobs::expired($table, $entry));
        $seo['alternates'] = [];
        if (Lang::multi()) {
            foreach (Data\Entries::translations($table, $entry) as $l => $tr) {
                if ($tr['status'] === 'published' && Lang::valid($l) && ($u = Data\Entries::url($table, $tr))) $seo['alternates'][$l] = site_url() . $u;
            }
            if (count($seo['alternates']) < 2) $seo['alternates'] = [];
        }
        $seo['jsonld'] = StructuredData::graph($template, $table, $entry, $seo);
        return $seo;
    }

    public static function forError(int $code): array
    {
        return ['title' => ($code === 404 ? lt('Seite nicht gefunden') : lt('Fehler')), 'description' => '', 'canonical' => null,
            'og_image' => null, 'noindex' => true, 'jsonld' => null];
    }
}
