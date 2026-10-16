<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Kit;

use Core\Data\Tables;
use Core\Design;
use Core\Media;
use Core\PageCache;
use Core\Pages;

/**
 * Gemeinsame Bausteine der Demo-Inhalte von Kits (kits/{kit}/tools/demo-content.php und tools/demo.php).
 *
 * Die Kits behalten ihre Inhalte (Texte, Bilder, Tabellen, Seitenbäume, Vorlagen); hier liegt nur, was in jedem Kit gleich ist:
 *   Blöcke und Seiten        block(), hero(), page(), blockId()
 *   Datentabellen, Medien    table(), import(), pdf() + pdfText() (einseitige PDF ohne Bibliothek), rrect() (GD)
 *   Anlegen und Entfernen    fill() (fiktive Kontaktdaten), ids(), deletePages(), deleteTables(), deleteTablesByPrefix(),
 *                            deleteMedia(), deleteCollections()
 *   Kommandozeile            cli() – Rahmen von tools/demo.php (--remove, --heroes, --force, --preset=…)
 *
 * $log ist überall fn(string $meldung): void (z. B. Ausgabe auf der Kommandozeile oder stumm beim Seeden).
 */
final class Demo
{
    /** Zufällige ID eines Blocks (10 Zeichen) */
    public static function blockId(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 10);
    }

    /** Block im Seitenformat: Typ, Daten und Abschnitts-Optionen (Hintergrund, Abstände, Anker …) */
    public static function block(string $type, array $data, array $tunes = []): array
    {
        return ['id' => self::blockId(), 'type' => $type, 'data' => $data, 'tunes' => ['section' => $tunes]];
    }

    /** Einstieg (Hero) für die Musterseite „Hero-Varianten“: Variante, Daten, fehlende Felder aus $defaults */
    public static function hero(string $variant, array $data, array $tunes = [], array $defaults = []): array
    {
        return self::block('hero', ['variant' => $variant] + $data + $defaults, $tunes);
    }

    /** Veröffentlichte Seite im Menü anlegen ($f: slug, title, parent_id, sort … – überschreibt die Vorgaben); liefert die ID */
    public static function page(array $f, array $blocks): int
    {
        return Pages::create($f + ['status' => 'published', 'menu' => 1], Pages::sanitizeBlocks($blocks));
    }

    /** Datentabelle anlegen; bei ungültiger Definition null und Meldung im Protokoll */
    public static function table(array $def, callable $log): ?array
    {
        [$clean, $errors] = Tables::validate($def);
        if ($errors) {
            $log('Tabelle ' . $def['handle'] . ': ' . implode(' ', $errors));
            return null;
        }
        return Tables::find(Tables::create($clean));
    }

    /**
     * Erzeugte Datei in die Mediathek übernehmen ($meta: title, tags, credit, collection …, wie Media::import) und die
     * Quelldatei danach löschen ($unlink); Fehler kommen als „{name}: …“ ins Protokoll. Liefert die Medien-ID.
     */
    public static function import(string $file, string $name, string $alt, array $meta, callable $log, bool $unlink = true): ?int
    {
        [$m, $err] = Media::import($file, $name, $alt, $meta);
        if ($unlink) @unlink($file);
        if ($err) $log($name . ': ' . $err);
        return $m ? (int) $m['id'] : null;
    }

    /** Textzeile für pdf(): eine Zeile in Schriftgröße $size oben links */
    public static function pdfText(string $text, int $size = 20): string
    {
        return "BT /F1 $size Tf 72 760 Td ($text) Tj ET";
    }

    /**
     * Einseitige PDF (A4, eine Standardschrift, nur ASCII) für Download-Blöcke in die Mediathek übernehmen.
     * $stream: PDF-Inhaltsstrom (z. B. pdfText()), $name: Dateiname, $meta: title, tags … wie Media::import.
     */
    public static function pdf(string $stream, string $name, array $meta, callable $log, string $font = 'Helvetica'): ?int
    {
        $file = tempnam(sys_get_temp_dir(), 'demo') . '.pdf';
        file_put_contents($file, self::pdfBytes($stream, $font));
        [$m, $err] = Media::import($file, $name, '', $meta);
        @unlink($file);
        if ($err) $log('PDF: ' . $err);
        return $m ? (int) $m['id'] : null;
    }

    /** Inhalt der einseitigen PDF (Katalog, Seitenbaum, Seite, Inhaltsstrom, Schrift + Querverweistabelle) */
    public static function pdfBytes(string $stream, string $font = 'Helvetica'): string
    {
        $objs = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
            '<</Length ' . strlen($stream) . ">>stream\n$stream\nendstream",
            '<</Type/Font/Subtype/Type1/BaseFont/' . $font . '>>',
        ];
        $pdf = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= 'trailer<</Size ' . (count($objs) + 1) . "/Root 1 0 R>>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }

    /** Gefülltes Rechteck mit runden Ecken (GD); der Radius wird auf die halbe Breite/Höhe begrenzt */
    public static function rrect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
    {
        $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
    }

    /** Einstellungen der Website (z. B. fiktive Kontaktdaten) nur dort setzen, wo noch nichts eingetragen ist */
    public static function fill(array $settings): void
    {
        foreach ($settings as $k => $v) {
            if (trim((string) setting($k)) === '') app()->settings->set($k, $v);
        }
    }

    /**
     * IDs aus einer Abfrage (Spalte id), z. B. die Seiten eines Demo-Seitenbaums
     * @return list<int>
     */
    public static function ids(string $sql, array $params = []): array
    {
        return array_map('intval', array_column(app()->db->fetchAll($sql, $params), 'id'));
    }

    /** Seiten löschen und die Pfade neu aufbauen */
    public static function deletePages(array $ids): void
    {
        foreach (array_unique($ids) as $id) app()->db->query('DELETE FROM pages WHERE id = ?', [(int) $id]);
        if ($ids) Pages::rebuildPaths();
    }

    /** Datentabellen nach Kurzname löschen (in dieser Reihenfolge – verknüpfte Tabellen zuerst nennen) */
    public static function deleteTables(string ...$handles): void
    {
        foreach ($handles as $h) {
            if ($t = Tables::find($h)) Tables::delete($t);
        }
    }

    /** Alle Datentabellen löschen, deren Kurzname so beginnt (z. B. „demo_“) */
    public static function deleteTablesByPrefix(string $prefix): void
    {
        foreach (Tables::all() as $t) {
            if (str_starts_with($t['handle'], $prefix)) Tables::delete($t);
        }
    }

    /** Medien mit einem der Schlagwörter löschen */
    public static function deleteMedia(string ...$tags): void
    {
        if (!$tags) return;
        $where = implode(' OR ', array_fill(0, count($tags), 'tags LIKE ?'));
        foreach (app()->db->fetchAll('SELECT id FROM media WHERE ' . $where, array_map(fn($t) => '%' . $t . '%', $tags)) as $m) Media::delete((int) $m['id']);
    }

    /** Medien-Sammlungen nach Name löschen (die Medien selbst bleiben – dafür deleteMedia()) */
    public static function deleteCollections(string ...$names): void
    {
        if (!$names) return;
        app()->db->query('DELETE FROM media_collections WHERE name IN (' . implode(', ', array_fill(0, count($names), '?')) . ')', $names);
    }

    /**
     * Rahmen für kits/{kit}/tools/demo.php (nach app/bootstrap.php und demo-content.php aufrufen); liefert den Exit-Code.
     *
     * $o:
     *   kit      Name des Kits (für die Meldung, wenn die Website ein anderes nutzt)
     *   accept   fn(string $aktivesKit): bool – passt die Website? Vorgabe: dieses Kit oder ein Kit mit eigenem {kit}_demo_install()
     *   reject   eigene Meldung, wenn accept() nein sagt (%s = aktives Kit)
     *   actions  Schalter => fn(callable $log): ?bool, in dieser Reihenfolge geprüft; der erste gesetzte Schalter läuft allein.
     *            true/false → Exit-Code 0/1; ohne Rückgabe (z. B. --remove): Seiten-Cache leeren, Exit-Code 0
     *   install  fn(bool $force, callable $log, array $args): mixed – ohne Schalter aus „actions“; eine Zahl beendet mit diesem
     *            Exit-Code, alles andere geht weiter mit --preset=…
     *   preset   Wort für Design-Voreinstellungen in Meldungen (Vorgabe „Vorlage“)
     *   missing  sprintf-Format, wenn es die Voreinstellung nicht gibt (%1$s Wort, %2$s Schlüssel, %3$s vorhandene, mit Komma)
     */
    public static function cli(array $argv, array $o): int
    {
        $args = array_slice($argv, 1);
        $log = function (string $m): void { echo $m, "\n"; };
        $active = app()->theme->name;
        $kit = (string) ($o['kit'] ?? '');
        $accept = $o['accept'] ?? fn(string $name) => $name === $kit || function_exists($name . '_demo_install');
        if (!$accept($active)) {
            fwrite(STDERR, sprintf($o['reject'] ?? "Die Website nutzt nicht das Kit „{$kit}“ (aktiv: %s).\n", $active));
            return 1;
        }
        foreach ($o['actions'] ?? [] as $flag => $fn) {
            if (!in_array($flag, $args, true)) continue;
            $ok = $fn($log);
            if (is_bool($ok)) return $ok ? 0 : 1;
            PageCache::clear();
            return 0;
        }
        $done = isset($o['install']) ? $o['install'](in_array('--force', $args, true), $log, $args) : null;
        if (is_int($done)) return $done;
        $word = (string) ($o['preset'] ?? 'Vorlage');
        foreach ($args as $a) {
            if (!str_starts_with($a, '--preset=')) continue;
            $key = substr($a, 9);
            $preset = Design::def()['presets'][$key] ?? null;
            if (!$preset) {
                fwrite(STDERR, sprintf($o['missing'] ?? "%1\$s „%2\$s“ gibt es nicht. Verfügbar: %3\$s\n", $word, $key, implode(', ', array_keys(Design::def()['presets']))));
                return 1;
            }
            Design::save($preset['values'], $word . ' ' . $preset['label'] . ' (tools/demo.php)', 'cli');
            $log('Design-' . $word . ' „' . $preset['label'] . '“ übernommen.');
        }
        return 0;
    }
}
