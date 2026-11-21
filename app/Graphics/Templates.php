<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Network\Network;
use Core\Svg;

/**
 * Vorlagen zum Ausfüllen (Persona „Bild“, Ausbaustufe 6): die Agentur gestaltet ein Bild im Grafik-Editor und gibt einzelne Ebenen
 * als Felder frei – Bild (Foto der Mediathek), Text (einzeilig/mehrzeilig, Höchstlänge, Schrift schrumpft bis zur Mindestgröße),
 * Farbe (nur Design-Farben der Website) und Ein/Aus. Alles andere bleibt gesperrt; die Redaktion füllt nur ein Formular aus
 * (Mediathek → Neu → „Aus Vorlage erstellen“) und bekommt fertige Pixelbilder je Format (Core\Graphics\Compose).
 *
 * Ablage: diese Website → Tabelle graphics (kind „template“), alle Websites der Installation → graphic_shared (kind „template“,
 * Netzwerk-Datenbank wie die gemeinsamen Bausteine). Verweis: „site:{id}“ bzw. „net:{id}“.
 * Felder stehen in der Arbeitsfassung am Element: data-ge-field = Base64(JSON {k, n, t: image|text|color|toggle, ml, max, min, lines, on}).
 * Gestalten (anlegen, ändern, teilen, löschen) nur mit dem Recht „graphics.templates“ (Standard: nur Administration);
 * Verwenden mit „media.upload“. Host-neutral: kein Request, Rechte prüft can()/der Aufrufer.
 */
final class Templates
{
    public const KIND = 'template';
    public const RIGHT = 'graphics.templates';
    public const TYPES = ['image', 'text', 'color', 'toggle'];
    /** Standard-Höchstlänge eines Textfelds (Zeichen) */
    public const TEXT_MAX = 200;

    public static function can(): bool
    {
        return can('media.upload') && can(self::RIGHT);
    }

    /**
     * Vorlagen dieser Website und für alle Websites (ohne SVG, mit Feldern für die Auswahl).
     * @return list<array{ref: string, scope: string, id: int, name: string, folder: string, width: int, height: int, fields: list<array<string, mixed>>, updated_at: string, svg?: string}>
     */
    public static function all(bool $withSvg = false): array
    {
        $out = [];
        foreach (app()->db->fetchAll('SELECT id, name, folder, svg, width, height, updated_at FROM graphics WHERE kind = ? ORDER BY name, id', [self::KIND]) as $r) {
            $out[] = self::row($r, 'site', $withSvg);
        }
        try {
            foreach (Network::db()->fetchAll('SELECT id, name, folder, svg, width, height, updated_at FROM graphic_shared WHERE kind = ? ORDER BY name, id', [self::KIND]) as $r) {
                $out[] = self::row($r, 'net', $withSvg);
            }
        } catch (\Throwable) {
            // Netzwerk-Datenbank nicht erreichbar bzw. noch ohne Spalte kind
        }
        return $out;
    }

    private static function row(array $r, string $scope, bool $svg): array
    {
        $item = ['ref' => $scope . ':' . (int) $r['id'], 'scope' => $scope === 'net' ? 'network' : 'site', 'id' => (int) $r['id'], 'name' => (string) $r['name'],
            'folder' => (string) ($r['folder'] ?? ''), 'width' => (int) $r['width'], 'height' => (int) $r['height'], 'fields' => self::fields((string) $r['svg']),
            'updated_at' => (string) ($r['updated_at'] ?? '')];
        if ($svg) $item['svg'] = (string) $r['svg'];
        return $item;
    }

    /** Eine Vorlage mit SVG (Arbeitsfassung) – null, wenn es sie nicht gibt */
    public static function get(string $ref): ?array
    {
        if (!preg_match('~^(site|net):(\d+)$~', $ref, $m)) return null;
        try {
            $r = $m[1] === 'site'
                ? app()->db->fetch('SELECT * FROM graphics WHERE id = ? AND kind = ?', [(int) $m[2], self::KIND])
                : Network::db()->fetch('SELECT * FROM graphic_shared WHERE id = ? AND kind = ?', [(int) $m[2], self::KIND]);
        } catch (\Throwable) {
            return null;
        }
        return $r ? self::row($r, $m[1], true) : null;
    }

    /**
     * Vorlage speichern (neu oder ersetzen per $ref). Die Arbeitsfassung läuft durch Core\Svg::clean (editor), Felder werden geprüft.
     * @return array<string, mixed> Eintrag wie get()
     * @throws \RuntimeException verständliche Meldung
     */
    public static function store(string $scope, string $name, string $raw, ?string $ref = null, string $folder = ''): array
    {
        $r = Svg::clean($raw, ['editor' => true]);
        if (!self::fields($r['svg'])) throw new \RuntimeException(__('Die Vorlage hat noch keine Felder – geben Sie mindestens eine Ebene als Feld frei.'));
        $name = Graphics::cleanName($name) ?: __('Vorlage');
        $row = ['name' => $name, 'folder' => Graphics::cleanFolder($folder), 'svg' => $r['svg'], 'width' => $r['width'], 'height' => $r['height'], 'updated_at' => now()];
        $old = $ref !== null ? self::get($ref) : null;
        if ($ref !== null && !$old) throw new \RuntimeException(__('Vorlage nicht gefunden.'));
        $net = $old ? $old['scope'] === 'network' : $scope === 'network';
        if ($net) {
            $db = Network::db();
            if ($old) {
                $db->update('graphic_shared', $row, 'id = :id', ['id' => $old['id']]);
                $id = (int) $old['id'];
            } else {
                $id = (int) $db->insert('graphic_shared', $row + ['kind' => self::KIND, 'site' => site()->key, 'user_email' => (string) (app()->auth->user()['email'] ?? ''), 'created_at' => now()]);
            }
            return (array) self::get('net:' . $id);
        }
        $db = app()->db;
        if ($old) {
            $prev = (string) $db->fetchValue('SELECT svg FROM graphics WHERE id = ?', [$old['id']]);
            if ($prev !== $row['svg']) Graphics::addVersion('graphic:' . $old['id'], $prev);
            $db->update('graphics', $row, 'id = :id', ['id' => $old['id']]);
            return (array) self::get('site:' . $old['id']);
        }
        $id = $db->insert('graphics', $row + ['kind' => self::KIND, 'created_at' => now(), 'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null]);
        return (array) self::get('site:' . $id);
    }

    public static function delete(string $ref): bool
    {
        $t = self::get($ref);
        if (!$t) return false;
        if ($t['scope'] === 'network') Network::db()->query('DELETE FROM graphic_shared WHERE id = ?', [$t['id']]);
        else {
            app()->db->query('DELETE FROM graphics WHERE id = ?', [$t['id']]);
            app()->db->query('DELETE FROM graphic_versions WHERE ref = ?', ['graphic:' . $t['id']]);
        }
        return true;
    }

    /**
     * Felder einer Arbeitsfassung in Dokumentreihenfolge (gleicher Schlüssel k mehrfach = dasselbe Feld, z. B. in mehreren Formaten).
     * @return list<array{k: string, n: string, t: string, ml?: bool, max?: int, min?: int, lines?: int, on?: bool}>
     */
    public static function fields(string $svg): array
    {
        $out = [];
        if (!preg_match_all('~data-ge-field="([A-Za-z0-9+/=]{1,4000})"~', $svg, $m)) return [];
        foreach ($m[1] as $b64) {
            $f = self::cleanField(json_decode((string) base64_decode($b64, true), true));
            if ($f && !isset($out[$f['k']])) $out[$f['k']] = $f;
        }
        return array_values($out);
    }

    /** Feld-Angabe prüfen und auf bekannte Schlüssel kürzen (null = ungültig) */
    public static function cleanField(mixed $f): ?array
    {
        if (!is_array($f) || !is_string($f['k'] ?? null) || !preg_match('~^[a-z0-9_-]{1,40}$~i', $f['k']) || !in_array($f['t'] ?? '', self::TYPES, true)) return null;
        $n = Graphics::cleanName((string) ($f['n'] ?? '')) ?: $f['k'];
        $out = ['k' => $f['k'], 'n' => mb_substr($n, 0, 80), 't' => (string) $f['t']];
        if ($f['t'] === 'text') {
            $out['ml'] = !empty($f['ml']);
            $out['max'] = max(1, min(2000, (int) ($f['max'] ?? self::TEXT_MAX)));
            $out['min'] = max(20, min(100, (int) ($f['min'] ?? 60)));          // Mindestgröße der Schrift in % der gestalteten Größe
            $out['lines'] = max(1, min(20, (int) ($f['lines'] ?? ($out['ml'] ? 3 : 1))));
            // gestaltet: Breite des Rahmens und Schriftgröße (Einheiten der Grafik) – Grundlage fürs Einpassen
            if (isset($f['w']) && is_numeric($f['w'])) $out['w'] = max(0.0, min(100000.0, (float) $f['w']));
            if (isset($f['fs']) && is_numeric($f['fs'])) $out['fs'] = max(1.0, min(2000.0, (float) $f['fs']));
        }
        if ($f['t'] === 'toggle') $out['on'] = !array_key_exists('on', $f) || !empty($f['on']);
        return $out;
    }

    /**
     * Ausgefüllte Werte prüfen (Formular der Redaktion): Text ohne Markup und höchstens max Zeichen (einzeilig ohne Umbruch), Bild =
     * Pixelbild der Mediathek, Farbe = Name einer Design-Farbe der Website, Ein/Aus = bool. Unbekannte Felder fallen weg.
     * @param list<string> $tokens erlaubte Design-Farben (Namen)
     * @return array{vals: array<string, mixed>, errors: list<string>}
     */
    public static function validate(string $svg, mixed $vals, array $tokens): array
    {
        $vals = is_array($vals) ? $vals : [];
        $out = [];
        $errors = [];
        foreach (self::fields($svg) as $f) {
            if (!array_key_exists($f['k'], $vals)) continue;
            $v = $vals[$f['k']];
            switch ($f['t']) {
                case 'text':
                    $s = trim(strip_tags(is_scalar($v) ? (string) $v : ''));
                    $s = (string) preg_replace('~\r\n?~', "\n", $s);
                    if (empty($f['ml'])) $s = (string) preg_replace('~\s*\n\s*~u', ' ', $s);
                    if (mb_strlen($s) > $f['max']) $errors[] = __('„{name}“ ist zu lang (höchstens {n} Zeichen).', ['name' => $f['n'], 'n' => $f['max']]);
                    $out[$f['k']] = mb_substr($s, 0, $f['max']);
                    break;
                case 'image':
                    $id = (int) (is_scalar($v) ? $v : 0);
                    if ($id === 0) { $out[$f['k']] = 0; break; }
                    if (!Compose::photoInfo([$id])) { $errors[] = __('„{name}“: Bitte ein Foto der Mediathek wählen.', ['name' => $f['n']]); break; }
                    $out[$f['k']] = $id;
                    break;
                case 'color':
                    $c = is_string($v) ? $v : '';
                    if ($c === '') break;
                    if (!in_array($c, $tokens, true)) { $errors[] = __('„{name}“: Nur Farben aus dem Design der Website sind möglich.', ['name' => $f['n']]); break; }
                    $out[$f['k']] = $c;
                    break;
                case 'toggle':
                    $out[$f['k']] = (bool) $v;
                    break;
            }
        }
        return ['vals' => $out, 'errors' => $errors];
    }

    /** Namen der Design-Farben der Website (für validate) */
    public static function tokenNames(): array
    {
        return array_values(array_map(fn($s) => (string) $s['token'], Graphics::swatches()));
    }
}
