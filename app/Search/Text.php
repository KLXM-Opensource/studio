<?php
declare(strict_types=1);

namespace Core\Search;

/** Text-Helfer der Suche: Klartext aus HTML, Vergleichsform (ohne Umlaute/Akzente), Wörter, Chunks, Auszüge. */
final class Text
{
    /** Füllwörter je Sprache – zählen bei Suche, Gewichtung und Hervorhebung nicht (Vergleichsform: ohne Umlaute) */
    public const STOP = [
        'de' => ['aber', 'als', 'am', 'an', 'auch', 'auf', 'aus', 'bei', 'bin', 'bis', 'bitte', 'da', 'damit', 'dann', 'das', 'dass', 'dem', 'den', 'der', 'des', 'die',
            'dies', 'diese', 'dieser', 'du', 'durch', 'ein', 'eine', 'einem', 'einen', 'einer', 'eines', 'er', 'es', 'fur', 'gibt', 'hat', 'habe', 'haben', 'ich', 'ihr',
            'im', 'in', 'ins', 'ist', 'kann', 'konnen', 'mein', 'meine', 'meinen', 'meiner', 'mich', 'mir', 'mit', 'nach', 'nicht', 'noch', 'nur', 'ob', 'oder', 'sich', 'sie',
            'sind', 'so', 'uber', 'um', 'und', 'uns', 'unser', 'unsere', 'vom', 'von', 'vor', 'war', 'was', 'wann', 'warum', 'welche', 'welcher', 'wenn', 'wer', 'wie', 'wir',
            'wird', 'wo', 'woher', 'wohin', 'zu', 'zum', 'zur', 'brauche', 'mochte', 'suche', 'für', 'über', 'können', 'möchte'],
        'en' => ['a', 'about', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'for', 'from', 'have', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'need',
            'of', 'on', 'or', 'our', 'please', 'the', 'there', 'this', 'to', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your'],
        'fr' => ['au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'elle', 'en', 'est', 'et', 'il', 'je', 'la', 'le', 'les', 'leur', 'mais', 'me', 'mon', 'ne',
            'nous', 'ou', 'par', 'pas', 'pour', 'quand', 'que', 'qui', 'sa', 'se', 'son', 'sur', 'un', 'une', 'vous'],
    ];

    /** Füllwörter der Sprache (Vergleichsform) */
    public static function stop(string $lang): array
    {
        return self::STOP[substr($lang, 0, 2)] ?? [];
    }

    /** HTML → Klartext mit Leerzeichen an Blockgrenzen */
    public static function plain(?string $html): string
    {
        $s = (string) $html;
        if ($s === '') return '';
        $s = preg_replace('~<(br|/p|/li|/h\d|/div|/td|/th|/tr|/blockquote)[^>]*>~i', "$0 ", $s);
        $s = html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('~\s+~u', ' ', $s));
    }

    /** Vergleichsform: klein, ohne Akzente, ß → ss, ä → a (wie Loupe/FTS „remove_diacritics“) */
    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'đ' => 'd', 'ł' => 'l']);
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($n)) return (string) preg_replace('~\p{Mn}+~u', '', $n);
        }
        return strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'á' => 'a', 'â' => 'a',
            'ç' => 'c', 'ñ' => 'n', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u']);
    }

    /** @return list<string> Wörter der Suchanfrage (Vergleichsform, ab 2 Zeichen, ohne Füllwörter der Sprache, max. 10) */
    public static function words(string $q, ?string $lang = null): array
    {
        preg_match_all('~[\p{L}\p{N}]{2,}~u', self::fold($q), $m);
        $words = array_values(array_unique($m[0]));
        $stop = self::stop($lang ?? \Core\Lang::current());
        $kept = array_values(array_diff($words, $stop));
        return array_slice($kept ?: $words, 0, 10);
    }

    /**
     * Text in Abschnitte für Embeddings teilen: ~$words Wörter (≈ 500 Tokens bei 380 Wörtern) mit Überlappung,
     * möglichst an Satzgrenzen. Kurze Texte bleiben ein Abschnitt.
     * @return list<string>
     */
    public static function chunks(string $text, int $words = 380, int $overlap = 60): array
    {
        $text = trim($text);
        if ($text === '') return [];
        $tokens = preg_split('~\s+~u', $text) ?: [];
        if (count($tokens) <= $words) return [$text];
        $out = [];
        $start = 0;
        $n = count($tokens);
        while ($start < $n) {
            $end = min($n, $start + $words);
            // Satzende in den letzten 20 % suchen
            if ($end < $n) {
                for ($i = $end - 1; $i > $start + (int) ($words * 0.8); $i--) {
                    if (preg_match('~[.!?:]["»“)]?$~u', $tokens[$i])) { $end = $i + 1; break; }
                }
            }
            $out[] = implode(' ', array_slice($tokens, $start, $end - $start));
            if ($end >= $n) break;
            $start = max($start + 1, $end - $overlap);
        }
        return $out;
    }

    /**
     * Auszug um den ersten Treffer, Treffer mit <mark> (HTML-sicher). Wortanfänge zählen (Präfixsuche), Umlaute egal.
     */
    public static function excerpt(string $text, array $words, int $len = 220): string
    {
        $text = trim($text);
        if ($text === '') return '';
        $folded = self::fold($text);
        $pos = null;
        foreach ($words as $w) {
            if (preg_match('~(?<![\p{L}\p{N}])' . preg_quote($w, '~') . '~u', $folded, $m, PREG_OFFSET_CAPTURE)) {
                $p = mb_strlen(substr($folded, 0, $m[0][1]));
                $pos = $pos === null ? $p : min($pos, $p);
            }
        }
        $start = 0;
        if ($pos !== null && $pos > $len / 3) {
            $start = $pos - (int) ($len / 3);
            $sp = mb_strpos($text, ' ', $start);
            $start = $sp !== false && $sp < $pos ? $sp + 1 : $start;
        }
        $cut = mb_substr($text, $start, $len);
        if ($start + $len < mb_strlen($text)) {
            $sp = mb_strrpos($cut, ' ');
            if ($sp !== false && $sp > $len * 0.6) $cut = mb_substr($cut, 0, $sp);
            $cut .= ' …';
        }
        return ($start > 0 ? '… ' : '') . self::mark($cut, $words);
    }

    /** Wörter (Vergleichsform) im Text markieren – Ausgabe ist HTML-escaped */
    public static function mark(string $text, array $words): string
    {
        if (!$words) return e($text);
        // Zeichenweise Zuordnung Original ↔ Vergleichsform (gleich lang je Zeichen außer ß/æ → Ausgleich über Zeichenliste)
        $chars = preg_split('~~u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $foldedChars = array_map(fn($c) => self::fold($c), $chars);
        $folded = implode('', $foldedChars);
        $ranges = [];
        foreach ($words as $w) {
            if ($w === '' ) continue;
            if (preg_match_all('~(?<![\p{L}\p{N}])' . preg_quote($w, '~') . '[\p{L}\p{N}]*~u', $folded, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$hit, $byte]) {
                    $ranges[] = [mb_strlen(substr($folded, 0, $byte)), mb_strlen($hit)];
                }
            }
        }
        if (!$ranges) return e($text);
        // Vergleichsform-Position → Zeichenindex im Original
        $map = [];
        $p = 0;
        foreach ($foldedChars as $i => $fc) {
            $l = mb_strlen($fc);
            for ($k = 0; $k < max(1, $l); $k++) $map[$p + $k] = $i;
            $p += $l;
        }
        $open = array_fill(0, count($chars) + 1, 0);
        foreach ($ranges as [$s, $l]) {
            $a = $map[$s] ?? null;
            $b = $map[$s + $l - 1] ?? null;
            if ($a === null || $b === null) continue;
            for ($i = $a; $i <= $b; $i++) $open[$i] = 1;
        }
        $html = '';
        $in = false;
        foreach ($chars as $i => $c) {
            if ($open[$i] && !$in) { $html .= '<mark>'; $in = true; }
            if (!$open[$i] && $in) { $html .= '</mark>'; $in = false; }
            $html .= e($c);
        }
        return $html . ($in ? '</mark>' : '');
    }
}
