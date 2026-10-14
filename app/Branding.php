<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Http\Response;

/**
 * Branding der Agentur (System → Branding & Support): eigenes Logo und eigener Name statt der Produktmarke (Anmeldung, Einrichtung,
 * Werkzeugleiste der Website, Begrüßung) und Support-Angaben (Hilfe & Support, Übersicht, Anmeldeseite).
 * Gilt für die ganze Installation: Einzel-Installation → Administration (system.manage), Netzwerk → nur die Netzwerk-Administration
 * (alle Websites). Ablage storage/branding/ (branding.json, logo.* / logo-dark.*), ausgeliefert über /branding/logo[-dark].
 * Optional der Hinweis „Technik: KLXM Studio“ (show_product) – Standard an.
 */
final class Branding
{
    public const KINDS = ['image/svg+xml' => 'svg', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    public const MAX_BYTES = 1048576;
    private static ?array $cfg = null;

    public static function dir(): string
    {
        return ROOT . '/storage/branding';
    }

    /**
     * Wer darf das Branding ändern? Einzel-Installation: Administration; Netzwerk: Netzwerk-Administration. Mehrere Websites ohne
     * Netzwerk-Konten: Administration der Haupt-Website (Network::siteKey).
     */
    public static function canEdit(): bool
    {
        if (!app()->auth->check()) return false;
        if (Domains::available()) return can('system.manage');
        if (Network\Network::isNetworkUser()) return true;
        try {
            $none = !(int) Network\Network::db()->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'network'");
        } catch (\Throwable) {
            $none = false;
        }
        return $none && Network\Network::isNetworkSite() && can('system.manage');
    }

    /** @return array{name:string, show_product:bool, logo:string, logo_dark:string, support:array{name:string,phone:string,email:string,web:string,hours:string,note:string}} */
    public static function get(): array
    {
        if (self::$cfg !== null) return self::$cfg;
        $raw = is_file(self::dir() . '/branding.json') ? json_decode((string) file_get_contents(self::dir() . '/branding.json'), true) : null;
        $raw = is_array($raw) ? $raw : [];
        $s = (array) ($raw['support'] ?? []);
        return self::$cfg = [
            'name' => (string) ($raw['name'] ?? ''), 'show_product' => (bool) ($raw['show_product'] ?? true),
            'logo' => (string) ($raw['logo'] ?? ''), 'logo_dark' => (string) ($raw['logo_dark'] ?? ''),
            'support' => array_map('strval', ['name' => $s['name'] ?? '', 'phone' => $s['phone'] ?? '', 'email' => $s['email'] ?? '',
                'web' => $s['web'] ?? '', 'hours' => $s['hours'] ?? '', 'note' => $s['note'] ?? '']),
        ];
    }

    /** Eigenes Logo oder eigener Name gesetzt? */
    public static function active(): bool
    {
        $c = self::get();
        return $c['logo'] !== '' || trim($c['name']) !== '';
    }

    /** Support-Angaben vorhanden? */
    public static function hasSupport(): bool
    {
        return (bool) array_filter(self::get()['support'], fn($v) => trim($v) !== '');
    }

    public static function save(array $c): void
    {
        if (!is_dir(self::dir())) @mkdir(self::dir(), 0775, true);
        $tmp = self::dir() . '/branding.json.tmp';
        file_put_contents($tmp, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        rename($tmp, self::dir() . '/branding.json');
        self::$cfg = null;
    }

    /**
     * Logo übernehmen (Upload): SVG wird bereinigt (Core\Svg), sonst PNG/JPG/WebP, höchstens 1 MB. $which 'logo' | 'logo_dark'.
     * @return string|null Fehlermeldung
     */
    public static function storeLogo(array $file, string $which): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) return __('Die Datei konnte nicht hochgeladen werden.');
        if ((int) $file['size'] > self::MAX_BYTES) return __('Das Logo ist zu groß (höchstens 1 MB).');
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (Svg::detect($file['tmp_name'], $mime, (string) ($file['name'] ?? ''))) $mime = 'image/svg+xml';
        $ext = self::KINDS[$mime] ?? null;
        if (!$ext) return __('Bitte ein Logo als SVG, PNG, JPG oder WebP hochladen.');
        $data = (string) file_get_contents($file['tmp_name']);
        if ($ext === 'svg') {
            try {
                $data = Svg::clean($data)['svg'];
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }
        }
        if (!is_dir(self::dir())) @mkdir(self::dir(), 0775, true);
        $c = self::get();
        if ($c[$which] !== '') @unlink(self::dir() . '/' . basename($c[$which]));
        $name = ($which === 'logo_dark' ? 'logo-dark' : 'logo') . '-' . substr(sha1($data), 0, 8) . '.' . $ext;
        file_put_contents(self::dir() . '/' . $name, $data);
        $c[$which] = $name;
        self::save($c);
        return null;
    }

    public static function removeLogo(string $which): void
    {
        $c = self::get();
        if ($c[$which] !== '') @unlink(self::dir() . '/' . basename($c[$which]));
        $c[$which] = '';
        self::save($c);
    }

    /** Adresse des Logos (null = keins); dunkle Fassung fällt auf das helle zurück */
    public static function logoUrl(bool $dark = false): ?string
    {
        $c = self::get();
        $f = $dark && $c['logo_dark'] !== '' ? $c['logo_dark'] : $c['logo'];
        return $f !== '' ? url('/branding/' . ($dark && $c['logo_dark'] !== '' ? 'logo-dark' : 'logo')) . '?v=' . substr(md5($f), 0, 8) : null;
    }

    /** Logo ausliefern (öffentlich – erscheint auch auf der Anmeldeseite), lange zwischenspeicherbar (Adresse enthält ?v=) */
    public static function serve(string $which): Response
    {
        $c = self::get();
        $f = basename($which === 'dark' && $c['logo_dark'] !== '' ? $c['logo_dark'] : $c['logo']);
        $path = self::dir() . '/' . $f;
        if ($f === '' || !is_file($path)) return new Response('', 404);
        $mime = array_search(pathinfo($f, PATHINFO_EXTENSION), self::KINDS, true) ?: 'application/octet-stream';
        return (new Response((string) file_get_contents($path), 200))->header('Content-Type', (string) $mime)
            ->header('Cache-Control', 'public, max-age=31536000, immutable')->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    /** Logo als <img> (helle und – falls vorhanden – dunkle Fassung) */
    public static function logoHtml(string $class = 'cms-mark__img'): string
    {
        $c = self::get();
        $alt = trim($c['name']) !== '' ? $c['name'] : '';
        if ($c['logo'] === '') return '';
        $h = '<img class="' . e($class) . ($c['logo_dark'] !== '' ? ' brand-light' : '') . '" src="' . e((string) self::logoUrl()) . '" alt="' . e($alt) . '">';
        if ($c['logo_dark'] !== '') $h .= '<img class="' . e($class) . ' brand-dark" src="' . e((string) self::logoUrl(true)) . '" alt="' . e($alt) . '">';
        return $h;
    }

    /** Ersatz für die Produktmarke (cms_mark): Logo und/oder Name, darunter klein „Technik: KLXM Studio“ */
    public static function mark(): string
    {
        $c = self::get();
        $logo = self::logoHtml();
        $name = trim($c['name']);
        return '<span class="cms-mark cms-mark--brand">' . $logo . ($name !== '' && $logo === '' ? '<b>' . e($name) . '</b>' : '')
            . ($c['show_product'] ? '<small class="cms-mark__by">' . e(__('Technik: {product}', ['product' => CMS_NAME])) . '</small>' : '') . '</span>';
    }

    /** Kleines Zeichen (cms_logo): Logo bzw. Anfangsbuchstabe des Namens */
    public static function icon(): string
    {
        $c = self::get();
        if ($c['logo'] !== '') return self::logoHtml('cms-mark__logo cms-mark__logo--img');
        return '<span class="cms-mark__logo cms-mark__logo--txt" aria-hidden="true">' . e(mb_strtoupper(mb_substr(trim($c['name']), 0, 1))) . '</span>';
    }

    /** Name für Titel/Beschriftungen: Agentur, sonst Produkt */
    public static function name(): string
    {
        return self::active() && trim(self::get()['name']) !== '' ? trim(self::get()['name']) : CMS_NAME;
    }

    /** Support-Angaben als Karte (Hilfe & Support, Übersicht) bzw. kurze Zeile (Anmeldeseite) */
    public static function supportHtml(bool $compact = false): string
    {
        if (!self::hasSupport()) return '';
        $s = self::get()['support'];
        $tel = $s['phone'] !== '' ? tel_href($s['phone']) : null;
        $web = $s['web'] !== '' ? (preg_match('~^https?://~i', $s['web']) ? $s['web'] : 'https://' . $s['web']) : '';
        $parts = array_filter([
            $tel ? '<a href="' . e($tel) . '">' . icon('phone') . ' ' . e($s['phone']) . '</a>' : ($s['phone'] !== '' ? e($s['phone']) : ''),
            $s['email'] !== '' && filter_var($s['email'], FILTER_VALIDATE_EMAIL) ? '<a href="mailto:' . e($s['email']) . '">' . icon('envelope-simple') . ' ' . e($s['email']) . '</a>' : '',
            $web !== '' ? '<a href="' . e($web) . '" target="_blank" rel="noopener">' . icon('globe') . ' ' . e((string) preg_replace('~^https?://~i', '', rtrim($s['web'], '/'))) . '</a>' : '',
        ]);
        $who = $s['name'] !== '' ? $s['name'] : self::get()['name'];
        if ($compact) {
            return '<p class="brand-support brand-support--line">' . e(__('Fragen? {who}', ['who' => $who !== '' ? $who : __('Ihr Support')])) . ' · ' . implode(' · ', $parts) . '</p>';
        }
        return '<section class="adm-card brand-support" aria-labelledby="brand-support-h"><h2 id="brand-support-h">' . icon('lifebuoy') . ' ' . e(__('Ihr Support')) . '</h2>'
            . ($who !== '' ? '<p class="brand-support__who"><b>' . e($who) . '</b></p>' : '')
            . ($parts ? '<p class="brand-support__links">' . implode('', $parts) . '</p>' : '')
            . ($s['hours'] !== '' ? '<p class="brand-support__hours">' . icon('clock') . ' ' . e($s['hours']) . '</p>' : '')
            . ($s['note'] !== '' ? '<p class="brand-support__note">' . nl2br(e($s['note']), false) . '</p>' : '') . '</section>';
    }
}
