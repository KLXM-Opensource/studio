<?php
declare(strict_types=1);

namespace Core;

/**
 * Rechtliche Seiten zentral (für Kern-Fragmente und Kits – statt praxis_privacy_url(), basis_legal_links() …):
 * Impressum, Datenschutzerklärung, Erklärung zur Barrierefreiheit in der Sprache der aufgerufenen Seite.
 *
 * Quelle: Einstellungen der zentralen Angaben (privacy_page, imprint_page, accessibility_page; ältere Kits
 * datenschutz_seite, impressum_seite; theme.php → 'privacy_settings' ergänzt die Schlüssel), für den Datenschutz
 * zuletzt eine veröffentlichte Seite „datenschutz“/„privacy“ (Core\Data\DataForms::privacyPage).
 * Helfer: privacy_url(), legal_links(), org_name().
 */
final class Legal
{
    /** Einstellungs-Schlüssel je Art (in dieser Reihenfolge geprüft) */
    public const KEYS = [
        'imprint' => ['imprint_page', 'impressum_seite'],
        'privacy' => ['privacy_page', 'datenschutz_seite'],
        'accessibility' => ['accessibility_page', 'barrierefreiheit_seite'],
    ];

    /** Seite einer Art (übersetzt in die aktuelle Sprache) – nur veröffentlichte, für Angemeldete auch Entwürfe */
    public static function page(string $which): ?array
    {
        $keys = self::KEYS[$which] ?? [];
        if ($which === 'privacy') $keys = array_values(array_unique(array_merge($keys, (array) (app()->theme->def['privacy_settings'] ?? []))));
        foreach ($keys as $k) {
            $id = (int) setting($k);
            $p = $id ? Pages::find($id) : null;
            if (!$p) continue;
            if (Lang::multi()) $p = Pages::translations($p)[Lang::current()] ?? $p;
            if ($p['status'] === 'published' || app()->auth->check()) return $p;
        }
        return $which === 'privacy' ? Data\DataForms::privacyPage() : null;
    }

    /** Adresse der Datenschutzerklärung – ohne Seite die Startseite (ein Link muss immer da sein, z. B. im 2-Klick-Video) */
    public static function privacyUrl(): string
    {
        $p = self::page('privacy');
        return $p ? Pages::url($p) : url('/');
    }

    /** Links Impressum / Datenschutz / Barrierefreiheit: [['key' => 'privacy_page', 'type' => 'privacy', 'label', 'href']] */
    public static function links(): array
    {
        $out = [];
        foreach (['imprint' => lt('Impressum'), 'privacy' => lt('Datenschutz'), 'accessibility' => lt('Barrierefreiheit')] as $type => $label) {
            $p = self::page($type);
            if ($p) {
                $out[] = ['key' => self::KEYS[$type][0], 'type' => $type, 'label' => $label, 'href' => Pages::url($p)];
            }
        }
        return $out;
    }

    /** Name der Organisation (zentrale Angaben org_name / short_name, sonst Name der Website) */
    public static function orgName(bool $short = false): string
    {
        $name = trim((string) setting('org_name'));
        $shortName = trim((string) setting('short_name'));
        if ($short && $shortName !== '') return $shortName;
        return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
    }
}
