<?php
declare(strict_types=1);

namespace Core;

/**
 * Sprachen der Website-Inhalte (nicht der Verwaltung – siehe I18n).
 *
 * Konfiguration: Grundeinstellungen → Sprachen (erste Sprache = Standardsprache, ohne URL-Präfix).
 * Weitere Sprachen erhalten ein Präfix: /en/…, /fr/…
 * Seiten und Datensätze haben eine Sprache (NULL = Standardsprache) und eine Übersetzungsgruppe.
 */
final class Lang
{
    private static ?array $cache = null;

    /** Aktive Sprachen [code => Bezeichnung], Standardsprache zuerst */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        foreach ((array) app()->settings->get('sys.languages', []) as $l) {
            $code = strtolower(trim((string) ($l['code'] ?? '')));
            if (preg_match('~^[a-z]{2}(-[a-z]{2})?$~', $code) && !empty($l['active']) && !isset($out[$code])) {
                $out[$code] = trim((string) ($l['label'] ?? '')) ?: strtoupper($code);
            }
        }
        $out = $out ?: ['de' => 'Deutsch'];
        // Mehrsprachigkeit für dieses Projekt abgeschaltet → nur die Standardsprache
        if (!Features::on('languages', false)) $out = array_slice($out, 0, 1, true);
        return self::$cache = $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function default(): string
    {
        return (string) array_key_first(self::all());
    }

    public static function multi(): bool
    {
        return count(self::all()) > 1;
    }

    /** Sprache der aktuellen Anfrage */
    public static function current(): string
    {
        return app()->lang ?? self::default();
    }

    public static function valid(?string $code): bool
    {
        return $code !== null && isset(self::all()[$code]);
    }

    /** NULL/leer → Standardsprache */
    public static function norm(?string $code): string
    {
        return $code !== null && $code !== '' ? $code : self::default();
    }

    /** URL-Präfix: '' für die Standardsprache, sonst '/en' */
    public static function prefix(?string $code): string
    {
        $code = self::norm($code);
        return $code === self::default() ? '' : '/' . $code;
    }

    /** SQL-Bedingung „Datensatz gehört zur Sprache“ (Spalte lang, NULL = Standard) */
    public static function sql(string $col = 'lang'): string
    {
        return "COALESCE($col, " . app()->db->pdo->quote(self::default()) . ') = ?';
    }
}
