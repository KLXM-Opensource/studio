<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

/**
 * Ablage-Daten der Grafiken, die der Core zum ANZEIGEN braucht – auch ohne den Grafik-Editor. Der Editor selbst ist ein
 * eigenes Paket (klxm/studio-grafik, Erweiterung „grafik“); seine Daten bleiben im Core, damit vorhandene Grafiken ohne das
 * Paket unverändert erscheinen und nach dem (erneuten) Einschalten weiter bearbeitbar sind:
 *
 *   graphics          Symbole, Blockvorschauen, Bausteine, Bilder mit Foto und Text (kind photo), Vorlagen (kind template)
 *   graphic_versions  frühere Fassungen („media:{id}“ bzw. „graphic:{id}“)
 *   graphic_sources   Arbeitsfassung je Grafik (Ebenen, Bewegung, Daten) – Motion und Bind lesen sie beim Ausgeben
 *   graphic_media     Bilder mit Foto und Text: Ausgaben (role out) und verwendete Fotos (role src)
 *   graphic_shared    Bausteine und Vorlagen für alle Websites (Netzwerk-Datenbank)
 *
 * Schema in Core\Database bzw. Core\Network (nur additiv). Dateien der Ablage liegen öffentlich in {Medienordner}/grafik/
 * (Symbole und Blockvorschauen als <img>, erzeugte Fassungen von Themed/Motion/Bind in t/, m/, b/).
 */
final class Store
{
    /** Art der Bildkompositionen (Persona Bild) in der Tabelle graphics */
    public const PHOTO = 'photo';

    /** Ordner der Ablage (öffentlich) */
    public static function dir(): string
    {
        return site()->mediaDir('grafik');
    }

    /** Öffentliche Adresse einer Datei der Ablage */
    public static function url(string $file): string
    {
        return site()->mediaUrl('grafik/' . $file);
    }

    /** Arbeitsfassung zur Web-Datei $web (null, wenn keine oder die Datei inzwischen anders ist) */
    public static function source(string $ref, string $web): ?string
    {
        try {
            $row = app()->db->fetch('SELECT svg, hash FROM graphic_sources WHERE ref = ?', [$ref]);
        } catch (\Throwable) {
            return null;
        }
        return $row && hash_equals((string) $row['hash'], sha1($web)) ? (string) $row['svg'] : null;
    }

    /**
     * „Verwendet auf“ eines Fotos: Bilder mit Foto und Text, die es verwenden. Ohne Adresse – das Bearbeiten gehört dem
     * Grafik-Editor, der sie über den Filter „media.usages“ ergänzt (Eintrag mit 'graphic' = ID der Komposition).
     * @return list<array{label: string, url: ?string, graphic: int}>
     */
    public static function usagesOf(int $mediaId): array
    {
        $out = [];
        try {
            $rows = app()->db->fetchAll("SELECT g.id, g.name FROM graphic_media gm JOIN graphics g ON g.id = gm.graphic_id WHERE gm.media_id = ? AND gm.role = 'src' AND g.kind = ? ORDER BY g.name",
                [$mediaId, self::PHOTO]);
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $r) $out[] = ['label' => __('Bild aus dem Grafik-Editor') . ' → ' . (string) $r['name'], 'url' => null, 'graphic' => (int) $r['id']];
        return $out;
    }

    /** @return array<int, true> Fotos, die in Bildern mit Foto und Text stecken (Filter „Nicht verwendet“) */
    public static function usedIds(): array
    {
        $out = [];
        try {
            foreach (app()->db->fetchAll("SELECT DISTINCT media_id FROM graphic_media WHERE role = 'src'") as $r) $out[(int) $r['media_id']] = true;
        } catch (\Throwable) {
            // Tabelle fehlt
        }
        return $out;
    }
}
