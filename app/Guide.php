<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Support\Markdown;

/**
 * „Hinweise zu diesem Projekt“: projektbezogene Anleitungen der Agentur für die Redaktion – Ergänzung zum Handbuch,
 * sichtbar nur, wenn es welche gibt (Kapitel im Handbuch, Übersicht, Suche, Hinweis in Blöcken und Bereichen).
 *
 * Quellen (gleicher Dateiname: die Website gewinnt):
 *   themes/{kit}/guide/*.md          mit dem Kit ausgeliefert (Ordner per theme.php → 'guide' => ['dir' => …] änderbar)
 *   {storage}/guide/*.md             je Website, in der Verwaltung bearbeitbar (Handbuch → Projekt-Hinweise, Recht system.manage)
 *
 * Datei: NN-name.md (Reihenfolge nach Dateiname, natürlich sortiert). Optional Front Matter:
 *   ---
 *   titel: Startseite pflegen        sonst die erste Überschrift „# …“, sonst der Dateiname
 *   bereich: medien, daten/arbeiten  Bereich(e) der Verwaltung → kleiner Hinweis-Link dort
 *   block: stage, hero               Blocktyp(en) → Hinweis-Link im Block-Formular
 *   ausblenden: ja                   Hinweis (z. B. aus dem Kit) für diese Website ausblenden
 *   ---
 * Inhalt: Markdown-lite wie Support (Core\Support\Markdown, HTML nie erlaubt, Links über Sanitizer::safeHref);
 * Überschriften im Text werden Zwischentitel, Bilder als eigene Zeile ![Alt](datei.png) aus dem guide-Ordner.
 *
 * theme.php → 'guide' => ['title' => …, 'author' => 'Agentur XY', 'lead' => …, 'dir' => 'guide'] (alles optional) bzw.
 * 'guide' => false (Kit-Hinweise aus).
 */
final class Guide
{
    /** Größte Datei (Bytes) */
    public const MAX = 100_000;
    /** Bildformate aus dem guide-Ordner (kein SVG: könnte Skript enthalten) */
    public const IMAGES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif'];
    /** Bereiche der Verwaltung (Front Matter „bereich“) → Abschnitt der Ansicht (erstes Segment von $view) */
    public const AREAS = ['uebersicht' => 'dashboard', 'seiten' => 'pages', 'einstellungen' => 'settings', 'website' => 'settings', 'medien' => 'media',
        'daten' => 'data', 'anfragen' => 'requests', 'design' => 'design', 'bloecke' => 'blocks', 'benutzer' => 'users', 'weiterleitungen' => 'redirects'];

    /** @var array<string, list<array>> je Website */
    private static array $cache = [];

    /** Einstellungen des Kits (theme.php → 'guide') */
    public static function config(): array
    {
        $c = app()->theme->def['guide'] ?? [];
        return is_array($c) ? $c : ['off' => $c === false];
    }

    /** Kit-Ordner (null: keiner oder abgeschaltet) */
    public static function kitDir(): ?string
    {
        $c = self::config();
        if (!empty($c['off'])) return null;
        $dir = app()->theme->path . '/' . trim(str_replace('..', '', (string) ($c['dir'] ?? 'guide')), '/');
        return is_dir($dir) ? $dir : null;
    }

    /** Ordner der Website (muss nicht existieren) */
    public static function siteDir(): string
    {
        return site()->storage('guide');
    }

    /** Überschrift des Kapitels */
    public static function title(): string
    {
        return (string) (self::config()['title'] ?? 'Hinweise zu diesem Projekt');
    }

    /** Wer die Hinweise schreibt (theme.php → guide.author, sonst „der Agentur“) */
    public static function author(): string
    {
        return trim((string) (self::config()['author'] ?? ''));
    }

    public static function exists(): bool
    {
        return self::notes() !== [];
    }

    /**
     * Alle sichtbaren Hinweise, sortiert. Je Eintrag: key, anchor, title, source (kit|site), overrides (bool),
     * areas, blocks, body (Markdown ohne Front Matter/Titel), file.
     * @return list<array>
     */
    public static function notes(): array
    {
        $k = site()->key . '|' . app()->theme->name;
        return self::$cache[$k] ??= array_values(array_filter(self::all(), fn($n) => !$n['hidden']));
    }

    /** Alle Hinweise inkl. ausgeblendeter (für die Verwaltung) @return list<array> */
    public static function all(): array
    {
        $out = [];
        foreach (['kit' => self::kitDir(), 'site' => self::siteDir()] as $source => $dir) {
            if ($dir === null || !is_dir($dir)) continue;
            foreach (glob($dir . '/*.md') ?: [] as $file) {
                $key = basename($file, '.md');
                if (!self::validKey($key) || !is_file($file) || filesize($file) > self::MAX) continue;
                $n = self::parse((string) file_get_contents($file), $key);
                $n += ['source' => $source, 'file' => $file, 'overrides' => $source === 'site' && isset($out[$key])];
                $out[$key] = $n;
            }
        }
        uksort($out, 'strnatcasecmp');
        return array_values($out);
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $n) if ($n['key'] === $key) return $n;
        return null;
    }

    /** Erlaubter Dateiname (ohne .md) */
    public static function validKey(string $key): bool
    {
        return (bool) preg_match('~^[a-z0-9][a-z0-9_-]{0,63}$~', $key) && $key !== 'neu';
    }

    /** Anker im Handbuch */
    public static function anchor(string $key): string
    {
        return 'projekt-' . trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($key)), '-');
    }

    /**
     * Quelltext zerlegen (ohne Dateisystem – auch für den Selbsttest).
     * @return array{key: string, anchor: string, title: string, areas: list<string>, blocks: list<string>, hidden: bool, body: string, meta: array<string, string>}
     */
    public static function parse(string $src, string $key): array
    {
        $src = str_replace(["\r\n", "\r"], "\n", $src);
        if (str_starts_with($src, "\u{FEFF}")) $src = substr($src, 3);
        $meta = [];
        if (preg_match('~^---[ \t]*\n(.*?)\n---[ \t]*(\n|$)~s', $src, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (preg_match('~^\s*([a-zA-ZäöüÄÖÜß_-]+)\s*:\s*(.*?)\s*$~u', $line, $x)) $meta[mb_strtolower($x[1])] = trim($x[2], " \"'");
            }
            $src = substr($src, strlen($m[0]));
        }
        $title = (string) ($meta['titel'] ?? $meta['title'] ?? '');
        // Erste Überschrift „# …“ (vor weiterem Text) ist der Titel
        if (preg_match('~^\s*#\s+(.+?)\s*#*\s*$~m', $src, $h, PREG_OFFSET_CAPTURE) && trim(substr($src, 0, $h[0][1])) === '') {
            if ($title === '') $title = $h[1][0];
            $src = substr($src, $h[0][1] + strlen($h[0][0]));
        }
        if ($title === '') $title = ucfirst(str_replace(['-', '_'], ' ', (string) preg_replace('~^\d+[-_]~', '', $key)));
        $list = fn(string $k) => array_values(array_filter(array_map(fn($v) => mb_strtolower(trim($v)), explode(',', (string) ($meta[$k] ?? ''))), fn($v) => $v !== ''));
        $hidden = in_array(mb_strtolower((string) ($meta['ausblenden'] ?? $meta['hidden'] ?? '')), ['ja', 'yes', 'true', '1'], true);
        return ['key' => $key, 'anchor' => self::anchor($key), 'title' => mb_substr($title, 0, 160), 'areas' => $list('bereich') ?: $list('area'),
            'blocks' => $list('block') ?: $list('blocks'), 'hidden' => $hidden, 'body' => trim($src, "\n"), 'meta' => $meta];
    }

    /** HTML eines Hinweises (Bilder aus dem guide-Ordner über die Verwaltung) */
    public static function html(array $note): string
    {
        return self::render((string) $note['body'], fn(string $f) => self::image($f) !== null ? url('/admin/hilfe/projekt/bild/' . rawurlencode($f)) : null);
    }

    /**
     * Markdown-lite → HTML (Core\Support\Markdown): Überschriften im Text werden h4, eine Zeile nur mit ![Alt](datei)
     * wird eine Abbildung, sofern $img(datei) eine Adresse liefert. Interne Links /… bekommen den Basis-Pfad.
     * @param ?\Closure(string): ?string $img
     */
    public static function render(string $md, ?\Closure $img = null): string
    {
        $out = '';
        $buf = [];
        $code = false;
        $flush = function () use (&$buf, &$out) {
            if ($buf) $out .= Markdown::render(implode("\n", $buf));
            $buf = [];
        };
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $md)) as $line) {
            if (preg_match('~^\s*```~', $line)) $code = !$code;
            elseif (!$code && preg_match('~^\s{0,3}#{1,6}\s+(.+)$~', $line, $m)) $line = '#### ' . $m[1];
            elseif (!$code && preg_match('~^\s*!\[([^\]\n]{0,200})\]\(([^)\s]{1,200})(?:\s+"([^"\n]{0,200})")?\)\s*$~', $line, $m)) {
                $flush();
                $src = $img ? $img($m[2]) : null;
                if ($src !== null) {
                    $cap = ($m[3] ?? '') !== '' ? $m[3] : '';
                    $out .= '<figure class="doc-fig doc-guide__fig"><img src="' . e($src) . '" alt="' . e($m[1]) . '" loading="lazy">' . ($cap !== '' ? '<figcaption>' . e($cap) . '</figcaption>' : '') . '</figure>';
                }
                continue;
            }
            $buf[] = $line;
        }
        $flush();
        $base = base_path();
        return $base === '' ? $out : (string) preg_replace('~ href="/(?!/)~', ' href="' . e($base) . '/', $out);
    }

    /** Klartext eines Hinweises (Suche) */
    public static function plain(array $note, int $max = 0): string
    {
        return Markdown::plain((string) preg_replace('~!\[([^\]]*)\]\([^)]*\)~', '$1', (string) $note['body']), $max);
    }

    /** Pfad eines Bildes aus dem guide-Ordner der Website bzw. des Kits (nur Dateiname, nur Bildformate) */
    public static function image(string $file): ?string
    {
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9_-]{0,100}(\.[A-Za-z0-9_-]{1,40})*\.([A-Za-z0-9]{2,5})$~', $file, $m) || !isset(self::IMAGES[strtolower($m[2])])) return null;
        foreach ([self::siteDir(), self::kitDir()] as $dir) {
            if ($dir !== null && is_file($dir . '/' . $file)) return $dir . '/' . $file;
        }
        return null;
    }

    /** Hinweise zu einem Blocktyp */
    public static function forBlock(string $type): array
    {
        $type = mb_strtolower($type);
        return array_values(array_filter(self::notes(), fn($n) => in_array($type, $n['blocks'], true)));
    }

    /** Hinweise zu einem Bereich der Verwaltung ($section = erstes Segment der Ansicht, $table = Handle der Datentabelle) */
    public static function forArea(string $section, ?string $table = null): array
    {
        return array_values(array_filter(self::notes(), fn($n) => self::areaMatch($n['areas'], $section, $table)));
    }

    /** Passt eine der Angaben „bereich“ (medien, daten/arbeiten …) zum Abschnitt der Verwaltung und zur Tabelle? */
    public static function areaMatch(array $areas, string $section, ?string $table = null): bool
    {
        foreach ($areas as $a) {
            [$area, $sub] = array_pad(explode('/', (string) $a, 2), 2, null);
            if ((self::AREAS[$area] ?? $area) !== $section) continue;
            if ($sub === null || ($table !== null && $sub === mb_strtolower($table))) return true;
        }
        return false;
    }

    /** Link auf einen Hinweis im Handbuch */
    public static function url(array $note): string
    {
        return url('/admin/hilfe') . '#' . $note['anchor'];
    }

    // ------------------------------------------------------------ Bearbeiten (Website)

    /** Quelltext der Website-Datei speichern (legt den Ordner an) */
    public static function save(string $key, string $src): void
    {
        if (!self::validKey($key)) throw new \InvalidArgumentException('Ungültiger Dateiname');
        $dir = self::siteDir();
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) throw new \RuntimeException('Ordner nicht beschreibbar: ' . $dir);
        $src = str_replace(["\r\n", "\r"], "\n", $src);
        if (strlen($src) > self::MAX) throw new \LengthException('Zu lang');
        if (file_put_contents($dir . '/' . $key . '.md', rtrim($src) . "\n", LOCK_EX) === false) throw new \RuntimeException('Speichern fehlgeschlagen');
        self::$cache = [];
    }

    /** Website-Datei löschen (ein gleichnamiger Kit-Hinweis gilt dann wieder) */
    public static function delete(string $key): bool
    {
        $f = self::siteDir() . '/' . $key . '.md';
        $ok = self::validKey($key) && is_file($f) && @unlink($f);
        self::$cache = [];
        return $ok;
    }

    /** Quelltext einer Datei (Website vor Kit) */
    public static function source(array $note): string
    {
        return is_file($note['file']) ? (string) file_get_contents($note['file']) : '';
    }

    /** Darf der Benutzer Hinweise der Website bearbeiten? */
    public static function canEdit(): bool
    {
        return can('system.manage');
    }

    /**
     * Selbsttest (php bin/console guide:selftest): Front Matter, Titel, Reihenfolge der Schlüssel, Escaping, Links,
     * Bilder, Bereiche. Ohne Dateisystem und Datenbank. @return array{ok: int, fails: list<string>}
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $has = fn(string $what, string $hay, string $needle, bool $want = true) => $eq($what, str_contains($hay, $needle), $want);

        $n = self::parse("---\nbereich: Medien, daten/arbeiten\nblock: stage,hero\n---\n# Startseite pflegen\n\nText **fett**.\n", '10-startseite');
        $eq('Titel aus Überschrift', $n['title'], 'Startseite pflegen');
        $eq('Bereiche', $n['areas'], ['medien', 'daten/arbeiten']);
        $eq('Blöcke', $n['blocks'], ['stage', 'hero']);
        $eq('Text ohne Titel', $n['body'], 'Text **fett**.');
        $eq('Anker', $n['anchor'], 'projekt-10-startseite');
        $eq('nicht ausgeblendet', $n['hidden'], false);
        $n = self::parse("---\ntitel: \"Aus dem Kopf\"\nausblenden: ja\n---\n# Anders\n", 'x');
        $eq('Titel aus Front Matter', $n['title'], 'Aus dem Kopf');
        $eq('ausgeblendet', $n['hidden'], true);
        $eq('Titel aus Dateiname', self::parse('Nur Text', '20-bild_formate')['title'], 'Bild formate');
        $eq('Überschrift erst nach Text ist kein Titel', self::parse("Vorweg\n# Später", '30-a')['title'], 'A');
        $eq('BOM + CRLF', self::parse("\u{FEFF}---\r\nblock: a\r\n---\r\n# T\r\n", 'k')['blocks'], ['a']);

        $eq('Schlüssel gültig', self::validKey('10-start_seite'), true);
        foreach (['../x', 'X', '-a', 'a/b', 'a.md', '', 'neu', str_repeat('a', 65)] as $bad) $eq('Schlüssel ungültig: ' . $bad, self::validKey($bad), false);
        $keys = ['10-b', '2-a', '100-c'];
        usort($keys, 'strnatcasecmp');
        $eq('natürliche Reihenfolge', $keys, ['2-a', '10-b', '100-c']);

        $h = self::render("<script>alert(1)</script>\n\n[x](javascript:alert(1)) [y](/admin/media) [z](https://example.org)");
        $has('HTML escaped', $h, '<script>', false);
        $has('HTML als Text', $h, '&lt;script&gt;');
        $has('javascript:-Link verworfen', $h, 'href="javascript', false);
        $has('interner Link', $h, 'href="/admin/media"');
        $has('externer Link neuer Tab', $h, 'href="https://example.org" rel="noopener noreferrer" target="_blank"');
        $has('Zwischentitel als h4', self::render("## Unterpunkt\ntext"), '<h4>Unterpunkt</h4>');
        $has('Liste', self::render("- eins\n- zwei"), '<ul><li>eins</li><li>zwei</li></ul>');
        $has('Code-Block unverändert', self::render("```\n## kein Titel\n<b>\n```"), "## kein Titel\n&lt;b&gt;");
        $img = fn(string $f) => $f === 'bild.png' ? '/bild?x="1"' : null;
        $h = self::render("Vor\n![Alt \"<i>\"](bild.png \"Unterschrift\")\nNach", $img);
        $has('Bild als Abbildung', $h, '<figure class="doc-fig doc-guide__fig"><img src="/bild?x=&quot;1&quot;" alt="Alt &quot;&lt;i&gt;&quot;" loading="lazy"><figcaption>Unterschrift</figcaption></figure>');
        $has('Text vor und nach dem Bild', $h, '<p>Vor</p><figure');
        $eq('unbekanntes Bild entfällt', self::render('![a](fehlt.png)', $img), '');
        foreach (['../x.png', 'a/b.png', 'x.svg', 'x.php', '.htaccess', 'x.png.php'] as $bad) $eq('Bildname abgelehnt: ' . $bad, self::image($bad), null);

        // Bereiche: Alias → Abschnitt, Tabelle
        $area = self::areaMatch(...);
        $eq('Bereich medien', $area(['medien'], 'media'), true);
        $eq('Bereich ohne Tabelle passt nicht zu daten/…', $area(['daten/arbeiten'], 'data'), false);
        $eq('Bereich daten/tabelle passt', $area(['daten/arbeiten'], 'data', 'arbeiten'), true);
        $eq('Bereich daten/tabelle andere Tabelle', $area(['daten/arbeiten'], 'data', 'team'), false);
        $eq('Bereich englischer Abschnitt', $area(['pages'], 'pages', null), true);
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Dateien für die Signatur des Hilfe-Index (Core\AI\HelpIndex) */
    public static function files(): array
    {
        $out = [];
        foreach ([self::kitDir(), self::siteDir()] as $dir) {
            if ($dir !== null && is_dir($dir)) $out = [...$out, ...(glob($dir . '/*.md') ?: [])];
        }
        return $out;
    }
}
