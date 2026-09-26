<?php
// KLXM Studio – Copyright (C) 2026 KLXM and contributors. Lizenz: MIT (siehe lib/compat/joomla-string/LICENSE)
declare(strict_types=1);

namespace Joomla\String;

/**
 * Schlanker Ersatz für joomla/string (GPL-2.0-or-later) – eigenständig geschrieben auf Basis von ext-mbstring.
 *
 * wamania/php-stemmer (MIT, genutzt von loupe/loupe) verlangt joomla/string, braucht daraus aber nur fünf
 * UTF-8-sichere Funktionen. composer.json ersetzt das Paket ("replace"), damit keine GPL-Abhängigkeit
 * mitgeliefert wird. Nur die vom Stemmer genutzten Methoden, Signaturen kompatibel.
 */
final class StringHelper
{
    private const ENC = 'UTF-8';

    public static function strlen(string $str): int
    {
        return mb_strlen($str, self::ENC);
    }

    /** $length false/null = bis zum Ende */
    public static function substr(string $str, int $offset, int|false|null $length = false): string
    {
        return mb_substr($str, $offset, $length === false ? null : $length, self::ENC);
    }

    /** Position oder false; $offset false = 0 */
    public static function strpos(string $str, string $search, int|false|null $offset = false): int|false
    {
        return $search === '' ? false : mb_strpos($str, $search, (int) $offset, self::ENC);
    }

    public static function strrpos(string $str, string $search, int $offset = 0): int|false
    {
        return $search === '' ? false : mb_strrpos($str, $search, $offset, self::ENC);
    }

    public static function strtolower(string $str): string
    {
        return mb_strtolower($str, self::ENC);
    }

    public static function strtoupper(string $str): string
    {
        return mb_strtoupper($str, self::ENC);
    }
}
