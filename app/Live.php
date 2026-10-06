<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Sse;

/**
 * Live-Aktualisierung für Besucher (Server-Sent Events) – zentral für Blöcke, Kits und Erweiterungen.
 *
 * Kanäle sind Namen, deren Version sich bei Änderungen erhöht – der Stream überträgt nur Versionen, nie Inhalte:
 *   data:{tabelle}          Einträge einer Datentabelle (automatisch bei Speichern, Status, Löschen, Sortieren)
 *   g:shared:{schlüssel}    geteilte Datentabelle (website-übergreifend, „g:“ = für alle Websites der Installation)
 *   media:collection:{id}   Sammlung der Mediathek (automatisch bei Hinzufügen, Entfernen, Löschen, Hochladen in die Sammlung)
 *   page:{id}               Seite (automatisch bei Veröffentlichen, Zurückziehen, Löschen)
 *   ext:{erweiterung}:…     eigene Kanäle von Erweiterungen: Live::touch('ext:shop:lager')
 *
 * Im Template:  <section<?= Live::attrs(['data:ticker'], Live::blockSrc($b)) ?>> … <ul data-live-list> <li data-live-id="…"> …
 * live.js hält eine EventSource für alle Elemente der Seite (/api/live?t=…); ändert sich eine Version, lädt es den Block
 * neu (data-live-src → JSON {html}), tauscht [data-live-list] aus, markiert neue [data-live-id] (Klasse is-live-new) und
 * meldet sie in [data-live-status] (aria-live). Ohne data-live-src: nur das Ereignis „cms:live“ am Element (eigenes JS).
 * Abos sind signiert (HMAC) – Besucher können keine beliebigen Kanäle abfragen.
 *
 * Betrieb: jeder offene Stream belegt einen PHP-Worker – deshalb kurz (config 'live_stream_seconds', 5–25, Standard 20) und
 * höchstens 'live_max_streams' (Standard 16) gleichzeitig je Installation; darüber und auf dem Entwicklungsserver (php -S)
 * antwortet der Endpunkt sofort mit den Versionen und längerem retry – EventSource fragt dann einfach seltener nach.
 * 'live_transport' => 'auto'|'sse'|'poll' erzwingt eine Art.
 */
final class Live
{
    private static bool $assets = false;

    // ------------------------------------------------------------------ Versionen

    private static function file(string $channel): string
    {
        $dir = str_starts_with($channel, 'g:') ? ROOT . '/storage/live' : site()->storage('live');
        return $dir . '/' . substr(sha1($channel), 0, 24) . '.v';
    }

    /** Kanäle als geändert markieren (Version = Zeitstempel in ms, steigt immer) */
    public static function touch(string ...$channels): void
    {
        foreach (array_unique($channels) as $c) {
            if ($c === '') continue;
            $f = self::file($c);
            if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
            $v = max((int) round(microtime(true) * 1000), self::version($c) + 1);
            @file_put_contents($f, (string) $v, LOCK_EX);
        }
    }

    public static function version(string $channel): int
    {
        $f = self::file($channel);
        clearstatcache(true, $f);
        return is_file($f) ? (int) @file_get_contents($f) : 0;
    }

    /** Höchste Version mehrerer Kanäle */
    public static function versionOf(array $channels): int
    {
        $v = 0;
        foreach ($channels as $c) $v = max($v, self::version((string) $c));
        return $v;
    }

    /** Typisierte Ereignisse (Extensions::emit) → Kanäle; Veröffentlichen/Zurückziehen ruft Pages zusätzlich direkt auf */
    public static function fromEvent(Events\Event $e): void
    {
        if ($e instanceof Events\PageDeleted) self::touch('page:' . $e->id);
    }

    // ------------------------------------------------------------------ Abos (signiert)

    private static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', 'live|' . site()->key . '|' . $payload, app()->key()), 0, 20);
    }

    /** Signiertes Abo für Kanäle (URL-sicher) */
    public static function token(array $channels): string
    {
        $p = rtrim(strtr(base64_encode(implode("\n", array_values(array_unique(array_map('strval', $channels))))), '+/', '-_'), '=');
        return $p . '.' . self::sign($p);
    }

    /** Kanäle eines Abos oder null (Signatur falsch) */
    public static function channels(string $token): ?array
    {
        if (!preg_match('~^([A-Za-z0-9_-]{1,800})\.([a-f0-9]{20})$~', $token, $m) || !hash_equals(self::sign($m[1]), $m[2])) return null;
        $raw = base64_decode(strtr($m[1], '-_', '+/'), true);
        return $raw === false ? null : array_values(array_filter(explode("\n", $raw), fn($c) => $c !== ''));
    }

    /**
     * Attribute fürs Element: Abo, aktuelle Version, optional Adresse zum Nachladen. Im Bearbeiten leer (kein Stream).
         */
    public static function attrs(array $channels, string $src = ''): string
    {
        if (is_editing() || !$channels) return '';
        return ' data-live="' . e(self::token($channels)) . '" data-live-v="' . self::versionOf($channels) . '"'
            . ($src !== '' ? ' data-live-src="' . e($src) . '"' : '');
    }

    /** Skript und Stile (einmal je Seite) – im Block nach dem Element ausgeben */
    public static function assets(): string
    {
        if (self::$assets || is_editing()) return '';
        self::$assets = true;
        return '<link rel="stylesheet" href="' . e(asset('css/live.css')) . '"><script src="' . e(asset('js/live.js')) . '" defer'
            . ' data-live-endpoint="' . e(url('/api/live')) . '"'
            . ' data-l-new="' . e(lt('{n} neue Einträge')) . '" data-l-new1="' . e(lt('Ein neuer Eintrag')) . '"></script>';
    }

    /** Pause-Schalter und Statuszeile (WCAG 2.2.2: sich selbst aktualisierende Inhalte anhalten können) */
    public static function controls(): string
    {
        if (is_editing()) return '';
        return '<div class="cms-live-bar"><span class="cms-live-dot" aria-hidden="true"></span><span class="cms-live-label">' . e(lt('Live')) . '</span>'
            . '<button type="button" class="cms-live-pause" data-live-pause aria-pressed="false" data-l-pause="' . e(lt('Live-Aktualisierung anhalten')) . '" data-l-play="' . e(lt('Live-Aktualisierung fortsetzen')) . '" data-l-play-text="' . e(lt('Fortsetzen')) . '">'
            . e(lt('Anhalten')) . '</button><span class="cms-live-status" data-live-status role="status" aria-live="polite"></span></div>';
    }

    /** Adresse, unter der ein Block der veröffentlichten Seite neu gerendert wird */
    public static function blockSrc(Block $b): string
    {
        $page = app()->currentPage;
        if (!is_array($page) || empty($page['id'])) return '';
        return url('/api/live/block') . '?p=' . (int) $page['id'] . '&b=' . rawurlencode($b->id);
    }

    // ------------------------------------------------------------------ Endpunkte

    private static function transport(): string
    {
        $mode = (string) app()->config->get('live_transport', 'auto');
        if ($mode === 'auto' && PHP_SAPI === 'cli-server' && (int) getenv('PHP_CLI_SERVER_WORKERS') > 1) return 'sse';
        return Sse::supported($mode) ? 'sse' : 'poll';
    }

    /** GET /api/live?t=…&t=… (EventSource) – Ereignis „v“: {token: version} */
    public static function stream(Request $r): Response
    {
        $tokens = [];
        $qs = (string) ($r->server['QUERY_STRING'] ?? '');
        foreach (explode('&', $qs) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if ($k === 't' && count($tokens) < 20 && ($ch = self::channels(rawurldecode($v))) !== null) $tokens[rawurldecode($v)] = $ch;
        }
        if (!$tokens) return Response::json(['error' => 'Kein gültiges Abo.'], 400);
        $versions = fn() => array_map(fn($ch) => self::versionOf($ch), $tokens);

        $slot = self::transport() === 'sse' ? self::slot() : null;
        if ($slot === null) {
            // Abfrage-Modus: Versionen sofort, Browser verbindet sich nach retry neu (wirkt wie Nachfragen alle 10–15 s)
            return Sse::stream(function (Sse $sse) use ($versions) { $sse->send('v', $versions()); $sse->send('bye', ['poll' => true]); }, [], self::transport() === 'poll' ? 8000 : 15000);
        }
        $seconds = max(5, min(25, (int) app()->config->get('live_stream_seconds', 20)));
        return Sse::stream(function (Sse $sse) use ($versions, $seconds, $slot) {
            $last = $versions();
            $sse->send('v', $last);
            $end = microtime(true) + $seconds;
            $ping = microtime(true) + 12;
            while (microtime(true) < $end) {
                usleep(1_000_000);
                if ($sse->aborted()) break;
                $now = $versions();
                $diff = array_filter($now, fn($v, $k) => ($last[$k] ?? 0) !== $v, ARRAY_FILTER_USE_BOTH);
                if ($diff) { if (!$sse->send('v', $diff)) break; $last = $now; $ping = microtime(true) + 12; }
                elseif (microtime(true) > $ping) { if (!$sse->ping()) break; $ping = microtime(true) + 12; }
            }
            $sse->send('bye', ['poll' => false]);
            flock($slot, LOCK_UN);
            fclose($slot);
        }, [], 2000);
    }

    /** Freier Platz für einen Stream (Dateisperre, gilt für die ganze Installation) oder null */
    private static function slot()
    {
        $max = max(1, (int) app()->config->get('live_max_streams', 16));
        $dir = ROOT . '/storage/live/slots';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        foreach (range(1, $max) as $n) {
            $h = @fopen("$dir/$n.lock", 'c');
            if ($h && flock($h, LOCK_EX | LOCK_NB)) return $h;
            if ($h) fclose($h);
        }
        return null;
    }

    /** GET /api/live/block?p=…&b=… – Block der veröffentlichten Seite neu rendern (nur Blöcke mit 'live' => true) */
    public static function block(Request $r): Response
    {
        $page = Pages::find((int) ($r->query['p'] ?? 0));
        $id = (string) ($r->query['b'] ?? '');
        if (!$page || Pages::state($page) !== 'online' || $id === '' || ($page['type'] ?? 'page') !== 'page'
            || (!app()->auth->check() && PageAccess::restricted($page))) return Response::json(['error' => 'Nicht gefunden.'], 404);
        $raw = self::findBlock(Pages::blocks($page), $id);
        $theme = app()->theme;
        $def = $raw ? ($theme->blocks()[(string) ($raw['type'] ?? '')] ?? null) : null;
        if (!$raw || empty($def['live'])) return Response::json(['error' => 'Nicht gefunden.'], 404);
        app()->currentPage = $page;
        if (!empty($page['lang']) && Lang::valid((string) $page['lang'])) app()->lang = (string) $page['lang'];
        $block = $theme->makeBlock($raw);
        $html = $block ? $theme->renderBlock($block) : '';
        return Response::json(['html' => $html])->header('Cache-Control', 'public, max-age=2')->header('X-Robots-Tag', 'noindex');
    }

    /** Block (auch in Spalten des Blocks „Layout“) */
    private static function findBlock(array $blocks, string $id): ?array
    {
        foreach ($blocks as $b) {
            if ((string) ($b['id'] ?? '') === $id) return $b;
            foreach ((array) ($b['data']['columns'] ?? []) as $c) {
                if ($f = self::findBlock((array) ($c['blocks'] ?? []), $id)) return $f;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ Selbsttest (php bin/console live:selftest)

    /** @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $c = 'ext:selftest:' . bin2hex(random_bytes(4));
        $eq('unbekannter Kanal hat Version 0', self::version($c), 0);
        self::touch($c);
        $v1 = self::version($c);
        $eq('touch setzt eine Version', $v1 > 0, true);
        self::touch($c);
        $eq('Version steigt bei jedem touch', self::version($c) > $v1, true);
        $tok = self::token([$c, 'data:test']);
        $eq('Abo liefert die Kanäle zurück', self::channels($tok), [$c, 'data:test']);
        [$p, $sig] = explode('.', $tok);
        $eq('gefälschte Signatur wird abgewiesen', self::channels($p . '.' . str_repeat('0', 20)), null);
        $other = rtrim(strtr(base64_encode('data:geheim'), '+/', '-_'), '=');
        $eq('fremde Kanäle mit alter Signatur abgewiesen', self::channels($other . '.' . $sig), null);
        $eq('Unsinn wird abgewiesen', self::channels('kein-abo'), null);
        $eq('höchste Version mehrerer Kanäle', self::versionOf([$c, 'data:gibt-es-nicht']), self::version($c));
        $g = 'g:selftest:' . bin2hex(random_bytes(4));
        self::touch($g);
        $eq('globaler Kanal liegt außerhalb der Website', str_starts_with(self::file($g), ROOT . '/storage/live/'), true);
        @unlink(self::file($c));
        @unlink(self::file($g));
        foreach (['live_gallery', 'live_text'] as $type) {
            $def = app()->theme->blocks()[$type] ?? null;
            $eq("Block „{$type}“ vorhanden", $def !== null, true);
            $eq("Block „{$type}“ ist live", !empty($def['live']), true);
        }
        $eq('Blöcke ohne live werden nicht nachgeliefert', !empty(app()->theme->blocks()['gallery']['live']), false);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
