<?php
declare(strict_types=1);

namespace Core;

/**
 * Ablage im Webroot public/ – die einzige Stelle, die weiß, wo Code- und Design-Dateien öffentlich liegen.
 *
 * Ganz oben in public/ liegen nur index.php, assets/ und die Upload-Ordner. Jeder Ordner dort sperrt die gleichnamige
 * Seitenadresse (Apache/nginx liefern den Ordner: 301 auf /name/, danach 403). Deshalb liegt alles, was Kits, Erweiterungen
 * und installierte Schriften mitbringen, unter /assets/:
 *
 *   public/assets/{css,js,fonts,icons,vendor,docs}/    Kern (Verwaltung, Editor, Lato, Symbole, Vendoren)
 *   public/assets/kits/{kit}/                          Kits (gebaut von tools/build.mjs)
 *   public/assets/ext/{name}/                          Erweiterungen (pnpm build, extensions:publish)
 *   public/assets/fonts/installed/{id}/ + fonts.json   installierte Schriften (Core\Fonts)
 *                                                      (eigener Unterordner: public/assets/fonts/ enthält die Kern-Schrift Lato)
 *   public/media/, public/pools/, public/sites/        Uploads – ihre Adressen stehen in Inhalten
 */
final class PublicPaths
{
    public const KITS = 'kits';
    public const EXT = 'ext';
    public const FONTS = 'fonts';

    /** Bereich => Ort relativ zu public/ */
    public const AREAS = [
        self::KITS => 'assets/kits',
        self::EXT => 'assets/ext',
        self::FONTS => 'assets/fonts/installed',
    ];

    /** Upload-Ordner ganz oben in public/ (ihre Adressen stehen in Inhalten) */
    public const UPLOADS = ['media', 'pools', 'sites'];

    /**
     * Seitenadressen ganz oben, die nie eine Seite sein können: Ordner in public/, feste Routen, Dateien der Wurzel.
     * Zentrale Liste für Seiten (PageController), KI-Generator, API und Datentabellen (die ihre eigenen Routen ergänzen).
     * Dazu sperrt reservedSlugs() jeden weiteren Ordner bzw. jede Datei, die in public/ liegt.
     */
    public const RESERVED_SLUGS = ['admin', 'api', 'anfrage', 'assets', 'media', 'pools', 'sites', 'geschuetzt', 'sitemap-xml', 'robots-txt', 'llms-txt', 'vcard', 'vcard-vcf', 'home', 'index-php'];

    /** Gesperrte Adressen ganz oben: die feste Liste plus jeder Ordner/jede Datei in public/ */
    public static function reservedSlugs(array $extra = []): array
    {
        $out = array_merge(self::RESERVED_SLUGS, $extra);
        foreach (scandir(self::root()) ?: [] as $f) {
            if ($f[0] === '.') continue;
            $out[] = strtolower(str_replace('.', '-', $f));
        }
        return array_values(array_unique($out));
    }

    public static function isReserved(string $slug, array $extra = []): bool
    {
        return $slug !== '' && in_array($slug, self::reservedSlugs($extra), true);
    }

    /** Absoluter Pfad von public/ */
    public static function root(): string
    {
        return ROOT . '/public';
    }

    /** Ort eines Bereichs relativ zu public/, z. B. 'assets/kits/praxis'; ohne $name der Hauptordner */
    public static function relative(string $area, string $name = ''): string
    {
        $base = self::AREAS[$area] ?? throw new \InvalidArgumentException("Unbekannter Bereich: $area");
        $name = trim($name, '/');
        return $base . ($name !== '' ? '/' . $name : '');
    }

    /** Absoluter Ordner, z. B. dir('kits', 'praxis') → …/public/assets/kits/praxis */
    public static function dir(string $area, string $name = ''): string
    {
        return self::root() . '/' . self::relative($area, $name);
    }

    /** Öffentliche Adresse ohne Versions-Parameter, z. B. url('kits', 'praxis', 'css/site.css') → /assets/kits/praxis/css/site.css */
    public static function url(string $area, string $name, string $path = ''): string
    {
        return base_path() . '/' . self::relative($area, $name) . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
