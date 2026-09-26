<?php
declare(strict_types=1);

namespace Core;

/**
 * Übersetzungen der Oberfläche (Verwaltung, Editor, Core-Texte).
 *
 * Quellsprache ist Deutsch: Der deutsche Text ist zugleich der Schlüssel – __('Speichern').
 * Übersetzungen liegen in lang/{locale}.php (Core) und themes/{name}/lang/{locale}.php (Theme, überschreibt).
 * Platzhalter: __('{n} Einträge', ['n' => 3]).
 */
final class I18n
{
    public const SOURCE = 'de';

    private static string $locale = self::SOURCE;
    private static array $dict = [];

    /** Verfügbare Oberflächensprachen: ['de' => 'Deutsch', 'en' => 'English', …] */
    public static function available(): array
    {
        $out = [self::SOURCE => 'Deutsch'];
        foreach (glob(ROOT . '/lang/*.php') ?: [] as $f) {
            $code = basename($f, '.php');
            $data = require $f;
            $out[$code] = (string) ($data['_name'] ?? $code);
        }
        return $out;
    }

    public static function setLocale(string $locale): void
    {
        $locale = preg_replace('~[^a-z_\-]~i', '', $locale) ?: self::SOURCE;
        self::$locale = $locale;
        self::$dict = [];
        if ($locale === self::SOURCE) {
            return;
        }
        $files = [ROOT . '/lang/' . $locale . '.php'];
        foreach (Extensions::active() as $x) $files[] = $x->dir . '/lang/' . $locale . '.php';
        $files[] = app()->theme->path . '/lang/' . $locale . '.php';
        foreach ($files as $f) {
            if (is_file($f)) {
                self::$dict = array_merge(self::$dict, (array) require $f);
            }
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function translate(string $text, array $params = []): string
    {
        $out = self::$dict[$text] ?? $text;
        foreach ($params as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    // ---------------------------------------------------------------- Texte der Website (Inhaltssprache)

    private static array $siteDict = [];

    /**
     * Feste Texte der Website (Templates, Blöcke, Core-Ausgaben) in der Sprache der aufgerufenen Seite – lt('Anfahrt').
     * Quelle: Sprache des Themes (theme.php → 'source_lang', Standard de). Übersetzungen:
     *   lang/site/{lang}.php (Core) und themes/{name}/lang/site/{lang}.php (Theme, überschreibt).
     * Fehlt eine Übersetzung, bleibt der Quelltext stehen (php bin/console i18n:missing en --site-texts).
     */
    public static function site(string $text, array $params = []): string
    {
        $lang = Lang::current();
        $source = (string) (app()->theme->def['source_lang'] ?? self::SOURCE);
        if ($lang !== $source) {
            if (!isset(self::$siteDict[$lang])) {
                self::$siteDict[$lang] = [];
                $files = [ROOT . '/lang/site/' . $lang . '.php'];
                foreach (Extensions::active() as $x) $files[] = $x->dir . '/lang/site/' . $lang . '.php';
                $files[] = app()->theme->path . '/lang/site/' . $lang . '.php';
                foreach ($files as $f) {
                    if (is_file($f)) self::$siteDict[$lang] = array_merge(self::$siteDict[$lang], (array) require $f);
                }
            }
            $text = self::$siteDict[$lang][$text] ?? $text;
        }
        foreach ($params as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }

    /** Wörterbuch für das JavaScript der Oberfläche (nur Einträge, die im Frontend gebraucht werden) */
    public static function dictionary(): array
    {
        return array_filter(self::$dict, fn($k) => $k !== '_name', ARRAY_FILTER_USE_KEY);
    }
}
