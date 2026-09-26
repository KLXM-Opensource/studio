<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Database;

/**
 * Einheitlicher Zugang zur Suche (Wissensartikel, Fragen). Alle Aufrufer gehen über diese Klasse,
 * das Verfahren dahinter ist austauschbar (Search::use()) – siehe SearchBackend.
 */
final class Search
{
    private static ?SearchBackend $backend = null;

    public static function use(SearchBackend $backend): void
    {
        self::$backend = $backend;
    }

    public static function backend(): SearchBackend
    {
        return self::$backend ??= new SqliteSearch(Support::db());
    }

    /** Suchindex anlegen (Teil der Migration der zentralen Datenbank) */
    public static function migrate(Database $db): void
    {
        SqliteSearch::migrate($db);
    }

    public static function index(string $kind, int $id, string $title, string $body, string $tags): void
    {
        self::backend()->index($kind, $id, $title, Markdown::plain($body), str_replace(',', ' ', $tags));
    }

    public static function remove(string $kind, int $id): void
    {
        self::backend()->remove($kind, $id);
    }

    public static function find(string $q, array $kinds = ['article', 'question'], int $limit = 20, bool $loose = false): array
    {
        $q = trim(mb_substr($q, 0, 200));
        if ($q === '') return [];
        return self::backend()->find($q, $kinds, $limit, $loose);
    }

    /** Suchbegriffe: Wörter ab 3 Zeichen ohne häufige Füllwörter (für „ähnliche Artikel“) */
    public static function words(string $q, bool $dropStop = true): array
    {
        static $stop = ['und', 'oder', 'aber', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'ist', 'sind',
            'wie', 'was', 'wer', 'wo', 'wird', 'werden', 'nicht', 'kein', 'keine', 'mit', 'bei', 'auf', 'für', 'von', 'vom', 'zum', 'zur', 'ich', 'wir',
            'sie', 'es', 'man', 'kann', 'können', 'soll', 'mein', 'meine', 'unser', 'unsere', 'auch', 'noch', 'nur', 'dass', 'wenn', 'dann', 'als',
            'im', 'in', 'an', 'am', 'zu', 'so', 'da', 'hat', 'habe', 'haben', 'mehr', 'sehr', 'bitte', 'danke', 'hallo', 'the', 'and', 'how', 'can'];
        preg_match_all('~[\p{L}\p{N}]{2,}~u', mb_strtolower($q), $m);
        $out = [];
        foreach ($m[0] as $w) {
            if (mb_strlen($w) < 3 && !ctype_digit($w)) continue;
            if ($dropStop && in_array($w, $stop, true)) continue;
            $out[$w] = true;
        }
        return array_map('strval', array_slice(array_keys($out), 0, 12));   // Zahlen-Schlüssel wieder als Text
    }
}
