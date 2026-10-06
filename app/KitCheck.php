<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console kit:check [--kit=name] [--all] [--strict] – Überschreibungen eines Kits prüfen (ohne Zustand, nur Quellen):
 *  - verwaiste Block-Vorlagen: kits/{kit}/blocks/{typ}.php ohne Block dieses Namens (Kit, Kern, Layout, Erweiterung) – wird nie gerendert;
 *  - Überschreibungen von Kern-Blöcken (Datenliste, Partner …): Angaben ($d['…'], $b->data['…'], $b->edit('…')), die der Kern-Block
 *    weder als Feld kennt noch selbst liest (Vertrag geändert bzw. Tippfehler), und neue Felder des Kerns, die die Kit-Fassung nicht
 *    liest (nur mit -v; viele werden über Kern-Helfer verarbeitet);
 *  - Kern-Fragmente mit geändertem Original (Core\Fragments::overrides, wie fragments:list);
 *  - Kit-Vertrag: Pflicht-Rollen --kit-* (accent, on-accent, link, muted, radius) in den Stylesheets des Kits.
 */
final class KitCheck
{
    /** @return list<array{level: string, file: string, text: string}> level: warn | info */
    public static function run(Theme $kit, bool $verbose = false): array
    {
        $out = [];
        $blocks = $kit->blocks();
        // Blöcke aktiver Erweiterungen hängen am aktiven Kit (Extension::blocks)
        if (isset(app()->theme)) {
            foreach (app()->theme->blocks() as $type => $b) if (!empty($b['extension'])) $blocks[$type] ??= $b;
        }
        $core = require ROOT . '/app/Blocks/blocks.php';
        $own = (array) ($kit->def['blocks'] ?? []);
        // Teil-Vorlagen: Dateien mit „_“ am Anfang oder solche, die eine andere Kit-Datei per include/require einbindet
        // (z. B. blocks/stage.php → stage-photo.php) – keine eigenen Blöcke, also nicht „verwaist“
        $included = [];
        foreach (array_merge(glob($kit->path . '/blocks/*.php') ?: [], glob($kit->path . '/templates/*.php') ?: [], glob($kit->path . '/templates/**/*.php') ?: [], [$kit->path . '/functions.php']) as $src) {
            if (!is_file($src)) continue;
            if (preg_match_all('~(?:include|require)(?:_once)?\s*\(?\s*[^;]*?[\'"](?:[^\'"]*/)?([\w.-]+\.php)[\'"]~', (string) file_get_contents($src), $m)) {
                foreach ($m[1] as $name) $included[$name] = true;
            }
        }
        foreach (glob($kit->path . '/blocks/*.php') ?: [] as $file) {
            $type = basename($file, '.php');
            $rel = Kit::relative($file);
            if (!isset($blocks[$type]) && (str_starts_with($type, '_') || isset($included[$type . '.php']))) continue;
            if (!isset($blocks[$type])) {
                $out[] = ['level' => 'warn', 'file' => $rel, 'text' => "verwaist: kein Block „{$type}“ (weder im Kit noch im Kern oder in einer Erweiterung) – wird nie gerendert"];
                continue;
            }
            // Überschreibung eines Kern-Blocks (nicht vom Kit selbst definiert): Vertrag vergleichen
            if (isset($own[$type]) || !isset($core[$type]) || ($kit->def['core_blocks'] ?? true) === false) continue;
            $fields = self::fieldNames((array) ($core[$type]['fields'] ?? []));
            $coreFile = ROOT . '/app/Blocks/' . $type . '.php';
            $coreUses = is_file($coreFile) ? self::keys((string) file_get_contents($coreFile)) : [];
            $kitUses = self::keys((string) file_get_contents($file));
            $unknown = array_values(array_diff($kitUses, $fields, $coreUses));
            if ($unknown) {
                $out[] = ['level' => 'warn', 'file' => $rel, 'text' => "überschreibt Kern-Block „{$type}“ und liest Angaben, die der Kern nicht (mehr) kennt: " . implode(', ', $unknown)];
            }
            $unused = array_values(array_diff(array_intersect($fields, $coreUses), $kitUses));
            if ($verbose && $unused) {
                $out[] = ['level' => 'info', 'file' => $rel, 'text' => "überschreibt Kern-Block „{$type}“; die Kern-Fassung liest zusätzlich: " . implode(', ', $unused)];
            }
        }
        foreach (Fragments::overrides($kit->name, false) as $o) {
            if (!empty($o['changed'])) $out[] = ['level' => 'warn', 'file' => Kit::relative((string) ($o['file'] ?? $o['name'])), 'text' => "Kern-Fragment „{$o['name']}“ überschrieben – Original geändert seit {$o['since']} (fragments:list --accept nach Prüfung)"];
        }
        // Kit-Vertrag: Kern-Bausteine und Erweiterungen lesen nur --kit-* – fehlt eine Rolle, fallen sie auf neutrale Vorgaben zurück
        $missing = self::missingContract($kit);
        if ($missing) {
            $out[] = ['level' => 'warn', 'file' => Kit::relative($kit->path . '/assets/css'), 'text' => 'Kit-Vertrag unvollständig – nicht gesetzt: ' . implode(', ', $missing)
                . ' (Buchung, Check, Mitglieder, Suche, Glossar … nutzen dann neutrale Farben; Entwicklerhandbuch → CSS & JS → „Kit-Vertrag“)'];
        }
        return $out;
    }

    /** Rollen des Kit-Vertrags: Pflicht (ohne sie passt keine Erweiterung zum Kit) und empfohlen */
    public const CONTRACT = ['accent', 'on-accent', 'link', 'muted', 'radius'];
    public const CONTRACT_OPTIONAL = ['ink', 'text', 'bg', 'surface', 'line', 'font', 'font-head'];

    /** Fehlende Pflicht-Rollen des Kit-Vertrags (--kit-*) in den Stylesheets des Kits */
    public static function missingContract(Theme $kit): array
    {
        $css = '';
        foreach (glob($kit->path . '/assets/css/*.css') ?: [] as $f) $css .= (string) file_get_contents($f);
        if ($css === '') return [];   // Kit ohne eigene Stylesheets (z. B. reine Vorlagen) – nichts zu prüfen
        return array_values(array_map(fn($r) => "--kit-$r", array_filter(self::CONTRACT, fn($r) => !preg_match('~--kit-' . preg_quote($r, '~') . '\s*:~', $css))));
    }

    /** Feldnamen eines Blocks (oberste Ebene, ohne Überschriften) */
    private static function fieldNames(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) if (is_array($f) && ($f['name'] ?? '') !== '') $out[] = (string) $f['name'];
        return $out;
    }

    /** Gelesene Angaben einer Vorlage: $d['x'], $d["x"], $b->data['x'], $b->edit('x…'), $b->val('x') – ohne interne (_x) */
    public static function keys(string $php): array
    {
        preg_match_all('~(?:\$d|\$b->data)\[\s*[\'"]([a-z][a-z0-9_]*)[\'"]\s*\]~i', $php, $m1);
        preg_match_all('~\$b->(?:edit|val|has)\(\s*[\'"]([a-z][a-z0-9_]*)~i', $php, $m2);
        $keys = array_values(array_unique([...$m1[1], ...$m2[1]]));
        sort($keys);
        return $keys;
    }
}
