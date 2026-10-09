<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Werte für die Anzeige formatieren – eine Stelle für Datum, Zahl, Größe, Dauer, Telefon, Adresse und Auszug (nach rex_formatter).
 * Sprache: Website-Sprache (Lang::current()) bzw. ausdrücklich Format::for('en'); die Verwaltung nutzt Format::admin() (I18n::locale()).
 *
 *   fmt()->date($e['beginn'])                 24.09.2026 | 24/09/2026      Stile: short, long, weekday, day_month
 *   fmt()->time('2026-09-24 14:05')           14:05
 *   fmt()->datetime($ts)                      24.09.2026, 14:05
 *   fmt()->relative($ts)                      gerade eben · vor 5 Min. · heute, 14:05 · gestern, 09:12 · vor 3 Tagen · 12.09.2026
 *   fmt()->number(1234.5, 1)                  1.234,5 | 1,234.5
 *   fmt()->decimal(2.50)                      2,5 (höchstens 2 Nachkommastellen, ohne Nullen am Ende)
 *   fmt()->currency(19.9)                     19,90 € | €19.90
 *   fmt()->bytes(1572864)                     1,5 MB (unter 1 MB: ganze KB, mindestens 1 KB)
 *   fmt()->duration(185)                      3:05 min · duration(185, false) → 3:05
 *   fmt()->phone('0211 123 45')               wie eingegeben bzw. international in weiteren Sprachen (phone_display())
 *   fmt()->host('https://www.example.org/a')  example.org
 *   fmt()->excerpt($html, 160)                reiner Text, an einer Wortgrenze gekürzt, „ …“ (excerpt($text, 160, false): Eingabe schon Text)
 *
 * Alles liefert reinen Text – beim Ausgeben in HTML wie immer e() verwenden. Leere bzw. ungültige Werte ergeben ''.
 */
final class Format
{
    /** Wörter der relativen Angaben (wie lang/en.php der Verwaltung) – unabhängig vom Wörterbuch, auch auf der Website */
    private const WORDS = [
        'en' => ['gerade eben' => 'just now', 'vor {n} Min.' => '{n} min. ago', 'heute, {time}' => 'today, {time}',
            'gestern' => 'yesterday', 'gestern, {time}' => 'yesterday, {time}', 'vor {n} Tagen' => '{n} days ago'],
    ];
    /** Währungszeichen */
    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF'];

    /** @var array<string, self> */
    private static array $cache = [];

    private function __construct(public readonly string $lang) {}

    /** Formatierer für eine Sprache (null = Sprache der aufgerufenen Seite, Lang::current()) */
    public static function for(?string $lang = null): self
    {
        if ($lang === null) {
            try {
                $lang = Lang::current();
            } catch (\Throwable) {
                $lang = 'de';
            }
        }
        $lang = strtolower(substr($lang, 0, 2)) ?: 'de';
        return self::$cache[$lang] ??= new self($lang);
    }

    /** Formatierer für die Verwaltung (Sprache der angemeldeten Person, I18n::locale()) */
    public static function admin(): self
    {
        return self::for(I18n::locale());
    }

    // ------------------------------------------------------------------ Datum und Zeit

    /** Datum: short (24.09.2026 / 24/09/2026), long (24. September 2026), weekday (Donnerstag), day_month (24. September) */
    public function date(int|string|\DateTimeInterface|null $when, string $style = 'short'): string
    {
        $ts = self::ts($when);
        if ($ts === null) return '';
        $de = $this->lang === 'de';
        if (class_exists(\IntlDateFormatter::class)) {
            $pattern = match ($style) {
                'long' => $de ? 'd. MMMM y' : 'd MMMM y',
                'weekday' => 'EEEE',
                'day_month' => $de ? 'd. MMMM' : 'd MMMM',
                default => $de ? 'dd.MM.y' : 'dd/MM/y',
            };
            $f = new \IntlDateFormatter($this->lang, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $pattern);
            $out = $f->format($ts);
            if (is_string($out)) return $out;
        }
        $months = ['de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']];
        $days = ['de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']];
        $m = isset($months[$this->lang]) ? $months[$this->lang][(int) date('n', $ts) - 1] : date('F', $ts);
        return match ($style) {
            'long' => date('j', $ts) . ($de ? '. ' : ' ') . $m . ' ' . date('Y', $ts),
            'weekday' => isset($days[$this->lang]) ? $days[$this->lang][(int) date('w', $ts)] : date('l', $ts),
            'day_month' => date('j', $ts) . ($de ? '. ' : ' ') . $m,
            default => date($de ? 'd.m.Y' : 'd/m/Y', $ts),
        };
    }

    /** Uhrzeit 24-Stunden „14:05“ */
    public function time(int|string|\DateTimeInterface|null $when): string
    {
        $ts = self::ts($when);
        return $ts === null ? '' : date('H:i', $ts);
    }

    /** Datum und Uhrzeit „24.09.2026, 14:05“ (Stil des Datums wie date()) */
    public function datetime(int|string|\DateTimeInterface|null $when, string $style = 'short'): string
    {
        $ts = self::ts($when);
        return $ts === null ? '' : $this->date($ts, $style) . ', ' . date('H:i', $ts);
    }

    /**
     * Relative Angabe. $unit 'minute' (Standard): „gerade eben“, „vor 5 Min.“, „heute, 14:05“, „gestern, 09:12“, „vor 3 Tagen“
     * (bis 7 Tage), sonst Datum. $unit 'day': „heute, 14:05“, „gestern“, „vor 3 Tagen“ (Kalendertage), sonst Datum.
     */
    public function relative(int|string|\DateTimeInterface|null $when, ?int $now = null, string $unit = 'minute'): string
    {
        $t = self::ts($when);
        if ($t === null) return '';
        $now ??= time();
        if ($unit === 'day') {
            $days = (int) floor((strtotime('today', $now) - strtotime('today', $t)) / 86400);
            return match (true) {
                $days <= 0 => $this->word('heute, {time}', ['time' => date('H:i', $t)]),
                $days === 1 => $this->word('gestern'),
                $days < 7 => $this->word('vor {n} Tagen', ['n' => $days]),
                default => $this->date($t),
            };
        }
        $d = $now - $t;
        return match (true) {
            $d < 90 => $this->word('gerade eben'),
            $d < 3600 => $this->word('vor {n} Min.', ['n' => (int) round($d / 60)]),
            $d < 86400 && date('Y-m-d', $t) === date('Y-m-d', $now) => $this->word('heute, {time}', ['time' => date('H:i', $t)]),
            date('Y-m-d', $t) === date('Y-m-d', $now - 86400) => $this->word('gestern, {time}', ['time' => date('H:i', $t)]),
            $d < 7 * 86400 => $this->word('vor {n} Tagen', ['n' => (int) ceil($d / 86400)]),
            default => $this->date($t),
        };
    }

    // ------------------------------------------------------------------ Zahlen

    /** Zahl mit festen Nachkommastellen: 1.234,5 (de) bzw. 1,234.5 */
    public function number(int|float|string|null $v, int $decimals = 0): string
    {
        if ($v === null || $v === '' || !is_numeric($v)) return '';
        [$dp, $ts] = $this->separators();
        return number_format((float) $v, max(0, min(10, $decimals)), $dp, $ts);
    }

    /** Zahl mit höchstens $max Nachkommastellen, ohne Nullen am Ende: 2,5 · 3 · 1.234,57 */
    public function decimal(int|float|string|null $v, int $max = 2): string
    {
        $s = $this->number($v, $max);
        if ($s === '' || $max <= 0) return $s;
        [$dp] = $this->separators();
        return rtrim(rtrim($s, '0'), $dp);
    }

    /** Betrag: 19,90 € (de) bzw. €19.90; $decimals null = 2, bei ganzen Beträgen 0 */
    public function currency(int|float|string|null $v, string $currency = 'EUR', ?int $decimals = 2): string
    {
        if ($v === null || $v === '' || !is_numeric($v)) return '';
        $dec = $decimals ?? (fmod((float) $v, 1.0) ? 2 : 0);
        $n = $this->number(abs((float) $v), $dec);
        $sym = self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency);
        $neg = (float) $v < 0 ? '−' : '';
        return $this->lang === 'de' || mb_strlen($sym) > 1 ? $neg . $n . ' ' . $sym : $neg . $sym . $n;
    }

    /** Dateigröße: unter 1 MB ganze KB (mindestens 1 KB), dann MB bzw. GB mit einer Nachkommastelle */
    public function bytes(int|float|null $bytes): string
    {
        if ($bytes === null || $bytes < 0) return '';
        if ($bytes >= 1073741824) return $this->number($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576) return $this->number($bytes / 1048576, 1) . ' MB';
        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /** Dauer: 3:05 min bzw. 1:02:03 h; ohne Einheit 3:05 / 1:02:03 (z. B. für Player) */
    public function duration(int|float|null $seconds, bool $unit = true): string
    {
        if ($seconds === null || $seconds < 0) return '';
        $s = (int) round($seconds);
        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) . ($unit ? ' h' : '')
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60) . ($unit ? ' min' : '');
    }

    // ------------------------------------------------------------------ Text

    /** Telefonnummer für die Anzeige (Standardsprache wie eingegeben, sonst international – phone_display()) */
    public function phone(?string $number): string
    {
        return phone_display($number, $this->lang);
    }

    /** Host einer Adresse ohne „www.“: https://www.example.org/pfad → example.org */
    public function host(?string $url): string
    {
        $u = trim((string) $url);
        if ($u === '') return '';
        $h = parse_url(str_contains($u, '://') ? $u : 'https://' . ltrim($u, '/'), PHP_URL_HOST);
        return is_string($h) ? (string) preg_replace('~^www\.~i', '', strtolower($h)) : '';
    }

    /**
     * Auszug als reiner Text: Tags entfernen (Rich-Text → Text, Entities aufgelöst), *Betonung*-Sternchen entfernen, Leerraum zusammenfassen, an einer Wortgrenze
     * kürzen, „ …“ anhängen. $html = false: Eingabe ist schon reiner Text – „<b>“ oder „&amp;“ bleiben dann stehen.
     */
    public function excerpt(?string $text, int $max = 160, bool $html = true): string
    {
        $s = (string) $text;
        if ($html && (str_contains($s, '<') || str_contains($s, '&'))) {
            $s = html_entity_decode(strip_tags((string) preg_replace('~<(br|/p|/li|/h\d|/blockquote)\b[^>]*>~i', ' $0', $s)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $s = strip_emphasis(trim((string) preg_replace('~\s+~u', ' ', $s)));   // *Betonung* gibt es im Auszug nicht
        if ($max <= 0 || mb_strlen($s) <= $max) return $s;
        $cut = mb_substr($s, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $max * .6) $cut = mb_substr($cut, 0, $sp);
        return rtrim($cut, " ,.;:–-") . ' …';
    }

    // ------------------------------------------------------------------ intern

    /** [Dezimaltrenner, Tausendertrenner] */
    private function separators(): array
    {
        return $this->lang === 'de' ? [',', '.'] : ['.', ','];
    }

    private function word(string $text, array $params = []): string
    {
        $s = $this->lang === 'de' ? $text : (self::WORDS[$this->lang][$text] ?? self::WORDS['en'][$text] ?? $text);
        foreach ($params as $k => $v) $s = str_replace('{' . $k . '}', (string) $v, $s);
        return $s;
    }

    /** Zeitstempel aus int, Zeichenkette („Y-m-d H:i:s“, ISO …) oder DateTime; null = leer/ungültig */
    private static function ts(int|string|\DateTimeInterface|null $when): ?int
    {
        if ($when instanceof \DateTimeInterface) return $when->getTimestamp();
        if (is_int($when)) return $when;
        if ($when === null || trim($when) === '') return null;
        $t = strtotime($when);
        return $t === false ? null : $t;
    }
}
