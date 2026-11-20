<?php
/**
 * NativeCSS im Kit „NativeCSS“ aktualisieren (kits/native/vendor/ncss/) – ohne Studio lauffähig.
 *
 *   php kits/native/tools/ncss-update.php --from=/pfad/zu/nativecss [--ref=v1.2.0] [--dry-run]
 *   php kits/native/tools/ncss-update.php --tag=v1.2.0 [--dry-run]          (Zip von GitHub, skerbis/nativecss)
 *
 * Übernimmt aus dist/ von NativeCSS NUR die Dateien, die das Kit verwendet (FILES – unverändert, Cherry-Picking im Sinne von
 * NativeCSS), dazu LICENSE (→ LICENSE.txt) und die Schriften der Darstellungs-Einstellungen (fonts/, mit OFL-Lizenzen).
 * Schreibt VERSION.txt (Version, Commit, Datum, Quelle) und zeigt vorher eine Übersicht (neu, geändert, entfernt).
 * Eigenes des Kits (assets/, blocks/, templates/) bleibt unberührt. Danach: cd tools && pnpm run build.
 *
 * Neue Komponente verwenden: Pfad in FILES ergänzen, Hülle assets/css/ncss-….css (bzw. assets/js/ncss-….js) anlegen und in
 * theme.php → conditional_css einem Block, einer Variante oder einem Merkmal zuordnen (Theme::markUsed).
 */
declare(strict_types=1);

const NCSS_REPO = 'skerbis/nativecss';

/** Verwendete Dateien aus dist/ (relativ) – Rahmen immer, alles andere nur auf Seiten, die es brauchen (theme.php → conditional_css) */
const NCSS_FILES = [
    // Rahmen (css/site.css): Farben, Tokens, Kopfleiste, Navigation, Off-Canvas, Fußbereich – Reset und Grundstile liefert fluid
    'colors.css', 'tokens.css',
    'components/topbar.css', 'components/nav.css', 'components/off-canvas.css', 'components/dialog-close.css', 'components/footer.css',
    'js/nav.js', 'js/command-fallback.js', 'js/dialog-light-dismiss.js',
    // Blöcke (bei Bedarf)
    'components/hero.css', 'components/split.css', 'components/card.css', 'components/tile.css', 'components/disclosure.css', 'components/badge.css',
    'helpers/reveal.css', 'js/reveal.js',
    'components/count-up.css', 'js/count-up.js',
    'components/marquee.css', 'js/marquee.js',
    'components/scrolly.css', 'js/scrolly.js',
    'components/compare.css', 'js/compare.js',
    'components/video-player.css', 'js/video-player.js',
    'components/audio-player.css', 'js/audio-player.js',
    'components/video-bg.css', 'js/video-bg.js',
    'components/morph.css',
    'helpers/scroll-shadow.css', 'components/table-sort.css', 'js/table-sort.js',
    'components/filter.css', 'js/filter.js',
    'components/timeline.css', 'components/testimonial.css', 'components/pricing.css', 'components/leaders.css',
    // Darstellungs-Knopf für Besucher (Design → „Darstellungs-Knopf für Besucher“)
    'components/preferences.css', 'components/preferences-fonts.css', 'js/preferences.js', 'js/preferences-early.js',
];

/** Schriften der Darstellungs-Einstellungen (Lese-Schriften) samt Lizenz */
const NCSS_FONTS = '~^fonts/[a-z0-9-]+/[A-Za-z0-9._-]+\.(woff2|txt)$~';

$opt = getopt('', ['from:', 'ref:', 'tag:', 'dry-run', 'help']);
$kit = dirname(__DIR__);
$target = $kit . '/vendor/ncss';
$tmp = [];
$fail = static function (string $msg): never {
    fwrite(STDERR, $msg . "\n");
    exit(1);
};
$run = static function (array $cmd) use ($fail): string {
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) $fail('Befehl lässt sich nicht starten: ' . $cmd[0]);
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    if (proc_close($p) !== 0) $fail(trim($err) ?: 'Fehler bei ' . implode(' ', $cmd));
    return trim($out);
};
$git = static function (string $root, array $args) use ($run): string {
    try { return $run(['git', '-C', $root, ...$args]); } catch (\Throwable) { return ''; }
};
$tmpDir = static function () use (&$tmp): string {
    $d = sys_get_temp_dir() . '/ncss-update-' . bin2hex(random_bytes(4));
    mkdir($d, 0o700, true);
    return $tmp[] = $d;
};
$version = static function (string $root): ?string {
    $j = is_file($root . '/package.json') ? json_decode((string) file_get_contents($root . '/package.json'), true) : null;
    $v = is_array($j) ? (string) ($j['version'] ?? '') : '';
    return preg_match('~^\d+\.\d+\.\d+[\w.+-]*$~', $v) ? $v : null;
};
$rmdir = static function (string $dir) use (&$rmdir): void {
    foreach (glob($dir . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $f) is_dir($f) && !is_link($f) ? $rmdir($f) : unlink($f);
    @rmdir($dir);
};
register_shutdown_function(static function () use (&$tmp, $rmdir) { foreach ($tmp as $d) $rmdir($d); });

if (isset($opt['help']) || (!isset($opt['from']) && !isset($opt['tag']))) {
    echo "NativeCSS im Kit „NativeCSS“ aktualisieren\n\n"
        . "  --from=ORDNER   Arbeitskopie von NativeCSS (mit dist/)\n"
        . "  --ref=STAND     mit --from: genau dieser Git-Stand (Tag, Commit) per git archive\n"
        . "  --tag=STAND     Release, Tag oder Branch von GitHub (" . NCSS_REPO . ")\n"
        . "  --dry-run       nur anzeigen, was sich ändert\n";
    exit(isset($opt['help']) ? 0 : 1);
}

// ------------------------------------------------------------------ Quelle
if (isset($opt['from'])) {
    $root = rtrim((string) $opt['from'], '/');
    if (is_file($root . '/ncss.css')) $root = dirname($root);
    $isGit = is_dir($root . '/.git') || is_file($root . '/.git');
    $ref = (string) ($opt['ref'] ?? '');
    if ($ref !== '') {
        if (!$isGit) $fail('--ref braucht eine Git-Arbeitskopie: ' . $root);
        $t = $tmpDir();
        $run(['git', '-C', $root, 'archive', '--format=tar', '-o', $t . '/dist.tar', $ref, 'dist', 'LICENSE', 'package.json']);
        $run(['tar', '-xf', $t . '/dist.tar', '-C', $t]);
        $src = $t;
        $info = ['version' => $version($t) ?? $git($root, ['describe', '--tags', '--always', $ref]), 'commit' => $git($root, ['rev-parse', '--short', $ref]),
            'date' => $git($root, ['log', '-1', '--format=%cs', $ref]), 'source' => $root . ' @ ' . $ref, 'dirty' => false];
    } else {
        $src = $root;
        $info = $isGit
            ? ['version' => $version($root) ?? $git($root, ['describe', '--tags', '--always']), 'commit' => $git($root, ['rev-parse', '--short', 'HEAD']),
                'date' => $git($root, ['log', '-1', '--format=%cs']), 'source' => $root, 'dirty' => $git($root, ['status', '--porcelain', '--', 'dist']) !== '']
            : ['version' => $version($root) ?? 'lokal', 'commit' => '', 'date' => date('Y-m-d'), 'source' => $root, 'dirty' => false];
    }
} else {
    $ref = (string) $opt['tag'];
    if (!preg_match('~^[\w.\-/]+$~', $ref)) $fail('Ungültige Angabe: ' . $ref);
    if (!class_exists(ZipArchive::class)) $fail('PHP-Erweiterung zip fehlt.');
    $t = $tmpDir();
    $url = 'https://codeload.github.com/' . NCSS_REPO . '/zip/' . rawurlencode($ref);
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: ncss-update\r\n"]]));
    if ($data === false || $data === '') $fail('Download fehlgeschlagen: ' . $url);
    file_put_contents($t . '/ncss.zip', $data);
    $zip = new ZipArchive();
    if ($zip->open($t . '/ncss.zip') !== true) $fail('Zip lässt sich nicht öffnen: ' . $url);
    $zip->extractTo($t . '/x');
    $zip->close();
    $top = glob($t . '/x/*', GLOB_ONLYDIR) ?: [];
    if (count($top) !== 1 || !is_dir($top[0] . '/dist')) $fail('Im Archiv fehlt dist/.');
    $src = $top[0];
    $info = ['version' => $version($src) ?? $ref, 'commit' => '', 'date' => date('Y-m-d'), 'source' => 'https://github.com/' . NCSS_REPO . ' (' . $ref . ')', 'dirty' => false];
}
$dist = $src . '/dist';
if (!is_file($dist . '/ncss.css')) $fail('Kein NativeCSS gefunden (dist/ncss.css fehlt): ' . $dist);

// ------------------------------------------------------------------ Plan
$files = [];
foreach (NCSS_FILES as $rel) {
    if (!is_file($dist . '/' . $rel)) $fail('NativeCSS ohne ' . $rel . ' – das Kit verwendet die Datei. Bitte NCSS_FILES und theme.php anpassen.');
    $files[$rel] = $dist . '/' . $rel;
}
$it = is_dir($dist . '/fonts') ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist . '/fonts', FilesystemIterator::SKIP_DOTS)) : [];
foreach ($it as $f) {
    $rel = 'fonts/' . str_replace('\\', '/', substr($f->getPathname(), strlen($dist . '/fonts') + 1));
    if (preg_match(NCSS_FONTS, $rel)) $files[$rel] = $f->getPathname();
}
ksort($files);
$current = [];
if (is_dir($target)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($target) + 1));
        if (!in_array($rel, ['VERSION.txt', 'LICENSE.txt'], true)) $current[$rel] = $f->getPathname();
    }
}
$added = $changed = [];
$same = 0;
foreach ($files as $rel => $from) {
    if (!isset($current[$rel])) $added[] = $rel;
    elseif (sha1_file($from) !== sha1_file($current[$rel])) $changed[] = $rel;
    else $same++;
}
$removed = array_values(array_diff(array_keys($current), array_keys($files)));
sort($removed);

$label = 'NativeCSS ' . ($info['commit'] !== '' && $info['commit'] !== $info['version'] ? $info['version'] . ' ' . $info['commit'] : $info['version'])
    . ' (' . $info['date'] . ')' . ($info['dirty'] ? ' + nicht committete Änderungen' : '');
echo $label . "\nQuelle: " . $info['source'] . "\nZiel:   " . $target . "\n\n";
foreach (['Neu' => $added, 'Geändert' => $changed, 'Entfernt' => $removed] as $k => $list) {
    echo $k . ': ' . count($list) . "\n";
    foreach ($list as $rel) echo '  ' . $rel . "\n";
}
echo 'Unverändert: ' . $same . "\n";
if (isset($opt['dry-run'])) {
    echo "\nNur angezeigt (--dry-run).\n";
    exit(0);
}

// ------------------------------------------------------------------ Schreiben
foreach ($files as $rel => $from) {
    if (!is_dir(dirname($target . '/' . $rel))) mkdir(dirname($target . '/' . $rel), 0o775, true);
    copy($from, $target . '/' . $rel);
}
foreach ($removed as $rel) unlink($target . '/' . $rel);
if (is_file($src . '/LICENSE')) copy($src . '/LICENSE', $target . '/LICENSE.txt');
file_put_contents($target . '/VERSION.txt', $label . ' – https://github.com/' . NCSS_REPO . "\n"
    . 'Übernommen mit kits/native/tools/ncss-update.php am ' . date('Y-m-d') . ' aus ' . $info['source'] . "\n"
    . 'Nur die Dateien, die das Kit verwendet (NCSS_FILES in tools/ncss-update.php), unverändert.' . "\n");
echo "\nFertig. Jetzt bauen: cd tools && pnpm run build\n";
