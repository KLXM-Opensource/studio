<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Mail;

use Core\Lang;
use Core\Legal;
use Core\Network\Network;
use Core\Pages;
use Core\Sites;

/**
 * Impressum in jeder E-Mail: Core\Mailer hängt es an Text- und HTML-Teil jeder Nachricht (eine Stelle für alle Aufrufer).
 * Quelle je Website: Grundeinstellung „Impressum für E-Mails“ (sys.mail_imprint, Anbieterkennzeichnung als Text) und der Link
 * zur Impressum-Seite (Core\Legal, Einstellung imprint_page, veröffentlicht, in der Sprache der Mail). Netzwerk-Mails
 * (Mailer-Option network) nehmen die Angaben der Netzwerk-Website.
 *
 * Mails mit eigenem Layout, die das Impressum selbst setzen (z. B. Newsletter), rufen get() und übergeben dem Mailer
 * ['imprint' => 'included'] – so steht es nie doppelt in der Nachricht.
 * Fehlt beides, geht die Mail trotzdem hinaus (Passwort-Reset darf nie blockieren); health meldet einen Fehler und
 * Massenversand (bulkError()) wird gesperrt.
 */
final class Imprint
{
    public const SETTING = 'sys.mail_imprint';

    /** @var array<string, array{text: string, html: string, url: string, body: string, label: string, lang: string}> je Website/Sprache/Netzwerk */
    private static array $cache = [];

    /** Zwischenspeicher leeren (nach dem Speichern der Grundeinstellungen, Selbsttests) */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Impressum für eine E-Mail.
     * text: Textblock (ohne Trenner), html: Fußzeile (Tabelle, Inline-Stile), url: absolute Adresse der Impressum-Seite ('' = keine),
     * body: Anbieterkennzeichnung aus der Grundeinstellung, label: „Impressum“ in der Sprache, lang: verwendete Sprache.
     * Alles leer, wenn weder Text noch Seite eingerichtet sind.
     * @return array{text: string, html: string, url: string, body: string, label: string, lang: string}
     */
    public static function get(?string $lang = null, bool $network = false): array
    {
        $net = $network && self::foreignNetwork();
        $lang = Lang::valid($lang) ? (string) $lang : Lang::current();
        $ck = site()->key . '|' . $lang . '|' . ($net ? 'n' : 's');
        if (isset(self::$cache[$ck])) return self::$cache[$ck];
        $body = '';
        $url = '';
        try {
            [$body, $url] = $net ? self::fromNetwork($lang) : self::local($lang);
        } catch (\Throwable $e) {
            error_log('[Mailer] Impressum für E-Mails nicht lesbar: ' . $e->getMessage());
        }
        $label = self::label($lang);
        return self::$cache[$ck] = self::build($body, $url, $label, $lang);
    }

    /** Weder Text noch veröffentlichte Impressum-Seite – E-Mails dieser Website (bzw. Netzwerk-Mails) wären ohne Impressum */
    public static function missing(bool $network = false): bool
    {
        if ($network && self::foreignNetwork()) {
            try {
                [$body, $url] = self::fromNetwork(Lang::current());
                return $body === '' && $url === '';
            } catch (\Throwable) {
                return true;
            }
        }
        return trim((string) app()->settings->get(self::SETTING, '')) === '' && self::page(Lang::current()) === null;
    }

    /**
     * Sperre für Werbe- und Massenversand (Newsletter-Ausgaben, Bestätigungsbitten an den Altbestand): Meldung oder null.
     * Einzelne Mails (Passwort, Formulare) laufen immer – dort wird nur gemeldet (health).
     */
    public static function bulkError(): ?string
    {
        return self::missing() ? __('Versand gesperrt: E-Mails dieser Website hätten kein Impressum. Bitte unter Grundeinstellungen → E-Mail-Versand das „Impressum für E-Mails“ ausfüllen oder unter Einstellungen → Recht eine Impressum-Seite festlegen.') : null;
    }

    /** Prüfung für `bin/console health` und die Übersicht */
    public static function health(): array
    {
        try {
            return self::missing() ? [__('E-Mails ohne Impressum – Grundeinstellungen → E-Mail-Versand → „Impressum für E-Mails“ ausfüllen oder eine Impressum-Seite festlegen') => false] : [];
        } catch (\Throwable $e) {
            return [__('Impressum für E-Mails nicht prüfbar ({msg})', ['msg' => $e->getMessage()]) => null];
        }
    }

    /** Textteil: Impressum nach dem Signatur-Trenner „-- “ anhängen */
    public static function appendText(string $text, array $imp): string
    {
        $block = (string) ($imp['text'] ?? '');
        return $block === '' ? $text : rtrim($text) . "\n\n-- \n" . $block . "\n";
    }

    /** HTML-Teil: Fußzeile vor </body> einfügen (sonst ans Ende) */
    public static function appendHtml(string $html, array $imp): string
    {
        $block = (string) ($imp['html'] ?? '');
        if ($block === '') return $html;
        $pos = strripos($html, '</body>');
        return $pos === false ? $html . "\n" . $block : substr($html, 0, $pos) . $block . "\n" . substr($html, $pos);
    }

    // ------------------------------------------------------------------ intern

    /** Text und HTML aus Anbieterkennzeichnung und Link */
    private static function build(string $body, string $url, string $label, string $lang): array
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($body === '' && $url === '') return ['text' => '', 'html' => '', 'url' => '', 'body' => '', 'label' => $label, 'lang' => $lang];
        $lines = array_values(array_filter(array_map('trim', explode("\n", $body)), fn($l) => $l !== ''));
        $text = implode("\n", array_merge([$label], $lines, $url !== '' ? [$url] : []));
        // Dezente Fußzeile: eigener Untergrund (Kontrast unabhängig vom Layout der Mail: #4a4a4a bzw. #1f4e8c auf Weiß ≥ 7:1),
        // Linie als Trennung vom Inhalt, keine Bilder, Systemschrift
        $font = 'Arial,Helvetica,sans-serif';
        $html = '<table role="presentation" align="center" width="100%" cellpadding="0" cellspacing="0" border="0" lang="' . e($lang) . '" style="width:100%;max-width:640px;border-collapse:collapse;margin:0 auto">'
            . '<tr><td style="padding:20px 16px 24px;border-top:1px solid #c8c8c8;background:#ffffff;color:#4a4a4a;font-family:' . $font . ';font-size:12px;line-height:1.55;text-align:left">'
            . '<p style="margin:0 0 4px;font-weight:bold;color:#333333">' . e($label) . '</p>'
            . ($lines ? '<p style="margin:0">' . implode('<br>', array_map('e', $lines)) . '</p>' : '')
            . ($url !== '' ? '<p style="margin:' . ($lines ? '6px' : '0') . ' 0 0"><a href="' . e($url) . '" style="color:#1f4e8c;text-decoration:underline">' . e($url) . '</a></p>' : '')
            . '</td></tr></table>';
        return ['text' => $text, 'html' => $html, 'url' => $url, 'body' => $body, 'label' => $label, 'lang' => $lang];
    }

    /** „Impressum“ in der Sprache der Mail (Website-Texte) */
    private static function label(string $lang): string
    {
        $prev = app()->lang;
        app()->lang = $lang;
        try {
            return lt('Impressum');
        } finally {
            app()->lang = $prev;
        }
    }

    /** Netzwerk-Mail auf einer anderen Website als der Netzwerk-Website */
    private static function foreignNetwork(): bool
    {
        try {
            return Sites::multi() && !Network::isNetworkSite();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{0: string, 1: string} Text und Link der aktuellen Website */
    private static function local(string $lang): array
    {
        $body = trim((string) app()->settings->get(self::SETTING, ''));
        $p = self::page($lang);
        $base = trusted_site_url() ?? '';
        $url = $p && $base !== '' ? $base . Pages::plainUrl($p) : '';
        return [$body, $url];
    }

    /** Veröffentlichte Impressum-Seite der aktuellen Website in der Sprache (sonst die zugeordnete Seite) – nie Entwürfe */
    private static function page(string $lang): ?array
    {
        foreach (Legal::KEYS['imprint'] as $k) {
            $id = (int) (app()->settings->get($k . '@' . $lang) ?: app()->settings->get($k));
            $p = $id ? Pages::find($id) : null;
            if (!$p) continue;
            if (Lang::multi()) {
                $t = Pages::translations($p)[$lang] ?? null;
                if ($t && $t['status'] === 'published') return $t;
            }
            if ($p['status'] === 'published') return $p;
        }
        return null;
    }

    /** @return array{0: string, 1: string} Text und Link der Netzwerk-Website (aus ihrer Datenbank) */
    private static function fromNetwork(string $lang): array
    {
        $db = Network::db();
        $get = function (string $k) use ($db): mixed {
            $v = $db->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [$k]);
            return $v === null ? null : json_decode((string) $v, true);
        };
        $body = trim((string) ($get(self::SETTING) ?? ''));
        $langs = array_values(array_filter((array) ($get('sys.languages') ?? []), fn($l) => is_array($l) && !empty($l['active']) && !empty($l['code'])));
        $default = (string) ($langs[0]['code'] ?? 'de');
        $page = null;
        foreach (Legal::KEYS['imprint'] as $k) {
            $id = (int) ($get($k . '@' . $lang) ?: $get($k));
            if (!$id) continue;
            $rows = $db->fetchAll('SELECT * FROM pages WHERE id = ? OR translation_group = (SELECT COALESCE(translation_group, id) FROM pages WHERE id = ?)', [$id, $id]);
            $by = [];
            foreach ($rows as $r) if ($r['status'] === 'published') $by[(string) ($r['lang'] ?: $default)] = $r;
            $orig = array_values(array_filter($rows, fn($r) => (int) $r['id'] === $id && $r['status'] === 'published'))[0] ?? null;
            $page = $by[$lang] ?? $orig;
            if ($page) break;
        }
        $url = '';
        if ($page) {
            $base = Network::siteUrl(Network::siteKey());
            $pl = (string) ($page['lang'] ?: $default);
            $prefix = $pl !== $default ? '/' . $pl : '';
            $path = !empty($page['is_home']) ? '/' : '/' . ((string) ($page['path'] ?? '') !== '' ? $page['path'] : $page['slug']);
            if ($base !== '') $url = $base . $prefix . $path;
        }
        return [$body, $url];
    }
}
