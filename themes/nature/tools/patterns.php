<?php
/**
 * Erzeugt die Muster des Kits „nature“ als kleine SVG (data:-URI) in den Stylesheets der Design-Optionen:
 *
 *   assets/css/opt-pagebg-grain.css     Papierstruktur (feTurbulence-Rauschen, weich eingeblendet)
 *   assets/css/opt-pagebg-contours.css  Höhenlinien (verschachtelte, gestörte Ringe um drei „Hügel“)
 *   assets/css/opt-pagebg-leaves.css    Blattadern (Kachel mit zwei Blattumrissen und Rippen)
 *   assets/css/opt-dividers-wave.css    Welle als Übergang farbiger Abschnitte
 *   assets/css/opt-dividers-hills.css   Hügel als Übergang farbiger Abschnitte
 *
 * Die Muster sind Masken (mask-image) – Farbe und Deckkraft kommen aus den Tokens, hell wie dunkel.
 * data:-URIs statt Dateien: keine zusätzliche Anfrage, und der Build (esbuild) muss keine SVG auflösen.
 * Deterministisch (fester Zufallsstart): gleiche Ausgabe bei jedem Lauf.
 *
 *   php themes/nature/tools/patterns.php      danach: cd tools && pnpm run build
 */
declare(strict_types=1);

$css = dirname(__DIR__) . '/assets/css';
$uri = fn(string $svg) => '"data:image/svg+xml,' . str_replace(['%', '#', '"', '<', '>', "\n"], ['%25', '%23', "'", '%3C', '%3E', ''], $svg) . '"';
$n = fn(float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');

/** Geschlossene, weiche Kurve durch Punkte (Catmull-Rom → kubische Bézier), relative Koordinaten */
$smooth = function (array $pts, int $dec = 1): string {
    $n = fn(float $v) => $dec === 0 ? (string) (int) round($v) : rtrim(rtrim(number_format($v, $dec, '.', ''), '0'), '.');
    $c = count($pts);
    $d = 'M' . $n($pts[0][0]) . ' ' . $n($pts[0][1]);
    for ($i = 0; $i < $c; $i++) {
        [$p0, $p1, $p2, $p3] = [$pts[($i - 1 + $c) % $c], $pts[$i], $pts[($i + 1) % $c], $pts[($i + 2) % $c]];
        $c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6];
        $c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6];
        $d .= 'c' . $n($c1[0] - $p1[0]) . ' ' . $n($c1[1] - $p1[1]) . ' ' . $n($c2[0] - $p1[0]) . ' ' . $n($c2[1] - $p1[1]) . ' ' . $n($p2[0] - $p1[0]) . ' ' . $n($p2[1] - $p1[1]);
    }
    return $d . 'z';
};

// ------------------------------------------------------------------ Höhenlinien
mt_srand(1911);
$paths = [];
foreach ([[260, 230, 6, 32], [930, 180, 6, 34], [640, 690, 7, 32]] as [$cx, $cy, $rings, $step]) {
    $harm = [];
    for ($h = 2; $h <= 4; $h++) $harm[] = [$h, mt_rand(6, 16) / 100, mt_rand(0, 628) / 100];
    for ($k = 1; $k <= $rings; $k++) {
        $pts = [];
        $r0 = 18 + $k * $step;
        for ($i = 0; $i < 11; $i++) {
            $t = $i / 11 * 2 * M_PI;
            $f = 1;
            foreach ($harm as [$h, $a, $ph]) $f += $a * sin($h * $t + $ph + $k * .12);
            $pts[] = [$cx + cos($t) * $r0 * $f * 1.25, $cy + sin($t) * $r0 * $f * .85];
        }
        $paths[] = $smooth($pts, 0);
    }
}
$contours = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><path fill="none" stroke="#000" stroke-width="1.1" d="' . implode('', $paths) . '"/></svg>';

// ------------------------------------------------------------------ Blattadern (Kachel 220 × 220)
$leaf = function (float $x, float $y, float $len, float $rot) use ($n): string {
    $r = deg2rad($rot);
    $p = fn(float $u, float $v) => [$x + cos($r) * $u - sin($r) * $v, $y + sin($r) * $u + cos($r) * $v];
    [$a, $b] = [$p(0, 0), $p($len, 0)];
    [$c1, $c2, $c3, $c4] = [$p($len * .3, -$len * .38), $p($len * .75, -$len * .3), $p($len * .75, $len * .3), $p($len * .3, $len * .38)];
    $d = 'M' . $n($a[0]) . ' ' . $n($a[1]) . 'C' . $n($c1[0]) . ' ' . $n($c1[1]) . ' ' . $n($c2[0]) . ' ' . $n($c2[1]) . ' ' . $n($b[0]) . ' ' . $n($b[1])
        . 'C' . $n($c3[0]) . ' ' . $n($c3[1]) . ' ' . $n($c4[0]) . ' ' . $n($c4[1]) . ' ' . $n($a[0]) . ' ' . $n($a[1]) . 'Z'
        . 'M' . $n($a[0]) . ' ' . $n($a[1]) . 'L' . $n($b[0]) . ' ' . $n($b[1]);
    foreach ([.25, .45, .65] as $u) {
        foreach ([-1, 1] as $s) {
            [$q, $w] = [$p($len * $u, 0), $p($len * ($u + .14), $s * $len * .2)];
            $d .= 'M' . $n($q[0]) . ' ' . $n($q[1]) . 'L' . $n($w[0]) . ' ' . $n($w[1]);
        }
    }
    return $d;
};
$veins = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 220"><path fill="none" stroke="#000" stroke-width="1" stroke-linecap="round" d="'
    . $leaf(30, 70, 70, -28) . $leaf(128, 170, 62, 40) . $leaf(150, 36, 46, 110) . '"/></svg>';

// ------------------------------------------------------------------ Papierstruktur
$grain = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="3" stitchTiles="stitch"/>'
    . '<feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 -1.6 1.25"/></filter><rect width="100%" height="100%" filter="url(#n)"/></svg>';

// ------------------------------------------------------------------ Übergänge (Masken, gestreckt)
$wave = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 40" preserveAspectRatio="none"><path d="M0 40V22C140 4 300 2 460 16S760 38 920 24S1110 6 1200 14V40Z"/></svg>';
$hills = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 40" preserveAspectRatio="none"><path d="M0 40V30C90 30 150 6 300 6S470 30 560 30S700 12 820 12S980 34 1080 34S1160 24 1200 22V40Z"/></svg>';

$head = fn(string $what) => "/* Kit „nature“ – $what. Erzeugt mit tools/patterns.php (nicht von Hand ändern). */\n";

// Seitenhintergründe: eine feste Ebene hinter dem Inhalt (z-index -1 im Wurzel-Stapelkontext) – farbige Abschnitte
// decken sie ab. Mit „Treiben“ wandert die Ebene sehr langsam (nur transform, GPU; nie bei „Bewegung reduzieren“).
$layer = 'body::before{content:"";position:fixed;inset:-4rem;z-index:-1;pointer-events:none;';
file_put_contents("$css/opt-pagebg-grain.css", $head('Seitenhintergrund „Papierstruktur“')
    . $layer . 'background:var(--n-ink);opacity:.07;mask:url(' . $uri($grain) . ') 0 0/220px 220px}' . "\n"
    . '.is-darkbase body::before{opacity:.12}@media (prefers-color-scheme:dark){.has-dark body::before{background:#000;opacity:.35}}' . "\n");
file_put_contents("$css/opt-pagebg-contours.css", $head('Seitenhintergrund „Höhenlinien“')
    . $layer . 'background:var(--n-a);opacity:.13;mask:url(' . $uri($contours) . ') 50% 0/max(100%,75rem) auto no-repeat}' . "\n"
    . "@media (prefers-reduced-motion:no-preference){.has-drift body::before{animation:n-float 90s ease-in-out infinite alternate}}\n"
    . "@keyframes n-float{to{translate:-2.5rem 1.5rem}}\n");
file_put_contents("$css/opt-pagebg-leaves.css", $head('Seitenhintergrund „Blattadern“')
    . $layer . 'background:var(--n-a);opacity:.1;mask:url(' . $uri($veins) . ') 0 0/220px 220px}' . "\n"
    . "@media (prefers-reduced-motion:no-preference){.has-drift body::before{animation:n-float 120s linear infinite alternate}}\n"
    . "@keyframes n-float{to{translate:3rem 2rem}}\n");

// Übergänge: farbige Abschnitte (Sand, Moos hell, Akzent, Waldnacht) erhalten oben und unten eine Kante in ihrer Farbe.
// Gleiche Farben hintereinander: keine Kante. Abschnitte mit Hintergrundbild/Vollbild bleiben gerade.
$dv = function (string $svg, string $h) use ($uri): string {
    $sel = ':is(.bg-muted,.bg-tint,.bg-accent,.bg-dark):not(.sec--has-bg,.sec--screen)';
    return $sel . '{--n-dv:url(' . $uri($svg) . ')}' . "\n"
        . $sel . '::before,' . $sel . '::after{content:"";position:absolute;left:0;right:0;height:' . $h . ';background:var(--n-sec-bg);mask:var(--n-dv) 0 0/100% 100% no-repeat;pointer-events:none}' . "\n"
        . $sel . '::before{bottom:calc(100% - 1px)}' . "\n"
        . $sel . '::after{top:calc(100% - 1px);scale:-1 -1}' . "\n"
        . '.bg-muted+.bg-muted::before,.bg-tint+.bg-tint::before,.bg-dark+.bg-dark::before,.bg-accent+.bg-accent::before{display:none}' . "\n"
        . ':is(.bg-muted,.bg-tint,.bg-accent,.bg-dark):has(+.ftr)::after,:is(.bg-muted,.bg-tint,.bg-accent,.bg-dark):last-child::after{display:none}' . "\n";
};
file_put_contents("$css/opt-dividers-wave.css", $head('Übergang „Welle“') . $dv($wave, 'clamp(1rem,3vw,2.25rem)'));
file_put_contents("$css/opt-dividers-hills.css", $head('Übergang „Hügel“') . $dv($hills, 'clamp(1.25rem,3.5vw,2.75rem)'));

foreach (['grain', 'contours', 'leaves'] as $k) printf("opt-pagebg-%-9s %5.1f KB\n", $k, filesize("$css/opt-pagebg-$k.css") / 1024);
foreach (['wave', 'hills'] as $k) printf("opt-dividers-%-7s %5.1f KB\n", $k, filesize("$css/opt-dividers-$k.css") / 1024);
