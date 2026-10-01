<?php
declare(strict_types=1);

namespace Core;

/**
 * Ablage im Webroot public/ – die einzige Stelle, die weiß, wo Code- und Design-Dateien öffentlich liegen.
 *
 * Ganz oben in public/ liegen nur noch index.php, assets/ und die Upload-Ordner. Jeder Ordner dort sperrt die gleichnamige
 * Seitenadresse (Apache/nginx liefern den Ordner: 301 auf /name/, danach 403). Deshalb liegt alles, was Kits, Erweiterungen
 * und installierte Schriften mitbringen, unter /assets/:
 *
 *   public/assets/{css,js,fonts,icons,vendor,docs}/    Kern (Verwaltung, Editor, Lato, Symbole, Vendoren) – unverändert
 *   public/assets/kits/{kit}/                          Kits (gebaut von tools/build.mjs)          früher public/kits/, public/themes/
 *   public/assets/ext/{name}/                          Erweiterungen (pnpm build, extensions:publish) früher public/extensions/
 *   public/assets/fonts/installed/{id}/ + fonts.json   installierte Schriften (Core\Fonts)        früher public/fonts/
 *                                                      (eigener Unterordner: public/assets/fonts/ enthält die Kern-Schrift Lato)
 *   public/media/, public/pools/, public/sites/        Uploads – bleiben, ihre Adressen stehen in Inhalten
 *
 * Rückfall (Code schon neu, Ordner noch nicht umgestellt): liegt ein Kit/eine Erweiterung/der Schriften-Ordner nur am alten
 * Ort, liefern dir()/url() den alten Ort – der Webserver bedient ihn dort wie bisher. Umstellen: php bin/console assets:migrate.
 * Alte Adressen (/kits/…, /themes/…, /extensions/…, /fonts/…) leitet public/index.php mit 301 auf /assets/… um, sobald die
 * Datei dort liegt (zwischengespeicherte Seiten und Stylesheets, fremde Verweise).
 */
final class PublicPaths
{
    public const KITS = 'kits';
    public const EXT = 'ext';
    public const FONTS = 'fonts';

    /** Bereich => [neuer Ort (relativ zu public/), alte Orte in Suchreihenfolge] */
    public const AREAS = [
        self::KITS => ['assets/kits', ['kits', 'themes']],
        self::EXT => ['assets/ext', ['extensions']],
        self::FONTS => ['assets/fonts/installed', ['fonts']],
    ];

    /** Alte Adress-Präfixe ganz oben => Bereich (für die 301-Umleitung in public/index.php und Hinweise) */
    public const LEGACY_PREFIXES = ['kits' => self::KITS, 'themes' => self::KITS, 'extensions' => self::EXT, 'fonts' => self::FONTS];

    /** Upload-Ordner ganz oben in public/ (bleiben unverändert – ihre Adressen stehen in Inhalten) */
    public const UPLOADS = ['media', 'pools', 'sites'];

    /**
     * Seitenadressen ganz oben, die nie eine Seite sein können: Ordner in public/, feste Routen, Dateien der Wurzel.
     * Zentrale Liste für Seiten (PageController), KI-Generator, API und Datentabellen (die ihre eigenen Routen ergänzen).
     * Solange ein Server noch nicht umgestellt ist, sperrt Pages::slugTaken() zusätzlich jeden vorhandenen Ordner in public/.
     */
    public const RESERVED_SLUGS = ['admin', 'api', 'anfrage', 'assets', 'media', 'pools', 'sites', 'sitemap-xml', 'robots-txt', 'vcard', 'vcard-vcf', 'home', 'index-php'];

    /** Gesperrte Adressen ganz oben: die feste Liste plus jeder Ordner/jede Datei, die (noch) in public/ liegt */
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

    /**
     * Ort eines Bereichs relativ zu public/, z. B. 'assets/kits/praxis' – oder der alte Ort ('kits/praxis', 'themes/praxis'),
     * wenn es nur dort etwas gibt. Ohne $name der Hauptordner (alter nur, wenn der neue fehlt und der alte existiert).
     */
    public static function relative(string $area, string $name = ''): string
    {
        [$new, $old] = self::AREAS[$area] ?? throw new \InvalidArgumentException("Unbekannter Bereich: $area");
        $name = trim($name, '/');
        $pub = self::root();
        $sub = $name !== '' ? '/' . $name : '';
        if (file_exists($pub . '/' . $new . $sub)) return $new . $sub;
        foreach ($old as $o) {
            if (file_exists($pub . '/' . $o . $sub)) return $o . $sub;
        }
        return $new . $sub;
    }

    /** Absoluter Ordner, z. B. dir('kits', 'praxis') → …/public/assets/kits/praxis (Rückfall siehe relative()) */
    public static function dir(string $area, string $name = ''): string
    {
        return self::root() . '/' . self::relative($area, $name);
    }

    /** Öffentliche Adresse ohne Versions-Parameter, z. B. url('kits', 'praxis', 'css/site.css') → /assets/kits/praxis/css/site.css */
    public static function url(string $area, string $name, string $path = ''): string
    {
        return base_path() . '/' . self::relative($area, $name) . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /** Wurde der Bereich schon umgestellt (bzw. gibt es keinen alten Ort)? */
    public static function migrated(string $area): bool
    {
        foreach (self::AREAS[$area][1] as $o) {
            if (file_exists(self::root() . '/' . $o)) return false;
        }
        return true;
    }

    /** Alte Ordner ganz oben in public/, die noch da sind (für health und assets:migrate) */
    public static function legacyDirs(): array
    {
        return array_values(array_filter(array_keys(self::LEGACY_PREFIXES), fn($o) => file_exists(self::root() . '/' . $o) || is_link(self::root() . '/' . $o)));
    }

    /**
     * Alte Adresse (ohne base_path) auf den aktuellen Ort abbilden, z. B. '/kits/klxm/tutorials/intro' → '/assets/kits/klxm/tutorials/intro'.
     * Maßgeblich ist, wo der Ordner des Kits bzw. der Erweiterung liegt; andere Adressen bleiben unverändert.
     */
    public static function current(string $path): string
    {
        if (!preg_match('~^/(kits|themes|extensions|fonts|assets/kits|assets/ext|assets/fonts/installed)/([^/]+)(/.*)?$~', $path, $m)) return $path;
        $area = match ($m[1]) {
            'kits', 'themes', 'assets/kits' => self::KITS,
            'extensions', 'assets/ext' => self::EXT,
            default => self::FONTS,
        };
        if ($area === self::FONTS) return '/' . self::relative($area) . '/' . $m[2] . ($m[3] ?? '');
        return '/' . self::relative($area, $m[2]) . ($m[3] ?? '');
    }

    /**
     * Umleitungsziel für eine alte Adresse (Pfad ohne base_path), oder null. Nur wenn die Datei am neuen Ort liegt –
     * so bleibt eine Seite mit der Adresse /kits (oder /kits/…, falls es dort keine Datei gibt) erreichbar.
     * Wird in public/index.php vor dem Start der App aufgerufen (ohne Autoloader: reine Dateiprüfung, siehe dort).
     */
    public static function legacyRedirect(string $path, string $public): ?string
    {
        if (!preg_match('~^/(kits|themes|extensions|fonts)/([a-z0-9_\-]+(?:/[^/?#]+)+)$~i', $path, $m) || str_contains($m[2], '..')) return null;
        $new = self::AREAS[self::LEGACY_PREFIXES[strtolower($m[1])]][0];
        if (is_file($public . '/' . $new . '/' . $m[2])) return '/' . $new . '/' . $m[2];
        // Noch nicht umgestellt, aber Kit schon unter public/kits (Umbenennung themes → kits)
        if (strtolower($m[1]) === 'themes' && is_file($public . '/kits/' . $m[2])) return '/kits/' . $m[2];
        return null;
    }

    // ------------------------------------------------------------------ Umstellung (bin/console assets:migrate)

    /**
     * Alte Ordner ganz oben nach /assets/ verschieben – idempotent, mit Sicherung außerhalb des Webroots.
     *
     *   public/kits, public/themes → public/assets/kits      (je Kit; gibt es ein Kit an zwei Orten, gewinnt die neueste Fassung,
     *                                                        die andere kommt nach storage/backups/public-assets-alt-<zeit>/)
     *   public/extensions         → public/assets/ext
     *   public/fonts              → public/assets/fonts/installed (inkl. fonts.json)
     *   Symbolische Links (z. B. public/themes → kits, public/fonts → …/shared/public/fonts): zeigt der Link in public/,
     *   wird er entfernt; zeigt er nach außen (geteilter Ordner bei Releases), wird er am neuen Ort neu angelegt.
     *
     * @param callable(string):void $out
     * @return array{changes:int, errors:int, findings:list<string>}
     */
    public static function migrate(bool $dry, callable $out, bool $backup = true): array
    {
        $pub = self::root();
        $ts = date('Ymd-His');
        $changes = $errors = 0;
        $run = function (string $label, callable $fn) use ($dry, $out, &$changes, &$errors): void {
            $out(($dry ? '  (dry-run) ' : '  ') . $label);
            $changes++;
            if (!$dry && $fn() === false) { $errors++; $out('    ✗ fehlgeschlagen'); }
        };

        // 1. Sicherung der echten Ordner (Links nicht – deren Ziel liegt woanders und bleibt unberührt)
        $real = array_values(array_filter(array_keys(self::LEGACY_PREFIXES), fn($o) => is_dir("$pub/$o") && !is_link("$pub/$o")));
        if ($real && $backup) {
            $file = ROOT . "/storage/backups/public-assets-$ts.tar.gz";
            $run('Sicherung: ' . Kit::relative($file) . ' (public/' . implode(', public/', $real) . ')', function () use ($file, $pub, $real) {
                @mkdir(dirname($file), 0770, true);
                exec('tar -czf ' . escapeshellarg($file) . ' -C ' . escapeshellarg($pub) . ' ' . implode(' ', array_map('escapeshellarg', $real)) . ' 2>&1', $o, $code);
                return $code === 0 && is_file($file);
            });
            if (!$dry && $errors) {
                $out('Abbruch: Sicherung fehlgeschlagen (tar vorhanden? storage/backups beschreibbar?) – nichts verschoben. Ohne Sicherung: --no-backup');
                return ['changes' => $changes, 'errors' => $errors, 'findings' => []];
            }
        }

        $alt = ROOT . "/storage/backups/public-assets-alt-$ts";
        $mk = fn(string $d): bool => is_dir($d) || mkdir($d, 0775, true);
        $planned = [];
        $newest = function (string $dir): int {
            $t = (int) @filemtime($dir);
            if (is_dir($dir)) foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) $t = max($t, (int) $f->getMTime());
            return $t;
        };

        foreach (self::LEGACY_PREFIXES as $old => $area) {
            $src = "$pub/$old";
            $dst = $pub . '/' . self::AREAS[$area][0];
            if (is_link($src)) {
                $target = (string) readlink($src);
                $abs = str_starts_with($target, '/') ? $target : dirname($src) . '/' . $target;
                $realTarget = realpath($abs);
                if ($realTarget === false || str_starts_with($realTarget . '/', realpath($pub) . '/')) {
                    // Übergangs-Link innerhalb des Webroots (z. B. public/themes → kits) oder ins Leere: entfernen
                    $run("Link public/$old → $target entfernen", fn() => unlink($src));
                } elseif (!file_exists($dst) && !is_link($dst)) {
                    // Geteilter Ordner außerhalb (Releases: shared/public/fonts) – am neuen Ort verlinken
                    $run("Link public/$old → $target: neu als " . self::AREAS[$area][0] . " → $realTarget", fn() => $mk(dirname($dst)) && symlink($realTarget, $dst) && unlink($src));
                } else {
                    $out("  ! public/$old ist ein Link nach $realTarget, " . self::AREAS[$area][0] . ' gibt es schon – bitte von Hand prüfen');
                    $errors++;
                }
                continue;
            }
            if (!is_dir($src)) continue;
            if (!file_exists($dst) && !isset($planned[$dst])) {
                $run("public/$old → public/" . self::AREAS[$area][0], fn() => $mk(dirname($dst)) && rename($src, $dst));
                $planned[$dst] = $src;   // dry-run: zweiter alter Ort (themes nach kits) sieht das Ziel schon
                continue;
            }
            // Ziel gibt es schon (neuer Code ausgerollt oder zweiter alter Ort): je Eintrag
            foreach (scandir($src) ?: [] as $n) {
                if ($n === '.' || $n === '..') continue;
                $from = "$src/$n";
                $to = "$dst/$n";
                $cur = file_exists($to) ? $to : ($dry && isset($planned[$dst]) && file_exists($planned[$dst] . "/$n") ? $planned[$dst] . "/$n" : null);
                if ($cur === null) {
                    $run("public/$old/$n → public/" . self::AREAS[$area][0] . "/$n", fn() => rename($from, $to));
                } elseif (is_dir($from) && is_dir($cur) && $newest($from) > $newest($cur)) {
                    // Die alte Fassung ist neuer (z. B. zuletzt nach public/themes hochgeladen): sie gewinnt
                    $run("public/$old/$n ist neuer als public/" . self::AREAS[$area][0] . "/$n: ersetzt diese (ältere → " . Kit::relative($alt) . ')',
                        fn() => $mk("$alt/" . self::AREAS[$area][0]) && rename($to, "$alt/" . self::AREAS[$area][0] . "/$n") && rename($from, $to));
                } else {
                    $run("public/$old/$n gibt es schon unter public/" . self::AREAS[$area][0] . ' – alte Fassung → ' . Kit::relative($alt),
                        fn() => $mk("$alt/$old") && rename($from, "$alt/$old/$n"));
                }
            }
            $run("leeren Ordner public/$old entfernen", fn() => @rmdir($src));
        }
        if (!$changes) $out('  Nichts zu tun – public/ ist schon umgestellt.');
        return ['changes' => $changes, 'errors' => $errors, 'findings' => self::hardcoded()];
    }

    /**
     * Fest eingetragene alte Adressen in Kits, Erweiterungen und Projekt-Anpassungen (Datei:Zeile – Ausschnitt).
     * Relative Adressen (url(../fonts/…)) und Kit::url()/Theme::asset()/Extension::asset() sind unbedenklich; Zeilen, die
     * PublicPaths nennen (Rückfall für ältere Kerne), werden übergangen; Dateipfade zählen nur mit public/kits|themes|extensions|fonts.
     * @return list<string>
     */
    public static function hardcoded(): array
    {
        $dirs = array_merge(
            array_values(Kit::all()),
            glob(ROOT . '/extensions/*', GLOB_ONLYDIR) ?: [],
            is_dir(ROOT . '/project') ? [ROOT . '/project'] : [],
            glob(self::root() . '/assets/kits/*', GLOB_ONLYDIR) ?: [],
            glob(self::root() . '/assets/ext/*', GLOB_ONLYDIR) ?: [],
        );
        // Adressen: '/kits/name/…', url(/fonts/…), base_path() . '/extensions/' . $name; Dateipfade: ROOT . '/public/extensions/…'
        $re = '~(?<![\w./-])(?:url\(\s*[\'"]?|[\'"`=(]\s*)(?:https?://[^/\s\'"]+)?/(kits|themes|extensions|fonts)/(?:[a-z0-9_\-]+/|[\'"]\s*\.)~i';
        $fs = '~public/(kits|themes|extensions|fonts)\b~';
        $out = [];
        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                fn($f) => !in_array($f->getFilename(), ['node_modules', 'vendor', '.git'], true)));
            foreach ($it as $f) {
                if (!in_array(strtolower($f->getExtension()), ['php', 'css', 'js', 'mjs', 'html', 'json', 'twig', 'svg'], true) || $f->getSize() > 2_000_000) continue;
                foreach (file($f->getPathname()) ?: [] as $i => $line) {
                    // Zeilen, die schon über PublicPaths gehen (Rückfall für ältere Kerne), und Dateipfade (__DIR__, ROOT) zählen nicht
                    $hit = (preg_match($re, $line) && !preg_match('~__DIR__|\bROOT\b|dirname\(~', $line)) || preg_match($fs, $line);
                    if ($hit && !str_contains($line, 'PublicPaths')) $out[] = Kit::relative($f->getPathname()) . ':' . ($i + 1) . '  ' . trim(mb_substr(trim($line), 0, 140));
                }
            }
        }
        return $out;
    }
}
