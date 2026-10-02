<?php
declare(strict_types=1);

namespace Core\Data;

/**
 * Textlänge im Block „Datenliste“ (data_list): Texte und Titel in Karten und Listen auf wenige Zeilen kürzen.
 *
 *  - Blockoptionen text_lines (''|2|3|4|6) und title_lines (''|2|3) – leer = vollständig (bestehende Blöcke unverändert).
 *  - Darstellung: CSS-Klassen dl-clamp dl-clamp-{n} (line-clamp, resources/css/_data-clamp.css; Kits mit eigenem data.css
 *    importieren die Datei). Kein style-Attribut – die CSP der Website erlaubt für Besucher keine Inline-Stile.
 *  - Text-, mehrzeilige und formatierte Felder erscheinen gekürzt als reiner Text (ohne Listen, Bilder, Überschriften) mit
 *    einem großzügigen Auszug vom Server (Rückfall ohne line-clamp; Screenreader lesen nur diesen Auszug, keinen doppelten Text).
 *  - Nur visuell: der volle Text steht auf der Detailseite, zu der Titel bzw. Karte verlinken – kein eigener „mehr“-Link.
 * Kits rufen Clamp::text() für die Felder und Clamp::titleClass() für die Überschrift auf.
 */
final class Clamp
{
    public const LINES = [2, 3, 4, 6];
    public const TITLE_LINES = [2, 3];
    /** Feldtypen, die gekürzt werden */
    public const TYPES = ['text', 'textarea', 'richtext'];
    /** Zeichen je Zeile für den Auszug vom Server – großzügig (breite „Liste mit Bild“), CSS kürzt genau */
    public const CHARS_PER_LINE = 120;

    /** Felddefinitionen für den Block (Seitenleiste) */
    public static function fields(): array
    {
        return [
            ['name' => 'text_lines', 'label' => 'Textlänge', 'type' => 'select', 'default' => '', 'width' => 'half',
                'options' => ['' => 'vollständig', '2' => '2 Zeilen', '3' => '3 Zeilen', '4' => '4 Zeilen', '6' => '6 Zeilen'],
                'help' => 'Kürzt Textfelder in Karten und Listen (z. B. Teaser aus einem RSS-Feed). Gekürzt erscheint der Text ohne Formatierung; der volle Text steht auf der Detailseite.'],
            ['name' => 'title_lines', 'label' => 'Titel kürzen', 'type' => 'select', 'default' => '', 'width' => 'half',
                'options' => ['' => 'nein (vollständig)', '2' => 'auf 2 Zeilen', '3' => 'auf 3 Zeilen']],
        ];
    }

    /** Gewählte Zeilenzahl für Texte (0 = vollständig) */
    public static function lines(array $d): int
    {
        $n = (int) ($d['text_lines'] ?? 0);
        return in_array($n, self::LINES, true) ? $n : 0;
    }

    /** Gewählte Zeilenzahl für Titel (0 = vollständig) */
    public static function titleLines(array $d): int
    {
        $n = (int) ($d['title_lines'] ?? 0);
        return in_array($n, self::TITLE_LINES, true) ? $n : 0;
    }

    /** Klassen für ein gekürztes Element (mit führendem Leerzeichen) oder '' */
    public static function cls(int $lines): string
    {
        return $lines > 0 ? ' dl-clamp dl-clamp-' . $lines : '';
    }

    public static function textClass(array $d): string
    {
        return self::cls(self::lines($d));
    }

    public static function titleClass(array $d): string
    {
        return self::cls(self::titleLines($d));
    }

    /** Wird dieses Feld gekürzt? */
    public static function applies(array $table, string $field): bool
    {
        return in_array(Tables::field($table, $field)['type'] ?? '', self::TYPES, true);
    }

    /** Auszug: Leerraum zusammenfassen, an einer Wortgrenze kürzen, „…“ anhängen */
    public static function excerpt(string $text, int $max): string
    {
        $s = trim((string) preg_replace('~\s+~u', ' ', $text));
        if ($max <= 0 || mb_strlen($s) <= $max) return $s;
        $cut = mb_substr($s, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $max * .6) $cut = mb_substr($cut, 0, $sp);
        return rtrim($cut, " ,.;:–-") . ' …';
    }

    /**
     * Feld für Karten/Listen: gekürzt → [Klassen, reiner Text (maskiert)], sonst ['', Entries::html()].
     * @return array{0: string, 1: string} [zusätzliche Klassen, HTML]
     */
    public static function text(array $table, array $e, string $field, array $d, array $o = ['link' => false]): array
    {
        $lines = self::lines($d);
        if (!$lines || !self::applies($table, $field)) return ['', Entries::html($table, $e, $field, $o)];
        $s = self::excerpt(Entries::text($table, $e, $field), $lines * self::CHARS_PER_LINE);
        return $s === '' ? ['', ''] : [self::cls($lines), e($s)];
    }
}
